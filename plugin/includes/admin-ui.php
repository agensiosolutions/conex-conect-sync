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
	// Descrierea într-un element al ei, nu lipită după `</strong>` ca text liber: altfel nu poate fi stilată
	// separat de titlu și moștenește tot ce are titlul.
	echo '<div class="ccs-gol"><strong>' . esc_html( $titlu ) . '</strong>';
	if ( '' !== $desc ) {
		echo '<span>' . esc_html( $desc ) . '</span>';
	}
	echo '</div>';
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
 * Semnătura panoului: o valoare care se schimbă, scrisă ca „acum → nou”.
 *
 * Apare peste tot unde ceva se schimbă și nicăieri altundeva. Cele patru coloane de cifre de dinainte cereau de la
 * om să adune singur povestea; aici se citește dintr-o privire, iar procentul spune în ce direcție merge.
 *
 * @param float $acum Valoarea de acum.
 * @param float $nou  Valoarea propusă.
 * @return string HTML.
 */
function ccs_ui_delta( $acum, $nou ) {
	$acum = (float) $acum;
	$nou  = (float) $nou;
	$dir  = $nou > $acum ? 'sus' : ( $nou < $acum ? 'jos' : '' );

	$cat = '';
	if ( $acum > 0 && $dir ) {
		$cat = sprintf(
			'<span class="ccs-delta__cat">%s%s%%</span>',
			'sus' === $dir ? '+' : "\xe2\x88\x92",
			ccs_lei( abs( $nou / $acum - 1 ) * 100, 0 )
		);
	}

	return sprintf(
		'<span class="ccs-delta%1$s"><span class="ccs-delta__acum">%2$s</span><span class="ccs-delta__sageata" aria-hidden="true">&rarr;</span><span class="ccs-delta__nou">%3$s</span>%4$s</span>',
		$dir ? ' ccs-delta--' . $dir : '',
		esc_html( ccs_lei( $acum ) ),
		esc_html( ccs_lei( $nou ) ),
		$cat
	);
}

/**
 * Cât din întrebările tabului au primit răspuns.
 *
 * Fără asta, un tab cu șaizeci de produse arată la fel după douăzeci de răspunsuri ca la început, iar omul nu știe
 * cât mai are de mers.
 *
 * @param int $rezolvate Câte au primit răspuns.
 * @param int $total     Câte sunt.
 * @return void
 */
function ccs_ui_progres( $rezolvate, $total ) {
	if ( ! $total ) {
		return;
	}
	printf(
		'<div class="ccs-progres"><span class="ccs-progres__pista"><span class="ccs-progres__umplut%1$s" style="width:%2$d%%"></span></span><span>%3$s</span></div>',
		$rezolvate >= $total ? ' ccs-progres__umplut--gata' : '',
		(int) round( 100 * $rezolvate / $total ),
		esc_html(
			sprintf(
				/* translators: 1: câte au primit răspuns, 2: câte sunt în total. */
				__( '%1$d din %2$d rezolvate', 'conex-conect-sync' ),
				$rezolvate,
				$total
			)
		)
	);
}

/**
 * Tonul unei decizii: ce face pluginul dacă o alegi.
 *
 * O singură regulă, aceeași în toate taburile: verde înseamnă că produsul se schimbă, chihlimbar că rămâne cum e.
 * Fără regula asta, patru perechi de butoane cu etichete diferite cer de la om să citească de fiecare dată.
 *
 * @param string $valoare Valoarea deciziei.
 * @return string 'bine', 'atentie' sau ''.
 */
function ccs_ui_ton_decizie( $valoare ) {
	$tonuri = array(
		'acelasi'         => 'bine',     // Se leagă, deci prețul și stocul încep să se sincronizeze.
		'corecteaza'      => 'bine',     // Prețul se corectează.
		'lasa_sa_coboare' => 'bine',     // Prețul se schimbă, chiar dacă în jos.
		'aprobat'         => 'bine',
		'exclus'          => 'atentie',  // Produsul rămâne neatins, pentru totdeauna.
		'pret_manual'     => 'atentie',  // Prețul rămâne cum e.
	);
	return $tonuri[ $valoare ] ?? '';
}

/**
 * Cele două variante de răspuns, ca un comutator.
 *
 * Varianta aleasă rămâne apăsată, deci rândul își spune singur starea. Înainte erau două butoane separate plus o
 * propoziție care apărea dedesubt și muta layoutul.
 *
 * @param array      $opt   valoare => etichetă.
 * @param array|null $luata Decizia deja luată, dacă există.
 * @return void
 */
function ccs_ui_alegeri( $opt, $luata ) {
	echo '<div class="ccs-alegeri">';
	foreach ( $opt as $valoare => $eticheta ) {
		printf(
			'<button type="button" class="ccs-alegere ccs-decizie" data-valoare="%1$s" data-ton="%2$s" aria-pressed="%3$s">%4$s</button>',
			esc_attr( $valoare ),
			esc_attr( ccs_ui_ton_decizie( $valoare ) ),
			$luata && $luata['valoare'] === $valoare ? 'true' : 'false',
			esc_html( $eticheta )
		);
	}
	echo '</div>';
}

/**
 * Câte rânduri de plan au primit deja un răspuns.
 *
 * @param array $randuri Rândurile.
 * @param array $decizii Deciziile luate, pe referință.
 * @return int Numărul.
 */
function ccs_ui_cate_rezolvate( $randuri, $decizii ) {
	$n = 0;
	foreach ( $randuri as $r ) {
		if ( isset( $decizii[ (string) $r['wc_id'] ] ) ) {
			$n++;
		}
	}
	return $n;
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
	echo '<span>' . esc_html__( 'Aplică aceeași alegere pentru restul:', 'conex-conect-sync' ) . '</span>';
	foreach ( $opt as $valoare => $eticheta ) {
		printf(
			'<button type="button" class="ccs-btn ccs-btn--mic ccs-lot" data-valoare="%1$s" data-ton="%2$s" data-eticheta="%3$s">%4$s</button>',
			esc_attr( $valoare ),
			esc_attr( ccs_ui_ton_decizie( $valoare ) ),
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
		wp_die( esc_html__( 'Nu ai acces la această pagină.', 'conex-conect-sync' ) );
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
					'nimic'    => __( 'Nu mai sunt produse de hotărât.', 'conex-conect-sync' ),
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
					? ccs_ui_pastila( __( 'Scrierea pe site e oprită', 'conex-conect-sync' ), 'info' )
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
		__( 'Modificările pe care le-ar aplica sincronizarea în acest moment.', 'conex-conect-sync' )
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
			__( 'Până la primirea unui răspuns, produsele respective rămân neschimbate.', 'conex-conect-sync' )
		);
		echo '<ul class="ccs-de-rezolvat">';
		$intrebari = array(
			'potriviri' => array(
				'potrivire',
				__( 'Produse de confirmat', 'conex-conect-sync' ),
				__( 'Codul corespunde unui produs de la Conex, dar denumirile indică produse diferite.', 'conex-conect-sync' ),
			),
			'scaderi'   => array(
				'scadere',
				__( 'Prețuri care ar coborî mult', 'conex-conect-sync' ),
				__( 'Regula de preț le-ar reduce la mai puțin de jumătate din valoarea actuală.', 'conex-conect-sync' ),
			),
			'subcost'   => array(
				'sub_cost',
				__( 'Produse vândute sub cost', 'conex-conect-sync' ),
				__( 'Prețul de vânzare este sub costul de achiziție.', 'conex-conect-sync' ),
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

	ccs_ui_card_joburi();

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
				__( '%s produse de pe site nu mai există în catalogul Conex. Nu se șterg.', 'conex-conect-sync' ),
				ccs_nr( $potrivire['orfane'] )
			),
		),
		array(
			$setari['mod_raport'] ? 'atentie' : 'bine',
			__( 'Scrierea pe site', 'conex-conect-sync' ),
			$setari['mod_raport']
				? __( 'Oprită. Panoul arată modificările propuse, fără să le aplice.', 'conex-conect-sync' )
				: __( 'Pornită. Prețul și stocul se actualizează pe produse.', 'conex-conect-sync' ),
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
 * Cardul cu rularea automată.
 *
 * @return void
 */
function ccs_ui_card_joburi() {
	$setari = ccs_setari();
	$stare  = ccs_joburi_stare();
	$job    = reset( $stare );
	$pornit = ! empty( $setari['joburi_pornite'] ) && $job && $job['pornit'];

	ccs_ui_card(
		__( 'Rularea automată', 'conex-conect-sync' ),
		'',
		$pornit
			? ccs_ui_pastila( __( 'Pornită', 'conex-conect-sync' ), 'bine' )
			: ccs_ui_pastila( __( 'Oprită', 'conex-conect-sync' ), 'atentie' )
	);

	echo '<p class="ccs-explic">';
	if ( $pornit ) {
		printf(
			/* translators: 1: la câte minute, 2: când e următoarea rulare. */
			esc_html__( 'Catalogul furnizorului se preia la fiecare %1$d minute. Următoarea rulare: %2$s.', 'conex-conect-sync' ),
			(int) ( $job['interval'] / MINUTE_IN_SECONDS ),
			esc_html( $job['urmatoarea'] ? wp_date( 'j F, H:i', $job['urmatoarea'] ) : '-' )
		);
	} else {
		esc_html_e( 'Sincronizarea nu rulează automat. Catalogul se preia doar la apăsarea butonului „Recalculează”, iar datele de mai jos rămân cele de la ultima preluare.', 'conex-conect-sync' );
	}
	echo '</p>';

	echo '<p class="ccs-explic">';
	echo $setari['mod_raport'] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		? esc_html__( 'Cât timp scrierea este oprită, sincronizarea doar actualizează datele din panou, fără să modifice vreun produs.', 'conex-conect-sync' )
		: esc_html__( 'Scrierea este pornită, deci fiecare rulare aplică prețurile și stocurile modificate la furnizor.', 'conex-conect-sync' );
	echo '</p>';

	?>
	<div class="ccs-butoane ccs-butoane--final">
		<button type="button" class="ccs-btn <?php echo $pornit ? '' : 'ccs-btn--principal'; ?>" id="ccs-joburi" data-pornit="<?php echo $pornit ? 'true' : 'false'; ?>">
			<?php echo $pornit ? esc_html__( 'Oprește rularea automată', 'conex-conect-sync' ) : esc_html__( 'Pornește rularea automată', 'conex-conect-sync' ); ?>
		</button>
		<span class="ccs-stare-text" id="ccs-joburi-stare"></span>
	</div>
	<?php
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
		__( 'Modifică valorile pentru a vedea efectul asupra întregului catalog. Nimic nu se aplică pe site până la salvare.', 'conex-conect-sync' )
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

	<div class="ccs-doua">
		<div>
			<label class="ccs-camp" for="ccs-adaos">
				<span><?php esc_html_e( 'Adaos comercial', 'conex-conect-sync' ); ?></span>
				<small><?php esc_html_e( 'Procentul aplicat peste costul cu TVA inclus.', 'conex-conect-sync' ); ?></small>
				<span class="ccs-glisor">
					<input type="range" id="ccs-adaos" min="1" max="2.5" step="0.01" value="<?php echo esc_attr( $p['adaos'] ); ?>">
					<output id="ccs-adaos-val"></output>
				</span>
			</label>

			<label class="ccs-camp" for="ccs-marja">
				<span><?php esc_html_e( 'Marjă minimă în lei, pe produs', 'conex-conect-sync' ); ?></span>
				<small><?php esc_html_e( 'Protejează produsele ieftine. La un cost de 1,36 lei, un adaos de 35% ar rezulta într-un preț de 2 lei, adică 64 de bani marjă, sub costul de procesare a unei comenzi.', 'conex-conect-sync' ); ?></small>
				<span class="ccs-glisor">
					<input type="range" id="ccs-marja" min="0" max="15" step="0.5" value="<?php echo esc_attr( $p['marja_minima_lei'] ); ?>">
					<output id="ccs-marja-val"></output>
				</span>
			</label>

			<label class="ccs-camp" for="ccs-cota">
				<span><?php esc_html_e( 'Cota de TVA inclusă în preț', 'conex-conect-sync' ); ?></span>
				<small><?php esc_html_e( 'Prețurile de pe site sunt finale, cu TVA inclus. Furnizorul transmite costul fără TVA.', 'conex-conect-sync' ); ?></small>
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

			<div class="ccs-butoane ccs-butoane--final">
				<button type="button" class="ccs-btn ccs-btn--principal" id="ccs-salveaza-politica">
					<?php esc_html_e( 'Salvează politica de preț', 'conex-conect-sync' ); ?>
				</button>
				<span class="ccs-stare-text" id="ccs-politica-stare"></span>
			</div>
		</div>

		<div>
			<h3 class="ccs-subtitlu ccs-subtitlu--prim"><?php esc_html_e( 'Ce se întâmplă cu magazinul', 'conex-conect-sync' ); ?></h3>
			<table class="ccs-efect">
				<tbody>
					<tr><th><?php esc_html_e( 'Prețuri care urcă', 'conex-conect-sync' ); ?></th><td id="ccs-e-sus"></td></tr>
					<tr><th><?php esc_html_e( 'Prețuri care coboară', 'conex-conect-sync' ); ?></th><td id="ccs-e-jos"></td></tr>
					<tr><th><?php esc_html_e( 'Prețuri neschimbate', 'conex-conect-sync' ); ?></th><td id="ccs-e-egal"></td></tr>
					<tr><th><?php esc_html_e( 'Rămân sub cost', 'conex-conect-sync' ); ?></th><td id="ccs-e-subcost"></td></tr>
					<tr class="ccs-total"><th><?php esc_html_e( 'Marjă brută, la o bucată din fiecare', 'conex-conect-sync' ); ?></th><td id="ccs-e-marja"></td></tr>
				</tbody>
			</table>

			<h3 class="ccs-subtitlu"><?php esc_html_e( 'Pe produse concrete', 'conex-conect-sync' ); ?></h3>
			<div class="ccs-tabel-cadru">
				<table class="ccs-tabel">
					<thead>
						<tr>
							<th class="num"><?php esc_html_e( 'Cost fără TVA', 'conex-conect-sync' ); ?></th>
							<th class="num"><?php esc_html_e( 'Preț, acum și propus', 'conex-conect-sync' ); ?></th>
							<th class="num"><?php esc_html_e( 'Câștig', 'conex-conect-sync' ); ?></th>
						</tr>
					</thead>
					<tbody id="ccs-exemple"></tbody>
				</table>
			</div>
			<p class="ccs-mic"><?php esc_html_e( 'Șase produse din catalog, ordonate după cost.', 'conex-conect-sync' ); ?></p>
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
		__( 'La produsele de mai jos, codul de pe site corespunde unui cod de la Conex, dar denumirile indică produse diferite. Până la clarificare, prețul și stocul lor rămân neschimbate.', 'conex-conect-sync' )
	);
	ccs_ui_progres( count( array_intersect_key( $decizii, $lista ) ), count( $lista ) );

	if ( ! $lista ) {
		ccs_ui_card( __( 'Nimic de confirmat', 'conex-conect-sync' ) );
		ccs_ui_gol( __( 'Toate produsele corespund și după denumire.', 'conex-conect-sync' ) );
		ccs_ui_card_sfarsit();
		return;
	}

	ccs_ui_card(
		sprintf(
			/* translators: %d: câte produse. */
			_n( '%d produs de lămurit', '%d produse de lămurit', count( $lista ), 'conex-conect-sync' ),
			count( $lista )
		),
		__( 'Fie codul de pe site este greșit, fie produsul provine de la alt furnizor și nu trebuie asociat.', 'conex-conect-sync' )
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
								// Procentul iese din textul muted, dar rămâne neutru: e o măsurătoare, nu o decizie.
								// Verdele și galbenul sunt rezervate pentru ce face pluginul cu produsul, iar o
								// culoare cu două înțelesuri pe același ecran nu mai înseamnă nimic.
								printf(
									'<span class="ccs-pastila ccs-pastila--masura">%s</span>',
									esc_html(
										sprintf(
											/* translators: %d: procentul. */
											__( '%d%% cuvinte comune', 'conex-conect-sync' ),
											(int) $v['scor']
										)
									)
								);
								?>
							</p>
						</div>
					</div>
				</div>
			</div>
			<div class="ccs-butoane">
				<?php
				ccs_ui_alegeri(
					array(
						'acelasi' => __( 'E același produs', 'conex-conect-sync' ),
						'exclus'  => __( 'Nu e același produs', 'conex-conect-sync' ),
					),
					$luata
				);
				?>
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
		__( 'Produsele de mai jos au în prezent un adaos foarte mare, iar regula de preț le-ar reduce prețul la mai puțin de jumătate. Rezultatul este corect din punct de vedere al formulei, dar o scădere de această amploare nu se aplică fără confirmare.', 'conex-conect-sync' )
	);
	ccs_ui_progres( ccs_ui_cate_rezolvate( $randuri, $decizii ), count( $randuri ) );

	if ( ! $randuri ) {
		ccs_ui_card( __( 'Niciun preț nu scade sub jumătate', 'conex-conect-sync' ) );
		ccs_ui_gol( __( 'Nu există produse în această situație.', 'conex-conect-sync' ) );
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
				__( '%1$d produse au prețul de vânzare sub costul de achiziție de la Conex. La o bucată din fiecare, pierderea totală ar fi de %2$s lei. Regula de preț le corectează pe toate, dar merită verificate: unele pot avea prețul greșit de mult timp.', 'conex-conect-sync' ),
				count( $randuri ),
				ccs_lei( $pierdere )
			)
			: __( 'Niciun produs nu se vinde sub costul de achiziție.', 'conex-conect-sync' )
	);
	ccs_ui_progres( ccs_ui_cate_rezolvate( $randuri, $decizii ), count( $randuri ) );

	if ( ! $randuri ) {
		ccs_ui_card( __( 'Totul e în regulă', 'conex-conect-sync' ) );
		ccs_ui_gol( __( 'Niciun produs nu are prețul sub costul de achiziție.', 'conex-conect-sync' ) );
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
		__( 'Ordonate după pierderea pe bucată.', 'conex-conect-sync' )
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
					<th class="num"><?php esc_html_e( 'Preț, acum și propus', 'conex-conect-sync' ); ?></th>
					<th class="num"><?php esc_html_e( 'Marjă nouă', 'conex-conect-sync' ); ?></th>
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
					<td class="num"><?php echo ccs_ui_delta( $r['pret_site'], $r['pret_tinta'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					<td class="num <?php echo $r['pret_tinta'] - $r['cost_cu_tva'] < 0 ? 'ccs-rosu' : ''; ?>">
						<?php echo esc_html( ccs_lei( $r['pret_tinta'] - $r['cost_cu_tva'] ) ); ?>
					</td>
					<td><?php ccs_ui_alegeri( $opt, $luata ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/**
 * Catalogul: ce dispare, ce vine nou, cum se asociază categoriile și cum arată un produs preluat.
 *
 * Tabul ăsta nu pune o întrebare, ca celelalte: arată două cataloage de zeci de mii de produse. De aceea e singurul
 * loc din panou unde folosim culoare plină și bare, nu doar gri și o pastilă. O bară segmentată spune din prima
 * câte produse merg, câte au nevoie de o hotărâre și câte sunt blocate de furnizor; aceleași cifre într-un tabel
 * cer să fie citite și adunate.
 *
 * Un sens, o culoare, peste tot în tab: verde merge, albastru e al magazinului, chihlimbar cere o hotărâre, gri e
 * blocat de furnizor.
 *
 * @return void
 */
function ccs_admin_tab_catalog() {
	$m        = ccs_miscare( 7 );
	$c        = ccs_admin_candidati();
	$grupuri  = ccs_retragere_grupuri();
	$pot      = ccs_admin_potrivire();

	ccs_ui_antet_tab(
		__( 'Catalogul', 'conex-conect-sync' ),
		__( 'Starea celor două cataloage și operațiunile rămase de făcut.', 'conex-conect-sync' )
	);

	ccs_ui_catalog_actiuni( $c, $grupuri, $m );
	ccs_ui_catalog_bare( $c, $grupuri, $pot, $m );
	ccs_ui_catalog_miscare( $m );
	ccs_ui_catalog_mapare();
	ccs_ui_catalog_de_mapat( $c );
	ccs_ui_catalog_produse_noi( $c );
	ccs_ui_catalog_dispare( $grupuri );
}

/**
 * Cele două sau trei lucruri de făcut, sus, în culoare plină.
 *
 * @param array $c       Candidații la import.
 * @param array $grupuri Grupurile de retragere.
 * @param array $m       Mișcarea.
 * @return void
 */
function ccs_ui_catalog_actiuni( $c, $grupuri, $m ) {
	$de_retras = isset( $grupuri['din_feed'] ) ? ccs_retragere_de_atins( array_keys( $grupuri['din_feed'] ) ) : 0;

	echo '<div class="ccs-actiuni-mari">';

	printf(
		'<div class="ccs-actiune ccs-actiune--verde"><span class="ccs-actiune__icon dashicons dashicons-download" aria-hidden="true"></span><span class="ccs-actiune__cifra">%s</span><span class="ccs-actiune__titlu">%s</span><span class="ccs-actiune__desc">%s</span></div>',
		esc_html( ccs_nr( $c['gata'] ) ),
		esc_html__( 'produse pot fi importate', 'conex-conect-sync' ),
		esc_html__( 'Au imagine, cost de achiziție, stoc și o categorie asociată. Importul va porni din acest panou.', 'conex-conect-sync' )
	);

	if ( $de_retras ) {
		?>
		<div class="ccs-actiune ccs-actiune--galben" data-grup="din_feed">
			<span class="ccs-actiune__icon dashicons dashicons-archive" aria-hidden="true"></span>
			<span class="ccs-actiune__cifra"><?php echo esc_html( ccs_nr( $de_retras ) ); ?></span>
			<span class="ccs-actiune__titlu"><?php esc_html_e( 'produse indisponibile la furnizor', 'conex-conect-sync' ); ?></span>
			<span class="ccs-actiune__desc"><?php esc_html_e( 'Sunt disponibile pe site, dar nu mai pot fi comandate de la furnizor. Trec pe stoc epuizat, fără să fie șterse.', 'conex-conect-sync' ); ?></span>
			<button type="button" class="ccs-btn ccs-btn--mic ccs-retrage" data-grup="din_feed"
				data-eticheta="<?php esc_attr_e( 'Retrage de la vânzare', 'conex-conect-sync' ); ?>">
				<?php esc_html_e( 'Retrage de la vânzare', 'conex-conect-sync' ); ?>
			</button>
			<span class="ccs-stare-text"></span>
		</div>
		<?php
	}

	if ( $c['fara_categorie'] ) {
		printf(
			'<div class="ccs-actiune ccs-actiune--albastru"><span class="ccs-actiune__icon dashicons dashicons-category" aria-hidden="true"></span><span class="ccs-actiune__cifra">%s</span><span class="ccs-actiune__titlu">%s</span><span class="ccs-actiune__desc">%s</span></div>',
			esc_html( ccs_nr( $c['fara_categorie'] ) ),
			esc_html__( 'produse așteaptă încadrarea pe categorie', 'conex-conect-sync' ),
			esc_html__( 'Au imagine, cost și stoc. Singurul element care lipsește este categoria din magazin.', 'conex-conect-sync' )
		);
	}

	echo '</div>';
}

/**
 * Cele două cataloage, ca bare segmentate.
 *
 * @param array $c       Candidații.
 * @param array $grupuri Grupurile.
 * @param array $pot     Potrivirea.
 * @param array $m       Mișcarea.
 * @return void
 */
function ccs_ui_catalog_bare( $c, $grupuri, $pot, $m ) {
	ccs_ui_card( __( 'Distribuția produselor', 'conex-conect-sync' ), __( 'Cum se împart cele două cataloage, după starea fiecărui produs.', 'conex-conect-sync' ) );

	$legate   = (int) $pot['legate'];
	$din_feed = isset( $grupuri['din_feed'] ) ? count( $grupuri['din_feed'] ) : 0;
	$manuale  = isset( $grupuri['manuale'] ) ? count( $grupuri['manuale'] ) : 0;
	$altele   = ( isset( $grupuri['altele'] ) ? count( $grupuri['altele'] ) : 0 ) + ( isset( $grupuri['excluse'] ) ? count( $grupuri['excluse'] ) : 0 );

	ccs_ui_bara(
		__( 'Pe site-ul tău', 'conex-conect-sync' ),
		array(
			array( 'verde', __( 'sincronizate cu Conex', 'conex-conect-sync' ), $legate ),
			array( 'galben', __( 'rămase din feed-ul vechi', 'conex-conect-sync' ), $din_feed ),
			array( 'albastru', __( 'introduse de echipă', 'conex-conect-sync' ), $manuale ),
			array( 'gri', __( 'altele', 'conex-conect-sync' ), $altele ),
		)
	);

	ccs_ui_bara(
		__( 'În catalogul Conex', 'conex-conect-sync' ),
		array(
			array( 'verde', __( 'deja pe site', 'conex-conect-sync' ), $legate ),
			array( 'verde-moale', __( 'pot fi importate', 'conex-conect-sync' ), $c['gata'] ),
			array( 'galben', __( 'fără categorie asociată', 'conex-conect-sync' ), $c['fara_categorie'] ),
			array( 'gri', __( 'fără poză la furnizor', 'conex-conect-sync' ), $c['fara_poza'] ),
			array( 'gri-moale', __( 'fără stoc', 'conex-conect-sync' ), $c['fara_stoc'] ),
		)
	);

	ccs_ui_card_sfarsit();
}

/**
 * O bară segmentată, cu legendă.
 *
 * @param string $titlu   Titlul.
 * @param array  $bucati  Câte array( culoare, eticheta, numar ).
 * @return void
 */
function ccs_ui_bara( $titlu, $bucati ) {
	$total = 0;
	foreach ( $bucati as $b ) {
		$total += (int) $b[2];
	}
	if ( ! $total ) {
		return;
	}

	echo '<div class="ccs-bara">';
	printf(
		'<div class="ccs-bara__cap"><span class="ccs-bara__titlu">%s</span><span class="ccs-bara__total">%s</span></div>',
		esc_html( $titlu ),
		esc_html( ccs_nr( $total ) )
	);

	echo '<div class="ccs-bara__pista">';
	foreach ( $bucati as $b ) {
		if ( (int) $b[2] <= 0 ) {
			continue;
		}
		printf(
			'<span class="ccs-bara__bucata ccs-bara__bucata--%1$s" style="width:%2$s%%" title="%3$s: %4$s"></span>',
			esc_attr( $b[0] ),
			esc_attr( number_format( 100 * (int) $b[2] / $total, 3, '.', '' ) ),
			esc_attr( $b[1] ),
			esc_attr( ccs_nr( $b[2] ) )
		);
	}
	echo '</div>';

	echo '<div class="ccs-legenda">';
	foreach ( $bucati as $b ) {
		if ( (int) $b[2] <= 0 ) {
			continue;
		}
		printf(
			'<span class="ccs-legenda__item"><span class="ccs-legenda__pata ccs-bara__bucata--%1$s"></span><span class="ccs-legenda__cifra">%2$s</span> %3$s</span>',
			esc_attr( $b[0] ),
			esc_html( ccs_nr( $b[2] ) ),
			esc_html( $b[1] )
		);
	}
	echo '</div></div>';
}

/**
 * Mișcarea din ultimele zile.
 *
 * @param array $m Mișcarea.
 * @return void
 */
function ccs_ui_catalog_miscare( $m ) {
	$setari = ccs_setari();

	ccs_ui_card(
		sprintf(
			/* translators: %d: câte zile. */
			__( 'Ce s-a schimbat în ultimele %d zile', 'conex-conect-sync' ),
			$m['zile']
		),
		$m['ultima_rulare']
			? sprintf(
				/* translators: %s: data ultimei preluări. */
				__( 'Ultima preluare: %s.', 'conex-conect-sync' ),
				mysql2date( 'j F, H:i', $m['ultima_rulare'] )
			)
			: __( 'Catalogul n-a fost preluat încă.', 'conex-conect-sync' )
	);
	?>
	<div class="ccs-miscare">
		<div class="ccs-miscare__item">
			<span class="ccs-miscare__semn ccs-miscare__semn--intra">+</span>
			<span>
				<span class="ccs-miscare__cifra"><?php echo esc_html( ccs_nr( $m['noi'] ) ); ?></span>
				<span class="ccs-miscare__eticheta"><?php esc_html_e( 'produse noi la furnizor', 'conex-conect-sync' ); ?></span>
			</span>
		</div>
		<div class="ccs-miscare__item">
			<span class="ccs-miscare__semn ccs-miscare__semn--iese">&minus;</span>
			<span>
				<span class="ccs-miscare__cifra"><?php echo esc_html( ccs_nr( $m['iesite'] ) ); ?></span>
				<span class="ccs-miscare__eticheta"><?php esc_html_e( 'au ieșit din catalog', 'conex-conect-sync' ); ?></span>
			</span>
		</div>
	</div>
	<?php

	if ( empty( $setari['joburi_pornite'] ) ) {
		echo '<p class="ccs-mic">' . esc_html__( 'Rularea automată este oprită, deci datele se actualizează doar la o preluare cerută manual.', 'conex-conect-sync' ) . '</p>';
	}

	$noi = ccs_miscare_lista( 'noi', $m['zile'], 5 );
	if ( $noi ) {
		echo '<h3 class="ccs-subtitlu">' . esc_html__( 'Ce a intrat', 'conex-conect-sync' ) . '</h3>';
		echo '<div class="ccs-tabel-cadru"><table class="ccs-tabel"><tbody>';
		foreach ( $noi as $r ) {
			printf(
				'<tr><td class="ccs-produs"><strong>%s</strong><span>%s &middot; %s</span></td><td class="num">%s lei</td><td class="num">%s</td></tr>',
				esc_html( $r->nume ),
				esc_html( $r->cod ),
				esc_html( $r->brand ),
				esc_html( ccs_lei( ccs_pret( $r->cost ) ) ),
				esc_html( sprintf( /* translators: %d: stocul. */ __( 'stoc %d', 'conex-conect-sync' ), (int) $r->stoc ) )
			);
		}
		echo '</tbody></table></div>';
	}

	ccs_ui_card_sfarsit();
}

/**
 * Asocierea categoriilor: ce cale de la furnizor duce în ce categorie de pe site.
 *
 * @return void
 */
function ccs_ui_catalog_mapare() {
	global $wpdb;
	$setari  = ccs_setari();
	$tabel   = ccs_tabel( 'categorii' );
	$reguli  = (array) $wpdb->get_results( "SELECT * FROM $tabel ORDER BY produse DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	$min_n   = (int) $setari['categorie_min_produse'];
	$min_inc = (int) $setari['categorie_min_incredere'];

	$folosite = 0;
	foreach ( $reguli as $r ) {
		if ( 'om' === $r['sursa'] || ( (int) $r['produse'] >= $min_n && (int) $r['incredere'] >= $min_inc ) ) {
			$folosite++;
		}
	}

	ccs_ui_card(
		__( 'Cum se asociază categoriile', 'conex-conect-sync' ),
		__( 'Conex organizează catalogul pe sisteme ale automobilului, iar magazinul pe categorii comerciale. Regulile de mai jos rezultă din cele peste trei mii de produse deja încadrate în magazin, fără completare manuală.', 'conex-conect-sync' ),
		ccs_ui_pastila(
			sprintf(
				/* translators: 1: reguli folosite, 2: total. */
				__( '%1$d din %2$d reguli active', 'conex-conect-sync' ),
				$folosite,
				count( $reguli )
			),
			'bine'
		)
	);

	if ( ! $reguli ) {
		ccs_ui_gol( __( 'Nicio regulă încă.', 'conex-conect-sync' ), __( 'Se învață automat din produsele deja legate de furnizor, la sincronizarea de noapte.', 'conex-conect-sync' ) );
		ccs_ui_card_sfarsit();
		return;
	}

	echo '<div class="ccs-mapare">';
	foreach ( array_slice( $reguli, 0, 14 ) as $r ) {
		$t        = get_term( (int) $r['term_id'], 'product_cat' );
		$slaba    = 'om' !== $r['sursa'] && ( (int) $r['produse'] < $min_n || (int) $r['incredere'] < $min_inc );
		$clase    = 'ccs-mapare__rand';
		$clase   .= $r['contine'] ? ' ccs-mapare__rand--cuvant' : '';
		$clase   .= $slaba ? ' ccs-mapare__rand--fara' : '';
		printf(
			'<div class="%1$s"><span class="ccs-mapare__cale">%2$s%3$s</span><span class="ccs-mapare__sageata">&rarr;</span><span class="ccs-mapare__dest">%4$s</span><span class="ccs-mapare__bara"><span style="width:%5$d%%"></span></span><span class="ccs-mapare__n">%6$s</span></div>',
			esc_attr( $clase ),
			esc_html( $r['cale'] ),
			$r['contine'] ? '<span class="ccs-mapare__cuvant">+ ' . esc_html( $r['contine'] ) . '</span>' : '',
			esc_html( $t && ! is_wp_error( $t ) ? $t->name : __( 'categorie ștearsă', 'conex-conect-sync' ) ),
			(int) $r['incredere'],
			esc_html(
				$slaba
					? __( 'prea puține', 'conex-conect-sync' )
					: sprintf( /* translators: %d: câte produse. */ __( '%d prod.', 'conex-conect-sync' ), (int) $r['produse'] )
			)
		);
	}
	echo '</div>';

	echo '<p class="ccs-mic">';
	esc_html_e( 'Rândurile cu un cuvânt marcat albastru se împart după denumirea produsului: aceeași categorie la furnizor, două categorii în magazin. Cele marcate galben provin din prea puține produse pentru a fi folosite.', 'conex-conect-sync' );
	echo '</p>';

	ccs_ui_card_sfarsit();
}

/**
 * Categoriile furnizorului care n-au o categorie din magazin asociată, cu un selector pentru fiecare.
 *
 * Învățarea se uită doar la produsele legate de Conex, deci o cale pe care omul o știe din prima n-are cum să intre
 * singură. Exemplul care a scos nevoia asta la iveală: magazinul are categoria „Ulei motor”, cu cincizeci de uleiuri
 * puse de echipă, dar alea au cod propriu și nu sunt legate, deci regula n-a fost învățată niciodată. Conex are 644
 * de uleiuri de motor care stăteau degeaba.
 *
 * O asociere pusă de om bate orice regulă învățată și nu se pierde la reînvățare.
 *
 * @param array $c Candidații la import.
 * @return void
 */
function ccs_ui_catalog_de_mapat( $c ) {
	$cai = isset( $c['cai'] ) ? $c['cai'] : array();
	if ( ! $cai ) {
		return;
	}

	$total = array_sum( $cai );

	ccs_ui_card(
		__( 'Categorii ale furnizorului neasociate', 'conex-conect-sync' ),
		sprintf(
			/* translators: 1: câte produse, 2: câte căi. */
			__( '%1$s produse au poză, cost și stoc, dar nu știm unde să le punem. Sunt pe %2$d căi: alege categoria o dată, și intră toate produsele de pe calea aia.', 'conex-conect-sync' ),
			ccs_nr( $total ),
			count( $cai )
		),
		ccs_ui_pastila(
			sprintf(
				/* translators: %d: câte căi. */
				_n( '%d categorie de asociat', '%d categorii de asociat', count( $cai ), 'conex-conect-sync' ),
				count( $cai )
			),
			'atentie'
		)
	);

	echo '<div class="ccs-de-mapat">';
	foreach ( array_slice( $cai, 0, 12, true ) as $cale => $n ) {
		$id = 'ccs-map-' . md5( $cale );
		?>
		<div class="ccs-de-mapat__rand" data-cale="<?php echo esc_attr( $cale ); ?>">
			<div class="ccs-de-mapat__stanga">
				<span class="ccs-de-mapat__cale"><?php echo esc_html( $cale ); ?></span>
				<span class="ccs-de-mapat__n">
					<?php
					printf(
						/* translators: %s: câte produse. */
						esc_html( _n( '%s produs s-ar importa', '%s produse s-ar importa', $n, 'conex-conect-sync' ) ),
						esc_html( ccs_nr( $n ) )
					);
					?>
				</span>
			</div>
			<?php
			wp_dropdown_categories(
				array(
					'taxonomy'          => 'product_cat',
					'name'              => $id,
					'id'                => $id,
					'class'             => 'ccs-intrare ccs-de-mapat__select',
					'show_option_none'  => __( 'alege categoria', 'conex-conect-sync' ),
					'option_none_value' => '0',
					'hierarchical'      => true,
					'hide_empty'        => false,
					'orderby'           => 'name',
				)
			);
			?>
			<span class="ccs-stare-text"></span>
		</div>
		<?php
	}
	echo '</div>';

	if ( count( $cai ) > 12 ) {
		printf(
			'<p class="ccs-mic">%s</p>',
			esc_html(
				sprintf(
					/* translators: %d: câte căi mai sunt. */
					__( 'Încă %d categorii, cu un număr mai mic de produse fiecare. Apar pe măsură ce sunt rezolvate cele de mai sus.', 'conex-conect-sync' ),
					count( $cai ) - 12
				)
			)
		);
	}

	ccs_ui_card_sfarsit();
}

/**
 * Cum arată produsele care ar fi aduse pe site.
 *
 * @param array $c Candidații.
 * @return void
 */
function ccs_ui_catalog_produse_noi( $c ) {
	ccs_ui_card(
		__( 'Cum arată un produs importat', 'conex-conect-sync' ),
		__( 'Datele cu care s-ar crea produsul: imaginea și denumirea de la furnizor, prețul rezultat din regula de calcul și categoria stabilită automat. Descrierea lipsește pentru că furnizorul nu o transmite.', 'conex-conect-sync' )
	);

	if ( ! $c['lista'] ) {
		ccs_ui_gol( __( 'Niciun produs nu poate fi importat deocamdată.', 'conex-conect-sync' ), __( 'Verifică dacă imaginile au fost preluate de la furnizor.', 'conex-conect-sync' ) );
		ccs_ui_card_sfarsit();
		return;
	}

	echo '<div class="ccs-produse-noi">';
	foreach ( array_slice( $c['lista'], 0, 5 ) as $p ) {
		$t    = get_term( ccs_categorie_pentru( $p ), 'product_cat' );
		$poze = ccs_imagini_produs( (int) $p->product_id, 1 );
		?>
		<div class="ccs-produs-nou">
			<div class="ccs-produs-nou__poza">
				<?php if ( $poze ) : ?>
					<img src="<?php echo esc_url( $poze[0] ); ?>" alt="" loading="lazy" onerror="this.style.display='none'">
				<?php endif; ?>
			</div>
			<div class="ccs-produs-nou__corp">
				<span class="ccs-produs-nou__nume"><?php echo esc_html( $p->nume ); ?></span>
				<span class="ccs-produs-nou__pret"><?php echo esc_html( ccs_lei( ccs_pret( $p->cost ) ) ); ?> lei</span>
				<span class="ccs-produs-nou__jos">
					<?php if ( $t && ! is_wp_error( $t ) ) : ?>
						<span class="ccs-eticheta ccs-eticheta--categorie"><?php echo esc_html( $t->name ); ?></span>
					<?php endif; ?>
					<span class="ccs-eticheta ccs-eticheta--stoc"><?php printf( esc_html__( 'stoc %d', 'conex-conect-sync' ), (int) $p->stoc ); ?></span>
					<span class="ccs-eticheta ccs-eticheta--poze"><?php printf( esc_html( _n( '%d poză', '%d poze', (int) $p->poze, 'conex-conect-sync' ) ), (int) $p->poze ); ?></span>
				</span>
			</div>
		</div>
		<?php
	}
	echo '</div>';
	ccs_ui_card_sfarsit();
}

/**
 * Produsele de pe site pe care furnizorul nu le mai are, pe grupuri.
 *
 * @param array $grupuri Grupurile.
 * @return void
 */
function ccs_ui_catalog_dispare( $grupuri ) {
	// Fiecare grup: iconiță, culoare, explicația de unde vine, și dacă se poate retrage.
	$etichete = array(
		'din_feed' => array(
			'titlu'   => __( 'Rămase din feed-ul vechi', 'conex-conect-sync' ),
			'desc'    => __( 'Provin din importul Mega Drive, oprit în noiembrie 2025. Nu mai există în catalogul Conex.', 'conex-conect-sync' ),
			'icon'    => 'archive',
			'culoare' => 'galben',
			'retrage' => true,
		),
		'excluse'  => array(
			'titlu'   => __( 'Scoase din potrivire', 'conex-conect-sync' ),
			'desc'    => __( 'Produse marcate ca fiind diferite de cele pe care Conex le are pe același cod.', 'conex-conect-sync' ),
			'icon'    => 'hidden',
			'culoare' => 'gri',
			'retrage' => true,
		),
		'altele'   => array(
			'titlu'   => __( 'Alt tipar de cod', 'conex-conect-sync' ),
			'desc'    => __( 'Codul nu corespunde niciunui tipar cunoscut. Necesită o verificare înainte de orice operațiune.', 'conex-conect-sync' ),
			'icon'    => 'editor-help',
			'culoare' => 'gri',
			'retrage' => true,
		),
		'manuale'  => array(
			'titlu'   => __( 'Introduse manual de echipă', 'conex-conect-sync' ),
			'desc'    => __( 'Codurile încep cu două litere. Aparțin magazinului, nu furnizorului, și nu sunt modificate de sincronizare.', 'conex-conect-sync' ),
			'icon'    => 'admin-users',
			'culoare' => 'albastru',
			'retrage' => false,
		),
	);

	ccs_ui_card(
		__( 'Produse pe care furnizorul nu le mai are', 'conex-conect-sync' ),
		__( 'Produsele nu se șterg. Trec pe stoc epuizat și rămân pe site, cu pagina și poziția lor în Google.', 'conex-conect-sync' )
	);

	foreach ( $etichete as $grup => $e ) {
		$ids = isset( $grupuri[ $grup ] ) ? array_keys( $grupuri[ $grup ] ) : array();
		if ( ! $ids ) {
			continue;
		}
		$total    = count( $ids );
		$de_atins = ccs_retragere_de_atins( $ids );
		$retrase  = $total - $de_atins;
		$eticheta = sprintf( /* translators: %s: câte produse. */ __( 'Retrage %s de la vânzare', 'conex-conect-sync' ), ccs_nr( $de_atins ) );
		?>
		<div class="ccs-grup ccs-grup--<?php echo esc_attr( $e['culoare'] ); ?>" data-grup="<?php echo esc_attr( $grup ); ?>">
			<span class="ccs-grup__icon dashicons dashicons-<?php echo esc_attr( $e['icon'] ); ?>" aria-hidden="true"></span>
			<div>
				<p class="ccs-grup__cap">
					<strong><?php echo esc_html( $e['titlu'] ); ?></strong>
					<span class="ccs-grup__n"><?php echo esc_html( ccs_nr( $total ) ); ?></span>
				</p>
				<p class="ccs-mic"><?php echo esc_html( $e['desc'] ); ?></p>

				<?php if ( $e['retrage'] ) : ?>
					<p class="ccs-grup__progres">
						<span class="ccs-grup__pista"><span style="width:<?php echo esc_attr( $total ? (int) round( 100 * $retrase / $total ) : 100 ); ?>%"></span></span>
						<span class="ccs-grup__stare">
							<?php
							printf(
								/* translators: 1: câte sunt retrase, 2: câte se mai vând. */
								esc_html__( '%1$s retrase, %2$s încă de vânzare', 'conex-conect-sync' ),
								esc_html( ccs_nr( $retrase ) ),
								esc_html( ccs_nr( $de_atins ) )
							);
							?>
						</span>
					</p>
				<?php endif; ?>

				<div class="ccs-butoane">
					<?php if ( $e['retrage'] && $de_atins ) : ?>
						<button type="button" class="ccs-btn ccs-btn--mic ccs-retrage" data-ton="atentie"
							data-grup="<?php echo esc_attr( $grup ); ?>" data-eticheta="<?php echo esc_attr( $eticheta ); ?>">
							<?php echo esc_html( $eticheta ); ?>
						</button>
					<?php elseif ( $e['retrage'] ) : ?>
						<?php echo ccs_ui_pastila( __( 'toate retrase', 'conex-conect-sync' ), 'bine' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endif; ?>
					<a class="ccs-btn ccs-btn--mic ccs-btn--fantoma" href="<?php echo esc_url( admin_url( 'edit.php?post_type=product&ccs_grup=' . $grup ) ); ?>">
						<?php esc_html_e( 'Vezi lista', 'conex-conect-sync' ); ?>
					</a>
					<span class="ccs-stare-text"></span>
				</div>
			</div>
		</div>
		<?php
	}
	ccs_ui_card_sfarsit();
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
		__( 'Cantitățile se preiau de la Conex la fiecare rulare, fără ajustări.', 'conex-conect-sync' )
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
				__( '%s produse figurează ca epuizate, deși furnizorul le are în stoc. Vor fi reactivate.', 'conex-conect-sync' ),
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
				__( 'Nu se șterg. Trec pe stoc epuizat după %d rulări consecutive în care lipsesc din catalog și rămân pe site.', 'conex-conect-sync' ),
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
	<div class="ccs-butoane ccs-butoane--final" data-tip="epuizare" data-referinta="">
		<button type="button" class="ccs-btn ccs-btn--principal ccs-decizie" data-valoare="aprobat">
			<?php esc_html_e( 'De acord, sincronizează stocul așa', 'conex-conect-sync' ); ?>
		</button>
		<span class="ccs-stare-text"><?php echo $luata ? esc_html( ccs_ui_text_decizie( $luata ) ) : ''; ?></span>
	</div>
	<?php
	ccs_ui_card_sfarsit();
}
