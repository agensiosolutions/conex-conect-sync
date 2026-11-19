<?php
/**
 * Raportul „ce aș face”, înainte să fac ceva.
 *
 * Scoate un CSV cu fiecare produs legat: ce are acum pe site, ce ar avea după sincronizare și de ce. Nimic nu se
 * scrie în WooCommerce. Ăsta e documentul pe care îl vede clientul înainte să aprobe prima aplicare.
 *
 * Citim prețul și stocul direct din `postmeta`, nu prin WooCommerce: raportul trebuie să poată rula și pe un mediu
 * unde WooCommerce nu e încărcat, și e mult mai rapid pe mii de produse.
 *
 * @package conex-conect-sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Prețul, stocul și starea stocului pentru niște produse de pe site.
 *
 * @param array $ids Id-urile produselor.
 * @return array id => array( pret, stoc, stare_stoc, nume ).
 */
function ccs_site_date_produse( $ids ) {
	global $wpdb;
	if ( ! $ids ) {
		return array();
	}
	$iesire = array();
	foreach ( array_chunk( $ids, 2000 ) as $bucata ) {
		$locuri  = implode( ',', array_fill( 0, count( $bucata ), '%d' ) );
		$randuri = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL
				"SELECT p.ID, p.post_title,
				        MAX(CASE WHEN m.meta_key = '_regular_price' THEN m.meta_value END) AS pret,
				        MAX(CASE WHEN m.meta_key = '_stock'         THEN m.meta_value END) AS stoc,
				        MAX(CASE WHEN m.meta_key = '_stock_status'  THEN m.meta_value END) AS stare_stoc
				 FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
				 WHERE p.ID IN ($locuri)
				 GROUP BY p.ID",
				$bucata
			)
		);
		foreach ( $randuri as $r ) {
			$iesire[ (int) $r->ID ] = array(
				'nume'       => (string) $r->post_title,
				'pret'       => null === $r->pret ? '' : (string) $r->pret,
				'stoc'       => null === $r->stoc ? '' : (string) $r->stoc,
				'stare_stoc' => (string) $r->stare_stoc,
			);
		}
	}
	return $iesire;
}

/**
 * Scrie raportul CSV.
 *
 * @param string $fisier Calea fișierului.
 * @return array|WP_Error Rezumatul, sau eroare.
 */
function ccs_raport_scrie( $fisier ) {
	global $wpdb;
	$tabel_p = ccs_tabel( 'products' );
	$tabel_l = ccs_tabel( 'links' );

	$randuri = $wpdb->get_results(
		"SELECT l.wc_product_id, l.metoda, l.incredere,
		        c.product_id, c.cod, c.nume, c.brand, c.cost, c.taxa_eco, c.stoc, c.lipsa_runde
		 FROM $tabel_l l
		 INNER JOIN $tabel_p c ON c.product_id = l.product_id" // phpcs:ignore WordPress.DB.PreparedSQL
	);
	if ( ! $randuri ) {
		return new WP_Error( 'ccs_fara_legaturi', __( 'Nu există legături. Rulează întâi potrivirea.', 'conex-conect-sync' ) );
	}

	$site = ccs_site_date_produse( wp_list_pluck( $randuri, 'wc_product_id' ) );

	$f = fopen( $fisier, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! $f ) {
		return new WP_Error( 'ccs_fisier', __( 'Nu pot scrie fișierul.', 'conex-conect-sync' ) );
	}
	fwrite( $f, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$antet = array(
		'sku', 'wc_id', 'nume', 'brand', 'metoda', 'incredere',
		'cost_fara_tva', 'cost_cu_tva', 'pret_site', 'pret_propus', 'delta_lei', 'delta_%', 'marja_lei', 'adaos_%',
		'stoc_site', 'stoc_conex', 'stare_stoc_site', 'stare_stoc_propusa',
		'sub_cost', 'absent_runde', 'actiune',
	);
	fputcsv( $f, $antet, ';' ); // phpcs:ignore WordPress.WP.AlternativeFunctions

	$setari   = ccs_setari();
	$rezumat  = array(
		'produse'        => 0,
		'pret_schimbat'  => 0,
		'stoc_schimbat'  => 0,
		'sub_cost'       => 0,
		'de_epuizat'     => 0,
		'de_reactivat'   => 0,
		'neatinse'       => 0,
	);

	foreach ( $randuri as $r ) {
		$s = $site[ (int) $r->wc_product_id ] ?? array( 'nume' => '', 'pret' => '', 'stoc' => '', 'stare_stoc' => '' );

		$cost_cu_tva = (float) $r->cost * (float) $setari['cota_tva'] + ( $setari['include_taxa_eco'] ? (float) $r->taxa_eco : 0 );
		$pret_propus = ccs_pret( $r->cost, $r->taxa_eco );
		$pret_site   = '' === $s['pret'] ? null : (float) $s['pret'];

		$stoc_conex  = (int) $r->stoc;
		$stoc_site   = '' === $s['stoc'] ? null : (int) $s['stoc'];
		$stare_prop  = $stoc_conex > 0 ? 'instock' : 'outofstock';

		$sub_cost = ( null !== $pret_site && $pret_site < $cost_cu_tva ) ? 'da' : '';
		if ( 'da' === $sub_cost ) {
			$rezumat['sub_cost']++;
		}

		$actiuni = array();
		if ( null === $pret_site || abs( $pret_site - $pret_propus ) >= 0.01 ) {
			$actiuni[] = 'preț';
			$rezumat['pret_schimbat']++;
		}
		if ( $stoc_site !== $stoc_conex ) {
			$actiuni[] = 'stoc';
			$rezumat['stoc_schimbat']++;
		}
		if ( $s['stare_stoc'] && $s['stare_stoc'] !== $stare_prop ) {
			$actiuni[] = 'outofstock' === $stare_prop ? 'epuizat' : 'reactivat';
			if ( 'outofstock' === $stare_prop ) {
				$rezumat['de_epuizat']++;
			} else {
				$rezumat['de_reactivat']++;
			}
		}
		if ( ! $actiuni ) {
			$rezumat['neatinse']++;
		}

		fputcsv( // phpcs:ignore WordPress.WP.AlternativeFunctions
			$f,
			array(
				$r->cod,
				$r->wc_product_id,
				$s['nume'] ? $s['nume'] : $r->nume,
				$r->brand,
				$r->metoda,
				$r->incredere,
				number_format( (float) $r->cost, 4, ',', '' ),
				number_format( $cost_cu_tva, 2, ',', '' ),
				null === $pret_site ? '' : number_format( $pret_site, 2, ',', '' ),
				number_format( $pret_propus, 2, ',', '' ),
				null === $pret_site ? '' : number_format( $pret_propus - $pret_site, 2, ',', '' ),
				null === $pret_site || 0.0 === $pret_site ? '' : number_format( ( $pret_propus / $pret_site - 1 ) * 100, 1, ',', '' ),
				number_format( $pret_propus - $cost_cu_tva, 2, ',', '' ),
				$cost_cu_tva > 0 ? number_format( ( $pret_propus / $cost_cu_tva - 1 ) * 100, 1, ',', '' ) : '',
				null === $stoc_site ? '' : $stoc_site,
				$stoc_conex,
				$s['stare_stoc'],
				$stare_prop,
				$sub_cost,
				$r->lipsa_runde,
				implode( '+', $actiuni ),
			),
			';'
		);
		$rezumat['produse']++;
	}

	fclose( $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	return $rezumat;
}

/**
 * Raport separat pentru produsele de pe site care nu mai sunt în catalogul furnizorului.
 *
 * Nu se șterg niciodată. Raportul spune doar care sunt și dacă mai figurează „în stoc”.
 *
 * @param string $fisier  Calea fișierului.
 * @param array  $orfane  id => sku, din `ccs_potriveste()`.
 * @return int Câte rânduri s-au scris.
 */
function ccs_raport_orfane( $fisier, $orfane ) {
	$site = ccs_site_date_produse( array_keys( $orfane ) );
	$f    = fopen( $fisier, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	fwrite( $f, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	fputcsv( $f, array( 'sku', 'wc_id', 'nume', 'pret_site', 'stoc_site', 'stare_stoc', 'introdus_manual' ), ';' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$n = 0;
	foreach ( $orfane as $wc_id => $sku ) {
		$s = $site[ (int) $wc_id ] ?? array( 'nume' => '', 'pret' => '', 'stoc' => '', 'stare_stoc' => '' );
		fputcsv( // phpcs:ignore WordPress.WP.AlternativeFunctions
			$f,
			array(
				$sku,
				$wc_id,
				$s['nume'],
				$s['pret'],
				$s['stoc'],
				$s['stare_stoc'],
				// Produsele introduse manual de client au alt tipar de SKU; la Cez Auto, `CZ####`.
				preg_match( '/^[A-Z]{2}\d+$/', $sku ) ? 'probabil' : '',
			),
			';'
		);
		$n++;
	}
	fclose( $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	return $n;
}
