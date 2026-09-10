/**
 * Camera barcode scanning.
 *
 * Uses the browser's native BarcodeDetector, available in Chromium based
 * browsers and on Android. Safari and Firefox do not implement it, so
 * isSupported() returns false there and the screen tells the user to use a USB
 * reader instead. No third party scanning library is bundled.
 *
 * @package Melomaniac_Sync
 */

( function ( window ) {
	'use strict';

	var FORMATS = [ 'ean_13', 'ean_8', 'upc_a', 'upc_e', 'itf' ];

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
	 * Whether this browser can scan with the camera.
	 *
	 * @return {boolean} True when supported.
	 */
	CameraScanner.prototype.isSupported = function () {
		return (
			'BarcodeDetector' in window &&
			!! window.navigator.mediaDevices &&
			!! window.navigator.mediaDevices.getUserMedia
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
	 * Requests the camera and starts the detection loop.
	 */
	CameraScanner.prototype.start = function () {
		var self = this;

		if ( this.running || ! this.isSupported() ) {
			return;
		}

		this.running = true;

		try {
			this.detector = new window.BarcodeDetector( { formats: FORMATS } );
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

			// Detecting on every frame is wasteful; four times a second is plenty.
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
