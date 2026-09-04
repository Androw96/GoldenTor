#!/usr/bin/env python3
from __future__ import annotations

import base64
import hashlib
import json
import os
import secrets
import smtplib
import sqlite3
import threading
import time
from datetime import date, datetime, timedelta, timezone
from email.message import EmailMessage
from http import HTTPStatus
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import parse_qs, urlparse

BASE_DIR = Path(__file__).resolve().parent
DATA_DIR = BASE_DIR / "data"
DB_PATH = DATA_DIR / "goldentor.sqlite3"
OUTBOX_PATH = DATA_DIR / "outbox.log"
BACKUP_DIR = DATA_DIR / "backups"
ALLOWED_SLOTS = {"09:00", "11:00", "14:00", "16:00"}
RATE_LIMIT: dict[str, list[float]] = {}
RATE_LOCK = threading.Lock()


def load_env() -> None:
    env_file = BASE_DIR / ".env"
    if not env_file.exists():
        return
    for raw in env_file.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        os.environ.setdefault(key.strip(), value.strip().strip('"').strip("'"))


load_env()
HOST = os.getenv("GOLDENTOR_HOST", "127.0.0.1")
PORT = int(os.getenv("GOLDENTOR_PORT", "4174"))
ADMIN_USER = os.getenv("GOLDENTOR_ADMIN_USER", "admin")
ADMIN_PASSWORD = os.getenv("GOLDENTOR_ADMIN_PASSWORD", "change-me-local")
ADMIN_KEY = os.getenv("GOLDENTOR_ADMIN_KEY") or secrets.token_urlsafe(24)
PUBLIC_BASE_URL = os.getenv("GOLDENTOR_PUBLIC_URL", f"http://{HOST}:{PORT}").rstrip("/")


def db() -> sqlite3.Connection:
    connection = sqlite3.connect(DB_PATH)
    connection.row_factory = sqlite3.Row
    connection.execute("PRAGMA foreign_keys = ON")
    return connection


def init_db() -> None:
    DATA_DIR.mkdir(exist_ok=True)
    with db() as connection:
        connection.executescript(
            """
            CREATE TABLE IF NOT EXISTS contacts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                created_at TEXT NOT NULL,
                name TEXT NOT NULL,
                email TEXT NOT NULL,
                phone TEXT NOT NULL DEFAULT '',
                topic TEXT NOT NULL,
                message TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'new'
            );

            CREATE TABLE IF NOT EXISTS bookings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                public_token TEXT NOT NULL UNIQUE,
                created_at TEXT NOT NULL,
                name TEXT NOT NULL,
                email TEXT NOT NULL,
                phone TEXT NOT NULL DEFAULT '',
                topic TEXT NOT NULL,
                meeting TEXT NOT NULL,
                booking_date TEXT NOT NULL,
                booking_time TEXT NOT NULL,
                message TEXT NOT NULL DEFAULT '',
                status TEXT NOT NULL DEFAULT 'requested'
            );

            CREATE UNIQUE INDEX IF NOT EXISTS unique_active_booking_slot
            ON bookings(booking_date, booking_time)
            WHERE status != 'cancelled';

            CREATE TABLE IF NOT EXISTS content (
                content_key TEXT NOT NULL,
                language TEXT NOT NULL,
                title TEXT NOT NULL,
                payload TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                PRIMARY KEY(content_key, language)
            );
            """
        )
        booking_columns = {row["name"] for row in connection.execute("PRAGMA table_info(bookings)")}
        if "reminder_sent_at" not in booking_columns:
            connection.execute("ALTER TABLE bookings ADD COLUMN reminder_sent_at TEXT")


def utc_now() -> str:
    return datetime.now(timezone.utc).isoformat(timespec="seconds")


def clean_text(value: object, limit: int, required: bool = True) -> str:
    text = " ".join(str(value or "").strip().split())
    if required and not text:
        raise ValueError("Kötelező mező hiányzik.")
    if len(text) > limit:
        raise ValueError("Az egyik mező túl hosszú.")
    return text


def valid_email(value: str) -> bool:
    return "@" in value and "." in value.rsplit("@", 1)[-1] and len(value) <= 254


def is_rate_limited(ip: str) -> bool:
    now = time.time()
    with RATE_LOCK:
        entries = [stamp for stamp in RATE_LIMIT.get(ip, []) if now - stamp < 600]
        if len(entries) >= 12:
            RATE_LIMIT[ip] = entries
            return True
        entries.append(now)
        RATE_LIMIT[ip] = entries
        return False


def send_email(subject: str, body: str, recipient: str) -> None:
    smtp_host = os.getenv("SMTP_HOST")
    smtp_user = os.getenv("SMTP_USER")
    smtp_password = os.getenv("SMTP_PASSWORD")
    sender = os.getenv("SMTP_FROM", smtp_user or "info@goldentor.hu")
    if not smtp_host or not smtp_user or not smtp_password:
        DATA_DIR.mkdir(exist_ok=True)
        with OUTBOX_PATH.open("a", encoding="utf-8") as handle:
            handle.write(json.dumps({"at": utc_now(), "to": recipient, "subject": subject, "body": body}, ensure_ascii=False) + "\n")
        return
    message = EmailMessage()
    message["From"] = sender
    message["To"] = recipient
    message["Subject"] = subject
    message.set_content(body)
    port = int(os.getenv("SMTP_PORT", "587"))
    with smtplib.SMTP(smtp_host, port, timeout=20) as smtp:
        smtp.starttls()
        smtp.login(smtp_user, smtp_password)
        smtp.send_message(message)


def row_dict(row: sqlite3.Row) -> dict:
    return {key: row[key] for key in row.keys()}


def booking_portal_url(token: str) -> str:
    return f"{PUBLIC_BASE_URL}/ugyfelportal.html?token={token}"


def send_booking_status_email(booking: sqlite3.Row, status: str) -> None:
    labels = {
        "confirmed": ("Időpontja visszaigazolva", "visszaigazoltuk"),
        "completed": ("Konzultáció lezárva", "lezártnak jelöltük"),
        "cancelled": ("Időpont lemondva", "lemondottnak jelöltük"),
    }
    if status not in labels:
        return
    subject, action = labels[status]
    portal = booking_portal_url(booking["public_token"])
    send_email(
        f"Golden Tor - {subject}",
        f"Kedves {booking['name']}!\n\nA {booking['booking_date']} {booking['booking_time']} időpontra szóló konzultációját {action}.\n\nRészletek és naptár: {portal}",
        booking["email"],
    )


def send_due_reminders(now: datetime | None = None) -> int:
    current = now or datetime.now()
    window_start = current + timedelta(hours=23, minutes=30)
    window_end = current + timedelta(hours=24, minutes=30)
    sent = 0
    with db() as connection:
        rows = connection.execute(
            "SELECT * FROM bookings WHERE status='confirmed' AND reminder_sent_at IS NULL"
        ).fetchall()
        for booking in rows:
            appointment = datetime.fromisoformat(f"{booking['booking_date']}T{booking['booking_time']}:00")
            if not window_start <= appointment <= window_end:
                continue
            portal = booking_portal_url(booking["public_token"])
            send_email(
                "Golden Tor - emlékeztető a holnapi konzultációra",
                f"Kedves {booking['name']}!\n\nEmlékeztetjük, hogy konzultációja holnap, {booking['booking_date']} {booking['booking_time']} időpontban lesz.\nMód: {booking['meeting']}\nTéma: {booking['topic']}\n\nRészletek: {portal}",
                booking["email"],
            )
            connection.execute("UPDATE bookings SET reminder_sent_at=? WHERE id=?", (utc_now(), booking["id"]))
            sent += 1
    return sent


def reminder_worker(stop_event: threading.Event) -> None:
    while not stop_event.wait(300):
        try:
            send_due_reminders()
        except Exception as exc:
            print(f"Emlékeztető hiba: {exc}")


def backup_database() -> Path:
    BACKUP_DIR.mkdir(parents=True, exist_ok=True)
    target = BACKUP_DIR / f"goldentor-{datetime.now().strftime('%Y%m%d-%H%M%S')}.sqlite3"
    source = db()
    destination = sqlite3.connect(target)
    try:
        source.backup(destination)
    finally:
        destination.close()
        source.close()
    backups = sorted(BACKUP_DIR.glob("goldentor-*.sqlite3"), reverse=True)
    for old_backup in backups[14:]:
        old_backup.unlink(missing_ok=True)
    return target


def backup_worker(stop_event: threading.Event) -> None:
    while True:
        try:
            backup_database()
        except Exception as exc:
            print(f"Adatbázismentési hiba: {exc}")
        if stop_event.wait(86_400):
            return


class GoldenTorHandler(SimpleHTTPRequestHandler):
    server_version = "GoldenTor/1.0"

    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=str(BASE_DIR), **kwargs)

    def end_headers(self) -> None:
        self.send_header("Cache-Control", "no-store, no-cache, must-revalidate, max-age=0")
        self.send_header("Pragma", "no-cache")
        self.send_header("Expires", "0")
        self.send_header("X-Content-Type-Options", "nosniff")
        self.send_header("X-Frame-Options", "SAMEORIGIN")
        self.send_header("Referrer-Policy", "strict-origin-when-cross-origin")
        self.send_header("Permissions-Policy", "camera=(), microphone=(), geolocation=()")
        self.send_header("Content-Security-Policy", "default-src 'self'; img-src 'self' data: https://www.google.com https://maps.gstatic.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; script-src 'self' 'unsafe-inline'; frame-src https://www.google.com; connect-src 'self'")
        super().end_headers()

    def log_message(self, fmt: str, *args) -> None:
        print(f"[{self.log_date_time_string()}] {self.address_string()} {fmt % args}")

    def json_response(self, payload: object, status: int = 200) -> None:
        data = json.dumps(payload, ensure_ascii=False).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(data)))
        self.end_headers()
        self.wfile.write(data)

    def read_json(self) -> dict:
        length = int(self.headers.get("Content-Length", "0"))
        if length <= 0 or length > 64_000:
            raise ValueError("Érvénytelen kérésméret.")
        try:
            return json.loads(self.rfile.read(length))
        except json.JSONDecodeError as exc:
            raise ValueError("Érvénytelen JSON kérés.") from exc

    def admin_authorized(self) -> bool:
        key_header = self.headers.get("X-Admin-Key", "")
        if key_header and secrets.compare_digest(key_header, ADMIN_KEY):
            return True
        if ADMIN_PASSWORD == "change-me-local":
            return False
        header = self.headers.get("Authorization", "")
        if not header.startswith("Basic "):
            return False
        try:
            decoded = base64.b64decode(header[6:]).decode("utf-8")
            username, password = decoded.split(":", 1)
        except (ValueError, UnicodeDecodeError):
            return False
        user_ok = secrets.compare_digest(username, ADMIN_USER)
        password_ok = secrets.compare_digest(password, ADMIN_PASSWORD)
        return user_ok and password_ok

    def require_admin(self) -> bool:
        if self.admin_authorized():
            return True
        self.send_response(HTTPStatus.UNAUTHORIZED)
        self.send_header("WWW-Authenticate", 'Basic realm="Golden Tor Admin"')
        self.end_headers()
        return False

    def do_GET(self) -> None:
        parsed = urlparse(self.path)
        path = parsed.path
        query = parse_qs(parsed.query)
        if path == "/api/health":
            self.json_response({"ok": True, "time": utc_now()})
            return
        if path == "/api/availability":
            self.get_availability(query)
            return
        if path == "/api/booking":
            self.get_booking(query)
            return
        if path == "/api/calendar.ics":
            self.get_calendar(query)
            return
        if path == "/api/admin/bookings":
            if self.require_admin():
                self.get_admin_rows("bookings")
            return
        if path == "/api/admin/contacts":
            if self.require_admin():
                self.get_admin_rows("contacts")
            return
        if path == "/api/admin/stats":
            if self.require_admin():
                self.get_admin_stats()
            return
        if path.startswith("/api/content/"):
            self.get_content(path.removeprefix("/api/content/"), query)
            return
        super().do_GET()

    def do_POST(self) -> None:
        if self.path not in {"/api/contact", "/api/bookings", "/api/booking/cancel"}:
            self.json_response({"error": "Ismeretlen végpont."}, HTTPStatus.NOT_FOUND)
            return
        if is_rate_limited(self.client_address[0]):
            self.json_response({"error": "Túl sok kérés. Próbálja később."}, HTTPStatus.TOO_MANY_REQUESTS)
            return
        try:
            payload = self.read_json()
            if payload.get("website"):
                self.json_response({"ok": True})
                return
            if self.path == "/api/contact":
                self.create_contact(payload)
            elif self.path == "/api/bookings":
                self.create_booking(payload)
            else:
                self.cancel_booking(payload)
        except ValueError as exc:
            self.json_response({"error": str(exc)}, HTTPStatus.BAD_REQUEST)
        except sqlite3.IntegrityError:
            self.json_response({"error": "Ez az időpont időközben foglalttá vált. Kérjük, válasszon másikat."}, HTTPStatus.CONFLICT)

    def do_PATCH(self) -> None:
        if not self.path.startswith(("/api/admin/bookings/", "/api/admin/contacts/")) or not self.require_admin():
            return
        try:
            payload = self.read_json()
            item_id = int(self.path.rsplit("/", 1)[-1])
            if self.path.startswith("/api/admin/bookings/"):
                status = clean_text(payload.get("status"), 24)
                if status not in {"requested", "confirmed", "completed", "cancelled"}:
                    raise ValueError("Érvénytelen státusz.")
                with db() as connection:
                    booking = connection.execute("SELECT * FROM bookings WHERE id=?", (item_id,)).fetchone()
                    if not booking:
                        raise ValueError("A foglalás nem található.")
                    connection.execute("UPDATE bookings SET status=? WHERE id=?", (status, item_id))
                if status != booking["status"]:
                    send_booking_status_email(booking, status)
            else:
                status = clean_text(payload.get("status"), 24)
                if status not in {"new", "in_progress", "done", "archived"}:
                    raise ValueError("Érvénytelen státusz.")
                with db() as connection:
                    cursor = connection.execute("UPDATE contacts SET status=? WHERE id=?", (status, item_id))
                if not cursor.rowcount:
                    raise ValueError("A megkeresés nem található.")
            self.json_response({"ok": True})
        except (ValueError, sqlite3.Error) as exc:
            self.json_response({"error": str(exc)}, HTTPStatus.BAD_REQUEST)

    def do_PUT(self) -> None:
        if not self.path.startswith("/api/admin/content/") or not self.require_admin():
            return
        try:
            segments = self.path.strip("/").split("/")
            if len(segments) != 5:
                raise ValueError("Érvénytelen tartalmi útvonal.")
            content_key, language = segments[3], segments[4]
            payload = self.read_json()
            title = clean_text(payload.get("title"), 180)
            content_payload = payload.get("payload", {})
            if not isinstance(content_payload, (dict, list)):
                raise ValueError("A tartalom formátuma hibás.")
            with db() as connection:
                connection.execute(
                    "INSERT INTO content(content_key,language,title,payload,updated_at) VALUES(?,?,?,?,?) ON CONFLICT(content_key,language) DO UPDATE SET title=excluded.title,payload=excluded.payload,updated_at=excluded.updated_at",
                    (content_key, language, title, json.dumps(content_payload, ensure_ascii=False), utc_now()),
                )
            self.json_response({"ok": True})
        except (ValueError, sqlite3.Error) as exc:
            self.json_response({"error": str(exc)}, HTTPStatus.BAD_REQUEST)

    def create_contact(self, payload: dict) -> None:
        name = clean_text(payload.get("name"), 120)
        email = clean_text(payload.get("email"), 254).lower()
        if not valid_email(email):
            raise ValueError("Érvénytelen e-mail-cím.")
        phone = clean_text(payload.get("phone"), 60, required=False)
        topic = clean_text(payload.get("topic"), 120)
        message = clean_text(payload.get("message"), 2000)
        with db() as connection:
            cursor = connection.execute(
                "INSERT INTO contacts(created_at,name,email,phone,topic,message) VALUES(?,?,?,?,?,?)",
                (utc_now(), name, email, phone, topic, message),
            )
        body = f"Új kapcsolatfelvétel\n\nNév: {name}\nE-mail: {email}\nTelefon: {phone or '-'}\nTéma: {topic}\n\n{message}"
        send_email("Golden Tor - új kapcsolatfelvétel", body, os.getenv("CONTACT_RECIPIENT", "info@goldentor.hu"))
        self.json_response({"ok": True, "id": cursor.lastrowid}, HTTPStatus.CREATED)

    def create_booking(self, payload: dict) -> None:
        name = clean_text(payload.get("name"), 120)
        email = clean_text(payload.get("email"), 254).lower()
        if not valid_email(email):
            raise ValueError("Érvénytelen e-mail-cím.")
        phone = clean_text(payload.get("phone"), 60, required=False)
        topic = clean_text(payload.get("topic"), 120)
        meeting = clean_text(payload.get("meeting"), 120)
        booking_date = clean_text(payload.get("date"), 10)
        booking_time = clean_text(payload.get("time"), 5)
        message = clean_text(payload.get("message"), 2000, required=False)
        try:
            selected_date = date.fromisoformat(booking_date)
        except ValueError as exc:
            raise ValueError("Érvénytelen dátum.") from exc
        if selected_date <= date.today() or selected_date.weekday() >= 5:
            raise ValueError("Kérjük, válasszon egy jövőbeli munkanapot.")
        if booking_time not in ALLOWED_SLOTS:
            raise ValueError("Érvénytelen időpont.")
        token = secrets.token_urlsafe(24)
        with db() as connection:
            cursor = connection.execute(
                "INSERT INTO bookings(public_token,created_at,name,email,phone,topic,meeting,booking_date,booking_time,message) VALUES(?,?,?,?,?,?,?,?,?,?)",
                (token, utc_now(), name, email, phone, topic, meeting, booking_date, booking_time, message),
            )
        portal = booking_portal_url(token)
        body = f"Időpontkérés érkezett\n\nNév: {name}\nE-mail: {email}\nTelefon: {phone or '-'}\nTéma: {topic}\nMód: {meeting}\nIdőpont: {booking_date} {booking_time}\n\n{message}\n\nÜgyfélportál: {portal}"
        send_email("Golden Tor - új időpontkérés", body, os.getenv("CONTACT_RECIPIENT", "info@goldentor.hu"))
        send_email("Golden Tor - időpontkérés fogadva", f"Kedves {name}!\n\nFogadtuk időpontkérését: {booking_date} {booking_time}. A foglalás visszaigazolás után válik véglegessé.\n\nÁllapot és naptár: {portal}", email)
        self.json_response({"ok": True, "id": cursor.lastrowid, "token": token, "portalUrl": portal, "calendarUrl": f"/api/calendar.ics?token={token}"}, HTTPStatus.CREATED)

    def cancel_booking(self, payload: dict) -> None:
        token = clean_text(payload.get("token"), 100)
        with db() as connection:
            cursor = connection.execute("UPDATE bookings SET status='cancelled' WHERE public_token=? AND status IN ('requested','confirmed')", (token,))
        if not cursor.rowcount:
            self.json_response({"error": "A foglalás nem található vagy már nem mondható le."}, HTTPStatus.NOT_FOUND)
            return
        self.json_response({"ok": True, "status": "cancelled"})

    def get_availability(self, query: dict) -> None:
        selected = query.get("date", [""])[0]
        try:
            selected_date = date.fromisoformat(selected)
        except ValueError:
            self.json_response({"error": "Érvénytelen dátum."}, HTTPStatus.BAD_REQUEST)
            return
        if selected_date <= date.today() or selected_date.weekday() >= 5:
            self.json_response({"slots": []})
            return
        with db() as connection:
            rows = connection.execute("SELECT booking_time FROM bookings WHERE booking_date=? AND status!='cancelled'", (selected,)).fetchall()
        busy = {row["booking_time"] for row in rows}
        self.json_response({"date": selected, "slots": [{"time": slot, "available": slot not in busy} for slot in sorted(ALLOWED_SLOTS)]})

    def get_booking(self, query: dict) -> None:
        token = query.get("token", [""])[0]
        if not token:
            self.json_response({"error": "Hiányzó token."}, HTTPStatus.BAD_REQUEST)
            return
        with db() as connection:
            row = connection.execute("SELECT id,created_at,name,topic,meeting,booking_date,booking_time,status FROM bookings WHERE public_token=?", (token,)).fetchone()
        if not row:
            self.json_response({"error": "A foglalás nem található."}, HTTPStatus.NOT_FOUND)
            return
        self.json_response(row_dict(row))

    def get_calendar(self, query: dict) -> None:
        token = query.get("token", [""])[0]
        with db() as connection:
            row = connection.execute("SELECT * FROM bookings WHERE public_token=?", (token,)).fetchone()
        if not row:
            self.json_response({"error": "A foglalás nem található."}, HTTPStatus.NOT_FOUND)
            return
        start = datetime.fromisoformat(f"{row['booking_date']}T{row['booking_time']}:00")
        end = start + timedelta(minutes=45)
        stamp = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
        ics = "\r\n".join([
            "BEGIN:VCALENDAR", "VERSION:2.0", "PRODID:-//Golden Tor Consulting//HU", "BEGIN:VEVENT",
            f"UID:{hashlib.sha256(token.encode()).hexdigest()[:24]}@goldentor.hu", f"DTSTAMP:{stamp}",
            f"DTSTART:{start.strftime('%Y%m%dT%H%M%S')}", f"DTEND:{end.strftime('%Y%m%dT%H%M%S')}",
            f"SUMMARY:Golden Tor konzultáció - {row['topic']}", f"DESCRIPTION:{row['meeting']}",
            "LOCATION:Golden Tor Consulting / egyeztetés szerint", "END:VEVENT", "END:VCALENDAR", ""
        ]).encode("utf-8")
        self.send_response(200)
        self.send_header("Content-Type", "text/calendar; charset=utf-8")
        self.send_header("Content-Disposition", 'attachment; filename="golden-tor-konzultacio.ics"')
        self.send_header("Content-Length", str(len(ics)))
        self.end_headers()
        self.wfile.write(ics)

    def get_admin_rows(self, table: str) -> None:
        with db() as connection:
            rows = connection.execute(f"SELECT * FROM {table} ORDER BY id DESC LIMIT 250").fetchall()
        self.json_response({"items": [row_dict(row) for row in rows]})

    def get_admin_stats(self) -> None:
        today = date.today().isoformat()
        month_start = date.today().replace(day=1).isoformat()
        with db() as connection:
            bookings = connection.execute(
                "SELECT COUNT(*) total, SUM(status='confirmed') confirmed, SUM(status='completed') completed FROM bookings"
            ).fetchone()
            contacts = connection.execute("SELECT COUNT(*) total FROM contacts").fetchone()
            upcoming = connection.execute(
                "SELECT COUNT(*) total FROM bookings WHERE booking_date>=? AND status IN ('requested','confirmed')", (today,)
            ).fetchone()
            monthly = connection.execute(
                "SELECT COUNT(*) total FROM bookings WHERE created_at>=?", (month_start,)
            ).fetchone()
            topic_rows = connection.execute(
                "SELECT topic, COUNT(*) count FROM bookings GROUP BY topic ORDER BY count DESC LIMIT 5"
            ).fetchall()
        total = bookings["total"] or 0
        confirmed = (bookings["confirmed"] or 0) + (bookings["completed"] or 0)
        self.json_response({
            "bookings": total,
            "contacts": contacts["total"] or 0,
            "upcoming": upcoming["total"] or 0,
            "monthly": monthly["total"] or 0,
            "conversion": round(confirmed / total * 100) if total else 0,
            "topics": [row_dict(row) for row in topic_rows],
        })

    def get_content(self, content_key: str, query: dict) -> None:
        language = query.get("lang", ["hu"])[0]
        with db() as connection:
            row = connection.execute("SELECT * FROM content WHERE content_key=? AND language=?", (content_key, language)).fetchone()
        if not row:
            self.json_response({"error": "A tartalom nem található."}, HTTPStatus.NOT_FOUND)
            return
        payload = row_dict(row)
        payload["payload"] = json.loads(payload["payload"])
        self.json_response(payload)


def run() -> None:
    init_db()
    server = ThreadingHTTPServer((HOST, PORT), GoldenTorHandler)
    stop_event = threading.Event()
    reminder_thread = threading.Thread(target=reminder_worker, args=(stop_event,), daemon=True)
    reminder_thread.start()
    backup_thread = threading.Thread(target=backup_worker, args=(stop_event,), daemon=True)
    backup_thread.start()
    print(f"Golden Tor server: http://{HOST}:{PORT}")
    if ADMIN_PASSWORD == "change-me-local":
        print("FIGYELEM: az admin jelszó alapértelmezett; élesítés előtt állítsa be a GOLDENTOR_ADMIN_PASSWORD értékét.")
    if not os.getenv("GOLDENTOR_ADMIN_KEY"):
        print(f"IDEIGLENES HELYI ADMIN KÓD: {ADMIN_KEY}")
        print("Élesítés előtt állítsa be a GOLDENTOR_ADMIN_KEY értékét az .env fájlban.")
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        pass
    finally:
        stop_event.set()
        server.server_close()


if __name__ == "__main__":
    run()
