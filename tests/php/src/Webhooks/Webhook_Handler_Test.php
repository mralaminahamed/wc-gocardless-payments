<?php
/**
 * Test case for Webhooks - Handler.
 *
 * @package WC_GoCardless_Payments
 */

namespace WC_GoCardless_Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use WC_GoCardless_Webhook_Handler;

/**
 * Webhook_Handler_Test.
 */
class Webhook_Handler_Test extends TestCase {

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
     * Test webhook handler can be instantiated.
     */
    public function test_webhook_handler_instantiation() {
        $handler = new WC_GoCardless_Webhook_Handler();
        $this->assertInstanceOf( WC_GoCardless_Webhook_Handler::class, $handler );
    }

    /**
     * Test webhook handler has required methods.
     */
    public function test_webhook_handler_has_required_methods() {
        $handler = new WC_GoCardless_Webhook_Handler();

        $this->assertTrue( method_exists( $handler, 'register_routes' ) );
        $this->assertTrue( method_exists( $handler, 'handle_webhook' ) );
    }
}
