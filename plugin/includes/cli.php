<?php
/**
 * Comenzile WP-CLI.
 *
 *   wp ccs test               verifică tokenul și numără produsele din portofoliu
 *   wp ccs noapte <endpoint>  sondează un endpoint din fereastra 19:00-05:00
 *   wp ccs pret <cost>        arată ce preț ar ieși pentru un cost dat, cu setările curente
 *
 * @package conex-conect-sync
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Sincronizarea cu Conex Conect.
 */
class CCS_CLI {

	/**
	 * Verifică legătura cu API-ul și arată ce conține portofoliul.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ccs test
	 *
	 * @return void
	 */
	public function test() {
		$inceput = microtime( true );
		$produse = ccs_api_catalog();

		if ( is_wp_error( $produse ) ) {
			WP_CLI::error( $produse->get_error_message() );
		}

		$durata = round( microtime( true ) - $inceput, 2 );
		WP_CLI::success( sprintf( 'Conectat. %d produse în portofoliu, preluate în %s secunde.', count( $produse ), $durata ) );

		$branduri   = array();
		$categorii  = array();
		$fara_stoc  = 0;
		$cu_taxa    = 0;
		foreach ( $produse as $p ) {
			$brand = isset( $p['brandprodus'] ) ? $p['brandprodus'] : '';
			$cat   = isset( $p['categorie1'] ) ? $p['categorie1'] : '';
			$branduri[ $brand ]  = ( $branduri[ $brand ] ?? 0 ) + 1;
			$categorii[ $cat ]   = ( $categorii[ $cat ] ?? 0 ) + 1;
			if ( empty( $p['stoc'] ) ) {
				$fara_stoc++;
			}
			if ( ! empty( $p['prettaxa'] ) ) {
				$cu_taxa++;
			}
		}
		arsort( $branduri );
		arsort( $categorii );

		WP_CLI::line( sprintf( '  %d branduri, %d categorii principale', count( $branduri ), count( $categorii ) ) );
		WP_CLI::line( sprintf( '  %d produse fără stoc, %d cu taxă verde', $fara_stoc, $cu_taxa ) );
		WP_CLI::line( '  Primele branduri: ' . implode( ', ', array_slice( array_keys( $branduri ), 0, 6 ) ) );

		$exemplu = reset( $produse );
		WP_CLI::line( '' );
		WP_CLI::line( 'Un produs, ca să se vadă câmpurile:' );
		foreach ( $exemplu as $cheie => $valoare ) {
			WP_CLI::line( sprintf( '  %-26s %s', $cheie, is_scalar( $valoare ) ? $valoare : wp_json_encode( $valoare ) ) );
		}

		if ( isset( $exemplu['pretronfaratva'] ) ) {
			$pret = ccs_pret( $exemplu['pretronfaratva'], $exemplu['prettaxa'] ?? 0 );
			WP_CLI::line( '' );
			WP_CLI::line( sprintf( 'Cu setările curente, acest produs ar avea prețul %s lei.', $pret ) );
		}
	}

	/**
	 * Sondează un endpoint din fereastra de noapte.
	 *
	 * ## OPTIONS
	 *
	 * <endpoint>
	 * : images, equivalents, criteria sau applications.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ccs noapte images
	 *
	 * @param array $args Argumentele.
	 * @return void
	 */
	public function noapte( $args ) {
		$endpoint = isset( $args[0] ) ? $args[0] : '';

		if ( ! ccs_api_fereastra_deschisa() ) {
			WP_CLI::warning( 'După ora serverului Conex suntem în afara ferestrei 19:00-05:00. Încerc oricum.' );
		}

		$randuri = 0;
		$produse = array();
		$rezultat = ccs_api_noapte(
			$endpoint,
			function ( $pagina ) use ( &$randuri, &$produse ) {
				$randuri += count( $pagina );
				foreach ( $pagina as $r ) {
					$id = $r['productid'] ?? null;
					if ( null !== $id ) {
						$produse[ $id ] = true;
					}
				}
			}
		);

		if ( is_wp_error( $rezultat ) ) {
			if ( 'ccs_fereastra_inchisa' === $rezultat->get_error_code() ) {
				WP_CLI::warning( $rezultat->get_error_message() );
				return;
			}
			WP_CLI::error( $rezultat->get_error_message() );
		}

		WP_CLI::success( sprintf( '%s: %d rânduri pe %d produse distincte.', $endpoint, $randuri, count( $produse ) ) );
	}

	/**
	 * Arată ce preț ar ieși pentru un cost dat.
	 *
	 * ## OPTIONS
	 *
	 * <cost>
	 * : Prețul de achiziție fără TVA.
	 *
	 * [--taxa=<taxa>]
	 * : Taxa verde, dacă există.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ccs pret 0.22
	 *     wp ccs pret 41.52
	 *
	 * @param array $args       Argumentele.
	 * @param array $asociative Opțiunile.
	 * @return void
	 */
	public function pret( $args, $asociative ) {
		$cost   = (float) ( $args[0] ?? 0 );
		$taxa   = (float) ( $asociative['taxa'] ?? 0 );
		$setari = ccs_setari();
		$cu_tva = $cost * (float) $setari['cota_tva'] + ( $setari['include_taxa_eco'] ? $taxa : 0 );
		$pret   = ccs_pret( $cost, $taxa );

		WP_CLI::line( sprintf( 'cost fără TVA      %8.4f lei', $cost ) );
		WP_CLI::line( sprintf( 'cost cu TVA %.0f%%     %8.2f lei', ( $setari['cota_tva'] - 1 ) * 100, $cu_tva ) );
		WP_CLI::line( sprintf( 'preț de vânzare    %8.2f lei', $pret ) );
		if ( $cu_tva > 0 ) {
			WP_CLI::line( sprintf( 'marjă brută        %8.2f lei (%.0f%%)', $pret - $cu_tva, ( $pret / $cu_tva - 1 ) * 100 ) );
		}
	}
}

WP_CLI::add_command( 'ccs', 'CCS_CLI' );
