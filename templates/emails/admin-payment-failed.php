<?php
/**
 * Template: Admin Payment Failed Email (HTML)
 *
 * @package WC_GoCardless_Payments
 * @since   1.0.0
 *
 * @var WC_Order  $order           WooCommerce order object.
 * @var string    $payment_id      GoCardless payment ID.
 * @var string    $failure_reason  Human-readable failure reason.
 * @var string    $email_heading  Email heading string.
 * @var bool      $sent_to_admin  Whether email is sent to admin.
 * @var bool      $plain_text    Whether rendering plain text.
 * @var WC_Email $email          Email object.
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p>
<?php
esc_html_e(
	'A GoCardless payment has failed and requires attention.',
	'wc-gocardless-payments'
);
?>
</p>

<?php if ( ! empty( $failure_reason ) ) : ?>
<p style="background:#f8d7da;padding:12px;border-left:4px solid #dc3545;margin:15px 0;">
	<strong><?php esc_html_e( 'Failure Reason:', 'wc-gocardless-payments' ); ?></strong>
	<?php echo esc_html( $failure_reason ); ?>
</p>
<?php endif; ?>

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
		<?php if ( 'yes' === $email->get_option( 'include_customer_email', 'yes' ) ) : ?>
		<tr>
			<th style="text-align:left;padding:8px 0;font-size:0.9em;color:#555;">
				<?php esc_html_e( 'Customer Email:', 'wc-gocardless-payments' ); ?>
			</th>
			<td style="text-align:right;padding:8px 0;font-size:0.9em;">
				<?php echo esc_html( $order->get_billing_email() ); ?>
			</td>
		</tr>
		<?php endif; ?>
	</tbody>
</table>

<?php
do_action( 'woocommerce_email_footer', $email );
