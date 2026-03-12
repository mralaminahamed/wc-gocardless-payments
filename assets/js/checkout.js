/**
 * WooCommerce GoCardless Payments — Checkout JavaScript
 *
 * Handles the Billing Request redirect flow on the classic WooCommerce
 * checkout page. Full implementation alongside Phase 2 gateway code.
 *
 * @package WC_GoCardless_Payments
 * @since   1.0.0
 */

/* global wcGoCardlessParams, jQuery */

( function ( $, params ) {
	'use strict';

	/**
	 * GoCardless checkout handler.
	 */
	var WCGoCardlessCheckout = {

		/**
		 * Initialise event bindings.
		 *
		 * @return {void}
		 */
		init: function () {
			$( document.body ).on(
				'checkout_error',
				WCGoCardlessCheckout.onCheckoutError
			);
		},

		/**
		 * Handle checkout errors.
		 *
		 * @param {Event}  event     jQuery event object.
		 * @param {string} errorMsg  Error message from WooCommerce.
		 * @return {void}
		 */
		onCheckoutError: function ( event, errorMsg ) {
			// Phase 2: handle Billing Request redirect errors.
		},
	};

	$( function () {
		WCGoCardlessCheckout.init();
	} );

}( jQuery, window.wcGoCardlessParams || {} ) );
