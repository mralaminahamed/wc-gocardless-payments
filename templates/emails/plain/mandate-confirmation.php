<?php
/**
 * Template: Mandate Confirmation Email (Plain Text)
 *
 * Plain-text version of the mandate confirmation email.
 *
 * This template can be overridden by copying it to:
 *   {your-theme}/woocommerce/emails/plain/mandate-confirmation.php
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

echo '= ' . esc_html( $email_heading ) . " =\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

printf(
	/* translators: %s: Customer first name */
	esc_html__( 'Hello %s,', 'wc-gocardless-payments' ) . "\n\n",
	esc_html( $order->get_billing_first_name() )
);

if ( 'vrp' === $payment_type ) {
	echo esc_html__(
		'Your Variable Recurring Payment (VRP) consent has been successfully authorised with your bank via GoCardless. Your subscription payments will be collected automatically on each renewal date — no further action is needed.',
		'wc-gocardless-payments'
	) . "\n\n";
} else {
	echo esc_html__(
		'Your Direct Debit mandate has been successfully set up with your bank via GoCardless. Future payments for your order will be collected automatically — no further action is needed from you.',
		'wc-gocardless-payments'
	) . "\n\n";
}

echo "----------------------------------------\n";

do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

if ( ! empty( $mandate_id ) && 'yes' === $email->get_option( 'include_mandate_reference', 'yes' ) ) {
	echo "\n----------------------------------------\n";

	if ( 'vrp' === $payment_type ) {
		echo esc_html__( 'VRP Consent Reference:', 'wc-gocardless-payments' ) . ' ' . esc_html( $mandate_id ) . "\n";
	} else {
		echo esc_html__( 'Mandate Reference:', 'wc-gocardless-payments' ) . ' ' . esc_html( $mandate_id ) . "\n";
	}

	echo esc_html__( 'Order Number:', 'wc-gocardless-payments' ) . ' ' . esc_html( $order->get_order_number() ) . "\n";
	echo esc_html__( 'Authorised On:', 'wc-gocardless-payments' ) . ' ' . esc_html( wc_format_datetime( $order->get_date_created() ) ) . "\n";
	echo "----------------------------------------\n\n";
}

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

echo "\n\n";

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
