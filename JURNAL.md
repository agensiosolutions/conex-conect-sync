# Jurnal conex-conect-sync

## 19 noiembrie 2026

Prima zi de cod. Oferta Cez Auto a fost acceptată pe 17 noiembrie, deci pluginul are primul client plătitor.

**Ce s-a hotărât.** Repo separat, nu în proiectul Cez Auto: pluginul e produs, nu lucrare. Dezvoltare locală pe o
copie a bazei Cez Auto, pentru că toată valoarea stă în potrivirea datelor reale. Stil de cod ca la `agensio-consent`,
cu `includes/` și prefix `ccs_`, nu PSR-4 cum propunea `PLAN.md`: consecvența cu celălalt plugin al agenției
cântărește mai mult decât structura din plan, iar Iulian le întreține pe amândouă.

**Răsturnarea de plan.** `PLAN.md` e scris cu Piese Total ca prim client, dar ei n-au răspuns după trei runde de
follow-up, iar Cez Auto a acceptat. Cele două magazine cer lucruri foarte diferite: la Piese Total potrivirea cere
fuzzy matching, aliasuri de brand și ecran de revizuire, la Cez Auto e `codEAN` exact cu zero ambiguități. Construim
pentru Cez Auto, dar cu interfețele deschise, ca mașinăria de la Piese Total să se adauge fără rescriere.

**Ce s-a scris.** Scheletul pluginului, clientul de API, setările cu regula de preț și trei comenzi WP-CLI. Clientul
de API conține tot ce am măsurat empiric pe API-ul real în octombrie, cu explicația lângă fiecare regulă, pentru că
niciuna nu e în documentație.

**Seara: mediul local.** Site `cezauto-dev` în Local by Flywheel, cu o copie a bazei de producție. Dump-ul ia
structura tuturor celor 136 de tabele, ca WordPress să pornească, dar datele doar pentru 15: produse, taxonomii,
atribute, setări. Zero comenzi, clienți, sesiuni, recenzii, intrări de formulare sau tabele Wordfence. Magazinul are
28 de comenzi reale; n-aveam nevoie de ele ca să construiesc sincronizarea de produse, deci nu le-am copiat.

`wp ccs test` rulează pe date reale: **20.755 de produse în 2,43 secunde**, 108 branduri. Catalogul avea 21.002
produse pe 13 octombrie, deci s-au retras 247 în cinci săptămâni, exact argumentul pentru care clientul plătește
sincronizarea. Produsele cu taxă verde au ajuns la 978, față de 40 cât numărasem la Piese Total în septembrie.

Regula de preț, probată pe cazuri-limită: un cost de 0,22 lei dă 5 lei preț (marja minimă intră în joc), iar 41,52
lei dau 68 de lei, adică exact 35% adaos. De discutat cu ownerul dacă 4 lei marjă minimă e valoarea potrivită: pune
un clips de carosierie la 5 lei, ceea ce poate fi prea scump pentru piață. E un parametru în setări, nu o constantă.

`Lecție:` Local by Flywheel nu pune WP-CLI în PATH și n-are CLI pentru site-uri, dar are tot ce trebuie în instalare:
PHP-ul propriu în `lightning-services`, `wp-cli.phar` în `resources/extraResources`, clientul MySQL tot acolo. Trei
capcane, în ordinea în care apar: PHP-ul de sistem n-are `mysqli`, deci trebuie folosit cel din Local cu `php.ini`-ul
randat al site-ului din `run/<id>/conf/php/php.ini`; `wp db import` cheamă binarul `mysql`, care nu e în PATH;
iar clientul MySQL se duce pe portul 3306 în timp ce site-ul ascultă pe portul lui (aici 10005), pentru că PHP ia
portul din `mysqli.default_port`, nu din `DB_HOST`. Simptomul ultimei: PHP se conectează fără probleme, dar
`wp db import` spune „Failed to get current SQL modes... Can't connect on localhost:3306”. Rezolvarea e să pui
portul explicit în `DB_HOST`. Totul e împachetat în `tools/wp-local.sh`.

`Lecție:` Când copiezi baza unui magazin pe mediul de dezvoltare, lista albă de tabele e mai sigură decât lista
neagră: dumpezi structura tuturor tabelelor, ca aplicația să pornească, și datele doar pentru cele de care ai
nevoie. Cu lista neagră uiți mereu ceva, iar la WooCommerce cu pluginuri „ceva” înseamnă IP-uri, secrete 2FA,
passkeys, intrări de formulare și jurnale de email. Tabela `options` rămâne oricum obligatorie și aduce cheile de
plată și facturare, deci are nevoie de o curățare separată după import.

`Lecție:` Când un furnizor îți dă un API, măsoară-i comportamentul înainte să construiești pe el, la ore diferite și
pe mai multe zile. La Conex, documentația nu spunea niciuna dintre cele cinci reguli care contează: `total` nu e
numărul de rânduri, o pagină peste final dă 404 în loc de listă goală, endpoint-urile secundare răspund doar noaptea,
rata de eșec crește pe parcursul nopții (0% la 19:04, 100% la 21:52 pe cel mai greu endpoint), iar un parametru de
filtrare schimbă capitalizarea cheilor. Simptomul după care recunoști nevoia: un import care raportează constant
„zero modificări” sau un `total` care nu se potrivește cu ce numeri tu în răspuns.

`Lecție:` Într-un import de la furnizor, un fetch eșuat nu trebuie confundat niciodată cu „datele au dispărut”. Dacă
o pagină nu vine, întreaga preluare trebuie să eșueze fără să scrie nimic. Altfel o eroare 500 a furnizorului la ora
3 noaptea trece produsele pe stoc zero în masă, iar magazinul se golește singur. Simptomul: cod de sincronizare care
calculează diferențe între „ce am primit” și „ce era”, fără să verifice întâi că a primit tot.

`Lecție:` Adaosul procentual singur se comportă prost la produsele ieftine. Un clips de 0,22 lei cost ajunge la 1 leu,
adică +359% adaos, dar 0,78 lei marjă brută, sub costul de procesare a comenzii. Regula corectă e
`max(cost × adaos, cost + marjă_minimă_lei, preț_minim)`. Pe 2.493 de produse măsurate, o marjă minimă de 4 lei
rezolvă toate cele 255 de cazuri problematice și costă 543 de lei, adică 0,13% din valoarea catalogului. Simptomul:
produse cu adaos procentual uriaș și preț absolut derizoriu.
