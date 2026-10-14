# B&P-logo uit mail van 14 oktober 2026

Het gevraagde bedrijf is het bestaande profiel `excel-2026-11`,
**B&P Boomkwekerijen**, website `https://www.bpboomkwekerijen.eu`, editie
`opheusden-2026`. De momentopname van 12 oktober toont dit profiel zonder logo.
Er is geen nieuw profiel nodig.

## Origineel en webversie

De opdrachtgever wees de doorgestuurde mail “FW: IJsbaan Opheusden 2026.” van
14 oktober aan. Deze mail is via de bestaande Gmail-koppeling gevonden. De
originele JPG bevat een roze drukcontour; de EPS-bijlage bevat de schone versie.
Gebruik daarom de EPS, niet een uitsnede uit de screenshot of de e-mailhandtekening.

- Origineel: `source-assets/partners/excel-2026-11.eps`.
- Export van dat origineel: `source-assets/partners/excel-2026-11.png`.
- Webversie: `dist/assets/partners/excel-2026-11-cbbfb80fb1.webp`,
  236 × 180 pixels, 13.690 bytes, transparant.
- De EPS is ongewijzigd op 300 dpi gerenderd met Ghostscript 10.08.0, SAFER,
  EPSCrop en pngalpha. Vervolgens `optimize_images.optimize_partner` gebruikt.
  De officiële converter staat alleen tijdelijk in de genegeerde werkmap;
  geen systeeminstallatie. Downloadhash:
  `52a91b8bf09298788d7a57b9206127026c23eacd75405f0a131e26dc381dce50`.
  Bron: https://github.com/ArtifexSoftware/ghostpdl-downloads/releases/tag/gs10080.

## Afgeleverd op 14 oktober 2026

Het originele B&P-logo is via het bestaande sponsorbeheer opgeslagen op profiel
`excel-2026-11`, van revision 1 naar 2. Naam, website, bijdrage, editie, volgorde,
zichtbaarheid en overige profielvelden zijn exact behouden. Bij deze B&P-wijziging geen nieuw profiel,
sponsoraanvraag of mail aangemaakt. B&P staat eenmaal in de sponsorstrook en eenmaal
in het volledige overzicht. De verse primaire homepage en publieke afbeelding
zijn teruggelezen; de live WebP is 236 × 180 pixels met transparantie.

Publiek logo: https://ijsbaannederbetuwe.nl/api/partners.php?logo=excel-2026-11&v=2

De openbare WebP wordt door de bestaande backend opnieuw gecodeerd. De zichtbare
pixels zijn op witte én zwarte achtergrond vergeleken met de geoptimaliseerde
bron (maximale RMS 1,78); ook de transparantie is gecontroleerd. Onzichtbare RGB
in volledig transparante pixels is geen betrouwbare vergelijking na hercodering.
De opslagbevestiging is bewaard vóór de controle. Er is eenmaal geüpload.

De echte publieke pagina is op desktop (1280 × 900) en mobiel (390 × 844)
via een uitsluitend lokaal, alleen-lezen venster op de live hosting gecontroleerd.
Het logo laadt, behoudt zijn verhouding en veroorzaakt geen horizontale overloop.
Dit venster gebruikt verse HTTPS-antwoorden van de hosting; geen lokaal aangepaste
sponsorpreview. Bronhashes staan bij het private controlebewijs.

## Opgeloste verbindingsblokkade

De eerste pc- en SRV01-pogingen liepen vóór TCP/TLS vast. De gebruiker bevestigde
dat de website via 4G/5G wel opende en vroeg op 14 oktober om een tijdelijke
VPN-achtige route. De oorzaak van het verschil per netwerk is niet vastgesteld.

De officiële, geldig door Cloudflare ondertekende WARP-client is tijdelijk
geïnstalleerd. De gebruiker gaf expliciet akkoord op de gratis dienstvoorwaarden,
uitsluitend tijdelijk gebruik voor deze IJsbaan-publicatie en verwijderen na
controle. `warp-cli mode proxy` met poort 40000 gaf `WarpProxy` en
`Packet Tunnel: false`; de luisterpoort was alleen `127.0.0.1`.

Alleen het upload- en verificatieproces gebruikte
`curl --proxy socks5h://127.0.0.1:40000`, met gewone TLS-certificaatcontrole en
zonder automatische herhaling van onzekere writes. Credentials kwamen uit de
bestaande lokale DPAPI-bestanden en bleven lokaal/in het procesgeheugen; niets
naar GitHub-secrets, een publieke helper of een extern proxyformulier gekopieerd.
De bestaande tijdelijke SRV01-routes zijn gesloten gebleven.

Niet opnieuw uploaden op basis van de oude momentopname zonder logo. Het actuele
profiel heeft revision 2. Private bewijzen:
`.deploy/bp-logo-warp-20261014/verified.json`, `upload-response.json`,
`profile-before.html`, `profile-after.html`, `public-after.json`,
`home-after.html`, `browser-source-checks.json` en `proxy-proof.json`.
Deze bestanden bevatten waar nodig private profielgegevens en blijven buiten Git.

De logo-assets zijn eerder gecommit in `5826f67`; een Git-push alleen veranderde
het databaseprofiel niet. Deze beheerwijziging heeft de statische siteversie niet
veranderd. De primaire hostingworkflow blijft apart van de uitgevoerde opslag.

## Afronding en opruiming

Ook de oorspronkelijke sponsorlijst van 12 oktober is via dezelfde verbinding
volledig afgerond: zie `../20261012/README.md`. Het eindoverzicht bevat 67 zichtbare
sponsors en 60 geladen logo's. De volledige carrousel is geverifieerd op desktop
(17 pagina's, inclusief 64–67 en terugkeer) en mobiel (34 pagina's, inclusief
66–67 en terugkeer). Geen kapotte afbeeldingen of horizontale overloop.

Het wachtende B&P-dossier en de negen sponsorwachtdossiers zijn pas na controle
privé gearchiveerd; beide wachtrijen zijn leeg. Beheersessies zijn uitgelogd.
De WARP-verbinding en de taakregistratie zijn verwijderd en de MSI-deïnstallatie
is met exitcode 0 voltooid. WARP-service, programma, productregistratie en de
luisterpoorten 40000/8767 zijn daarna afwezig bevestigd. Bewijs:
`browser-verified.json`, `cleanup.json` en de private uninstall-log.

Dit was een tijdelijke route voor de door de gebruiker gevraagde IJsbaan-wijzigingen;
er is geen permanente VPN of nieuwe hostingcredential achtergelaten. De oorzaak
van de oorspronkelijke netwerkafhankelijke onbereikbaarheid is niet gewijzigd.
