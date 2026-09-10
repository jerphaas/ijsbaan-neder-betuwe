# IJsbaan Neder-Betuwe

Publieke website voor IJsbaan Neder-Betuwe. Editie Opheusden 2026/2027, met Kesteren als volgende editie en een uitbreidbaar overzicht voor andere locaties.

Website: https://ijsbaannederbetuwe.nl/

Broncode en bewerkbare bestanden: https://github.com/jerphaas/ijsbaan-neder-betuwe

De GitHub Pages-kopie blijft beschikbaar op https://jerphaas.github.io/ijsbaan-neder-betuwe/.

## Publicatie

De complete statische website staat in `dist/`. Er is geen build, CMS of installatie van pakketten nodig. Wijzigingen kunnen in deze projectmap worden gemaakt, of door Codex met de opdracht om de ijsbaanwebsite te wijzigen en te publiceren.

1. Pas de gewenste bestanden in `dist/` aan en bekijk het resultaat lokaal.
2. Sla de wijziging op met een Git-commit en push naar `main`.
3. Dubbelklik op `publiceer.cmd`, of voer `python scripts/publish.py --publish` uit.

De publicatieknop zet de **laatste lokale commit** online. Niet-gecommitte wijzigingen in `dist/` worden tegengehouden. Na een wijziging via de GitHub-website moet deze lokale map dus eerst worden bijgewerkt met `git pull --ff-only`. Alleen bestanden uit `dist/` gaan naar de webmap. Andere bestanden op de hosting worden behouden. Elk te vervangen bestand krijgt vooraf een lokale reservekopie; de startpagina wordt als laatste geplaatst. Daarna vergelijkt het script de openbare website en alle publieke bestanden met de commit.

`python scripts/publish.py --check` controleert de online bestanden zonder iets te wijzigen. De geplaatste versie staat ook op `https://ijsbaannederbetuwe.nl/site-version.json`.

GitHub Pages publiceert daarnaast automatisch vanuit `dist/` met `.github/workflows/pages.yml` na een push naar `main`. Die workflow publiceert niet naar de eigen hosting; daarvoor is de bovenstaande publicatieknop bedoeld.

### Opgeslagen hostingroute

- Niet-geheime instellingen: `deploy.json`.
- Beveiligde verbinding: expliciete FTPS op poort 21, `vserver99.axc.eu`, met certificaatcontrole en versleutelde gegevensverbinding. De opgegeven alias `ftp.ijsbaannederbetuwe.nl` verwijst naar dezelfde server, maar het FTP-certificaat hoort bij `*.axc.eu`. Beide hostnamen en dezelfde IPv4/IPv6-adressen zijn op 10 september 2026 gecontroleerd. ProFTPD vereist hergebruik van de TLS-sessie voor de gegevensverbinding; `scripts/hosting.py` verzorgt dit.
- Gecontroleerde webmap: `/domains/ijsbaannederbetuwe.nl/public_html`.
- Het wachtwoord staat uitsluitend lokaal in `.deploy/ftp-login.dpapi`, versleuteld met Windows DPAPI voor deze Windows-gebruiker. Deze map wordt niet naar GitHub of de hosting geüpload. Invoer of vervanging kan met `python scripts/publish.py --save-login`; de invoer wordt niet getoond.
- Reservekopieën: `.deploy/backups/`. Laatste publicatierapport: `.deploy/last-publish.json`.
- Een reservekopie bevat alleen de vervangen websitebestanden en een bestandslijst. Terugzetten kan door de gewenste eerdere Git-versie als nieuwe commit te herstellen en opnieuw te publiceren. De publicatie verwijdert geen overige bestanden.
- Op een andere pc of onder een andere Windows-gebruiker moeten de bestaande hostinggegevens eenmalig opnieuw worden opgeslagen. Schakel bij certificaatproblemen de controle niet uit; controleer de servernaam en de Windows-certificaatketen.

De oorspronkelijke fotohero, indeling, lettertypen en teksten zijn bij de verhuizing behouden. De metadata verwijst naar het eigen domein; `.htaccess` stuurt HTTP en `www` door naar de HTTPS-versie zonder `www`.

## Inhoud aanpassen

- `dist/edition.js`: actieve editie, datum, locatie en editieoverzicht. Voeg een object aan `editions` toe voor een nieuwe locatie.
- `dist/index.html`: teksten, sponsorpakketten en algemene inhoud. Bij een jaarlijkse wissel ook editiegebonden copy, formulieren en metadata controleren.
- `dist/styles.css`: vormgeving en responsive weergave.
- `dist/winter-play.css`: speelse winterstijl met Caveat-handlettering, fotokaarten, kleuraccenten en compacte sponsortickets. De hoverbewegingen respecteren de voorkeur voor minder beweging.
- `dist/app.js`: navigatie, sponsordialoog en agendadownload.
- `dist/schedule.js` en `dist/schedule.css`: openingstijden per periode. De tijden, vakantie en sluitingsdagen staan per editie in `openingHours` in `dist/edition.js`.
- `dist/motion.css` en `dist/motion.js`: eenmalige introductie, scrollanimaties en hoverreacties. Respecteert `prefers-reduced-motion`, behoudt toetsenbordbediening en printweergave, en laat alle inhoud zien zonder JavaScript.
- `dist/snow.js`: subtiele, pauzeerbare sneeuw in de hero. Minder vlokken op mobiel; pauzeert buiten beeld en in een verborgen tab; uit bij `prefers-reduced-motion`.
- `dist/edition.js` bevat ook de volledige sponsorpakketdetails die in het keuzevenster verschijnen.
- `dist/assets/icons.svg`: lokaal gehoste Lucide-iconen; de licentie staat in `dist/assets/LUCIDE-LICENSE.txt`.
- `dist/assets/`: aangeleverde foto's en logo.
- `dist/assets/schaatsmaatjes.png`: gegenereerde pinguïnillustratie, uitsluitend als klein decoratief accent op de bestaande fotokaart.
- `dist/downloads/`: het originele, digitaal invulbare sponsorformulier.

De startdatum is 11 december 2026. De Wordbrief vermeldt 3 januari 2027 als einddatum; het PDF-sponsorformulier vermeldt 2 januari 2027. Tot bevestiging staat `end: null` en communiceert de website alleen de startdatum. Het gedownloade bronformulier blijft ongewijzigd.

Er worden geen bezoekersgegevens opgeslagen en geen berichten automatisch verzonden. Sponsorcontact opent de e-mailapp van de bezoeker; het PDF-formulier moet de bezoeker zelf invullen en bijvoegen.

Het voorlopige rooster is op 10 september 2026 overgenomen van het patroon op de aangeleverde historische poster, met toestemming van de opdrachtgever. Schooldagen: 15.00–20.00 uur; zaterdag en kerstvakantie: 10.00–20.00 uur. Zondagen, maandagen, beide kerstdagen en nieuwjaarsdag zijn gesloten. De opdrachtgever bevestigde desgevraagd dat ook de maandagen dicht blijven. De kerstvakantie loopt van 19 december 2026 tot en met 3 januari 2027, volgens de [Rijksoverheid](https://www.rijksoverheid.nl/themas/onderwijs/schoolvakanties/kerstvakantie/kerstvakantie-2026). Het getoonde rooster omvat 11 december t/m 3 januari; die laatste dag is zondag en gesloten. `through` is het einde van het rooster, geen bevestiging van de nog onduidelijke officiële einddatum. Het originele sponsorformulier blijft ongewijzigd.

## Lokaal bekijken

```sh
python -m http.server 4173 --directory dist
```

Open vervolgens `http://localhost:4173`.

## Bronnen

Inhoud en prijzen zijn overgenomen uit de door de opdrachtgever aangeleverde sponsorbrief en het sponsorformulier van 2026. De foto's en het logo zijn door de opdrachtgever aangeleverd. Het ontwerp en de website voegen geen rechten toe aan deze assets.
