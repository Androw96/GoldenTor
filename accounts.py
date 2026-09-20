"""SQLite-backed customer accounts and short-lived, server-validated sessions."""
import hashlib
import json
import math
import secrets
import sqlite3
import time
from http.cookies import SimpleCookie
from urllib.parse import urlparse

SESSION_SECONDS = 8 * 60 * 60


def init_accounts(connection):
    connection.executescript('''
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY, email TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL, phone TEXT NOT NULL DEFAULT '',
            password_hash TEXT NOT NULL, salt TEXT NOT NULL, created_at TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS sessions (
            token_hash TEXT PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            expires_at REAL NOT NULL
        );
        CREATE TABLE IF NOT EXISTS feedback (
            id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES users(id),
            expert TEXT NOT NULL, rating INTEGER NOT NULL, message TEXT NOT NULL, created_at TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS calculations (
            id INTEGER PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            calculator_type TEXT NOT NULL CHECK(calculator_type IN ('investment', 'loan', 'reserve')),
            inputs_json TEXT NOT NULL,
            results_json TEXT NOT NULL,
            created_at TEXT NOT NULL
        );
        CREATE INDEX IF NOT EXISTS calculations_by_user ON calculations(user_id, id DESC);
    ''')


CALCULATION_FIELDS = {
    'investment': ('capital', 'monthly', 'rate', 'years', 'total', 'paid', 'growth'),
    'loan': ('amount', 'rate', 'years', 'payment', 'total', 'cost'),
    'reserve': ('expense', 'dependants', 'months', 'current', 'target', 'coverage', 'gap'),
}


def clean_calculation(payload):
    calculator_type = payload.get('calculator_type')
    if calculator_type not in CALCULATION_FIELDS:
        raise ValueError('Ismeretlen kalkulátortípus.')
    inputs = payload.get('inputs')
    results = payload.get('results')
    if not isinstance(inputs, dict) or not isinstance(results, dict):
        raise ValueError('A kalkuláció adatai hiányosak.')
    allowed = set(CALCULATION_FIELDS[calculator_type])
    values = {}
    for key, value in {**inputs, **results}.items():
        if key not in allowed or isinstance(value, bool) or not isinstance(value, (int, float)) or not math.isfinite(value):
            raise ValueError('Érvénytelen kalkulációs adat.')
        values[key] = round(float(value), 6)
    missing = allowed - set(values)
    if missing:
        raise ValueError('A kalkuláció adatai hiányosak.')
    input_keys = set(inputs)
    return calculator_type, {key: values[key] for key in input_keys}, {key: values[key] for key in allowed - input_keys}


def password_hash(password, salt):
    return hashlib.scrypt(password.encode(), salt=bytes.fromhex(salt), n=16384, r=8, p=1).hex()


class AccountMixin:
    def session_token(self):
        cookie = SimpleCookie()
        try:
            cookie.load(self.headers.get('Cookie', ''))
            return cookie['gt_session'].value if 'gt_session' in cookie else ''
        except Exception:
            return ''

    def current_user(self):
        from server import db
        token = self.session_token()
        if not token:
            return None
        with db() as connection:
            row = connection.execute('''SELECT u.id,u.name,u.email,u.phone FROM users u
                JOIN sessions s ON s.user_id=u.id WHERE s.token_hash=? AND s.expires_at>?''',
                (hashlib.sha256(token.encode()).hexdigest(), time.time())).fetchone()
        return dict(row) if row else None

    def account_response(self, payload, token='', logout=False, status=200):
        import json
        from server import PUBLIC_BASE_URL
        data = json.dumps(payload, ensure_ascii=False).encode()
        self.send_response(status)
        if token or logout:
            secure = '; Secure' if PUBLIC_BASE_URL.startswith('https://') else ''
            self.send_header('Set-Cookie', f'gt_session={token}; Path=/; HttpOnly; SameSite=Lax; Max-Age={0 if logout else SESSION_SECONDS}{secure}')
        self.send_header('Content-Type', 'application/json; charset=utf-8')
        self.send_header('Content-Length', str(len(data)))
        self.end_headers()
        self.wfile.write(data)

    def account_post(self):
        from server import db, clean_text, valid_email, utc_now, is_rate_limited, PUBLIC_BASE_URL
        # Custom header blocks cross-origin HTML forms; same-origin fetch is the only client.
        origin = self.headers.get('Origin')
        expected = urlparse(PUBLIC_BASE_URL)
        if self.headers.get('X-GoldenTor-Request') != '1' or (origin and origin != f'{expected.scheme}://{expected.netloc}'):
            self.json_response({'error': 'Érvénytelen kérés eredete.'}, 403)
            return
        if is_rate_limited('account:' + self.client_address[0]):
            self.json_response({'error': 'Túl sok próbálkozás. Kérjük, próbálja meg 10 perc múlva.'}, 429)
            return
        try:
            payload = self.read_json()
            if not isinstance(payload, dict):
                raise ValueError('Érvénytelen kérés.')
            route = urlparse(self.path).path
            if route in ('/api/auth/register', '/api/auth/login'):
                email = clean_text(payload.get('email'), 254).casefold()
                password = payload.get('password', '')
                if not valid_email(email) or not isinstance(password, str) or not 10 <= len(password) <= 128:
                    raise ValueError('Érvényes e-mail-cím és 10–128 karakteres jelszó szükséges.')
                with db() as connection:
                    if route.endswith('register'):
                        name = clean_text(payload.get('name'), 120)
                        if payload.get('privacy') is not True:
                            raise ValueError('Kérjük, olvassa el az adatkezelési tájékoztatót.')
                        salt = secrets.token_hex(16)
                        try:
                            cursor = connection.execute('INSERT INTO users(email,name,password_hash,salt,created_at) VALUES(?,?,?,?,?)',
                                (email, name, password_hash(password, salt), salt, utc_now()))
                        except sqlite3.IntegrityError:
                            raise ValueError('Ezzel az e-mail-címmel nem hozható létre fiók. Próbáljon bejelentkezni.')
                        user_id = cursor.lastrowid
                    else:
                        row = connection.execute('SELECT * FROM users WHERE email=?', (email,)).fetchone()
                        candidate = password_hash(password, row['salt'] if row else '00' * 16)
                        if not row or not secrets.compare_digest(candidate, row['password_hash']):
                            self.json_response({'error': 'Hibás e-mail-cím vagy jelszó.'}, 401)
                            return
                        user_id = row['id']
                    token = secrets.token_urlsafe(32)
                    connection.execute('DELETE FROM sessions WHERE expires_at<=? OR token_hash=?',
                        (time.time(), hashlib.sha256(self.session_token().encode()).hexdigest()))
                    connection.execute('INSERT INTO sessions VALUES(?,?,?)',
                        (hashlib.sha256(token.encode()).hexdigest(), user_id, time.time() + SESSION_SECONDS))
                self.account_response({'ok': True}, token=token, status=201 if route.endswith('register') else 200)
                return
            user = self.current_user()
            if route == '/api/auth/logout':
                with db() as connection:
                    connection.execute('DELETE FROM sessions WHERE token_hash=?',
                        (hashlib.sha256(self.session_token().encode()).hexdigest(),))
                self.account_response({'ok': True}, logout=True)
                return
            if not user:
                self.json_response({'error': 'A folytatáshoz jelentkezzen be.'}, 401)
                return
            if route == '/api/profile':
                name = clean_text(payload.get('name'), 120)
                phone = clean_text(payload.get('phone'), 40, required=False)
                with db() as connection:
                    connection.execute('UPDATE users SET name=?,phone=? WHERE id=?', (name, phone, user['id']))
                self.json_response({'ok': True})
            elif route == '/api/feedback':
                expert = clean_text(payload.get('expert'), 120)
                rating = payload.get('rating')
                if type(rating) is not int or rating not in range(1, 6):
                    raise ValueError('Válasszon 1 és 5 közötti értékelést.')
                message = clean_text(payload.get('message'), 3000)
                with db() as connection:
                    connection.execute('INSERT INTO feedback(user_id,expert,rating,message,created_at) VALUES(?,?,?,?,?)',
                        (user['id'], expert, rating, message, utc_now()))
                self.json_response({'ok': True}, 201)
            elif route == '/api/calculations':
                calculator_type, inputs, results = clean_calculation(payload)
                with db() as connection:
                    cursor = connection.execute('''INSERT INTO calculations(user_id,calculator_type,inputs_json,results_json,created_at)
                        VALUES(?,?,?,?,?)''', (user['id'], calculator_type, json.dumps(inputs), json.dumps(results), utc_now()))
                    connection.execute('''DELETE FROM calculations WHERE id IN (
                        SELECT id FROM calculations WHERE user_id=? ORDER BY id DESC LIMIT -1 OFFSET 100
                    )''', (user['id'],))
                self.json_response({'ok': True, 'id': cursor.lastrowid}, 201)
            else:
                self.json_response({'error': 'Ismeretlen végpont.'}, 404)
        except ValueError as exc:
            self.json_response({'error': str(exc)}, 400)
