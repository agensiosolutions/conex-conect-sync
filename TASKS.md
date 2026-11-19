# Taskuri conex-conect-sync

<!--
Sursa pentru pagina de taskuri (/start). Claude ține fișierul la zi; pagina doar îl arată.
- Secțiunile: Acum, Urmează, Așteaptă, Gata. În Gata rămân doar ultimele 14 zile; ce e mai vechi trăiește în JURNAL.md.
- Un task: `- [ ] Ce se face` plus, opțional, `@iulian` `@claude` `@client` și `📅 AAAA-LL-ZZ` (termen).
- Detaliile stau dedesubt, indentate cu 2 spații (text sau sub-bife `- [ ]`).
-->

## Acum

### Etapa 0: schelet și client de API
- [ ] Site nou în Local by Flywheel, numit `cezauto-dev` @iulian
  Doar crearea site-ului gol, din interfața Local (nu are CLI pentru asta). PHP 8.1+, WordPress curat.
  Restul fac eu: import, curățare, cont de admin, legarea pluginului.
- [ ] Repo privat `agensiosolutions/conex-conect-sync` creat pe GitHub @iulian
  https://github.com/new?owner=agensiosolutions&name=conex-conect-sync&visibility=private
  Gol, fără README. Remote-ul, hook-ul de backup și primul push le pun eu după.
- [ ] Importul bazei locale și curățarea secretelor @claude
  Dump gata: `C:\Users\Iulian\dumps\cezauto-dev.sql` (89 MB, 6.852 produse, zero date personale).
- [ ] `wp ccs test` rulat pe mediul local, cu tokenul Cez Auto @claude
  Confirmă 21.002 produse și afișează câmpurile unui produs. Codul e scris, lipsește doar mediul.

## Urmează

### Etapa 1: legare și raport
- [ ] Tabelele proprii (`ccs_products`, `ccs_links`, `ccs_runs`, `ccs_log`), cu `dbDelta` @claude
- [ ] Preluarea catalogului în staging, cu hash per produs ca să vedem ce s-a schimbat @claude
- [ ] Legarea pe `codEAN` = SKU, plus recuperarea prin `codProducator` @claude
  La Cez Auto: 3.988 legate, 2.443 orfane, 26 recuperabile prin cod producător. Zero ambiguități.
- [ ] Raport CSV „ce aș face”, fără să atingă WooCommerce @claude

### Etapa 2: preț și stoc
- [ ] `ccs_pret()` legat de produsele reale, cu raport înainte de aplicare @claude
- [ ] Aplicarea stocului, în loturi, prin CRUD WooCommerce @claude
- [ ] Flag „preț manual” per produs, ca să nu suprascriem ce a pus clientul @claude

### Etapa 3: curățare și joburi de noapte
- [ ] Produsele absente N runde trec pe stoc epuizat, nu se șterg @claude
- [ ] Joburile de noapte (imagini, criterii) pe Action Scheduler, programate 19:00-20:00 @claude

### Etapa 4: import de produse noi
- [ ] Importul celor 993 de produse complete @claude
- [ ] Mecanismul care urcă un produs când furnizorul îi adaugă poza @claude
- [ ] Maparea categoriilor, învățată din produsele deja legate, plus regula covorașelor @claude

### Etapa 5: panou, cron, predare
- [ ] Panoul de admin: tablou de bord, setări, catalog, jurnal @claude
- [ ] `tools/zip.mjs`, `release.mjs`, `flota.mjs` copiate și adaptate din `agensio-consent` @claude
- [ ] Ghid scurt de utilizare pentru owner @claude

## Așteaptă

- [ ] Deciziile de preț ale ownerului: adaos, cotă, marjă minimă, excepții @client
  Întrebările și cifrele sunt în oferta acceptată. Se stabilesc la prima discuție din etapa 2.
- [ ] Confirmarea de la Conex că avem dreptul să afișăm public compatibilitățile auto @iulian
  Datele din `applications` au forma datelor TecDoc. Probabil Conex are licență și redistribuie, dar întrebarea
  trebuie pusă în scris înainte să le punem pe site.
- [ ] Decizia Piese Total pe oferta lor, trimisă pe 24 sept. @iulian
  Dacă acceptă, etapa de potrivire fuzzy (aliasuri de brand, ecran de revizuire) devine necesară. Până atunci,
  `ProductMatcher` are o singură strategie implementată, dar interfața rămâne deschisă.

## Gata

- [x] Repo creat, cu structura Agensio și convențiile de plugin din `agensio-consent` (19.11.2026)
- [x] Clientul de API în PHP, cu tot ce am măsurat pe API-ul real: paginare fără `total`, 404 = sfârșit, retry cu
      backoff, fereastra de noapte, chei normalizate, niciodată date parțiale (19.11.2026)
- [x] Regula de preț `max(adaos, cost + marjă minimă, preț minim)`, cu `wp ccs pret` ca s-o poți proba pe orice cost (19.11.2026)
