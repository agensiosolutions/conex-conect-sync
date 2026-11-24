<?php
/**
 * Maparea categoriilor furnizorului pe categoriile magazinului.
 *
 * Cele două taxonomii sunt construite diferit și nu se pot traduce mecanic. Conex împarte catalogul pe sisteme ale
 * mașinii („Elemente de caroserie / Elemente frontale / Bara fata”), magazinul îl împarte pe logica vânzării
 * („Accesorii auto / Covorașe auto”). Nimeni n-a scris vreodată corespondența dintre ele.
 *
 * Dar magazinul are 3.954 de produse deja legate, fiecare cu o cale la furnizor și o categorie pe site. Alea sunt
 * răspunsuri date deja de om, fără să-și dea seama. Le numărăm și scoatem regula.
 *
 * Două feluri de reguli:
 *   - pe cale: toată calea furnizorului merge într-o categorie de pe site;
 *   - pe cale plus un cuvânt din nume: aceeași cale se împarte în două, după ce e produsul.
 *
 * Al doilea fel a ieșit din date, nu dintr-o presupunere: la Cez Auto, calea „Covorase auto” ține și covorașe (744,
 * în „Covorașe auto”) și covoare de portbagaj (431, în „Accesorii interior”). Cuvântul care le desparte e
 * „PORTBAGAJ”. `ccs_categorii_invata()` caută singură astfel de cuvinte.
 *
 * @package conex-conect-sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Calea unui produs la furnizor, ca text.
 *
 * @param array|object $p Produsul, din catalog sau din API.
 * @return string Calea.
 */
function ccs_categorie_cale( $p ) {
	$p = (array) $p;
	$c = array();
	foreach ( array( 'cat1', 'cat2', 'cat3' ) as $cheie ) {
		$c[] = trim( (string) ( $p[ $cheie ] ?? '' ) );
	}
	return implode( ' / ', $c );
}

/**
 * Categoria cea mai adâncă a unui produs de pe site.
 *
 * Luăm copilul, nu părintele: WooCommerce pune oricum produsul și în părinte, iar dacă învățăm din părinte toate
 * căile ajung în „Accesorii auto” și maparea nu mai spune nimic.
 *
 * @param int $wc_id Produsul.
 * @return int term_id, sau 0.
 */
function ccs_categorie_adanca( $wc_id ) {
	$termeni = wp_get_post_terms( $wc_id, 'product_cat' );
	if ( is_wp_error( $termeni ) || ! $termeni ) {
		return 0;
	}

	$ales     = 0;
	$adancime = -1;
	foreach ( $termeni as $t ) {
		$d = count( get_ancestors( $t->term_id, 'product_cat' ) );
		if ( $d > $adancime ) {
			$adancime = $d;
			$ales     = (int) $t->term_id;
		}
	}
	return $ales;
}

/**
 * Cuvintele dintr-un nume, pentru regulile pe cuvânt.
 *
 * @param string $nume Numele.
 * @return array Cuvintele.
 */
function ccs_categorie_cuvinte( $nume ) {
	return function_exists( 'ccs_nume_cuvinte' ) ? ccs_nume_cuvinte( $nume ) : array();
}

/**
 * Cuvintele care sunt nume de brand în catalogul furnizorului.
 *
 * @return array Cuvântul => true.
 */
function ccs_categorii_branduri() {
	global $wpdb;
	static $branduri = null;
	if ( null !== $branduri ) {
		return $branduri;
	}
	$branduri = array();
	$tabel    = ccs_tabel( 'products' );
	foreach ( (array) $wpdb->get_col( "SELECT DISTINCT brand FROM $tabel WHERE brand <> ''" ) as $b ) { // phpcs:ignore WordPress.DB.PreparedSQL
		foreach ( ccs_categorie_cuvinte( $b ) as $c ) {
			$branduri[ $c ] = true;
		}
	}
	return $branduri;
}

/**
 * Caută un cuvânt care desparte curat două grupuri de produse.
 *
 * Un cuvânt bun apare aproape în toate produsele dintr-o categorie și aproape în niciunul din celelalte. Fără
 * condiția a doua, „AUTO” ar părea o regulă excelentă.
 *
 * Cuvintele care sunt de fapt branduri se sar. Măsurat pe datele reale: „HICO” și „JBM” despart perfect grupurile,
 * pentru că cineva a pus cândva toate produsele mărcii într-o categorie. Dar o regulă pe brand spune ce s-a
 * întâmplat în trecut, nu ce fel de produs e, și ar trimite greșit orice produs nou al mărcii.
 *
 * @param array $ale_lui  Numele produselor din categoria căutată.
 * @param array $ale_altora Numele celorlalte produse de pe aceeași cale.
 * @return string Cuvântul, sau '' dacă niciunul nu desparte destul de curat.
 */
function ccs_categorie_cuvant_separator( $ale_lui, $ale_altora ) {
	if ( count( $ale_lui ) < 5 || ! $ale_altora ) {
		return '';
	}

	$in_grup = array();
	foreach ( $ale_lui as $nume ) {
		foreach ( array_unique( ccs_categorie_cuvinte( $nume ) ) as $c ) {
			$in_grup[ $c ] = ( $in_grup[ $c ] ?? 0 ) + 1;
		}
	}
	$in_rest = array();
	foreach ( $ale_altora as $nume ) {
		foreach ( array_unique( ccs_categorie_cuvinte( $nume ) ) as $c ) {
			$in_rest[ $c ] = ( $in_rest[ $c ] ?? 0 ) + 1;
		}
	}

	$branduri     = ccs_categorii_branduri();
	$cel_mai_bun  = '';
	$cel_mai_scor = 0;
	foreach ( $in_grup as $cuvant => $n ) {
		if ( isset( $branduri[ $cuvant ] ) ) {
			continue;
		}
		$acoperire = $n / count( $ale_lui );
		$scurgere  = ( $in_rest[ $cuvant ] ?? 0 ) / count( $ale_altora );
		if ( $acoperire < 0.85 || $scurgere > 0.1 ) {
			continue;
		}
		$scor = $acoperire - $scurgere;
		if ( $scor > $cel_mai_scor ) {
			$cel_mai_scor = $scor;
			$cel_mai_bun  = $cuvant;
		}
	}
	return $cel_mai_bun;
}

/**
 * Învață maparea din produsele deja legate.
 *
 * @param bool $scrie Dacă false, doar calculează.
 * @return array Rezumatul, cu lista de reguli.
 */
function ccs_categorii_invata( $scrie = true ) {
	global $wpdb;
	$tp = ccs_tabel( 'products' );
	$tl = ccs_tabel( 'links' );

	$randuri = $wpdb->get_results(
		"SELECT c.cat1, c.cat2, c.cat3, c.nume, l.wc_product_id
		 FROM $tl l INNER JOIN $tp c ON c.product_id = l.product_id
		 WHERE l.incredere IN ('A','B')" // phpcs:ignore WordPress.DB.PreparedSQL
	);

	// Pe fiecare cale: ce categorii de pe site au produsele ei și cum se numesc.
	$pe_cale = array();
	foreach ( $randuri as $r ) {
		$term = ccs_categorie_adanca( (int) $r->wc_product_id );
		if ( ! $term ) {
			continue;
		}
		$cale = ccs_categorie_cale( $r );
		$pe_cale[ $cale ][ $term ][] = (string) $r->nume;
	}

	$reguli  = array();
	$rezumat = array(
		'cai'        => count( $pe_cale ),
		'pe_cale'    => 0,
		'pe_cuvant'  => 0,
		'nesigure'   => 0,
		'produse'    => count( $randuri ),
	);

	foreach ( $pe_cale as $cale => $categorii ) {
		$total = 0;
		foreach ( $categorii as $nume ) {
			$total += count( $nume );
		}
		uasort(
			$categorii,
			static function ( $a, $b ) {
				return count( $b ) <=> count( $a );
			}
		);

		$principala = array_key_first( $categorii );
		$incredere  = $total ? count( $categorii[ $principala ] ) / $total : 0;

		// Când o cale e împărțită, căutăm cuvântul care desparte categoriile secundare de cea principală.
		if ( $incredere < 0.85 && count( $categorii ) > 1 ) {
			foreach ( $categorii as $term => $nume ) {
				if ( $term === $principala ) {
					continue;
				}
				$altele = array();
				foreach ( $categorii as $t2 => $n2 ) {
					if ( $t2 !== $term ) {
						$altele = array_merge( $altele, $n2 );
					}
				}
				$cuvant = ccs_categorie_cuvant_separator( $nume, $altele );
				if ( $cuvant ) {
					$reguli[] = array(
						'cale'      => $cale,
						'contine'   => $cuvant,
						'term_id'   => (int) $term,
						'incredere' => 100,
						'produse'   => count( $nume ),
					);
					$rezumat['pe_cuvant']++;
					// Produsele prinse de regula pe cuvânt nu mai contează pentru încrederea căii.
					$total     -= count( $nume );
					$incredere  = $total ? count( $categorii[ $principala ] ) / $total : 0;
				}
			}
		}

		$reguli[] = array(
			'cale'      => $cale,
			'contine'   => '',
			'term_id'   => (int) $principala,
			'incredere' => (int) round( $incredere * 100 ),
			'produse'   => count( $categorii[ $principala ] ),
		);
		if ( $incredere >= 0.6 && count( $categorii[ $principala ] ) >= 3 ) {
			$rezumat['pe_cale']++;
		} else {
			$rezumat['nesigure']++;
		}
	}

	if ( $scrie ) {
		$tabel = ccs_tabel( 'categorii' );
		// Regulile puse de om nu se pierd la reînvățare: se rescriu doar cele învățate.
		$wpdb->query( $wpdb->prepare( "DELETE FROM $tabel WHERE sursa = %s", 'invatat' ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		$acum  = current_time( 'mysql' );
		$puse  = ccs_categorii_reguli( 'om' );
		foreach ( $reguli as $r ) {
			if ( isset( $puse[ $r['cale'] . '|' . $r['contine'] ] ) ) {
				continue;
			}
			$wpdb->insert(
				$tabel,
				array(
					'cale'      => $r['cale'],
					'contine'   => $r['contine'],
					'term_id'   => $r['term_id'],
					'incredere' => $r['incredere'],
					'produse'   => $r['produse'],
					'sursa'     => 'invatat',
					'creat_la'  => $acum,
				)
			);
		}
	}

	$rezumat['lista'] = $reguli;
	return $rezumat;
}

/**
 * Regulile din tabel.
 *
 * @param string $sursa '' pentru toate, sau 'invatat' / 'om'.
 * @return array "cale|contine" => rând.
 */
function ccs_categorii_reguli( $sursa = '' ) {
	global $wpdb;
	$tabel = ccs_tabel( 'categorii' );
	$sql   = "SELECT * FROM $tabel";
	$randuri = $sursa
		? $wpdb->get_results( $wpdb->prepare( "$sql WHERE sursa = %s", $sursa ), ARRAY_A ) // phpcs:ignore WordPress.DB.PreparedSQL
		: $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL

	$iesire = array();
	foreach ( (array) $randuri as $r ) {
		$iesire[ $r['cale'] . '|' . $r['contine'] ] = $r;
	}
	return $iesire;
}

/**
 * Categoria de pe site pentru un produs al furnizorului.
 *
 * Regulile pe cuvânt bat regula pe cale, pentru că sunt mai precise. Între regulile puse de om și cele învățate,
 * câștigă omul.
 *
 * @param array $produs Produsul furnizorului, cu `cat1`, `cat2`, `cat3` și `nume`.
 * @return int term_id, sau 0 dacă nu știm unde merge.
 */
function ccs_categorie_pentru( $produs ) {
	static $reguli = null;
	if ( null === $reguli ) {
		$reguli = array();
		foreach ( ccs_categorii_reguli() as $r ) {
			$reguli[ $r['cale'] ][] = $r;
		}
	}

	$produs = (array) $produs;
	$cale   = ccs_categorie_cale( $produs );
	$term   = 0;

	if ( isset( $reguli[ $cale ] ) ) {
		$nume     = mb_strtoupper( (string) ( $produs['nume'] ?? $produs['numeprodus'] ?? '' ) );
		$pe_cale  = null;
		foreach ( $reguli[ $cale ] as $r ) {
			if ( '' === $r['contine'] ) {
				// Păstrăm regula pe cale ca rezervă, dar întâi căutăm una pe cuvânt.
				if ( ! $pe_cale || 'om' === $r['sursa'] ) {
					$pe_cale = $r;
				}
				continue;
			}
			if ( false !== mb_strpos( $nume, $r['contine'] ) ) {
				$term = (int) $r['term_id'];
				break;
			}
		}
		if ( ! $term && $pe_cale ) {
			$term = (int) $pe_cale['term_id'];
		}
	}

	/**
	 * Permite schimbarea categoriei alese pentru un produs.
	 *
	 * @param int   $term   term_id ales, 0 dacă niciunul.
	 * @param array $produs Produsul furnizorului.
	 * @param string $cale  Calea lui la furnizor.
	 */
	return (int) apply_filters( 'ccs_map_category', $term, $produs, $cale );
}
