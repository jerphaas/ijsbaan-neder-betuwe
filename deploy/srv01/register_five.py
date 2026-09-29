"""Register and verify only the five requested website sponsors, through SRV01."""
import base64
import hashlib
import json
import re
import sys
import time
from datetime import datetime, timezone
from pathlib import Path
from urllib.parse import parse_qs, urlparse
from uuid import uuid4
from client import Client, SITE
from html.parser import HTMLParser

sys.dont_write_bytecode = True
sys.path.insert(0, str(SITE / 'scripts'))
from check_partners_live import Form

class Elements(HTMLParser):
    def __init__(self, body):
        super().__init__()
        self.tags = []
        self.feed(body.decode('utf-8') if isinstance(body, bytes) else body)
    def handle_starttag(self, tag, attrs):
        self.tags.append((tag, dict(attrs)))

def name(value):
    return re.sub(r'[^a-z0-9]', '', value.lower()).removesuffix('bv')

def host(value):
    return (urlparse(value).hostname or '').lower().removeprefix('www.')


SLUGS = ['de-bruin-betonwerken', 'arends-natuurlijk', 'van-dijk-metaaldesign',
         'van-dam-wonen-en-slapen', 'heeren-van-opheusden']
FIELDS = ('name', 'website', 'description', 'edition_id', 'amount', 'tier', 'sort_order', 'visible', 'logo_dark')
OUT = SITE / '.deploy/srv01/2026-09-29'

def clean_form(body):
    return {key: value for key, value in Form(body).values.items() if key not in ('csrf', 'action')}

def profile_list(client):
    _, body = client.request('admin-read', path='/beheer/?partners=1')
    paths = sorted({a['href'] for tag, a in Elements(body).tags
                    if tag == 'a' and a.get('href', '').startswith('/beheer/?partners=1&edit=')})
    return paths, body

def public_check(client, profile, profile_id):
    _, body = client.request('public', path='/api/partners.php?check=' + uuid4().hex)
    fragments = json.loads(body)['fragments']
    for block in ('strip', 'grid'):
        tags = Elements(fragments[block]).tags
        links = [a for tag, a in tags if tag == 'a' and a.get('href') == profile['website']]
        images = [a for tag, a in tags if tag == 'img' and a.get('alt') == 'Logo van ' + profile['name']]
        assert len(links) == len(images) == 1, 'Expected one link and logo in ' + block
        assert 'logo=' + profile_id in images[0]['src']
    source = urlparse(images[0]['src'])
    assert not source.hostname or source.hostname == 'ijsbaannederbetuwe.nl'
    logo_path = source.path + ('?' + source.query if source.query else '')
    result, logo = client.request('public', path=logo_path)
    assert result['headers']['content-type'][0] == 'image/webp' and logo[8:12] == b'WEBP'
    (OUT / (profile_id + '.webp')).write_bytes(logo)
    return {'public_logo_path': logo_path, 'live_logo_sha256': hashlib.sha256(logo).hexdigest(),
            'public_blocks_verified': ['strip', 'grid']}

def main():
    OUT.mkdir(parents=True, exist_ok=True)
    client = Client()
    profiles = {slug: json.loads((SITE / '.deploy/pending-sponsors' / (slug + '.json')).read_text(encoding='utf-8'))
                for slug in SLUGS}
    for profile in profiles.values():
        assert profile['edition_id'] == 'opheusden-2026' and profile['visible'] == 1
        assert profile['amount'] == 0 and profile['amount_confirmed'] is False and profile['confirmed_amount'] is None
    before = client.maintenance('health')
    _, body = client.request('login', ticket=client.maintenance('admin-link')['ticket'])
    assert json.loads(body)['ok']
    paths, _ = profile_list(client)
    existing = []
    for index, path in enumerate(paths):
        _, body = client.request('admin-read', path=path)
        existing.append(clean_form(body))
        if (index + 1) % 10 == 0:
            print(f'Duplicate check: {index + 1}/{len(paths)} profiles read.', flush=True)
        time.sleep(0.15)
    (OUT / 'profiles-before.json').write_text(json.dumps(existing, ensure_ascii=False, indent=2), encoding='utf-8')
    initial_ids = {p['profile_id'] for p in existing}
    receipts = []
    for slug, profile in profiles.items():
        matches = [p for p in existing if p['edition_id'] == profile['edition_id'] and
                   (name(p['name']) == name(profile['name']) or host(p['website']) == host(profile['website']))]
        assert len(matches) <= 1, 'Multiple matching profiles: ' + slug
        if matches:
            saved = matches[0]
            profile_id = saved['profile_id']
        else:
            _, body = client.request('admin-read', path='/beheer/?partners=1&new=1')
            form = Form(body).values
            assert form['profile_id'] == '' and form['revision'] == '0' and form['application'] == ''
            logo = (SITE / 'dist' / profile['logo_asset']).read_bytes()
            _, body = client.request('save', slug=slug, csrf=form['csrf'], logo=base64.b64encode(logo).decode())
            result = json.loads(body)
            assert result.get('ok')
            profile_id = parse_qs(urlparse(result['url']).query)['edit'][0]
            assert re.fullmatch(r'sp-[0-9a-f]{24}', profile_id)
            _, body = client.request('admin-read', path='/beheer/?partners=1&edit=' + profile_id)
            saved = clean_form(body)
            existing.append(saved)
        for field in FIELDS:
            assert saved.get(field, '0' if field == 'logo_dark' else '') == str(profile[field]), 'Field mismatch: ' + field
        proof = public_check(client, profile, profile_id)
        receipt = {**profile, **proof, 'live_profile_id': profile_id,
                   'verified_at': datetime.now(timezone.utc).isoformat(), 'route': 'SRV01'}
        (OUT / (slug + '.json')).write_text(json.dumps(receipt, ensure_ascii=False, indent=2), encoding='utf-8')
        receipts.append(receipt)
        print('Saved and verified in both public blocks: ' + profile['name'], flush=True)
    final_paths, _ = profile_list(client)
    final_ids = {parse_qs(urlparse(path).query)['edit'][0] for path in final_paths}
    assert final_ids == initial_ids | {r['live_profile_id'] for r in receipts}, 'Unexpected profile membership change'
    after = client.maintenance('health')
    assert all(before[k] == after[k] for k in ('applications', 'tests', 'pending')), 'Application/mail counters changed'
    _, home = client.request('public', path='/')
    for receipt in receipts:
        assert home.decode('utf-8').count('Logo van ' + receipt['name']) == 2, 'Homepage missing both sponsor blocks'
    (OUT / 'homepage.html').write_bytes(home)
    _, form_body = client.request('admin-read', path='/beheer/?partners=1&new=1')
    client.request('logout', csrf=Form(form_body).values['csrf'])
    report = {'verified_at': datetime.now(timezone.utc).isoformat(), 'stored_profiles': len(final_ids),
              'new_or_verified_profiles': [r['live_profile_id'] for r in receipts],
              'existing_ids_preserved': True, 'applications_and_mail_unchanged': True,
              'homepage_sha256': hashlib.sha256(home).hexdigest(), 'route': 'SRV01'}
    (OUT / 'verification.json').write_text(json.dumps(report, indent=2), encoding='utf-8')
    print('All five verified on the live homepage; applications/mail unchanged. Admin session logged out.', flush=True)

if __name__ == '__main__':
    main()
