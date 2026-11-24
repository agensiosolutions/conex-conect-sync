# Taskuri conex-conect-sync

<!--
Sursa pentru pagina de taskuri (/start). Claude ține fișierul la zi; pagina doar îl arată.
- Secțiunile: Acum, Urmează, Așteaptă, Gata. În Gata rămân doar ultimele 14 zile; ce e mai vechi trăiește în JURNAL.md.
- Un task: `- [ ] Ce se face` plus, opțional, `@iulian` `@claude` `@client` și `📅 AAAA-LL-ZZ` (termen).
- Detaliile stau dedesubt, indentate cu 2 spații (text sau sub-bife `- [ ]`).
-->

## Acum

- [ ] Discuția de preț cu ownerul, pe pagina de decizii de pe live @iulian
  `Sincronizare Conex` în meniul de admin. Ownerul are cont de administrator (`cezauto`). Pagina are date reale:
  3.950 de produse legate, 88 de întrebări fără răspuns (26 potriviri, 2 scăderi de preț, 60 sub cost).
  Modul raport e pornit, deci orice ar apăsa ownerul nu schimbă niciun preț pe site: se înregistrează doar decizia.
- [ ] De hotărât dacă punem catalogul pe cron zilnic, până la prima aplicare @iulian
  Ar ține cifrele din pagină proaspete dacă ownerul o deschide peste câteva zile. Scrie doar în tabelele noastre,
  niciodată în WooCommerce, deci e fără risc. N-am pus-o singur: e o schimbare pe live care n-a fost cerută.
  3.696 de prețuri s-ar schimba: 3.178 în sus (în medie +18,35 lei), 521 în jos (în medie -13,24 lei). Marja brută
  pe bucată crește cu 44,5%. Întrebările, în ordinea în care contează:
  - [ ] Adaosul de 35% și cota de 21% rămân? @client
  - [ ] Marja minimă de 4 lei: pune un clips de caroserie la 5-6 lei. Prea scump? @client
  - [ ] Cele 521 de scăderi de preț vin din produsele cu adaos acum foarte mare (88 între 60 și 100%, 26 peste
        100%). Le lăsăm să coboare sau marcăm o parte „preț manual”? @client
  - [ ] Cele 60 de produse vândute sub cost: cel mai rău cu 394 lei pierdere la bucată. Se corectează la prima
        aplicare sau se verifică una cu una? @client
  - [ ] Cele 392 de produse care ar trece pe epuizat la prima aplicare @client
  Secțiunea 1 din pagină: trage de adaos și de marja minimă și vede efectul pe tot magazinul, instantaneu. Cele
  26 de potriviri, cele 3 scăderi mari și cele 60 de produse sub cost sunt secțiunile 2, 3 și 4, fiecare cu butoane
  pe fiecare produs și cu „la fel pentru toate”.

## Urmează

### Etapa 2: preț și stoc
- [ ] Prima aplicare pe live, după deciziile ownerului @claude
  Comanda e `wp ccs aplica --forteaza`, după `wp ccs setare mod_raport 0`. Durează în jur de 6 minute pentru 3.900
  de produse. Backup de bază înainte, iar dacă e ceva greșit, `wp ccs revino <rulare>`.
- [ ] Marcarea „preț manual” din admin, per produs @claude
  Mecanismul există deja în plan (meta `_ccs_pret_manual` blochează prețul, stocul se aplică în continuare), dar nu
  are încă niciun loc din care clientul să-l bifeze.

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

- [x] Panoul nou pe live, versiunea 0.2.0 (24.11.2026)
      Toate cele șase taburi se randează cu date reale, fără erori. Versiunea a fost urcată de la 0.1.0 tocmai ca
      stilul nou să nu fie servit din cache-ul browserelor. Setările și modul raport au rămas neatinse.
- [x] Panoul de admin refăcut după design-ul din `agensio-consent` (24.11.2026)
      Șase taburi cu navigație laterală, în loc de o pagină lungă cu cinci secțiuni. Cifra din dreptul unui tab
      spune câte răspunsuri lipsesc de acolo. `tools/previzualizare.php` randează acum toate taburile, cu
      navigația rescrisă către fișierele vecine, deci panoul se poate parcurge fără login.
- [x] Live: token pus, catalog preluat, potrivire făcută, pagina de decizii are date reale (24.11.2026)
      20.725 de produse în 7,16 secunde la prima preluare, 2,74 la a doua, cu zero atinse. 3.950 legate pe cod
      exact, 26 de verificat, zero ambiguități. Pagina se randează în 0,89 secunde. Zero erori PHP, site-ul
      răspunde normal.
- [x] Pluginul pus pe live: fișiere urcate, activat, tabele create, modul raport pornit (24.11.2026)
      Backup înainte: `~/backups/cezauto-inainte-de-ccs-20261124-1016.sql.gz`, 12 MB, dump verificat.
      Live are WP 7.1.3, PHP 8.1.34, WooCommerce 11.2.1, aceeași versiune ca pe local. Toate fișierele trec de
      `php -l` cu PHP-ul serverului. Pagina de decizii se randează fără erori.
- [x] Pagina de decizii din admin, cu simulator de preț și acțiuni în masă (20.11.2026)
      Înlocuiește rapoartele CSV pentru discuția cu ownerul. Fiecare decizie se scrie în `ccs_decizii`, cu cine și
      când, și are efect imediat: legătură confirmată, produs exclus definitiv, sau preț marcat manual.
- [x] Etapa 2, partea de cod: planul comun, aplicarea prin CRUD, revenirea și verificarea de nume (19.11.2026)
      WooCommerce 11.2.1 local, aceeași versiune ca pe live. Taxele sunt oprite în WooCommerce, deci prețul introdus
      e prețul final: formula cu TVA inclus e corectă. Probat pe 40 de produse, cu revenire completă pe 78 de valori.
- [x] Verificarea de nume la potrivire: 26 de legături false găsite și scoase din aplicare (19.11.2026)
      Toate veneau din potrivirea pe cod de producător, 26 din 26 greșite. Pe `codEAN`, toate cele 3.954 sunt bune.
      Raportat greșit în dimineața aceea ca „greșeală de preț la SKU 25540”; era o potrivire greșită.
- [x] Etapa 1 închisă: tabele proprii, preluare cu amprentă, legare și raport CSV (19.11.2026)
      Pe date reale: 20.755 produse preluate, 3.980 legate (3.954 pe cod exact, 26 pe cod producător), zero ambiguități.
      Raportul spune 3.724 prețuri de schimbat, 3.485 stocuri, 394 de epuizat, 345 de reactivat, 65 sub cost.
      A doua rulare a catalogului: 3,26 secunde, pentru că amprentele nu s-au schimbat.
- [x] Mediu local gata: site `cezauto-dev` în Local, baza importată (6.852 produse, zero date personale), secretele
      șterse, pluginul legat prin symlink, tokenul în wp-config (19.11.2026)
- [x] `wp ccs test` rulează pe date reale: 20.755 produse în 2,43 s, 108 branduri (19.11.2026)
- [x] Repo privat pe GitHub, primul push făcut (19.11.2026)

- [x] Repo creat, cu structura Agensio și convențiile de plugin din `agensio-consent` (19.11.2026)
- [x] Clientul de API în PHP, cu tot ce am măsurat pe API-ul real: paginare fără `total`, 404 = sfârșit, retry cu
      backoff, fereastra de noapte, chei normalizate, niciodată date parțiale (19.11.2026)
- [x] Regula de preț `max(adaos, cost + marjă minimă, preț minim)`, cu `wp ccs pret` ca s-o poți proba pe orice cost (19.11.2026)
