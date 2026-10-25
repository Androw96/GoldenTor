"""Check setup sessions and CSRF without creating an account or sending personal data."""
import http.cookiejar
from html.parser import HTMLParser
import os
import re
import urllib.error
import urllib.parse
import urllib.request

# The UNAS host does not answer over IPv6 from GitHub runners: resolve IPv4 only.
import socket
_getaddrinfo = socket.getaddrinfo
socket.getaddrinfo = lambda host, port, family=0, *args, **kwargs: _getaddrinfo(host, port, socket.AF_INET, *args, **kwargs)

base = os.environ['GOLDENTOR_PUBLIC_URL'].rstrip('/')
url = base + '/admin/setup'
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

class LoginScripts(HTMLParser):
    def __init__(self):
        super().__init__()
        self.livewire = []

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'script' and attrs.get('data-update-uri'):
            self.livewire.append(attrs)

# The login HTML alone can return 200 even when its scripts and POST endpoint are broken.
login = opener.open(base + '/admin/login', timeout=30)
assert login.status == 200, f'Login GET: {login.status}'
scripts = LoginScripts()
scripts.feed(login.read().decode())
assert len(scripts.livewire) == 1, 'Livewire script not found on login page'
for attribute in ['src', 'data-update-uri']:
    endpoint = urllib.parse.urljoin(base, scripts.livewire[0][attribute])
    assert urllib.parse.urlsplit(endpoint).netloc == urllib.parse.urlsplit(base).netloc, 'Unexpected endpoint origin'
    if attribute == 'src':
        asset = opener.open(endpoint, timeout=30)
        assert asset.status == 200 and 'javascript' in asset.headers.get('Content-Type', ''), 'Livewire JavaScript is unavailable'
    else:
        probe = urllib.request.Request(endpoint, data=b'{}', headers={
            'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Livewire': '1', 'Origin': base})
        try:
            result = opener.open(probe, timeout=30)
        except urllib.error.HTTPError as error:
            result = error
        assert result.status == 419, f'Livewire POST must reach CSRF protection: {result.status}'
print('PASS: Livewire JavaScript loads and its POST endpoint reaches CSRF protection.')

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
