# API Reference

This document covers the GoCardless API client and endpoint classes used by the plugin.

## API Client

### WC_GoCardless_API_Client

The main HTTP client for communicating with the GoCardless API.

**File:** `includes/api/class-wc-gocardless-api-client.php`

**Features:**
- HTTP GET, POST, PUT requests
- Automatic idempotency key generation
- Token decryption
- Sandbox/Production mode switching
- Structured logging
- User-Agent header with version info

**Constants:**

| Constant | Description |
|----------|-------------|
| `LIVE_API_URL` | Production API: `https://api.gocardless.com` |
| `SANDBOX_API_URL` | Sandbox API: `https://api-sandbox.gocardless.com` |
| `API_VERSION` | API version: `2015-07-06` |
| `REQUEST_TIMEOUT` | HTTP timeout: 30 seconds |

**Methods:**

| Method | Description |
|--------|-------------|
| `get( $endpoint, $params )` | Execute GET request |
| `post( $endpoint, $body, $idempotency_key )` | Execute POST request |
| `put( $endpoint, $body )` | Execute PUT request |
| `is_sandbox()` | Check if in sandbox mode |
| `refresh_credentials()` | Reload credentials from settings |
| `get_api_url()` | Get current API base URL |

## API Endpoints

### WC_GoCardless_API_Payments

Handles payment creation and management.

**File:** `includes/api/class-wc-gocardless-api-payments.php`

**Methods:**

| Method | Description |
|--------|-------------|
| `create( $params )` | Create a new payment |
| `get( $payment_id )` | Get payment details |
| `list( $params )` | List payments |
| `cancel( $payment_id )` | Cancel a payment |
| `refund( $payment_id, $params )` | Create a refund |

### WC_GoCardless_API_Billing_Requests

Handles Instant Bank Pay billing requests.

**File:** `includes/api/class-wc-gocardless-api-billing-requests.php`

**Methods:**

| Method | Description |
|--------|-------------|
| `create( $params )` | Create billing request |
| `get( $billing_request_id )` | Get billing request |
| `collect_customer_details( $billing_request_id, $params )` | Collect customer bank info |
| `collect_bank_account( $billing_request_id, $params )` | Collect bank account details |
| `confirm( $billing_request_id )` | Confirm billing request |
| `cancel( $billing_request_id )` | Cancel billing request |

### WC_GoCardless_API_VRP

Handles Variable Recurring Payments.

**File:** `includes/api/class-wc-gocardless-api-vrp.php`

**Methods:**

| Method | Description |
|--------|-------------|
| `create_billing_request( $params )` | Create VRP billing request |
| `create_vrp( $params )` | Create VRP payment |
| `get( $vrp_id )` | Get VRP details |
| `list( $params )` | List VRPs |

### WC_GoCardless_API_Customers

Manages GoCardless customers.

**File:** `includes/api/class-wc-gocardless-api-customers.php`

**Methods:**

| Method | Description |
|--------|-------------|
| `create( $params )` | Create customer |
| `get( $customer_id )` | Get customer details |
| `update( $customer_id, $params )` | Update customer |
| `list( $params )` | List customers |

### WC_GoCardless_API_Mandates

Handles Direct Debit mandates.

**File:** `includes/api/class-wc-gocardless-api-mandates.php`

**Methods:**

| Method | Description |
|--------|-------------|
| `create( $params )` | Create mandate |
| `get( $mandate_id )` | Get mandate details |
| `cancel( $mandate_id )` | Cancel mandate |
| `reinstate( $mandate_id )` | Reinstate cancelled mandate |
| `list( $params )` | List mandates |

### WC_GoCardless_API_Exception

Exception class for API errors.

**File:** `includes/api/class-wc-gocardless-api-exception.php`

**Properties:**
- `$message` - Error message
- `$type` - Error type (e.g., `invalid_api_key`, `validation_failed`)
- `$code` - HTTP status code
- `$errors` - Array of detailed errors

**Example Usage:**

```php
try {
    $client = new WC_GoCardless_API_Client();
    $payment = $client->post( '/payments', array(
        'amount'   => 1000,
        'currency' => 'GBP',
        'links'    => array(
            'mandate' => 'MD123'
        )
    ));
} catch ( WC_GoCardless_API_Exception $e ) {
    // Handle error
    $message = $e->getMessage();
    $type    = $e->getType();
}
```

## Utilities

### WC_GoCardless_Logger

Structured logging utility.

**File:** `includes/utilities/class-wc-gocardless-logger.php`

**Methods:**

| Method | Description |
|--------|-------------|
| `debug( $message, $context )` | Log debug message |
| `info( $message, $context )` | Log info message |
| `warning( $message, $context )` | Log warning message |
| `error( $message, $context )` | Log error message |

### WC_GoCardless_Order_Helper

Order management helpers.

**File:** `includes/utilities/class-wc-gocardless-order-helper.php`

### WC_GoCardless_Idempotency

Idempotency key management to prevent duplicate payments.

**File:** `includes/utilities/class-wc-gocardless-idempotency.php`

## Webhooks

### WC_GoCardless_Webhook_Handler

REST API endpoint for webhook events.

**File:** `includes/webhooks/class-wc-gocardless-webhook-handler.php`

**Endpoint:** `POST /wp-json/wc-gocardless-payments/v1/webhook`

### WC_GoCardless_Webhook_Processor

Processes webhook events.

**File:** `includes/webhooks/class-wc-gocardless-webhook-processor.php`

**Supported Events:**
- `payment_created`
- `payment_confirmed`
- `payment_failed`
- `payment_cancelled`
- `mandate_created`
- `mandate_activated`
- `mandate_failed`
- `mandate_cancelled`
- `billing_request_created`
- `billing_request_completed`
- `billing_request_failed`
- `refund_created`
- `refund_paid`

## Subscriptions

### WC_GoCardless_Subscriptions

WooCommerce Subscriptions integration.

**File:** `includes/subscriptions/class-wc-gocardless-subscriptions.php`

### WC_GoCardless_Renewal_Handler

Handles subscription renewal payments.

**File:** `includes/subscriptions/class-wc-gocardless-renewal-handler.php`

## Emails

All email classes extend `WC_Email` and are registered via the `woocommerce_email_classes` filter.

### Customer Emails

#### WC_GoCardless_Email_Mandate_Confirmed

Sent when a Direct Debit mandate or VRP consent is authorized.

**File:** `includes/emails/class-wc-gocardless-email-mandate-confirmed.php`

**Email ID:** `wc_gocardless_mandate_confirmed`
**Trigger:** `wc_gocardless_billing_request_fulfilled`

**Properties:**
- `$mandate_id` - GoCardless mandate ID
- `$payment_type` - Payment method type ('direct_debit' or 'vrp')

#### WC_GoCardless_Email_Payment_Success

Sent when a payment is successfully confirmed.

**File:** `includes/emails/class-wc-gocardless-email-payment-success.php`

**Email ID:** `wc_gocardless_payment_success`
**Trigger:** `wc_gocardless_payment_confirmed`

**Properties:**
- `$payment_id` - GoCardless payment ID

#### WC_GoCardless_Email_Payment_Failed

Sent when a payment fails.

**File:** `includes/emails/class-wc-gocardless-email-payment-failed.php`

**Email ID:** `wc_gocardless_payment_failed`
**Trigger:** `wc_gocardless_payment_failed`

**Properties:**
- `$payment_id` - GoCardless payment ID
- `$failure_reason` - Human-readable failure reason

#### WC_GoCardless_Email_Refund_Processed

Sent when a refund is processed.

**File:** `includes/emails/class-wc-gocardless-email-refund-processed.php`

**Email ID:** `wc_gocardless_refund_processed`
**Trigger:** `wc_gocardless_refund_processed`

**Properties:**
- `$refund_id` - GoCardless refund ID
- `$refund_amount` - Refund amount (formatted)

#### WC_GoCardless_Email_Subscription_Renewal

Sent when a subscription renewal payment is processed.

**File:** `includes/emails/class-wc-gocardless-email-subscription-renewal.php`

**Email ID:** `wc_gocardless_subscription_renewal`
**Trigger:** `wc_gocardless_subscription_renewal_processed`

**Properties:**
- `$payment_id` - GoCardless payment ID
- `$subscription_id` - WooCommerce subscription ID

### Admin Emails

#### WC_GoCardless_Email_Admin_Payment_Failed

Sent to admin when a payment fails.

**File:** `includes/emails/class-wc-gocardless-email-admin-payment-failed.php`

**Email ID:** `wc_gocardless_admin_payment_failed`
**Trigger:** `wc_gocardless_payment_failed`

**Properties:**
- `$payment_id` - GoCardless payment ID
- `$failure_reason` - Human-readable failure reason

#### WC_GoCardless_Email_Admin_Webhook_Error

Sent to admin when webhook processing fails.

**File:** `includes/emails/class-wc-gocardless-email-admin-webhook-error.php`

**Email ID:** `wc_gocardless_admin_webhook_error`
**Trigger:** `wc_gocardless_webhook_error`

**Properties:**
- `$event_type` - GoCardless event type
- `$error_message` - Error description
- `$event_data` - Sanitized event payload
