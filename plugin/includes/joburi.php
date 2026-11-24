<?php
/**
 * Joburile programate.
 *
 * Folosim Action Scheduler, care vine cu WooCommerce, nu `wp_cron` direct: ține coada în baza de date, reia ce a
 * eșuat, nu pierde joburi când două cereri pică în același moment și are un ecran propriu de urmărit. Pe un magazin
 * fără trafic noaptea, `wp_cron` pur și simplu nu rulează, pentru că depinde de vizitatori.
 *
 * Ce se întâmplă la fiecare rulare de catalog: se preia catalogul în tabelele noastre, se reface potrivirea dacă
 * s-au adăugat produse, și, dacă modul raport e oprit, se aplică în WooCommerce ce s-a schimbat. Cu modul raport
 * pornit, jobul nu atinge magazinul deloc: doar ține copia locală la zi, ca panoul să arate cifre proaspete.
 *
 * Fereastra de noapte (19:00-05:00) e pentru endpoint-urile grele ale furnizorului. Deocamdată niciun job nu intră
 * acolo; `ccs_joburi_definitii()` e locul unde se adaugă, la etapa 4, odată cu imaginile.
 *
 * @package conex-conect-sync
 */

defined( 'ABSPATH' ) || exit;

const CCS_GRUP_JOBURI = 'conex-conect-sync';
const CCS_LACAT       = 'ccs_job_in_lucru';

/**
 * Joburile pe care le cunoaște pluginul.
 *
 * @return array hook => array( nume, interval în secunde, doar_noaptea ).
 */
function ccs_joburi_definitii() {
	$setari = ccs_setari();

	$joburi = array(
		'ccs_job_catalog' => array(
			'nume'         => __( 'Catalogul furnizorului', 'conex-conect-sync' ),
			'interval'     => max( 5, (int) $setari['interval_catalog'] ) * MINUTE_IN_SECONDS,
			'doar_noaptea' => false,
		),
	);

	/**
	 * Permite adăugarea de joburi programate.
	 *
	 * @param array $joburi Joburile.
	 */
	return apply_filters( 'ccs_joburi', $joburi );
}

/**
 * Programează joburile, sau le reprogramează dacă s-a schimbat intervalul.
 *
 * @return void
 */
function ccs_joburi_porneste() {
	if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
		ccs_jurnal( 'eroare', 'Action Scheduler lipsește. Vine cu WooCommerce; fără el nu se pot programa joburi.' );
		return;
	}

	foreach ( ccs_joburi_definitii() as $hook => $job ) {
		$urmatoarea = as_next_scheduled_action( $hook, null, CCS_GRUP_JOBURI );
		if ( $urmatoarea ) {
			// Deja programat. Dacă intervalul s-a schimbat, îl luăm de la capăt.
			if ( ccs_job_interval_programat( $hook ) === $job['interval'] ) {
				continue;
			}
			as_unschedule_all_actions( $hook, null, CCS_GRUP_JOBURI );
		}

		$start = $job['doar_noaptea'] ? ccs_joburi_urmatoarea_noapte() : time() + $job['interval'];
		as_schedule_recurring_action( $start, $job['interval'], $hook, array(), CCS_GRUP_JOBURI );
		ccs_jurnal( 'info', sprintf( 'Job programat: %s, la fiecare %d minute.', $hook, $job['interval'] / MINUTE_IN_SECONDS ) );
	}
}

/**
 * Oprește toate joburile.
 *
 * @return void
 */
function ccs_joburi_opreste() {
	if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
		return;
	}
	foreach ( array_keys( ccs_joburi_definitii() ) as $hook ) {
		as_unschedule_all_actions( $hook, null, CCS_GRUP_JOBURI );
	}
	ccs_jurnal( 'info', 'Joburile au fost oprite.' );
}

/**
 * Intervalul cu care e programat un job acum.
 *
 * Action Scheduler nu are o funcție pentru asta, deci îl citim din acțiunea programată.
 *
 * @param string $hook Hook-ul.
 * @return int Intervalul în secunde, sau 0.
 */
function ccs_job_interval_programat( $hook ) {
	if ( ! class_exists( 'ActionScheduler' ) ) {
		return 0;
	}
	$ids = as_get_scheduled_actions(
		array(
			'hook'     => $hook,
			'group'    => CCS_GRUP_JOBURI,
			'status'   => ActionScheduler_Store::STATUS_PENDING,
			'per_page' => 1,
		),
		'ids'
	);
	if ( ! $ids ) {
		return 0;
	}
	$actiune  = ActionScheduler::store()->fetch_action( reset( $ids ) );
	$programa = $actiune ? $actiune->get_schedule() : null;

	return $programa && method_exists( $programa, 'get_recurrence' ) ? (int) $programa->get_recurrence() : 0;
}

/**
 * Următorul moment din fereastra de noapte a furnizorului, devreme în ea.
 *
 * Devreme, pentru că rata de eșec a API-ului crește pe parcursul nopții: la 19:04 totul mergea din prima, la 21:52
 * un endpoint a dat 500 de cinci ori la rând.
 *
 * @return int Timestamp.
 */
function ccs_joburi_urmatoarea_noapte() {
	// Ora se socotește în fusul furnizorului și se întoarce ca timestamp real, cum vrea Action Scheduler.
	// Amestecul dintre `current_time('timestamp')` și `strtotime()` dă o oră deplasată cu offsetul fusului: pe un
	// site pe Europe/Bucharest, un job gândit pentru 19:10 ajunge programat la 21:10.
	$acum = ccs_api_ora_furnizor();
	if ( ! $acum ) {
		return time() + HOUR_IN_SECONDS;
	}

	$tinta = $acum->setTime( CCS_NOAPTE_DE_LA, 10, 0 );
	if ( $tinta <= $acum ) {
		$tinta = $tinta->modify( '+1 day' );
	}
	return $tinta->getTimestamp();
}

/**
 * Jobul de catalog.
 *
 * @return void
 */
function ccs_job_catalog() {
	// Două rulări simultane ar scrie una peste alta. Lacătul expiră singur, ca o rulare căzută să nu blocheze totul.
	if ( get_transient( CCS_LACAT ) ) {
		ccs_jurnal( 'avertisment', 'O rulare e deja în lucru. O sar pe asta.' );
		return;
	}
	set_transient( CCS_LACAT, time(), 15 * MINUTE_IN_SECONDS );

	try {
		$catalog = ccs_catalog_preia();
		if ( is_wp_error( $catalog ) ) {
			ccs_jurnal( 'eroare', 'Preluarea catalogului a eșuat: ' . $catalog->get_error_message() );
			return;
		}

		// Potrivirea se reface doar dacă s-au adăugat produse noi la furnizor; altfel legăturile sunt aceleași.
		if ( $catalog['noi'] > 0 ) {
			$potrivire = ccs_potriveste( true );
			ccs_jurnal( 'info', sprintf( '%d produse noi la furnizor, %d produse legate după repotrivire.', $catalog['noi'], $potrivire['legate'] ) );
		}

		// Panoul trebuie să arate cifrele de acum, nu pe cele dinainte de rulare.
		if ( function_exists( 'ccs_admin_uita' ) ) {
			ccs_admin_uita();
		}

		$setari = ccs_setari();
		if ( $setari['mod_raport'] ) {
			ccs_jurnal( 'info', 'Modul raport e pornit, deci nu scriu nimic în WooCommerce.' );
			return;
		}

		$aplicare = ccs_aplica();
		if ( is_wp_error( $aplicare ) ) {
			// Pragul de schimbări care sare într-un job nesupravegheat e exact ce trebuie să se întâmple: ceva s-a
			// schimbat mult mai mult decât de obicei și cineva trebuie să se uite.
			ccs_jurnal( 'avertisment', 'Nu am aplicat: ' . $aplicare->get_error_message() );
			return;
		}

		ccs_jurnal(
			'info',
			sprintf( 'Aplicat: %d produse, %d prețuri, %d stocuri.', $aplicare['atinse'], $aplicare['pret'], $aplicare['stoc'] )
		);
	} finally {
		delete_transient( CCS_LACAT );
	}
}
add_action( 'ccs_job_catalog', 'ccs_job_catalog' );

/**
 * Starea joburilor, pentru panou și pentru linia de comandă.
 *
 * @return array hook => array( nume, pornit, urmatoarea, interval ).
 */
function ccs_joburi_stare() {
	$stare = array();
	foreach ( ccs_joburi_definitii() as $hook => $job ) {
		$urmatoarea     = function_exists( 'as_next_scheduled_action' ) ? as_next_scheduled_action( $hook, null, CCS_GRUP_JOBURI ) : false;
		$stare[ $hook ] = array(
			'nume'       => $job['nume'],
			'pornit'     => (bool) $urmatoarea,
			'urmatoarea' => is_int( $urmatoarea ) ? $urmatoarea : 0,
			'interval'   => $job['interval'],
		);
	}
	return $stare;
}

/**
 * Pornește sau oprește joburile când se schimbă setarea.
 *
 * @param mixed $vechi Setările de dinainte.
 * @param mixed $nou   Setările de acum.
 * @return void
 */
function ccs_joburi_la_setari( $vechi, $nou ) {
	$pornite_acum = ! empty( $nou['joburi_pornite'] );
	$interval_nou = isset( $nou['interval_catalog'] ) ? (int) $nou['interval_catalog'] : 0;
	$interval_vechi = isset( $vechi['interval_catalog'] ) ? (int) $vechi['interval_catalog'] : 0;

	if ( ! $pornite_acum ) {
		ccs_joburi_opreste();
		return;
	}
	if ( empty( $vechi['joburi_pornite'] ) || $interval_nou !== $interval_vechi ) {
		ccs_joburi_porneste();
	}
}
add_action( 'update_option_' . CCS_OPTIUNE_SETARI, 'ccs_joburi_la_setari', 10, 2 );

/**
 * La dezactivarea pluginului, joburile nu rămân în coadă.
 *
 * @return void
 */
function ccs_joburi_la_dezactivare() {
	ccs_joburi_opreste();
}
register_deactivation_hook( CCS_FISIER, 'ccs_joburi_la_dezactivare' );
