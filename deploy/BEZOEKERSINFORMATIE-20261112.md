# HKV Ochten, catering en openingstijden vandaag

Opdrachtgever bevestigde op 12 november 2026 HKV Ochten als sponsor van de
boarding, BeaupainFood voor catering en drankjes, en een
dagmelding die pas vanaf de opening zichtbaar mag zijn.

Laatste gebruikerscorrectie op 12 november: **Steven Beaupain & Sanne Slootweg
uit Kesteren** verzorgen de catering. Dit vervangt de eerdere vermelding van
alleen Steven met Opheusden als woonplaats. Alleen de cateringtekst en deze
bronnotities wijzigen; het logo en de sponsorprofielen blijven gelijk.

## Bron en sponsoropslag

HKV komt ook voor in Tons mail van 6 november. Het originele beeldmerk en
woordmerk zijn uit het inline header-SVG van https://hkvochten.nl/ gehaald.
De officiële huisstijlkleur uit de bijbehorende CSS is #001a40. Alleen gerasterd
en via `scripts/optimize_images.py::optimize_partner` verkleind; geen nieuw logo
ontworpen. Logo-upload: `hkv-ochten-16388d4110.webp`, 480 × 105 px.

Via bestaand sponsorbeheer opgeslagen als `sp-b465c049bf3ea84b079f67e2`,
revision 1, website https://www.hkvochten.nl/, omschrijving
“Sponsor van de boarding”. Eerst naam, domein en editie op dubbelingen
gecontroleerd; daarna opgeslagen velden, logo, beide publieke blokken en
echte homepage teruggelezen. 74 profielen, 73 zichtbaar. Overige profielen,
aanvragen en mailtellers bleven gelijk. Geen bijdragebedrag verondersteld,
geen aanvraag of mail verstuurd. Privaat bewijs: `.deploy/hkv-20261112/`.

BeaupainFood gebruikt het originele aangeleverde logo uit het eerdere dossier
`.deploy/sponsor-beaupain-pizzamore-20261101/logos/image1.png`, opnieuw via de
bestaande optimizer verwerkt tot `assets/partners/beaupainfood-2540f8d53a.webp`.
Het cateringblok verwijst naar https://www.beaupainfood.nl/.
De twee bestaande sponsorprofielen zijn niet gewijzigd.

## Dagmelding

`opening-hours.js` gebruikt het bevestigde rooster uit `edition.js` voor zowel
de tabel als de dagmelding. Vanaf 11 december verschijnt de melding boven de
hero, inclusief afwijkingen en sluitingsdagen. Daarvoor en na het bekende
rooster is zij verborgen. De datum is altijd Europe/Amsterdam; een open tab
ververst iedere minuut en bij terugkeer uit de achtergrond. Er is geen
productieparameter om de datum te manipuleren.

Vier tests dekken alle 24 bekende dagen, de Nederlandse middernachtgrens,
scholen/feestdagen en de zichtbaarheid/verversing van de gemonteerde melding.
Uitvoeren met `node tests/opening-hours.test.mjs` (de afzonderlijke Node
testrunner kan in de Windows-sandbox op spawn EPERM stranden).

Vooraf bekeken op desktop en mobiel via een lokale kopie met gecontroleerde
testdatum. 11 december: 19.00–22.00 zonder entree; 14 december: publiek
15.00–20.00; 25 december: gesloten. Gemeten breedtes 1265 en 375 px zonder
horizontale overloop. Het productie-script bevat geen testklok.

## Publicatie en verse controle

Websitecommit `9e76f48f9e8576b210267644117b7a88b35fd14c` is gepusht en op
12 november gepubliceerd. GitHub Pages-run `43226026279` slaagde;
hoofdhosting-run `43226026434` was overgeslagen. Daarom is de bestaande,
gedocumenteerde DirectAdmin-route via de lokale WARP-proxy gebruikt.

Scope: `opening-hours.js`, `today.js`, `visit-extras.css`, het Beaupain-logo,
`schedule.js`, `index.html` en daarna `site-version.json`. De nieuwe locaties
gaven vooraf 404; bestaande bytes kwamen overeen met live commit `7675fd0`.
Private backups van bestaande bestanden zijn exact teruggelezen vóór
overschrijven. Alle nieuwe bytes en de echte homepage zijn via HTTPS exact
vergeleken, buiten de twee ongewijzigde sponsorblokken met 73 sponsors.
Versiebewijs vermeldt `deployment=visitor-information-only`; dit is geen
volledige backendpublicatie. Geen configuratie, aanvragen of mail gewijzigd.

De browsercontrole gebruikte verse ongewijzigde publieke responses via een
alleen-lezen loopbackviewer. De directe IAB-route via de eerder niet-actieve
PAC-helper is hiermee niet gerepareerd. Desktop 1265 px, mobiel 375 px, beide
zonder horizontale overloop. Alle 73 sponsors kwamen volledig voorbij in
19 desktop- en 37 mobiele carrouselstappen, inclusief terugkeer naar het begin;
alle aanwezige logo's waren geladen. HKV-logo, website en boardingvermelding
zijn ook in het volledige overzicht bekeken. Het cateringblok is op beide
formaten gecontroleerd; de dagmelding was op de echte datum verborgen.
Alle 24 roosterdagen zijn via de drie periodeknoppen teruggelezen.
Geen browserconsolefouten. De tijdelijke viewport is teruggezet en de viewer
gestopt. `python scripts/check_seo.py --live` slaagde voor deze commit.

Privaat bewijs: `.deploy/visitor-info-20261112/`, inclusief voor/na-bestanden,
backup- en uploadontvangsten, versie, bronhashes, carrouselreeksen en screenshots.
De eenmalige begrensde publicatiehelper staat in
`C:/Users/huver/Documents/Codex/2026-11-12/ij/work/publish_visitor_info.py`.
Niet opnieuw uitvoeren zonder actuele bron- en ontvangstcontrole.
