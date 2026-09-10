"""Check the site's crawlable content and deployed HTTP behavior."""
import argparse
from collections import Counter
from concurrent.futures import ThreadPoolExecutor
from html.parser import HTMLParser
import json
from pathlib import Path
import re
import subprocess
from urllib.error import HTTPError
from urllib.parse import urlsplit
from urllib.request import Request, urlopen
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
DIST = ROOT / 'dist'
BASE = json.loads((ROOT / 'deploy.json').read_text(encoding='utf-8'))['publicUrl']


class Page(HTMLParser):
    def __init__(self):
        super().__init__()
        self.elements = []
    def handle_starttag(self, tag, attrs):
        self.elements.append((tag, dict(attrs)))


def validate():
    html = (DIST / 'index.html').read_text(encoding='utf-8')
    page = Page()
    page.feed(html)
    elements = page.elements
    assert sum(tag == 'h1' for tag, _ in elements) == 1, 'Er moet precies een H1 zijn.'
    ids = [attrs['id'] for _, attrs in elements if 'id' in attrs]
    assert all(count == 1 for count in Counter(ids).values()), 'Dubbele HTML-id.'
    assert ('html', {'lang':'nl'}) in elements, 'Nederlandse taal ontbreekt.'
    titles = re.findall(r'<title>([^<]+)</title>', html)
    assert len(titles) == 1 and 'Schaatsen in' in titles[0], 'Paginatitel ontbreekt.'
    metas = [a for t,a in elements if t == 'meta']
    def meta(name):
        result = [a['content'] for a in metas if a.get('name', a.get('property')) == name]
        assert len(result) == 1, f'Metadata ontbreekt of is dubbel: {name}'
        return result[0]
    assert 100 <= len(meta('description')) <= 190
    assert 'noindex' not in meta('robots') and 'max-image-preview:large' in meta('robots')
    assert meta('og:url') == BASE
    assert meta('og:site_name') == 'IJsbaan Neder-Betuwe'
    for name in ('og:image', 'og:image:alt', 'twitter:card', 'twitter:image:alt'):
        assert meta(name)
    assert [a['href'] for t,a in elements if t == 'link' and a.get('rel') == 'canonical'] == [BASE]
    images = [a for t,a in elements if t == 'img']
    for image in images:
        assert 'alt' in image, 'Afbeelding mist alt-attribuut.'
        assert image['alt'] or image.get('aria-hidden') == 'true', 'Inhoudelijke afbeelding mist alt-tekst.'
        assert int(image['width']) > 0 and int(image['height']) > 0
        assert image['src'].endswith('.webp'), 'Gebruik de geoptimaliseerde webkopie.'
    hero = next(a for a in images if a.get('class') == 'hero-photo')
    assert hero.get('fetchpriority') == 'high' and hero.get('loading') != 'lazy'
    assert sum((DIST / a['src']).stat().st_size for a in images) < 550_000, 'Afbeeldingsbudget overschreden.'
    for tag, attrs in elements:
        targets = [attrs[key] for key in ('src', 'href') if key in attrs]
        if 'srcset' in attrs:
            targets += [part.strip().split()[0] for part in attrs['srcset'].split(',')]
        for target in targets:
            if target.startswith('#'):
                assert target == '#' or target[1:] in ids, f'Anker bestaat niet: {target}'
            elif target and not urlsplit(target).scheme:
                assert (DIST / urlsplit(target).path).is_file(), f'Bestand ontbreekt: {target}'
    raw = re.findall(r'<script type="application/ld\+json">([\s\S]*?)</script>', html)
    assert len(raw) == 1
    schema = json.loads(raw[0])
    assert schema['@context'] == 'https://schema.org'
    graph = schema['@graph']
    assert {item['@type'] for item in graph} == {'Organization','WebSite','WebPage'}
    assert 'openingHours' not in raw[0] and 'endDate' not in raw[0], 'Onbevestigde evenementgegevens in schema.'
    for item in graph:
        assert item['url'] == BASE
    namespaces = {'s':'http://www.sitemaps.org/schemas/sitemap/0.9','i':'http://www.google.com/schemas/sitemap-image/1.1'}
    sitemap = ET.fromstring((DIST / 'sitemap.xml').read_bytes())
    assert [e.text for e in sitemap.findall('s:url/s:loc',namespaces)] == [BASE], 'Alleen de canonieke pagina hoort in de sitemap.'
    sitemap_images = [e.text for e in sitemap.findall('s:url/i:image/i:loc',namespaces)]
    assert len(sitemap_images) == 3
    assert all((DIST / url.removeprefix(BASE)).is_file() for url in sitemap_images)
    robots = (DIST / 'robots.txt').read_text(encoding='utf-8')
    assert 'Allow: /' in robots and f'Sitemap: {BASE}sitemap.xml' in robots
    assert 'Disallow:' not in robots
    verification = DIST / 'google4244cf326cd58185.html'
    assert verification.read_bytes().strip() == b'google-site-verification: google4244cf326cd58185.html'
    subprocess.run(['node', str(ROOT / 'scripts/update_seo.mjs'), '--check'], check=True)
    print('SEO-controle: metadata, schema, sitemap, alt-teksten, links en afbeeldingsbudget in orde.')


def validate_live():
    from uuid import uuid4
    nonce = uuid4().hex
    def fetch(url):
        try:
            response = urlopen(Request(url, headers={'Cache-Control':'no-cache'}), timeout=20)
        except HTTPError as error:
            response = error
        with response:
            return response.status, response.url, dict(response.headers), response.read()
    urls = [BASE, BASE+'robots.txt', BASE+'sitemap.xml', BASE+'index.html',
            BASE.replace('https:', 'http:'), BASE.replace('https://','https://www.'),
            BASE+'google4244cf326cd58185.html', BASE+'site-version.json', BASE+'pagina-bestaat-niet-'+nonce]
    with ThreadPoolExecutor(max_workers=4) as pool:
        results = list(pool.map(fetch, urls))
    for i, (status, url, headers, body) in enumerate(results):
        assert status == (404 if i == 8 else 200), (urls[i],status)
        if i in (3,4,5):
            assert url == BASE, f'Omleiding niet canoniek: {url}'
        if i == 0:
            assert 'noindex' not in headers.get('X-Robots-Tag','')
            assert b'application/ld+json' in body
        if i in (6,7):
            assert 'noindex' in headers.get('X-Robots-Tag','')
        if i == 8:
            assert b'Even van de baan geraakt?' in body
        print('HTTP-controle:', status, urls[i])
    current = subprocess.check_output(['git','-C',str(ROOT),'rev-parse','HEAD']).decode().strip()
    assert json.loads(results[7][3])['commit'] == current, 'Live versie loopt achter.'
    print('Live SEO, redirects, Google-verificatie en echte 404 bevestigd voor', current[:12])


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--live', action='store_true')
    args = parser.parse_args()
    validate()
    if args.live:
        validate_live()
