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
			body.append( key, data[ key ] );
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
	 * @param {string} mbid    Release MBID.
	 * @param {string} barcode Barcode.
	 */
	function loadRelease( mbid, barcode ) {
		if ( busy ) {
			return;
		}

		busy = true;
		setStatus( i18n.loadingRelease, 'loading' );

		request( config.actions.release, { mbid: mbid, barcode: barcode } )
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
	 * Creates the draft product for a release.
	 *
	 * @param {HTMLElement} button  Clicked button.
	 * @param {string}      mbid    Release MBID.
	 * @param {string}      barcode Barcode.
	 */
	function createProduct( button, mbid, barcode ) {
		if ( busy ) {
			return;
		}

		busy = true;
		button.disabled = true;
		setStatus( i18n.creating, 'loading' );

		request( config.actions.create, { mbid: mbid, barcode: barcode } )
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
	 */
	function openManualForm() {
		if ( busy ) {
			return;
		}

		busy = true;
		setStatus( '' );

		request( config.actions.manual, { barcode: input.value.trim() } )
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
		manualToggle.addEventListener( 'click', openManualForm );
	}

	// Delegated, because the result area content is replaced on every lookup.
	resultBox.addEventListener( 'click', function ( event ) {
		var create = event.target.closest( '.melomaniac-create-product' );

		if ( create ) {
			createProduct( create, create.getAttribute( 'data-mbid' ), create.getAttribute( 'data-barcode' ) );
			return;
		}

		var select = event.target.closest( '.melomaniac-select-candidate' );

		if ( select ) {
			loadRelease( select.getAttribute( 'data-mbid' ), select.getAttribute( 'data-barcode' ) );
			return;
		}

		if ( event.target.closest( '.melomaniac-reject-match' ) ) {
			openManualForm();
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
