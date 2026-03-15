<?php
/**
 * Test case for Frontend - Checkout.
 *
 * @package WC_GoCardless_Payments
 */

namespace WC_GoCardless_Payments\Tests\Frontend;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;

/**
 * Checkout_Test.
 */
class Checkout_Test extends TestCase {

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
     * Test checkout class can be instantiated.
     */
    public function test_checkout_instantiation() {
        $checkout = new WC_GoCardless_Checkout();
        $this->assertInstanceOf( WC_GoCardless_Checkout::class, $checkout );
    }

    /**
     * Test checkout has required methods.
     */
    public function test_checkout_has_required_methods() {
        $checkout = new WC_GoCardless_Checkout();
        
        $this->assertTrue( method_exists( $checkout, 'enqueue_checkout_scripts' ) );
        $this->assertTrue( method_exists( $checkout, 'render_payment_fields' ) );
    }
}
