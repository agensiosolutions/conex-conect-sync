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

**Panoul, trecut prin unslop.** Iulian a cerut întâi scanarea skillului cu SkillSpector, conform regulii
`skill-nou-verificat`. 6 HIGH și 5 MEDIUM, iar toate cele HIGH sunt false pozitive: două sunt linia de instalare din
README (`cp -r unslop/skills/unslop ~/.gemini/config/skills/`, adică scriere **în** folderul de skilluri), iar patru
sunt teste care dovedesc că serverul local **refuză** să servească un fișier din afara rădăcinii, inclusiv prin
symlink. Scanerul a văzut cuvântul `secret.txt`. Am verificat și ce nu vede un pattern matcher: singurele două
`fetch` rulează în contextul paginii analizate, ca să-i încorporeze stilurile în captură, iar `ffmpeg` e chemat cu
`execFileSync` și listă de argumente. Nimic nu pleacă în afară.

Direcția, scrisă înainte de orice valoare, și păstrată în capul fișierului CSS: produsul e panoul prin care patronul
unui magazin de piese hotărăște ce face sincronizarea; caracterul e „exact, liniștit, de atelier”; lumea e fișa de
lucru dintr-un service, cu cifre citite în grabă între două comenzi; momentele sunt perechea „acum → nou” și
contorul de progres; semnătura e săgeata de schimbare.

Ce a găsit auditul în panoul de ieri: valori în afara grilei de 4 aproape peste tot (7, 9, 11, 14, 18, 22, 26),
etichete cu majuscule și tracking larg pe cardurile de potrivire, unsprezece dimensiuni de text, cifre tabulare doar
pe unele coloane, stiluri inline în PHP, zero tranziții, și o coloană „Ce facem” cu 120 de butoane pe pagina de
produse sub cost.

Cea mai utilă schimbare n-a fost însă nici una de curățenie. Înainte, prețul se citea pe patru coloane de cifre, iar
omul trebuia să le adune singur. Acum e o singură celulă, `738,00 → 1.529,00 +107%`, folosită peste tot unde o
valoare se schimbă, inclusiv în simulatorul care se recalculează în browser. Dacă vezi săgeata, acolo se schimbă
ceva. A doua: cele două butoane de pe fiecare rând au devenit un comutator care rămâne apăsat pe varianta aleasă,
deci rândul își spune singur starea, fără o propoziție care apărea dedesubt și muta layoutul.

Două principii din skill nu le-am aplicat ca atare. „Un singur element luminos pe ecran” și scena generată din hero
sunt scrise pentru pagini de prezentare; un panou unde omul ia șaizeci de decizii n-are hero și n-are nevoie de
imagine generată, iar skillul o spune el însuși despre dashboarduri. Iar „toate rândurile dintr-o listă au aceeași
înălțime” l-am lăsat deoparte acolo unde numele produselor au două rânduri: ca să respect regula ar fi trebuit să
trunchiez tocmai textul după care patronul decide.

**Patru corecturi, după ce Iulian s-a uitat.** „Nu văd mari schimbări, dar e mai bine” e genul de răspuns care
spune că ai făcut curățenie, nu design. Cele patru observații ale lui au fost toate exacte: iconițele din navigație
prea mici și centrate pe verticală, deci căzute între titlu și explicație; butonul de pe tabul Stocul lipit de lista
de deasupra; formula de preț lipită de controale; și, cea mai importantă, „totul e prea sec și gri”.

Ultima a dus la singura regulă de culoare din panou: **verde înseamnă că pluginul schimbă produsul, chihlimbar că îl
lasă cum e.** Aceeași regulă în toate cele patru perechi de butoane, la hover și după alegere. Un tabel parcurs pe
jumătate se citește acum dintr-o privire, fără să recitești etichetele.

Introducând-o, am creat imediat un conflict pe care nu-l căutam: pastila „0% cuvinte la fel” era tot galbenă, dar
acolo galbenul însemna „uită-te aici”, nu „rămâne cum e”. Două înțelesuri pentru o culoare pe același ecran. Am
făcut-o neutră, cu text mono: e o măsurătoare, nu o decizie.

Pus pe live ca versiunea 0.3.0, din nou cu versiunea urcată ca stilul să nu vină din cache. Toate cele șase taburi
se randează fără erori, cu tonurile la locul lor. Modul raport e pornit, zero decizii luate, zero scrieri în
WooCommerce.

`Lecție:` Când introduci o regulă de culoare semantică într-o interfață, caută imediat unde mai folosești culoarea
aia pentru altceva. La Cez Auto, galbenul însemna „rămâne cum e” pe butoane și „uită-te aici” pe o pastilă, la două
centimetri distanță. O culoare cu două înțelesuri pe același ecran nu mai înseamnă nimic, iar regula se strică exact
în momentul în care crezi că ai aplicat-o. Separă: culoarea pentru decizii, neutrul pentru măsurători.

`Lecție:` O iconiță lângă două linii de text se aliniază cu prima linie, nu cu mijlocul blocului. Centrată pe
verticală pare căzută între titlu și explicație, iar la rânduri de înălțimi diferite coloana de iconițe nu mai e o
coloană. `align-items: flex-start` plus o iconiță cât înălțimea primei linii.

`Lecție:` Un audit de design găsește lucruri de curățat, dar îmbunătățirea mare vine din a întreba ce trebuie să
citească omul dintr-o privire. La Cez Auto, patru coloane de cifre corecte cereau din partea patronului o adunare în
minte, de șaizeci de ori. O singură celulă `acum → nou` cu procentul alături a rezolvat mai mult decât toate
corecturile de spațiere la un loc. Simptomul: un tabel unde două coloane vecine n-au sens decât comparate una cu
alta.

`Lecție:` O regulă de design scrisă pentru pagini de prezentare nu se aplică la un panou de lucru. „Un singur
element luminos”, „scena care umple hero-ul”, „toate rândurile la fel de înalte”: primele două n-au obiect într-un
ecran fără hero, iar a treia ar cere trunchierea exact a textului după care omul decide. Ia regulile verificabile
(grila de spațiere, o singură rampă, cifre tabulare, o singură curbă de animație) și cântărește-le pe celelalte
față de ce face ecranul.

**Două lucruri împrumutate dintr-un skill.** Iulian mi-a dat
`github.com/multica-ai/andrej-karpathy-skills`, patru principii de comportament într-un fișier de 2,5 KB, copiat de
trei ori în repo. Nu l-am adăugat ca `CLAUDE.md`: majoritatea e deja la noi, iar două dintre cele patru principii ar
fi stricat lucruri aici. „Fără tratare de erori pentru scenarii imposibile” ar fi tăiat retry-ul cu backoff, fereastra
de 503 și protecția la scădere de catalog, adică exact codul scris după ce am măsurat cum pică API-ul noaptea. Un
principiu care nu face diferența între speculație și realitate măsurată e periculos tocmai la un plugin care scrie
prețuri într-un magazin.

Am luat două lucruri. Primul, regula de schimbări chirurgicale: fiecare linie schimbată se leagă direct de ce s-a
cerut, codul mort găsit pe drum se semnalează, nu se șterge. Aplicând-o pe loc, am găsit `ccs_site_skuuri()`, rămasă
din rescrierea potrivirii de pe 19 noiembrie și nechemată de nicăieri. E în `TASKS.md`, nu ștearsă.

Al doilea, testele. Folderul `teste/` era gol de la început, la 3.849 de linii de PHP. Acum acoperă două funcții:
`ccs_pret()` și `ccs_nume_asemanare()`. Alese pentru că sunt singurele care hotărăsc ceva important fără să atingă
baza de date sau API-ul, deci se pot testa curat, iar o regresie în oricare schimbă magazinul fără ca nimeni să
observe. 28 de verificări, fără dependențe, fără WordPress, sub o secundă.

Important: după ce au trecut toate, le-am probat stricând intenționat funcțiile pe o copie. Cinci mutații, toate
prinse: marja minimă scoasă din formulă, rotunjire în jos, taxa verde ignorată, Jaccard în loc de raportare la
numele scurt, cuvintele scurte nefiltrate. Controlul nemodificat trece.

Testul a găsit și ceva ce nu căutam: PHP-ul livrat cu Local by Flywheel n-are `mbstring` fără `php.ini`-ul site-ului,
iar `ccs_nume_cuvinte()` cade fatal fără ea. Verificat pe toate cele trei PHP-uri care contează, inclusiv cel de pe
serverul live: toate o au. Am pus doar un mesaj clar în locul erorii fatale, nu o alternativă fără `mbstring`: ar fi
fost exact complexitatea speculativă pe care skillul o critică pe bună dreptate.

`Lecție:` Un test care n-a fost probat stricând intenționat codul pe care îl acoperă nu valorează nimic. După ce
trece, modifică funcția într-un mod în care s-ar putea strica pe bune (o constantă scoasă, o rotunjire schimbată, o
formulă înlocuită cu una plauzibilă) și verifică dacă testul cade. Dacă tot trece, testul verifică forma, nu
comportamentul. Simptomul: o suită verde care n-a prins niciodată nicio regresie.

`Lecție:` Când alegi ce să testezi într-un proiect fără teste, începe cu funcțiile pure care hotărăsc bani sau
identitate: formula de preț, potrivirea de produse, calculul de reduceri. Se testează fără bază de date și fără
rețea, în milisecunde, iar o regresie acolo nu dă eroare, ci schimbă liniștit ce vede clientul. Restul codului, cel
care vorbește cu platforma și cu API-uri, se verifică mai bine pe date reale decât cu date inventate.

`Lecție:` Un set de reguli generale de comportament pentru LLM-uri merită citit, dar nu adoptat întreg. Regulile de
tip „fără configurabilitate care n-a fost cerută” și „fără tratare de erori pentru scenarii imposibile” nu fac
diferența între complexitate speculativă și cod scris după o măsurătoare. Într-un proiect care integrează un API
instabil, a doua categorie e tocmai ce ține produsul în picioare. Ia regulile care se pot verifica (fiecare linie
schimbată se leagă de cerere, codul mort se semnalează) și lasă-le pe cele care cer judecată despre ce e „necesar”.

**Curățat de notificări, înainte de a-l arăta ownerului.** Iulian a deschis panoul și deasupra lui stătea o alertă
roșie: „Your License Is Inactive”, de la Elementor, plus anunțul de actualizare a WordPress-ului. Pe o pagină de
setări ar fi fost în regulă. Aici nu: pagina asta o deschide clientul ca să hotărască prețurile magazinului lui, iar
o alertă roșie deasupra îl face să creadă că sincronizarea are o problemă.

Măsurat pe live: site-ul are **38 de notificări agățate** de alte pluginuri. Toate se scot pe ecranul nostru cu
`remove_all_actions()` pe cele patru hook-uri de notificări, chemat din `in_admin_header`, care se execută în
`admin-header.php` înainte de locul unde se tipăresc. Verificat cu notificări de probă: pe ecranul de produse rămân
toate 41, pe ecranul nostru rămân zero.

Am lăsat un filtru, `ccs_ascunde_notificarile`, pentru cine ar vrea totuși să le vadă.

`Lecție:` Pe un ecran de admin pe care îl deschide clientul, nu dezvoltatorul, scoate notificările celorlalte
pluginuri. Se face cu `remove_all_actions()` pe `admin_notices`, `all_admin_notices`, `network_admin_notices` și
`user_admin_notices`, chemat din `in_admin_header`, și numai pe ecranul tău, verificat cu `get_current_screen()`.
Motivul nu e estetic: o alertă roșie despre licența altui plugin, deasupra paginii tale, îl face pe client să
creadă că produsul tău e stricat. Simptomul: clientul întreabă „ce s-a stricat?” despre ceva ce n-are legătură cu
ce i-ai livrat.

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

**Cele 26 de produse pe cod comun, oprite înainte să stric ceva.** Iulian mi-a cerut să le înlocuiesc cu produsele
noi. M-am uitat întâi și premisa nu ținea: produsul de la Conex de pe acel cod nu e o versiune nouă a produsului de
pe site, e cu totul altceva. La codul `10000`, site-ul are o pompă de aer cu manometru, iar Conex un set de 50 de
clipsuri de plastic. Înlocuirea ar fi însemnat că magazinul nu mai vinde pompa la adresa aia.

Am căutat apoi dacă produsele de pe site există la Conex sub codul lor corect. Căutarea largă a dat 11 potriviri,
dar niciuna reală: 6 chei contra 21, 2 leviere contra 5, siguranță de 60A contra 40A, aspirator de 20 de litri
contra 27. Variante apropiate, nu același produs. Cu o măsură strictă, nume aproape identic și același brand, a
rămas **unul singur din 26**: aspiratorul de geamuri Kärcher, SKU `20465` pe site, `1.633-453.0` la Conex.

Concluzia e că cele 25 nu sunt „de înlocuit”, sunt clasificate greșit: produse pe care Conex nu le are, adică exact
situația celorlalte 2.743 de orfane. Iulian a oprit lucrul ca să întrebe ownerul, ceea ce e răspunsul corect.

**Etapa 3, joburile programate.** Action Scheduler, nu `wp_cron`: pe un magazin fără trafic noaptea, `wp_cron` pur
și simplu nu rulează, pentru că depinde de vizitatori. Jobul de catalog preia catalogul, reface potrivirea doar dacă
furnizorul a adăugat produse, golește cache-ul panoului și aplică în WooCommerce dacă modul raport e oprit. Cu modul
raport pornit, nu atinge magazinul deloc: doar ține cifrele din panou la zi.

Un lacăt pe tranzient oprește rulările suprapuse, probat: a doua chemare a jobului s-a sărit și s-a înregistrat o
singură rulare. Intervalul se schimbă din setări și jobul se reprogramează singur, probat de la 30 la 15 minute.

Joburile sunt **oprite implicit**. Un plugin care începe să scrie singur în magazin imediat ce a fost activat e un
plugin în care nu mai ai încredere a doua oară.

Am probat și regula de produse absente din feed, care era scrisă din 19 noiembrie dar nu fusese verificată
niciodată: la o rundă lipsă nu se întâmplă nimic, la două produsul trece pe stoc 0 și „epuizat”, și nu se șterge
niciodată. Pe un produs real, cu `lipsa_runde` dus de la 0 la 3.

Jobul de noapte pentru imagini nu l-am scris: mecanismul de programare și calculul ferestrei există, dar un job
programat care nu face nimic e cod mort. Vine la etapa 4, odată cu imaginile.

**Maparea categoriilor, scoasă din date.** Cele două taxonomii nu se pot traduce mecanic: Conex împarte catalogul
pe sisteme ale mașinii („Elemente de caroserie / Elemente frontale / Bara fata”), magazinul pe logica vânzării
(„Accesorii auto / Covorașe auto”). Nimeni n-a scris vreodată corespondența. Dar magazinul are 3.954 de produse
legate, fiecare cu o cale la furnizor și o categorie pusă de om: sunt răspunsuri date deja, fără ca cineva să-și dea
seama.

Prima încercare a dat rezultate proaste, și motivul merită reținut: WooCommerce pune produsul și în categoria copil
și în părinte, iar dacă înveți din părinte toate căile ajung în „Accesorii auto” și maparea nu mai spune nimic.
Luând categoria cea mai adâncă, aceleași date au dat 100%, 99%, 96% pe căile mari.

A doua descoperire a venit din cifre: calea „Covorase auto” avea doar 63% încredere. M-am uitat de ce, și acolo era
regula covorașelor din `TASKS.md`, pe care n-o înțelesesem: 744 de seturi de covorașe merg în „Covorașe auto”, dar
431 de covoare de portbagaj merg în „Accesorii interior”. Aceeași cale, două categorii, despărțite de un cuvânt din
nume. Am scris căutarea de cuvânt separator generic, iar ea a găsit singură „PORTBAGAJ”.

Căutarea a găsit însă și „HICO” și „JBM”, care sunt **branduri**. Despart perfect grupurile, pentru că cineva a pus
cândva toate produsele unei mărci într-o categorie, dar o regulă pe brand spune ce s-a întâmplat în trecut, nu ce
fel de produs e, și ar trimite greșit orice produs nou al mărcii. Cuvintele care apar ca brand în catalog se sar
acum.

**Probat cum trebuie.** Am scos 30% din legături, am reînvățat fără ele și am testat pe ele: **95,5% corect**, 3
produse din 1.157 fără răspuns. Greșelile sunt toate în zonele unde magazinul însuși e inconsecvent: „Scule auto”
contra „Diverse”, „Cric auto” contra „Diverse”.

Acoperirea: din cele 16.800 de produse Conex care nu sunt pe site, regula știe unde merg 6.598. Restul de 10.202
sunt pe 96 de căi, aproape toate piese OE: bare față, hayoane, oglinzi, plăcuțe și discuri de frână, amortizoare,
planetare. Adică exact lucrurile despre care ownerul n-a hotărât încă dacă intră pe ele. Site-ul are o singură
categorie pentru tot, „Piese auto”, cu 112 produse. N-am construit tabul de mapare manuală tocmai de asta: n-are
rost să-i punem în față 96 de căi care poate nu-l interesează.

Pus pe live ca 0.4.0, cu backup înainte. Pe datele de acolo, aceleași 64 de reguli și aceleași 7 reguli pe cuvânt
ca pe local, ceea ce e un semn bun: regulile nu depind de zgomotul unei copii de bază mai vechi cu cinci zile.
Joburile au rămas oprite, modul raport pornit, cele 60 de decizii intacte.

**Categoriile duplicate, curățate.** Pe listă erau cinci. Una n-ar fi trebuit să fie acolo: „Compresor auto” e
goală, dar e în **meniul principal** al site-ului, iar Conex are 21 de compresoare, dintre care 14 încă neimportate.
Ownerul a pus-o acolo intenționat, ca să o umple. Am păstrat-o și am spus de ce.

Șterse: „Siguranța auto” (după ce stingătorul din ea a trecut în „Siguranță rutieră”), „Siguranta rutiera” și
„CONSUMABILE SERVICE”, toate goale după mutare. Cele trei „Diverse” cu produse au rămas: fiecare e sub alt părinte,
deci numele care se repetă e corect acolo.

Backup înainte, 13 MB, verificat. După ștergere: 62 de categorii au devenit 59, toate cele 79 de intrări din cele
cinci meniuri sunt întregi, iar `wp ccs categorii` reînvățat n-a avut nicio regulă care să arate spre o categorie
ștearsă.

Cele trei adrese dădeau 404, așa că le-am pus redirect 301 prin Rank Math. Două capcane acolo: scrierea directă în
tabelul lui a fost oprită, pe bună dreptate, și am folosit `RankMath\Redirections\DB::add()`, API-ul lui propriu;
iar `url_to` vrea adresă completă, nu cale relativă, altfel 301-ul duce spre `http://produse/...`, cu primul segment
luat drept domeniu. Redirectul n-a mers nici după ce l-am pus, până la purjarea LiteSpeed: cache-ul servea 404-ul
memorat.

`Lecție:` Înainte să ștergi o categorie goală dintr-un magazin, uită-te dacă e în meniu și dacă furnizorul are
produse pentru ea. Goală nu înseamnă nefolosită: poate fi un raft pe care omul l-a pregătit și nu l-a umplut încă.
La Cez Auto, „Compresor auto” era goală, în meniul principal, iar furnizorul avea 21 de compresoare.

`Lecție:` Când scrii în datele altui plugin, folosește API-ul lui, nu `INSERT` direct în tabel. Formatul serializat,
tabelele de cache și validările nu se văd din schema tabelului. La Rank Math, `DB::add()` acceptă `sources` ca
tablou și îl serializează singur, iar `url_to` vrea adresă completă: cu o cale relativă, redirectul duce spre
`http://primul-segment/`, cu primul segment luat drept domeniu.

`Lecție:` După orice schimbare de taxonomie sau de redirectări pe un site cu LiteSpeed, purjează cache-ul înainte
să tragi concluzia că nu merge. Un 404 memorat se servește în continuare, iar redirectul proaspăt pare că n-a prins.

`Lecție:` Înainte să ceri unui client să mapeze ceva, numără ce răspunsuri a dat deja fără să-și dea seama. Un
magazin cu produse deja categorisite conține maparea completă între taxonomia furnizorului și a lui; trebuie doar
numărată. La Cez Auto, 3.954 de produse au dat 64 de reguli cu 95,5% acuratețe, iar clientul n-a fost întrebat
nimic. Simptomul că ai în față cazul ăsta: urmează să trimiți un tabel de completat cu sute de rânduri.

`Lecție:` Când înveți reguli din date, verifică dacă nu cumva înveți istoria în loc de regulă. Un cuvânt care
desparte perfect două grupuri poate fi un nume de brand, iar asta înseamnă „cineva a pus cândva toate produsele
mărcii astea într-o categorie”, nu „produsele astea sunt de alt fel”. Se recunoaște ușor: cuvântul e un brand, un
furnizor sau o dată, nu o însușire a produsului. Exclude-le explicit.

`Lecție:` Când înveți dintr-o taxonomie cu părinți și copii, învață din categoria cea mai adâncă. WooCommerce pune
produsul în toate categoriile de pe lanț, iar dacă nu alegi, ajungi să înveți din părinte, unde intră tot. Simptomul:
o mapare care pare că merge dar trimite aproape totul în una sau două categorii largi.

`Lecție:` Când clientul îți cere să înlocuiești niște date cu „cele noi”, verifică întâi că sunt într-adevăr aceleași
lucruri. La Cez Auto, codul comun era o coincidență între EAN-ul unui produs și codul de producător al altuia, iar
înlocuirea ar fi pus clipsuri de plastic la adresa unei pompe de aer. Simptomul: o potrivire făcută pe un câmp care
nu e identificator unic în ambele sisteme.

`Lecție:` Pentru joburi recurente într-un magazin WooCommerce, folosește Action Scheduler, nu `wp_cron`. `wp_cron`
se declanșează la vizite, deci pe un magazin fără trafic noaptea nu rulează tocmai când ai nevoie. Action Scheduler
ține coada în baza de date, reia ce a eșuat și are ecran propriu de urmărit. Pune și un lacăt contra rulărilor
suprapuse: două rulări simultane ale aceluiași job scriu una peste alta.

`Lecție:` Un plugin care sincronizează date nu pornește singur la activare. Lasă joburile oprite implicit și cere o
apăsare. Prima impresie a clientului despre un plugin care i-a schimbat trei mii de prețuri fără să-l întrebe nu se
mai repară, oricât de corecte ar fi fost prețurile.

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
