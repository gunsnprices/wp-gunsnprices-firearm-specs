/**
 * GunsNPrices Firearm Specs: "Fill from title" on the product screen and "Fill specs now" on the settings page.
 *
 * @package GunsNPricesFirearmSpecs
 */
( function () {
	'use strict';

	var cfg = window.gnpfsAdmin || {};
	var i18n = cfg.i18n || {};

	function post( data ) {
		var body = new URLSearchParams();
		Object.keys( data ).forEach( function ( key ) {
			body.append( key, data[ key ] );
		} );
		return fetch( cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		} ).then( function ( response ) {
			return response.json();
		} ).then( function ( json ) {
			if ( ! json || ! json.success ) {
				throw new Error( 'request failed' );
			}
			return json.data;
		} );
	}

	function value( id ) {
		var el = document.getElementById( id );
		return el ? el.value : '';
	}

	function format( text ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var n = 0;
		return String( text ).replace( /%(\d\$)?d/g, function ( match, pos ) {
			var index = pos ? parseInt( pos, 10 ) - 1 : n++;
			return String( args[ index ] );
		} );
	}

	// Product screen.
	var fillButton = document.getElementById( 'gnpfs-fill-from-title' );
	if ( fillButton ) {
		var status = document.querySelector( '.gnpfs-fill-status' );
		fillButton.addEventListener( 'click', function () {
			fillButton.disabled = true;
			status.textContent = '';
			post( {
				action: 'gnpfs_parse_title',
				nonce: cfg.parseNonce,
				title: value( 'title' ),
				sku: value( '_sku' ),
				mpn: value( 'gnpfs_mpn' ),
				brand: value( 'gnpfs_brand' ),
				kind: value( 'gnpfs_kind' )
			} ).then( function ( specs ) {
				var filled = 0;
				[ 'caliber', 'brand', 'model', 'capacity', 'barrel_length', 'rounds' ].forEach( function ( field ) {
					var input = document.getElementById( 'gnpfs_' + field );
					if ( input && specs[ field ] ) {
						if ( input.value !== specs[ field ] ) {
							filled++;
						}
						input.value = specs[ field ];
						input.placeholder = specs[ field ];
					}
				} );
				status.textContent = filled ? format( i18n.filled, filled ) : i18n.nothing;
			} ).catch( function () {
				status.textContent = i18n.error;
			} ).then( function () {
				fillButton.disabled = false;
			} );
		} );
	}

	// Settings page.
	var allButton = document.getElementById( 'gnpfs-fill-all' );
	if ( allButton ) {
		var allStatus = document.getElementById( 'gnpfs-fill-status' );
		allButton.addEventListener( 'click', function () {
			var overwrite = document.getElementById( 'gnpfs-fill-overwrite' );
			var done = 0;
			var updated = 0;
			allButton.disabled = true;

			function run( page ) {
				post( {
					action: 'gnpfs_fill_batch',
					nonce: cfg.fillNonce,
					page: page,
					overwrite: overwrite && overwrite.checked ? 1 : 0
				} ).then( function ( data ) {
					done += data.processed;
					updated += data.updated;
					if ( data.more ) {
						allStatus.textContent = format( i18n.progress, done, data.total );
						run( data.next );
					} else {
						allStatus.textContent = format( i18n.done, done, updated );
						allButton.disabled = false;
					}
				} ).catch( function () {
					allStatus.textContent = i18n.error;
					allButton.disabled = false;
				} );
			}
			run( 1 );
		} );
	}
}() );
