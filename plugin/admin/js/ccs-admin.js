/*
 * Panoul de admin: simulatorul de preț și trimiterea deciziilor.
 *
 * Simulatorul calculează în browser pentru că trebuie să răspundă instantaneu când omul trage de un adaos. Costurile
 * celor câteva mii de produse sunt deja în pagină, iar formula e scurtă. Oglinda ei din PHP e `ccs_pret()`; dacă una
 * se schimbă, trebuie schimbată și cealaltă, iar la salvare serverul recalculează și pagina arată cifrele lui.
 */
( function () {
	'use strict';

	var date = window.ccsDate || {};
	var sim = date.simulator || {};
	var texte = date.texte || {};

	function lei( v, zecimale ) {
		return new Intl.NumberFormat( 'ro-RO', {
			minimumFractionDigits: zecimale === undefined ? 2 : zecimale,
			maximumFractionDigits: zecimale === undefined ? 2 : zecimale
		} ).format( v );
	}

	function nr( v ) {
		return new Intl.NumberFormat( 'ro-RO' ).format( v );
	}

	function arataStare( element, text, eroare ) {
		if ( ! element ) {
			return;
		}
		element.textContent = text;
		element.classList.toggle( 'ccs-eroare', !! eroare );
	}

	/** Trimite ceva la server, cu nonce-ul paginii. */
	function trimite( actiune, campuri ) {
		var corp = new URLSearchParams();
		corp.append( 'action', actiune );
		corp.append( '_wpnonce', date.nonce );
		Object.keys( campuri ).forEach( function ( k ) {
			corp.append( k, campuri[ k ] );
		} );

		return fetch( date.ajaxurl || window.ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: corp.toString()
		} ).then( function ( r ) {
			return r.json();
		} );
	}

	/** Lasă apăsată varianta aleasă, ca rândul să-și spună singur starea. */
	function marcheazaAleasa( gazda, buton ) {
		gazda.querySelectorAll( '.ccs-alegere' ).forEach( function ( b ) {
			b.setAttribute( 'aria-pressed', b === buton ? 'true' : 'false' );
		} );
	}

	/** Rândul sau cardul care ține o decizie. */
	function gazdaRand( el ) {
		return el.closest( 'tr' ) || el.closest( '.ccs-potrivire' ) || el;
	}

	/**
	 * Scade cifra din dreptul tabului curent, fără să reîncarce pagina.
	 *
	 * Taburile sunt pagini separate, deci contorul care trebuie ținut la zi e cel din navigație, nu cifrele mari de
	 * pe panou.
	 */
	function actualizeazaRest() {
		var ramase = document.querySelectorAll( '.ccs-principal [data-tip]:not(.ccs-rezolvat) .ccs-decizie' );
		var vazute = {};
		var total = 0;
		ramase.forEach( function ( b ) {
			var gazda = b.closest( '[data-tip]' );
			if ( 'epuizare' === gazda.getAttribute( 'data-tip' ) ) {
				return;
			}
			if ( gazdaRand( gazda ).classList.contains( 'ccs-rezolvat' ) ) {
				return;
			}
			var cheie = gazda.getAttribute( 'data-tip' ) + ':' + gazda.getAttribute( 'data-referinta' );
			if ( vazute[ cheie ] ) {
				return;
			}
			vazute[ cheie ] = true;
			total++;
		} );

		var activ = document.querySelector( '.ccs-nav__link[aria-current="page"] .ccs-nav__rest' );
		if ( activ ) {
			if ( total > 0 ) {
				activ.textContent = nr( total );
			} else {
				activ.remove();
			}
		}

		actualizeazaProgres( total );
	}

	/** Mută bara de progres după câte răspunsuri mai lipsesc. */
	function actualizeazaProgres( raman ) {
		var bara = document.querySelector( '.ccs-progres' );
		if ( ! bara ) {
			return;
		}
		var total = document.querySelectorAll( '.ccs-principal [data-tip]:not([data-tip="epuizare"])' ).length;
		if ( ! total ) {
			return;
		}
		var gata = total - raman;
		var umplut = bara.querySelector( '.ccs-progres__umplut' );
		umplut.style.width = Math.round( 100 * gata / total ) + '%';
		umplut.classList.toggle( 'ccs-progres__umplut--gata', gata >= total );
		bara.querySelector( 'span:last-child' ).textContent = nr( gata ) + ' din ' + nr( total ) + ' rezolvate';
	}

	/* Butoanele de decizie, prinse o dată pe toată pagina. */
	document.addEventListener( 'click', function ( ev ) {
		var buton = ev.target.closest( '.ccs-decizie' );
		if ( ! buton ) {
			return;
		}
		ev.preventDefault();

		var gazda = buton.closest( '[data-tip]' );
		if ( ! gazda ) {
			return;
		}
		var rand = gazdaRand( gazda );
		var stare = rand.querySelector( '.ccs-stare-text' ) || gazda.querySelector( '.ccs-stare-text' );
		var butoane = gazda.querySelectorAll( '.ccs-decizie' );

		butoane.forEach( function ( b ) {
			b.disabled = true;
		} );
		arataStare( stare, texte.lucrez, false );

		trimite( 'ccs_decizie', {
			tip: gazda.getAttribute( 'data-tip' ),
			referinta: gazda.getAttribute( 'data-referinta' ) || '',
			valoare: buton.getAttribute( 'data-valoare' )
		} ).then( function ( raspuns ) {
			butoane.forEach( function ( b ) {
				b.disabled = false;
			} );
			if ( ! raspuns || ! raspuns.success ) {
				arataStare( stare, ( raspuns && raspuns.data && raspuns.data.mesaj ) || texte.eroare, true );
				return;
			}
			arataStare( stare, raspuns.data.efect || texte.salvat, false );
			marcheazaAleasa( gazda, buton );
			rand.classList.add( 'ccs-rezolvat' );
			actualizeazaRest();
		} ).catch( function () {
			butoane.forEach( function ( b ) {
				b.disabled = false;
			} );
			arataStare( stare, texte.eroare, true );
		} );
	} );

	/* „La fel pentru toate”. Primul clic cere confirmare pe buton, al doilea trimite. */
	document.addEventListener( 'click', function ( ev ) {
		var buton = ev.target.closest( '.ccs-lot' );
		if ( ! buton ) {
			return;
		}
		ev.preventDefault();

		var bara = buton.closest( '[data-tip-lot]' );
		var tip = bara.getAttribute( 'data-tip-lot' );
		var stare = bara.querySelector( '.ccs-stare-text' );
		var zona = bara.closest( '.ccs-card' ) || document;
		var ramase = [];

		zona.querySelectorAll( '[data-tip="' + tip + '"]' ).forEach( function ( el ) {
			if ( ! gazdaRand( el ).classList.contains( 'ccs-rezolvat' ) ) {
				ramase.push( el );
			}
		} );

		if ( ! ramase.length ) {
			arataStare( stare, texte.nimic, false );
			return;
		}

		if ( 'da' !== buton.getAttribute( 'data-confirma' ) ) {
			// Primul clic: cerem confirmarea pe buton, și o anulăm pe celelalte.
			bara.querySelectorAll( '.ccs-lot' ).forEach( function ( b ) {
				b.removeAttribute( 'data-confirma' );
				b.textContent = b.getAttribute( 'data-eticheta' );
			} );
			buton.setAttribute( 'data-confirma', 'da' );
			buton.textContent = texte.confirma + ' (' + nr( ramase.length ) + ')';
			return;
		}

		buton.disabled = true;
		buton.textContent = buton.getAttribute( 'data-eticheta' );
		buton.removeAttribute( 'data-confirma' );
		arataStare( stare, texte.lucrez, false );

		trimite( 'ccs_decizie_lot', {
			tip: tip,
			valoare: buton.getAttribute( 'data-valoare' ),
			referinte: ramase.map( function ( el ) {
				return el.getAttribute( 'data-referinta' );
			} ).join( ',' )
		} ).then( function ( raspuns ) {
			buton.disabled = false;
			if ( ! raspuns || ! raspuns.success ) {
				arataStare( stare, ( raspuns && raspuns.data && raspuns.data.mesaj ) || texte.eroare, true );
				return;
			}
			var valoare = buton.getAttribute( 'data-valoare' );
			ramase.forEach( function ( el ) {
				var rand = gazdaRand( el );
				marcheazaAleasa( el, el.querySelector( '.ccs-alegere[data-valoare="' + valoare + '"]' ) );
				rand.classList.add( 'ccs-rezolvat' );
				arataStare( rand.querySelector( '.ccs-stare-text' ), texte.salvat, false );
			} );
			arataStare( stare, raspuns.data.efect, false );
			actualizeazaRest();
		} ).catch( function () {
			buton.disabled = false;
			arataStare( stare, texte.eroare, true );
		} );
	} );

	/* Comutatorul de rulare automată. Pagina se reîncarcă după, ca ora următoarei rulări să fie cea reală, nu una
	   ghicită în browser. */
	var joburi = document.getElementById( 'ccs-joburi' );
	if ( joburi ) {
		joburi.addEventListener( 'click', function ( ev ) {
			ev.preventDefault();
			var stare = document.getElementById( 'ccs-joburi-stare' );
			joburi.disabled = true;
			arataStare( stare, texte.lucrez, false );

			trimite( 'ccs_joburi', { pornit: 'true' === joburi.dataset.pornit ? 'false' : 'true' } )
				.then( function ( raspuns ) {
					if ( ! raspuns || ! raspuns.success ) {
						joburi.disabled = false;
						arataStare( stare, ( raspuns && raspuns.data && raspuns.data.mesaj ) || texte.eroare, true );
						return;
					}
					arataStare( stare, raspuns.data.efect, false );
					window.location.reload();
				} )
				.catch( function () {
					joburi.disabled = false;
					arataStare( stare, texte.eroare, true );
				} );
		} );
	}

	/* ---------------------------------------------------------------- simulatorul de preț */

	if ( ! document.getElementById( 'ccs-adaos' ) || ! sim.cost || ! sim.cost.length ) {
		return;
	}

	/**
	 * Semnătura panoului, aceeași ca `ccs_ui_delta()` din PHP: o valoare care se schimbă, scrisă „acum → nou”.
	 *
	 * @param {number} acum Valoarea de acum.
	 * @param {number} nou  Valoarea propusă.
	 * @return {string} HTML.
	 */
	function delta( acum, nou ) {
		var dir = nou > acum ? 'sus' : ( nou < acum ? 'jos' : '' );
		var cat = acum > 0 && dir
			? '<span class="ccs-delta__cat">' + ( 'sus' === dir ? '+' : '−' ) +
				lei( Math.abs( nou / acum - 1 ) * 100, 0 ) + '%</span>'
			: '';
		return '<span class="ccs-delta' + ( dir ? ' ccs-delta--' + dir : '' ) +
			'"><span class="ccs-delta__acum">' + lei( acum ) +
			'</span><span class="ccs-delta__sageata" aria-hidden="true">→</span>' +
			'<span class="ccs-delta__nou">' + lei( nou ) + '</span>' + cat + '</span>';
	}

	/** Aceeași regulă ca `ccs_pret()` din PHP. */
	function pretPropus( cost, taxa, p ) {
		var c = cost * p.cota + ( p.include_taxa_eco ? taxa : 0 );
		if ( c <= 0 ) {
			return 0;
		}
		var rezultat = Math.max( c * p.adaos, c + p.marja, p.pret_minim );
		return 'sus' === p.rotunjire ? Math.ceil( rezultat ) : Math.round( rezultat * 100 ) / 100;
	}

	/** Șase produse, de la cel mai ieftin la cel mai scump, alese o singură dată. */
	var exemple = ( function () {
		var indici = sim.cost.map( function ( _, i ) {
			return i;
		} );
		indici.sort( function ( a, b ) {
			return sim.cost[ a ] - sim.cost[ b ];
		} );
		return [ 0, 0.25, 0.5, 0.75, 0.95, 0.999 ].map( function ( f ) {
			return indici[ Math.min( indici.length - 1, Math.round( f * ( indici.length - 1 ) ) ) ];
		} );
	} )();

	function politicaDinPagina() {
		return {
			adaos: parseFloat( document.getElementById( 'ccs-adaos' ).value ),
			cota: parseFloat( document.getElementById( 'ccs-cota' ).value ),
			marja: parseFloat( document.getElementById( 'ccs-marja' ).value ),
			pret_minim: date.politica ? date.politica.pret_minim_lei : 0,
			rotunjire: document.getElementById( 'ccs-rotunjire' ).value,
			include_taxa_eco: date.politica ? date.politica.include_taxa_eco : true
		};
	}

	function deseneaza() {
		var p = politicaDinPagina();
		var sus = 0, jos = 0, egal = 0, subCost = 0;
		var marjaAcum = 0, marjaProp = 0, deltaSus = 0, deltaJos = 0;
		var i;

		for ( i = 0; i < sim.cost.length; i++ ) {
			var costCuTva = sim.cost[ i ] * p.cota + ( p.include_taxa_eco ? sim.taxa[ i ] : 0 );
			var nou = pretPropus( sim.cost[ i ], sim.taxa[ i ], p );
			var acum = sim.pret[ i ];

			marjaAcum += acum - costCuTva;
			marjaProp += nou - costCuTva;
			if ( nou - costCuTva < 0 ) {
				subCost++;
			}

			var d = nou - acum;
			if ( d > 0.009 ) {
				sus++;
				deltaSus += d;
			} else if ( d < -0.009 ) {
				jos++;
				deltaJos += -d;
			} else {
				egal++;
			}
		}

		document.getElementById( 'ccs-adaos-val' ).textContent = '+' + Math.round( ( p.adaos - 1 ) * 100 ) + '%';
		document.getElementById( 'ccs-marja-val' ).textContent = lei( p.marja ) + ' lei';

		document.getElementById( 'ccs-e-sus' ).textContent =
			nr( sus ) + ( sus ? '  (în medie +' + lei( deltaSus / sus ) + ' lei)' : '' );
		document.getElementById( 'ccs-e-jos' ).textContent =
			nr( jos ) + ( jos ? '  (în medie −' + lei( deltaJos / jos ) + ' lei)' : '' );
		document.getElementById( 'ccs-e-egal' ).textContent = nr( egal );
		document.getElementById( 'ccs-e-subcost' ).textContent = nr( subCost );

		var procent = marjaAcum > 0 ? ( marjaProp / marjaAcum - 1 ) * 100 : 0;
		document.getElementById( 'ccs-e-marja' ).textContent =
			lei( marjaAcum, 0 ) + ' → ' + lei( marjaProp, 0 ) + ' lei  (' +
			( procent >= 0 ? '+' : '−' ) + lei( Math.abs( procent ), 1 ) + '%)';

		document.getElementById( 'ccs-exemple' ).innerHTML = exemple.map( function ( i ) {
			var costCuTva = sim.cost[ i ] * p.cota + ( p.include_taxa_eco ? sim.taxa[ i ] : 0 );
			var nou = pretPropus( sim.cost[ i ], sim.taxa[ i ], p );
			var castig = nou - costCuTva;
			return '<tr><td class="num">' + lei( sim.cost[ i ] ) + '</td><td class="num">' +
				delta( sim.pret[ i ], nou ) + '</td><td class="num' +
				( castig < 0 ? ' ccs-rosu' : '' ) + '">' + lei( castig ) + '</td></tr>';
		} ).join( '' );
	}

	[ 'ccs-adaos', 'ccs-cota', 'ccs-marja', 'ccs-rotunjire' ].forEach( function ( id ) {
		var el = document.getElementById( id );
		el.addEventListener( 'input', deseneaza );
		el.addEventListener( 'change', deseneaza );
	} );
	deseneaza();

	var salveaza = document.getElementById( 'ccs-salveaza-politica' );
	if ( salveaza ) {
		salveaza.addEventListener( 'click', function ( ev ) {
			ev.preventDefault();
			var stare = document.getElementById( 'ccs-politica-stare' );
			var p = politicaDinPagina();
			salveaza.disabled = true;
			arataStare( stare, texte.lucrez, false );

			trimite( 'ccs_politica', {
				adaos: p.adaos,
				cota_tva: p.cota,
				marja_minima_lei: p.marja,
				rotunjire: p.rotunjire
			} ).then( function ( raspuns ) {
				salveaza.disabled = false;
				if ( ! raspuns || ! raspuns.success ) {
					arataStare( stare, ( raspuns && raspuns.data && raspuns.data.mesaj ) || texte.eroare, true );
					return;
				}
				var e = raspuns.data.efect;
				arataStare(
					stare,
					'Salvat. Verificat pe server: ' + nr( e.sus ) + ' prețuri în sus, ' + nr( e.jos ) +
						' în jos, câștig brut ' + ( e.marja_delta >= 0 ? '+' : '−' ) +
						lei( Math.abs( e.marja_delta ), 1 ) + '%.',
					false
				);
			} ).catch( function () {
				salveaza.disabled = false;
				arataStare( stare, texte.eroare, true );
			} );
		} );
	}
}() );
