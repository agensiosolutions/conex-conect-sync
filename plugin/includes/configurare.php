<?php
/**
 * Setările pluginului.
 *
 * Ordinea, ca la agensio-consent: valorile implicite, apoi opțiunea din admin, apoi filtrul `ccs_setari`.
 * Tokenul poate veni și din constanta `CCS_API_TOKEN` din wp-config, care bate setarea (vezi `ccs_api_token()`).
 *
 * @package conex-conect-sync
 */

defined( 'ABSPATH' ) || exit;

const CCS_OPTIUNE_SETARI = 'ccs_setari';

/**
 * Valorile implicite.
 *
 * Regula de preț vine din măsurătorile pe Cez Auto: adaosul singur, în procente, se comportă prost la produsele
 * ieftine. Un clips de 0,22 lei ajunge la 1 leu, adică +359% adaos, dar 0,78 lei marjă brută, sub costul de
 * procesare a comenzii. De aceea `marja_minima_lei`: pe cele 2.493 de produse măsurate rezolvă toate cele 255 de
 * cazuri problematice și costă 543 de lei în total, adică 0,13%.
 *
 * @return array Valorile implicite.
 */
function ccs_setari_implicite() {
	return array(
		'token'              => '',
		'adaos'              => 1.35,
		'cota_tva'           => 1.21,
		'marja_minima_lei'   => 4.0,
		'pret_minim_lei'     => 0.0,
		'rotunjire'          => 'sus',   // 'sus' = ceil, cum sunt prețurile de azi pe cezauto.ro.
		'include_taxa_eco'   => true,
		'stoc_absent_runde'  => 2,       // De câte ori la rând lipsește un produs din feed înainte să treacă pe stoc 0.
		'poze_per_produs'    => 4,

		// Când devine un atribut tehnic filtru în magazin. Vezi `atribute.php` pentru de ce raportul și nu un prag
		// fix pe numărul de valori: un prag fix respingea „Partea de montare”, care are 125 de valori dar pe 2.874
		// de produse, adică 4%, și e exact genul de filtru pe care îl caută cumpărătorul.
		// Produsele care își primesc fotografia mai târziu urcă singure, dacă e pornit. Implicit e oprit: un plugin
		// care începe să creeze produse fără ca cineva să fi apăsat ceva e un plugin în care nu ai încredere a doua
		// oară. Plafonul e pe rulare, ca o zi în care furnizorul adaugă cinci mii de poze să nu umple magazinul
		// într-o noapte.
		// Emailul. Există fiindcă feed-ul Mega Drive s-a oprit în noiembrie 2025 și nimeni n-a aflat până în
		// octombrie 2026. Alertele sunt pornite implicit, rezumatul zilnic la fel; adresa goală înseamnă adresa
		// administratorului site-ului.
		'email_catre'           => '',
		'email_alerte'          => true,
		'email_rezumat'         => true,
		'alerta_pauza_ore'      => 12,    // Cât timp nu se repetă aceeași alertă.
		'ore_fara_sincronizare' => 6,     // După câte ore fără o preluare reușită se dă alarma.

		'import_automat'        => false,
		'import_automat_limita' => 50,

		'atribut_min_produse'   => 200,
		'atribut_max_raport'    => 0.15,
		'atribut_plafon_valori' => 150,

		// Pozele de la furnizor vin JPEG de 1000x1000. Se convertesc în WebP la import, fiindcă după import nu
		// se mai convertește nimeni. 90 e peste implicita WordPress, care e 82: la poze de produs, unde omul
		// mărește ca să vadă cusătura husei sau filetul șurubului, artefactele costă mai mult decât kilobaiții
		// economisiți. Zero oprește conversia.
		'webp_calitate'      => 90,
		'mod_raport'         => true,    // Pornit: nu scrie nimic în WooCommerce, doar raportează.

		// Două frâne pentru scriere, fiecare pentru alt fel de accident.
		// `scadere_pret_maxima`: cât poate scădea prețul unui produs într-o singură rulare, ca fracție din prețul de
		// acum. Sub pragul ăsta nu scriem, raportăm. Un preț care se face jumătate peste noapte e, în practică, un
		// cost greșit la furnizor, nu o reducere.
		// `prag_schimbari`: ce fracție din produsele legate poate atinge o rulare fără confirmare explicită. Prima
		// aplicare trece peste el o singură dată, cu `--forteaza`, după ce clientul a văzut raportul. Pe urmă, o
		// rulare care vrea să atingă un sfert din magazin e un semnal, nu o treabă de rutină.
		'scadere_pret_maxima' => 0.5,
		// Cât trebuie să semene numele de pe site cu numele de la furnizor ca potrivirea pe cod să fie de încredere.
		// Măsurat pe cele 3.980 de legături de la Cez Auto: tot ce trece de 67% e potrivire bună (nume trunchiate,
		// „VW” în loc de „VOLKSWAGEN”, alt interval de ani), tot ce stă sub 33% e alt produs. Între ele nu e nimic.
		'prag_nume'           => 0.5,
		'prag_schimbari'      => 0.25,
		'lot_aplicare'        => 200,    // Câte produse se scriu între două pauze de golire a cache-ului.

		// Excepții de la regula de preț, pentru un brand sau pentru o categorie a furnizorului. Fiecare e de forma
		// array( 'tip' => 'brand'|'categorie', 'valoare' => '...', plus câmpurile pe care le schimbă ).
		// Prima regulă care se potrivește câștigă, în ordinea din listă, ca omul să poată pune o excepție îngustă
		// înaintea uneia largi fără să inventăm un scor de specificitate pe care tot el ar trebui să-l ghicească.
		'exceptii_pret'       => array(),

		// Joburile sunt oprite până le pornește cineva. Un plugin care începe să scrie singur în magazin imediat ce
		// a fost activat e un plugin în care nu ai încredere a doua oară.
		// O regulă de categorie învățată dintr-un singur produs are 100% „încredere” fiindcă n-are cu ce se
		// contrazice. Sub pragurile astea, regula există în tabel dar nu se folosește la import: produsul intră în
		// lista de mapat de om. Regulile puse de om se aplică oricum, indiferent de praguri.
		'categorie_min_produse'   => 3,
		'categorie_min_incredere' => 60,

		'joburi_pornite'      => false,
		'interval_catalog'    => 30,     // Minute între două preluări de catalog.
	);
}

/**
 * Setările efective.
 *
 * @return array Setările.
 */
function ccs_setari() {
	$setari = wp_parse_args( (array) get_option( CCS_OPTIUNE_SETARI, array() ), ccs_setari_implicite() );

	/**
	 * Permite schimbarea setărilor din cod, pentru cazuri speciale.
	 *
	 * @param array $setari Setările.
	 */
	return apply_filters( 'ccs_setari', $setari );
}

/**
 * Prima excepție de preț care se potrivește cu produsul, sau null.
 *
 * Potrivirea pe categorie se face pe **categoria furnizorului**, nu pe cea din magazin. Motivul: regulile trebuie
 * să funcționeze la fel pentru un produs care e deja pe site și pentru unul care tocmai se importă, iar al doilea
 * n-are încă nicio categorie din magazin. Categoria furnizorului e singurul lucru pe care îl au amândouă.
 *
 * Se compară cu oricare dintre cele trei niveluri ale căii, fără să conteze literele mari, fiindcă omul care
 * scrie regula se uită la calea afișată în panou și nu știe pe ce nivel stă bucata pe care o vrea.
 *
 * @param array $context brand, cat1, cat2, cat3.
 * @return array|null Regula.
 */
function ccs_exceptie_pret( $context ) {
	$exceptii = ccs_setari()['exceptii_pret'];
	if ( ! is_array( $exceptii ) || ! $exceptii || ! is_array( $context ) || ! $context ) {
		return null;
	}

	$brand = mb_strtoupper( trim( (string) ( $context['brand'] ?? '' ) ) );
	$cai   = array();
	foreach ( array( 'cat1', 'cat2', 'cat3' ) as $nivel ) {
		$c = mb_strtoupper( trim( (string) ( $context[ $nivel ] ?? '' ) ) );
		if ( '' !== $c ) {
			$cai[] = $c;
		}
	}

	foreach ( $exceptii as $regula ) {
		$valoare = mb_strtoupper( trim( (string) ( $regula['valoare'] ?? '' ) ) );
		$tip     = (string) ( $regula['tip'] ?? '' );
		if ( '' === $valoare ) {
			continue;
		}
		if ( 'brand' === $tip && '' !== $brand && $brand === $valoare ) {
			return $regula;
		}
		if ( 'categorie' === $tip && in_array( $valoare, $cai, true ) ) {
			return $regula;
		}
	}

	return null;
}

/**
 * Setările de preț pentru un produs anume: cele generale, cu excepția lui pusă peste.
 *
 * @param array $context brand, cat1, cat2, cat3.
 * @return array Setările.
 */
function ccs_setari_pret( $context = array() ) {
	$setari = ccs_setari();
	$regula = ccs_exceptie_pret( $context );
	if ( ! $regula ) {
		return $setari;
	}

	// Doar câmpurile care au voie să difere. Cota de TVA e aici fiindcă există produse cu cotă diferită, dar
	// `include_taxa_eco` nu e: taxa verde ori se adaugă peste tot, ori nicăieri.
	foreach ( array( 'adaos', 'cota_tva', 'marja_minima_lei', 'pret_minim_lei', 'rotunjire' ) as $camp ) {
		if ( isset( $regula[ $camp ] ) && '' !== $regula[ $camp ] ) {
			$setari[ $camp ] = 'rotunjire' === $camp ? (string) $regula[ $camp ] : (float) $regula[ $camp ];
		}
	}

	return $setari;
}

/**
 * Un moment scris în baza noastră, ca timestamp.
 *
 * Toate datele pluginului se scriu cu `current_time( 'mysql' )`, adică în ora site-ului. La citire foloseam
 * `mysql2date( 'U', $x, false )`, care interpretează șirul ca UTC fiindcă PHP-ul serverului e pe UTC. Pe un
 * site românesc, asta adăuga două ore: ultima preluare de la 22:55 se afișa „28 noiembrie, 00:55”.
 *
 * Partea care nu se vedea era mai rea: aceeași conversie se folosea la „de câte ore n-a mai rulat
 * sincronizarea”, unde scădea vârsta cu două ore, deci alerta pleca cu două ore mai târziu decât ar fi
 * trebuit.
 *
 * `get_gmt_from_date()` citește șirul în fusul site-ului, care e exact cum a fost scris.
 *
 * @param string $mysql Data, în forma `Y-m-d H:i:s`, ora site-ului.
 * @return int Timestamp, sau 0.
 */
function ccs_ts( $mysql ) {
	$mysql = trim( (string) $mysql );
	if ( '' === $mysql ) {
		return 0;
	}
	return (int) get_gmt_from_date( $mysql, 'U' );
}

/**
 * Marginile fiecărei setări numerice.
 *
 * Panoul și linia de comandă scriau amândouă setările fără nicio margine. Un adaos pus din greșeală la 0,9 ar
 * fi scris prețuri sub cost pe tot magazinul, iar frâna de scădere, care se uită la cât coboară un preț față
 * de cel de pe site, n-ar fi prins-o: o scădere de 10% e cuminte, doar că aplicată peste tot.
 *
 * Marginile sunt largi intenționat. Nu încearcă să ghicească ce politică de preț e bună, ci doar să prindă
 * greșeala de tastare: o virgulă pusă aiurea, un zero în plus, un minus.
 *
 * @return array cheie => array( minim, maxim, explicație ).
 */
function ccs_setari_margini() {
	return array(
		'adaos'                   => array( 1.0, 10.0, __( 'Adaosul e un înmulțitor: 1,35 înseamnă +35%. Sub 1 ai vinde sub cost.', 'conex-conect-sync' ) ),
		'cota_tva'                => array( 1.0, 2.0, __( 'Cota de TVA e tot un înmulțitor: 1,21 înseamnă 21%.', 'conex-conect-sync' ) ),
		'marja_minima_lei'        => array( 0.0, 1000.0, '' ),
		'pret_minim_lei'          => array( 0.0, 100000.0, '' ),
		'scadere_pret_maxima'     => array( 0.0, 1.0, __( 'E o fracție din prețul de acum: 0,5 înseamnă „nu lăsa prețul să scadă sub jumătate”.', 'conex-conect-sync' ) ),
		'prag_schimbari'          => array( 0.0, 1.0, __( 'E o fracție din produsele legate: 0,25 înseamnă un sfert.', 'conex-conect-sync' ) ),
		'prag_nume'               => array( 0.0, 1.0, '' ),
		'webp_calitate'           => array( 1, 100, '' ),
		'poze_per_produs'         => array( 1, 20, '' ),
		'stoc_absent_runde'       => array( 1, 100, '' ),
		'interval_catalog'        => array( 5, 1440, __( 'Minute între două preluări. Sub cinci minute ai bate furnizorul degeaba.', 'conex-conect-sync' ) ),
		'import_automat_limita'   => array( 1, 1000, '' ),
		'alerta_pauza_ore'        => array( 1, 720, '' ),
		'ore_fara_sincronizare'   => array( 1, 720, '' ),
		'atribut_min_produse'     => array( 1, 100000, '' ),
		'atribut_max_raport'      => array( 0.0, 1.0, '' ),
		'atribut_plafon_valori'   => array( 1, 10000, '' ),
		'categorie_min_produse'   => array( 1, 10000, '' ),
		'categorie_min_incredere' => array( 0, 100, '' ),
		'lot_aplicare'            => array( 1, 5000, '' ),
	);
}

/**
 * Verifică o valoare de setare înainte să fie scrisă.
 *
 * Aceeași funcție pentru panou și pentru linia de comandă: două căi care scriu aceeași setare trebuie să o
 * verifice la fel, altfel margina pusă într-una se ocolește prin cealaltă.
 *
 * @param string $cheie   Setarea.
 * @param mixed  $valoare Valoarea propusă.
 * @return mixed|WP_Error Valoarea, sau eroare cu un mesaj pentru om.
 */
function ccs_setare_valideaza( $cheie, $valoare ) {
	$margini = ccs_setari_margini();
	if ( ! isset( $margini[ $cheie ] ) ) {
		return $valoare;
	}

	list( $minim, $maxim, $explicatie ) = $margini[ $cheie ];

	if ( ! is_numeric( $valoare ) ) {
		return new WP_Error(
			'ccs_setare_nenumerica',
			sprintf(
				/* translators: %s: numele setării. */
				__( '„%s” cere un număr.', 'conex-conect-sync' ),
				$cheie
			)
		);
	}

	$valoare = (float) $valoare;
	if ( $valoare < $minim || $valoare > $maxim ) {
		return new WP_Error(
			'ccs_setare_in_afara',
			trim(
				sprintf(
					/* translators: 1: setarea, 2: valoarea dată, 3: minimul, 4: maximul. */
					__( '„%1$s” trebuie să fie între %3$s și %4$s. Ai dat %2$s.', 'conex-conect-sync' ),
					$cheie,
					ccs_nr_setare( $valoare ),
					ccs_nr_setare( $minim ),
					ccs_nr_setare( $maxim )
				) . ' ' . $explicatie
			)
		);
	}

	return $valoare;
}

/**
 * Un număr de setare, scris scurt.
 *
 * @param float $n Numărul.
 * @return string
 */
function ccs_nr_setare( $n ) {
	return rtrim( rtrim( number_format( (float) $n, 2, ',', '' ), '0' ), ',' );
}

/**
 * Prețul de vânzare pentru un cost de achiziție.
 *
 * `max()` între trei reguli, nu doar adaosul: adaosul procentual, marja minimă în lei și un preț minim absolut.
 * Fără marja minimă, produsele foarte ieftine se vând în pierdere oricât de mare ar fi procentul.
 *
 * Dacă produsul are brandul sau categoria într-o excepție, regula aia bate setările generale. Contextul e
 * opțional tocmai ca apelurile care n-au de unde să-l știe să rămână corecte, pe setările generale.
 *
 * @param float $cost_fara_tva Prețul de achiziție fără TVA.
 * @param float $taxa_eco      Taxa verde, dacă există.
 * @param array $context       Opțional: brand, cat1, cat2, cat3, pentru excepții.
 * @return float Prețul de vânzare, rotunjit după setare.
 */
function ccs_pret( $cost_fara_tva, $taxa_eco = 0.0, $context = array() ) {
	$setari = ccs_setari_pret( $context );
	$cost   = (float) $cost_fara_tva * (float) $setari['cota_tva'];
	if ( $setari['include_taxa_eco'] ) {
		$cost += (float) $taxa_eco;
	}
	if ( $cost <= 0 ) {
		return 0.0;
	}

	$din_adaos = $cost * (float) $setari['adaos'];
	$din_marja = $cost + (float) $setari['marja_minima_lei'];

	$pret = max( $din_adaos, $din_marja, (float) $setari['pret_minim_lei'] );

	return 'sus' === $setari['rotunjire'] ? (float) ceil( $pret ) : (float) round( $pret, 2 );
}
