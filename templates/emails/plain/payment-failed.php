<?php
/**
 * Template: Payment Failed Email (Plain Text)
 *
 * @package WC_GoCardless_Payments
 * @since   1.0.0
 *
 * @see templates/emails/payment-failed.php for variable documentation.
 */

defined( 'ABSPATH' ) || exit;

echo '= ' . esc_html( $email_heading ) . " =\n\n";

printf(
	esc_html__( 'Hello %s,', 'wc-gocardless-payments' ) . "\n\n",
	esc_html( $order->get_billing_first_name() )
);

echo esc_html__(
	'We regret to inform you that your recent payment via GoCardless could not be processed.',
	'wc-gocardless-payments'
) . "\n\n";

if ( ! empty( $failure_reason ) ) {
	echo '----------------------------------------' . "\n";
	echo esc_html__( 'Reason:', 'wc-gocardless-payments' ) . ' ' . esc_html( $failure_reason ) . "\n";
	echo '----------------------------------------' . "\n\n";
}

echo "----------------------------------------\n";

do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

echo "\n----------------------------------------\n";
echo esc_html__( 'Payment ID:', 'wc-gocardless-payments' ) . ' ' . esc_html( $payment_id ) . "\n";
echo "----------------------------------------\n\n";

printf(
	esc_html__( 'Please try again by placing a new order, or contact %s if you need assistance.', 'wc-gocardless-payments' ) . "\n\n",
	esc_html( get_bloginfo( 'name' ) )
);

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) . "\n";
