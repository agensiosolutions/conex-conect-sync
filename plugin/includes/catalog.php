<?php
/**
 * Preluarea catalogului furnizorului în tabelul propriu, cu diferențe.
 *
 * Fluxul: cerem catalogul complet, calculăm o amprentă pe produs, comparăm cu ce aveam și raportăm ce s-a schimbat.
 * Nimic nu atinge WooCommerce aici; asta e doar copia locală a catalogului.
 *
 * @package conex-conect-sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Amprenta unui produs: md5 peste câmpurile care ne interesează.
 *
 * Numai câmpurile care, dacă se schimbă, cer o acțiune pe site. Dacă furnizorul schimbă ceva ce nu folosim,
 * amprenta rămâne aceeași și produsul nu e atins degeaba.
 *
 * @param array $p Produsul, cu chei mici, așa cum vine din API.
 * @return string Amprenta.
 */
function ccs_amprenta( $p ) {
	return md5(
		implode(
			'|',
			array(
				$p['numeprodus'] ?? '',
				$p['brandprodus'] ?? '',
				$p['codproducator'] ?? '',
				$p['categorie1'] ?? '',
				$p['categorie2'] ?? '',
				$p['categorie3'] ?? '',
				$p['pretronfaratva'] ?? '',
				$p['prettaxa'] ?? '',
				$p['stoc'] ?? '',
			)
		)
	);
}

/**
 * Pornește o rulare și întoarce id-ul ei.
 *
 * @param string $tip Tipul rulării.
 * @return int Id-ul rulării.
 */
function ccs_rulare_start( $tip ) {
	global $wpdb;
	$wpdb->insert(
		ccs_tabel( 'runs' ),
		array(
			'tip'     => $tip,
			'inceput' => current_time( 'mysql' ),
			'stare'   => 'pornit',
		)
	);
	return (int) $wpdb->insert_id;
}

/**
 * Închide o rulare.
 *
 * @param int    $id      Id-ul rulării.
 * @param string $stare   'gata' sau 'esuat'.
 * @param array  $rezumat Ce s-a întâmplat.
 * @return void
 */
function ccs_rulare_stop( $id, $stare, $rezumat = array() ) {
	global $wpdb;
	$wpdb->update(
		ccs_tabel( 'runs' ),
		array(
			'sfarsit' => current_time( 'mysql' ),
			'stare'   => $stare,
			'rezumat' => wp_json_encode( $rezumat, JSON_UNESCAPED_UNICODE ),
		),
		array( 'id' => $id )
	);
}

/**
 * Preia catalogul și îl scrie în tabelul propriu, raportând diferențele.
 *
 * Protecție, din `PLAN.md` și confirmată de eșecurile măsurate: dacă numărul de produse scade brusc față de rularea
 * anterioară, ne oprim fără să scriem. Un catalog care se înjumătățește peste noapte înseamnă aproape sigur o
 * problemă la furnizor, nu că magazinul lui s-a golit.
 *
 * @param float $prag_scadere Cât poate scădea catalogul fără să ne oprim (0.5 = jumătate).
 * @return array|WP_Error Rezumatul, sau eroare.
 */
function ccs_catalog_preia( $prag_scadere = 0.5 ) {
	global $wpdb;
	$tabel = ccs_tabel( 'products' );

	$produse = ccs_api_catalog();
	if ( is_wp_error( $produse ) ) {
		return $produse;
	}

	$inainte = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $tabel" ); // phpcs:ignore WordPress.DB.PreparedSQL
	if ( $inainte > 0 && count( $produse ) < $inainte * $prag_scadere ) {
		return new WP_Error(
			'ccs_scadere_brusca',
			sprintf(
				/* translators: 1: produse primite acum, 2: produse la rularea anterioară. */
				__( 'Catalogul a scăzut de la %2$d la %1$d produse. M-am oprit fără să scriu nimic.', 'conex-conect-sync' ),
				count( $produse ),
				$inainte
			)
		);
	}

	$rulare = ccs_rulare_start( 'catalog' );
	$acum   = current_time( 'mysql' );

	// Amprentele de dinainte, ca să vedem ce s-a schimbat fără să citim rând cu rând.
	$vechi = $wpdb->get_results( "SELECT product_id, amprenta, cost, stoc FROM $tabel", OBJECT_K ); // phpcs:ignore WordPress.DB.PreparedSQL

	$noi = 0;
	$schimbate = 0;
	$neatinse  = 0;
	$cost_schimbat = 0;
	$stoc_schimbat = 0;
	$vazute = array();

	foreach ( $produse as $p ) {
		$id = isset( $p['productid'] ) ? (int) $p['productid'] : 0;
		if ( ! $id ) {
			continue;
		}
		$vazute[] = $id;
		$amprenta = ccs_amprenta( $p );

		if ( isset( $vechi[ $id ] ) ) {
			if ( $vechi[ $id ]->amprenta === $amprenta ) {
				$neatinse++;
				continue;
			}
			$schimbate++;
			if ( (float) $vechi[ $id ]->cost !== (float) ( $p['pretronfaratva'] ?? 0 ) ) {
				$cost_schimbat++;
			}
			if ( (int) $vechi[ $id ]->stoc !== (int) ( $p['stoc'] ?? 0 ) ) {
				$stoc_schimbat++;
			}
		} else {
			$noi++;
		}

		$rand = array(
			'product_id'    => $id,
			'cod'           => (string) ( $p['codean'] ?? '' ),
			'mpn'           => (string) ( $p['codproducator'] ?? '' ),
			'nume'          => (string) ( $p['numeprodus'] ?? '' ),
			'brand'         => (string) ( $p['brandprodus'] ?? '' ),
			'cat1'          => (string) ( $p['categorie1'] ?? '' ),
			'cat2'          => (string) ( $p['categorie2'] ?? '' ),
			'cat3'          => (string) ( $p['categorie3'] ?? '' ),
			'cost'          => (float) ( $p['pretronfaratva'] ?? 0 ),
			'taxa_eco'      => (float) ( $p['prettaxa'] ?? 0 ),
			'stoc'          => (int) ( $p['stoc'] ?? 0 ),
			'amprenta'      => $amprenta,
			'ultima_vedere' => $acum,
			'lipsa_runde'   => 0,
		);

		if ( isset( $vechi[ $id ] ) ) {
			$wpdb->update( $tabel, $rand, array( 'product_id' => $id ) );
		} else {
			$rand['prima_vedere'] = $acum;
			$wpdb->insert( $tabel, $rand );
		}
	}

	// Produsele neatinse primesc și ele `ultima_vedere`, ca să știm că furnizorul le mai are.
	if ( $vazute ) {
		foreach ( array_chunk( $vazute, 2000 ) as $bucata ) {
			$locuri = implode( ',', array_fill( 0, count( $bucata ), '%d' ) );
			$wpdb->query(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL
					"UPDATE $tabel SET ultima_vedere = %s, lipsa_runde = 0 WHERE product_id IN ($locuri)",
					array_merge( array( $acum ), $bucata )
				)
			);
		}
	}

	// Produsele care n-au mai apărut: le numărăm rundele de absență, dar nu le ștergem niciodată.
	$lipsa = (int) $wpdb->query(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL
			"UPDATE $tabel SET lipsa_runde = lipsa_runde + 1 WHERE ultima_vedere < %s",
			$acum
		)
	);

	$rezumat = array(
		'primite'       => count( $produse ),
		'noi'           => $noi,
		'schimbate'     => $schimbate,
		'neatinse'      => $neatinse,
		'cost_schimbat' => $cost_schimbat,
		'stoc_schimbat' => $stoc_schimbat,
		'absente'       => $lipsa,
	);
	ccs_rulare_stop( $rulare, 'gata', $rezumat );

	return $rezumat;
}
