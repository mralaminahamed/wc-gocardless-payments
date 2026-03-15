<?php
/**
 * Test case for Idempotency utility.
 *
 * @package WC_GoCardless_Payments
 */

namespace WC_GoCardless_Payments\Tests\Utilities;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;

/**
 * Idempotency_Test.
 */
class Idempotency_Test extends TestCase {

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
     * Test idempotency can be instantiated.
     */
    public function test_idempotency_instantiation() {
        $idempotency = new WC_GoCardless_Idempotency();
        $this->assertInstanceOf( WC_GoCardless_Idempotency::class, $idempotency );
    }

    /**
     * Test idempotency generates unique keys.
     */
    public function test_idempotency_generates_unique_keys() {
        $idempotency = new WC_GoCardless_Idempotency();
        
        $key1 = $idempotency->generate();
        $key2 = $idempotency->generate();
        
        $this->assertNotEquals( $key1, $key2 );
    }

    /**
     * Test idempotency generates string keys.
     */
    public function test_idempotency_generates_string() {
        $idempotency = new WC_GoCardless_Idempotency();
        $key = $idempotency->generate();
        
        $this->assertIsString( $key );
    }

    /**
     * Test idempotency key has reasonable length.
     */
    public function test_idempotency_key_length() {
        $idempotency = new WC_GoCardless_Idempotency();
        $key = $idempotency->generate();
        
        $this->assertLessThanOrEqual( 40, strlen( $key ) );
    }
}
