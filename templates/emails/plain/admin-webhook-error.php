<?php
/**
 * Template: Admin Webhook Error Email (Plain Text)
 *
 * @package WC_GoCardless_Payments
 * @since   1.0.0
 *
 * @see templates/emails/admin-webhook-error.php for variable documentation.
 */

defined( 'ABSPATH' ) || exit;

echo '= ' . esc_html( $email_heading ) . " =\n\n";

echo esc_html__(
	'A GoCardless webhook failed to process. This may require attention.',
	'wc-gocardless-payments'
) . "\n\n";

echo "----------------------------------------\n";
echo esc_html__( 'Event Type:', 'wc-gocardless-payments' ) . ' ' . esc_html( $event_type ) . "\n";
echo esc_html__( 'Error:', 'wc-gocardless-payments' ) . ' ' . esc_html( $error_message ) . "\n";
echo "----------------------------------------\n\n";

if ( 'yes' === $email->get_option( 'include_event_data', 'yes' ) && ! empty( $event_data ) ) {
	echo esc_html__( 'Event Data:', 'wc-gocardless-payments' ) . "\n";
	print_r( $event_data ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
	echo "\n";
}

printf(
	esc_html__( 'Please check the WooCommerce logs at %s for more details.', 'wc-gocardless-payments' ) . "\n\n",
	admin_url( 'admin.php?page=wc-status&tab=logs' )
);

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) . "\n";
