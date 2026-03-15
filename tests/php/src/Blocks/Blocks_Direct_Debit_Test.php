<?php
 namespace WC_GoCardless_Payments\Tests\Blocks;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use WC_GoCardless_Blocks_Direct_Debit;

/**
 * Test case for Direct Debit Blocks.
 */
class Blocks_Direct_Debit_Test extends TestCase {

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
     * Test Direct Debit blocks can be instantiated.
     */
    public function test_direct_debit_blocks_instantiation() {
        $blocks = new WC_GoCardless_Blocks_Direct_Debit();
        $this->assertInstanceOf( WC_GoCardless_Blocks_Direct_Debit::class, $blocks );
    }
}
