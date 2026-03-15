<?php
/**
 * Test case for Utilities - Logger, Order Helper, Idempotency.
 *
 * @package WC_GoCardless_Payments
 */

namespace WC_GoCardless_Payments\Tests\Utilities;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use WC_GoCardless_Idempotency;
use WC_GoCardless_Logger;
use WC_GoCardless_Order_Helper;

/**
 * Utilities_Test.
 */
class Utilities_Test extends TestCase {

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
     * Test logger can be instantiated.
     */
    public function test_logger_instantiation() {
        $logger = new WC_GoCardless_Logger();
        $this->assertInstanceOf( WC_GoCardless_Logger::class, $logger );
    }

    /**
     * Test logger has info method.
     */
    public function test_logger_has_info_method() {
        $logger = new WC_GoCardless_Logger();
        $this->assertTrue( method_exists( $logger, 'info' ) );
    }

    /**
     * Test logger has error method.
     */
    public function test_logger_has_error_method() {
        $logger = new WC_GoCardless_Logger();
        $this->assertTrue( method_exists( $logger, 'error' ) );
    }

    /**
     * Test logger has debug method.
     */
    public function test_logger_has_debug_method() {
        $logger = new WC_GoCardless_Logger();
        $this->assertTrue( method_exists( $logger, 'debug' ) );
    }

    /**
     * Test idempotency can be instantiated.
     */
    public function test_idempotency_instantiation() {
        $idempotency = new WC_GoCardless_Idempotency();
        $this->assertInstanceOf( WC_GoCardless_Idempotency::class, $idempotency );
    }

    /**
     * Test idempotency generates unique keys.
     */
    public function test_idempotency_generates_unique_keys() {
        $idempotency = new WC_GoCardless_Idempotency();

        $key1 = $idempotency->generate();
        $key2 = $idempotency->generate();

        $this->assertNotEquals( $key1, $key2 );
        $this->assertIsString( $key1 );
    }

    /**
     * Test idempotency format.
     */
    public function test_idempotency_format() {
        $idempotency = new WC_GoCardless_Idempotency();

        $key = $idempotency->generate();

        // Key should be alphanumeric
        $this->assertTrue( ctype_alnum( $key ) );
        // Key should have reasonable length
        $this->assertLessThanOrEqual( 40, strlen( $key ) );
    }

    /**
     * Test order helper has required methods.
     */
    public function test_order_helper_has_required_methods() {
        $helper = new WC_GoCardless_Order_Helper();

        $this->assertTrue( method_exists( $helper, 'get_order' ) );
        $this->assertTrue( method_exists( $helper, 'get_payment_id' ) );
        $this->assertTrue( method_exists( $helper, 'set_payment_id' ) );
        $this->assertTrue( method_exists( $helper, 'get_mandate_id' ) );
        $this->assertTrue( method_exists( $helper, 'set_mandate_id' ) );
    }

    /**
     * Test order helper has META_PREFIX constant.
     */
    public function test_order_helper_has_meta_prefix() {
        $this->assertTrue( defined( 'WC_GoCardless_Order_Helper::META_PREFIX' ) );
        $this->assertEquals( '_gocardless_', WC_GoCardless_Order_Helper::META_PREFIX );
    }
}
