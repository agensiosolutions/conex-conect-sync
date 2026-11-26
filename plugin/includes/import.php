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
		$piedici['fara_poza'] = __( 'Furnizorul nu transmite nicio imagine. Produsele fără imagine nu se importă.', 'conex-conect-sync' );
	}
	if ( $cost <= 0 ) {
		$piedici['fara_cost'] = __( 'Fără cost de achiziție nu se poate calcula prețul de vânzare.', 'conex-conect-sync' );
	}
	if ( $stoc <= 0 ) {
		$piedici['fara_stoc'] = __( 'Nu figurează pe stoc la furnizor.', 'conex-conect-sync' );
	}
	if ( ! $term ) {
		$piedici['fara_categorie'] = __( 'Nu are o categorie din magazin asociată.', 'conex-conect-sync' );
	}
	if ( '' === $nume ) {
		$piedici['fara_nume'] = __( 'Produsul n-are nume.', 'conex-conect-sync' );
	}

	// Un cod care e deja pe site înseamnă că produsul nu e nou: ar fi un duplicat, nu un import.
	$existent = $cod ? ccs_import_produs_dupa_sku( $cod ) : 0;
	if ( $existent ) {
		$piedici['exista_deja'] = sprintf(
			/* translators: %d: id-ul produsului de pe site. */
			__( 'Codul există deja pe site, la produsul %d.', 'conex-conect-sync' ),
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

/**
 * Creează pe site produsele pe care furnizorul le are și magazinul nu.
 *
 * Singurul loc din plugin care creează produse. Totul trece prin CRUD-ul WooCommerce, din același motiv pentru care
 * o face și `aplica.php`: `update_post_meta` ar lăsa `wc_product_meta_lookup` în urmă, iar produsul nou n-ar apărea
 * la filtrare și la sortare în magazin.
 *
 * Porțile, în ordinea în care se verifică:
 *   1. `mod_raport` oprit, exact ca la aplicarea de prețuri;
 *   2. o limită explicită, sau `--forteaza`: o rulare care creează mii de produse nu se dă din greșeală;
 *   3. pentru fiecare produs, `ccs_import_pregateste()` să nu aibă nicio piedică.
 *
 * **Categoria e obligatorie.** Un produs fără categorie din magazin nu se importă, oricâte alte condiții ar
 * îndeplini. Verificarea e făcută de două ori, și asta e intenționat: o dată ca piedică în `ccs_import_pregateste()`,
 * și încă o dată aici, chiar înainte de scriere. Dacă cineva slăbește vreodată lista de piedici, poarta asta tot
 * ține. Un produs aterizat în categoria greșită, sau în niciuna, e mai greu de găsit și de reparat decât unul care
 * n-a fost creat.
 *
 * Produsele se creează ca **ciornă**, nu publicate. Publicarea e o hotărâre a magazinului, nu a importului.
 *
 * @param array $opt limita, proba, publica, forteaza, coduri, raport.
 * @return array|WP_Error Rezumatul, sau eroare.
 */
function ccs_import_executa( $opt = array() ) {
	global $wpdb;
	$opt = wp_parse_args(
		$opt,
		array(
			'limita'   => 0,
			'proba'    => false,
			'publica'  => false,
			'forteaza' => false,
			'coduri'   => array(),
			'raport'   => null, // Callback de progres: function( $facut, $total ).
		)
	);

	if ( ! function_exists( 'wc_get_product' ) ) {
		return new WP_Error( 'ccs_fara_woo', __( 'WooCommerce nu e activ. Fără el nu se poate crea niciun produs.', 'conex-conect-sync' ) );
	}

	$setari = ccs_setari();
	if ( ! $opt['proba'] && $setari['mod_raport'] ) {
		return new WP_Error(
			'ccs_mod_raport',
			__( 'Modul raport e pornit, deci nu creez nimic. Oprește-l cu `wp ccs setare mod_raport 0` când ești gata.', 'conex-conect-sync' )
		);
	}

	$candidati = ccs_import_candidati();
	$lista     = $candidati['lista'];

	// Import punctual, pe coduri date de om: așa se probează câteva produse înainte de o rulare mare.
	if ( $opt['coduri'] ) {
		$cerute = array_map( 'strval', (array) $opt['coduri'] );
		$lista  = array_values(
			array_filter(
				$lista,
				function ( $p ) use ( $cerute ) {
					return in_array( (string) $p->cod, $cerute, true );
				}
			)
		);
	}

	if ( ! $opt['proba'] && ! $opt['limita'] && ! $opt['forteaza'] && ! $opt['coduri'] ) {
		return new WP_Error(
			'ccs_fara_limita',
			sprintf(
				/* translators: %d: câte produse s-ar crea. */
				__( 'S-ar crea %d produse dintr-o dată. Dă o limită cu `--limita=50`, sau `--forteaza` dacă chiar vrei tot.', 'conex-conect-sync' ),
				count( $lista )
			)
		);
	}

	if ( $opt['limita'] ) {
		$lista = array_slice( $lista, 0, (int) $opt['limita'] );
	}

	$rezumat = array(
		'rulare'   => 0,
		'de_creat' => count( $lista ),
		'create'   => 0,
		'sarite'   => 0,
		'esuate'   => 0,
		'poze'     => 0,
		'proba'    => (bool) $opt['proba'],
		'motive'   => array(),
		'ids'      => array(),
	);

	if ( $opt['proba'] ) {
		foreach ( $lista as $p ) {
			$pregatit = ccs_import_pregateste( $p );
			if ( $pregatit['gata'] ) {
				$rezumat['create']++;
			} else {
				$rezumat['sarite']++;
				foreach ( array_keys( $pregatit['piedici'] ) as $motiv ) {
					$rezumat['motive'][ $motiv ] = ( $rezumat['motive'][ $motiv ] ?? 0 ) + 1;
				}
			}
		}
		return $rezumat;
	}

	$rulare            = ccs_rulare_start( 'import' );
	$rezumat['rulare'] = $rulare;
	$tabel_modificari  = ccs_tabel( 'modificari' );
	$acum              = current_time( 'mysql' );
	$lot               = max( 1, (int) $setari['lot_aplicare'] );
	$facut             = 0;

	foreach ( $lista as $p ) {
		$pregatit = ccs_import_pregateste( $p );

		if ( ! $pregatit['gata'] ) {
			$rezumat['sarite']++;
			foreach ( array_keys( $pregatit['piedici'] ) as $motiv ) {
				$rezumat['motive'][ $motiv ] = ( $rezumat['motive'][ $motiv ] ?? 0 ) + 1;
			}
			continue;
		}

		// Poarta de categorie, verificată a doua oară. Vezi comentariul funcției.
		$term = (int) $pregatit['date']['categorie'];
		if ( ! $term || ! term_exists( $term, 'product_cat' ) ) {
			$rezumat['sarite']++;
			$rezumat['motive']['fara_categorie'] = ( $rezumat['motive']['fara_categorie'] ?? 0 ) + 1;
			continue;
		}

		$creat = ccs_import_creeaza( $pregatit, $opt['publica'] );

		if ( is_wp_error( $creat ) ) {
			$rezumat['esuate']++;
			ccs_jurnal( 'avertisment', $creat->get_error_message() );
			continue;
		}

		$rezumat['create']++;
		$rezumat['poze']  += (int) $creat['poze'];
		$rezumat['ids'][]  = (int) $creat['wc_id'];

		// Legătura se scrie acum, nu la următoarea potrivire: produsul nou e legat prin construcție, nu ghicit.
		$wpdb->replace(
			ccs_tabel( 'links' ),
			array(
				'wc_product_id' => (int) $creat['wc_id'],
				'product_id'    => (int) $pregatit['product_id'],
				'metoda'        => 'import',
				'incredere'     => 'A',
				'nume_scor'     => 100,
				'confirmat_la'  => $acum,
			)
		);

		// Scris ca modificare, ca `wp ccs revino <rulare>` să poată trimite produsele la coșul de gunoi.
		$wpdb->insert(
			$tabel_modificari,
			array(
				'rulare_id'     => $rulare,
				'wc_product_id' => (int) $creat['wc_id'],
				'camp'          => 'creat',
				'vechi'         => '',
				'nou'           => (string) $pregatit['date']['sku'],
				'aplicat_la'    => $acum,
			)
		);

		$facut++;
		if ( is_callable( $opt['raport'] ) ) {
			call_user_func( $opt['raport'], $facut, count( $lista ) );
		}
		if ( 0 === $facut % $lot && function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
	}

	ccs_rulare_stop( $rulare, $rezumat['esuate'] ? 'gata_cu_erori' : 'gata', $rezumat );
	ccs_jurnal( 'info', sprintf( 'Import: %d produse create, %d poze.', $rezumat['create'], $rezumat['poze'] ) );

	if ( function_exists( 'ccs_admin_uita' ) ) {
		ccs_admin_uita();
	}

	return $rezumat;
}

/**
 * Creează un singur produs, cu pozele lui.
 *
 * Produsul se salvează întâi fără poze, ca să aibă un id de care să atârne fișierele, apoi pozele se descarcă și se
 * leagă. Dacă nu reușește nicio poză, produsul se duce la coșul de gunoi și rularea îl numără ca eșuat: regula
 * „produsele fără imagine nu se importă” trebuie să țină și când descărcarea cade, nu doar când furnizorul n-are
 * poze.
 *
 * @param array $pregatit Ce întoarce `ccs_import_pregateste()`.
 * @param bool  $publica  Publicat în loc de ciornă.
 * @return array|WP_Error array( wc_id, poze ), sau eroare.
 */
function ccs_import_creeaza( $pregatit, $publica = false ) {
	$d = $pregatit['date'];

	$produs = new WC_Product_Simple();
	$produs->set_name( $d['nume'] );
	$produs->set_sku( $d['sku'] );
	$produs->set_regular_price( (string) wc_format_decimal( $d['pret'], wc_get_price_decimals() ) );
	$produs->set_manage_stock( true );
	$produs->set_stock_quantity( (int) $d['stoc'] );
	$produs->set_stock_status( $d['stare_stoc'] );
	$produs->set_category_ids( array( (int) $d['categorie'] ) );
	$produs->set_status( $publica ? 'publish' : 'draft' );
	$produs->set_catalog_visibility( 'visible' );

	// Prețul calculat e final, cu TVA înăuntru. Produsele existente ale magazinului sunt toate pe `none`, iar un
	// produs nou lăsat pe `taxable` ar primi TVA încă o dată la coș.
	$produs->set_tax_status( 'none' );

	// Furnizorul nu trimite descrieri, măsurat pe tot catalogul. Produsul rămâne fără, iar asta se vede în panou.
	$produs->set_description( (string) $d['descriere'] );

	if ( $d['atribute'] ) {
		$atribute = array();
		foreach ( $d['atribute'] as $nume => $valoare ) {
			$a = new WC_Product_Attribute();
			$a->set_name( $nume );
			$a->set_options( array( $valoare ) );
			$a->set_visible( true );
			$a->set_variation( false );
			$atribute[] = $a;
		}
		$produs->set_attributes( $atribute );
	}

	$wc_id = $produs->save();
	if ( ! $wc_id ) {
		return new WP_Error(
			'ccs_import_nescris',
			sprintf(
				/* translators: %s: codul produsului. */
				__( 'Produsul %s nu s-a putut crea.', 'conex-conect-sync' ),
				$d['sku']
			)
		);
	}

	$poze = ccs_import_poze( $wc_id, (array) $d['poze'], $d['nume'], $d['sku'] );

	if ( ! $poze ) {
		wp_trash_post( $wc_id );
		return new WP_Error(
			'ccs_import_fara_poza',
			sprintf(
				/* translators: %s: codul produsului. */
				__( 'Niciuna dintre pozele produsului %s nu s-a putut descărca. L-am trimis la coșul de gunoi.', 'conex-conect-sync' ),
				$d['sku']
			)
		);
	}

	return array(
		'wc_id' => (int) $wc_id,
		'poze'  => $poze,
	);
}

/**
 * Numele sub care se salvează o poză de produs.
 *
 * Furnizorul trimite fișiere numite `4e668598-bda0-4478-bfbb-af20f332d9b6.jpg`. Pentru un magazin, un nume de UUID
 * nu aduce nimic din Google Images, iar pozele sunt singurul conținut pe care îl primim de la Conex, fiindcă
 * descrieri nu trimite. Numele se face deci din denumirea produsului, plus codul, ca să rămână de urmărit de unde
 * vine poza, plus un număr de la a doua încolo.
 *
 * Extensia se ia din URL, dar numai dacă e una pe care o așteptăm: un URL fără extensie, sau cu una inventată, ar
 * produce altfel fișiere pe care WordPress le respinge la încărcare.
 *
 * @param string $nume     Denumirea produsului.
 * @param string $sku      Codul.
 * @param int    $pozitia  Poziția pozei, de la 0.
 * @param string $url      URL-ul de la furnizor, pentru extensie.
 * @param int    $rezerva  Id-ul produsului, folosit dacă denumirea și codul dau un nume gol.
 * @return string Numele fișierului, cu extensie.
 */
function ccs_import_nume_fisier( $nume, $sku, $pozitia, $url, $rezerva = 0 ) {
	// 60 de caractere: destul cât să se citească despre ce produs e vorba, fără nume absurd de lungi.
	$baza = substr( sanitize_title( (string) $nume ), 0, 60 );
	if ( '' !== (string) $sku ) {
		$baza = ( '' !== $baza ? $baza . '-' : '' ) . sanitize_title( (string) $sku );
	}
	if ( '' === $baza ) {
		$baza = 'produs-' . (int) $rezerva;
	}

	$extensie = strtolower( (string) pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
	if ( ! in_array( $extensie, array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true ) ) {
		$extensie = 'jpg';
	}

	return $baza . ( $pozitia ? '-' . ( (int) $pozitia + 1 ) : '' ) . '.' . $extensie;
}

/**
 * Descarcă pozele unui produs și le leagă: prima ca imagine principală, restul în galerie.
 *
 * Nu folosim `media_sideload_image()`, deși ar fi fost o linie: el păstrează numele fișierului din URL, iar
 * furnizorul le dă nume de forma `4e668598-bda0-4478-bfbb-af20f332d9b6.jpg`. Pentru un magazin, un fișier cu nume
 * de UUID și fără text alternativ nu aduce nimic din Google Images, iar pozele sunt singurul lucru pe care îl
 * primim de la furnizor, fiindcă descrieri nu trimite. Deci descărcăm singuri și punem numele produsului în
 * fișier și în `alt`.
 *
 * @param int    $wc_id  Produsul.
 * @param array  $urluri URL-urile, în ordine.
 * @param string $nume   Denumirea produsului, pentru numele fișierului și textul alternativ.
 * @param string $sku    Codul, lipit la numele fișierului ca să rămână de urmărit de unde vine poza.
 * @return int Câte poze au intrat.
 */
function ccs_import_poze( $wc_id, $urluri, $nume = '', $sku = '' ) {
	if ( ! function_exists( 'wp_handle_sideload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	$nume = '' !== $nume ? $nume : get_the_title( $wc_id );
	$ids  = array();
	foreach ( array_values( $urluri ) as $i => $url ) {
		$temporar = download_url( $url );
		if ( is_wp_error( $temporar ) ) {
			ccs_jurnal( 'avertisment', sprintf( 'Poza %s nu s-a descărcat: %s', $url, $temporar->get_error_message() ) );
			continue;
		}

		// `wp_handle_sideload()` primește primul argument prin referință, deci nu se poate da un array literal:
		// în PHP 8 e eroare fatală, nu avertisment. Trebuie să fie o variabilă.
		$fisier = array(
			'name'     => ccs_import_nume_fisier( $nume, $sku, $i, $url, $wc_id ),
			'tmp_name' => $temporar,
		);
		$asezat = wp_handle_sideload( $fisier, array( 'test_form' => false ) );

		if ( ! empty( $asezat['error'] ) ) {
			// `wp_handle_sideload` mută fișierul când reușește; când cade, rămâne al nostru de șters.
			if ( file_exists( $temporar ) ) {
				wp_delete_file( $temporar );
			}
			ccs_jurnal( 'avertisment', sprintf( 'Poza %s nu s-a putut salva: %s', $url, $asezat['error'] ) );
			continue;
		}

		$id = wp_insert_attachment(
			array(
				'post_mime_type' => $asezat['type'],
				'post_title'     => $nume,
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$asezat['file'],
			$wc_id
		);
		if ( is_wp_error( $id ) || ! $id ) {
			ccs_jurnal( 'avertisment', sprintf( 'Poza %s nu s-a putut înregistra în bibliotecă.', $url ) );
			continue;
		}

		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $asezat['file'] ) );
		update_post_meta( $id, '_wp_attachment_image_alt', $nume );

		$ids[] = (int) $id;
	}

	if ( ! $ids ) {
		return 0;
	}

	$produs = wc_get_product( $wc_id );
	if ( ! $produs ) {
		return 0;
	}
	$produs->set_image_id( array_shift( $ids ) );
	if ( $ids ) {
		$produs->set_gallery_image_ids( $ids );
	}
	$produs->save();

	return count( $ids ) + 1;
}
