/**
 * Bulk import screen: polls the batch's progress while items are pending,
 * and lets the shop pick an edition for an ambiguous barcode.
 *
 * @package Melomaniac_Sync
 */

( function ( window, document ) {
	'use strict';

	var wrap = document.querySelector( '[data-melomaniac-bulk-status]' );

	if ( ! wrap || 'undefined' === typeof window.melomaniacSyncBulk ) {
		return;
	}

	var config = window.melomaniacSyncBulk;
	var batchId = wrap.getAttribute( 'data-batch-id' );
	var itemsBody = wrap.querySelector( '[data-melomaniac-bulk-items]' );

	var labels = {
		pendiente: 'Pendiente',
		eleccion: 'Elegir edición',
		creado: 'Creado',
		no_encontrado: 'No encontrado',
		duplicado: 'Duplicado',
		sin_cupo: 'Sin cupo',
		error: 'Error',
		cancelado: 'Cancelado',
	};

	/**
	 * Builds the "elegir edición" buttons for an item with several candidates.
	 *
	 * @param {Array} candidates {source, release_id, display_name, label, year}.
	 * @return {HTMLElement}
	 */
	function buildCandidates( candidates ) {
		var wrapEl = document.createElement( 'div' );
		wrapEl.className = 'melomaniac-bulk-candidates';

		candidates.forEach( function ( candidate ) {
			var button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'button button-small';
			button.setAttribute( 'data-action', 'resolve-candidate' );
			button.setAttribute( 'data-source', candidate.source );
			button.setAttribute( 'data-release-id', candidate.release_id );
			button.textContent = [ candidate.display_name, candidate.label, candidate.year ]
				.filter( Boolean )
				.join( ' · ' );
			wrapEl.appendChild( button );
		} );

		return wrapEl;
	}

	/**
	 * Applies one item's current state to its row.
	 *
	 * @param {Object} item {id, barcode, status, message, candidates, editUrl, name}.
	 */
	function updateRow( item ) {
		var row = itemsBody.querySelector( '[data-item-id="' + item.id + '"]' ) || itemsBody.querySelector( '[data-barcode="' + item.barcode + '"]' );

		if ( ! row ) {
			return;
		}

		var cell = row.querySelector( '[data-cell="status"]' );
		cell.textContent = labels[ item.status ] || item.status;

		if ( item.message && 'creado' !== item.status ) {
			cell.textContent += ' — ' + item.message;
		}

		cell = row.querySelector( '[data-cell="product"]' );
		cell.innerHTML = '';

		if ( item.editUrl ) {
			var link = document.createElement( 'a' );
			link.href = item.editUrl;
			link.textContent = item.name;
			cell.appendChild( link );
		} else if ( 'eleccion' === item.status && item.candidates && item.candidates.length ) {
			cell.appendChild( buildCandidates( item.candidates ) );
		}
	}

	/**
	 * Applies one poll response to the summary counts and every item row.
	 *
	 * @param {Object} data {summary, items}.
	 */
	function render( data ) {
		var summary = data.summary || {};

		wrap.querySelectorAll( '[data-melomaniac-bulk-summary] [data-status]' ).forEach( function ( el ) {
			var status = el.getAttribute( 'data-status' );
			var count = el.querySelector( '[data-count]' );

			if ( count ) {
				count.textContent = String( summary[ status ] || 0 );
			}
		} );

		( data.items || [] ).forEach( updateRow );
	}

	/**
	 * Asks the server for the batch's current state, then schedules the next
	 * poll while anything is still pending.
	 */
	function poll() {
		var body = new window.FormData();
		body.append( 'action', config.action );
		body.append( 'nonce', config.nonce );
		body.append( 'batch_id', batchId );

		window
			.fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				if ( ! json.success ) {
					window.setTimeout( poll, 5000 );
					return;
				}

				render( json.data );

				if ( json.data.summary && json.data.summary.pendiente > 0 ) {
					window.setTimeout( poll, 3000 );
				}
			} )
			.catch( function () {
				window.setTimeout( poll, 5000 );
			} );
	}

	/**
	 * Handles a tap on one of an item's candidate buttons: sends the choice
	 * to the server and applies the resulting row state when it comes back.
	 */
	itemsBody.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-action="resolve-candidate"]' );

		if ( ! button ) {
			return;
		}

		var row = button.closest( '[data-item-id]' );
		var group = button.closest( '[data-candidates]' ) || button.parentElement;

		group.querySelectorAll( 'button' ).forEach( function ( btn ) {
			btn.disabled = true;
		} );

		var body = new window.FormData();
		body.append( 'action', config.resolveAction );
		body.append( 'nonce', config.nonce );
		body.append( 'item_id', row.getAttribute( 'data-item-id' ) );
		body.append( 'source', button.getAttribute( 'data-source' ) );
		body.append( 'release_id', button.getAttribute( 'data-release-id' ) );

		window
			.fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				if ( json.success && json.data.item ) {
					updateRow( json.data.item );
				} else {
					group.querySelectorAll( 'button' ).forEach( function ( btn ) {
						btn.disabled = false;
					} );
				}
			} )
			.catch( function () {
				group.querySelectorAll( 'button' ).forEach( function ( btn ) {
					btn.disabled = false;
				} );
			} );
	} );

	poll();
} )( window, document );
