<?php
/**
 * Legarea produselor de pe site cu produsele furnizorului.
 *
 * La Cez Auto potrivirea e banală: SKU-ul de pe site e chiar `codEAN`-ul de la Conex, verificat pe 5.076 de coduri
 * cu `ProductId` identic, zero nepotriviri. De aceea strategia principală e egalitatea exactă.
 *
 * Interfața rămâne însă deschisă, pentru că la Piese Total aceeași treabă cere potrivire pe cod fără zerouri
 * inițiale, pe cod de producător, aliasuri de brand și un ecran de revizuire. Strategiile se adaugă în
 * `ccs_potrivire_strategii()` fără să se rescrie nimic aici.
 *
 * Nivelurile de încredere: A = cod exact, B = recuperat prin cod de producător, C = de verificat de om.
 * Doar A și B se scriu automat.
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
 * SKU-urile produselor publicate de pe site.
 *
 * Citim direct din `postmeta`, nu prin WooCommerce: e de zeci de ori mai rapid pe 6.800 de produse și nu cere
 * WooCommerce încărcat. Produsele fără SKU nu pot fi legate, deci nici nu ne interesează.
 *
 * @return array product_id => sku.
 */
function ccs_site_skuuri() {
	global $wpdb;
	$randuri = $wpdb->get_results(
		"SELECT p.ID, m.meta_value AS sku
		 FROM {$wpdb->posts} p
		 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_sku'
		 WHERE p.post_type = 'product' AND p.post_status != 'trash' AND m.meta_value <> ''"
	);
	$iesire = array();
	foreach ( $randuri as $r ) {
		$iesire[ (int) $r->ID ] = trim( (string) $r->sku );
	}
	return $iesire;
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

	$skuuri = ccs_site_skuuri();

	// Indexăm catalogul furnizorului o dată, pe fiecare coloană folosită de strategii.
	$catalog = $wpdb->get_results( "SELECT product_id, cod, mpn FROM $tabel_p", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	$index   = array();
	foreach ( ccs_potrivire_strategii() as $s ) {
		$index[ $s['nume'] ] = array();
	}
	foreach ( $catalog as $c ) {
		foreach ( ccs_potrivire_strategii() as $s ) {
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

	$legate     = array();
	$pe_metoda  = array();
	$ambigue    = 0;
	$orfane     = array();

	foreach ( $skuuri as $wc_id => $sku ) {
		$gasit = null;
		foreach ( ccs_potrivire_strategii() as $s ) {
			$cheie = $s['normaliza'] ? call_user_func( $s['normaliza'], $sku ) : $sku;
			if ( ! isset( $index[ $s['nume'] ][ $cheie ] ) ) {
				continue;
			}
			if ( 'ambiguu' === $index[ $s['nume'] ][ $cheie ] ) {
				$ambigue++;
				continue;
			}
			$gasit = array(
				'product_id' => $index[ $s['nume'] ][ $cheie ],
				'metoda'     => $s['nume'],
				'incredere'  => $s['incredere'],
			);
			break;
		}

		if ( ! $gasit ) {
			$orfane[ $wc_id ] = $sku;
			continue;
		}

		$legate[ $wc_id ] = $gasit;
		$pe_metoda[ $gasit['metoda'] ] = ( $pe_metoda[ $gasit['metoda'] ] ?? 0 ) + 1;
	}

	if ( $scrie ) {
		$wpdb->query( "TRUNCATE TABLE $tabel_l" ); // phpcs:ignore WordPress.DB.PreparedSQL
		$acum = current_time( 'mysql' );
		foreach ( array_chunk( $legate, 500, true ) as $bucata ) {
			$valori = array();
			$date   = array();
			foreach ( $bucata as $wc_id => $l ) {
				$valori[] = '(%d,%d,%s,%s,%s)';
				array_push( $date, $wc_id, $l['product_id'], $l['metoda'], $l['incredere'], $acum );
			}
			$wpdb->query(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL
					"INSERT INTO $tabel_l (wc_product_id, product_id, metoda, incredere, confirmat_la) VALUES " . implode( ',', $valori ),
					$date
				)
			);
		}
	}

	return array(
		'produse_site'  => count( $skuuri ),
		'legate'        => count( $legate ),
		'pe_metoda'     => $pe_metoda,
		'ambigue'       => $ambigue,
		'orfane'        => count( $orfane ),
		'lista_orfane'  => $orfane,
		'in_catalog'    => count( $catalog ),
		'nelegate_conex' => count( $catalog ) - count( array_unique( wp_list_pluck( $legate, 'product_id' ) ) ),
	);
}
