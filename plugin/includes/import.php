<?php
/**
 * Pregătirea unui produs pentru import.
 *
 * Fișierul ăsta nu scrie nimic în WooCommerce. Ia un produs de la furnizor și întoarce exact ce s-ar crea, plus
 * motivul fiecărei hotărâri: de ce prețul ăla, de ce categoria aia, de ce e sau nu gata de urcat. Importul propriu-
 * zis se adaugă aici, peste aceeași funcție, ca să nu existe două calcule care pot să nu fie de acord.
 *
 * API-ul furnizorului **nu filtrează pe produs**: măsurat pe 24 noiembrie, `productId`, `ProductId`, `codEAN`,
 * `code` și `search` lasă toate `total` la 20.725. Deci un singur produs se ia tot din catalogul întreg, care vine
 * în jur de 7 secunde de pe server. Pentru o verificare punctuală e în regulă; pentru import oricum avem nevoie de
 * tot catalogul.
 *
 * @package conex-conect-sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Caută un produs în catalogul furnizorului, luat proaspăt de la API.
 *
 * @param string $cod Codul EAN sau id-ul de produs.
 * @return array|WP_Error Produsul, cu chei mici, sau eroare.
 */
function ccs_import_adu_produs( $cod ) {
	$catalog = ccs_api_catalog();
	if ( is_wp_error( $catalog ) ) {
		return $catalog;
	}

	$cod = trim( (string) $cod );
	foreach ( $catalog as $p ) {
		if ( (string) ( $p['codean'] ?? '' ) === $cod || (string) ( $p['productid'] ?? '' ) === $cod ) {
			return $p;
		}
	}

	return new WP_Error(
		'ccs_produs_negasit',
		sprintf(
			/* translators: 1: codul căutat, 2: câte produse are catalogul. */
			__( 'Codul %1$s nu e în catalog. Catalogul are %2$d produse.', 'conex-conect-sync' ),
			$cod,
			count( $catalog )
		)
	);
}

/**
 * Ce s-ar crea în WooCommerce pentru un produs al furnizorului.
 *
 * @param array $p Produsul, cu chei mici, așa cum vine din API sau din tabelul propriu.
 * @return array Datele produsului, motivele hotărârilor și piedicile.
 */
function ccs_import_pregateste( $p ) {
	$p = (array) $p;

	// Tabelul propriu și API-ul folosesc nume diferite pentru aceleași câmpuri.
	$nume  = (string) ( $p['numeprodus'] ?? $p['nume'] ?? '' );
	$cod   = (string) ( $p['codean'] ?? $p['cod'] ?? '' );
	$mpn   = (string) ( $p['codproducator'] ?? $p['mpn'] ?? '' );
	$brand = (string) ( $p['brandprodus'] ?? $p['brand'] ?? '' );
	$cost  = (float) ( $p['pretronfaratva'] ?? $p['cost'] ?? 0 );
	$taxa  = (float) ( $p['prettaxa'] ?? $p['taxa_eco'] ?? 0 );
	$stoc  = (int) ( $p['stoc'] ?? 0 );
	$pid   = (int) ( $p['productid'] ?? $p['product_id'] ?? 0 );

	$setari = ccs_setari();
	$pret   = ccs_pret( $cost, $taxa );
	$term   = ccs_categorie_pentru(
		array(
			'cat1' => $p['categorie1'] ?? $p['cat1'] ?? '',
			'cat2' => $p['categorie2'] ?? $p['cat2'] ?? '',
			'cat3' => $p['categorie3'] ?? $p['cat3'] ?? '',
			'nume' => $nume,
		)
	);
	$poze = $pid ? ccs_imagini_produs( $pid, (int) $setari['poze_per_produs'] ) : array();

	$piedici = array();
	if ( ! $poze ) {
		$piedici['fara_poza'] = __( 'Furnizorul n-are nicio poză. Un produs fără poză nu urcă pe site.', 'conex-conect-sync' );
	}
	if ( $cost <= 0 ) {
		$piedici['fara_cost'] = __( 'Fără cost de achiziție nu se poate calcula prețul.', 'conex-conect-sync' );
	}
	if ( $stoc <= 0 ) {
		$piedici['fara_stoc'] = __( 'Nu e pe stoc la furnizor în ziua importului.', 'conex-conect-sync' );
	}
	if ( ! $term ) {
		$piedici['fara_categorie'] = __( 'Nu știm în ce categorie de pe site merge.', 'conex-conect-sync' );
	}
	if ( '' === $nume ) {
		$piedici['fara_nume'] = __( 'Produsul n-are nume.', 'conex-conect-sync' );
	}

	// Un cod care e deja pe site înseamnă că produsul nu e nou: ar fi un duplicat, nu un import.
	$existent = $cod ? ccs_import_produs_dupa_sku( $cod ) : 0;
	if ( $existent ) {
		$piedici['exista_deja'] = sprintf(
			/* translators: %d: id-ul produsului de pe site. */
			__( 'Codul e deja pe site, la produsul %d.', 'conex-conect-sync' ),
			$existent
		);
	}

	$cost_cu_tva = $cost * (float) $setari['cota_tva'] + ( $setari['include_taxa_eco'] ? $taxa : 0 );

	return array(
		'product_id' => $pid,
		'date'       => array(
			'nume'          => $nume,
			'sku'           => $cod,
			'pret'          => $pret,
			'gestiune_stoc' => true,
			'stoc'          => $stoc,
			'stare_stoc'    => $stoc > 0 ? 'instock' : 'outofstock',
			'categorie'     => $term,
			'poze'          => $poze,
			'atribute'      => array_filter(
				array(
					'Brand'           => $brand,
					'Cod producător'  => $mpn,
				)
			),
			// Furnizorul nu trimite descrieri, măsurat pe tot catalogul în octombrie. Produsul urcă fără descriere,
			// iar asta e o limită de date, nu o scăpare de implementare.
			'descriere'     => '',
		),
		'motive'     => array(
			'pret'      => sprintf(
				/* translators: 1: cost, 2: cota TVA, 3: taxa verde, 4: cost cu TVA, 5: adaos, 6: marja minimă, 7: preț. */
				__( 'cost %1$s × TVA %2$s%% + taxă verde %3$s = %4$s; cel mai mare dintre ×%5$s și +%6$s lei, rotunjit = %7$s lei', 'conex-conect-sync' ),
				ccs_lei( $cost, 4 ),
				(int) round( ( (float) $setari['cota_tva'] - 1 ) * 100 ),
				ccs_lei( $taxa ),
				ccs_lei( $cost_cu_tva ),
				ccs_lei( $setari['adaos'] ),
				ccs_lei( $setari['marja_minima_lei'] ),
				ccs_lei( $pret )
			),
			'categorie' => ccs_import_motiv_categorie( $p, $term ),
			'poze'      => $poze
				? sprintf(
					/* translators: 1: câte se iau, 2: câte are furnizorul. */
					__( '%1$d poze luate, din câte are furnizorul pentru produs', 'conex-conect-sync' ),
					count( $poze )
				)
				: __( 'furnizorul n-are poze pentru produsul ăsta', 'conex-conect-sync' ),
		),
		'piedici'    => $piedici,
		'gata'       => empty( $piedici ),
	);
}

/**
 * Id-ul produsului de pe site care are deja SKU-ul dat.
 *
 * @param string $sku SKU-ul.
 * @return int Id-ul, sau 0.
 */
function ccs_import_produs_dupa_sku( $sku ) {
	global $wpdb;
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_sku'
			 WHERE m.meta_value = %s AND p.post_type = 'product' AND p.post_status != 'trash'
			 LIMIT 1",
			$sku
		)
	);
}

/**
 * Explicația pe scurt a categoriei alese.
 *
 * @param array $p    Produsul furnizorului.
 * @param int   $term Categoria aleasă.
 * @return string Explicația.
 */
function ccs_import_motiv_categorie( $p, $term ) {
	global $wpdb;
	$p    = (array) $p;
	$cale = ccs_categorie_cale(
		array(
			'cat1' => $p['categorie1'] ?? $p['cat1'] ?? '',
			'cat2' => $p['categorie2'] ?? $p['cat2'] ?? '',
			'cat3' => $p['categorie3'] ?? $p['cat3'] ?? '',
		)
	);

	if ( ! $term ) {
		return sprintf(
			/* translators: %s: calea la furnizor. */
			__( 'nicio regulă pentru calea „%s”', 'conex-conect-sync' ),
			$cale
		);
	}

	$tabel  = ccs_tabel( 'categorii' );
	$reguli = $wpdb->get_results(
		$wpdb->prepare( "SELECT * FROM $tabel WHERE cale = %s", $cale ), // phpcs:ignore WordPress.DB.PreparedSQL
		ARRAY_A
	);
	$nume   = mb_strtoupper( (string) ( $p['numeprodus'] ?? $p['nume'] ?? '' ) );

	foreach ( $reguli as $r ) {
		if ( '' !== $r['contine'] && false !== mb_strpos( $nume, $r['contine'] ) && (int) $r['term_id'] === (int) $term ) {
			return sprintf(
				/* translators: 1: calea, 2: cuvântul din nume, 3: sursa regulii. */
				__( '„%1$s” plus cuvântul „%2$s” din nume (regulă %3$s)', 'conex-conect-sync' ),
				$cale,
				$r['contine'],
				'om' === $r['sursa'] ? __( 'pusă de om', 'conex-conect-sync' ) : __( 'învățată', 'conex-conect-sync' )
			);
		}
	}
	foreach ( $reguli as $r ) {
		if ( '' === $r['contine'] && (int) $r['term_id'] === (int) $term ) {
			return sprintf(
				/* translators: 1: calea, 2: încrederea, 3: câte produse. */
				__( '„%1$s”, regulă învățată din %3$d produse, %2$d%% dintre ele în categoria asta', 'conex-conect-sync' ),
				$cale,
				(int) $r['incredere'],
				(int) $r['produse']
			);
		}
	}

	return sprintf(
		/* translators: %s: calea la furnizor. */
		__( 'din calea „%s”, prin filtru', 'conex-conect-sync' ),
		$cale
	);
}
