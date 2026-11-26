<?php
/**
 * Plugin Name:       Conex Conect Sync pentru WooCommerce
 * Description:       Sincronizează stocul, prețul și produsele cu API-ul Conex Conect: catalog la 30 de minute, imagini și date tehnice din fereastra de noapte, import de produse noi cu confirmare.
 * Version:           0.4.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Agensio
 * Author URI:        https://agensio.ro
 * License:           GPL-2.0-or-later
 * Text Domain:       conex-conect-sync
 * Domain Path:       /languages
 * Update URI:        false
 *
 * @package conex-conect-sync
 */

defined( 'ABSPATH' ) || exit;

// Update URI: false, ca la agensio-consent: fără actualizări automate de nicăieri. Versiunile se urcă din chatul
// fiecărui proiect, cu arhiva din `npm run zip`.
define( 'CCS_VERSIUNE', '0.4.0' );
define( 'CCS_FISIER', __FILE__ );
define( 'CCS_DIR', plugin_dir_path( __FILE__ ) );
define( 'CCS_URL', plugin_dir_url( __FILE__ ) );

require CCS_DIR . 'includes/configurare.php';
require CCS_DIR . 'includes/schema.php';
require CCS_DIR . 'includes/api.php';
require CCS_DIR . 'includes/jurnal.php';
require CCS_DIR . 'includes/catalog.php';
require CCS_DIR . 'includes/potrivire.php';
require CCS_DIR . 'includes/plan.php';
require CCS_DIR . 'includes/raport.php';
require CCS_DIR . 'includes/aplica.php';
require CCS_DIR . 'includes/decizii.php';
require CCS_DIR . 'includes/categorii.php';
require CCS_DIR . 'includes/imagini.php';
require CCS_DIR . 'includes/import.php';
require CCS_DIR . 'includes/corectii.php';
require CCS_DIR . 'includes/joburi.php';
require CCS_DIR . 'includes/admin.php';
require CCS_DIR . 'includes/admin-ui.php';
require CCS_DIR . 'includes/cli.php';

add_action(
	'init',
	function () {
		load_plugin_textdomain( 'conex-conect-sync', false, dirname( plugin_basename( CCS_FISIER ) ) . '/languages' );
	},
	0
);
