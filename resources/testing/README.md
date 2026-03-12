# Testing Guide

This guide covers testing procedures for the WooCommerce GoCardless Payments plugin.

## Test Environment

### Requirements

- PHP 7.4 or higher
- WordPress 6.2+
- WooCommerce 8.0+
- PHPUnit 9.x
- Brain Monkey (for WordPress mocking)

### Setup

```bash
# Install dependencies
composer install

# Verify PHPUnit
./vendor/bin/phpunit --version
```

---

## Running Tests

### All Tests

```bash
./vendor/bin/phpunit
```

### Single Test File

```bash
./vendor/bin/phpunit tests/php/src/Api/API_Client_Test.php
```

### Specific Test Method

```bash
./vendor/bin/phpunit --filter test_get_request
```

### With Coverage

```bash
./vendor/bin/phpunit --coverage-html coverage/
```

---

## Test Structure

```
tests/
├── php/
│   ├── bootstrap.php           # WordPress/Brain Monkey setup
│   └── src/
│       ├── Api/
│       │   └── API_Client_Test.php
│       ├── Gateway/
│       │   └── Gateway_Test.php
│       └── Webhooks/
│           └── Webhook_Processor_Test.php
```

---

## Writing Tests

### Basic Test Structure

```php
<?php
/**
 * Test case for API Client.
 *
 * @package WC_GoCardless_Payments
 */

namespace WC_GoCardless_Payments\Tests\Api;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;

/**
 * API_Client_Test.
 */
class API_Client_Test extends TestCase {

    /**
     * Set up test.
     */
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    /**
     * Tear down test.
     */
    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test get request returns array.
     */
    public function test_get_request_returns_array() {
        $client = new API_Client();
        $result = $client->get( 'payments/payment_xxx' );
        $this->assertIsArray( $result );
    }
}
```

### Mocking WordPress Functions

```php
// Mock WooCommerce function
Monkey\Functions::expect( 'WC' )->andReturn( $wc_mock );

// Mock plugin function
Monkey\Functions::expect( 'wp_remote_get' )->andReturn(
    [
        'body'     => json_encode( [ 'id' => 'payment_123' ] ),
        'response' => [ 'code' => 200 ],
    ]
);

// Mock WordPress option
Monkey\Functions::expect( 'get_option' )
    ->with( 'wc_gocardless_settings' )
    ->andReturn( [ 'access_token' => 'test_token' ] );
```

### Mocking API Responses

```php
public function test_creates_payment() {
    // Mock the HTTP request
    Monkey\Functions::expect( 'wp_remote_post' )
        ->once()
        ->with(
            'https://api.gocardless.com/payments',
            Mockery::any()
        )
        ->andReturn(
            [
                'body'     => json_encode( [
                    'payments' => [
                        [
                            'id'     => 'PM123',
                            'status' => 'pending',
                        ],
                    ],
                ] ),
                'response' => [ 'code' => 201 ],
            ]
        );

    $client   = new API_Client();
    $result   = $client->create_payment( 1000, 'GBP' );
    
    $this->assertArrayHasKey( 'id', $result );
    $this->assertEquals( 'pending', $result['status'] );
}
```

---

## Test Categories

### Unit Tests

Test individual methods in isolation.

```bash
./vendor/bin/phpunit --testsuite unit
```

### Integration Tests

Test WordPress/WooCommerce integration.

```bash
./vendor/bin/phpunit --testsuite integration
```

---

## Testing Best Practices

### Arrange-Act-Assert

```php
public function test_payment_calculation() {
    // Arrange
    $order = $this->create_mock_order( 100.00 );
    
    // Act
    $result = $this->gateway->calculate_payment_amount( $order );
    
    // Assert
    $this->assertEquals( 10000, $result ); // in pence
}
```

### Test Naming

- Use descriptive names: `test_creates_payment_with_valid_data`
- Follow pattern: `test_<method>_<expected_behavior>`
- One assertion per test when possible

### Isolation

- Each test should be independent
- Use `setUp()` to reset mocks
- Clean up any database changes

---

## Mock Objects

### Creating Mock Orders

```php
private function create_mock_order( $total = 100.00 ) {
    $order = $this->getMockBuilder( 'WC_Order' )
        ->onlyMethods( [ 'get_total', 'get_currency', 'get_id' ] )
        ->getMock();
    
    $order->method( 'get_total' )->willReturn( $total );
    $order->method( 'get_currency' )->willReturn( 'GBP' );
    $order->method( 'get_id' )->willReturn( 123 );
    
    return $order;
}
```

### Stubbing WP_Error

```php
public function test_api_error_handling() {
    Monkey\Functions::expect( 'wp_remote_post' )
        ->andReturn( new \WP_Error( 'http_error', 'Connection failed' ) );

    $this->expectException( API_Exception::class );
    
    $client = new API_Client();
    $client->get( 'payments' );
}
```

---

## Coverage Requirements

### Minimum Coverage

- API Client: 80%
- Gateway: 70%
- Webhook Processor: 80%

### Running Coverage

```bash
./vendor/bin/phpunit --coverage-text --coverage-filter includes/api/
```

---

## CI Integration

### GitHub Actions Example

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Install PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '7.4'
      - name: Install dependencies
        run: composer install
      - name: Run tests
        run: ./vendor/bin/phpunit
      - name: Run PHPStan
        run: ./vendor/bin/phpstan analyse
```

---

## Debugging Tests

### Verbose Output

```bash
./vendor/bin/phpunit --verbose
```

### Stop on First Failure

```bash
./vendor/bin/phpunit --stop-on-failure
```

### Show Code Coverage

```bash
./vendor/bin/phpunit --coverage-text
```

---

## Troubleshooting

### Tests Not Running

```bash
# Check PHPUnit configuration
cat phpunit.xml

# Verify bootstrap file exists
ls tests/php/bootstrap.php
```

### Memory Issues

```bash
./vendor/bin/phpunit --memory-limit=512M
```

### Timeout Issues

```bash
./vendor/bin/phpunit --max-execution-time=300
```
