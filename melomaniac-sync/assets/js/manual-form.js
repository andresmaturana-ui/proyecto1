/**
 * Manual entry form behaviour.
 *
 * Events are delegated from the document because the form is injected into the
 * page by scan.js after an AJAX response.
 *
 * @package Melomaniac_Sync
 */

/* global wp */
( function ( $ ) {
	'use strict';

	var config = window.melomaniacSyncForm || {};
	var i18n = config.i18n || {};
	var frame = null;

	/**
	 * Opens the WordPress media library and stores the chosen attachment.
	 */
	function openMediaFrame() {
		if ( ! window.wp || ! wp.media ) {
			return;
		}

		if ( ! frame ) {
			frame = wp.media( {
				title: i18n.selectCover,
				button: { text: i18n.useCover },
				library: { type: 'image' },
				multiple: false
			} );

			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				var size = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium : attachment;

				$( '#melomaniac-manual-cover' ).val( attachment.id );
				$( '#melomaniac-cover-preview' ).html(
					$( '<img />', {
						src: size.url,
						alt: '',
						width: size.width,
						height: size.height
					} )
				);
				$( '#melomaniac-cover-remove' ).prop( 'hidden', false );
			} );
		}

		frame.open();
	}

	$( document )
		.on( 'click', '#melomaniac-cover-select', function ( event ) {
			event.preventDefault();
			openMediaFrame();
		} )
		.on( 'click', '#melomaniac-cover-remove', function ( event ) {
			event.preventDefault();
			$( '#melomaniac-manual-cover' ).val( '' );
			$( '#melomaniac-cover-preview' ).empty();
			$( this ).prop( 'hidden', true );
		} );
} )( window.jQuery );
