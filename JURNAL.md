# Jurnal conex-conect-sync

## 24 noiembrie 2026

**Pluginul e pe live.** Iulian a decis să facem verificările direct pe magazinul real: traficul e mic, iar comenzile
se acceptă manual, deci o greșeală se vede înainte să plece ceva la client. Argument bun, iar pluginul e construit
exact pentru situația asta: modul raport e pornit din start și nicio linie nu se scrie în WooCommerce fără ca
cineva să-l oprească explicit.

Ordinea, și motivul fiecărui pas: verificări read-only întâi, apoi backup cu `mysqldump` (12 MB, cu ultima linie
`-- Dump completed on` verificată, pentru că pe Hosterion `wp db export` eșuează fără eroare), apoi fișierele, apoi
activarea. Serverul n-are `rsync`, deci `tar` printr-un tunel SSH. Toate fișierele trec de `php -l` rulat cu PHP-ul
serverului, nu cu al meu: live are PHP 8.1.34, local are 8.2.27, iar o sintaxă mai nouă s-ar fi văzut abia la prima
cerere, pe un site în funcțiune.

Live: WordPress 7.1.3, PHP 8.1.34, WooCommerce 11.2.1. Aceeași versiune de WooCommerce ca pe local, ceea ce
înseamnă că tot ce am probat despre CRUD și `wc_product_meta_lookup` e valabil și aici.

**Pasul care a rămas la Iulian.** Tokenul API trebuia pus în `wp-config`. Am scris `tools/token-live.py`, care îl
trimite prin intrarea standard a unui PHP rulat pe server, nu ca argument de comandă: pe un server partajat,
argumentele se pot citi din `/proc/<pid>/cmdline`, iar variabilele de mediu doar de proprietar și de root, deci
stdin e singura cale care nu lasă tokenul nicăieri. Scriptul face și o copie a lui `wp-config.php` înainte.

Rularea a fost oprită de regula care nu-mi dă voie să citesc `.env` și să-i trimit conținutul pe un server. Regula e
corectă, scriptul rămâne scris, iar comanda o dă Iulian. Până atunci pluginul e activ, tabelele există și pagina de
decizii spune corect că nu are încă legături.

**Seara: live-ul merge pe date reale.** Iulian a pus tokenul, iar restul a mers dintr-o bucată. API-ul răspunde de
pe server în 2,54 secunde cu 20.725 de produse, cu 30 mai puține decât pe 19 noiembrie: furnizorul retrage produse
în continuare, cam șase pe zi.

Surpriza plăcută: prima preluare a catalogului, care pe local durase 109 secunde, a durat **7,16 secunde pe live**.
A doua rulare, 2,74 secunde, cu toate cele 20.725 de amprente neschimbate. Îngrijorarea pe care o notasem pe 19
noiembrie, că scrierea rând cu rând ar fi prea lentă și ar trebui trecută pe inserturi în bloc, era un artefact al
bazei de date din Local by Flywheel, nu o problemă reală. Bine că n-am optimizat-o înainte s-o măsor unde contează.

Potrivirea pe live: 3.950 pe cod exact, aceleași 26 de verificat, zero ambiguități. Planul se calculează în 0,9
secunde, iar pagina de decizii se randează în 0,89 secunde și are 85 KB. Zero erori PHP, site-ul răspunde normal,
fișierele CSS și JS se servesc public.

**Panoul, refăcut după `agensio-consent`.** Iulian a cerut același design ca la celălalt plugin al agenției, și
avea dreptate să-l ceară: două panouri ale aceleiași agenții care arată diferit par două produse cumpărate de la
oameni diferiți. Am luat de acolo regula de design (fundal deschis, gri neutre, culoare doar pe butoane și pe
indicatorii de stare), structura paginii (antet cu iconiță, navigație laterală lipită, carduri) și împărțirea
fișierelor: `admin.php` pentru logică, `admin-ui.php` pentru interfață, ca cine umblă la amândouă să le găsească
la fel.

Ce am schimbat față de original, pentru că aici conținutul e altul: la agensio-consent omul configurează, aici omul
hotărăște. Cele cinci secțiuni dintr-o pagină lungă au devenit șase taburi, fiecare cu o întrebare, iar cifra din
dreptul unui tab din navigație spune câte răspunsuri lipsesc de acolo. Panoul adaugă un card „De hotărât” care duce
direct în tabul potrivit. Așa, omul vede dintr-o privire ce i se cere și nu trebuie să deruleze ca să afle.

`tools/previzualizare.php` randează acum toate cele șase taburi în fișiere separate și rescrie legăturile din
navigație către fișierele vecine, deci panoul se poate parcurge întreg fără login. O capcană: datele puse cu
`wp_localize_script()` se adună pe handle-ul scriptului, deci între taburi trebuie golite, altfel al doilea tab
primește și datele primului.

**Pus pe live, versiunea 0.2.0.** Toate cele șase taburi se randează cu date reale, fără erori, iar datele
simulatorului ajung în pagină: 45 KB de costuri, fără nimic în plus și fără tokenul API.

Versiunea a fost urcată de la 0.1.0 dintr-un motiv care se uită ușor: adresa stilului are versiunea pluginului în
coadă, iar dacă versiunea nu se schimbă, browserele servesc fișierul vechi din cache. Panoul ar fi arătat stricat
exact la oamenii care l-au mai deschis o dată, adică la Iulian și la owner.

`Lecție:` Când agenția are mai multe pluginuri la același client, panourile lor trebuie să arate la fel: aceleași
culori, aceeași structură de pagină, aceeași împărțire a fișierelor. Nu e o chestiune de gust, e una de încredere:
două panouri care arată diferit par două produse cumpărate de la oameni diferiți. Ia regula de design și structura
din primul, și schimbă doar ce cere conținutul.

`Lecție:` Când schimbi CSS-ul sau JavaScript-ul unui plugin, urcă-i versiunea. WordPress pune versiunea pluginului
în coada adresei fișierului, iar fără schimbarea ei browserele servesc din cache fișierul vechi. Simptomul e
înșelător: pagina arată corect la cine o deschide prima oară și stricat la cine a mai văzut-o, adică fix la clientul
care ți-a dat părerea ieri.

`Lecție:` `wp_localize_script()` adaugă datele pe handle-ul scriptului și le păstrează. Dacă randezi mai multe
pagini în aceeași rulare de PHP, ca la o previzualizare în masă, a doua pagină primește și datele primei. Se golesc
cu `wp_scripts()->registered['<handle>']->extra['data'] = ''` între randări. Simptomul: prima pagină e corectă, a
doua are în plus datele primei, iar JavaScript-ul folosește ce găsește primul.

`Lecție:` Nu optimiza pe baza unei măsurători făcute pe mediul de dezvoltare. O scriere care dura 109 secunde pe
baza de date locală a durat 7 pe server, adică de cincisprezece ori mai puțin. Simptomul care trebuie să te
oprească: ai o cifră proastă, dar vine de pe un MySQL care rulează într-un container pe laptop. Măsoară pe
infrastructura reală înainte să rescrii ceva.

`Lecție:` Când pui cod pe un server, rulează `php -l` cu PHP-ul serverului, nu cu al tău. Versiunile diferă aproape
întotdeauna, iar o sintaxă mai nouă nu se vede la upload: se vede la prima cerere a unui vizitator, sub forma unei
pagini albe. Costă o comandă și se face înainte de activare.

`Lecție:` Un secret care trebuie să ajungă pe un server se trimite pe intrarea standard, nu ca argument de comandă.
Argumentele oricărui proces sunt citibile de toți utilizatorii prin `/proc/<pid>/cmdline`; variabilele de mediu sunt
citibile doar de proprietar și de root. Simptomul că ai greșit: secretul apare în `history`, în `ps` sau în jurnalul
de comenzi al uneltei de deploy.

## 20 noiembrie 2026

**Rapoartele CSV nu sunt pentru client.** Iulian s-a uitat la cele trei fișiere de ieri și a spus direct ce era de
spus: nu-l ajută, pentru că nu se înțeleg vizual, și oricum ownerul nu le poate folosi ca să dea un răspuns. Avea
dreptate, iar problema e mai adâncă decât formatul. Un CSV cu 3.954 de rânduri nu e un document de decizie, e o
groapă. Iar deciziile luate pe email se pierd: peste trei luni nimeni nu mai știe de ce are un produs preț fix.

**Ce s-a construit în loc.** O pagină în admin, `Sincronizare Conex`, cu cinci întrebări numerotate și datele lângă
fiecare. Ownerul intră cu userul lui, pentru că face deja importurile manuale, deci e administrator și știe drumul.

Întrebarea 1 e un simulator: trage de adaos și de marja minimă și vede pe loc câte prețuri urcă, câte coboară, câte
rămân sub cost și cât se schimbă câștigul brut pe tot magazinul, plus șase produse concrete de la cel mai ieftin la
cel mai scump. Calculul se face în browser, pentru că un om care trage de un cursor vrea răspuns imediat, nu după o
secundă. Formula din JavaScript e oglinda lui `ccs_pret()`, iar la salvare serverul recalculează și pagina arată
cifrele lui, ca o eventuală diferență între cele două să sară în ochi.

Întrebările 2, 3 și 4 sunt listele de ieri, dar cu butoane: cele 26 de potriviri dubioase apar ca două produse față
în față, cu poza de pe site și procentul de cuvinte comune; cele 3 scăderi mari și cele 60 de produse sub cost apar
ca tabele cu prețul de acum, prețul nou și câștigul, fiecare cu decizia lui.

**Ce am adăugat după ce am văzut pagina în browser.** Cele 60 de produse sub cost cereau 60 de apăsări. Un om care
are de dat 60 de clicuri nu dă niciunul, iar întrebarea rămâne fără răspuns și blochează tot. Am pus „la fel pentru
toate cele rămase”, cu confirmare la al doilea clic pe același buton, nu cu fereastra de confirmare a browserului,
care întrerupe și arată ca o eroare.

**Previzualizarea fără login.** Paginile de admin cer autentificare, iar pe mediul local n-avem parola și nici nu ne
trebuie ca să vedem cum arată pagina. `tools/previzualizare.php` randează funcția direct prin WP-CLI, îi pune
stilurile reale de admin, care sunt fișiere statice, și scrie rezultatul în `uploads`. O capcană: nu se declanșează
hook-ul global `admin_enqueue_scripts`, pentru că acolo se agață și WooCommerce, care cade fără context de cerere.
Se cheamă direct funcția noastră de încărcare.

**Probat.** Excluderea unui produs îi șterge legătura și potrivirea nu-l mai leagă la rulările următoare. Prețul
marcat manual blochează prețul în plan, dar stocul continuă să se sincronizeze; am verificat-o stricând intenționat
stocul unui produs cu preț manual și uitându-mă ce zice planul.

`Lecție:` Când clientul trebuie să ia o decizie, nu-i trimite datele, pune-i întrebarea. Un raport cu mii de rânduri
e un instrument pentru cine construiește, nu pentru cine hotărăște: ownerul nu vrea să vadă 3.954 de produse, vrea
să răspundă la cinci întrebări. Simptomul după care recunoști situația: ai scos un export și aștepți de la client un
răspuns care nu mai vine. Scrie deciziile într-un tabel, cu cine și când, nu într-o setare: o setare se suprascrie,
o decizie rămâne ca dovadă peste luni.

`Lecție:` Orice listă de decizii mai lungă de vreo zece rânduri are nevoie de o acțiune în masă, altfel nu primește
niciun răspuns. Confirmarea se face cu al doilea clic pe același buton, nu cu `confirm()`: fereastra browserului
întrerupe omul, arată ca o eroare și, în automatizări, blochează pagina de tot.

`Lecție:` Un simulator care răspunde instantaneu schimbă discuția cu clientul mai mult decât orice raport. Datele
pentru el încap în pagină dacă trimiți doar ce intră în formulă, pe liste paralele, nu obiecte cu chei repetate de
mii de ori: pentru 3.954 de produse înseamnă trei șiruri de numere. Riscul e că formula ajunge scrisă de două ori,
în PHP și în JavaScript; se ține în frâu dacă la salvare serverul recalculează și pagina arată cifra lui.

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

**Etapa 1, gata.** Tabele proprii, preluarea catalogului cu amprentă pe produs, legarea și raportul CSV. Nimic nu
atinge WooCommerce; de fapt WooCommerce nici nu e instalat pe mediul local, pentru că SKU-urile, prețurile și
stocurile se citesc direct din `postmeta`. E de zeci de ori mai rapid pe 6.800 de produse și scoate o dependență
din calea unui raport care trebuie să poată rula oriunde.

Cifrele de pe date reale: 20.755 de produse preluate, **3.980 legate** (3.954 pe cod exact, 26 pe cod de producător),
**zero ambiguități** pe cod, 2.743 orfane și 16.795 produse Conex care nu sunt pe site. Se potrivesc cu analiza din
octombrie, ajustate cu cele 247 de produse pe care furnizorul le-a retras între timp.

Raportul: 3.724 de prețuri s-ar schimba, 3.485 de stocuri, 394 de produse ar trece pe epuizat, 345 s-ar reactiva,
65 se vând sub cost. Printre ele, SKU 25540, dat ca blister cu 6 siguranțe fuzibile la 4 lei cu un cost de 114,50
lei. **L-am raportat ca greșeală de preț, și n-a fost.** A doua zi a ieșit altceva: potrivirea era greșită. Vezi mai
jos.

**Etapa 2: scrierea în WooCommerce.** WooCommerce instalat local, exact versiunea de pe live (11.2.1), ca să nu
testez pe alt cod decât cel din producție. Două lucruri verificate înainte de prima linie de cod, amândouă schimbă
calculul: taxele sunt complet oprite în WooCommerce (`calc_taxes = no`, zero cote definite), deci prețul introdus e
prețul final și formula care include TVA-ul în preț e corectă; și toate cele 3.980 de produse legate au gestiunea
stocului pornită, deci cantitatea scrisă contează (cele 419 fără gestiune sunt produsele introduse manual de client).

Verificat tot în sursa WooCommerce, nu din memorie: `set_regular_price()` plus `save()` actualizează singur și
`_price`, în `WC_Product_Data_Store_CPT::update()`. Nu trebuie pus cu mâna, dar comentariul a rămas în cod, pentru
că e genul de lucru care se schimbă între versiuni.

**Refactorizare care s-a plătit imediat.** Raportul și aplicarea cheamă acum aceeași funcție, `ccs_plan()`. Înainte
raportul își calcula singur ce ar face. Dacă aplicarea ar fi avut al doilea calcul, raportul pe care clientul aprobă
ar fi putut să nu fie ce se scrie pe site. Acum nu se poate întâmpla, iar fiecare rând de plan cară și lista de
blocaje, adică motivele pentru care refuzăm să atingem un produs.

**Ce a ieșit la lumină.** Prima frână pusă, cea de scădere de preț, a oprit 19 produse. M-am uitat la ele și n-au
fost deloc ce credeam: codul se potrivea, dar produsul era altul. SKU `25918` e aspirator Kärcher pe site și ulei
Liqui Moly la Conex. SKU `00728` e cameră auto Full HD pe site și set de clipsuri de plastic la Conex. Iar `25540`,
pe care îl raportasem ca greșeală de preț, e siguranțe fuzibile pe site și extractor de articulație homocinetică la
Conex. Nu era nicio greșeală de preț: era o legătură greșită.

Măsurat pe toate cele 3.980 de legături, cu suprapunerea cuvintelor din nume: **26 erau false**. Separarea e curată,
fără zonă gri: peste 67% suprapunere sunt potriviri bune (nume trunchiate, „VW” în loc de „VOLKSWAGEN”, alt interval
de ani), sub 33% sunt alt produs, și între 33% și 67% nu cade absolut nimic. Pragul stă la 50%.

Partea care contează: **toate cele 26 de potriviri false veneau din strategia pe cod de producător, și niciuna bună
nu venea de acolo.** 26 din 26, greșite. Potrivirea pe `codEAN` e corectă pe toate cele 3.954. Strategia pe cod de
producător rămâne în cod, pentru că la Piese Total va fi necesară, dar la Cez Auto nu produce decât zgomot, iar acum
verificarea de nume o pune singură în carantină. Legăturile marcate `C` nu intră în plan și ies în raport separat.

Cifrele corectate, pe 3.954 de legături de încredere: 3.696 de prețuri de schimbat, 3.460 de stocuri, 392 de trecut
epuizat, 338 de reactivat, **60 vândute sub cost**.

**Pentru discuția de preț cu ownerul.** 3.178 de prețuri ar urca, în medie cu 18,35 lei; 521 ar coborî, în medie cu
13,24 lei. Marja brută totală pe bucată crește de la 115.558 la 166.970 de lei, adică **+44,5%**. Unde stă magazinul
acum, la produsele care s-ar schimba: 60 sub cost, 660 cu adaos între 0 și 20%, 2.238 între 20 și 35%, 627 între 35
și 60%, 88 între 60 și 100%, 26 peste 100%. Cele 521 de scăderi vin tocmai din ultimele două grupe: formula de 35%
plus 4 lei marjă minimă taie adaosul acolo unde e acum foarte gras, mai ales pe mărunțișuri. E o decizie de politică
de preț, nu o eroare, și ownerul trebuie să o vadă așa.

**Ce s-a probat pe bani adevărați.** Aplicare pe 40 de produse: prețul, stocul și starea stocului scrise prin CRUD,
cu `_regular_price`, `_price` și `wc_product_meta_lookup` toate de acord. Apoi `wp ccs revino 3`: 78 de valori
readuse exact, inclusiv în tabelul de căutare, iar a doua rulare a comenzii n-a mai făcut nimic. Prima aplicare nu
mai e un pas fără retur, și asta schimbă complet discuția cu clientul.

`Lecție:` Potrivirea pe cod nu e de ajuns, nici când codurile par să se potrivească perfect. Verifică și numele, cu
suprapunerea cuvintelor raportată la numele mai scurt (nu Jaccard: numele furnizorului e adesea mai lung și ar fi
penalizat nedrept). La Cez Auto, 26 din 3.980 de potriviri arătau spre cu totul alt produs, iar fără verificarea de
nume prețul și stocul s-ar fi scris greșit pe ele. Simptomul: un magazin care are produse de la mai mulți furnizori
cu coduri scurte din aceeași plajă numerică. Corolarul, și mai util: numără câte potriviri bune și câte greșite dă
fiecare strategie separat. O strategie care dă 26 din 26 greșite nu e o strategie slabă, e zgomot, și verificarea de
nume o descoperă singură.

`Lecție:` Când o frână de siguranță se declanșează, uită-te la ce a prins înainte să-i urci pragul. Frâna de scădere
de preț a fost pusă contra costurilor greșite de la furnizor; ce a prins de fapt a fost o problemă de potrivire, pe
care nu o căutam. Un prag care oprește lucruri pe care nu știi să le explici e un prag care își face treaba.

`Lecție:` Scrie în WooCommerce numai prin CRUD (`wc_get_product`, `set_*`, `save()`), niciodată prin
`update_post_meta`. Altfel prețul se schimbă în admin, dar `wc_product_meta_lookup` rămâne vechi și produsul se
filtrează, se sortează și se afișează în magazin cu prețul de ieri. Simptomul: prețul e corect pe pagina produsului
și greșit în listări, sau filtrul de preț nu găsește produsul. Verifică în sursa versiunii instalate ce actualizează
singur `save()`: în WooCommerce 11.2.1, `_price` se scrie automat când se schimbă `regular_price`.

`Lecție:` Jurnalizează fiecare valoare pe care o scrii, cu valoarea veche, într-un tabel propriu. Costă un `INSERT`
pe modificare și cumpără o comandă de revenire care funcționează. Nu e doar o plasă de siguranță tehnică: schimbă
conversația cu clientul, pentru că prima aplicare pe un magazin cu mii de produse nu mai e un pas fără retur.

`Lecție:` Într-o sincronizare care rulează des, ține o copie locală a catalogului furnizorului cu o amprentă pe
produs și compară amprentele, nu valorile. Prima preluare a durat 109 secunde pentru 20.755 de produse, a doua 3,26,
pentru că nimic nu se schimbase. Fără amprentă, fiecare rulare la 30 de minute ar fi rescris tot catalogul degeaba.
Amprenta se calculează doar peste câmpurile care, dacă se schimbă, cer o acțiune: dacă furnizorul modifică ceva ce
nu folosești, produsul nu trebuie atins.

`Lecție:` Un raport „ce aș face”, scos înainte de prima aplicare, găsește greșeli pe care nu le căuta nimeni. La
Cez Auto a scos 60 de produse vândute sub costul de achiziție, cel mai rău cu 394 de lei pierdere la bucată: un set
de huse de scaun listat la 738 de lei, cu un cost de 1.132 de lei cu TVA. Simptomul după care merită rulat: orice
magazin care n-a mai comparat prețurile de vânzare cu costurile de achiziție de mai mult de câteva luni.

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
