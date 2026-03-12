# Payment Methods

## Direct Debit (ACH/BACS/SEPA)

Direct Debit allows customers to authorize automatic deductions from their bank account.

### Supported Currencies

| Method | Country | Currency |
|--------|---------|----------|
| ACH    | USA     | USD      |
| BACS   | UK      | GBP      |
| SEPA   | EU      | EUR      |

### How It Works

1. Customer selects Direct Debit at checkout
2. Customer enters bank account details
3. Plugin creates a **mandate** (authorization) with GoCardless
4. Payment is collected against the mandate
5. Funds are transferred to your GoCardless account

### Mandate Flow

```
Customer → Enter Bank Details → Create Mandate → Confirm Payment → Complete
```

### Order Status Mapping

| GoCardless Status | WooCommerce Status |
|-------------------|-------------------|
| submitted         | processing        |
| confirmed         | processing        |
| failed            | failed            |
| cancelled         | cancelled         |

---

## Instant Bank Pay

Real-time bank payments using Open Banking.

### Features

- Instant payment confirmation
- No card required
- Secure bank-level authentication
- Available in UK and EU

### How It Works

1. Customer selects Instant Bank Pay at checkout
2. Customer is redirected to their bank
3. Customer authenticates with their bank
4. Customer returns to your site
5. Payment is confirmed in real-time

### Order Status Mapping

| GoCardless Status | WooCommerce Status |
|-------------------|-------------------|
| confirmed         | processing        |
| failed            | failed            |
| pending           | on-hold           |

---

## Variable Recurring Payments (VRP)

Open Banking recurring payments for subscriptions and variable amounts.

### Use Cases

- Subscription boxes with variable quantities
- Pay-as-you-go services
- Flexible installment plans

### Requirements

- Customer must have a UK or EU bank account
- Bank must support VRP (Variable Recurring Payments)

### How It Works

1. Customer selects VRP at checkout
2. Customer authorizes recurring payments via their bank
3. Each subscription renewal triggers a payment
4. Customer can revoke authorization at any time via their bank

### Subscription Integration

When used with WooCommerce Subscriptions:
- Initial payment at checkout
- Automatic renewal payments
- Failed payment handling
- Subscription cancellation sync

---

## Refunds

All payment methods support full and partial refunds via WooCommerce refund UI.

### Processing Refunds

1. Go to **WooCommerce → Orders**
2. Select the order
3. Click **Refund** 
4. Enter refund amount and reason
5. Click **Process Refund**

### Refund Status

| GoCardless Status | Description |
|-------------------|-------------|
| submitted         | Refund initiated |
| settled           | Refund completed |
| failed            | Refund failed    |

---

## Webhook Events

The plugin processes the following GoCardless webhook events:

- `payment_created`
- `payment_confirmed`
- `payment_failed`
- `payment_cancelled`
- `mandate_created`
- `mandate_active`
- `mandate_failed`
- `mandate_cancelled`
- `billing_request_created`
- `billing_request_completed`
- `billing_request_failed`
- `billing_request_cancelled`
