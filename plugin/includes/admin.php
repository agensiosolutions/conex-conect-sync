<?php
/**
 * Pagina de decizii din admin.
 *
 * De ce există: rapoartele CSV sunt bune pentru cine construiește sincronizarea, dar nu pentru cine trebuie să
 * hotărască. Un fișier cu 3.954 de rânduri nu se citește, iar deciziile luate pe email se pierd. Pagina asta pune
 * fiecare întrebare cu datele lângă ea și scrie răspunsul în `ccs_decizii`, cu cine a răspuns și când.
 *
 * Patru întrebări, în ordinea în care contează:
 *   1. Politica de preț. Simulatorul arată efectul pe tot magazinul înainte să se schimbe orice.
 *   2. Potrivirile la care codul zice una și numele alta.
 *   3. Prețurile care ar coborî la mai puțin de jumătate.
 *   4. Produsele vândute sub costul de achiziție.
 *
 * Simulatorul calculează în browser, nu pe server: datele sunt deja în pagină, iar un om care trage de un adaos
 * vrea să vadă efectul imediat, nu după o secundă de așteptare. Formula din JavaScript e aceeași cu `ccs_pret()`;
 * la salvare, serverul recalculează și pagina arată cifrele lui, ca o eventuală diferență să sară în ochi.
 *
 * @package conex-conect-sync
 */

defined( 'ABSPATH' ) || exit;

const CCS_CAPACITATE = 'manage_woocommerce';

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
 * Planul, ținut în cache câteva minute.
 *
 * Calculul trece prin 4.000 de produse și două tabele; la fiecare apăsare de buton n-are rost refăcut. Butonul
 * „Recalculează” îl forțează.
 *
 * @param bool $proaspat Dacă true, recalculează.
 * @return array|WP_Error Planul.
 */
function ccs_admin_plan( $proaspat = false ) {
	$cheie = 'ccs_plan_cache';
	if ( ! $proaspat ) {
		$cache = get_transient( $cheie );
		if ( is_array( $cache ) ) {
			return $cache;
		}
	}
	$plan = ccs_plan();
	if ( is_wp_error( $plan ) ) {
		return $plan;
	}
	set_transient( $cheie, $plan, 10 * MINUTE_IN_SECONDS );
	return $plan;
}

/**
 * Șterge cache-ul planului.
 *
 * @return void
 */
function ccs_admin_uita_planul() {
	delete_transient( 'ccs_plan_cache' );
}

/**
 * Primește o decizie prin AJAX.
 *
 * @return void
 */
function ccs_admin_ajax_decizie() {
	check_ajax_referer( 'ccs-decizii' );
	if ( ! current_user_can( CCS_CAPACITATE ) ) {
		wp_send_json_error( array( 'mesaj' => __( 'Nu ai dreptul să faci asta.', 'conex-conect-sync' ) ), 403 );
	}

	$r = ccs_decizie_scrie(
		sanitize_key( wp_unslash( $_POST['tip'] ?? '' ) ),
		sanitize_text_field( wp_unslash( $_POST['referinta'] ?? '' ) ),
		sanitize_key( wp_unslash( $_POST['valoare'] ?? '' ) ),
		sanitize_textarea_field( wp_unslash( $_POST['nota'] ?? '' ) )
	);

	if ( is_wp_error( $r ) ) {
		wp_send_json_error( array( 'mesaj' => $r->get_error_message() ) );
	}

	ccs_admin_uita_planul();
	wp_send_json_success( $r );
}
add_action( 'wp_ajax_ccs_decizie', 'ccs_admin_ajax_decizie' );

/**
 * Aceeași decizie pe mai multe produse deodată.
 *
 * Fără asta, cele 60 de produse sub cost ar cere 60 de apăsări. Un client care are de dat 60 de clicuri nu dă niciunul
 * și întrebarea rămâne fără răspuns, ceea ce blochează tot.
 *
 * @return void
 */
function ccs_admin_ajax_decizie_lot() {
	check_ajax_referer( 'ccs-decizii' );
	if ( ! current_user_can( CCS_CAPACITATE ) ) {
		wp_send_json_error( array( 'mesaj' => __( 'Nu ai dreptul să faci asta.', 'conex-conect-sync' ) ), 403 );
	}

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

	ccs_admin_uita_planul();
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
	check_ajax_referer( 'ccs-decizii' );
	if ( ! current_user_can( CCS_CAPACITATE ) ) {
		wp_send_json_error( array( 'mesaj' => __( 'Nu ai dreptul să faci asta.', 'conex-conect-sync' ) ), 403 );
	}

	$setari = (array) get_option( CCS_OPTIUNE_SETARI, array() );
	$numere = array( 'adaos', 'cota_tva', 'marja_minima_lei', 'pret_minim_lei' );
	foreach ( $numere as $cheie ) {
		if ( isset( $_POST[ $cheie ] ) ) {
			$setari[ $cheie ] = (float) str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST[ $cheie ] ) ) );
		}
	}
	if ( isset( $_POST['rotunjire'] ) ) {
		$rot                  = sanitize_key( wp_unslash( $_POST['rotunjire'] ) );
		$setari['rotunjire']  = in_array( $rot, array( 'sus', 'exact' ), true ) ? $rot : 'sus';
	}
	update_option( CCS_OPTIUNE_SETARI, $setari );

	ccs_admin_uita_planul();
	$plan = ccs_admin_plan( true );
	if ( is_wp_error( $plan ) ) {
		wp_send_json_error( array( 'mesaj' => $plan->get_error_message() ) );
	}

	ccs_decizie_scrie( 'politica_pret', '', 'aprobat', wp_json_encode( ccs_admin_politica_curenta(), JSON_UNESCAPED_UNICODE ) );

	wp_send_json_success(
		array(
			'rezumat' => $plan['rezumat'],
			'efect'   => ccs_admin_efect( $plan ),
		)
	);
}
add_action( 'wp_ajax_ccs_politica', 'ccs_admin_ajax_politica' );

/**
 * Politica de preț curentă, doar câmpurile care se pot schimba din pagină.
 *
 * @return array Politica.
 */
function ccs_admin_politica_curenta() {
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
 * Efectul politicii pe tot magazinul: câte prețuri urcă, câte coboară, cât se schimbă marja.
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
 * Doar ce trebuie pentru formula de preț: cost, taxă verde și prețul de acum. Nici nume, nici altceva.
 *
 * @param array $plan Planul.
 * @return array Liste paralele, ca să nu umflăm pagina cu chei repetate de 4.000 de ori.
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

/**
 * Lei, cu virgulă și separator de mii, cum se citesc în România.
 *
 * @param float $v         Valoarea.
 * @param int   $zecimale  Câte zecimale.
 * @return string Textul.
 */
function ccs_lei( $v, $zecimale = 2 ) {
	return number_format( (float) $v, $zecimale, ',', '.' );
}

/**
 * Pagina.
 *
 * @return void
 */
function ccs_admin_pagina() {
	if ( ! current_user_can( CCS_CAPACITATE ) ) {
		wp_die( esc_html__( 'Nu ai acces la pagina asta.', 'conex-conect-sync' ) );
	}

	$proaspat = isset( $_GET['recalculeaza'] ) && check_admin_referer( 'ccs-recalculeaza' ); // phpcs:ignore WordPress.Security.NonceVerification
	$plan     = ccs_admin_plan( $proaspat );

	echo '<div class="wrap ccs">';
	echo '<h1>' . esc_html__( 'Sincronizare Conex: ce trebuie hotărât', 'conex-conect-sync' ) . '</h1>';

	if ( is_wp_error( $plan ) ) {
		printf(
			'<div class="notice notice-error"><p>%s</p></div></div>',
			esc_html( $plan->get_error_message() )
		);
		return;
	}

	$rezumat   = $plan['rezumat'];
	$potrivire = ccs_potriveste( false );
	$decizii   = array(
		'potrivire' => ccs_decizii_curente( 'potrivire' ),
		'scadere'   => ccs_decizii_curente( 'scadere' ),
		'sub_cost'  => ccs_decizii_curente( 'sub_cost' ),
		'epuizare'  => ccs_decizii_curente( 'epuizare' ),
	);

	$scaderi  = array();
	$sub_cost = array();
	foreach ( $plan['randuri'] as $r ) {
		if ( isset( $r['blocaje']['scadere_mare'] ) ) {
			$scaderi[] = $r;
		}
		if ( $r['sub_cost'] ) {
			$sub_cost[] = $r;
		}
	}
	usort(
		$sub_cost,
		static function ( $a, $b ) {
			return ( $a['pret_site'] - $a['cost_cu_tva'] ) <=> ( $b['pret_site'] - $b['cost_cu_tva'] );
		}
	);

	$de_raspuns = array(
		'potrivire' => count( $potrivire['lista_verificat'] ) - count( $decizii['potrivire'] ),
		'scadere'   => count( $scaderi ) - count( array_intersect_key( $decizii['scadere'], array_flip( wp_list_pluck( $scaderi, 'wc_id' ) ) ) ),
		'sub_cost'  => count( $sub_cost ) - count( array_intersect_key( $decizii['sub_cost'], array_flip( wp_list_pluck( $sub_cost, 'wc_id' ) ) ) ),
	);

	wp_localize_script(
		'ccs-admin',
		'ccsDate',
		array(
			'nonce'     => wp_create_nonce( 'ccs-decizii' ),
			'ajaxurl'   => admin_url( 'admin-ajax.php' ),
			'simulator' => ccs_admin_date_simulator( $plan ),
			'politica'  => ccs_admin_politica_curenta(),
			'efect'     => ccs_admin_efect( $plan ),
			'texte'     => array(
				'salvat'   => __( 'Salvat.', 'conex-conect-sync' ),
				'eroare'   => __( 'Nu s-a putut salva. Încearcă din nou.', 'conex-conect-sync' ),
				'lucrez'   => __( 'Salvez...', 'conex-conect-sync' ),
				'confirma' => __( 'Apasă din nou ca să confirmi', 'conex-conect-sync' ),
			),
		)
	);

	ccs_admin_rand_sumar( $rezumat, $de_raspuns );
	ccs_admin_navigare( $de_raspuns );
	ccs_admin_sectiune_pret( $rezumat );
	ccs_admin_sectiune_potriviri( $potrivire['lista_verificat'], $decizii['potrivire'] );
	ccs_admin_sectiune_scaderi( $scaderi, $decizii['scadere'] );
	ccs_admin_sectiune_sub_cost( $sub_cost, $decizii['sub_cost'] );
	ccs_admin_sectiune_stoc( $rezumat, $decizii['epuizare'] );

	printf(
		'<p class="ccs-recalc"><a class="button" href="%s">%s</a> <span class="description">%s</span></p>',
		esc_url( wp_nonce_url( admin_url( 'admin.php?page=ccs-decizii&recalculeaza=1' ), 'ccs-recalculeaza' ) ),
		esc_html__( 'Recalculează', 'conex-conect-sync' ),
		esc_html__( 'Cifrele se recalculează oricum la fiecare 10 minute.', 'conex-conect-sync' )
	);

	echo '</div>';
}

/**
 * Rândul de sus, cu cifrele mari.
 *
 * @param array $rezumat    Rezumatul planului.
 * @param array $de_raspuns Câte întrebări au rămas pe fiecare secțiune.
 * @return void
 */
function ccs_admin_rand_sumar( $rezumat, $de_raspuns ) {
	$total = array_sum( $de_raspuns );
	echo '<div class="ccs-sumar">';
	ccs_admin_cifra( $rezumat['produse'], __( 'produse sincronizate', 'conex-conect-sync' ) );
	ccs_admin_cifra( $rezumat['pret'], __( 'prețuri de schimbat', 'conex-conect-sync' ) );
	ccs_admin_cifra( $rezumat['stoc'], __( 'stocuri de schimbat', 'conex-conect-sync' ) );
	ccs_admin_cifra( $total, __( 'întrebări fără răspuns', 'conex-conect-sync' ), $total ? 'atentie' : 'bine' );
	echo '</div>';
}

/**
 * O cifră mare, cu eticheta ei.
 *
 * @param int    $cifra    Cifra.
 * @param string $eticheta Eticheta.
 * @param string $stare    '', 'atentie' sau 'bine'.
 * @return void
 */
function ccs_admin_cifra( $cifra, $eticheta, $stare = '' ) {
	printf(
		'<div class="ccs-cifra %s"><strong>%s</strong><span>%s</span></div>',
		esc_attr( $stare ),
		esc_html( number_format( (int) $cifra, 0, ',', '.' ) ),
		esc_html( $eticheta )
	);
}

/**
 * Navigarea între secțiuni.
 *
 * @param array $de_raspuns Câte întrebări au rămas.
 * @return void
 */
function ccs_admin_navigare( $de_raspuns ) {
	$sectiuni = array(
		'pret'      => array( __( '1. Politica de preț', 'conex-conect-sync' ), 0 ),
		'potriviri' => array( __( '2. Produse de confirmat', 'conex-conect-sync' ), $de_raspuns['potrivire'] ),
		'scaderi'   => array( __( '3. Prețuri care coboară mult', 'conex-conect-sync' ), $de_raspuns['scadere'] ),
		'subcost'   => array( __( '4. Vândute sub cost', 'conex-conect-sync' ), $de_raspuns['sub_cost'] ),
		'stoc'      => array( __( '5. Stocul', 'conex-conect-sync' ), 0 ),
	);
	echo '<nav class="ccs-nav">';
	foreach ( $sectiuni as $id => list( $titlu, $rest ) ) {
		printf(
			'<a href="#ccs-%s">%s%s</a>',
			esc_attr( $id ),
			esc_html( $titlu ),
			$rest > 0 ? '<em>' . esc_html( $rest ) . '</em>' : ''
		);
	}
	echo '</nav>';
}

/**
 * Secțiunea 1: politica de preț, cu simulator.
 *
 * @param array $rezumat Rezumatul planului.
 * @return void
 */
function ccs_admin_sectiune_pret( $rezumat ) {
	$p = ccs_admin_politica_curenta();
	?>
	<section class="ccs-sectiune" id="ccs-pret">
		<h2><?php esc_html_e( '1. Politica de preț', 'conex-conect-sync' ); ?></h2>
		<p class="ccs-explic">
			<?php esc_html_e( 'Prețul de vânzare se calculează din costul de achiziție după regula de mai jos. Trage de valori și vezi imediat ce se întâmplă cu tot magazinul. Nimic nu se schimbă pe site până nu apeși „Salvează politica”.', 'conex-conect-sync' ); ?>
		</p>

		<div class="ccs-formula">
			<code><?php esc_html_e( 'preț = cel mai mare dintre: (cost × TVA × adaos) și (cost × TVA + marjă minimă)', 'conex-conect-sync' ); ?></code>
		</div>

		<div class="ccs-doua">
			<div class="ccs-controale">
				<label>
					<span><?php esc_html_e( 'Adaos comercial', 'conex-conect-sync' ); ?></span>
					<input type="range" id="ccs-adaos" min="1" max="2.5" step="0.01" value="<?php echo esc_attr( $p['adaos'] ); ?>">
					<output id="ccs-adaos-val"></output>
				</label>
				<label>
					<span><?php esc_html_e( 'Cota de TVA inclusă în preț', 'conex-conect-sync' ); ?></span>
					<select id="ccs-cota">
						<option value="1.19" <?php selected( $p['cota_tva'], 1.19 ); ?>><?php esc_html_e( '19% (cota veche, cea de pe site acum)', 'conex-conect-sync' ); ?></option>
						<option value="1.21" <?php selected( $p['cota_tva'], 1.21 ); ?>><?php esc_html_e( '21% (cota în vigoare din august 2025)', 'conex-conect-sync' ); ?></option>
						<option value="1.11" <?php selected( $p['cota_tva'], 1.11 ); ?>><?php esc_html_e( '11% (cota redusă)', 'conex-conect-sync' ); ?></option>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Marjă minimă în lei, pe produs', 'conex-conect-sync' ); ?></span>
					<input type="range" id="ccs-marja" min="0" max="15" step="0.5" value="<?php echo esc_attr( $p['marja_minima_lei'] ); ?>">
					<output id="ccs-marja-val"></output>
				</label>
				<p class="description">
					<?php esc_html_e( 'Marja minimă apără produsele ieftine: la un cost de 1,36 lei, adaosul de 35% ar da 2 lei preț, adică 64 de bani câștig, mai puțin decât costă ambalarea comenzii.', 'conex-conect-sync' ); ?>
				</p>
				<label class="ccs-rotunjire">
					<span><?php esc_html_e( 'Rotunjire', 'conex-conect-sync' ); ?></span>
					<select id="ccs-rotunjire">
						<option value="sus" <?php selected( $p['rotunjire'], 'sus' ); ?>><?php esc_html_e( 'la leu, în sus (ca acum pe site)', 'conex-conect-sync' ); ?></option>
						<option value="exact" <?php selected( $p['rotunjire'], 'exact' ); ?>><?php esc_html_e( 'exact, cu bani', 'conex-conect-sync' ); ?></option>
					</select>
				</label>
			</div>

			<div class="ccs-efect" id="ccs-efect">
				<h3><?php esc_html_e( 'Ce se întâmplă cu magazinul', 'conex-conect-sync' ); ?></h3>
				<table class="ccs-tabel-efect">
					<tbody>
						<tr><th><?php esc_html_e( 'Prețuri care urcă', 'conex-conect-sync' ); ?></th><td id="ccs-e-sus"></td></tr>
						<tr><th><?php esc_html_e( 'Prețuri care coboară', 'conex-conect-sync' ); ?></th><td id="ccs-e-jos"></td></tr>
						<tr><th><?php esc_html_e( 'Prețuri neschimbate', 'conex-conect-sync' ); ?></th><td id="ccs-e-egal"></td></tr>
						<tr><th><?php esc_html_e( 'Rămân sub cost', 'conex-conect-sync' ); ?></th><td id="ccs-e-subcost"></td></tr>
						<tr class="ccs-total"><th><?php esc_html_e( 'Câștig brut, dacă s-ar vinde o bucată din fiecare', 'conex-conect-sync' ); ?></th><td id="ccs-e-marja"></td></tr>
					</tbody>
				</table>

				<h3><?php esc_html_e( 'Pe produse concrete', 'conex-conect-sync' ); ?></h3>
				<table class="ccs-exemple widefat">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Cost fără TVA', 'conex-conect-sync' ); ?></th>
							<th><?php esc_html_e( 'Preț acum', 'conex-conect-sync' ); ?></th>
							<th><?php esc_html_e( 'Preț nou', 'conex-conect-sync' ); ?></th>
							<th><?php esc_html_e( 'Câștig', 'conex-conect-sync' ); ?></th>
						</tr>
					</thead>
					<tbody id="ccs-exemple"></tbody>
				</table>
			</div>
		</div>

		<p class="ccs-actiuni">
			<button class="button button-primary button-hero" id="ccs-salveaza-politica">
				<?php esc_html_e( 'Salvează politica de preț', 'conex-conect-sync' ); ?>
			</button>
			<span class="ccs-stare" id="ccs-politica-stare"></span>
		</p>
	</section>
	<?php
}

/**
 * Secțiunea 2: potrivirile de confirmat.
 *
 * @param array $lista   Lista de verificat, din `ccs_potriveste()`.
 * @param array $decizii Deciziile deja luate.
 * @return void
 */
function ccs_admin_sectiune_potriviri( $lista, $decizii ) {
	?>
	<section class="ccs-sectiune" id="ccs-potriviri">
		<h2><?php esc_html_e( '2. Produse de confirmat', 'conex-conect-sync' ); ?></h2>
		<p class="ccs-explic">
			<?php esc_html_e( 'La produsele de mai jos, codul de pe site se potrivește cu un cod de la Conex, dar numele arată cu totul alt produs. Până ne spui care e situația, produsele astea nu se sincronizează deloc: nu li se schimbă nici prețul, nici stocul.', 'conex-conect-sync' ); ?>
		</p>
		<?php
		if ( ! $lista ) {
			echo '<p class="ccs-gol">' . esc_html__( 'Nimic de confirmat. Toate produsele se potrivesc și pe nume.', 'conex-conect-sync' ) . '</p></section>';
			return;
		}
		?>
		<?php
		ccs_admin_bara_lot(
			'potrivire',
			array(
				'exclus'  => __( 'Nu e același produs', 'conex-conect-sync' ),
				'acelasi' => __( 'E același produs', 'conex-conect-sync' ),
			)
		);
		?>
		<div class="ccs-carduri">
			<?php
			foreach ( $lista as $wc_id => $v ) {
				$luata = $decizii[ (string) $wc_id ] ?? null;
				ccs_admin_card_potrivire( (int) $wc_id, $v, $luata );
			}
			?>
		</div>
	</section>
	<?php
}

/**
 * Un card de potrivire, cu cele două produse față în față.
 *
 * @param int        $wc_id Produsul de pe site.
 * @param array      $v     Datele potrivirii.
 * @param array|null $luata Decizia deja luată, dacă există.
 * @return void
 */
function ccs_admin_card_potrivire( $wc_id, $v, $luata ) {
	$poza  = get_the_post_thumbnail_url( $wc_id, 'thumbnail' );
	$pret  = get_post_meta( $wc_id, '_regular_price', true );
	$clasa = $luata ? 'ccs-card ccs-rezolvat' : 'ccs-card';
	?>
	<div class="<?php echo esc_attr( $clasa ); ?>" data-referinta="<?php echo esc_attr( $wc_id ); ?>" data-tip="potrivire">
		<div class="ccs-fata-in-fata">
			<div class="ccs-parte">
				<h4><?php esc_html_e( 'Pe site-ul tău', 'conex-conect-sync' ); ?></h4>
				<?php if ( $poza ) : ?>
					<img src="<?php echo esc_url( $poza ); ?>" alt="">
				<?php endif; ?>
				<p class="ccs-nume"><?php echo esc_html( $v['nume_site'] ); ?></p>
				<p class="ccs-meta">
					<?php
					printf(
						/* translators: 1: codul produsului, 2: prețul. */
						esc_html__( 'cod %1$s, preț %2$s lei', 'conex-conect-sync' ),
						esc_html( $v['sku'] ),
						esc_html( ccs_lei( $pret ) )
					);
					?>
					&middot; <a href="<?php echo esc_url( get_edit_post_link( $wc_id ) ); ?>" target="_blank"><?php esc_html_e( 'vezi produsul', 'conex-conect-sync' ); ?></a>
				</p>
			</div>
			<div class="ccs-vs"><?php esc_html_e( 'contra', 'conex-conect-sync' ); ?></div>
			<div class="ccs-parte">
				<h4><?php esc_html_e( 'La Conex, pe același cod', 'conex-conect-sync' ); ?></h4>
				<p class="ccs-nume"><?php echo esc_html( $v['nume_conex'] ); ?></p>
				<p class="ccs-meta">
					<?php
					printf(
						/* translators: %d: procentul de cuvinte comune. */
						esc_html__( 'doar %d%% din cuvinte sunt la fel', 'conex-conect-sync' ),
						(int) $v['scor']
					);
					?>
				</p>
			</div>
		</div>
		<div class="ccs-butoane">
			<button class="button ccs-decizie" data-valoare="acelasi">
				<?php esc_html_e( 'E același produs, leagă-l', 'conex-conect-sync' ); ?>
			</button>
			<button class="button ccs-decizie" data-valoare="exclus">
				<?php esc_html_e( 'Nu e același produs, lasă-l în pace', 'conex-conect-sync' ); ?>
			</button>
			<span class="ccs-stare"><?php echo $luata ? esc_html( ccs_admin_text_decizie( 'potrivire', $luata ) ) : ''; ?></span>
		</div>
	</div>
	<?php
}

/**
 * Secțiunea 3: prețurile care coboară mult.
 *
 * @param array $randuri Rândurile blocate de frână.
 * @param array $decizii Deciziile luate.
 * @return void
 */
function ccs_admin_sectiune_scaderi( $randuri, $decizii ) {
	?>
	<section class="ccs-sectiune" id="ccs-scaderi">
		<h2><?php esc_html_e( '3. Prețuri care ar coborî mult', 'conex-conect-sync' ); ?></h2>
		<p class="ccs-explic">
			<?php esc_html_e( 'Produsele astea au acum un adaos foarte mare, iar regula de preț le-ar cobori prețul la mai puțin de jumătate. Nu e o greșeală de calcul: așa iese din formulă. Dar e o scădere pe care n-o facem fără să ne spui.', 'conex-conect-sync' ); ?>
		</p>
		<?php
		if ( ! $randuri ) {
			echo '<p class="ccs-gol">' . esc_html__( 'Niciun preț nu coboară atât de mult.', 'conex-conect-sync' ) . '</p></section>';
			return;
		}
		$optiuni = array(
			'lasa_sa_coboare' => __( 'Lasă prețul să coboare', 'conex-conect-sync' ),
			'pret_manual'     => __( 'Păstrează prețul de acum', 'conex-conect-sync' ),
		);
		ccs_admin_bara_lot( 'scadere', $optiuni );
		ccs_admin_tabel_decizii( $randuri, $decizii, 'scadere', $optiuni );
		?>
	</section>
	<?php
}

/**
 * Secțiunea 4: produsele vândute sub cost.
 *
 * @param array $randuri Rândurile sub cost.
 * @param array $decizii Deciziile luate.
 * @return void
 */
function ccs_admin_sectiune_sub_cost( $randuri, $decizii ) {
	$pierdere = 0.0;
	foreach ( $randuri as $r ) {
		$pierdere += $r['cost_cu_tva'] - $r['pret_site'];
	}
	?>
	<section class="ccs-sectiune" id="ccs-subcost">
		<h2><?php esc_html_e( '4. Produse vândute sub cost', 'conex-conect-sync' ); ?></h2>
		<p class="ccs-explic">
			<?php
			printf(
				/* translators: 1: câte produse, 2: pierderea totală. */
				esc_html__( 'Chiar acum, %1$d produse din magazin au prețul mai mic decât costul la care le cumperi de la Conex. Dacă s-ar vinde o bucată din fiecare, pierderea ar fi de %2$s lei. Regula de preț le corectează pe toate, dar merită să te uiți la ele: unele pot avea prețul greșit de mult timp.', 'conex-conect-sync' ),
				count( $randuri ),
				esc_html( ccs_lei( $pierdere ) )
			);
			?>
		</p>
		<?php
		if ( ! $randuri ) {
			echo '<p class="ccs-gol">' . esc_html__( 'Niciun produs nu se vinde sub cost.', 'conex-conect-sync' ) . '</p></section>';
			return;
		}
		$optiuni = array(
			'corecteaza'  => __( 'Corectează prețul', 'conex-conect-sync' ),
			'pret_manual' => __( 'Lasă-l cum e', 'conex-conect-sync' ),
		);
		ccs_admin_bara_lot( 'sub_cost', $optiuni );
		ccs_admin_tabel_decizii( $randuri, $decizii, 'sub_cost', $optiuni );
		?>
	</section>
	<?php
}

/**
 * Bara cu „la fel pentru toate”.
 *
 * Al doilea clic confirmă. Nu folosim fereastra de confirmare a browserului: întrerupe omul și arată ca o eroare.
 *
 * @param string $tip Tipul deciziei.
 * @param array  $opt valoare => etichetă.
 * @return void
 */
function ccs_admin_bara_lot( $tip, $opt ) {
	echo '<div class="ccs-lot-bara" data-tip-lot="' . esc_attr( $tip ) . '">';
	echo '<span class="ccs-lot-eticheta">' . esc_html__( 'La fel pentru toate cele rămase:', 'conex-conect-sync' ) . '</span>';
	foreach ( $opt as $valoare => $eticheta ) {
		printf(
			'<button class="button ccs-lot" data-valoare="%s" data-eticheta="%s">%s</button>',
			esc_attr( $valoare ),
			esc_attr( $eticheta ),
			esc_html( $eticheta )
		);
	}
	echo '<span class="ccs-stare"></span>';
	echo '</div>';
}

/**
 * Un tabel de produse cu butoane de decizie pe fiecare rând.
 *
 * @param array  $randuri Rândurile de plan.
 * @param array  $decizii Deciziile luate.
 * @param string $tip     Tipul deciziei.
 * @param array  $opt     valoare => etichetă.
 * @return void
 */
function ccs_admin_tabel_decizii( $randuri, $decizii, $tip, $opt ) {
	?>
	<table class="widefat ccs-tabel">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Produs', 'conex-conect-sync' ); ?></th>
				<th class="num"><?php esc_html_e( 'Cost cu TVA', 'conex-conect-sync' ); ?></th>
				<th class="num"><?php esc_html_e( 'Preț acum', 'conex-conect-sync' ); ?></th>
				<th class="num"><?php esc_html_e( 'Preț nou', 'conex-conect-sync' ); ?></th>
				<th class="num"><?php esc_html_e( 'Câștig nou', 'conex-conect-sync' ); ?></th>
				<th><?php esc_html_e( 'Ce facem', 'conex-conect-sync' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $randuri as $r ) : ?>
			<?php $luata = $decizii[ (string) $r['wc_id'] ] ?? null; ?>
			<tr data-tip="<?php echo esc_attr( $tip ); ?>" data-referinta="<?php echo esc_attr( $r['wc_id'] ); ?>" class="<?php echo $luata ? 'ccs-rezolvat' : ''; ?>">
				<td>
					<strong><?php echo esc_html( $r['nume'] ); ?></strong><br>
					<span class="ccs-meta">
						<?php echo esc_html( $r['sku'] ); ?>
						<?php if ( $r['brand'] ) : ?>
							&middot; <?php echo esc_html( $r['brand'] ); ?>
						<?php endif; ?>
						&middot; <a href="<?php echo esc_url( get_edit_post_link( $r['wc_id'] ) ); ?>" target="_blank"><?php esc_html_e( 'vezi', 'conex-conect-sync' ); ?></a>
					</span>
				</td>
				<td class="num"><?php echo esc_html( ccs_lei( $r['cost_cu_tva'] ) ); ?></td>
				<td class="num"><?php echo esc_html( ccs_lei( $r['pret_site'] ) ); ?></td>
				<td class="num ccs-nou"><?php echo esc_html( ccs_lei( $r['pret_tinta'] ) ); ?></td>
				<td class="num <?php echo $r['pret_tinta'] - $r['cost_cu_tva'] < 0 ? 'ccs-rosu' : ''; ?>">
					<?php echo esc_html( ccs_lei( $r['pret_tinta'] - $r['cost_cu_tva'] ) ); ?>
				</td>
				<td class="ccs-butoane">
					<?php foreach ( $opt as $valoare => $eticheta ) : ?>
						<button class="button ccs-decizie" data-valoare="<?php echo esc_attr( $valoare ); ?>">
							<?php echo esc_html( $eticheta ); ?>
						</button>
					<?php endforeach; ?>
					<span class="ccs-stare"><?php echo $luata ? esc_html( ccs_admin_text_decizie( $tip, $luata ) ) : ''; ?></span>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

/**
 * Secțiunea 5: stocul.
 *
 * @param array $rezumat Rezumatul planului.
 * @param array $decizii Deciziile luate.
 * @return void
 */
function ccs_admin_sectiune_stoc( $rezumat, $decizii ) {
	$luata = $decizii[''] ?? null;
	?>
	<section class="ccs-sectiune" id="ccs-stoc">
		<h2><?php esc_html_e( '5. Stocul', 'conex-conect-sync' ); ?></h2>
		<p class="ccs-explic">
			<?php
			printf(
				/* translators: 1: stocuri de schimbat, 2: de epuizat, 3: de reactivat. */
				esc_html__( 'Stocul se ia de la Conex așa cum e: %1$d produse ar primi altă cantitate. Dintre ele, %2$d ar trece pe „stoc epuizat”, pentru că furnizorul nu le mai are, și %3$d s-ar reactiva, pentru că au revenit în stoc și acum sunt ascunse degeaba.', 'conex-conect-sync' ),
				(int) $rezumat['stoc'],
				(int) $rezumat['de_epuizat'],
				(int) $rezumat['de_reactivat']
			);
			?>
		</p>
		<p class="ccs-explic">
			<?php esc_html_e( 'Produsele care nu mai sunt în catalogul Conex nu se șterg niciodată. Trec pe stoc epuizat după două rulări în care lipsesc, și rămân pe site.', 'conex-conect-sync' ); ?>
		</p>
		<div data-tip="epuizare" data-referinta="" class="ccs-butoane <?php echo $luata ? 'ccs-rezolvat' : ''; ?>">
			<button class="button button-primary ccs-decizie" data-valoare="aprobat">
				<?php esc_html_e( 'De acord, sincronizează stocul așa', 'conex-conect-sync' ); ?>
			</button>
			<span class="ccs-stare"><?php echo $luata ? esc_html( ccs_admin_text_decizie( 'epuizare', $luata ) ) : ''; ?></span>
		</div>
	</section>
	<?php
}

/**
 * Textul care arată o decizie deja luată.
 *
 * @param string $tip   Tipul.
 * @param array  $luata Decizia.
 * @return string Textul.
 */
function ccs_admin_text_decizie( $tip, $luata ) {
	$etichete = array(
		'acelasi'         => __( 'confirmat ca același produs', 'conex-conect-sync' ),
		'exclus'          => __( 'marcat ca alt produs', 'conex-conect-sync' ),
		'lasa_sa_coboare' => __( 'prețul are voie să coboare', 'conex-conect-sync' ),
		'pret_manual'     => __( 'preț păstrat manual', 'conex-conect-sync' ),
		'corecteaza'      => __( 'prețul se corectează', 'conex-conect-sync' ),
		'aprobat'         => __( 'aprobat', 'conex-conect-sync' ),
	);
	$cine = get_userdata( $luata['utilizator'] );
	return sprintf(
		/* translators: 1: ce s-a decis, 2: cine, 3: când. */
		__( '%1$s, de %2$s pe %3$s', 'conex-conect-sync' ),
		$etichete[ $luata['valoare'] ] ?? $luata['valoare'],
		$cine ? $cine->display_name : __( 'cineva', 'conex-conect-sync' ),
		mysql2date( 'j F Y, H:i', $luata['creat_la'] )
	);
}
