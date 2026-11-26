<?php
/**
 * Corectarea produselor care au același cod dar alt conținut.
 *
 * Situația, de la Cez Auto: pe site există produse rămase din feed-ul vechi Mega Drive, înghețat din noiembrie 2025.
 * Codul lor nu mai e `codEAN` la Conex, dar e `codProducator` pentru cu totul alt produs. Nu sunt produse introduse
 * de mână de client, deci nu sunt ale lui: sunt intrări vechi din catalogul furnizorului, pentru marfă pe care
 * furnizorul n-o mai vinde sub codul ăla.
 *
 * Hotărârea clientului, pe 25 noiembrie: se corectează cu titlurile și conținutul de la Conex. Ce înseamnă în fapt e
 * că anunțul de pe site își schimbă identitatea, nu doar prețul, deci nu e o sincronizare obișnuită și nu se face
 * niciodată automat. De aici fișierul ăsta separat, cu previzualizare și cu valorile vechi păstrate.
 *
 * Ce se schimbă: titlul, categoria, prețul, stocul, brandul și codul de producător. Ce nu se atinge: SKU-ul, care e
 * însăși legătura, și adresa produsului, care rămâne cea veche. Pozele se fac separat, pentru că importul de media
 * are capcanele lui.
 *
 * @package conex-conect-sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Produsele de pe site care au fost scoase din potrivire și codul cărora duce la un produs Conex.
 *
 * @return array Câte un rând cu ce e acum pe site și ce ar veni de la furnizor.
 */
function ccs_corectii_lista() {
	global $wpdb;
	$tp = ccs_tabel( 'products' );

	$excluse = ccs_excluse();
	if ( ! $excluse ) {
		return array();
	}

	// Indexăm catalogul pe aceleași coloane pe care le folosește potrivirea.
	$catalog = $wpdb->get_results( "SELECT product_id, cod, mpn, nume, brand, cat1, cat2, cat3, cost, taxa_eco, stoc FROM $tp", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	$index   = array();
	foreach ( $catalog as $c ) {
		foreach ( array( $c['cod'], strtoupper( (string) $c['mpn'] ) ) as $cheie ) {
			$cheie = trim( (string) $cheie );
			if ( '' !== $cheie && ! isset( $index[ $cheie ] ) ) {
				$index[ $cheie ] = $c;
			}
		}
	}

	$lista = array();
	foreach ( $excluse as $wc_id ) {
		$sku = (string) get_post_meta( $wc_id, '_sku', true );
		if ( '' === $sku ) {
			continue;
		}
		$conex = $index[ $sku ] ?? $index[ strtoupper( $sku ) ] ?? null;
		if ( ! $conex ) {
			continue;
		}

		$pregatit = ccs_import_pregateste( $conex );

		// Capcana: produsul de pe site poate exista la furnizor sub alt cod, iar atunci corectarea ar fi greșită.
		// Măsurat la Cez Auto: aspiratorul de geamuri Kärcher, SKU `20465` pe site, e `1.633-453.0` la Conex. Ăla
		// trebuie relegat la codul lui, nu înlocuit cu produsul care întâmplător poartă `20465` ca cod de
		// producător.
		$altundeva = ccs_corectii_acelasi_produs_alt_cod( get_the_title( $wc_id ), $catalog, $sku );

		$lista[]  = array(
			'wc_id'       => (int) $wc_id,
			'sku'         => $sku,
			'nume_vechi'  => get_the_title( $wc_id ),
			'pret_vechi'  => (string) get_post_meta( $wc_id, '_regular_price', true ),
			'stoc_vechi'  => (string) get_post_meta( $wc_id, '_stock', true ),
			'categ_vechi' => ccs_categorie_adanca( $wc_id ),
			'conex'       => $conex,
			'nume_nou'    => (string) $conex['nume'],
			'pret_nou'    => ccs_pret( $conex['cost'], $conex['taxa_eco'] ),
			'stoc_nou'    => (int) $conex['stoc'],
			'categ_nou'   => $pregatit['date']['categorie'],
			'poze'        => count( $pregatit['date']['poze'] ),
			'asemanare'   => ccs_nume_asemanare( get_the_title( $wc_id ), (string) $conex['nume'] ),
			'altundeva'   => $altundeva,
		);
	}

	return $lista;
}

/**
 * Caută produsul de pe site în catalogul furnizorului, sub alt cod.
 *
 * Măsură strictă, nu cea de la potrivire: aici întrebarea e „e chiar același produs”, nu „seamănă destul”. Un
 * Jaccard de 0,8 cere ca aproape toate cuvintele să fie comune în ambele sensuri, deci „SET 2 LEVIERE” nu se
 * potrivește cu „SET 5 LEVIERE”.
 *
 * @param string $nume_site Numele de pe site.
 * @param array  $catalog   Catalogul furnizorului.
 * @param string $sku       Codul de pe site, de sărit.
 * @return array|null Produsul găsit, sau null.
 */
function ccs_corectii_acelasi_produs_alt_cod( $nume_site, $catalog, $sku ) {
	$a = ccs_nume_cuvinte( $nume_site );
	if ( count( $a ) < 3 ) {
		return null;
	}

	foreach ( $catalog as $c ) {
		if ( (string) $c['cod'] === (string) $sku ) {
			continue;
		}
		$b = ccs_nume_cuvinte( (string) $c['nume'] );
		if ( ! $b ) {
			continue;
		}
		$comun = count( array_intersect( $a, $b ) );
		if ( $comun / count( array_unique( array_merge( $a, $b ) ) ) >= 0.8 ) {
			return $c;
		}
	}
	return null;
}

/**
 * Corectează un produs de pe site cu datele produsului Conex de pe același cod.
 *
 * @param array $r Rândul din `ccs_corectii_lista()`.
 * @return array|WP_Error Ce s-a schimbat, sau eroare.
 */
function ccs_corecteaza_produs( $r, $rulare = 0 ) {
	global $wpdb;

	$produs = wc_get_product( $r['wc_id'] );
	if ( ! $produs ) {
		return new WP_Error(
			'ccs_produs_lipsa',
			sprintf(
				/* translators: %d: id-ul produsului. */
				__( 'Produsul %d nu mai există.', 'conex-conect-sync' ),
				$r['wc_id']
			)
		);
	}
	if ( ! empty( $r['altundeva'] ) ) {
		return new WP_Error(
			'ccs_exista_alt_cod',
			sprintf(
				/* translators: 1: codul de pe site, 2: codul de la furnizor. */
				__( 'Produsul %1$s există la furnizor sub codul %2$s. Ăsta se releagă la codul lui, nu se înlocuiește.', 'conex-conect-sync' ),
				$r['sku'],
				$r['altundeva']['cod']
			)
		);
	}
	if ( ! $r['categ_nou'] ) {
		return new WP_Error(
			'ccs_fara_categorie',
			sprintf(
				/* translators: %s: codul. */
				__( 'Nu știm în ce categorie merge produsul %s. Nu-l corectez pe jumătate.', 'conex-conect-sync' ),
				$r['sku']
			)
		);
	}

	$modificari = array();
	$adauga     = function ( $camp, $vechi, $nou ) use ( &$modificari ) {
		if ( (string) $vechi !== (string) $nou ) {
			$modificari[] = array(
				'camp'  => $camp,
				'vechi' => (string) $vechi,
				'nou'   => (string) $nou,
			);
		}
	};

	$adauga( 'titlu', $r['nume_vechi'], $r['nume_nou'] );
	$adauga( 'pret', $produs->get_regular_price( 'edit' ), wc_format_decimal( $r['pret_nou'], wc_get_price_decimals() ) );
	$adauga( 'stoc', $produs->get_stock_quantity( 'edit' ), $r['stoc_nou'] );
	$adauga( 'categorii', implode( ',', $produs->get_category_ids() ), $r['categ_nou'] );

	if ( ! $modificari ) {
		return array();
	}

	$produs->set_name( $r['nume_nou'] );
	$produs->set_regular_price( (string) wc_format_decimal( $r['pret_nou'], wc_get_price_decimals() ) );
	$produs->set_manage_stock( true );
	$produs->set_stock_quantity( (int) $r['stoc_nou'] );
	$produs->set_category_ids( array( (int) $r['categ_nou'] ) );
	$produs->save();

	// Adresa produsului rămâne cea veche, deliberat: o schimbare de slug ar rupe legăturile și poziția din Google
	// fără să aducă nimic. Dacă se dorește altfel, se face separat, cu redirect.

	if ( $rulare ) {
		$acum  = current_time( 'mysql' );
		$tabel = ccs_tabel( 'modificari' );
		foreach ( $modificari as $m ) {
			$wpdb->insert(
				$tabel,
				array(
					'rulare_id'     => $rulare,
					'wc_product_id' => $r['wc_id'],
					'camp'          => $m['camp'],
					'vechi'         => mb_substr( $m['vechi'], 0, 64 ),
					'nou'           => mb_substr( $m['nou'], 0, 64 ),
					'aplicat_la'    => $acum,
				)
			);
		}
	}

	// Produsul nu mai e „de lăsat în pace”: de acum e produsul Conex, deci intră în sincronizarea obișnuită.
	$wpdb->delete( ccs_tabel( 'excluse' ), array( 'wc_product_id' => $r['wc_id'] ) );
	ccs_decizie_scrie( 'potrivire', (string) $r['wc_id'], 'acelasi', __( 'Corectat cu datele de la Conex.', 'conex-conect-sync' ) );

	return $modificari;
}

/**
 * Corectează toate produsele din listă.
 *
 * @param array $opt limita, proba.
 * @return array Rezumatul.
 */
function ccs_corectii_aplica( $opt = array() ) {
	$opt = wp_parse_args(
		$opt,
		array(
			'limita' => 0,
			'proba'  => false,
		)
	);

	$lista = ccs_corectii_lista();
	if ( $opt['limita'] ) {
		$lista = array_slice( $lista, 0, (int) $opt['limita'] );
	}

	$rezumat = array(
		'rulare'  => 0,
		'de_atins' => count( $lista ),
		'atinse'  => 0,
		'esuate'  => 0,
		'erori'   => array(),
	);

	if ( $opt['proba'] ) {
		return $rezumat;
	}

	$rezumat['rulare'] = ccs_rulare_start( 'corectii' );
	foreach ( $lista as $r ) {
		$m = ccs_corecteaza_produs( $r, $rezumat['rulare'] );
		if ( is_wp_error( $m ) ) {
			$rezumat['esuate']++;
			$rezumat['erori'][] = $m->get_error_message();
			continue;
		}
		if ( $m ) {
			$rezumat['atinse']++;
		}
	}

	ccs_rulare_stop( $rezumat['rulare'], $rezumat['esuate'] ? 'gata_cu_erori' : 'gata', $rezumat );

	// Legăturile se refac, ca produsele corectate să intre în plan.
	ccs_potriveste( true );
	if ( function_exists( 'ccs_admin_uita' ) ) {
		ccs_admin_uita();
	}

	return $rezumat;
}
