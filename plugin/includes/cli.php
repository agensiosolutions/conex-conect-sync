<?php
/**
 * Comenzile WP-CLI.
 *
 *   wp ccs test               verifică tokenul și numără produsele din portofoliu
 *   wp ccs schema             creează sau pune la zi tabelele proprii
 *   wp ccs catalog            preia catalogul în tabelul propriu și arată diferențele
 *   wp ccs potriveste         leagă produsele de pe site de catalog
 *   wp ccs raport             scrie CSV-ul „ce aș face”, fără să atingă nimic
 *   wp ccs aplica             scrie prețul și stocul în WooCommerce
 *   wp ccs revino <rulare>    dă înapoi o rulare de aplicare
 *   wp ccs setare <cheie>     arată sau schimbă o setare
 *   wp ccs joburi             arată joburile programate; --porneste, --opreste, --acum
 *   wp ccs categorii          învață maparea categoriilor din produsele deja legate
 *   wp ccs imagini            preia adresele pozelor din fereastra de noapte; fără opțiuni, doar arată starea
 *   wp ccs import             arată ce produse s-ar putea importa și de ce nu se pot celelalte
 *   wp ccs produs <cod>       ia un produs de la furnizor și arată ce s-ar crea, fără să scrie nimic
 *   wp ccs corectii           produsele cu același cod dar alt conținut: ce s-ar schimba, și `--aplica`
 *   wp ccs retrage            trece pe stoc epuizat produsele care nu se mai pot aduce
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
	 * Creează sau pune la zi tabelele pluginului.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ccs schema
	 *
	 * @return void
	 */
	public function schema() {
		ccs_schema_instaleaza();
		global $wpdb;
		foreach ( array( 'products', 'links', 'runs', 'log', 'modificari', 'decizii', 'excluse', 'categorii', 'imagini' ) as $t ) {
			$nume = ccs_tabel( $t );
			$n    = $wpdb->get_var( "SELECT COUNT(*) FROM $nume" ); // phpcs:ignore WordPress.DB.PreparedSQL
			WP_CLI::line( sprintf( '  %-28s %s rânduri', $nume, null === $n ? 'lipsește' : $n ) );
		}
		WP_CLI::success( 'Schema e la zi.' );
	}

	/**
	 * Preia catalogul furnizorului în tabelul propriu și arată ce s-a schimbat.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ccs catalog
	 *
	 * @return void
	 */
	public function catalog() {
		$inceput = microtime( true );
		$r       = ccs_catalog_preia();
		if ( is_wp_error( $r ) ) {
			WP_CLI::error( $r->get_error_message() );
		}
		WP_CLI::success( sprintf( '%d produse primite în %s secunde.', $r['primite'], round( microtime( true ) - $inceput, 2 ) ) );
		WP_CLI::line( sprintf( '  noi                %d', $r['noi'] ) );
		WP_CLI::line( sprintf( '  schimbate          %d  (cost %d, stoc %d)', $r['schimbate'], $r['cost_schimbat'], $r['stoc_schimbat'] ) );
		WP_CLI::line( sprintf( '  neatinse           %d', $r['neatinse'] ) );
		WP_CLI::line( sprintf( '  absente din feed   %d', $r['absente'] ) );
	}

	/**
	 * Leagă produsele de pe site de catalogul furnizorului.
	 *
	 * ## OPTIONS
	 *
	 * [--proba]
	 * : Doar calculează, fără să scrie legăturile.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ccs potriveste --proba
	 *     wp ccs potriveste
	 *
	 * @param array $args       Argumentele.
	 * @param array $asociative Opțiunile.
	 * @return void
	 */
	public function potriveste( $args, $asociative ) {
		$proba = isset( $asociative['proba'] );
		$r     = ccs_potriveste( ! $proba );

		WP_CLI::success(
			sprintf(
				'%d din %d produse de pe site, legate de catalog%s.',
				$r['legate'],
				$r['produse_site'],
				$proba ? ' (probă, nu am scris nimic)' : ''
			)
		);
		foreach ( $r['pe_metoda'] as $metoda => $n ) {
			WP_CLI::line( sprintf( '  %-18s %d', $metoda, $n ) );
		}
		WP_CLI::line( sprintf( '  %-18s %d  (codul potrivește, numele nu: nu se aplică automat)', 'de verificat', $r['de_verificat'] ) );
		WP_CLI::line( sprintf( '  %-18s %d', 'ambigue', $r['ambigue'] ) );
		WP_CLI::line( sprintf( '  %-18s %d  (nu mai sunt în catalog)', 'orfane', $r['orfane'] ) );
		WP_CLI::line( sprintf( '  %-18s %d  (în catalog, nu pe site)', 'nelegate la Conex', $r['nelegate_conex'] ) );
	}

	/**
	 * Scrie raportul „ce aș face”, fără să atingă nimic.
	 *
	 * ## OPTIONS
	 *
	 * [--dir=<dir>]
	 * : Unde se scriu fișierele. Implicit, folderul curent.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ccs raport --dir=C:/Users/Iulian/dumps
	 *
	 * @param array $args       Argumentele.
	 * @param array $asociative Opțiunile.
	 * @return void
	 */
	public function raport( $args, $asociative ) {
		$dir  = rtrim( $asociative['dir'] ?? getcwd(), "/\\" );
		$data = gmdate( 'Y-m-d' );

		$plan = ccs_plan();
		if ( is_wp_error( $plan ) ) {
			WP_CLI::error( $plan->get_error_message() );
		}

		$fisier = $dir . '/ccs-raport-' . $data . '.csv';
		$r      = ccs_raport_scrie( $fisier, $plan );
		if ( is_wp_error( $r ) ) {
			WP_CLI::error( $r->get_error_message() );
		}

		WP_CLI::success( sprintf( 'Raport scris: %s', $fisier ) );
		self::arata_rezumat_plan( $r );

		$potrivire = ccs_potriveste( false );
		WP_CLI::line( '' );
		if ( $potrivire['lista_verificat'] ) {
			$fv = $dir . '/ccs-de-verificat-' . $data . '.csv';
			$n  = ccs_raport_de_verificat( $fv, $potrivire['lista_verificat'] );
			WP_CLI::success( sprintf( '%d potriviri pe cod cu nume diferit, lăsate pe loc: %s', $n, $fv ) );
		}
		if ( $potrivire['lista_orfane'] ) {
			$fo = $dir . '/ccs-orfane-' . $data . '.csv';
			$n  = ccs_raport_orfane( $fo, $potrivire['lista_orfane'] );
			WP_CLI::success( sprintf( '%d produse care nu mai sunt în catalog: %s', $n, $fo ) );
		}
	}

	/**
	 * Scrie prețul și stocul în WooCommerce.
	 *
	 * ## OPTIONS
	 *
	 * [--tip=<tip>]
	 * : Ce se aplică: tot, pret sau stoc. Implicit tot.
	 * ---
	 * default: tot
	 * options:
	 *   - tot
	 *   - pret
	 *   - stoc
	 * ---
	 *
	 * [--limita=<n>]
	 * : Oprește-te după n produse. Bun pentru o probă pe cantitate mică.
	 *
	 * [--proba]
	 * : Numără ce s-ar face, fără să scrie. Merge și cu modul raport pornit.
	 *
	 * [--forteaza]
	 * : Trece peste pragul de schimbări. Pentru prima aplicare, după ce clientul a văzut raportul.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ccs aplica --proba
	 *     wp ccs aplica --tip=stoc --limita=50
	 *     wp ccs aplica --forteaza
	 *
	 * @param array $args       Argumentele.
	 * @param array $asociative Opțiunile.
	 * @return void
	 */
	public function aplica( $args, $asociative ) {
		$inceput = microtime( true );
		$progres = null;
		$aratat  = 0;

		$r = ccs_aplica(
			array(
				'tip'      => $asociative['tip'] ?? 'tot',
				'limita'   => (int) ( $asociative['limita'] ?? 0 ),
				'proba'    => isset( $asociative['proba'] ),
				'forteaza' => isset( $asociative['forteaza'] ),
				'raport'   => function ( $facut, $total ) use ( &$progres, &$aratat ) {
					if ( ! $progres ) {
						$progres = WP_CLI\Utils\make_progress_bar( 'Scriu în WooCommerce', $total );
					}
					$progres->tick( $facut - $aratat );
					$aratat = $facut;
				},
			)
		);

		if ( is_wp_error( $r ) ) {
			WP_CLI::error( $r->get_error_message() );
		}
		if ( $progres ) {
			$progres->finish();
		}

		$durata = round( microtime( true ) - $inceput, 2 );

		if ( $r['proba'] ) {
			WP_CLI::success( sprintf( '%d produse de atins (probă, nu am scris nimic).', $r['de_atins'] ) );
		} else {
			WP_CLI::success( sprintf( '%d din %d produse scrise în %s secunde (rularea #%d).', $r['atinse'], $r['de_atins'], $durata, $r['rulare'] ) );
		}
		WP_CLI::line( sprintf( '  prețuri             %d', $r['pret'] ) );
		WP_CLI::line( sprintf( '  stocuri             %d', $r['stoc'] ) );
		WP_CLI::line( sprintf( '  stări de stoc       %d', $r['stare_stoc'] ) );
		if ( $r['esuate'] ) {
			WP_CLI::line( sprintf( '  eșuate              %d', $r['esuate'] ) );
		}
		if ( $r['blocate'] ) {
			WP_CLI::line( '' );
			WP_CLI::line( sprintf( '%d produse lăsate deoparte, pe motive:', $r['blocate'] ) );
			foreach ( $r['pe_blocaj'] as $cod => $n ) {
				WP_CLI::line( sprintf( '  %-20s %d', $cod, $n ) );
			}
		}
		if ( ! $r['proba'] && $r['atinse'] ) {
			WP_CLI::line( '' );
			WP_CLI::line( sprintf( 'Dacă e ceva greșit: wp ccs revino %d', $r['rulare'] ) );
		}
	}

	/**
	 * Dă înapoi o rulare de aplicare.
	 *
	 * ## OPTIONS
	 *
	 * <rulare>
	 * : Id-ul rulării, cel afișat de `wp ccs aplica`.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ccs revino 7
	 *
	 * @param array $args Argumentele.
	 * @return void
	 */
	public function revino( $args ) {
		$r = ccs_revino( (int) ( $args[0] ?? 0 ) );
		if ( is_wp_error( $r ) ) {
			WP_CLI::error( $r->get_error_message() );
		}
		$spuse = array();
		if ( $r['produse'] ) {
			$spuse[] = sprintf( '%d produse readuse la valorile dinainte (%d valori)', $r['produse'], $r['valori'] );
		}
		if ( ! empty( $r['sterse'] ) ) {
			$spuse[] = sprintf( '%d produse create de import, trecute la coșul de gunoi', $r['sterse'] );
		}
		if ( ! empty( $r['readuse'] ) ) {
			$spuse[] = sprintf( '%d produse scoase din coșul de gunoi', $r['readuse'] );
		}
		WP_CLI::success( ( $spuse ? implode( '. ', $spuse ) : 'Nimic de dat înapoi' ) . '.' );
		if ( $r['esuate'] ) {
			WP_CLI::line( sprintf( '  produse care nu mai există  %d', $r['esuate'] ) );
		}
	}

	/**
	 * Preia adresele pozelor, sau arată ce știm deja.
	 *
	 * ## OPTIONS
	 *
	 * [--preia]
	 * : Cere imaginile de la furnizor. Merge doar între 19:00 și 05:00.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ccs imagini
	 *     wp ccs imagini --preia
	 *
	 * @param array $args       Argumentele.
	 * @param array $asociative Opțiunile.
	 * @return void
	 */
	public function imagini( $args, $asociative ) {
		if ( isset( $asociative['preia'] ) ) {
			if ( ! ccs_api_fereastra_deschisa() ) {
				WP_CLI::warning( 'După ora serverului Conex, fereastra de noapte e închisă. Încerc oricum.' );
			}
			$inceput = microtime( true );
			$r       = ccs_imagini_preia();
			if ( is_wp_error( $r ) ) {
				WP_CLI::error( $r->get_error_message() );
			}
			WP_CLI::success( sprintf( '%d poze pentru %d produse, în %s secunde.', $r['poze'], $r['produse'], round( microtime( true ) - $inceput, 2 ) ) );
		}

		$s = ccs_imagini_stare();
		WP_CLI::line( '' );
		WP_CLI::line( sprintf( '  poze știute          %s', number_format( $s['poze'], 0, ',', '.' ) ) );
		WP_CLI::line(
			sprintf(
				'  produse cu poză      %s din %s (%d%%)',
				number_format( $s['produse'], 0, ',', '.' ),
				number_format( $s['din_catalog'], 0, ',', '.' ),
				$s['din_catalog'] ? round( 100 * $s['produse'] / $s['din_catalog'] ) : 0
			)
		);
		WP_CLI::line( sprintf( '  ultima preluare      %s', $s['ultima_preluare'] ? $s['ultima_preluare'] : 'niciodată' ) );
	}

	/**
	 * Trece pe stoc epuizat produsele care nu se mai pot aduce.
	 *
	 * Nu șterge nimic: produsele rămân pe site, cu pagina și adresa lor. Fără `--aplica` nu scrie nimic.
	 *
	 * ## OPTIONS
	 *
	 * [<grup>]
	 * : Ce grup: excluse, din_feed, manuale sau altele. Fără el, doar arată grupurile.
	 *
	 * [--aplica]
	 * : Scrie în WooCommerce.
	 *
	 * [--limita=<n>]
	 * : Oprește-te după n produse.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ccs retrage
	 *     wp ccs retrage excluse --aplica
	 *     wp ccs retrage din_feed --aplica --limita=50
	 *
	 * @param array $args       Argumentele.
	 * @param array $asociative Opțiunile.
	 * @return void
	 */
	public function retrage( $args, $asociative ) {
		$grupuri = ccs_retragere_grupuri();
		$etichete = array(
			'excluse'  => 'scoase din potrivire de client',
			'din_feed' => 'rămase din feed-ul Mega Drive',
			'manuale'  => 'introduse manual de echipă',
			'altele'   => 'alt tipar de cod',
		);

		$grup = (string) ( $args[0] ?? '' );
		if ( '' === $grup ) {
			WP_CLI::line( '' );
			WP_CLI::line( 'Produse care nu sunt în catalogul Conex:' );
			foreach ( $grupuri as $nume => $ids ) {
				WP_CLI::line(
					sprintf(
						'  %-10s %5d produse, %4d încă de vânzare   %s',
						$nume,
						count( $ids ),
						ccs_retragere_de_atins( array_keys( $ids ) ),
						$etichete[ $nume ] ?? ''
					)
				);
			}
			WP_CLI::line( '' );
			WP_CLI::line( 'Alege un grup: wp ccs retrage <grup> --aplica' );
			return;
		}

		if ( ! isset( $grupuri[ $grup ] ) ) {
			WP_CLI::error( sprintf( 'Grup necunoscut. Alege dintre: %s', implode( ', ', array_keys( $grupuri ) ) ) );
		}

		$ids      = array_keys( $grupuri[ $grup ] );
		$de_atins = ccs_retragere_de_atins( $ids );

		WP_CLI::line( '' );
		WP_CLI::line( sprintf( 'Grupul „%s”: %d produse, %d încă de vânzare.', $grup, count( $ids ), $de_atins ) );
		$n = 0;
		foreach ( $ids as $wc_id ) {
			if ( 'outofstock' === get_post_meta( $wc_id, '_stock_status', true ) ) {
				continue;
			}
			WP_CLI::line( sprintf( '  %-10s %s', $grupuri[ $grup ][ $wc_id ], mb_substr( html_entity_decode( get_the_title( $wc_id ) ), 0, 58 ) ) );
			if ( ++$n >= 8 ) {
				WP_CLI::line( sprintf( '  ... și încă %d', $de_atins - $n ) );
				break;
			}
		}

		if ( ! isset( $asociative['aplica'] ) ) {
			WP_CLI::line( '' );
			WP_CLI::success( 'Probă. N-am scris nimic. Adaugă --aplica când e bine.' );
			return;
		}

		$r = ccs_retrage(
			$ids,
			array(
				'limita' => (int) ( $asociative['limita'] ?? 0 ),
				'motiv'  => sprintf( 'grupul %s', $grup ),
			)
		);
		WP_CLI::line( '' );
		WP_CLI::success( sprintf( '%d produse trecute pe stoc epuizat (rularea #%d).', $r['atinse'], $r['rulare'] ) );
		WP_CLI::line( sprintf( '  erau deja epuizate  %d', $r['sarite'] ) );
		if ( $r['esuate'] ) {
			WP_CLI::line( sprintf( '  eșuate              %d', $r['esuate'] ) );
		}
		WP_CLI::line( '' );
		WP_CLI::line( sprintf( 'Dacă e ceva greșit: wp ccs revino %d', $r['rulare'] ) );
	}

	/**
	 * Produsele care au același cod dar alt conținut: ce s-ar schimba pe fiecare.
	 *
	 * Fără `--aplica` nu scrie nimic. Nu e o sincronizare obișnuită: anunțul de pe site își schimbă identitatea,
	 * nu doar prețul.
	 *
	 * ## OPTIONS
	 *
	 * [--aplica]
	 * : Scrie corecturile în WooCommerce.
	 *
	 * [--limita=<n>]
	 * : Oprește-te după n produse.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ccs corectii
	 *     wp ccs corectii --aplica --limita=3
	 *
	 * @param array $args       Argumentele.
	 * @param array $asociative Opțiunile.
	 * @return void
	 */
	public function corectii( $args, $asociative ) {
		$lista = ccs_corectii_lista();
		if ( ! $lista ) {
			WP_CLI::success( 'Nu e niciun produs de corectat.' );
			return;
		}

		$fara_categorie = 0;
		WP_CLI::line( '' );
		foreach ( $lista as $r ) {
			$t = $r['categ_nou'] ? get_term( $r['categ_nou'], 'product_cat' ) : null;
			$v = $r['categ_vechi'] ? get_term( $r['categ_vechi'], 'product_cat' ) : null;
			if ( ! $r['categ_nou'] ) {
				$fara_categorie++;
			}
			WP_CLI::line( sprintf( '  %-10s #%d', $r['sku'], $r['wc_id'] ) );
			WP_CLI::line( sprintf( '    acum  %-52s %8s lei  stoc %-4s  %s', mb_substr( html_entity_decode( $r['nume_vechi'] ), 0, 50 ), $r['pret_vechi'], $r['stoc_vechi'], $v && ! is_wp_error( $v ) ? $v->name : '-' ) );
			WP_CLI::line( sprintf( '    nou   %-52s %8s lei  stoc %-4d  %s', mb_substr( $r['nume_nou'], 0, 50 ), ccs_lei( $r['pret_nou'] ), $r['stoc_nou'], $t && ! is_wp_error( $t ) ? $t->name : 'NU ȘTIM' ) );
			WP_CLI::line( sprintf( '    %d poze la furnizor, nume asemănătoare %d%%', $r['poze'], (int) round( $r['asemanare'] * 100 ) ) );
			if ( ! empty( $r['altundeva'] ) ) {
				WP_CLI::line( sprintf( '    ATENȚIE: produsul de pe site există la Conex sub codul %s (%s). Ăsta se releagă, nu se înlocuiește.', $r['altundeva']['cod'], mb_substr( $r['altundeva']['nume'], 0, 44 ) ) );
			}
			WP_CLI::line( '' );
		}

		WP_CLI::line( sprintf( '%d produse de corectat, %d fără categorie știută.', count( $lista ), $fara_categorie ) );

		if ( ! isset( $asociative['aplica'] ) ) {
			WP_CLI::success( 'Probă. N-am scris nimic. Adaugă --aplica când e bine.' );
			return;
		}

		$r = ccs_corectii_aplica( array( 'limita' => (int) ( $asociative['limita'] ?? 0 ) ) );
		WP_CLI::line( '' );
		WP_CLI::success( sprintf( '%d din %d produse corectate (rularea #%d).', $r['atinse'], $r['de_atins'], $r['rulare'] ) );
		foreach ( $r['erori'] as $e ) {
			WP_CLI::warning( $e );
		}
		if ( $r['atinse'] ) {
			WP_CLI::line( sprintf( 'Dacă e ceva greșit: wp ccs revino %d', $r['rulare'] ) );
		}
	}

	/**
	 * Ia un produs de la furnizor și arată exact ce s-ar crea pe site.
	 *
	 * Nu scrie nimic. Preluarea e reală: catalogul se cere de la API, nu din tabelul propriu, pentru că asta se
	 * verifică aici. API-ul nu filtrează pe produs, deci vine tot catalogul, în jur de 7 secunde.
	 *
	 * ## OPTIONS
	 *
	 * <cod>
	 * : Codul EAN sau id-ul de produs de la furnizor.
	 *
	 * [--local]
	 * : Ia produsul din tabelul propriu, fără să ceară nimic de la furnizor.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ccs produs 01152
	 *     wp ccs produs 01152 --local
	 *
	 * @param array $args       Argumentele.
	 * @param array $asociative Opțiunile.
	 * @return void
	 */
	public function produs( $args, $asociative ) {
		global $wpdb;
		$cod = (string) ( $args[0] ?? '' );
		if ( '' === $cod ) {
			WP_CLI::error( 'Spune-mi un cod.' );
		}

		if ( isset( $asociative['local'] ) ) {
			$tabel = ccs_tabel( 'products' );
			$p     = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $tabel WHERE cod = %s OR product_id = %s", $cod, $cod ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
			if ( ! $p ) {
				WP_CLI::error( 'Codul nu e în tabelul propriu. Rulează `wp ccs catalog` sau scoate `--local`.' );
			}
			WP_CLI::line( 'Din tabelul propriu, fără să cer nimic de la furnizor.' );
		} else {
			$inceput = microtime( true );
			$p       = ccs_import_adu_produs( $cod );
			if ( is_wp_error( $p ) ) {
				WP_CLI::error( $p->get_error_message() );
			}
			WP_CLI::line( sprintf( 'Luat de la furnizor în %s secunde.', round( microtime( true ) - $inceput, 2 ) ) );
		}

		WP_CLI::line( '' );
		WP_CLI::line( 'CE TRIMITE FURNIZORUL' );
		foreach ( $p as $cheie => $valoare ) {
			if ( is_scalar( $valoare ) && '' !== (string) $valoare ) {
				WP_CLI::line( sprintf( '  %-24s %s', $cheie, $valoare ) );
			}
		}

		$r = ccs_import_pregateste( $p );

		WP_CLI::line( '' );
		WP_CLI::line( 'CE S-AR CREA PE SITE' );
		$d = $r['date'];
		WP_CLI::line( sprintf( '  %-24s %s', 'nume', $d['nume'] ) );
		WP_CLI::line( sprintf( '  %-24s %s', 'cod (SKU)', $d['sku'] ) );
		WP_CLI::line( sprintf( '  %-24s %s lei', 'preț', ccs_lei( $d['pret'] ) ) );
		WP_CLI::line( sprintf( '  %-24s %d bucăți, %s', 'stoc', $d['stoc'], $d['stare_stoc'] ) );
		$t = $d['categorie'] ? get_term( $d['categorie'], 'product_cat' ) : null;
		WP_CLI::line( sprintf( '  %-24s %s', 'categorie', $t && ! is_wp_error( $t ) ? $t->name : '(niciuna)' ) );
		foreach ( $d['atribute'] as $nume => $valoare ) {
			WP_CLI::line( sprintf( '  %-24s %s', mb_strtolower( $nume ), $valoare ) );
		}
		WP_CLI::line( sprintf( '  %-24s %s', 'descriere', $d['descriere'] ? $d['descriere'] : '(furnizorul nu trimite)' ) );
		if ( $d['poze'] ) {
			WP_CLI::line( sprintf( '  %-24s %d', 'poze', count( $d['poze'] ) ) );
			foreach ( $d['poze'] as $url ) {
				WP_CLI::line( sprintf( '  %-24s %s', '', $url ) );
			}
		} else {
			WP_CLI::line( sprintf( '  %-24s %s', 'poze', 'niciuna' ) );
		}

		WP_CLI::line( '' );
		WP_CLI::line( 'DE CE AȘA' );
		foreach ( $r['motive'] as $ce => $motiv ) {
			WP_CLI::line( sprintf( '  %-12s %s', $ce, $motiv ) );
		}

		WP_CLI::line( '' );
		if ( $r['gata'] ) {
			WP_CLI::success( 'Produsul e gata de importat. N-am scris nimic.' );
			return;
		}
		WP_CLI::warning( 'Produsul nu se poate importa acum:' );
		foreach ( $r['piedici'] as $cod_piedica => $motiv ) {
			WP_CLI::line( sprintf( '  %-16s %s', $cod_piedica, $motiv ) );
		}
	}

	/**
	 * Arată ce produse s-ar putea importa, și le aduce pe site cu `--creeaza`.
	 *
	 * Fără `--creeaza` nu scrie nimic: arată doar pâlnia și primele zece produse. Produsele se creează ca ciornă,
	 * cu poze, preț, stoc și categorie, și se leagă pe loc de furnizor. Cele fără categorie asociată nu se importă.
	 *
	 * ## OPTIONS
	 *
	 * [--creeaza]
	 * : Chiar creează produsele. Fără asta, comanda doar arată ce s-ar importa.
	 *
	 * [--limita=<n>]
	 * : Câte produse cel mult. Obligatoriu la prima rulare, dacă nu dai `--coduri` sau `--forteaza`.
	 *
	 * [--coduri=<lista>]
	 * : Coduri EAN despărțite prin virgulă, ca să probezi câteva produse anume.
	 *
	 * [--publica]
	 * : Publicate, nu ciorne. Implicit produsele rămân ciorne.
	 *
	 * [--proba]
	 * : Numără ce s-ar crea, fără să scrie.
	 *
	 * [--forteaza]
	 * : Fără limită. Creează tot ce e gata de import.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ccs import
	 *     wp ccs import --creeaza --coduri=5901234567890
	 *     wp ccs import --creeaza --limita=50
	 *
	 * @param array $args       Argumentele.
	 * @param array $asociative Opțiunile.
	 * @return void
	 */
	public function import( $args, $asociative ) {
		$face = isset( $asociative['creeaza'] );
		$r    = ccs_import_candidati();

		WP_CLI::success( sprintf( '%s produse se pot importa acum.', number_format( $r['gata'], 0, ',', '.' ) ) );
		WP_CLI::line( '' );
		WP_CLI::line( sprintf( '  produse Conex care nu sunt pe site   %s', number_format( $r['nelegate'], 0, ',', '.' ) ) );
		WP_CLI::line( sprintf( '    nu au poză                         %s', number_format( $r['fara_poza'], 0, ',', '.' ) ) );
		WP_CLI::line( sprintf( '    nu au cost                         %s', number_format( $r['fara_cost'], 0, ',', '.' ) ) );
		WP_CLI::line( sprintf( '    nu au stoc                         %s', number_format( $r['fara_stoc'], 0, ',', '.' ) ) );
		WP_CLI::line( sprintf( '    nu știm în ce categorie merg       %s', number_format( $r['fara_categorie'], 0, ',', '.' ) ) );
		WP_CLI::line( sprintf( '    gata de import                     %s', number_format( $r['gata'], 0, ',', '.' ) ) );

		if ( ! $r['lista'] ) {
			if ( $face ) {
				WP_CLI::line( '' );
				WP_CLI::warning( 'Nu e nimic de importat, deci n-am creat nimic.' );
			}
			return;
		}
		WP_CLI::line( '' );
		WP_CLI::line( 'Primele zece:' );
		foreach ( array_slice( $r['lista'], 0, 10 ) as $p ) {
			$t = get_term( ccs_categorie_pentru( $p ), 'product_cat' );
			WP_CLI::line(
				sprintf(
					'  %-10s %-44s %7.2f lei  stoc %3d  %d poze  -> %s',
					$p->cod,
					mb_substr( $p->nume, 0, 42 ),
					ccs_pret( $p->cost ),
					$p->stoc,
					$p->poze,
					$t && ! is_wp_error( $t ) ? $t->name : '?'
				)
			);
		}

		if ( ! $face ) {
			WP_CLI::line( '' );
			WP_CLI::line( 'Nimic nu s-a creat. Adaugă `--creeaza` când vrei să le aduci pe site.' );
			return;
		}

		$coduri = isset( $asociative['coduri'] ) ? array_filter( array_map( 'trim', explode( ',', (string) $asociative['coduri'] ) ) ) : array();
		$proba  = isset( $asociative['proba'] );

		$rezumat = ccs_import_executa(
			array(
				'limita'   => isset( $asociative['limita'] ) ? (int) $asociative['limita'] : 0,
				'proba'    => $proba,
				'publica'  => isset( $asociative['publica'] ),
				'forteaza' => isset( $asociative['forteaza'] ),
				'coduri'   => $coduri,
				'raport'   => $proba ? null : function ( $facut, $total ) {
					if ( 0 === $facut % 10 || $facut === $total ) {
						WP_CLI::line( sprintf( '  %d / %d', $facut, $total ) );
					}
				},
			)
		);

		if ( is_wp_error( $rezumat ) ) {
			WP_CLI::error( $rezumat->get_error_message() );
		}

		WP_CLI::line( '' );
		WP_CLI::success(
			sprintf(
				'%d produse create, %d poze%s.',
				$rezumat['create'],
				$rezumat['poze'],
				$rezumat['proba'] ? ' (probă, n-am scris nimic)' : ''
			)
		);
		WP_CLI::line( sprintf( '  sărite  %d', $rezumat['sarite'] ) );
		WP_CLI::line( sprintf( '  eșuate  %d', $rezumat['esuate'] ) );
		foreach ( $rezumat['motive'] as $motiv => $n ) {
			WP_CLI::line( sprintf( '    %-16s %d', $motiv, $n ) );
		}
		if ( $rezumat['rulare'] ) {
			WP_CLI::line( '' );
			WP_CLI::line( sprintf( 'Dacă trebuie date înapoi: wp ccs revino %d', $rezumat['rulare'] ) );
		}
	}

	/**
	 * Șterge și ascunde produsele pe care furnizorul nu le mai are deloc.
	 *
	 * Hotărârile vin dintr-un fișier JSON de forma `{ "sterge": [12, 34], "ascunde": [56] }`, făcut dintr-o
	 * măsurătoare Search Console. Vezi `includes/curatenie.php` pentru de ce nu e automat.
	 *
	 * Fără `--executa` nu scrie nimic: arată doar ce ar face.
	 *
	 * ## OPTIONS
	 *
	 * --fisier=<cale>
	 * : Fișierul JSON cu hotărârile.
	 *
	 * [--executa]
	 * : Chiar scrie. Fără asta, comanda doar arată ce ar face.
	 *
	 * [--limita=<n>]
	 * : Cel mult atâtea produse din fiecare listă.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ccs curata --fisier=curatenie.json
	 *     wp ccs curata --fisier=curatenie.json --executa --limita=50
	 *     wp ccs curata --fisier=curatenie.json --executa
	 *
	 * @param array $args       Argumentele.
	 * @param array $asociative Opțiunile.
	 * @return void
	 */
	public function curata( $args, $asociative ) {
		$cale = isset( $asociative['fisier'] ) ? (string) $asociative['fisier'] : '';
		if ( '' === $cale ) {
			WP_CLI::error( 'Lipsește --fisier=<cale>.' );
		}

		$decizii = ccs_curatenie_citeste( $cale );
		if ( is_wp_error( $decizii ) ) {
			WP_CLI::error( $decizii->get_error_message() );
		}

		$executa = isset( $asociative['executa'] );
		$rezumat = ccs_curata(
			$decizii,
			array(
				'limita' => isset( $asociative['limita'] ) ? (int) $asociative['limita'] : 0,
				'proba'  => ! $executa,
			)
		);
		if ( is_wp_error( $rezumat ) ) {
			WP_CLI::error( $rezumat->get_error_message() );
		}

		WP_CLI::line( sprintf( '  de șters   %s', number_format( $rezumat['de_sters'], 0, ',', '.' ) ) );
		WP_CLI::line( sprintf( '  de ascuns  %s', number_format( $rezumat['de_ascuns'], 0, ',', '.' ) ) );
		if ( $rezumat['in_amandoua'] ) {
			WP_CLI::warning(
				sprintf(
					'%d produse erau pe ambele liste. Le-am ascuns, nu le-am șters.',
					$rezumat['in_amandoua']
				)
			);
		}

		if ( ! $executa ) {
			WP_CLI::line( '' );
			WP_CLI::success( 'Probă. N-am scris nimic. Adaugă `--executa` când ești gata.' );
			return;
		}

		WP_CLI::line( '' );
		WP_CLI::success(
			sprintf(
				'%d produse la coșul de gunoi, %d ascunse din catalog.',
				$rezumat['sterse'],
				$rezumat['ascunse']
			)
		);
		WP_CLI::line( sprintf( '  trecute pe stoc epuizat  %d', $rezumat['epuizate'] ) );
		WP_CLI::line( sprintf( '  sărite, erau deja așa    %d', $rezumat['sarite'] ) );
		WP_CLI::line( sprintf( '  eșuate                   %d', $rezumat['esuate'] ) );
		WP_CLI::line( '' );
		WP_CLI::line( sprintf( 'Dacă trebuie date înapoi: wp ccs revino %d', $rezumat['rulare'] ) );
		WP_CLI::line( 'Atenție: WordPress golește singur coșul de gunoi după 30 de zile.' );
	}

	/**
	 * Învață maparea categoriilor din produsele deja legate și arată acoperirea.
	 *
	 * ## OPTIONS
	 *
	 * [--proba]
	 * : Doar calculează, fără să scrie regulile.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ccs categorii --proba
	 *     wp ccs categorii
	 *
	 * @param array $args       Argumentele.
	 * @param array $asociative Opțiunile.
	 * @return void
	 */
	public function categorii( $args, $asociative ) {
		global $wpdb;
		$proba = isset( $asociative['proba'] );
		$r     = ccs_categorii_invata( ! $proba );

		WP_CLI::success(
			sprintf(
				'%d reguli din %d produse legate%s.',
				count( $r['lista'] ),
				$r['produse'],
				$proba ? ' (probă, n-am scris nimic)' : ''
			)
		);
		WP_CLI::line( sprintf( '  căi la furnizor      %d', $r['cai'] ) );
		WP_CLI::line( sprintf( '  reguli pe cale       %d', $r['pe_cale'] ) );
		WP_CLI::line( sprintf( '  reguli pe cuvânt     %d', $r['pe_cuvant'] ) );
		WP_CLI::line( sprintf( '  căi nesigure         %d', $r['nesigure'] ) );

		if ( $r['pe_cuvant'] ) {
			WP_CLI::line( '' );
			WP_CLI::line( 'Căile care se împart după un cuvânt din nume:' );
			foreach ( $r['lista'] as $x ) {
				if ( '' === $x['contine'] ) {
					continue;
				}
				$t = get_term( $x['term_id'], 'product_cat' );
				WP_CLI::line(
					sprintf(
						'  %-44s + „%s” -> %s (%d produse)',
						mb_substr( $x['cale'], 0, 42 ),
						$x['contine'],
						$t && ! is_wp_error( $t ) ? $t->name : '?',
						$x['produse']
					)
				);
			}
		}

		// Cât din catalogul nelegat ar ști unde să meargă.
		$tp   = ccs_tabel( 'products' );
		$tl   = ccs_tabel( 'links' );
		$noi  = $wpdb->get_results( "SELECT c.cat1, c.cat2, c.cat3, c.nume FROM $tp c LEFT JOIN $tl l ON l.product_id = c.product_id WHERE l.product_id IS NULL" ); // phpcs:ignore WordPress.DB.PreparedSQL
		$stiu = 0;
		$fara = array();
		foreach ( $noi as $p ) {
			if ( ccs_categorie_pentru( $p ) ) {
				$stiu++;
			} else {
				$cale          = ccs_categorie_cale( $p );
				$fara[ $cale ] = ( $fara[ $cale ] ?? 0 ) + 1;
			}
		}
		arsort( $fara );

		WP_CLI::line( '' );
		WP_CLI::line( sprintf( 'Din %d produse Conex care nu sunt pe site:', count( $noi ) ) );
		WP_CLI::line( sprintf( '  știm unde merg      %d', $stiu ) );
		WP_CLI::line( sprintf( '  nu știm             %d, pe %d căi', count( $noi ) - $stiu, count( $fara ) ) );
		foreach ( array_slice( $fara, 0, 8, true ) as $cale => $n ) {
			WP_CLI::line( sprintf( '    %5d  %s', $n, mb_substr( $cale, 0, 62 ) ) );
		}
	}

	/**
	 * Arată joburile programate, sau le pornește, oprește și rulează pe loc.
	 *
	 * ## OPTIONS
	 *
	 * [--porneste]
	 * : Programează joburile și pornește setarea.
	 *
	 * [--opreste]
	 * : Scoate joburile din coadă și oprește setarea.
	 *
	 * [--acum]
	 * : Rulează jobul de catalog pe loc, fără să aștepte programarea.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ccs joburi
	 *     wp ccs joburi --porneste
	 *     wp ccs joburi --acum
	 *
	 * @param array $args       Argumentele.
	 * @param array $asociative Opțiunile.
	 * @return void
	 */
	public function joburi( $args, $asociative ) {
		if ( isset( $asociative['porneste'] ) || isset( $asociative['opreste'] ) ) {
			$pornit = isset( $asociative['porneste'] );
			$setari = (array) get_option( CCS_OPTIUNE_SETARI, array() );
			$setari['joburi_pornite'] = $pornit;
			update_option( CCS_OPTIUNE_SETARI, $setari );
			WP_CLI::success( $pornit ? 'Joburile sunt pornite.' : 'Joburile sunt oprite.' );
		}

		if ( isset( $asociative['acum'] ) ) {
			$inceput = microtime( true );
			WP_CLI::line( 'Rulez jobul de catalog...' );
			ccs_job_catalog();
			WP_CLI::success( sprintf( 'Gata, în %s secunde.', round( microtime( true ) - $inceput, 2 ) ) );
		}

		$setari = ccs_setari();
		WP_CLI::line( '' );
		WP_CLI::line( sprintf( '  %-22s %s', 'setarea', $setari['joburi_pornite'] ? 'pornite' : 'oprite' ) );
		WP_CLI::line( sprintf( '  %-22s %s', 'modul raport', $setari['mod_raport'] ? 'pornit, nu se scrie în WooCommerce' : 'oprit, se scrie în WooCommerce' ) );
		WP_CLI::line( '' );

		foreach ( ccs_joburi_stare() as $hook => $j ) {
			WP_CLI::line( sprintf( '  %s', $j['nume'] ) );
			WP_CLI::line( sprintf( '    %-20s %s', 'hook', $hook ) );
			WP_CLI::line( sprintf( '    %-20s la %d minute', 'interval', $j['interval'] / MINUTE_IN_SECONDS ) );
			WP_CLI::line(
				sprintf(
					'    %-20s %s',
					'următoarea rulare',
					$j['urmatoarea'] ? wp_date( 'j F Y, H:i', $j['urmatoarea'] ) : 'neprogramat'
				)
			);
		}
	}

	/**
	 * Arată sau schimbă o setare.
	 *
	 * ## OPTIONS
	 *
	 * [<cheie>]
	 * : Setarea. Fără ea, se afișează toate.
	 *
	 * [<valoare>]
	 * : Valoarea nouă. Fără ea, se afișează doar.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ccs setare
	 *     wp ccs setare adaos
	 *     wp ccs setare mod_raport 0
	 *
	 * @param array $args Argumentele.
	 * @return void
	 */
	public function setare( $args ) {
		$implicite = ccs_setari_implicite();
		$cheie     = $args[0] ?? '';

		if ( '' === $cheie ) {
			$setari = ccs_setari();
			foreach ( $implicite as $k => $v ) {
				if ( 'token' === $k ) {
					continue; // Tokenul nu se afișează niciodată.
				}
				$acum = $setari[ $k ];
				WP_CLI::line(
					sprintf(
						'  %-22s %-10s %s',
						$k,
						is_bool( $acum ) ? ( $acum ? 'da' : 'nu' ) : $acum,
						$acum === $v ? '' : sprintf( '(implicit: %s)', is_bool( $v ) ? ( $v ? 'da' : 'nu' ) : $v )
					)
				);
			}
			return;
		}

		if ( 'token' === $cheie ) {
			WP_CLI::error( 'Tokenul se pune în wp-config, prin constanta CCS_API_TOKEN, nu de aici.' );
		}
		if ( ! array_key_exists( $cheie, $implicite ) ) {
			WP_CLI::error( sprintf( 'Nu există setarea „%s”.', $cheie ) );
		}

		$setari = (array) get_option( CCS_OPTIUNE_SETARI, array() );

		if ( ! isset( $args[1] ) ) {
			$efectiv = ccs_setari();
			WP_CLI::line( is_bool( $efectiv[ $cheie ] ) ? ( $efectiv[ $cheie ] ? 'da' : 'nu' ) : $efectiv[ $cheie ] );
			return;
		}

		$valoare = $args[1];
		if ( is_bool( $implicite[ $cheie ] ) ) {
			$valoare = in_array( strtolower( $valoare ), array( '1', 'da', 'yes', 'true', 'on' ), true );
		} elseif ( is_int( $implicite[ $cheie ] ) ) {
			$valoare = (int) $valoare;
		} elseif ( is_float( $implicite[ $cheie ] ) ) {
			$valoare = (float) str_replace( ',', '.', $valoare );
		}

		$setari[ $cheie ] = $valoare;
		update_option( CCS_OPTIUNE_SETARI, $setari );
		WP_CLI::success( sprintf( '%s = %s', $cheie, is_bool( $valoare ) ? ( $valoare ? 'da' : 'nu' ) : $valoare ) );
	}

	/**
	 * Afișează rezumatul unui plan.
	 *
	 * @param array $r Rezumatul.
	 * @return void
	 */
	private static function arata_rezumat_plan( $r ) {
		WP_CLI::line( sprintf( '  produse legate       %d', $r['produse'] ) );
		WP_CLI::line( sprintf( '  cu ceva de schimbat  %d', $r['de_schimbat'] ) );
		WP_CLI::line( sprintf( '  preț de schimbat     %d', $r['pret'] ) );
		WP_CLI::line( sprintf( '  stoc de schimbat     %d', $r['stoc'] ) );
		WP_CLI::line( sprintf( '  de trecut epuizat    %d', $r['de_epuizat'] ) );
		WP_CLI::line( sprintf( '  de reactivat         %d', $r['de_reactivat'] ) );
		WP_CLI::line( sprintf( '  vândute sub cost     %d', $r['sub_cost'] ) );
		WP_CLI::line( sprintf( '  fără nicio schimbare %d', $r['neatinse'] ) );
		if ( $r['blocate'] ) {
			WP_CLI::line( sprintf( '  lăsate deoparte      %d', $r['blocate'] ) );
			foreach ( $r['pe_blocaj'] as $cod => $n ) {
				WP_CLI::line( sprintf( '    %-20s %d', $cod, $n ) );
			}
		}
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
