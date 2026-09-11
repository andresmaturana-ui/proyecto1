/**
 * Bulk import screen: polls the batch's progress while items are pending.
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
		creado: 'Creado',
		no_encontrado: 'No encontrado',
		duplicado: 'Duplicado',
		sin_cupo: 'Sin cupo',
		error: 'Error',
		cancelado: 'Cancelado',
	};

	/**
	 * Applies one poll response to the summary counts and the item rows.
	 *
	 * @param {Object} data {summary, items}.
	 */
	function render( data ) {
		var summary = data.summary || {};
		var items = data.items || [];
		var index;
		var item;
		var row;
		var cell;

		wrap.querySelectorAll( '[data-melomaniac-bulk-summary] [data-status]' ).forEach( function ( el ) {
			var status = el.getAttribute( 'data-status' );
			var count = el.querySelector( '[data-count]' );

			if ( count ) {
				count.textContent = String( summary[ status ] || 0 );
			}
		} );

		for ( index = 0; index < items.length; index++ ) {
			item = items[ index ];
			row = itemsBody.querySelector( '[data-barcode="' + item.barcode + '"]' );

			if ( ! row ) {
				continue;
			}

			cell = row.querySelector( '[data-cell="status"]' );
			cell.textContent = labels[ item.status ] || item.status;

			if ( item.message && 'creado' !== item.status ) {
				cell.textContent += ' — ' + item.message;
			}

			cell = row.querySelector( '[data-cell="product"]' );

			if ( item.editUrl ) {
				cell.innerHTML = '';
				var link = document.createElement( 'a' );
				link.href = item.editUrl;
				link.textContent = item.name;
				cell.appendChild( link );
			}
		}
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

	poll();
} )( window, document );
