# Deploy via SRV01

De vijf sponsors uit de opdracht van 29 september 2026 staan op
https://ijsbaannederbetuwe.nl/. Hun opgeslagen naam, website, editie, beschrijving,
logo en zichtbaarheid zijn gecontroleerd. Iedere sponsor staat eenmaal in de
sponsorstrook en eenmaal in het overzicht. Het beheer bevat 52 profielen, waarvan
51 zichtbaar zijn. Aanvragen en mailtellers zijn gelijk gebleven.

## Wanneer welke route gebruiken

- Websitecode: de bestaande `scripts/publish.py --publish`-route met back-up en
  FTPS-controle, of de bestaande GitHub-productieworkflow zodra die daadwerkelijk
  geactiveerd is. `IJSBAAN_AUTO_DEPLOY` is nog niet ingesteld; een groene
  GitHub Pages-run is geen publicatie op het hoofddomein.
- Sponsorprofielen: het bestaande `/beheer/`, met originele WebP-logo-upload,
  sessie, CSRF en terugleescontrole. Een codepublicatie maakt geen sponsorprofiel.
- Als deze pc de hosting niet bereikt: de hier vastgelegde SRV01-procedure kan
  als uitgangspunt dienen voor een nieuwe, expliciet afgebakende opdracht.
  De uitgevoerde incidentroute is gesloten; ze is geen permanente algemene proxy.

## Bewezen verbinding

De pc bereikte `185.182.56.187:443` niet. SRV01 was bereikbaar via de bestaande
private dashboard- en sync-API, maar de DNS-opzoeking naar de hosting liep daar
vast. De pc bevestigde op 29 september zowel `ijsbaannederbetuwe.nl` als
`vserver99.axc.eu` op `185.182.56.187`. In de specifieke HTTPS-aanvraag vanaf
SRV01 loste `CURLOPT_RESOLVE` dat op. De juiste URL, SNI en certificaatcontrole
bleven behouden. Er zijn geen OS-, DNS-, router- of firewallinstellingen gewijzigd.
Controleer dit adres opnieuw bij later gebruik; neem het niet blind over.

## Opgeslagen onderdelen

- `srv01/client.py`: versleutelde communicatie met de private SRV01-route.
- `srv01/register_five.py`: de uitgevoerde procedure voor uitsluitend deze vijf
  profielen, met duplicaatcontrole, afzonderlijke upload en terugleescontrole.
- `srv01/server/`: exacte afgesloten servercode en gerichte tests.
- `.deploy/srv01/2026-09-29/`: lokale controlebewijzen, buiten Git.
- `.deploy/pending-sponsors/archive/`: geverifieerde ontvangsten van deze vijf.
  De vijf oudere wachtende sponsors zijn niet verwerkt of gewijzigd.

De dashboardkant hoort bij `jerphaas/dashboard` en wordt gepubliceerd via de
bestaande GitHub + `tools/run_sync_api.ps1`-route. De normale dashboardwerkmap
kan wijzigingen van andere taken bevatten; gebruik een schone werkmap.

## Credentials en veiligheidsgrenzen

Websitecredentials blijven lokaal in `.deploy/sponsor-secrets.dpapi`; alleen de
benodigde onderhoudssleutel wordt in het geheugen gebruikt voor health en een
eenmalig beheerticket. Het dashboardtoken komt uit de bestaande config/env-route
via `fictief/beursopheusden_support.php`. Er zijn geen credentials naar GitHub
gekopieerd en er is geen nieuw token gemaakt. De transportversleuteling voorkomt
dat tokens, sessies of beheerantwoorden leesbaar over de private HTTP-hop gaan.

De client leidt het ijsbaanproject af uit zijn eigen locatie. Het bestaande
dashboardproject staat standaard onder `~/Documents/GitHub/dashboard`; zo nodig
kan `IJSBAAN_DASHBOARD_ROOT` alleen dat lokale pad aanpassen. PHP volgt
`IJSBAAN_PHP_EXECUTABLE`, PATH of de bestaande `C:/PHP/php.exe`.

Alleen de vijf vastgelegde namen, links, logohashes en de actieve editie waren
toegestaan. Geen bestaande profielen overschrijven, geen aanvraag of mail,
geen directe database- of shellroute. Onbekende bijdragen zijn niet bevestigd;
nul was uitsluitend de door het bestaande beheer vereiste technische standaard.
Na een onzekere schrijfpoging eerst de werkelijke opslag teruglezen.

## Afronding

De tijdelijke route is direct gesloten met de sluitmarkering en daarna ook in
code uitgezet (`IJSBAAN_29_CLOSED=true`, dashboardcommit `1094eeb5`). De
beheersessie is uitgelogd; HTTP 410 is op de live route bevestigd. Controleer bij een nieuwe taak eerst de actuele status;
heropen deze afgeronde incidentroute niet stilzwijgend.

De primaire websitecode bleef `105b8f72a493`; dit was een wijziging van
sponsorprofielen via het bestaande beheer. De originele logo's en geoptimaliseerde
bronbestanden zijn al opgenomen in ijsbaancommit `7af0cd0`.
