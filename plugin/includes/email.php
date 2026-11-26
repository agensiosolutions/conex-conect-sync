<?php
/**
 * Rezumatul zilnic și alertele.
 *
 * Fișierul ăsta există din cauza unui lucru concret: feed-ul Mega Drive s-a oprit în noiembrie 2025 și nimeni
 * n-a aflat până în octombrie 2026. Importul rula, raporta „zero produse modificate”, și nimănui nu i s-a părut
 * ciudat, fiindcă nimeni nu primea nimic. Unsprezece luni de prețuri și stocuri vechi.
 *
 * Deci aici nu e vorba de „ar fi frumos să primim un email”. E singurul mecanism care face diferența între o
 * sincronizare care s-a oprit și una care merge, pentru cineva care nu se uită în panou.
 *
 * ## De ce două căi, nu una
 *
 * Verificarea de sănătate rulează și ca job programat, și la intrarea în wp-admin. Pare redundant, dar nu e: un
 * job programat nu poate raporta că joburile programate nu mai rulează. Dacă Action Scheduler se oprește, sau
 * cronul WordPress nu mai e lovit de nimeni, singura cale care rămâne e un om care deschide adminul. Verificarea
 * din admin e păzită de un transient, deci costă o citire de opțiune pe oră, nu una pe cerere.
 *
 * @package conex-conect-sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adresa pe care pleacă emailurile.
 *
 * @return string Adresa.
 */
function ccs_email_catre() {
	$setari = ccs_setari();
	$catre  = trim( (string) $setari['email_catre'] );
	if ( '' === $catre || ! is_email( $catre ) ) {
		$catre = (string) get_option( 'admin_email' );
	}

	/**
	 * Permite schimbarea destinatarului.
	 *
	 * @param string $catre Adresa.
	 */
	return apply_filters( 'ccs_email_catre', $catre );
}

/**
 * Trimite un email, cu subiectul prefixat cu numele magazinului.
 *
 * @param string $subiect Subiectul.
 * @param string $mesaj   Textul.
 * @return bool Dacă a plecat.
 */
function ccs_email_trimite( $subiect, $mesaj ) {
	$catre = ccs_email_catre();
	if ( '' === $catre ) {
		ccs_jurnal( 'avertisment', 'Nu am unde trimite emailul: nicio adresă configurată.' );
		return false;
	}

	$magazin = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
	$plecat  = wp_mail(
		$catre,
		sprintf( '[%s] %s', $magazin, $subiect ),
		$mesaj . "\n\n" . ccs_email_subsol(),
		array( 'Content-Type: text/plain; charset=UTF-8' )
	);

	if ( ! $plecat ) {
		ccs_jurnal( 'avertisment', sprintf( 'Emailul „%s” nu a putut fi trimis.', $subiect ) );
	}

	return (bool) $plecat;
}

/**
 * Subsolul fiecărui email: de unde vine și unde se oprește.
 *
 * @return string Textul.
 */
function ccs_email_subsol() {
	return sprintf(
		"--\n%s\n%s",
		__( 'Mesaj trimis automat de sincronizarea cu furnizorul.', 'conex-conect-sync' ),
		admin_url( 'admin.php?page=ccs-decizii' )
	);
}

/**
 * Trimite o alertă, dar nu mai des decât o dată la câteva ore pentru aceeași problemă.
 *
 * Fără pauza asta, o sincronizare oprită ar trimite un email la fiecare verificare, iar omul ar pune regula de
 * filtrare care îi ascunde exact mesajele pe care trebuie să le vadă.
 *
 * @param string $cheie   Identifică problema, nu mesajul.
 * @param string $subiect Subiectul.
 * @param string $mesaj   Textul.
 * @return bool Dacă a plecat acum.
 */
function ccs_alerta( $cheie, $subiect, $mesaj ) {
	$setari = ccs_setari();
	if ( ! $setari['email_alerte'] ) {
		return false;
	}

	$trimise = (array) get_option( 'ccs_alerte_trimise', array() );
	$pauza   = max( 1, (int) $setari['alerta_pauza_ore'] ) * HOUR_IN_SECONDS;
	$acum    = time();

	if ( isset( $trimise[ $cheie ] ) && ( $acum - (int) $trimise[ $cheie ] ) < $pauza ) {
		return false;
	}

	$plecat = ccs_email_trimite( $subiect, $mesaj );
	if ( $plecat ) {
		$trimise[ $cheie ] = $acum;
		update_option( 'ccs_alerte_trimise', $trimise, false );
		ccs_jurnal( 'avertisment', sprintf( 'Alertă trimisă: %s', $cheie ) );
	}

	return $plecat;
}

/**
 * Uită că o alertă a fost trimisă, ca ea să poată pleca din nou când problema reapare.
 *
 * @param string $cheie Cheia.
 * @return void
 */
function ccs_alerta_rezolvata( $cheie ) {
	$trimise = (array) get_option( 'ccs_alerte_trimise', array() );
	if ( isset( $trimise[ $cheie ] ) ) {
		unset( $trimise[ $cheie ] );
		update_option( 'ccs_alerte_trimise', $trimise, false );
	}
}

/**
 * Ține minte de ce a căzut ultima rulare.
 *
 * @param WP_Error $eroare Eroarea.
 * @return void
 */
function ccs_eroare_retine( $eroare ) {
	if ( ! is_wp_error( $eroare ) ) {
		return;
	}
	update_option(
		'ccs_ultima_eroare',
		array(
			'cod'   => $eroare->get_error_code(),
			'mesaj' => $eroare->get_error_message(),
			'cand'  => current_time( 'mysql' ),
		),
		false
	);
}

/**
 * Uită ultima eroare, după o rulare reușită.
 *
 * @return void
 */
function ccs_eroare_uita() {
	if ( get_option( 'ccs_ultima_eroare', false ) ) {
		delete_option( 'ccs_ultima_eroare' );
	}
}

/**
 * Când a reușit ultima dată o preluare de catalog.
 *
 * @return string|null Data, sau null dacă n-a reușit niciodată.
 */
function ccs_ultima_sincronizare() {
	global $wpdb;
	$tabel = ccs_tabel( 'runs' );
	$data  = $wpdb->get_var(
		// phpcs:ignore WordPress.DB.PreparedSQL
		"SELECT sfarsit FROM $tabel WHERE tip = 'catalog' AND stare = 'gata' AND sfarsit IS NOT NULL ORDER BY id DESC LIMIT 1"
	);
	return $data ? (string) $data : null;
}

/**
 * Caută problemele care merită un email.
 *
 * Fiecare problemă are o cheie stabilă, ca să se poată trimite o singură dată, și un text scris pentru cineva
 * care nu știe ce e un endpoint.
 *
 * @return array cheie => array( subiect, mesaj ).
 */
function ccs_verifica_sanatatea() {
	$setari    = ccs_setari();
	$probleme  = array();
	$ore_prag  = max( 1, (int) $setari['ore_fara_sincronizare'] );

	// 1. Sincronizarea s-a oprit. Asta e problema din cauza căreia există fișierul.
	$ultima = ccs_ultima_sincronizare();
	if ( $setari['joburi_pornite'] ) {
		if ( ! $ultima ) {
			$probleme['fara_sincronizare'] = array(
				__( 'Sincronizarea nu a rulat niciodată', 'conex-conect-sync' ),
				__( "Sincronizarea cu furnizorul este pornită, dar nu a reușit nicio preluare până acum.\nDeschide panoul și verifică dacă tokenul este configurat.", 'conex-conect-sync' ),
			);
		} else {
			$ore = ( time() - (int) mysql2date( 'U', $ultima, false ) ) / HOUR_IN_SECONDS;
			if ( $ore > $ore_prag ) {
				$probleme['fara_sincronizare'] = array(
					__( 'Sincronizarea s-a oprit', 'conex-conect-sync' ),
					sprintf(
						/* translators: 1: de câte ore, 2: data ultimei reușite. */
						__( "Nu s-a mai preluat nimic de la furnizor de %1\$d ore. Ultima preluare reușită: %2\$s.\n\nCât timp sincronizarea este oprită, prețurile și stocurile de pe site rămân cele de atunci.", 'conex-conect-sync' ),
						(int) $ore,
						$ultima
					),
				);
			}
		}
	}

	// 2. Ultima rulare a căzut. Codul erorii spune ce fel de problemă e, iar fiecare fel merită alt text:
	// un token respins și un catalog care s-a înjumătățit se repară în moduri complet diferite.
	$eroare = (array) get_option( 'ccs_ultima_eroare', array() );
	$cod    = (string) ( $eroare['cod'] ?? '' );
	$mesaj  = (string) ( $eroare['mesaj'] ?? '' );

	if ( 'ccs_token_invalid' === $cod || 'ccs_fara_token' === $cod ) {
		$probleme['token'] = array(
			__( 'Accesul la furnizor nu mai funcționează', 'conex-conect-sync' ),
			sprintf(
				/* translators: %s: mesajul primit. */
				__( "Furnizorul ne-a refuzat cererile. Cel mai probabil tokenul de acces a expirat sau a fost schimbat.\n\nCât timp ține, prețurile și stocurile de pe site rămân cele de acum.\n\nRăspunsul primit: %s", 'conex-conect-sync' ),
				$mesaj
			),
		);
	} elseif ( 'ccs_scadere_brusca' === $cod ) {
		// Preluarea s-a oprit singură fără să scrie nimic. Asta nu e o eroare tehnică, e „furnizorul a schimbat
		// ceva”, exact cazul pe care oferta promite să îl semnaleze.
		$probleme['catalog_mic'] = array(
			__( 'Catalogul furnizorului s-a micșorat brusc', 'conex-conect-sync' ),
			sprintf(
				/* translators: %s: mesajul cu cifrele. */
				__( "Furnizorul trimite mult mai puține produse decât înainte, iar sincronizarea s-a oprit singură fără să schimbe nimic pe site.\n\n%s\n\nPoate fi o schimbare reală la ei, sau o problemă de moment. Până se lămurește, nimic nu se scrie pe site.", 'conex-conect-sync' ),
				$mesaj
			),
		);
	} elseif ( '' !== $cod ) {
		$probleme['rulare_cazuta'] = array(
			__( 'Sincronizarea a dat eroare', 'conex-conect-sync' ),
			sprintf(
				/* translators: %s: mesajul erorii. */
				__( "Ultima încercare de sincronizare nu a reușit.\n\n%s", 'conex-conect-sync' ),
				$mesaj
			),
		);
	}

	/**
	 * Permite adăugarea de verificări.
	 *
	 * @param array $probleme Problemele găsite.
	 */
	return apply_filters( 'ccs_probleme', $probleme );
}

/**
 * Verifică și trimite alertele pentru ce găsește.
 *
 * @return int Câte alerte au plecat.
 */
function ccs_alerte_verifica() {
	$probleme = ccs_verifica_sanatatea();
	$plecate  = 0;

	foreach ( $probleme as $cheie => $p ) {
		if ( ccs_alerta( $cheie, $p[0], $p[1] ) ) {
			$plecate++;
		}
	}

	// O problemă care a dispărut trebuie să poată alerta din nou dacă revine.
	foreach ( array( 'fara_sincronizare', 'token', 'catalog_mic', 'rulare_cazuta' ) as $cheie ) {
		if ( ! isset( $probleme[ $cheie ] ) ) {
			ccs_alerta_rezolvata( $cheie );
		}
	}

	return $plecate;
}

/**
 * Rezumatul a ce s-a schimbat într-un interval.
 *
 * @param int $ore Câte ore în urmă se uită.
 * @return array Cifrele.
 */
function ccs_rezumat_perioada( $ore = 24 ) {
	global $wpdb;
	$modificari = ccs_tabel( 'modificari' );
	$de_la      = gmdate( 'Y-m-d H:i:s', time() - $ore * HOUR_IN_SECONDS - (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );

	$pe_camp = $wpdb->get_results(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL
			"SELECT camp, COUNT(*) AS n FROM $modificari WHERE aplicat_la >= %s AND revenit_la IS NULL GROUP BY camp",
			$de_la
		)
	);

	$cifre = array(
		'pret'         => 0,
		'stoc'         => 0,
		'stare_stoc'   => 0,
		'creat'        => 0,
		'sters'        => 0,
		'vizibilitate' => 0,
	);
	foreach ( $pe_camp as $r ) {
		$cifre[ (string) $r->camp ] = (int) $r->n;
	}

	$cifre['produse'] = (int) $wpdb->get_var(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL
			"SELECT COUNT(DISTINCT wc_product_id) FROM $modificari WHERE aplicat_la >= %s AND revenit_la IS NULL",
			$de_la
		)
	);

	return $cifre;
}

/**
 * Textul rezumatului zilnic.
 *
 * Nu se trimite un email care spune „nu s-a schimbat nimic” în fiecare zi: ăla se învață repede ca zgomot, iar
 * când chiar nu se mai schimbă nimic fiindcă sincronizarea s-a oprit, omul nu mai observă. Tăcerea în zilele
 * liniștite e acoperită de alerta de sincronizare oprită, care e altceva.
 *
 * @return string|null Textul, sau null dacă n-are ce raporta.
 */
function ccs_rezumat_text() {
	$c = ccs_rezumat_perioada( 24 );
	if ( ! $c['produse'] ) {
		return null;
	}

	$randuri = array();
	$etichete = array(
		'pret'         => __( 'prețuri schimbate', 'conex-conect-sync' ),
		'stoc'         => __( 'cantități actualizate', 'conex-conect-sync' ),
		'stare_stoc'   => __( 'produse intrate sau ieșite din stoc', 'conex-conect-sync' ),
		'creat'        => __( 'produse noi aduse de la furnizor', 'conex-conect-sync' ),
		'sters'        => __( 'produse trecute la coșul de gunoi', 'conex-conect-sync' ),
		'vizibilitate' => __( 'produse ascunse din catalog', 'conex-conect-sync' ),
	);
	foreach ( $etichete as $camp => $eticheta ) {
		if ( $c[ $camp ] ) {
			$randuri[] = sprintf( '  %s %s', number_format_i18n( $c[ $camp ] ), $eticheta );
		}
	}

	$candidati = function_exists( 'ccs_import_candidati' ) ? ccs_import_candidati() : array( 'gata' => 0 );

	$text = sprintf(
		/* translators: %s: câte produse au fost atinse. */
		__( "În ultimele 24 de ore s-au modificat %s produse:", 'conex-conect-sync' ),
		number_format_i18n( $c['produse'] )
	);
	$text .= "\n\n" . implode( "\n", $randuri );

	if ( ! empty( $candidati['gata'] ) ) {
		$text .= "\n\n" . sprintf(
			/* translators: %s: câte produse se pot importa. */
			__( '%s produse de la furnizor pot fi aduse pe site. Le vezi în panou, la Catalogul.', 'conex-conect-sync' ),
			number_format_i18n( $candidati['gata'] )
		);
	}

	return $text;
}

/**
 * Jobul zilnic: rezumatul, plus verificarea de sănătate.
 *
 * @return void
 */
function ccs_job_email() {
	ccs_alerte_verifica();

	if ( ! ccs_setari()['email_rezumat'] ) {
		return;
	}

	$text = ccs_rezumat_text();
	if ( null === $text ) {
		return;
	}

	ccs_email_trimite( __( 'Ce s-a schimbat în ultimele 24 de ore', 'conex-conect-sync' ), $text );
}
add_action( 'ccs_job_email', 'ccs_job_email' );

/**
 * Înregistrează jobul zilnic de email.
 *
 * Nu e job de noapte: raportul se citește dimineața, deci pleacă la ora stabilită în setări, implicit 8.
 *
 * @param array $joburi Joburile.
 * @return array Joburile.
 */
function ccs_email_job( $joburi ) {
	$joburi['ccs_job_email'] = array(
		'nume'         => __( 'Rezumatul zilnic pe email', 'conex-conect-sync' ),
		'interval'     => DAY_IN_SECONDS,
		'doar_noaptea' => false,
	);
	return $joburi;
}
add_filter( 'ccs_joburi', 'ccs_email_job' );

/**
 * Verificarea de sănătate la intrarea în wp-admin.
 *
 * Un job programat nu poate raporta că joburile programate nu mai rulează. Asta e plasa de sub ele. Transientul
 * o ține la o verificare pe oră, indiferent câte pagini deschide omul.
 *
 * @return void
 */
function ccs_alerte_din_admin() {
	if ( wp_doing_ajax() || ! is_admin() || get_transient( 'ccs_alerte_verificate' ) ) {
		return;
	}
	set_transient( 'ccs_alerte_verificate', 1, HOUR_IN_SECONDS );
	ccs_alerte_verifica();
}
add_action( 'admin_init', 'ccs_alerte_din_admin' );
