"""Import a confirmed local sponsor list into private profiles; never creates applications or mail."""
import argparse
import json
from pathlib import Path
from sponsor_admin import action


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('file', type=Path, help='Ignored local JSON with confirmed sponsor profiles')
    args = parser.parse_args()
    rows = json.loads(args.file.read_text(encoding='utf-8'))
    if not isinstance(rows, list) or not 1 <= len(rows) <= 200:
        raise RuntimeError('Unexpected profile count')
    before = action('health')
    result = action('profiles-import', profiles=json.dumps(rows, ensure_ascii=False))
    after = action('health')
    for key in ('applications', 'tests', 'pending'):
        if before[key] != after[key]:
            raise RuntimeError('Application or mail state changed; inspect before continuing.')
    if result['created'] + result['preserved'] != len(rows):
        raise RuntimeError('Unexpected import count')
    print(json.dumps(result))
    print('Applications and mail states preserved. No emails sent by this import.')


if __name__ == '__main__':
    main()
