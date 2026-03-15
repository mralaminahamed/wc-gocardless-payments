<?php
/**
 * Template: Admin Webhook Error Email (HTML)
 *
 * @package WC_GoCardless_Payments
 * @since   1.0.0
 *
 * @var string    $event_type    GoCardless event type.
 * @var string    $error_message Error message.
 * @var array     $event_data    Event payload.
 * @var string    $email_heading Email heading string.
 * @var bool      $sent_to_admin Whether email is sent to admin.
 * @var bool      $plain_text    Whether rendering plain text.
 * @var WC_Email $email         Email object.
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p>
<?php
esc_html_e(
	'A GoCardless webhook failed to process. This may require attention.',
	'wc-gocardless-payments'
);
?>
</p>

<table cellspacing="0" cellpadding="6" style="width:100%;margin-top:20px;border-top:1px solid #e5e5e5;">
	<tbody>
		<tr>
			<th style="text-align:left;padding:8px 0;font-size:0.9em;color:#555;">
				<?php esc_html_e( 'Event Type:', 'wc-gocardless-payments' ); ?>
			</th>
			<td style="text-align:right;padding:8px 0;font-size:0.9em;font-family:monospace;">
				<?php echo esc_html( $event_type ); ?>
			</td>
		</tr>
		<tr>
			<th style="text-align:left;padding:8px 0;font-size:0.9em;color:#555;">
				<?php esc_html_e( 'Error:', 'wc-gocardless-payments' ); ?>
			</th>
			<td style="text-align:right;padding:8px 0;font-size:0.9em;">
				<?php echo esc_html( $error_message ); ?>
			</td>
		</tr>
	</tbody>
</table>

<?php if ( 'yes' === $email->get_option( 'include_event_data', 'yes' ) && ! empty( $event_data ) ) : ?>
<h3 style="margin-top:20px;"><?php esc_html_e( 'Event Data:', 'wc-gocardless-payments' ); ?></h3>
<pre style="background:#f5f5f5;padding:12px;overflow-x:auto;font-size:0.85em;"><?php echo esc_html( print_r( $event_data, true ) ); ?></pre>
<?php endif; ?>

<p style="margin-top:20px;font-size:0.9em;color:#555;">
<?php
printf(
	esc_html__( 'Please check the WooCommerce logs at %s for more details.', 'wc-gocardless-payments' ),
	esc_html( admin_url( 'admin.php?page=wc-status&tab=logs' ) )
);
?>
</p>

<?php
do_action( 'woocommerce_email_footer', $email );
