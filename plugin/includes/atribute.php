<?php
/**
 * Datele tehnice ale produselor, din fereastra de noapte a furnizorului.
 *
 * Endpoint-ul `criteria` răspunde, ca și `images`, doar între 19:00 și 05:00. Întoarce rânduri de forma
 * `{productId, code, value}`, unde `code` e numele atributului („Material”, „Culoare”, „Partea de montare”) și
 * `value` e valoarea lui.
 *
 * Măsurat pe sondajul din 14 octombrie 2026: **5.815 produse** au date tehnice, cu 31.544 de perechi și 381 de
 * nume distincte de atribut. Furnizorul nu trimite descrieri, deci astea sunt singurul conținut în afară de poze
 * pe care îl primim pentru un produs nou.
 *
 * ## De ce nu devin toate filtre
 *
 * Un atribut e util ca filtru în magazin doar dacă împarte produsele în grupuri. Cele 381 de nume nu sunt egale:
 * „Culoare” are 20 de valori pentru 688 de produse, deci grupează; „pt. cod original (OE)” are 1.531 de valori
 * pentru 1.577 de produse, deci e un identificator, iar ca filtru ar da o listă cu un produs în fiecare opțiune.
 *
 * Despărțirea se face pe **raportul** dintre numărul de valori distincte și numărul de produse, nu pe un prag
 * fix. Un prag fix respingea „Partea de montare”, care are 125 de valori dar pe 2.874 de produse, adică 4%, și e
 * exact genul de filtru pe care omul îl caută. Pe datele reale, regula alege 21 de atribute filtrabile din 381.
 *
 * Atributele filtrabile devin atribute globale WooCommerce (`pa_*`), care intră în filtrele din magazin. Restul
 * rămân atribute locale: se văd pe pagina produsului, dar nu produc filtre.
 *
 * @package conex-conect-sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Preia datele tehnice de la furnizor și le scrie în tabelul propriu.
 *
 * Ca la imagini: strângem tot în memorie și scriem o singură dată. `ccs_api_noapte()` nu întoarce niciodată date
 * parțiale, deci când ajungem la scriere avem lista întreagă și o putem înlocui pe cea veche fără să rămânem cu
 * jumătăți.
 *
 * @return array|WP_Error Rezumatul, sau eroare.
 */
function ccs_atribute_preia() {
	global $wpdb;

	$pe_produs = array();
	$randuri   = 0;

	$rezultat = ccs_api_noapte(
		'criteria',
		function ( $pagina ) use ( &$pe_produs, &$randuri ) {
			foreach ( $pagina as $r ) {
				$id      = isset( $r['productid'] ) ? (int) $r['productid'] : 0;
				$nume    = isset( $r['code'] ) ? trim( (string) $r['code'] ) : '';
				$valoare = isset( $r['value'] ) ? trim( (string) $r['value'] ) : '';
				if ( ! $id || '' === $nume || '' === $valoare ) {
					continue;
				}
				$randuri++;
				// Un produs poate primi același atribut de două ori de la furnizor. Păstrăm prima valoare.
				if ( ! isset( $pe_produs[ $id ][ $nume ] ) ) {
					$pe_produs[ $id ][ $nume ] = $valoare;
				}
			}
		}
	);

	if ( is_wp_error( $rezultat ) ) {
		return $rezultat;
	}
	if ( ! $pe_produs ) {
		return new WP_Error(
			'ccs_fara_atribute',
			__( 'Furnizorul n-a trimis nicio dată tehnică. Nu scriu nimic.', 'conex-conect-sync' )
		);
	}

	$rulare = ccs_rulare_start( 'atribute' );
	$tabel  = ccs_tabel( 'atribute' );
	$acum   = current_time( 'mysql' );

	$wpdb->query( "TRUNCATE TABLE $tabel" ); // phpcs:ignore WordPress.DB.PreparedSQL

	$valori = array();
	$date   = array();
	$scrise = 0;
	foreach ( $pe_produs as $id => $atribute ) {
		foreach ( $atribute as $nume => $valoare ) {
			$valori[] = '(%d,%s,%s,%s)';
			array_push( $date, $id, mb_substr( $nume, 0, 120 ), mb_substr( $valoare, 0, 255 ), $acum );
			$scrise++;

			if ( count( $valori ) >= 500 ) {
				$wpdb->query(
					$wpdb->prepare(
						// phpcs:ignore WordPress.DB.PreparedSQL
						"INSERT INTO $tabel (product_id, nume, valoare, vazut_la) VALUES " . implode( ',', $valori ),
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
				"INSERT INTO $tabel (product_id, nume, valoare, vazut_la) VALUES " . implode( ',', $valori ),
				$date
			)
		);
	}

	// Lista de atribute filtrabile se recalculează din datele noi, nu din cele de ieri.
	delete_transient( 'ccs_atribute_filtrabile' );

	$rezumat = array(
		'randuri' => $randuri,
		'produse' => count( $pe_produs ),
		'perechi' => $scrise,
	);
	ccs_rulare_stop( $rulare, 'gata', $rezumat );

	return $rezumat;
}

/**
 * Datele tehnice ale unui produs al furnizorului.
 *
 * @param int $product_id Id-ul la furnizor.
 * @return array nume => valoare.
 */
function ccs_atribute_produs( $product_id ) {
	global $wpdb;
	$tabel   = ccs_tabel( 'atribute' );
	$randuri = $wpdb->get_results(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL
			"SELECT nume, valoare FROM $tabel WHERE product_id = %d ORDER BY nume",
			(int) $product_id
		)
	);
	$iesire = array();
	foreach ( $randuri as $r ) {
		$iesire[ (string) $r->nume ] = (string) $r->valoare;
	}
	return $iesire;
}

/**
 * Câte date tehnice știm, și pentru câte produse.
 *
 * @return array Cifrele.
 */
function ccs_atribute_stare() {
	global $wpdb;
	$tabel = ccs_tabel( 'atribute' );
	$r     = $wpdb->get_row( "SELECT COUNT(*) AS perechi, COUNT(DISTINCT product_id) AS produse, COUNT(DISTINCT nume) AS nume, MAX(vazut_la) AS ultima FROM $tabel" ); // phpcs:ignore WordPress.DB.PreparedSQL
	return array(
		'perechi' => $r ? (int) $r->perechi : 0,
		'produse' => $r ? (int) $r->produse : 0,
		'nume'    => $r ? (int) $r->nume : 0,
		'ultima'  => $r ? (string) $r->ultima : '',
	);
}

/**
 * Numele atributelor care merită să devină filtre în magazin.
 *
 * Vezi comentariul fișierului pentru de ce raportul și nu un prag fix. Rezultatul se ține într-un transient,
 * fiindcă interogarea trece prin tot tabelul, iar lista se schimbă doar când se preiau date noi.
 *
 * @return array nume => array( produse, valori ).
 */
function ccs_atribute_filtrabile() {
	global $wpdb;

	$pastrat = get_transient( 'ccs_atribute_filtrabile' );
	if ( is_array( $pastrat ) ) {
		return $pastrat;
	}

	$setari = ccs_setari();
	$tabel  = ccs_tabel( 'atribute' );

	$randuri = $wpdb->get_results(
		"SELECT nume, COUNT(DISTINCT product_id) AS produse, COUNT(DISTINCT valoare) AS valori
		 FROM $tabel GROUP BY nume" // phpcs:ignore WordPress.DB.PreparedSQL
	);

	$min_produse = max( 1, (int) $setari['atribut_min_produse'] );
	$max_raport  = (float) $setari['atribut_max_raport'];
	$plafon      = max( 1, (int) $setari['atribut_plafon_valori'] );

	$iesire = array();
	foreach ( $randuri as $r ) {
		$produse = (int) $r->produse;
		$valori  = (int) $r->valori;
		if ( $produse < $min_produse || $valori > $plafon ) {
			continue;
		}
		if ( $valori / $produse > $max_raport ) {
			continue;
		}
		$iesire[ (string) $r->nume ] = array(
			'produse' => $produse,
			'valori'  => $valori,
		);
	}

	/**
	 * Permite schimbarea listei de atribute filtrabile.
	 *
	 * @param array $iesire Atributele.
	 */
	$iesire = apply_filters( 'ccs_atribute_filtrabile', $iesire );

	set_transient( 'ccs_atribute_filtrabile', $iesire, DAY_IN_SECONDS );

	return $iesire;
}

/**
 * Slugul sub care intră un atribut al furnizorului.
 *
 * Harta nume -> slug stă într-o opțiune și nu se recalculează: odată ce un atribut a primit un slug, el rămâne,
 * altfel valorile deja scrise pe produse ar rămâne legate de o taxonomie pe care n-o mai scrie nimeni.
 *
 * `$scrie` există pentru verificarea de sănătate, care vrea să știe unde *ar* intra un nume fără să rezerve
 * nimic. Fără el, simpla verificare ar schimba starea pe care o verifică.
 *
 * @param string $nume  Numele de la furnizor.
 * @param bool   $scrie Dacă memorăm în hartă un slug nou.
 * @return string Slugul, fără prefixul `pa_`.
 */
function ccs_atribut_slug( $nume, $scrie = true ) {
	$harta = (array) get_option( 'ccs_atribute_slug', array() );
	if ( isset( $harta[ $nume ] ) ) {
		return (string) $harta[ $nume ];
	}

	$slug = substr( sanitize_title( $nume ), 0, 28 );
	if ( '' === $slug ) {
		$slug = 'atribut-' . substr( md5( $nume ), 0, 8 );
	}
	// Slug deja luat de alt nume: îi punem o coadă scurtă și stabilă.
	if ( in_array( $slug, $harta, true ) ) {
		$slug = substr( $slug, 0, 23 ) . '-' . substr( md5( $nume ), 0, 4 );
	}

	if ( $scrie ) {
		$harta[ $nume ] = $slug;
		update_option( 'ccs_atribute_slug', $harta, false );
	}

	return $slug;
}

/**
 * Taxonomia WooCommerce pentru un atribut, creată dacă nu există.
 *
 * Numele unui atribut global WooCommerce are cel mult 28 de caractere, fiindcă `pa_` plus numele trebuie să intre
 * în cele 32 de caractere ale unei taxonomii WordPress. Numele furnizorului sunt mai lungi de atât („Articol
 * extins/Informatii de extindere”), deci se taie. Două nume diferite pot da același slug tăiat, așa că ținem
 * minte ce slug a primit fiecare nume și adăugăm un sufix când se ciocnesc. Harta se păstrează într-o opțiune,
 * ca slug-ul unui atribut să nu se schimbe de la o rulare la alta: dacă s-ar schimba, produsele vechi ar rămâne
 * legate de o taxonomie pe care nimic n-o mai scrie.
 *
 * @param string $nume Numele atributului, așa cum vine de la furnizor.
 * @return string|WP_Error Numele taxonomiei (`pa_...`), sau eroare.
 */
function ccs_atribut_taxonomie( $nume ) {
	if ( ! function_exists( 'wc_attribute_taxonomy_name' ) ) {
		return new WP_Error( 'ccs_fara_woo', __( 'WooCommerce nu e activ.', 'conex-conect-sync' ) );
	}

	$slug      = ccs_atribut_slug( $nume );
	$taxonomie = wc_attribute_taxonomy_name( $slug );

	if ( ! wc_attribute_taxonomy_id_by_name( $slug ) ) {
		$creat = wc_create_attribute(
			array(
				'name'         => mb_substr( $nume, 0, 120 ),
				'slug'         => $slug,
				'type'         => 'select',
				'order_by'     => 'menu_order',
				'has_archives' => false,
			)
		);
		if ( is_wp_error( $creat ) ) {
			return $creat;
		}
		// Ținem minte care atribute le-am făcut noi. Fără lista asta nu se poate deosebi un atribut al nostru de
		// unul pe care și l-a făcut magazinul cu același nume, iar deosebirea contează: în al doilea caz scriem
		// valorile furnizorului peste un vocabular ales de om.
		$create = (array) get_option( 'ccs_atribute_create', array() );
		if ( ! in_array( $slug, $create, true ) ) {
			$create[] = $slug;
			update_option( 'ccs_atribute_create', $create, false );
		}
		// WooCommerce înregistrează taxonomiile de atribut la `init`, care a trecut demult când rulăm din CLI sau
		// dintr-un job. Fără înregistrarea asta pe loc, `wp_set_object_terms()` de mai jos ar eșua tăcut.
		delete_transient( 'wc_attribute_taxonomies' );
	}

	if ( ! taxonomy_exists( $taxonomie ) ) {
		register_taxonomy(
			$taxonomie,
			'product',
			array(
				'hierarchical' => false,
				'show_ui'      => false,
				'query_var'    => true,
				'rewrite'      => false,
			)
		);
	}

	return $taxonomie;
}

/**
 * Atributele unui produs al furnizorului, gata de pus pe un produs WooCommerce.
 *
 * Întoarce două liste, fiindcă se scriu diferit: cele globale au nevoie de termeni în taxonomie, cele locale sunt
 * doar perechi nume-valoare pe produs.
 *
 * @param int   $product_id Id-ul la furnizor.
 * @param array $peste      Atribute puse de noi, care intră oricum: Brand, Cod producător.
 * @return array array( 'globale' => nume => valoare, 'locale' => nume => valoare ).
 */
function ccs_atribute_pentru_produs( $product_id, $peste = array() ) {
	$toate      = ccs_atribute_produs( $product_id );
	$filtrabile = ccs_atribute_filtrabile();

	$globale = array();
	$locale  = array();
	foreach ( $toate as $nume => $valoare ) {
		if ( isset( $filtrabile[ $nume ] ) ) {
			$globale[ $nume ] = $valoare;
		} else {
			$locale[ $nume ] = $valoare;
		}
	}

	// Brandul și codul producătorului vin din catalog, nu din datele tehnice, dar stau pe produs lângă ele.
	foreach ( (array) $peste as $nume => $valoare ) {
		if ( '' !== (string) $valoare ) {
			$locale[ $nume ] = (string) $valoare;
		}
	}

	return array(
		'globale' => $globale,
		'locale'  => $locale,
	);
}

/**
 * Jobul de noapte pentru datele tehnice.
 *
 * @return void
 */
function ccs_job_atribute() {
	if ( ! ccs_api_fereastra_deschisa() ) {
		ccs_jurnal( 'avertisment', 'Fereastra de noapte e închisă. Sar peste preluarea datelor tehnice.' );
		return;
	}

	$r = ccs_atribute_preia();
	if ( is_wp_error( $r ) ) {
		ccs_jurnal( 'eroare', 'Preluarea datelor tehnice a eșuat: ' . $r->get_error_message() );
		return;
	}

	ccs_jurnal( 'info', sprintf( '%d date tehnice pentru %d produse.', $r['perechi'], $r['produse'] ) );

	if ( function_exists( 'ccs_admin_uita' ) ) {
		ccs_admin_uita();
	}
}
add_action( 'ccs_job_atribute', 'ccs_job_atribute' );

/**
 * Înregistrează jobul de noapte pentru datele tehnice.
 *
 * Se programează la 19:25, după imagini: amândouă citesc din aceeași fereastră, iar rata de eșec a furnizorului
 * crește pe parcursul nopții, deci nu are rost să concureze între ele.
 *
 * @param array $joburi Joburile.
 * @return array Joburile, cu al nostru.
 */
function ccs_atribute_job( $joburi ) {
	$joburi['ccs_job_atribute'] = array(
		'nume'         => __( 'Datele tehnice ale produselor', 'conex-conect-sync' ),
		'interval'     => DAY_IN_SECONDS,
		'doar_noaptea' => true,
		'minut'        => 25,
	);
	return $joburi;
}
add_filter( 'ccs_joburi', 'ccs_atribute_job' );
