/*
 * Pagina de decizii: simulatorul de preț și trimiterea deciziilor.
 *
 * Simulatorul calculează în browser pentru că trebuie să răspundă instantaneu când omul trage de un adaos. Costurile
 * celor câteva mii de produse sunt deja în pagină, iar formula e scurtă. Oglinda ei din PHP e `ccs_pret()`; dacă
 * una se schimbă, trebuie schimbată și cealaltă, iar la salvare serverul recalculează și pagina arată cifrele lui.
 */
( function () {
	'use strict';

	var date = window.ccsDate || {};
	var sim = date.simulator || { cost: [], taxa: [], pret: [] };

	/** Formatează lei cum se citesc în România. */
	function lei( v, zecimale ) {
		return new Intl.NumberFormat( 'ro-RO', {
			minimumFractionDigits: zecimale === undefined ? 2 : zecimale,
			maximumFractionDigits: zecimale === undefined ? 2 : zecimale
		} ).format( v );
	}

	function nr( v ) {
		return new Intl.NumberFormat( 'ro-RO' ).format( v );
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

	/** Produsele alese ca exemple: de la cel mai ieftin la cel mai scump, pe toată plaja. */
	var exemple = ( function () {
		var indici = sim.cost.map( function ( _, i ) {
			return i;
		} );
		indici.sort( function ( a, b ) {
			return sim.cost[ a ] - sim.cost[ b ];
		} );
		if ( ! indici.length ) {
			return [];
		}
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
		var sus = 0;
		var jos = 0;
		var egal = 0;
		var subCost = 0;
		var marjaAcum = 0;
		var marjaProp = 0;
		var deltaSus = 0;
		var deltaJos = 0;
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

		document.getElementById( 'ccs-adaos-val' ).textContent =
			'+' + Math.round( ( p.adaos - 1 ) * 100 ) + '%';
		document.getElementById( 'ccs-marja-val' ).textContent = lei( p.marja ) + ' lei';

		document.getElementById( 'ccs-e-sus' ).textContent =
			nr( sus ) + ( sus ? ' (în medie +' + lei( deltaSus / sus ) + ' lei)' : '' );
		document.getElementById( 'ccs-e-jos' ).textContent =
			nr( jos ) + ( jos ? ' (în medie −' + lei( deltaJos / jos ) + ' lei)' : '' );
		document.getElementById( 'ccs-e-egal' ).textContent = nr( egal );
		document.getElementById( 'ccs-e-subcost' ).textContent = nr( subCost );

		var procent = marjaAcum > 0 ? ( marjaProp / marjaAcum - 1 ) * 100 : 0;
		document.getElementById( 'ccs-e-marja' ).textContent =
			lei( marjaAcum, 0 ) + ' lei → ' + lei( marjaProp, 0 ) + ' lei (' +
			( procent >= 0 ? '+' : '−' ) + lei( Math.abs( procent ), 1 ) + '%)';

		var rows = exemple.map( function ( i ) {
			var costCuTva = sim.cost[ i ] * p.cota + ( p.include_taxa_eco ? sim.taxa[ i ] : 0 );
			var nou = pretPropus( sim.cost[ i ], sim.taxa[ i ], p );
			var castig = nou - costCuTva;
			return '<tr><td>' + lei( sim.cost[ i ] ) + '</td><td>' + lei( sim.pret[ i ] ) +
				'</td><td><strong>' + lei( nou ) + '</strong></td><td' +
				( castig < 0 ? ' class="ccs-rosu"' : '' ) + '>' + lei( castig ) + '</td></tr>';
		} );
		document.getElementById( 'ccs-exemple' ).innerHTML = rows.join( '' );
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

	function arataStare( element, text, eroare ) {
		if ( ! element ) {
			return;
		}
		element.textContent = text;
		element.classList.toggle( 'ccs-eroare', !! eroare );
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
		var stare = gazda.querySelector( '.ccs-stare' );
		var butoane = gazda.querySelectorAll( '.ccs-decizie' );

		butoane.forEach( function ( b ) {
			b.disabled = true;
		} );
		arataStare( stare, date.texte.lucrez, false );

		trimite( 'ccs_decizie', {
			tip: gazda.getAttribute( 'data-tip' ),
			referinta: gazda.getAttribute( 'data-referinta' ) || '',
			valoare: buton.getAttribute( 'data-valoare' )
		} ).then( function ( raspuns ) {
			butoane.forEach( function ( b ) {
				b.disabled = false;
			} );
			if ( ! raspuns || ! raspuns.success ) {
				arataStare( stare, ( raspuns && raspuns.data && raspuns.data.mesaj ) || date.texte.eroare, true );
				return;
			}
			arataStare( stare, raspuns.data.efect || date.texte.salvat, false );
			var rand = gazda.closest( 'tr' ) || gazda.closest( '.ccs-card' ) || gazda;
			rand.classList.add( 'ccs-rezolvat' );
			actualizeazaContor();
		} ).catch( function () {
			butoane.forEach( function ( b ) {
				b.disabled = false;
			} );
			arataStare( stare, date.texte.eroare, true );
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
		var stare = bara.querySelector( '.ccs-stare' );
		var sectiune = bara.closest( '.ccs-sectiune' );
		var ramase = sectiune.querySelectorAll( '[data-tip="' + tip + '"]:not(.ccs-rezolvat)' );

		if ( ! ramase.length ) {
			arataStare( stare, 'Nu a mai rămas nimic de hotărât aici.', false );
			return;
		}

		if ( 'da' !== buton.getAttribute( 'data-confirma' ) ) {
			// Primul clic: cerem confirmarea pe buton, și o anulăm dacă omul se duce în altă parte.
			bara.querySelectorAll( '.ccs-lot' ).forEach( function ( b ) {
				b.removeAttribute( 'data-confirma' );
				b.textContent = b.getAttribute( 'data-eticheta' );
			} );
			buton.setAttribute( 'data-confirma', 'da' );
			buton.textContent = date.texte.confirma + ' (' + nr( ramase.length ) + ')';
			return;
		}

		var referinte = [];
		ramase.forEach( function ( el ) {
			referinte.push( el.getAttribute( 'data-referinta' ) );
		} );

		buton.disabled = true;
		buton.textContent = buton.getAttribute( 'data-eticheta' );
		buton.removeAttribute( 'data-confirma' );
		arataStare( stare, date.texte.lucrez, false );

		trimite( 'ccs_decizie_lot', {
			tip: tip,
			valoare: buton.getAttribute( 'data-valoare' ),
			referinte: referinte.join( ',' )
		} ).then( function ( raspuns ) {
			buton.disabled = false;
			if ( ! raspuns || ! raspuns.success ) {
				arataStare( stare, ( raspuns && raspuns.data && raspuns.data.mesaj ) || date.texte.eroare, true );
				return;
			}
			ramase.forEach( function ( el ) {
				el.classList.add( 'ccs-rezolvat' );
				arataStare( el.querySelector( '.ccs-stare' ), date.texte.salvat, false );
			} );
			arataStare( stare, raspuns.data.efect, false );
			actualizeazaContor();
		} ).catch( function () {
			buton.disabled = false;
			arataStare( stare, date.texte.eroare, true );
		} );
	} );

	/** Scade numărul de întrebări rămase, fără să reîncarce pagina. */
	function actualizeazaContor() {
		var raman = document.querySelectorAll( '[data-tip]:not(.ccs-rezolvat) .ccs-decizie' );
		var vazute = {};
		var total = 0;
		raman.forEach( function ( b ) {
			var gazda = b.closest( '[data-tip]' );
			var rand = gazda.closest( 'tr' ) || gazda.closest( '.ccs-card' ) || gazda;
			if ( rand.classList.contains( 'ccs-rezolvat' ) || 'epuizare' === gazda.getAttribute( 'data-tip' ) ) {
				return;
			}
			var cheie = gazda.getAttribute( 'data-tip' ) + ':' + gazda.getAttribute( 'data-referinta' );
			if ( vazute[ cheie ] ) {
				return;
			}
			vazute[ cheie ] = true;
			total++;
		} );

		var cifra = document.querySelector( '.ccs-cifra.atentie strong, .ccs-cifra.bine strong' );
		if ( cifra ) {
			cifra.textContent = nr( total );
			cifra.parentNode.classList.toggle( 'atentie', total > 0 );
			cifra.parentNode.classList.toggle( 'bine', 0 === total );
		}
	}

	/* Simulatorul. */
	var controale = [ 'ccs-adaos', 'ccs-cota', 'ccs-marja', 'ccs-rotunjire' ];
	if ( document.getElementById( 'ccs-adaos' ) ) {
		controale.forEach( function ( id ) {
			var el = document.getElementById( id );
			el.addEventListener( 'input', deseneaza );
			el.addEventListener( 'change', deseneaza );
		} );
		deseneaza();
	}

	var salveaza = document.getElementById( 'ccs-salveaza-politica' );
	if ( salveaza ) {
		salveaza.addEventListener( 'click', function ( ev ) {
			ev.preventDefault();
			var stare = document.getElementById( 'ccs-politica-stare' );
			var p = politicaDinPagina();
			salveaza.disabled = true;
			arataStare( stare, date.texte.lucrez, false );

			trimite( 'ccs_politica', {
				adaos: p.adaos,
				cota_tva: p.cota,
				marja_minima_lei: p.marja,
				rotunjire: p.rotunjire
			} ).then( function ( raspuns ) {
				salveaza.disabled = false;
				if ( ! raspuns || ! raspuns.success ) {
					arataStare( stare, ( raspuns && raspuns.data && raspuns.data.mesaj ) || date.texte.eroare, true );
					return;
				}
				var e = raspuns.data.efect;
				arataStare(
					stare,
					'Salvat. Pe server: ' + nr( e.sus ) + ' prețuri în sus, ' + nr( e.jos ) +
						' în jos, câștig brut ' + ( e.marja_delta >= 0 ? '+' : '−' ) +
						lei( Math.abs( e.marja_delta ), 1 ) + '%.',
					false
				);
			} ).catch( function () {
				salveaza.disabled = false;
				arataStare( stare, date.texte.eroare, true );
			} );
		} );
	}
}() );
