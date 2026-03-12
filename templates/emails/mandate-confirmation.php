<?php
/**
 * Template: Mandate Confirmation Email (HTML)
 *
 * Sent to the customer when a GoCardless Direct Debit mandate or VRP consent
 * is successfully authorised via the Billing Request flow.
 *
 * This template can be overridden by copying it to:
 *   {your-theme}/woocommerce/emails/mandate-confirmation.php
 *
 * @package WC_GoCardless_Payments
 * @since   1.0.0
 *
 * @var WC_Order  $order         WooCommerce order object.
 * @var string    $mandate_id    GoCardless mandate or VRP consent ID.
 * @var string    $payment_type  'direct_debit' or 'vrp'.
 * @var string    $email_heading Email heading string.
 * @var bool      $sent_to_admin Whether email is sent to admin.
 * @var bool      $plain_text    Whether rendering plain text.
 * @var WC_Email  $email         Email object.
 */

defined( 'ABSPATH' ) || exit;

// Load WooCommerce email header.
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
if ( 'vrp' === $payment_type ) {
	esc_html_e(
		'Your Variable Recurring Payment (VRP) consent has been successfully authorised with your bank via GoCardless. Your subscription payments will be collected automatically on each renewal date — no further action is needed.',
		'wc-gocardless-payments'
	);
} else {
	esc_html_e(
		'Your Direct Debit mandate has been successfully set up with your bank via GoCardless. Future payments for your order will be collected automatically — no further action is needed from you.',
		'wc-gocardless-payments'
	);
}
?>
</p>

<?php
// Show the order summary table.
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );
?>

<?php if ( ! empty( $mandate_id ) && 'yes' === $email->get_option( 'include_mandate_reference', 'yes' ) ) : ?>
<table cellspacing="0" cellpadding="6" style="width:100%;margin-top:20px;border-top:1px solid #e5e5e5;">
	<tbody>
		<tr>
			<th style="text-align:left;padding:8px 0;font-size:0.9em;color:#555;">
				<?php
				if ( 'vrp' === $payment_type ) {
					esc_html_e( 'VRP Consent Reference:', 'wc-gocardless-payments' );
				} else {
					esc_html_e( 'Mandate Reference:', 'wc-gocardless-payments' );
				}
				?>
			</th>
			<td style="text-align:right;padding:8px 0;font-size:0.9em;font-family:monospace;">
				<?php echo esc_html( $mandate_id ); ?>
			</td>
		</tr>
		<tr>
			<th style="text-align:left;padding:8px 0;font-size:0.9em;color:#555;">
				<?php esc_html_e( 'Order Number:', 'wc-gocardless-payments' ); ?>
			</th>
			<td style="text-align:right;padding:8px 0;font-size:0.9em;">
				<?php echo esc_html( $order->get_order_number() ); ?>
			</td>
		</tr>
		<tr>
			<th style="text-align:left;padding:8px 0;font-size:0.9em;color:#555;">
				<?php esc_html_e( 'Authorised On:', 'wc-gocardless-payments' ); ?>
			</th>
			<td style="text-align:right;padding:8px 0;font-size:0.9em;">
				<?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?>
			</td>
		</tr>
	</tbody>
</table>
<?php endif; ?>

<p style="margin-top:20px;font-size:0.9em;color:#555;">
<?php
if ( 'vrp' === $payment_type ) {
	printf(
		/* translators: %s: Site name */
		esc_html__( 'To cancel or modify your VRP consent, please contact %s or manage your consent directly via your banking app.', 'wc-gocardless-payments' ),
		esc_html( get_bloginfo( 'name' ) )
	);
} else {
	printf(
		/* translators: %s: Site name */
		esc_html__( 'To cancel your Direct Debit mandate, please contact %s. You can also cancel directly with your bank.', 'wc-gocardless-payments' ),
		esc_html( get_bloginfo( 'name' ) )
	);
}
?>
</p>

<?php
// Load WooCommerce email footer.
do_action( 'woocommerce_email_footer', $email );
