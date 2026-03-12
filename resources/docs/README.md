# WooCommerce GoCardless Payments

WooCommerce payment gateway integrating GoCardless for Direct Debit (ACH/BACS/SEPA), Instant Bank Pay, Variable Recurring Payments (VRP), and Payment Intentions.

## Features

- **Direct Debit** — ACH, BACS, and SEPA payment methods
- **Instant Bank Pay** — Real-time bank payments via Open Banking
- **Variable Recurring Payments (VRP)** — Open banking recurring payments
- **Full & Partial Refunds** — Through WooCommerce refund UI
- **Subscription Support** — WooCommerce Subscriptions integration
- **Webhook Handling** — HMAC-SHA256 signature verification
- **HPOS Compatible** — High-Performance Order Storage support
- **WooCommerce Blocks** — Cart & Checkout block support
- **Email Notifications** — Mandate confirmation emails

## Requirements

| Requirement   | Version   |
|---------------|-----------|
| PHP           | ≥ 7.4     |
| WordPress     | ≥ 6.2     |
| WooCommerce   | ≥ 8.0     |

## Quick Start

1. Install and activate the plugin
2. Configure your GoCardless API credentials
3. Enable payment methods in WooCommerce → Settings → Payments

## Documentation

- [Installation](Installation.md)
- [Configuration](Configuration.md)
- [Payment Methods](Payment-Methods.md)
- [Webhooks](Webhooks.md)
- [Troubleshooting](Troubleshooting.md)

## License

GPL-2.0+ — see LICENSE file.
