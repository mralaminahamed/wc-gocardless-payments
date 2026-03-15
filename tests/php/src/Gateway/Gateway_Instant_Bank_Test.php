<?php
 namespace WC_GoCardless_Payments\Tests\Gateway;

use PHPUnit\Framework\TestCase;
use function Brain\Monkey;

/**
 * Gateway_Instant_Bank_Test.
 */
class Gateway_Instant_Bank_Test extends TestCase {

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
     * Test Instant Bank gateway has correct ID.
     */
    public function test_instant_bank_gateway_id() {
        $gateway = new WC_GoCardless_Gateway_Instant_Bank();
        $this->assertEquals( 'gocardless_instant_bank', $gateway->id );
    }

    /**
     * Test Instant Bank gateway supports required features.
     */
    public function test_instant_bank_supports() {
        $gateway = new WC_GoCardless_Gateway_Instant_Bank();
        $this->assertTrue( $gateway->supports( 'products' ) );
        $this->assertTrue( $gateway->supports( 'refunds' ) );
    }
}
