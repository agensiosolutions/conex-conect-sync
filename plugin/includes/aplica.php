<?php
/**
 * Scrierea în WooCommerce.
 *
 * Singurul loc din plugin care modifică produse. Totul trece prin CRUD-ul WooCommerce (`wc_get_product`, `save()`),
 * nu prin `update_post_meta`: altfel prețul s-ar schimba în admin, dar `wc_product_meta_lookup` ar rămâne vechi și
 * produsul s-ar filtra, sorta și afișa în magazin cu prețul de ieri.
 *
 * Trei chei trebuie potrivite ca să se scrie ceva:
 *   1. `mod_raport` oprit (`wp ccs setare mod_raport 0`),
 *   2. rularea să nu depășească `prag_schimbari`, altfel cere `--forteaza`,
 *   3. produsul să nu aibă blocaje în plan (preț manual, cost zero, scădere mare).
 *
 * Fiecare valoare schimbată se scrie și în `ccs_modificari`, cu valoarea veche, ca o rulare întreagă să poată fi
 * dată înapoi cu `wp ccs revino <rulare>`.
 *
 * @package conex-conect-sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Scrie un produs prin CRUD-ul WooCommerce și întoarce ce s-a schimbat efectiv.
 *
 * @param array $rand  Rândul de plan.
 * @param array $tipuri Ce se aplică: 'pret', 'stoc'.
 * @return array|WP_Error Lista modificărilor: array( camp, vechi, nou ), sau eroare.
 */
function ccs_aplica_produs( $rand, $tipuri ) {
	$produs = wc_get_product( $rand['wc_id'] );
	if ( ! $produs ) {
		return new WP_Error(
			'ccs_produs_lipsa',
			sprintf(
				/* translators: %d: id-ul produsului. */
				__( 'Produsul %d nu mai există.', 'conex-conect-sync' ),
				$rand['wc_id']
			)
		);
	}

	$modificari = array();
	$face_pret  = in_array( 'pret', $tipuri, true ) && in_array( 'pret', $rand['actiuni'], true );
	$face_stoc  = in_array( 'stoc', $tipuri, true )
		&& ( in_array( 'stoc', $rand['actiuni'], true ) || in_array( 'epuizat', $rand['actiuni'], true ) || in_array( 'reactivat', $rand['actiuni'], true ) );

	if ( $face_pret ) {
		$vechi = (string) $produs->get_regular_price( 'edit' );
		$nou   = (string) wc_format_decimal( $rand['pret_tinta'], wc_get_price_decimals() );
		if ( $vechi !== $nou ) {
			$produs->set_regular_price( $nou );
			$modificari[] = array(
				'camp'  => 'pret',
				'vechi' => $vechi,
				'nou'   => $nou,
			);
		}
	}

	$stare_veche = $produs->get_stock_status( 'edit' );

	if ( $face_stoc ) {
		$vechi = $produs->get_stock_quantity( 'edit' );
		if ( (int) $vechi !== (int) $rand['stoc_tinta'] ) {
			$produs->set_stock_quantity( (int) $rand['stoc_tinta'] );
			$modificari[] = array(
				'camp'  => 'stoc',
				'vechi' => null === $vechi ? '' : (string) (int) $vechi,
				'nou'   => (string) (int) $rand['stoc_tinta'],
			);
		}
		// Starea stocului o lăsăm pe seama lui `WC_Product::validate_props()`, care o scoate din cantitate la
		// `save()`. O punem cu mâna doar când cantitatea e deja bună dar starea nu: un produs cu 5 bucăți pe stoc
		// marcat „epuizat” de om. Fără asta, produsul ar rămâne ascuns pentru totdeauna, pentru că nimic nu l-ar
		// atinge vreodată.
		if ( $stare_veche !== $rand['stare_tinta'] && ! $modificari ) {
			$produs->set_stock_status( $rand['stare_tinta'] );
		}
	}

	if ( ! $modificari && $stare_veche === $produs->get_stock_status( 'edit' ) ) {
		return array();
	}

	$produs->save();

	// După salvare, `$produs` are deja starea recalculată în memorie: nu mai e nevoie de o citire din baza de date.
	$stare_nou = $produs->get_stock_status( 'edit' );
	if ( $stare_veche !== $stare_nou ) {
		$modificari[] = array(
			'camp'  => 'stare_stoc',
			'vechi' => (string) $stare_veche,
			'nou'   => (string) $stare_nou,
		);
	}

	return $modificari;
}

/**
 * Ia lacătul de scriere, apoi aplică.
 *
 * Două operații care schimbă produse nu pot rula în paralel: jobul de noapte și o comandă dată de om ar scrie
 * una peste alta. Proba nu cere lacătul, fiindcă nu scrie nimic.
 *
 * @param array $opt Vezi `ccs_aplica_lucreaza()`.
 * @return array|WP_Error
 */
function ccs_aplica( $opt = array() ) {
	if ( ! empty( $opt['proba'] ) ) {
		return ccs_aplica_lucreaza( $opt );
	}
	return ccs_cu_lacat(
		__( 'aplicarea prețurilor și a stocurilor', 'conex-conect-sync' ),
		function () use ( $opt ) {
			return ccs_aplica_lucreaza( $opt );
		}
	);
}

/**
 * Aplică planul în WooCommerce.
 *
 * @param array $opt tip ('tot', 'pret', 'stoc'), limita, proba, forteaza.
 * @return array|WP_Error Rezumatul, sau eroare.
 */
function ccs_aplica_lucreaza( $opt = array() ) {
	global $wpdb;
	$opt = wp_parse_args(
		$opt,
		array(
			'tip'      => 'tot',
			'limita'   => 0,
			'proba'    => false,
			'forteaza' => false,
			'raport'   => null, // Callback de progres: function( $facut, $total ).
		)
	);

	if ( ! function_exists( 'wc_get_product' ) ) {
		return new WP_Error( 'ccs_fara_woo', __( 'WooCommerce nu e activ. Fără el nu se poate scrie nimic.', 'conex-conect-sync' ) );
	}

	$setari = ccs_setari();
	if ( ! $opt['proba'] && $setari['mod_raport'] ) {
		return new WP_Error(
			'ccs_mod_raport',
			__( 'Modul raport e pornit, deci nu scriu nimic. Oprește-l cu `wp ccs setare mod_raport 0` când ești gata.', 'conex-conect-sync' )
		);
	}

	$tipuri = 'tot' === $opt['tip'] ? array( 'pret', 'stoc' ) : array( $opt['tip'] );

	$plan = ccs_plan( array( 'doar_actiuni' => true ) );
	if ( is_wp_error( $plan ) ) {
		return $plan;
	}

	// Numai rândurile pe care tipul cerut le atinge.
	$randuri = array();
	foreach ( $plan['randuri'] as $r ) {
		$atinge = false;
		foreach ( $r['actiuni'] as $a ) {
			if ( in_array( 'pret', $tipuri, true ) && 'pret' === $a ) {
				$atinge = true;
			}
			if ( in_array( 'stoc', $tipuri, true ) && in_array( $a, array( 'stoc', 'epuizat', 'reactivat' ), true ) ) {
				$atinge = true;
			}
		}
		if ( $atinge ) {
			$randuri[] = $r;
		}
	}

	$total_legate = (int) $plan['rezumat']['produse'];
	$prag         = (float) $setari['prag_schimbari'];
	if ( ! $opt['proba'] && ! $opt['forteaza'] && $total_legate > 0 && $prag > 0
		&& count( $randuri ) > $total_legate * $prag ) {
		return new WP_Error(
			'ccs_prea_multe',
			sprintf(
				/* translators: 1: produse de schimbat, 2: produse legate, 3: pragul în procente. */
				__( 'Rularea ar atinge %1$d din %2$d produse legate, peste pragul de %3$d%%. M-am oprit fără să scriu. Verifică raportul, apoi rulează cu `--forteaza` dacă așa trebuie.', 'conex-conect-sync' ),
				count( $randuri ),
				$total_legate,
				(int) round( $prag * 100 )
			)
		);
	}

	if ( $opt['limita'] ) {
		$randuri = array_slice( $randuri, 0, (int) $opt['limita'] );
	}

	$rezumat = array(
		'rulare'     => 0,
		'de_atins'   => count( $randuri ),
		'atinse'     => 0,
		'pret'       => 0,
		'stoc'       => 0,
		'stare_stoc' => 0,
		'esuate'     => 0,
		'proba'      => (bool) $opt['proba'],
		'blocate'    => (int) $plan['rezumat']['blocate'],
		'pe_blocaj'  => $plan['rezumat']['pe_blocaj'],
	);

	if ( $opt['proba'] ) {
		foreach ( $randuri as $r ) {
			foreach ( $r['actiuni'] as $a ) {
				if ( 'pret' === $a && in_array( 'pret', $tipuri, true ) ) {
					$rezumat['pret']++;
				}
				if ( 'stoc' === $a && in_array( 'stoc', $tipuri, true ) ) {
					$rezumat['stoc']++;
				}
				if ( in_array( $a, array( 'epuizat', 'reactivat' ), true ) && in_array( 'stoc', $tipuri, true ) ) {
					$rezumat['stare_stoc']++;
				}
			}
		}
		$rezumat['atinse'] = count( $randuri );
		return $rezumat;
	}

	$rulare             = ccs_rulare_start( 'aplicare_' . $opt['tip'] );
	$rezumat['rulare']  = $rulare;
	$tabel_modificari   = ccs_tabel( 'modificari' );
	$acum               = current_time( 'mysql' );
	$lot                = max( 1, (int) $setari['lot_aplicare'] );
	$facut              = 0;

	foreach ( $randuri as $r ) {
		$modificari = ccs_aplica_produs( $r, $tipuri );

		if ( is_wp_error( $modificari ) ) {
			$rezumat['esuate']++;
			ccs_jurnal( 'avertisment', $modificari->get_error_message() );
		} elseif ( $modificari ) {
			$rezumat['atinse']++;
			foreach ( $modificari as $m ) {
				$rezumat[ $m['camp'] ]++;
				$wpdb->insert(
					$tabel_modificari,
					array(
						'rulare_id'     => $rulare,
						'wc_product_id' => $r['wc_id'],
						'camp'          => $m['camp'],
						'vechi'         => $m['vechi'],
						'nou'           => $m['nou'],
						'aplicat_la'    => $acum,
					)
				);
			}
		}

		$facut++;
		if ( 0 === $facut % $lot ) {
			// Pe mii de produse, cache-ul de obiecte al WordPress crește până umple memoria dacă nu e golit.
			if ( function_exists( 'wp_cache_flush' ) ) {
				wp_cache_flush();
			}
			if ( is_callable( $opt['raport'] ) ) {
				call_user_func( $opt['raport'], $facut, count( $randuri ) );
			}
		}
	}

	if ( is_callable( $opt['raport'] ) ) {
		call_user_func( $opt['raport'], $facut, count( $randuri ) );
	}

	ccs_rulare_stop( $rulare, $rezumat['esuate'] ? 'gata_cu_erori' : 'gata', $rezumat );

	return $rezumat;
}

/**
 * Dă înapoi o rulare de aplicare.
 *
 * Scrie valorile vechi din `ccs_modificari`, tot prin CRUD, și marchează rândurile ca revenite. Modificările deja
 * revenite se sar, deci comanda poate fi rulată de două ori fără să strice nimic.
 *
 * @param int $rulare_id Id-ul rulării de dat înapoi.
 * @return array|WP_Error Rezumatul, sau eroare.
 */
function ccs_revino( $rulare_id ) {
	global $wpdb;
	if ( ! function_exists( 'wc_get_product' ) ) {
		return new WP_Error( 'ccs_fara_woo', __( 'WooCommerce nu e activ.', 'conex-conect-sync' ) );
	}

	$tabel = ccs_tabel( 'modificari' );
	$randuri = $wpdb->get_results(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL
			"SELECT * FROM $tabel WHERE rulare_id = %d AND revenit_la IS NULL ORDER BY id DESC",
			(int) $rulare_id
		)
	);
	if ( ! $randuri ) {
		return new WP_Error(
			'ccs_nimic_de_revenit',
			sprintf(
				/* translators: %d: id-ul rulării. */
				__( 'Rularea %d nu are modificări de dat înapoi.', 'conex-conect-sync' ),
				(int) $rulare_id
			)
		);
	}

	// Grupăm pe produs, ca să salvăm o dată chiar dacă s-au schimbat și prețul și stocul.
	$pe_produs = array();
	foreach ( $randuri as $r ) {
		$pe_produs[ (int) $r->wc_product_id ][] = $r;
	}

	$rulare  = ccs_rulare_start( 'revenire' );
	$acum    = current_time( 'mysql' );
	$rezumat = array(
		'rulare'  => $rulare,
		'produse' => 0,
		'valori'  => 0,
		'sterse'  => 0,
		'readuse' => 0,
		'esuate'  => 0,
	);

	foreach ( $pe_produs as $wc_id => $ale_lui ) {
		// Un produs trimis la coșul de gunoi de curățenie se scoate de acolo întâi: `wc_get_product()` nu-l mai
		// întoarce după ștergere, deci orice altă revenire pe el ar eșua dacă n-ar fi scos mai întâi.
		$scoase = array();
		foreach ( $ale_lui as $m ) {
			if ( 'sters' === $m->camp ) {
				wp_untrash_post( $wc_id );
				// `wp_untrash_post()` readuce produsul ca ciornă pe unele versiuni. Punem starea pe care am
				// găsit-o, ca produsul să se întoarcă exact cum era, nu aproape cum era.
				if ( '' !== (string) $m->vechi && get_post_status( $wc_id ) !== $m->vechi ) {
					wp_update_post(
						array(
							'ID'          => $wc_id,
							'post_status' => (string) $m->vechi,
						)
					);
				}
				$scoase[] = (int) $m->id;
			}
		}
		if ( $scoase ) {
			$rezumat['readuse'] = ( $rezumat['readuse'] ?? 0 ) + 1;
			$locuri_s           = implode( ',', array_fill( 0, count( $scoase ), '%d' ) );
			$wpdb->query(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL
					"UPDATE $tabel SET revenit_la = %s WHERE id IN ($locuri_s)",
					array_merge( array( $acum ), $scoase )
				)
			);
		}

		$produs = wc_get_product( $wc_id );
		if ( ! $produs ) {
			if ( ! $scoase ) {
				$rezumat['esuate']++;
			}
			continue;
		}
		// Un produs creat de import nu se dă înapoi câmp cu câmp: se duce la coșul de gunoi, întreg. Nu se șterge
		// definitiv, ca pozele descărcate și orice legătură făcută între timp să se poată recupera.
		$create = array();
		foreach ( $ale_lui as $m ) {
			if ( 'creat' === $m->camp ) {
				$create[] = (int) $m->id;
			}
		}
		if ( $create ) {
			wp_trash_post( $wc_id );
			$wpdb->delete( ccs_tabel( 'links' ), array( 'wc_product_id' => $wc_id ) );
			$rezumat['sterse']++;
			$locuri = implode( ',', array_fill( 0, count( $create ), '%d' ) );
			$wpdb->query(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL
					"UPDATE $tabel SET revenit_la = %s WHERE id IN ($locuri)",
					array_merge( array( $acum ), $create )
				)
			);
			continue;
		}

		$ids_revenite = array();
		foreach ( $ale_lui as $m ) {
			if ( 'pret' === $m->camp ) {
				$produs->set_regular_price( $m->vechi );
			} elseif ( 'stoc' === $m->camp ) {
				$produs->set_stock_quantity( '' === $m->vechi ? null : (int) $m->vechi );
			} elseif ( 'vizibilitate' === $m->camp ) {
				$produs->set_catalog_visibility( $m->vechi );
			} elseif ( 'stare_stoc' === $m->camp ) {
				// Starea o lăsăm pe seama cantității readuse, exact cum a fost pusă. O setăm doar dacă produsul nu
				// gestionează stocul, caz în care nimic n-o recalculează.
				if ( ! $produs->get_manage_stock( 'edit' ) ) {
					$produs->set_stock_status( $m->vechi );
				}
			} else {
				continue;
			}
			$ids_revenite[] = (int) $m->id;
		}
		if ( ! $ids_revenite ) {
			continue;
		}
		$produs->save();
		$rezumat['produse']++;
		$rezumat['valori'] += count( $ids_revenite );
		$locuri = implode( ',', array_fill( 0, count( $ids_revenite ), '%d' ) );
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL
				"UPDATE $tabel SET revenit_la = %s WHERE id IN ($locuri)",
				array_merge( array( $acum ), $ids_revenite )
			)
		);
	}

	ccs_rulare_stop( $rulare, 'gata', $rezumat );
	return $rezumat;
}
