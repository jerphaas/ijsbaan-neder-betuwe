# WARP behouden voor geautoriseerd IJsbaanbeheer

## Gebruikerskeuze en actuele status

Op 26 oktober 2026 vroeg de gebruiker Cloudflare WARP geïnstalleerd te laten
voor hergebruik. Daarna gaf hij expliciet toestemming: **“Ja, registreren en
bewaren”** voor de gratis apparaatregistratie bij Cloudflare. Later vroeg hij
expliciet `https://ijsbaannederbetuwe.nl/#onze-sponsors` standaard via Cloudflare
te openen. Daarom blijft WARP nu verbonden als lokale proxy en start een
afgebakende domeinregel automatisch bij Windows-aanmelding. Dit vervangt de
eerdere afspraak om na iedere taak te disconnecten. Het autoriseert geen
ongevraagde websitewijzigingen, externe berichten, andere systemen/domeinen
of een VPN-tunnel voor de hele pc.

Gecontroleerde installatie: `C:/Program Files/Cloudflare/Cloudflare WARP/`,
`warp-cli 2026.8.2110.0`, Windows-service `CloudflareWARP`. De bestaande officiële
MSI heeft een geldige Cloudflare-handtekening; installatie eindigde met code 0.
Actuele eindstand: bestaande registratie, **Connected**, **Always On false**,
**WarpProxy on port 40000**, **Packet Tunnel false**. Alleen loopback luistert
op 40000 (WARP) en 40001 (PAC-helper). Tijdelijke viewer 8767 is gestopt.

## Automatische browserroute op deze pc

`deploy/windows/ijsbaan.pac` stuurt uitsluitend de exacte hosts
`ijsbaannederbetuwe.nl` en `www.ijsbaannederbetuwe.nl` via
`PROXY 127.0.0.1:40000; DIRECT`. Alle andere hosts, inclusief OpenAI en
bedrijfs-/LAN-adressen, krijgen `DIRECT`. De regel geldt voor het hele domein,
dus ook afbeeldingen en `/beheer/`; een URL-fragment zoals `#onze-sponsors`
is geen aparte netwerkbestemming. Bij niet-beschikbare proxy is een directe
verbinding de terugvalroute. Apps met eigen proxy-instellingen kunnen de
Windows-instelling negeren; CLI-processen gebruiken zo nodig expliciet WARP.

Windows-gebruiker `huver`: automatische configuratie
`http://127.0.0.1:40001/ijsbaan.pac`, via de ondersteunde WinINet-API ingesteld
en teruggelezen. De vooraf bestaande instelling (flags 9, lege PAC-URL) en de
oorspronkelijke eigen opstartwaarde staan lokaal in
`.deploy/windows-ijsbaan-route/before.json`; deze backup blijft behouden.
De lokale helper serveert alleen de vaste PAC en een beperkte status,
luistert uitsluitend op loopback en registreert geen browsegeschiedenis.
Er is geen firewall-, DNS-, router- of certificaatwijziging.

HKCU Run `IJsbaanDomainRoute` start de bestaande `C:/Python314/pythonw.exe`
met `deploy/windows/ijsbaan_route.py serve`. Bij aanmelden controleert de
helper eerst de bestaande WARP-proxymodus en verbindt die. Er wordt geen
nieuwe registratie gemaakt en een andere WARP-modus wordt niet overschreven.
Het markerbestand `.deploy/windows-ijsbaan-route/enabled` bepaalt of de
helper blijft draaien. Een handmatige WARP-disconnect wordt niet telkens
ongedaan gemaakt; heractiveren kan met `install`.

Vanuit de projectmap:

```powershell
python deploy/windows/ijsbaan_route.py status
python deploy/windows/ijsbaan_route.py install
python deploy/windows/ijsbaan_route.py restore
```

`restore` herstelt de oorspronkelijke gebruikersproxy, verwijdert alleen de
eigen opstartvermelding, disconnect WARP en laat de helper binnen twee seconden
stoppen. Installatie en apparaatregistratie blijven behouden. Bij een inmiddels
andere proxyconfiguratie weigert het script die blind te overschrijven.

Controle op 26 oktober: directe HTTPS-verbinding reproduceerbaar time-out;
via WARP HTTP CONNECT en SOCKS5 HTTP 200. Domeinregel getest met drie
doeldomeinvarianten en acht andere hosts (allemaal DIRECT). Werkelijke gewone
HTTPS-sponsorlink laadt in Chrome én de ingebouwde browser met 68 sponsors en
het nieuwe logo. Voor de ingebouwde browser was `PROXY` nodig; de eerste
SOCKS5-PAC gaf daar nog een verbindingsfout. Herstel is werkelijk uitgevoerd
en teruggelezen; daarna is de definitieve instelling opnieuw geactiveerd.
Opstartvermelding en herstart van de helper zijn gecontroleerd; er is geen
volledige Windows-herstart uitgevoerd. Privébewijs staat in
`.deploy/windows-ijsbaan-route/`.

Documentatie:
- https://developers.cloudflare.com/warp-client/warp-modes/#local-proxy
- https://learn.microsoft.com/en-us/deployedge/configure-microsoft-edge-proxy-support

## Hergebruik

1. Alleen bij een geautoriseerde taak eerst de doelhost en actuele bereikbaarheid
   controleren. Voor een al bekende verbindingsfout geen langdurige herhaling.
2. Lees `warp-cli --accept-tos status` en de beperkte relevante regels uit
   `settings`. Controleer dat de bestaande registratie aanwezig is; druk geen
   registratiegegevens, sleutels of identifiers af. Maak geen nieuwe registratie
   en verwijder de bestaande niet als routinematige reparatie.
3. Bevestig `Mode: WarpProxy on port 40000` en `Packet Tunnel: false` voordat
   `connect` wordt gebruikt. De proxy moet alleen op `127.0.0.1:40000` luisteren.
4. CLI-taakprocessen kunnen `--proxy socks5h://127.0.0.1:40000 --noproxy ''`
   gebruiken als zij de Windows-PAC niet ondersteunen.
   Behoud gewone HTTPS-certificaatcontrole, vaste bestemming, beperkte time-outs
   en geen automatische herhaling van onzekere schrijfacties. Verander geen
   de vastgelegde domeinlijst, DNS, router, firewall of volledige VPN-route.
5. Gebruik het bestaande sponsorbeheer, DPAPI-auth, sessie/CSRF, revision en
   duplicaatcontrole. Een logo-/profielwijziging verstuurt geen nieuwe aanvraag
   of mail. Bewaar de opslagontvangst direct en lees de werkelijke opslag terug.
6. Log de beheersessie uit en stop eigen tijdelijke controleprocessen. Behoud
   de vaste domeinroute, WARP-verbinding, installatie en apparaatregistratie.

Cloudflare-documentatie over de gekozen lokale proxymodus:
https://developers.cloudflare.com/warp-client/warp-modes/#local-proxy

## Afgeleverd sponsorlogo op 26 oktober 2026

De aangewezen Gmail-mail van Ton Keuken noemt Windkracht 10 Producties. In de
geciteerde reactie vraagt Jurgen specifiek het logo van 123ledschermhuren.nl te
gebruiken. De doorgestuurde mail bevatte de bestandsnaam, maar geen afbeelding.
Het originele logo is daarom ongewijzigd opgehaald van de bijbehorende officiële
bedrijfswebsite, die dezelfde bedrijfs- en contactgegevens toont:

https://123ledschermhuren.nl/assets/media/2611f5a6069a2dd3fbbd.svg

De PNG-export behoudt het originele ontwerp. `scripts/optimize_images.py` heeft
de WebP gemaakt (480 × 119, SHA-256
`ddd321e609cb96999deeec879ef1ea144a30dc24f6e4a912a02512e12cebef94`). Het beheer
encodeert de publieke afbeelding opnieuw; zichtbare pixels zijn tegen de bron
op wit en zwart gecontroleerd, maximale RMS 5,22.

Vanuit de al bestaande sponsoraanvraag is één profiel toegevoegd:
`sp-bf6bcfbcbddb373725eeba82`, revision 1, **Windkracht 10 Producties**, zichtbaar
voor `opheusden-2026`, met link `https://123ledschermhuren.nl/`. Er zijn daarna
69 opgeslagen profielen en 68 zichtbare sponsors. Overige profielen en publieke
tegels zijn exact behouden; aanvraag- en mailtellers bleven gelijk.

Publiek logo:
https://ijsbaannederbetuwe.nl/api/partners.php?logo=sp-bf6bcfbcbddb373725eeba82&v=1

De echte homepage, publieke API, opgeslagen profiel en logo zijn vers
teruggelezen. Browsercontrole gebruikte een uitsluitend lokaal, alleen-lezen
venster op verse publieke HTTPS-antwoorden, geen aangepaste lokale preview.
Desktop 1280 × 900: alle 17 carrouselpagina's, 65–68 en terug naar 1–4.
Mobiel 390 × 844: alle 34 pagina's, 67–68 en terug naar 1–2. Het nieuwe logo
laadt in strook én overzicht; geen horizontale overloop. Screenshots zijn
visueel beoordeeld. Beheersessie uitgelogd en viewer gestopt.

Private bronnen, ontvangsten, voor/na-bewijs en screenshots staan in
`.deploy/sponsor-123led-20261026/`. Bijdragen, aanvraagdetails en lokale
beheerantwoorden blijven buiten Git. Geen code-deploy was nodig: de wijziging
is live via sponsorbeheer opgeslagen; de statische websiteversie is niet
gewijzigd.
