"""Live authorization/read-only export checks. Does not create applications or send mail."""
import csv
import hashlib
from html.parser import HTMLParser
from http.cookiejar import CookieJar
from io import StringIO
import json
import re
from urllib.error import HTTPError
from urllib.parse import urlencode
from urllib.request import build_opener, HTTPCookieProcessor, Request
from sponsor_admin import action
from hosting import CONFIG

BASE = CONFIG['publicUrl'].rstrip('/')


class Page(HTMLParser):
    def __init__(self, body):
        super().__init__()
        self.csrf = None
        self.references = []
        self.feed(body.decode('utf-8'))

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == 'input' and a.get('name') == 'csrf':
            self.csrf = a['value']
        if tag == 'a' and a.get('href', '').startswith('/beheer/?id='):
            self.references.append(a['href'].split('id=')[1])


def main():
    before = action('health')
    jar = CookieJar()
    browser = build_opener(HTTPCookieProcessor(jar))

    def fetch(path='', payload=None, origin=BASE):
        headers = {'Cache-Control': 'no-cache'}
        if payload is not None:
            headers['Origin'] = origin
        req = Request(BASE + '/beheer/' + path, data=None if payload is None else urlencode(payload).encode(), headers=headers)
        try:
            response = browser.open(req, timeout=20)
        except HTTPError as error:
            response = error
        with response:
            return response.status, dict(response.headers), response.read()

    for path in ['', '?view=test', '?csv=1', '?logo=IJS-260914-32E12205', '?id=IJS-260914-32E12205']:
        status, headers, body = fetch(path)
        assert status == (401 if any(key in path for key in ('csv=', 'logo=', 'id=')) else 200)
        assert b'Open Sponsorbeheer op je pc' in body and b'TECHNISCHE TEST - IJsbaanwebsite' not in body
        assert 'noindex' in headers.get('X-Robots-Tag', '') and 'no-store' in headers.get('Cache-Control', '')
    print('PASS: anonymous users cannot read applications, CSV or logos; private pages are not cached/indexable.')
    ticket = action('admin-link')['ticket']
    assert fetch(payload={'action': 'login', 'ticket': ticket}, origin='https://example.com')[0] == 403
    assert fetch(payload={'action': 'login', 'ticket': 'bad'})[0] == 401
    status, headers, body = fetch(payload={'action': 'login', 'ticket': ticket})
    assert status == 200 and json.loads(body)['ok']
    for cookie in jar:
        assert cookie.secure and cookie.has_nonstandard_attr('HttpOnly')
        assert cookie.get_nonstandard_attr('SameSite') == 'Lax'
    assert fetch(payload={'action': 'login', 'ticket': ticket})[0] == 401
    print('PASS: origin validation, invalid/reused ticket rejection and Secure/HttpOnly/SameSite cookies.')
    status, _, real_body = fetch()
    assert status == 200 and b'Sponsoraanvragen' in real_body and b'name="csrf"' in real_body
    status, _, test_body = fetch('?view=test')
    test_page = Page(test_body)
    assert status == 200
    status, headers, csv_body = fetch('?view=test&csv=1')
    rows = list(csv.reader(StringIO(csv_body.decode('utf-8-sig')), delimiter=';'))
    assert status == 200 and 'attachment' in headers['Content-Disposition']
    assert len(rows) - 1 == before['tests'] and all(row[-1] == 'Ja' for row in rows[1:])
    if test_page.references:
        reference = test_page.references[0]
        status, _, detail = fetch('?id=' + reference)
        assert status == 200 and reference.encode() in detail and b'Technische test.' in detail
        status, _, filtered = fetch('?view=test&q=' + reference)
        assert status == 200 and reference.encode() in filtered
        status, _, empty = fetch('?view=test&q=does-not-exist-' + hashlib.sha256(ticket.encode()).hexdigest()[:12])
        assert status == 200 and b'Geen passende aanvragen.' in empty
        if ('?logo=' + reference).encode() in detail:
            status, headers, logo = fetch('?logo=' + reference)
            assert status == 200 and logo.startswith(b'\x89PNG\r\n\x1a\n') and 'attachment' in headers['Content-Disposition']
    print('PASS: authenticated list, test filter, search, detail, CSV and private logo download.')
    old_device = next(c.value for c in jar if c.name == '__Host-ijsbaan_herken')
    for cookie in list(jar):
        if cookie.name == '__Host-ijsbaan_beheer':
            jar.clear(cookie.domain, cookie.path, cookie.name)
    status, _, restored = fetch()
    assert status == 200 and b'name="csrf"' in restored
    new_device = next(c.value for c in jar if c.name == '__Host-ijsbaan_herken')
    assert new_device != old_device
    assert fetch(payload={'action': 'logout', 'csrf': 'invalid'})[0] == 403
    csrf = Page(restored).csrf
    status, _, body = fetch(payload={'action': 'logout', 'csrf': csrf})
    assert status == 200 and b'Open Sponsorbeheer op je pc' in body
    assert fetch('?csv=1')[0] == 401
    after = action('health')
    assert (before['applications'], before['tests'], before['pending']) == (after['applications'], after['tests'], after['pending'])
    print('PASS: remembered login rotates its token; CSRF-safe logout revokes access. No applications or mail changed.')


if __name__ == '__main__':
    main()
