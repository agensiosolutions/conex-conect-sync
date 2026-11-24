<?php
/**
 * Interfața panoului: componentele (card, cifră, pastilă, stare) și cele șase taburi.
 * Logica (date, cache, AJAX) e în `admin.php`; stilul în `admin/css/ccs-admin.css`.
 *
 * Regula de design, ca la agensio-consent: fundal deschis, gri neutre, culoare doar pe butoane și pe indicatorii de
 * stare. Fiecare tab are un titlu scurt și cel mult o frază de explicație.
 *
 * Regula de conținut, specifică aici: fiecare tab pune o întrebare, nu arată o listă. Omul care deschide pagina nu
 * configurează un plugin, ci hotărăște ce se întâmplă cu prețurile magazinului lui.
 *
 * @package conex-conect-sync
 */

defined( 'ABSPATH' ) || exit;

/* ------------------------------------------------------------------------------------------------ componente */

/**
 * Pastilă de stare: bine, atentie, problema, info, neutru.
 *
 * @param string $text Textul.
 * @param string $ton  Tonul.
 * @return string HTML.
 */
function ccs_ui_pastila( $text, $ton = 'neutru' ) {
	return '<span class="ccs-pastila ccs-pastila--' . esc_attr( $ton ) . '">' . esc_html( $text ) . '</span>';
}

/**
 * Card: titlu, o frază, corp.
 *
 * @param string $titlu Titlul.
 * @param string $desc  Fraza de sub titlu.
 * @param string $extra HTML în dreapta titlului.
 * @return void
 */
function ccs_ui_card( $titlu, $desc = '', $extra = '' ) {
	echo '<section class="ccs-card"><header class="ccs-card__cap"><div><h2>' . esc_html( $titlu ) . '</h2>';
	if ( $desc ) {
		echo '<p>' . esc_html( $desc ) . '</p>';
	}
	echo '</div>' . $extra . '</header><div class="ccs-card__corp">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $extra e construit de noi, escapat.
}

/**
 * Sfârșitul cardului.
 *
 * @return void
 */
function ccs_ui_card_sfarsit() {
	echo '</div></section>';
}

/**
 * Antetul unui tab.
 *
 * @param string $titlu Titlul.
 * @param string $desc  Fraza.
 * @return void
 */
function ccs_ui_antet_tab( $titlu, $desc ) {
	echo '<div class="ccs-tab-antet"><h2>' . esc_html( $titlu ) . '</h2><p>' . esc_html( $desc ) . '</p></div>';
}

/**
 * O cifră mare, cu eticheta ei.
 *
 * @param string $eticheta Eticheta.
 * @param string $valoare  Valoarea, deja formatată.
 * @param string $ton      '', 'atentie' sau 'bine'.
 * @return void
 */
function ccs_ui_cifra( $eticheta, $valoare, $ton = '' ) {
	printf(
		'<div class="ccs-cifra%1$s"><span>%2$s</span><strong>%3$s</strong></div>',
		$ton ? ' ccs-cifra--' . esc_attr( $ton ) : '',
		esc_html( $eticheta ),
		esc_html( $valoare )
	);
}

/**
 * Starea goală: nu mai e nimic de hotărât aici.
 *
 * @param string $titlu Prima linie.
 * @param string $desc  A doua.
 * @return void
 */
function ccs_ui_gol( $titlu, $desc = '' ) {
	echo '<div class="ccs-gol"><strong>' . esc_html( $titlu ) . '</strong>' . esc_html( $desc ) . '</div>';
}

/**
 * Lei, cu virgulă și separator de mii, cum se citesc în România.
 *
 * @param float $v        Valoarea.
 * @param int   $zecimale Câte zecimale.
 * @return string Textul.
 */
function ccs_lei( $v, $zecimale = 2 ) {
	return number_format( (float) $v, $zecimale, ',', '.' );
}

/**
 * Un număr întreg, cu separator de mii.
 *
 * @param int $v Valoarea.
 * @return string Textul.
 */
function ccs_nr( $v ) {
	return number_format( (int) $v, 0, ',', '.' );
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
function ccs_ui_bara_lot( $tip, $opt ) {
	echo '<div class="ccs-lot-bara" data-tip-lot="' . esc_attr( $tip ) . '">';
	echo '<span>' . esc_html__( 'La fel pentru toate cele rămase:', 'conex-conect-sync' ) . '</span>';
	foreach ( $opt as $valoare => $eticheta ) {
		printf(
			'<button type="button" class="ccs-btn ccs-btn--mic ccs-lot" data-valoare="%s" data-eticheta="%s">%s</button>',
			esc_attr( $valoare ),
			esc_attr( $eticheta ),
			esc_html( $eticheta )
		);
	}
	echo '<span class="ccs-stare-text"></span>';
	echo '</div>';
}

/**
 * Textul care arată o decizie deja luată.
 *
 * @param array $luata Decizia.
 * @return string Textul.
 */
function ccs_ui_text_decizie( $luata ) {
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

/* ------------------------------------------------------------------------------------------------ pagina */

/**
 * Pagina: antet, navigația din stânga, tabul curent.
 *
 * @return void
 */
function ccs_admin_pagina() {
	if ( ! current_user_can( CCS_CAPACITATE ) ) {
		wp_die( esc_html__( 'Nu ai acces la pagina asta.', 'conex-conect-sync' ) );
	}

	if ( isset( $_GET['recalculeaza'] ) && check_admin_referer( 'ccs-recalculeaza' ) ) {
		ccs_admin_uita();
	}

	$tab    = ccs_admin_tab();
	$setari = ccs_setari();
	$plan   = ccs_admin_plan();
	$rest   = is_wp_error( $plan ) ? array() : ccs_admin_rest();

	if ( ! is_wp_error( $plan ) ) {
		wp_localize_script(
			'ccs-admin',
			'ccsDate',
			array(
				'ajaxurl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'ccs-decizii' ),
				'simulator' => 'pret' === $tab ? ccs_admin_date_simulator( $plan ) : array(),
				'politica'  => ccs_admin_politica(),
				'texte'     => array(
					'salvat'   => __( 'Salvat.', 'conex-conect-sync' ),
					'eroare'   => __( 'Nu s-a putut salva. Încearcă din nou.', 'conex-conect-sync' ),
					'lucrez'   => __( 'Salvez...', 'conex-conect-sync' ),
					'confirma' => __( 'Apasă din nou ca să confirmi', 'conex-conect-sync' ),
					'nimic'    => __( 'Nu a mai rămas nimic de hotărât aici.', 'conex-conect-sync' ),
				),
			)
		);
	}
	?>
	<div class="ccs-admin">
		<header class="ccs-antet">
			<div class="ccs-antet__titlu">
				<span class="ccs-antet__icon dashicons dashicons-update" aria-hidden="true"></span>
				<div>
					<h1><?php esc_html_e( 'Sincronizare Conex', 'conex-conect-sync' ); ?></h1>
					<p><?php esc_html_e( 'Prețul, stocul și produsele, luate automat de la furnizor', 'conex-conect-sync' ); ?></p>
				</div>
			</div>
			<div class="ccs-antet__dreapta">
				<?php
				echo $setari['mod_raport'] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					? ccs_ui_pastila( __( 'Nu se scrie nimic pe site', 'conex-conect-sync' ), 'info' )
					: ccs_ui_pastila( __( 'Sincronizare pornită', 'conex-conect-sync' ), 'bine' );
				?>
				<a class="ccs-btn ccs-btn--fantoma" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=ccs-decizii&tab=' . $tab . '&recalculeaza=1' ), 'ccs-recalculeaza' ) ); ?>">
					<span class="dashicons dashicons-image-rotate" aria-hidden="true"></span><?php esc_html_e( 'Recalculează', 'conex-conect-sync' ); ?>
				</a>
			</div>
		</header>
		<hr class="wp-header-end">

		<?php if ( is_wp_error( $plan ) ) : ?>
			<div class="ccs-mesaj ccs-mesaj--atentie" role="status"><?php echo esc_html( $plan->get_error_message() ); ?></div>
		</div>
			<?php
			return;
		endif;
		?>

		<div class="ccs-cadru">
			<nav class="ccs-nav" aria-label="<?php esc_attr_e( 'Secțiunile panoului', 'conex-conect-sync' ); ?>">
				<?php foreach ( ccs_admin_taburi() as $slug => $t ) : ?>
					<a href="<?php echo esc_url( ccs_admin_url( $slug ) ); ?>" class="ccs-nav__link"<?php echo $slug === $tab ? ' aria-current="page"' : ''; ?>>
						<span class="dashicons dashicons-<?php echo esc_attr( $t[1] ); ?>" aria-hidden="true"></span>
						<span><strong><?php echo esc_html( $t[0] ); ?></strong><small><?php echo esc_html( $t[2] ); ?></small></span>
						<?php if ( $t[3] && ! empty( $rest[ $t[3] ] ) ) : ?>
							<span class="ccs-nav__rest" title="<?php esc_attr_e( 'întrebări fără răspuns', 'conex-conect-sync' ); ?>"><?php echo esc_html( $rest[ $t[3] ] ); ?></span>
						<?php endif; ?>
					</a>
				<?php endforeach; ?>
			</nav>
			<main class="ccs-principal">
				<?php call_user_func( 'ccs_admin_tab_' . str_replace( '-', '_', $tab ) ); ?>
			</main>
		</div>
	</div>
	<?php
}

/* ------------------------------------------------------------------------------------------------ taburile */

/**
 * Panou: cifrele, starea, ce e de rezolvat.
 *
 * @return void
 */
function ccs_admin_tab_panou() {
	global $wpdb;
	$plan      = ccs_admin_plan();
	$r         = $plan['rezumat'];
	$potrivire = ccs_admin_potrivire();
	$rest      = ccs_admin_rest();
	$setari    = ccs_setari();
	$total     = array_sum( $rest );

	ccs_ui_antet_tab(
		__( 'Panou', 'conex-conect-sync' ),
		__( 'Ce ar face sincronizarea chiar acum, dacă ar fi pornită.', 'conex-conect-sync' )
	);
	?>
	<div class="ccs-cifre">
		<?php
		ccs_ui_cifra( __( 'Produse sincronizate', 'conex-conect-sync' ), ccs_nr( $r['produse'] ) );
		ccs_ui_cifra( __( 'Prețuri de schimbat', 'conex-conect-sync' ), ccs_nr( $r['pret'] ) );
		ccs_ui_cifra( __( 'Stocuri de schimbat', 'conex-conect-sync' ), ccs_nr( $r['stoc'] ) );
		ccs_ui_cifra(
			__( 'Întrebări fără răspuns', 'conex-conect-sync' ),
			ccs_nr( $total ),
			$total ? 'atentie' : 'bine'
		);
		?>
	</div>

	<?php
	if ( $total ) {
		ccs_ui_card(
			__( 'De hotărât', 'conex-conect-sync' ),
			__( 'Până primesc răspuns, produsele astea nu se ating.', 'conex-conect-sync' )
		);
		echo '<ul class="ccs-de-rezolvat">';
		$intrebari = array(
			'potriviri' => array(
				'potrivire',
				__( 'Produse de confirmat', 'conex-conect-sync' ),
				__( 'Codul se potrivește cu un produs de la Conex, dar numele arată altceva.', 'conex-conect-sync' ),
			),
			'scaderi'   => array(
				'scadere',
				__( 'Prețuri care ar coborî mult', 'conex-conect-sync' ),
				__( 'Regula de preț le-ar tăia la mai puțin de jumătate din cât sunt acum.', 'conex-conect-sync' ),
			),
			'subcost'   => array(
				'sub_cost',
				__( 'Produse vândute sub cost', 'conex-conect-sync' ),
				__( 'Prețul de pe site e mai mic decât costul la care le cumperi.', 'conex-conect-sync' ),
			),
		);
		foreach ( $intrebari as $slug => $i ) {
			if ( empty( $rest[ $i[0] ] ) ) {
				continue;
			}
			printf(
				'<li><span class="dashicons dashicons-warning" aria-hidden="true"></span><div><strong>%1$s</strong><p>%2$s</p></div><a class="ccs-btn ccs-btn--mic" href="%3$s">%4$s</a></li>',
				esc_html( $i[1] ),
				esc_html( $i[2] ),
				esc_url( ccs_admin_url( $slug ) ),
				esc_html(
					sprintf(
						/* translators: %d: câte produse. */
						_n( 'Vezi %d produs', 'Vezi %d produse', $rest[ $i[0] ], 'conex-conect-sync' ),
						$rest[ $i[0] ]
					)
				)
			);
		}
		echo '</ul>';
		ccs_ui_card_sfarsit();
	}

	$ultima  = $wpdb->get_row( "SELECT inceput, stare FROM " . ccs_tabel( 'runs' ) . " WHERE tip = 'catalog' ORDER BY id DESC LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL
	$produse = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . ccs_tabel( 'products' ) ); // phpcs:ignore WordPress.DB.PreparedSQL

	$stari = array(
		array(
			$produse ? 'bine' : 'problema',
			__( 'Catalogul furnizorului', 'conex-conect-sync' ),
			$produse
				? sprintf(
					/* translators: 1: câte produse, 2: când. */
					__( '%1$s produse, preluate %2$s', 'conex-conect-sync' ),
					ccs_nr( $produse ),
					$ultima ? mysql2date( 'j F, H:i', $ultima->inceput ) : __( 'nu se știe când', 'conex-conect-sync' )
				)
				: __( 'Nu a fost preluat încă', 'conex-conect-sync' ),
		),
		array(
			$potrivire['legate'] ? 'bine' : 'atentie',
			__( 'Produsele legate', 'conex-conect-sync' ),
			sprintf(
				/* translators: 1: legate, 2: total pe site, 3: de verificat. */
				__( '%1$s din %2$s produse de pe site, %3$s de confirmat', 'conex-conect-sync' ),
				ccs_nr( $potrivire['legate'] - $potrivire['de_verificat'] ),
				ccs_nr( $potrivire['produse_site'] ),
				ccs_nr( $potrivire['de_verificat'] )
			),
		),
		array(
			'neutru',
			__( 'Produse ieșite din catalog', 'conex-conect-sync' ),
			sprintf(
				/* translators: %s: câte produse. */
				__( '%s produse de pe site nu mai sunt la Conex. Nu se șterg niciodată.', 'conex-conect-sync' ),
				ccs_nr( $potrivire['orfane'] )
			),
		),
		array(
			$setari['mod_raport'] ? 'atentie' : 'bine',
			__( 'Scrierea pe site', 'conex-conect-sync' ),
			$setari['mod_raport']
				? __( 'Oprită. Pagina arată ce s-ar schimba, dar nu schimbă nimic.', 'conex-conect-sync' )
				: __( 'Pornită. Prețul și stocul se aplică pe produse.', 'conex-conect-sync' ),
		),
	);

	$iconuri = array(
		'bine'     => 'yes',
		'atentie'  => 'warning',
		'problema' => 'dismiss',
		'neutru'   => 'minus',
	);

	ccs_ui_card( __( 'Starea', 'conex-conect-sync' ) );
	echo '<ul class="ccs-stari">';
	foreach ( $stari as $st ) {
		printf(
			'<li class="ccs-stare ccs-stare--%1$s"><span class="ccs-stare__icon dashicons dashicons-%2$s" aria-hidden="true"></span><strong>%3$s</strong><span>%4$s</span></li>',
			esc_attr( $st[0] ),
			esc_attr( $iconuri[ $st[0] ] ),
			esc_html( $st[1] ),
			esc_html( $st[2] )
		);
	}
	echo '</ul>';
	ccs_ui_card_sfarsit();
}

/**
 * Politica de preț, cu simulator.
 *
 * @return void
 */
function ccs_admin_tab_pret() {
	$p = ccs_admin_politica();
	ccs_ui_antet_tab(
		__( 'Politica de preț', 'conex-conect-sync' ),
		__( 'Trage de valori și vezi imediat ce se întâmplă cu tot magazinul. Nimic nu se schimbă pe site până nu apeși „Salvează”.', 'conex-conect-sync' )
	);

	ccs_ui_card( __( 'Cum se calculează prețul', 'conex-conect-sync' ) );
	?>
	<p class="ccs-formula">
		<?php
		printf(
			/* translators: textul formulei. */
			esc_html__( 'Prețul de vânzare e %1$s dintre %2$s și %3$s.', 'conex-conect-sync' ),
			'<b>' . esc_html__( 'cel mai mare', 'conex-conect-sync' ) . '</b>',
			'<b>' . esc_html__( 'cost × TVA × adaos', 'conex-conect-sync' ) . '</b>',
			'<b>' . esc_html__( 'cost × TVA + marja minimă', 'conex-conect-sync' ) . '</b>'
		);
		?>
	</p>

	<div class="ccs-doua" style="margin-top:20px">
		<div>
			<label class="ccs-camp" for="ccs-adaos">
				<span><?php esc_html_e( 'Adaos comercial', 'conex-conect-sync' ); ?></span>
				<small><?php esc_html_e( 'Cât se adaugă peste costul cu TVA inclus.', 'conex-conect-sync' ); ?></small>
				<span class="ccs-glisor">
					<input type="range" id="ccs-adaos" min="1" max="2.5" step="0.01" value="<?php echo esc_attr( $p['adaos'] ); ?>">
					<output id="ccs-adaos-val"></output>
				</span>
			</label>

			<label class="ccs-camp" for="ccs-marja">
				<span><?php esc_html_e( 'Marjă minimă în lei, pe produs', 'conex-conect-sync' ); ?></span>
				<small><?php esc_html_e( 'Apără produsele ieftine: la un cost de 1,36 lei, adaosul de 35% ar da 2 lei preț, adică 64 de bani câștig, mai puțin decât costă ambalarea comenzii.', 'conex-conect-sync' ); ?></small>
				<span class="ccs-glisor">
					<input type="range" id="ccs-marja" min="0" max="15" step="0.5" value="<?php echo esc_attr( $p['marja_minima_lei'] ); ?>">
					<output id="ccs-marja-val"></output>
				</span>
			</label>

			<label class="ccs-camp" for="ccs-cota">
				<span><?php esc_html_e( 'Cota de TVA inclusă în preț', 'conex-conect-sync' ); ?></span>
				<small><?php esc_html_e( 'Prețurile de pe site sunt finale, cu TVA-ul înăuntru. Furnizorul trimite costul fără TVA.', 'conex-conect-sync' ); ?></small>
				<select class="ccs-intrare" id="ccs-cota">
					<option value="1.21" <?php selected( $p['cota_tva'], 1.21 ); ?>><?php esc_html_e( '21%, cota în vigoare din august 2025', 'conex-conect-sync' ); ?></option>
					<option value="1.19" <?php selected( $p['cota_tva'], 1.19 ); ?>><?php esc_html_e( '19%, cota veche, cea folosită la prețurile de acum', 'conex-conect-sync' ); ?></option>
					<option value="1.11" <?php selected( $p['cota_tva'], 1.11 ); ?>><?php esc_html_e( '11%, cota redusă', 'conex-conect-sync' ); ?></option>
				</select>
			</label>

			<label class="ccs-camp" for="ccs-rotunjire">
				<span><?php esc_html_e( 'Rotunjire', 'conex-conect-sync' ); ?></span>
				<select class="ccs-intrare" id="ccs-rotunjire">
					<option value="sus" <?php selected( $p['rotunjire'], 'sus' ); ?>><?php esc_html_e( 'la leu, în sus, ca acum pe site', 'conex-conect-sync' ); ?></option>
					<option value="exact" <?php selected( $p['rotunjire'], 'exact' ); ?>><?php esc_html_e( 'exact, cu bani', 'conex-conect-sync' ); ?></option>
				</select>
			</label>

			<div class="ccs-butoane" style="margin-top:4px">
				<button type="button" class="ccs-btn ccs-btn--principal" id="ccs-salveaza-politica">
					<?php esc_html_e( 'Salvează politica de preț', 'conex-conect-sync' ); ?>
				</button>
				<span class="ccs-stare-text" id="ccs-politica-stare"></span>
			</div>
		</div>

		<div>
			<h3 style="margin:0 0 8px;font-size:14px"><?php esc_html_e( 'Ce se întâmplă cu magazinul', 'conex-conect-sync' ); ?></h3>
			<table class="ccs-efect">
				<tbody>
					<tr><th><?php esc_html_e( 'Prețuri care urcă', 'conex-conect-sync' ); ?></th><td id="ccs-e-sus"></td></tr>
					<tr><th><?php esc_html_e( 'Prețuri care coboară', 'conex-conect-sync' ); ?></th><td id="ccs-e-jos"></td></tr>
					<tr><th><?php esc_html_e( 'Prețuri neschimbate', 'conex-conect-sync' ); ?></th><td id="ccs-e-egal"></td></tr>
					<tr><th><?php esc_html_e( 'Rămân sub cost', 'conex-conect-sync' ); ?></th><td id="ccs-e-subcost"></td></tr>
					<tr class="ccs-total"><th><?php esc_html_e( 'Câștig brut, la o bucată din fiecare', 'conex-conect-sync' ); ?></th><td id="ccs-e-marja"></td></tr>
				</tbody>
			</table>

			<h3 style="margin:24px 0 8px;font-size:14px"><?php esc_html_e( 'Pe produse concrete', 'conex-conect-sync' ); ?></h3>
			<div class="ccs-tabel-cadru">
				<table class="ccs-tabel">
					<thead>
						<tr>
							<th class="num"><?php esc_html_e( 'Cost fără TVA', 'conex-conect-sync' ); ?></th>
							<th class="num"><?php esc_html_e( 'Preț acum', 'conex-conect-sync' ); ?></th>
							<th class="num"><?php esc_html_e( 'Preț nou', 'conex-conect-sync' ); ?></th>
							<th class="num"><?php esc_html_e( 'Câștig', 'conex-conect-sync' ); ?></th>
						</tr>
					</thead>
					<tbody id="ccs-exemple"></tbody>
				</table>
			</div>
			<p class="ccs-mic"><?php esc_html_e( 'Șase produse din magazin, de la cel mai ieftin la cel mai scump.', 'conex-conect-sync' ); ?></p>
		</div>
	</div>
	<?php
	ccs_ui_card_sfarsit();
}

/**
 * Produsele de confirmat.
 *
 * @return void
 */
function ccs_admin_tab_potriviri() {
	$potrivire = ccs_admin_potrivire();
	$lista     = $potrivire['lista_verificat'];
	$decizii   = ccs_decizii_curente( 'potrivire' );

	ccs_ui_antet_tab(
		__( 'Produse de confirmat', 'conex-conect-sync' ),
		__( 'La produsele de mai jos, codul de pe site se potrivește cu un cod de la Conex, dar numele arată cu totul alt produs. Până ne spui care e situația, nu li se schimbă nici prețul, nici stocul.', 'conex-conect-sync' )
	);

	if ( ! $lista ) {
		ccs_ui_card( __( 'Nimic de confirmat', 'conex-conect-sync' ) );
		ccs_ui_gol( __( 'Toate produsele se potrivesc și pe nume.', 'conex-conect-sync' ) );
		ccs_ui_card_sfarsit();
		return;
	}

	ccs_ui_card(
		sprintf(
			/* translators: %d: câte produse. */
			_n( '%d produs de lămurit', '%d produse de lămurit', count( $lista ), 'conex-conect-sync' ),
			count( $lista )
		),
		__( 'Ori codul de pe site e greșit, ori produsul e de la alt furnizor și nu trebuie legat niciodată.', 'conex-conect-sync' )
	);

	ccs_ui_bara_lot(
		'potrivire',
		array(
			'exclus'  => __( 'Nu e același produs', 'conex-conect-sync' ),
			'acelasi' => __( 'E același produs', 'conex-conect-sync' ),
		)
	);

	foreach ( $lista as $wc_id => $v ) {
		$wc_id = (int) $wc_id;
		$luata = $decizii[ (string) $wc_id ] ?? null;
		$poza  = get_the_post_thumbnail_url( $wc_id, 'thumbnail' );
		$pret  = get_post_meta( $wc_id, '_regular_price', true );
		?>
		<div class="ccs-potrivire <?php echo $luata ? 'ccs-rezolvat' : ''; ?>" data-tip="potrivire" data-referinta="<?php echo esc_attr( $wc_id ); ?>">
			<div class="ccs-fata">
				<div class="ccs-parte">
					<p class="ccs-parte__cap"><span class="dashicons dashicons-admin-home" aria-hidden="true"></span><?php esc_html_e( 'Pe site-ul tău', 'conex-conect-sync' ); ?></p>
					<div class="ccs-parte__corp">
						<?php if ( $poza ) : ?>
							<?php // Dacă fișierul lipsește, ascundem imaginea: un pătrat gol spart arată ca o eroare a paginii. ?>
							<img src="<?php echo esc_url( $poza ); ?>" alt="" loading="lazy" onerror="this.style.display='none'">
						<?php endif; ?>
						<div>
							<p class="ccs-parte__nume"><?php echo esc_html( $v['nume_site'] ); ?></p>
							<p class="ccs-parte__meta">
								<?php
								printf(
									/* translators: 1: codul, 2: prețul. */
									esc_html__( 'cod %1$s, preț %2$s lei', 'conex-conect-sync' ),
									esc_html( $v['sku'] ),
									esc_html( ccs_lei( $pret ) )
								);
								?>
								&middot; <a href="<?php echo esc_url( get_edit_post_link( $wc_id ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'vezi produsul', 'conex-conect-sync' ); ?></a>
							</p>
						</div>
					</div>
				</div>
				<span class="ccs-contra"><?php esc_html_e( 'contra', 'conex-conect-sync' ); ?></span>
				<div class="ccs-parte">
					<p class="ccs-parte__cap"><span class="dashicons dashicons-cart" aria-hidden="true"></span><?php esc_html_e( 'La Conex, pe același cod', 'conex-conect-sync' ); ?></p>
					<div class="ccs-parte__corp">
						<div>
							<p class="ccs-parte__nume"><?php echo esc_html( $v['nume_conex'] ); ?></p>
							<p class="ccs-parte__meta">
								<?php
								printf(
									/* translators: %d: procentul. */
									esc_html__( 'doar %d%% din cuvinte sunt la fel', 'conex-conect-sync' ),
									(int) $v['scor']
								);
								?>
							</p>
						</div>
					</div>
				</div>
			</div>
			<div class="ccs-butoane">
				<button type="button" class="ccs-btn ccs-decizie" data-valoare="acelasi"><?php esc_html_e( 'E același produs, leagă-l', 'conex-conect-sync' ); ?></button>
				<button type="button" class="ccs-btn ccs-decizie" data-valoare="exclus"><?php esc_html_e( 'Nu e același produs, lasă-l în pace', 'conex-conect-sync' ); ?></button>
				<span class="ccs-stare-text"><?php echo $luata ? esc_html( ccs_ui_text_decizie( $luata ) ) : ''; ?></span>
			</div>
		</div>
		<?php
	}

	ccs_ui_card_sfarsit();
}

/**
 * Prețurile care ar coborî mult.
 *
 * @return void
 */
function ccs_admin_tab_scaderi() {
	$randuri = ccs_admin_randuri( 'scaderi' );
	$decizii = ccs_decizii_curente( 'scadere' );

	ccs_ui_antet_tab(
		__( 'Prețuri care ar coborî mult', 'conex-conect-sync' ),
		__( 'Produsele astea au acum un adaos foarte mare, iar regula de preț le-ar cobori prețul la mai puțin de jumătate. Nu e o greșeală de calcul: așa iese din formulă. Dar e o scădere pe care n-o facem fără să ne spui.', 'conex-conect-sync' )
	);

	if ( ! $randuri ) {
		ccs_ui_card( __( 'Niciun preț nu coboară atât de mult', 'conex-conect-sync' ) );
		ccs_ui_gol( __( 'Nimic de hotărât aici.', 'conex-conect-sync' ) );
		ccs_ui_card_sfarsit();
		return;
	}

	$optiuni = array(
		'lasa_sa_coboare' => __( 'Lasă prețul să coboare', 'conex-conect-sync' ),
		'pret_manual'     => __( 'Păstrează prețul de acum', 'conex-conect-sync' ),
	);

	ccs_ui_card(
		sprintf(
			/* translators: %d: câte produse. */
			_n( '%d produs', '%d produse', count( $randuri ), 'conex-conect-sync' ),
			count( $randuri )
		)
	);
	ccs_ui_bara_lot( 'scadere', $optiuni );
	ccs_ui_tabel_decizii( $randuri, $decizii, 'scadere', $optiuni );
	ccs_ui_card_sfarsit();
}

/**
 * Produsele vândute sub cost.
 *
 * @return void
 */
function ccs_admin_tab_subcost() {
	$randuri  = ccs_admin_randuri( 'subcost' );
	$decizii  = ccs_decizii_curente( 'sub_cost' );
	$pierdere = 0.0;
	foreach ( $randuri as $r ) {
		$pierdere += $r['cost_cu_tva'] - $r['pret_site'];
	}

	ccs_ui_antet_tab(
		__( 'Produse vândute sub cost', 'conex-conect-sync' ),
		$randuri
			? sprintf(
				/* translators: 1: câte produse, 2: pierderea. */
				__( '%1$d produse au prețul mai mic decât costul la care le cumperi de la Conex. La o bucată din fiecare, pierderea ar fi de %2$s lei. Regula de preț le corectează pe toate, dar merită să te uiți: unele pot avea prețul greșit de mult timp.', 'conex-conect-sync' ),
				count( $randuri ),
				ccs_lei( $pierdere )
			)
			: __( 'Niciun produs nu se vinde sub costul de achiziție.', 'conex-conect-sync' )
	);

	if ( ! $randuri ) {
		ccs_ui_card( __( 'Totul e în regulă', 'conex-conect-sync' ) );
		ccs_ui_gol( __( 'Niciun produs nu se vinde în pierdere.', 'conex-conect-sync' ) );
		ccs_ui_card_sfarsit();
		return;
	}

	$optiuni = array(
		'corecteaza'  => __( 'Corectează prețul', 'conex-conect-sync' ),
		'pret_manual' => __( 'Lasă-l cum e', 'conex-conect-sync' ),
	);

	ccs_ui_card(
		sprintf(
			/* translators: %d: câte produse. */
			_n( '%d produs', '%d produse', count( $randuri ), 'conex-conect-sync' ),
			count( $randuri )
		),
		__( 'Cele cu pierderea cea mai mare, primele.', 'conex-conect-sync' )
	);
	ccs_ui_bara_lot( 'sub_cost', $optiuni );
	ccs_ui_tabel_decizii( $randuri, $decizii, 'sub_cost', $optiuni );
	ccs_ui_card_sfarsit();
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
function ccs_ui_tabel_decizii( $randuri, $decizii, $tip, $opt ) {
	?>
	<div class="ccs-tabel-cadru">
		<table class="ccs-tabel">
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
					<td class="ccs-produs">
						<strong><?php echo esc_html( $r['nume'] ); ?></strong>
						<span>
							<?php echo esc_html( $r['sku'] ); ?>
							<?php if ( $r['brand'] ) : ?>
								&middot; <?php echo esc_html( $r['brand'] ); ?>
							<?php endif; ?>
							&middot; <a href="<?php echo esc_url( get_edit_post_link( $r['wc_id'] ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'vezi', 'conex-conect-sync' ); ?></a>
						</span>
					</td>
					<td class="num"><?php echo esc_html( ccs_lei( $r['cost_cu_tva'] ) ); ?></td>
					<td class="num"><?php echo esc_html( ccs_lei( $r['pret_site'] ) ); ?></td>
					<td class="num ccs-nou"><?php echo esc_html( ccs_lei( $r['pret_tinta'] ) ); ?></td>
					<td class="num <?php echo $r['pret_tinta'] - $r['cost_cu_tva'] < 0 ? 'ccs-rosu' : ''; ?>">
						<?php echo esc_html( ccs_lei( $r['pret_tinta'] - $r['cost_cu_tva'] ) ); ?>
					</td>
					<td>
						<div class="ccs-butoane">
							<?php foreach ( $opt as $valoare => $eticheta ) : ?>
								<button type="button" class="ccs-btn ccs-btn--mic ccs-decizie" data-valoare="<?php echo esc_attr( $valoare ); ?>">
									<?php echo esc_html( $eticheta ); ?>
								</button>
							<?php endforeach; ?>
						</div>
						<span class="ccs-stare-text"><?php echo $luata ? esc_html( ccs_ui_text_decizie( $luata ) ) : ''; ?></span>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/**
 * Stocul.
 *
 * @return void
 */
function ccs_admin_tab_stoc() {
	$plan    = ccs_admin_plan();
	$r       = $plan['rezumat'];
	$decizii = ccs_decizii_curente( 'epuizare' );
	$luata   = $decizii[''] ?? null;
	$setari  = ccs_setari();

	ccs_ui_antet_tab(
		__( 'Stocul', 'conex-conect-sync' ),
		__( 'Cantitățile se iau de la Conex așa cum sunt, la fiecare rulare.', 'conex-conect-sync' )
	);
	?>
	<div class="ccs-cifre">
		<?php
		ccs_ui_cifra( __( 'Cantități de schimbat', 'conex-conect-sync' ), ccs_nr( $r['stoc'] ) );
		ccs_ui_cifra( __( 'Ar trece pe epuizat', 'conex-conect-sync' ), ccs_nr( $r['de_epuizat'] ) );
		ccs_ui_cifra( __( 'S-ar reactiva', 'conex-conect-sync' ), ccs_nr( $r['de_reactivat'] ) );
		ccs_ui_cifra( __( 'Fără nicio schimbare', 'conex-conect-sync' ), ccs_nr( $r['neatinse'] ) );
		?>
	</div>
	<?php

	ccs_ui_card( __( 'Ce se întâmplă cu stocul', 'conex-conect-sync' ) );
	echo '<ul class="ccs-stari">';
	$puncte = array(
		array(
			'bine',
			__( 'Produsele revenite în stoc', 'conex-conect-sync' ),
			sprintf(
				/* translators: %s: câte produse. */
				__( '%s produse sunt acum ascunse ca „epuizat”, dar furnizorul le are. Se reactivează.', 'conex-conect-sync' ),
				ccs_nr( $r['de_reactivat'] )
			),
		),
		array(
			'atentie',
			__( 'Produsele fără stoc', 'conex-conect-sync' ),
			sprintf(
				/* translators: %s: câte produse. */
				__( '%s produse ar trece pe „stoc epuizat”, pentru că furnizorul nu le mai are.', 'conex-conect-sync' ),
				ccs_nr( $r['de_epuizat'] )
			),
		),
		array(
			'neutru',
			__( 'Produsele ieșite din catalog', 'conex-conect-sync' ),
			sprintf(
				/* translators: %d: câte rulări. */
				__( 'Nu se șterg niciodată. Trec pe stoc epuizat după %d rulări în care lipsesc, și rămân pe site.', 'conex-conect-sync' ),
				(int) $setari['stoc_absent_runde']
			),
		),
	);
	$iconuri = array(
		'bine'    => 'yes',
		'atentie' => 'warning',
		'neutru'  => 'minus',
	);
	foreach ( $puncte as $st ) {
		printf(
			'<li class="ccs-stare ccs-stare--%1$s"><span class="ccs-stare__icon dashicons dashicons-%2$s" aria-hidden="true"></span><strong>%3$s</strong><span>%4$s</span></li>',
			esc_attr( $st[0] ),
			esc_attr( $iconuri[ $st[0] ] ),
			esc_html( $st[1] ),
			esc_html( $st[2] )
		);
	}
	echo '</ul>';
	?>
	<div class="ccs-butoane" style="margin-top:16px" data-tip="epuizare" data-referinta="">
		<button type="button" class="ccs-btn ccs-btn--principal ccs-decizie" data-valoare="aprobat">
			<?php esc_html_e( 'De acord, sincronizează stocul așa', 'conex-conect-sync' ); ?>
		</button>
		<span class="ccs-stare-text"><?php echo $luata ? esc_html( ccs_ui_text_decizie( $luata ) ) : ''; ?></span>
	</div>
	<?php
	ccs_ui_card_sfarsit();
}
