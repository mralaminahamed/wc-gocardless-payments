<?php
/**
 * Template: Admin Payment Failed Email (Plain Text)
 *
 * @package WC_GoCardless_Payments
 * @since   1.0.0
 *
 * @see templates/emails/admin-payment-failed.php for variable documentation.
 */

defined( 'ABSPATH' ) || exit;

echo '= ' . esc_html( $email_heading ) . " =\n\n";

echo esc_html__(
	'A GoCardless payment has failed and requires attention.',
	'wc-gocardless-payments'
) . "\n\n";

if ( ! empty( $failure_reason ) ) {
	echo '----------------------------------------' . "\n";
	echo esc_html__( 'Failure Reason:', 'wc-gocardless-payments' ) . ' ' . esc_html( $failure_reason ) . "\n";
	echo '----------------------------------------' . "\n\n";
}

echo "----------------------------------------\n";

do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

echo "\n----------------------------------------\n";
echo esc_html__( 'Payment ID:', 'wc-gocardless-payments' ) . ' ' . esc_html( $payment_id ) . "\n";

if ( 'yes' === $email->get_option( 'include_customer_email', 'yes' ) ) {
	echo esc_html__( 'Customer Email:', 'wc-gocardless-payments' ) . ' ' . esc_html( $order->get_billing_email() ) . "\n";
}

echo "----------------------------------------\n\n";

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) . "\n";
