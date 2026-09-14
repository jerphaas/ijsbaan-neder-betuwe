"""Deploy only committed sponsor code into the previously verified private directory."""
import json
from pathlib import PurePosixPath
import subprocess
from hosting import CONFIG, PRIVATE, ROOT, crypt
from sponsor_admin import credentials, action

PRIVATE_REMOTE = '/domains/ijsbaannederbetuwe.nl/sponsor-private'


def snapshot():
    from publish import git
    subprocess.run(['node', 'scripts/update_sponsor_catalog.mjs', '--check'], cwd=ROOT, check=True)
    if git('status', '--porcelain', '--untracked-files=all', '--', 'server').strip():
        raise RuntimeError('Commit de serverbestanden voordat je publiceert.')
    files = {}
    for row in git('ls-tree', '-r', '-z', 'HEAD', 'server').split(b'\0'):
        if not row:
            continue
        details, name = row.decode().split('\t')
        mode, kind, blob = details.split()
        path = PurePosixPath(name).relative_to('server')
        if mode != '100644' or kind != 'blob' or any(part.startswith('.') for part in path.parts):
            raise RuntimeError('Onverwacht serverbestand: ' + name)
        files[str(path)] = git('cat-file', 'blob', blob)
    if 'bootstrap.php' not in files or 'schema.sql' not in files or not 5 <= len(files) <= 30 or sum(map(len, files.values())) > 2_000_000:
        raise RuntimeError('Onverwachte serveromvang.')
    return files


def deploy(ftp, commit, files, backup, token):
    from publish import upload, read_remote, remote_inventory
    ftp.cwd(str(PurePosixPath(PRIVATE_REMOTE).parent))
    if dict(ftp.mlsd()).get('sponsor-private', {}).get('type') != 'dir':
        raise RuntimeError('De gecontroleerde privémap ontbreekt of is een symlink.')
    ftp.cwd(PRIVATE_REMOTE)
    inventory = dict(ftp.mlsd())
    if inventory.get('config.json', {}).get('type') != 'file':
        raise RuntimeError('De afgeschermde sponsorconfig ontbreekt.')
    remote_config = json.loads(read_remote(ftp, 'config.json'))
    local_config = credentials()
    for key in ('db', 'smtp', 'maintenance_key', 'form_key', 'site_url', 'from_email', 'organizer_email'):
        if remote_config[key] != local_config[key]:
            raise RuntimeError('Lokale en remote sponsorconfig wijken af: ' + key)
    if 'app' not in inventory:
        ftp.mkd('app')
    elif inventory['app'].get('type') != 'dir':
        raise RuntimeError('Privé-appmap is geen gewone map.')
    ftp.sendcmd('SITE CHMOD 700 app')
    ftp.cwd('app')
    files = dict(files)
    files['version.json'] = json.dumps({'commit': commit}).encode()
    folders = {'.'}
    for name in files:
        folders.update(str(p) for p in PurePosixPath(name).parents)
    inventory = remote_inventory(ftp, folders)
    for name in files:
        if name not in inventory:
            continue
        if inventory[name].get('type') != 'file':
            raise RuntimeError('Privé-doelbestand is geen gewoon bestand: ' + name)
        target = backup / 'sponsor-server' / name
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_bytes(read_remote(ftp, name))
    for folder in sorted(folders, key=lambda x: (len(PurePosixPath(x).parts), x)):
        if folder == '.':
            continue
        if folder not in inventory:
            ftp.mkd(folder)
        elif inventory[folder].get('type') != 'dir':
            raise RuntimeError('Privé-doelmap is geen gewone map: ' + folder)
        ftp.sendcmd('SITE CHMOD 700 ' + folder)
    for name, content in files.items():
        upload(ftp, name, content, token)
        ftp.sendcmd('SITE CHMOD 600 ' + name)
    ftp.cwd(CONFIG['remoteRoot'])
    print(f'Sponsorbackend: {len(files)} gecommitte bestanden afgeschermd geplaatst en gecontroleerd.', flush=True)


def activate(ftp, commit, token):
    from publish import upload, read_remote
    action('migrate')
    ftp.cwd(PRIVATE_REMOTE)
    config = json.loads(read_remote(ftp, 'config.json'))
    if not config.get('enabled'):
        config['enabled'] = True
        upload(ftp, 'config.json', json.dumps(config).encode(), token)
        ftp.sendcmd('SITE CHMOD 600 config.json')
        (PRIVATE / 'sponsor-secrets.dpapi').write_bytes(crypt(json.dumps(config).encode(), True))
    ftp.cwd(CONFIG['remoteRoot'])
    health = action('health')
    if health['commit'] != commit or not health['enabled'] or health['schema'] != 1:
        raise RuntimeError('De actieve sponsorbackend is niet bevestigd.')
    print('Sponsorbackend actief: PHP', health['php'], 'schema', health['schema'], 'commit', commit[:12], flush=True)
    if health['pending']:
        print('Let op: onverzonden sponsorberichten; bekijk python scripts/sponsor_admin.py health.', flush=True)
