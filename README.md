# IJsbaan Neder-Betuwe

Publieke website voor IJsbaan Neder-Betuwe. Editie Opheusden 2026/2027, met Kesteren als volgende editie en een uitbreidbaar overzicht voor andere locaties.

Website: https://jerphaas.github.io/ijsbaan-neder-betuwe/

## Publicatie

De complete statische website staat in `dist/`. GitHub Pages publiceert deze map met de workflow in `.github/workflows/pages.yml` na een push naar `main`. Er is geen build of installatie van pakketten nodig.

## Inhoud aanpassen

- `dist/edition.js`: actieve editie, datum, locatie en editieoverzicht. Voeg een object aan `editions` toe voor een nieuwe locatie.
- `dist/index.html`: teksten, sponsorpakketten en algemene inhoud. Bij een jaarlijkse wissel ook editiegebonden copy, formulieren en metadata controleren.
- `dist/styles.css`: vormgeving en responsive weergave.
- `dist/app.js`: navigatie, sponsordialoog en agendadownload.
- `dist/assets/`: aangeleverde foto's en logo.
- `dist/downloads/`: het originele, digitaal invulbare sponsorformulier.

De startdatum is 11 december 2026. De Wordbrief vermeldt 3 januari 2027 als einddatum; het PDF-sponsorformulier vermeldt 2 januari 2027. Tot bevestiging staat `end: null` en communiceert de website alleen de startdatum. Het gedownloade bronformulier blijft ongewijzigd.

Er worden geen bezoekersgegevens opgeslagen en geen berichten automatisch verzonden. Sponsorcontact opent de e-mailapp van de bezoeker; het PDF-formulier moet de bezoeker zelf invullen en bijvoegen. Openingstijden en details die niet in de bronnen staan worden niet ingevuld.

## Lokaal bekijken

```sh
python -m http.server 4173 --directory dist
```

Open vervolgens `http://localhost:4173`.

## Bronnen

Inhoud en prijzen zijn overgenomen uit de door de opdrachtgever aangeleverde sponsorbrief en het sponsorformulier van 2026. De foto's en het logo zijn door de opdrachtgever aangeleverd. Het ontwerp en de website voegen geen rechten toe aan deze assets.
