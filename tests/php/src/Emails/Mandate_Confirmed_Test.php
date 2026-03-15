<?php
/**
 * Test case for Emails - Mandate Confirmed.
 *
 * @package WC_GoCardless_Payments
 */

namespace WC_GoCardless_Payments\Tests\Emails;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;

/**
 * Mandate_Confirmed_Test.
 */
class Mandate_Confirmed_Test extends TestCase {

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
     * Test email class can be instantiated.
     */
    public function test_email_mandate_confirmed_instantiation() {
        $email = new WC_GoCardless_Email_Mandate_Confirmed();
        $this->assertInstanceOf( WC_GoCardless_Email_Mandate_Confirmed::class, $email );
    }

    /**
     * Test email has required methods.
     */
    public function test_email_has_required_methods() {
        $email = new WC_GoCardless_Email_Mandate_Confirmed();
        
        $this->assertTrue( method_exists( $email, 'get_default_subject' ) );
        $this->assertTrue( method_exists( $email, 'get_default_heading' ) );
        $this->assertTrue( method_exists( $email, 'trigger' ) );
    }
}
