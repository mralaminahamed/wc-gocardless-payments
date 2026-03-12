# AGENTS.md - WooCommerce GoCardless Payments

Agent-specific documentation for the WooCommerce GoCardless Payments WordPress plugin.

## Overview

This is a WooCommerce payment gateway plugin that integrates GoCardless for Direct Debit (ACH/BACS/SEPA), Instant Bank Pay, Variable Recurring Payments (VRP), and Payment Intentions.

- **PHP Version**: 7.4+
- **WooCommerce**: 8.0+
- **WordPress**: 6.2+

---

## 1. Build / Lint / Test Commands

### PHP Code Sniffer (PHPCS)
```bash
# Run WordPress Coding Standards on entire plugin
./vendor/bin/phpcs --standard=WordPress --runtime-set testVersion 7.4- includes/ wc-gocardless-payments.php

# Run with custom ruleset
./vendor/bin/phpcs --standard=phpcs.xml.dist includes/

# Auto-fix fixable issues
./vendor/bin/phpcbf includes/
```

### PHPStan (Static Analysis)
```bash
# Run PHPStan at level 4
./vendor/bin/phpstan analyse

# Or if phpstan is not installed via vendor
./vendor/bin/phpstan analyse --configuration=phpstan.neon
```

### PHPUnit (Testing)
```bash
# Run all tests
./vendor/bin/phpunit

# Run a single test file
./vendor/bin/phpunit tests/php/src/Api/API_Client_Test.php

# Run a specific test method
./vendor/bin/phpunit --filter test_get_request
```

### JavaScript Linting
```bash
# If npm is available and configured
npm run lint
npm run lint:fix
```

### Internationalization
```bash
# Generate .pot file for translations
composer makepot
```

### Release Build
```bash
# Create release zip (requires composer, rsync, zip)
composer release
```

---

## 2. Code Style Guidelines

### PHP

#### General
- Always use `declare( strict_types=1 );` at the top of PHP files
- Use PHP 7.4+ syntax (typed properties, null coalescing, arrow functions where appropriate)
- Follow WordPress Coding Standards (WPCS)

#### Namespaces & Classes
- Namespace: `WC_GoCardless`
- Sub-namespaces: `API`, `Admin`, `Gateway`, `Webhooks`, `Subscriptions`, `Utilities`
- Class files: `class-wc-gocardless-*.php` format
- Use final classes where inheritance is not needed

```php
namespace WC_GoCardless\API;

final class API_Client {
    // ...
}
```

#### Naming Conventions
- Classes: `PascalCase` (e.g., `API_Client`, `Gateway`)
- Methods: `snake_case` (e.g., `process_payment`)
- Properties: `snake_case` (e.g., `$api_client`)
- Constants: `UPPER_SNAKE_CASE`
- Hooks: lowercase with underscores (e.g., `woocommerce_payment_gateways`)

#### PHPDoc
- Document all public methods with `@param` and `@return` types
- Use concise descriptions (1-2 sentences max)

```php
/**
 * Execute a GET request against the GoCardless API.
 *
 * @param  string               $endpoint API endpoint path.
 * @param  array<string, mixed> $params   Query parameters.
 * @return array<string, mixed> Decoded response body.
 * @throws API_Exception On HTTP or API error.
 */
public function get( string $endpoint, array $params = [] ): array {
    // ...
}
```

#### Error Handling
- Throw domain-specific exceptions
- Catch exceptions at appropriate levels (controller/gateway level)
- Use `\WP_Error` for WordPress-specific errors

#### Security
- **Escape on output**: `esc_html__()`, `esc_attr__()`, `esc_url()`, `esc_js()`
- **Sanitize on input**: `sanitize_text_field()`, `absint()`, `wp_kses()`
- **Nonces**: Use `wp_create_nonce()` and `check_admin_referer()` for admin actions
- **Capability checks**: Always verify `current_user_can()` for privileged operations
- **Database**: Use `$wpdb->prepare()` with placeholders (`%s`, `%d`)
- **Secrets**: Never hardcode; use WordPress options or constants

```php
// Escaping
echo '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Link', 'wc-gocardless-payments' ) . '</a>';

// Sanitization
$order_id = absint( $_POST['order_id'] );
$email    = sanitize_email( $_POST['email'] );
```

#### Internationalization (i18n)
- Wrap all user-facing strings: `__( 'Text', 'wc-gocardless-payments' )`
- Use escape variants: `esc_html__()`, `esc_html_e()`, `esc_attr__()`
- Never concatenate translatable strings with variables; use `sprintf()`:

```php
// Bad
$msg = __( 'Order #' . $order_id, 'wc-gocardless-payments' );

// Good
$msg = sprintf( __( 'Order #%d', 'wc-gocardless-payments' ), $order_id );
```

---

### JavaScript

#### File Structure
- Plain JS files in `assets/js/` — no build step required
- Use IIFE pattern with jQuery:

```javascript
( function ( $ ) {
    'use strict';

    $( document ).ready( function () {
        // ...
    } );
}( jQuery ) );
```

#### Naming
- Variables/functions: `camelCase`
- Constants: `UPPER_SNAKE_CASE`
- Use descriptive names

#### Globals
- Declare all globals in `.eslintrc.js`:
```javascript
globals: {
    wp: "readonly",
    GoCardless: "readonly",
    wcGoCardless: "readonly",
}
```

#### Best Practices
- Use `'use strict';` in all scripts
- Prefer `const` over `let`, avoid `var`
- Use template literals instead of concatenation

---

### File Organization

```
includes/
├── api/
│   ├── class-wc-gocardless-api-client.php
│   └── class-wc-gocardless-api-payments.php
├── admin/
│   └── class-wc-gocardless-admin.php
├── gateway/
│   ├── class-wc-gocardless-gateway.php
│   ├── class-wc-gocardless-gateway-direct-debit.php
│   ├── class-wc-gocardless-gateway-instant-bank.php
│   └── class-wc-gocardless-gateway-vrp.php
├── webhooks/
│   ├── class-wc-gocardless-webhook-handler.php
│   └── class-wc-gocardless-webhook-processor.php
├── subscriptions/
│   └── class-wc-gocardless-subscriptions.php
├── utilities/
│   ├── class-wc-gocardless-logger.php
│   ├── class-wc-gocardless-order-helper.php
│   └── class-wc-gocardless-idempotency.php
└── class-wc-gocardless.php
```

---

## 3. Testing Guidelines

- Tests go in `tests/php/src/` mirroring the class path
- Use PHPUnit with Brain Monkey for WordPress function mocking
- Bootstrap: `tests/php/bootstrap.php`
- Test naming: `ClassNameTest.php`

```php
class API_Client_Test extends \PHPUnit\Framework\TestCase {
    public function test_get_request_returns_array() {
        // Arrange
        $client = new API_Client();

        // Act
        $result = $client->get( 'payments/payment_xxx' );

        // Assert
        $this->assertIsArray( $result );
    }
}
```

---

## 4. Commit Messages

Format: `<type>(<scope>): <short imperative summary>`

Types: `feat`, `fix`, `perf`, `refactor`, `docs`, `test`, `chore`, `build`, `ci`, `security`

Examples:
```
feat(gateway): add Instant Bank Pay support
fix(webhook): validate HMAC signature before processing
docs: update webhook URL in readme.txt
```

---

## 5. Key Security Considerations

1. **Never expose secrets** — API tokens, webhook secret, access tokens
2. **Validate webhook signatures** using `hash_equals()` with HMAC-SHA256
3. **Use nonces** for all AJAX/admin form submissions
4. **Check capabilities** (`manage_woocommerce`, etc.) before privileged actions
5. **Sanitize all input** — never trust `$_GET`, `$_POST`, `$_REQUEST`
6. **Escape all output** — every user data point must be escaped

---

## 6. Important Hooks & Filters

- `woocommerce_payment_gateways` — Register the gateway
- `rest_api_init` — Register REST routes (webhooks)
- `woocommerce_update_options_payment_gateways` — Save settings
- `woocommerce_order_refunded` — Trigger refund via API

---

## 7. Resources

- WordPress Coding Standards: https://developer.wordpress.org/coding-standards/wordpress-coding-standards/
- WooCommerce Plugin Handbook: https://developer.woocommerce.com/
- WPCS Sniffs: https://github.com/WordPress/WordPress-Coding-Standards
- GoCardless API Documentation: https://developer.gocardless.com/

---

This file is used by AI agents to understand project conventions. Update it when project conventions change.
