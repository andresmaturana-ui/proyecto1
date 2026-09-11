/**
 * Melomaniac Sync — app shell service worker.
 *
 * Caches only the shell itself (this page, its script and its stylesheet) so
 * the app still opens on a weak connection. Everything else — the REST API,
 * the camera stream, the barcode lookups — needs a live connection anyway,
 * so it is left to hit the network untouched rather than pretending to work
 * offline.
 *
 * @package Melomaniac_Sync
 */

var CACHE_NAME = 'melomaniac-sync-app-v__CACHE_VERSION__';
var SHELL_URLS = [
	'__SHELL_URL__',
	'__APP_JS_URL__',
	'__APP_CSS_URL__',
	'__CAMERA_JS_URL__',
	'__ZXING_JS_URL__',
	'__MB_SEED_JS_URL__',
];

self.addEventListener( 'install', function ( event ) {
	event.waitUntil(
		caches
			.open( CACHE_NAME )
			.then( function ( cache ) {
				return cache.addAll( SHELL_URLS );
			} )
			.then( function () {
				return self.skipWaiting();
			} )
	);
} );

self.addEventListener( 'activate', function ( event ) {
	event.waitUntil(
		caches
			.keys()
			.then( function ( names ) {
				return Promise.all(
					names
						.filter( function ( name ) {
							return name !== CACHE_NAME;
						} )
						.map( function ( name ) {
							return caches.delete( name );
						} )
				);
			} )
			.then( function () {
				return self.clients.claim();
			} )
	);
} );

self.addEventListener( 'fetch', function ( event ) {
	var request = event.request;

	// Only the app's own shell is cached; the REST API and anything else
	// (including cross-origin requests) is left completely untouched.
	if ( 'GET' !== request.method || -1 === SHELL_URLS.indexOf( request.url ) ) {
		return;
	}

	event.respondWith(
		caches.match( request ).then( function ( cached ) {
			var network = fetch( request )
				.then( function ( response ) {
					caches.open( CACHE_NAME ).then( function ( cache ) {
						cache.put( request, response.clone() );
					} );
					return response;
				} )
				.catch( function () {
					return cached;
				} );

			return cached || network;
		} )
	);
} );
