/**
 * Javascript for the Settings Page.
 *
 * Implements visibility toggles on the plugin's Settings Page.
 *
 * @package WPCV_CGI
 */

/**
 * Pass the jQuery shortcut in.
 *
 * @since 0.3.0
 *
 * @param {Object} $ The jQuery object.
 */
( function( $ ) {

	/**
	 * Act on document ready.
	 *
	 * @since 0.3.0
	 */
	$(document).ready( function() {

		// Define vars.
		var interval = $('#cvgrp_settings_interval_id'),
			sync_details = $('table.sync-details');

		// Initial visibility toggle.
		if ( 'off' === interval.val() ) {
			sync_details.hide();
		} else {
			sync_details.show();
		}

		/**
		 * Add a change event listener to the "Schedule Interval" select.
		 *
		 * @since 0.3.0
		 *
		 * @param {Object} event The event object.
		 */
		interval.on( 'change', function( event ) {
			if ( 'off' === interval.val() ) {
				sync_details.hide();
			} else {
				sync_details.show();
			}
		} );

   	});

} )( jQuery );
