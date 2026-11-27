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
		'panou'     => array( __( 'Panou', 'conex-conect-sync' ), 'dashboard', __( 'Starea sincronizării', 'conex-conect-sync' ), '' ),
		'pret'      => array( __( 'Politica de preț', 'conex-conect-sync' ), 'tag', __( 'Adaos, TVA, marjă minimă', 'conex-conect-sync' ), '' ),
		'potriviri' => array( __( 'Produse de confirmat', 'conex-conect-sync' ), 'randomize', __( 'Cod identic, produs diferit', 'conex-conect-sync' ), 'potrivire' ),
		'scaderi'   => array( __( 'Prețuri care coboară', 'conex-conect-sync' ), 'arrow-down-alt', __( 'Scăderi mai mari de jumătate', 'conex-conect-sync' ), 'scadere' ),
		'subcost'   => array( __( 'Vândute sub cost', 'conex-conect-sync' ), 'warning', __( 'Preț mai mic decât achiziția', 'conex-conect-sync' ), 'sub_cost' ),
		'stoc'      => array( __( 'Stocul', 'conex-conect-sync' ), 'archive', __( 'Cantități și produse epuizate', 'conex-conect-sync' ), '' ),
		'catalog'   => array( __( 'Catalogul', 'conex-conect-sync' ), 'list-view', __( 'Starea celor două cataloage', 'conex-conect-sync' ), '' ),
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
 * Versiunea unui fișier de resurse, pentru cache-ul browserului.
 *
 * Versiunea pluginului plus data fișierului. Prima e pentru om, a doua e cea care contează: se schimbă exact
 * când se schimbă fișierul, deci o corectură de CSS urcată fără ridicarea versiunii ajunge totuși pe ecran.
 *
 * @param string $cale Calea relativă în plugin.
 * @return string Versiunea.
 */
function ccs_admin_versiune_fisier( $cale ) {
	$fisier = CCS_DIR . $cale;
	$data   = file_exists( $fisier ) ? filemtime( $fisier ) : 0;
	return CCS_VERSIUNE . ( $data ? '.' . $data : '' );
}

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
	// Versiunea include data fișierului, nu doar versiunea pluginului. Altfel, o schimbare de CSS urcată fără
	// să ridicăm versiunea rămâne în cache-ul browserului, iar omul vede codul nou cu stilul vechi. S-a
	// întâmplat: butoanele reparate au ajuns pe server și au rămas gri pe ecran.
	wp_enqueue_style( 'ccs-admin', CCS_URL . 'admin/css/ccs-admin.css', array(), ccs_admin_versiune_fisier( 'admin/css/ccs-admin.css' ) );
	wp_enqueue_script( 'ccs-admin', CCS_URL . 'admin/js/ccs-admin.js', array(), ccs_admin_versiune_fisier( 'admin/js/ccs-admin.js' ), true );
}
add_action( 'admin_enqueue_scripts', 'ccs_admin_assets' );

/**
 * Pornește scrierea pe site, adică oprește modul raport.
 *
 * E singura comandă din panou după care sincronizarea începe să schimbe produse de la sine, deci are trei
 * lucruri pe care celelalte nu le au: cere confirmare de două ori în interfață, scrie în jurnal cine a pornit-o,
 * și nu pornește dacă politica de preț nu e confirmată. Ultima nu e o formalitate: fără o politică asumată,
 * prima rulare ar rescrie câteva mii de prețuri după valori pe care nu le-a aprobat nimeni.
 *
 * Oprirea nu are nicio condiție. Un buton de oprire care întreabă ceva e un buton de oprire stricat.
 *
 * @return void
 */
function ccs_admin_ajax_scriere() {
	ccs_admin_ajax_poarta();

	$pornit = ! empty( $_POST['pornit'] ) && 'false' !== $_POST['pornit'];

	if ( $pornit ) {
		$politica = ccs_decizii_curente( 'politica_pret' );
		if ( empty( $politica[''] ) ) {
			wp_send_json_error(
				array(
					'mesaj' => __( 'Confirmă întâi politica de preț. Fără ea, prima rulare ar schimba prețurile după valori pe care nu le-a aprobat nimeni.', 'conex-conect-sync' ),
				)
			);
		}
	}

	$setari               = (array) get_option( CCS_OPTIUNE_SETARI, array() );
	$setari['mod_raport'] = ! $pornit;
	update_option( CCS_OPTIUNE_SETARI, $setari );

	ccs_jurnal(
		'info',
		sprintf(
			'Scrierea pe site a fost %s de %s.',
			$pornit ? 'pornită' : 'oprită',
			wp_get_current_user()->user_login
		)
	);

	ccs_admin_uita();

	wp_send_json_success(
		array(
			'pornit' => $pornit,
			'efect'  => $pornit
				? __( 'Scrierea e pornită. De acum, sincronizarea schimbă prețurile și stocurile pe site.', 'conex-conect-sync' )
				: __( 'Scrierea e oprită. Panoul doar arată ce s-ar schimba.', 'conex-conect-sync' ),
		)
	);
}
add_action( 'wp_ajax_ccs_scriere', 'ccs_admin_ajax_scriere' );

/**
 * Închide ghidul cu pași, definitiv.
 *
 * @return void
 */
function ccs_admin_ajax_inchide_ghid() {
	ccs_admin_ajax_poarta();
	update_option( 'ccs_ghid_inchis', 1, false );
	wp_send_json_success( array( 'efect' => __( 'Gata, nu îl mai arăt.', 'conex-conect-sync' ) ) );
}
add_action( 'wp_ajax_ccs_inchide_ghid', 'ccs_admin_ajax_inchide_ghid' );

/**
 * Subsolul paginilor pluginului.
 *
 * WordPress scrie acolo „Thank you for creating with WordPress”, ceea ce pe o pagină de plugin nu spune nimic
 * despre cine a făcut-o și pe cine suni dacă ceva nu merge. Se schimbă numai pe paginile noastre.
 *
 * @param string $text Textul WordPress.
 * @return string Textul nostru, pe paginile pluginului.
 */
function ccs_admin_subsol( $text ) {
	$ecran = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $ecran || false === strpos( (string) $ecran->id, 'ccs-decizii' ) ) {
		return $text;
	}

	return sprintf(
		/* translators: 1: numele pluginului, 2: versiunea, 3: linkul către Agensio. */
		__( '%1$s %2$s, dezvoltat și întreținut de %3$s', 'conex-conect-sync' ),
		'<strong>Sincronizare Conex</strong>',
		esc_html( CCS_VERSIUNE ),
		'<a href="https://agensio.ro" target="_blank" rel="noopener">Agensio</a>'
	);
}
add_filter( 'admin_footer_text', 'ccs_admin_subsol' );

/**
 * Scoate și versiunea WordPress din colțul din dreapta al subsolului, pe paginile noastre.
 *
 * @param string $text Textul.
 * @return string Gol pe paginile pluginului.
 */
function ccs_admin_subsol_versiune( $text ) {
	$ecran = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $ecran && false !== strpos( (string) $ecran->id, 'ccs-decizii' ) ) {
		return '';
	}
	return $text;
}
add_filter( 'update_footer', 'ccs_admin_subsol_versiune', 20 );

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
 * Candidații la import, ținuți în cache.
 *
 * Calculul trece prin toate produsele nelegate și cheamă maparea de categorii pentru fiecare, deci nu are ce căuta
 * la fiecare încărcare de pagină.
 *
 * @param bool $proaspat Dacă true, recalculează.
 * @return array Rezumatul.
 */
function ccs_admin_candidati( $proaspat = false ) {
	static $memorie = null;
	if ( null !== $memorie && ! $proaspat ) {
		return $memorie;
	}
	if ( ! $proaspat ) {
		$cache = get_transient( 'ccs_candidati_cache' );
		if ( is_array( $cache ) ) {
			$memorie = $cache;
			return $memorie;
		}
	}
	$r = ccs_import_candidati();
	// Lista întreagă n-are ce căuta în cache; păstrăm doar primele, pentru exemple. Căile rămân toate: sunt sub
	// o sută și sunt tocmai lucrul pe care omul îl are de rezolvat.
	$r['lista'] = array_slice( $r['lista'], 0, 10 );
	set_transient( 'ccs_candidati_cache', $r, 10 * MINUTE_IN_SECONDS );
	$memorie = $r;
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
	delete_transient( 'ccs_candidati_cache' );
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
		wp_send_json_error( array( 'mesaj' => __( 'Nu există produse în această situație.', 'conex-conect-sync' ) ) );
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
	update_option( CCS_OPTIUNE_SETARI, $setari );

	// Explicit, nu prin hook-ul de setări: dacă setarea era deja pornită, `update_option()` nu schimbă nimic și
	// hook-ul nu se declanșează, iar butonul ar raporta „pornită” peste o coadă goală.
	if ( $pornit ) {
		ccs_joburi_porneste();
	} else {
		ccs_joburi_opreste();
	}

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

/**
 * Retrage de la vânzare un grup de produse.
 *
 * @return void
 */
function ccs_admin_ajax_retrage() {
	ccs_admin_ajax_poarta();

	$grup    = sanitize_key( wp_unslash( $_POST['grup'] ?? '' ) );
	$grupuri = ccs_retragere_grupuri();
	if ( ! isset( $grupuri[ $grup ] ) ) {
		wp_send_json_error( array( 'mesaj' => __( 'Grup necunoscut.', 'conex-conect-sync' ) ) );
	}

	$r = ccs_retrage( array_keys( $grupuri[ $grup ] ), array( 'motiv' => 'din panou, grupul ' . $grup ) );

	wp_send_json_success(
		array(
			'efect' => sprintf(
				/* translators: 1: câte produse, 2: id-ul rulării. */
				__( '%1$d produse trecute pe stoc epuizat. Se pot readuce cu `wp ccs revino %2$d`.', 'conex-conect-sync' ),
				$r['atinse'],
				$r['rulare']
			),
		)
	);
}
add_action( 'wp_ajax_ccs_retrage', 'ccs_admin_ajax_retrage' );

/**
 * Aduce pe site produsele bifate în catalog.
 *
 * Produsele intră ca ciorne, deci nimic nu ajunge în magazin fără ca un om să publice. Plafonul e aici ca o
 * apăsare pe „bifează tot” să nu pornească o rulare de o mie de produse dintr-un clic: fiecare produs înseamnă
 * poze descărcate și convertite, deci o rulare mare ține minute bune, iar o cerere web nu trebuie să țină atât.
 *
 * @return void
 */
function ccs_admin_ajax_importa() {
	ccs_admin_ajax_poarta();

	$brut   = isset( $_POST['coduri'] ) ? sanitize_text_field( wp_unslash( $_POST['coduri'] ) ) : '';
	$coduri = array_filter( array_map( 'trim', explode( ',', $brut ) ) );

	if ( ! $coduri ) {
		wp_send_json_error( array( 'mesaj' => __( 'Nu ai bifat niciun produs.', 'conex-conect-sync' ) ) );
	}

	$plafon = (int) apply_filters( 'ccs_import_din_panou_plafon', 40 );
	if ( count( $coduri ) > $plafon ) {
		wp_send_json_error(
			array(
				'mesaj' => sprintf(
					/* translators: 1: câte au fost bifate, 2: plafonul. */
					__( 'Ai bifat %1$d produse, iar dintr-o apăsare se aduc cel mult %2$d. Fiecare produs înseamnă poze descărcate, deci se face pe bucăți.', 'conex-conect-sync' ),
					count( $coduri ),
					$plafon
				),
			)
		);
	}

	$r = ccs_import_executa( array( 'coduri' => $coduri ) );
	if ( is_wp_error( $r ) ) {
		wp_send_json_error( array( 'mesaj' => $r->get_error_message() ) );
	}

	$spus = sprintf(
		/* translators: 1: câte produse, 2: câte poze. */
		__( '%1$d produse aduse ca ciorne, cu %2$d poze.', 'conex-conect-sync' ),
		$r['create'],
		$r['poze']
	);
	if ( $r['sarite'] ) {
		/* translators: %d: câte au fost sărite. */
		$spus .= ' ' . sprintf( __( '%d sărite.', 'conex-conect-sync' ), $r['sarite'] );
	}
	if ( $r['esuate'] ) {
		/* translators: %d: câte au eșuat. */
		$spus .= ' ' . sprintf( __( '%d eșuate, scrise în jurnal.', 'conex-conect-sync' ), $r['esuate'] );
	}

	wp_send_json_success( array( 'efect' => $spus ) );
}
add_action( 'wp_ajax_ccs_importa', 'ccs_admin_ajax_importa' );

/**
 * Dă înapoi o rulare, din panou.
 *
 * Până acum revenirea exista doar ca `wp ccs revino`, adică numai pentru cineva cu acces la terminal. Pentru
 * client, o sincronizare care îi schimbă trei mii de prețuri fără buton de înapoi e un sistem în care trebuie să
 * aibă încredere orbește. Ăsta e butonul.
 *
 * Numai ultima rulare se poate da înapoi, și numai dacă mai are modificări neanulate. O rulare de acum trei zile,
 * peste care s-au aplicat altele, ar lăsa prețuri amestecate: o parte vechi, o parte noi, fără ca cineva să poată
 * spune care.
 *
 * @return void
 */
function ccs_admin_ajax_revino() {
	ccs_admin_ajax_poarta();

	$cerut = (int) ( $_POST['rulare'] ?? 0 );
	if ( ! $cerut ) {
		wp_send_json_error( array( 'mesaj' => __( 'Nu știu ce rulare să dau înapoi.', 'conex-conect-sync' ) ) );
	}

	$ultimele = ccs_rulari_recente( 1 );
	if ( ! $ultimele || (int) $ultimele[0]['id'] !== $cerut ) {
		wp_send_json_error(
			array(
				'mesaj' => __( 'Se poate da înapoi doar ultima rulare. Între timp a rulat altceva, deci reîncarcă pagina.', 'conex-conect-sync' ),
			)
		);
	}

	if ( ! ccs_rulare_de_revenit( $cerut ) ) {
		wp_send_json_error( array( 'mesaj' => __( 'Rularea asta nu mai are nimic de dat înapoi.', 'conex-conect-sync' ) ) );
	}

	$r = ccs_revino( $cerut );
	if ( is_wp_error( $r ) ) {
		wp_send_json_error( array( 'mesaj' => $r->get_error_message() ) );
	}

	ccs_admin_uita();

	$spuse = array();
	if ( ! empty( $r['produse'] ) ) {
		/* translators: %d: câte produse. */
		$spuse[] = sprintf( __( '%d produse readuse la valorile dinainte', 'conex-conect-sync' ), $r['produse'] );
	}
	if ( ! empty( $r['sterse'] ) ) {
		/* translators: %d: câte produse. */
		$spuse[] = sprintf( __( '%d produse trecute la coșul de gunoi', 'conex-conect-sync' ), $r['sterse'] );
	}
	if ( ! empty( $r['readuse'] ) ) {
		/* translators: %d: câte produse. */
		$spuse[] = sprintf( __( '%d produse scoase din coșul de gunoi', 'conex-conect-sync' ), $r['readuse'] );
	}

	wp_send_json_success(
		array(
			'efect' => $spuse ? implode( '. ', $spuse ) . '.' : __( 'Nu era nimic de dat înapoi.', 'conex-conect-sync' ),
		)
	);
}
add_action( 'wp_ajax_ccs_revino', 'ccs_admin_ajax_revino' );

/**
 * Scrie o asociere de categorie pusă de om.
 *
 * Regulile puse de om bat regulile învățate și nu se pierd la reînvățare. Un `term_id` zero șterge asocierea.
 *
 * @return void
 */
function ccs_admin_ajax_mapeaza() {
	ccs_admin_ajax_poarta();
	global $wpdb;

	$cale    = sanitize_text_field( wp_unslash( $_POST['cale'] ?? '' ) );
	$term_id = (int) ( $_POST['term_id'] ?? 0 );
	if ( '' === $cale ) {
		wp_send_json_error( array( 'mesaj' => __( 'Lipsește calea.', 'conex-conect-sync' ) ) );
	}

	$tabel = ccs_tabel( 'categorii' );
	$wpdb->delete( $tabel, array( 'cale' => $cale, 'contine' => '' ) );

	if ( ! $term_id ) {
		ccs_admin_uita();
		wp_send_json_success( array( 'efect' => __( 'Asocierea a fost ștearsă.', 'conex-conect-sync' ) ) );
	}

	$termen = get_term( $term_id, 'product_cat' );
	if ( ! $termen || is_wp_error( $termen ) ) {
		wp_send_json_error( array( 'mesaj' => __( 'Categoria nu există.', 'conex-conect-sync' ) ) );
	}

	$wpdb->insert(
		$tabel,
		array(
			'cale'      => $cale,
			'contine'   => '',
			'term_id'   => $term_id,
			'incredere' => 100,
			'produse'   => 0,
			'sursa'     => 'om',
			'creat_la'  => current_time( 'mysql' ),
		)
	);

	ccs_decizie_scrie( 'categorie', $cale, 'mapat', $termen->name );
	ccs_admin_uita();

	wp_send_json_success(
		array(
			'efect' => sprintf(
				/* translators: %s: numele categoriei. */
				__( 'Asociate cu „%s”. Produsele din această categorie trec în lista de import.', 'conex-conect-sync' ),
				$termen->name
			),
		)
	);
}
add_action( 'wp_ajax_ccs_mapeaza', 'ccs_admin_ajax_mapeaza' );

/**
 * Filtrează lista de produse din WordPress pe un grup al nostru.
 *
 * Pentru „ce dispare” nu construim tabel propriu: alea sunt produse WooCommerce reale, iar clientul le lucrează mai
 * bine în lista pe care o știe, cu filtrele și acțiunile ei.
 *
 * @param WP_Query $q Interogarea.
 * @return void
 */
/**
 * Grupurile după care se poate filtra lista de produse din wp-admin.
 *
 * Ownerul lucrează în `Produse`, nu în panoul pluginului, și cu mii de produse în listă nu mai găsește nimic.
 * Grupurile de aici sunt întrebările pe care și le pune de fapt: „ce vine de la Conex”, „ce am pus eu de mână”,
 * „ce a rămas din feed-ul vechi”.
 *
 * @return array cheie => eticheta.
 */
function ccs_admin_lista_grupuri() {
	return array(
		'conex'          => __( 'Legate de Conex', 'conex-conect-sync' ),
		'manuale'        => __( 'Introduse manual de echipă', 'conex-conect-sync' ),
		'din_feed'       => __( 'Rămase din feed-ul vechi', 'conex-conect-sync' ),
		'ascunse'        => __( 'Ascunse din catalog', 'conex-conect-sync' ),
		'fara_categorie' => __( 'Fără categorie', 'conex-conect-sync' ),
	);
}

/**
 * Id-urile produselor dintr-un grup, luate direct din baza de date.
 *
 * Nu folosim `ccs_retragere_grupuri()`, deși are aceleași nume de grupuri: aia cheamă potrivirea întreagă, care
 * ia minute pe un catalog de douăzeci de mii de produse. Pentru un filtru pe care omul îl apasă des, trebuie
 * interogări care trec prin indecși.
 *
 * @param string $grup Cheia grupului.
 * @return array|null Id-uri, sau null dacă grupul nu se filtrează pe id-uri.
 */
function ccs_admin_lista_ids( $grup ) {
	global $wpdb;
	$links = ccs_tabel( 'links' );

	if ( 'conex' === $grup ) {
		return array_map( 'intval', (array) $wpdb->get_col( "SELECT wc_product_id FROM $links" ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	// Tiparul codului spune de unde vine produsul: `CZ1135` e pus de mână, `10000` a venit din feed-ul Mega Drive.
	// Aceeași regulă ca în `retragere.php`, scrisă aici în SQL ca să nu ceară potrivirea întreagă.
	$tipare = array(
		'manuale'  => '^[A-Za-z]{2}[0-9]+$',
		'din_feed' => '^[0-9]{4,6}$',
	);
	if ( isset( $tipare[ $grup ] ) ) {
		return array_map(
			'intval',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL
					"SELECT m.post_id FROM {$wpdb->postmeta} m
					 LEFT JOIN $links l ON l.wc_product_id = m.post_id
					 WHERE m.meta_key = '_sku' AND m.meta_value REGEXP %s AND l.wc_product_id IS NULL",
					$tipare[ $grup ]
				)
			)
		);
	}

	return null;
}

/**
 * Căsuța de filtrare de deasupra listei de produse.
 *
 * @return void
 */
function ccs_admin_lista_filtru_ui() {
	global $typenow;
	if ( 'product' !== $typenow || ! current_user_can( CCS_CAPACITATE ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$acum = isset( $_GET['ccs_grup'] ) ? sanitize_key( wp_unslash( $_GET['ccs_grup'] ) ) : '';

	echo '<select name="ccs_grup" id="ccs_grup">';
	printf( '<option value="">%s</option>', esc_html__( 'Toate sursele', 'conex-conect-sync' ) );
	foreach ( ccs_admin_lista_grupuri() as $cheie => $eticheta ) {
		printf(
			'<option value="%s"%s>%s</option>',
			esc_attr( $cheie ),
			selected( $acum, $cheie, false ),
			esc_html( $eticheta )
		);
	}
	echo '</select>';
}
add_action( 'restrict_manage_posts', 'ccs_admin_lista_filtru_ui' );

/**
 * Aplică filtrul pe lista de produse.
 *
 * @param WP_Query $q Interogarea.
 * @return void
 */
function ccs_admin_filtru_lista( $q ) {
	if ( ! is_admin() || ! $q->is_main_query() || 'product' !== $q->get( 'post_type' ) ) {
		return;
	}
	$grup = isset( $_GET['ccs_grup'] ) ? sanitize_key( wp_unslash( $_GET['ccs_grup'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! $grup || ! current_user_can( CCS_CAPACITATE ) ) {
		return;
	}

	if ( 'ascunse' === $grup ) {
		$q->set(
			'tax_query',
			array(
				array(
					'taxonomy' => 'product_visibility',
					'field'    => 'name',
					'terms'    => 'exclude-from-catalog',
				),
			)
		);
		return;
	}

	if ( 'fara_categorie' === $grup ) {
		$q->set(
			'tax_query',
			array(
				array(
					'taxonomy' => 'product_cat',
					'operator' => 'NOT EXISTS',
				),
			)
		);
		return;
	}

	$ids = ccs_admin_lista_ids( $grup );
	if ( null === $ids ) {
		// Grupurile vechi, din panou, care vin tot pe `?ccs_grup=`. Aici chiar merită potrivirea întreagă,
		// fiindcă se ajunge pe link din panou, nu prin apăsarea unui filtru.
		$grupuri = ccs_retragere_grupuri();
		$ids     = isset( $grupuri[ $grup ] ) ? array_keys( $grupuri[ $grup ] ) : array();
	}

	// `post__in` gol ar întoarce tot, deci punem un id imposibil când grupul e gol.
	$q->set( 'post__in', $ids ? $ids : array( 0 ) );
}
add_action( 'pre_get_posts', 'ccs_admin_filtru_lista' );

/**
 * Coloana „Conex” din lista de produse.
 *
 * @param array $coloane Coloanele.
 * @return array Coloanele, cu a noastră înaintea prețului.
 */
function ccs_admin_coloana( $coloane ) {
	$iesire = array();
	foreach ( $coloane as $cheie => $eticheta ) {
		if ( 'price' === $cheie ) {
			$iesire['ccs'] = __( 'Conex', 'conex-conect-sync' );
		}
		$iesire[ $cheie ] = $eticheta;
	}
	if ( ! isset( $iesire['ccs'] ) ) {
		$iesire['ccs'] = __( 'Conex', 'conex-conect-sync' );
	}
	return $iesire;
}
add_filter( 'manage_edit-product_columns', 'ccs_admin_coloana' );

/**
 * Datele furnizorului pentru produsele de pe pagina curentă, dintr-o singură interogare.
 *
 * Prima variantă făcea un `get_row()` cu JOIN pentru fiecare rând. La 20 de produse pe pagină erau 20 de
 * interogări, iar cu Screen Options pe 200 erau două sute, pe un ecran pe care ownerul îl deschide zilnic.
 * Acum lista de id-uri se adună la `the_posts` și se citește o dată.
 *
 * @param int $post_id Produsul.
 * @return object|null Rândul, sau null dacă produsul nu e legat de furnizor.
 */
function ccs_admin_coloana_date( $post_id ) {
	global $wpdb;
	static $date = null;

	if ( null === $date ) {
		$ids = ccs_admin_coloana_ids();
		if ( ! $ids ) {
			$date = array();
		} else {
			$links   = ccs_tabel( 'links' );
			$produse = ccs_tabel( 'products' );
			$locuri  = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$date    = $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL
					"SELECT l.wc_product_id, c.cod, c.cost, c.stoc, l.incredere
					 FROM $links l INNER JOIN $produse c ON c.product_id = l.product_id
					 WHERE l.wc_product_id IN ($locuri)",
					$ids
				),
				OBJECT_K
			);
		}
	}

	return $date[ $post_id ] ?? null;
}

/**
 * Id-urile produselor pe care le afișează pagina curentă.
 *
 * @param array|null $pune Lista, când o pune `the_posts`.
 * @return array Id-urile.
 */
function ccs_admin_coloana_ids( $pune = null ) {
	static $ids = array();
	if ( null !== $pune ) {
		$ids = $pune;
	}
	return $ids;
}

/**
 * Strânge id-urile paginii curente, înainte să se deseneze coloanele.
 *
 * @param array $posts Produsele.
 * @return array Produsele, neatinse.
 */
function ccs_admin_coloana_aduna( $posts ) {
	global $typenow;
	if ( is_admin() && 'product' === $typenow && is_array( $posts ) ) {
		ccs_admin_coloana_ids( wp_list_pluck( $posts, 'ID' ) );
	}
	return $posts;
}
add_filter( 'the_posts', 'ccs_admin_coloana_aduna' );

/**
 * Conținutul coloanei: ce știe furnizorul despre produsul ăsta, acum.
 *
 * Costul și stocul sunt cele din ultima citire a catalogului, nu din WooCommerce. Asta e și ideea: coloana
 * răspunde la „mai există la furnizor și cu cât?”, întrebare la care lista de produse nu răspundea deloc.
 *
 * @param string $coloana Coloana.
 * @param int    $post_id Produsul.
 * @return void
 */
function ccs_admin_coloana_continut( $coloana, $post_id ) {
	if ( 'ccs' !== $coloana ) {
		return;
	}

	$furnizor = ccs_admin_coloana_date( (int) $post_id );

	if ( ! $furnizor ) {
		$sku = (string) get_post_meta( $post_id, '_sku', true );
		if ( preg_match( '/^[A-Za-z]{2}[0-9]+$/', $sku ) ) {
			printf( '<span class="ccs-col ccs-col--manual">%s</span>', esc_html__( 'pus de echipă', 'conex-conect-sync' ) );
			return;
		}
		printf( '<span class="ccs-col ccs-col--lipsa">%s</span>', esc_html__( 'nu e la Conex', 'conex-conect-sync' ) );
		return;
	}

	printf(
		'<span class="ccs-col ccs-col--legat">%s</span><br><span class="ccs-col-mic">%s</span>',
		esc_html(
			sprintf(
				/* translators: %d: stocul la furnizor. */
				_n( 'stoc %d la furnizor', 'stoc %d la furnizor', (int) $furnizor->stoc, 'conex-conect-sync' ),
				(int) $furnizor->stoc
			)
		),
		esc_html(
			sprintf(
				/* translators: %s: costul de achiziție. */
				__( 'cost %s lei', 'conex-conect-sync' ),
				ccs_lei( (float) $furnizor->cost )
			)
		)
	);
}
add_action( 'manage_product_posts_custom_column', 'ccs_admin_coloana_continut', 10, 2 );

/**
 * Puțin stil pentru coloană, pus direct: trei reguli nu merită un fișier încărcat pe tot adminul.
 *
 * @return void
 */
function ccs_admin_coloana_stil() {
	global $typenow;
	if ( 'product' !== $typenow ) {
		return;
	}
	echo '<style>
		.column-ccs { width: 11em; }
		.ccs-col { font-size: 12px; }
		.ccs-col--legat { color: #1a7f45; }
		.ccs-col--manual { color: #8a6d1f; }
		.ccs-col--lipsa { color: #9aa0a6; }
		.ccs-col-mic { font-size: 11px; color: #6b7280; }
	</style>';
}
add_action( 'admin_head', 'ccs_admin_coloana_stil' );
