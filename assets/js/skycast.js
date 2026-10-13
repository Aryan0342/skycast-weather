/**
 * Skycast Weather front end.
 *
 * Fills each .skycast-card by calling the plugin's own REST route on this
 * site. It never contacts the weather service and never sees the API key.
 */
( function () {
	'use strict';

	const config = window.skycastConfig || {};
	const i18n = config.i18n || {};

	// OpenWeatherMap icon codes -> emoji, so the browser loads nothing from a third party.
	const ICONS = {
		'01': '☀️',
		'02': '⛅',
		'03': '☁️',
		'04': '☁️',
		'09': '🌧️',
		'10': '🌦️',
		'11': '⛈️',
		'13': '❄️',
		'50': '🌫️',
	};

	/**
	 * Create an element with optional class and text. textContent keeps it XSS-safe.
	 */
	function el( tag, className, text ) {
		const node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( text !== undefined && text !== null ) {
			node.textContent = String( text );
		}
		return node;
	}

	function iconFor( code ) {
		const night = code.endsWith( 'n' );
		const key = code.slice( 0, 2 );
		if ( key === '01' && night ) {
			return '🌙';
		}
		return ICONS[ key ] || '🌡️';
	}

	function render( card, data ) {
		const metric = data.units !== 'imperial';
		const deg = metric ? '°C' : '°F';
		const speed = metric ? 'm/s' : 'mph';

		const head = el( 'div', 'skycast-head' );
		head.append(
			el( 'span', 'skycast-icon', iconFor( data.icon || '' ) ),
			el( 'span', 'skycast-temp', data.temp !== null ? data.temp + deg : '–' )
		);

		const place = el( 'p', 'skycast-place', data.country ? data.city + ', ' + data.country : data.city );
		const desc = el( 'p', 'skycast-desc', data.description );

		const details = el( 'ul', 'skycast-details' );
		if ( data.feels_like !== null ) {
			details.append( el( 'li', '', i18n.feels + ' ' + data.feels_like + deg ) );
		}
		if ( data.humidity !== null ) {
			details.append( el( 'li', '', i18n.humidity + ' ' + data.humidity + '%' ) );
		}
		if ( data.wind !== null ) {
			// Round on display: PHP's JSON float output can read 2.3999999999999999.
			details.append( el( 'li', '', i18n.wind + ' ' + Number( data.wind ).toFixed( 1 ) + ' ' + speed ) );
		}

		card.replaceChildren( head, place, desc, details );

		if ( data.stale ) {
			card.append( el( 'p', 'skycast-note', i18n.stale ) );
		}
	}

	function renderError( card, message ) {
		card.replaceChildren( el( 'p', 'skycast-error', message || i18n.error ) );
	}

	async function load( card ) {
		const url = new URL( config.restUrl, window.location.href );
		url.searchParams.set( 'city', card.dataset.city || '' );

		try {
			const response = await fetch( url, {
				headers: { 'X-WP-Nonce': config.nonce },
				credentials: 'same-origin',
			} );
			const data = await response.json().catch( () => null );

			if ( ! response.ok || ! data ) {
				// The route returns { code, message } with a message safe to show.
				renderError( card, data && data.message );
				return;
			}
			render( card, data );
		} catch ( e ) {
			renderError( card );
		}
	}

	function init() {
		if ( ! config.restUrl ) {
			return;
		}
		document.querySelectorAll( '.skycast-card' ).forEach( load );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
