<?php
/**
 * Curățarea produselor pe care furnizorul nu le mai are deloc.
 *
 * Deosebirea față de `retragere.php`: acolo un produs iese de la vânzare dar rămâne întreg pe site, pentru că
 * poate reveni. Aici e vorba de produse care nu mai pot reveni niciodată, fiindcă vin dintr-un feed oprit în
 * noiembrie 2025, iar furnizorul de azi nu le are.
 *
 * **Ce se șterge și ce nu se hotărăște cu date, nu din burtă.** Pe 26 noiembrie 2026 am încrucișat cele 2.439 de
 * produse rămase din feed cu 16 luni de Search Console: 1.827 n-au avut nicio afișare în Google în tot acest timp,
 * iar celelalte 612 au adus împreună 105 clicuri, adică 6,6 pe lună. Prin urmare:
 *
 *   - produsele fără nicio urmă în Google se duc la coșul de gunoi: nu costă nimic la ștergere;
 *   - produsele care apar în Google rămân, dar ies din magazin: stoc epuizat plus ascunse din catalog, deci adresa
 *     și poziția lor în Google rămân vii, dar nu mai apar în liste și în căutarea de pe site.
 *
 * Măsurătoarea nu se poate face din plugin, fiindcă Search Console cere alt cont și altă autentificare. Se face
 * din afară și intră aici ca listă de id-uri. Asta e și motivul pentru care curățarea nu e automată: cere o
 * măsurătoare pe care cineva trebuie să o facă și să o privească.
 *
 * Nimic din ce face fișierul ăsta nu e definitiv la momentul rulării. Ștergerea e tot o trimitere la coșul de
 * gunoi, iar `wp ccs revino <rulare>` readuce și produsele șterse, și vizibilitatea schimbată. Atenție totuși:
 * WordPress golește singur coșul după `EMPTY_TRASH_DAYS`, care pe cezauto.ro e 30 de zile.
 *
 * @package conex-conect-sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ia lacătul de scriere, apoi curăță.
 *
 * Două operații care schimbă produse nu pot rula în paralel. Proba nu cere lacătul, fiindcă nu scrie nimic.
 *
 * @param array $decizii Vezi `ccs_curata_lucreaza()`.
 * @param array $opt     Vezi `ccs_curata_lucreaza()`.
 * @return array|WP_Error
 */
function ccs_curata( $decizii, $opt = array() ) {
	if ( ! empty( $opt['proba'] ) ) {
		return ccs_curata_lucreaza( $decizii, $opt );
	}
	return ccs_cu_lacat(
		__( 'curățenia în catalogul site-ului', 'conex-conect-sync' ),
		function () use ( $decizii, $opt ) {
			return ccs_curata_lucreaza( $decizii, $opt );
		}
	);
}

/**
 * Șterge și ascunde produsele dintr-o listă de hotărâri.
 *
 * @param array $decizii array( 'sterge' => id-uri, 'ascunde' => id-uri ).
 * @param array $opt     limita, proba.
 * @return array|WP_Error Rezumatul, sau eroare.
 */
function ccs_curata_lucreaza( $decizii, $opt = array() ) {
	global $wpdb;
	$opt = wp_parse_args(
		$opt,
		array(
			'limita' => 0,
			'proba'  => false,
		)
	);

	if ( ! function_exists( 'wc_get_product' ) ) {
		return new WP_Error( 'ccs_fara_woo', __( 'WooCommerce nu e activ.', 'conex-conect-sync' ) );
	}

	$de_sters  = array_values( array_unique( array_map( 'intval', (array) ( $decizii['sterge'] ?? array() ) ) ) );
	$de_ascuns = array_values( array_unique( array_map( 'intval', (array) ( $decizii['ascunde'] ?? array() ) ) ) );

	// Un produs care e și pe o listă, și pe cealaltă, rămâne: ascunderea e hotărârea mai blândă, iar o listă care
	// se contrazice singură e un semn că măsurătoarea are o problemă, nu o invitație să ștergi.
	$in_amandoua = array_intersect( $de_sters, $de_ascuns );
	$de_sters    = array_values( array_diff( $de_sters, $in_amandoua ) );

	if ( $opt['limita'] ) {
		$de_sters  = array_slice( $de_sters, 0, (int) $opt['limita'] );
		$de_ascuns = array_slice( $de_ascuns, 0, (int) $opt['limita'] );
	}

	$rezumat = array(
		'rulare'       => 0,
		'de_sters'     => count( $de_sters ),
		'de_ascuns'    => count( $de_ascuns ),
		'sterse'       => 0,
		'ascunse'      => 0,
		'epuizate'     => 0,
		'sarite'       => 0,
		'esuate'       => 0,
		'in_amandoua'  => count( $in_amandoua ),
		'proba'        => (bool) $opt['proba'],
	);

	if ( $opt['proba'] ) {
		foreach ( $de_sters as $wc_id ) {
			$stare = get_post_status( $wc_id );
			if ( ! $stare || 'trash' === $stare ) {
				$rezumat['sarite']++;
				$rezumat['de_sters']--;
			}
		}
		foreach ( $de_ascuns as $wc_id ) {
			$produs = wc_get_product( $wc_id );
			if ( ! $produs ) {
				$rezumat['sarite']++;
				$rezumat['de_ascuns']--;
			}
		}
		return $rezumat;
	}

	$rulare            = ccs_rulare_start( 'curatenie' );
	$rezumat['rulare'] = $rulare;
	$tabel             = ccs_tabel( 'modificari' );
	$acum              = current_time( 'mysql' );
	$lot               = max( 1, (int) ccs_setari()['lot_aplicare'] );
	$facut             = 0;

	foreach ( $de_sters as $wc_id ) {
		$stare = get_post_status( $wc_id );
		if ( ! $stare || 'trash' === $stare ) {
			$rezumat['sarite']++;
			continue;
		}
		if ( ! wp_trash_post( $wc_id ) ) {
			$rezumat['esuate']++;
			continue;
		}
		$wpdb->insert(
			$tabel,
			array(
				'rulare_id'     => $rulare,
				'wc_product_id' => $wc_id,
				'camp'          => 'sters',
				'vechi'         => (string) $stare,
				'nou'           => 'trash',
				'aplicat_la'    => $acum,
			)
		);
		$rezumat['sterse']++;
		$facut++;
		if ( 0 === $facut % $lot && function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
	}

	foreach ( $de_ascuns as $wc_id ) {
		$produs = wc_get_product( $wc_id );
		if ( ! $produs ) {
			$rezumat['esuate']++;
			continue;
		}

		$modificari       = array();
		$vizibilitate_vec = $produs->get_catalog_visibility( 'edit' );
		$stare_veche      = $produs->get_stock_status( 'edit' );

		if ( 'hidden' !== $vizibilitate_vec ) {
			$produs->set_catalog_visibility( 'hidden' );
			$modificari[] = array( 'vizibilitate', (string) $vizibilitate_vec, 'hidden' );
		}
		if ( 'outofstock' !== $stare_veche ) {
			$stoc_vechi = $produs->get_stock_quantity( 'edit' );
			$produs->set_manage_stock( true );
			$produs->set_stock_quantity( 0 );
			$produs->set_stock_status( 'outofstock' );
			$modificari[] = array( 'stoc', null === $stoc_vechi ? '' : (string) (int) $stoc_vechi, '0' );
			$modificari[] = array( 'stare_stoc', (string) $stare_veche, 'outofstock' );
			$rezumat['epuizate']++;
		}

		if ( ! $modificari ) {
			$rezumat['sarite']++;
			continue;
		}

		$produs->save();

		foreach ( $modificari as $m ) {
			$wpdb->insert(
				$tabel,
				array(
					'rulare_id'     => $rulare,
					'wc_product_id' => $wc_id,
					'camp'          => $m[0],
					'vechi'         => $m[1],
					'nou'           => $m[2],
					'aplicat_la'    => $acum,
				)
			);
		}

		$rezumat['ascunse']++;
		$facut++;
		if ( 0 === $facut % $lot && function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
	}

	ccs_rulare_stop( $rulare, $rezumat['esuate'] ? 'gata_cu_erori' : 'gata', $rezumat );
	ccs_jurnal(
		'info',
		sprintf( 'Curățenie: %d produse șterse, %d ascunse din catalog.', $rezumat['sterse'], $rezumat['ascunse'] )
	);

	if ( function_exists( 'ccs_admin_uita' ) ) {
		ccs_admin_uita();
	}

	return $rezumat;
}

/**
 * Citește hotărârile dintr-un fișier JSON.
 *
 * Forma așteptată: `{ "sterge": [12, 34], "ascunde": [56] }`. Orice altceva e o greșeală de generare, iar o
 * greșeală aici înseamnă produse șterse degeaba, deci se oprește în loc să ghicească.
 *
 * @param string $cale Calea fișierului.
 * @return array|WP_Error Hotărârile.
 */
function ccs_curatenie_citeste( $cale ) {
	if ( ! is_readable( $cale ) ) {
		return new WP_Error(
			'ccs_fisier_lipsa',
			sprintf(
				/* translators: %s: calea fișierului. */
				__( 'Nu pot citi fișierul %s.', 'conex-conect-sync' ),
				$cale
			)
		);
	}

	$date = json_decode( (string) file_get_contents( $cale ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! is_array( $date ) ) {
		return new WP_Error( 'ccs_json_stricat', __( 'Fișierul nu conține JSON valid.', 'conex-conect-sync' ) );
	}
	if ( ! isset( $date['sterge'] ) && ! isset( $date['ascunde'] ) ) {
		return new WP_Error(
			'ccs_json_gol',
			__( 'Fișierul n-are nici „sterge”, nici „ascunde”. Verifică de unde vine.', 'conex-conect-sync' )
		);
	}

	return array(
		'sterge'  => array_map( 'intval', (array) ( $date['sterge'] ?? array() ) ),
		'ascunde' => array_map( 'intval', (array) ( $date['ascunde'] ?? array() ) ),
	);
}
