# HKV Ochten, catering en openingstijden vandaag

Opdrachtgever bevestigde op 12 november 2026 HKV Ochten als sponsor van de
boarding, Steven Beaupain uit Opheusden voor catering en drankjes, en een
dagmelding die pas vanaf de opening zichtbaar mag zijn.

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
