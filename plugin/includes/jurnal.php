<?php
/**
 * Jurnalul pluginului.
 *
 * Multă vreme fișierul ăsta a scris numai în `error_log`, și numai când `WP_DEBUG` era pornit, plus în ieșirea
 * WP-CLI când rula cineva o comandă de mână. Pe live `WP_DEBUG` e oprit, iar joburile de noapte rulează prin
 * Action Scheduler, nu prin CLI: adică tot ce încercau să spună se pierdea. Tabela `ccs_log` exista din schemă,
 * creată și goală, fiindcă nimic nu scria în ea.
 *
 * S-a văzut la prima noapte adevărată: joburile au mers toate, iar jurnalul avea zero rânduri. Contează mai
 * ales la import, unde fiecare poză care nu se descarcă scrie un avertisment. Două sute de poze pierdute în
 * tăcere nu se observă din cifre.
 *
 * Acum scrie întotdeauna în tabel, legat de rularea în curs, și păstrează pe deasupra vechile ieșiri, care sunt
 * utile când stai cu ochii pe o comandă.
 *
 * Tokenul nu ajunge niciodată în jurnal: orice mesaj trece prin `ccs_api_fara_token()`.
 *
 * @package conex-conect-sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Câte zile se păstrează liniile de jurnal.
 */
const CCS_JURNAL_ZILE = 30;

/**
 * Scrie o linie în jurnal.
 *
 * @param string $nivel 'info', 'avertisment' sau 'eroare'.
 * @param string $mesaj Mesajul.
 * @return void
 */
function ccs_jurnal( $nivel, $mesaj ) {
	$mesaj = function_exists( 'ccs_api_fara_token' ) ? ccs_api_fara_token( $mesaj ) : $mesaj;
	$linie = sprintf( '[ccs][%s] %s', $nivel, $mesaj );

	ccs_jurnal_scrie( $nivel, $mesaj );

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		if ( 'eroare' === $nivel ) {
			WP_CLI::warning( $mesaj );
		} elseif ( 'avertisment' === $nivel ) {
			WP_CLI::log( $linie );
		} else {
			WP_CLI::debug( $mesaj, 'ccs' );
		}
		return;
	}

	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( $linie ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}

/**
 * Pune linia în tabel.
 *
 * Nu se plânge dacă scrierea cade: un jurnal care oprește operația pe care o jurnalizează ar fi mai rău decât
 * lipsa jurnalului.
 *
 * @param string $nivel Nivelul.
 * @param string $mesaj Mesajul, deja curățat de token.
 * @return void
 */
function ccs_jurnal_scrie( $nivel, $mesaj ) {
	global $wpdb;

	if ( ! isset( $wpdb ) || ! function_exists( 'ccs_tabel' ) ) {
		return;
	}

	$wpdb->insert(
		ccs_tabel( 'log' ),
		array(
			'rulare_id' => function_exists( 'ccs_rulare_curenta' ) ? ccs_rulare_curenta() : 0,
			'nivel'     => substr( (string) $nivel, 0, 16 ),
			'mesaj'     => (string) $mesaj,
			'creat_la'  => current_time( 'mysql' ),
		)
	);
}

/**
 * Taie liniile vechi, o dată pe zi.
 *
 * Se cheamă la închiderea fiecărei rulări, dar lucrează doar dacă a trecut o zi de la ultima curățare. Fără
 * oprirea asta, un magazin cu preluare la jumătate de oră ar șterge de 48 de ori pe zi degeaba.
 *
 * @return void
 */
function ccs_jurnal_curata() {
	global $wpdb;

	$ultima = (int) get_option( 'ccs_jurnal_curatat', 0 );
	if ( time() - $ultima < DAY_IN_SECONDS ) {
		return;
	}
	update_option( 'ccs_jurnal_curatat', time(), false );

	$tabel = ccs_tabel( 'log' );
	$wpdb->query(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL -- numele tabelei vine din prefixul WordPress.
			"DELETE FROM $tabel WHERE creat_la < %s",
			gmdate( 'Y-m-d H:i:s', strtotime( '-' . CCS_JURNAL_ZILE . ' days', (int) current_datetime()->format( 'U' ) ) )
		)
	);
}

/**
 * Citește din jurnal.
 *
 * @param array $arg nivel, rulare, numar.
 * @return array Rândurile, cele mai noi întâi.
 */
function ccs_jurnal_citeste( $arg = array() ) {
	global $wpdb;

	$arg = wp_parse_args(
		$arg,
		array(
			'nivel'  => '',
			'rulare' => 0,
			'numar'  => 50,
		)
	);

	$tabel  = ccs_tabel( 'log' );
	$unde   = array( '1=1' );
	$valori = array();

	if ( '' !== (string) $arg['nivel'] ) {
		$unde[]   = 'nivel = %s';
		$valori[] = (string) $arg['nivel'];
	}
	if ( (int) $arg['rulare'] ) {
		$unde[]   = 'rulare_id = %d';
		$valori[] = (int) $arg['rulare'];
	}

	$valori[] = max( 1, (int) $arg['numar'] );
	$sql      = 'SELECT id, rulare_id, nivel, mesaj, creat_la FROM ' . $tabel
		. ' WHERE ' . implode( ' AND ', $unde ) . ' ORDER BY id DESC LIMIT %d';

	// phpcs:ignore WordPress.DB.PreparedSQL -- numele tabelei vine din prefixul WordPress, restul e parametrizat.
	return (array) $wpdb->get_results( $wpdb->prepare( $sql, $valori ) );
}
