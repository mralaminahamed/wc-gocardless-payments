<?php
/**
 * Subscription Renewal Handler — WC_GoCardless_Renewal_Handler
 *
 * Processes scheduled WooCommerce Subscriptions renewal payments for both
 * the Direct Debit and VRP GoCardless gateways.
 *
 * Renewal flow (Direct Debit):
 *   1. WC Subscriptions fires `woocommerce_scheduled_subscription_payment_gocardless_direct_debit`.
 *   2. Renewal handler locates the mandate_id on the parent subscription order.
 *   3. Verifies the mandate is still active via the GoCardless Mandates API.
 *   4. Creates a GoCardless payment directly against the mandate.
 *   5. Stores the payment_id on the renewal order; sets status to on-hold.
 *   6. Webhook payment.confirmed transitions the renewal order to processing.
 *
 * Renewal flow (VRP):
 *   1. WC Subscriptions fires `woocommerce_scheduled_subscription_payment_gocardless_vrp`.
 *   2. Renewal handler locates the VRP consent (mandate_id) on the parent order.
 *   3. Verifies consent is active and amount is within VRP constraints.
 *   4. Creates payment via WC_GoCardless_API_VRP::create_payment().
 *   5. Webhook payment.confirmed transitions the renewal order.
 *
 * Key design decisions:
 *   - The parent subscription's payment method determines which flow is used.
 *   - If a mandate is inactive, the subscription is placed on-hold and
 *     a customer email is triggered via `wcs_create_customer_note()`.
 *   - Idempotency keys are scoped to renewal_order + attempt to prevent
 *     double-charging on retry.
 *   - All GoCardless API errors are caught; WC Subscriptions error handling
 *     is invoked via `$renewal_order->update_status('failed', ...)`.
 *
 * @package WC_GoCardless_Payments\Subscriptions
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_GoCardless_Renewal_Handler
 *
 * @since 1.0.0
 */
class WC_GoCardless_Renewal_Handler {

	/**
	 * Logger instance.
	 *
	 * @since 1.0.0
	 * @var WC_GoCardless_Logger
	 */
	private WC_GoCardless_Logger $logger;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->logger = wc_gocardless_payments()->logger;
	}

	/**
	 * Process a Direct Debit scheduled subscription renewal payment.
	 *
	 * Retrieves the stored mandate ID from the subscription's parent order,
	 * verifies it is active, and creates a GoCardless payment.
	 *
	 * @since 1.0.0
	 *
	 * @param float    $amount_to_charge Total amount to charge for the renewal.
	 * @param WC_Order $renewal_order    WooCommerce renewal order created by Subscriptions.
	 * @return void
	 */
	public function process_direct_debit_renewal( float $amount_to_charge, WC_Order $renewal_order ): void {
		$this->logger->info(
			sprintf(
				'[Renewal][DD] Processing renewal #%d — %s %s',
				$renewal_order->get_id(),
				$amount_to_charge,
				$renewal_order->get_currency()
			)
		);

		$mandate_id = $this->get_mandate_id_for_renewal( $renewal_order );

		if ( empty( $mandate_id ) ) {
			$this->fail_renewal(
				$renewal_order,
				__( 'No Direct Debit mandate found for renewal. Customer must re-authorise.', 'wc-gocardless-payments' )
			);
			return;
		}

		// Verify the mandate is still active before attempting payment.
		try {
			$mandates_api  = new WC_GoCardless_API_Mandates( wc_gocardless_payments()->api );
			$mandate_resp  = $mandates_api->get( $mandate_id );

			if ( ! $mandates_api->is_active( $mandate_resp ) ) {
				$mandate_status = $mandate_resp['mandates']['status'] ?? 'unknown';

				$this->logger->warning(
					sprintf(
						'[Renewal][DD] Mandate %s is not active (status: %s) for renewal #%d.',
						$mandate_id,
						$mandate_status,
						$renewal_order->get_id()
					)
				);

				$this->fail_renewal(
					$renewal_order,
					sprintf(
					/* translators: 1: Mandate ID 2: Mandate status */
						__( 'Direct Debit mandate %1$s is not active (status: %2$s). Subscription placed on-hold.', 'wc-gocardless-payments' ),
						$mandate_id,
						$mandate_status
					),
					true // place_on_hold rather than fail.
				);
				return;
			}
		} catch ( WC_GoCardless_API_Exception $e ) {
			$this->logger->error(
				sprintf(
					'[Renewal][DD] Cannot verify mandate %s for renewal #%d: %s',
					$mandate_id,
					$renewal_order->get_id(),
					$e->getMessage()
				)
			);

			$this->fail_renewal(
				$renewal_order,
				sprintf(
				/* translators: %s: API error message */
					__( 'GoCardless mandate verification failed: %s', 'wc-gocardless-payments' ),
					$e->getMessage()
				)
			);
			return;
		}

		// Create the GoCardless payment against the mandate.
		$this->create_direct_debit_payment( $renewal_order, $mandate_id, $amount_to_charge );
	}

	/**
	 * Process a VRP scheduled subscription renewal payment.
	 *
	 * Retrieves the VRP consent (mandate) from the parent order,
	 * verifies it is active, and creates a VRP payment within the constraints.
	 *
	 * @since 1.0.0
	 *
	 * @param float    $amount_to_charge Total amount to charge for the renewal.
	 * @param WC_Order $renewal_order    WooCommerce renewal order.
	 * @return void
	 */
	public function process_vrp_renewal( float $amount_to_charge, WC_Order $renewal_order ): void {
		$this->logger->info(
			sprintf(
				'[Renewal][VRP] Processing renewal #%d — %s %s',
				$renewal_order->get_id(),
				$amount_to_charge,
				$renewal_order->get_currency()
			)
		);

		$consent_id = $this->get_vrp_consent_id_for_renewal( $renewal_order );

		if ( empty( $consent_id ) ) {
			$this->fail_renewal(
				$renewal_order,
				__( 'No VRP consent found for renewal. Customer must re-authorise.', 'wc-gocardless-payments' )
			);
			return;
		}

		// Verify the VRP consent is active.
		try {
			$vrp_api      = new WC_GoCardless_API_VRP( wc_gocardless_payments()->api );
			$consent_resp = $vrp_api->get_consent( $consent_id );

			if ( ! $vrp_api->is_consent_active( $consent_resp ) ) {
				$consent_status = $consent_resp['mandates']['status'] ?? 'unknown';

				$this->logger->warning(
					sprintf(
						'[Renewal][VRP] Consent %s is not active (status: %s) for renewal #%d.',
						$consent_id,
						$consent_status,
						$renewal_order->get_id()
					)
				);

				$this->fail_renewal(
					$renewal_order,
					sprintf(
					/* translators: 1: Consent ID 2: Consent status */
						__( 'VRP consent %1$s is not active (status: %2$s). Subscription placed on-hold.', 'wc-gocardless-payments' ),
						$consent_id,
						$consent_status
					),
					true
				);
				return;
			}
		} catch ( WC_GoCardless_API_Exception $e ) {
			$this->logger->error(
				sprintf(
					'[Renewal][VRP] Cannot verify consent %s for renewal #%d: %s',
					$consent_id,
					$renewal_order->get_id(),
					$e->getMessage()
				)
			);

			$this->fail_renewal(
				$renewal_order,
				sprintf(
				/* translators: %s: Error message */
					__( 'VRP consent verification failed: %s', 'wc-gocardless-payments' ),
					$e->getMessage()
				)
			);
			return;
		}

		// Create the VRP payment.
		$this->create_vrp_payment( $renewal_order, $consent_id, $amount_to_charge );
	}

	/**
	 * Create a GoCardless Direct Debit payment for a renewal order.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $renewal_order    Renewal order.
	 * @param string   $mandate_id       GoCardless mandate ID.
	 * @param float    $amount_to_charge Amount to charge.
	 * @return void
	 */
	private function create_direct_debit_payment(
		WC_Order $renewal_order,
		string $mandate_id,
		float $amount_to_charge
	): void {
		$currency        = $renewal_order->get_currency();
		$idempotency     = new WC_GoCardless_Idempotency();
		$idempotency_key = $idempotency->get_or_create_for_order( $renewal_order, 'renewal_payment' );
		$description     = $this->get_renewal_description( $renewal_order );

		try {
			$payments_api     = new WC_GoCardless_API_Payments( wc_gocardless_payments()->api );
			$payment_response = $payments_api->create(
				$mandate_id,
				$this->to_minor_units( $amount_to_charge, $currency ),
				$currency,
				$description,
				$idempotency_key
			);

			$payment    = $payment_response['payments'] ?? array();
			$payment_id = $payment['id'] ?? '';

			if ( empty( $payment_id ) ) {
				throw new WC_GoCardless_API_Exception(
					__( 'GoCardless returned an invalid payment response — missing ID.', 'wc-gocardless-payments' ),
					'invalid_response'
				);
			}

			WC_GoCardless_Order_Helper::bulk_update_meta(
				$renewal_order,
				array(
					'payment_id'   => $payment_id,
					'mandate_id'   => $mandate_id,
					'payment_type' => 'direct_debit',
				)
			);

			$renewal_order->update_status(
				'on-hold',
				sprintf(
				/* translators: 1: Payment ID 2: Mandate ID */
					__( 'GoCardless renewal payment created (Payment ID: %1$s, Mandate: %2$s). Awaiting bank confirmation.', 'wc-gocardless-payments' ),
					esc_html( $payment_id ),
					esc_html( $mandate_id )
				)
			);

			$this->logger->info(
				sprintf(
					'[Renewal][DD] Renewal #%d — payment %s created against mandate %s.',
					$renewal_order->get_id(),
					$payment_id,
					$mandate_id
				)
			);

			/**
			 * Fires after a Direct Debit renewal payment is successfully created.
			 *
			 * @since 1.0.0
			 *
			 * @param WC_Order $renewal_order WooCommerce renewal order.
			 * @param string   $payment_id   GoCardless payment ID.
			 * @param string   $mandate_id   GoCardless mandate ID.
			 */
			do_action( 'wc_gocardless_renewal_payment_created', $renewal_order, $payment_id, $mandate_id );

		} catch ( WC_GoCardless_API_Exception $e ) {
			$this->logger->error(
				sprintf(
					'[Renewal][DD] Payment creation failed for renewal #%d [%s]: %s',
					$renewal_order->get_id(),
					$e->get_error_type(),
					$e->getMessage()
				)
			);

			$this->fail_renewal(
				$renewal_order,
				sprintf(
				/* translators: %s: API error message */
					__( 'GoCardless renewal payment failed: %s', 'wc-gocardless-payments' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Create a GoCardless VRP payment for a renewal order.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $renewal_order    Renewal order.
	 * @param string   $consent_id       GoCardless VRP mandate (consent) ID.
	 * @param float    $amount_to_charge Amount to charge.
	 * @return void
	 */
	private function create_vrp_payment(
		WC_Order $renewal_order,
		string $consent_id,
		float $amount_to_charge
	): void {
		$currency        = $renewal_order->get_currency();
		$idempotency     = new WC_GoCardless_Idempotency();
		$idempotency_key = $idempotency->get_or_create_for_order( $renewal_order, 'vrp_renewal_payment' );
		$description     = $this->get_renewal_description( $renewal_order );

		try {
			$vrp_api          = new WC_GoCardless_API_VRP( wc_gocardless_payments()->api );
			$payment_response = $vrp_api->create_payment(
				$consent_id,
				$this->to_minor_units( $amount_to_charge, $currency ),
				$currency,
				$description,
				$idempotency_key
			);

			$payment    = $payment_response['payments'] ?? array();
			$payment_id = $payment['id'] ?? '';

			if ( empty( $payment_id ) ) {
				throw new WC_GoCardless_API_Exception(
					__( 'GoCardless returned an invalid VRP payment response — missing ID.', 'wc-gocardless-payments' ),
					'invalid_response'
				);
			}

			WC_GoCardless_Order_Helper::bulk_update_meta(
				$renewal_order,
				array(
					'payment_id'   => $payment_id,
					'mandate_id'   => $consent_id,
					'payment_type' => 'vrp',
				)
			);

			// VRP payments confirm faster than Direct Debit (open banking rails).
			// Order is set to on-hold; webhook payment.confirmed completes it.
			$renewal_order->update_status(
				'on-hold',
				sprintf(
				/* translators: 1: Payment ID 2: Consent ID */
					__( 'GoCardless VRP renewal payment created (Payment ID: %1$s, Consent: %2$s). Awaiting settlement.', 'wc-gocardless-payments' ),
					esc_html( $payment_id ),
					esc_html( $consent_id )
				)
			);

			$this->logger->info(
				sprintf(
					'[Renewal][VRP] Renewal #%d — VRP payment %s created against consent %s.',
					$renewal_order->get_id(),
					$payment_id,
					$consent_id
				)
			);

			/**
			 * Fires after a VRP renewal payment is successfully created.
			 *
			 * @since 1.0.0
			 *
			 * @param WC_Order $renewal_order WooCommerce renewal order.
			 * @param string   $payment_id   GoCardless VRP payment ID.
			 * @param string   $consent_id   GoCardless VRP consent (mandate) ID.
			 */
			do_action( 'wc_gocardless_vrp_renewal_payment_created', $renewal_order, $payment_id, $consent_id );

		} catch ( WC_GoCardless_API_Exception $e ) {
			$this->logger->error(
				sprintf(
					'[Renewal][VRP] Payment creation failed for renewal #%d [%s]: %s',
					$renewal_order->get_id(),
					$e->get_error_type(),
					$e->getMessage()
				)
			);

			// If GoCardless reports a consent constraint violation, surface clearly.
			$note = $e->get_error_type() === 'mandate_payment_amount_over_limit'
				? sprintf(
				/* translators: %s: Constraint error */
					__( 'VRP renewal failed — amount exceeds consent limit: %s', 'wc-gocardless-payments' ),
					$e->getMessage()
				)
				: sprintf(
				/* translators: %s: API error message */
					__( 'GoCardless VRP renewal payment failed: %s', 'wc-gocardless-payments' ),
					$e->getMessage()
				);

			$this->fail_renewal( $renewal_order, $note );
		}
	}

	/**
	 * Find the Direct Debit mandate ID for a renewal order.
	 *
	 * Searches the renewal order's related subscriptions for a stored
	 * mandate_id meta value set during the initial checkout or a prior renewal.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $renewal_order WooCommerce renewal order.
	 * @return string GoCardless mandate ID or empty string.
	 */
	private function get_mandate_id_for_renewal( WC_Order $renewal_order ): string {
		// Check if the mandate_id is already on the renewal order itself.
		$mandate_id = WC_GoCardless_Order_Helper::get_mandate_id( $renewal_order );

		if ( ! empty( $mandate_id ) ) {
			return $mandate_id;
		}

		// Walk the subscription hierarchy to find the parent order's mandate.
		return $this->get_mandate_from_subscription( $renewal_order, 'mandate_id' );
	}

	/**
	 * Find the VRP consent (mandate) ID for a renewal order.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $renewal_order WooCommerce renewal order.
	 * @return string GoCardless VRP mandate ID or empty string.
	 */
	private function get_vrp_consent_id_for_renewal( WC_Order $renewal_order ): string {
		$consent_id = WC_GoCardless_Order_Helper::get_vrp_consent_id( $renewal_order );

		if ( ! empty( $consent_id ) ) {
			return $consent_id;
		}

		return $this->get_mandate_from_subscription( $renewal_order, 'mandate_id' );
	}

	/**
	 * Walk the subscription chain to retrieve a GoCardless meta value
	 * from the parent (initial) subscription order.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $renewal_order WooCommerce renewal order.
	 * @param string   $meta_key      Meta key suffix (without prefix).
	 * @return string Retrieved meta value, or empty string.
	 */
	private function get_mandate_from_subscription( WC_Order $renewal_order, string $meta_key ): string {
		if ( ! function_exists( 'wcs_get_subscriptions_for_renewal_order' ) ) {
			return '';
		}

		$subscriptions = wcs_get_subscriptions_for_renewal_order( $renewal_order );

		foreach ( $subscriptions as $subscription ) {
			// Check the subscription object itself.
			$value = WC_GoCardless_Order_Helper::get_meta( $subscription, $meta_key );

			if ( ! empty( $value ) ) {
				return (string) $value;
			}

			// Check the subscription's parent (initial) order.
			$parent_order = $subscription->get_parent();

			if ( $parent_order instanceof WC_Order ) {
				$value = WC_GoCardless_Order_Helper::get_meta( $parent_order, $meta_key );

				if ( ! empty( $value ) ) {
					return (string) $value;
				}
			}
		}

		return '';
	}

	/**
	 * Mark a renewal order as failed and optionally suspend the subscription.
	 *
	 * When WooCommerce Subscriptions processes a failed renewal, it automatically
	 * triggers retry logic and customer notifications if configured.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $renewal_order WooCommerce renewal order.
	 * @param string   $reason        Human-readable failure reason for the order note.
	 * @param bool     $place_on_hold Whether to place on-hold rather than failed.
	 * @return void
	 */
	private function fail_renewal(
		WC_Order $renewal_order,
		string $reason,
		bool $place_on_hold = false
	): void {
		$new_status = $place_on_hold ? 'on-hold' : 'failed';

		$renewal_order->update_status( $new_status, $reason );

		/**
		 * Fires when a GoCardless subscription renewal payment fails.
		 *
		 * @since 1.0.0
		 *
		 * @param WC_Order $renewal_order WooCommerce renewal order.
		 * @param string   $reason        Failure reason.
		 * @param bool     $place_on_hold True when on-hold rather than failed.
		 */
		do_action( 'wc_gocardless_renewal_failed', $renewal_order, $reason, $place_on_hold );

		$this->logger->error(
			sprintf(
				'[Renewal] Order #%d marked as %s: %s',
				$renewal_order->get_id(),
				$new_status,
				$reason
			)
		);
	}

	/**
	 * Build a payment description for a renewal order.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $renewal_order WooCommerce renewal order.
	 * @return string Payment description (max 140 characters).
	 */
	private function get_renewal_description( WC_Order $renewal_order ): string {
		return substr(
			sprintf(
			/* translators: 1: Site name 2: Order number */
				__( '%1$s — Renewal Order #%2$s', 'wc-gocardless-payments' ),
				get_bloginfo( 'name' ),
				$renewal_order->get_order_number()
			),
			0,
			140
		);
	}

	/**
	 * Convert a decimal amount to the currency's smallest unit (minor units).
	 *
	 * Mirrors the base gateway method — duplicated here so the renewal handler
	 * does not depend on a gateway instance being available.
	 *
	 * @since 1.0.0
	 *
	 * @param float  $amount   Decimal amount.
	 * @param string $currency ISO 4217 currency code.
	 * @return int Amount in minor units.
	 */
	private function to_minor_units( float $amount, string $currency ): int {
		$zero_decimal = array(
			'JPY', 'BIF', 'CLP', 'GNF', 'KMF', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
		);

		if ( in_array( strtoupper( $currency ), $zero_decimal, true ) ) {
			return (int) round( $amount );
		}

		return (int) round( $amount * 100 );
	}
}
