<?php
/**
 * Webhook Processor — WC_GoCardless_Webhook_Processor
 *
 * Processes individual GoCardless webhook events and maps them to
 * WooCommerce order status transitions and metadata updates.
 *
 * Supported resource types:
 *   - payments
 *   - mandates
 *   - billing_requests
 *   - refunds
 *   - subscriptions (GoCardless subscription objects)
 *
 * @package WC_GoCardless_Payments\Webhooks
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_GoCardless_Webhook_Processor
 *
 * @since 1.0.0
 */
class WC_GoCardless_Webhook_Processor {

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
	 * Route a single webhook event to the appropriate handler method.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $event GoCardless event object.
	 * @return void
	 */
	public function process( array $event ): void {
		$event_id      = $event['id'] ?? '';
		$resource_type = $event['resource_type'] ?? '';
		$action        = $event['action'] ?? '';
		$links         = $event['links'] ?? array();

		$this->logger->info(
			sprintf( '[Webhook] Processing event %s: %s.%s', $event_id, $resource_type, $action )
		);

		/**
		 * Fires before a GoCardless webhook event is processed.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $event         Full event object.
		 * @param string               $resource_type Event resource type.
		 * @param string               $action        Event action.
		 */
		do_action( 'wc_gocardless_webhook_event', $event, $resource_type, $action );

		switch ( $resource_type ) {
			case 'payments':
				$this->handle_payment_event( $action, $links, $event );
				break;

			case 'mandates':
				$this->handle_mandate_event( $action, $links, $event );
				break;

			case 'billing_requests':
				$this->handle_billing_request_event( $action, $links, $event );
				break;

			case 'refunds':
				$this->handle_refund_event( $action, $links, $event );
				break;

			default:
				$this->logger->debug(
					sprintf( '[Webhook] Unhandled resource type: %s', $resource_type )
				);
		}

		/**
		 * Fires after a GoCardless webhook event has been processed.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $event Full event object.
		 */
		do_action( 'wc_gocardless_webhook_event_processed', $event );
	}

	/**
	 * Handle payment resource events.
	 *
	 * Handles both Direct Debit and Instant Bank Pay payment events.
	 * IBP payments are identified by the `payment_type` order meta set
	 * during the Billing Request creation flow.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $action Action identifier.
	 * @param array<string, mixed> $links  Event links (resource IDs).
	 * @param array<string, mixed> $event  Full event object.
	 * @return void
	 */
	private function handle_payment_event( string $action, array $links, array $event ): void {
		$payment_id = $links['payment'] ?? '';

		if ( empty( $payment_id ) ) {
			$this->logger->warning( '[Webhook] Payment event missing payment ID in links.' );
			return;
		}

		$order = $this->find_order_by_meta( 'payment_id', $payment_id );

		if ( ! $order ) {
			$this->logger->warning(
				sprintf( '[Webhook] No order found for payment ID: %s', $payment_id )
			);
			return;
		}

		$is_ibp = 'instant_bank_pay' === WC_GoCardless_Order_Helper::get_payment_type( $order );

		switch ( $action ) {
			case 'created':
				$order->add_order_note(
					sprintf(
						/* translators: %s: GoCardless payment ID */
						__( 'GoCardless payment created (Payment ID: %s). Awaiting bank confirmation.', 'wc-gocardless-payments' ),
						esc_html( $payment_id )
					)
				);
				break;

			case 'submitted':
				$order->add_order_note(
					sprintf(
						/* translators: %s: GoCardless payment ID */
						__( 'GoCardless payment submitted to the bank (Payment ID: %s).', 'wc-gocardless-payments' ),
						esc_html( $payment_id )
					)
				);
				break;

			case 'confirmed':
				if ( $is_ibp ) {
					// IBP: 'confirmed' means funds are secured — complete the order.
					if ( ! $order->is_paid() ) {
						$order->payment_complete( $payment_id );
						wc_reduce_stock_levels( $order->get_id() );

						$order->add_order_note(
							sprintf(
								/* translators: %s: Payment ID */
								__( 'Instant Bank Pay confirmed via webhook (Payment ID: %s).', 'wc-gocardless-payments' ),
								esc_html( $payment_id )
							)
						);
					}
				} elseif ( 'vrp' === WC_GoCardless_Order_Helper::get_payment_type( $order ) ) {
					// VRP: open-banking confirmation — complete the order.
					if ( ! $order->is_paid() ) {
						$order->payment_complete( $payment_id );
						wc_reduce_stock_levels( $order->get_id() );

						$order->add_order_note(
							sprintf(
								/* translators: %s: Payment ID */
								__( 'VRP payment confirmed via webhook (Payment ID: %s).', 'wc-gocardless-payments' ),
								esc_html( $payment_id )
							)
						);
					}
				} else {
					// Direct Debit: 'confirmed' means bank has accepted the collection.
					$order->update_status(
						'processing',
						sprintf(
							/* translators: %s: GoCardless payment ID */
							__( 'GoCardless Direct Debit payment confirmed (Payment ID: %s).', 'wc-gocardless-payments' ),
							esc_html( $payment_id )
						)
					);
					wc_reduce_stock_levels( $order->get_id() );
				}
				break;

			case 'paid_out':
				// Funds paid out to the merchant account.
				if ( $is_ibp ) {
					// IBP paid_out: definitive settlement — complete order if not already.
					if ( ! $order->is_paid() ) {
						$order->payment_complete( $payment_id );
						wc_reduce_stock_levels( $order->get_id() );
					}
					$order->add_order_note(
						sprintf(
							/* translators: %s: Payment ID */
							__( 'Instant Bank Pay settled to merchant account (Payment ID: %s).', 'wc-gocardless-payments' ),
							esc_html( $payment_id )
						)
					);
				} else {
					$order->add_order_note(
						sprintf(
							/* translators: %s: GoCardless payment ID */
							__( 'GoCardless payment paid out to merchant account (Payment ID: %s).', 'wc-gocardless-payments' ),
							esc_html( $payment_id )
						)
					);
					if ( $order->has_status( 'processing' ) ) {
						$order->update_status( 'completed' );
					}
				}
				break;

			case 'failed':
				$failure_reason = $event['details']['description']
					?? __( 'Unknown reason', 'wc-gocardless-payments' );
				$failure_cause  = $event['details']['cause'] ?? '';

				$order->update_status(
					'failed',
					sprintf(
						/* translators: 1: Payment ID 2: Failure reason 3: Cause code */
						__( 'GoCardless payment failed (Payment ID: %1$s). Reason: %2$s. Cause: %3$s', 'wc-gocardless-payments' ),
						esc_html( $payment_id ),
						esc_html( $failure_reason ),
						esc_html( $failure_cause )
					)
				);

				/**
				 * Fires when a GoCardless payment fails.
				 *
				 * @since 1.0.0
				 *
				 * @param WC_Order             $order    WooCommerce order.
				 * @param string               $payment_id GoCardless payment ID.
				 * @param array<string, mixed> $event    Full GoCardless event object.
				 */
				do_action( 'wc_gocardless_payment_failed', $order, $payment_id, $event );
				break;

			case 'cancelled':
				$order->update_status(
					'cancelled',
					sprintf(
						/* translators: %s: GoCardless payment ID */
						__( 'GoCardless payment cancelled (Payment ID: %s).', 'wc-gocardless-payments' ),
						esc_html( $payment_id )
					)
				);
				break;

			case 'charged_back':
				$order->update_status(
					'on-hold',
					sprintf(
						/* translators: %s: GoCardless payment ID */
						__( 'GoCardless payment charged back (Payment ID: %s). Please review.', 'wc-gocardless-payments' ),
						esc_html( $payment_id )
					)
				);

				/**
				 * Fires when a GoCardless payment is charged back.
				 *
				 * @since 1.0.0
				 *
				 * @param WC_Order $order      WooCommerce order.
				 * @param string   $payment_id GoCardless payment ID.
				 */
				do_action( 'wc_gocardless_payment_charged_back', $order, $payment_id );
				break;

			case 'late_failure_settled':
				// A previously failed payment has been settled — rare edge case.
				$order->add_order_note(
					sprintf(
						/* translators: %s: Payment ID */
						__( 'GoCardless late failure settled (Payment ID: %s).', 'wc-gocardless-payments' ),
						esc_html( $payment_id )
					)
				);
				break;

			case 'chargeback_settled':
				$order->add_order_note(
					sprintf(
						/* translators: %s: Payment ID */
						__( 'GoCardless chargeback settled (Payment ID: %s).', 'wc-gocardless-payments' ),
						esc_html( $payment_id )
					)
				);
				break;

			default:
				$this->logger->debug(
					sprintf( '[Webhook] Unhandled payment action: %s for payment %s', $action, $payment_id )
				);
		}
	}

	/**
	 * Handle mandate resource events.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $action Action identifier.
	 * @param array<string, mixed> $links  Event links.
	 * @param array<string, mixed> $event  Full event object.
	 * @return void
	 */
	private function handle_mandate_event( string $action, array $links, array $event ): void {
		$mandate_id = $links['mandate'] ?? '';

		if ( empty( $mandate_id ) ) {
			return;
		}

		$order = $this->find_order_by_meta( 'mandate_id', $mandate_id );

		if ( ! $order ) {
			$this->logger->debug(
				sprintf( '[Webhook] No order found for mandate ID: %s (may be customer-level)', $mandate_id )
			);
			return;
		}

		switch ( $action ) {
			case 'created':
				$order->add_order_note(
					sprintf(
						/* translators: %s: GoCardless mandate ID */
						__( 'GoCardless mandate created (Mandate ID: %s).', 'wc-gocardless-payments' ),
						esc_html( $mandate_id )
					)
				);
				break;

			case 'active':
				$order->add_order_note(
					sprintf(
						/* translators: %s: GoCardless mandate ID */
						__( 'GoCardless mandate is now active (Mandate ID: %s).', 'wc-gocardless-payments' ),
						esc_html( $mandate_id )
					)
				);
				break;

			case 'cancelled':
			case 'expired':
			case 'failed':
				// Update any active subscription to require a new payment method.
				$order->add_order_note(
					sprintf(
						/* translators: 1: Mandate ID 2: Action */
						__( 'GoCardless mandate %2$s (Mandate ID: %1$s). Subscription may require new payment method.', 'wc-gocardless-payments' ),
						esc_html( $mandate_id ),
						esc_html( $action )
					)
				);

				/**
				 * Fires when a GoCardless mandate is cancelled, expired, or failed.
				 *
				 * @since 1.0.0
				 *
				 * @param string   $mandate_id GoCardless mandate ID.
				 * @param string   $action     Event action.
				 * @param WC_Order $order      Associated WooCommerce order.
				 */
				do_action( 'wc_gocardless_mandate_invalidated', $mandate_id, $action, $order );
				break;
		}
	}

	/**
	 * Handle billing_request resource events.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $action Action identifier.
	 * @param array<string, mixed> $links  Event links.
	 * @param array<string, mixed> $event  Full event object.
	 * @return void
	 */
	private function handle_billing_request_event( string $action, array $links, array $event ): void {
		$billing_request_id = $links['billing_request'] ?? '';

		if ( empty( $billing_request_id ) ) {
			return;
		}

		$order = $this->find_order_by_meta( 'billing_request_id', $billing_request_id );

		if ( ! $order ) {
			$this->logger->debug(
				sprintf( '[Webhook] No order for billing request: %s', $billing_request_id )
			);
			return;
		}

		switch ( $action ) {
			case 'fulfilled':
				$order->add_order_note(
					sprintf(
						/* translators: %s: Billing request ID */
						__( 'GoCardless Billing Request fulfilled (ID: %s).', 'wc-gocardless-payments' ),
						esc_html( $billing_request_id )
					)
				);
				break;

			case 'cancelled':
				$order->update_status(
					'cancelled',
					sprintf(
						/* translators: %s: Billing request ID */
						__( 'GoCardless Billing Request cancelled by customer (ID: %s).', 'wc-gocardless-payments' ),
						esc_html( $billing_request_id )
					)
				);
				break;
		}
	}

	/**
	 * Handle refund resource events.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $action Action identifier.
	 * @param array<string, mixed> $links  Event links.
	 * @param array<string, mixed> $event  Full event object.
	 * @return void
	 */
	private function handle_refund_event( string $action, array $links, array $event ): void {
		$payment_id = $links['payment'] ?? '';
		$refund_id  = $links['refund'] ?? '';

		if ( empty( $payment_id ) ) {
			return;
		}

		$order = $this->find_order_by_meta( 'payment_id', $payment_id );

		if ( ! $order ) {
			return;
		}

		if ( 'paid' === $action ) {
			$order->add_order_note(
				sprintf(
					/* translators: %s: GoCardless refund ID */
					__( 'GoCardless refund processed successfully (Refund ID: %s).', 'wc-gocardless-payments' ),
					esc_html( $refund_id )
				)
			);
		}
	}

	/**
	 * Locate a WooCommerce order by a GoCardless-namespaced meta value.
	 *
	 * Uses WooCommerce's order query API for HPOS compatibility.
	 *
	 * @since 1.0.0
	 *
	 * @param string $meta_key   Meta key suffix (without prefix).
	 * @param string $meta_value Meta value to match.
	 * @return WC_Order|null Matching order or null if not found.
	 */
	private function find_order_by_meta( string $meta_key, string $meta_value ): ?WC_Order {
		$full_key = WC_GoCardless_Order_Helper::META_PREFIX . $meta_key;

		$orders = wc_get_orders(
			array(
				'limit'      => 1,
				'meta_key'   => $full_key,   // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => sanitize_text_field( $meta_value ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		return ! empty( $orders ) ? $orders[0] : null;
	}
}
