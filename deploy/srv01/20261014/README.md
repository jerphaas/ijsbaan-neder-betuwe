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

## Werkelijke afleverstatus

**Voorbereid en lokaal gecontroleerd; nog niet online geplaatst.** De pc en
SRV01 bereiken de primaire hosting niet. DNS bevestigt 185.182.56.187;
HTTPS vanaf SRV01 geeft cURL 28 vóór TCP/TLS. De vaste IPv4-poorten 443, 80 en
2222 en IPv6-poort 443 lopen ook vast. Dit is geen bewijs van een wereldwijde
storing. Er is geen beheerlogin of logo-upload geprobeerd en geen sponsor
bijgemaakt. GitHub Pages-publicatie is geen bewijs van de wijziging op de
primaire website of in de sponsordatabase.

De tijdelijke SRV01-route was uitsluitend lezen, met bestaande credentials,
AES-GCM, vaste bestemmingen en TLS-controle. Geen vrije proxy, writes of mail.
Na de diagnose gesloten; constante `IJSBAAN_14_CLOSED=true` en HTTP 410 bevestigd.
Dashboardcommit voor sluiting: `de98c5303`.

## Veilige vervolgactie

De exacte wachtende update staat privé in
`.deploy/pending-logo-updates/excel-2026-11.json`. Dit is een **logo-update**, geen
wachtende sponsortoevoeging. De oude route mag niet worden heropend.

1. Controleer verse hostingbereikbaarheid via de normale route.
2. Lees het huidige B&P-profiel en de actuele `revision` via sponsorbeheer;
   controleer unieke bedrijfsnaam/website/identiteit. Bewaar de oude velden privé.
3. Upload uitsluitend deze WebP op dit bestaande profiel. Behoud naam, website,
   privébijdrage, editie, volgorde en zichtbaarheid. Geen sponsoraanvraag of mail.
4. Bewaar de opslagbevestiging onmiddellijk. Bij een onzekere uitkomst eerst
   teruglezen, nooit blind opnieuw uploaden of een profiel bijmaken.
5. Lees profiel, publieke sponsorblokken en afbeelding vers terug. B&P moet
   eenmaal per blok voorkomen. Vergelijk overige velden en mailtellers.
6. Controleer desktop/mobiel en archiveer de wachtende update pas na live bewijs.

Lokale browsercontrole: bestaande pagina met uitsluitend het B&P-logo vervangen,
1280 × 900 en 390 × 844, geen overloop, afbeelding geladen, één vermelding per
blok. Volledige slidercyclus van de bewaarde 51 sponsors per zichtbare pagina
gecontroleerd, inclusief laatste pagina en terugkeer. Dit is lokale QA, geen
actuele live sponsorstand. Bewijs in `work/bp-logo-20261014/browser-check.json`.
SEO en sponsorcatalogus slagen. Private netwerkbewijzen staan in
`.deploy/srv01/2026-10-14/`; mailinhoud en private profielvelden staan niet in Git.
