<?php
/**
 * Randează panoul de admin în fișiere HTML, fără să fie nevoie de login.
 *
 * De ce: paginile de admin cer autentificare, iar pe mediul local n-avem parola și nici n-avem nevoie de ea ca să
 * vedem cum arată panoul. Randăm funcția direct, punem stilurile reale de admin (sunt fișiere statice, se încarcă
 * fără sesiune) și scriem câte un fișier pentru fiecare tab, în `uploads`.
 *
 * Legăturile din navigație se rescriu către fișierele vecine, deci panoul se poate parcurge ca și cum ar fi în
 * admin. Butoanele care trimit la server nu funcționează aici: fără sesiune, cererile AJAX sunt respinse.
 *
 * Rulare:
 *   sh tools/wp-local.sh eval-file tools/previzualizare.php
 *
 * Nu se folosește pe live: scrie fișiere în `uploads` și randează pagini de admin fără verificare de drepturi.
 *
 * @package conex-conect-sync
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

require_once ABSPATH . 'wp-admin/includes/admin.php';

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

$stiluri = array(
	includes_url( 'css/dashicons.min.css' ),
	admin_url( 'css/common.min.css' ),
	admin_url( 'css/forms.min.css' ),
	includes_url( 'css/buttons.min.css' ),
	CCS_URL . 'admin/css/ccs-admin.css',
);

$dir    = wp_upload_dir();
$taburi = array_keys( ccs_admin_taburi() );
$total  = 0;

foreach ( $taburi as $tab ) {
	// Taburile se aleg din `$_GET`, ca în admin.
	$_GET['tab'] = $tab;

	// Scriptul își ia datele prin `wp_localize_script()`, care le adună pe handle. Le golim între taburi, altfel
	// al doilea tab ar primi și datele primului.
	wp_scripts()->registered['ccs-admin']->extra['data'] = '';

	$inceput = microtime( true );
	ob_start();
	ccs_admin_pagina();
	$continut = ob_get_clean();
	$durata   = round( microtime( true ) - $inceput, 2 );

	// Navigația duce către fișierele vecine, nu către admin.
	foreach ( $taburi as $t ) {
		$continut = str_replace(
			esc_url( admin_url( 'admin.php?page=ccs-decizii&tab=' . $t ) ),
			'ccs-previzualizare-' . $t . '.html',
			$continut
		);
	}

	$localizate = wp_scripts()->get_data( 'ccs-admin', 'data' );

	$html  = "<!doctype html>\n<html lang=\"ro\">\n<head>\n<meta charset=\"utf-8\">\n";
	$html .= "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
	$html .= '<title>Previzualizare: ' . esc_html( $tab ) . "</title>\n";
	foreach ( $stiluri as $s ) {
		$html .= '<link rel="stylesheet" href="' . esc_url( $s ) . "\">\n";
	}
	$html .= "<style>body{background:#f7f8fa;margin:0;padding:12px 20px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;font-size:13px;line-height:1.4;color:#3c434a}</style>\n";
	$html .= "</head>\n<body class=\"wp-admin wp-core-ui ccs-pagina\">\n";
	$html .= $continut;
	$html .= "\n<script>var ajaxurl = " . wp_json_encode( admin_url( 'admin-ajax.php' ) ) . ";</script>\n";
	$html .= '<script>' . $localizate . "</script>\n";
	$html .= '<script src="' . esc_url( CCS_URL . 'admin/js/ccs-admin.js' ) . "\"></script>\n";
	$html .= "</body>\n</html>\n";

	$fisier = trailingslashit( $dir['basedir'] ) . 'ccs-previzualizare-' . $tab . '.html';
	if ( ! file_put_contents( $fisier, $html ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
		WP_CLI::error( 'Nu pot scrie ' . $fisier );
	}

	WP_CLI::line( sprintf( '  %-12s %5s s  %4s KB', $tab, $durata, round( strlen( $html ) / 1024 ) ) );
	$total++;
}

WP_CLI::success( sprintf( '%d taburi randate.', $total ) );
WP_CLI::line( trailingslashit( $dir['baseurl'] ) . 'ccs-previzualizare-panou.html' );
