# WooCommerce GoCardless Payments

A production-ready WooCommerce payment gateway integrating GoCardless for Direct Debit (ACH/BACS/SEPA), Instant Bank Pay, Variable Recurring Payments (VRP), and Payment Intentions.

---

## Features

- **Direct Debit** — ACH, BACS, and SEPA payment methods
- **Instant Bank Pay** — Real-time bank payments via Open Banking
- **Variable Recurring Payments (VRP)** — Open banking recurring payments
- **Full & Partial Refunds** — Through WooCommerce refund UI
- **Subscription Support** — WooCommerce Subscriptions integration
- **Webhook Handling** — HMAC-SHA256 signature verification
- **HPOS Compatible** — High-Performance Order Storage support

---

## Requirements

| Requirement   | Version   |
|---------------|-----------|
| PHP           | ≥ 7.4     |
| WordPress     | ≥ 6.2     |
| WooCommerce   | ≥ 8.0     |

---

## Installation

### 1. Upload the plugin

Upload the plugin folder to `/wp-content/plugins/` or install via WordPress admin.

### 2. Install Composer dependencies

```bash
cd wp-content/plugins/wc-gocardless-payments
composer install
```

### 3. Activate the plugin

Go to **Plugins → Installed Plugins** and activate **WooCommerce GoCardless Payments**.

### 4. Configure the gateway

1. Navigate to **WooCommerce → Settings → Payments**
2. Enable GoCardless payment gateways
3. Configure your GoCardless API credentials

---

## Plugin Structure

```
wc-gocardless-payments/
├── wc-gocardless-payments.php              # Main plugin file / bootstrap
├── includes/
│   ├── class-wc-gocardless-payments.php    # Main plugin class
│   ├── utilities/
│   │   ├── class-wc-gocardless-logger.php
│   │   ├── class-wc-gocardless-order-helper.php
│   │   └── class-wc-gocardless-idempotency.php
│   ├── api/
│   │   ├── class-wc-gocardless-api-client.php
│   │   ├── class-wc-gocardless-api-payments.php
│   │   ├── class-wc-gocardless-api-mandates.php
│   │   ├── class-wc-gocardless-api-customers.php
│   │   ├── class-wc-gocardless-api-billing-requests.php
│   │   └── class-wc-gocardless-api-vrp.php
│   ├── admin/
│   │   └── class-wc-gocardless-admin.php
│   ├── gateway/
│   │   ├── class-wc-gocardless-gateway.php
│   │   ├── class-wc-gocardless-gateway-direct-debit.php
│   │   ├── class-wc-gocardless-gateway-instant-bank.php
│   │   └── class-wc-gocardless-gateway-vrp.php
│   ├── frontend/
│   │   ├── class-wc-gocardless-checkout.php
│   │   └── class-wc-gocardless-redirect.php
│   ├── webhooks/
│   │   ├── class-wc-gocardless-webhook-handler.php
│   │   └── class-wc-gocardless-webhook-processor.php
│   ├── subscriptions/
│   │   ├── class-wc-gocardless-subscriptions.php
│   │   └── class-wc-gocardless-renewal-handler.php
│   └── payment-token/
│       └── class-wc-gocardless-payment-token-mandate.php
├── templates/
│   ├── admin/
│   └── checkout/
│       └── ibp-order-received.php
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   └── checkout.css
│   └── js/
│       ├── admin.js
│       └── checkout.js
└── languages/
    └── wc-gocardless-payments.pot
```

---

## REST API Reference

**Base URL:** `{site_url}/wp-json/wc-gocardless-payments/v1`

| Method | Endpoint      | Auth           | Description              |
|--------|---------------|----------------|--------------------------|
| POST   | `/webhook`    | Signature      | Webhook endpoint        |

The webhook URL is displayed on the settings page:
`https://yoursite.com/wp-json/wc-gocardless-payments/v1/webhook`

---

## Security

- Webhook signatures verified with HMAC-SHA256 using `hash_equals()`
- Nonces required for all admin AJAX/form submissions
- Capability checks (`manage_woocommerce`) for privileged operations
- Input sanitization: `sanitize_text_field()`, `absint()`, `wp_kses()`
- Output escaping: `esc_html__()`, `esc_attr__()`, `esc_url()`

---

## License

GPL-2.0+ — see LICENSE file.
