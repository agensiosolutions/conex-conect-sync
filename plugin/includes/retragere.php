<?php
/**
 * Retragerea de la vânzare a produselor pe care magazinul nu le mai poate aduce.
 *
 * Nu se șterge nimic, niciodată. Produsul rămâne pe site, cu pagina și adresa lui, și trece pe stoc epuizat.
 * Motivul e simplu: o pagină de produs care a stat ani în Google aduce în continuare vizitatori, iar un „stoc
 * epuizat” îi ține pe site, unde pot cumpăra altceva. O ștergere îi trimite în 404.
 *
 * Totul trece prin CRUD-ul WooCommerce și se scrie în `ccs_modificari`, deci `wp ccs revino <rulare>` readuce
 * stocurile exact cum erau.
 *
 * @package conex-conect-sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Produsele de pe site care nu mai sunt în catalogul furnizorului, grupate după cum arată codul lor.
 *
 * La Cez Auto tiparul codului spune de unde vine produsul: `CZ1135` e pus de mână de echipa magazinului, `10000` a
 * venit din feed-ul Mega Drive, înghețat din noiembrie 2025. Primele rămân, celelalte nu mai pot fi aduse.
 *
 * @return array grup => array( wc_id => sku ).
 */
function ccs_retragere_grupuri() {
	$potrivire = ccs_potriveste( false );

	$grupuri = array(
		'manuale'   => array(),
		'din_feed'  => array(),
		'altele'    => array(),
	);

	foreach ( $potrivire['lista_orfane'] as $wc_id => $sku ) {
		if ( preg_match( '/^[A-Z]{2}\d+$/i', $sku ) ) {
			$grupuri['manuale'][ $wc_id ] = $sku;
		} elseif ( preg_match( '/^\d{4,6}$/', $sku ) ) {
			$grupuri['din_feed'][ $wc_id ] = $sku;
		} else {
			$grupuri['altele'][ $wc_id ] = $sku;
		}
	}

	// Produsele excluse de client din potrivire nu apar la orfane, dar sunt în aceeași situație.
	foreach ( ccs_excluse() as $wc_id ) {
		$sku = (string) get_post_meta( $wc_id, '_sku', true );
		if ( '' !== $sku ) {
			$grupuri['excluse'][ $wc_id ] = $sku;
		}
	}

	/**
	 * Permite schimbarea grupării, pentru magazine cu alt tipar de coduri.
	 *
	 * @param array $grupuri Grupurile.
	 */
	return apply_filters( 'ccs_retragere_grupuri', $grupuri );
}

/**
 * Câte produse dintr-o listă mai sunt afișate ca disponibile.
 *
 * @param array $ids Id-urile.
 * @return int Numărul.
 */
function ccs_retragere_de_atins( $ids ) {
	$n = 0;
	foreach ( $ids as $wc_id ) {
		if ( 'outofstock' !== get_post_meta( $wc_id, '_stock_status', true ) ) {
			$n++;
		}
	}
	return $n;
}

/**
 * Trece produsele pe stoc epuizat.
 *
 * @param array $ids Id-urile produselor.
 * @param array $opt limita, proba, motiv.
 * @return array Rezumatul.
 */
function ccs_retrage( $ids, $opt = array() ) {
	global $wpdb;
	$opt = wp_parse_args(
		$opt,
		array(
			'limita' => 0,
			'proba'  => false,
			'motiv'  => '',
		)
	);

	$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
	if ( $opt['limita'] ) {
		$ids = array_slice( $ids, 0, (int) $opt['limita'] );
	}

	$rezumat = array(
		'rulare'   => 0,
		'de_atins' => ccs_retragere_de_atins( $ids ),
		'atinse'   => 0,
		'sarite'   => 0,
		'esuate'   => 0,
	);

	if ( $opt['proba'] ) {
		return $rezumat;
	}
	if ( ! function_exists( 'wc_get_product' ) ) {
		$rezumat['esuate'] = count( $ids );
		return $rezumat;
	}

	$rezumat['rulare'] = ccs_rulare_start( 'retragere' );
	$tabel             = ccs_tabel( 'modificari' );
	$acum              = current_time( 'mysql' );
	$lot               = max( 1, (int) ccs_setari()['lot_aplicare'] );
	$facut             = 0;

	foreach ( $ids as $wc_id ) {
		$produs = wc_get_product( $wc_id );
		if ( ! $produs ) {
			$rezumat['esuate']++;
			continue;
		}
		$stare_veche = $produs->get_stock_status( 'edit' );
		if ( 'outofstock' === $stare_veche ) {
			$rezumat['sarite']++;
			continue;
		}
		$stoc_vechi = $produs->get_stock_quantity( 'edit' );

		$produs->set_manage_stock( true );
		$produs->set_stock_quantity( 0 );
		$produs->set_stock_status( 'outofstock' );
		$produs->save();

		foreach ( array(
			array( 'stoc', null === $stoc_vechi ? '' : (string) (int) $stoc_vechi, '0' ),
			array( 'stare_stoc', $stare_veche, 'outofstock' ),
		) as $m ) {
			$wpdb->insert(
				$tabel,
				array(
					'rulare_id'     => $rezumat['rulare'],
					'wc_product_id' => $wc_id,
					'camp'          => $m[0],
					'vechi'         => $m[1],
					'nou'           => $m[2],
					'aplicat_la'    => $acum,
				)
			);
		}

		$rezumat['atinse']++;
		$facut++;
		if ( 0 === $facut % $lot && function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
	}

	ccs_rulare_stop( $rezumat['rulare'], $rezumat['esuate'] ? 'gata_cu_erori' : 'gata', $rezumat );
	ccs_jurnal( 'info', sprintf( 'Retrase de la vânzare: %d produse. %s', $rezumat['atinse'], $opt['motiv'] ) );

	if ( function_exists( 'ccs_admin_uita' ) ) {
		ccs_admin_uita();
	}

	return $rezumat;
}
