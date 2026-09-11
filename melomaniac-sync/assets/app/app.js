/**
 * Melomaniac Sync — installable web app.
 *
 * Talks to melomaniac-sync/v1 with an Application Password carried in the
 * X-Melomaniac-Auth header (base64 user:pass), the same scheme any other
 * client of that API uses. No framework: the app is small enough that a
 * hand-rolled screen switcher is simpler than a build step.
 *
 * @package Melomaniac_Sync
 */

( function ( window, document ) {
	'use strict';

	var STORAGE_KEY = 'melomaniac_sync_app_auth';
	var STRINGS = {
		networkError: 'No se pudo conectar. Revisa tu conexión e inténtalo de nuevo.',
		connectError: 'No se pudo conectar. Revisa el usuario y la contraseña de aplicación.',
		notFound: 'No encontramos ningún disco con ese código. Puedes cargarlo a mano.',
		created: 'El producto se creó como borrador.',
	};
	var FORMATS = {
		vinyl: 'Vinilo',
		cd: 'CD',
		cassette: 'Cassette',
		other: 'Otro',
	};

	var config = window.MelomaniacSyncApp || {};
	var app = document.getElementById( 'app' );
	var categoriesCache = null;

	var state = {
		auth: '',
		barcode: '',
		candidates: [],
		review: null,
		rejected: null,
	};

	/**
	 * Reads a stored credential, if any.
	 *
	 * @return {string}
	 */
	function loadAuth() {
		try {
			return window.localStorage.getItem( STORAGE_KEY ) || '';
		} catch ( error ) {
			return '';
		}
	}

	/**
	 * Persists (or clears) the credential.
	 *
	 * @param {string} value Base64 "user:pass", or '' to clear.
	 */
	function saveAuth( value ) {
		try {
			if ( value ) {
				window.localStorage.setItem( STORAGE_KEY, value );
			} else {
				window.localStorage.removeItem( STORAGE_KEY );
			}
		} catch ( error ) {
			// Private browsing or storage disabled: the session just won't persist.
		}
	}

	/**
	 * Calls the REST API.
	 *
	 * @param {string} path   Path under the namespace, e.g. '/lookup'.
	 * @param {Object} options Fetch options; body, when present, is JSON-encoded.
	 * @return {Promise<Object>} Resolves with the parsed JSON body.
	 */
	function api( path, options ) {
		options = options || {};

		var headers = {
			'X-Melomaniac-Auth': state.auth,
		};

		var init = {
			method: options.method || 'GET',
			headers: headers,
		};

		if ( options.body instanceof Blob ) {
			init.body = options.body;
			if ( options.contentType ) {
				headers['Content-Type'] = options.contentType;
			}
			if ( options.filename ) {
				headers['Content-Disposition'] = 'attachment; filename="' + options.filename + '"';
			}
		} else if ( options.body ) {
			headers['Content-Type'] = 'application/json';
			init.body = JSON.stringify( options.body );
		}

		var url = config.restUrl.replace( /\/$/, '' ) + path;

		return window
			.fetch( url, init )
			.catch( function () {
				return Promise.reject( { message: STRINGS.networkError } );
			} )
			.then( function ( response ) {
				return response.json().catch( function () {
					return {};
				} ).then( function ( body ) {
					if ( ! response.ok ) {
						return Promise.reject( body && body.message ? body : { message: STRINGS.networkError } );
					}
					return body;
				} );
			} );
	}

	/**
	 * Shows one screen, hiding the rest.
	 *
	 * @param {string} name Value matching a [data-screen-name].
	 */
	function showScreen( name ) {
		var sections = app.querySelectorAll( '[data-screen-name]' );
		var index;

		for ( index = 0; index < sections.length; index++ ) {
			sections[ index ].hidden = sections[ index ].getAttribute( 'data-screen-name' ) !== name;
		}

		app.setAttribute( 'data-screen', name );
		window.scrollTo( 0, 0 );
	}

	/**
	 * Shows/clears an inline error under a given [data-*-error] element.
	 *
	 * @param {string} selector Attribute selector, e.g. '[data-scan-error]'.
	 * @param {string} message  Message to show; empty hides the element.
	 */
	function setError( selector, message ) {
		var el = app.querySelector( selector );

		if ( ! el ) {
			return;
		}

		el.textContent = message || '';
		el.hidden = ! message;
	}

	/**
	 * Fetches (and caches) the store's product categories.
	 *
	 * @return {Promise<Array>}
	 */
	function loadCategories() {
		if ( categoriesCache ) {
			return Promise.resolve( categoriesCache );
		}

		return api( '/categories' ).then( function ( list ) {
			categoriesCache = list;
			return list;
		} );
	}

	/**
	 * Renders the shared price/stock/category/tag fields into a mount point.
	 *
	 * @param {HTMLElement} mount Element with [data-fields-mount].
	 * @return {Promise<void>}
	 */
	function renderProductFields( mount ) {
		mount.innerHTML =
			'<label>Precio</label>' +
			'<input type="text" inputmode="decimal" name="price" />' +
			'<label>Cantidad</label>' +
			'<input type="number" min="0" step="1" name="stock" value="1" />' +
			'<label>Categorías</label>' +
			'<select name="category_ids" multiple data-categories-select></select>' +
			'<input type="text" name="new_category" placeholder="Crear una categoría nueva" />' +
			'<label>Etiquetas</label>' +
			'<input type="text" name="tags" placeholder="thrash metal, chileno" />';

		return loadCategories().then( function ( list ) {
			var select = mount.querySelector( '[data-categories-select]' );
			var index;
			var option;

			for ( index = 0; index < list.length; index++ ) {
				option = document.createElement( 'option' );
				option.value = String( list[ index ].id );
				option.textContent = list[ index ].name;
				select.appendChild( option );
			}
		} );
	}

	/**
	 * Reads the shared product fields back out of a form.
	 *
	 * @param {HTMLFormElement} form Form containing the fields.
	 * @return {Object}
	 */
	function readProductFields( form ) {
		var select = form.querySelector( '[data-categories-select]' );
		var categoryIds = [];
		var index;

		if ( select ) {
			for ( index = 0; index < select.options.length; index++ ) {
				if ( select.options[ index ].selected ) {
					categoryIds.push( select.options[ index ].value );
				}
			}
		}

		return {
			price: form.price ? form.price.value : '',
			stock: form.stock ? form.stock.value : '',
			category_ids: categoryIds,
			new_category: form.new_category ? form.new_category.value : '',
			tags: form.tags ? form.tags.value : '',
		};
	}

	/**
	 * Turns a release's track array into the plain-text shape the manual form
	 * (and the builder on the server) expect.
	 *
	 * @param {Array} tracklist Track objects: {number, title, length}.
	 * @return {string}
	 */
	function tracklistToText( tracklist ) {
		var lines = [];
		var index;
		var track;
		var line;

		for ( index = 0; index < ( tracklist || [] ).length; index++ ) {
			track = tracklist[ index ];
			if ( ! track.title ) {
				continue;
			}
			line = track.number ? track.number + '. ' + track.title : track.title;
			if ( track.length ) {
				line += ' | ' + track.length;
			}
			lines.push( line );
		}

		return lines.join( '\n' );
	}

	/**
	 * Turns a WP_Error style REST payload into a human message.
	 *
	 * @param {Object} error Rejected api() payload.
	 * @return {string}
	 */
	function errorMessage( error ) {
		return ( error && error.message ) || STRINGS.networkError;
	}

	// --- Connect screen ---------------------------------------------------

	function initConnect() {
		app.querySelector( '[data-form="connect"]' ).addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			var form = event.target;
			var username = form.username.value.trim();
			var password = form.password.value.replace( /\s+/g, '' );
			var candidateAuth = window.btoa( username + ':' + password );
			var previous = state.auth;

			state.auth = candidateAuth;
			setError( '[data-connect-error]', '' );

			api( '/categories' )
				.then( function () {
					saveAuth( candidateAuth );
					onConnected();
				} )
				.catch( function ( error ) {
					state.auth = previous;
					setError( '[data-connect-error]', errorMessage( error ) || STRINGS.connectError );
				} );
		} );
	}

	function onConnected() {
		var headerActions = app.querySelector( '[data-header-actions]' );

		if ( headerActions ) {
			headerActions.hidden = false;
		}

		var usernameEl = app.querySelector( '[data-app-username]' );

		if ( usernameEl ) {
			usernameEl.textContent = 'Conectado como ' + currentUsername();
			usernameEl.hidden = false;
		}

		showScreen( 'scan' );
	}

	/**
	 * The username out of the stored "user:pass" credential, for display only.
	 *
	 * @return {string}
	 */
	function currentUsername() {
		try {
			return window.atob( state.auth ).split( ':' )[ 0 ];
		} catch ( error ) {
			return '';
		}
	}

	// --- Scan screen --------------------------------------------------------

	var cameraScanner = null;

	function initScan() {
		var video = app.querySelector( '[data-camera-video]' );
		var hint = app.querySelector( '[data-camera-hint]' );
		var toggleBtn = app.querySelector( '[data-action="toggle-camera"]' );

		if ( window.MelomaniacSyncCameraScanner ) {
			cameraScanner = new window.MelomaniacSyncCameraScanner( video, {
				onDetect: function ( barcode ) {
					stopCamera();
					lookupBarcode( barcode );
				},
				onError: function () {
					hint.textContent = 'No se pudo usar la cámara. Revisa que le hayas dado permiso, o escribe el código a mano.';
					hint.hidden = false;
					video.hidden = true;
				},
			} );
		}

		function stopCamera() {
			if ( cameraScanner ) {
				cameraScanner.stop();
			}
			video.hidden = true;
			toggleBtn.textContent = 'Usar la cámara';
		}

		// Checked once on load rather than only after a tap: with the camera
		// button always visible, a page opened over HTTP instead of HTTPS (the
		// one case camera-scanner.js's ZXing fallback can't work around
		// either, since getUserMedia itself needs a secure context) looked
		// exactly like a tap that "did nothing".
		if ( ! window.isSecureContext ) {
			toggleBtn.hidden = true;
			hint.textContent = 'Esta página no está en HTTPS, así que el navegador no deja usar la cámara aquí. Escribe el código a mano o usa un lector USB.';
			hint.hidden = false;
		} else if ( ! cameraScanner || ! cameraScanner.isSupported() ) {
			toggleBtn.hidden = true;
			hint.textContent = 'Este navegador no puede escanear con la cámara. Escribe el código a mano o usa un lector USB.';
			hint.hidden = false;
		}

		toggleBtn.addEventListener( 'click', function () {
			if ( cameraScanner.isRunning() ) {
				stopCamera();
				return;
			}

			video.hidden = false;
			hint.hidden = true;
			toggleBtn.textContent = 'Detener cámara';
			cameraScanner.start();
		} );

		app.querySelector( '[data-form="barcode"]' ).addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			stopCamera();
			lookupBarcode( event.target.barcode.value.trim() );
		} );

		app.querySelector( '[data-action="go-manual"]' ).addEventListener( 'click', function () {
			state.barcode = '';
			state.rejected = null;
			openManual();
		} );
	}

	function lookupBarcode( barcode ) {
		if ( ! barcode ) {
			return;
		}

		state.barcode = barcode;
		setError( '[data-scan-error]', '' );
		app.querySelector( '[data-scan-loading]' ).hidden = false;

		api( '/lookup', { method: 'POST', body: { barcode: barcode } } )
			.then( function ( result ) {
				app.querySelector( '[data-scan-loading]' ).hidden = true;
				state.candidates = result.candidates || [];

				if ( ! result.resolved || 0 === state.candidates.length ) {
					state.rejected = null;
					openManual( STRINGS.notFound );
					return;
				}

				if ( 1 === state.candidates.length ) {
					openReview( state.candidates[ 0 ] );
					return;
				}

				renderCandidates();
				showScreen( 'candidates' );
			} )
			.catch( function ( error ) {
				app.querySelector( '[data-scan-loading]' ).hidden = true;
				setError( '[data-scan-error]', errorMessage( error ) );
			} );
	}

	// --- Candidates screen ----------------------------------------------

	function initCandidates() {
		app.querySelector( '[data-screen-name="candidates"] [data-action="back-to-scan"]' ).addEventListener( 'click', function () {
			showScreen( 'scan' );
		} );

		app.querySelector( '[data-action="go-manual-from-candidates"]' ).addEventListener( 'click', function () {
			state.rejected = null;
			openManual();
		} );
	}

	function renderCandidates() {
		var list = app.querySelector( '[data-candidate-list]' );
		var index;
		var candidate;
		var button;

		list.innerHTML = '';

		for ( index = 0; index < state.candidates.length; index++ ) {
			candidate = state.candidates[ index ];
			button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'melomaniac-candidate';
			button.innerHTML =
				'<strong>' + escapeHtml( candidate.display_name || '' ) + '</strong>' +
				'<span>' + escapeHtml( [ candidate.label, candidate.year, candidate.format_label ].filter( Boolean ).join( ' · ' ) ) + '</span>';

			( function ( picked ) {
				button.addEventListener( 'click', function () {
					fetchReleaseAndReview( picked.source, picked.mbid || picked.discogs_id );
				} );
			} )( candidate );

			list.appendChild( button );
		}
	}

	function fetchReleaseAndReview( source, id ) {
		api( '/release?source=' + encodeURIComponent( source ) + '&id=' + encodeURIComponent( id ) )
			.then( openReview )
			.catch( function ( error ) {
				setError( '[data-scan-error]', errorMessage( error ) );
				showScreen( 'scan' );
			} );
	}

	// --- Review screen -----------------------------------------------------

	function initReview() {
		app.querySelector( '[data-action="back-to-candidates"]' ).addEventListener( 'click', function () {
			showScreen( state.candidates.length > 1 ? 'candidates' : 'scan' );
		} );

		app.querySelector( '[data-action="go-manual-from-review"]' ).addEventListener( 'click', function () {
			state.rejected = state.review;
			openManual();
		} );

		wireCoverPreview(
			app.querySelector( '[data-review-cover-input]' ),
			app.querySelector( '[data-review-cover-preview]' )
		);

		app.querySelector( '[data-form="review"]' ).addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			var release = state.review;
			var payload = readProductFields( event.target );

			payload.source = release.source;
			payload.release_id = release.mbid || release.discogs_id;
			payload.barcode = state.barcode;

			setError( '[data-review-error]', '' );
			submitWithOptionalCover( app.querySelector( '[data-review-cover-input]' ), payload, '[data-review-error]' );
		} );
	}

	function openReview( release ) {
		state.review = release;

		var summary = app.querySelector( '[data-review-summary]' );
		var tracklistEl = app.querySelector( '[data-review-tracklist]' );

		summary.innerHTML =
			'<h2>' + escapeHtml( release.display_name || '' ) + '</h2>' +
			'<p>' + escapeHtml( [ release.label, release.catalog_number, release.year, release.country, release.format_label ].filter( Boolean ).join( ' · ' ) ) + '</p>';

		var tracklistText = tracklistToText( release.tracklist );
		tracklistEl.textContent = tracklistText;
		tracklistEl.hidden = ! tracklistText;

		var coverInput = app.querySelector( '[data-review-cover-input]' );
		var coverPreview = app.querySelector( '[data-review-cover-preview]' );
		var coverHint = app.querySelector( '[data-review-cover-hint]' );
		var existingCover = release.cover_thumb_url || release.cover_url || '';

		coverInput.value = '';

		if ( existingCover ) {
			coverPreview.style.backgroundImage = 'url(' + existingCover + ')';
			coverPreview.hidden = false;
			coverHint.textContent = 'Esta es la portada que se va a usar. Si quieres, reemplázala con una foto.';
		} else {
			coverPreview.hidden = true;
			coverHint.textContent = 'No encontramos una portada. Puedes tomar una foto o subir una imagen.';
		}

		var mount = app.querySelector( '[data-screen-name="review"] [data-fields-mount]' );
		renderProductFields( mount );

		showScreen( 'review' );
	}

	// --- Manual screen -------------------------------------------------------

	function initManual() {
		var formatSelect = app.querySelector( '#melomaniac-manual-format' );
		var key;
		var option;

		for ( key in FORMATS ) {
			if ( Object.prototype.hasOwnProperty.call( FORMATS, key ) ) {
				option = document.createElement( 'option' );
				option.value = key;
				option.textContent = FORMATS[ key ];
				formatSelect.appendChild( option );
			}
		}

		wireCoverPreview( app.querySelector( '[data-cover-input]' ), app.querySelector( '[data-cover-preview]' ) );

		app.querySelectorAll( '[data-screen-name="manual"] [data-action="back-to-scan"]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				showScreen( 'scan' );
			} );
		} );

		app.querySelector( '[data-form="manual"]' ).addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			var form = event.target;
			var fields = readProductFields( form );
			var payload = Object.assign(
				{
					manual: true,
					barcode: state.barcode,
					artist: form.artist.value,
					title: form.title.value,
					label: form.label.value,
					year: form.year.value,
					format: form.format.value,
					country: form.country.value,
					catalog_number: form.catalog_number.value,
					tracklist: form.tracklist.value,
				},
				fields
			);

			setError( '[data-manual-error]', '' );
			submitWithOptionalCover( app.querySelector( '[data-cover-input]' ), payload, '[data-manual-error]' );
		} );
	}

	function uploadCover( file ) {
		return api( '/media', {
			method: 'POST',
			body: file,
			contentType: file.type || 'image/jpeg',
			filename: file.name || 'portada.jpg',
		} );
	}

	/**
	 * Wires a cover [type=file] input to its preview, shared by the review
	 * and manual screens.
	 *
	 * @param {HTMLInputElement} input   The file input.
	 * @param {HTMLElement}      preview Element whose background-image shows the photo.
	 */
	function wireCoverPreview( input, preview ) {
		input.addEventListener( 'change', function ( event ) {
			var file = event.target.files && event.target.files[ 0 ];

			if ( ! file ) {
				preview.hidden = true;
				return;
			}

			preview.style.backgroundImage = 'url(' + URL.createObjectURL( file ) + ')';
			preview.hidden = false;
		} );
	}

	/**
	 * Uploads a cover photo first when one was picked, then creates the
	 * product either way. Shared by the review and manual forms.
	 *
	 * @param {HTMLInputElement} fileInput     The screen's cover file input.
	 * @param {Object}           payload       Product payload built so far.
	 * @param {string}           errorSelector Where to show a failure.
	 */
	function submitWithOptionalCover( fileInput, payload, errorSelector ) {
		var file = fileInput.files && fileInput.files[ 0 ];

		if ( ! file ) {
			createProduct( payload, errorSelector );
			return;
		}

		uploadCover( file )
			.then( function ( media ) {
				payload.cover_attachment_id = media.id;
				createProduct( payload, errorSelector );
			} )
			.catch( function ( error ) {
				setError( errorSelector, errorMessage( error ) );
			} );
	}

	/**
	 * The same three manual lookup shortcuts the admin's manual form shows
	 * (Discogs, Google, MusicBrainz), for a shop to open in a new tab and
	 * copy data from by hand — the plugin never requests these on its own.
	 *
	 * @param {string} barcode Current barcode, may be empty.
	 * @param {string} extra   Artist/title, when correcting a rejected match.
	 * @return {Array} {label, url, description}.
	 */
	function referenceLinks( barcode, extra ) {
		var query = ( barcode + ' ' + ( extra || '' ) ).trim();

		return [
			{
				label: 'Buscar en Discogs',
				url: 'https://www.discogs.com/search/?' + new URLSearchParams( { q: query, type: 'release' } ).toString(),
				description: 'Abre la búsqueda en discogs.com. Copia a mano los datos que quieras usar.',
			},
			{
				label: 'Buscar en Google',
				url: 'https://www.google.com/search?' + new URLSearchParams( { q: query } ).toString(),
				description: 'Útil cuando el disco es una edición local o muy antigua.',
			},
			{
				label: 'Buscar en MusicBrainz',
				url: 'https://musicbrainz.org/search?' + new URLSearchParams( { query: 'barcode:' + barcode, type: 'release' } ).toString(),
				description: 'Verifica si el disco existe con otro código de barra.',
			},
		];
	}

	/**
	 * Renders the reference links into the manual screen's list.
	 *
	 * @param {string} barcode Current barcode, may be empty.
	 * @param {string} extra   Artist/title, when correcting a rejected match.
	 */
	function renderReferenceLinks( barcode, extra ) {
		var wrap = app.querySelector( '[data-manual-references]' );
		var list = wrap.querySelector( '.melomaniac-reference-links' );

		list.innerHTML = '';

		referenceLinks( barcode, extra ).forEach( function ( link ) {
			var item = document.createElement( 'li' );
			var anchor = document.createElement( 'a' );

			anchor.href = link.url;
			anchor.target = '_blank';
			anchor.rel = 'noopener noreferrer';
			anchor.textContent = link.label;
			item.appendChild( anchor );

			var description = document.createElement( 'span' );
			description.className = 'melomaniac-reference-description';
			description.textContent = link.description;
			item.appendChild( description );

			list.appendChild( item );
		} );

		wrap.hidden = false;
	}

	function openManual( intro ) {
		var form = app.querySelector( '[data-form="manual"]' );
		var release = state.rejected;

		form.reset();
		app.querySelector( '[data-cover-preview]' ).hidden = true;

		var title = app.querySelector( '[data-manual-title]' );
		title.textContent = release ? 'Corregir los datos del disco' : 'Cargar el disco a mano';

		setError( '[data-manual-error]', '' );

		var introEl = app.querySelector( '[data-manual-intro]' );
		introEl.textContent = intro || ( release ? 'Los datos que encontramos ya están cargados. Corrige lo que esté mal y crea el producto.' : '' );
		introEl.hidden = ! introEl.textContent;

		renderReferenceLinks( state.barcode, release ? ( release.artist + ' ' + release.title ).trim() : '' );

		if ( release ) {
			form.artist.value = release.artist || '';
			form.title.value = release.title || '';
			form.label.value = release.label || '';
			form.year.value = release.year || '';
			form.format.value = release.format || 'other';
			form.country.value = release.country || '';
			form.catalog_number.value = release.catalog_number || '';
			form.tracklist.value = tracklistToText( release.tracklist );
		}

		var mount = app.querySelector( '[data-screen-name="manual"] [data-fields-mount]' );
		renderProductFields( mount );

		showScreen( 'manual' );
	}

	// --- Shared: create product, done screen --------------------------------

	function createProduct( payload, errorSelector ) {
		api( '/products', { method: 'POST', body: payload } )
			.then( function ( product ) {
				app.querySelector( '[data-done-message]' ) &&
					( app.querySelector( '[data-done-message]' ).textContent =
						STRINGS.created + ' "' + product.name + '"' );
				var link = app.querySelector( '[data-done-edit-link]' );
				link.href = product.edit_url || '#';
				link.hidden = ! product.edit_url;

				updateQuota( product.quota );

				showScreen( 'done' );
			} )
			.catch( function ( error ) {
				setError( errorSelector, errorMessage( error ) );
			} );
	}

	function updateQuota( quota ) {
		var el = app.querySelector( '[data-quota]' );

		if ( ! el || ! quota || null === quota.limit ) {
			if ( el ) {
				el.hidden = true;
			}
			return;
		}

		el.textContent = quota.used + ' / ' + quota.limit + ' este mes';
		el.hidden = false;
	}

	function initDone() {
		app.querySelector( '[data-action="scan-another"]' ).addEventListener( 'click', function () {
			state.review = null;
			state.rejected = null;
			state.candidates = [];
			showScreen( 'scan' );
		} );
	}

	function initMenu() {
		app.querySelector( '[data-header-actions] [data-action="disconnect"]' ).addEventListener( 'click', function () {
			saveAuth( '' );
			state.auth = '';
			window.location.reload();
		} );
	}

	/**
	 * Minimal HTML escaping for text interpolated into innerHTML.
	 *
	 * @param {string} value Raw text.
	 * @return {string}
	 */
	function escapeHtml( value ) {
		var div = document.createElement( 'div' );
		div.textContent = String( value || '' );
		return div.innerHTML;
	}

	function registerServiceWorker() {
		if ( 'serviceWorker' in window.navigator && config.swUrl ) {
			window.navigator.serviceWorker.register( config.swUrl ).catch( function () {
				// Offline shell is a nice-to-have; the app still works online without it.
			} );
		}
	}

	function boot() {
		initConnect();
		initScan();
		initCandidates();
		initReview();
		initManual();
		initDone();
		initMenu();
		registerServiceWorker();

		state.auth = loadAuth();

		if ( state.auth ) {
			onConnected();
		} else {
			showScreen( 'connect' );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )( window, document );
