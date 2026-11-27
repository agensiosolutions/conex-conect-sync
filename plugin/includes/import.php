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

	$context = array(
		'brand' => $brand,
		'cat1'  => (string) ( $p['categorie1'] ?? $p['cat1'] ?? '' ),
		'cat2'  => (string) ( $p['categorie2'] ?? $p['cat2'] ?? '' ),
		'cat3'  => (string) ( $p['categorie3'] ?? $p['cat3'] ?? '' ),
	);
	$setari = ccs_setari_pret( $context );
	$pret   = ccs_pret( $cost, $taxa, $context );
	$term   = ccs_categorie_pentru(
		array(
			'cat1' => $p['categorie1'] ?? $p['cat1'] ?? '',
			'cat2' => $p['categorie2'] ?? $p['cat2'] ?? '',
			'cat3' => $p['categorie3'] ?? $p['cat3'] ?? '',
			'nume' => $nume,
		)
	);
	$poze = $pid ? ccs_imagini_produs( $pid, (int) $setari['poze_per_produs'] ) : array();

	// O singură dată: și datele produsului, și explicația de mai jos se uită la aceeași listă, iar la un import de
	// o mie de produse o a doua interogare pe fiecare s-ar simți.
	$atribute = $pid
		? ccs_atribute_pentru_produs(
			$pid,
			array(
				'Brand'          => $brand,
				'Cod producător' => $mpn,
			)
		)
		: array( 'globale' => array(), 'locale' => array_filter( array( 'Brand' => $brand, 'Cod producător' => $mpn ) ) );

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
			// Pentru numele pozelor și pentru textul alternativ. Nu ajung pe produs ca atare: marca are
			// deja un atribut al ei, iar codul de producător stă în datele tehnice.
			'brand'         => $brand,
			'mpn'           => $mpn,
			'pret'          => $pret,
			'gestiune_stoc' => true,
			'stoc'          => $stoc,
			'stare_stoc'    => $stoc > 0 ? 'instock' : 'outofstock',
			'categorie'     => $term,
			'poze'          => $poze,
			'atribute'      => $atribute,
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
			'atribute'  => ccs_import_motiv_atribute( $atribute ),
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
 * Catalogul furnizorului, răsfoibil: filtrat, căutat și paginat.
 *
 * Oferta promite „filtrezi după categorie, brand și stoc, vezi ce nu ai pe site și imporți cu un click”, și o
 * repetă ca Decizia 3. Până acum panoul arăta numai cifre și o previzualizare a primelor produse, deci omul nu
 * avea de unde alege.
 *
 * Interogarea se uită numai la produsele furnizorului **care nu sunt legate de niciun produs de pe site**: alea
 * sunt singurele care se pot importa. Numărul de poze vine dintr-un subselect, nu dintr-un `JOIN` cu `GROUP BY`,
 * ca paginarea să rămână corectă: un produs cu patru poze ar fi apărut de patru ori.
 *
 * @param array $arg cautare, brand, cale, doar_importabile, pagina, pe_pagina.
 * @return array randuri, total, pagini, branduri, cai.
 */
function ccs_catalog_rasfoire( $arg = array() ) {
	global $wpdb;

	$arg = wp_parse_args(
		$arg,
		array(
			'cautare'          => '',
			'brand'            => '',
			'cale'             => '',
			'doar_importabile' => false,
			'pagina'           => 1,
			'pe_pagina'        => 24,
		)
	);

	$tp = ccs_tabel( 'products' );
	$tl = ccs_tabel( 'links' );
	$ti = ccs_tabel( 'imagini' );

	$unde   = array( 'l.product_id IS NULL' );
	$valori = array();

	if ( '' !== trim( (string) $arg['cautare'] ) ) {
		$cauta    = '%' . $wpdb->esc_like( trim( (string) $arg['cautare'] ) ) . '%';
		$unde[]   = '( c.nume LIKE %s OR c.cod LIKE %s OR c.mpn LIKE %s )';
		$valori[] = $cauta;
		$valori[] = $cauta;
		$valori[] = $cauta;
	}
	if ( '' !== (string) $arg['brand'] ) {
		$unde[]   = 'c.brand = %s';
		$valori[] = (string) $arg['brand'];
	}
	if ( '' !== (string) $arg['cale'] ) {
		// Calea e „cat1 / cat2 / cat3”, construită la fel ca în `ccs_categorie_cale()`.
		$unde[]   = "TRIM(BOTH ' / ' FROM CONCAT_WS(' / ', c.cat1, c.cat2, c.cat3)) = %s";
		$valori[] = (string) $arg['cale'];
	}
	if ( $arg['doar_importabile'] ) {
		$unde[] = 'c.cost > 0 AND c.stoc > 0';
		$unde[] = "EXISTS ( SELECT 1 FROM $ti i WHERE i.product_id = c.product_id )";
	}

	$filtru = implode( ' AND ', $unde );
	$baza   = "FROM $tp c LEFT JOIN $tl l ON l.product_id = c.product_id WHERE $filtru";

	$total = (int) ( $valori
		// phpcs:ignore WordPress.DB.PreparedSQL
		? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) $baza", $valori ) )
		// phpcs:ignore WordPress.DB.PreparedSQL
		: $wpdb->get_var( "SELECT COUNT(*) $baza" ) );

	$pe_pagina = max( 1, (int) $arg['pe_pagina'] );
	$pagini    = max( 1, (int) ceil( $total / $pe_pagina ) );
	$pagina    = min( max( 1, (int) $arg['pagina'] ), $pagini );
	$sari      = ( $pagina - 1 ) * $pe_pagina;

	$select = "SELECT c.product_id, c.cod, c.mpn, c.nume, c.brand, c.cat1, c.cat2, c.cat3, c.cost, c.taxa_eco, c.stoc,
		( SELECT COUNT(*) FROM $ti i WHERE i.product_id = c.product_id ) AS poze
		$baza ORDER BY c.stoc DESC, c.nume LIMIT %d OFFSET %d";

	$randuri = $wpdb->get_results(
		// phpcs:ignore WordPress.DB.PreparedSQL
		$wpdb->prepare( $select, array_merge( $valori, array( $pe_pagina, $sari ) ) )
	);

	return array(
		'randuri'   => (array) $randuri,
		'total'     => $total,
		'pagina'    => $pagina,
		'pagini'    => $pagini,
		'pe_pagina' => $pe_pagina,
	);
}

/**
 * Brandurile și căile pe care le are furnizorul printre produsele nelegate.
 *
 * Separat de răsfoire, și păstrat într-un transient: listele astea se schimbă o dată la o preluare de catalog,
 * nu la fiecare apăsare de filtru, iar `GROUP BY` peste douăzeci de mii de rânduri la fiecare încărcare de pagină
 * se simte.
 *
 * @return array branduri, cai.
 */
function ccs_catalog_filtre() {
	global $wpdb;

	$pastrat = get_transient( 'ccs_catalog_filtre' );
	if ( is_array( $pastrat ) ) {
		return $pastrat;
	}

	$tp = ccs_tabel( 'products' );
	$tl = ccs_tabel( 'links' );

	$branduri = $wpdb->get_results(
		"SELECT c.brand AS nume, COUNT(*) AS n
		 FROM $tp c LEFT JOIN $tl l ON l.product_id = c.product_id
		 WHERE l.product_id IS NULL AND c.brand != ''
		 GROUP BY c.brand ORDER BY n DESC LIMIT 60" // phpcs:ignore WordPress.DB.PreparedSQL
	);

	$cai = $wpdb->get_results(
		"SELECT TRIM(BOTH ' / ' FROM CONCAT_WS(' / ', c.cat1, c.cat2, c.cat3)) AS nume, COUNT(*) AS n
		 FROM $tp c LEFT JOIN $tl l ON l.product_id = c.product_id
		 WHERE l.product_id IS NULL
		 GROUP BY nume HAVING nume != '' ORDER BY n DESC LIMIT 60" // phpcs:ignore WordPress.DB.PreparedSQL
	);

	$iesire = array(
		'branduri' => (array) $branduri,
		'cai'      => (array) $cai,
	);

	set_transient( 'ccs_catalog_filtre', $iesire, HOUR_IN_SECONDS );

	return $iesire;
}

/**
 * Ia lacătul de scriere, apoi importă.
 *
 * Două operații care schimbă produse nu pot rula în paralel: importul automat de la 19:40 și jobul de catalog
 * de la fiecare jumătate de oră se suprapuneau. Proba nu cere lacătul, fiindcă nu scrie nimic.
 *
 * @param array $opt Vezi `ccs_import_executa_lucreaza()`.
 * @return array|WP_Error
 */
function ccs_import_executa( $opt = array() ) {
	if ( ! empty( $opt['proba'] ) ) {
		return ccs_import_executa_lucreaza( $opt );
	}
	return ccs_cu_lacat(
		__( 'importul de produse noi', 'conex-conect-sync' ),
		function () use ( $opt ) {
			return ccs_import_executa_lucreaza( $opt );
		}
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
function ccs_import_executa_lucreaza( $opt = array() ) {
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

	$atribute = ccs_import_atribute_woo( $d['atribute'] );
	if ( $atribute ) {
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

	// Termenii atributelor globale se pun după salvare: `wp_set_object_terms()` are nevoie de un id de produs.
	ccs_import_atribute_termeni( $wc_id, $d['atribute'] );

	$poze = ccs_import_poze( $wc_id, (array) $d['poze'], $d );

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
 * Explicația pe scurt a datelor tehnice.
 *
 * @param array $atribute array( 'globale' => ..., 'locale' => ... ).
 * @return string Explicația.
 */
function ccs_import_motiv_atribute( $atribute ) {
	$globale = count( (array) ( $atribute['globale'] ?? array() ) );
	$locale  = count( (array) ( $atribute['locale'] ?? array() ) );
	if ( ! $globale && ! $locale ) {
		return __( 'furnizorul n-are date tehnice pentru produsul ăsta', 'conex-conect-sync' );
	}

	return sprintf(
		/* translators: 1: câte date tehnice în total, 2: câte dintre ele devin filtre. */
		__( '%1$d date tehnice de la furnizor, %2$d dintre ele intră în filtrele magazinului', 'conex-conect-sync' ),
		$globale + $locale,
		$globale
	);
}

/**
 * Atributele produsului, în forma pe care o cere WooCommerce.
 *
 * Cele filtrabile devin atribute globale (`pa_*`), legate de o taxonomie, fiindcă numai alea produc filtre în
 * magazin. Restul rămân atribute locale: se văd pe pagina produsului, dar nu filtrează nimic. Despărțirea o face
 * `ccs_atribute_pentru_produs()`; aici doar le punem în obiectele WooCommerce.
 *
 * Dacă o taxonomie nu se poate crea, atributul coboară la local în loc să se piardă: o dată tehnică văzută pe
 * pagina produsului e mai bună decât una dispărută.
 *
 * @param array $atribute array( 'globale' => ..., 'locale' => ... ).
 * @return array Obiecte `WC_Product_Attribute`.
 */
function ccs_import_atribute_woo( $atribute ) {
	$iesire  = array();
	$pozitie = 0;
	$locale  = (array) ( $atribute['locale'] ?? array() );

	foreach ( (array) ( $atribute['globale'] ?? array() ) as $nume => $valoare ) {
		$taxonomie = ccs_atribut_taxonomie( $nume );
		if ( is_wp_error( $taxonomie ) ) {
			ccs_jurnal( 'avertisment', sprintf( 'Atributul „%s” rămâne local: %s', $nume, $taxonomie->get_error_message() ) );
			$locale[ $nume ] = $valoare;
			continue;
		}

		$a = new WC_Product_Attribute();
		$a->set_id( wc_attribute_taxonomy_id_by_name( $taxonomie ) );
		$a->set_name( $taxonomie );
		$a->set_options( array( $valoare ) );
		$a->set_position( $pozitie++ );
		$a->set_visible( true );
		$a->set_variation( false );
		$iesire[] = $a;
	}

	foreach ( $locale as $nume => $valoare ) {
		if ( '' === (string) $valoare ) {
			continue;
		}
		$a = new WC_Product_Attribute();
		$a->set_name( (string) $nume );
		$a->set_options( array( (string) $valoare ) );
		$a->set_position( $pozitie++ );
		$a->set_visible( true );
		$a->set_variation( false );
		$iesire[] = $a;
	}

	return $iesire;
}

/**
 * Pune termenii atributelor globale pe produs.
 *
 * `WC_Product::save()` scrie legătura produs-atribut, dar nu creează termenii în taxonomie. Fără pasul ăsta,
 * atributul apare gol în admin și nu filtrează nimic.
 *
 * @param int   $wc_id    Produsul.
 * @param array $atribute array( 'globale' => ... ).
 * @return void
 */
function ccs_import_atribute_termeni( $wc_id, $atribute ) {
	foreach ( (array) ( $atribute['globale'] ?? array() ) as $nume => $valoare ) {
		$taxonomie = ccs_atribut_taxonomie( $nume );
		if ( is_wp_error( $taxonomie ) || '' === (string) $valoare ) {
			continue;
		}
		wp_set_object_terms( (int) $wc_id, (string) $valoare, $taxonomie, false );
	}
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
 * @param string $fortata  Extensia de folosit în locul celei din URL, după o conversie.
 * @return string Numele fișierului, cu extensie.
 */
function ccs_import_nume_fisier( $date, $pozitia = 0, $url = '', $fortata = '' ) {
	$date = wp_parse_args(
		(array) $date,
		array(
			'nume'  => '',
			'brand' => '',
			'mpn'   => '',
			'sku'   => '',
			'wc_id' => 0,
		)
	);

	// Brandul în față: așa caută oamenii („meyle pompa spalator”) și așa se grupează fișierele în folder.
	$bucati = array();
	if ( '' !== (string) $date['brand'] ) {
		$bucati[] = ccs_slug_scurt( $date['brand'], 20 );
	}
	if ( '' !== (string) $date['nume'] ) {
		$bucati[] = ccs_slug_scurt( $date['nume'], 60 );
	}
	// Codul de producător, nu codul intern al furnizorului. La 82% dintre produsele Conex sunt diferite, iar
	// cel intern nu înseamnă nimic pentru nimeni din afara furnizorului: nici pentru cumpărător, nici pentru
	// Google. Când furnizorul nu trimite unul, cade pe SKU, ca fișierul să rămână distinct.
	$cod = '' !== (string) $date['mpn'] ? $date['mpn'] : $date['sku'];
	if ( '' !== (string) $cod ) {
		$bucati[] = ccs_slug_scurt( $cod, 24 );
	}

	$bucati = array_values( array_filter( $bucati ) );
	$baza   = implode( '-', $bucati );
	if ( '' === $baza ) {
		$baza = 'produs-' . (int) $date['wc_id'];
	}

	$extensie = '' !== (string) $fortata
		? strtolower( (string) $fortata )
		: strtolower( (string) pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
	if ( ! in_array( $extensie, array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true ) ) {
		$extensie = 'jpg';
	}

	$nume_fisier = $baza . ( $pozitia ? '-' . ( (int) $pozitia + 1 ) : '' ) . '.' . $extensie;

	/**
	 * Numele sub care intră o poză în biblioteca media.
	 *
	 * Un alt magazin poate avea altă regulă: fără brand, cu SKU-ul la coadă, altceva. Numele se schimbă de
	 * aici, fără să umble nimeni în import.
	 *
	 * @param string $nume_fisier Numele propus.
	 * @param array  $date        nume, brand, mpn, sku, wc_id.
	 * @param int    $pozitia     A câta poză a produsului, de la zero.
	 */
	return apply_filters( 'ccs_nume_fisier_poza', $nume_fisier, $date, $pozitia );
}

/**
 * Un slug tăiat la ultimul cuvânt întreg.
 *
 * `substr()` pe un slug taie în mijlocul cuvântului și lasă adesea o cratimă la capăt:
 * „banda-multifunctionala-pentru-repara-”. Numele unui fișier se citește de om, deci tăiem la cratima
 * dinainte de limită.
 *
 * @param string $text  Textul.
 * @param int    $maxim Câte caractere.
 * @return string Slugul.
 */
function ccs_slug_scurt( $text, $maxim ) {
	$slug = sanitize_title( (string) $text );
	if ( strlen( $slug ) <= $maxim ) {
		return $slug;
	}

	$taiat = substr( $slug, 0, $maxim );
	$ultim = strrpos( $taiat, '-' );
	// Dacă primul cuvânt singur e mai lung decât limita, n-avem ce tăia frumos: rămâne tăietura brută.
	if ( false !== $ultim && $ultim > 0 ) {
		$taiat = substr( $taiat, 0, $ultim );
	}
	return rtrim( $taiat, '-' );
}

/**
 * Convertește o poză descărcată în WebP.
 *
 * Se face la import fiindcă după import nu se mai face niciodată: o poză intrată JPEG rămâne JPEG în toate
 * dimensiunile generate de WordPress, iar magazinul plătește diferența la fiecare vizitator, nu o dată.
 *
 * Calitatea e mai mare decât implicita WordPress, care e 82. La poze de produs, unde omul mărește ca să vadă
 * cusătura husei sau filetul șurubului, artefactele costă mai mult decât kilobaiții economisiți.
 *
 * Dacă pe server nu se poate scrie WebP, întoarce eroare și poza rămâne cum a venit. O poză în formatul vechi e
 * mai bună decât niciuna.
 *
 * @param string $cale     Fișierul descărcat.
 * @param int    $calitate 1-100. Zero sau mai puțin oprește conversia.
 * @return string|WP_Error Calea fișierului WebP, sau eroare.
 */
function ccs_import_webp( $cale, $calitate ) {
	if ( (int) $calitate <= 0 ) {
		return new WP_Error( 'ccs_webp_oprit', __( 'Conversia în WebP e oprită din setări.', 'conex-conect-sync' ) );
	}

	if ( ! function_exists( 'wp_get_image_editor' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	// O poză care vine deja WebP nu se recodează: a doua trecere printr-un format cu pierderi strică fără să
	// câștige nimic.
	$tip = wp_check_filetype( $cale );
	if ( 'image/webp' === ( $tip['type'] ?? '' ) ) {
		return $cale;
	}

	if ( ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
		return new WP_Error( 'ccs_fara_webp', __( 'Serverul nu poate scrie WebP.', 'conex-conect-sync' ) );
	}

	$editor = wp_get_image_editor( $cale );
	if ( is_wp_error( $editor ) ) {
		return $editor;
	}
	$editor->set_quality( min( 100, max( 1, (int) $calitate ) ) );

	// Ținta se face din bucățile căii, nu cu un regex peste tot drumul. O cale ca `/tmp/dosar.vechi/poza` are
	// punctul în dosar, nu în fișier, iar o tăiere pe ultimul punct ar scrie altundeva. Prima variantă scrisă
	// aici chiar avea regexul stricat: `preg_replace` întorcea null, ținta ieșea `.webp`, o cale relativă, iar
	// fișierul ateriza în directorul curent al procesului.
	$dosar = rtrim( dirname( $cale ), "/\\\\" );
	$baza  = pathinfo( $cale, PATHINFO_FILENAME );
	$tinta = $dosar . DIRECTORY_SEPARATOR . $baza . '.webp';
	for ( $numar = 1; file_exists( $tinta ) && $numar < 100; $numar++ ) {
		$tinta = $dosar . DIRECTORY_SEPARATOR . $baza . '-' . $numar . '.webp';
	}

	$salvat = $editor->save( $tinta, 'image/webp' );
	if ( is_wp_error( $salvat ) ) {
		return $salvat;
	}

	return (string) $salvat['path'];
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
function ccs_import_poze( $wc_id, $urluri, $d = array() ) {
	if ( ! function_exists( 'wp_handle_sideload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	$d = wp_parse_args(
		(array) $d,
		array(
			'nume'  => '',
			'brand' => '',
			'mpn'   => '',
			'sku'   => '',
		)
	);
	$d['wc_id'] = (int) $wc_id;
	if ( '' === (string) $d['nume'] ) {
		$d['nume'] = get_the_title( $wc_id );
	}

	// Textul alternativ poartă și marca: omul care caută o poză de piesă caută „meyle pompă spălător”, nu
	// doar „pompă spălător”. Marca stă în față, ca în numele fișierului, ca să fie aceeași poveste.
	$alt_baza = trim( ( '' !== (string) $d['brand'] ? $d['brand'] . ' ' : '' ) . $d['nume'] );
	$calitate = (int) ccs_setari()['webp_calitate'];
	$ids      = array();
	foreach ( array_values( $urluri ) as $i => $url ) {
		$temporar = download_url( $url );
		if ( is_wp_error( $temporar ) ) {
			ccs_jurnal( 'avertisment', sprintf( 'Poza %s nu s-a descărcat: %s', $url, $temporar->get_error_message() ) );
			continue;
		}

		/**
		 * Cât poate cântări o poză adusă de la furnizor, în octeți.
		 *
		 * `download_url()` n-are nicio limită: ce trimite serverul celălalt, aia scrie pe disc. O poză de
		 * produs stă sub două megaocteți; zece e larg. Verificarea e după descărcare, nu înainte: un `HEAD`
		 * pe fiecare poză ar însemna încă o cerere pentru fiecare dintre cele opt sute ale unui import, iar
		 * multe servere nici nu trimit `Content-Length`. Fișierul prea mare se șterge pe loc, deci pe disc
		 * nu rămâne nimic.
		 *
		 * @param int $octeti Limita.
		 */
		$maxim = (int) apply_filters( 'ccs_poza_marime_maxima', 10 * MB_IN_BYTES );
		$cat   = (int) @filesize( $temporar ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( $maxim > 0 && $cat > $maxim ) {
			wp_delete_file( $temporar );
			ccs_jurnal(
				'avertisment',
				sprintf(
					'Poza %s are %s, peste limita de %s. Am sărit-o.',
					$url,
					size_format( $cat ),
					size_format( $maxim )
				)
			);
			continue;
		}

		$incarcat = $temporar;
		$fortata  = '';
		$webp     = ccs_import_webp( $temporar, $calitate );
		if ( is_wp_error( $webp ) ) {
			// Poza rămâne cum a venit. Merită știut, fiindcă dacă pică pentru toate, serverul e de vină.
			ccs_jurnal( 'avertisment', sprintf( 'Poza %s a rămas în formatul original: %s', $url, $webp->get_error_message() ) );
		} elseif ( $webp !== $temporar ) {
			$incarcat = $webp;
			$fortata  = 'webp';
			// Originalul nu mai e de folos. `wp_handle_sideload()` mută numai fișierul pe care i-l dăm.
			if ( file_exists( $temporar ) ) {
				wp_delete_file( $temporar );
			}
		}

		// `wp_handle_sideload()` primește primul argument prin referință, deci nu se poate da un array literal:
		// în PHP 8 e eroare fatală, nu avertisment. Trebuie să fie o variabilă.
		$fisier = array(
			'name'     => ccs_import_nume_fisier( $d, $i, $url, $fortata ),
			'tmp_name' => $incarcat,
		);
		$asezat = wp_handle_sideload( $fisier, array( 'test_form' => false ) );

		if ( ! empty( $asezat['error'] ) ) {
			// `wp_handle_sideload` mută fișierul când reușește; când cade, rămâne al nostru de șters.
			if ( file_exists( $incarcat ) ) {
				wp_delete_file( $incarcat );
			}
			ccs_jurnal( 'avertisment', sprintf( 'Poza %s nu s-a putut salva: %s', $url, $asezat['error'] ) );
			continue;
		}

		$id = wp_insert_attachment(
			array(
				'post_mime_type' => $asezat['type'],
				'post_title'     => $alt_baza,
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
		// A doua poză a aceluiași produs nu e aceeași poză: un text alternativ identic pe toate spune
		// cititorului de ecran de patru ori același lucru și nu ajută pe nimeni.
		update_post_meta(
			$id,
			'_wp_attachment_image_alt',
			$i
				? sprintf(
					/* translators: 1: marca și denumirea, 2: a câta poză. */
					__( '%1$s, imaginea %2$d', 'conex-conect-sync' ),
					$alt_baza,
					(int) $i + 1
				)
				: $alt_baza
		);

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

/**
 * Importul automat al produselor care tocmai și-au primit fotografia.
 *
 * Oferta promite, pentru cele aproximativ 1.500 de produse bune dar fără poză, că „pluginul le verifică periodic
 * și le urcă singur în momentul în care fotografia apare”. Jobul ăsta e partea aia. Rulează după preluarea
 * pozelor, fiindcă abia atunci se știe cine tocmai a devenit importabil.
 *
 * Trei lucruri îl țin în frâu, și toate trei sunt intenționate:
 *
 *   - e **oprit implicit**, deci nu creează nimic până nu cere cineva;
 *   - produsele intră tot ca **ciornă**, deci nimic nu ajunge în magazin fără ca un om să publice;
 *   - are un **plafon pe rulare**, ca o zi în care furnizorul adaugă cinci mii de poze să nu umple magazinul
 *     într-o noapte.
 *
 * Așa stau împreună cele două promisiuni ale ofertei care par să se bată cap în cap: „le urcă singur” și „nimic
 * nu urcă pe site fără acordul tău”. Pluginul le pregătește singur, omul le publică.
 *
 * @return void
 */
function ccs_job_import_automat() {
	$setari = ccs_setari();

	if ( ! $setari['import_automat'] ) {
		return;
	}
	if ( $setari['mod_raport'] ) {
		ccs_jurnal( 'info', 'Import automat: modul raport e pornit, deci nu creez nimic.' );
		return;
	}

	$rezumat = ccs_import_executa(
		array(
			'limita'  => max( 1, (int) $setari['import_automat_limita'] ),
			'publica' => false,
		)
	);

	if ( is_wp_error( $rezumat ) ) {
		ccs_jurnal( 'avertisment', 'Import automat oprit: ' . $rezumat->get_error_message() );
		return;
	}

	if ( ! $rezumat['create'] ) {
		return;
	}

	ccs_jurnal(
		'info',
		sprintf(
			'Import automat: %d produse noi, ca ciorne, din rularea %d.',
			$rezumat['create'],
			$rezumat['rulare']
		)
	);

	/**
	 * Se declanșează după un import automat care chiar a creat produse.
	 *
	 * @param array $rezumat Rezumatul rulării.
	 */
	do_action( 'ccs_import_automat_gata', $rezumat );
}
add_action( 'ccs_job_import_automat', 'ccs_job_import_automat' );

/**
 * Înregistrează jobul de import automat.
 *
 * La 19:40, după imagini (19:10) și date tehnice (19:25): produsul se importă cu poze și cu atribute, deci
 * amândouă trebuie să fie deja luate.
 *
 * @param array $joburi Joburile.
 * @return array Joburile, cu al nostru.
 */
function ccs_import_job( $joburi ) {
	$joburi['ccs_job_import_automat'] = array(
		'nume'         => __( 'Importul produselor care și-au primit poza', 'conex-conect-sync' ),
		'interval'     => DAY_IN_SECONDS,
		'doar_noaptea' => true,
		'minut'        => 40,
	);
	return $joburi;
}
add_filter( 'ccs_joburi', 'ccs_import_job' );
