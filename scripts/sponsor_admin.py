"""Scoped sponsor maintenance over HTTPS; secrets stay in local Windows DPAPI storage."""
import argparse
import json
from urllib.parse import urlencode
from urllib.request import Request, urlopen
from hosting import PRIVATE, crypt, CONFIG


def credentials():
    config = json.loads(crypt((PRIVATE / 'sponsor-secrets.dpapi').read_bytes(), False))
    if config['site_url'] != CONFIG['publicUrl'].rstrip('/') or config['db']['name'] != 'ijsbaan_ijsbaannederbetuwe':
        raise RuntimeError('Sponsorconfig hoort niet bij dit project.')
    return config


def action(name, **params):
    config = credentials()
    request = Request(CONFIG['publicUrl'] + 'api/sponsor.php?action=' + name,
                      data=urlencode(params).encode(),
                      headers={'X-Maintenance-Key': config['maintenance_key'], 'Cache-Control': 'no-cache'})
    with urlopen(request, timeout=90) as response:
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
