# Sponsoruitbreiding 12 oktober 2026

Opdracht: de 17 bedrijven uit Foto 1 toevoegen, zonder duplicaten, met commit,
push en live controle. Combinatie Mauritz B.V. stond reeds zichtbaar in het beheer
met dezelfde website; het bestaande profiel blijft ongewijzigd. De overige 16
profielen zijn begrensd op exacte naam, website, editie, originele logo en privébijdrage.
Bedragen en bronnotities staan uitsluitend in de genegeerde `.deploy/`-map.

## Uitvoering en onderbreking

Zeven toevoegingen zijn opgeslagen en afzonderlijk teruggelezen: Etos Roelofsen,
Willem Designvloeren, HK Vastgoed Rhenen, Van Roekel Optiek, HDV Wonen,
MVL Dodewaard en Van Leeuwen Grondwerk en Bestrating. Elk staat eenmaal in beide
publieke sponsorblokken. Van Leeuwen gebruikt een teksttegel, omdat de bron geen
website of logo leverde. De originele logo's behouden verhouding en transparantie.

Bij Village Collection gaf het beheer een geslaagde opslag terug; het direct
volgende leesverzoek liep vast op de TCP-verbinding. Die opslag is nog niet
teruggelezen en mag niet blind worden herhaald. Acht overige toevoegingen zijn
niet geprobeerd. De oorspronkelijke 52 profielen waren vóór de uitvoering exact
behouden; een eindcontrole van alle profielen ontbreekt zolang hosting onbereikbaar is.

De vaste hostingverbinding reageert niet op IPv4 443, 80 of 2222 en evenmin op
het opnieuw via DNS bevestigde IPv6-adres op 443. Dit gebeurt vóór TLS en login.
Er zijn geen nieuwe credentials, gewijzigde beveiligingsinstellingen of mails.

## Bewijs en veilig hervatten

- `.deploy/srv01/2026-10-12/manifest.json`: exacte private payloads, willekeurige
  salts en logohashes. Niet opnieuw genereren; de gepubliceerde route accepteert
  uitsluitend deze hashes.
- `profiles-before.json`: alle 52 oorspronkelijke profielen, 51 zichtbaar.
- `*.attempt.json`, `*.accepted.json`, `*.receipt.json`: poging, geaccepteerde
  opslag en geslaagde terugleescontrole zijn afzonderlijke bewijzen. Een ontbrekend
  ontvangstbestand is geen bewijs dat de opslag niet plaatsvond.
- `progress.json`, `connectivity.json`, `connection-failure.json`: exacte status.
- `.deploy/pending-sponsors/`: vijf eerdere en vier nieuwe nog niet volledig
  geverifieerde sponsors. Eerst live op naam, website en editie vergelijken.
- `register.py` leest eerst alle actuele profielen en bewaart de oorspronkelijke
  52 exact. Een bestaand nieuw profiel wordt gecontroleerd en overgeslagen; een
  ontbrekend profiel met een eerdere poging veroorzaakt een stop.
- De client begrenst het verzoektempo op één verzoek per 1,1 seconde en herhaalt
  geen onzekere schrijfactie. Opslagbevestigingen worden direct vastgelegd vóór
  de volgende leesaanroep. Cookies en CSRF blijven in het procesgeheugen.

De route onder `server/` is een afgebakende taaksnapshot van de dashboardcode,
geen permanente proxy. De oudere incidentroutes blijven gesloten. De bestaande
config/env-toegang, AES-GCM, vaste HTTPS-host en certificaatcontrole zijn behouden.

## Controle van de weergave

De voorbereide weergave bevat 67 sponsors, zonder dubbele namen. De hele cyclus
is via de browser gecontroleerd op desktop (1280 × 900, vier tegelijk, laatste
64–67) en mobiel (390 × 844, twee tegelijk, laatste 66–67), inclusief terugkeer
naar het begin en vorige/volgende. Geen horizontale pagina-overloop of kapotte
logo's. Dit lokale bewijs staat in `work/sponsors-20261012/browser-check.json`
en bewijst uitdrukkelijk geen volledige live registratie.

SEO, sponsorcatalogus, Python-syntax, PHP-syntax en elf gerichte scopetests slagen.
GitHub Pages is een afzonderlijke kopie; de primaire hostingworkflow is niet
geactiveerd. De statische hostingversie verandert niet door profielbeheer.

## Sluiting

De tijdelijke route is op 12 oktober 2026 expliciet gesloten; HTTP 410 is live
bevestigd en `IJSBAAN_12_CLOSED=true` is gecommit/gepusht. Dit sluit de
hulproute, maar maakt de onvolledige sponsoruitbreiding niet afgerond.
Dashboardcommit voor de permanente sluiting: `f44c9120d`.

De pc-browser bevestigt eveneens `ERR_CONNECTION_TIMED_OUT` op het primaire
domein. Herstel van de hostingverbinding is de concrete resterende voorwaarde.
Bij hervatten: een nieuwe begrensde taakidentiteit, eerst alle actuele profielen
teruglezen, Village Collection controleren en uitsluitend werkelijk ontbrekende
namen toevoegen. Daarna de oorspronkelijke profielen, publieke blokken, logo's,
mailtellers en homepage opnieuw vergelijken. Archiveer ieder lokaal wachtend
bestand pas na die sponsor zijn volledige live terugleescontrole.
