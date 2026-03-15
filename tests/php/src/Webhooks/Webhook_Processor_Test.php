<?php
/**
 * Test case for Webhook Processor.
 *
 * @package WC_GoCardless_Payments
 */

namespace WC_GoCardless_Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use WC_GoCardless\Webhooks\Webhook_Processor;

/**
 * Webhook_Processor_Test.
 */
class Webhook_Processor_Test extends TestCase {

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
     * Test webhook processor is instantiated correctly.
     */
    public function test_processor_instantiation() {
        $processor = new WC_GoCardless_Webhook_Processor();
        $this->assertInstanceOf( WC_GoCardless_Webhook_Processor::class, $processor );
    }

    /**
     * Test valid webhook signature passes verification.
     */
    public function test_valid_signature_passes_verification() {
        $payload    = '{"events": [{"id": "EV123"}]}';
        $signature = $this->generate_valid_signature( $payload );

        $processor = new Webhook_Processor();
        $result    = $processor->verify_signature( $payload, $signature );

        $this->assertTrue( $result );
    }

    /**
     * Test invalid webhook signature fails verification.
     */
    public function test_invalid_signature_fails_verification() {
        $payload    = '{"events": [{"id": "EV123"}]}';
        $signature = 'invalid_signature';

        $processor = new Webhook_Processor();
        $result    = $processor->verify_signature( $payload, $signature );

        $this->assertFalse( $result );
    }

    /**
     * Test payment_confirmed event updates order status.
     */
    public function test_payment_confirmed_updates_order() {
        $event = $this->create_mock_event( 'payment_confirmed', [
            'id'          => 'payment_123',
            'status'      => 'confirmed',
            'amount'      => 1000,
            'currency'    => 'GBP',
            'description' => 'Test payment',
        ] );

        Monkey\Functions::expect( 'get_post_meta' )
            ->with( 123, '_gocardless_payment_id', true )
            ->andReturn( 'payment_123' );

        Monkey\Functions::expect( 'wc_update_order_status' )
            ->once()
            ->with( 123, 'processing' );

        $processor = new Webhook_Processor();
        $processor->process_event( $event );
    }

    /**
     * Test payment_failed event updates order status.
     */
    public function test_payment_failed_updates_order() {
        $event = $this->create_mock_event( 'payment_failed', [
            'id'     => 'payment_123',
            'status' => 'failed',
        ] );

        Monkey\Functions::expect( 'get_post_meta' )
            ->with( 123, '_gocardless_payment_id', true )
            ->andReturn( 'payment_123' );

        Monkey\Functions::expect( 'wc_update_order_status' )
            ->once()
            ->with( 123, 'failed' );

        $processor = new Webhook_Processor();
        $processor->process_event( $event );
    }

    /**
     * Test billing_request_completed event.
     */
    public function test_billing_request_completed() {
        $event = $this->create_mock_event( 'billing_request_completed', [
            'id'                => 'BR123',
            'status'            => 'completed',
            'billing_request'   => [
                'id' => 'BR123',
            ],
        ] );

        $processor = new Webhook_Processor();
        $result    = $processor->process_event( $event );

        $this->assertTrue( $result );
    }

    /**
     * Test duplicate event is not processed twice.
     */
    public function test_duplicate_event_ignored() {
        $event_id = 'EV123';

        // First call should process
        Monkey\Functions::expect( 'get_transient' )
            ->once()
            ->with( 'gocardless_event_' . $event_id )
            ->andReturn( false );

        // Set transient to mark as processed
        Monkey\Functions::expect( 'set_transient' )
            ->once()
            ->with( 'gocardless_event_' . $event_id, true, DAY_IN_SECONDS );

        // Second call should be skipped
        Monkey\Functions::expect( 'get_transient' )
            ->once()
            ->with( 'gocardless_event_' . $event_id )
            ->andReturn( true );

        $event     = $this->create_mock_event( 'payment_confirmed', [ 'id' => 'payment_123' ] );
        $processor = new Webhook_Processor();

        // Process twice
        $processor->process_event( $event );
        $processor->process_event( $event );
    }

    /**
     * Test unsupported event type is handled gracefully.
     */
    public function test_unsupported_event_handled() {
        $event = $this->create_mock_event( 'unknown_event', [] );

        $processor = new Webhook_Processor();
        $result    = $processor->process_event( $event );

        $this->assertTrue( $result ); // Should return true, just skip
    }

    /**
     * Create mock event data.
     *
     * @param string $type Event type.
     * @param array  $data Event data.
     * @return array
     */
    private function create_mock_event( string $type, array $data ): array {
        return [
            'id'        => 'EV123',
            'type'      => $type,
            'created_at' => date( 'c' ),
            'resource_type' => 'events',
            'data'      => [
                'resource' => $data,
            ],
        ];
    }

    /**
     * Generate valid HMAC signature for testing.
     *
     * @param string $payload Payload to sign.
     * @return string
     */
    private function generate_valid_signature( string $payload ): string {
        $secret = defined( 'WC_GOCARDLESS_WEBHOOK_SECRET' )
            ? WC_GOCARDLESS_WEBHOOK_SECRET
            : 'test_secret';

        return hash_hmac( 'sha256', $payload, $secret );
    }
}
