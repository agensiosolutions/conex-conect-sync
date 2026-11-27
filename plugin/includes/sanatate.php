<?php
/**
 * Verificări de configurare: lucrurile care strică tăcut.
 *
 * Problemele din `email.php` sunt operaționale: sincronizarea s-a oprit, tokenul a fost refuzat, catalogul s-a
 * înjumătățit. Toate se văd, mai devreme sau mai târziu, pentru că cifrele din panou încremenesc.
 *
 * Astea de aici sunt altceva. Sunt presupuneri pe care pluginul le face despre magazin și pe care nimeni nu le
 * leagă de plugin când le schimbă. Nimic nu dă eroare, cifrele arată normal, iar greșeala se vede abia în coșul
 * unui client sau pe un raft greșit. De-aia există fișierul ăsta: fiecare verificare prinde o presupunere care,
 * dacă pică, pică fără zgomot.
 *
 * Forma unei probleme e aceeași cu cea din `email.php` (`cheie => array( titlu, mesaj )`), ca aceeași listă să
 * poată fi și arătată în panou, și trimisă pe email, fără două scrieri ale aceluiași text.
 *
 * @package conex-conect-sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Presupunerile pluginului despre magazin, verificate.
 *
 * @return array cheie => array( titlu, mesaj ).
 */
function ccs_sanatate_configurare() {
	$probleme = array();

	foreach ( array( 'ccs_sanatate_taxe', 'ccs_sanatate_moneda', 'ccs_sanatate_categorii', 'ccs_sanatate_atribute' ) as $verificare ) {
		$gasit = call_user_func( $verificare );
		if ( $gasit ) {
			$probleme = array_merge( $probleme, $gasit );
		}
	}

	return $probleme;
}

/**
 * TVA-ul: pluginul scrie prețuri cu TVA inclus.
 *
 * `ccs_pret()` înmulțește costul cu cota de TVA și scrie rezultatul în `regular_price`. Asta e corect cât timp
 * WooCommerce nu mai adaugă TVA pe deasupra. Azi, pe cezauto.ro, taxele sunt oprite de tot, deci e consistent.
 *
 * Dacă cineva pornește vreodată taxele fără să bifeze și „prețurile sunt introduse cu TVA inclus”, WooCommerce
 * tratează prețul nostru ca pe unul fără TVA și mai pune o dată 21% la casă. Tot magazinul sare cu 21% peste
 * noapte, iar pluginul nu are de unde să afle: el a scris exact ce trebuia.
 *
 * @return array
 */
function ccs_sanatate_taxe() {
	if ( ! function_exists( 'wc_tax_enabled' ) || ! wc_tax_enabled() ) {
		return array();
	}
	if ( 'yes' === get_option( 'woocommerce_prices_include_tax' ) ) {
		return array();
	}

	return array(
		'taxe_dublate' => array(
			__( 'TVA-ul s-ar aduna de două ori', 'conex-conect-sync' ),
			__( "În WooCommerce taxele sunt pornite, dar prețurile sunt marcate ca fiind introduse fără TVA.\n\nSincronizarea scrie prețuri care au deja TVA-ul inclus, deci WooCommerce îl mai adaugă o dată la casă. Clienții ar plăti cu aproximativ 21% mai mult decât scrie pe pagina produsului.\n\nSe repară dintr-un singur loc: WooCommerce / Setări / Taxe, opțiunea „Prețurile introduse cu taxă”, pusă pe „Da, voi introduce prețuri cu taxă inclusă”.", 'conex-conect-sync' ),
		),
	);
}

/**
 * Moneda: toate pragurile pluginului sunt în lei.
 *
 * Marja minimă, prețul minim și rotunjirea sunt numere fără monedă. Dacă magazinul trece pe altă monedă, ele
 * rămân aceleași numere și încep să însemne cu totul altceva: patru lei marjă minimă devin patru euro.
 *
 * @return array
 */
function ccs_sanatate_moneda() {
	if ( ! function_exists( 'get_woocommerce_currency' ) ) {
		return array();
	}
	$moneda = get_woocommerce_currency();
	if ( 'RON' === $moneda ) {
		return array();
	}

	return array(
		'moneda' => array(
			__( 'Moneda magazinului nu mai e leul', 'conex-conect-sync' ),
			sprintf(
				/* translators: %s: codul monedei din WooCommerce. */
				__( "Magazinul lucrează acum în %s, dar costurile furnizorului vin în lei, iar pragurile din politica de preț (marja minimă, prețul minim) sunt tot în lei.\n\nPână se lămurește, prețurile calculate nu înseamnă ce par. Verifică politica de preț înainte de următoarea aplicare.", 'conex-conect-sync' ),
				$moneda
			),
		),
	);
}

/**
 * Reguli de categorie rămase fără categorie.
 *
 * Vezi `ccs_categorii_termeni_vii()`: o categorie ștearsă de pe site lasă regula noastră să arate în gol, iar
 * WooCommerce pune produsul în categoria implicită a magazinului fără să spună nimic. Regula e ignorată de la
 * sine, deci produsele așteaptă o categorie în loc să ajungă pe raftul greșit; rămâne doar de spus cuiva.
 *
 * @return array
 */
function ccs_sanatate_categorii() {
	if ( ! function_exists( 'ccs_categorii_reguli_moarte' ) ) {
		return array();
	}

	$moarte = ccs_categorii_reguli_moarte();
	if ( ! $moarte ) {
		return array();
	}

	$exemple = array_slice( wp_list_pluck( $moarte, 'cale' ), 0, 5 );

	return array(
		'categorii_moarte' => array(
			sprintf(
				/* translators: %d: câte reguli. */
				_n( '%d regulă de categorie arată spre o categorie ștearsă', '%d reguli de categorie arată spre categorii șterse', count( $moarte ), 'conex-conect-sync' ),
				count( $moarte )
			),
			sprintf(
				/* translators: 1: câte reguli, 2: lista căilor. */
				__( "%1\$d reguli trimiteau produsele spre categorii care nu mai există pe site. Sincronizarea le ignoră, deci produsele respective așteaptă o categorie în loc să ajungă unde nu trebuie.\n\nCăile rămase fără categorie:\n%2\$s\n\nSe repară din panou, la „Produse de confirmat”, alegând altă categorie pentru fiecare cale.", 'conex-conect-sync' ),
				count( $moarte ),
				'  ' . implode( "\n  ", $exemple )
			),
		),
	);
}

/**
 * Atribute ale magazinului în care s-ar vărsa valori noi de la furnizor.
 *
 * Harta noastră de slug-uri se ferește de ciocniri între numele furnizorului, dar nu se uită la atributele pe
 * care și le-a făcut magazinul. Când un nume de la Conex dă același slug cu un atribut existent, pluginul nu
 * creează nimic: scrie valorile furnizorului în atributul omului.
 *
 * Prima versiune a verificării se oprea la nume și scria „Material -> Material”, ceea ce nu spunea nimic: e
 * evident că Material intră în Material. Riscul nu stă niciodată în nume, stă în valori. „Tip montare” la
 * Cez Auto înseamnă cum se prinde un portbagaj („Haion/Portbagaj”, „Magnetica pe cupola”); la Conex înseamnă
 * tipul unei bucșe de suspensie („hidrolagar”, „Bucșa cauciuc-metal”). Același nume, două noțiuni.
 *
 * Deci comparăm mulțimile de valori, nu numele, și tăcem când furnizorul n-ar adăuga nimic nou: două
 * vocabulare care se potrivesc sunt exact ce vrei, nu o problemă.
 *
 * @return array
 */
function ccs_sanatate_atribute() {
	global $wpdb;

	if ( ! function_exists( 'wc_get_attribute_taxonomies' ) || ! function_exists( 'ccs_atribut_slug' ) ) {
		return array();
	}

	$tabel = ccs_tabel( 'atribute' );
	// phpcs:ignore WordPress.DB.PreparedSQL -- numele tabelei vine din prefixul WordPress.
	$nume = (array) $wpdb->get_col( "SELECT DISTINCT nume FROM $tabel" );
	if ( ! $nume ) {
		return array();
	}

	$ale_magazinului = array();
	foreach ( wc_get_attribute_taxonomies() as $a ) {
		$ale_magazinului[ $a->attribute_name ] = $a->attribute_label;
	}

	$ale_noastre = (array) get_option( 'ccs_atribute_create', array() );

	$peste = array();
	foreach ( $nume as $n ) {
		$slug = ccs_atribut_slug( $n, false );
		if ( ! isset( $ale_magazinului[ $slug ] ) || in_array( $slug, $ale_noastre, true ) ) {
			continue;
		}

		$ale_lui = get_terms(
			array(
				'taxonomy'   => 'pa_' . $slug,
				'hide_empty' => false,
				'fields'     => 'names',
			)
		);
		$ale_lui = is_wp_error( $ale_lui ) ? array() : (array) $ale_lui;

		// Comparăm fără majuscule și fără spații la capete: „Negru” și „negru” sunt aceeași valoare pentru om,
		// iar o avertizare care nu știe asta ar striga la fiecare diferență de scriere.
		$cheie = array_map(
			static function ( $v ) {
				return function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $v ) ) : strtolower( trim( $v ) );
			},
			$ale_lui
		);

		$valorile_lui = (array) $wpdb->get_col(
			// phpcs:ignore WordPress.DB.PreparedSQL -- numele tabelei vine din prefixul WordPress.
			$wpdb->prepare( "SELECT DISTINCT valoare FROM $tabel WHERE nume = %s", $n )
		);

		$noi = array();
		foreach ( $valorile_lui as $v ) {
			$jos = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $v ) ) : strtolower( trim( $v ) );
			if ( '' !== $jos && ! in_array( $jos, $cheie, true ) ) {
				$noi[] = $v;
			}
		}

		if ( ! $noi ) {
			continue;
		}

		$peste[] = array(
			'eticheta' => $ale_magazinului[ $slug ],
			'nume'     => $n,
			'are'      => count( $ale_lui ),
			'noi'      => count( $noi ),
			'ale_lui'  => array_slice( $ale_lui, 0, 4 ),
			'adaugate' => array_slice( $noi, 0, 6 ),
		);
	}

	if ( ! $peste ) {
		return array();
	}

	// Cel mai rău caz sus: un atribut cu trei valori peste care vin opt e altceva decât unul cu cincizeci peste
	// care vine una.
	usort(
		$peste,
		static function ( $a, $b ) {
			return $b['noi'] <=> $a['noi'];
		}
	);

	// Fara indentare cu spatii: in panou textul ajunge in HTML, unde spatiile de la inceput de rand se
	// colapseaza, deci alinierea s-ar vedea doar in email. Un singur format care se citeste in amandoua.
	$linii = array();
	foreach ( $peste as $x ) {
		$linii[] = sprintf(
			/* translators: 1: atributul, 2: câte valori are acum, 3: câte s-ar adăuga, 4: valorile de acum, 5: cele noi. */
			__( "%1\$s: are acum %2\$d valori, Conex ar adăuga %3\$d noi.\nacum: %4\$s\ns-ar adăuga: %5\$s", 'conex-conect-sync' ),
			$x['eticheta'],
			$x['are'],
			$x['noi'],
			implode( ', ', $x['ale_lui'] ) . ( $x['are'] > 4 ? ', …' : '' ),
			implode( ', ', $x['adaugate'] ) . ( $x['noi'] > 6 ? ', …' : '' )
		);
	}

	return array(
		'atribute_comune' => array(
			sprintf(
				/* translators: %d: câte atribute. */
				_n( '%d atribut al magazinului ar primi valori noi de la Conex', '%d atribute ale magazinului ar primi valori noi de la Conex', count( $peste ), 'conex-conect-sync' ),
				count( $peste )
			),
			sprintf(
				/* translators: %s: lista atributelor cu valorile lor. */
				__( "La importul de produse noi, valorile de la Conex s-ar scrie în atribute pe care magazinul le are deja, nu în atribute noi. Atât timp cât înseamnă același lucru, e în regulă. Când nu înseamnă, filtrele din magazin ajung să arate două vocabulare amestecate.\n\n%s\n\nCâteva valori noi sunt de obicei normale. Multe valori noi peste puține existente înseamnă, de regulă, că cele două atribute poartă același nume din întâmplare.", 'conex-conect-sync' ),
				implode( "\n\n", $linii )
			),
		),
	);
}
