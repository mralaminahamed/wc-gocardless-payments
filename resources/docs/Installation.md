# Installation

## Requirements

- PHP 8.0 or higher
- WordPress 6.2 or higher
- WooCommerce 8.0 or higher
- GoCardless account with API credentials

## Step 1: Upload the Plugin

### Option A: WordPress Admin

1. Go to **Plugins → Add New**
2. Click **Upload Plugin**
3. Select the plugin zip file
4. Click **Install Now**

### Option B: Manual Upload

1. Upload the plugin folder to `/wp-content/plugins/`
2. Ensure the folder is named `wc-gocardless-payments`

### Option C: Composer

```bash
composer require mralaminahamed/wc-gocardless-payments
```

## Step 2: Install Dependencies

If using manual upload:

```bash
cd wp-content/plugins/wc-gocardless-payments
composer install
```

## Step 3: Activate the Plugin

1. Go to **Plugins → Installed Plugins**
2. Find **WooCommerce GoCardless Payments**
3. Click **Activate**

## Step 4: Configure API Credentials

1. Navigate to **WooCommerce → Settings → Payments**
2. Enable the desired payment methods
3. Click **Manage** to configure each gateway
4. Enter your GoCardless API credentials:
   - **Access Token**: Your GoCardless access token
   - **Webhook Secret**: For signature verification

## Step 5: Verify Webhook Setup

1. Go to **WooCommerce → Settings → Payments**
2. Note the webhook URL displayed
3. Add this URL in your GoCardless dashboard under **Developers → Webhooks**

## Troubleshooting

### Plugin Not Appearing

- Ensure WooCommerce is installed and active
- Check PHP version meets requirements (8.0+)
- Verify all files were uploaded correctly

### API Connection Fails

- Double-check your access token
- Ensure your GoCardless account has API access
- Check server can make outbound HTTPS requests

See [Troubleshooting](Troubleshooting.md) for more help.
