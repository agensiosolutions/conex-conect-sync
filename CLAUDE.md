# conex-conect-sync: instrucțiuni pentru Claude

Pluginul WooCommerce de sincronizare cu **Conex Conect**, API-ul distribuitorului Conex Distribution. Ține stocul și
prețul la zi, curăță produsele ieșite din catalog și importă produse noi, fără fișiere încărcate manual.

Primul client care îl plătește: **Cez Auto** (cezauto.ro), ofertă de 4.000 lei acceptată pe 17 nov. 2026. Pluginul e
construit ca produs, nu ca lucrare pentru un singur magazin: Piese Total și alți parteneri Conex urmează.

## Reguli de lucru

- Română cu diacritice (ș, ț cu virgulă), în cod, comentarii, texte și documente. Niciodată liniuța lungă; nici cea medie.
- Nu citești fișiere cu credențiale (`.env`, `wp-config.php`, tokenuri). Tokenul Conex îl pune Iulian în admin sau în
  `CCS_API_TOKEN` din wp-config. Nu ajunge niciodată în repo, în jurnal sau pe ecran: tot ce se scrie trece prin
  `ccs_api_fara_token()`.
- Nimic pe live sau pe staging fără „push pe live” explicit de la Iulian, la fiecare urcare. Backup înainte.
- Dezvoltarea se face local, pe o copie a bazei Cez Auto (decizia Iulian, 19 nov.): valoarea pluginului stă în
  potrivirea datelor reale, deci am nevoie de cele 6.723 de produse, categoriile și atributele lor.
- Planul pe etape e în `TASKS.md`; o schimbare de direcție se propune întâi acolo.
- Lecțiile (rândurile `Lecție:` din `JURNAL.md`) trec în MasterMind doar după aprobarea lui Iulian.

## Ce știm despre API, din măsurători

Toate regulile de mai jos vin din teste pe API-ul real (Cez Auto, 13-14 oct. 2026). **Niciuna nu e în documentație.**
Sunt implementate în `plugin/includes/api.php`, cu explicația lângă fiecare. Nu le „simplifica”: fiecare costă o zi de
redescoperit.

- Câmpul `total` din răspuns **nu** e numărul de rânduri. Paginăm după lungimea paginii, niciodată după `total`.
- O pagină peste ultima răspunde **404**, nu listă goală. La paginare, 404 înseamnă „gata”, nu eroare.
- `images`, `equivalents`, `criteria`, `applications` răspund **doar între 19:00 și 05:00**; în rest, 503 cu mesaj
  explicit. Jobul se reprogramează, nu eșuează.
- API-ul are **eșecuri intermitente** pe răspunsurile mari, care se înrăutățesc spre dimineață: la 19:04 totul mergea
  din prima, la 21:52 `criteria` a dat 500 de cinci ori la rând. De aici retry cu backoff și pagini mici (20.000, nu
  200.000) pe endpoint-urile grele. Joburile de noapte se programează **devreme în fereastră, 19:00-20:00**.
- Parametrul `Fields` schimbă capitalizarea cheilor din răspuns. Normalizăm la litere mici la citire.
- **Mai bine fără date noi decât cu date parțiale.** Dacă o pagină nu vine după toate încercările, întreaga preluare
  eșuează și nu se scrie nimic. Altfel un 500 la ora 3 noaptea ar trece produsele pe stoc zero în masă.

Scripturile Python care au produs măsurătorile sunt în `D:\Work\Repos\cez-auto\tools\` și rămân sursa de adevăr pentru
cifre: `conex-fetch.py`, `conex-night-probe.py`, `conex-night-fetch.py`, `conex-stability-probe.py`.

## Principii de construcție

- **Nimic nu se șterge.** Un produs absent din feed N runde trece pe stoc epuizat, nu dispare. Linkurile și poziția în
  Google rămân.
- **Dry-run întâi.** Orice sincronizare poate rula în „mod raport”: arată ce s-ar schimba, fără să atingă WooCommerce.
  `mod_raport` e pornit implicit.
- **Prețul nu e doar un procent.** `max(adaos, cost + marjă_minimă_lei, preț_minim)`. Adaosul procentual singur se
  comportă prost la produsele ieftine: un clips de 0,22 lei ajunge la 1 leu, adică +359% adaos dar 0,78 lei marjă
  brută, sub costul de procesare a comenzii.
- **Produsele clientului rămân ale clientului.** Produsele introduse manual (la Cez Auto, cele cu SKU `CZ*`) nu sunt
  atinse. La fel prețurile marcate manual.
- **Un produs fără poză nu urcă pe site.** Produsele incomplete rămân în panou și se importă singure când furnizorul
  adaugă fotografia.
- **Generic, fără hardcodări pe un client.** Adaos, cotă, rotunjire, reguli de stoc, mapare de categorii: toate în
  setări, cu export/import JSON.
- **Extensibil prin filtre**: `ccs_setari`, `ccs_calculate_price`, `ccs_should_import_product`, `ccs_product_payload`,
  `ccs_match_candidates`, `ccs_map_category`.
- **WordPress:** `defined( 'ABSPATH' ) || exit;` în fiecare fișier PHP; prefix `ccs_` pe funcții, hook-uri, opțiuni și
  tabele; text domain `conex-conect-sync`, încărcat pe `init`; escapare la ieșire; `Update URI: false`.
- **Stil de cod ca la `agensio-consent`**: `includes/*.php` cu prefix, nu PSR-4 cu namespace-uri. `PLAN.md` de la
  Piese Total propunea PSR-4; am ales consecvența cu celălalt plugin al agenției (decizia Claude, 19 nov., de confirmat
  de Iulian).
- **Versiuni și flotă**: ca la `agensio-consent`, cu `npm run release` și `tools/flota.mjs`. Uneltele se copiază de
  acolo la etapa 5, când avem ce livra.

## Surse

- Planul de arhitectură: `D:\Work\Repos\piese-total\PLAN.md` (scris pentru Piese Total, adaptat aici).
- Documentația oficială Conex: `D:\Work\Repos\piese-total\Documentatie Conex Conect.pdf`.
- Analiza catalogului Cez Auto și rapoartele: `D:\Work\Repos\cez-auto\rapoarte\`.
- Oferta acceptată: https://agensio.ro/oferta-cezauto-rkryt7/
- Convențiile de plugin: `D:\Work\Repos\agensio-consent\CLAUDE.md`.

<!-- structura-agensio -->
## Structura proiectului (comună în proiectele Agensio)

- `TASKS.md`: starea (Acum, Urmează, Așteaptă, Gata). Îl actualizezi pe loc când se termină, se adaugă sau se blochează
  ceva. Pagina de taskuri îl afișează (`/start`).
- `JURNAL.md`: istoricul, cea mai nouă intrare sus, una pe zi de lucru. La finalul fiecărei sesiuni scrii ce s-a făcut,
  ce s-a decis și de ce; ce merită păstrat în MasterMind începe cu `Lecție:`. Lecția spune regula generală și simptomul
  după care o recunoști într-un alt proiect, nu doar ce s-a întâmplat aici; MasterMind o preia singur din jurnal.
- **Înainte să diagnostichezi sau să schimbi ceva pe server, Cloudflare, WooCommerce, cron, deploy sau o campanie**
  (Black Friday, lansare), caută întâi ce s-a mai întâmplat în alte proiecte:
  `grep -ril "<simptom>" D:/Work/Repos/agensio-mastermind/content/` (ex. `bot`, `503`, `cloudflare`, `black-friday`), apoi
  citește capcanele, checklisturile și exemplele găsite. Dacă se potrivește ceva, spune-i lui Iulian ce ai găsit și din
  ce proiect vine.
- `PROJECT.md`: contextul care se schimbă rar (clientul, accesul fără secrete, stack-ul, deciziile mari).
- `docs/evidenta/`: ce trimite clientul (oferte, aprobări, capturi, poze). Când Iulian aduce un document nou, îl salvezi
  aici cu data în nume, îl citești ca să înțelegi taskul și îl legi din taskul respectiv.
- **Imaginile pentru site** (poze de la client, capturi, bannere) le convertești singur în WebP, local, înainte de
  upload, fără să ți se ceară: `python -I D:/Work/Repos/agensio-mastermind/scripts/imagini/webp.py <fișier sau folder> --slug`, întâi cu `--proba`.
  Originalul rămâne local, pe site urcă doar WebP-ul. Conversia pe server se pune doar la cerere (magazine cu imagini
  din feed-uri sau API). `/webp` face același lucru, la cerere.
- `brand/brand.json` (opțional): culorile și fontul pentru pagina de taskuri. Fără el, pagina e închisă și neutră.
- Formatul lui `TASKS.md` se poate schimba (secțiuni proprii, categorii `###`, etichete `#`, alt layout în
  `brand/brand.json` sau `brand/taskuri.css`); regulile sunt în `agensio-mastermind/scripts/taskuri/README.md`. După ce
  Iulian schimbă formatul sau aspectul, rulează `node D:/Work/Repos/agensio-mastermind/scripts/taskuri/taskuri.mjs verifica .`
  și repară ce semnalează, ca nimic să nu dispară de pe pagină.
