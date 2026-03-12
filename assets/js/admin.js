/**
 * WooCommerce GoCardless Payments — Admin JavaScript
 *
 * @package WC_GoCardless_Payments
 * @since   1.0.0
 */

/* global wcGoCardlessAdmin, jQuery */

( function ( $, params ) {
	'use strict';

	var WCGoCardlessAdmin = {

		init: function () {
			// Toggle field visibility based on sandbox mode checkbox.
			$( '#woocommerce_gocardless_direct_debit_sandbox_mode' ).on(
				'change',
				WCGoCardlessAdmin.toggleApiFields
			).trigger( 'change' );
		},

		/**
		 * Show/hide live vs sandbox token fields based on mode.
		 *
		 * @return {void}
		 */
		toggleApiFields: function () {
			var isSandbox = $( this ).is( ':checked' );

			$( '#woocommerce_gocardless_direct_debit_live_access_token' )
				.closest( 'tr' )
				.toggle( ! isSandbox );

			$( '#woocommerce_gocardless_direct_debit_sandbox_access_token' )
				.closest( 'tr' )
				.toggle( isSandbox );
		},
	};

	$( function () {
		WCGoCardlessAdmin.init();
	} );

}( jQuery, window.wcGoCardlessAdmin || {} ) );
