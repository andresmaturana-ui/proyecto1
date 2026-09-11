/**
 * Camera barcode scanning.
 *
 * Uses the browser's native BarcodeDetector where it exists (Chrome, Edge and
 * Samsung Internet on Android — the fast path, since it runs on-device ML
 * rather than JS). Everywhere else — Safari on iPhone or Mac, and desktop
 * browsers in general, none of which implement BarcodeDetector at all — this
 * falls back to the vendored @zxing/library (vendor-libs/zxing), decoding
 * frames in plain JS instead.
 *
 * @package Melomaniac_Sync
 */

( function ( window ) {
	'use strict';

	var NATIVE_FORMATS = [ 'ean_13', 'ean_8', 'upc_a', 'upc_e', 'itf' ];

	/**
	 * Adapts the vendored ZXing decoder to the same detect(video) => Promise
	 * interface the native BarcodeDetector exposes, so the rest of this file
	 * does not need to know which one is doing the work.
	 *
	 * @constructor
	 */
	function ZXingAdapter() {
		var hints = new Map();
		var formats = window.ZXing.BarcodeFormat;

		hints.set( window.ZXing.DecodeHintType.POSSIBLE_FORMATS, [
			formats.EAN_13,
			formats.EAN_8,
			formats.UPC_A,
			formats.UPC_E,
			formats.ITF,
		] );

		this.reader = new window.ZXing.BrowserMultiFormatReader( hints );
	}

	/**
	 * Decodes one frame from the video element.
	 *
	 * ZXing's decode() is synchronous and throws (NotFoundException, most of
	 * the time) when the frame has no barcode in it, which is the normal case
	 * for most frames; that is reported the same way the native detector
	 * reports "nothing found" — an empty array, not a rejection.
	 *
	 * @param {HTMLVideoElement} video Current video frame source.
	 * @return {Promise<Array>}
	 */
	ZXingAdapter.prototype.detect = function ( video ) {
		try {
			return Promise.resolve( [ { rawValue: this.reader.decode( video ).getText() } ] );
		} catch ( error ) {
			return Promise.resolve( [] );
		}
	};

	/**
	 * Wraps a video element and the detection loop.
	 *
	 * @param {HTMLVideoElement} video    Video element to render the stream in.
	 * @param {Object}           handlers Callbacks: onDetect, onError.
	 * @constructor
	 */
	function CameraScanner( video, handlers ) {
		this.video = video;
		this.handlers = handlers || {};
		this.stream = null;
		this.detector = null;
		this.running = false;
		this.frameHandle = null;
		this.lastAttempt = 0;
	}

	/**
	 * Whether this browser can scan with the camera: a camera to get a stream
	 * from, and either detection backend to read a barcode out of it.
	 *
	 * @return {boolean} True when supported.
	 */
	CameraScanner.prototype.isSupported = function () {
		return (
			!! window.navigator.mediaDevices &&
			!! window.navigator.mediaDevices.getUserMedia &&
			( 'BarcodeDetector' in window || !! window.ZXing )
		);
	};

	/**
	 * Whether the scanner is currently active.
	 *
	 * @return {boolean} True while running.
	 */
	CameraScanner.prototype.isRunning = function () {
		return this.running;
	};

	/**
	 * Builds whichever detection backend this browser has available, native
	 * BarcodeDetector first.
	 *
	 * @return {Object} Something exposing detect(video) => Promise<Array>.
	 */
	CameraScanner.prototype.createDetector = function () {
		if ( 'BarcodeDetector' in window ) {
			return new window.BarcodeDetector( { formats: NATIVE_FORMATS } );
		}

		return new ZXingAdapter();
	};

	/**
	 * Requests the camera and starts the detection loop.
	 */
	CameraScanner.prototype.start = function () {
		var self = this;

		if ( this.running || ! this.isSupported() ) {
			return;
		}

		this.running = true;

		try {
			this.detector = this.createDetector();
		} catch ( error ) {
			this.running = false;
			this.fail( 'cameraUnsupported' );
			return;
		}

		window.navigator.mediaDevices
			.getUserMedia( { video: { facingMode: 'environment' } } )
			.then( function ( stream ) {
				if ( ! self.running ) {
					// Stopped while the permission prompt was open.
					stream.getTracks().forEach( function ( track ) {
						track.stop();
					} );
					return;
				}

				self.stream = stream;
				self.video.srcObject = stream;

				return self.video.play();
			} )
			.then( function () {
				if ( self.running ) {
					self.scheduleFrame();
				}
			} )
			.catch( function () {
				self.running = false;
				self.fail( 'cameraDenied' );
			} );
	};

	/**
	 * Stops the camera and releases the stream.
	 */
	CameraScanner.prototype.stop = function () {
		this.running = false;

		if ( this.frameHandle ) {
			window.cancelAnimationFrame( this.frameHandle );
			this.frameHandle = null;
		}

		if ( this.stream ) {
			this.stream.getTracks().forEach( function ( track ) {
				track.stop();
			} );
			this.stream = null;
		}

		this.video.srcObject = null;
	};

	/**
	 * Queues the next detection attempt.
	 */
	CameraScanner.prototype.scheduleFrame = function () {
		var self = this;

		this.frameHandle = window.requestAnimationFrame( function ( timestamp ) {
			self.frameHandle = null;

			if ( ! self.running ) {
				return;
			}

			// Detecting on every frame is wasteful; four times a second is plenty,
			// and keeps the JS decoder path from pegging the CPU on a phone.
			if ( timestamp - self.lastAttempt < 250 ) {
				self.scheduleFrame();
				return;
			}

			self.lastAttempt = timestamp;
			self.detect();
		} );
	};

	/**
	 * Runs one detection pass over the current video frame.
	 */
	CameraScanner.prototype.detect = function () {
		var self = this;

		this.detector
			.detect( this.video )
			.then( function ( codes ) {
				if ( ! self.running ) {
					return;
				}

				var value = self.pickBarcode( codes );

				if ( value ) {
					if ( typeof self.handlers.onDetect === 'function' ) {
						self.handlers.onDetect( value );
					}
					return;
				}

				self.scheduleFrame();
			} )
			.catch( function () {
				if ( self.running ) {
					self.scheduleFrame();
				}
			} );
	};

	/**
	 * Picks the first plausible barcode out of a detection result.
	 *
	 * @param {Array} codes Detected barcodes.
	 * @return {string} Digits, empty when nothing usable was found.
	 */
	CameraScanner.prototype.pickBarcode = function ( codes ) {
		var index;
		var digits;

		for ( index = 0; index < ( codes || [] ).length; index++ ) {
			digits = String( codes[ index ].rawValue || '' ).replace( /\D/g, '' );

			if ( digits.length >= 6 && digits.length <= 14 ) {
				return digits;
			}
		}

		return '';
	};

	/**
	 * Reports an error to the caller.
	 *
	 * @param {string} code Error code matching a localised string key.
	 */
	CameraScanner.prototype.fail = function ( code ) {
		if ( typeof this.handlers.onError === 'function' ) {
			this.handlers.onError( code );
		}
	};

	window.MelomaniacSyncCameraScanner = CameraScanner;
} )( window );
