import json,sys,time
from pathlib import Path
from html.parser import HTMLParser
from datetime import datetime,timezone
from client import Client,SITE
sys.path.insert(0,str(SITE/'scripts'))
from check_partners_live import Form
class Elements(HTMLParser):
 def __init__(self,body):super().__init__();self.tags=[];self.feed(body.decode('utf-8') if isinstance(body,bytes) else body)
 def handle_starttag(self,tag,attrs):self.tags.append((tag,dict(attrs)))
def clean(body):return {k:v for k,v in Form(body).values.items() if k not in ('csrf','action')}
OUT=SITE/'.deploy/srv01/2026-10-12';OUT.mkdir(parents=True,exist_ok=True)
client=Client()
health=client.maintenance('health');(OUT/'health-before.json').write_text(json.dumps(health,indent=2),encoding='utf-8')
_,body=client.request('login',ticket=client.maintenance('admin-link')['ticket']);assert json.loads(body)['ok']
_,body=client.request('admin-read',path='/beheer/?partners=1')
paths=sorted({a['href'] for t,a in Elements(body).tags if t=='a' and a.get('href','').startswith('/beheer/?partners=1&edit=')})
rows=[]
for i,path in enumerate(paths):
 _,body=client.request('admin-read',path=path);rows.append(clean(body))
 if (i+1)%15==0:print(f'Profiles checked: {i+1}/{len(paths)}',flush=True)
 time.sleep(.08)
(OUT/'profiles-before.json').write_text(json.dumps(rows,indent=2,ensure_ascii=False),encoding='utf-8')
for path,filename in [('/api/partners.php','public-before.json'),('/','home-before.html'),('/site-version.json','version-before.json')]:
 _,body=client.request('public',path=path);(OUT/filename).write_bytes(body)
_,body=client.request('admin-read',path='/beheer/?partners=1&new=1');client.request('logout',csrf=Form(body).values['csrf'])
print(json.dumps({'profiles':len(rows),'visible':sum(p.get('visible')=='1' and p['edition_id']=='opheusden-2026' for p in rows),'names':[p['name'] for p in rows],'logged_out':True},ensure_ascii=False))
