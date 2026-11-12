# Website publiceren via SRV01 en DirectAdmin

## Hergebruik via lokale WARP-proxy (geverifieerd 12 november 2026)

De correctie van de openingstijden is gepubliceerd als commit
`7675fd0d31e49b34a3e567b31cc31e968783e9d0`. De bestaande DirectAdmin-host,
accountlogin via `scripts/hosting.py::ftp_password()`, accountpaden en
multipart-aanroepen hieronder werkten ook rechtstreeks vanaf PC-JERPHAAS
via de bestaande WARP-proxy `socks5h://127.0.0.1:40000`. Er was geen nieuwe
SRV01-helper, registratie of credential nodig. Dit behoudt de eerder expliciet
goedgekeurde HTTP-route van DirectAdmin; het maakt die laatste verbinding
niet tot HTTPS. Publieke eindcontroles gebruiken normale HTTPS-certificaatcontrole.

Voor deze beperkte publicatie zijn alleen `edition.js`, `schedule.js`,
`app.js`, `index.html` en daarna `site-version.json` geplaatst. Alle oude bytes
kwamen vooraf overeen met live commit `5402e7b`; bronhashen zijn vóór het
schrijven opnieuw gecontroleerd. Per bestand is een private backup met prefix
`hours-20261112-` exact teruggelezen, gevolgd door het opgeslagen doelbestand.
De echte homepage en publieke scripts kwamen daarna exact overeen met de
nieuwe commit, buiten de twee ongewijzigde sponsorblokken (72 sponsors).
Backend, database, configuratie en mail zijn niet gewijzigd.

GitHub Pages-run `43222177239` slaagde. Hoofdhosting-run `43222177155` was
overgeslagen omdat `IJSBAAN_AUTO_DEPLOY` nog niet is ingesteld. Controleer dit
bij volgende publicaties; publiceer nooit tegelijk met een actieve hostingrun.
`python scripts/check_seo.py --live` slaagde via tijdelijke procesvariabelen
`HTTPS_PROXY` en `HTTP_PROXY` met waarde `http://127.0.0.1:40000`.

De directe IAB-navigatie gaf een time-out; de PAC-helper op 40001 was niet
actief. Desktop en mobiel zijn gecontroleerd via de bestaande alleen-lezen
loopbackviewer met verse, ongewijzigde publieke HTTPS-responses. Alle drie
roosterperiodes zijn bediend: 24 dagen, inclusief 11 december 19.00–22.00,
24/31 december 10.00–16.00 en 2 januari 10.00–14.00. Gemeten paginabreedtes
1265 en 375 px zonder horizontale overloop. Tijdelijke viewport teruggezet;
deze verificatie repareert de aparte PAC-helper niet.

Private bronbestanden, backups, opslagontvangsten, versiebewijs en hashes van
de browserbronnen staan in `.deploy/hours-20261112/`. De eenmalige, op vier
bestanden en één commit begrensde taakhelper staat in
`C:/Users/huver/Documents/Codex/2026-11-12/ij/work/publish_hours.py`.
Niet blind opnieuw uitvoeren: controleer actuele bronversie, scope en backupnamen.

## Historische publicatie via SRV01

Bewezen op 1 oktober 2026: de definitieve openingstijden staan op het primaire
domein. Gepubliceerde websitecommit: `5402e7b4a7e1c21480899d0307ac645f04df35a3`.
De gebruiker vroeg deze route ook vast te leggen voor volgende websitewijzigingen.

## Bestaande toegang en bestemming

- IJsbaanproject: deze repository, `main`, remote `jerphaas/ijsbaan-neder-betuwe`.
- DirectAdmin: `http://www.ijsbaannederbetuwe.nl:2222`, account `ijsbaan`.
- Hergebruik `scripts/hosting.py::ftp_password()` en `.deploy/ftp-login.dpapi`.
  Deze bestaande hostinglogin werkt ook voor DirectAdmin. Geen nieuwe sleutel of
  wachtwoord maken, niets daarvan tonen, loggen of naar GitHub schrijven.
- De gebruiker heeft op 1 oktober expliciet bevestigd dat de bestaande HTTP-route
  gebruikt mag worden nadat het onversleutelde laatste traject was uitgelegd.
  Hergebruik die beslissing voor dezelfde route en gewone gevraagde websitepublicatie;
  vraag niet telkens opnieuw. Dit geeft geen toestemming voor ongevraagde taken,
  gewijzigde credentials, andere hosts, configuratie, databasewijzigingen of mail.
- De lokale hop naar SRV01 gebruikt geauthenticeerde AES-256-GCM-versleuteling,
  afgeleid van het bestaande dashboardconfig/env-token via
  `fictief/beursopheusden_support.php::bbo_store_config_value()`. Gebruik dezelfde
  bestaande tokenvolgorde als `deploy/srv01/client.py`, geen broncode-tokenfallback.
- Op 1 oktober is DNS voor zowel het hoofddomein, www als `vserver99.axc.eu`
  bevestigd op `185.182.56.187`. Bevestig dit opnieuw bij latere opdrachten.
  Resolutie uitsluitend per verzoek vastzetten; geen router-, DNS- of firewallwijziging.
- Publieke bestanden: `/domains/ijsbaannederbetuwe.nl/public_html/`.
- Private backups: `/domains/ijsbaannederbetuwe.nl/sponsor-private/deploy-backups/`.
  Bewaar daarnaast de oorspronkelijke bytes lokaal in `.deploy/`.

## De werkende DirectAdmin-aanroepen

Alle onderstaande aanroepen gebruiken de bestaande Basic Auth in het geheugen.
Geen wachtwoord in de URL, shellargumenten of uitvoer. Volg redirects niet naar
andere hosts. DirectAdmin HTTP is de hierboven expliciet goedgekeurde route;
de publieke eindcontrole blijft certificaatgecontroleerde HTTPS.

1. Bestand lezen: `GET /CMD_FILE_MANAGER?path=<URL-encoded absoluut accountpad>&noredirect=true`.
   Gebruik de **path-parameter**. De padvariant geeft redirects; de API-padvariant
   kan 403 geven. Controleer de werkelijk ontvangen bestandbytes.
2. Alleen indien nodig private backupmap maken:
   `GET /CMD_API_FILE_MANAGER?action=folder&path=<URL-encoded sponsor-private>&name=deploy-backups`.
3. Upload: `POST /CMD_API_FILE_MANAGER`, **multipart/form-data** met velden
   `action=upload`, `path=<directory>`, `overwrite=yes` en `file1=<file bytes met filename>`.
   Laat cURL Content-Length berekenen, gebruik een willekeurige multipart-boundary
   en een lege `Expect:`-header. De oude raw-uploadroute met
   `X-DirectAdmin-File-Upload` werkte voor JS, maar gaf 502 bij HTML: niet herhalen.
4. Lees zowel backup als doelbestand via stap 1 terug en vergelijk SHA-256.
   Een antwoordtekst of HTTP 200 is geen opslagbewijs.

Officiële documentatie:
https://docs.directadmin.com/changelog/version-1.27.2.html#cmd-api-file-manager-new

## Publicatie en bewijs

1. Inspecteer status en behoud ander werk. Werk alleen aan de gevraagde inhoud.
   SEO en passende syntax-/browsercontrole uitvoeren, committen en pushen.
2. Lees de verse publieke pagina en `site-version.json` via SRV01. Lees de
   bestaande doelbestanden via DirectAdmin en vergelijk ze met de oude live commit.
   Stop bij afwijkende host, onverwachte bytes of een gewijzigde bronhash.
3. Gebruik een tijdelijke, private, versleutelde taakroute met vaste host,
   bestandsnamen, nieuwe commit-hashes en vervaldatum. Geen algemene proxy,
   shell- of database-API. De oorspronkelijke sponsorroute van 29 september blijft dicht.
4. Maak voor ieder bestand eerst een private reservekopie met een unieke taaknaam.
   Lees deze exact terug voordat het doel wordt overschreven. Plaats de startpagina
   als laatste. Bij een onzekere schrijfpoging eerst teruglezen; een reeds exact
   geplaatste nieuwe hash is een veilige no-op, geen reden de originele backup te vervangen.
5. Controleer publieke JS/CSS via verse HTTPS en de **echte startpagina `/`**.
   `/index.html` kan 301 naar `/` geven. Strip uitsluitend de twee expliciete
   sponsorblokken met `scripts/publish.py::static_home()` voor de templatevergelijking;
   vergelijk alle andere HTML exact. Controleer daarnaast dat de sponsorblokken gelijk bleven.
6. Plaats pas daarna het versiebewijs en lees het via HTTPS terug. Deze opdracht
   publiceerde vier statische bestanden en een versie-ontvangst: backendcode bleef
   op de vorige versie. `deployment=opening-hours-only`, `previous_commit` en
   `changed_files` vermelden dit eerlijk. Een gedeeltelijke publicatie bewijst geen
   volledige backenddeploy. Bij een latere volledige publicatie hoort volledige verificatie.
7. Controleer de gewijzigde UI op desktop en mobiel. Als de pc het primaire domein
   niet bereikt, documenteer dat en combineer de browsercontrole van de exacte
   gecommitte versie met verse server-side vergelijking van HTML en scripts.
8. Sluit de tijdelijke route onmiddellijk: sluitmarkering, vervolgens de gesloten
   constante committen/pushen/synchroniseren, en HTTP 410 live bevestigen.

De exact uitgevoerde helper staat in `jerphaas/dashboard`:
`api/ijsbaan_hours_20261001.php` en `includes/IjsbaanHours20261001.php`.
Het betreft een afgesloten taaksnapshot. Gebruik bij een volgende opdracht een
nieuwe afgebakende manifest/taakidentiteit; open afgeronde incidentroutes niet opnieuw.
Voor dashboardlevering: schone werkmap, commit/push, dan de bestaande
`tools/run_sync_api.ps1 -Action run`, en bronhashen vergelijken met SRV01.
Zet de PHP-helperbestanden via `.gitattributes` vooraf op `text eol=lf`.

## Relevante technische grenzen

- GitHub Pages is een aparte kopie. `IJSBAAN_AUTO_DEPLOY` was op 1 oktober nog
  niet ingesteld; een overgeslagen hostingworkflow is geen primaire publicatie.
- De pc bereikt de hosting momenteel niet. SRV01 bereikt publieke HTTPS en
  DirectAdmin. De FTPS-controlverbinding werkte na hergebruik van de bestaande
  Windows-certificaatstore, maar de passieve bestandsoverdracht liep vast.
  Ga voor dezelfde situatie direct naar deze bewezen DirectAdmin-route;
  herhaal geen lange certificaat-/netwerkproefreeks.
- Geen certificatcontrole uitschakelen, geen credentials naar GitHub, geen
  configuratie of uploads overschrijven, geen sponsorprofielen/aanvragen/mail wijzigen.
- Controlebewijs van deze publicatie staat privaat in
  `.deploy/directadmin-20261001/`, inclusief oude bytes, bronhashes, nieuwe payloads,
  live homepage, voor/na sponsorblokken, versie en clientscript.
