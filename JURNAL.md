# Jurnal conex-conect-sync

## 27 noiembrie 2026

**Un audit cerut înainte de 1.0, și trei apărări construite din el.** Iulian a întrebat dacă pluginul e sigur,
ce precauții mai putem lua și ce situații neprevăzute pot apărea. Am citit codul în loc să răspund din memorie,
iar ce nu se putea deduce din citit am probat pe local.

Partea de securitate e în regulă, și se poate spune cu dovezi: toate cele unsprezece endpointuri AJAX trec prin
aceeași poartă cu nonce și capabilitate, singura acțiune pe GET e verificată, datele utilizatorului ajung în SQL
numai prin `prepare()` cu `esc_like()`, iar cele treizeci și șase de interogări fără `prepare` conțin doar nume
de tabele din prefixul WordPress. Extensia pozelor trece printr-o listă albă de cinci tipuri, deci un URL care
se termină în `.php` ajunge pe disc ca `.jpg`. Mesajele de eroare sunt texte fixe, iar corpul răspunsului de la
furnizor stă doar în datele erorii, filtrat de token.

Interesant e ce a ieșit din proba pe local, nu din citit.

**O categorie ștearsă de pe site nu dă eroare, varsă produsele în categoria implicită.** Regulile de mapare țin
`term_id` și nimeni nu verifica dacă termenul mai există. Am probat: WooCommerce înlocuiește tăcut categoria
moartă cu cea implicită a magazinului. Pe cezauto.ro implicita nu e „Necategorizat", e **Accesorii auto**, o
categorie reală din meniu, verificat și pe live. Importul ar fi raportat succes.

**Un atribut al furnizorului cu același nume intră peste atributul magazinului.** Harta noastră se ferea de
ciocniri între numele furnizorului, dar nu se uita la atributele pe care și le-a făcut magazinul. Am probat:
am încercat să creez „Material" pe local, WooCommerce a refuzat fiindcă există deja, iar funcția noastră a
întors senin `pa_material`. Pe datele de probă sunt cinci astfel de nume, dintre care „Material", „Culoare" și
„Tip montare" sunt chiar ale magazinului.

**Capcana de TVA, cea mai serioasă și cea mai tăcută.** Pluginul scrie prețuri cu TVA inclus și nu se uita
niciodată la setările de taxe ale WooCommerce. Azi e consistent, fiindcă pe live taxele sunt oprite de tot. Dar
dacă cineva le pornește vreodată fără să bifeze „prețurile sunt introduse cu TVA", WooCommerce mai adaugă o
dată 21% la casă, pe tot magazinul, iar pluginul n-ar afla: el a scris exact ce trebuia.

Au ieșit și două lucruri de scară mai mică: lacătul era luat doar de jobul de catalog, deci o comandă dată de om
sau importul automat de la 19:40 treceau pe lângă el, iar politica de preț acceptă orice număr fără margini.

Din toate astea am construit trei: verificarea termenului înainte de folosire, un fișier de verificări de
configurare arătat în panou și trimis pe email, și lacătul mutat în toate cele patru operații care schimbă
produse. Lacătul e reintrant, fiindcă jobul îl ține pentru toată rularea și cheamă înăuntru `ccs_aplica()`.

`Lecție:` Întreabă baza de date ce face framework-ul, nu documentația. „WooCommerce ignoră o categorie
inexistentă" părea evident din citit. Probat, se dovedește că o înlocuiește cu categoria implicită a
magazinului, care rareori e „Necategorizat" pe un magazin adevărat. Simptomul: o operație în masă care
raportează succes și produse care apar unde nu le-a pus nimeni.

`Lecție:` Un plugin care calculează prețuri își leagă soarta de setările de taxe ale platformei, chiar dacă nu
le citește niciodată. Tocmai pentru că nu le citește. Caută presupunerile tăcute întrebându-te ce setare din
afara pluginului, schimbată de altcineva, i-ar face rezultatul greșit fără să dea eroare.

`Lecție:` Un lacăt pus pe job, nu pe operație, apără doar calea pe care te-ai uitat. Pune-l pe lucrul care nu
suportă concurență, iar atunci fă-l reintrant: altfel prima cale care îl ia își refuză singură pașii următori.

`Lecție:` Când o verificare și creatorul calculează același lucru în două locuri, verificarea minte mai devreme
sau mai târziu. Am extras `ccs_atribut_slug()` din funcția care creează atributul, ca amândouă să întrebe
aceeași funcție. O verificare nu trebuie nici să schimbe starea pe care o verifică: de-aia are un parametru care
îi spune să nu scrie în hartă.

**Panoul, corectat bucată cu bucată după ce Iulian s-a uitat pe live.** Nu o listă de dorințe, ci lucruri
văzute pe ecran: butoanele din „Aplică aceeași alegere pentru toate” arătau inactive deși erau active, rândurile
din tabele aveau un hover rămas de la altă stilizare, iar Catalogul furnizorului afișa zero fără să spună de ce.
Zeroul avea un motiv adevărat: tabela de poze e goală, iar toate cele 16.778 de produse nelegate sunt oprite de
condiția „fără poză”. Jobul de poze rulează la 19:10, deci catalogul se umple diseară. Mesajul spune acum exact
asta, nu „nu sunt produse”.

**Explicația a trecut din bandă în tooltip, apoi în pastilă.** Întâi am pus o bandă sus care explica de ce e
oprită scrierea pe site. Iulian a tăiat-o pe captură: voia o iconiță de info, nu un paragraf. Am pus iconița
lângă pastilă, și iar n-a fost bine: trebuia *în* pastilă, ca hover-ul să prindă pe text. Varianta bună e
`ccs_ui_pastila_info()`, o pastilă care își poartă singură explicația.

**Iconițele au trecut de la Dashicons la Lucide.** Dashicons e setul livrat cu WordPress, din 2012, desenat la
20px, și se vedea: eticheta de preț era o formă plină fără contur, iar categoria un dosar generic. Lucide e
desenat pe o grilă de 24 cu contur de 2px și rămâne citibil la 14px, mărimea la care îl folosim cel mai des. Cele
25 de iconițe stau lipite ca SVG în `includes/iconite.php`, generat de `tools/adu-iconite.py`, nu luate de pe un
CDN: o pagină de admin care așteaptă un server străin ca să își deseneze iconițele se strică atunci când serverul
ăla are o zi proastă. Cheile au rămas numele vechi, de la Dashicons, fiindcă ele descriu rolul din panou, nu
desenul: alt pachet se schimbă rescriind numai fișierul generat.

Tot atunci a apărut și butonul „Arată pașii” din antet. Iulian apăsase „Nu îmi mai arăta pașii” și n-avea cum
să-i aducă înapoi. Butonul apare doar după ce ghidul a fost ascuns și doar cât mai e ceva de pus la punct, și
duce pe Panou, fiindcă numai acolo se randează cardul.

`Lecție:` Inventariază un simbol în tot proiectul, nu în fișierul la care lucrezi. Am înlocuit paisprezece apeluri
de iconiță în `admin-ui.php` și am declarat treaba gata; iconițele de navigație stăteau în `admin.php` și s-au
randat ca nimic. Simptomul: după o înlocuire „completă”, un element lipsește exact într-un singur loc.

`Lecție:` Versionează resursele după data fișierului, nu după versiunea produsului. Într-o sesiune de zeci de
schimbări de CSS versiunea pluginului nu se mișcă, deci browserul servește fișierul vechi și ajungi să depanezi o
schimbare care e deja pe disc. Simptomul: „am pus pe live, dar arată la fel”.

`Lecție:` Când clientul respinge o explicație de două ori, a doua oară nu mai schimba textul, mută-l. O explicație
trebuie să stea pe lucrul pe care îl explică și să se deschidă la hover pe cuvintele citite, nu într-o bandă
deasupra a tot și nu pe o iconiță de 14px de lângă.

**Ghidul e în panou, nu într-un fișier, iar rularea automată e pornită pe live.** Iulian a întrebat dacă nu e
mai simplu un ghidaj în panou decât un document. Este, din două motive: un document trebuie deschis, și
îmbătrânește. Cardul „Ce mai e de pus la punct” citește starea reală, deci nu poate minți, și arată un singur pas
deschis, primul neterminat. Fișierul rămâne, dar nu mai e livrabilul.

**Joburile pornite pe live, și un bug găsit exact atunci.** Am dat comanda, a răspuns „Joburile sunt pornite”, și
nu s-a programat nimic. Programarea se făcea doar din hook-ul de schimbare a setării, iar dacă setarea e deja
pornită `update_option()` nu schimbă nimic, deci hook-ul nu se declanșează. Acum există o verificare pe
`admin_init` care repune joburile dacă setarea le cere și coada e goală.

`Lecție:` Nu lega o acțiune doar de hook-ul de schimbare a unei setări. `update_option()` nu declanșează nimic
când valoarea e aceeași, deci o stare care s-a pierdut din alt motiv nu se mai reface niciodată, iar interfața
raportează vesel că totul merge. Simptomul: butonul spune „pornit”, dar nu se întâmplă nimic. Pune și o
verificare care compară ce ar trebui să existe cu ce există.

`Lecție:` După ce repari o funcție WordPress care urmează limba adminului, caută-i rudele. Am reparat
`human_time_diff()` dimineața și am crezut că am terminat; `mysql2date()` și `wp_date()` cu nume de lună scriau în
continuare „26 November” în același panou. Caută tot ce formatează timp, numere sau liste, nu doar ce ai văzut.

**Tot ce lipsea din ofertă e construit, plus un redesign cerut de Iulian, iar 0.9.0 e pe live.** Zi lungă, deci
țin minte doar ce schimbă felul în care lucrăm data viitoare.

**Ce lipsea din ofertă.** Am recitit oferta întreagă și am verificat fiecare promisiune în cod, nu din memorie.
Patru lucruri vândute explicit nu existau deloc: datele tehnice de la furnizor, răsfoirea catalogului în panou,
emailul cu rezumat și alerte, și produsul care urcă singur când apare poza. Plus excepțiile de preț și ghidul.
Toate sunt făcute acum.

Despărțirea care mi-a plăcut cel mai mult: care atribute tehnice devin filtre în magazin. Un prag fix pe numărul
de valori respingea „Partea de montare”, care are 125 de valori dar pe 2.874 de produse, adică exact genul de
filtru pe care îl caută cumpărătorul. Raportul dintre valori distincte și produse alege corect: 21 de atribute
filtrabile din 381, iar identificatoarele ca „pt. cod original (OE)”, cu 1.531 de valori pentru 1.577 de produse,
cad singure.

**Redesignul.** Iulian a cerut capturi din pluginuri care fac același lucru, nu principii. Am adunat unsprezece,
de la ATUM, WP All Import, Smart Manager, Omni Supplier Sync, AliNext și Rank Math, și a ales limbajul lui Omni.
L-am măsurat, nu l-am aproximat: paletă scoasă din imagine, zone decupate și mărite. Regula care a ieșit și care
ține acum tot CSS-ul: **culoarea stă în plăcuța de iconiță, în pastile și în butonul principal, nicăieri
altundeva**. Cardul rămâne alb.

Asta explică retroactiv de ce cardurile pline de culoare făcute cu două zile înainte nu funcționau, și de ce
feedbackul „prea sec și gri” și „prea mult acum” au venit amândouă: problema nu era cantitatea de culoare, era
locul ei.

`Lecție:` Când clientul respinge aceeași zonă de două ori, în direcții opuse, oprește-te din reglat. Culoarea a
fost crescută, apoi scăzută, iar reproșul a supraviețuit amândurora. Semnul că problema e în altă parte, de
obicei în ierarhie sau în ordine, nu în intensitate.

`Lecție:` Un selector CSS pe element, într-o componentă care are mai multe elemente de același fel, e o capcană.
`.ccs-cifra span` lovea și plăcuța de iconiță, tot un `span`, cu specificitate mai mare decât clasa ei, și îi
anula `display: flex`. Iconița ateriza în colțul din stânga sus. Simptomul: un element arată ca și cum nu i s-ar
aplica regula proprie. Verifică `getComputedStyle` înainte să schimbi valori la ghici.

`Lecție:` Numele unui simbol de culoare trebuie să spună înțelesul, nu nuanța. `--c-galben` care ține un
portocaliu minte pe oricine îl citește, iar la următoarea schimbare cineva caută galben și nu găsește nimic.
Redenumit în `--c-atentie`. Atenție că numele de culoare ajung și în PHP, acolo unde se construiesc clase CSS din
șiruri: o redenumire numai în CSS rupe tăcut tot ce vine din PHP.

`Lecție:` Caută valorile scrise de mână înainte să declari o schimbare de paletă terminată. Două nuanțe ale
galbenului vechi erau în CSS ca `rgba(161, 98, 7, ...)`, într-o umbră și într-un chenar, și ar fi rămas galbene
lângă restul portocaliu. Le-am găsit căutând cifrele, nu uitându-mă la pagină.

`Lecție:` Funcțiile WordPress care întorc text urmează limba adminului, nu limba pluginului. Pe un site cu admin
în engleză și plugin în română, `human_time_diff()` scria „acum 23 hours”. Dacă textele tale sunt într-o singură
limbă, scrie-ți singur formatările de timp și de număr. Pentru română, regula pe care o uită toată lumea e „de”
de la 20 în sus: 19 minute, dar 20 de minute.

`Lecție:` O previzualizare care nu încarcă toate resursele te face orb exact la ce verifici. Previzualizarea mea
statică nu lega fontul Dashicons, deci plăcuțele apăreau goale și am pus-o pe seama previzualizării, timp de
două zile. Prima randare adevărată, o captură trimisă de Iulian, a arătat bugul în zece secunde. Dacă
previzualizezi o interfață, încarcă-i toate resursele, sau notează explicit ce lipsește din ea.

`Lecție:` Un `tr` cu `display: grid` face `colspan` să nu mai facă nimic. Am botezat un rând de grup `ccs-grup`,
nume folosit deja pentru altceva în același fișier, iar banda de grup s-a randat lată de 40px în loc de toată
lățimea tabelului. Înainte de a inventa un nume de clasă, caută-l.

**0.9.0 pe live**, cu backup, lint cu PHP-ul serverului și migrarea schemei la v8. Scrierea în WooCommerce e în
continuare oprită.

## 26 noiembrie 2026

**Corectarea celor 26 nu trebuie făcută, iar previzualizarea a dovedit-o.** Ownerul răspunsese pe WhatsApp că
produsele nu sunt introduse manual de echipa lui, iar Iulian a concluzionat că atunci se corectează cu datele de la
Conex. Concluzia părea logică și am scris corectarea. Previzualizarea, rulată înainte de orice scriere, a arătat
altceva.

Zece din cele 26 ar fi devenit clipsuri de tapițerie: pompa de aer, sprayul de deblocare, adezivul de oglinzi,
vopseaua spray, odorizantul. Toate, clipsuri. Un asemenea tipar nu e o coincidență, e un mecanism.

Mecanismul: **ROMIX folosește coduri de producător de cinci cifre**, 109 din cele 344 de produse ale lor, iar alea
nimeresc peste SKU-urile rămase pe site din feed-ul Mega Drive. Codul propriu al produsului Conex e cu totul altul:
SKU `10000` de pe site duce la produsul Conex `61497`, care doar are `10000` în câmpul de cod de producător. Nu e
„codul înseamnă acum alt produs”, sunt două sisteme de numerotare care se ciocnesc pe un câmp care nu e
identificator unic.

Cifrele, dacă s-ar fi aplicat: valoarea celor 26 de anunțuri ar fi scăzut de la 4.113 la 1.465 lei, 20 de prețuri ar
fi coborât, două produse ar fi ajuns cu stoc zero, iar aspiratorul de geamuri Kärcher ar fi devenit ulei Liqui Moly.

Ce e adevărat din ce a spus ownerul: produsele chiar nu sunt introduse manual. Au venit din feed-ul Mega Drive, sub
codurile lor. Dar de aici nu rezultă că produsul Conex care poartă acel număr în alt câmp e înlocuitorul lor.

**Un bug găsit pe drum, de protecția pe care o pusesem.** Protecția care caută produsul de pe site sub alt cod la
furnizor n-a sărit pentru aspiratorul Kärcher, deși cazul era exact ăla. Cauza: `get_the_title()` trece prin
`wptexturize`, care transformă liniuța în `&#8211;`, iar tokenizarea o rupea în cuvântul „8211”. Asemănarea scădea
de la 0,86 la 0,75, sub pragul de 0,80. Potrivirea principală citea titlul direct din baza de date, deci curat, iar
corecțiile prin filtru: două căi de cod, două rezultate, pe aceleași date. Decodarea entităților e acum în
`ccs_nume_cuvinte()`, deci peste tot, cu două teste în plus. Suita a ajuns la 42.

**Hotărârea, după ce a văzut cifrele:** cele 26 nu se mai vând deocamdată, iar focusul rămâne pe produsele Conex
plus cele câteva introduse manual de echipă. Am măsurat ce înseamnă fraza asta pentru tot magazinul, nu doar pentru
cele 26, pentru că tiparul codului spune de unde vine fiecare produs: 292 au SKU `CZ*` și sunt puse de mână de
echipă, 2.435 au cod numeric scurt și au venit din feed-ul Mega Drive, înghețat din noiembrie 2025. Dintre toate
orfanele, **2.127 sunt încă afișate ca disponibile**, deși magazinul nu le mai poate aduce.

Am scris retragerea ca operație separată, cu grupuri și cu probă implicită. Nu șterge niciodată nimic: produsul
rămâne pe site, cu pagina și adresa lui, și trece pe stoc epuizat. O pagină care a stat ani în Google aduce în
continuare vizitatori, iar „stoc epuizat” îi ține pe site, unde pot cumpăra altceva; o ștergere îi trimite în 404.

Probat pe local, pe cele 26: 19 trecute pe epuizat, 7 erau deja, metadatele și `wc_product_meta_lookup` de acord,
iar `wp ccs revino` a readus 38 de valori exact cum erau.

Pus pe live ca 0.5.0, cu backup înainte. Cele 26 sunt retrase: 19 trecute pe epuizat, 7 erau deja, 38 de valori
păstrate pentru revenire. Pagina produsului arată „Stoc epuizat” și rămâne în Google, cum trebuie.

**Tabul „Catalogul”.** Împărțit în două feluri de informație, pentru că răspund la întrebări diferite: mișcarea,
adică ce s-a schimbat la furnizor de la o rulare la alta, și restanța adunată până acum. Prima rămâne interesantă
pentru totdeauna, a doua se rezolvă o dată.

Mișcarea n-a cerut date noi: tabelul de catalog are deja `prima_vedere`, `ultima_vedere` și `lipsa_runde`. O
capcană, văzută imediat la prima rulare: prima preluare a scris tot catalogul deodată, deci toate produsele au
`prima_vedere` în aceeași zi, iar „produse noi în ultimele șapte zile” arăta 20.760. Pragul e sfârșitul primei
rulări reușite; după el, cifrele devin 5 noi și 35 ieșite, adică adevărul.

Jos, două coloane. „Ce dispare”, pe grupuri după tiparul codului, fiecare cu explicația de unde vine și cu buton de
retragere. „Ce vine nou”, ca pâlnie: gata de import, fără poză, fără categorie, fără stoc.

Două hotărâri de structură. Pentru „ce dispare” n-am construit tabel propriu: alea sunt produse WooCommerce reale,
iar „Vezi lista” duce în lista de produse din WordPress, filtrată pe grup printr-un `pre_get_posts`. Clientul
lucrează acolo cu filtrele și acțiunile pe care le știe. Pentru „ce vine nou” tabelul propriu e inevitabil, fiindcă
produsele alea nu există încă în WordPress.

Niciun buton de ștergere, nicăieri, nici pentru produse ieșite din catalog de un an. Singura acțiune e retragerea
de la vânzare.

**Tabul refăcut, după ce Iulian a spus că e sec.** Avea dreptate și motivul e instructiv: celelalte taburi pun o
întrebare și primesc un răspuns, deci gri plus o pastilă ajung. Tabul ăsta arată două cataloage de zeci de mii de
produse, iar acolo gri înseamnă „citește toate cifrele și adună-le singur”.

Ce s-a schimbat: trei carduri de acțiune în culoare plină, cu cifra mare și cu ce e de făcut scris pe ele; două
bare segmentate, una pentru produsele de pe site și una pentru catalogul Conex, fiecare cu legendă; mișcarea cu un
plus verde și un minus chihlimbar.

Plus cele două lucruri care lipseau și pe care Iulian le-a numit: **asocierea categoriilor**, ca listă de reguli cu
calea furnizorului, raionul de pe site, bara de încredere și cuvântul care împarte o cale în două; și **cum arată
un produs adus**, cinci carduri cu poza reală de la furnizor, numele, prețul calculat și etichetele de raion, stoc
și număr de poze.

Al doilea a fost cel mai ieftin de făcut și probabil cel mai valoros: adresele pozelor erau deja în tabel, iar un
`<img>` în admin arată mai bine decât orice descriere a ce s-ar importa.

Două corecturi după ce Iulian s-a uitat: cardurile de acțiune erau prea tari. Trei dreptunghiuri în culoare plină
unul lângă altul strigă mai tare decât conținutul lor. Acum sunt albe ca restul panoului, cu iconița colorată în
colț și cifra în culoarea stării, ceea ce păstrează citirea dintr-o privire fără să domine pagina. Iar grupurile
de produse retrase au primit și ele iconiță, cifră mare și o bară care arată cât s-a făcut deja: fără bară,
„2.435” și „1.826 încă de vânzare” sunt două numere care cer o scădere în minte.

**Maparea manuală, pornită de o întrebare.** Iulian a întrebat ce înseamnă „5.522 așteaptă o categorie”, iar
răspunsul a scos la iveală trei lucruri.

Întâi, eticheta era înșelătoare: 5.522 sunt produsele oprite **numai** de categorie, pentru că pâlnia numără fiecare
produs o singură dată, la primul lucru care îl oprește. În total 13.332 n-au categorie, dar restul sunt oprite
oricum de lipsa pozei sau a stocului.

Apoi, printre căile nemapate nu erau numai piese OE. „Uleiuri, lichide si chimice / Lubrifianti auto / Uleiuri
pentru motor” are 644 de produse, 528 cu stoc, iar magazinul **are deja raionul potrivit**: „Ulei motor”, cu
cincizeci de uleiuri Castrol puse de echipă. Regula n-a fost învățată dintr-un motiv de proiectare: învățarea se
uită doar la produsele legate de Conex, iar uleiurile echipei au cod propriu, deci sunt orfane. Omul vede
corespondența din prima; mașinăria n-avea de unde.

De aici a treia: lipsea un loc din care omul să mapeze o cale. Acum cele 98 de căi apar sortate după câte produse
ar debloca fiecare, cu un selector de raion alături. Probat pe uleiuri: o alegere a mutat **307 produse** din
„așteaptă” în „gata de adus”, iar regula a supraviețuit reînvățării.

**Două bug-uri găsite de testul ăla.** `ccs_decizii.referinta` era `VARCHAR(64)`, dar o cale de categorie ajunge la
105 caractere, deci decizia nu se scria. Mai rău: `ccs_decizie_scrie()` nu verifica rezultatul lui `$wpdb->insert()`
și raporta „ok” oricum, iar interfața ar fi spus „Salvat” peste o scriere căzută. Amândouă reparate.

`Lecție:` Verifică întotdeauna valoarea întoarsă de `$wpdb->insert()`. Fără asta, o coloană prea scurtă, o
constrângere sau un mod strict fac scrierea să cadă în tăcere, iar interfața spune „Salvat”. Simptomul: clientul
jură că a apăsat, iar în baza de date nu e nimic.

`Lecție:` Când dimensionezi o coloană pentru o cheie compusă din text, măsoară cel mai lung caz real, nu cel pe care
îl ai în față. Căile de categorie ale furnizorului ajungeau la 105 caractere, iar coloana avea 64, pentru că
primele valori puse acolo erau id-uri de produs de cinci cifre.

**Textele din panou, trecute prin regulile de redactare.** Iulian a dat două exemple: „Aceleași cifre pe care
le-ai vedea într-un tabel, dar pe lungime” și „alege raionul”. Amândouă greșesc în feluri diferite, și amândouă
sunt greșeli pe care le-am făcut eu, nu moșteniri.

Prima descrie forma, nu conținutul: un subtitlu trebuie să spună ce arată secțiunea, nu cum arată. A doua e mai
gravă: „raion” e un termen pe care l-am inventat. Ownerul citește „categorie” peste tot în WordPress și în
WooCommerce, deci fiecare „raion” îl punea să traducă în minte.

Am luat regulile din `agensio-creare-oferte/REGULI-REDACTARE.md` și am trecut tot panoul prin ele: 74 de texte
schimbate în trei fișiere, plus încă zece în celelalte. Verbele colocviale au dispărut: un produs **se importă**,
nu „urcă pe site”; rămâne **neschimbat**, nu „nu se atinge”; valorile **se modifică**, nu „se trage de ele”. Regula
e acum în `CLAUDE.md`, cu cele două greșeli scrise explicit.

`Lecție:` Nu inventa termeni pentru lucruri care au deja un nume pe ecranul clientului. Am scris „raion” pentru
categoria de produs, într-un panou care stă în WordPress, unde scrie „categorie” în meniu, în lista de produse și
în ecranul de editare. Un sinonim mai prietenos nu ajută: obligă omul să traducă. Folosește cuvintele pe care
platforma i le-a băgat deja în cap.

`Lecție:` Un subtitlu explică ce conține secțiunea, nu cum arată ea. „Aceleași cifre, dar pe lungime” vorbește
despre forma graficului; „Cum se împart cele două cataloage, după starea fiecărui produs” spune ce poți afla din
el. Simptomul: subtitlul ar rămâne adevărat dacă ai schimba datele cu totul.

**0.7.0 pe live.** Backup întâi (16 MB, cu `-- Dump completed on` verificat), apoi fișierele într-un director de
probă, unde au trecut toate de `php -l` rulat cu PHP-ul serverului, 8.1.34, nu cu al meu. Abia după aia mutate
peste. Migrarea schemei la v7 a trecut, deci `ccs_decizii.referinta` e acum `varchar(255)` și pe live: căile de
categorie care depășesc 64 de caractere se pot salva. Scrierea în WooCommerce e în continuare oprită,
`mod_raport` e tot pe `true`.

Pe live au ieșit la iveală două texte pe care previzualizarea locală nu le arătase, fiindcă apar doar în stări
goale: „Niciun produs pot fi importate”, un dezacord dintr-o etichetă de plural refolosită, și starea goală a
regulilor de categorie, care îi spunea ownerului să ruleze `wp ccs categorii`. Amândouă corectate și reurcate.

`Lecție:` Stările goale scapă de la verificare tocmai pentru că pe mediul de lucru sunt pline cu date. Mediul
local avea 1.059 de produse gata de import, deci ramura „niciunul” nu s-a randat niciodată; pe live, unde pozele
nu fuseseră încă preluate, s-a văzut din prima. Când treci textele unei interfețe printr-o revizuire, caută
explicit ramurile goale în cod, nu te baza pe ce apare pe ecran.

`Lecție:` O stare goală care spune „rulează comanda X” e scrisă pentru dezvoltator, nu pentru client. Simptomul:
textul cere o acțiune pe care omul care îl citește n-are cum să o facă. Spune în schimb ce se întâmplă de la sine
și când.

**Importul scrie, în sfârșit.** Până acum `ccs_import_pregateste()` doar spunea ce s-ar crea. Acum există și
partea care creează: produs prin CRUD-ul WooCommerce, ca ciornă, cu preț, stoc, categorie, atribute și poze, legat
pe loc de furnizor. Trei porți, în ordinea tăriei: `mod_raport`, o limită explicită sau `--forteaza`, și lipsa
oricărei piedici pe produs.

Categoria e poartă dură, verificată de două ori: o dată ca piedică în pregătire, și încă o dată chiar înainte de
scriere. Cele 5.215 produse pe care nu știm unde să le punem nu se importă, iar asta e cerut explicit. Dublura e
intenționată: un produs aterizat în categoria greșită se găsește mai greu decât unul care n-a fost creat.

Importul e reversibil. `wp ccs revino <rulare>` știe acum și câmpul `creat`: trimite produsul la coșul de gunoi,
nu îl șterge, și scoate legătura. Pozele descărcate rămân, ca o revenire greșită să nu coste o a doua descărcare.

**Două lucruri găsite probind.** Primul, un fatal tăcut: `wp_handle_sideload()` primește primul argument prin
referință, iar eu îi dădeam un array literal. În PHP 8 asta nu e avertisment, e eroare fatală. Rezultatul a fost
cel mai prost cu putință: trei produse create pe jumătate, fără poze și fără legătură, trei rulări rămase pe
„pornit”, și o comandă care se termina cu cod de ieșire 0 și fără niciun mesaj.

Al doilea, numele pozelor. `media_sideload_image()` ar fi fost o linie, dar păstrează numele din URL, iar Conex
trimite fișiere numite `4e668598-bda0-4478-bfbb-af20f332d9b6.jpg`. Pozele sunt singurul conținut pe care îl primim
de la furnizor, fiindcă descrieri nu trimite, deci un fișier cu nume de UUID și fără text alternativ aruncă tot ce
se putea lua din Google Images. Acum numele vine din denumirea produsului plus cod, iar `alt` e denumirea.
`ccs_import_nume_fisier()` e scoasă separat și are nouă verificări; toate trei mutațiile încercate pe ea pică.

`Lecție:` Când o funcție WordPress primește un argument prin referință, un array literal în apel e eroare fatală în
PHP 8, nu avertisment. Simptomul e înșelător: procesul moare fără mesaj, cu cod de ieșire 0, iar munca făcută până
atunci rămâne pe jumătate scrisă. `wp_handle_sideload()`, `wp_handle_upload()` și `wp_update_user()` sunt din
familia asta. Dacă o comandă se oprește brusc și nu lasă nicio urmă, caută un apel cu array literal înainte să
cauți în logica ta.

`Lecție:` O operație care scrie în mai multe locuri trebuie să lase urma înainte de partea care poate cădea, nu
după. Aici produsul se crea, apoi se descărcau pozele, apoi se scria rândul de revenire; fatalul dintre ele a lăsat
produse pe care nimic nu le mai putea da înapoi. Simptomul: după o cădere, găsești obiecte create fără niciun rând
care să spună cine le-a creat.

`Lecție:` Fișierele luate dintr-un feed sau API vin cu nume generate, de obicei UUID-uri. Pentru un magazin, numele
fișierului și textul alternativ sunt singura descriere pe care o are imaginea, și de multe ori furnizorul nu
trimite descrieri deloc. Redenumește la import, din denumirea produsului; după import nu se mai face niciodată.

**Pozele se convertesc în WebP, la calitate 90, și 0.8.0 e pe live.** Conversia se face la import fiindcă după
import nu se mai face niciodată: o poză intrată JPEG rămâne JPEG în toate dimensiunile pe care le generează
WordPress, iar magazinul plătește diferența la fiecare vizitator, nu o dată. Calitatea e peste implicita
WordPress, care e 82, pentru că la poze de produs omul mărește ca să vadă cusătura husei sau filetul șurubului.
Măsurat pe produse reale, la 1000x1000: 30 KB coboară la 14, 31 la 11, 61 la 48.

Dacă serverul nu poate scrie WebP, poza rămâne cum a venit și se scrie în jurnal: o poză în formatul vechi e mai
bună decât niciuna. O poză care vine deja WebP nu se recodează. Local conversia merge prin GD, pe live prin
Imagick, și am probat-o pe amândouă.

Produsele noi rămân ciorne, cum a cerut Iulian. Publicarea cere `--publica`, pe care nu o dau.

**Un bug prins de probă, nu de teste.** Regexul care tăia extensia ca să pună `.webp` avea clasa de caractere
neînchisă. `preg_replace` întorcea null, deci ținta ieșea `.webp`, o cale relativă, iar fișierul se scria în
directorul curent al procesului, nu lângă sursă. Nimic nu se plângea: imaginea ajungea până la urmă în bibliotecă,
fiindcă `wp_handle_sideload` o muta de oriunde ar fi fost. S-a văzut doar fiindcă am pus proba să tipărească
numele rezultatului, care a ieșit `.webp.webp`. Acum ținta se face din `dirname` plus `PATHINFO_FILENAME`, fără
regex.

`Lecție:` Un regex cu clasă de caractere neînchisă nu dă eroare în PHP, dă null. Lipit de un șir cu punct, null
devine un nume de fișier relativ, iar fișierul aterizează în directorul curent al procesului. Simptomul: un
fișier cu nume ciudat apare în rădăcina site-ului sau în directorul din care s-a dat comanda. Verifică
`preg_last_error_msg()` când un `preg_replace` pare să nu facă nimic.

`Lecție:` Când construiești o cale schimbând extensia, lucrează pe bucăți, cu `dirname()` și `PATHINFO_FILENAME`,
nu cu un regex peste tot drumul. O cale ca `/tmp/dosar.vechi/poza` are punctul în dosar, nu în fișier, iar o
tăiere pe ultimul punct scrie în alt loc decât crezi.

`Lecție:` Pune proba să tipărească rezultatul, nu doar să spună că a reușit. Conversia „mergea”: întorcea un
fișier WebP valid, de dimensiunea potrivită. Numai numele tipărit a arătat că fișierul se scria unde nu trebuie.

**Ce ștergem de pe site, hotărât cu date.** Roli a scris că multe produse i se par degeaba pe site și greu de
găsit, și a propus o regulă: stoc 0 două săptămâni, apoi la coșul de gunoi. Regula are semnalul greșit. Stocul 0
la Conex e temporar, produsul revine. Am verificat și două lucruri pe serverul lor, în loc să le presupun:
`EMPTY_TRASH_DAYS` e 30, deci „trash” e o ștergere cu o lună întârziere; și am probat că după trash importul nostru
vede codul ca liber, deci ar recrea produsul cu alt id. Un produs care revine pe stoc ar fi intrat într-o buclă.

Am măsurat în loc să opinez. Am încrucișat cele 2.773 de produse fără furnizor cu 16 luni de Search Console, prin
MCP-ul Google. Tot site-ul face 795 de clicuri în 16 luni. Din cele 2.439 rămase din feed-ul Mega Drive oprit,
**1.827 n-au avut nicio afișare** în tot acest timp, iar celelalte 612 au adus împreună 105 clicuri, adică 6,6 pe
lună. Deci toată discuția „ștergem 2.439 de produse” se reducea la: merită 6,6 clicuri pe lună ca să ținem 612
pagini? Celelalte 1.827 nu costă nimic la ștergere. Cele 292 introduse manual de echipă sunt grupul cu cele mai
multe clicuri de pe site, 162, și nu se ating.

Hotărârea: 1.827 la coșul de gunoi, 612 ascunse din catalog și pe stoc epuizat, cu adresa vie. `wp ccs curata`
face asta dintr-o listă de id-uri, nu dintr-o regulă, iar `wp ccs revino` dă totul înapoi, inclusiv starea și
vizibilitatea. Lista se face cu `tools/curatenie-lista.py` și nu se poate face din plugin, fiindcă Search Console
cere alt cont. Ăsta e și motivul pentru care curățarea rămâne o apăsare de buton, nu un cron.

**A doua jumătate a mesajului era altă problemă.** „Greu de găsit” nu era despre clienți, ci despre el: lucrează
în `Produse` din wp-admin și cu șase mii de produse în listă nu mai găsește nimic. Asta e o reparație mult mai
ieftină decât restructurarea categoriilor la care mă gândeam. Lista are acum un filtru după sursă și o coloană
care spune, pentru fiecare produs, dacă mai e la furnizor, cu ce stoc și ce cost.

`Lecție:` Când clientul propune o regulă automată de ștergere, întreabă ce semnal măsoară de fapt. „Stoc 0 de N
zile” pare să însemne „nu se mai vinde”, dar într-un magazin care se aprovizionează de la un furnizor înseamnă
„furnizorul n-are acum”. Semnalul corect era „furnizorul nu-l mai are deloc”, pe care îl aveam deja calculat.

`Lecție:` Înainte să ștergi pagini de pe un magazin, întreabă Search Console cât aduc. Aici 75% din paginile
candidate n-au avut nicio afișare în 16 luni, iar restul valorau 6,6 clicuri pe lună: două cifre care transformă
o ceartă de principiu într-o hotărâre de cinci minute. Search Console listează doar paginile cu cel puțin o
afișare, deci „zero afișări” chiar înseamnă zero, nu lipsă de date.

`Lecție:` „Nu găsesc produsele” poate însemna „clienții nu le găsesc pe site” sau „eu nu le găsesc în wp-admin”.
Sunt două lucrări complet diferite, una de luni și una de ore. Întreabă care, înainte să propui restructurarea
categoriilor.

`Lecție:` Când un filtru de admin se apasă des, nu-l pune peste o funcție care face toată munca grea. Grupurile
din panou vin din potrivirea întreagă, care ia minute pe douăzeci de mii de produse; pentru lista de produse
aceleași grupuri se calculează cu o interogare pe `_sku` și un `LEFT JOIN`, prin indecși.

`Lecție:` O învățare din date nu vede decât datele legate. La Cez Auto, regula „uleiurile de motor merg în raionul
Ulei motor” era evidentă pentru orice om, dar invizibilă pentru mașinărie, pentru că uleiurile magazinului nu erau
legate de furnizor. Lasă întotdeauna un loc din care omul să adauge o regulă pe care învățarea n-are cum s-o
găsească, și fă ca regula lui să bată pe cele învățate.

`Lecție:` Când clientul întreabă ce înseamnă o cifră de pe ecran, eticheta e greșită. Nu explica cifra, schimbă
eticheta. Și verifică dacă întrebarea nu cumva ascunde o lipsă mai mare: aici, „ce înseamnă 5.522” a dus la o
funcționalitate care lipsea cu totul.

`Lecție:` Culoarea plină merge pe suprafețe mari, ca o bară care împarte un catalog, nu pe carduri mici puse în
rând. Trei carduri colorate unul lângă altul se concurează între ele și acoperă conținutul paginii; aceeași
informație cu fundal alb, iconiță colorată în colț și cifra în culoarea stării se citește la fel de repede și
lasă restul paginii să respire.

`Lecție:` Când arăți „câte au fost făcute” și „câte au rămas”, pune o bară, nu două numere. Două numere cer o
scădere în minte de fiecare dată când omul se uită la ecran.

`Lecție:` Regula de design se alege după ce face ecranul, nu pentru tot produsul. Un ecran care pune o întrebare se
descurcă cu gri și o singură culoare de accent. Un ecran care arată un catalog de zeci de mii de rânduri are nevoie
de culoare plină și de bare, altfel omul trebuie să citească și să adune singur. Aceeași paletă, altă cantitate.

`Lecție:` Când clientul spune „nu se deduce nimic de la prima vedere”, nu adăuga explicații, adaugă forme. O bară
segmentată cu patru culori spune în două secunde ce spune un tabel cu patru rânduri în douăzeci. Și arată ce
lipsește: la Cez Auto, bara catalogului Conex face vizibil dintr-o privire că jumătate din el e blocat de lipsa
pozelor, ceea ce nicio cifră din tabel nu transmitea.

`Lecție:` Dacă produsele tale au poze, arată-le. O previzualizare a cinci produse cu poza reală răspunde la
întrebarea „ce se va importa” mai bine decât orice listă de câmpuri, și costă un `<img>` dacă adresele sunt deja în
baza ta.

`Lecție:` Într-un tablou de bord, desparte fluxul de restanță. „Ce s-a schimbat săptămâna asta” și „ce s-a adunat
până acum” arată la fel ca date, dar prima se citește mereu, iar a doua o dată. Puse împreună, cifra mare a
restanței îneacă mișcarea, care e singura care spune dacă sistemul e viu.

`Lecție:` Prima rulare a unei sincronizări strică orice statistică de tip „nou în ultimele N zile”, pentru că
inserează tot deodată. Ține minte când s-a încheiat rularea de pornire și numără numai după ea. Simptomul: în prima
săptămână, „produse noi” e egal cu tot catalogul.

`Lecție:` Un tipar de cod spune de unde vine un produs, iar asta e adesea tot ce-ți trebuie ca să împarți un catalog
moștenit. La Cez Auto, `CZ1135` e pus de mână de echipă și `10000` a venit din feed-ul vechi; aceeași întrebare
pusă clientului produs cu produs ar fi însemnat 2.743 de răspunsuri, iar pe tipare a însemnat două.

`Lecție:` Când un client confirmă o premisă, verifică dacă din ea chiar rezultă concluzia. Ownerul a spus adevărul,
că produsele nu sunt introduse manual, dar concluzia trasă din asta, că se pot înlocui cu produsul Conex de pe acel
cod, nu rezulta. Între premisă și concluzie era o presupunere nescrisă: că numărul comun înseamnă același articol.

`Lecție:` Scrie previzualizarea înainte de operație, chiar când operația pare hotărâtă și simplă. Aici a costat
treizeci de minute și a oprit o schimbare care ar fi transformat zece anunțuri reale în clipsuri de tapițerie.
Simptomul că îți trebuie: urmează să scrii în masă peste conținut existent, pe baza unei potriviri.

`Lecție:` Nu te potrivi niciodată pe un câmp care nu e identificator unic în ambele sisteme. Codul de producător e
unic la nivelul unui producător, nu al catalogului: ROMIX și Mega Drive folosesc amândouă numere scurte secvențiale,
deci se ciocnesc. Simptomul: potrivirile greșite au un tipar, toate duc la produse de același fel sau de același
brand.

`Lecție:` Două căi de cod care citesc același lucru trebuie să-l citească la fel. Titlul unui produs WordPress e
curat în baza de date și cu entități după `get_the_title()`, pentru că trece prin `wptexturize`. O funcție care
compară nume trebuie să decodeze, altfel dă rezultate diferite după cum a fost chemată.

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

**Preluarea reală a unui produs.** Iulian a cerut să nu importăm încă nimic, dar să facem preluări reale.
`wp ccs produs <cod>` cere catalogul de la API, găsește produsul și arată ce s-ar crea pe site: prețul cu formula
desfăcută pe bucăți, categoria cu regula care a hotărât-o, pozele, atributele, și piedicile dacă există. Nu scrie
nimic. Funcția de pregătire e aceeași pe care o va folosi importul, ca să nu existe două calcule care pot să nu fie
de acord.

Măsurat pe drum: **API-ul nu filtrează pe produs.** `productId`, `ProductId`, `codEAN`, `code` și `search` lasă toate
`total` la 20.725. Deci un produs se ia tot din catalogul întreg, care vine în 2 secunde de pe local și 7 de pe
server. Tot atunci am văzut că răspunsul e un plic `{total, data}`, nu o listă.

**Ce a găsit previzualizarea.** Primul produs probat, un set de yale, a primit categoria „Accesorii exterior” pe baza
unei reguli **învățate dintr-un singur produs**, cu 100% încredere. O regulă din un exemplu are întotdeauna 100%,
pentru că n-are cu ce se contrazice. Din cifre n-aș fi văzut-o niciodată; s-a văzut pentru că previzualizarea scrie
de ce a ales ce a ales.

Am pus praguri: sub 3 produse sau sub 60% încredere, regula rămâne în tabel dar nu se folosește la import, iar
produsul intră în lista de mapat de om. Regulile puse de om trec oricum. Efectul: produsele gata de import au scăzut
de la 1.618 la **1.059**, iar 45 din 64 de reguli rămân folosibile. Cifra nouă e aproape de cele 993 din ofertă,
ceea ce e un semn bun: numărul din ofertă era corect, al meu de dimineață era umflat.

**O greșeală a mea, care a atins API-ul furnizorului.** Testând filtrele, am chemat `ccs_api_toate()` cu al treilea
argument înțeles greșit: credeam că cere o singură pagină, dar e un callback, și funcția a pornit paginarea întregului
catalog cu pagini de 3 produse. Am oprit-o în câteva secunde, iar plafonul de 500 de pagini din funcție a mărginit
oricum paguba. Lecția e mai jos.

**Jobul de noapte pentru imagini, și cifra adevărată a importului.** Piesa care deblochează etapa 4: fără să știm
ce produse au poze, regula „un produs fără poză nu urcă pe site” rămâne pe hârtie. Tabel propriu cu adresele
pozelor, job programat la 19:10 în fereastra furnizorului, plus `wp ccs import`, care spune câte produse se pot
importa **și de ce nu se pot celelalte**.

Nu s-a putut proba cu API-ul, pentru că endpoint-ul răspunde doar între 19:00 și 05:00, iar era 13:30. Dar sondajul
din 14 octombrie e salvat în `cez-auto/tools/conex-images.json`, în exact forma agregată de care era nevoie. Am
scris `tools/imagini-de-proba.php`, care îl încarcă în tabel pe mediul local, și tot lanțul s-a putut verifica ziua.

Rezultatul, pe datele alea: **1.618 produse se pot importa**, nu 993 cât credeam în octombrie. Diferența vine din
maparea categoriilor, care acoperă acum mai mult decât atunci. Restul: 9.519 fără poză, 4.963 fără categorie știută
(piesele OE), 700 fără stoc.

**Un bug găsit la verificare.** Jobul de noapte s-ar fi programat la 21:10, nu la 19:10. Cauza:
`current_time('timestamp')` întoarce ora locală deghizată în timestamp UTC, `strtotime()` citește șirul în fusul
PHP, iar `wp_date()` adaugă încă o dată offsetul. Trei funcții, fiecare corectă singură, greșit împreună. Acum ora se
socotește în fusul furnizorului și se întoarce ca timestamp real.

Am despărțit regula ferestrei într-o funcție proprie, `ccs_api_in_fereastra()`, tocmai ca să poată fi probată:
fereastra trece peste miezul nopții, deci condiția nu e un interval obișnuit. Testele: 12 ore plus marginile, iar
trei mutații (începe la 20, ține și la 5, `și` în loc de `sau`) cad toate. Suita a ajuns la 40 de verificări.

`Lecție:` Scrie o previzualizare care spune **de ce**, nu doar **ce**. Un raport care arată „categoria: Accesorii
exterior” e corect și nefolositor; unul care arată „regulă învățată din 1 produse, 100%” scoate la iveală că regula
e o coincidență. La Cez Auto, linia asta a schimbat numărul de produse importabile de la 1.618 la 1.059, adică
adevărul. Simptomul că îți lipsește: te uiți la o cifră agregată și n-ai cum să verifici dacă e bună.

`Lecție:` O regulă învățată dintr-un singur exemplu are întotdeauna 100% încredere, pentru că n-are cu ce se
contrazice. Orice învățare din date are nevoie de un prag pe **numărul de exemple**, nu doar pe procent. Fără el,
regulile cele mai slabe arată exact ca cele mai bune.

`Lecție:` Înainte să chemi o funcție de paginare cu argumente pe care nu le-ai recitit, uită-te la semnătură. Am
crezut că al treilea argument al lui `ccs_api_toate()` cere o singură pagină; era un callback, iar funcția a pornit
paginarea întregului catalog cu pagini de 3 produse, către API-ul furnizorului. Două lucruri au limitat paguba:
plafonul de 500 de pagini scris în funcție și oprirea pe loc. Pune plafoane în buclele care cheamă servicii străine,
chiar dacă pare că nu se pot declanșa niciodată.

`Lecție:` Când nu poți proba ceva din cauza unei ferestre de timp, caută dacă n-ai deja datele salvate dintr-un
sondaj mai vechi. La Cez Auto, endpoint-ul de imagini răspunde doar noaptea, dar sondajul din octombrie era pe disc
în forma potrivită, iar tot lanțul de după el s-a putut verifica la prânz. Un script de încărcare marcat clar ca
fiind doar pentru dezvoltare costă zece minute și scutește o zi de așteptare.

`Lecție:` Nu amesteca `current_time('timestamp')` cu `strtotime()` și `wp_date()`. Prima întoarce ora locală
deghizată în timestamp UTC, a doua citește în fusul PHP, a treia adaugă iar offsetul: fiecare e corectă singură și
greșite împreună. Pentru o oră viitoare, lucrează cu `DateTimeImmutable` într-un fus explicit și întoarce
`getTimestamp()`. Simptomul: un job programat apare cu ora deplasată exact cu offsetul fusului.

`Lecție:` O condiție care trece peste miezul nopții se desparte într-o funcție proprie și se testează. `$ora >= 19
|| $ora < 5` nu e un interval obișnuit, iar o greșeală în ea se vede abia la 3 dimineața, când jobul fie refuză să
pornească, fie pornește când serviciul e închis.

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
