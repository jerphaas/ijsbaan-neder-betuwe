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

De publicatieknop zet de **laatste lokale commit** online. Niet-gecommitte wijzigingen in `dist/` en `server/` worden tegengehouden. Na een wijziging via de GitHub-website moet deze lokale map dus eerst worden bijgewerkt met `git pull --ff-only`. Bestanden uit `dist/` gaan naar de webmap; `server/` gaat naar de afgeschermde map `sponsor-private/app/`. Andere bestanden op de hosting worden behouden. Elk te vervangen bestand krijgt vooraf een lokale reservekopie; de startpagina wordt als laatste geplaatst. Daarna vergelijkt het script de openbare statische bestanden met de commit; PHP wordt via FTPS gecontroleerd en de actieve backend via een beveiligde gezondheidscontrole.

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

Na een inhoudelijke wijziging: voer `node scripts/update_seo.mjs` en `python scripts/check_seo.py` uit vóór de commit. De titel, zoekomschrijving, deelvoorbeelden en gestructureerde gegevens gebruiken automatisch de actieve editie in `dist/edition.js`. Zowel publicatie naar de eigen hosting als GitHub Pages controleert deze gegevens vooraf.

- `dist/edition.js`: actieve editie, datum, locatie en editieoverzicht. Voeg een object aan `editions` toe voor een nieuwe locatie.
- `dist/index.html`: teksten, sponsorpakketten en algemene inhoud. Bij een jaarlijkse wissel ook editiegebonden copy, formulieren en metadata controleren.
- `dist/styles.css`: vormgeving en responsive weergave.
- `dist/winter-play.css`: speelse winterstijl met Caveat-handlettering, fotokaarten, kleuraccenten en compacte sponsortickets. De hoverbewegingen respecteren de voorkeur voor minder beweging.
- `dist/app.js`: navigatie en agendadownload. `dist/sponsor.js` en `dist/sponsor.css`: sponsormodal, formulier en logo-preview.
- `dist/schedule.js` en `dist/schedule.css`: openingstijden per periode. De tijden, vakantie en sluitingsdagen staan per editie in `openingHours` in `dist/edition.js`.
- `dist/motion.css` en `dist/motion.js`: eenmalige introductie, scrollanimaties en hoverreacties. Respecteert `prefers-reduced-motion`, behoudt toetsenbordbediening en printweergave, en laat alle inhoud zien zonder JavaScript.
- `dist/snow.js`: subtiele, pauzeerbare sneeuw in de hero. Minder vlokken op mobiel; pauzeert buiten beeld en in een verborgen tab; uit bij `prefers-reduced-motion`.
- `dist/edition.js` bevat ook de volledige sponsorpakketdetails die in het keuzevenster verschijnen.
- De knop **Vergelijk de pakketten** opent een vergelijking: zes pakketten op desktop, twee vrij te kiezen pakketten tot 1.000 px. De tabel gebruikt `sponsorComparison` in `dist/edition.js` en haalt muntaantallen uit `sponsorPackages`. Controleer bij een pakketwijziging beide beschrijvingen. Een streepje betekent niet vermeld; hogere pakketten erven niet automatisch alle eerdere voordelen. Vanuit de tabel opent **Kies pakket** het aanvraagformulier; al ingevulde gegevens blijven behouden bij vergelijken vanuit dat formulier.
- `dist/assets/icons.svg`: lokaal gehoste Lucide-iconen; de licentie staat in `dist/assets/LUCIDE-LICENSE.txt`.
- `dist/assets/`: aangeleverde foto's en logo.
- `dist/assets/schaatsmaatjes.png`: gegenereerde pinguïnillustratie, uitsluitend als klein decoratief accent op de bestaande fotokaart.
- `dist/assets/*-<hash>.webp`: lichte afbeeldingen voor de website, met passende formaten voor mobiele schermen. De bestandsnaam bevat een inhoudshash zodat lang cachen veilig blijft. Bronfoto's blijven intact. Opnieuw maken kan met `python scripts/optimize_images.py` (Pillow), gevolgd door `node scripts/update_seo.mjs`.
- `dist/downloads/`: het originele, digitaal invulbare sponsorformulier.

De startdatum is 11 december 2026. De Wordbrief vermeldt 3 januari 2027 als einddatum; het PDF-sponsorformulier vermeldt 2 januari 2027. Tot bevestiging staat `end: null` en communiceert de website alleen de startdatum. Het gedownloade bronformulier blijft ongewijzigd.

Op verzoek van 14 september 2026 slaat het sponsorformulier aanvragen op in MySQL en stuurt het e-mails naar Ton en de sponsor. Het originele PDF-bestand blijft als bron aanwezig; bezoekers hoeven het niet meer in te vullen.

Het voorlopige rooster is op 10 september 2026 overgenomen van het patroon op de aangeleverde historische poster, met toestemming van de opdrachtgever. Schooldagen: 15.00–20.00 uur; zaterdag en kerstvakantie: 10.00–20.00 uur. Zondagen, maandagen, beide kerstdagen en nieuwjaarsdag zijn gesloten. De opdrachtgever bevestigde desgevraagd dat ook de maandagen dicht blijven. De kerstvakantie loopt van 19 december 2026 tot en met 3 januari 2027, volgens de [Rijksoverheid](https://www.rijksoverheid.nl/themas/onderwijs/schoolvakanties/kerstvakantie/kerstvakantie-2026). Het getoonde rooster omvat 11 december t/m 3 januari; die laatste dag is zondag en gesloten. `through` is het einde van het rooster, geen bevestiging van de nog onduidelijke officiële einddatum. Het originele sponsorformulier blijft ongewijzigd.

## Lokaal bekijken

```sh
python -m http.server 4173 --directory dist
```

Open vervolgens `http://localhost:4173`.

## Bronnen

Inhoud en prijzen zijn overgenomen uit de door de opdrachtgever aangeleverde sponsorbrief en het sponsorformulier van 2026. De foto's en het logo zijn door de opdrachtgever aangeleverd. Het ontwerp en de website voegen geen rechten toe aan deze assets.

## SEO en Google

- De canonieke URL is `https://ijsbaannederbetuwe.nl/`. HTTP, `www` en `/index.html` sturen door naar die URL. De GitHub Pages-kopie verwijst er via de canonical naar.
- `robots.txt` staat crawlen toe en verwijst naar `sitemap.xml`. De sitemap bevat de echte startpagina en drie inhoudelijke afbeeldingen; ankers, de foutpagina en technische bestanden horen er niet in. Er wordt geen kunstmatige versheidsdatum gegenereerd.
- Alle inhoudelijke afbeeldingen hebben beschrijvende Nederlandse alt-teksten. De decoratieve pinguïns, sneeuw en iconen worden bewust overgeslagen door schermlezers.
- De statische JSON-LD beschrijft de organisatie, website en pagina. Het betreft geen nieuwe juridische entiteit. Er zijn geen verzonnen beoordelingen, openingstijden of zoektermen toegevoegd. Event-markup volgt pas wanneer de einddatum en evenementgegevens bevestigd zijn; een meerdaags evenement zonder einddatum kan verkeerde informatie opleveren.
- Titels en beschrijvingen zijn ook beschikbaar voor gedeelde links via Open Graph en Twitter Cards. De content, navigatie en belangrijkste bezoekinformatie staan in de HTML; er is geen JavaScript nodig om die te lezen.
- Tekstbestanden worden gecomprimeerd en opnieuw gevalideerd; afbeeldingen met een inhoudshash mogen langdurig gecachet worden. De gebruikte afbeeldingen daalden bij de optimalisatie van circa 2,73 MB naar 0,42 MB op de grootste formaten. Dit is bestandsoverdracht, geen gemeten Core Web Vitals-score.
- `404.html` krijgt bij een ontbrekende URL de echte HTTP-status 404. De foutpagina, het technische versiebestand en het Google-verificatiebestand zijn niet bedoeld als zoekresultaat.
- Het aangeleverde bestand `google4244cf326cd58185.html` staat ongewijzigd in de hoofdmap. **Behoud dit bestand bij alle toekomstige publicaties**, ook na een geslaagde eigendomscontrole.
- In Google Search Console: verifieer de URL-prefix-property `https://ijsbaannederbetuwe.nl/` met het HTML-bestand, dien `sitemap.xml` in en inspecteer de startpagina. Controleer daar later indexering, zoektermen en Core Web Vitals. Een geüpload verificatiebestand alleen bewijst niet dat Google de eigendomscontrole of sitemapaanmelding heeft afgerond.
- Controleer lokaal met `python scripts/check_seo.py`; controleer na publicatie ook met `python scripts/check_seo.py --live`. De gewone publicatiecontrole vergelijkt daarnaast alle online bestanden met de commit.

Gebaseerd op de officiële [SEO-startgids van Google](https://developers.google.com/search/docs/fundamentals/seo-starter-guide), [sitemaprichtlijnen](https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap), [site-naamgegevens](https://developers.google.com/search/docs/appearance/site-names) en [evenementrichtlijnen](https://developers.google.com/search/docs/appearance/structured-data/event). Indexering en posities worden door Google bepaald; een technische controle garandeert geen ranking of uitgebreid zoekresultaat.

## Sponsoraanvragen beheren

Het formulier vraagt pakket, bedrijf/organisatie, contactpersoon en e-mailadres. Telefoon, opmerkingen en logo zijn optioneel. Na opslag krijgt elke aanvraag een unieke `IJS-...`-referentie; Ton ontvangt de gegevens met het logo als bijlage, de sponsor ontvangt een ontvangstbevestiging. Beide mails komen van `info@ijsbaannederbetuwe.nl`; antwoorden gaan naar Ton of de sponsor. Er wordt niets betaald of definitief geboekt.

- Publieke ingang: `dist/api/sponsor.php`. Privé-app: `server/bootstrap.php`, `server/mail.php`, `server/schema.sql` en `server/catalog.json`. PHPMailer 7.1.1 komt uit de officiële release, met licentie en bestandshashes in `server/vendor/phpmailer/`.
- Pakket- of editiewijziging: pas `dist/edition.js` aan, draai `node scripts/update_sponsor_catalog.mjs`, daarna de bestaande SEO-controles. Bedragen en voordelen in een binnengekomen aanvraag blijven als momentopname bewaard.
- Hosting: PHP 8.5.6 op 14 september 2026 gecontroleerd na verhoging via het open DirectAdmin-tabblad. Benodigde extensies: PDO/MySQL, GD, fileinfo, mbstring, OpenSSL. Uploadgrens hosting 2 MB, POST 8 MB.
- De bestaande MySQL-database wordt lokaal op de hosting benaderd. Tabellen: `sponsor_applications`, `sponsor_mail`, `sponsor_rate_limits`. Migratie maakt uitsluitend deze tabellen aan als ze nog ontbreken; geen verwijderingen.
- Secrets: lokaal Windows DPAPI `.deploy/sponsor-secrets.dpapi`; remote `/domains/ijsbaannederbetuwe.nl/sponsor-private/config.json` (600), map 700. Hergebruik `scripts/sponsor_admin.py`; geen secrets in argumenten, browser of Git. De `enabled`-schakelaar onderbreekt nieuwe aanvragen zonder gegevens te verwijderen.
- Uploads: dezelfde privémap, submap `uploads/`, willekeurige namen, rechten 600. Alleen echte PNG/JPEG/WebP tot 2 MB, maximaal 12 megapixels en 6.000 pixels per zijde. GD decodeert en schrijft een nieuwe PNG zonder oorspronkelijke metadata of bestandsnaam. Geen SVG/PDF/uitvoerbare uploads. De upload is geen openbaar downloadbestand.
- Bescherming: HTTPS, exacte origincontrole, ondertekend formuliertoken (2 uur), honeypot, invoervalidatie, PDO-parameters, limieten per IP en e-mailadres en unieke aanvraagtoken. Rate-limit-identiteiten worden HMAC-gehasht en verlopen na 2 uur; de webserver kan eigen toegangslogs bijhouden. Aanvragen en logo’s worden bewaard voor afhandeling; verwijderverzoeken lopen via Ton.
- Toestemming in het formulier geldt uitsluitend voor de sponsoraanvraag en communicatie daarover; er wordt geen marketinginschrijving aangemaakt.
- Mailstatus per ontvanger: `pending`, `sending`, `sent` of `uncertain`. `sent` betekent dat SMTP het bericht heeft aangenomen, niet dat het gelezen is. Tijdelijke connectie-/authenticatiefouten blijven `pending`; opgeslagen aanvragen blijven behouden.
- Onderhoud: `python scripts/sponsor_admin.py health` toont actuele commit, aantallen en nog niet verzonden berichten zonder persoonsgegevens. `python scripts/sponsor_admin.py retry --reference IJS-...` probeert alleen pending-berichten van die aanvraag opnieuw (maximaal 5 pogingen per ontvanger). Reeds verstuurde mails worden overgeslagen.
- Bij een onderbreking tijdens/na SMTP DATA blijft de status `uncertain`; een afgebroken proces kan `sending` achterlaten. Niet blind opnieuw verzenden: controleer eerst de mailserverlogs met het deterministische Message-ID `<ijs-... .organizer/sponsor@ijsbaannederbetuwe.nl>` (zonder spatie). Er is geen automatische achtergrondtaak ingesteld; controleer pending-mails met de beheeropdracht.
- De deploy bewaart de vorige privébronbestanden, behoudt config/uploads/aanvragen, controleert uploads en schema en plaatst de nieuwe homepage als laatste. `site-version.json` en beveiligde backendhealth moeten dezelfde commit tonen.
- GitHub Pages blijft een statische kopie zonder `api/`; de pakketlinks openen het formulier op het eigen domein. Een lokale `http.server`-preview kan het uiterlijk tonen, maar aanvraagverwerking vereist de eigen PHP-hosting.
- Technische verzendproeven gebruiken de eigen mailbox als sponsoradres, worden gemarkeerd `is_test=1` en hebben onderwerp `[TECHNISCHE TEST]`. Ton krijgt expliciet te zien dat er niets te verwerken of factureren is.

## Bedrijven en aanvragen bekijken

De vaste beheerlink is **https://ijsbaannederbetuwe.nl/beheer/**. Het overzicht toont bedrijven, contactgegevens, pakketkeuze, aanvraagdatum, mailstatus en logo-download. Zoek op bedrijf, contact, e-mail of referentie; filter op editie en pakket. De CSV-download bevat alle resultaten binnen de gekozen filters. Testaanvragen staan apart en tellen niet mee in echte aantallen/bedragen. Dit is een overzicht van aanvragen, niet van geaccepteerde contracten of betalingen.

Dubbelklik op **Open sponsorbeheer.cmd** in deze projectmap. Deze opener gebruikt de bestaande Windows-DPAPI-toegang en logt in via een eenmalig ticket van vijf minuten. Er worden geen nieuwe blijvende wachtwoorden aangemaakt en geen hosting-, database- of mailwachtwoorden naar de browser gestuurd. Alleen het eenmalige ticket gaat naar de browser, in het URL-fragment; de pagina verwijdert dat direct. De vaste link kan vervolgens als bladwijzer worden opgeslagen.

De browser wordt maximaal 30 dagen herkend via een apart, gehasht en bij sessieherstel roterend token. Gewone PHP-sessies verlopen na 8 uur of 2 uur inactiviteit. Uitloggen trekt de huidige browsertoegang in. Opnieuw inloggen kan met hetzelfde cmd-bestand op deze pc. De opener is gekoppeld aan de lokale Windows-gebruiker en diens opgeslagen toegang; het cmd-bestand alleen geeft op een andere pc geen toegang.

Bronnen: `server/admin.php`, `server/admin_views.php`, `dist/beheer/` en `scripts/open_sponsor_admin.py`. Alleen geauthenticeerde gebruikers kunnen aanvragen, details, CSV en private logo’s ophalen. Het beheer stuurt geen mails en wijzigt geen oorspronkelijke aanvragen. Er is geen publieke herbruikbare geheime URL. De map `beheer/` wordt niet gepubliceerd op GitHub Pages; gebruik het eigen domein.

## Sponsors zichtbaar maken en aanpassen

Open **https://ijsbaannederbetuwe.nl/beheer/?partners=1** na inloggen met de bestaande pc-opener. Kies **Bewerken** bij een bedrijf. Je kunt de naam, website, korte openbare tekst, logo, editie, plek, volgorde en zichtbaarheid aanpassen. **Sponsor opslaan** werkt de website direct bij. Een wit logo krijgt met de optie ‘Donkere achtergrond’ een groene ondergrond. Zonder logo verschijnt de bedrijfsnaam. Via **Sponsor toevoegen** maak je een nieuw profiel; nieuwe profielen staan standaard verborgen. Een echte online aanvraag kan vanuit de detailpagina worden overgenomen, inclusief het aangeleverde logo. Dat verstuurt geen mail.

De bijdrage is uitsluitend zichtbaar in het afgeschermde beheer. De door de opdrachtgever bevestigde groepsbedragen uit Sponsorlijst.xlsx worden ongewijzigd bewaard, ook € 1.500 en € 350. Onbekende vermelding N.N. is verborgen. De eerste import is lokaal opgeslagen in genegeerd `work/sponsor-research/partners-import.json`; de oorspronkelijke Excel is niet gewijzigd. Importeren gaat met `python scripts/import_partners.py <lokaal-jsonbestand>`. Deze begrensde import voegt alleen ontbrekende bron-ID's toe en overschrijft nooit beheerwijzigingen. Het bestand met bedragen en bronnotities hoort nooit in Git. De live database is na import de bron voor profielwijzigingen.

`server/partners.php` levert uitsluitend zichtbare bedrijven van de actieve editie. `dist/index.php` vult twee expliciete blokken in de HTML-template, zodat zoekmachines de namen ook zonder JavaScript zien. De overige pagina blijft statisch. `dist/partners.css` en `dist/partners.js` verzorgen de compacte strook en het volledige sponsoroverzicht. Alle sponsors in dit overzicht zijn direct zichtbaar; vanuit de hoofdsponsorstrook leidt een duidelijke link met het totale aantal ernaartoe. De GitHub Pages-kopie haalt alleen deze openbare blokken op bij het hoofddomein. Geen databasegegevens, contactpersonen of privébedragen gaan mee.

Originele gevonden logo's staan in `source-assets/partners/`; `sources.json` bevat hun herkomst. Bedrijfslogo's behouden hun originele kleuren en horen bij hun respectieve eigenaren. Geoptimaliseerde WebP-kopieën staan in `dist/assets/partners/` en zijn gemaakt met `scripts/optimize_images.py:optimize_partner`. Opgeslagen uploadlogo's blijven buiten de webmap; de publieke route controleert de zichtbaarheid bij iedere aanvraag en levert alleen een kleine WebP. Zowel beheer als publieke profielgegevens gebruiken geen langdurige cache.

Controle na relevante wijzigingen: `python scripts/check_sponsor_admin.py`. Die test anonieme afscherming, tijdelijke tickets, cookie-eigenschappen, zoeken, CSV, logotoegang, sessieherstel en uitloggen op de werkelijke hosting, zonder nieuwe aanvragen of mail te maken. Tabellen `sponsor_admin_tickets` en `sponsor_admin_devices` bevatten alleen hashes van toegangstokens en hun termijnen; sessies staan in `sponsor-private/sessions/`. CSV-velden worden als tekst beschermd tegen formule-injectie.
