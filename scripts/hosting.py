"""Windows DPAPI or CI environment credentials; certificate-validated FTPS."""
import ctypes
from ctypes import wintypes
from ftplib import FTP, FTP_TLS
import json
import os
from pathlib import Path
import ssl

ROOT = Path(__file__).resolve().parents[1]
PRIVATE = ROOT / '.deploy'
CONFIG = json.loads((ROOT / 'deploy.json').read_text(encoding='utf-8'))


class Blob(ctypes.Structure):
    _fields_ = [('size', wintypes.DWORD), ('data', ctypes.POINTER(ctypes.c_ubyte))]


def crypt(data, encrypt):
    if os.name != 'nt':
        raise RuntimeError('Windows DPAPI is alleen lokaal beschikbaar; configureer de GitHub environment-secrets.')
    buffer = (ctypes.c_ubyte * len(data)).from_buffer_copy(data)
    source = Blob(len(data), buffer)
    result = Blob()
    dll = ctypes.WinDLL('crypt32', use_last_error=True)
    kernel = ctypes.WinDLL('kernel32', use_last_error=True)
    kernel.LocalFree.argtypes = [ctypes.c_void_p]
    kernel.LocalFree.restype = ctypes.c_void_p
    if encrypt:
        ok = dll.CryptProtectData(ctypes.byref(source), 'IJsbaan FTPS', None,
                                 None, None, 1, ctypes.byref(result))
    else:
        ok = dll.CryptUnprotectData(ctypes.byref(source), None, None,
                                   None, None, 1, ctypes.byref(result))
    if not ok:
        raise ctypes.WinError(ctypes.get_last_error())
    try:
        return ctypes.string_at(result.data, result.size)
    finally:
        kernel.LocalFree(result.data)


def save_password(password):
    if not password:
        raise ValueError('Het wachtwoord mag niet leeg zijn.')
    PRIVATE.mkdir(exist_ok=True)
    payload = json.dumps({'host': CONFIG['host'], 'username': CONFIG['username'],
                          'password': password}).encode('utf-8')
    (PRIVATE / 'ftp-login.dpapi').write_bytes(crypt(payload, True))


class HostingFTP(FTP_TLS):
    # ProFTPD requires the protected data channel to reuse the login TLS session.
    def ntransfercmd(self, cmd, rest=None):
        connection, size = FTP.ntransfercmd(self, cmd, rest)
        if self._prot_p:
            connection = self.context.wrap_socket(
                connection, server_hostname=self.host, session=self.sock.session)
        return connection, size


def ftp_password():
    password = os.environ.get('IJSBAAN_FTP_PASSWORD')
    if password:
        return password
    if os.environ.get('GITHUB_ACTIONS') == 'true':
        raise RuntimeError('GitHub environment-secret IJSBAAN_FTP_PASSWORD ontbreekt.')
    path = PRIVATE / 'ftp-login.dpapi'
    if not path.exists():
        raise RuntimeError('Inloggegevens ontbreken. Gebruik python scripts/publish.py --save-login.')
    data = json.loads(crypt(path.read_bytes(), False))
    if data['host'] != CONFIG['host'] or data['username'] != CONFIG['username']:
        raise RuntimeError('Opgeslagen login hoort bij een andere host of gebruiker.')
    return data['password']


def connect():
    password = ftp_password()
    ftp = HostingFTP(context=ssl.create_default_context(), timeout=30)
    ftp.connect(CONFIG['host'], CONFIG['port'])
    ftp.login(CONFIG['username'], password)
    ftp.prot_p()
    return ftp
