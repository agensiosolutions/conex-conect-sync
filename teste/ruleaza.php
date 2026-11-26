<?php
/**
 * Testele pentru cele două funcții pure ale pluginului.
 *
 * De ce doar două: `ccs_pret()` și `ccs_nume_asemanare()` sunt singurele care hotărăsc ceva important fără să atingă
 * baza de date sau API-ul. Una stabilește cu ce preț se vinde fiecare produs, cealaltă dacă un produs de pe site e
 * același cu unul de la furnizor. O regresie în oricare din ele schimbă magazinul fără ca cineva să observe.
 *
 * Restul codului vorbește cu WooCommerce și cu API-ul Conex, unde verificarea pe date reale spune mai mult decât un
 * test cu date inventate. Cifrele de aici nu sunt inventate: sunt produse adevărate din catalogul Cez Auto, alese
 * pentru că fiecare a scos la iveală ceva.
 *
 * Rulare:
 *   php teste/ruleaza.php
 *
 * @package conex-conect-sync
 */

// `ccs_nume_cuvinte()` folosește mbstring. E prezentă pe orice gazdă care rulează WooCommerce, dar PHP-ul livrat cu
// Local by Flywheel n-o încarcă fără `php.ini`-ul site-ului, iar fără mesajul ăsta testul cade cu o eroare fatală
// care nu spune nimic.
if ( ! function_exists( 'mb_strtoupper' ) ) {
	echo "Lipsește extensia mbstring. Rulează testele cu PHP-ul de sistem: php teste/ruleaza.php
";
	exit( 2 );
}

// Cele patru funcții WordPress pe care le folosesc fișierele testate. Atât, nimic altceva.
define( 'ABSPATH', __DIR__ );

$GLOBALS['ccs_setari_test'] = array();

/**
 * Setările, din variabila de test.
 *
 * @param string $nume     Numele opțiunii.
 * @param mixed  $implicit Valoarea implicită.
 * @return mixed Setările.
 */
function get_option( $nume, $implicit = false ) {
	return $GLOBALS['ccs_setari_test'];
}

/**
 * Filtrele, fără niciun ascultător.
 *
 * @param string $hook    Numele.
 * @param mixed  $valoare Valoarea.
 * @return mixed Valoarea, neatinsă.
 */
function apply_filters( $hook, $valoare ) {
	return $valoare;
}

/**
 * Argumentele peste valorile implicite.
 *
 * @param array $argumente Argumentele.
 * @param array $implicite Valorile implicite.
 * @return array Rezultatul.
 */
function wp_parse_args( $argumente, $implicite = array() ) {
	return array_merge( $implicite, (array) $argumente );
}

/**
 * Filtrele cu mai multe argumente, pentru `api.php`.
 *
 * @param string $hook    Numele.
 * @param mixed  $valoare Valoarea.
 * @return mixed Valoarea, neatinsă.
 */
function ccs_test_nimic( $hook, $valoare = null ) {
	return $valoare;
}

require __DIR__ . '/../plugin/includes/configurare.php';
require __DIR__ . '/../plugin/includes/potrivire.php';

// Din `api.php` ne trebuie doar regula ferestrei, dar fișierul se încarcă întreg; funcțiile lui care cheamă rețeaua
// nu se apelează aici.
define( 'MINUTE_IN_SECONDS', 60 );
require __DIR__ . '/../plugin/includes/api.php';

/**
 * Varianta minimă a lui `sanitize_title()`: doar cât folosește `ccs_import_nume_fisier()`.
 *
 * @param string $text Textul.
 * @return string Slug-ul.
 */
function sanitize_title( $text ) {
	$text = mb_strtolower( (string) $text, 'UTF-8' );
	$text = strtr(
		$text,
		array( 'ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ț' => 't' )
	);
	$text = preg_replace( '/[^a-z0-9]+/u', '-', $text );
	return trim( (string) $text, '-' );
}

/**
 * `wp_parse_url()` din WordPress e `parse_url()` cu câteva corecturi care nu ne privesc aici.
 *
 * @param string $url       URL-ul.
 * @param int    $componenta Componenta cerută.
 * @return mixed Componenta.
 */
function wp_parse_url( $url, $componenta = -1 ) {
	return parse_url( $url, $componenta ); // phpcs:ignore WordPress.WP.AlternativeFunctions
}

require __DIR__ . '/../plugin/includes/import.php';

/* ------------------------------------------------------------------------------------------------ ajutoare */

$GLOBALS['ccs_trecute'] = 0;
$GLOBALS['ccs_cazute']  = array();

/**
 * Compară două valori și ține socoteala.
 *
 * @param string $ce       Ce se verifică.
 * @param mixed  $asteptat Valoarea așteptată.
 * @param mixed  $primit   Valoarea primită.
 * @return void
 */
function verifica( $ce, $asteptat, $primit ) {
	$la_fel = is_float( $asteptat ) || is_float( $primit )
		? abs( (float) $asteptat - (float) $primit ) < 0.0001
		: $asteptat === $primit;

	if ( $la_fel ) {
		$GLOBALS['ccs_trecute']++;
		return;
	}
	$GLOBALS['ccs_cazute'][] = sprintf(
		"%s\n      așteptat: %s\n      primit:   %s",
		$ce,
		var_export( $asteptat, true ),
		var_export( $primit, true )
	);
}

/**
 * Verifică o condiție.
 *
 * @param string $ce        Ce se verifică.
 * @param bool   $conditie  Condiția.
 * @param string $detaliu   Ce se afișează dacă pică.
 * @return void
 */
function verifica_ca( $ce, $conditie, $detaliu = '' ) {
	if ( $conditie ) {
		$GLOBALS['ccs_trecute']++;
		return;
	}
	$GLOBALS['ccs_cazute'][] = $ce . ( $detaliu ? "\n      " . $detaliu : '' );
}

/**
 * Pune setările folosite de `ccs_pret()`.
 *
 * @param array $schimbari Ce se schimbă față de valorile implicite.
 * @return void
 */
function setari( $schimbari = array() ) {
	$GLOBALS['ccs_setari_test'] = $schimbari;
}

/**
 * Un titlu de secțiune.
 *
 * @param string $text Textul.
 * @return void
 */
function sectiune( $text ) {
	echo "\n" . $text . "\n";
}

/* ------------------------------------------------------------------------------------------------ prețul */

sectiune( 'ccs_pret(), cu setările implicite' );
setari();

// Cazul care a dus la existența marjei minime. Un clips de 22 de bani: adaosul de 35% singur ar da 36 de bani preț,
// adică mai puțin decât costă ambalarea comenzii. Marja minimă îl urcă la 5 lei.
verifica( 'clips de 0,22 lei, marja minimă intră în joc', 5.0, ccs_pret( 0.22 ) );

// Produs normal, unde adaosul procentual e mai mare decât marja minimă: 35% curat.
verifica( 'produs de 41,52 lei, adaosul domină', 68.0, ccs_pret( 41.52 ) );

// Clipsul de caroserie din cele 3 scăderi mari de preț găsite pe live.
verifica( 'clips de 1,36 lei', 6.0, ccs_pret( 1.36 ) );

// Punctul în care adaosul depășește marja minimă e în jur de 9,45 lei cost fără TVA. De o parte și de alta:
verifica( 'cost 9,00 lei, încă decide marja minimă', 15.0, ccs_pret( 9.0 ) );
verifica( 'cost 10,00 lei, deja decide adaosul', 17.0, ccs_pret( 10.0 ) );

// Niciodată preț zero sau negativ dintr-un cost stricat de la furnizor. Planul blochează produsul, dar funcția
// trebuie să răspundă curat și singură.
verifica( 'cost zero', 0.0, ccs_pret( 0 ) );
verifica( 'cost negativ', 0.0, ccs_pret( -5 ) );

sectiune( 'ccs_pret(), taxa verde' );

// Taxa verde se adaugă la cost înainte de adaos, nu după. La cele 978 de produse cu taxă din catalog, diferența
// dintre cele două variante e vizibilă.
verifica( 'cost 100 lei cu taxă verde 5 lei, taxa inclusă', 171.0, ccs_pret( 100, 5 ) );
setari( array( 'include_taxa_eco' => false ) );
verifica( 'aceleași, cu taxa exclusă', 164.0, ccs_pret( 100, 5 ) );

sectiune( 'ccs_pret(), setări schimbate' );

setari( array( 'marja_minima_lei' => 0 ) );
verifica( 'fără marjă minimă, clipsul de 0,22 lei cade la 1 leu', 1.0, ccs_pret( 0.22 ) );

setari( array( 'pret_minim_lei' => 10 ) );
verifica( 'prețul minim absolut bate și marja', 10.0, ccs_pret( 0.22 ) );

setari( array( 'rotunjire' => 'exact' ) );
verifica( 'rotunjire exactă, cu bani', 67.82, ccs_pret( 41.52 ) );

setari( array( 'cota_tva' => 1.19 ) );
verifica( 'cota veche de 19%, același cost', 67.0, ccs_pret( 41.52 ) );

setari( array( 'adaos' => 1.5 ) );
verifica( 'adaos de 50%', 76.0, ccs_pret( 41.52 ) );

/* ------------------------------------------------------------------------------------------------ numele */

setari();
$prag = ccs_setari()['prag_nume'];

sectiune( sprintf( 'ccs_nume_asemanare(), pragul e %.2f', $prag ) );

// Perechile de mai jos sunt reale, din cele 3.980 de legături de la Cez Auto. Cele bune trebuie să treacă pragul,
// cele greșite trebuie să cadă sub el. Între 33% și 67% nu cade nimic, măsurat pe tot magazinul.

$bune = array(
	array(
		'nume identice',
		'SET COVORASE AUTO CAUCIUC UMBRELLA PENTRU DACIA LOGAN',
		'SET COVORASE AUTO CAUCIUC UMBRELLA PENTRU DACIA LOGAN',
	),
	array(
		'prescurtare de marcă: VW în loc de VOLKSWAGEN',
		'SET HUSE SCAUN UMBRELLA PENTRU VOLKSWAGEN CRAFTER',
		'SET HUSE SCAUN UMBRELLA PENTRU VW CRAFTER',
	),
	array(
		'spațiu lipsă într-un cuvânt',
		'CLICHET ARTICULATSCURT CU 72 DINTI - 1/2',
		'CLICHET ARTICULAT SCURT CU 72 DINTI - 1/2',
	),
	array(
		'nume trunchiat la furnizor',
		'COVOR PROTECTIE PORTBAGAJ UMBRELLA PENTRU FORD FOCUS 2011-2018',
		'COVOR PROTECTIE PORTBAGAJ UMBRELLA PENTRU FORD FOCUS',
	),
);

$gresite = array(
	array(
		'cod 10000: pompă de aer contra clipsuri de tapiterie',
		'POMPA DE AER CU MANOMETRU ANALOGIC JBM',
		'CLIPS TAPITERIE PENTRU PENTRU FIAT/LANCIA/ALFA ROMEO SET 25 BUC',
	),
	array(
		'cod 25918: aspirator Kärcher contra ulei Liqui Moly',
		'ASPIRATOR MULTIFUNCTIONAL UMED-USCAT. 20 L - KARCHER',
		'LIQUI MOLY GL 75W-90- API GL4+- 1L',
	),
	array(
		'cod 10154: leviere contra tubulară, același brand',
		'SET 2 LEVIERE JBM',
		'TUBULARA 12 FETE 1/2` 17 MM JBM',
	),
	array(
		'cod 190429: cârlig magnetic contra tubulară, același brand',
		'CARLIG MAGNETIC DE 6CM JBM',
		'TUBULARA SCURTA XZN - 1/2\' 10 MM JBM',
	),
);

$cel_mai_slab_bun = 1.0;
foreach ( $bune as list( $ce, $a, $b ) ) {
	$scor             = ccs_nume_asemanare( $a, $b );
	$cel_mai_slab_bun = min( $cel_mai_slab_bun, $scor );
	verifica_ca(
		'potrivire bună: ' . $ce,
		$scor >= $prag,
		sprintf( 'scor %.2f, sub pragul de %.2f', $scor, $prag )
	);
}

$cel_mai_bun_gresit = 0.0;
foreach ( $gresite as list( $ce, $a, $b ) ) {
	$scor               = ccs_nume_asemanare( $a, $b );
	$cel_mai_bun_gresit = max( $cel_mai_bun_gresit, $scor );
	verifica_ca(
		'potrivire greșită: ' . $ce,
		$scor < $prag,
		sprintf( 'scor %.2f, peste pragul de %.2f', $scor, $prag )
	);
}

// Proprietatea care contează cel mai mult: între cele bune și cele greșite rămâne loc. Dacă distanța asta se
// strânge, pragul de 0,5 devine o alegere fragilă, chiar dacă fiecare caz în parte încă trece.
verifica_ca(
	'rămâne distanță între potrivirile bune și cele greșite',
	$cel_mai_slab_bun - $cel_mai_bun_gresit > 0.2,
	sprintf( 'cea mai slabă bună %.2f, cea mai bună greșită %.2f', $cel_mai_slab_bun, $cel_mai_bun_gresit )
);

sectiune( 'ccs_nume_asemanare(), cazuri la limită' );

// Un produs fără titlu nu trebuie blocat din cauza numelui: n-avem ce compara, deci nu ne opunem.
verifica( 'nume gol pe site', 1.0, ccs_nume_asemanare( '', 'ORICE PRODUS' ) );
verifica( 'nume gol la furnizor', 1.0, ccs_nume_asemanare( 'ORICE PRODUS', '' ) );

// Numai cuvinte de cel mult două litere: tot gol, după filtrare.
verifica( 'nume din cuvinte prea scurte', 1.0, ccs_nume_asemanare( 'SE DE LA', 'ALT PRODUS' ) );

// Entitățile HTML din titlurile WordPress nu trebuie să rupă cuvinte. `&#8211;` e o liniuță, nu cuvântul „8211”.
verifica(
	'entitate HTML în titlu, ignorată',
	1.0,
	ccs_nume_asemanare( 'ASPIRATOR DE GEAMURI 105 M2 &#8211; KARCHER', 'ASPIRATOR DE GEAMURI 105 M2 KARCHER' )
);
verifica_ca(
	'entitatea nu adaugă un cuvânt',
	! in_array( '8211', ccs_nume_cuvinte( 'FILTRU &#8211; ULEI' ), true ),
	implode( ' ', ccs_nume_cuvinte( 'FILTRU &#8211; ULEI' ) )
);

// Majusculele și punctuația nu contează.
verifica( 'litere mici și punctuație în plus', 1.0, ccs_nume_asemanare( 'filtru ulei, motor', 'FILTRU ULEI - MOTOR' ) );

// Raportarea se face la numele mai scurt, nu la reuniune: numele furnizorului e adesea mai lung, cu capacități și
// coduri în plus, iar un Jaccard clasic ar penaliza potriviri bune.
verifica(
	'numele mai lung nu penalizează',
	1.0,
	ccs_nume_asemanare( 'FILTRU ULEI', 'FILTRU ULEI MANN W712 PENTRU VOLKSWAGEN GOLF' )
);

/* ------------------------------------------------------------------------------------------------ fereastra */

sectiune( 'ccs_api_in_fereastra(), 19:00-05:00 după ora furnizorului' );

// Fereastra trece peste miezul nopții, deci condiția nu e un interval obișnuit. O greșeală aici se vede abia la 3
// dimineața, când jobul de noapte refuză să pornească sau pornește când API-ul e închis.
foreach ( array( 19, 20, 23, 0, 3, 4 ) as $ora ) {
	verifica_ca( sprintf( 'ora %02d:00 e în fereastră', $ora ), ccs_api_in_fereastra( $ora ) );
}
foreach ( array( 5, 6, 12, 18 ) as $ora ) {
	verifica_ca( sprintf( 'ora %02d:00 e în afara ferestrei', $ora ), ! ccs_api_in_fereastra( $ora ) );
}

// Marginile, scrise explicit: 19 intră, 5 nu.
verifica( 'exact 19:00 intră', true, ccs_api_in_fereastra( 19 ) );
verifica( 'exact 05:00 nu mai intră', false, ccs_api_in_fereastra( 5 ) );

/* ------------------------------------------------------------------------------------------- nume de fișier */

/*
 * Pozele sunt singurul conținut pe care îl primim de la Conex: descrieri nu trimite. Numele fișierului e deci tot
 * ce are produsul nou pentru Google Images, iar furnizorul le dă nume de UUID. Cazurile de mai jos sunt luate din
 * catalogul real.
 */

sectiune( 'ccs_import_nume_fisier(), numele pozelor importate' );

verifica(
	'prima poză: denumire plus cod',
	'laveta-piele-sintetica-chamois-43x32cm-07222.jpg',
	ccs_import_nume_fisier( 'LAVETA PIELE SINTETICA CHAMOIS 43x32CM', '07222', 0, 'https://x.ro/4e668598-bda0-4478.jpg' )
);

verifica(
	'a doua poză primește număr, nu suprascrie prima',
	'set-bare-portbagaj-aluminiu-brio-120cm-07775-2.jpg',
	ccs_import_nume_fisier( 'SET BARE PORTBAGAJ ALUMINIU BRIO 120CM', '07775', 1, 'https://x.ro/a.jpg' )
);

verifica(
	'diacriticele nu ajung în numele fișierului',
	'stergatoare-parbriz-cz1.jpg',
	ccs_import_nume_fisier( 'Ștergătoare parbriz', 'CZ1', 0, 'https://x.ro/a.jpg' )
);

// O denumire de 120 de caractere ar da un fișier pe care unele sisteme de fișiere îl taie, iar WordPress adaugă
// peste el și sufixele de dimensiune („-1000x1000”). Tăiem la 60 înainte, nu după.
verifica_ca(
	'denumirile lungi se taie la 60 de caractere plus cod',
	strlen( ccs_import_nume_fisier( str_repeat( 'ABCDEFGHIJ', 12 ), '99999', 0, 'https://x.ro/a.jpg' ) ) <= 72,
	ccs_import_nume_fisier( str_repeat( 'ABCDEFGHIJ', 12 ), '99999', 0, 'https://x.ro/a.jpg' )
);

verifica(
	'extensia vine din URL când e una așteptată',
	'spray-cu-silicon-22362.png',
	ccs_import_nume_fisier( 'SPRAY CU SILICON', '22362', 0, 'https://x.ro/poza.PNG' )
);

// Un URL fără extensie, sau cu una inventată, ar produce un fișier pe care WordPress îl respinge la încărcare.
verifica(
	'URL fără extensie: presupunem jpg',
	'spray-cu-silicon-22362.jpg',
	ccs_import_nume_fisier( 'SPRAY CU SILICON', '22362', 0, 'https://x.ro/imagine?id=4' )
);

verifica(
	'extensie neașteptată: tot jpg',
	'spray-cu-silicon-22362.jpg',
	ccs_import_nume_fisier( 'SPRAY CU SILICON', '22362', 0, 'https://x.ro/poza.exe' )
);

// Fără plasa asta, un produs cu denumire goală ar încerca să salveze un fișier numit „.jpg”.
verifica(
	'fără denumire și fără cod, rămâne id-ul produsului',
	'produs-4312.jpg',
	ccs_import_nume_fisier( '', '', 0, 'https://x.ro/a.jpg', 4312 )
);

verifica(
	'fără denumire, dar cu cod',
	'07222.jpg',
	ccs_import_nume_fisier( '', '07222', 0, 'https://x.ro/a.jpg', 4312 )
);

/* ------------------------------------------------------------------------------------------------ rezultatul */

echo "\n";
if ( $GLOBALS['ccs_cazute'] ) {
	printf( "%d trecute, %d căzute:\n\n", $GLOBALS['ccs_trecute'], count( $GLOBALS['ccs_cazute'] ) );
	foreach ( $GLOBALS['ccs_cazute'] as $c ) {
		echo '  x ' . $c . "\n";
	}
	echo "\n";
	exit( 1 );
}

printf( "Toate cele %d verificări trec.\n", $GLOBALS['ccs_trecute'] );
exit( 0 );
