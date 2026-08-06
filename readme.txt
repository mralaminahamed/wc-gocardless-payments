=== WooCommerce GoCardless Payments ===
Contributors: mralaminahamed
Tags: woocommerce, gocardless, payment gateway, direct debit, instant bank pay, subscriptions
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 7.4
WC requires at least: 8.0
WC tested up to: 9.9
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A production-ready WooCommerce payment gateway integrating GoCardless for Direct Debit (ACH/BACS/SEPA), Instant Bank Pay, Variable Recurring Payments (VRP), and Payment Intentions.

== Description ==

WooCommerce GoCardless Payments integrates GoCardless into WooCommerce, providing secure Direct Debit payments, Instant Bank Pay, and Variable Recurring Payments (VRP) through the GoCardless API.

**Payment Methods:**

* **Direct Debit** — ACH, BACS, and SEPA bank debit payments
* **Instant Bank Pay** — Real-time bank payments via Open Banking
* **Variable Recurring Payments (VRP)** — Open banking recurring payments

**Features:**

* Direct Debit payment processing (ACH/BACS/SEPA)
* Instant Bank Pay for real-time payments
* Variable Recurring Payments support
* Full and partial refunds via WooCommerce refund UI
* Subscription support (requires WooCommerce Subscriptions)
* Webhook handling with HMAC signature verification
* HPOS (High-Performance Order Storage) compatible

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Install Composer dependencies: `composer install`
3. Activate the plugin through the Plugins menu.
4. Navigate to WooCommerce > Settings > Payments.
5. Enable and configure the GoCardless payment gateways.

== Frequently Asked Questions ==

= Where do I get my GoCardless API credentials? =

From your GoCardless Dashboard under Developers > API credentials.

= What is the webhook URL? =

Your webhook URL is displayed on the settings page. Format:
`https://yoursite.com/wp-json/wc-gocardless-payments/v1/webhook`

= Does this support WooCommerce Subscriptions? =

Yes, with WooCommerce Subscriptions plugin installed, recurring billing is handled via GoCardless Subscriptions API.

== Changelog ==

= 1.0.0 =
* Initial release.
* Direct Debit support (ACH/BACS/SEPA).
* Instant Bank Pay support.
* Variable Recurring Payments (VRP) support.
* Full and partial refunds.
* WooCommerce Subscriptions integration.
* Webhook handling with signature verification.
* HPOS compatible.
