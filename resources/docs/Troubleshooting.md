# Troubleshooting

## Common Issues

### Plugin Activation Fails

**Symptom:** Plugin cannot be activated

**Solutions:**
- Verify PHP version is 8.0 or higher
- Verify WordPress version is 6.2 or higher
- Verify WooCommerce is installed and active
- Check for JavaScript errors in browser console

### API Connection Errors

**Symptom:** "Invalid credentials" or "Connection failed" errors

**Solutions:**
1. Verify access token is correct
2. Ensure token has required permissions
3. Check environment (sandbox/production) matches credentials
4. Verify server can make HTTPS requests to GoCardless API

```bash
# Test API connectivity
curl -I https://api.gocardless.com/
```

### Webhooks Not Working

**Symptom:** Orders not updating automatically

**Solutions:**
1. Verify webhook URL in GoCardless dashboard
2. Check webhook secret matches in settings
3. Enable debug mode and check logs
4. Verify permalinks are saved
5. Check server firewall allows POST requests

### Payment Not Processing

**Symptom:** Customer redirected but payment not completed

**Solutions:**
1. Check payment method is enabled
2. Verify currency is supported
3. Check order total is within limits
4. Enable debug mode for detailed logs

### Refund Not Working

**Symptom:** Refund fails or doesn't appear in GoCardless

**Solutions:**
1. Verify API credentials have refund permissions
2. Check original payment status (must be successful)
3. Verify refund amount doesn't exceed original payment
4. Check logs for specific error messages

### Subscription Renewals Failing

**Symptom:** Subscription renewals not processing

**Solutions:**
1. Verify WooCommerce Subscriptions is installed
2. Check customer payment mandate is active
3. Verify webhook events for `payment_failed`
4. Enable automatic payment retry in GoCart

## Debug Mode

### Enabling Debug

1. Go to **WooCommerce → Settings → Payments**
2. Click **Manage** on the gateway
3. Enable **Debug Mode**
4. Click **Save Changes**

### Viewing Logs

1. Go to **WooCommerce → Status → Logs**
2. Select the log file (contains "gocardless")
3. Review entries for errors

### Log Rotation

Logs are automatically rotated. To configure:

```php
// In wp-config.php
define( 'WC_LOG_HANDLER', 'WC_Log_Handler_File' );
define( 'WC_LOG_LEVEL', 'debug' ); // or 'info', 'warning', 'error'
```

## Testing in Sandbox

### Switch to Sandbox Mode

1. Go to **WooCommerce → Settings → Payments**
2. Click **Manage** on the gateway
3. Set **Environment** to **Sandbox**
4. Enter sandbox API credentials
5. Save changes

### Sandbox Credentials

Get sandbox credentials from GoCardless Dashboard:
**Developers → API keys → Sandbox**

## Performance Issues

### Slow Checkout

**Solutions:**
- Enable caching on your site
- Use a faster hosting provider
- Minimize plugin conflicts
- Check for database query issues

### Timeout Errors

**Solutions:**
- Increase PHP memory limit
- Increase server timeout settings
- Use asynchronous payment processing where possible

## Getting Help

### Enable Support Information

When requesting support, gather:

1. **System Status**: WooCommerce → Status
2. **Debug Logs**: WooCommerce → Status → Logs
3. **Plugin Version**: Plugins list
4. **PHP Version**: `php -v`
5. **Error Screenshots**: Any error messages

### Support Channels

- GitHub Issues: Report bugs with reproduction steps
- WordPress Forums: Community support
- GoCardless Support: For API-specific questions

## Known Limitations

- Some banks may not support all payment methods
- VRP not available for all banks
- SEPA payments may take 1-2 business days
- Instant Bank Pay availability varies by bank
