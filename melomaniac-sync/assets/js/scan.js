/**
 * Scan screen behaviour.
 *
 * A USB barcode reader behaves like a keyboard that types the digits and presses
 * Enter, so the plain form submit below is all that is needed to support one.
 *
 * @package Melomaniac_Sync
 */

( function () {
	'use strict';

	var config = window.melomaniacSync || {};
	var i18n = config.i18n || {};

	var form = document.getElementById( 'melomaniac-scan-form' );
	var input = document.getElementById( 'melomaniac-barcode' );
	var statusBox = document.getElementById( 'melomaniac-status' );
	var resultBox = document.getElementById( 'melomaniac-result' );
	var manualToggle = document.getElementById( 'melomaniac-manual-toggle' );
	var cameraToggle = document.getElementById( 'melomaniac-camera-toggle' );
	var cameraBox = document.getElementById( 'melomaniac-camera' );
	var cameraVideo = document.getElementById( 'melomaniac-camera-video' );
	var cameraMessage = document.getElementById( 'melomaniac-camera-message' );

	var scanner = null;
	var busy = false;

	if ( ! form || ! input || ! resultBox ) {
		return;
	}

	/**
	 * Shows a transient status message.
	 *
	 * @param {string} message Text to display.
	 * @param {string} type    One of loading, error, success.
	 */
	function setStatus( message, type ) {
		if ( ! statusBox ) {
			return;
		}

		statusBox.textContent = message || '';
		statusBox.className = 'melomaniac-status' + ( message ? ' is-' + ( type || 'loading' ) : '' );
	}

	/**
	 * Replaces the result area content.
	 *
	 * @param {string} html Markup rendered by the server.
	 */
	function setResult( html ) {
		resultBox.innerHTML = html || '';

		if ( html ) {
			resultBox.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
		}
	}

	/**
	 * Posts to admin-ajax.php.
	 *
	 * @param {string} action Action name.
	 * @param {Object} data   Extra fields.
	 * @return {Promise<Object>} Resolves with the data payload.
	 */
	function request( action, data ) {
		var body = new FormData();

		body.append( 'action', action );
		body.append( 'nonce', config.nonce );

		Object.keys( data || {} ).forEach( function ( key ) {
			var value = data[ key ];

			if ( Array.isArray( value ) ) {
				value.forEach( function ( item ) {
					body.append( key + '[]', item );
				} );
				return;
			}

			body.append( key, value );
		} );

		return window
			.fetch( config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: body
			} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( payload ) {
				if ( ! payload || ! payload.success ) {
					var error = new Error(
						( payload && payload.data && payload.data.message ) || i18n.genericError
					);
					error.data = payload && payload.data ? payload.data : {};
					throw error;
				}

				return payload.data;
			} );
	}

	/**
	 * Runs the barcode lookup.
	 *
	 * @param {string} barcode Raw barcode.
	 */
	function lookup( barcode ) {
		if ( busy ) {
			return;
		}

		if ( ! barcode ) {
			setStatus( i18n.emptyBarcode, 'error' );
			return;
		}

		busy = true;
		setResult( '' );
		setStatus( i18n.searching, 'loading' );

		request( config.actions.lookup, { barcode: barcode } )
			.then( function ( data ) {
				setStatus( '' );
				setResult( data.html );
			} )
			.catch( function ( error ) {
				setStatus( error.message, 'error' );
			} )
			.then( function () {
				busy = false;
				input.select();
			} );
	}

	form.addEventListener( 'submit', function ( event ) {
		event.preventDefault();
		lookup( input.value.trim() );
	} );

	/**
	 * Loads the full detail of a candidate release.
	 *
	 * @param {string} source    Which service the release came from.
	 * @param {string} releaseId Identifier within that service.
	 * @param {string} barcode   Barcode.
	 */
	function loadRelease( source, releaseId, barcode ) {
		if ( busy ) {
			return;
		}

		busy = true;
		setStatus( i18n.loadingRelease, 'loading' );

		request( config.actions.release, { source: source, release_id: releaseId, barcode: barcode } )
			.then( function ( data ) {
				setStatus( '' );
				setResult( data.html );
			} )
			.catch( function ( error ) {
				setStatus( error.message, 'error' );
			} )
			.then( function () {
				busy = false;
			} );
	}

	/**
	 * Reads the price, stock, category and tag choices out of the result area.
	 *
	 * Returns an empty object when the block is absent, so the server falls back
	 * to the defaults from the settings screen.
	 *
	 * @return {Object} Fields to send along with the create request.
	 */
	function collectFields() {
		var box = resultBox.querySelector( '[data-melomaniac-fields]' );

		if ( ! box ) {
			return {};
		}

		var fields = {};
		var price = box.querySelector( '.melomaniac-field-price' );
		var stock = box.querySelector( '.melomaniac-field-stock' );
		var categories = box.querySelector( '.melomaniac-field-categories' );
		var newCategory = box.querySelector( '.melomaniac-field-new-category' );
		var tags = box.querySelector( '.melomaniac-field-tags' );

		if ( price ) {
			fields.price = price.value.trim();
		}

		if ( stock ) {
			fields.stock = stock.value.trim();
		}

		// Always sent, even empty: an empty tag field means "no tags on purpose",
		// which the server must be able to tell apart from "field not present".
		if ( tags ) {
			fields.tags = tags.value;
		}

		if ( newCategory ) {
			fields.new_category = newCategory.value.trim();
		}

		if ( categories ) {
			fields.category_ids = Array.prototype.slice
				.call( categories.selectedOptions )
				.map( function ( option ) {
					return option.value;
				} );
		}

		return fields;
	}

	/**
	 * Merges two plain objects into a new one.
	 *
	 * @param {Object} base  Base values.
	 * @param {Object} extra Values to add.
	 * @return {Object} Merged object.
	 */
	function withFields( base, extra ) {
		var merged = {};

		Object.keys( base ).forEach( function ( key ) {
			merged[ key ] = base[ key ];
		} );

		Object.keys( extra ).forEach( function ( key ) {
			merged[ key ] = extra[ key ];
		} );

		return merged;
	}

	/**
	 * Creates the draft product for a release.
	 *
	 * @param {HTMLElement} button    Clicked button.
	 * @param {string}      source    Which service the release came from.
	 * @param {string}      releaseId Identifier within that service.
	 * @param {string}      barcode   Barcode.
	 */
	function createProduct( button, source, releaseId, barcode ) {
		if ( busy ) {
			return;
		}

		busy = true;
		button.disabled = true;
		setStatus( i18n.creating, 'loading' );

		request(
			config.actions.create,
			withFields(
				{ source: source, release_id: releaseId, barcode: barcode },
				collectFields()
			)
		)
			.then( function ( data ) {
				setStatus( data.message, 'success' );
				appendEditLink( data.editUrl );
				setResult( '' );
				input.value = '';
				input.focus();
			} )
			.catch( function ( error ) {
				setStatus( error.message, 'error' );
				appendEditLink( error.data && error.data.editUrl );
			} )
			.then( function () {
				busy = false;
				button.disabled = false;
			} );
	}

	/**
	 * Appends a link to the product editor to the status area.
	 *
	 * @param {string} url Edit URL, ignored when empty.
	 */
	function appendEditLink( url ) {
		if ( ! url || ! statusBox ) {
			return;
		}

		var link = document.createElement( 'a' );

		link.href = url;
		link.textContent = i18n.editProduct;

		statusBox.appendChild( document.createTextNode( ' ' ) );
		statusBox.appendChild( link );
	}

	/**
	 * Opens the manual entry form for the current barcode.
	 *
	 * @param {HTMLElement|null} trigger Button that asked for it, when it knows
	 *                                   which release was on screen.
	 */
	function openManualForm( trigger ) {
		if ( busy ) {
			return;
		}

		busy = true;
		setStatus( '' );

		var fields = { barcode: input.value.trim() };

		// Carrying the release over means the form arrives filled in, so
		// correcting one wrong field does not mean retyping the track list.
		if ( trigger && trigger.getAttribute( 'data-release-id' ) ) {
			fields.source = trigger.getAttribute( 'data-source' );
			fields.release_id = trigger.getAttribute( 'data-release-id' );

			if ( trigger.getAttribute( 'data-barcode' ) ) {
				fields.barcode = trigger.getAttribute( 'data-barcode' );
			}
		}

		request( config.actions.manual, fields )
			.then( function ( data ) {
				setResult( data.html );
			} )
			.catch( function ( error ) {
				setStatus( error.message, 'error' );
			} )
			.then( function () {
				busy = false;
			} );
	}

	if ( manualToggle ) {
		manualToggle.addEventListener( 'click', function () {
			openManualForm( null );
		} );
	}

	// Delegated, because the result area content is replaced on every lookup.
	resultBox.addEventListener( 'click', function ( event ) {
		var create = event.target.closest( '.melomaniac-create-product' );

		if ( create ) {
			createProduct(
				create,
				create.getAttribute( 'data-source' ),
				create.getAttribute( 'data-release-id' ),
				create.getAttribute( 'data-barcode' )
			);
			return;
		}

		var select = event.target.closest( '.melomaniac-select-candidate' );

		if ( select ) {
			loadRelease(
				select.getAttribute( 'data-source' ),
				select.getAttribute( 'data-release-id' ),
				select.getAttribute( 'data-barcode' )
			);
			return;
		}

		var reject = event.target.closest( '.melomaniac-reject-match' );

		if ( reject ) {
			openManualForm( reject );
		}
	} );

	/**
	 * Shows a camera related message.
	 *
	 * @param {string} message Text, empty to hide.
	 */
	function setCameraMessage( message ) {
		if ( ! cameraMessage ) {
			return;
		}

		cameraMessage.textContent = message || '';
		cameraMessage.hidden = ! message;
	}

	/**
	 * Stops the camera and restores the button label.
	 */
	function stopCamera() {
		if ( scanner ) {
			scanner.stop();
		}

		if ( cameraBox ) {
			cameraBox.hidden = true;
		}

		if ( cameraToggle ) {
			cameraToggle.textContent = i18n.cameraStart;
		}
	}

	if ( cameraToggle && cameraVideo && cameraBox && window.MelomaniacSyncCameraScanner ) {
		scanner = new window.MelomaniacSyncCameraScanner( cameraVideo, {
			onDetect: function ( value ) {
				stopCamera();
				input.value = value;
				lookup( value );
			},
			onError: function ( code ) {
				stopCamera();
				setCameraMessage( i18n[ code ] || i18n.genericError );
			}
		} );

		cameraToggle.addEventListener( 'click', function () {
			if ( scanner.isRunning() ) {
				stopCamera();
				return;
			}

			setCameraMessage( '' );

			if ( ! scanner.isSupported() ) {
				setCameraMessage( i18n.cameraUnsupported );
				return;
			}

			if ( ! window.isSecureContext ) {
				setCameraMessage( i18n.cameraInsecure );
				return;
			}

			cameraBox.hidden = false;
			cameraToggle.textContent = i18n.cameraStop;
			scanner.start();
		} );
	} else if ( cameraToggle ) {
		cameraToggle.addEventListener( 'click', function () {
			setCameraMessage( i18n.cameraUnsupported );
		} );
	}

	// A barcode arriving in the URL means another screen deep linked here.
	if ( input.value.trim() ) {
		lookup( input.value.trim() );
	}
} )();
