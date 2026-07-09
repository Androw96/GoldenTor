import tempfile
import unittest
from datetime import date, datetime, timedelta
from pathlib import Path

import server


class FakeHandler:
    def __init__(self):
        self.status = None
        self.payload = None

    def json_response(self, payload, status=200):
        self.status = status
        self.payload = payload


class GoldenTorBackendTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        server.DATA_DIR = Path(self.temp.name)
        server.DB_PATH = server.DATA_DIR / "test.sqlite3"
        server.OUTBOX_PATH = server.DATA_DIR / "outbox.log"
        server.PUBLIC_BASE_URL = "http://127.0.0.1:4174"
        server.RATE_LIMIT.clear()
        server.init_db()

    def tearDown(self):
        self.temp.cleanup()

    def next_weekday(self):
        day = date.today() + timedelta(days=1)
        while day.weekday() >= 5:
            day += timedelta(days=1)
        return day.isoformat()

    def test_contact_is_saved_and_email_is_queued(self):
        handler = FakeHandler()
        server.GoldenTorHandler.create_contact(handler, {
            "name": "Teszt Elek", "email": "teszt@example.com", "phone": "", "topic": "Befektetés", "message": "Teszt üzenet"
        })
        self.assertEqual(handler.status, 201)
        with server.db() as connection:
            row = connection.execute("SELECT * FROM contacts").fetchone()
        self.assertEqual(row["email"], "teszt@example.com")
        self.assertTrue(server.OUTBOX_PATH.exists())

    def test_booking_portal_and_cancellation(self):
        handler = FakeHandler()
        booking_date = self.next_weekday()
        server.GoldenTorHandler.create_booking(handler, {
            "name": "Minta Anna", "email": "anna@example.com", "phone": "", "topic": "Ingatlan",
            "meeting": "Online konzultáció", "date": booking_date, "time": "11:00", "message": ""
        })
        self.assertEqual(handler.status, 201)
        token = handler.payload["token"]
        with server.db() as connection:
            row = connection.execute("SELECT * FROM bookings WHERE public_token=?", (token,)).fetchone()
        self.assertEqual(row["status"], "requested")
        cancel_handler = FakeHandler()
        server.GoldenTorHandler.cancel_booking(cancel_handler, {"token": token})
        self.assertEqual(cancel_handler.payload["status"], "cancelled")

    def test_invalid_email_is_rejected(self):
        handler = FakeHandler()
        with self.assertRaises(ValueError):
            server.GoldenTorHandler.create_contact(handler, {
                "name": "Teszt", "email": "hibas", "topic": "Biztosítás", "message": "Teszt"
            })

    def test_admin_stats_and_reminder(self):
        appointment = datetime.now().replace(second=0, microsecond=0) + timedelta(days=1)
        token = "reminder-token"
        with server.db() as connection:
            connection.execute(
                "INSERT INTO bookings(public_token,created_at,name,email,topic,meeting,booking_date,booking_time,status) VALUES(?,?,?,?,?,?,?,?,?)",
                (token, server.utc_now(), "Emlékeztető Elek", "reminder@example.com", "Befektetés", "Online", appointment.date().isoformat(), appointment.strftime("%H:%M"), "confirmed"),
            )
        sent = server.send_due_reminders(appointment - timedelta(days=1))
        self.assertEqual(sent, 1)
        with server.db() as connection:
            row = connection.execute("SELECT reminder_sent_at FROM bookings WHERE public_token=?", (token,)).fetchone()
        self.assertIsNotNone(row["reminder_sent_at"])
        handler = FakeHandler()
        server.GoldenTorHandler.get_admin_stats(handler)
        self.assertEqual(handler.payload["bookings"], 1)
        self.assertEqual(handler.payload["upcoming"], 1)


if __name__ == "__main__":
    unittest.main()
