"""Check setup sessions and CSRF without creating an account or sending personal data."""
import http.cookiejar
import os
import re
import urllib.error
import urllib.parse
import urllib.request

base = os.environ['GOLDENTOR_PUBLIC_URL'].rstrip('/')
url = base + '/admin/setup'
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

def request(data=None):
    req = urllib.request.Request(url, data=None if data is None else urllib.parse.urlencode(data).encode(),
                                 headers={'Accept': 'application/json' if data is not None else 'text/html',
                                          'Origin': base, 'Referer': url})
    try:
        return opener.open(req, timeout=30)
    except urllib.error.HTTPError as error:
        return error

first = request()
if first.status == 404:
    print('Setup closed; first administrator already exists.')
    raise SystemExit(0)
assert first.status == 200, f'Setup GET: {first.status}'
assert 'no-store' in first.headers.get('Cache-Control', ''), 'Setup form must not be cached'
token = re.search(r'name="_token" value="([^"]+)"', first.read().decode()).group(1)
second = request()
assert second.status == 200, f'Second setup GET: {second.status}'
assert token == re.search(r'name="_token" value="([^"]+)"', second.read().decode()).group(1), 'Session did not persist'
bad = request({'_token': 'invalid-csrf-test-token'})
assert bad.status == 419, f'Invalid CSRF accepted: {bad.status}'
valid = request({'_token': token})
assert valid.status == 422, f'Valid CSRF with empty form must reach validation: {valid.status}'
print('PASS: session persists, invalid CSRF rejected, valid CSRF reaches validation; no account created.')
