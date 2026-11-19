<?php
/**
 * Raportul „ce aș face”, înainte să fac ceva.
 *
 * Scoate un CSV cu fiecare produs legat: ce are acum pe site, ce ar avea după sincronizare, de ce, și dacă e un
 * produs pe care refuzăm să-l atingem automat. Nimic nu se scrie în WooCommerce. Ăsta e documentul pe care îl vede
 * clientul înainte să aprobe prima aplicare.
 *
 * Calculul nu e aici: vine din `ccs_plan()`, exact același pe care îl folosește și aplicarea. Raportul doar îl pune
 * pe hârtie.
 *
 * @package conex-conect-sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Deschide un CSV pentru Excel: separator `;` și BOM, altfel Excel pe română strică diacriticele și coloanele.
 *
 * @param string $fisier Calea fișierului.
 * @return resource|false Fișierul deschis.
 */
function ccs_raport_deschide( $fisier ) {
	$f = fopen( $fisier, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! $f ) {
		return false;
	}
	fwrite( $f, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	return $f;
}

/**
 * Scrie raportul CSV.
 *
 * @param string $fisier Calea fișierului.
 * @param array  $plan   Planul, dacă e deja calculat. Altfel se calculează aici.
 * @return array|WP_Error Rezumatul, sau eroare.
 */
function ccs_raport_scrie( $fisier, $plan = null ) {
	if ( null === $plan ) {
		$plan = ccs_plan();
	}
	if ( is_wp_error( $plan ) ) {
		return $plan;
	}

	$f = ccs_raport_deschide( $fisier );
	if ( ! $f ) {
		return new WP_Error( 'ccs_fisier', __( 'Nu pot scrie fișierul.', 'conex-conect-sync' ) );
	}

	fputcsv( $f, ccs_plan_coloane(), ';' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	foreach ( $plan['randuri'] as $rand ) {
		fputcsv( $f, ccs_plan_rand_csv( $rand ), ';' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	fclose( $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions

	return $plan['rezumat'];
}

/**
 * Raport pentru potrivirile pe cod la care numele arată spre alt produs.
 *
 * Astea nu se aplică niciodată automat. Codul se potrivește, dar numele nu seamănă deloc, deci ori magazinul are un
 * produs de la alt furnizor cu același cod, ori furnizorul a refolosit un cod. Ambele cazuri cer un om.
 *
 * @param string $fisier Calea fișierului.
 * @param array  $lista  Lista din `ccs_potriveste()`.
 * @return int Câte rânduri s-au scris.
 */
function ccs_raport_de_verificat( $fisier, $lista ) {
	$f = ccs_raport_deschide( $fisier );
	if ( ! $f ) {
		return 0;
	}
	fputcsv( $f, array( 'sku', 'wc_id', 'nume_pe_site', 'nume_la_conex', 'cuvinte_comune_%' ), ';' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$n = 0;
	foreach ( $lista as $wc_id => $v ) {
		fputcsv( $f, array( $v['sku'], $wc_id, $v['nume_site'], $v['nume_conex'], $v['scor'] ), ';' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$n++;
	}
	fclose( $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	return $n;
}

/**
 * Raport separat pentru produsele de pe site care nu mai sunt în catalogul furnizorului.
 *
 * Nu se șterg niciodată. Raportul spune doar care sunt și dacă mai figurează „în stoc”.
 *
 * @param string $fisier Calea fișierului.
 * @param array  $orfane id => sku, din `ccs_potriveste()`.
 * @return int Câte rânduri s-au scris.
 */
function ccs_raport_orfane( $fisier, $orfane ) {
	$site = ccs_site_date_produse( array_keys( $orfane ) );
	$f    = ccs_raport_deschide( $fisier );
	if ( ! $f ) {
		return 0;
	}
	fputcsv( $f, array( 'sku', 'wc_id', 'nume', 'pret_site', 'stoc_site', 'stare_stoc', 'introdus_manual' ), ';' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$n = 0;
	foreach ( $orfane as $wc_id => $sku ) {
		$s = $site[ (int) $wc_id ] ?? array(
			'nume'       => '',
			'pret'       => '',
			'stoc'       => '',
			'stare_stoc' => '',
		);
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
