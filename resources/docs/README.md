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
| PHP           | ≥ 8.0     |
| WordPress     | ≥ 6.2     |
| WooCommerce   | ≥ 8.0     |

## Architecture

The plugin is organized into several components:

```
includes/
├── api/                    # GoCardless API client and endpoints
│   ├── Client              # Main API client with HTTP handling
│   ├── Payments            # Payment creation and management
│   ├── Billing_Requests    # Instant Bank Pay billing requests
│   ├── VRP                 # Variable Recurring Payments
│   ├── Customers           # Customer management
│   ├── Mandates            # Direct Debit mandate handling
│   └── Exception           # API exception handling
├── gateway/                # WooCommerce payment gateways
│   ├── Base                # Base gateway class
│   ├── Direct_Debit        # ACH/BACS/SEPA payments
│   ├── Instant_Bank        # Instant Bank Pay
│   └── VRP                 # Variable Recurring Payments
├── blocks/                 # WooCommerce Blocks integration
│   ├── Integration         # Base blocks integration
│   ├── Direct_Debit        # Direct Debit block
│   ├── Instant_Bank        # Instant Bank Pay block
│   └── VRP                 # VRP block
├── frontend/               # Checkout and redirect handling
├── webhooks/               # Webhook endpoint and processor
├── subscriptions/          # WooCommerce Subscriptions integration
├── utilities/              # Helper classes
├── admin/                  # Admin settings
├── emails/                 # Email notifications
└── payment-token/          # Payment token handling
```

### Core Components

| Component | Description |
|-----------|-------------|
| **API Client** | Handles all HTTP communication with GoCardless API |
| **Payment Gateways** | Three gateway types for different payment flows |
| **Webhook Handler** | Processes real-time payment events |
| **Subscriptions** | Automatic renewal payment processing |
| **Blocks Integration** | Support for WooCommerce Cart/Checkout blocks |

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
- [API Reference](API-Reference.md)

## License

GPL-2.0+ — see LICENSE file.
