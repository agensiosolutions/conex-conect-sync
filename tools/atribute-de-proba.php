<?php
/**
 * Umple tabelul de date tehnice cu sondajul din octombrie, ca să se poată proba ziua.
 *
 * Endpoint-ul `criteria` răspunde doar între 19:00 și 05:00, exact ca `images`. Fișierul salvat pe 14 octombrie de
 * `cez-auto/tools/conex-night-fetch.py` are deja forma agregată de care avem nevoie: `{productId: {nume: valoare}}`.
 *
 * Doar pe mediul local. Pe live datele se iau de la furnizor, cu `wp ccs atribute --preia`.
 *
 * Rulare:
 *   sh tools/wp-local.sh eval-file tools/atribute-de-proba.php
 *
 * @package conex-conect-sync
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

$fisier = 'D:/Work/Repos/cez-auto/tools/conex-criteria.json';
if ( ! file_exists( $fisier ) ) {
	WP_CLI::error( 'Nu găsesc ' . $fisier );
}

$date = json_decode( file_get_contents( $fisier ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
if ( ! is_array( $date ) ) {
	WP_CLI::error( 'Fișierul nu se citește ca JSON.' );
}

global $wpdb;
$tabel = ccs_tabel( 'atribute' );
$acum  = current_time( 'mysql' );

$wpdb->query( "TRUNCATE TABLE $tabel" ); // phpcs:ignore WordPress.DB.PreparedSQL

$valori = array();
$randuri = array();
$scrise  = 0;
foreach ( $date as $product_id => $atribute ) {
	foreach ( (array) $atribute as $nume => $valoare ) {
		$nume    = trim( (string) $nume );
		$valoare = trim( (string) $valoare );
		if ( '' === $nume || '' === $valoare ) {
			continue;
		}
		$valori[] = '(%d,%s,%s,%s)';
		array_push( $randuri, (int) $product_id, mb_substr( $nume, 0, 120 ), mb_substr( $valoare, 0, 255 ), $acum );
		$scrise++;

		if ( count( $valori ) >= 500 ) {
			$wpdb->query(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL
					"INSERT INTO $tabel (product_id, nume, valoare, vazut_la) VALUES " . implode( ',', $valori ),
					$randuri
				)
			);
			$valori  = array();
			$randuri = array();
		}
	}
}
if ( $valori ) {
	$wpdb->query(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL
			"INSERT INTO $tabel (product_id, nume, valoare, vazut_la) VALUES " . implode( ',', $valori ),
			$randuri
		)
	);
}

delete_transient( 'ccs_atribute_filtrabile' );

$stare = ccs_atribute_stare();
WP_CLI::success(
	sprintf(
		'%d perechi pentru %d produse, %d nume distincte. Doar pentru dezvoltare.',
		$stare['perechi'],
		$stare['produse'],
		$stare['nume']
	)
);
