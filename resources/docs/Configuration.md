# Configuration

## Gateway Settings

Each payment method has its own settings page accessible via **WooCommerce → Settings → Payments**.

## Common Settings

### API Configuration

| Setting | Description |
|---------|-------------|
| **Enable/Disable** | Toggle the payment method on/off |
| **Title** | Display name shown to customers |
| **Description** | Instructions shown at checkout |
| **Access Token** | GoCardless API access token |
| **Webhook Secret** | Secret for HMAC signature verification |
| **Environment** | Sandbox or Production |

### Payment Settings

| Setting | Description |
|---------|-------------|
| **Payment Action** | Authorize only or Authorize + Capture |
| **Debug Mode** | Enable logging for troubleshooting |

## Direct Debit Settings

- **Supported Currencies**: GBP, EUR, USD
- **Payment Methods**: ACH (US), BACS (UK), SEPA (EU)
- **Requires Customer Authorization**: Yes (mandate)

## Instant Bank Pay Settings

- **Supported Countries**: UK, EU
- **Real-time Confirmation**: Yes
- **Redirect Flow**: Customer redirected to bank

## Variable Recurring Payments (VRP)

- **Use Cases**: Subscriptions, variable amount payments
- **Smart Authentication**: VRP with SCA
- **Maximum Amount**: Configurable per transaction

## Environment Variables

You can set credentials via environment variables:

```php
// In wp-config.php
define( 'WC_GOCARDLESS_ACCESS_TOKEN', 'your_access_token' );
define( 'WC_GOCARDLESS_WEBHOOK_SECRET', 'your_webhook_secret' );
define( 'WC_GOCARDLESS_ENVIRONMENT', 'sandbox' );
```

## Webhook Configuration

The webhook URL is:
`https://yoursite.com/wp-json/wc-gocardless-payments/v1/webhook`

Add this URL in GoCardless Dashboard:
**Developers → Webhooks → Add webhook URL**

## Advanced Settings

### Idempotency

The plugin uses idempotency keys to prevent duplicate payments. This is handled automatically via the `WC_GoCardless_Idempotency` utility class.

### Logging

Enable debug mode to log API requests and responses. Logs appear in **WooCommerce → Status → Logs**. The `WC_GoCardless_Logger` class provides structured logging.

### HPOS Support

The plugin is compatible with WooCommerce High-Performance Order Storage (HPOS).

### Order Helper

The `WC_GoCardless_Order_Helper` utility provides helpers for:
- Calculating order amounts
- Managing order notes
- Handling refund logic

## WooCommerce Blocks Support

The plugin supports the new WooCommerce Cart and Checkout blocks:

- **Direct Debit** — Available in block checkout
- **Instant Bank Pay** — Available in block checkout
- **VRP** — Available in block checkout

### Enabling Blocks

1. Go to **WooCommerce → Settings → Payments**
2. Ensure payment methods are enabled
3. Blocks will automatically appear when using the block-based checkout

## Email Notifications

The plugin includes email notifications for both customers and admins:

### Customer Emails

| Email | Description | Trigger |
|-------|-------------|---------|
| **Mandate Confirmed** | Sent when a Direct Debit mandate or VRP consent is authorized | `wc_gocardless_billing_request_fulfilled` |
| **Payment Success** | Sent when a payment is successfully confirmed | `wc_gocardless_payment_confirmed` |
| **Payment Failed** | Sent when a payment fails | `wc_gocardless_payment_failed` |
| **Refund Processed** | Sent when a refund is processed | `wc_gocardless_refund_processed` |
| **Subscription Renewal** | Sent when a subscription renewal payment is processed | `wc_gocardless_subscription_renewal_processed` |

### Admin Emails

| Email | Description | Trigger |
|-------|-------------|---------|
| **Payment Failed** | Alert when a payment fails | `wc_gocardless_payment_failed` |
| **Webhook Error** | Alert when webhook processing fails | `wc_gocardless_webhook_error` |

### Configuring Emails

1. Go to **WooCommerce → Settings → Emails**
2. Find the desired GoCardless email
3. Enable/disable and customize as needed

### Email Settings

Each email supports the following settings:
- **Enable/Disable** — Toggle email on/off
- **Subject** — Email subject line
- **Heading** — Email heading
- **Email type** — Choose HTML, Plain text, or Multipart

## Subscriptions Integration

The plugin integrates with WooCommerce Subscriptions for recurring payments:

- **WC_GoCardless_Subscriptions** — Main subscriptions handler
- **WC_GoCardless_Renewal_Handler** — Processes subscription renewal payments

### Subscription Settings

1. Ensure WooCommerce Subscriptions is installed
2. Create a subscription product
3. Select GoCardless as the payment method
4. Customer authorization carries over to renewals

## Frontend Components

### Checkout Handler

The `WC_GoCardless_Checkout` class handles:
- Payment form rendering
- JavaScript initialization
- AJAX payment processing

### Redirect Handler

The `WC_GoCardless_Redirect` class handles:
- Processing return from GoCardless
- Order completion and verification
- Error handling

## Payment Tokens

The plugin stores payment method authorization as tokens:

- **WC_GoCardless_Payment_Token_Mandate** — Stores mandate reference for Direct Debit

Tokens allow returning customers to use saved payment methods.
