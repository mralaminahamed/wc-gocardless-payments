<?php
/**
 * WooCommerce Subscriptions Integration — WC_GoCardless_Subscriptions
 *
 * Registers and coordinates all hooks required for WooCommerce Subscriptions
 * (Automattic) compatibility, including:
 *
 *   - Scheduled renewal payment processing (Direct Debit and VRP).
 *   - Subscription status synchronisation when mandates/consents change.
 *   - Payment method change handling (customer updates their bank mandate).
 *   - Subscription meta propagation from parent to renewal orders.
 *   - Failed payment retry gate (prevent retrying on inactive mandates).
 *
 * This class acts solely as a hook coordinator. All payment logic is
 * delegated to WC_GoCardless_Renewal_Handler.
 *
 * @package WC_GoCardless_Payments\Subscriptions
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_GoCardless_Subscriptions
 *
 * @since 1.0.0
 */
class WC_GoCardless_Subscriptions {

	/**
	 * Renewal handler instance.
	 *
	 * @since 1.0.0
	 * @var WC_GoCardless_Renewal_Handler
	 */
	private WC_GoCardless_Renewal_Handler $renewal_handler;

	/**
	 * Constructor — instantiate the renewal handler and register all hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->renewal_handler = new WC_GoCardless_Renewal_Handler();

		$this->register_renewal_hooks();
		$this->register_lifecycle_hooks();
		$this->register_meta_hooks();
	}

	// -------------------------------------------------------------------------
	// Hook registration
	// -------------------------------------------------------------------------

	/**
	 * Register scheduled renewal payment hooks for each gateway.
	 *
	 * WooCommerce Subscriptions fires `woocommerce_scheduled_subscription_payment_{gateway_id}`
	 * for each active gateway when a renewal is due.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function register_renewal_hooks(): void {
		// Direct Debit renewal.
		add_action(
			'woocommerce_scheduled_subscription_payment_gocardless_direct_debit',
			array( $this, 'process_direct_debit_renewal' ),
			10,
			2
		);

		// VRP renewal.
		add_action(
			'woocommerce_scheduled_subscription_payment_gocardless_vrp',
			array( $this, 'process_vrp_renewal' ),
			10,
			2
		);
	}

	/**
	 * Register subscription lifecycle hooks.
	 *
	 * Handles subscription cancellations, payment method changes,
	 * and reactivation events.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function register_lifecycle_hooks(): void {
		// Cancel the GoCardless mandate when a subscription is cancelled.
		add_action(
			'woocommerce_subscription_status_cancelled',
			array( $this, 'handle_subscription_cancelled' )
		);

		// When payment method changes on a subscription, update the stored mandate.
		add_action(
			'woocommerce_subscription_payment_method_updated',
			array( $this, 'handle_payment_method_updated' ),
			10,
			3
		);

		// Synchronise WC subscription status when GoCardless mandate is invalidated.
		add_action(
			'wc_gocardless_mandate_invalidated',
			array( $this, 'handle_mandate_invalidated_for_subscriptions' ),
			10,
			3
		);

		// Exclude GoCardless gateways from manual renewal when mandate is inactive.
		add_filter(
			'woocommerce_can_subscription_be_updated_to_active',
			array( $this, 'gate_subscription_reactivation' ),
			10,
			2
		);
	}

	/**
	 * Register order meta copy hooks.
	 *
	 * Propagates GoCardless mandate and consent IDs from parent orders
	 * to renewal orders so the renewal handler can locate them.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function register_meta_hooks(): void {
		// Copy GoCardless meta from the subscription to its renewal order.
		add_action(
			'woocommerce_checkout_order_created',
			array( $this, 'copy_meta_to_renewal_order' )
		);

		// Also copy when WC Subscriptions creates the renewal order directly.
		add_action(
			'wcs_renewal_order_created',
			array( $this, 'copy_meta_to_renewal_order_from_subscription' ),
			10,
			2
		);
	}

	// -------------------------------------------------------------------------
	// Renewal payment dispatchers
	// -------------------------------------------------------------------------

	/**
	 * Dispatch a Direct Debit scheduled renewal to the renewal handler.
	 *
	 * @since 1.0.0
	 *
	 * @param float    $amount_to_charge Amount to charge.
	 * @param WC_Order $renewal_order    WooCommerce renewal order.
	 * @return void
	 */
	public function process_direct_debit_renewal( float $amount_to_charge, WC_Order $renewal_order ): void {
		$this->renewal_handler->process_direct_debit_renewal( $amount_to_charge, $renewal_order );
	}

	/**
	 * Dispatch a VRP scheduled renewal to the renewal handler.
	 *
	 * @since 1.0.0
	 *
	 * @param float    $amount_to_charge Amount to charge.
	 * @param WC_Order $renewal_order    WooCommerce renewal order.
	 * @return void
	 */
	public function process_vrp_renewal( float $amount_to_charge, WC_Order $renewal_order ): void {
		$this->renewal_handler->process_vrp_renewal( $amount_to_charge, $renewal_order );
	}

	// -------------------------------------------------------------------------
	// Lifecycle handlers
	// -------------------------------------------------------------------------

	/**
	 * Cancel the GoCardless mandate when a WooCommerce Subscription is cancelled.
	 *
	 * Attempts to cancel the GoCardless mandate so no further payments can be
	 * collected even if the cancellation webhook is delayed or missed.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Subscription $subscription Cancelled WooCommerce subscription.
	 * @return void
	 */
	public function handle_subscription_cancelled( WC_Subscription $subscription ): void {
		if ( ! $this->is_gocardless_subscription( $subscription ) ) {
			return;
		}

		$parent_order = $subscription->get_parent();

		if ( ! $parent_order instanceof WC_Order ) {
			return;
		}

		$mandate_id  = WC_GoCardless_Order_Helper::get_mandate_id( $parent_order );
		$payment_type = WC_GoCardless_Order_Helper::get_payment_type( $parent_order );

		if ( empty( $mandate_id ) ) {
			return;
		}

		try {
			if ( 'vrp' === $payment_type ) {
				$vrp_api = new WC_GoCardless_API_VRP( wc_gocardless_payments()->api );
				$vrp_api->cancel_consent(
					$mandate_id,
					sprintf( 'WooCommerce subscription #%d cancelled', $subscription->get_id() )
				);
			} else {
				$mandates_api = new WC_GoCardless_API_Mandates( wc_gocardless_payments()->api );
				$mandates_api->cancel(
					$mandate_id,
					sprintf( 'WooCommerce subscription #%d cancelled', $subscription->get_id() )
				);
			}

			wc_gocardless_payments()->logger->info(
				sprintf(
					'[Subscriptions] Mandate %s cancelled on subscription #%d cancellation.',
					$mandate_id,
					$subscription->get_id()
				)
			);

			$subscription->add_order_note(
				sprintf(
					/* translators: %s: Mandate ID */
					__( 'GoCardless mandate/consent %s cancelled on subscription cancellation.', 'wc-gocardless-payments' ),
					esc_html( $mandate_id )
				)
			);

		} catch ( WC_GoCardless_API_Exception $e ) {
			// Non-fatal — mandate may already be cancelled or not found.
			wc_gocardless_payments()->logger->warning(
				sprintf(
					'[Subscriptions] Could not cancel mandate %s for subscription #%d: %s',
					$mandate_id,
					$subscription->get_id(),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Update stored mandate meta when a subscription payment method changes.
	 *
	 * When the customer updates their payment method on a subscription
	 * (e.g. via My Account → Subscriptions → Change Payment), the new
	 * payment method's mandate ID must be propagated to the subscription.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Subscription $subscription  WooCommerce subscription.
	 * @param string          $new_gateway   New gateway ID.
	 * @param string          $old_gateway   Previous gateway ID.
	 * @return void
	 */
	public function handle_payment_method_updated(
		WC_Subscription $subscription,
		string $new_gateway,
		string $old_gateway
	): void {
		// Only act when switching to or between GoCardless gateways.
		$is_new_gc = str_starts_with( $new_gateway, 'gocardless_' );
		$is_old_gc = str_starts_with( $old_gateway, 'gocardless_' );

		if ( ! $is_new_gc ) {
			return;
		}

		$subscription->add_order_note(
			sprintf(
				/* translators: 1: Old gateway 2: New gateway */
				__( 'Payment method changed from %1$s to %2$s. A new mandate/consent will be collected on the next renewal checkout.', 'wc-gocardless-payments' ),
				esc_html( $old_gateway ),
				esc_html( $new_gateway )
			)
		);

		wc_gocardless_payments()->logger->info(
			sprintf(
				'[Subscriptions] Payment method updated for subscription #%d: %s → %s',
				$subscription->get_id(),
				$old_gateway,
				$new_gateway
			)
		);
	}

	/**
	 * Handle GoCardless mandate invalidation for active subscriptions.
	 *
	 * When the webhook processor fires `wc_gocardless_mandate_invalidated`,
	 * any active subscription linked to the affected order is placed on-hold.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $mandate_id GoCardless mandate ID.
	 * @param string   $action     Invalidation action (cancelled|expired|failed).
	 * @param WC_Order $order      Parent WooCommerce order.
	 * @return void
	 */
	public function handle_mandate_invalidated_for_subscriptions(
		string $mandate_id,
		string $action,
		WC_Order $order
	): void {
		if ( ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
			return;
		}

		$subscriptions = wcs_get_subscriptions_for_order( $order, array( 'order_type' => 'any' ) );

		foreach ( $subscriptions as $subscription ) {
			if ( ! $subscription->has_status( 'active' ) ) {
				continue;
			}

			$subscription->update_status(
				'on-hold',
				sprintf(
					/* translators: 1: Mandate ID 2: Action type */
					__( 'GoCardless mandate/consent %1$s %2$s. Subscription placed on-hold — new payment method required.', 'wc-gocardless-payments' ),
					esc_html( $mandate_id ),
					esc_html( $action )
				)
			);

			wc_gocardless_payments()->logger->warning(
				sprintf(
					'[Subscriptions] Subscription #%d placed on-hold: mandate %s %s.',
					$subscription->get_id(),
					$mandate_id,
					$action
				)
			);
		}
	}

	/**
	 * Gate subscription reactivation when the GoCardless mandate is inactive.
	 *
	 * Prevents a subscription from being reactivated via the admin when
	 * its associated mandate is cancelled or expired, avoiding failed
	 * renewal attempts.
	 *
	 * @since 1.0.0
	 *
	 * @param bool            $can_be_updated Whether the subscription can be updated.
	 * @param WC_Subscription $subscription   WooCommerce subscription.
	 * @return bool
	 */
	public function gate_subscription_reactivation( bool $can_be_updated, WC_Subscription $subscription ): bool {
		if ( ! $can_be_updated || ! $this->is_gocardless_subscription( $subscription ) ) {
			return $can_be_updated;
		}

		$parent_order = $subscription->get_parent();

		if ( ! $parent_order instanceof WC_Order ) {
			return $can_be_updated;
		}

		$mandate_id = WC_GoCardless_Order_Helper::get_mandate_id( $parent_order );

		if ( empty( $mandate_id ) ) {
			return $can_be_updated;
		}

		try {
			$mandates_api = new WC_GoCardless_API_Mandates( wc_gocardless_payments()->api );
			$mandate_resp = $mandates_api->get( $mandate_id );

			if ( ! $mandates_api->is_active( $mandate_resp ) ) {
				// Block reactivation — merchant needs to update payment method first.
				return false;
			}
		} catch ( WC_GoCardless_API_Exception $e ) {
			// On API error, allow reactivation — do not silently block.
			wc_gocardless_payments()->logger->warning(
				sprintf(
					'[Subscriptions] Could not verify mandate %s for reactivation gate: %s',
					$mandate_id,
					$e->getMessage()
				)
			);
		}

		return $can_be_updated;
	}

	// -------------------------------------------------------------------------
	// Meta propagation
	// -------------------------------------------------------------------------

	/**
	 * Copy GoCardless meta from the subscription to a newly-created renewal order.
	 *
	 * WooCommerce Subscriptions creates a fresh WC_Order for each renewal.
	 * GoCardless mandate/consent IDs must be copied so the renewal handler
	 * can locate them without traversing the full subscription hierarchy
	 * on every renewal.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $renewal_order Newly-created renewal order.
	 * @return void
	 */
	public function copy_meta_to_renewal_order( WC_Order $renewal_order ): void {
		if ( ! function_exists( 'wcs_get_subscriptions_for_renewal_order' ) ) {
			return;
		}

		$gateway_id = $renewal_order->get_payment_method();

		if ( ! str_starts_with( $gateway_id, 'gocardless_' ) ) {
			return;
		}

		$subscriptions = wcs_get_subscriptions_for_renewal_order( $renewal_order );

		foreach ( $subscriptions as $subscription ) {
			$parent_order = $subscription->get_parent();

			if ( ! $parent_order instanceof WC_Order ) {
				continue;
			}

			$this->propagate_meta( $parent_order, $renewal_order );
			break; // Only need the first subscription's parent.
		}
	}

	/**
	 * Copy GoCardless meta when WC Subscriptions creates a renewal order directly.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order        $renewal_order Newly-created renewal order.
	 * @param WC_Subscription $subscription  Parent subscription.
	 * @return void
	 */
	public function copy_meta_to_renewal_order_from_subscription(
		WC_Order $renewal_order,
		WC_Subscription $subscription
	): void {
		$gateway_id = $renewal_order->get_payment_method();

		if ( ! str_starts_with( $gateway_id, 'gocardless_' ) ) {
			return;
		}

		$parent_order = $subscription->get_parent();

		if ( $parent_order instanceof WC_Order ) {
			$this->propagate_meta( $parent_order, $renewal_order );
		}
	}

	/**
	 * Propagate relevant GoCardless meta keys from source to destination order.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $source      Source order (parent or subscription).
	 * @param WC_Order $destination Destination order (renewal).
	 * @return void
	 */
	private function propagate_meta( WC_Order $source, WC_Order $destination ): void {
		$keys_to_copy = array(
			'mandate_id',
			'customer_id',
			'payment_scheme',
			'payment_type',
			'vrp_max_amount',
		);

		$meta_to_set = array();

		foreach ( $keys_to_copy as $key ) {
			$value = WC_GoCardless_Order_Helper::get_meta( $source, $key );
			if ( ! empty( $value ) ) {
				$meta_to_set[ $key ] = $value;
			}
		}

		if ( ! empty( $meta_to_set ) ) {
			WC_GoCardless_Order_Helper::bulk_update_meta( $destination, $meta_to_set );

			wc_gocardless_payments()->logger->debug(
				sprintf(
					'[Subscriptions] Propagated meta (%s) from order #%d to renewal #%d.',
					implode( ', ', array_keys( $meta_to_set ) ),
					$source->get_id(),
					$destination->get_id()
				)
			);
		}
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Determine whether a subscription uses a GoCardless payment gateway.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Subscription $subscription WooCommerce subscription.
	 * @return bool True when a GoCardless gateway is set.
	 */
	private function is_gocardless_subscription( WC_Subscription $subscription ): bool {
		return str_starts_with( $subscription->get_payment_method(), 'gocardless_' );
	}
}
