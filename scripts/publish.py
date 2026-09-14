"""Publish the committed dist/ snapshot; keep credentials and backups local."""
import argparse
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime, timezone
import getpass
import hashlib
from io import BytesIO
import json
import re
from pathlib import PurePosixPath
import subprocess
import sys
from urllib.request import Request, urlopen
from uuid import uuid4
from hosting import CONFIG, PRIVATE, ROOT, connect, save_password


def git(*args):
    return subprocess.check_output(['git', '-C', str(ROOT), *args])


def digest(data):
    return hashlib.sha256(data).hexdigest()


def static_home(data):
    """Only two explicit sponsor slots may differ from the committed HTML template."""
    for key in (b'STRIP', b'GRID'):
        start = b'<!-- PARTNERS:' + key + b':START -->'
        end = b'<!-- PARTNERS:' + key + b':END -->'
        if start not in data and end not in data:
            continue  # One-time migration from the original static home page.
        if data.count(start) != 1 or data.count(end) != 1:
            raise RuntimeError('Onverwachte sponsorblokken in de startpagina.')
        data = re.sub(re.escape(start) + b'.*?' + re.escape(end), start + end, data, flags=re.S)
    return data


def snapshot():
    # Do not publish stale metadata after an edition or asset change.
    from check_seo import validate
    validate()
    if git('status', '--porcelain', '--untracked-files=all', '--', 'dist').strip():
        raise RuntimeError('Sla wijzigingen in dist/ eerst op met een Git-commit.')
    files = {}
    for record in git('ls-tree', '-r', '-z', 'HEAD', 'dist').split(b'\0'):
        if not record:
            continue
        details, raw_path = record.decode().split('\t')
        mode, kind, blob = details.split()
        path = PurePosixPath(raw_path).relative_to('dist')
        if mode != '100644' or kind != 'blob' or '..' in path.parts:
            raise RuntimeError('Onverwacht bestandstype in dist/: ' + str(path))
        if any(part.startswith('.') for part in path.parts) and str(path) not in ('.htaccess', '.nojekyll'):
            raise RuntimeError('Verborgen bestand in dist/ wordt niet gepubliceerd: ' + str(path))
        files[str(path)] = git('cat-file', 'blob', blob)
    if 'index.html' not in files or not 5 <= len(files) <= 200 or sum(map(len, files.values())) > 25_000_000:
        raise RuntimeError('Onverwachte websiteomvang; publicatie gestopt.')
    return git('rev-parse', 'HEAD').decode().strip(), files


def read_remote(ftp, name):
    result = BytesIO()
    def append(chunk):
        if result.tell() + len(chunk) > 25_000_000:
            raise RuntimeError('Onverwacht groot bestand op de hosting: ' + name)
        result.write(chunk)
    ftp.retrbinary('RETR ' + name, append)
    return result.getvalue()


def live_file(name, nonce):
    url = CONFIG['publicUrl'] + name + '?verify=' + nonce
    with urlopen(Request(url, headers={'Cache-Control': 'no-cache'}), timeout=30) as response:
        if not response.url.startswith(CONFIG['publicUrl']):
            raise RuntimeError('Onverwachte omleiding naar een ander domein.')
        return response.read()


def verify(files):
    nonce = uuid4().hex
    names = [name for name in files if not name.startswith('.') and not name.endswith('.php')]
    def check(name):
        actual, expected = live_file(name, nonce), files[name]
        if name == 'index.html':
            actual, expected = static_home(actual), static_home(expected)
        if digest(actual) != digest(expected):
            raise RuntimeError('Online bestand wijkt af van de commit: ' + name)
        return name
    with ThreadPoolExecutor(max_workers=4) as pool:
        checked = list(pool.map(check, names))
    if digest(static_home(live_file('', nonce))) != digest(static_home(files['index.html'])):
        raise RuntimeError('De domeinstartpagina toont nog een ander bestand.')
    print(f'HTTPS-controle: {len(checked)} bestanden bevestigd; startpagina gelijk aan de commit buiten de twee actuele sponsorblokken.', flush=True)


def remote_inventory(ftp, folders):
    result = {}
    for folder in sorted(folders, key=lambda p: (len(PurePosixPath(p).parts), p)):
        if folder != '.':
            if result.get(folder, {}).get('type') != 'dir':
                continue
        for name, facts in ftp.mlsd(folder):
            if name in ('.', '..'):
                continue
            result[str(PurePosixPath(folder) / name)] = facts
    return result


def upload(ftp, name, content, token):
    path = PurePosixPath(name)
    temporary = str(path.with_name('.publish-' + token + '-' + path.name))
    try:
        ftp.storbinary('STOR ' + temporary, BytesIO(content))
        if digest(read_remote(ftp, temporary)) != digest(content):
            raise RuntimeError('Uploadcontrole mislukt voor ' + name)
        ftp.rename(temporary, name)
    except Exception:
        try:
            ftp.delete(temporary)
        except Exception:
            pass
        raise


def publish(commit, files):
    import deploy_sponsor
    server_files = deploy_sponsor.snapshot()
    root = CONFIG['remoteRoot']
    if root != '/domains/ijsbaannederbetuwe.nl/public_html':
        raise RuntimeError('De gecontroleerde webmap is gewijzigd; eerst opnieuw inspecteren.')
    stamp = datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ')
    token = uuid4().hex[:12]
    backup = PRIVATE / 'backups' / (stamp + '-' + token)
    backup.mkdir(parents=True)
    folders = {'.'}
    for name in files:
        folders.update(str(p) for p in PurePosixPath(name).parents)
    with connect() as ftp:
        ftp.cwd(root)
        if ftp.pwd() != root:
            raise RuntimeError('De FTP-server heeft een onverwachte webmap gekozen.')
        inventory = remote_inventory(ftp, folders)
        if inventory.get('index.html', {}).get('type') != 'file':
            raise RuntimeError('Geen herkenbare index.html in de gecontroleerde webmap.')
        previous_index = read_remote(ftp, 'index.html')
        if digest(static_home(previous_index)) != digest(static_home(live_file('index.html', token))):
            raise RuntimeError('FTP-webmap en openbaar domein tonen verschillende index.html-bestanden.')
        saved = []
        for name in [*files, 'site-version.json']:
            facts = inventory.get(name)
            if not facts:
                continue
            if facts.get('type') != 'file':
                raise RuntimeError('Doelbestand is geen gewoon bestand: ' + name)
            content = previous_index if name == 'index.html' else read_remote(ftp, name)
            target = backup / name
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(content)
            saved.append(name)
        (backup / 'backup-info.json').write_text(json.dumps({
            'publicUrl': CONFIG['publicUrl'], 'remoteRoot': root,
            'overwrittenFiles': saved, 'newFiles': [n for n in files if n not in inventory]
        }, indent=2), encoding='utf-8')
        print(f'Reservekopie: {len(saved)} bestaande bestanden veilig lokaal opgeslagen.', flush=True)
        for folder in sorted(folders, key=lambda p: (len(PurePosixPath(p).parts), p)):
            if folder == '.':
                continue
            if folder in inventory:
                if inventory[folder].get('type') != 'dir':
                    raise RuntimeError('Doelmap is geen gewone map: ' + folder)
            else:
                ftp.mkd(folder)
        deploy_sponsor.deploy(ftp, commit, server_files, backup, token)
        # Each file is checked before rename; publish the entry page last.
        for name in sorted(files, key=lambda n: (n == 'index.html', n == '.htaccess', n)):
            if name == 'index.html':
                deploy_sponsor.activate(ftp, commit, token)
            upload(ftp, name, files[name], token)
            print('Geplaatst:', name, flush=True)
        verify(files)
        report = {'commit': commit, 'publishedAt': stamp, 'publicUrl': CONFIG['publicUrl'],
                  'files': {name: digest(data) for name, data in files.items()}}
        serialized = json.dumps(report, indent=2).encode('utf-8')
        upload(ftp, 'site-version.json', serialized, token)
        if digest(live_file('site-version.json', token)) != digest(serialized):
            raise RuntimeError('Het online versiebestand is niet bevestigd.')
        (PRIVATE / 'last-publish.json').write_bytes(serialized)
    print('Gepubliceerd:', CONFIG['publicUrl'], 'commit', commit[:12], flush=True)


def main():
    parser = argparse.ArgumentParser(description='IJsbaan website publiceren')
    action = parser.add_mutually_exclusive_group(required=True)
    action.add_argument('--save-login', action='store_true')
    action.add_argument('--inspect', action='store_true')
    action.add_argument('--check', action='store_true', help='Vergelijk de website met de huidige commit')
    action.add_argument('--publish', action='store_true', help='Publiceer de gecommitte website')
    args = parser.parse_args()
    if args.save_login:
        save_password(getpass.getpass('FTP-wachtwoord (wordt niet getoond): '))
        print('Inloggegevens lokaal versleuteld opgeslagen voor deze Windows-gebruiker.')
        return
    if args.inspect:
        with connect() as ftp:
            print('Beveiligde FTPS-verbinding:', CONFIG['host'], ftp.sock.version())
            print('Startmap:', ftp.pwd())
            ftp.cwd(CONFIG['remoteRoot'])
            print('Webmap:', ftp.pwd())
            for name, facts in ftp.mlsd():
                print(facts.get('type'), name, facts.get('size', ''))
        return
    if args.publish or args.check:
        commit, files = snapshot()
        if args.publish:
            publish(commit, files)
        else:
            verify(files)
            from sponsor_admin import action
            health = action('health')
            if health['commit'] != commit or not health['enabled']:
                raise RuntimeError('De sponsorbackend loopt achter op de commit.')
        return
    parser.error('Kies --publish, --check, --inspect of --save-login.')


if __name__ == '__main__':
    try:
        main()
    except Exception as error:
        print('Publicatie gestopt:', str(error), file=sys.stderr)
        sys.exit(1)
