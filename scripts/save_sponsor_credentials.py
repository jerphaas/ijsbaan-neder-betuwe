"""Store sponsor database/mail secrets encrypted for this Windows user."""
import getpass
import json
import secrets
from hosting import PRIVATE, crypt

path = PRIVATE / 'sponsor-secrets.dpapi'
if path.exists():
    raise SystemExit('Opgeslagen sponsorgegevens bestaan al; hergebruik ze.')
data = {
    'db': {'host':'localhost','name':'ijsbaan_ijsbaannederbetuwe','user':'ijsbaan_ijsbaannederbetuwe',
           'password':getpass.getpass('Databasewachtwoord: ')},
    'smtp': {'host':'mail.ijsbaannederbetuwe.nl','port':587,'user':'info@ijsbaannederbetuwe.nl',
             'password':getpass.getpass('Mailwachtwoord: ')},
    'from_email':'info@ijsbaannederbetuwe.nl',
    'organizer_email':'tonkeuken@gmail.com',
    'maintenance_key':secrets.token_hex(32),
    'form_key':secrets.token_hex(32),
    'site_url':'https://ijsbaannederbetuwe.nl',
    'enabled':False
}
PRIVATE.mkdir(exist_ok=True)
path.write_bytes(crypt(json.dumps(data).encode(),True))
print('Database- en mailgegevens versleuteld opgeslagen; geen geheimen in Git.')
