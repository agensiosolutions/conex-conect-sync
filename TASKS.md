# Taskuri conex-conect-sync

<!--
Sursa pentru pagina de taskuri (/start). Claude ține fișierul la zi; pagina doar îl arată.
- Secțiunile: Acum, Urmează, Așteaptă, Gata. În Gata rămân doar ultimele 14 zile; ce e mai vechi trăiește în JURNAL.md.
- Un task: `- [ ] Ce se face` plus, opțional, `@iulian` `@claude` `@client` și `📅 AAAA-LL-ZZ` (termen).
- Detaliile stau dedesubt, indentate cu 2 spații (text sau sub-bife `- [ ]`).
-->

## Acum

### Etapa 1: legare și raport
- [ ] Tabelele proprii (`ccs_products`, `ccs_links`, `ccs_runs`, `ccs_log`), cu `dbDelta` @claude
  Primul lucru la reluare. Mediul local e gata și testat.

## Urmează

### Etapa 1: legare și raport
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

- [x] Mediu local gata: site `cezauto-dev` în Local, baza importată (6.852 produse, zero date personale), secretele
      șterse, pluginul legat prin symlink, tokenul în wp-config (19.11.2026)
- [x] `wp ccs test` rulează pe date reale: 20.755 produse în 2,43 s, 108 branduri (19.11.2026)
- [x] Repo privat pe GitHub, primul push făcut (19.11.2026)

- [x] Repo creat, cu structura Agensio și convențiile de plugin din `agensio-consent` (19.11.2026)
- [x] Clientul de API în PHP, cu tot ce am măsurat pe API-ul real: paginare fără `total`, 404 = sfârșit, retry cu
      backoff, fereastra de noapte, chei normalizate, niciodată date parțiale (19.11.2026)
- [x] Regula de preț `max(adaos, cost + marjă minimă, preț minim)`, cu `wp ccs pret` ca s-o poți proba pe orice cost (19.11.2026)
