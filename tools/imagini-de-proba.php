<?php
/**
 * Umple tabelul de imagini cu datele sondate în octombrie, ca să se poată proba ziua.
 *
 * Endpoint-ul `images` răspunde doar între 19:00 și 05:00, deci în timpul zilei nu se poate verifica nimic din ce
 * vine după el: numărul de produse importabile, panoul, regula „fără poză nu urcă”. Fișierul salvat pe 14 octombrie
 * de `cez-auto/tools/conex-night-fetch.py` are exact forma agregată de care avem nevoie.
 *
 * Doar pe mediul local. Pe live imaginile se iau de la furnizor, cu `wp ccs imagini --preia`.
 *
 * Rulare:
 *   sh tools/wp-local.sh eval-file tools/imagini-de-proba.php
 *
 * @package conex-conect-sync
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

$fisier = 'D:/Work/Repos/cez-auto/tools/conex-images.json';
if ( ! file_exists( $fisier ) ) {
	WP_CLI::error( 'Nu găsesc ' . $fisier );
}

$date = json_decode( file_get_contents( $fisier ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
if ( ! is_array( $date ) ) {
	WP_CLI::error( 'Fișierul nu se citește ca JSON.' );
}

global $wpdb;
$tabel = ccs_tabel( 'imagini' );
$acum  = current_time( 'mysql' );
$wpdb->query( "TRUNCATE TABLE $tabel" ); // phpcs:ignore WordPress.DB.PreparedSQL

$valori = array();
$parametri = array();
$poze = 0;
foreach ( $date as $product_id => $urls ) {
	$pozitie = 0;
	foreach ( (array) $urls as $url ) {
		// Forma agregată ține fie adresa simplă, fie perechea [ordine, adresă].
		$url = is_array( $url ) ? ( $url[1] ?? '' ) : $url;
		if ( ! $url ) {
			continue;
		}
		$valori[] = '(%d,%d,%s,%s)';
		array_push( $parametri, (int) $product_id, $pozitie, $url, $acum );
		$pozitie++;
		$poze++;

		if ( count( $valori ) >= 500 ) {
			$wpdb->query( $wpdb->prepare( "INSERT INTO $tabel (product_id, pozitie, url, vazut_la) VALUES " . implode( ',', $valori ), $parametri ) ); // phpcs:ignore WordPress.DB.PreparedSQL
			$valori    = array();
			$parametri = array();
		}
	}
}
if ( $valori ) {
	$wpdb->query( $wpdb->prepare( "INSERT INTO $tabel (product_id, pozitie, url, vazut_la) VALUES " . implode( ',', $valori ), $parametri ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}

WP_CLI::success( sprintf( '%d poze pentru %d produse, din sondajul din 14 octombrie.', $poze, count( $date ) ) );
WP_CLI::line( 'Sunt date vechi, bune doar ca să se poată proba lanțul ziua.' );
