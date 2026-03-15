<?php
/**
 * Template: Subscription Renewal Email (HTML)
 *
 * @package WC_GoCardless_Payments
 * @since   1.0.0
 *
 * @var WC_Order  $order            WooCommerce order object.
 * @var string    $payment_id      GoCardless payment ID.
 * @var string    $subscription_id WooCommerce subscription ID.
 * @var string    $email_heading   Email heading string.
 * @var bool      $sent_to_admin   Whether email is sent to admin.
 * @var bool      $plain_text     Whether rendering plain text.
 * @var WC_Email $email          Email object.
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p>
<?php
$customer_name = esc_html( $order->get_billing_first_name() );
printf(
	esc_html__( 'Hello %s,', 'wc-gocardless-payments' ),
	$customer_name
);
?>
</p>

<p>
<?php
esc_html_e(
	'Your subscription payment has been successfully processed. Thank you for your continued subscription.',
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
				<?php esc_html_e( 'Subscription ID:', 'wc-gocardless-payments' ); ?>
			</th>
			<td style="text-align:right;padding:8px 0;font-size:0.9em;">
				<?php echo esc_html( $subscription_id ); ?>
			</td>
		</tr>
	</tbody>
</table>

<?php
do_action( 'woocommerce_email_footer', $email );
