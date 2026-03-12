/**
 * WooCommerce GoCardless Payments — Admin JavaScript
 *
 * @package WC_GoCardless_Payments
 * @since   1.0.0
 */

/* global wcGoCardlessAdmin, jQuery */

( function ( $, params ) {
	'use strict';

	var gateways = [
		'gocardless_direct_debit',
		'gocardless_instant_bank',
		'gocardless_vrp',
	];

	var WCGoCardlessAdmin = {

		init: function () {
			gateways.forEach( function ( gateway ) {
				// Toggle field visibility based on sandbox mode checkbox.
				$( '#woocommerce_' + gateway + '_sandbox_mode' ).on(
					'change',
					WCGoCardlessAdmin.toggleApiFields
				).trigger( 'change' );
			} );
		},

		/**
		 * Show/hide live vs sandbox token fields based on mode.
		 *
		 * @return {void}
		 */
		toggleApiFields: function () {
			var isSandbox = $( this ).is( ':checked' );
			var gateway   = this.id.replace( 'woocommerce_', '' ).replace( '_sandbox_mode', '' );

			$( '#woocommerce_' + gateway + '_live_access_token' )
				.closest( 'tr' )
				.toggle( ! isSandbox );

			$( '#woocommerce_' + gateway + '_sandbox_access_token' )
				.closest( 'tr' )
				.toggle( isSandbox );
		},
	};

	$( function () {
		WCGoCardlessAdmin.init();
	} );

}( jQuery, window.wcGoCardlessAdmin || {} ) );
