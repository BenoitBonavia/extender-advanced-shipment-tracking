/**
 * Scripts d'administration – Extender for Advanced Shipment Tracking.
 *
 * `eastAdmin` est fourni par wp_add_inline_script() : { ajaxUrl, nonce }.
 */
( function ( $ ) {
	'use strict';

	var EAST = {
		init: function () {
			// Les interactions des modules viendront se brancher ici.
		}
	};

	$( function () {
		EAST.init();
	} );

	window.EAST = EAST;
}( jQuery ) );
