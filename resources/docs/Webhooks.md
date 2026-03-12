# Webhooks

Webhooks allow GoCardless to notify your site about payment events in real-time.

## Webhook Endpoint

**URL:** `https://yoursite.com/wp-json/wc-gocardless-payments/v1/webhook`

**Method:** POST

## Configuration

### 1. Get Your Webhook URL

Navigate to **WooCommerce → Settings → Payments** - the webhook URL is displayed on each gateway settings page.

### 2. Add to GoCardless Dashboard

1. Log in to GoCardless Dashboard
2. Go to **Developers → Webhooks**
3. Click **Add webhook**
4. Enter your webhook URL
5. Select events to subscribe to
6. Save

### 3. Configure Webhook Secret

In WooCommerce gateway settings, enter your GoCardless webhook secret for signature verification.

## Security

### Signature Verification

The plugin verifies webhook signatures using HMAC-SHA256:

```php
// Internal verification logic
$signature = hash_hmac( 'sha256', $body, $secret );
$is_valid  = hash_equals( $signature, $request_signature );
```

### Best Practices

- Always verify webhook signatures
- Never process webhooks without validation
- Log all webhook events for debugging

## Supported Events

### Payment Events

| Event | Description |
|-------|-------------|
| `payment_created` | Payment created |
| `payment_confirmed` | Payment confirmed by bank |
| `payment_failed` | Payment failed |
| `payment_cancelled` | Payment cancelled |
| `payment_submitted` | Payment submitted for collection |

### Mandate Events

| Event | Description |
|-------|-------------|
| `mandate_created` | Mandate created |
| `mandate_activated` | Mandate activated |
| `mandate_failed` | Mandate failed |
| `mandate_cancelled` | Mandate cancelled |

### Billing Request Events

| Event | Description |
|-------|-------------|
| `billing_request_created` | Billing request created |
| `billing_request_completed` | Billing request completed |
| `billing_request_failed` | Billing request failed |
| `billing_request_cancelled` | Billing request cancelled |

## Troubleshooting

### Webhooks Not Received

1. Verify webhook URL is correct in GoCardless dashboard
2. Check server can receive POST requests
3. Enable debug mode to log webhook attempts
4. Check WordPress permalinks are saved

### Webhook Validation Fails

1. Verify webhook secret matches in both systems
2. Check for extra whitespace in request body
3. Ensure server time is synchronized

### Duplicate Events

The plugin uses idempotency keys to prevent duplicate processing. Each webhook event includes a unique ID that is tracked.

## Testing Webhooks

### Using GoCardless Dashboard

1. Go to **Developers → Webhooks**
2. Select your webhook
3. Click **Send test webhook**
4. Choose an event type
5. View response

### Manual Testing

```bash
# Test webhook endpoint
curl -X POST https://yoursite.com/wp-json/wc-gocardless-payments/v1/webhook \
  -H "Content-Type: application/json" \
  -d '{"events": []}'
```

## Logs

Webhook events are logged in **WooCommerce → Status → Logs**.

Enable debug mode in gateway settings to see detailed webhook processing logs.
