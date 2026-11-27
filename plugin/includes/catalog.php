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
 * Ultimele rulări, cu rezumatul lor despachetat.
 *
 * Tabelul `ccs_runs` ținea până acum evidența doar pentru cine se uita în baza de date. Rezumatul fiecărei rulări
 * se scrie ca JSON la `ccs_rulare_stop()`, deci tot ce trebuie ca să arăți în panou „ce s-a întâmplat azi-noapte”
 * era deja acolo, nefolosit.
 *
 * @param int    $limita Câte rulări.
 * @param string $tip    Opțional, doar un tip de rulare.
 * @return array Rulările, cea mai nouă prima.
 */
function ccs_rulari_recente( $limita = 10, $tip = '' ) {
	global $wpdb;
	$tabel = ccs_tabel( 'runs' );

	$randuri = $tip
		? $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL
				"SELECT * FROM $tabel WHERE tip = %s ORDER BY id DESC LIMIT %d",
				$tip,
				(int) $limita
			)
		)
		: $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL
				"SELECT * FROM $tabel ORDER BY id DESC LIMIT %d",
				(int) $limita
			)
		);

	$iesire = array();
	foreach ( (array) $randuri as $r ) {
		$rezumat = json_decode( (string) $r->rezumat, true );
		$inceput = $r->inceput ? (int) mysql2date( 'U', $r->inceput, false ) : 0;
		$sfarsit = $r->sfarsit ? (int) mysql2date( 'U', $r->sfarsit, false ) : 0;

		$iesire[] = array(
			'id'      => (int) $r->id,
			'tip'     => (string) $r->tip,
			'stare'   => (string) $r->stare,
			'inceput' => (string) $r->inceput,
			'sfarsit' => (string) $r->sfarsit,
			'durata'  => ( $inceput && $sfarsit ) ? max( 0, $sfarsit - $inceput ) : 0,
			'rezumat' => is_array( $rezumat ) ? $rezumat : array(),
		);
	}

	return $iesire;
}

/**
 * Câte modificări ale unei rulări mai pot fi date înapoi.
 *
 * Zero înseamnă ori că rularea n-a scris nimic, ori că s-a revenit deja asupra ei. În ambele cazuri butonul de
 * revenire n-are ce face, deci nu se arată.
 *
 * @param int $rulare_id Rularea.
 * @return int Numărul.
 */
function ccs_rulare_de_revenit( $rulare_id ) {
	global $wpdb;
	$tabel = ccs_tabel( 'modificari' );
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL
			"SELECT COUNT(*) FROM $tabel WHERE rulare_id = %d AND revenit_la IS NULL",
			(int) $rulare_id
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
 * Mișcarea catalogului: ce a intrat și ce a ieșit la furnizor.
 *
 * Restanța adunată până acum se rezolvă o dată; mișcarea e ce se schimbă de la o rulare la alta și rămâne
 * interesantă pentru totdeauna. Datele sunt deja în tabel: `prima_vedere` spune când a apărut un produs, iar
 * `ultima_vedere` plus `lipsa_runde` spun când a dispărut.
 *
 * Atenție la citire: cifrele astea au sens doar dacă preluarea rulează regulat. Cu joburile oprite, „ultimele șapte
 * zile” înseamnă de fapt „de la ultimele două-trei rulări pe care le-a pornit cineva de mână”.
 *
 * @param int $zile Câte zile în urmă.
 * @return array Cifrele mișcării.
 */
function ccs_miscare( $zile = 7 ) {
	global $wpdb;
	$tabel = ccs_tabel( 'products' );
	$de_la = gmdate( 'Y-m-d H:i:s', strtotime( '-' . (int) $zile . ' days', (int) current_datetime()->format( 'U' ) ) );

	$ultima = $wpdb->get_row(
		"SELECT inceput, sfarsit, rezumat FROM " . ccs_tabel( 'runs' ) . " WHERE tip = 'catalog' AND stare = 'gata' ORDER BY id DESC LIMIT 1" // phpcs:ignore WordPress.DB.PreparedSQL
	);
	$rezumat_ultima = $ultima && $ultima->rezumat ? json_decode( $ultima->rezumat, true ) : array();

	// Prima preluare a scris tot catalogul deodată, deci toate produsele au `prima_vedere` în ziua aia. Fără
	// pragul ăsta, „produse noi în ultimele șapte zile” ar fi tot catalogul, în prima săptămână de funcționare.
	$prag = ccs_catalog_dupa_pornire();
	$de_la = max( $de_la, $prag );

	return array(
		'zile'          => (int) $zile,
		'ultima_rulare' => $ultima ? $ultima->inceput : '',
		'ultima'        => is_array( $rezumat_ultima ) ? $rezumat_ultima : array(),
		'de_la_pornire' => '' === $prag,
		'noi'           => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $tabel WHERE prima_vedere > %s", $de_la ) ), // phpcs:ignore WordPress.DB.PreparedSQL
		'iesite'        => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $tabel WHERE lipsa_runde > 0 AND ultima_vedere >= %s", $de_la ) ), // phpcs:ignore WordPress.DB.PreparedSQL
		'lipsesc_total' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $tabel WHERE lipsa_runde > 0" ), // phpcs:ignore WordPress.DB.PreparedSQL
		'in_catalog'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $tabel WHERE lipsa_runde = 0" ), // phpcs:ignore WordPress.DB.PreparedSQL
	);
}

/**
 * Momentul de după care un produs chiar e nou, nu doar scris la prima preluare.
 *
 * @return string Data, în format MySQL. Gol dacă n-a rulat nimic.
 */
function ccs_catalog_dupa_pornire() {
	global $wpdb;
	$prima = $wpdb->get_var(
		"SELECT sfarsit FROM " . ccs_tabel( 'runs' ) . " WHERE tip = 'catalog' AND stare = 'gata' ORDER BY id ASC LIMIT 1" // phpcs:ignore WordPress.DB.PreparedSQL
	);
	return $prima ? (string) $prima : '';
}

/**
 * Produsele intrate sau ieșite din catalog în ultimele zile.
 *
 * @param string $ce     'noi' sau 'iesite'.
 * @param int    $zile   Câte zile în urmă.
 * @param int    $limita Câte, cel mult.
 * @return array Rândurile.
 */
function ccs_miscare_lista( $ce, $zile = 7, $limita = 10 ) {
	global $wpdb;
	$tabel = ccs_tabel( 'products' );
	$de_la = gmdate( 'Y-m-d H:i:s', strtotime( '-' . (int) $zile . ' days', (int) current_datetime()->format( 'U' ) ) );

	$de_la = max( $de_la, ccs_catalog_dupa_pornire() );
	$unde  = 'noi' === $ce
		? $wpdb->prepare( 'prima_vedere > %s', $de_la )
		: $wpdb->prepare( 'lipsa_runde > 0 AND ultima_vedere >= %s', $de_la );

	return (array) $wpdb->get_results(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL
			"SELECT product_id, cod, nume, brand, cost, stoc, prima_vedere, ultima_vedere
			 FROM $tabel WHERE $unde ORDER BY " . ( 'noi' === $ce ? 'prima_vedere' : 'ultima_vedere' ) . " DESC LIMIT %d",
			(int) $limita
		)
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
