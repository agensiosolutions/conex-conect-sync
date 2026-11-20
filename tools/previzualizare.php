<?php
/**
 * Randează o pagină de admin a pluginului într-un fișier HTML, fără să fie nevoie de login.
 *
 * De ce: paginile de admin cer autentificare, iar pe mediul local n-avem parola și nici n-avem nevoie de ea ca să
 * vedem cum arată pagina. Randăm funcția direct, punem stilurile reale de admin (sunt fișiere statice, se încarcă
 * fără sesiune) și scriem rezultatul în `uploads`, de unde se deschide în browser.
 *
 * Rulare:
 *   sh tools/wp-local.sh eval-file tools/previzualizare.php
 *
 * Nu se folosește niciodată pe live: scrie un fișier în `uploads` și randează o pagină de admin fără verificare de
 * drepturi. E strict pentru dezvoltare.
 *
 * @package conex-conect-sync
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

require_once ABSPATH . 'wp-admin/includes/admin.php';

// Un administrator, ca verificările de drepturi din pagină să treacă.
$admini = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
if ( ! $admini ) {
	WP_CLI::error( 'Nu există niciun administrator pe site.' );
}
wp_set_current_user( (int) $admini[0] );

set_current_screen( 'toplevel_page_ccs-decizii' );

// Chemăm direct funcția noastră, nu hook-ul global `admin_enqueue_scripts`: acolo se agață și WooCommerce, care
// are nevoie de un context de cerere pe care WP-CLI nu-l are, și cade.
ccs_admin_assets( 'toplevel_page_ccs-decizii' );

$inceput = microtime( true );
ob_start();
ccs_admin_pagina();
$continut = ob_get_clean();
$durata   = round( microtime( true ) - $inceput, 2 );

// Datele pe care `wp_localize_script()` le-ar fi tipărit înaintea scriptului.
$localizate = wp_scripts()->get_data( 'ccs-admin', 'data' );

$stiluri = array(
	includes_url( 'css/dashicons.min.css' ),
	admin_url( 'css/common.min.css' ),
	admin_url( 'css/forms.min.css' ),
	includes_url( 'css/buttons.min.css' ),
	admin_url( 'css/list-tables.min.css' ),
	CCS_URL . 'admin/css/ccs-admin.css',
);

$html  = "<!doctype html>\n<html lang=\"ro\">\n<head>\n<meta charset=\"utf-8\">\n";
$html .= "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
$html .= "<title>Previzualizare: pagina de decizii</title>\n";
foreach ( $stiluri as $s ) {
	$html .= '<link rel="stylesheet" href="' . esc_url( $s ) . "\">\n";
}
$html .= "<style>body{background:#f0f0f1;margin:0;padding:20px 24px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;font-size:13px;line-height:1.4;color:#3c434a}</style>\n";
$html .= "</head>\n<body class=\"wp-admin wp-core-ui\">\n";
$html .= $continut;
$html .= "\n<script>var ajaxurl = " . wp_json_encode( admin_url( 'admin-ajax.php' ) ) . ";</script>\n";
$html .= '<script>' . $localizate . "</script>\n";
$html .= '<script src="' . esc_url( CCS_URL . 'admin/js/ccs-admin.js' ) . "\"></script>\n";
$html .= "</body>\n</html>\n";

$dir    = wp_upload_dir();
$fisier = trailingslashit( $dir['basedir'] ) . 'ccs-previzualizare.html';
if ( ! file_put_contents( $fisier, $html ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
	WP_CLI::error( 'Nu pot scrie fișierul de previzualizare.' );
}

WP_CLI::success( sprintf( 'Randat în %s secunde, %s KB.', $durata, round( strlen( $html ) / 1024 ) ) );
WP_CLI::line( trailingslashit( $dir['baseurl'] ) . 'ccs-previzualizare.html' );
