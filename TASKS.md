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

- [ ] Două precauții ieftine, rămase din audit @iulian
      Le-am semnalat, nu le-am construit, fiindcă acordul a fost pe celelalte trei.
      - [ ] Margini pe politica de preț: azi acceptă orice număr. Un adaos pus din greșeală la 0,9 ar scrie
            prețuri sub cost pe tot magazinul, iar frâna de scădere (50%) nu l-ar prinde.
      - [ ] Limită de mărime la descărcarea unei poze: `download_url()` n-are niciuna.

- [ ] De confirmat unde ține live-ul tokenul API @iulian
      În `wp-config.php` sau în opțiunea din baza de date. N-am putut verifica: `wp-config.php` e pe lista de
      fișiere pe care nu le citesc. Dacă e în baza de date, intră în orice export și în orice backup.

- [ ] Produsele variabile nu sunt tratate separat @claude
      Azi nu contează, toate cele 6.849 de produse de pe live sunt simple. Un singur produs variabil creat mai
      târziu ar primi `set_regular_price()` pe părinte, unde prețul vine din variații. O gardă de o linie.

- [ ] Cele 14 compresoare din catalogul Conex, de importat în „Compresor auto” @iulian
  Categoria e în meniul principal al site-ului și e goală. Conex are 21 de compresoare, 7 sunt deja pe site dar în
  alte categorii, 14 nu sunt deloc. Intră în etapa 4, odată cu importul.

- [ ] De hotărât ce facem cu `ccs_site_skuuri()` din `plugin/includes/potrivire.php` @iulian
  Nu mai e chemată de nicăieri: a rămas ca punte de compatibilitate când am rescris potrivirea pe 19 nov. Ori o
  ștergem, ori o păstrăm declarat, ca funcție publică pentru alte pluginuri. Semnalată, nu ștearsă.

### Etapa 2: preț și stoc
- [ ] Prima aplicare pe live, după deciziile ownerului @claude
  Comanda e `wp ccs aplica --forteaza`, după `wp ccs setare mod_raport 0`. Durează în jur de 6 minute pentru 3.900
  de produse. Backup de bază înainte, iar dacă e ceva greșit, `wp ccs revino <rulare>`.
- [ ] Marcarea „preț manual” din admin, per produs @claude
  Mecanismul există deja în plan (meta `_ccs_pret_manual` blochează prețul, stocul se aplică în continuare), dar nu
  are încă niciun loc din care clientul să-l bifeze.

### Etapa 3: curățare și joburi de noapte
- [ ] Prima preluare reală de imagini, după ora 19:00 @claude
  Comanda: `wp ccs imagini --preia` pe live. Lanțul e probat pe datele sondate în octombrie; preluarea adevărată se
  poate face doar în fereastra furnizorului.

- [ ] Curățarea celor 2.439 produse rămase din feed-ul vechi @iulian
  Lista e făcută din 16 luni de Search Console: `cez-auto/rapoarte/curatenie-2026-11-26.json`, 1.827 de șters
  (zero afișări, zero comenzi, zero recenzii) și 612 de ascuns din catalog. Se dă cu
  `wp ccs curata --fisier=... --executa`, probat pe local, reversibil cu `wp ccs revino`. Cere push pe live.
- [x] Panoul rescris în limbajul Omni Supplier Sync, direcțiile A, B și C (27.11.2026)
      A: linia de stare, cifre cu plăcuță și pastilă, ultima rulare cu verbe, jurnalul rulărilor, revenire din
      panou. B: poze pe rând, pastile, grupare pe gravitate, totaluri. C: catalogul furnizorului răsfoibil, cu
      filtre prin URL și import pe bifă. Referințele vizuale: `cez-auto/docs/referinte-design/`.
- [x] Ghidul scurt de utilizare pentru owner (27.11.2026)
      `cez-auto/docs/ghid-panou-cezauto.md`. Scris pentru Roli, fără termeni tehnici. Cu asta, tot ce promite
      oferta e construit.
- [ ] Filtru și coloană în wp-admin > Produse @claude
  Scrise și probate pe local, nu sunt încă pe live. Roli lucrează în lista de produse, nu în panou, și acolo nu
  găsea nimic. De verificat vizual pe live după urcare.

### Etapa 4: import de produse noi
- [x] Importul produselor complete, scris și probat pe local (26.11.2026)
      `wp ccs import --creeaza` creează produsul ca ciornă, cu preț, stoc, categorie, atribute și poze, și îl leagă
      pe loc de furnizor. Poze redenumite din denumirea produsului, cu text alternativ. Reversibil cu
      `wp ccs revino`. Probat pe produse reale: creare, verificare, revenire, de două ori.
- [ ] Prima rulare de import pe live @iulian
      Așteaptă preluarea pozelor de pe live, care se face în fereastra de noapte. Pe live toate cele 16.778 produse
      nelegate sunt oprite de lipsa pozelor, deci până atunci nu e nimic de importat. Prima rulare cu `--limita=20`,
      produsele rămân ciorne.
- [ ] Cele 19 reguli de categorie prea slabe, de confirmat cu ownerul @client
  Învățate din unul sau două produse, deci nefolosite. Intră în aceeași discuție cu cele 96 de căi nemapate.
- [ ] Mecanismul care urcă un produs când furnizorul îi adaugă poza @claude
- [ ] Maparea celor 96 de căi rămase, cu ownerul @client
  Sunt aproape toate piese OE: bare față, hayoane, oglinzi, plăcuțe și discuri de frână, amortizoare. Depinde de
  decizia lui dacă intră pe piese OE. Site-ul are o singură categorie pentru tot, „Piese auto”, cu 112 produse.
- [ ] Un tab de categorii în panou, pentru căile pe care regula nu le știe @claude
  Are rost după ce ownerul hotărăște dacă intră pe piese OE: altfel îi punem în față 96 de căi care poate nu-l
  interesează.

### Etapa 5: panou, cron, predare
- [ ] Panoul de admin: tablou de bord, setări, catalog, jurnal @claude
- [ ] `tools/zip.mjs`, `release.mjs`, `flota.mjs` copiate și adaptate din `agensio-consent` @claude
- [ ] Ghid scurt de utilizare pentru owner @claude

## Așteaptă

- [ ] Cele 2.435 rămase din feed-ul Mega Drive, de hotărât @iulian
  1.826 sunt încă afișate ca disponibile, deși feed-ul e înghețat din noiembrie 2025 și Conex nu le mai are.
  Aceeași comandă, grupul `din_feed`. Cele 292 cu SKU `CZ*`, introduse manual de echipă, rămân neatinse.
- [ ] Cele 26: corectarea NU trebuie făcută, de relămurit cu ownerul @iulian
  Previzualizarea arată că nu e o schimbare de cod, ci o ciocnire de numerotări. ROMIX folosește coduri de
  producător de 5 cifre (109 din cele 344 de produse ale lor), care nimeresc peste SKU-urile rămase din feed-ul
  Mega Drive. Codul propriu al produsului Conex e cu totul altul: SKU `10000` de pe site duce la produsul Conex
  `61497`, care doar are `10000` ca **cod de producător**.
  Dacă s-ar aplica: 10 din 26 ar deveni clipsuri de tapițerie, valoarea ar scădea de la 4.113 la 1.465 lei, iar
  aspiratorul Kärcher ar deveni ulei Liqui Moly. Întrebarea bună pentru owner: **mai vinde produsele astea 26?**
- [ ] Ce facem cu cele 26 de produse pe cod comun @client
  Produsul Conex de pe acel cod e alt produs, nu o versiune nouă: la `10000`, site-ul are o pompă de aer, Conex un
  set de clipsuri. Toate 26 vin din potrivirea pe cod de producător, care la Cez Auto n-a nimerit nimic. Unul
  singur, SKU `20465`, chiar e același produs sub alt cod (`1.633-453.0`, aspirator de geamuri Kärcher).
  Întrebarea pentru owner: cele 25 le mai poate aduce de undeva?

- [ ] Deciziile de preț ale ownerului: adaos, cotă, marjă minimă, excepții @client
  Întrebările și cifrele sunt în oferta acceptată. Se stabilesc la prima discuție din etapa 2.
- [ ] Confirmarea de la Conex că avem dreptul să afișăm public compatibilitățile auto @iulian
  Datele din `applications` au forma datelor TecDoc. Probabil Conex are licență și redistribuie, dar întrebarea
  trebuie pusă în scris înainte să le punem pe site.
- [ ] Decizia Piese Total pe oferta lor, trimisă pe 24 sept. @iulian
  Dacă acceptă, etapa de potrivire fuzzy (aliasuri de brand, ecran de revizuire) devine necesară. Până atunci,
  `ProductMatcher` are o singură strategie implementată, dar interfața rămâne deschisă.

## Gata

- [x] Auditul de securitate și robustețe, înainte de 1.0 (27.11.2026)
      Securitatea e în regulă, cu dovezi: 11 din 11 endpointuri AJAX cu nonce și capabilitate, o singură acțiune
      pe GET și aia verificată, datele utilizatorului în SQL numai prin `prepare()`, extensia pozelor pe listă
      albă, tokenul niciodată în mesajele de eroare.
- [x] Regulile de categorie se verifică înainte de folosire (27.11.2026)
      O categorie ștearsă de pe site nu dă eroare: WooCommerce pune produsul în categoria implicită, care pe
      cezauto.ro e „Accesorii auto”. Acum regula moartă e ignorată și produsul așteaptă o categorie.
- [x] Verificarea de configurare, în panou și pe email (27.11.2026)
      `sanatate.php`: TVA-ul, moneda, regulile de categorie moarte, atributele furnizorului care ar intra peste
      atribute ale magazinului. Patru presupuneri care, dacă pică, pică fără zgomot.
- [x] Lacătul de scriere, în toate cele patru operații care schimbă produse (27.11.2026)
      Până acum îl lua doar jobul de catalog. Reintrant, fiindcă jobul îl ține pentru toată rularea. 14 teste
      noi, probate cu două mutații.
- [x] Fontul panoului, de la 14px la 15px (27.11.2026)
      Cifrele mari și plăcuțele de iconițe au rămas cum erau.
- [x] Iconițele panoului, de la Dashicons la Lucide (27.11.2026)
      25 de iconițe lipite ca SVG în `includes/iconite.php`, generat cu `tools/adu-iconite.py`, nu luate de pe un
      CDN. Cheile au rămas numele vechi, fiindcă descriu rolul, nu desenul: alt pachet se schimbă rescriind numai
      fișierul generat. Mărimea vine acum din apel, nu dintr-o regulă CSS globală.
- [x] Butonul „Arată pașii”, în antet (27.11.2026)
      Apare doar după ce ghidul a fost ascuns și doar cât mai e ceva de pus la punct, și duce pe Panou, fiindcă
      numai acolo se randează cardul cu pași.
- [x] Explicația „Scrierea pe site e oprită”, mutată în pastilă (27.11.2026)
      `ccs_ui_pastila_info()`: pastila își poartă singură explicația, iar hover-ul prinde pe tot textul, nu pe o
      iconiță de lângă. Același tipar și în mesajul din Catalogul furnizorului.
- [x] Corecturile cerute după ce Iulian s-a uitat pe live (27.11.2026)
      Butoanele din „Aplică aceeași alegere pentru toate” arătau inactive, rândurile aveau un hover rămas de la
      altă stilizare, iar Catalogul afișa zero fără să spună de ce. Zeroul e real: tabela de poze e goală și toate
      produsele nelegate sunt oprite de condiția „fără poză”. Jobul de poze rulează la 19:10.
- [x] Resursele poartă data fișierului în versiune (27.11.2026)
      Versiunea pluginului nu se mișcă într-o sesiune de schimbări de CSS, deci browserul servea fișierul vechi.
- [x] Maparea manuală a căilor, din panou (26.11.2026)
      Cele 98 de căi fără raion, sortate după câte produse ar debloca, fiecare cu un selector de categorie.
      Probat pe uleiuri: o singură alegere a mutat 307 produse din „așteaptă” în „gata de adus”, iar regula
      supraviețuiește reînvățării. Motivul pentru care lipsea: învățarea se uită doar la produsele legate, iar
      uleiurile magazinului au cod propriu, deci regula n-avea de unde să fie învățată.
- [x] Două bug-uri găsite de testul maparii (26.11.2026)
      `ccs_decizii.referinta` era VARCHAR(64), iar căile de categorie ajung la 105 caractere. Mai rău,
      `ccs_decizie_scrie()` nu verifica dacă scrierea a reușit, deci o decizie pierdută raporta „Salvat”.
- [x] Tabul „Catalogul”, refăcut ca să se citească dintr-o privire (26.11.2026)
      Trei carduri de acțiune în culoare plină, două bare segmentate pentru cele două cataloage, mișcarea cu plus
      și minus, asocierea categoriilor cu bară de încredere, și cinci produse desenate exact cum ar arăta pe site,
      cu poza furnizorului. Aici regula „culoare doar pe stare” se relaxează deliberat: tabul arată două cataloage
      de zeci de mii de produse, iar culoarea plină e singurul lucru care le face inteligibile fără să citești
      cifre.
- [x] Tabul „Catalogul” pe live, versiunea 0.6.0 (26.11.2026)
      Pe datele de acolo: 3 produse noi, 10 ieșite, 20.718 în catalog. Grupurile: 2.439 din feed (1.830 încă de
      vânzare), 292 manuale, 16 altele, 26 excluse (toate retrase). Toate cele 7 taburi fără erori.
- [x] Tabul „Catalogul”: mișcarea furnizorului plus restanța, pe două coloane (26.11.2026)
      Sus: produse noi și ieșite în ultimele 7 zile, cu liste. Jos: „Ce dispare”, pe grupuri după tiparul codului,
      cu buton de retragere, și „Ce vine nou”, cu pâlnia de motive. „Vezi lista” duce în lista de produse din
      WordPress, filtrată pe grup, nu într-un tabel inventat de noi.
- [x] Cele 26 retrase de la vânzare pe live, versiunea 0.5.0 (26.11.2026)
      Backup înainte: `~/backups/cezauto-inainte-de-retragere-20261126-1237.sql.gz`, 16 MB, verificat.
      19 trecute pe epuizat, 7 erau deja. 38 de valori în `ccs_modificari`, deci `wp ccs revino 4` le readuce.
      Pagina produsului arată „Stoc epuizat”, site-ul răspunde în 0,35 secunde cu cache cald.
- [x] Retragerea de la vânzare, scrisă și probată pe local (26.11.2026)
      Produsele trec pe stoc epuizat, nu se șterg niciodată: pagina și adresa rămân, iar vizitatorul din Google
      ajunge pe site, nu în 404. Grupuri după tiparul codului: `excluse` 26, `din_feed` 2.435, `manuale` 292,
      `altele` 16. Probat pe cele 26, inclusiv revenirea: 38 de valori readuse exact.
- [x] Un bug la citirea titlurilor, găsit de protecția corecțiilor (26.11.2026)
      `get_the_title()` trece prin `wptexturize` și transformă liniuța în `&#8211;`, care se rupea în cuvântul
      „8211”. Potrivirea principală citea titlul direct din bază, corecțiile prin filtru: două căi, două rezultate.
      Pe aspiratorul Kärcher, asemănarea scădea de la 0,86 la 0,75 și cădea sub prag. Decodat acum peste tot, cu
      două teste în plus.
- [x] Preluarea reală a unui produs, cu previzualizare completă (24.11.2026)
      `wp ccs produs <cod>` ia produsul de la furnizor în ~2 secunde și arată ce s-ar crea: preț cu formula
      desfăcută, categorie cu regula care a decis-o, poze, atribute, plus piedicile. Nu scrie nimic.
      Măsurat atunci: API-ul **nu filtrează pe produs**, `total` rămâne 20.725 pentru `productId`, `ProductId`,
      `codEAN`, `code` și `search`.
- [x] Praguri pentru regulile de categorie, după ce previzualizarea a scos o regulă din 1 produs (24.11.2026)
      Sub 3 produse sau sub 60% încredere, regula nu se mai folosește la import. Cele gata de import au scăzut de la
      1.618 la **1.059**, aproape de cele 993 din ofertă. 45 de reguli folosibile din 64.
- [x] Jobul de noapte pentru imagini, plus lista de produse importabile (24.11.2026)
      Tabel propriu cu adresele pozelor, job programat la 19:10 în fereastra furnizorului, și `wp ccs import` care
      spune câte produse se pot importa și de ce nu se pot celelalte. Probat pe datele din octombrie: 1.618 gata de
      import, 9.519 fără poză, 4.963 fără categorie știută, 700 fără stoc.
- [x] Un bug de fus orar la programarea joburilor de noapte, găsit și acoperit cu teste (24.11.2026)
      Jobul s-ar fi programat la 21:10 în loc de 19:10. Regula ferestrei e acum o funcție separată, probată pe 12
      ore și pe marginile intervalului, plus trei mutații.
- [x] Categoriile duplicate de pe site, curățate (24.11.2026)
      Șterse: „Siguranța auto”, „Siguranta rutiera” și „CONSUMABILE SERVICE”. Stingătorul din prima a trecut în
      „Siguranță rutieră”, care are acum 23 de produse. Cele trei adrese au redirect 301 prin Rank Math.
      „Compresor auto” **nu** s-a șters: e în meniul principal și Conex are 21 de compresoare. 62 de categorii au
      devenit 59, toate cele 79 de intrări din cele cinci meniuri sunt întregi.
- [x] Versiunea 0.4.0 pe live: joburi programate și mapare de categorii (24.11.2026)
      Backup înainte: `~/backups/cezauto-inainte-de-ccs-0.4.0-20261124-1253.sql.gz`, 13 MB, verificat.
      Pe datele de pe live: 64 de reguli din 3.950 de produse legate, aceleași 7 reguli pe cuvânt. Joburile rămân
      oprite, modul raport rămâne pornit, cele 60 de decizii sunt intacte.
- [x] Maparea categoriilor, învățată din cele 3.954 de produse legate (24.11.2026)
      64 de reguli: 57 pe cale, 7 pe cuvânt din nume. Probată pe un set de test de 1.157 de produse din care n-a
      învățat: **95,5% corect**, 3 fără răspuns. Regula covorașelor a fost găsită singură („PORTBAGAJ” duce în
      „Accesorii interior”, 431 de produse). Cuvintele care sunt branduri se sar.
- [x] Joburile programate pe Action Scheduler, cu comutator în panou (24.11.2026)
      Catalogul se preia la un interval configurabil, cu lacăt contra rulărilor suprapuse. Cu modul raport pornit,
      jobul ține doar cifrele din panou la zi. Pornit din panou sau cu `wp ccs joburi --porneste`; implicit oprit.
- [x] Regula de produse absente din feed, probată: la 2 runde lipsă trec pe stoc 0 și „epuizat” (24.11.2026)
      Nu se șterg niciodată. Verificat pe un produs real, cu lipsa_runde de la 0 la 3.
- [x] Cele 60 de produse sub cost, confirmate de owner pe „corectează” (24.11.2026)
      Înregistrate în `ccs_decizii`. Neaplicate: așteaptă politica de preț.
- [x] Panoul nou pe live, versiunea 0.3.0 (24.11.2026)
      Toate cele șase taburi se randează fără erori, cu tonurile de decizie prezente (54 pe potriviri, 122 pe sub
      cost). Modul raport e în continuare pornit: zero decizii luate, zero scrieri în WooCommerce.
- [x] Panoul trecut prin unslop: grilă de 4px, o rampă, semnătura „acum → nou” (24.11.2026)
      Skillul scanat cu SkillSpector înainte: 6 HIGH și 5 MEDIUM, toate cele HIGH false pozitive, verificate de mână.
      Două dintre principiile lui n-au fost aplicate ca atare, pentru că aici conținutul nu e o pagină de prezentare.
- [x] Notificările celorlalte pluginuri, scoase de pe ecranul nostru (24.11.2026)
      Site-ul are 38 de notificări agățate de alte pluginuri, printre care una roșie despre o licență Elementor
      expirată. Pe pagina de decizii nu se mai afișează niciuna; în restul adminului rămân toate.
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
