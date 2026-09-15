"""Live sponsor checks. Optional bounded edit of the existing hidden N.N. profile, restored afterwards."""
import argparse
from html.parser import HTMLParser
from http.cookiejar import CookieJar
import json
from pathlib import Path
from urllib.error import HTTPError
from urllib.parse import urlencode
from urllib.request import Request, build_opener, HTTPCookieProcessor
from uuid import uuid4
from sponsor_admin import action
from hosting import CONFIG

BASE=CONFIG['publicUrl'].rstrip('/')

class Form(HTMLParser):
    def __init__(self, body):
        super().__init__(); self.values={}; self.select=None; self.feed(body.decode('utf-8'))
    def handle_starttag(self,tag,attrs):
        a=dict(attrs)
        if tag=='input' and a.get('name'):
            if a.get('type') not in ('file','checkbox') or (a.get('type')=='checkbox' and 'checked' in a): self.values[a['name']]=a.get('value','')
        if tag=='select':self.select=a.get('name')
        if tag=='option' and self.select and 'selected' in a:self.values[self.select]=a.get('value','')
    def handle_endtag(self,tag):
        if tag=='select':self.select=None

def main():
    parser=argparse.ArgumentParser();parser.add_argument('--exercise-hidden-profile',action='store_true');parser.add_argument('--expected-public',type=int,help='Expected visible sponsor count after a confirmed addition');args=parser.parse_args()
    browser=build_opener(HTTPCookieProcessor(CookieJar()))
    def request(path,payload=None,logo=None,origin=BASE):
        headers={'Cache-Control':'no-cache','Accept':'application/json' if payload else 'text/html'}
        data=None
        if payload is not None:
            headers['Origin']=origin
            if logo:
                boundary='ijsbaan-'+uuid4().hex;chunks=[]
                for key,value in payload.items():chunks.append((f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n').encode())
                chunks.append((f'--{boundary}\r\nContent-Disposition: form-data; name="logo"; filename="logo.webp"\r\nContent-Type: image/webp\r\n\r\n').encode()+logo+b'\r\n')
                chunks.append((f'--{boundary}--\r\n').encode());data=b''.join(chunks);headers['Content-Type']='multipart/form-data; boundary='+boundary
            else:data=urlencode(payload).encode()
        try:r=browser.open(Request(BASE+path,data=data,headers=headers),timeout=25)
        except HTTPError as e:r=e
        with r:return r.status,dict(r.headers),r.read()
    before=action('health')
    for path in ['/beheer/?partners=1','/beheer/?partners=1&edit=excel-2026-03','/beheer/?partner_logo=excel-2026-03']:
        status,headers,body=request(path);assert status==401 and b'name="profile_id"' not in body
    assert request('/beheer/',{'action':'profile-save','csrf':'x'})[0]==401
    assert request('/api/partners.php?logo=excel-2026-03')[0]==404
    status,headers,body=request('/api/partners.php');assert status==200
    fragments=json.loads(body)['fragments']
    assert 'Huverba B.V.' in fragments['grid'] and 'N.N.' not in body.decode()
    public_count=fragments['grid'].count('class="partner-item"')
    assert public_count>0
    if args.expected_public is not None: assert public_count==args.expected_public
    assert fragments['strip'].count('class="partner-item"')==public_count
    assert all(key not in body.decode() for key in ('source_note','package_amount','contact_name','logo_file'))
    status,_,home=request('/?check='+uuid4().hex)
    assert status==200 and b'Huverba B.V.' in home and f'{public_count} sponsors'.encode() in home and b'N.N.' not in home
    print(f'PASS: all {public_count} public sponsors in slider and wall, crawlable HTML, hidden profile/logo protected.')
    ticket=action('admin-link')['ticket'];assert request('/beheer/',{'action':'login','ticket':ticket})[0]==200
    status,_,body=request('/beheer/?partners=1');assert status==200 and b'N.N.' in body and body.count(b'data-label="Bedrijf"')>=public_count+1
    status,_,body=request('/beheer/?partners=1&edit=excel-2026-03');saved=Form(body).values
    assert saved['profile_id']=='excel-2026-03' and 'visible' not in saved
    assert request('/beheer/',{**saved,'csrf':'invalid'})[0]==403
    assert request('/beheer/',saved,origin='https://example.com')[0]==403
    assert request('/beheer/',{**saved,'website':'javascript:alert(1)'})[0]==422
    assert request('/beheer/',{**saved,'revision':'0'})[0]==409
    print('PASS: authenticated profiles and amount grouping; invalid CSRF, origin, URL and stale edit rejected.')
    if args.exercise_hidden_profile:
        assert saved['name']=='N.N.' and saved['amount']=='5000'
        root=Path(__file__).resolve().parents[1]
        logo=next((root/'dist/assets/partners').glob('excel-2026-18-*.webp')).read_bytes()
        try:
            changed={**saved,'description':'Interne controle – niet openbaar'}
            status,_,body=request('/beheer/',changed,logo=logo);assert status==200 and json.loads(body)['ok']
            status,_,body=request('/beheer/?partners=1&edit=excel-2026-03');current=Form(body).values
            assert current['description']==changed['description'] and 'visible' not in current
            status,headers,body=request('/beheer/?partner_logo=excel-2026-03');assert status==200 and headers['Content-Type']=='image/webp' and body[8:12]==b'WEBP'
            assert request('/api/partners.php?logo=excel-2026-03')[0]==404
            assert request('/beheer/',saved)[0]==409
            print('PASS: hidden profile edit and logo upload persisted, private preview works, public access remains blocked.')
        finally:
            status,_,body=request('/beheer/?partners=1&edit=excel-2026-03');current=Form(body).values
            status,_,body=request('/beheer/',{**saved,'csrf':current['csrf'],'revision':current['revision'],'remove_logo':'1'})
            assert status==200 and json.loads(body)['ok']
        status,_,body=request('/beheer/?partners=1&edit=excel-2026-03');restored=Form(body).values
        assert all(restored.get(k)==v for k,v in saved.items() if k not in ('csrf','revision'))
        assert request('/beheer/?partner_logo=excel-2026-03')[0]==404
        print('PASS: original hidden profile restored; uploaded test copy remains private, no active logo.')
    after=action('health');assert all(before[k]==after[k] for k in ('applications','tests','pending'))
    print('PASS: application counts and mail states unchanged.')

if __name__=='__main__':main()
