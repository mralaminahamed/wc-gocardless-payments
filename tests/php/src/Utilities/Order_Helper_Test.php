<?php
/**
 * Test case for Order Helper utility.
 *
 * @package WC_GoCardless_Payments
 */

namespace WC_GoCardless_Payments\Tests\Utilities;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;

/**
 * Order_Helper_Test.
 */
class Order_Helper_Test extends TestCase {

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
     * Test order helper can be instantiated.
     */
    public function test_order_helper_instantiation() {
        $helper = new WC_GoCardless_Order_Helper();
        $this->assertInstanceOf( WC_GoCardless_Order_Helper::class, $helper );
    }

    /**
     * Test order helper has META_PREFIX constant.
     */
    public function test_order_helper_has_meta_prefix() {
        $this->assertTrue( defined( 'WC_GoCardless_Order_Helper::META_PREFIX' ) );
    }

    /**
     * Test order helper has get_order method.
     */
    public function test_order_helper_has_get_order_method() {
        $helper = new WC_GoCardless_Order_Helper();
        $this->assertTrue( method_exists( $helper, 'get_order' ) );
    }

    /**
     * Test order helper has get_payment_id method.
     */
    public function test_order_helper_has_get_payment_id_method() {
        $helper = new WC_GoCardless_Order_Helper();
        $this->assertTrue( method_exists( $helper, 'get_payment_id' ) );
    }

    /**
     * Test order helper has set_payment_id method.
     */
    public function test_order_helper_has_set_payment_id_method() {
        $helper = new WC_GoCardless_Order_Helper();
        $this->assertTrue( method_exists( $helper, 'set_payment_id' ) );
    }

    /**
     * Test order helper has get_mandate_id method.
     */
    public function test_order_helper_has_get_mandate_id_method() {
        $helper = new WC_GoCardless_Order_Helper();
        $this->assertTrue( method_exists( $helper, 'get_mandate_id' ) );
    }

    /**
     * Test order helper has set_mandate_id method.
     */
    public function test_order_helper_has_set_mandate_id_method() {
        $helper = new WC_GoCardless_Order_Helper();
        $this->assertTrue( method_exists( $helper, 'set_mandate_id' ) );
    }
}
