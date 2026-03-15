<?php
/**
 * Template: Payment Success Email (Plain Text)
 *
 * @package WC_GoCardless_Payments
 * @since   1.0.0
 *
 * @see templates/emails/payment-success.php for variable documentation.
 */

defined( 'ABSPATH' ) || exit;

echo '= ' . esc_html( $email_heading ) . " =\n\n";

printf(
	esc_html__( 'Hello %s,', 'wc-gocardless-payments' ) . "\n\n",
	esc_html( $order->get_billing_first_name() )
);

echo esc_html__(
	'Thank you for your payment. Your GoCardless payment has been successfully processed and confirmed. Your order is now being processed.',
	'wc-gocardless-payments'
) . "\n\n";

echo "----------------------------------------\n";

do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

echo "\n----------------------------------------\n";
echo esc_html__( 'Payment ID:', 'wc-gocardless-payments' ) . ' ' . esc_html( $payment_id ) . "\n";
echo esc_html__( 'Payment Method:', 'wc-gocardless-payments' ) . ' GoCardless Direct Debit' . "\n";
echo "----------------------------------------\n\n";

printf(
	esc_html__( 'Thank you for shopping with %s.', 'wc-gocardless-payments' ) . "\n\n",
	esc_html( get_bloginfo( 'name' ) )
);

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) . "\n";
