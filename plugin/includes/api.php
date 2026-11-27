<?php
/**
 * Clientul pentru API-ul Conex Conect.
 *
 * Fiecare regulă de aici vine dintr-o măsurătoare pe API-ul real (Cez Auto, 13-14 oct. 2026), nu din documentație.
 * Documentația nu spune niciuna dintre ele:
 *
 * 1. Câmpul `total` din răspuns NU e numărul de rânduri și nu e de încredere. La `images` spune 23.094 pentru 30.498
 *    de rânduri reale, la `criteria` 64.268 pentru 33.667. Pare să numere într-un domeniu mai larg decât portofoliul
 *    tokenului. Paginăm după lungimea paginii, niciodată după `total`.
 * 2. O pagină peste ultima răspunde **404**, nu listă goală. 404 la paginare înseamnă „s-a terminat”, nu eroare.
 * 3. Endpoint-urile secundare (images, equivalents, criteria, applications) răspund doar între 19:00 și 05:00; în rest
 *    dau 503 cu mesaj explicit. Jobul se reprogramează, nu eșuează.
 * 4. API-ul are eșecuri intermitente pe răspunsurile mari, care se înrăutățesc pe parcursul nopții: la 19:04 totul
 *    mergea din prima, la 21:52 `criteria` a dat 500 de cinci ori la rând. De aici retry-ul cu backoff și paginile
 *    mici pe endpoint-urile grele.
 * 5. Parametrul `Fields` funcționează, dar schimbă capitalizarea cheilor din răspuns (`ProductID` în loc de
 *    `productID`). Normalizăm cheile la citire.
 *
 * Regula de aur, învățată tot acolo: **mai bine fără date noi decât cu date parțiale.** Dacă o pagină nu vine după
 * toate încercările, întreaga preluare eșuează și nu se scrie nimic. Altfel un 500 la ora 3 noaptea ar trece produsele
 * pe stoc zero în masă.
 *
 * @package conex-conect-sync
 */

defined( 'ABSPATH' ) || exit;

const CCS_API_BAZA = 'https://api.conexdist.ro/api/Products/All';

// Fereastra în care răspund endpoint-urile grele, după ora serverului furnizorului.
const CCS_FUS_FURNIZOR   = 'Europe/Bucharest';
const CCS_NOAPTE_DE_LA   = 19;
const CCS_NOAPTE_PANA_LA = 5;

/**
 * Tokenul de acces. Constanta din wp-config bate setarea din admin, ca pe staging să se poată pune altul.
 *
 * @return string Tokenul sau șir gol dacă nu e configurat.
 */
function ccs_api_token() {
	if ( defined( 'CCS_API_TOKEN' ) && CCS_API_TOKEN ) {
		return (string) CCS_API_TOKEN;
	}
	$setari = ccs_setari();
	return isset( $setari['token'] ) ? (string) $setari['token'] : '';
}

/**
 * Tokenul vine din cod, nu din setări?
 *
 * Constanta din `wp-config.php` bate întotdeauna setarea, iar panoul trebuie să spună asta: altfel cineva
 * schimbă tokenul din interfață, nu se întâmplă nimic, și caută ore întregi de ce.
 *
 * @return bool
 */
function ccs_api_token_din_cod() {
	return defined( 'CCS_API_TOKEN' ) && CCS_API_TOKEN;
}

/**
 * Tokenul, numai cât să se poată deosebi de altul.
 *
 * Niciodată întreg, nicăieri. Ultimele patru caractere ajung ca să recunoști care token e pus, fără ca ecranul
 * să devină un loc din care se poate fura.
 *
 * @return string Masca, sau șir gol dacă nu e configurat niciun token.
 */
function ccs_api_token_masca() {
	$token = ccs_api_token();
	if ( '' === $token ) {
		return '';
	}
	$coada = strlen( $token ) > 4 ? substr( $token, -4 ) : '';
	return str_repeat( '•', 12 ) . $coada;
}

/**
 * Cere o pagină mică de la furnizor, ca să se vadă dacă tokenul merge.
 *
 * O cerere adevărată, nu o presupunere: un token poate fi scris corect și totuși refuzat, iar singurul care
 * știe asta e furnizorul.
 *
 * @return int|WP_Error Câte produse a întors, sau eroarea primită.
 */
function ccs_api_proba() {
	// Aceiași parametri ca `ccs_api_toate()`: o probă care cere altceva decât codul adevărat n-ar proba nimic.
	$raspuns = ccs_api_cere( '', array( 'page' => 1, 'pageSize' => 1 ), 1 );
	if ( is_wp_error( $raspuns ) ) {
		return $raspuns;
	}
	return is_array( $raspuns ) ? count( $raspuns ) : 0;
}

/**
 * Ascunde tokenul dintr-un text, ca să nu ajungă în jurnal sau pe ecran.
 *
 * @param string $text Textul.
 * @return string Textul cu tokenul înlocuit.
 */
function ccs_api_fara_token( $text ) {
	$token = ccs_api_token();
	return $token ? str_replace( $token, '<token>', (string) $text ) : (string) $text;
}

/**
 * Cheile răspunsului, normalizate la litere mici, pentru că `Fields` le schimbă capitalizarea.
 *
 * @param array $rand Un rând din răspuns.
 * @return array Rândul cu chei mici.
 */
function ccs_api_chei_mici( $rand ) {
	$iesire = array();
	foreach ( (array) $rand as $cheie => $valoare ) {
		$iesire[ strtolower( (string) $cheie ) ] = $valoare;
	}
	return $iesire;
}

/**
 * O singură cerere, cu reîncercări și backoff.
 *
 * @param string $endpoint Numele endpoint-ului ('' pentru catalogul de produse).
 * @param array  $parametri Parametrii din query string, fără token.
 * @param int    $incercari Câte încercări înainte de a renunța.
 * @return array|WP_Error  Răspunsul decodat, sau eroare. Codul 'ccs_gata' înseamnă 404, adică sfârșitul paginării.
 */
function ccs_api_cere( $endpoint = '', $parametri = array(), $incercari = 5 ) {
	$token = ccs_api_token();
	if ( ! $token ) {
		return new WP_Error( 'ccs_fara_token', __( 'Tokenul Conex nu este configurat.', 'conex-conect-sync' ) );
	}

	$url = CCS_API_BAZA . ( $endpoint ? '/' . $endpoint : '' );
	$url = add_query_arg( array_merge( $parametri, array( 'token' => $token ) ), $url );

	$ultima = null;
	for ( $incercare = 1; $incercare <= $incercari; $incercare++ ) {
		$raspuns = wp_remote_get(
			$url,
			array(
				'timeout'   => 300,
				'sslverify' => true,
				'headers'   => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $raspuns ) ) {
			$ultima = $raspuns;
		} else {
			$cod = (int) wp_remote_retrieve_response_code( $raspuns );

			// 404: nu mai există pagina următoare. Nu e eroare, e semnalul de oprire.
			if ( 404 === $cod ) {
				return new WP_Error( 'ccs_gata', __( 'Nu mai sunt pagini.', 'conex-conect-sync' ) );
			}

			// 401: tokenul e respins. Nu are rost să reîncercăm.
			if ( 401 === $cod ) {
				return new WP_Error( 'ccs_token_invalid', __( 'Token invalid. Verifică setările.', 'conex-conect-sync' ) );
			}

			// 503: în afara ferestrei de noapte. Jobul se reprogramează, nu se reîncearcă acum.
			if ( 503 === $cod ) {
				return new WP_Error(
					'ccs_fereastra_inchisa',
					__( 'Endpoint-ul răspunde doar între 19:00 și 05:00.', 'conex-conect-sync' ),
					array( 'mesaj' => ccs_api_fara_token( wp_remote_retrieve_body( $raspuns ) ) )
				);
			}

			if ( 200 === $cod ) {
				$date = json_decode( wp_remote_retrieve_body( $raspuns ), true );
				if ( is_array( $date ) ) {
					return $date;
				}
				$ultima = new WP_Error( 'ccs_json', __( 'Răspuns care nu e JSON valid.', 'conex-conect-sync' ) );
			} else {
				$ultima = new WP_Error(
					'ccs_http',
					/* translators: %d: codul HTTP întors de API. */
					sprintf( __( 'API-ul a răspuns cu HTTP %d.', 'conex-conect-sync' ), $cod )
				);
			}
		}

		// Backoff: 3, 6, 12, 24 secunde, plafonat la un minut.
		if ( $incercare < $incercari ) {
			ccs_jurnal(
				'avertisment',
				sprintf( 'Cerere eșuată la %s, încercarea %d din %d: %s', $endpoint ? $endpoint : 'catalog', $incercare, $incercari, $ultima->get_error_message() )
			);
			sleep( min( 60, 3 * ( 2 ** ( $incercare - 1 ) ) ) );
		}
	}

	return $ultima;
}

/**
 * Preia toate paginile unui endpoint.
 *
 * Paginează până la o pagină mai scurtă decât `pageSize` sau până la 404. Nu se uită deloc la `total`.
 * Dacă o pagină nu vine, întoarce eroare și NU întoarce rândurile strânse până atunci: cine o cheamă nu trebuie
 * să poată confunda o preluare parțială cu una completă.
 *
 * @param string   $endpoint   Numele endpoint-ului ('' pentru catalogul de produse).
 * @param int      $per_pagina Câte rânduri pe pagină. Mic pe endpoint-urile grele.
 * @param callable $la_pagina  Opțional, chemat cu fiecare pagină, ca să nu ținem tot în memorie.
 * @return array|WP_Error Rândurile (goale dacă s-a folosit $la_pagina), sau eroare.
 */
function ccs_api_toate( $endpoint = '', $per_pagina = 20000, $la_pagina = null ) {
	$rezultat    = array();
	$pagina      = 1;
	$total_randuri = 0;

	while ( $pagina <= 500 ) {
		$date = ccs_api_cere( $endpoint, array( 'page' => $pagina, 'pageSize' => $per_pagina ) );

		if ( is_wp_error( $date ) ) {
			// 404 pe pagina următoare înseamnă că am terminat, nu că am dat greș.
			if ( 'ccs_gata' === $date->get_error_code() ) {
				break;
			}
			return $date;
		}

		$randuri = isset( $date['data'] ) && is_array( $date['data'] ) ? $date['data'] : array();
		if ( ! $randuri ) {
			break;
		}

		$randuri        = array_map( 'ccs_api_chei_mici', $randuri );
		$total_randuri += count( $randuri );

		if ( is_callable( $la_pagina ) ) {
			call_user_func( $la_pagina, $randuri, $pagina );
		} else {
			$rezultat = array_merge( $rezultat, $randuri );
		}

		// Pagina incompletă înseamnă ultima pagină. Nu mai cerem una, ca să nu luăm 404 degeaba.
		if ( count( $randuri ) < $per_pagina ) {
			break;
		}
		$pagina++;
	}

	ccs_jurnal( 'info', sprintf( 'Preluat %s: %d rânduri în %d pagini.', $endpoint ? $endpoint : 'catalog', $total_randuri, $pagina ) );
	return $rezultat;
}

/**
 * Catalogul complet de produse. Răspunde non-stop, ~21.000 de produse într-o singură pagină, în câteva secunde.
 *
 * @return array|WP_Error Produsele cu chei mici.
 */
function ccs_api_catalog() {
	return ccs_api_toate( '', 30000 );
}

/**
 * Endpoint-urile din fereastra de noapte, cu pagini mici pentru că acolo apar eșecurile.
 *
 * @param string   $endpoint  'images', 'equivalents', 'criteria' sau 'applications'.
 * @param callable $la_pagina Opțional, pentru procesare pe bucăți.
 * @return array|WP_Error Rândurile sau eroarea.
 */
function ccs_api_noapte( $endpoint, $la_pagina = null ) {
	$permise = array( 'images', 'equivalents', 'criteria', 'applications' );
	if ( ! in_array( $endpoint, $permise, true ) ) {
		return new WP_Error( 'ccs_endpoint', __( 'Endpoint necunoscut.', 'conex-conect-sync' ) );
	}
	// 20.000 în loc de 200.000: răspunsurile mari sunt cele care pică.
	return ccs_api_toate( $endpoint, 20000, $la_pagina );
}

/**
 * Suntem în fereastra în care răspund endpoint-urile secundare?
 *
 * Verificarea e orientativă, după ora serverului Conex (Europa/București). Adevărul îl spune tot un 503.
 *
 * @return bool
 */
function ccs_api_fereastra_deschisa() {
	$acum = ccs_api_ora_furnizor();
	if ( ! $acum ) {
		return false;
	}
	return ccs_api_in_fereastra( (int) $acum->format( 'G' ) );
}

/**
 * Dacă o oră cade în fereastra de noapte.
 *
 * Despărțită de citirea ceasului ca să poată fi probată: fereastra trece peste miezul nopții, deci condiția nu e
 * un interval obișnuit, iar o greșeală aici se vede abia la 3 dimineața.
 *
 * @param int $ora Ora, de la 0 la 23.
 * @return bool Dacă e în fereastră.
 */
function ccs_api_in_fereastra( $ora ) {
	$ora = (int) $ora;
	return $ora >= CCS_NOAPTE_DE_LA || $ora < CCS_NOAPTE_PANA_LA;
}

/**
 * Ora de la furnizor, acum.
 *
 * Fereastra de noapte e a serverului Conex, nu a magazinului. Un magazin al unui client din altă țară ar avea alt
 * fus, iar jobul ar porni la ora greșită: de aceea ancora e aici, nu în setările WordPress.
 *
 * @return DateTimeImmutable|null Ora, sau null dacă fusul nu se poate încărca.
 */
function ccs_api_ora_furnizor() {
	try {
		return new DateTimeImmutable( 'now', new DateTimeZone( CCS_FUS_FURNIZOR ) );
	} catch ( Exception $e ) {
		return null;
	}
}
