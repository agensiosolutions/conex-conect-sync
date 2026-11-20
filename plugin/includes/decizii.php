<?php
/**
 * Deciziile clientului.
 *
 * Pluginul nu poate hotărî singur cât adaos vrea magazinul, dacă un produs cu cod identic dar nume diferit e același
 * produs, sau dacă un preț are voie să coboare. Astea sunt decizii de afacere. Fișierul ăsta le ține: fiecare
 * decizie se scrie cu cine a luat-o și când, iar unele au și un efect imediat pe site.
 *
 * De ce într-un tabel și nu în setări: o setare se suprascrie și dispare, o decizie e un răspuns la o întrebare
 * concretă, într-un moment anume. Peste trei luni, întrebarea „de ce are produsul ăsta preț manual” are răspuns.
 *
 * @package conex-conect-sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tipurile de decizie, cu ce întreabă fiecare și ce valori acceptă.
 *
 * @return array tip => array( intrebare, valori ).
 */
function ccs_decizii_tipuri() {
	return array(
		'politica_pret' => array(
			'intrebare' => __( 'Politica de preț: adaos, cotă de TVA, marjă minimă', 'conex-conect-sync' ),
			'valori'    => array( 'aprobat' ),
		),
		'potrivire'     => array(
			'intrebare' => __( 'Codul se potrivește, dar numele arată alt produs', 'conex-conect-sync' ),
			'valori'    => array( 'acelasi', 'exclus' ),
		),
		'scadere'       => array(
			'intrebare' => __( 'Prețul ar coborî la mai puțin de jumătate', 'conex-conect-sync' ),
			'valori'    => array( 'lasa_sa_coboare', 'pret_manual' ),
		),
		'sub_cost'      => array(
			'intrebare' => __( 'Produs vândut sub costul de achiziție', 'conex-conect-sync' ),
			'valori'    => array( 'corecteaza', 'pret_manual' ),
		),
		'epuizare'      => array(
			'intrebare' => __( 'Produsele fără stoc la furnizor trec pe „stoc epuizat”', 'conex-conect-sync' ),
			'valori'    => array( 'aprobat' ),
		),
		'prima_aplicare' => array(
			'intrebare' => __( 'Pornirea sincronizării pe magazinul real', 'conex-conect-sync' ),
			'valori'    => array( 'aprobat' ),
		),
	);
}

/**
 * Scrie o decizie și îi aplică efectul.
 *
 * O decizie nouă pe aceeași referință o înlocuiește pe cea veche în efect, dar nu o șterge din tabel: istoricul
 * rămâne întreg, inclusiv răzgândirile.
 *
 * @param string $tip       Tipul, din `ccs_decizii_tipuri()`.
 * @param string $referinta Produsul sau subiectul deciziei. Gol pentru deciziile generale.
 * @param string $valoare   Valoarea aleasă.
 * @param string $nota      Opțional, ce a spus clientul.
 * @return array|WP_Error Ce s-a întâmplat, sau eroare.
 */
function ccs_decizie_scrie( $tip, $referinta, $valoare, $nota = '' ) {
	global $wpdb;
	$tipuri = ccs_decizii_tipuri();

	if ( ! isset( $tipuri[ $tip ] ) ) {
		return new WP_Error( 'ccs_tip_necunoscut', __( 'Tip de decizie necunoscut.', 'conex-conect-sync' ) );
	}
	if ( ! in_array( $valoare, $tipuri[ $tip ]['valori'], true ) ) {
		return new WP_Error( 'ccs_valoare_necunoscuta', __( 'Valoare nepermisă pentru decizia asta.', 'conex-conect-sync' ) );
	}

	$wpdb->insert(
		ccs_tabel( 'decizii' ),
		array(
			'tip'        => $tip,
			'referinta'  => (string) $referinta,
			'valoare'    => $valoare,
			'nota'       => $nota,
			'utilizator' => get_current_user_id(),
			'creat_la'   => current_time( 'mysql' ),
		)
	);

	$efect = ccs_decizie_aplica_efectul( $tip, $referinta, $valoare );

	ccs_jurnal( 'info', sprintf( 'Decizie: %s / %s = %s', $tip, $referinta, $valoare ) );

	return array(
		'tip'       => $tip,
		'referinta' => $referinta,
		'valoare'   => $valoare,
		'efect'     => $efect,
	);
}

/**
 * Efectul imediat al unei decizii.
 *
 * @param string $tip       Tipul.
 * @param string $referinta Referința.
 * @param string $valoare   Valoarea.
 * @return string Ce s-a făcut, în română, pentru mesajul din interfață.
 */
function ccs_decizie_aplica_efectul( $tip, $referinta, $valoare ) {
	global $wpdb;
	$wc_id = (int) $referinta;

	if ( 'potrivire' === $tip && 'acelasi' === $valoare && $wc_id ) {
		// Clientul confirmă legătura: o ridicăm la încredere maximă, ca să intre în plan de acum înainte.
		$wpdb->update( ccs_tabel( 'links' ), array( 'incredere' => 'A' ), array( 'wc_product_id' => $wc_id ) );
		return __( 'Legătura e confirmată. Produsul intră în sincronizare.', 'conex-conect-sync' );
	}

	if ( 'potrivire' === $tip && 'exclus' === $valoare && $wc_id ) {
		// Nu e același produs: ștergem legătura și ținem minte, ca potrivirea să nu-l mai lege niciodată.
		$wpdb->delete( ccs_tabel( 'links' ), array( 'wc_product_id' => $wc_id ) );
		$wpdb->replace(
			ccs_tabel( 'excluse' ),
			array(
				'wc_product_id' => $wc_id,
				'motiv'         => __( 'Clientul a spus că nu e același produs.', 'conex-conect-sync' ),
				'creat_la'      => current_time( 'mysql' ),
			)
		);
		return __( 'Produsul rămâne pe loc și nu se mai leagă niciodată de furnizor.', 'conex-conect-sync' );
	}

	if ( 'pret_manual' === $valoare && $wc_id ) {
		update_post_meta( $wc_id, '_ccs_pret_manual', 1 );
		return __( 'Prețul rămâne cum e acum. Stocul se sincronizează în continuare.', 'conex-conect-sync' );
	}

	if ( in_array( $tip, array( 'scadere', 'sub_cost' ), true ) && $wc_id ) {
		delete_post_meta( $wc_id, '_ccs_pret_manual' );
		if ( 'lasa_sa_coboare' === $valoare ) {
			// Scăderea e acceptată pentru produsul ăsta: frâna de scădere nu i se mai aplică.
			update_post_meta( $wc_id, '_ccs_scadere_permisa', 1 );
			return __( 'Prețul are voie să coboare la prima aplicare.', 'conex-conect-sync' );
		}
		return __( 'Prețul se corectează la prima aplicare.', 'conex-conect-sync' );
	}

	return '';
}

/**
 * Deciziile în vigoare, cea mai nouă pe fiecare referință.
 *
 * @param string $tip Tipul.
 * @return array referinta => array( valoare, nota, creat_la, utilizator ).
 */
function ccs_decizii_curente( $tip ) {
	global $wpdb;
	$tabel   = ccs_tabel( 'decizii' );
	$randuri = $wpdb->get_results(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL
			"SELECT d.* FROM $tabel d
			 INNER JOIN ( SELECT referinta, MAX(id) AS id FROM $tabel WHERE tip = %s GROUP BY referinta ) u
			         ON u.id = d.id",
			$tip
		)
	);
	$iesire = array();
	foreach ( $randuri as $r ) {
		$iesire[ (string) $r->referinta ] = array(
			'valoare'    => (string) $r->valoare,
			'nota'       => (string) $r->nota,
			'creat_la'   => (string) $r->creat_la,
			'utilizator' => (int) $r->utilizator,
		);
	}
	return $iesire;
}

/**
 * Produsele pe care clientul a spus să nu le legăm niciodată.
 *
 * @return array Id-uri de produs WooCommerce.
 */
function ccs_excluse() {
	global $wpdb;
	$tabel = ccs_tabel( 'excluse' );
	return array_map( 'intval', (array) $wpdb->get_col( "SELECT wc_product_id FROM $tabel" ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}
