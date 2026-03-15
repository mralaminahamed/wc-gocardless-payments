<?php
/**
 * Test case for Payment Token - Mandate.
 *
 * @package WC_GoCardless_Payments
 */

namespace WC_GoCardless_Payments\Tests\PaymentToken;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use WC_GoCardless_Payment_Token_Mandate;

/**
 * Payment_Token_Mandate_Test.
 */
class Payment_Token_Mandate_Test extends TestCase {

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
     * Test payment token mandate class can be instantiated.
     */
    public function test_payment_token_mandate_instantiation() {
        $token = new WC_GoCardless_Payment_Token_Mandate();
        $this->assertInstanceOf( WC_GoCardless_Payment_Token_Mandate::class, $token );
    }

    /**
     * Test payment token has correct type.
     */
    public function test_payment_token_has_correct_type() {
        $token = new WC_GoCardless_Payment_Token_Mandate();
        $this->assertEquals( 'gocardless_mandate', $token->get_type() );
    }

    /**
     * Test payment token can set and get mandate ID.
     */
    public function test_payment_token_mandate_id() {
        $token = new WC_GoCardless_Payment_Token_Mandate();
        $token->set_mandate_id( 'MD123' );
        $this->assertEquals( 'MD123', $token->get_mandate_id() );
    }

    /**
     * Test payment token can set and get scheme.
     */
    public function test_payment_token_scheme() {
        $token = new WC_GoCardless_Payment_Token_Mandate();
        $token->set_scheme( 'bacs_debit' );
        $this->assertEquals( 'bacs_debit', $token->get_scheme() );
    }

    /**
     * Test payment token has META_KEYS constant.
     */
    public function test_payment_token_has_meta_keys() {
        $this->assertTrue( defined( 'WC_GoCardless_Payment_Token_Mandate::META_KEY_MANDATE_ID' ) );
        $this->assertTrue( defined( 'WC_GoCardless_Payment_Token_Mandate::META_KEY_SCHEME' ) );
    }
}
