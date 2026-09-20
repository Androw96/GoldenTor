import hashlib
import http.client
import json
import tempfile
import threading
import time
import unittest
from pathlib import Path
import server


class AccountHTTPTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.original = (server.DATA_DIR, server.DB_PATH, server.PUBLIC_BASE_URL, server.ADMIN_KEY)
        cls.temp = tempfile.TemporaryDirectory()
        server.DATA_DIR = Path(cls.temp.name)
        server.DB_PATH = server.DATA_DIR / 'accounts.sqlite3'
        server.init_db()
        cls.http = server.ThreadingHTTPServer(('127.0.0.1', 0), server.GoldenTorHandler)
        cls.port = cls.http.server_address[1]
        server.PUBLIC_BASE_URL = f'http://127.0.0.1:{cls.port}'
        server.ADMIN_KEY = 'test-admin'
        cls.thread = threading.Thread(target=cls.http.serve_forever, daemon=True)
        cls.thread.start()

    @classmethod
    def tearDownClass(cls):
        cls.http.shutdown(); cls.http.server_close(); cls.thread.join()
        server.DATA_DIR, server.DB_PATH, server.PUBLIC_BASE_URL, server.ADMIN_KEY = cls.original
        cls.temp.cleanup()

    def setUp(self):
        self.cookie = ''
        server.RATE_LIMIT.clear()
        with server.db() as connection:
            connection.execute('DELETE FROM calculations')
            connection.execute('DELETE FROM feedback')
            connection.execute('DELETE FROM sessions')
            connection.execute('DELETE FROM users')

    def request(self, path, payload=None, method=None, headers=None):
        conn = http.client.HTTPConnection('127.0.0.1', self.port)
        req_headers = {'Cookie':self.cookie, 'Origin':server.PUBLIC_BASE_URL, 'X-GoldenTor-Request':'1', 'Content-Type':'application/json'}
        req_headers.update(headers or {})
        conn.request(method or ('POST' if payload is not None else 'GET'), path, json.dumps(payload) if payload is not None else None, req_headers)
        response = conn.getresponse(); body = response.read()
        result = (response.status, dict(response.getheaders()), body)
        conn.close()
        return result

    def register(self, email='test@example.com'):
        status, headers, body = self.request('/api/auth/register', {'name':'Teszt Elek','email':email,'password':'test-password-482!', 'privacy':True})
        self.assertEqual(status, 201, body)
        self.assertIn('HttpOnly', headers['Set-Cookie']); self.assertIn('SameSite=Lax', headers['Set-Cookie'])
        self.cookie = headers['Set-Cookie'].split(';')[0]

    def test_anonymous_cannot_fetch_tools_or_private_files(self):
        for path in ['/kalkulatorok.html', '/%6balkulatorok.html', '/assets/../kalkulatorok.html']:
            for method in ['GET','HEAD']:
                status, headers, _ = self.request(path, method=method)
                self.assertEqual(status, 303, path); self.assertTrue(headers['Location'].startswith('/fiok.html?next='))
        for path in ['/calculator.js', '/insurance-market.js', '/api/member/valiora']:
            self.assertEqual(self.request(path)[0], 401, path)
        for path in ['/private/valiora.html','/data/goldentor.sqlite3','/.env','/server.py','/accounts.py','/.git/config','/assets/../private/valiora.html']:
            self.assertEqual(self.request(path)[0], 404, path)
        self.assertNotIn(b'data-market-form', self.request('/biztositas.html')[2])
        self.assertEqual(self.request('/api/feedback', {'expert':'Test','rating':5,'message':'Test'})[0],401)

    def test_register_profile_access_and_logout(self):
        self.register()
        for path in ['/kalkulatorok.html','/calculator.js','/insurance-market.js','/api/member/valiora']:
            self.assertEqual(self.request(path)[0],200,path)
        me = json.loads(self.request('/api/auth/me')[2])['user']
        self.assertEqual(me['name'],'Teszt Elek'); self.assertNotIn('password_hash',me)
        self.assertEqual(self.request('/api/profile', {'name':'Új név','phone':'+36123456'})[0],200)
        self.assertEqual(json.loads(self.request('/api/auth/me')[2])['user']['name'],'Új név')
        with server.db() as connection:
            row=connection.execute('SELECT * FROM users').fetchone()
            self.assertNotEqual(row['password_hash'],'test-password-482!')
            session=connection.execute('SELECT * FROM sessions').fetchone()
            self.assertEqual(session['token_hash'],hashlib.sha256(self.cookie.split('=',1)[1].encode()).hexdigest())
        self.assertEqual(self.request('/api/auth/logout',{})[0],200)
        self.assertEqual(self.request('/calculator.js')[0],401)
        self.assertIsNone(json.loads(self.request('/api/auth/me')[2])['user'])

    def test_login_expiry_and_invalid_credentials(self):
        self.register(); self.request('/api/auth/logout',{}); self.cookie=''
        self.assertEqual(self.request('/api/auth/login',{'email':'test@example.com','password':'wrong-password'})[0],401)
        status,headers,_=self.request('/api/auth/login',{'email':'TEST@example.com','password':'test-password-482!'})
        self.assertEqual(status,200);self.cookie=headers['Set-Cookie'].split(';')[0]
        with server.db() as connection: connection.execute('UPDATE sessions SET expires_at=?',(time.time()-1,))
        self.assertEqual(self.request('/api/member/valiora')[0],401)

    def test_csrf_validation_and_feedback_admin_boundary(self):
        self.assertEqual(self.request('/api/auth/login',{},headers={'Origin':'https://evil.example'})[0],403)
        self.assertEqual(self.request('/api/auth/login',{},headers={'X-GoldenTor-Request':''})[0],403)
        self.assertEqual(self.request('/api/auth/register',{'email':'test@example.com','name':'Test','password':'short','privacy':True})[0],400)
        self.assertEqual(self.request('/api/auth/login',[])[0],400)
        self.register()
        self.assertEqual(self.request('/api/feedback',{'expert':'Test','rating':0,'message':'Test'})[0],400)
        self.assertEqual(self.request('/api/feedback',{'expert':'Test','rating':5,'message':'Hasznos konzultáció'})[0],201)
        self.assertEqual(self.request('/api/admin/feedback')[0],401)
        result=self.request('/api/admin/feedback',headers={'X-Admin-Key':'test-admin'})
        self.assertEqual(result[0],200)
        self.assertEqual(json.loads(result[2])['items'][0]['message'],'Hasznos konzultáció')

    def test_profiles_are_isolated_and_duplicates_rejected(self):
        self.register(); first_cookie=self.cookie
        self.cookie=''; self.register('second@example.com')
        self.request('/api/profile',{'name':'Második','phone':'','id':1})
        self.cookie=first_cookie
        self.assertEqual(json.loads(self.request('/api/auth/me')[2])['user']['name'],'Teszt Elek')
        self.assertEqual(self.request('/api/auth/register',{'name':'Duplicate','email':'TEST@example.com','password':'test-password-482!', 'privacy':True})[0],400)

    def test_calculations_are_saved_per_customer(self):
        self.register()
        payload = {'calculator_type':'investment', 'inputs':{'capital':5000000,'monthly':150000,'rate':7,'years':15}, 'results':{'total':65000000,'paid':32000000,'growth':33000000}}
        self.assertEqual(self.request('/api/calculations', payload)[0], 201)
        saved = json.loads(self.request('/api/calculations')[2])['items']
        self.assertEqual(len(saved), 1)
        self.assertEqual(saved[0]['calculator_type'], 'investment')
        self.assertEqual(saved[0]['inputs']['capital'], 5000000)
        self.assertEqual(self.request('/api/calculations', {'calculator_type':'loan','inputs':{},'results':{}})[0], 400)

if __name__=='__main__': unittest.main()
