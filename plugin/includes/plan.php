<?php
/**
 * Planul: ce s-ar schimba pe site, cu motivele.
 *
 * Un singur loc calculează ce trebuie făcut. Raportul CSV îl scrie pe hârtie, aplicarea îl scrie în WooCommerce.
 * Dacă ar exista două calcule, raportul pe care clientul îl aprobă ar putea să nu fie ce se aplică. Așa nu se poate
 * întâmpla: `wp ccs raport` și `wp ccs aplica` cheamă aceeași funcție.
 *
 * Citim starea actuală a site-ului direct din `postmeta`, nu prin WooCommerce: pe 4.000 de produse e de zeci de ori
 * mai rapid și planul trebuie să poată fi calculat și când WooCommerce nu e încărcat. Scrierea, în schimb, trece
 * obligatoriu prin CRUD-ul WooCommerce (vezi `aplica.php`).
 *
 * @package conex-conect-sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Starea actuală a unor produse de pe site.
 *
 * @param array $ids Id-urile produselor.
 * @return array id => array cu starea.
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
				"SELECT p.ID, p.post_title, p.post_status,
				        MAX(CASE WHEN m.meta_key = '_regular_price'   THEN m.meta_value END) AS pret,
				        MAX(CASE WHEN m.meta_key = '_sale_price'      THEN m.meta_value END) AS pret_redus,
				        MAX(CASE WHEN m.meta_key = '_stock'           THEN m.meta_value END) AS stoc,
				        MAX(CASE WHEN m.meta_key = '_stock_status'    THEN m.meta_value END) AS stare_stoc,
				        MAX(CASE WHEN m.meta_key = '_manage_stock'    THEN m.meta_value END) AS gestiune_stoc,
				        MAX(CASE WHEN m.meta_key = '_ccs_pret_manual' THEN m.meta_value END) AS pret_manual,
		        MAX(CASE WHEN m.meta_key = '_ccs_scadere_permisa' THEN m.meta_value END) AS scadere_permisa
				 FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
				 WHERE p.ID IN ($locuri)
				 GROUP BY p.ID, p.post_title, p.post_status",
				$bucata
			)
		);
		foreach ( $randuri as $r ) {
			$iesire[ (int) $r->ID ] = array(
				'nume'          => (string) $r->post_title,
				'stare_post'    => (string) $r->post_status,
				'pret'          => null === $r->pret ? '' : (string) $r->pret,
				'pret_redus'    => null === $r->pret_redus ? '' : (string) $r->pret_redus,
				'stoc'          => null === $r->stoc || '' === $r->stoc ? '' : (string) $r->stoc,
				'stare_stoc'    => (string) $r->stare_stoc,
				'gestiune_stoc' => 'yes' === $r->gestiune_stoc,
				'pret_manual'   => ! empty( $r->pret_manual ),
				'scadere_ok'    => ! empty( $r->scadere_permisa ),
			);
		}
	}
	return $iesire;
}

/**
 * Antetul raportului, în ordinea coloanelor din `ccs_plan_rand_csv()`.
 *
 * @return array Numele coloanelor.
 */
function ccs_plan_coloane() {
	return array(
		'sku', 'wc_id', 'nume', 'brand', 'metoda', 'incredere', 'nume_scor_%',
		'cost_fara_tva', 'cost_cu_tva', 'pret_site', 'pret_propus', 'delta_lei', 'delta_%', 'marja_lei', 'adaos_%',
		'stoc_site', 'stoc_conex', 'stare_stoc_site', 'stare_stoc_propusa',
		'sub_cost', 'absent_runde', 'actiune', 'blocat', 'motiv',
	);
}

/**
 * Un rând de plan, pregătit pentru CSV.
 *
 * @param array $r Rândul de plan.
 * @return array Valorile, în ordinea din `ccs_plan_coloane()`.
 */
function ccs_plan_rand_csv( $r ) {
	$lei = static function ( $v, $zecimale = 2 ) {
		return null === $v ? '' : number_format( (float) $v, $zecimale, ',', '' );
	};

	return array(
		$r['sku'],
		$r['wc_id'],
		$r['nume'],
		$r['brand'],
		$r['metoda'],
		$r['incredere'],
		$r['nume_scor'],
		$lei( $r['cost'], 4 ),
		$lei( $r['cost_cu_tva'] ),
		$lei( $r['pret_site'] ),
		$lei( $r['pret_tinta'] ),
		null === $r['pret_site'] ? '' : $lei( $r['pret_tinta'] - $r['pret_site'] ),
		null === $r['pret_site'] || 0.0 === (float) $r['pret_site'] ? '' : $lei( ( $r['pret_tinta'] / $r['pret_site'] - 1 ) * 100, 1 ),
		$lei( $r['pret_tinta'] - $r['cost_cu_tva'] ),
		$r['cost_cu_tva'] > 0 ? $lei( ( $r['pret_tinta'] / $r['cost_cu_tva'] - 1 ) * 100, 1 ) : '',
		null === $r['stoc_site'] ? '' : $r['stoc_site'],
		$r['stoc_tinta'],
		$r['stare_site'],
		$r['stare_tinta'],
		$r['sub_cost'] ? 'da' : '',
		$r['lipsa_runde'],
		implode( '+', $r['actiuni'] ),
		$r['blocaje'] ? implode( '+', array_keys( $r['blocaje'] ) ) : '',
		$r['blocaje'] ? implode( ' ', $r['blocaje'] ) : '',
	);
}

/**
 * Calculează ce s-ar schimba pe site.
 *
 * Fiecare produs legat primește o țintă de preț și una de stoc, plus lista de acțiuni și lista de blocaje. Un blocaj
 * nu e o eroare: e un produs pe care refuzăm să-l atingem automat și care intră în raport ca atare.
 *
 * @param array $argumente doar_actiuni: numai rândurile cu ceva de făcut. limita: câte rânduri, maxim.
 * @return array|WP_Error array( randuri, rezumat ), sau eroare.
 */
function ccs_plan( $argumente = array() ) {
	global $wpdb;
	$argumente = wp_parse_args(
		$argumente,
		array(
			'doar_actiuni' => false,
			'limita'       => 0,
		)
	);

	$tabel_p = ccs_tabel( 'products' );
	$tabel_l = ccs_tabel( 'links' );

	// Numai legăturile de încredere. Cele marcate `C` au codul potrivit dar numele complet diferit, adică arată spre
	// alt produs; intră în raportul de verificat, nu în plan.
	$legaturi = $wpdb->get_results(
		"SELECT l.wc_product_id, l.metoda, l.incredere, l.nume_scor,
		        c.product_id, c.cod, c.nume, c.brand, c.cost, c.taxa_eco, c.stoc, c.lipsa_runde
		 FROM $tabel_l l
		 INNER JOIN $tabel_p c ON c.product_id = l.product_id
		 WHERE l.incredere IN ('A','B')
		 ORDER BY l.wc_product_id" // phpcs:ignore WordPress.DB.PreparedSQL
	);
	if ( ! $legaturi ) {
		return new WP_Error( 'ccs_fara_legaturi', __( 'Nu există legături. Rulează întâi `wp ccs potriveste`.', 'conex-conect-sync' ) );
	}

	$site   = ccs_site_date_produse( wp_list_pluck( $legaturi, 'wc_product_id' ) );
	$setari = ccs_setari();

	$randuri = array();
	$rezumat = array(
		'produse'       => 0,
		'de_schimbat'   => 0,
		'pret'          => 0,
		'stoc'          => 0,
		'de_epuizat'    => 0,
		'de_reactivat'  => 0,
		'sub_cost'      => 0,
		'neatinse'      => 0,
		'blocate'       => 0,
		'pe_blocaj'     => array(),
	);

	foreach ( $legaturi as $l ) {
		$wc_id = (int) $l->wc_product_id;
		$s     = $site[ $wc_id ] ?? null;
		if ( ! $s ) {
			// Produs șters între potrivire și acum. Legătura se curăță la următoarea potrivire.
			continue;
		}

		$cost        = (float) $l->cost;
		$taxa        = (float) $l->taxa_eco;
		$cost_cu_tva = $cost * (float) $setari['cota_tva'] + ( $setari['include_taxa_eco'] ? $taxa : 0 );
		$pret_tinta  = ccs_pret( $cost, $taxa );
		$pret_site   = '' === $s['pret'] ? null : (float) $s['pret'];

		// Stocul țintă: ce spune furnizorul, sau zero dacă produsul lipsește din feed de prea multe runde la rând.
		$lipsa_runde = (int) $l->lipsa_runde;
		$stoc_tinta  = (int) $l->stoc;
		if ( $lipsa_runde >= (int) $setari['stoc_absent_runde'] ) {
			$stoc_tinta = 0;
		}
		$stoc_site   = '' === $s['stoc'] ? null : (int) $s['stoc'];
		$stare_tinta = $stoc_tinta > (int) get_option( 'woocommerce_notify_no_stock_amount', 0 ) ? 'instock' : 'outofstock';

		$blocaje = array();

		// Cost zero sau negativ: furnizorul a trimis ceva ce nu putem folosi. Nu scriem niciodată preț zero.
		if ( $cost <= 0 ) {
			$blocaje['cost_zero'] = __( 'Furnizorul nu a trimis un cost valid.', 'conex-conect-sync' );
		}

		// Preț pus de om. Stocul se aplică în continuare; prețul nu se atinge.
		if ( $s['pret_manual'] ) {
			$blocaje['pret_manual'] = __( 'Preț marcat manual, nu se suprascrie.', 'conex-conect-sync' );
		}

		// O prăbușire de preț e aproape sigur un cost greșit la furnizor, nu o reducere reală.
		// `scadere_ok` e decizia clientului pe produsul ăsta: a văzut scăderea și a spus că e corectă.
		$scadere = $s['scadere_ok'] ? 0.0 : (float) $setari['scadere_pret_maxima'];
		if ( ! isset( $blocaje['cost_zero'] ) && null !== $pret_site && $pret_site > 0 && $scadere > 0
			&& $pret_tinta < $pret_site * $scadere ) {
			$blocaje['scadere_mare'] = sprintf(
				/* translators: 1: prețul de pe site, 2: prețul propus. */
				__( 'Prețul ar scădea de la %1$s la %2$s lei, prea mult ca să fie automat.', 'conex-conect-sync' ),
				number_format( $pret_site, 2, ',', '.' ),
				number_format( $pret_tinta, 2, ',', '.' )
			);
		}

		// Produs la care clientul a oprit gestiunea stocului. Nu i-o repornim pe la spate.
		if ( ! $s['gestiune_stoc'] ) {
			$blocaje['fara_gestiune_stoc'] = __( 'Gestiunea stocului e oprită pe produs.', 'conex-conect-sync' );
		}

		$poate_pret = ! isset( $blocaje['cost_zero'] ) && ! isset( $blocaje['pret_manual'] ) && ! isset( $blocaje['scadere_mare'] );
		$poate_stoc = ! isset( $blocaje['fara_gestiune_stoc'] );

		$actiuni = array();
		if ( $poate_pret && ( null === $pret_site || abs( $pret_site - $pret_tinta ) >= 0.01 ) ) {
			$actiuni[] = 'pret';
		}
		if ( $poate_stoc && $stoc_site !== $stoc_tinta ) {
			$actiuni[] = 'stoc';
		}
		if ( $poate_stoc && $s['stare_stoc'] && $s['stare_stoc'] !== $stare_tinta ) {
			$actiuni[] = 'outofstock' === $stare_tinta ? 'epuizat' : 'reactivat';
		}

		$sub_cost = null !== $pret_site && $pret_site < $cost_cu_tva;

		$rand = array(
			'wc_id'       => $wc_id,
			'product_id'  => (int) $l->product_id,
			'sku'         => (string) $l->cod,
			'nume'        => $s['nume'] ? $s['nume'] : (string) $l->nume,
			'brand'       => (string) $l->brand,
			'metoda'      => (string) $l->metoda,
			'incredere'   => (string) $l->incredere,
			'nume_scor'   => (int) $l->nume_scor,
			'cost'        => $cost,
			'taxa_eco'    => $taxa,
			'cost_cu_tva' => $cost_cu_tva,
			'pret_site'   => $pret_site,
			'pret_tinta'  => $pret_tinta,
			'stoc_site'   => $stoc_site,
			'stoc_tinta'  => $stoc_tinta,
			'stare_site'  => $s['stare_stoc'],
			'stare_tinta' => $stare_tinta,
			'sub_cost'    => $sub_cost,
			'lipsa_runde' => $lipsa_runde,
			'actiuni'     => $actiuni,
			'blocaje'     => $blocaje,
		);

		$rezumat['produse']++;
		if ( $sub_cost ) {
			$rezumat['sub_cost']++;
		}
		if ( $blocaje ) {
			$rezumat['blocate']++;
			foreach ( array_keys( $blocaje ) as $cod ) {
				$rezumat['pe_blocaj'][ $cod ] = ( $rezumat['pe_blocaj'][ $cod ] ?? 0 ) + 1;
			}
		}
		if ( $actiuni ) {
			$rezumat['de_schimbat']++;
			foreach ( $actiuni as $a ) {
				$cheie = 'epuizat' === $a ? 'de_epuizat' : ( 'reactivat' === $a ? 'de_reactivat' : $a );
				$rezumat[ $cheie ]++;
			}
		} else {
			$rezumat['neatinse']++;
		}

		if ( $argumente['doar_actiuni'] && ! $actiuni ) {
			continue;
		}
		// `limita` taie lista de rânduri, nu numărătoarea: rezumatul rămâne cel al întregului magazin, ca să nu pară
		// că s-a schimbat mai puțin decât s-a schimbat.
		if ( $argumente['limita'] && count( $randuri ) >= (int) $argumente['limita'] ) {
			continue;
		}
		$randuri[] = $rand;
	}

	return array(
		'randuri' => $randuri,
		'rezumat' => $rezumat,
	);
}
