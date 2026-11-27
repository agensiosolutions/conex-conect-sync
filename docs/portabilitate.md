# Ce mai cere pluginul ca să meargă în orice magazin WooCommerce

Scris pe 27 noiembrie 2026, după ce Iulian a spus că pluginul trebuie să poată fi instalat ușor în alte
magazine. Premisa, confirmată de el: **furnizorul va fi mereu Conex.** Deci nu e nevoie de nicio abstractizare
peste furnizori, iar adresa API-ului, fereastra de noapte și numele câmpurilor pot rămâne cablate. Singura
variabilă e magazinul.

Tot ce scrie mai jos e verificat în cod sau pe serverul Cez Auto, nu presupus. Unde e o bănuială, scrie că e.

## Ce e deja în regulă

Merită spus, ca să se vadă cât de departe e drumul:

- antetul declară `Requires at least: 6.4`, `Requires PHP: 8.0`, `Text Domain`, `Domain Path` și
  `Update URI: false`;
- schema are versiune (`CCS_SCHEMA_VERSIUNE = 8`) și se migrează singură la încărcare, prin `dbDelta`;
- activarea creează tabelele, dezactivarea scoate joburile din coadă;
- tot ce scrie în WooCommerce trece prin CRUD (`wc_get_product`, `save()`), nu prin `update_post_meta`;
- scrierea e oprită din start (`mod_raport`), deci o instalare nouă nu poate strica nimic până nu o pornește
  cineva;
- există frâne: scăderea maximă de preț, pragul de produse atinse într-o rulare, oprirea când catalogul
  furnizorului se înjumătățește;
- verificările de configurare semnalează TVA-ul, moneda, categoriile șterse și atributele care s-ar suprapune;
- tokenul se pune din panou, fără SSH, și se probează pe loc;
- punctele în care alt magazin ar vrea altceva au deja filtre: `ccs_map_category`, `ccs_nume_fisier_poza`,
  `ccs_atribute_filtrabile`, `ccs_poza_marime_maxima`, `ccs_import_din_panou_plafon`.

## 1. Blochează o instalare nouă

### Maparea categoriilor nu are de unde să se învețe

Cel mai serios lucru din document.

Regula „calea X a furnizorului merge în categoria Y a magazinului” se învață din produsele deja legate. La
Cez Auto sunt 3.950 de perechi, făcute de oameni de-a lungul anilor, din care au ieșit 64 de reguli. Un
magazin nou n-are nicio pereche, deci învață zero reguli, deci niciun produs nu trece de condiția „are
categorie”, deci importul rămâne gol.

Pe ecran ar arăta ca un plugin stricat: catalogul furnizorului are 20.735 de produse, iar „gata de import”
scrie zero, fără să spună de ce într-un fel pe care cineva îl poate repara.

Ce ar rezolva: un ecran de mapare pentru magazine fără istoric, care arată căile furnizorului ordonate după
câte produse ar debloca fiecare și cere o categorie pentru fiecare. Partea de calcul există deja, se folosește
azi pentru cele 3.873 de produse blocate la Cez Auto; lipsește forma pentru cazul „nu avem nimic învățat”.

De adăugat și ghidarea: la prima instalare, pasul unu nu e tokenul, e „leagă-ți categoriile”.

### Nu există ecran de setări

Pluginul are în jur de treizeci de setări. Din panou se pot schimba exact două lucruri: politica de preț și
tokenul. Restul, de la intervalul de preluare la pragurile de atribute, numai din linia de comandă.

Pentru noi e în regulă. Pentru un magazin străin, linia de comandă nu există.

Marginile sunt deja scrise (`ccs_setari_margini()`), deci un ecran de setări are de unde să-și ia validarea și
textele de ajutor. E muncă de interfață, nu de logică.

### Nu există dezinstalare

Nu există `uninstall.php`. La ștergerea pluginului rămân în urmă **zece tabele** (`ccs_products`, `ccs_links`,
`ccs_runs`, `ccs_imagini`, `ccs_atribute`, `ccs_categorii`, `ccs_decizii`, `ccs_excluse`, `ccs_modificari`,
`ccs_log`) și cel puțin nouă opțiuni.

Un plugin care își lasă gunoiul în baza altuia nu se poate da mai departe. De gândit și ce *nu* se șterge: pe
produsele importate rămân metadate (`_ccs_pret_manual`, `_ccs_scadere_permisa`), iar alea sunt decizii ale
omului, nu date ale noastre. Probabil se păstrează, cu o întrebare la dezinstalare.

### WooCommerce nu e declarat ca dependență

Antetul n-are `Requires Plugins: woocommerce`, iar codul nu verifică la încărcare că WooCommerce există.
Instalat pe un WordPress fără WooCommerce, pluginul se activează și abia apoi cade, pe funcții care nu există.

Reparația e de câteva linii: antetul plus o verificare care oprește încărcarea cu un mesaj clar.

## 2. Se instalează, dar se poartă greșit

Lucrurile astea nu împiedică instalarea. Sunt mai periculoase tocmai de-aia.

### Prima potrivire pe un magazin necunoscut

La Cez Auto potrivirea s-a făcut pe un magazin pe care îl știam, și tot a scos la iveală capcana ROMIX: coduri
de producător de cinci cifre care se ciocneau peste SKU-uri vechi. Am prins-o fiindcă ne uitam.

Pe un magazin străin, potrivirea pe SKU și pe nume poate lega greșit mii de produse din prima rulare. Frâna de
25% există, dar n-a fost probată niciodată pe alt magazin.

Ce ar ajuta: prima potrivire să fie întotdeauna o probă, cu un ecran care arată un eșantion de legături și cere
confirmare înainte să scrie ceva. Azi `ccs_potriveste( false )` face deja calculul fără să scrie; lipsește
ecranul și obligativitatea.

### Produsele variabile

Toate cele 6.849 de produse de pe cezauto.ro sunt simple. Alt magazin aproape sigur are și variabile, iar azi
un produs variabil ar primi `set_regular_price()` pe părinte, unde prețul vine din variații. Nu dă eroare, pur
și simplu nu face nimic util.

Minimul: o gardă care sare peste ele și le numără undeva vizibil. Varianta întreagă, sincronizarea pe variații,
e alt proiect.

### Moneda și cota de TVA

Verificarea de sănătate le semnalează de ieri, dar implicitele rămân românești: cota 1,21, marja minimă în lei,
prețul minim în lei. Interfața politicii de preț are „21%, cota în vigoare din august 2025” scris ca opțiune
fixă.

Pentru magazine din România e exact ce trebuie. Dincolo, implicitele sunt greșite din prima secundă.

### Limba

Totul e în română. `load_plugin_textdomain()` se cheamă corect și arată spre `/languages`, dar **folderul nu
există** și nu există niciun fișier `.pot`. Pentru magazine românești nu e o problemă; pentru altele, e muncă
de traducere plus generarea catalogului.

### Lungimea rulărilor

Aplicarea pe 3.900 de produse ia în jur de șase minute. Nu știu ce se întâmplă la 50.000: lacătul expiră în
treizeci de minute, iar Action Scheduler are limitele lui de lot și de timp. Asta e o bănuială, nu o măsurătoare.
Ar trebui probată pe un catalog mare, generat, înainte să fie o surpriză la client.

## 3. Ca să fie produs, nu doar cod care merge

### Nu există pachet

`package.json` pomenește `tools/zip.mjs`, `tools/release.mjs` și `tools/flota.mjs`. **Niciunul nu există.** Nu
există `.zip` instalabil, nu există cale de actualizare (`Update URI: false`, deliberat), nu există număr de
versiune pe care să-l vadă cineva din afară.

Fără asta, „instalare ușoară” înseamnă copiat fișiere prin FTP.

### Nu s-a probat niciodată pe WordPress curat

Tot ce verific rulează pe o copie a bazei Cez Auto. Nu știu dacă pluginul se activează fără erori pe un
WordPress gol cu WooCommerce proaspăt instalat, iar ăsta e literalmente primul lucru pe care îl face un magazin
nou.

E și cel mai ieftin test din document: un WordPress curat în Local, WooCommerce, activare, parcurs toate
taburile. O oră, și răspunde la mai multe întrebări de mai sus decât orice citire de cod.

### Documentație pentru cine instalează

Ghidul din panou e scris pentru cine folosește pluginul zi de zi. Lipsește cel pentru prima oră: de unde se ia
tokenul, ce se întâmplă la prima potrivire, de ce scrierea e oprită și când se pornește.

## Ordinea pe care aș merge

1. **Testul pe WordPress curat.** Cel mai ieftin, și schimbă restul listei: o parte din bănuielile de mai sus
   se confirmă sau pică într-o oră.
2. **Dependența de WooCommerce** în antet și o verificare la încărcare. Câteva linii.
3. **Maparea fără istoric.** Fără ea pluginul nu poate funcționa nicăieri altundeva, oricât de bine ar arăta
   restul.
4. **Dezinstalarea.** Ține de a fi produs, nu de a funcționa, dar nu se poate da mai departe fără ea.
5. **Pachetul `.zip` și versionarea.**
6. **Ecranul de setări.**
7. **Prima potrivire ca probă obligatorie.**
8. Restul: variabile, monedă, limbă, cataloage mari. După primul magazin adevărat, care va spune care contează.

Primele cinci sunt „nu se poate instala altundeva”. De la șase încolo e „se poate, dar e neplăcut”.
