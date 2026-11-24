<?php
/**
 * Setările pluginului.
 *
 * Ordinea, ca la agensio-consent: valorile implicite, apoi opțiunea din admin, apoi filtrul `ccs_setari`.
 * Tokenul poate veni și din constanta `CCS_API_TOKEN` din wp-config, care bate setarea (vezi `ccs_api_token()`).
 *
 * @package conex-conect-sync
 */

defined( 'ABSPATH' ) || exit;

const CCS_OPTIUNE_SETARI = 'ccs_setari';

/**
 * Valorile implicite.
 *
 * Regula de preț vine din măsurătorile pe Cez Auto: adaosul singur, în procente, se comportă prost la produsele
 * ieftine. Un clips de 0,22 lei ajunge la 1 leu, adică +359% adaos, dar 0,78 lei marjă brută, sub costul de
 * procesare a comenzii. De aceea `marja_minima_lei`: pe cele 2.493 de produse măsurate rezolvă toate cele 255 de
 * cazuri problematice și costă 543 de lei în total, adică 0,13%.
 *
 * @return array Valorile implicite.
 */
function ccs_setari_implicite() {
	return array(
		'token'              => '',
		'adaos'              => 1.35,
		'cota_tva'           => 1.21,
		'marja_minima_lei'   => 4.0,
		'pret_minim_lei'     => 0.0,
		'rotunjire'          => 'sus',   // 'sus' = ceil, cum sunt prețurile de azi pe cezauto.ro.
		'include_taxa_eco'   => true,
		'stoc_absent_runde'  => 2,       // De câte ori la rând lipsește un produs din feed înainte să treacă pe stoc 0.
		'poze_per_produs'    => 4,
		'mod_raport'         => true,    // Pornit: nu scrie nimic în WooCommerce, doar raportează.

		// Două frâne pentru scriere, fiecare pentru alt fel de accident.
		// `scadere_pret_maxima`: cât poate scădea prețul unui produs într-o singură rulare, ca fracție din prețul de
		// acum. Sub pragul ăsta nu scriem, raportăm. Un preț care se face jumătate peste noapte e, în practică, un
		// cost greșit la furnizor, nu o reducere.
		// `prag_schimbari`: ce fracție din produsele legate poate atinge o rulare fără confirmare explicită. Prima
		// aplicare trece peste el o singură dată, cu `--forteaza`, după ce clientul a văzut raportul. Pe urmă, o
		// rulare care vrea să atingă un sfert din magazin e un semnal, nu o treabă de rutină.
		'scadere_pret_maxima' => 0.5,
		// Cât trebuie să semene numele de pe site cu numele de la furnizor ca potrivirea pe cod să fie de încredere.
		// Măsurat pe cele 3.980 de legături de la Cez Auto: tot ce trece de 67% e potrivire bună (nume trunchiate,
		// „VW” în loc de „VOLKSWAGEN”, alt interval de ani), tot ce stă sub 33% e alt produs. Între ele nu e nimic.
		'prag_nume'           => 0.5,
		'prag_schimbari'      => 0.25,
		'lot_aplicare'        => 200,    // Câte produse se scriu între două pauze de golire a cache-ului.

		// Joburile sunt oprite până le pornește cineva. Un plugin care începe să scrie singur în magazin imediat ce
		// a fost activat e un plugin în care nu ai încredere a doua oară.
		'joburi_pornite'      => false,
		'interval_catalog'    => 30,     // Minute între două preluări de catalog.
	);
}

/**
 * Setările efective.
 *
 * @return array Setările.
 */
function ccs_setari() {
	$setari = wp_parse_args( (array) get_option( CCS_OPTIUNE_SETARI, array() ), ccs_setari_implicite() );

	/**
	 * Permite schimbarea setărilor din cod, pentru cazuri speciale.
	 *
	 * @param array $setari Setările.
	 */
	return apply_filters( 'ccs_setari', $setari );
}

/**
 * Prețul de vânzare pentru un cost de achiziție.
 *
 * `max()` între trei reguli, nu doar adaosul: adaosul procentual, marja minimă în lei și un preț minim absolut.
 * Fără marja minimă, produsele foarte ieftine se vând în pierdere oricât de mare ar fi procentul.
 *
 * @param float $cost_fara_tva Prețul de achiziție fără TVA.
 * @param float $taxa_eco      Taxa verde, dacă există.
 * @return float Prețul de vânzare, rotunjit după setare.
 */
function ccs_pret( $cost_fara_tva, $taxa_eco = 0.0 ) {
	$setari = ccs_setari();
	$cost   = (float) $cost_fara_tva * (float) $setari['cota_tva'];
	if ( $setari['include_taxa_eco'] ) {
		$cost += (float) $taxa_eco;
	}
	if ( $cost <= 0 ) {
		return 0.0;
	}

	$din_adaos = $cost * (float) $setari['adaos'];
	$din_marja = $cost + (float) $setari['marja_minima_lei'];

	$pret = max( $din_adaos, $din_marja, (float) $setari['pret_minim_lei'] );

	return 'sus' === $setari['rotunjire'] ? (float) ceil( $pret ) : (float) round( $pret, 2 );
}
