<?php
/**
 * Template: Refund Processed Email (Plain Text)
 *
 * @package WC_GoCardless_Payments
 * @since   1.0.0
 *
 * @see templates/emails/refund-processed.php for variable documentation.
 */

defined( 'ABSPATH' ) || exit;

echo '= ' . esc_html( $email_heading ) . " =\n\n";

printf(
	esc_html__( 'Hello %s,', 'wc-gocardless-payments' ) . "\n\n",
	esc_html( $order->get_billing_first_name() )
);

printf(
	esc_html__( 'Your refund of %s has been successfully processed.', 'wc-gocardless-payments' ) . "\n\n",
	esc_html( $refund_amount )
);

echo "----------------------------------------\n";

do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

echo "\n----------------------------------------\n";
echo esc_html__( 'Refund ID:', 'wc-gocardless-payments' ) . ' ' . esc_html( $refund_id ) . "\n";
echo esc_html__( 'Refund Amount:', 'wc-gocardless-payments' ) . ' ' . esc_html( $refund_amount ) . "\n";
echo "----------------------------------------\n\n";

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) . "\n";
