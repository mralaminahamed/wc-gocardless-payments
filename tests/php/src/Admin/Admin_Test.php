<?php
/**
 * Test case for Admin.
 *
 * @package WC_GoCardless_Payments
 */

namespace WC_GoCardless_Payments\Tests\Admin;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use WC_GoCardless_Admin;

/**
 * Admin_Test.
 */
class Admin_Test extends TestCase {

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
     * Test admin class can be instantiated.
     */
    public function test_admin_instantiation() {
        $admin = new WC_GoCardless_Admin();
        $this->assertInstanceOf( WC_GoCardless_Admin::class, $admin );
    }

    /**
     * Test admin has required methods.
     */
    public function test_admin_has_required_methods() {
        $admin = new WC_GoCardless_Admin();

        $this->assertTrue( method_exists( $admin, 'init' ) );
        $this->assertTrue( method_exists( $admin, 'enqueue_scripts' ) );
        $this->assertTrue( method_exists( $admin, 'add_meta_boxes' ) );
    }
}
