<?php
/**
 * Tabelele proprii ale pluginului.
 *
 * De ce tabele proprii și nu direct în WooCommerce: catalogul furnizorului se citește des (la 30 de minute) și
 * aproape nimic nu se schimbă de la o citire la alta. Ținem o copie locală cu o amprentă pe produs, comparăm
 * amprentele și atingem WooCommerce doar pentru produsele la care chiar s-a schimbat ceva. Fără asta, fiecare rulare
 * ar rescrie 20.000 de produse degeaba.
 *
 * @package conex-conect-sync
 */

defined( 'ABSPATH' ) || exit;

const CCS_SCHEMA_VERSIUNE = 6;

/**
 * Numele unui tabel al pluginului, cu prefixul bazei.
 *
 * @param string $nume Numele scurt: 'products', 'links', 'runs', 'log', 'modificari', 'decizii', 'excluse', 'categorii', 'imagini'.
 * @return string Numele complet.
 */
function ccs_tabel( $nume ) {
	global $wpdb;
	return $wpdb->prefix . 'ccs_' . $nume;
}

/**
 * Creează sau actualizează tabelele.
 *
 * @return void
 */
function ccs_schema_instaleaza() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$colatie = $wpdb->get_charset_collate();

	$produse = ccs_tabel( 'products' );
	$links   = ccs_tabel( 'links' );
	$rulari  = ccs_tabel( 'runs' );
	$jurnal  = ccs_tabel( 'log' );
	$modif   = ccs_tabel( 'modificari' );
	$decizii = ccs_tabel( 'decizii' );
	$excluse = ccs_tabel( 'excluse' );
	$categ   = ccs_tabel( 'categorii' );
	$imagini = ccs_tabel( 'imagini' );

	// Copia catalogului furnizorului. `cod` e codEAN, adică ce se potrivește cu SKU-ul de pe site.
	// `amprenta` e un md5 peste câmpurile care ne interesează: dacă nu s-a schimbat, produsul se sare.
	dbDelta(
		"CREATE TABLE $produse (
			product_id BIGINT UNSIGNED NOT NULL,
			cod VARCHAR(64) NOT NULL DEFAULT '',
			mpn VARCHAR(64) NOT NULL DEFAULT '',
			nume VARCHAR(255) NOT NULL DEFAULT '',
			brand VARCHAR(120) NOT NULL DEFAULT '',
			cat1 VARCHAR(120) NOT NULL DEFAULT '',
			cat2 VARCHAR(120) NOT NULL DEFAULT '',
			cat3 VARCHAR(120) NOT NULL DEFAULT '',
			cost DECIMAL(12,4) NOT NULL DEFAULT 0,
			taxa_eco DECIMAL(10,4) NOT NULL DEFAULT 0,
			stoc INT NOT NULL DEFAULT 0,
			amprenta CHAR(32) NOT NULL DEFAULT '',
			prima_vedere DATETIME NULL,
			ultima_vedere DATETIME NULL,
			lipsa_runde SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (product_id),
			KEY cod (cod),
			KEY mpn (mpn),
			KEY ultima_vedere (ultima_vedere)
		) $colatie;"
	);

	// Legătura produs WooCommerce -> produs Conex. Mai multe produse Woo pot arăta spre același produs Conex
	// (același ștergător listat per model auto), deci cheia primară e produsul Woo.
	dbDelta(
		"CREATE TABLE $links (
			wc_product_id BIGINT UNSIGNED NOT NULL,
			product_id BIGINT UNSIGNED NOT NULL,
			metoda VARCHAR(32) NOT NULL DEFAULT '',
			incredere CHAR(1) NOT NULL DEFAULT 'A',
			nume_scor TINYINT UNSIGNED NOT NULL DEFAULT 100,
			confirmat_la DATETIME NULL,
			PRIMARY KEY  (wc_product_id),
			KEY product_id (product_id),
			KEY incredere (incredere)
		) $colatie;"
	);

	dbDelta(
		"CREATE TABLE $rulari (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tip VARCHAR(32) NOT NULL DEFAULT '',
			inceput DATETIME NULL,
			sfarsit DATETIME NULL,
			stare VARCHAR(20) NOT NULL DEFAULT 'pornit',
			rezumat TEXT NULL,
			PRIMARY KEY  (id),
			KEY tip (tip)
		) $colatie;"
	);

	dbDelta(
		"CREATE TABLE $jurnal (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			rulare_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			nivel VARCHAR(16) NOT NULL DEFAULT 'info',
			mesaj TEXT NULL,
			creat_la DATETIME NULL,
			PRIMARY KEY  (id),
			KEY rulare_id (rulare_id)
		) $colatie;"
	);

	// Fiecare schimbare scrisă în WooCommerce, cu valoarea veche. De aici se poate da înapoi o rulare întreagă
	// (`wp ccs revino`), ceea ce schimbă complet discuția cu clientul: prima aplicare nu mai e un pas fără retur.
	dbDelta(
		"CREATE TABLE $modif (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			rulare_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			wc_product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			camp VARCHAR(24) NOT NULL DEFAULT '',
			vechi VARCHAR(64) NOT NULL DEFAULT '',
			nou VARCHAR(64) NOT NULL DEFAULT '',
			aplicat_la DATETIME NULL,
			revenit_la DATETIME NULL,
			PRIMARY KEY  (id),
			KEY rulare_id (rulare_id),
			KEY wc_product_id (wc_product_id)
		) $colatie;"
	);

	// Deciziile luate de client, cu cine și când. Nu sunt setări: sunt răspunsuri la întrebări concrete, iar istoricul
	// lor e dovada pe care o avem dacă cineva întreabă mai târziu de ce s-a schimbat un preț.
	dbDelta(
		"CREATE TABLE $decizii (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tip VARCHAR(32) NOT NULL DEFAULT '',
			referinta VARCHAR(64) NOT NULL DEFAULT '',
			valoare VARCHAR(64) NOT NULL DEFAULT '',
			nota TEXT NULL,
			utilizator BIGINT UNSIGNED NOT NULL DEFAULT 0,
			creat_la DATETIME NULL,
			PRIMARY KEY  (id),
			KEY tip_referinta (tip, referinta),
			KEY creat_la (creat_la)
		) $colatie;"
	);

	// Produse de pe site pe care clientul a spus să nu le legăm niciodată, oricât de bine s-ar potrivi codul.
	dbDelta(
		"CREATE TABLE $excluse (
			wc_product_id BIGINT UNSIGNED NOT NULL,
			motiv VARCHAR(255) NOT NULL DEFAULT '',
			creat_la DATETIME NULL,
			PRIMARY KEY  (wc_product_id)
		) $colatie;"
	);

	// Maparea categoriilor furnizorului pe cele ale magazinului. `contine` e gol pentru o regulă pe toată calea, sau
	// un cuvânt din numele produsului când aceeași cale se împarte în două categorii.
	dbDelta(
		"CREATE TABLE $categ (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			cale VARCHAR(255) NOT NULL DEFAULT '',
			contine VARCHAR(64) NOT NULL DEFAULT '',
			term_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			incredere TINYINT UNSIGNED NOT NULL DEFAULT 0,
			produse INT UNSIGNED NOT NULL DEFAULT 0,
			sursa VARCHAR(16) NOT NULL DEFAULT 'invatat',
			creat_la DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY cale_contine (cale, contine),
			KEY sursa (sursa)
		) $colatie;"
	);

	// Adresele pozelor, luate din fereastra de noapte. Nu descărcăm nimic aici: tabelul răspunde la întrebarea
	// „care produse au poză”, de care depinde ce se poate importa.
	dbDelta(
		"CREATE TABLE $imagini (
			product_id BIGINT UNSIGNED NOT NULL,
			pozitie SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			url VARCHAR(255) NOT NULL DEFAULT '',
			vazut_la DATETIME NULL,
			PRIMARY KEY  (product_id, pozitie)
		) $colatie;"
	);

	update_option( 'ccs_schema_versiune', CCS_SCHEMA_VERSIUNE );
}

/**
 * Pune schema la zi dacă pluginul a fost actualizat fără reactivare.
 *
 * @return void
 */
function ccs_schema_verifica() {
	if ( (int) get_option( 'ccs_schema_versiune', 0 ) < CCS_SCHEMA_VERSIUNE ) {
		ccs_schema_instaleaza();
	}
}
add_action( 'admin_init', 'ccs_schema_verifica' );

register_activation_hook( CCS_FISIER, 'ccs_schema_instaleaza' );
