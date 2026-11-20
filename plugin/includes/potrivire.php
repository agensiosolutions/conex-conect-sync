<?php
/**
 * Legarea produselor de pe site cu produsele furnizorului.
 *
 * La Cez Auto SKU-ul de pe site e, de regulă, chiar `codEAN`-ul de la Conex. Dar nu întotdeauna: magazinul are și
 * produse de la alți furnizori, cu coduri scurte din aceeași plajă, care nimeresc peste coduri Conex. Măsurat pe
 * date reale, 26 din 3.980 de potriviri pe cod arătau spre cu totul alt produs: codul `25918` e aspirator Kärcher pe
 * site și ulei Liqui Moly la Conex.
 *
 * De aceea potrivirea nu se încheie la cod. După ce codul dă un candidat, comparăm numele: câte cuvinte are în comun
 * numele de pe site cu numele de la furnizor, raportat la cel mai scurt dintre ele. Pe cele 3.980 de legături,
 * separarea e curată: peste 67% sunt potriviri bune (nume trunchiate, „VW” în loc de „VOLKSWAGEN”, alt interval de
 * ani), sub 33% sunt alt produs, iar între ele nu cade nimic. Pragul stă la 50%, în `prag_nume`.
 *
 * Nivelurile de încredere: A = cod exact și nume care seamănă, B = recuperat prin cod de producător, C = codul
 * potrivește dar numele nu, deci trebuie văzut de om. Doar A și B intră în plan și se scriu automat.
 *
 * Interfața rămâne deschisă: la Piese Total aceeași treabă cere potrivire pe cod fără zerouri inițiale, aliasuri de
 * brand și un ecran de revizuire. Strategiile se adaugă în `ccs_potrivire_strategii()` fără să se rescrie nimic aici.
 *
 * @package conex-conect-sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Strategiile de potrivire, în ordinea în care se încearcă.
 *
 * @return array Fiecare strategie: nume, coloana din tabelul furnizorului, nivelul de încredere.
 */
function ccs_potrivire_strategii() {
	$strategii = array(
		array(
			'nume'      => 'cod_exact',
			'coloana'   => 'cod',
			'incredere' => 'A',
			'normaliza' => null,
		),
		array(
			'nume'      => 'cod_producator',
			'coloana'   => 'mpn',
			'incredere' => 'B',
			'normaliza' => 'strtoupper',
		),
	);

	/**
	 * Permite adăugarea de strategii de potrivire, pentru magazine cu SKU-uri care nu se potrivesc direct.
	 *
	 * @param array $strategii Strategiile.
	 */
	return apply_filters( 'ccs_match_candidates', $strategii );
}

/**
 * Cuvintele dintr-un nume de produs, pregătite pentru comparare.
 *
 * Majuscule, fără semne de punctuație, fără cuvintele de două litere (prepoziții, unități, bucăți de cod) care ar
 * umfla artificial suprapunerea.
 *
 * @param string $nume Numele.
 * @return array Cuvintele, unice.
 */
function ccs_nume_cuvinte( $nume ) {
	$nume = mb_strtoupper( (string) $nume );
	$nume = preg_replace( '/[^A-Z0-9ĂÂÎȘȚ]+/u', ' ', $nume );
	$cuvinte = array_filter(
		explode( ' ', $nume ),
		static function ( $c ) {
			return mb_strlen( $c ) > 2;
		}
	);
	return array_values( array_unique( $cuvinte ) );
}

/**
 * Cât seamănă două nume de produs, de la 0 la 1.
 *
 * Suprapunerea cuvintelor, raportată la numele mai scurt, nu la reuniune: numele de la furnizor e adesea mai lung,
 * cu capacități și coduri în plus, iar un Jaccard clasic ar penaliza potriviri bune.
 *
 * @param string $a Un nume.
 * @param string $b Celălalt.
 * @return float De la 0 la 1. Întoarce 1 dacă unul din nume e gol, ca să nu blocheze produsele fără titlu.
 */
function ccs_nume_asemanare( $a, $b ) {
	$ca = ccs_nume_cuvinte( $a );
	$cb = ccs_nume_cuvinte( $b );
	if ( ! $ca || ! $cb ) {
		return 1.0;
	}
	return count( array_intersect( $ca, $cb ) ) / min( count( $ca ), count( $cb ) );
}

/**
 * Produsele publicate de pe site, cu SKU și nume.
 *
 * Citim direct din `postmeta`, nu prin WooCommerce: e de zeci de ori mai rapid pe 6.800 de produse și nu cere
 * WooCommerce încărcat. Produsele fără SKU nu pot fi legate, deci nici nu ne interesează.
 *
 * @return array product_id => array( sku, nume ).
 */
function ccs_site_produse() {
	global $wpdb;
	$randuri = $wpdb->get_results(
		"SELECT p.ID, p.post_title, m.meta_value AS sku
		 FROM {$wpdb->posts} p
		 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_sku'
		 WHERE p.post_type = 'product' AND p.post_status != 'trash' AND m.meta_value <> ''"
	);
	$iesire = array();
	foreach ( $randuri as $r ) {
		$iesire[ (int) $r->ID ] = array(
			'sku'  => trim( (string) $r->sku ),
			'nume' => (string) $r->post_title,
		);
	}
	return $iesire;
}

/**
 * Doar SKU-urile, pentru codul care nu are nevoie de nume.
 *
 * @return array product_id => sku.
 */
function ccs_site_skuuri() {
	return wp_list_pluck( ccs_site_produse(), 'sku' );
}

/**
 * Leagă produsele de pe site de catalogul furnizorului.
 *
 * @param bool $scrie Dacă false, doar calculează și raportează, fără să scrie legăturile.
 * @return array Rezumatul.
 */
function ccs_potriveste( $scrie = true ) {
	global $wpdb;
	$tabel_p = ccs_tabel( 'products' );
	$tabel_l = ccs_tabel( 'links' );

	$produse_site = ccs_site_produse();
	// Produsele pe care clientul a spus că nu sunt același produs nu se mai leagă, oricât de bine ar da codul.
	foreach ( ccs_excluse() as $wc_id ) {
		unset( $produse_site[ $wc_id ] );
	}
	$setari       = ccs_setari();
	$prag_nume    = (float) $setari['prag_nume'];
	$strategii    = ccs_potrivire_strategii();

	// Indexăm catalogul furnizorului o dată, pe fiecare coloană folosită de strategii.
	$catalog = $wpdb->get_results( "SELECT product_id, cod, mpn, nume FROM $tabel_p", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	$nume_conex = array();
	$index      = array();
	foreach ( $strategii as $s ) {
		$index[ $s['nume'] ] = array();
	}
	foreach ( $catalog as $c ) {
		$nume_conex[ (int) $c['product_id'] ] = (string) $c['nume'];
		foreach ( $strategii as $s ) {
			$valoare = trim( (string) $c[ $s['coloana'] ] );
			if ( '' === $valoare ) {
				continue;
			}
			if ( $s['normaliza'] ) {
				$valoare = call_user_func( $s['normaliza'], $valoare );
			}
			// Un cod care arată spre mai multe produse ale furnizorului e ambiguu: îl marcăm și nu-l folosim.
			if ( isset( $index[ $s['nume'] ][ $valoare ] ) ) {
				$index[ $s['nume'] ][ $valoare ] = 'ambiguu';
				continue;
			}
			$index[ $s['nume'] ][ $valoare ] = (int) $c['product_id'];
		}
	}

	$legate        = array();
	$pe_metoda     = array();
	$ambigue       = 0;
	$orfane        = array();
	$de_verificat  = array();

	foreach ( $produse_site as $wc_id => $p ) {
		$sku   = $p['sku'];
		$gasit = null;
		foreach ( $strategii as $s ) {
			$cheie = $s['normaliza'] ? call_user_func( $s['normaliza'], $sku ) : $sku;
			if ( ! isset( $index[ $s['nume'] ][ $cheie ] ) ) {
				continue;
			}
			if ( 'ambiguu' === $index[ $s['nume'] ][ $cheie ] ) {
				$ambigue++;
				continue;
			}
			$product_id = $index[ $s['nume'] ][ $cheie ];
			$scor       = ccs_nume_asemanare( $p['nume'], $nume_conex[ $product_id ] ?? '' );
			$gasit      = array(
				'product_id' => $product_id,
				'metoda'     => $s['nume'],
				'incredere'  => $scor < $prag_nume ? 'C' : $s['incredere'],
				'nume_scor'  => (int) round( $scor * 100 ),
			);
			break;
		}

		if ( ! $gasit ) {
			$orfane[ $wc_id ] = $sku;
			continue;
		}

		$legate[ $wc_id ] = $gasit;
		if ( 'C' === $gasit['incredere'] ) {
			$de_verificat[ $wc_id ] = array(
				'sku'        => $sku,
				'nume_site'  => $p['nume'],
				'nume_conex' => $nume_conex[ $gasit['product_id'] ] ?? '',
				'scor'       => $gasit['nume_scor'],
			);
		} else {
			$pe_metoda[ $gasit['metoda'] ] = ( $pe_metoda[ $gasit['metoda'] ] ?? 0 ) + 1;
		}
	}

	if ( $scrie ) {
		$wpdb->query( "TRUNCATE TABLE $tabel_l" ); // phpcs:ignore WordPress.DB.PreparedSQL
		$acum = current_time( 'mysql' );
		foreach ( array_chunk( $legate, 500, true ) as $bucata ) {
			$valori = array();
			$date   = array();
			foreach ( $bucata as $wc_id => $l ) {
				$valori[] = '(%d,%d,%s,%s,%d,%s)';
				array_push( $date, $wc_id, $l['product_id'], $l['metoda'], $l['incredere'], $l['nume_scor'], $acum );
			}
			$wpdb->query(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL
					"INSERT INTO $tabel_l (wc_product_id, product_id, metoda, incredere, nume_scor, confirmat_la) VALUES " . implode( ',', $valori ),
					$date
				)
			);
		}
	}

	$automate = count( $legate ) - count( $de_verificat );

	return array(
		'produse_site'   => count( $produse_site ),
		'legate'         => count( $legate ),
		'automate'       => $automate,
		'pe_metoda'      => $pe_metoda,
		'ambigue'        => $ambigue,
		'de_verificat'   => count( $de_verificat ),
		'lista_verificat' => $de_verificat,
		'orfane'         => count( $orfane ),
		'lista_orfane'   => $orfane,
		'in_catalog'     => count( $catalog ),
		'nelegate_conex' => count( $catalog ) - count( array_unique( wp_list_pluck( $legate, 'product_id' ) ) ),
	);
}
