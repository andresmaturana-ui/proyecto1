/**
 * Opens MusicBrainz's own "Add Release" page, pre-filled from a manual entry
 * form, so a shop can contribute a disc MusicBrainz doesn't have yet.
 *
 * MusicBrainz has no API to create a release automatically: releases are
 * reviewed and entered by its own community, by design, to keep the data
 * consistent. What it does offer is documented "seeding" of the release
 * editor via a POST request with a specific set of field names
 * (https://musicbrainz.org/doc/Development/Seeding/Release_Editor) — the
 * same mechanism other third-party tools use to hand off to it. This never
 * creates anything on its own: it opens musicbrainz.org in a new tab with
 * as much of the form filled in as it can, and the shop reviews, finishes
 * and submits it there, signed in with their own MusicBrainz account.
 *
 * Shared between the admin manual form and the app's manual screen — both
 * post the same field names (built by Melomaniac_Sync_Manual_Release_Builder
 * on the server), so one mapping here covers both.
 *
 * @package Melomaniac_Sync
 */

( function ( window, document ) {
	'use strict';

	var ADD_RELEASE_URL = 'https://musicbrainz.org/release/add';

	/**
	 * Known format keys mapped to a MusicBrainz medium format name. Best
	 * guess for vinyl (12" is the most common pressing); the shop can pick a
	 * different size on MusicBrainz's own page if this one is a 7" or 10".
	 */
	var FORMATS = {
		vinyl: '12" Vinyl',
		cd: 'CD',
		cassette: 'Cassette',
	};

	/**
	 * Splits a manual-form tracklist textarea into MusicBrainz seed fields.
	 *
	 * Deliberately simpler than the server's own parse_tracklist(): this only
	 * has to produce something MusicBrainz can seed a text field with, not a
	 * structured value the plugin itself will store.
	 *
	 * @param {string} raw   Tracklist textarea content.
	 * @param {Object} out   Field map to add entries to.
	 */
	function seedTracklist( raw, out ) {
		var lines = ( raw || '' ).split( /\r\n|\r|\n/ );
		var position = 0;

		lines.forEach( function ( line ) {
			line = line.trim();

			if ( ! line ) {
				return;
			}

			var numbered = line.match( /^([A-Za-z0-9]{1,3})[.)]\s*(.+)$/ );

			if ( numbered ) {
				line = numbered[ 2 ];
			}

			var length = '';
			var parts = line.split( '|' );

			if ( parts.length > 1 ) {
				var candidate = parts[ parts.length - 1 ].trim();

				if ( /^\d{1,3}:[0-5]\d$/.test( candidate ) ) {
					length = candidate;
					parts.pop();
					line = parts.join( '|' );
				}
			}

			line = line.trim();

			if ( ! line ) {
				return;
			}

			out[ 'edit-release.mediums.0.track.' + position + '.name' ] = line;

			if ( length ) {
				out[ 'edit-release.mediums.0.track.' + position + '.length' ] = length;
			}

			position++;
		} );
	}

	/**
	 * Reads a manual entry form's current values into MusicBrainz seed
	 * fields. Only fields with something typed in are included — MusicBrainz
	 * leaves the rest blank for the shop to fill in on its own page.
	 *
	 * @param {HTMLFormElement} form Manual entry form (admin or the app).
	 * @return {Object<string,string>}
	 */
	function seedFields( form ) {
		var out = {};

		/**
		 * @param {string} key   MusicBrainz seed field name.
		 * @param {string} value Value, skipped when empty.
		 */
		function set( key, value ) {
			value = ( value || '' ).trim();

			if ( value ) {
				out[ key ] = value;
			}
		}

		set( 'edit-release.name', form.title ? form.title.value : '' );
		set( 'edit-release.artist_credit.names.0.artist.name', form.artist ? form.artist.value : '' );
		set( 'edit-release.artist_credit.names.0.name', form.artist ? form.artist.value : '' );
		set( 'edit-release.barcode', form.barcode ? form.barcode.value : '' );
		set( 'edit-release.events.0.date.year', form.year ? form.year.value : '' );
		set( 'edit-release.events.0.date.month', form.month ? form.month.value : '' );
		set( 'edit-release.events.0.date.day', form.day ? form.day.value : '' );
		set( 'edit-release.events.0.country', form.country ? form.country.value : '' );
		set( 'edit-release.labels.0.name', form.label ? form.label.value : '' );
		set( 'edit-release.labels.0.catalog_number', form.catalog_number ? form.catalog_number.value : '' );

		if ( form.status && form.status.value ) {
			set( 'edit-release.status', form.status.value.toLowerCase() );
		}

		if ( form.language ) {
			set( 'edit-release.language', form.language.value );
		}

		if ( form.script ) {
			set( 'edit-release.script', form.script.value );
		}

		if ( form.packaging ) {
			set( 'edit-release.packaging', form.packaging.value );
		}

		var formatKey = form.format ? form.format.value : '';
		set( 'edit-release.mediums.0.format', FORMATS[ formatKey ] || '' );

		seedTracklist( form.tracklist ? form.tracklist.value : '', out );

		return out;
	}

	/**
	 * Opens MusicBrainz's Add Release page in a new tab, seeded from the
	 * given manual entry form's current values.
	 *
	 * A fresh, minimal <form> is built for this rather than pointing the
	 * shop's own form at MusicBrainz directly, so only the mapped fields
	 * above are sent — not the nonce, price or category fields the manual
	 * form also carries, which mean nothing to MusicBrainz and have no
	 * reason to leave this site.
	 *
	 * @param {HTMLFormElement} form Manual entry form to read values from.
	 */
	function submit( form ) {
		var fields = seedFields( form );
		var seedForm = document.createElement( 'form' );

		seedForm.method = 'POST';
		seedForm.action = ADD_RELEASE_URL;
		seedForm.target = '_blank';
		seedForm.rel = 'noopener';
		seedForm.style.display = 'none';

		Object.keys( fields ).forEach( function ( key ) {
			var input = document.createElement( 'input' );
			input.type = 'hidden';
			input.name = key;
			input.value = fields[ key ];
			seedForm.appendChild( input );
		} );

		document.body.appendChild( seedForm );
		seedForm.submit();
		document.body.removeChild( seedForm );
	}

	window.MelomaniacSyncMusicBrainzSeed = {
		seedFields: seedFields,
		submit: submit,
	};
} )( window, document );
