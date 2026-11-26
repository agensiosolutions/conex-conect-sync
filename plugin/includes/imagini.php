<?php
/**
 * Imaginile produselor, din fereastra de noapte a furnizorului.
 *
 * Endpoint-ul `images` răspunde doar între 19:00 și 05:00; în rest dă 503 cu mesaj explicit. De aceea jobul se
 * programează devreme în fereastră, la 19:10: rata de eșec a API-ului crește pe parcursul nopții.
 *
 * Ce ținem: pentru fiecare produs al furnizorului, adresele pozelor lui. Nu descărcăm nimic aici. Tabelul ăsta
 * răspunde la o singură întrebare, dar care decide tot restul: **care produse au poză**. Un produs fără poză nu
 * urcă pe site, deci lista asta e cea care spune ce se poate importa și ce nu.
 *
 * Măsurat pe datele din octombrie: 11.018 produse din ~21.000 aveau cel puțin o poză, cu 30.498 de poze în total.
 *
 * @package conex-conect-sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Preia adresele pozelor de la furnizor și le scrie în tabelul propriu.
 *
 * Strângem tot în memorie și scriem o singură dată, la final. `ccs_api_noapte()` nu întoarce niciodată date
 * parțiale: dacă o pagină nu vine după toate încercările, întreaga preluare eșuează. Deci când ajungem la scriere,
 * avem lista întreagă și o putem înlocui pe cea veche fără să rămânem cu jumătăți.
 *
 * @return array|WP_Error Rezumatul, sau eroare.
 */
function ccs_imagini_preia() {
	global $wpdb;

	$pe_produs = array();
	$randuri   = 0;

	$rezultat = ccs_api_noapte(
		'images',
		function ( $pagina ) use ( &$pe_produs, &$randuri ) {
			foreach ( $pagina as $r ) {
				$id  = isset( $r['productid'] ) ? (int) $r['productid'] : 0;
				$url = isset( $r['url'] ) ? trim( (string) $r['url'] ) : '';
				if ( ! $id || '' === $url ) {
					continue;
				}
				$randuri++;
				$pe_produs[ $id ][] = array( (int) ( $r['order'] ?? 0 ), $url );
			}
		}
	);

	if ( is_wp_error( $rezultat ) ) {
		return $rezultat;
	}
	if ( ! $pe_produs ) {
		return new WP_Error( 'ccs_fara_imagini', __( 'Furnizorul n-a trimis nicio imagine. Nu scriu nimic.', 'conex-conect-sync' ) );
	}

	$rulare = ccs_rulare_start( 'imagini' );
	$tabel  = ccs_tabel( 'imagini' );
	$acum   = current_time( 'mysql' );

	$wpdb->query( "TRUNCATE TABLE $tabel" ); // phpcs:ignore WordPress.DB.PreparedSQL

	$valori = array();
	$date   = array();
	$scrise = 0;
	foreach ( $pe_produs as $id => $poze ) {
		// Ordinea de la furnizor hotărăște care e poza principală.
		usort(
			$poze,
			static function ( $a, $b ) {
				return $a[0] <=> $b[0];
			}
		);
		$pozitie = 0;
		foreach ( $poze as $p ) {
			$valori[] = '(%d,%d,%s,%s)';
			array_push( $date, $id, $pozitie, $p[1], $acum );
			$pozitie++;
			$scrise++;

			if ( count( $valori ) >= 500 ) {
				$wpdb->query(
					$wpdb->prepare(
						// phpcs:ignore WordPress.DB.PreparedSQL
						"INSERT INTO $tabel (product_id, pozitie, url, vazut_la) VALUES " . implode( ',', $valori ),
						$date
					)
				);
				$valori = array();
				$date   = array();
			}
		}
	}
	if ( $valori ) {
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL
				"INSERT INTO $tabel (product_id, pozitie, url, vazut_la) VALUES " . implode( ',', $valori ),
				$date
			)
		);
	}

	$rezumat = array(
		'randuri'  => $randuri,
		'produse'  => count( $pe_produs ),
		'poze'     => $scrise,
	);
	ccs_rulare_stop( $rulare, 'gata', $rezumat );

	return $rezumat;
}

/**
 * Câte poze știm, și pentru câte produse.
 *
 * @return array Cifrele.
 */
function ccs_imagini_stare() {
	global $wpdb;
	$tabel = ccs_tabel( 'imagini' );
	$tp    = ccs_tabel( 'products' );

	return array(
		'poze'          => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $tabel" ), // phpcs:ignore WordPress.DB.PreparedSQL
		'produse'       => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT product_id) FROM $tabel" ), // phpcs:ignore WordPress.DB.PreparedSQL
		'din_catalog'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $tp" ), // phpcs:ignore WordPress.DB.PreparedSQL
		'ultima_preluare' => (string) $wpdb->get_var( "SELECT MAX(vazut_la) FROM $tabel" ), // phpcs:ignore WordPress.DB.PreparedSQL
	);
}

/**
 * Pozele unui produs al furnizorului, în ordine.
 *
 * @param int $product_id Produsul.
 * @param int $limita     Câte, cel mult. 0 înseamnă toate.
 * @return array Adresele.
 */
function ccs_imagini_produs( $product_id, $limita = 0 ) {
	global $wpdb;
	$tabel = ccs_tabel( 'imagini' );
	$sql   = $wpdb->prepare(
		// phpcs:ignore WordPress.DB.PreparedSQL
		"SELECT url FROM $tabel WHERE product_id = %d ORDER BY pozitie",
		(int) $product_id
	);
	if ( $limita > 0 ) {
		$sql .= $wpdb->prepare( ' LIMIT %d', (int) $limita );
	}
	return (array) $wpdb->get_col( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL
}

/**
 * Produsele furnizorului care s-ar putea importa pe site.
 *
 * Un produs intră la import doar dacă trece toate condițiile de mai jos. Fiecare are un motiv, nu e o listă de
 * prudență: fără poză pagina arată rupt, fără cost nu i se poate calcula prețul, fără stoc n-ar fi de vânzare în
 * ziua importului, iar fără categorie ar ajunge nicăieri în meniul magazinului.
 *
 * @return array Rezumatul, cu numărul pe fiecare motiv de respingere.
 */
function ccs_import_candidati() {
	global $wpdb;
	$tp = ccs_tabel( 'products' );
	$tl = ccs_tabel( 'links' );
	$ti = ccs_tabel( 'imagini' );

	$produse = $wpdb->get_results(
		"SELECT c.product_id, c.cod, c.nume, c.brand, c.cat1, c.cat2, c.cat3, c.cost, c.stoc,
		        (SELECT COUNT(*) FROM $ti i WHERE i.product_id = c.product_id) AS poze
		 FROM $tp c
		 LEFT JOIN $tl l ON l.product_id = c.product_id
		 WHERE l.product_id IS NULL" // phpcs:ignore WordPress.DB.PreparedSQL
	);

	$rezumat = array(
		'nelegate'     => count( $produse ),
		'gata'         => 0,
		'fara_poza'    => 0,
		'fara_cost'    => 0,
		'fara_stoc'    => 0,
		'fara_categorie' => 0,
		'lista'        => array(),
		// Pe fiecare cale a furnizorului, câte produse sunt oprite numai de lipsa categoriei. Asta e cifra care
		// spune cât se deblochează dacă omul mapează calea aia, și se calculează în aceeași trecere.
		'cai'          => array(),
	);

	foreach ( $produse as $p ) {
		if ( ! (int) $p->poze ) {
			$rezumat['fara_poza']++;
			continue;
		}
		if ( (float) $p->cost <= 0 ) {
			$rezumat['fara_cost']++;
			continue;
		}
		if ( (int) $p->stoc <= 0 ) {
			$rezumat['fara_stoc']++;
			continue;
		}
		if ( ! ccs_categorie_pentru( $p ) ) {
			$rezumat['fara_categorie']++;
			$cale = ccs_categorie_cale( $p );
			$rezumat['cai'][ $cale ] = ( $rezumat['cai'][ $cale ] ?? 0 ) + 1;
			continue;
		}
		$rezumat['gata']++;
		$rezumat['lista'][] = $p;
	}

	arsort( $rezumat['cai'] );

	return $rezumat;
}

/**
 * Jobul de noapte pentru imagini.
 *
 * @return void
 */
function ccs_job_imagini() {
	if ( ! ccs_api_fereastra_deschisa() ) {
		ccs_jurnal( 'avertisment', 'Fereastra de noapte e închisă. Sar peste preluarea imaginilor.' );
		return;
	}

	$r = ccs_imagini_preia();
	if ( is_wp_error( $r ) ) {
		ccs_jurnal( 'eroare', 'Preluarea imaginilor a eșuat: ' . $r->get_error_message() );
		return;
	}

	ccs_jurnal( 'info', sprintf( '%d poze pentru %d produse.', $r['poze'], $r['produse'] ) );

	if ( function_exists( 'ccs_admin_uita' ) ) {
		ccs_admin_uita();
	}
}
add_action( 'ccs_job_imagini', 'ccs_job_imagini' );

/**
 * Înregistrează jobul de imagini lângă cel de catalog.
 *
 * @param array $joburi Joburile.
 * @return array Joburile.
 */
function ccs_imagini_job( $joburi ) {
	$joburi['ccs_job_imagini'] = array(
		'nume'         => __( 'Imaginile produselor', 'conex-conect-sync' ),
		'interval'     => DAY_IN_SECONDS,
		'doar_noaptea' => true,
	);
	return $joburi;
}
add_filter( 'ccs_joburi', 'ccs_imagini_job' );
