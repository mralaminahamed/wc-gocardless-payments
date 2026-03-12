<?php
/**
 * Template: IBP Pending Settlement Notice
 *
 * Displayed on the WooCommerce order-received (thank you) page when an
 * Instant Bank Pay order is on-hold pending settlement confirmation from
 * GoCardless via webhook.
 *
 * Hooked via: woocommerce_thankyou_gocardless_instant_bank
 *
 * @package WC_GoCardless_Payments
 * @since   1.0.0
 *
 * @var WC_Order $order Current WooCommerce order object.
 */

defined( 'ABSPATH' ) || exit;

$payment_id = WC_GoCardless_Order_Helper::get_payment_id( $order );
$ibp_status = WC_GoCardless_Order_Helper::get_ibp_status( $order );
$is_confirmed = in_array( $ibp_status, array( 'confirmed', 'paid_out' ), true )
	|| $order->is_paid()
	|| $order->has_status( array( 'processing', 'completed' ) );
?>

<?php if ( $is_confirmed ) : ?>
	<div class="wc-gocardless-ibp-confirmed">
		<strong><?php esc_html_e( 'Payment Confirmed', 'wc-gocardless-payments' ); ?></strong>
		<p>
			<?php
			printf(
				/* translators: %s: payment ID */
				esc_html__( 'Your Instant Bank Pay payment has been confirmed. Reference: %s', 'wc-gocardless-payments' ),
				'<code>' . esc_html( $payment_id ) . '</code>'
			);
			?>
		</p>
	</div>

<?php elseif ( $order->has_status( 'on-hold' ) ) : ?>
	<div class="wc-gocardless-ibp-pending">
		<strong><?php esc_html_e( 'Payment Authorised — Awaiting Settlement', 'wc-gocardless-payments' ); ?></strong>
		<p>
			<?php esc_html_e( 'Your bank has authorised the payment and it is being processed by GoCardless. You will receive an email confirmation once settlement is complete — typically within a few minutes.', 'wc-gocardless-payments' ); ?>
		</p>
		<?php if ( $payment_id ) : ?>
			<p>
				<small>
					<?php
					printf(
						/* translators: %s: Payment reference */
						esc_html__( 'Payment reference: %s', 'wc-gocardless-payments' ),
						'<code>' . esc_html( $payment_id ) . '</code>'
					);
					?>
				</small>
			</p>
		<?php endif; ?>
	</div>

<?php else : ?>
	<div class="wc-gocardless-pending-notice">
		<p>
			<?php esc_html_e( 'Your Instant Bank Pay payment is being processed. You will receive a confirmation email once payment is confirmed.', 'wc-gocardless-payments' ); ?>
		</p>
	</div>
<?php endif; ?>
