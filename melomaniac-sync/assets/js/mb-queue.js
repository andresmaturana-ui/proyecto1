/**
 * MusicBrainz contribution queue: opens each pending disc's seeded Add
 * Release page in turn, and marks it as contributed once the button is
 * used, so it drops off the list without a page reload.
 *
 * @package Melomaniac_Sync
 */

( function ( window, document ) {
	'use strict';

	if ( 'undefined' === typeof window.melomaniacSyncMbQueue ) {
		return;
	}

	var config = window.melomaniacSyncMbQueue;

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-action="mb-queue-contribute"]' );

		if ( ! button ) {
			return;
		}

		var row = button.closest( '[data-melomaniac-mb-queue-item]' );
		var form = button.closest( 'form' );

		if ( form && window.MelomaniacSyncMusicBrainzSeed ) {
			window.MelomaniacSyncMusicBrainzSeed.submit( form );
		}

		if ( ! row ) {
			return;
		}

		button.disabled = true;

		var body = new window.FormData();
		body.append( 'action', config.action );
		body.append( 'nonce', config.nonce );
		body.append( 'product_id', row.getAttribute( 'data-product-id' ) );

		window
			.fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function () {
				row.remove();
			} )
			.catch( function () {
				button.disabled = false;
			} );
	} );
} )( window, document );
