<?php
/**
 * Template: Payment Success Email (HTML)
 *
 * Sent to the customer when a GoCardless payment is successfully confirmed.
 *
 * This template can be overridden by copying it to:
 *   {your-theme}/woocommerce/emails/payment-success.php
 *
 * @package WC_GoCardless_Payments
 * @since   1.0.0
 *
 * @var WC_Order  $order         WooCommerce order object.
 * @var string    $payment_id    GoCardless payment ID.
 * @var string    $email_heading Email heading string.
 * @var bool      $sent_to_admin Whether email is sent to admin.
 * @var bool      $plain_text    Whether rendering plain text.
 * @var WC_Email  $email         Email object.
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p>
<?php
$customer_name = esc_html( $order->get_billing_first_name() );
printf(
	/* translators: %s: Customer first name */
	esc_html__( 'Hello %s,', 'wc-gocardless-payments' ),
	$customer_name
);
?>
</p>

<p>
<?php
esc_html_e(
	'Thank you for your payment. Your GoCardless payment has been successfully processed and confirmed. Your order is now being processed.',
	'wc-gocardless-payments'
);
?>
</p>

<?php
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );
?>

<table cellspacing="0" cellpadding="6" style="width:100%;margin-top:20px;border-top:1px solid #e5e5e5;">
	<tbody>
		<tr>
			<th style="text-align:left;padding:8px 0;font-size:0.9em;color:#555;">
				<?php esc_html_e( 'Payment ID:', 'wc-gocardless-payments' ); ?>
			</th>
			<td style="text-align:right;padding:8px 0;font-size:0.9em;font-family:monospace;">
				<?php echo esc_html( $payment_id ); ?>
			</td>
		</tr>
		<tr>
			<th style="text-align:left;padding:8px 0;font-size:0.9em;color:#555;">
				<?php esc_html_e( 'Payment Method:', 'wc-gocardless-payments' ); ?>
			</th>
			<td style="text-align:right;padding:8px 0;font-size:0.9em;">
				<?php esc_html_e( 'GoCardless Direct Debit', 'wc-gocardless-payments' ); ?>
			</td>
		</tr>
	</tbody>
</table>

<p style="margin-top:20px;font-size:0.9em;color:#555;">
<?php
printf(
	/* translators: %s: Site name */
	esc_html__( 'Thank you for shopping with %s.', 'wc-gocardless-payments' ),
	esc_html( get_bloginfo( 'name' ) )
);
?>
</p>

<?php
do_action( 'woocommerce_email_footer', $email );
