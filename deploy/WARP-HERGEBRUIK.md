# WARP behouden voor geautoriseerd IJsbaanbeheer

## Gebruikerskeuze en actuele status

Op 26 oktober 2026 vroeg de gebruiker Cloudflare WARP geïnstalleerd te laten
voor hergebruik. Daarna gaf hij expliciet toestemming: **“Ja, registreren en
bewaren”** voor de gratis apparaatregistratie bij Cloudflare. Na de taak gaat
de verbinding uit; client en bestaande registratie blijven bewaard. Dit
vervangt alleen de tijdelijke-installatiegrens van de opdracht van 14 oktober.
Het autoriseert geen ongevraagde websitewijzigingen, externe berichten, andere
systemen of verruiming van netwerk-/beveiligingsinstellingen.

Gecontroleerde installatie: `C:/Program Files/Cloudflare/Cloudflare WARP/`,
`warp-cli 2026.8.2110.0`, Windows-service `CloudflareWARP`. De bestaande officiële
MSI heeft een geldige Cloudflare-handtekening; installatie eindigde met code 0.
Eindcontrole: registratie aanwezig, **Disconnected**, **Always On false**,
**WarpProxy on port 40000**, **Packet Tunnel false**. Geen luisterpoort op
40000 of de tijdelijke viewerpoort 8767 na afloop.

## Hergebruik

1. Alleen bij een geautoriseerde taak eerst de doelhost en actuele bereikbaarheid
   controleren. Voor een al bekende verbindingsfout geen langdurige herhaling.
2. Lees `warp-cli --accept-tos status` en de beperkte relevante regels uit
   `settings`. Controleer dat de bestaande registratie aanwezig is; druk geen
   registratiegegevens, sleutels of identifiers af. Maak geen nieuwe registratie
   en verwijder de bestaande niet als routinematige reparatie.
3. Bevestig `Mode: WarpProxy on port 40000` en `Packet Tunnel: false` voordat
   `connect` wordt gebruikt. De proxy moet alleen op `127.0.0.1:40000` luisteren.
4. Alleen het taakproces krijgt `--proxy socks5h://127.0.0.1:40000 --noproxy ''`.
   Behoud gewone HTTPS-certificaatcontrole, vaste bestemming, beperkte time-outs
   en geen automatische herhaling van onzekere schrijfacties. Verander geen
   algemene Windows/browser-proxy, DNS, router, firewall of volledige VPN-route.
5. Gebruik het bestaande sponsorbeheer, DPAPI-auth, sessie/CSRF, revision en
   duplicaatcontrole. Een logo-/profielwijziging verstuurt geen nieuwe aanvraag
   of mail. Bewaar de opslagontvangst direct en lees de werkelijke opslag terug.
6. Log de beheersessie uit, stop eigen controleprocessen en voer `disconnect`
   uit. Verifieer **Disconnected**. Behoud installatie en apparaatregistratie.

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
