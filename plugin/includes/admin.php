<?php
/**
 * Panoul de admin: meniul, taburile, datele și răspunsurile AJAX.
 *
 * Interfața e în `admin-ui.php`, stilul în `admin/css/ccs-admin.css`. Împărțirea e cea de la agensio-consent, ca
 * cine umblă la un plugin al agenției să le găsească pe amândouă la fel.
 *
 * De ce există panoul: rapoartele CSV sunt bune pentru cine construiește sincronizarea, nu pentru cine trebuie să
 * hotărască. Un fișier cu patru mii de rânduri nu se citește, iar deciziile luate pe email se pierd. Fiecare tab
 * pune o întrebare, cu datele lângă ea, și scrie răspunsul în `ccs_decizii`, cu cine a răspuns și când.
 *
 * @package conex-conect-sync
 */

defined( 'ABSPATH' ) || exit;

const CCS_CAPACITATE = 'manage_woocommerce';

/**
 * Taburile: slug => array( titlu, dashicon, o frază, cheia de la care se ia numărul de întrebări rămase ).
 *
 * @return array Taburile.
 */
function ccs_admin_taburi() {
	return array(
		'panou'     => array( __( 'Panou', 'conex-conect-sync' ), 'dashboard', __( 'Cum stă sincronizarea', 'conex-conect-sync' ), '' ),
		'pret'      => array( __( 'Politica de preț', 'conex-conect-sync' ), 'tag', __( 'Adaos, TVA, marjă minimă', 'conex-conect-sync' ), '' ),
		'potriviri' => array( __( 'Produse de confirmat', 'conex-conect-sync' ), 'randomize', __( 'Cod la fel, produs diferit', 'conex-conect-sync' ), 'potrivire' ),
		'scaderi'   => array( __( 'Prețuri care coboară', 'conex-conect-sync' ), 'arrow-down-alt', __( 'Scăderi mai mari de jumătate', 'conex-conect-sync' ), 'scadere' ),
		'subcost'   => array( __( 'Vândute sub cost', 'conex-conect-sync' ), 'warning', __( 'Preț mai mic decât achiziția', 'conex-conect-sync' ), 'sub_cost' ),
		'stoc'      => array( __( 'Stocul', 'conex-conect-sync' ), 'archive', __( 'Cantități și produse epuizate', 'conex-conect-sync' ), '' ),
	);
}

/**
 * Tabul cerut, sau primul.
 *
 * @return string Slugul tabului.
 */
function ccs_admin_tab() {
	$taburi = ccs_admin_taburi();
	$tab    = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'panou'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return isset( $taburi[ $tab ] ) ? $tab : 'panou';
}

/**
 * Adresa unui tab.
 *
 * @param string $tab Slugul.
 * @return string Adresa.
 */
function ccs_admin_url( $tab ) {
	return admin_url( 'admin.php?page=ccs-decizii&tab=' . $tab );
}

/**
 * Pune meniul în admin.
 *
 * @return void
 */
function ccs_admin_meniu() {
	add_menu_page(
		__( 'Sincronizare Conex', 'conex-conect-sync' ),
		__( 'Sincronizare Conex', 'conex-conect-sync' ),
		CCS_CAPACITATE,
		'ccs-decizii',
		'ccs_admin_pagina',
		'dashicons-update',
		56
	);
}
add_action( 'admin_menu', 'ccs_admin_meniu' );

/**
 * Clasa pe `body`, ca stilul să poată schimba fundalul paginii de admin.
 *
 * @param string $clase Clasele.
 * @return string Clasele.
 */
function ccs_admin_body_class( $clase ) {
	if ( 'toplevel_page_ccs-decizii' === get_current_screen()->id ) {
		$clase .= ' ccs-pagina';
	}
	return $clase;
}
add_filter( 'admin_body_class', 'ccs_admin_body_class' );

/**
 * Încarcă stilul și scriptul, doar pe pagina noastră.
 *
 * @param string $hook Pagina curentă.
 * @return void
 */
function ccs_admin_assets( $hook ) {
	if ( 'toplevel_page_ccs-decizii' !== $hook ) {
		return;
	}
	wp_enqueue_style( 'ccs-admin', CCS_URL . 'admin/css/ccs-admin.css', array(), CCS_VERSIUNE );
	wp_enqueue_script( 'ccs-admin', CCS_URL . 'admin/js/ccs-admin.js', array(), CCS_VERSIUNE, true );
}
add_action( 'admin_enqueue_scripts', 'ccs_admin_assets' );

/**
 * Scoate notificările celorlalte pluginuri de pe ecranul nostru.
 *
 * Pe o pagină de setări obișnuită, notificările își au rostul. Aici nu: pagina asta o deschide clientul ca să ia
 * decizii despre prețurile magazinului lui, iar o alertă roșie despre licența altui plugin îl face să creadă că
 * sincronizarea are o problemă. Pluginurile care își pun reclame și reînnoiri pe orice ecran de admin fac zgomotul
 * ăsta peste tot, nu doar aici.
 *
 * Se scot doar pe ecranul nostru. În restul adminului apar ca de obicei, inclusiv anunțul de actualizare a
 * WordPress-ului, care rămâne treaba celui care întreține site-ul, nu a celui care hotărăște prețurile.
 *
 * @return void
 */
function ccs_admin_fara_notificari() {
	$ecran = get_current_screen();
	if ( ! $ecran || 'toplevel_page_ccs-decizii' !== $ecran->id ) {
		return;
	}

	/**
	 * Permite păstrarea notificărilor, dacă cineva le vrea totuși pe ecranul ăsta.
	 *
	 * @param bool $ascunde Dacă se ascund.
	 */
	if ( ! apply_filters( 'ccs_ascunde_notificarile', true ) ) {
		return;
	}

	foreach ( array( 'admin_notices', 'all_admin_notices', 'network_admin_notices', 'user_admin_notices' ) as $hook ) {
		remove_all_actions( $hook );
	}
}
// `in_admin_header` se execută în `admin-header.php` înainte de locul unde se tipăresc notificările, deci e
// momentul în care mai pot fi scoase, oricât de târziu s-ar fi agățat pluginul care le pune.
add_action( 'in_admin_header', 'ccs_admin_fara_notificari', 1000 );

/* ------------------------------------------------------------------------------------------------ datele */

/**
 * Planul, ținut în cache câteva minute.
 *
 * Calculul trece prin patru mii de produse și două tabele; la fiecare schimbare de tab n-are rost refăcut.
 *
 * @param bool $proaspat Dacă true, recalculează.
 * @return array|WP_Error Planul.
 */
function ccs_admin_plan( $proaspat = false ) {
	static $memorie = null;
	if ( null !== $memorie && ! $proaspat ) {
		return $memorie;
	}
	if ( ! $proaspat ) {
		$cache = get_transient( 'ccs_plan_cache' );
		if ( is_array( $cache ) ) {
			$memorie = $cache;
			return $memorie;
		}
	}
	$plan = ccs_plan();
	if ( is_wp_error( $plan ) ) {
		return $plan;
	}
	set_transient( 'ccs_plan_cache', $plan, 10 * MINUTE_IN_SECONDS );
	$memorie = $plan;
	return $memorie;
}

/**
 * Potrivirea, ținută la fel în cache.
 *
 * @param bool $proaspat Dacă true, recalculează.
 * @return array Rezultatul potrivirii.
 */
function ccs_admin_potrivire( $proaspat = false ) {
	static $memorie = null;
	if ( null !== $memorie && ! $proaspat ) {
		return $memorie;
	}
	if ( ! $proaspat ) {
		$cache = get_transient( 'ccs_potrivire_cache' );
		if ( is_array( $cache ) ) {
			$memorie = $cache;
			return $memorie;
		}
	}
	$memorie = ccs_potriveste( false );
	set_transient( 'ccs_potrivire_cache', $memorie, 10 * MINUTE_IN_SECONDS );
	return $memorie;
}

/**
 * Șterge cache-urile.
 *
 * @return void
 */
function ccs_admin_uita() {
	delete_transient( 'ccs_plan_cache' );
	delete_transient( 'ccs_potrivire_cache' );
}

/**
 * Rândurile de plan care intră într-un tab anume.
 *
 * @param string $tab 'scaderi' sau 'subcost'.
 * @return array Rândurile.
 */
function ccs_admin_randuri( $tab ) {
	$plan = ccs_admin_plan();
	if ( is_wp_error( $plan ) ) {
		return array();
	}

	$randuri = array();
	foreach ( $plan['randuri'] as $r ) {
		if ( 'scaderi' === $tab && isset( $r['blocaje']['scadere_mare'] ) ) {
			$randuri[] = $r;
		}
		if ( 'subcost' === $tab && $r['sub_cost'] ) {
			$randuri[] = $r;
		}
	}

	if ( 'subcost' === $tab ) {
		// Cele mai dureroase întâi: pierderea cea mai mare la bucată.
		usort(
			$randuri,
			static function ( $a, $b ) {
				return ( $a['pret_site'] - $a['cost_cu_tva'] ) <=> ( $b['pret_site'] - $b['cost_cu_tva'] );
			}
		);
	}

	return $randuri;
}

/**
 * Câte întrebări au rămas fără răspuns, pe fiecare tip de decizie.
 *
 * @return array tip => număr.
 */
function ccs_admin_rest() {
	$rest = array(
		'potrivire' => 0,
		'scadere'   => 0,
		'sub_cost'  => 0,
	);

	$potrivire = ccs_admin_potrivire();
	$luate     = ccs_decizii_curente( 'potrivire' );
	foreach ( array_keys( $potrivire['lista_verificat'] ) as $wc_id ) {
		if ( ! isset( $luate[ (string) $wc_id ] ) ) {
			$rest['potrivire']++;
		}
	}

	foreach ( array( 'scaderi' => 'scadere', 'subcost' => 'sub_cost' ) as $tab => $tip ) {
		$luate = ccs_decizii_curente( $tip );
		foreach ( ccs_admin_randuri( $tab ) as $r ) {
			if ( ! isset( $luate[ (string) $r['wc_id'] ] ) ) {
				$rest[ $tip ]++;
			}
		}
	}

	return $rest;
}

/**
 * Politica de preț curentă, doar câmpurile care se pot schimba din pagină.
 *
 * @return array Politica.
 */
function ccs_admin_politica() {
	$setari = ccs_setari();
	return array(
		'adaos'            => (float) $setari['adaos'],
		'cota_tva'         => (float) $setari['cota_tva'],
		'marja_minima_lei' => (float) $setari['marja_minima_lei'],
		'pret_minim_lei'   => (float) $setari['pret_minim_lei'],
		'rotunjire'        => (string) $setari['rotunjire'],
		'include_taxa_eco' => (bool) $setari['include_taxa_eco'],
	);
}

/**
 * Efectul politicii pe tot magazinul: câte prețuri urcă, câte coboară, cât se schimbă câștigul.
 *
 * @param array $plan Planul.
 * @return array Cifrele.
 */
function ccs_admin_efect( $plan ) {
	$sus        = 0;
	$jos        = 0;
	$delta_sus  = 0.0;
	$delta_jos  = 0.0;
	$marja_acum = 0.0;
	$marja_prop = 0.0;

	foreach ( $plan['randuri'] as $r ) {
		if ( null === $r['pret_site'] ) {
			continue;
		}
		$marja_acum += $r['pret_site'] - $r['cost_cu_tva'];
		$marja_prop += $r['pret_tinta'] - $r['cost_cu_tva'];
		$d           = $r['pret_tinta'] - $r['pret_site'];
		if ( $d > 0.009 ) {
			$sus++;
			$delta_sus += $d;
		} elseif ( $d < -0.009 ) {
			$jos++;
			$delta_jos += -$d;
		}
	}

	return array(
		'sus'         => $sus,
		'jos'         => $jos,
		'medie_sus'   => $sus ? $delta_sus / $sus : 0,
		'medie_jos'   => $jos ? $delta_jos / $jos : 0,
		'marja_acum'  => $marja_acum,
		'marja_prop'  => $marja_prop,
		'marja_delta' => $marja_acum > 0 ? ( $marja_prop / $marja_acum - 1 ) * 100 : 0,
	);
}

/**
 * Datele minime pentru simulatorul din browser.
 *
 * Doar ce intră în formulă: cost, taxă verde și prețul de acum, pe liste paralele. Pentru patru mii de produse
 * înseamnă trei șiruri de numere, nu patru mii de obiecte cu aceleași chei repetate.
 *
 * @param array $plan Planul.
 * @return array Listele.
 */
function ccs_admin_date_simulator( $plan ) {
	$cost = array();
	$taxa = array();
	$pret = array();
	foreach ( $plan['randuri'] as $r ) {
		if ( null === $r['pret_site'] ) {
			continue;
		}
		$cost[] = round( $r['cost'], 4 );
		$taxa[] = round( $r['taxa_eco'], 4 );
		$pret[] = round( $r['pret_site'], 2 );
	}
	return array(
		'cost' => $cost,
		'taxa' => $taxa,
		'pret' => $pret,
	);
}

/* ------------------------------------------------------------------------------------------------ AJAX */

/**
 * Verifică nonce-ul și drepturile. Oprește cererea dacă ceva nu e în regulă.
 *
 * @return void
 */
function ccs_admin_ajax_poarta() {
	check_ajax_referer( 'ccs-decizii' );
	if ( ! current_user_can( CCS_CAPACITATE ) ) {
		wp_send_json_error( array( 'mesaj' => __( 'Nu ai dreptul să faci asta.', 'conex-conect-sync' ) ), 403 );
	}
}

/**
 * Primește o decizie.
 *
 * @return void
 */
function ccs_admin_ajax_decizie() {
	ccs_admin_ajax_poarta();

	$r = ccs_decizie_scrie(
		sanitize_key( wp_unslash( $_POST['tip'] ?? '' ) ),
		sanitize_text_field( wp_unslash( $_POST['referinta'] ?? '' ) ),
		sanitize_key( wp_unslash( $_POST['valoare'] ?? '' ) ),
		sanitize_textarea_field( wp_unslash( $_POST['nota'] ?? '' ) )
	);

	if ( is_wp_error( $r ) ) {
		wp_send_json_error( array( 'mesaj' => $r->get_error_message() ) );
	}

	ccs_admin_uita();
	wp_send_json_success( $r );
}
add_action( 'wp_ajax_ccs_decizie', 'ccs_admin_ajax_decizie' );

/**
 * Aceeași decizie pe mai multe produse deodată.
 *
 * Fără asta, cele șaizeci de produse sub cost ar cere șaizeci de apăsări. Un om care are de dat șaizeci de clicuri
 * nu dă niciunul, iar întrebarea rămâne fără răspuns și blochează tot.
 *
 * @return void
 */
function ccs_admin_ajax_decizie_lot() {
	ccs_admin_ajax_poarta();

	$tip       = sanitize_key( wp_unslash( $_POST['tip'] ?? '' ) );
	$valoare   = sanitize_key( wp_unslash( $_POST['valoare'] ?? '' ) );
	$referinte = array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['referinte'] ?? '' ) ) ) ) );

	if ( ! $referinte ) {
		wp_send_json_error( array( 'mesaj' => __( 'Nu e nimic de hotărât aici.', 'conex-conect-sync' ) ) );
	}

	$facute = 0;
	foreach ( $referinte as $referinta ) {
		$r = ccs_decizie_scrie( $tip, (string) $referinta, $valoare );
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( array( 'mesaj' => $r->get_error_message() ) );
		}
		$facute++;
	}

	ccs_admin_uita();
	wp_send_json_success(
		array(
			'facute' => $facute,
			'efect'  => sprintf(
				/* translators: %d: câte produse. */
				_n( '%d produs rezolvat.', '%d produse rezolvate.', $facute, 'conex-conect-sync' ),
				$facute
			),
		)
	);
}
add_action( 'wp_ajax_ccs_decizie_lot', 'ccs_admin_ajax_decizie_lot' );

/**
 * Salvează politica de preț și întoarce cifrele recalculate pe server.
 *
 * @return void
 */
function ccs_admin_ajax_politica() {
	ccs_admin_ajax_poarta();

	$setari = (array) get_option( CCS_OPTIUNE_SETARI, array() );
	foreach ( array( 'adaos', 'cota_tva', 'marja_minima_lei', 'pret_minim_lei' ) as $cheie ) {
		if ( isset( $_POST[ $cheie ] ) ) {
			$setari[ $cheie ] = (float) str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST[ $cheie ] ) ) );
		}
	}
	if ( isset( $_POST['rotunjire'] ) ) {
		$rot                 = sanitize_key( wp_unslash( $_POST['rotunjire'] ) );
		$setari['rotunjire'] = in_array( $rot, array( 'sus', 'exact' ), true ) ? $rot : 'sus';
	}
	update_option( CCS_OPTIUNE_SETARI, $setari );

	ccs_admin_uita();
	$plan = ccs_admin_plan( true );
	if ( is_wp_error( $plan ) ) {
		wp_send_json_error( array( 'mesaj' => $plan->get_error_message() ) );
	}

	ccs_decizie_scrie( 'politica_pret', '', 'aprobat', wp_json_encode( ccs_admin_politica(), JSON_UNESCAPED_UNICODE ) );

	wp_send_json_success(
		array(
			'rezumat' => $plan['rezumat'],
			'efect'   => ccs_admin_efect( $plan ),
		)
	);
}
add_action( 'wp_ajax_ccs_politica', 'ccs_admin_ajax_politica' );

/**
 * Pornește sau oprește rularea automată.
 *
 * @return void
 */
function ccs_admin_ajax_joburi() {
	ccs_admin_ajax_poarta();

	$pornit = ! empty( $_POST['pornit'] ) && 'false' !== $_POST['pornit'];
	$setari = (array) get_option( CCS_OPTIUNE_SETARI, array() );
	$setari['joburi_pornite'] = $pornit;
	// `update_option` declanșează `ccs_joburi_la_setari()`, care programează sau scoate joburile din coadă.
	update_option( CCS_OPTIUNE_SETARI, $setari );

	wp_send_json_success(
		array(
			'pornit' => $pornit,
			'efect'  => $pornit
				? __( 'Rularea automată e pornită.', 'conex-conect-sync' )
				: __( 'Rularea automată e oprită.', 'conex-conect-sync' ),
		)
	);
}
add_action( 'wp_ajax_ccs_joburi', 'ccs_admin_ajax_joburi' );
