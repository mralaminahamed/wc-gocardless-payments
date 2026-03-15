<?php
 namespace WC_GoCardless_Payments\Tests\Blocks;

use PHPUnit\Framework\TestCase;
use function Brain\Monkey;

/**
 * Test case for Instant Bank Blocks.
 */
class Blocks_Instant_Bank_Test extends TestCase {

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
     * Test Instant Bank blocks can be instantiated.
     */
    public function test_instant_bank_blocks_instantiation() {
        $blocks = new WC_GoCardless_Blocks_Instant_Bank();
        $this->assertInstanceOf( WC_GoCardless_Blocks_Instant_Bank::class, $blocks );
    }
}
