<?php
/**
 * Test case for Subscriptions - Renewal Handler.
 *
 * @package WC_GoCardless_Payments
 */

namespace WC_GoCardless_Payments\Tests\Subscriptions;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;

/**
 * Renewal_Handler_Test.
 */
class Renewal_Handler_Test extends TestCase {

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
     * Test renewal handler can be instantiated.
     */
    public function test_renewal_handler_instantiation() {
        $handler = new WC_GoCardless_Renewal_Handler();
        $this->assertInstanceOf( WC_GoCardless_Renewal_Handler::class, $handler );
    }

    /**
     * Test renewal handler has required methods.
     */
    public function test_renewal_handler_has_process_renewal_method() {
        $handler = new WC_GoCardless_Renewal_Handler();
        $this->assertTrue( method_exists( $handler, 'process_renewal' ) );
    }

    /**
     * Test renewal handler has scheduled method.
     */
    public function test_renewal_handler_has_scheduled_method() {
        $handler = new WC_GoCardless_Renewal_Handler();
        $this->assertTrue( method_exists( $handler, 'scheduled_subscription_payment' ) );
    }
}
