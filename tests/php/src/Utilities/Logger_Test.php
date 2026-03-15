<?php
/**
 * Test case for Logger utility.
 *
 * @package WC_GoCardless_Payments
 */

namespace WC_GoCardless_Payments\Tests\Utilities;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;

/**
 * Logger_Test.
 */
class Logger_Test extends TestCase {

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
     * Test logger has warning method.
     */
    public function test_logger_has_warning_method() {
        $logger = new WC_GoCardless_Logger();
        $this->assertTrue( method_exists( $logger, 'warning' ) );
    }

    /**
     * Test logger has debug method.
     */
    public function test_logger_has_debug_method() {
        $logger = new WC_GoCardless_Logger();
        $this->assertTrue( method_exists( $logger, 'debug' ) );
    }

    /**
     * Test logger log method exists.
     */
    public function test_logger_has_log_method() {
        $logger = new WC_GoCardless_Logger();
        $this->assertTrue( method_exists( $logger, 'log' ) );
    }
}
