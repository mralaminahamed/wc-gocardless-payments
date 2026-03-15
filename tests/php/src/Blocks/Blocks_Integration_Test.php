<?php
 namespace WC_GoCardless_Payments\Tests\Blocks;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use WC_GoCardless_Blocks_Integration;

/**
 * Blocks_Integration_Test.
 */
class Blocks_Integration_Test extends TestCase {

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
     * Test blocks integration base class can be instantiated.
     */
    public function test_blocks_integration_instantiation() {
        $blocks = new WC_GoCardless_Blocks_Integration();
        $this->assertInstanceOf( WC_GoCardless_Blocks_Integration::class, $blocks );
    }

    /**
     * Test blocks integration has required methods.
     */
    public function test_blocks_integration_has_required_methods() {
        $blocks = new WC_GoCardless_Blocks_Integration();

        $this->assertTrue( method_exists( $blocks, 'initialize' ) );
        $this->assertTrue( method_exists( $blocks, 'get_payment_method_data' ) );
    }
}
