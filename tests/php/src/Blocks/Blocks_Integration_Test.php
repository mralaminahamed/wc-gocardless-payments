<?php
namespace WC_GoCardless_Payments\Tests\Blocks;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;

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
     * Test blocks integration base class methods exist.
     */
    public function test_blocks_integration_has_required_methods() {
        $reflection = new \ReflectionClass( 'WC_GoCardless_Blocks_Integration' );

        $this->assertTrue( $reflection->isAbstract() );
        $this->assertTrue( $reflection->hasMethod( 'initialize' ) );
        $this->assertTrue( $reflection->hasMethod( 'get_payment_method_data' ) );
        $this->assertTrue( $reflection->hasMethod( 'is_active' ) );
    }
}
