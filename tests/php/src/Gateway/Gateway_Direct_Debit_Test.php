<?php
 namespace WC_GoCardless_Payments\Tests\Gateway;

use PHPUnit\Framework\TestCase;
use function Brain\Monkey;

/**
 * Gateway_Direct_Debit_Test.
 */
class Gateway_Direct_Debit_Test extends TestCase {

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
     * Test Direct Debit gateway has correct ID.
     */
    public function test_direct_debit_gateway_id() {
        $gateway = new WC_GoCardless_Gateway_Direct_Debit();
        $this->assertEquals( 'gocardless_direct_debit', $gateway->id );
    }

    /**
     * Test Direct Debit gateway supports required features.
     */
    public function test_direct_debit_supports() {
        $gateway = new WC_GoCardless_Gateway_Direct_Debit();
        $this->assertTrue( $gateway->supports( 'products' ) );
        $this->assertTrue( $gateway->supports( 'refunds' ) );
        $this->assertTrue( $gateway->supports( 'tokenization' ) );
        $this->assertTrue( $gateway->supports( 'subscriptions' ) );
    }
}
