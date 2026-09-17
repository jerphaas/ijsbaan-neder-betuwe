"""Scoped sponsor maintenance over HTTPS; secrets stay in local Windows DPAPI storage."""
import argparse
import json
import hashlib
import hmac
import os
from urllib.parse import urlencode
from urllib.request import Request, urlopen
from hosting import PRIVATE, crypt, CONFIG


def credentials():
    config = json.loads(crypt((PRIVATE / 'sponsor-secrets.dpapi').read_bytes(), False))
    if config['site_url'] != CONFIG['publicUrl'].rstrip('/') or config['db']['name'] != 'ijsbaan_ijsbaannederbetuwe':
        raise RuntimeError('Sponsorconfig hoort niet bij dit project.')
    return config


CONFIG_KEYS = ('db', 'smtp', 'maintenance_key', 'form_key', 'site_url', 'from_email', 'organizer_email')


def config_fingerprint(config):
    payload = {key: config[key] for key in CONFIG_KEYS}
    return hashlib.sha256(json.dumps(payload, sort_keys=True, separators=(',', ':'), ensure_ascii=True).encode()).hexdigest()


def validate_remote_config(config):
    expected = os.environ.get('IJSBAAN_CONFIG_SHA256')
    if not expected:
        if os.environ.get('GITHUB_ACTIONS') == 'true':
            raise RuntimeError('GitHub environment-secret IJSBAAN_CONFIG_SHA256 ontbreekt.')
        expected = config_fingerprint(credentials())
    if not hmac.compare_digest(config_fingerprint(config), expected):
        raise RuntimeError('De afgeschermde sponsorconfig wijkt af van de bevestigde configuratie.')
    if os.environ.get('GITHUB_ACTIONS') == 'true' and not config.get('enabled'):
        raise RuntimeError('Sponsorbackend is uitgeschakeld; automatische publicatie gestopt.')


def maintenance_key():
    key = os.environ.get('IJSBAAN_MAINTENANCE_KEY')
    if key:
        return key
    if os.environ.get('GITHUB_ACTIONS') == 'true':
        raise RuntimeError('GitHub environment-secret IJSBAAN_MAINTENANCE_KEY ontbreekt.')
    return credentials()['maintenance_key']


def action(name, **params):
    request = Request(CONFIG['publicUrl'] + 'api/sponsor.php?action=' + name,
                      data=urlencode(params).encode(),
                      headers={'X-Maintenance-Key': maintenance_key(), 'Cache-Control': 'no-cache'})
    with urlopen(request, timeout=30) as response:
        if not response.url.startswith(CONFIG['publicUrl']):
            raise RuntimeError('Onverwachte omleiding van de sponsor-API.')
        result = json.load(response)
    if not result.get('ok'):
        raise RuntimeError('Sponsoronderhoud niet bevestigd.')
    return result


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='Controleer sponsoraanvragen of herhaal alleen nog niet verzonden e-mails.')
    parser.add_argument('action', choices=['health', 'retry'])
    parser.add_argument('--reference', default='')
    args = parser.parse_args()
    print(json.dumps(action(args.action, reference=args.reference), indent=2, ensure_ascii=False))
