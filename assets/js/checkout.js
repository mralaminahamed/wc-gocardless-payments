/**
 * WooCommerce GoCardless Payments — Checkout JavaScript
 *
 * Handles the Billing Request redirect flow on the classic WooCommerce checkout:
 *   - Manages pending-payment UI state during GoCardless redirect.
 *   - Handles return-URL query parameter cleanup after authorisation.
 *   - Toggles the "new mandate" UI when switching between saved/new methods.
 *
 * @package WC_GoCardless_Payments
 * @since   1.0.0
 */

/* global wcGoCardlessParams, jQuery */

( function ( $, params ) {
	'use strict';

	var WCGoCardlessCheckout = {

		/**
		 * Initialise event bindings.
		 *
		 * @return {void}
		 */
		init: function () {
			// Handle checkout submission for GoCardless gateways.
			$( document.body ).on(
				'checkout_place_order_gocardless_direct_debit checkout_place_order_gocardless_instant_bank',
				WCGoCardlessCheckout.onPlaceOrder
			);

			// Handle payment method switching (show/hide new mandate notice).
			$( document.body ).on(
				'payment_method_selected',
				WCGoCardlessCheckout.onPaymentMethodSelected
			);

			// Clean up GoCardless query params from the URL after return.
			WCGoCardlessCheckout.cleanReturnUrl();
		},

		/**
		 * Called when customer clicks Place Order with a GoCardless gateway selected.
		 *
		 * Shows a processing overlay to indicate the redirect is in progress.
		 *
		 * @param {Event} event jQuery event object.
		 * @return {boolean} Always true — let WooCommerce handle the AJAX submission.
		 */
		onPlaceOrder: function ( event ) {
			// Show processing indicator — will be replaced by the GoCardless redirect.
			$( '#place_order' )
				.prop( 'disabled', true )
				.text( params.i18n.processing || 'Processing…' );

			return true;
		},

		/**
		 * Toggle the "new mandate" notice visibility when payment method changes.
		 *
		 * Also shows/hides IBP-specific bank list and notices.
		 *
		 * @return {void}
		 */
		onPaymentMethodSelected: function () {
			var selectedMethod = $( 'input[name="payment_method"]:checked' ).val();
			var isGoCardless   = selectedMethod && selectedMethod.indexOf( 'gocardless' ) === 0;
			var isIBP          = 'gocardless_instant_bank' === selectedMethod;

			$( '.wc-gocardless-redirect-notice' ).toggle( isGoCardless );
			$( '.wc-gocardless-bank-list' ).toggle( isIBP );
		},

		/**
		 * Remove GoCardless-specific query parameters from the current URL
		 * without triggering a page reload.
		 *
		 * Prevents duplicate processing if the customer refreshes the return page.
		 *
		 * @return {void}
		 */
		cleanReturnUrl: function () {
			if ( ! window.history || ! window.history.replaceState ) {
				return;
			}

			var urlParams     = new URLSearchParams( window.location.search );
			var gcParams      = [ 'billing_request_id', 'billing_request_flow_id', 'nonce' ];
			var needsCleaning = false;

			gcParams.forEach( function ( param ) {
				if ( urlParams.has( param ) ) {
					urlParams.delete( param );
					needsCleaning = true;
				}
			} );

			if ( needsCleaning ) {
				var cleanUrl = window.location.pathname;
				if ( urlParams.toString() ) {
					cleanUrl += '?' + urlParams.toString();
				}
				window.history.replaceState( {}, document.title, cleanUrl );
			}
		},
	};

	$( function () {
		WCGoCardlessCheckout.init();
	} );

}( jQuery, window.wcGoCardlessParams || {} ) );
