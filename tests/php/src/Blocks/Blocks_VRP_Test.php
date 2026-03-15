<?php
/**
 * Test case for Blocks Integration.
 *
 * @package WC_GoCardless_Payments
 */

namespace WC_GoCardless_Payments\Tests\Blocks;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use WC_GoCardless_Blocks_VRP;

/**
 * Test case for VRP Blocks.
 */
class Blocks_VRP_Test extends TestCase {

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
     * Test VRP blocks can be instantiated.
     */
    public function test_vrp_blocks_instantiation() {
        $blocks = new WC_GoCardless_Blocks_VRP();
        $this->assertInstanceOf( WC_GoCardless_Blocks_VRP::class, $blocks );
    }
}
