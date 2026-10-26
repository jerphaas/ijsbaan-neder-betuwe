"""User-scoped Windows PAC setup. Only explicit hosts in ijsbaan.pac use WARP.

No credentials, certificates, firewall rules, or machine-wide tunnels are changed.
The server serves only a fixed PAC and health response, never arbitrary files.
"""
import argparse
import ctypes as C
from ctypes import wintypes as W
import hashlib
import json
import os
from pathlib import Path
import re
import socket
import subprocess
import sys
import time
import urllib.request
import winreg
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

ROOT = Path(__file__).resolve().parents[2]
STATE = ROOT / '.deploy' / 'windows-ijsbaan-route'
BACKUP = STATE / 'before.json'
ENABLED = STATE / 'enabled'
PAC = Path(__file__).with_name('ijsbaan.pac')
URL_BASE = 'http://127.0.0.1:40001/ijsbaan.pac'
URL = URL_BASE + '?v=' + hashlib.sha256(PAC.read_bytes()).hexdigest()[:12]
RUN_KEY = r'Software\Microsoft\Windows\CurrentVersion\Run'
RUN_NAME = 'IJsbaanDomainRoute'
WARP = Path(os.environ.get('ProgramFiles', r'C:\Program Files')) / 'Cloudflare/Cloudflare WARP/warp-cli.exe'


class Value(C.Union):
    _fields_ = [('number', W.DWORD), ('text', C.c_void_p), ('time', W.FILETIME)]


class Option(C.Structure):
    _fields_ = [('kind', W.DWORD), ('value', Value)]


class Options(C.Structure):
    _fields_ = [('size', W.DWORD), ('connection', W.LPWSTR),
                ('count', W.DWORD), ('error', W.DWORD), ('options', C.POINTER(Option))]


wininet = C.WinDLL('wininet', use_last_error=True)
wininet.InternetQueryOptionW.argtypes = [C.c_void_p, W.DWORD, C.c_void_p, C.POINTER(W.DWORD)]
wininet.InternetSetOptionW.argtypes = [C.c_void_p, W.DWORD, C.c_void_p, W.DWORD]
kernel = C.WinDLL('kernel32', use_last_error=True)
kernel.GlobalFree.argtypes = [C.c_void_p]
kernel.GlobalFree.restype = C.c_void_p


def proxy_settings(new=None):
    """Use WinINet's supported per-connection API, including change notification."""
    opts = (Option * 2)()
    opts[0].kind, opts[1].kind = 1, 4  # FLAGS and AUTOCONFIG_URL
    config = Options(C.sizeof(Options), None, 2, 0, opts)
    if new is None:
        size = W.DWORD(C.sizeof(config))
        if not wininet.InternetQueryOptionW(None, 75, C.byref(config), C.byref(size)):
            raise C.WinError(C.get_last_error())
        try:
            return {'flags': opts[0].value.number,
                    'url': C.wstring_at(opts[1].value.text) if opts[1].value.text else ''}
        finally:
            if opts[1].value.text:
                kernel.GlobalFree(opts[1].value.text)
    text = C.create_unicode_buffer(new['url'])
    opts[0].value.number = new['flags']
    opts[1].value.text = C.cast(text, C.c_void_p).value
    if not wininet.InternetSetOptionW(None, 75, C.byref(config), C.sizeof(config)):
        raise C.WinError(C.get_last_error())
    for option in (39, 37):  # SETTINGS_CHANGED, REFRESH
        if not wininet.InternetSetOptionW(None, option, None, 0):
            raise C.WinError(C.get_last_error())


def run_value():
    try:
        with winreg.OpenKey(winreg.HKEY_CURRENT_USER, RUN_KEY) as key:
            value, kind = winreg.QueryValueEx(key, RUN_NAME)
            return {'value': value, 'kind': kind}
    except FileNotFoundError:
        return None


def set_run(value):
    with winreg.CreateKey(winreg.HKEY_CURRENT_USER, RUN_KEY) as key:
        if value is None:
            try:
                winreg.DeleteValue(key, RUN_NAME)
            except FileNotFoundError:
                pass
        else:
            winreg.SetValueEx(key, RUN_NAME, 0, value['kind'], value['value'])


def is_our_url(url):
    # Accept earlier revisions for safe upgrades/restores, never other PAC URLs.
    return re.fullmatch(re.escape(URL_BASE) + r'(?:\?v=[0-9a-f]{12})?', url) is not None


def warp(*args):
    result = subprocess.run([str(WARP), '--accept-tos', *args], capture_output=True,
                            text=True, timeout=15, creationflags=subprocess.CREATE_NO_WINDOW)
    if result.returncode:
        raise RuntimeError('WARP command failed: ' + ' '.join(args))
    return result.stdout


def assert_proxy_mode():
    settings = warp('settings')
    if 'Mode: WarpProxy on port 40000' not in settings or 'Packet Tunnel: false' not in settings:
        raise RuntimeError('WARP must already use local proxy 40000 with Packet Tunnel false')


def health():
    try:
        opener = urllib.request.build_opener(urllib.request.ProxyHandler({}))
        with opener.open('http://127.0.0.1:40001/health', timeout=2) as response:
            data = json.load(response)
            return data if data.get('service') == RUN_NAME else None
    except (OSError, ValueError):
        return None


class Handler(BaseHTTPRequestHandler):
    def do_GET(self):
        if self.headers.get('Host') != '127.0.0.1:40001':
            self.send_error(403)
            return
        path = self.path.split('?', 1)[0]
        if path == '/ijsbaan.pac':
            body, mime = PAC.read_bytes(), 'application/x-ns-proxy-autoconfig'
            self.server.pac_requests += 1
        elif path == '/health':
            body = json.dumps({'service': RUN_NAME, 'pid': os.getpid(),
                               'pac_requests': self.server.pac_requests}).encode()
            mime = 'application/json'
        else:
            self.send_error(404)
            return
        self.send_response(200)
        self.send_header('Content-Type', mime)
        self.send_header('Content-Length', str(len(body)))
        self.send_header('Cache-Control', 'no-store')
        self.end_headers()
        self.wfile.write(body)

    def log_message(self, *args):
        pass  # No browsing history or request headers are recorded.


class Server(ThreadingHTTPServer):
    allow_reuse_address = False
    daemon_threads = True

    def server_bind(self):
        self.socket.setsockopt(socket.SOL_SOCKET, socket.SO_EXCLUSIVEADDRUSE, 1)
        super().server_bind()


def serve():
    if not ENABLED.exists():
        return
    with Server(('127.0.0.1', 40001), Handler) as server:
        server.pac_requests = 0
        server.timeout = 2
        # Startup may precede WARP's service. No change of mode or registration.
        for attempt in range(6):
            try:
                assert_proxy_mode()
                warp('connect')
                break
            except (OSError, RuntimeError, subprocess.TimeoutExpired):
                if attempt == 5:
                    raise
                time.sleep(5)
        while ENABLED.exists():
            server.handle_request()


def install():
    current = proxy_settings()
    if (current['url'] and not is_our_url(current['url'])) or current['flags'] & 2:
        raise RuntimeError('Existing proxy configuration requires review; nothing changed')
    assert_proxy_mode()
    pythonw = Path(sys.executable).with_name('pythonw.exe')
    if not pythonw.is_file():
        raise RuntimeError('Matching pythonw.exe is missing')
    command = subprocess.list2cmdline([str(pythonw), str(Path(__file__).resolve()), 'serve'])
    old_run = run_value()
    if old_run and old_run['value'] != command:
        raise RuntimeError('Startup entry belongs to another configuration')
    STATE.mkdir(parents=True, exist_ok=True)
    if not BACKUP.exists():
        if is_our_url(current['url']):
            raise RuntimeError('Refusing to overwrite a missing original backup')
        BACKUP.write_text(json.dumps({'proxy': current, 'run': old_run}, indent=2))
    ENABLED.touch()
    warp('connect')
    if not health():
        subprocess.Popen([str(pythonw), str(Path(__file__).resolve()), 'serve'],
                         creationflags=subprocess.CREATE_NO_WINDOW | subprocess.DETACHED_PROCESS,
                         stdin=subprocess.DEVNULL, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        for _ in range(40):
            if health():
                break
            time.sleep(1)
        else:
            raise RuntimeError('PAC helper did not start; proxy settings unchanged')
    opener = urllib.request.build_opener(urllib.request.ProxyHandler({}))
    with opener.open(URL, timeout=3) as response:
        if response.read() != PAC.read_bytes():
            raise RuntimeError('Served PAC differs; proxy settings unchanged')
    try:
        set_run({'value': command, 'kind': winreg.REG_SZ})
        proxy_settings({'flags': 5, 'url': URL})  # DIRECT | AUTO_PROXY_URL
        if proxy_settings() != {'flags': 5, 'url': URL}:
            raise RuntimeError('Proxy readback differs')
    except Exception:
        proxy_settings(current)
        set_run(old_run)
        raise
    status()


def restore():
    if not is_our_url(proxy_settings()['url']):
        raise RuntimeError('Proxy changed since installation; refusing to overwrite')
    before = json.loads(BACKUP.read_text())
    proxy_settings(before['proxy'])
    set_run(before['run'])
    ENABLED.unlink(missing_ok=True)
    assert_proxy_mode()
    warp('disconnect')
    # The helper exits within two seconds; no arbitrary process is terminated.
    status()


def status():
    print(json.dumps({'proxy': proxy_settings(), 'startup': run_value() is not None,
                      'helper': health(), 'warp_status': warp('status').strip()}, indent=2))


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('action', choices=['status', 'install', 'restore', 'serve'])
    args = parser.parse_args()
    {'status': status, 'install': install, 'restore': restore, 'serve': serve}[args.action]()
