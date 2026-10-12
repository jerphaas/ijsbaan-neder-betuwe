"""Register only the screenshot's missing sponsors via the existing admin form."""
import argparse,base64,hashlib,json,re,sys,time
from datetime import datetime,timezone
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import parse_qs,urlparse
from uuid import uuid4
from client import Client,SITE
sys.path.insert(0,str(SITE/'scripts'))
from check_partners_live import Form
OUT=SITE/'.deploy/srv01/2026-10-12'
FIELDS=('name','website','description','edition_id','amount','tier','sort_order','visible','logo_dark')
class Elements(HTMLParser):
 def __init__(self,body):super().__init__();self.tags=[];self.feed(body.decode('utf-8') if isinstance(body,bytes) else body)
 def handle_starttag(self,tag,attrs):self.tags.append((tag,dict(attrs)))
class Tiles(HTMLParser):
 def __init__(self,body):super().__init__();self.rows=[];self.row=None;self.naming=False;self.feed(body)
 def handle_starttag(self,tag,attrs):
  a=dict(attrs)
  if tag=='li' and a.get('class')=='partner-item':self.row={'name':'','website':'','image':None,'dark':False}
  if self.row is not None:
   if tag in ('a','div') and 'partner-tile' in a.get('class',''):self.row.update(website=a.get('href',''),dark='partner-dark' in a.get('class',''))
   if tag=='img':self.row['image']=a
   if tag=='span' and a.get('class') in ('partner-name','partner-wordmark'):self.naming=True
 def handle_data(self,data):
  if self.row is not None and self.naming:self.row['name']+=data
 def handle_endtag(self,tag):
  if tag=='span':self.naming=False
  if tag=='li' and self.row is not None:self.rows.append(self.row);self.row=None

def normal(value):return re.sub('[^a-z0-9]','',value.lower()).removesuffix('bv')
def host(value):return (urlparse(value).hostname or '').lower().removeprefix('www.')
def clean(body):return {k:v for k,v in Form(body).values.items() if k not in ('csrf','action')}
def paths(client):
 _,body=client.request('admin-read',path='/beheer/?partners=1')
 return sorted({a['href'] for t,a in Elements(body).tags if t=='a' and a.get('href','').startswith('/beheer/?partners=1&edit=')})
def read_all(client):
 rows=[]
 for path in paths(client):
  _,body=client.request('admin-read',path=path);rows.append(clean(body));time.sleep(.06)
 return rows

def public_check(client,p,profile_id):
 _,body=client.request('public',path='/api/partners.php?check='+uuid4().hex)
 fragments=json.loads(body)['fragments'];tile=None
 for block in ('strip','grid'):
  matches=[r for r in Tiles(fragments[block]).rows if r['name']==p['name']]
  assert len(matches)==1, 'Duplicate or missing public sponsor: '+p['name']
  tile=matches[0];assert tile['website']==p['website'] and tile['dark']==bool(p['logo_dark'])
  if p['logo_asset']:
   assert tile['image'] and tile['image']['alt']=='Logo van '+p['name'] and 'logo='+profile_id in tile['image']['src']
  else:assert tile['image'] is None
 proof={'public_blocks':['strip','grid']}
 if tile['image']:
  url=urlparse(tile['image']['src']);assert not url.hostname or url.hostname=='ijsbaannederbetuwe.nl'
  path=url.path+('?' + url.query if url.query else '')
  response,logo=client.request('public',path=path)
  assert response['headers']['content-type'][0]=='image/webp' and logo[8:12]==b'WEBP'
  (OUT/'logos').mkdir(exist_ok=True);(OUT/'logos'/(profile_id+'.webp')).write_bytes(logo)
  proof.update(logo_path=path,logo_sha256=hashlib.sha256(logo).hexdigest())
 return proof

def main():
 parser=argparse.ArgumentParser();parser.add_argument('--apply',action='store_true');args=parser.parse_args()
 manifest=json.loads((OUT/'manifest.json').read_text(encoding='utf-8'));baseline=json.loads((OUT/'profiles-before.json').read_text(encoding='utf-8'))
 assert len(manifest)==16 and len({r['slug'] for r in manifest})==16
 for r in manifest:assert hashlib.sha256(r['profile_json'].encode()).hexdigest()==r['sha256']
 client=Client();before=client.maintenance('health')
 _,body=client.request('login',ticket=client.maintenance('admin-link')['ticket']);assert json.loads(body)['ok']
 try:
  existing=read_all(client);by_id={p['profile_id']:p for p in existing}
  for p in baseline:assert by_id.get(p['profile_id'])==p, 'Existing profile changed since preflight: '+p['name']
  print('Fresh baseline confirmed: all 52 original profiles unchanged.',flush=True)
  decisions=[]
  for r in manifest:
   p=r['profile'];matches=[e for e in existing if e['edition_id']==p['edition_id'] and (normal(e['name'])==normal(p['name']) or (host(p['website']) and host(e['website'])==host(p['website'])))]
   assert len(matches)<=1, 'Ambiguous duplicate: '+p['name']
   decisions.append((r,matches[0] if matches else None))
  if not args.apply:
   print(json.dumps({'new':sum(saved is None for _,saved in decisions),'already_present':sum(saved is not None for _,saved in decisions),'mutations':0}));return
  expected_ids=set(by_id);receipts=[]
  for r,saved in decisions:
   p=r['profile'];slug=r['slug']
   current_ids={parse_qs(urlparse(path).query)['edit'][0] for path in paths(client)}
   assert current_ids==expected_ids,'Profile membership changed concurrently; stopped'
   if saved is None:
    intent=OUT/(slug+'.attempt.json');assert not intent.exists(),'Uncertain earlier attempt; inspect saved state before retrying'
    _,body=client.request('admin-read',path='/beheer/?partners=1&new=1');form=Form(body).values
    assert form['profile_id']=='' and form['revision']=='0' and form['application']==''
    logo=(SITE/'dist'/p['logo_asset']).read_bytes() if p['logo_asset'] else b''
    intent.write_text(json.dumps({'slug':slug,'payload_sha256':r['sha256'],'started_at':datetime.now(timezone.utc).isoformat()}),encoding='utf-8')
    _,body=client.request('save',slug=slug,csrf=form['csrf'],profile_json=r['profile_json'],logo=base64.b64encode(logo).decode())
    result=json.loads(body);assert result.get('ok');profile_id=parse_qs(urlparse(result['url']).query)['edit'][0];assert re.fullmatch('sp-[0-9a-f]{24}',profile_id)
    _,body=client.request('admin-read',path='/beheer/?partners=1&edit='+profile_id);saved=clean(body);expected_ids.add(profile_id)
   else:profile_id=saved['profile_id']
   for field in FIELDS:assert saved.get(field,'0' if field=='logo_dark' else '')==str(p[field]),'Persisted field mismatch: '+field
   proof=public_check(client,p,profile_id)
   receipt={**p,**proof,'live_profile_id':profile_id,'payload_sha256':r['sha256'],'verified_at':datetime.now(timezone.utc).isoformat(),'route':'SRV01 existing sponsor admin'}
   (OUT/(slug+'.receipt.json')).write_text(json.dumps(receipt,ensure_ascii=False,indent=2),encoding='utf-8');receipts.append(receipt)
   print('Saved and verified: '+p['name'],flush=True)
  final=read_all(client);final_map={p['profile_id']:p for p in final};assert set(final_map)==expected_ids
  for p in baseline:assert final_map[p['profile_id']]==p,'Original profile changed: '+p['name']
  assert len(final)==68 and sum(p.get('visible')=='1' and p['edition_id']=='opheusden-2026' for p in final)==67
  after=client.maintenance('health');assert all(before[k]==after[k] for k in ('applications','tests','pending')),'Application/mail counters changed'
  _,home=client.request('public',path='/');_,api=client.request('public',path='/api/partners.php?check='+uuid4().hex)
  parts=json.loads(api)['fragments']
  for block in ('strip','grid'):
   tiles=Tiles(parts[block]).rows;assert len(tiles)==67 and len({normal(t['name']) for t in tiles})==67
   assert len([t for t in tiles if host(t['website'])=='combinatiemauritz.nl'])==1
   marker=re.search(r'<!-- PARTNERS:'+block.upper()+r':START -->(.*?)<!-- PARTNERS:'+block.upper()+r':END -->',home.decode('utf-8'),re.S);assert marker and marker.group(1)==parts[block]
  (OUT/'profiles-after.json').write_text(json.dumps(final,ensure_ascii=False,indent=2),encoding='utf-8');(OUT/'home-after.html').write_bytes(home);(OUT/'public-after.json').write_bytes(api)
  (OUT/'health-after.json').write_text(json.dumps(after,indent=2),encoding='utf-8')
  report={'verified_at':datetime.now(timezone.utc).isoformat(),'source_rows':17,'added':16,'existing_preserved':'Combinatie Mauritz B.V.','stored_profiles':68,'visible_sponsors':67,'existing_52_profiles_unchanged':True,'duplicate_names_or_websites_in_requested_set':0,'applications_and_mail_unchanged':True,'homepage_sha256':hashlib.sha256(home).hexdigest(),'new_names':[p['name'] for p in receipts]}
  (OUT/'verification.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8')
  print('VERIFIED: 16 additions; 67 visible sponsors; all 52 original profiles unchanged; no duplicate, application or mail.',flush=True)
 finally:
  _,body=client.request('admin-read',path='/beheer/?partners=1&new=1');client.request('logout',csrf=Form(body).values['csrf']);print('Admin session logged out.',flush=True)
if __name__=='__main__':main()
