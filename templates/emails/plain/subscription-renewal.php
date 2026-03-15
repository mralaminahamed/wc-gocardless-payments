<?php
/**
 * Template: Subscription Renewal Email (Plain Text)
 *
 * @package WC_GoCardless_Payments
 * @since   1.0.0
 *
 * @see templates/emails/subscription-renewal.php for variable documentation.
 */

defined( 'ABSPATH' ) || exit;

echo '= ' . esc_html( $email_heading ) . " =\n\n";

printf(
	esc_html__( 'Hello %s,', 'wc-gocardless-payments' ) . "\n\n",
	esc_html( $order->get_billing_first_name() )
);

echo esc_html__(
	'Your subscription payment has been successfully processed. Thank you for your continued subscription.',
	'wc-gocardless-payments'
) . "\n\n";

echo "----------------------------------------\n";

do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

echo "\n----------------------------------------\n";
echo esc_html__( 'Payment ID:', 'wc-gocardless-payments' ) . ' ' . esc_html( $payment_id ) . "\n";
echo esc_html__( 'Subscription ID:', 'wc-gocardless-payments' ) . ' ' . esc_html( $subscription_id ) . "\n";
echo "----------------------------------------\n\n";

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) . "\n";
