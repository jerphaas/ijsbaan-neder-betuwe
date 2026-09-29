"""Encrypted, bounded website requests over the user's existing private SRV01 route."""
import base64
import hashlib
import json
import os
import subprocess
import shutil
import sys
import time
from pathlib import Path
from urllib.error import HTTPError
from urllib.request import Request, build_opener, ProxyHandler
from cryptography.hazmat.primitives.ciphers.aead import AESGCM

sys.dont_write_bytecode = True
CONTEXT = b'ijsbaan-sponsors-srv01-20260929-v1'
ENDPOINT = 'http://172.16.85.1/github/api/ijsbaan_sponsors_20260929.php'
SITE = Path(__file__).resolve().parents[2]
DELIVERY = Path(__file__).parent / 'server'
CONFIG_NAMES = ['BEURSOPHEUSDEN_API_TOKEN', 'DASHBOARD_HISTORY_API_TOKEN', 'TEST_RUNNER_API_TOKEN',
                'SYNC_API_TOKEN', 'DASHBOARD_SYNC_API_TOKEN', 'DEPLOY_API_TOKEN', 'TEST_RUNNER_INLINE_TOKEN']

def existing_key():
    dashboard = Path(os.environ.get('IJSBAAN_DASHBOARD_ROOT', str(Path.home() / 'Documents/GitHub/dashboard')))
    support = (dashboard / 'fictief/beursopheusden_support.php').as_posix().replace("'", "\\'")
    code = "<?php require '" + support + "'; "
    code += 'echo bbo_store_config_value(' + "['" + "','".join(CONFIG_NAMES) + "'], '');"
    process = subprocess.run([os.environ.get('IJSBAAN_PHP_EXECUTABLE') or shutil.which('php') or r'C:\PHP\php.exe'], input=code.encode(), capture_output=True, check=True)
    token = process.stdout.strip()
    if len(token) < 32 or b'Warning' in token or b'<' in token:
        raise RuntimeError('Existing dashboard config/env token unavailable.')
    return hashlib.sha256(CONTEXT + b'\0' + token).digest()

class Client:
    def __init__(self):
        self.aes = AESGCM(existing_key())
        self.opener = build_opener(ProxyHandler({}))
        self.cookies = {}

    def call(self, **fields):
        nonce = os.urandom(12)
        payload = json.dumps({'time': int(time.time()), **fields}, ensure_ascii=False).encode()
        cipher = self.aes.encrypt(nonce, payload, CONTEXT + b':request')
        envelope = json.dumps({'nonce': base64.b64encode(nonce).decode(),
                               'ciphertext': base64.b64encode(cipher).decode()}).encode()
        request = Request(ENDPOINT, data=envelope, headers={'Content-Type':'application/json', 'Accept':'application/json'})
        try:
            with self.opener.open(request, timeout=35) as response:
                status, raw = response.status, response.read()
        except HTTPError as error:
            status, raw = error.code, error.read()
        data = json.loads(raw)
        if 'ciphertext' not in data:
            raise RuntimeError(f'SRV01 HTTP {status}: {data.get("error", "Unencrypted response")}')
        result = json.loads(self.aes.decrypt(base64.b64decode(data['nonce']), base64.b64decode(data['ciphertext']),
                                            CONTEXT + b':response:' + nonce.hex().encode()))
        for filename, field in [('api/ijsbaan_sponsors_20260929.php', 'endpoint_sha256'),
                                ('includes/IjsbaanSponsors20260929.php', 'client_sha256')]:
            assert result[field] == hashlib.sha256((DELIVERY / filename).read_bytes()).hexdigest(), 'Live helper differs'
        if status != 200 or not result.get('ok'):
            safe = {'status': status, 'connect_error': result.get('connect_error'),
                    'connection': result.get('connection'), 'error': result.get('error')}
            report = SITE / '.deploy/srv01/connection-failure.json'
            report.parent.mkdir(parents=True, exist_ok=True)
            report.write_text(json.dumps(safe, indent=2), encoding='utf-8')
            raise RuntimeError('SRV01 operation failed: ' + json.dumps(safe))
        for header in result.get('headers', {}).get('set-cookie', []):
            item = header.split(';', 1)[0]
            key, value = item.split('=', 1)
            self.cookies[key] = value
        return result

    def request(self, operation, **fields):
        if operation in ('admin-read', 'login', 'save', 'logout'):
            fields['cookie'] = '; '.join(f'{key}={value}' for key, value in self.cookies.items())
        result = self.call(operation=operation, **fields)
        body = base64.b64decode(result.get('body', ''))
        if result.get('status', 200) != 200 and not (operation == 'logout' and result['status'] == 303):
            raise RuntimeError(f'Hosting HTTP {result["status"]}; body suppressed')
        return result, body

    def maintenance(self, action):
        sys.path.insert(0, str(SITE / 'scripts'))
        from sponsor_admin import maintenance_key
        result, body = self.request('maintenance', action=action, maintenance_key=maintenance_key())
        data = json.loads(body)
        assert data.get('ok'), 'Maintenance not confirmed'
        return data

if __name__ == '__main__':
    client = Client()
    result, body = client.request('public', path='/site-version.json')
    version = json.loads(body)
    print(json.dumps({'srv01': 'reachable', 'hosting_status': result['status'], 'live_commit': version.get('commit')}))
