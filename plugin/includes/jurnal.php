<?php
/**
 * Jurnalul pluginului.
 *
 * Deocamdată scrie în `error_log` prin `WP_CLI::debug` sau `error_log()`, ca să nu inventăm un tabel înainte să știm
 * ce punem în el. Tabelul `ccs_log` din plan vine la etapa 1, odată cu restul schemei.
 *
 * Tokenul nu ajunge niciodată în jurnal: orice mesaj trece prin `ccs_api_fara_token()`.
 *
 * @package conex-conect-sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Scrie o linie în jurnal.
 *
 * @param string $nivel  'info', 'avertisment' sau 'eroare'.
 * @param string $mesaj  Mesajul.
 * @return void
 */
function ccs_jurnal( $nivel, $mesaj ) {
	$mesaj = function_exists( 'ccs_api_fara_token' ) ? ccs_api_fara_token( $mesaj ) : $mesaj;
	$linie = sprintf( '[ccs][%s] %s', $nivel, $mesaj );

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
