<?php
/**
 * Test case for Frontend - Redirect.
 *
 * @package WC_GoCardless_Payments
 */

namespace WC_GoCardless_Payments\Tests\Frontend;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;

/**
 * Redirect_Test.
 */
class Redirect_Test extends TestCase {

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
     * Test redirect class can be instantiated.
     */
    public function test_redirect_instantiation() {
        $redirect = new WC_GoCardless_Redirect();
        $this->assertInstanceOf( WC_GoCardless_Redirect::class, $redirect );
    }

    /**
     * Test redirect has return endpoint constant.
     */
    public function test_redirect_has_return_endpoint() {
        $this->assertTrue( defined( 'WC_GoCardless_Redirect::RETURN_ENDPOINT' ) );
        $this->assertEquals( 'wc_gocardless_return', WC_GoCardless_Redirect::RETURN_ENDPOINT );
    }
}
