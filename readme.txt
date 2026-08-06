=== WooCommerce GoCardless Payments ===
Contributors: mralaminahamed
Tags: woocommerce, gocardless, payment-gateway, direct-debit, subscriptions
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 7.4
WC requires at least: 8.0
WC tested up to: 9.9
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Take Direct Debit through GoCardless — BACS, SEPA and ACH — plus Instant Bank Pay and recurring payments.

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

== External Services ==

This plugin is a payment gateway for **GoCardless**, so it sends what GoCardless
needs to take a Direct Debit payment. Without those requests there is no payment.

**GoCardless** — `api.gocardless.com`. Requests are made when a customer chooses
this gateway at checkout and afterwards over the life of the mandate: the plugin
creates a customer, a bank account and a mandate, then creates payments against
it, and reads payment and subscription status back. What leaves your site is the
customer's name, email address, billing address, the bank details they entered,
and the order's amount, currency and reference. Every request carries the access
token you saved in the plugin settings.

The sandbox host `api-sandbox.gocardless.com` is used instead whenever the gateway
is in test mode.

Terms: https://gocardless.com/legal/merchant-agreement — Privacy:
https://gocardless.com/privacy

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
