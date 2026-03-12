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

The plugin uses idempotency keys to prevent duplicate payments. This is handled automatically.

### Logging

Enable debug mode to log API requests and responses. Logs appear in **WooCommerce → Status → Logs**.

### HPOS Support

The plugin is compatible with WooCommerce High-Performance Order Storage (HPOS).
