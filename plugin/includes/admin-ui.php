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
function ccs_ui_cifra( $eticheta, $valoare, $ton = '', $optiuni = array() ) {
	$optiuni = wp_parse_args(
		$optiuni,
		array(
			'icon'       => '',   // Numele unei dashicon, fără prefix.
			'sub'        => '',   // Pastila de sub număr.
			'sub_ton'    => '',   // Tonul pastilei, dacă e altul decât al cifrei.
			'secundar'   => '',   // Valoarea retrasă de lângă număr, ca „6 / 6”.
		)
	);

	echo '<div class="ccs-cifra">';

	// Plăcuța de iconiță e singurul loc din card unde intră culoare. Vezi antetul fișierului CSS.
	printf(
		'<span class="ccs-plac%1$s">%2$s</span>',
		$ton ? ' ccs-plac--' . esc_attr( $ton ) : '',
		$optiuni['icon'] ? ccs_icon( $optiuni['icon'], 18 ) : ''
	);

	echo '<span class="ccs-cifra__corp">';
	printf( '<span class="ccs-cifra__eticheta">%s</span>', esc_html( $eticheta ) );
	printf(
		'<strong>%1$s%2$s</strong>',
		esc_html( $valoare ),
		'' !== (string) $optiuni['secundar'] ? ' <em>' . esc_html( $optiuni['secundar'] ) . '</em>' : ''
	);
	if ( '' !== (string) $optiuni['sub'] ) {
		// `ccs_ui_pastila()` întoarce, nu tipărește.
		$ton_pastila = $optiuni['sub_ton'] ? $optiuni['sub_ton'] : $ton;
		echo wp_kses_post( ccs_ui_pastila( $optiuni['sub'], $ton_pastila ? $ton_pastila : 'neutru' ) );
	}
	echo '</span></div>';
}

/**
 * O dată scrisă în română.
 *
 * Aceeași problemă ca la durate: `mysql2date()` și `wp_date()` urmează limba adminului, care pe cezauto.ro e
 * engleza, deci ieșea „26 November, 11:46” într-un panou în rest românesc. Lunile sunt scrise aici, fiindcă sunt
 * douăsprezece și nu se schimbă niciodată.
 *
 * @param int|string $cand Timestamp sau dată din baza de date.
 * @param bool       $cu_an Dacă să scrie și anul.
 * @return string Data.
 */
function ccs_data( $cand, $cu_an = false ) {
	if ( ! $cand ) {
		return '?';
	}

	$ts = is_numeric( $cand ) ? (int) $cand : (int) mysql2date( 'U', (string) $cand, false );
	if ( ! $ts ) {
		return '?';
	}

	$luni = array(
		1  => __( 'ianuarie', 'conex-conect-sync' ),
		2  => __( 'februarie', 'conex-conect-sync' ),
		3  => __( 'martie', 'conex-conect-sync' ),
		4  => __( 'aprilie', 'conex-conect-sync' ),
		5  => __( 'mai', 'conex-conect-sync' ),
		6  => __( 'iunie', 'conex-conect-sync' ),
		7  => __( 'iulie', 'conex-conect-sync' ),
		8  => __( 'august', 'conex-conect-sync' ),
		9  => __( 'septembrie', 'conex-conect-sync' ),
		10 => __( 'octombrie', 'conex-conect-sync' ),
		11 => __( 'noiembrie', 'conex-conect-sync' ),
		12 => __( 'decembrie', 'conex-conect-sync' ),
	);

	// Formatele numerice le lăsăm pe seama WordPress, ca ora să respecte fusul site-ului.
	$zi   = (int) wp_date( 'j', $ts );
	$luna = (int) wp_date( 'n', $ts );
	$an   = wp_date( 'Y', $ts );
	$ora  = wp_date( 'H:i', $ts );

	return $cu_an
		? sprintf( '%d %s %s, %s', $zi, $luni[ $luna ], $an, $ora )
		: sprintf( '%d %s, %s', $zi, $luni[ $luna ], $ora );
}

/**
 * O durată scrisă în română.
 *
 * `human_time_diff()` urmează limba adminului WordPress, iar pe cezauto.ro adminul e în engleză. Rezultatul era
 * „acum 23 hours”, româno-engleză în aceeași propoziție, într-un panou în care tot restul e în română. Pluginul
 * nu poate schimba limba adminului și nici nu trebuie, deci își scrie singur durata.
 *
 * Rotunjim în jos și ne oprim la o singură unitate: „acum 3 ore” spune destul, „acum 3 ore și 14 minute” nu
 * ajută pe nimeni să hotărască ceva.
 *
 * @param int $secunde Câte secunde.
 * @return string Durata, fără prefix.
 */
function ccs_durata( $secunde ) {
	$secunde = max( 0, (int) $secunde );

	if ( $secunde < 60 ) {
		return sprintf(
			/* translators: %d: câte secunde. */
			_n( '%d secundă', '%d secunde', $secunde, 'conex-conect-sync' ),
			$secunde
		);
	}

	$minute = (int) floor( $secunde / MINUTE_IN_SECONDS );
	if ( $minute < 60 ) {
		// În română, numeralele de la 20 în sus cer „de”: 20 de minute, dar 19 minute.
		return $minute >= 20
			/* translators: %d: câte minute. */
			? sprintf( __( '%d de minute', 'conex-conect-sync' ), $minute )
			/* translators: %d: câte minute. */
			: sprintf( _n( '%d minut', '%d minute', $minute, 'conex-conect-sync' ), $minute );
	}

	$ore = (int) floor( $secunde / HOUR_IN_SECONDS );
	if ( $ore < 24 ) {
		return $ore >= 20
			/* translators: %d: câte ore. */
			? sprintf( __( '%d de ore', 'conex-conect-sync' ), $ore )
			/* translators: %d: câte ore. */
			: sprintf( _n( '%d oră', '%d ore', $ore, 'conex-conect-sync' ), $ore );
	}

	$zile = (int) floor( $secunde / DAY_IN_SECONDS );
	if ( $zile < 30 ) {
		return $zile >= 20
			/* translators: %d: câte zile. */
			? sprintf( __( '%d de zile', 'conex-conect-sync' ), $zile )
			/* translators: %d: câte zile. */
			: sprintf( _n( '%d zi', '%d zile', $zile, 'conex-conect-sync' ), $zile );
	}

	$luni = (int) floor( $zile / 30 );
	return $luni >= 20
		/* translators: %d: câte luni. */
		? sprintf( __( '%d de luni', 'conex-conect-sync' ), $luni )
		/* translators: %d: câte luni. */
		: sprintf( _n( '%d lună', '%d luni', $luni, 'conex-conect-sync' ), $luni );
}

/**
 * De cât timp s-a întâmplat ceva, în română.
 *
 * @param string $data Data din baza de date.
 * @return string Durata.
 */
function ccs_de_cand( $data ) {
	if ( ! $data ) {
		return '?';
	}
	return ccs_durata( time() - (int) mysql2date( 'U', $data, false ) );
}

/**
 * Cum se cheamă fiecare tip de rulare, pe românește.
 *
 * @param string $tip Tipul din baza de date.
 * @return string Numele.
 */
function ccs_ui_nume_rulare( $tip ) {
	$nume = array(
		'catalog'        => __( 'Preluare catalog', 'conex-conect-sync' ),
		'potrivire'      => __( 'Legare produse', 'conex-conect-sync' ),
		'aplicare_tot'   => __( 'Aplicare preț și stoc', 'conex-conect-sync' ),
		'aplicare_pret'  => __( 'Aplicare prețuri', 'conex-conect-sync' ),
		'aplicare_stoc'  => __( 'Aplicare stocuri', 'conex-conect-sync' ),
		'import'         => __( 'Import produse noi', 'conex-conect-sync' ),
		'imagini'        => __( 'Preluare imagini', 'conex-conect-sync' ),
		'atribute'       => __( 'Preluare date tehnice', 'conex-conect-sync' ),
		'retragere'      => __( 'Retragere de la vânzare', 'conex-conect-sync' ),
		'curatenie'      => __( 'Curățare produse', 'conex-conect-sync' ),
		'revenire'       => __( 'Revenire', 'conex-conect-sync' ),
		'corectii'       => __( 'Corectare produse', 'conex-conect-sync' ),
	);
	return $nume[ $tip ] ?? ucfirst( str_replace( '_', ' ', $tip ) );
}

/**
 * Tonul și eticheta stării unei rulări.
 *
 * @param string $stare Starea din baza de date.
 * @return array array( eticheta, ton ).
 */
function ccs_ui_stare_rulare( $stare ) {
	$stari = array(
		'gata'           => array( __( 'reușită', 'conex-conect-sync' ), 'bine' ),
		'gata_cu_erori'  => array( __( 'cu erori', 'conex-conect-sync' ), 'atentie' ),
		'pornit'         => array( __( 'în lucru', 'conex-conect-sync' ), 'info' ),
		'intrerupta'     => array( __( 'întreruptă', 'conex-conect-sync' ), 'problema' ),
	);
	return $stari[ $stare ] ?? array( $stare, 'neutru' );
}

/**
 * Ce a schimbat o rulare, în cuvinte, din rezumatul ei.
 *
 * Rezumatele au chei diferite după tip, fiindcă fiecare rulare face altceva. Aici le traducem într-o listă de
 * perechi „cifră, ce e”, din care se fac pastilele. Cheile care lipsesc sau sunt zero se sar: un rând cu cinci
 * zerouri nu spune nimic.
 *
 * Întoarce și cheia, nu doar eticheta: tonul pastilei se alege după ea. O comparație pe eticheta tradusă s-ar
 * rupe la prima traducere, și se rupsese deja aici, într-un loc unde scrisesem „esuate” fără diacritice.
 *
 * @param array $rulare O rulare din `ccs_rulari_recente()`.
 * @return array Listă de array( valoare, eticheta, cheie ).
 */
function ccs_ui_efect_rulare( $rulare ) {
	$r = $rulare['rezumat'];

	$harta = array(
		'pret'       => __( 'prețuri', 'conex-conect-sync' ),
		'stoc'       => __( 'stocuri', 'conex-conect-sync' ),
		'stare_stoc' => __( 'intrate sau ieșite din stoc', 'conex-conect-sync' ),
		'create'     => __( 'produse noi', 'conex-conect-sync' ),
		'poze'       => __( 'poze', 'conex-conect-sync' ),
		'atinse'     => __( 'produse atinse', 'conex-conect-sync' ),
		'sterse'     => __( 'la coșul de gunoi', 'conex-conect-sync' ),
		'ascunse'    => __( 'ascunse din catalog', 'conex-conect-sync' ),
		'readuse'    => __( 'readuse', 'conex-conect-sync' ),
		'produse'    => __( 'produse', 'conex-conect-sync' ),
		'perechi'    => __( 'date tehnice', 'conex-conect-sync' ),
		'noi'        => __( 'produse noi la furnizor', 'conex-conect-sync' ),
		'esuate'     => __( 'eșuate', 'conex-conect-sync' ),
	);

	// „atinse” și „produse” spun același lucru când rularea are deja detalii pe câmpuri; le ținem doar ca plasă.
	if ( ! empty( $r['pret'] ) || ! empty( $r['stoc'] ) ) {
		unset( $harta['atinse'], $harta['produse'] );
	}

	$iesire = array();
	foreach ( $harta as $cheie => $eticheta ) {
		if ( ! empty( $r[ $cheie ] ) && is_scalar( $r[ $cheie ] ) ) {
			$iesire[] = array( (int) $r[ $cheie ], $eticheta, $cheie );
		}
	}

	return $iesire;
}

/**
 * Cardul „Ce s-a întâmplat la ultima rulare”.
 *
 * E primul lucru pe care ar trebui să îl vadă cineva care deschide panoul dimineața, iar până acum nu exista
 * nicăieri: datele erau în `ccs_runs` de la bun început, dar nimic nu le citea.
 *
 * @return void
 */
function ccs_ui_ultima_rulare() {
	$rulari = ccs_rulari_recente( 1 );
	if ( ! $rulari ) {
		return;
	}
	$r = $rulari[0];

	list( $eticheta, $ton ) = ccs_ui_stare_rulare( $r['stare'] );
	$cand    = $r['sfarsit'] ? $r['sfarsit'] : $r['inceput'];
	$de_dat  = ccs_rulare_de_revenit( $r['id'] );

	ccs_ui_card(
		__( 'Ultima rulare', 'conex-conect-sync' ),
		ccs_ui_nume_rulare( $r['tip'] ),
		ccs_ui_pastila( $eticheta, $ton )
	);

	echo '<div class="ccs-rulare">';

	echo '<p class="ccs-rulare__cand">';
	printf(
		/* translators: %s: de cât timp, de exemplu „20 de minute”. */
		esc_html__( 'acum %s', 'conex-conect-sync' ),
		esc_html( ccs_de_cand( $cand ) )
	);
	if ( $r['durata'] ) {
		echo ' · ';
		printf(
			/* translators: %s: durata, de exemplu „6 minute”. */
			esc_html__( 'a durat %s', 'conex-conect-sync' ),
			esc_html( ccs_durata( $r['durata'] ) )
		);
	}
	echo '</p>';

	$efect = ccs_ui_efect_rulare( $r );
	if ( $efect ) {
		echo '<p class="ccs-rulare__efect">';
		foreach ( $efect as $e ) {
			printf(
				'<span class="ccs-pastila ccs-pastila--%1$s"><b>%2$s</b> %3$s</span>',
				'esuate' === $e[2] ? 'problema' : 'info',
				esc_html( ccs_nr( $e[0] ) ),
				esc_html( $e[1] )
			);
		}
		echo '</p>';
	} else {
		printf( '<p class="ccs-rulare__efect ccs-mic">%s</p>', esc_html__( 'Nu a schimbat nimic pe site.', 'conex-conect-sync' ) );
	}

	if ( $de_dat ) {
		printf(
			'<p class="ccs-rulare__verbe"><button type="button" class="ccs-btn ccs-btn--principal ccs-revino" data-rulare="%1$d" data-cate="%2$d">%3$s</button> <span class="ccs-stare-text"></span></p>',
			(int) $r['id'],
			(int) $de_dat,
			esc_html(
				sprintf(
					/* translators: %s: câte modificări. */
					__( 'Dă înapoi rularea (%s modificări)', 'conex-conect-sync' ),
					ccs_nr( $de_dat )
				)
			)
		);
	}

	echo '</div>';
	ccs_ui_card_sfarsit();
}

/**
 * Jurnalul rulărilor recente.
 *
 * Forma e luată din „Recent Sync Activity” al lui Omni Supplier Sync: ce rulare, starea ca pastilă, acum cât
 * timp, și ce a schimbat, tot în pastile. Un tabel care se scanează pe coloane, nu se citește rând cu rând.
 *
 * @param int $limita Câte rulări.
 * @return void
 */
function ccs_ui_jurnal_rulari( $limita = 10 ) {
	$rulari = ccs_rulari_recente( $limita );
	if ( ! $rulari ) {
		return;
	}

	ccs_ui_card(
		__( 'Rulările recente', 'conex-conect-sync' ),
		__( 'Fiecare pornire a sincronizării, cu ce a schimbat pe site.', 'conex-conect-sync' )
	);

	echo '<div class="ccs-tabel-cadru"><table class="ccs-tabel"><thead><tr>';
	printf( '<th>%s</th>', esc_html__( 'Rulare', 'conex-conect-sync' ) );
	printf( '<th>%s</th>', esc_html__( 'Stare', 'conex-conect-sync' ) );
	printf( '<th>%s</th>', esc_html__( 'Când', 'conex-conect-sync' ) );
	printf( '<th>%s</th>', esc_html__( 'Ce a schimbat', 'conex-conect-sync' ) );
	echo '</tr></thead><tbody>';

	foreach ( $rulari as $r ) {
		list( $eticheta, $ton ) = ccs_ui_stare_rulare( $r['stare'] );
		$cand = $r['sfarsit'] ? $r['sfarsit'] : $r['inceput'];

		echo '<tr>';
		printf( '<td><strong>%s</strong></td>', esc_html( ccs_ui_nume_rulare( $r['tip'] ) ) );
		printf( '<td>%s</td>', wp_kses_post( ccs_ui_pastila( $eticheta, $ton ) ) );
		printf(
			'<td class="ccs-tabel__slab">%s</td>',
			esc_html(
				$cand
					/* translators: %s: de cât timp. */
					? sprintf( __( 'acum %s', 'conex-conect-sync' ), ccs_de_cand( $cand ) )
					: '?'
			)
		);

		echo '<td>';
		$efect = ccs_ui_efect_rulare( $r );
		if ( $efect ) {
			foreach ( array_slice( $efect, 0, 4 ) as $e ) {
				printf(
					'<span class="ccs-pastila ccs-pastila--%1$s"><b>%2$s</b> %3$s</span> ',
					'esuate' === $e[2] ? 'problema' : 'neutru',
					esc_html( ccs_nr( $e[0] ) ),
					esc_html( $e[1] )
				);
			}
		} else {
			printf( '<span class="ccs-tabel__slab">%s</span>', esc_html__( 'nimic', 'conex-conect-sync' ) );
		}
		echo '</td></tr>';
	}

	echo '</tbody></table></div>';
	ccs_ui_card_sfarsit();
}

/**
 * O pastilă care își poartă explicația, arătată la hover pe tot textul ei.
 *
 * Iconița stă în pastilă, nu lângă ea: un semn de întrebare singur, la câțiva pixeli de cuvânt, pare alt lucru
 * și cere ochit cu mouse-ul. Tot blocul e ținta, deci ajunge să treci peste text.
 *
 * @param string $text       Textul pastilei.
 * @param string $ton        Tonul.
 * @param string $explicatie Ce se arată la hover.
 * @param string $unde       'stanga' când pastila e lângă marginea din dreapta.
 * @return string HTML.
 */
function ccs_ui_pastila_info( $text, $ton, $explicatie, $unde = '' ) {
	return sprintf(
		'<span class="ccs-pastila ccs-pastila--%1$s ccs-info ccs-info--pastila%2$s" tabindex="0" role="note" aria-label="%3$s">%4$s' . ccs_icon( 'info-outline', 14 ) . '<span class="ccs-info__bula">%5$s</span></span>',
		esc_attr( $ton ),
		$unde ? ' ccs-info--' . esc_attr( $unde ) : '',
		esc_attr( $text . '. ' . wp_strip_all_tags( $explicatie ) ),
		esc_html( $text ),
		esc_html( $explicatie )
	);
}

/**
 * Explicația pentru „scrierea pe site e oprită”, adaptată la ce mai e de făcut.
 *
 * @return string Textul.
 */
function ccs_ui_explica_scrierea() {
	$politica = ccs_decizii_curente( 'politica_pret' );

	return ! empty( $politica[''] )
		? __( 'Sincronizarea citește de la furnizor și îți arată ce s-ar schimba, dar nu modifică niciun produs. Politica de preț e confirmată, deci poți porni scrierea din Panou, de la pasul „Scrierea pe site e pornită”.', 'conex-conect-sync' )
		: __( 'Sincronizarea citește de la furnizor și îți arată ce s-ar schimba, dar nu modifică niciun produs. Ca să o poți porni, confirmă întâi politica de preț: adaosul, cota de TVA și marja minimă.', 'conex-conect-sync' );
}

/**
 * Pașii de pus la punct, cu starea fiecăruia citită din sistem.
 *
 * Un ghid scris într-un fișier separat are două probleme: trebuie deschis, și îmbătrânește. Ăsta stă în panou și
 * se uită la ce s-a făcut deja, deci nu poate minți. Bifele nu se pun cu mâna niciodată.
 *
 * Ordinea e ordinea reală de lucru: fără catalog nu ai ce lega, fără legături nu ai ce calcula, fără politica de
 * preț nu ai voie să scrii, și așa mai departe. De aceea se arată un singur pas deschis, primul neterminat: un om
 * care vede șapte lucruri deodată nu știe cu care să înceapă.
 *
 * @return array Pașii, fiecare cu gata, titlu, text, buton.
 */
function ccs_ui_pasi() {
	global $wpdb;
	$setari = ccs_setari();

	$produse  = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . ccs_tabel( 'products' ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	$legaturi = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . ccs_tabel( 'links' ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	$imagini  = ccs_imagini_stare();
	$politica = ccs_decizii_curente( 'politica_pret' );
	$aplicari = (int) $wpdb->get_var(
		// phpcs:ignore WordPress.DB.PreparedSQL
		"SELECT COUNT(*) FROM " . ccs_tabel( 'runs' ) . " WHERE tip LIKE 'aplicare%' AND stare IN ('gata','gata_cu_erori')"
	);

	return array(
		array(
			'gata'  => $produse > 0,
			'titlu' => __( 'Catalogul furnizorului e preluat', 'conex-conect-sync' ),
			'text'  => $produse
				? sprintf(
					/* translators: %s: câte produse. */
					__( '%s produse citite de la Conex.', 'conex-conect-sync' ),
					ccs_nr( $produse )
				)
				: __( 'Încă nu am citit catalogul Conex. Apasă „Recalculează”, sus în dreapta.', 'conex-conect-sync' ),
			'buton' => '',
			'tab'   => '',
		),
		array(
			'gata'  => $legaturi > 0,
			'titlu' => __( 'Produsele de pe site sunt legate de furnizor', 'conex-conect-sync' ),
			'text'  => $legaturi
				? sprintf(
					/* translators: %s: câte legături. */
					__( '%s produse de pe site au corespondent la Conex. Numai ele se sincronizează.', 'conex-conect-sync' ),
					ccs_nr( $legaturi )
				)
				: __( 'Niciun produs de pe site nu e legat încă de catalogul Conex.', 'conex-conect-sync' ),
			'buton' => '',
			'tab'   => '',
		),
		array(
			'gata'  => ! empty( $politica[''] ),
			'titlu' => __( 'Politica de preț e confirmată', 'conex-conect-sync' ),
			'text'  => ! empty( $politica[''] )
				? __( 'Adaosul, cota de TVA și marja minimă sunt stabilite.', 'conex-conect-sync' )
				: __( 'Stabilește adaosul, cota de TVA și marja minimă. Până atunci nu se poate schimba niciun preț.', 'conex-conect-sync' ),
			'buton' => __( 'Deschide politica de preț', 'conex-conect-sync' ),
			'tab'   => 'pret',
		),
		array(
			'gata'  => ! $setari['mod_raport'],
			'titlu' => __( 'Scrierea pe site e pornită', 'conex-conect-sync' ),
			'text'  => $setari['mod_raport']
				? __( 'Deocamdată panoul doar îți arată ce s-ar schimba. Nimic nu se aplică pe produse. Pornește scrierea când ești mulțumit de ce vezi în celelalte taburi.', 'conex-conect-sync' )
				: __( 'Sincronizarea schimbă prețurile și stocurile pe site.', 'conex-conect-sync' ),
			'buton' => '',
			'tab'   => '',
			// Butonul e aici, nu un link spre alt tab: e singura comandă din tot panoul după care site-ul începe
			// să se schimbe singur, deci trebuie să stea lângă explicația ei.
			'html'  => sprintf(
				'<button type="button" class="ccs-btn ccs-btn--mic ccs-btn--principal ccs-porneste-scrierea" data-eticheta="%1$s">%1$s</button> <span class="ccs-stare-text"></span>',
				esc_attr__( 'Pornește scrierea pe site', 'conex-conect-sync' )
			),
		),
		array(
			'gata'  => $aplicari > 0,
			'titlu' => __( 'Prima aplicare e făcută', 'conex-conect-sync' ),
			'text'  => $aplicari
				? sprintf(
					/* translators: %s: câte aplicări. */
					__( '%s aplicări duse la capăt până acum.', 'conex-conect-sync' ),
					ccs_nr( $aplicari )
				)
				: __( 'Prețurile și stocurile nu au fost încă scrise pe site nici măcar o dată.', 'conex-conect-sync' ),
			'buton' => '',
			'tab'   => '',
		),
		array(
			'gata'  => $imagini['produse'] > 0,
			'titlu' => __( 'Pozele produselor sunt preluate', 'conex-conect-sync' ),
			'text'  => $imagini['produse']
				? sprintf(
					/* translators: 1: câte produse au poze, 2: câte poze. */
					__( '%1$s produse au poze la furnizor, %2$s poze în total.', 'conex-conect-sync' ),
					ccs_nr( $imagini['produse'] ),
					ccs_nr( $imagini['poze'] )
				)
				: __( 'Pozele se iau noaptea, între 19:00 și 05:00, fiindcă furnizorul le dă doar atunci. Fără ele nu se poate importa niciun produs.', 'conex-conect-sync' ),
			'buton' => '',
			'tab'   => '',
		),
		array(
			'gata'  => (bool) $setari['joburi_pornite'],
			'titlu' => __( 'Sincronizarea rulează singură', 'conex-conect-sync' ),
			'text'  => $setari['joburi_pornite']
				? sprintf(
					/* translators: %d: la câte minute. */
					__( 'Catalogul se citește la fiecare %d minute, fără să apeși nimic.', 'conex-conect-sync' ),
					(int) $setari['interval_catalog']
				)
				: __( 'Catalogul se preia doar când apeși „Recalculează”. Pornește rularea automată ca să meargă singură.', 'conex-conect-sync' ),
			'buton' => '',
			'tab'   => '',
		),
	);
}

/**
 * Mai are ghidul ceva de arătat?
 *
 * Butonul care îl readuce n-are rost când toți pașii sunt bifați: ar aduce înapoi un card gol. Întrebarea asta
 * o pune și cardul, și butonul din antet, deci stă într-un singur loc.
 *
 * @return bool
 */
function ccs_ui_mai_sunt_pasi() {
	foreach ( ccs_ui_pasi() as $p ) {
		if ( ! $p['gata'] ) {
			return true;
		}
	}
	return false;
}

/**
 * Cardul cu pașii de pus la punct.
 *
 * Dispare singur când totul e gata, fiindcă un card bifat în întregime devine mobilier. Se poate și închide, iar
 * alegerea se ține minte.
 *
 * @return void
 */
function ccs_ui_card_pasi() {
	if ( get_option( 'ccs_ghid_inchis' ) ) {
		return;
	}

	$pasi  = ccs_ui_pasi();
	$gata  = 0;
	foreach ( $pasi as $p ) {
		if ( $p['gata'] ) {
			$gata++;
		}
	}
	$total = count( $pasi );
	if ( $gata >= $total ) {
		return;
	}

	ccs_ui_card(
		__( 'Ce mai e de pus la punct', 'conex-conect-sync' ),
		__( 'Pașii se bifează singuri, pe măsură ce lucrurile se întâmplă. Nu ai de ținut minte nimic.', 'conex-conect-sync' ),
		ccs_ui_pastila(
			sprintf(
				/* translators: 1: câți pași gata, 2: câți în total. */
				__( '%1$d din %2$d', 'conex-conect-sync' ),
				$gata,
				$total
			),
			$gata ? 'info' : 'atentie'
		)
	);

	echo '<ol class="ccs-pasi">';
	$deschis = false;
	foreach ( $pasi as $i => $p ) {
		// Un singur pas deschis: primul neterminat. Șapte lucruri deodată nu spun de unde începi.
		$acesta  = ! $p['gata'] && ! $deschis;
		$deschis = $deschis || $acesta;
		?>
		<li class="ccs-pas<?php echo $p['gata'] ? ' ccs-pas--gata' : ''; ?><?php echo $acesta ? ' ccs-pas--acum' : ''; ?>">
			<span class="ccs-pas__bifa" aria-hidden="true">
				<?php echo ccs_icon( $p['gata'] ? 'yes' : 'marker', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</span>
			<span class="ccs-pas__corp">
				<span class="ccs-pas__titlu"><?php echo esc_html( $p['titlu'] ); ?></span>
				<?php if ( $acesta || $p['gata'] ) : ?>
					<span class="ccs-pas__text"><?php echo esc_html( $p['text'] ); ?></span>
				<?php endif; ?>
				<?php if ( $acesta && $p['buton'] && $p['tab'] ) : ?>
					<a class="ccs-btn ccs-btn--mic ccs-btn--principal" href="<?php echo esc_url( ccs_admin_url( $p['tab'] ) ); ?>">
						<?php echo esc_html( $p['buton'] ); ?>
					</a>
				<?php endif; ?>
				<?php if ( $acesta && ! empty( $p['html'] ) ) : ?>
					<span class="ccs-pas__actiune"><?php echo $p['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- construit de noi. ?></span>
				<?php endif; ?>
			</span>
		</li>
		<?php
	}
	echo '</ol>';

	printf(
		'<p class="ccs-mic"><button type="button" class="ccs-btn ccs-btn--mic ccs-btn--fantoma ccs-inchide-ghid">%s%s</button></p>',
		ccs_icon( 'no-alt', 14 ),
		esc_html__( 'Nu îmi mai arăta pașii', 'conex-conect-sync' )
	);

	ccs_ui_card_sfarsit();
}

/**
 * Faptele despre sistem pentru linia de stare de pe Panou.
 *
 * Patru lucruri, în ordinea în care contează pentru cineva care tocmai a deschis pagina: mai vorbim cu furnizorul,
 * de când, scriem pe site, și rulează singur.
 *
 * @return array Faptele, gata de dat lui `ccs_ui_linie_stare()`.
 */
function ccs_ui_fapte_sistem() {
	$setari = ccs_setari();
	$fapte  = array();

	$ultima = function_exists( 'ccs_ultima_sincronizare' ) ? ccs_ultima_sincronizare() : null;
	if ( $ultima ) {
		$trecut = time() - (int) mysql2date( 'U', $ultima, false );
		$ore    = $trecut / HOUR_IN_SECONDS;
		$fapte[] = array(
			__( 'Ultima preluare', 'conex-conect-sync' ),
			sprintf(
				/* translators: %s: de cât timp, de exemplu „20 de minute”. */
				__( 'acum %s', 'conex-conect-sync' ),
				ccs_de_cand( $ultima )
			),
			$ore > (int) $setari['ore_fara_sincronizare'] ? 'problema' : 'bine',
		);
	} else {
		$fapte[] = array( __( 'Ultima preluare', 'conex-conect-sync' ), __( 'niciodată', 'conex-conect-sync' ), 'problema' );
	}

	$fapte[] = array(
		__( 'Scrierea pe site', 'conex-conect-sync' ),
		$setari['mod_raport'] ? __( 'oprită', 'conex-conect-sync' ) : __( 'pornită', 'conex-conect-sync' ),
		$setari['mod_raport'] ? 'atentie' : 'bine',
		$setari['mod_raport'] ? ccs_ui_explica_scrierea() : '',
	);

	$fapte[] = array(
		__( 'Rularea automată', 'conex-conect-sync' ),
		$setari['joburi_pornite']
			/* translators: %d: la câte minute rulează. */
			? sprintf( __( 'la %d minute', 'conex-conect-sync' ), (int) $setari['interval_catalog'] )
			: __( 'oprită', 'conex-conect-sync' ),
		$setari['joburi_pornite'] ? 'bine' : 'atentie',
	);

	$eroare = (array) get_option( 'ccs_ultima_eroare', array() );
	if ( ! empty( $eroare['cod'] ) ) {
		$fapte[] = array( __( 'Ultima rulare', 'conex-conect-sync' ), __( 'cu eroare', 'conex-conect-sync' ), 'problema' );
	}

	return $fapte;
}

/**
 * Cardul de acțiune, în limbajul cardurilor de produs din catalog.
 *
 * Același obiect peste tot: plăcuță de iconiță și cifră sus, titlu, o explicație scurtă, iar jos ce poți face.
 * Înainte existau două limbaje în paralel, unul cu plăcuțe pline și cifre colorate în Catalog, altul cu o listă
 * simplă în Panou. Două limbaje pentru același lucru înseamnă că omul învață de două ori.
 *
 * Culoarea stă tot în plăcuță și în pastile, niciodată pe card și niciodată pe cifră.
 *
 * @param array $a cifra, titlu, desc, icon, ton, href, jos (HTML), atribute (string), clase.
 * @return void
 */
function ccs_ui_tile( $a ) {
	$a = wp_parse_args(
		$a,
		array(
			'cifra'     => '',
			'titlu'     => '',
			'desc'      => '',
			'icon'      => 'marker',
			'ton'       => '',
			'href'      => '',
			'jos'       => '',
			'atribute'  => '',
			'clase'     => '',
		)
	);

	$etichetă = $a['href'] ? 'a' : 'div';
	printf(
		'<%1$s class="ccs-tile%2$s"%3$s%4$s>',
		$etichetă, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal din cod.
		$a['clase'] ? ' ' . esc_attr( $a['clase'] ) : '',
		$a['href'] ? ' href="' . esc_url( $a['href'] ) . '"' : '',
		$a['atribute'] ? ' ' . $a['atribute'] : '' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- construit de noi.
	);

	echo '<span class="ccs-tile__cap">';
	printf(
		'<span class="ccs-plac%1$s">%2$s</span>',
		$a['ton'] ? ' ccs-plac--' . esc_attr( $a['ton'] ) : '',
		ccs_icon( $a['icon'], 18 )
	);
	if ( '' !== (string) $a['cifra'] ) {
		printf( '<span class="ccs-tile__cifra">%s</span>', esc_html( $a['cifra'] ) );
	}
	echo '</span>';

	printf( '<span class="ccs-tile__titlu">%s</span>', esc_html( $a['titlu'] ) );
	if ( $a['desc'] ) {
		printf( '<span class="ccs-tile__desc">%s</span>', esc_html( $a['desc'] ) );
	}
	if ( $a['jos'] ) {
		printf( '<span class="ccs-tile__jos">%s</span>', $a['jos'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- construit de noi.
	}

	printf( '</%s>', $etichetă ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal din cod.
}

/**
 * Un rând de fapte despre sistem, deasupra cifrelor.
 *
 * Răspunde la „merge?”, întrebarea dinaintea oricărei cifre. Fără cutii: bulină, eticheta în gri, valoarea în
 * negru. Bulina pulsează numai când chiar rulează ceva.
 *
 * @param array $fapte Listă de array( eticheta, valoare, ton ). Tonul: bine, atentie, problema, lucreaza.
 * @return void
 */
function ccs_ui_linie_stare( $fapte ) {
	if ( ! $fapte ) {
		return;
	}
	echo '<div class="ccs-linie-stare">';
	foreach ( $fapte as $f ) {
		$are_info = ! empty( $f[3] );
		printf(
			'<span class="ccs-linie-stare__fapt%1$s"%2$s><span class="ccs-bulina%3$s"></span>%4$s <b>%5$s</b>%6$s</span>',
			$are_info ? ' ccs-info' : '',
			$are_info ? ' tabindex="0" role="note" aria-label="' . esc_attr( $f[0] . ' ' . $f[1] . '. ' . wp_strip_all_tags( $f[3] ) ) . '"' : '',
			! empty( $f[2] ) ? ' ccs-bulina--' . esc_attr( $f[2] ) : '',
			esc_html( $f[0] ),
			esc_html( $f[1] ),
			$are_info
				? ccs_icon( 'info-outline', 14 ) . '<span class="ccs-info__bula">' . esc_html( $f[3] ) . '</span>'
				: ''
		);
	}
	echo '</div>';
}

/**
 * Eticheta unui grup de carduri.
 *
 * Împarte o stivă plată de carduri în secțiuni cu sens, ceea ce e cea mai ieftină ierarhie din tot panoul.
 *
 * @param string $text Eticheta.
 * @return void
 */
function ccs_ui_eticheta_sectiune( $text ) {
	printf( '<p class="ccs-eticheta-sectiune">%s</p>', esc_html( $text ) );
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
	echo '<span>' . esc_html__( 'Aplică aceeași alegere pentru toate:', 'conex-conect-sync' ) . '</span>';

	// Aceeași formă ca alegerile de pe rând: aceeași acțiune, deci același obiect. Înainte era buton cu chenar
	// sus și control segmentat pe rând, adică două lucruri diferite pentru aceeași hotărâre.
	echo '<div class="ccs-alegeri">';
	foreach ( $opt as $valoare => $eticheta ) {
		printf(
			'<button type="button" class="ccs-alegere ccs-lot" data-valoare="%1$s" data-ton="%2$s" data-eticheta="%3$s">%4$s</button>',
			esc_attr( $valoare ),
			esc_attr( ccs_ui_ton_decizie( $valoare ) ),
			esc_attr( $eticheta ),
			esc_html( $eticheta )
		);
	}
	echo '</div>';
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
		ccs_data( $luata['creat_la'], true )
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
					'confirmaRevenirea' => __( 'Sigur? Apasă din nou ca să dai înapoi', 'conex-conect-sync' ),
					'selectate' => __( '%d produse selectate', 'conex-conect-sync' ),
					'confirmaScrierea' => __( 'Sigur? De acum se schimbă produse', 'conex-conect-sync' ),
					'nimic'    => __( 'Nu mai sunt produse de hotărât.', 'conex-conect-sync' ),
				),
			)
		);
	}
	?>
	<div class="ccs-admin">
		<header class="ccs-antet">
			<div class="ccs-antet__titlu">
				<span class="ccs-antet__icon"><?php echo ccs_icon( 'update', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<div>
					<h1><?php esc_html_e( 'Sincronizare Conex', 'conex-conect-sync' ); ?></h1>
					<p><?php esc_html_e( 'Prețul, stocul și produsele, luate automat de la furnizor', 'conex-conect-sync' ); ?></p>
				</div>
			</div>
			<div class="ccs-antet__dreapta">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- construit de noi, escapat.
				echo $setari['mod_raport']
					? ccs_ui_pastila_info(
						__( 'Scrierea pe site e oprită', 'conex-conect-sync' ),
						'atentie',
						ccs_ui_explica_scrierea(),
						'stanga'
					)
					: ccs_ui_pastila( __( 'Sincronizare pornită', 'conex-conect-sync' ), 'bine' );
				?>
				<?php if ( get_option( 'ccs_ghid_inchis' ) && ccs_ui_mai_sunt_pasi() ) : ?>
					<button type="button" class="ccs-btn ccs-btn--fantoma ccs-arata-ghid" data-panou="<?php echo esc_url( ccs_admin_url( 'panou' ) ); ?>">
						<?php
						echo ccs_icon( 'editor-help', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						esc_html_e( 'Arată pașii', 'conex-conect-sync' );
						?>
					</button>
				<?php endif; ?>
				<a class="ccs-btn ccs-btn--fantoma" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=ccs-decizii&tab=' . $tab . '&recalculeaza=1' ), 'ccs-recalculeaza' ) ); ?>">
					<?php echo ccs_icon( 'image-rotate', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Recalculează', 'conex-conect-sync' ); ?>
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
						<?php echo ccs_icon( $t[1], 17 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
	<?php
	// Întâi „merge?”, apoi „cât?”. O cifră citită fără să știi dacă sincronizarea mai rulează poate fi veche de
	// nouă luni, exact ce s-a întâmplat cu feed-ul vechi.
	ccs_ui_linie_stare( ccs_ui_fapte_sistem() );

	// Ghidul stă primul cât timp mai e ceva de pus la punct, fiindcă atunci e singurul lucru care contează.
	ccs_ui_card_pasi();
	?>

	<div class="ccs-cifre">
		<?php
		$setari = ccs_setari();

		ccs_ui_cifra(
			__( 'Produse sincronizate', 'conex-conect-sync' ),
			ccs_nr( $r['produse'] ),
			'info',
			array(
				'icon' => 'update',
				'sub'  => __( 'legate de Conex', 'conex-conect-sync' ),
			)
		);
		ccs_ui_cifra(
			__( 'Prețuri de schimbat', 'conex-conect-sync' ),
			ccs_nr( $r['pret'] ),
			$r['pret'] ? 'atentie' : 'bine',
			array(
				'icon' => 'tag',
				'sub'  => $setari['mod_raport']
					? __( 'doar propuse', 'conex-conect-sync' )
					: __( 'se aplică automat', 'conex-conect-sync' ),
			)
		);
		ccs_ui_cifra(
			__( 'Stocuri de schimbat', 'conex-conect-sync' ),
			ccs_nr( $r['stoc'] ),
			$r['stoc'] ? 'atentie' : 'bine',
			array(
				'icon' => 'archive',
				'sub'  => $setari['mod_raport']
					? __( 'doar propuse', 'conex-conect-sync' )
					: __( 'se aplică automat', 'conex-conect-sync' ),
			)
		);
		ccs_ui_cifra(
			__( 'Întrebări fără răspuns', 'conex-conect-sync' ),
			ccs_nr( $total ),
			$total ? 'atentie' : 'bine',
			array(
				'icon' => $total ? 'editor-help' : 'yes-alt',
				'sub'  => $total
					? __( 'așteaptă o decizie', 'conex-conect-sync' )
					: __( 'nimic de hotărât', 'conex-conect-sync' ),
			)
		);
		?>
	</div>

	<?php
	// Ce s-a întâmplat, înainte de ce ai de făcut: dimineața prima întrebare e „a mers azi-noapte?”.
	ccs_ui_eticheta_sectiune( __( 'Ce s-a întâmplat', 'conex-conect-sync' ) );
	ccs_ui_ultima_rulare();

	if ( $total ) {
		ccs_ui_eticheta_sectiune( __( 'Ce ai de hotărât', 'conex-conect-sync' ) );
		ccs_ui_card(
			__( 'De hotărât', 'conex-conect-sync' ),
			__( 'Până la primirea unui răspuns, produsele respective rămân neschimbate.', 'conex-conect-sync' )
		);
		$intrebari = array(
			'potriviri' => array(
				'potrivire',
				__( 'Produse de confirmat', 'conex-conect-sync' ),
				__( 'Codul corespunde unui produs de la Conex, dar denumirile indică produse diferite.', 'conex-conect-sync' ),
				'randomize',
			),
			'scaderi'   => array(
				'scadere',
				__( 'Prețuri care ar coborî mult', 'conex-conect-sync' ),
				__( 'Regula de preț le-ar reduce la mai puțin de jumătate din valoarea actuală.', 'conex-conect-sync' ),
				'arrow-down-alt',
			),
			'subcost'   => array(
				'sub_cost',
				__( 'Produse vândute sub cost', 'conex-conect-sync' ),
				__( 'Prețul de vânzare este sub costul de achiziție.', 'conex-conect-sync' ),
				'warning',
			),
		);

		echo '<div class="ccs-tile-grila">';
		foreach ( $intrebari as $slug => $i ) {
			if ( empty( $rest[ $i[0] ] ) ) {
				continue;
			}
			ccs_ui_tile(
				array(
					'cifra' => ccs_nr( $rest[ $i[0] ] ),
					'titlu' => $i[1],
					'desc'  => $i[2],
					'icon'  => $i[3],
					'ton'   => 'atentie',
					'href'  => ccs_admin_url( $slug ),
					'jos'   => '<span class="ccs-tile__verb">' . esc_html__( 'Deschide', 'conex-conect-sync' ) . '</span>',
				)
			);
		}
		echo '</div>';
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
					$ultima ? ccs_data( $ultima->inceput ) : __( 'nu se știe când', 'conex-conect-sync' )
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

	// Jurnalul rulărilor stă înaintea stării, fiindcă răspunde la ce s-a întâmplat, nu la cum e configurat.
	ccs_ui_eticheta_sectiune( __( 'Istoric', 'conex-conect-sync' ) );
	ccs_ui_jurnal_rulari( 10 );

	ccs_ui_eticheta_sectiune( __( 'Starea sistemului', 'conex-conect-sync' ) );
	ccs_ui_card( __( 'Starea', 'conex-conect-sync' ) );
	echo '<ul class="ccs-stari">';
	foreach ( $stari as $st ) {
		printf(
			'<li class="ccs-stare ccs-stare--%1$s"><span class="ccs-stare__icon">%2$s</span><strong>%3$s</strong><span>%4$s</span></li>',
			esc_attr( $st[0] ),
			ccs_icon( $iconuri[ $st[0] ], 14 ),
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
			esc_html( $job['urmatoarea'] ? ccs_data( $job['urmatoarea'] ) : '-' )
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
			<?php
			// Aceeași anatomie ca o cifră din Panou: plăcuță de iconiță, etichetă, valoare. Înainte era un tabel
			// cu eticheta în stânga și cifra lipită de marginea din dreapta, cu un gol mare între ele, iar un
			// tabel de patru rânduri cu două coloane se citește greu tocmai fiindcă ochiul traversează golul.
			$efecte = array(
				array( 'ccs-e-sus', __( 'Prețuri care urcă', 'conex-conect-sync' ), 'arrow-up-alt', 'bine' ),
				array( 'ccs-e-jos', __( 'Prețuri care coboară', 'conex-conect-sync' ), 'arrow-down-alt', 'atentie' ),
				array( 'ccs-e-egal', __( 'Prețuri neschimbate', 'conex-conect-sync' ), 'minus', '' ),
				array( 'ccs-e-subcost', __( 'Rămân sub cost', 'conex-conect-sync' ), 'warning', 'problema' ),
			);
			?>
			<div class="ccs-efect-grila">
				<?php foreach ( $efecte as $e ) : ?>
					<div class="ccs-efect-item">
						<span class="ccs-plac<?php echo $e[3] ? ' ccs-plac--' . esc_attr( $e[3] ) : ''; ?>">
							<?php echo ccs_icon( $e[2], 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</span>
						<span class="ccs-efect-item__corp">
							<span class="ccs-efect-item__eticheta"><?php echo esc_html( $e[1] ); ?></span>
							<strong id="<?php echo esc_attr( $e[0] ); ?>"></strong>
						</span>
					</div>
				<?php endforeach; ?>
			</div>


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
					<p class="ccs-parte__cap"><?php echo ccs_icon( 'admin-home', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Pe site-ul tău', 'conex-conect-sync' ); ?></p>
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
					<p class="ccs-parte__cap"><?php echo ccs_icon( 'cart', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'La Conex, pe același cod', 'conex-conect-sync' ); ?></p>
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
	// Grupate pe gravitate: șaizeci de rânduri devin trei decizii, fără să dispară niciun rând.
	ccs_ui_tabel_decizii(
		$randuri,
		$decizii,
		'sub_cost',
		$optiuni,
		array(
			'grupuri' => ccs_ui_grupeaza_pe_pierdere( $randuri ),
			'total'   => true,
		)
	);
	ccs_ui_card_sfarsit();
}

/**
 * Grupează rândurile după cât de tare doare fiecare.
 *
 * Șaizeci de rânduri sortate după pierdere sunt tot șaizeci de decizii. Aceleași șaizeci, împărțite în „peste 100
 * lei”, „între 10 și 100” și „sub 10”, sunt trei: omul se uită la primul grup cu atenție și le trece pe celelalte
 * în bloc. Rândurile rămân toate vizibile, deci nu se ascunde nimic.
 *
 * @param array $randuri Rândurile de plan, sortate deja după pierdere.
 * @return array Listă de array( eticheta, randuri ).
 */
function ccs_ui_grupeaza_pe_pierdere( $randuri ) {
	$praguri = array(
		array( 100.0, __( 'Pierdere de peste 100 lei la bucată', 'conex-conect-sync' ) ),
		array( 10.0, __( 'Pierdere între 10 și 100 lei', 'conex-conect-sync' ) ),
		array( 0.0, __( 'Pierdere sub 10 lei', 'conex-conect-sync' ) ),
	);

	$grupuri = array();
	foreach ( $praguri as $p ) {
		$grupuri[] = array(
			'eticheta' => $p[1],
			'randuri'  => array(),
		);
	}

	foreach ( $randuri as $r ) {
		$pierdere = (float) $r['cost_cu_tva'] - (float) $r['pret_site'];
		foreach ( $praguri as $i => $p ) {
			if ( $pierdere >= $p[0] ) {
				$grupuri[ $i ]['randuri'][] = $r;
				break;
			}
		}
	}

	// Un grup gol n-are ce căuta pe ecran.
	return array_values(
		array_filter(
			$grupuri,
			static function ( $g ) {
				return (bool) $g['randuri'];
			}
		)
	);
}

/**
 * Miniatura unui produs, pentru un rând de tabel.
 *
 * Un patron de magazin de piese recunoaște produsul din poză, nu din `61497`. Fără asta, fiecare rând cere un
 * clic pe „vezi” într-un tab nou, iar la șaizeci de rânduri nimeni nu face asta.
 *
 * @param int $wc_id Produsul.
 * @return string HTML.
 */
function ccs_ui_miniatura( $wc_id ) {
	$id_poza = get_post_thumbnail_id( $wc_id );
	if ( ! $id_poza ) {
		return '<span class="ccs-mini ccs-mini--goala" aria-hidden="true"></span>';
	}
	$src = wp_get_attachment_image_url( $id_poza, 'thumbnail' );
	if ( ! $src ) {
		return '<span class="ccs-mini ccs-mini--goala" aria-hidden="true"></span>';
	}
	return sprintf( '<span class="ccs-mini"><img src="%s" alt="" loading="lazy"></span>', esc_url( $src ) );
}

/**
 * Încarcă dintr-o dată tot ce are nevoie o listă de rânduri.
 *
 * Fără asta, fiecare miniatură adaugă două interogări pe rând. La șaizeci de rânduri, diferența e între două
 * interogări și o sută douăzeci.
 *
 * @param array $randuri Rândurile.
 * @return void
 */
function ccs_ui_incalzeste( $randuri ) {
	$ids = array_filter( wp_list_pluck( $randuri, 'wc_id' ) );
	if ( ! $ids ) {
		return;
	}
	_prime_post_caches( $ids, false, true );

	$poze = array_filter( array_map( 'get_post_thumbnail_id', $ids ) );
	if ( $poze ) {
		_prime_post_caches( $poze, false, true );
	}
}

/**
 * Un tabel de produse cu butoane de decizie pe fiecare rând.
 *
 * @param array  $randuri Rândurile de plan.
 * @param array  $decizii Deciziile luate.
 * @param string $tip     Tipul deciziei.
 * @param array  $opt     Opțiunile de decizie.
 * @param array  $setari  grupuri: listă de array( eticheta, randuri ); total: arată rândul de totaluri.
 * @return void
 */
function ccs_ui_tabel_decizii( $randuri, $decizii, $tip, $opt, $setari = array() ) {
	$setari = wp_parse_args(
		$setari,
		array(
			'grupuri' => null,
			'total'   => false,
		)
	);

	ccs_ui_incalzeste( $randuri );

	$grupuri = $setari['grupuri'];
	if ( null === $grupuri ) {
		$grupuri = array( array( 'eticheta' => '', 'randuri' => $randuri ) );
	}

	$total_pierdere = 0.0;
	$total_marja    = 0.0;
	foreach ( $randuri as $r ) {
		$marja           = (float) $r['pret_tinta'] - (float) $r['cost_cu_tva'];
		$total_marja    += $marja;
		$total_pierdere += max( 0.0, (float) $r['cost_cu_tva'] - (float) $r['pret_site'] );
	}
	?>
	<div class="ccs-tabel-cadru">
		<table class="ccs-tabel ccs-tabel--decizii">
			<thead>
				<tr>
					<th colspan="2"><?php esc_html_e( 'Produs', 'conex-conect-sync' ); ?></th>
					<th class="num"><?php esc_html_e( 'Cost cu TVA', 'conex-conect-sync' ); ?></th>
					<th class="num"><?php esc_html_e( 'Preț, acum și propus', 'conex-conect-sync' ); ?></th>
					<th class="num"><?php esc_html_e( 'Marjă nouă', 'conex-conect-sync' ); ?></th>
					<th><?php esc_html_e( 'Ce facem', 'conex-conect-sync' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $grupuri as $g ) : ?>
				<?php if ( '' !== $g['eticheta'] ) : ?>
					<tr class="ccs-rand-grup">
						<th colspan="6" scope="colgroup">
							<?php echo esc_html( $g['eticheta'] ); ?>
							<?php
							echo wp_kses_post(
								ccs_ui_pastila(
									sprintf(
										/* translators: %s: câte produse. */
										_n( '%s produs', '%s produse', count( $g['randuri'] ), 'conex-conect-sync' ),
										ccs_nr( count( $g['randuri'] ) )
									),
									'neutru'
								)
							);
							?>
						</th>
					</tr>
				<?php endif; ?>
				<?php foreach ( $g['randuri'] as $r ) : ?>
					<?php
					$luata = $decizii[ (string) $r['wc_id'] ] ?? null;
					$marja = (float) $r['pret_tinta'] - (float) $r['cost_cu_tva'];
					?>
					<tr data-tip="<?php echo esc_attr( $tip ); ?>" data-referinta="<?php echo esc_attr( $r['wc_id'] ); ?>" class="<?php echo $luata ? 'ccs-rezolvat' : ''; ?>">
						<td class="ccs-celula-mini"><?php echo wp_kses_post( ccs_ui_miniatura( $r['wc_id'] ) ); ?></td>
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
						<td class="num">
							<?php
							echo wp_kses_post(
								ccs_ui_pastila( ccs_lei( $marja ) . ' lei', $marja < 0 ? 'problema' : 'bine' )
							);
							?>
						</td>
						<td><?php ccs_ui_alegeri( $opt, $luata ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endforeach; ?>
			</tbody>
			<?php if ( $setari['total'] ) : ?>
				<tfoot>
					<tr>
						<td colspan="4"><?php esc_html_e( 'Total', 'conex-conect-sync' ); ?></td>
						<td class="num">
							<?php
							echo wp_kses_post(
								ccs_ui_pastila( ccs_lei( $total_marja ) . ' lei', $total_marja < 0 ? 'problema' : 'bine' )
							);
							?>
						</td>
						<td class="ccs-tabel__slab">
							<?php
							printf(
								/* translators: %s: pierderea totală în lei. */
								esc_html__( 'acum se pierd %s lei la câte o bucată din fiecare', 'conex-conect-sync' ),
								esc_html( ccs_lei( $total_pierdere ) )
							);
							?>
						</td>
					</tr>
				</tfoot>
			<?php endif; ?>
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

	// Ordinea e după ce poate face omul, nu după cât de interesant e. Distribuția celor douăzeci de mii de
	// produse stătea înaintea celor 96 de căi nemapate, adică informația pe care n-o poate folosi stătea
	// înaintea singurului loc unde o alegere mută trei sute de produse.
	ccs_ui_eticheta_sectiune( __( 'Ce poți face acum', 'conex-conect-sync' ) );
	ccs_ui_catalog_actiuni( $c, $grupuri, $m );

	ccs_ui_eticheta_sectiune( __( 'Categoriile de asociat', 'conex-conect-sync' ) );
	ccs_ui_catalog_mapare();
	ccs_ui_catalog_de_mapat( $c );

	ccs_ui_eticheta_sectiune( __( 'Produsele furnizorului', 'conex-conect-sync' ) );
	// Înlocuiește previzualizarea fixă a primelor produse: `ccs_ui_catalog_produse_noi()` rămâne scrisă, dar nu
	// se mai cheamă de nicăieri, fiindcă răsfoirea arată același lucru și lasă omul să aleagă.
	ccs_ui_catalog_rasfoire();

	ccs_ui_eticheta_sectiune( __( 'Starea celor două cataloage', 'conex-conect-sync' ) );
	ccs_ui_catalog_bare( $c, $grupuri, $pot, $m );
	ccs_ui_catalog_miscare( $m );
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

	echo '<div class="ccs-tile-grila">';

	// Zero nu spune nimic de unul singur. Pâlnia știe de ce, deci o întrebăm: aproape întotdeauna lipsesc pozele,
	// care se iau doar noaptea, iar omul care vede „0” fără explicație crede că ceva s-a stricat.
	if ( $c['gata'] ) {
		$de_ce = __( 'Au imagine, cost de achiziție, stoc și o categorie asociată.', 'conex-conect-sync' );
	} elseif ( $c['fara_poza'] >= max( 1, $c['nelegate'] ) * 0.9 ) {
		$de_ce = __( 'Niciun produs nu are încă poze. Pozele se iau de la furnizor noaptea, între 19:00 și 05:00, fiindcă doar atunci le dă. Până atunci nu se poate importa nimic.', 'conex-conect-sync' );
	} elseif ( $c['fara_categorie'] ) {
		$de_ce = __( 'Produsele au poze, cost și stoc, dar le lipsește categoria din magazin. Asociaz-o mai jos.', 'conex-conect-sync' );
	} else {
		$de_ce = __( 'Niciun produs nu îndeplinește deocamdată toate condițiile.', 'conex-conect-sync' );
	}

	ccs_ui_tile(
		array(
			'cifra' => ccs_nr( $c['gata'] ),
			'titlu' => __( 'produse pot fi importate', 'conex-conect-sync' ),
			'desc'  => $de_ce,
			'icon'  => 'download',
			'ton'   => $c['gata'] ? 'bine' : '',
		)
	);

	if ( $de_retras ) {
		ccs_ui_tile(
			array(
				'cifra'    => ccs_nr( $de_retras ),
				'titlu'    => __( 'produse indisponibile la furnizor', 'conex-conect-sync' ),
				'desc'     => __( 'Sunt disponibile pe site, dar nu mai pot fi comandate. Trec pe stoc epuizat, fără să fie șterse.', 'conex-conect-sync' ),
				'icon'     => 'archive',
				'ton'      => 'atentie',
				'atribute' => 'data-grup="din_feed"',
				'jos'      => sprintf(
					'<button type="button" class="ccs-btn ccs-btn--mic ccs-retrage" data-grup="din_feed" data-eticheta="%1$s">%1$s</button> <span class="ccs-stare-text"></span>',
					esc_attr__( 'Retrage de la vânzare', 'conex-conect-sync' )
				),
			)
		);
	}

	if ( $c['fara_categorie'] ) {
		ccs_ui_tile(
			array(
				'cifra' => ccs_nr( $c['fara_categorie'] ),
				'titlu' => __( 'produse așteaptă încadrarea pe categorie', 'conex-conect-sync' ),
				'desc'  => __( 'Au imagine, cost și stoc. Singurul element care lipsește este categoria din magazin.', 'conex-conect-sync' ),
				'icon'  => 'category',
				'ton'   => 'info',
			)
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
			array( 'atentie', __( 'rămase din feed-ul vechi', 'conex-conect-sync' ), $din_feed ),
			array( 'albastru', __( 'introduse de echipă', 'conex-conect-sync' ), $manuale ),
			array( 'gri', __( 'altele', 'conex-conect-sync' ), $altele ),
		)
	);

	ccs_ui_bara(
		__( 'În catalogul Conex', 'conex-conect-sync' ),
		array(
			array( 'verde', __( 'deja pe site', 'conex-conect-sync' ), $legate ),
			array( 'verde-moale', __( 'pot fi importate', 'conex-conect-sync' ), $c['gata'] ),
			array( 'atentie', __( 'fără categorie asociată', 'conex-conect-sync' ), $c['fara_categorie'] ),
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
				ccs_data( $m['ultima_rulare'] )
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
 * Răsfoirea catalogului furnizorului, cu filtre și import pe selecție.
 *
 * Asta e Decizia 3 din ofertă: „te uiți peste catalogul Conex din panou, filtrat după categorie, brand și stoc,
 * și bifezi”. Forma e luată din AliNext, singurul plugin dintre cele comparate care rezolvă aceeași problemă:
 * carduri cu poză, fiindcă un produs fără poză nu se recunoaște, plus o bară de selecție care înlocuiește
 * filtrele când ai bifat ceva.
 *
 * Produsele intră ca ciorne, deci lista de import a lui AliNext nu ne trebuie ca ecran separat: ciornele din
 * WooCommerce sunt deja zona intermediară în care omul verifică înainte să publice.
 *
 * @return void
 */
function ccs_ui_catalog_rasfoire() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- filtre de citire, fără efect.
	$cautare = isset( $_GET['ccs_caut'] ) ? sanitize_text_field( wp_unslash( $_GET['ccs_caut'] ) ) : '';
	$brand   = isset( $_GET['ccs_brand'] ) ? sanitize_text_field( wp_unslash( $_GET['ccs_brand'] ) ) : '';
	$cale    = isset( $_GET['ccs_cale'] ) ? sanitize_text_field( wp_unslash( $_GET['ccs_cale'] ) ) : '';
	$pagina  = isset( $_GET['ccs_pag'] ) ? max( 1, (int) $_GET['ccs_pag'] ) : 1;
	$toate   = isset( $_GET['ccs_toate'] );
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	$r = ccs_catalog_rasfoire(
		array(
			'cautare'          => $cautare,
			'brand'            => $brand,
			'cale'             => $cale,
			'doar_importabile' => ! $toate,
			'pagina'           => $pagina,
		)
	);

	// Filtrul din interogare verifică poza, costul și stocul. Categoria se calculează în PHP, deci nu se poate
	// pune în SQL; de aceea cifra din pastilă e mai mare decât câte se pot aduce chiar acum, iar subtitlul o
	// spune pe șleau în loc să lase omul să descopere singur că jumătate din carduri nu se pot bifa.
	$gata = ccs_admin_candidati();

	// Mesajul se schimbă după situația reală. „0 au poză, cost și stoc; dintre ele 0 au și o categorie” e
	// adevărat, dar nu spune nimic omului care se uită la o listă goală și se întreabă dacă s-a stricat ceva.
	if ( 0 === (int) $gata['nelegate'] ) {
		$povestea = __( 'Toate produsele din catalogul Conex sunt deja pe site. Nu mai e nimic de adus.', 'conex-conect-sync' );
	} elseif ( 0 === (int) $r['total'] && $gata['fara_poza'] >= max( 1, $gata['nelegate'] ) * 0.9 ) {
		$povestea = sprintf(
			/* translators: %s: câte produse are furnizorul și nu sunt pe site. */
			__( 'Conex are %s produse pe care magazinul nu le are, dar încă nu avem nicio poză pentru ele, iar un produs fără poză nu se poate aduce pe site. Pozele se iau automat de la furnizor în fiecare noapte, între 19:00 și 05:00, fiindcă doar atunci le pune la dispoziție. După prima preluare, produsele apar aici.', 'conex-conect-sync' ),
			ccs_nr( $gata['nelegate'] )
		);
	} elseif ( 0 === (int) ( $gata['gata'] ?? 0 ) ) {
		$povestea = sprintf(
			/* translators: %s: câte au poză, cost și stoc. */
			__( '%s produse au poză, cost și stoc, dar niciunul nu are încă o categorie din magazin, iar fără ea nu știm unde să îl punem. Asociază categoriile mai sus, apoi revino aici.', 'conex-conect-sync' ),
			ccs_nr( $r['total'] )
		);
	} else {
		$povestea = sprintf(
			/* translators: 1: câte au poză, cost și stoc, 2: câte se pot aduce acum. */
			__( 'Produsele pe care Conex le are și magazinul nu. %1$s au poză, cost și stoc; dintre ele %2$s au și o categorie asociată, deci se pot aduce acum. Restul așteaptă o asociere de categorie, mai sus.', 'conex-conect-sync' ),
			ccs_nr( $r['total'] ),
			ccs_nr( $gata['gata'] ?? 0 )
		);
	}

	// Explicația nu mai e subtitlu gri, ci o notă cu iconiță și culoare: când e singurul lucru de citit dintr-un
	// card gol, trebuie să arate ca o informație, nu ca o notă de subsol.
	$nota = $povestea;
	$povestea = '';

	ccs_ui_card(
		__( 'Catalogul furnizorului', 'conex-conect-sync' ),
		$povestea,
		ccs_ui_pastila(
			sprintf(
				/* translators: %s: câte produse se pot aduce. */
				__( '%s se pot aduce', 'conex-conect-sync' ),
				ccs_nr( $gata['gata'] ?? 0 )
			),
			( $gata['gata'] ?? 0 ) ? 'info' : 'neutru'
		)
	);

	printf(
		'<div class="ccs-nota ccs-nota--%1$s"><span class="ccs-nota__icon">%2$s</span><span>%3$s</span></div>',
		( $gata['gata'] ?? 0 ) ? 'bine' : 'info',
		ccs_icon( ( $gata['gata'] ?? 0 ) ? 'yes-alt' : 'info-outline', 18 ),
		esc_html( $nota )
	);

	ccs_ui_catalog_filtre_bara( $cautare, $brand, $cale, $toate );

	if ( ! $r['randuri'] ) {
		$filtrat = '' !== $cautare || '' !== $brand || '' !== $cale;
		ccs_ui_gol(
			$filtrat
				? __( 'Niciun produs nu se potrivește cu filtrele.', 'conex-conect-sync' )
				: __( 'Nu e nimic de adus deocamdată.', 'conex-conect-sync' ),
			$filtrat
				? __( 'Încearcă fără filtre, sau bifează „arată și produsele incomplete”.', 'conex-conect-sync' )
				: __( 'Vezi explicația de mai sus: nu e o eroare, doar nu sunt încă produse gata de adus.', 'conex-conect-sync' )
		);
		ccs_ui_card_sfarsit();
		return;
	}

	echo '<div class="ccs-grila-produse">';
	foreach ( $r['randuri'] as $p ) {
		ccs_ui_card_produs( $p );
	}
	echo '</div>';

	ccs_ui_paginare( $r['pagina'], $r['pagini'] );
	ccs_ui_card_sfarsit();
}

/**
 * Bara de filtre, plus bara de selecție care o înlocuiește când ai bifat ceva.
 *
 * Filtrele trec prin URL, nu prin JavaScript: așa butonul Înapoi funcționează, iar o căutare se poate pune la
 * favorite sau trimite cuiva.
 *
 * @param string $cautare Căutarea curentă.
 * @param string $brand   Brandul ales.
 * @param string $cale    Calea aleasă.
 * @param bool   $toate   Dacă se arată și produsele incomplete.
 * @return void
 */
function ccs_ui_catalog_filtre_bara( $cautare, $brand, $cale, $toate ) {
	$filtre = ccs_catalog_filtre();
	?>
	<div class="ccs-filtre" data-zona-filtre>
		<form method="get" class="ccs-filtre__form">
			<input type="hidden" name="page" value="ccs-decizii">
			<input type="hidden" name="tab" value="catalog">

			<input type="search" class="ccs-intrare ccs-filtre__cauta" name="ccs_caut"
				value="<?php echo esc_attr( $cautare ); ?>"
				placeholder="<?php esc_attr_e( 'Caută după denumire sau cod', 'conex-conect-sync' ); ?>">

			<select class="ccs-intrare" name="ccs_brand">
				<option value=""><?php esc_html_e( 'Toate brandurile', 'conex-conect-sync' ); ?></option>
				<?php foreach ( $filtre['branduri'] as $b ) : ?>
					<option value="<?php echo esc_attr( $b->nume ); ?>" <?php selected( $brand, $b->nume ); ?>>
						<?php echo esc_html( $b->nume . ' (' . ccs_nr( $b->n ) . ')' ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<select class="ccs-intrare" name="ccs_cale">
				<option value=""><?php esc_html_e( 'Toate categoriile furnizorului', 'conex-conect-sync' ); ?></option>
				<?php foreach ( $filtre['cai'] as $c ) : ?>
					<option value="<?php echo esc_attr( $c->nume ); ?>" <?php selected( $cale, $c->nume ); ?>>
						<?php echo esc_html( mb_strimwidth( $c->nume, 0, 58, '...' ) . ' (' . ccs_nr( $c->n ) . ')' ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<label class="ccs-filtre__bifa">
				<input type="checkbox" name="ccs_toate" value="1" <?php checked( $toate ); ?>>
				<?php esc_html_e( 'arată și produsele incomplete', 'conex-conect-sync' ); ?>
			</label>

			<button type="submit" class="ccs-btn"><?php esc_html_e( 'Filtrează', 'conex-conect-sync' ); ?></button>
		</form>
	</div>

	<div class="ccs-selectie" data-zona-selectie hidden>
		<span class="ccs-selectie__cate"></span>
		<button type="button" class="ccs-btn ccs-btn--principal ccs-importa-selectate">
			<?php esc_html_e( 'Adu pe site ca ciorne', 'conex-conect-sync' ); ?>
		</button>
		<button type="button" class="ccs-btn ccs-sterge-selectia"><?php esc_html_e( 'Renunță', 'conex-conect-sync' ); ?></button>
		<span class="ccs-stare-text"></span>
	</div>
	<?php
}

/**
 * Un card de produs din catalogul furnizorului.
 *
 * @param object $p Produsul.
 * @return void
 */
function ccs_ui_card_produs( $p ) {
	$poze       = ccs_imagini_produs( (int) $p->product_id, 1 );
	$pret       = ccs_pret(
		(float) $p->cost,
		(float) $p->taxa_eco,
		array(
			'brand' => $p->brand,
			'cat1'  => $p->cat1,
			'cat2'  => $p->cat2,
			'cat3'  => $p->cat3,
		)
	);
	$categorie  = ccs_categorie_pentru( (array) $p );
	$se_importa = $poze && (float) $p->cost > 0 && (int) $p->stoc > 0 && $categorie;
	?>
	<label class="ccs-produs-card<?php echo $se_importa ? '' : ' ccs-produs-card--blocat'; ?>">
		<input type="checkbox" class="ccs-bifa-produs" value="<?php echo esc_attr( $p->cod ); ?>"
			<?php disabled( ! $se_importa ); ?>>

		<span class="ccs-produs-card__poza">
			<?php if ( $poze ) : ?>
				<img src="<?php echo esc_url( $poze[0] ); ?>" alt="" loading="lazy">
			<?php endif; ?>
		</span>

		<span class="ccs-produs-card__nume"><?php echo esc_html( $p->nume ); ?></span>
		<span class="ccs-produs-card__cod"><?php echo esc_html( $p->cod ); ?><?php echo $p->brand ? ' · ' . esc_html( $p->brand ) : ''; ?></span>

		<span class="ccs-produs-card__jos">
			<span class="ccs-produs-card__pret"><?php echo esc_html( ccs_lei( $pret ) ); ?> lei</span>
			<?php
			if ( $se_importa ) {
				echo wp_kses_post(
					ccs_ui_pastila(
						sprintf(
							/* translators: %d: stocul la furnizor. */
							__( 'stoc %d', 'conex-conect-sync' ),
							(int) $p->stoc
						),
						'bine'
					)
				);
			} else {
				echo wp_kses_post( ccs_ui_pastila( ccs_ui_de_ce_blocat( $p, $poze, $categorie ), 'atentie' ) );
			}
			?>
		</span>
	</label>
	<?php
}

/**
 * De ce nu se poate importa un produs, în două cuvinte.
 *
 * @param object $p         Produsul.
 * @param array  $poze      Pozele lui.
 * @param int    $categorie Categoria găsită.
 * @return string Motivul.
 */
function ccs_ui_de_ce_blocat( $p, $poze, $categorie ) {
	if ( ! $poze ) {
		return __( 'fără poză', 'conex-conect-sync' );
	}
	if ( (float) $p->cost <= 0 ) {
		return __( 'fără cost', 'conex-conect-sync' );
	}
	if ( (int) $p->stoc <= 0 ) {
		return __( 'fără stoc', 'conex-conect-sync' );
	}
	if ( ! $categorie ) {
		return __( 'fără categorie', 'conex-conect-sync' );
	}
	return __( 'nu se poate', 'conex-conect-sync' );
}

/**
 * Paginarea, cu starea păstrată în URL.
 *
 * @param int $pagina Pagina curentă.
 * @param int $pagini Câte pagini sunt.
 * @return void
 */
function ccs_ui_paginare( $pagina, $pagini ) {
	if ( $pagini < 2 ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$acum = $_GET;
	unset( $acum['ccs_pag'] );

	$leg = function ( $p ) use ( $acum ) {
		$acum['ccs_pag'] = $p;
		return admin_url( 'admin.php?' . http_build_query( $acum ) );
	};
	?>
	<nav class="ccs-paginare">
		<?php if ( $pagina > 1 ) : ?>
			<a class="ccs-btn" href="<?php echo esc_url( $leg( $pagina - 1 ) ); ?>"><?php esc_html_e( 'Înapoi', 'conex-conect-sync' ); ?></a>
		<?php endif; ?>
		<span class="ccs-paginare__unde">
			<?php
			printf(
				/* translators: 1: pagina curentă, 2: câte pagini. */
				esc_html__( 'pagina %1$s din %2$s', 'conex-conect-sync' ),
				esc_html( ccs_nr( $pagina ) ),
				esc_html( ccs_nr( $pagini ) )
			);
			?>
		</span>
		<?php if ( $pagina < $pagini ) : ?>
			<a class="ccs-btn" href="<?php echo esc_url( $leg( $pagina + 1 ) ); ?>"><?php esc_html_e( 'Înainte', 'conex-conect-sync' ); ?></a>
		<?php endif; ?>
	</nav>
	<?php
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
	// Fiecare grup: iconiță, ton, explicația de unde vine, și dacă se poate retrage.
	$etichete = array(
		'din_feed' => array(
			'titlu'   => __( 'Rămase din feed-ul vechi', 'conex-conect-sync' ),
			'desc'    => __( 'Provin din importul Mega Drive, oprit în noiembrie 2025. Nu mai există în catalogul Conex.', 'conex-conect-sync' ),
			'icon'    => 'archive',
			'ton'     => 'atentie',
			'retrage' => true,
		),
		'excluse'  => array(
			'titlu'   => __( 'Scoase din potrivire', 'conex-conect-sync' ),
			'desc'    => __( 'Produse marcate ca fiind diferite de cele pe care Conex le are pe același cod.', 'conex-conect-sync' ),
			'icon'    => 'hidden',
			'ton'     => '',
			'retrage' => true,
		),
		'altele'   => array(
			'titlu'   => __( 'Alt tipar de cod', 'conex-conect-sync' ),
			'desc'    => __( 'Codul nu corespunde niciunui tipar cunoscut. Necesită o verificare înainte de orice operațiune.', 'conex-conect-sync' ),
			'icon'    => 'editor-help',
			'ton'     => '',
			'retrage' => true,
		),
		'manuale'  => array(
			'titlu'   => __( 'Introduse manual de echipă', 'conex-conect-sync' ),
			'desc'    => __( 'Codurile încep cu două litere. Aparțin magazinului, nu furnizorului, și nu sunt modificate de sincronizare.', 'conex-conect-sync' ),
			'icon'    => 'admin-users',
			'ton'     => 'info',
			'retrage' => false,
		),
	);

	ccs_ui_card(
		__( 'Produse pe care furnizorul nu le mai are', 'conex-conect-sync' ),
		__( 'Produsele nu se șterg. Trec pe stoc epuizat și rămân pe site, cu pagina și poziția lor în Google.', 'conex-conect-sync' )
	);

	echo '<div class="ccs-tile-grila ccs-tile-grila--larg">';

	foreach ( $etichete as $grup => $e ) {
		$ids = isset( $grupuri[ $grup ] ) ? array_keys( $grupuri[ $grup ] ) : array();
		if ( ! $ids ) {
			continue;
		}
		$total    = count( $ids );
		$de_atins = ccs_retragere_de_atins( $ids );
		$retrase  = $total - $de_atins;

		$jos = '';

		if ( $e['retrage'] ) {
			$jos .= sprintf(
				'<span class="ccs-tile__progres"><span class="ccs-tile__pista"><span style="width:%1$s%%"></span></span><span class="ccs-tile__stare">%2$s</span></span>',
				esc_attr( $total ? (int) round( 100 * $retrase / $total ) : 100 ),
				esc_html(
					sprintf(
						/* translators: 1: câte sunt retrase, 2: câte se mai vând. */
						__( '%1$s retrase, %2$s încă de vânzare', 'conex-conect-sync' ),
						ccs_nr( $retrase ),
						ccs_nr( $de_atins )
					)
				)
			);
		}

		$butoane = '';
		if ( $e['retrage'] && $de_atins ) {
			$eticheta = sprintf(
				/* translators: %s: câte produse. */
				__( 'Retrage %s de la vânzare', 'conex-conect-sync' ),
				ccs_nr( $de_atins )
			);
			$butoane .= sprintf(
				'<button type="button" class="ccs-btn ccs-btn--mic ccs-retrage" data-ton="atentie" data-grup="%1$s" data-eticheta="%2$s">%2$s</button>',
				esc_attr( $grup ),
				esc_attr( $eticheta )
			);
		} elseif ( $e['retrage'] ) {
			$butoane .= ccs_ui_pastila( __( 'toate retrase', 'conex-conect-sync' ), 'bine' );
		}
		$butoane .= sprintf(
			'<a class="ccs-btn ccs-btn--mic ccs-btn--fantoma" href="%s">%s</a>',
			esc_url( admin_url( 'edit.php?post_type=product&ccs_grup=' . $grup ) ),
			esc_html__( 'Vezi lista', 'conex-conect-sync' )
		);
		$butoane .= '<span class="ccs-stare-text"></span>';

		$jos .= '<span class="ccs-tile__butoane">' . $butoane . '</span>';

		ccs_ui_tile(
			array(
				'cifra'    => ccs_nr( $total ),
				'titlu'    => $e['titlu'],
				'desc'     => $e['desc'],
				'icon'     => $e['icon'],
				'ton'      => $e['ton'],
				'atribute' => 'data-grup="' . esc_attr( $grup ) . '"',
				'jos'      => $jos,
			)
		);
	}

	echo '</div>';
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
		ccs_ui_cifra(
			__( 'Cantități de schimbat', 'conex-conect-sync' ),
			ccs_nr( $r['stoc'] ),
			$r['stoc'] ? 'atentie' : 'bine',
			array(
				'icon' => 'archive',
				'sub'  => $setari['mod_raport']
					? __( 'doar propuse', 'conex-conect-sync' )
					: __( 'se aplică automat', 'conex-conect-sync' ),
			)
		);
		ccs_ui_cifra(
			__( 'Ar trece pe epuizat', 'conex-conect-sync' ),
			ccs_nr( $r['de_epuizat'] ),
			$r['de_epuizat'] ? 'atentie' : 'bine',
			array(
				'icon' => 'hidden',
				'sub'  => __( 'ies din vânzare', 'conex-conect-sync' ),
			)
		);
		ccs_ui_cifra(
			__( 'S-ar reactiva', 'conex-conect-sync' ),
			ccs_nr( $r['de_reactivat'] ),
			$r['de_reactivat'] ? 'bine' : '',
			array(
				'icon' => 'visibility',
				'sub'  => __( 'revin la vânzare', 'conex-conect-sync' ),
			)
		);
		ccs_ui_cifra(
			__( 'Fără nicio schimbare', 'conex-conect-sync' ),
			ccs_nr( $r['neatinse'] ),
			'',
			array(
				'icon' => 'yes-alt',
				'sub'  => __( 'rămân cum sunt', 'conex-conect-sync' ),
			)
		);
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
			'<li class="ccs-stare ccs-stare--%1$s"><span class="ccs-stare__icon">%2$s</span><strong>%3$s</strong><span>%4$s</span></li>',
			esc_attr( $st[0] ),
			ccs_icon( $iconuri[ $st[0] ], 14 ),
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
