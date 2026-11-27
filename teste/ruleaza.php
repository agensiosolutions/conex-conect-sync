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
 * Înregistrarea hook-urilor, fără efect.
 *
 * Fișierele testate își înregistrează joburile și filtrele la încărcare. Aici nu rulează niciun hook, dar
 * funcțiile trebuie să existe, altfel `require` cade cu eroare fatală înainte să înceapă vreun test.
 *
 * @param string   $hook     Numele.
 * @param callable $functie  Funcția.
 * @param int      $ordine   Prioritatea.
 * @param int      $cate     Câte argumente.
 * @return bool Mereu adevărat, ca în WordPress.
 */
function add_action( $hook, $functie, $ordine = 10, $cate = 1 ) {
	return true;
}

/**
 * La fel ca `add_action()`, pentru filtre.
 *
 * @param string   $hook    Numele.
 * @param callable $functie Funcția.
 * @param int      $ordine  Prioritatea.
 * @param int      $cate    Câte argumente.
 * @return bool Mereu adevărat.
 */
function add_filter( $hook, $functie, $ordine = 10, $cate = 1 ) {
	return true;
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

/**
 * Pluralul, varianta minimă: engleza are două forme, româna trei, dar `ccs_durata()` alege singură forma cu
 * „de”, deci aici ajunge regula simplă.
 *
 * @param string $unu   Forma de singular.
 * @param string $multe Forma de plural.
 * @param int    $cate  Numărul.
 * @return string Forma potrivită.
 */
function _n( $unu, $multe, $cate ) {
	return 1 === (int) $cate ? $unu : $multe;
}

/**
 * Traducerea, fără niciun fișier de limbă: textele sunt deja scrise în română în cod.
 *
 * @param string $text Textul.
 * @return string Textul, neatins.
 */
function __( $text ) {
	return $text;
}

defined( 'HOUR_IN_SECONDS' ) || define( 'HOUR_IN_SECONDS', 3600 );
defined( 'DAY_IN_SECONDS' ) || define( 'DAY_IN_SECONDS', 86400 );

/**
 * Tranzientele, într-un tablou. Lacătul e singurul lucru testat aici care le folosește.
 *
 * @param string $cheie Cheia.
 * @return mixed Valoarea, sau false.
 */
function get_transient( $cheie ) {
	return $GLOBALS['ccs_tranziente'][ $cheie ] ?? false;
}

/**
 * Scrie un tranzient.
 *
 * @param string $cheie    Cheia.
 * @param mixed  $valoare  Valoarea.
 * @param int    $cat_tine Cât ține, ignorat aici.
 * @return bool
 */
function set_transient( $cheie, $valoare, $cat_tine = 0 ) {
	$GLOBALS['ccs_tranziente'][ $cheie ] = $valoare;
	return true;
}

/**
 * Șterge un tranzient.
 *
 * @param string $cheie Cheia.
 * @return bool
 */
function delete_transient( $cheie ) {
	unset( $GLOBALS['ccs_tranziente'][ $cheie ] );
	return true;
}

$GLOBALS['ccs_tranziente'] = array();

/**
 * Minimul din `WP_Error` folosit de lacăt.
 */
class WP_Error {

	/**
	 * Codul erorii.
	 *
	 * @var string
	 */
	public $code;

	/**
	 * Mesajul.
	 *
	 * @var string
	 */
	public $message;

	/**
	 * Construiește eroarea.
	 *
	 * @param string $code    Codul.
	 * @param string $message Mesajul.
	 */
	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}
}

/**
 * Varianta minimă a lui `is_wp_error()`.
 *
 * @param mixed $ceva Ce verificăm.
 * @return bool
 */
function is_wp_error( $ceva ) {
	return $ceva instanceof WP_Error;
}

/**
 * Cârligul de dezactivare, fără efect.
 *
 * @param string   $fisier Fișierul pluginului.
 * @param callable $apel   Ce s-ar chema.
 * @return void
 */
function register_deactivation_hook( $fisier, $apel ) {}

define( 'CCS_FISIER', __DIR__ . '/../plugin/conex-conect-sync.php' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

require __DIR__ . '/../plugin/includes/joburi.php';

require __DIR__ . '/../plugin/includes/admin-ui.php';

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

/* ------------------------------------------------------------------------------------------------ excepții */

sectiune( 'ccs_pret(), excepții pe brand și pe categorie' );

/*
 * Excepțiile sunt singurul loc din plugin unde două produse cu același cost ies cu prețuri diferite. O greșeală
 * aici se vede ca un preț aiurea pe o întreagă categorie, nu pe un produs, deci merită verificate și cazurile
 * care nu se potrivesc, nu doar cele care se potrivesc.
 */

setari(
	array(
		'adaos'            => 1.35,
		'cota_tva'         => 1.21,
		'marja_minima_lei' => 4.0,
		'rotunjire'        => 'sus',
		'include_taxa_eco' => false,
		'exceptii_pret'    => array(
			array(
				'tip'     => 'brand',
				'valoare' => 'MANNOL',
				'adaos'   => 1.5,
			),
			array(
				'tip'              => 'categorie',
				'valoare'          => 'Ulei motor',
				'adaos'            => 1.2,
				'marja_minima_lei' => 10.0,
			),
		),
	)
);

// 100 lei cost, TVA 21% = 121. Adaos general 1,35 ar da 164; excepția de brand 1,5 dă 182.
verifica( 'fără context, se aplică regula generală', 164.0, ccs_pret( 100 ) );
verifica( 'brandul are excepție', 182.0, ccs_pret( 100, 0, array( 'brand' => 'MANNOL' ) ) );
verifica( 'alt brand, regula generală', 164.0, ccs_pret( 100, 0, array( 'brand' => 'MOTUL' ) ) );

// Literele mari nu contează: omul scrie regula uitându-se la ce vede pe ecran.
verifica( 'brandul se potrivește indiferent de litere', 182.0, ccs_pret( 100, 0, array( 'brand' => 'mannol' ) ) );
verifica( 'spațiile din jur nu încurcă', 182.0, ccs_pret( 100, 0, array( 'brand' => '  MANNOL ' ) ) );

// Categoria se potrivește pe oricare dintre cele trei niveluri ale căii.
verifica( 'categoria prinde pe nivelul 1', 146.0, ccs_pret( 100, 0, array( 'cat1' => 'Ulei motor' ) ) );
verifica( 'categoria prinde pe nivelul 3', 146.0, ccs_pret( 100, 0, array( 'cat3' => 'Ulei motor' ) ) );
verifica( 'o categorie fără excepție lasă regula generală', 164.0, ccs_pret( 100, 0, array( 'cat1' => 'Becuri' ) ) );

// Prima regulă care se potrivește câștigă. Aici brandul e înaintea categoriei în listă, deci un ulei MANNOL
// primește adaosul de brand, nu pe cel de categorie.
verifica(
	'prima regulă din listă câștigă',
	182.0,
	ccs_pret( 100, 0, array( 'brand' => 'MANNOL', 'cat1' => 'Ulei motor' ) )
);

// Tipul regulii chiar contează: o regulă de categorie nu are voie să prindă un produs doar fiindcă brandul lui
// se cheamă la fel, și invers. Fără verificările astea două, o regulă pusă pe „Ulei motor” ar fi schimbat prețul
// și la un brand cu același nume.
setari(
	array(
		'exceptii_pret' => array(
			array(
				'tip'     => 'categorie',
				'valoare' => 'MANNOL',
				'adaos'   => 3.0,
			),
		),
	)
);
verifica(
	'o regulă de categorie nu prinde un brand cu același nume',
	164.0,
	ccs_pret( 100, 0, array( 'brand' => 'MANNOL' ) )
);

setari(
	array(
		'exceptii_pret' => array(
			array(
				'tip'     => 'brand',
				'valoare' => 'Ulei motor',
				'adaos'   => 3.0,
			),
		),
	)
);
verifica(
	'o regulă de brand nu prinde o categorie cu același nume',
	164.0,
	ccs_pret( 100, 0, array( 'cat1' => 'Ulei motor' ) )
);

setari(
	array(
		'exceptii_pret' => array(
			array(
				'tip'     => 'brand',
				'valoare' => 'MANNOL',
				'adaos'   => 1.5,
			),
			array(
				'tip'              => 'categorie',
				'valoare'          => 'Ulei motor',
				'adaos'            => 1.2,
				'marja_minima_lei' => 10.0,
			),
		),
	)
);

// Marja minimă din excepție se aplică acolo unde bate adaosul: cost 1 leu, TVA 21% = 1,21; adaosul de 1,2 dă
// 1,45, dar marja minimă de 10 lei din excepție dă 11,21, rotunjit în sus 12.
verifica( 'marja minimă din excepție intră în joc', 12.0, ccs_pret( 1, 0, array( 'cat1' => 'Ulei motor' ) ) );

// O excepție cu valoarea goală nu trebuie să prindă tot. Fără verificarea asta, un rând lăsat necompletat în
// panou ar schimba prețul la întreg magazinul.
setari(
	array(
		'exceptii_pret' => array(
			array(
				'tip'     => 'brand',
				'valoare' => '',
				'adaos'   => 3.0,
			),
		),
	)
);
verifica( 'o excepție necompletată nu prinde nimic', 164.0, ccs_pret( 100, 0, array( 'brand' => 'MANNOL' ) ) );
verifica( 'o excepție necompletată nu prinde nici produsele fără brand', 164.0, ccs_pret( 100, 0, array( 'brand' => '' ) ) );

// O excepție poate schimba și cota de TVA, fiindcă există produse cu cotă diferită.
setari(
	array(
		'exceptii_pret' => array(
			array(
				'tip'      => 'categorie',
				'valoare'  => 'Carti tehnice',
				'cota_tva' => 1.05,
			),
		),
	)
);
verifica( 'excepția poate schimba cota de TVA', 142.0, ccs_pret( 100, 0, array( 'cat2' => 'Carti tehnice' ) ) );

setari( array( 'exceptii_pret' => array() ) );

/* ------------------------------------------------------------------------------------------------ durata */

sectiune( 'ccs_durata(), în română, cu regula lui „de”' );

/*
 * `human_time_diff()` din WordPress urmează limba adminului, iar pe cezauto.ro adminul e în engleză, deci scria
 * „acum 23 hours” într-un panou în rest românesc. Funcția noastră scrie singură durata, iar partea pe care o
 * greșește oricine o scrie prima dată e „de”: româna îl cere de la 20 în sus.
 */

verifica( 'sub un minut', '5 secunde', ccs_durata( 5 ) );
verifica( 'exact un minut', '1 minut', ccs_durata( 60 ) );
verifica( 'se rotunjește în jos', '1 minut', ccs_durata( 119 ) );
verifica( 'minute, fără „de”', '19 minute', ccs_durata( 19 * 60 ) );
verifica( 'minute, cu „de” de la 20', '20 de minute', ccs_durata( 20 * 60 ) );
verifica( 'o oră', '1 oră', ccs_durata( 3600 ) );
verifica( 'ore, fără „de”', '3 ore', ccs_durata( 3 * 3600 ) );
verifica( 'ore, cu „de” de la 20', '23 de ore', ccs_durata( 23 * 3600 ) );
verifica( 'trecerea la zile', '1 zi', ccs_durata( 86400 ) );
verifica( 'zile, fără „de”', '2 zile', ccs_durata( 2 * 86400 ) );
verifica( 'zile, cu „de” de la 20', '23 de zile', ccs_durata( 23 * 86400 ) );
verifica( 'trecerea la luni', '2 luni', ccs_durata( 60 * 86400 ) );
// O durată negativă nu are sens, dar un ceas nepotrivit între server și baza de date o poate produce.
verifica( 'durată negativă, nu număr negativ pe ecran', '0 secunde', ccs_durata( -500 ) );

/* ------------------------------------------------------------------------------------------- nume de fișier */

/*
 * Pozele sunt singurul conținut pe care îl primim de la Conex: descrieri nu trimite. Numele fișierului e deci tot
 * ce are produsul nou pentru Google Images, iar furnizorul le dă nume de UUID. Cazurile de mai jos sunt luate din
 * catalogul real.
 */

sectiune( 'ccs_import_nume_fisier(), numele pozelor importate' );

verifica(
	'prima poză: marcă, denumire, cod de producător',
	'philips-bec-proiector-h3-12v-vision-12336prb1.jpg',
	ccs_import_nume_fisier(
		array( 'nume' => 'BEC PROIECTOR H3 12V VISION', 'brand' => 'PHILIPS', 'mpn' => '12336PRB1', 'sku' => '78123' ),
		0,
		'https://x.ro/4e668598-bda0-4478.jpg'
	)
);

// Codul intern al furnizorului nu spune nimic nimănui din afara lui. La 82% dintre produsele Conex e diferit
// de codul de producător, iar ăla e cel pe care îl caută oamenii.
verifica(
	'codul de producător bate codul intern al furnizorului',
	'meyle-pompa-spalator-parbriz-014-870-0003.jpg',
	ccs_import_nume_fisier(
		array( 'nume' => 'POMPA SPALATOR PARBRIZ', 'brand' => 'MEYLE', 'mpn' => '014 870 0003', 'sku' => '111822' ),
		0,
		'https://x.ro/a.jpg'
	)
);

verifica(
	'fără cod de producător, cade pe SKU',
	'umbrella-covor-portbagaj-119106.jpg',
	ccs_import_nume_fisier(
		array( 'nume' => 'COVOR PORTBAGAJ', 'brand' => 'UMBRELLA', 'mpn' => '', 'sku' => '119106' ),
		0,
		'https://x.ro/a.jpg'
	)
);

verifica(
	'a doua poză primește număr, nu suprascrie prima',
	'menabo-set-bare-portbagaj-aluminiu-brio-120cm-07775-2.jpg',
	ccs_import_nume_fisier(
		array( 'nume' => 'SET BARE PORTBAGAJ ALUMINIU BRIO 120CM', 'brand' => 'MENABO', 'mpn' => '07775', 'sku' => '07775' ),
		1,
		'https://x.ro/a.jpg'
	)
);

verifica(
	'diacriticele nu ajung în numele fișierului',
	'bosch-stergatoare-parbriz-cz1.jpg',
	ccs_import_nume_fisier(
		array( 'nume' => 'Ștergătoare parbriz', 'brand' => 'BOSCH', 'mpn' => 'CZ1' ),
		0,
		'https://x.ro/a.jpg'
	)
);

// O denumire lungă ar da un fișier pe care unele sisteme de fișiere îl taie, iar WordPress mai adaugă peste el
// sufixele de dimensiune („-1000x1000”). Tăiem înainte, nu după.
verifica_ca(
	'denumirile lungi se taie',
	strlen(
		ccs_import_nume_fisier(
			array( 'nume' => str_repeat( 'ABCDEFGHIJ ', 12 ), 'brand' => 'MANNOL', 'mpn' => '99999' ),
			0,
			'https://x.ro/a.jpg'
		)
	) <= 96,
	ccs_import_nume_fisier(
		array( 'nume' => str_repeat( 'ABCDEFGHIJ ', 12 ), 'brand' => 'MANNOL', 'mpn' => '99999' ),
		0,
		'https://x.ro/a.jpg'
	)
);

// `substr()` pe un slug taie in mijlocul cuvantului si lasa o cratima la capat. Numele se citeste de om.
verifica(
	'tăierea cade la ultimul cuvânt întreg, nu în mijlocul lui',
	'banda-multifunctionala-pentru-reparatii-si',
	ccs_slug_scurt( 'BANDA MULTIFUNCTIONALA PENTRU REPARATII SI IZOLARI 5ML', 45 )
);

verifica(
	'un singur cuvânt mai lung decât limita se taie oricum',
	'abcdefghij',
	ccs_slug_scurt( 'ABCDEFGHIJKLMNOP', 10 )
);

verifica(
	'extensia vine din URL când e una așteptată',
	'mannol-spray-cu-silicon-22362.png',
	ccs_import_nume_fisier( array( 'nume' => 'SPRAY CU SILICON', 'brand' => 'MANNOL', 'mpn' => '22362' ), 0, 'https://x.ro/poza.PNG' )
);

// Un URL fără extensie, sau cu una inventată, ar produce un fișier pe care WordPress îl respinge la încărcare.
verifica(
	'URL fără extensie: presupunem jpg',
	'mannol-spray-cu-silicon-22362.jpg',
	ccs_import_nume_fisier( array( 'nume' => 'SPRAY CU SILICON', 'brand' => 'MANNOL', 'mpn' => '22362' ), 0, 'https://x.ro/imagine?id=4' )
);

verifica(
	'extensie neașteptată: tot jpg',
	'mannol-spray-cu-silicon-22362.jpg',
	ccs_import_nume_fisier( array( 'nume' => 'SPRAY CU SILICON', 'brand' => 'MANNOL', 'mpn' => '22362' ), 0, 'https://x.ro/poza.exe' )
);

// După conversia în WebP, numele trebuie să poarte extensia nouă, nu pe cea din URL-ul furnizorului. Fără asta,
// WordPress ar primi un fișier WebP numit `.jpg` și l-ar servi cu tipul greșit.
verifica(
	'extensia forțată bate extensia din URL',
	'mannol-spray-cu-silicon-22362.webp',
	ccs_import_nume_fisier( array( 'nume' => 'SPRAY CU SILICON', 'brand' => 'MANNOL', 'mpn' => '22362' ), 0, 'https://x.ro/poza.jpg', 'webp' )
);

verifica(
	'extensia forțată se aplică și pozelor numerotate',
	'mannol-spray-cu-silicon-22362-3.webp',
	ccs_import_nume_fisier( array( 'nume' => 'SPRAY CU SILICON', 'brand' => 'MANNOL', 'mpn' => '22362' ), 2, 'https://x.ro/poza.jpg', 'WEBP' )
);

// Fără plasa asta, un produs fără nicio dată ar încerca să salveze un fișier numit „.jpg”.
verifica(
	'fără nimic, rămâne id-ul produsului',
	'produs-4312.jpg',
	ccs_import_nume_fisier( array( 'wc_id' => 4312 ), 0, 'https://x.ro/a.jpg' )
);

verifica(
	'fără denumire, dar cu marcă și cod',
	'mannol-07222.jpg',
	ccs_import_nume_fisier( array( 'brand' => 'MANNOL', 'mpn' => '07222' ), 0, 'https://x.ro/a.jpg' )
);

/* ---------------------------------------------------------------------------------------- lacătul de scriere */

/*
 * Lacătul merită teste tocmai pentru că reintranța nu se vede din citit. Greșit într-un fel, jobul de catalog
 * își refuză singur propria aplicare și nu se mai scrie nimic niciodată. Greșit în celălalt, prima eliberare
 * deschide ușa în mijlocul lucrului și două rulări scriu una peste alta, exact ce trebuia să împiedice.
 */

$GLOBALS['ccs_tranziente'] = array();

verifica( 'la început, adâncimea e zero', 0, ccs_lacat_adancime() );

$prima = ccs_lacat_ia( 'prima operație' );
verifica_ca( 'prima luare reușește', true === $prima );
verifica( 'adâncimea urcă la unu', 1, ccs_lacat_adancime() );

$a_doua = ccs_lacat_ia( 'din interiorul primei' );
verifica_ca( 'același proces îl poate lua din nou', true === $a_doua );
verifica( 'adâncimea urcă la doi', 2, ccs_lacat_adancime() );

ccs_lacat_lasa();
verifica( 'eliberarea interioară coboară adâncimea', 1, ccs_lacat_adancime() );
verifica_ca(
	'eliberarea interioară nu deschide lacătul',
	false !== get_transient( CCS_LACAT ),
	'lacătul s-a deschis în mijlocul lucrului'
);

ccs_lacat_lasa();
verifica( 'eliberarea exterioară coboară la zero', 0, ccs_lacat_adancime() );
verifica_ca(
	'eliberarea exterioară deschide lacătul',
	false === get_transient( CCS_LACAT ),
	'lacătul a rămas închis după ce toți l-au lăsat'
);

// Altcineva îl ține: cererea trebuie refuzată, cu un mesaj care spune ce rulează.
set_transient( CCS_LACAT, array( 'ce' => 'jobul de noapte', 'de_la' => 1 ), 60 );
$respins = ccs_lacat_ia( 'o comandă dată de om' );
verifica_ca( 'un alt proces e refuzat', is_wp_error( $respins ) );
verifica_ca(
	'refuzul spune ce rulează deja',
	is_wp_error( $respins ) && false !== strpos( $respins->message, 'jobul de noapte' ),
	is_wp_error( $respins ) ? $respins->message : 'n-a fost refuzat deloc'
);
verifica( 'un refuz nu urcă adâncimea', 0, ccs_lacat_adancime() );

// Eliberări în plus nu trebuie să ducă adâncimea sub zero: altfel următoarea luare ar crede că deja îl avem.
ccs_lacat_lasa();
ccs_lacat_lasa();
verifica( 'adâncimea nu coboară sub zero', 0, ccs_lacat_adancime() );

$GLOBALS['ccs_tranziente'] = array();
$dupa = ccs_lacat_ia( 'după eliberări în plus' );
verifica_ca( 'lacătul se mai poate lua după eliberări în plus', true === $dupa );
ccs_lacat_lasa();

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
