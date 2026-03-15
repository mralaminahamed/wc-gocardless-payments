<?php
/**
 * Test case for Gateways.
 *
 * @package WC_GoCardless_Payments
 */

namespace WC_GoCardless_Payments\Tests\Gateway;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;

/**
 * Gateway_VRP_Test.
 */
class Gateway_VRP_Test extends TestCase {

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
     * Test VRP gateway has correct ID.
     */
    public function test_vrp_gateway_id() {
        $gateway = new WC_GoCardless_Gateway_VRP();
        $this->assertEquals( 'gocardless_vrp', $gateway->id );
    }

    /**
     * Test VRP gateway supports required features.
     */
    public function test_vrp_supports() {
        $gateway = new WC_GoCardless_Gateway_VRP();
        $this->assertTrue( $gateway->supports( 'products' ) );
        $this->assertTrue( $gateway->supports( 'refunds' ) );
        $this->assertTrue( $gateway->supports( 'subscriptions' ) );
    }
}
