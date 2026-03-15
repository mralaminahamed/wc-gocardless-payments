<?php
/**
 * Test case for Billing Requests API.
 *
 * @package WC_GoCardless_Payments
 */

namespace WC_GoCardless_Payments\Tests\Api;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use WC_GoCardless_API_Billing_Requests;
use WC_GoCardless_API_Client;

/**
 * Billing_Requests_Test.
 */
class Billing_Requests_Test extends TestCase {

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
     * Test billing requests API creates direct debit billing request.
     */
    public function test_create_for_direct_debit() {
        $mock_client = $this->createMock( WC_GoCardless_API_Client::class );

        $mock_client->expects( $this->once() )
            ->method( 'post' )
            ->with(
                '/billing_requests',
                $this->callback( function( $body ) {
                    return isset( $body['billing_requests']['mandate_request'] )
                        && isset( $body['billing_requests']['payment_request'] );
                } ),
                $this->anything()
            )
            ->willReturn( [
                'billing_requests' => [
                    'id' => 'BR123',
                    'status' => 'pending',
                ]
            ] );

        $api = new WC_GoCardless_API_Billing_Requests( $mock_client );
        $result = $api->create_for_direct_debit( [
            'amount' => 1000,
            'currency' => 'GBP',
            'description' => 'Test payment',
            'wc_order_id' => '123',
            'idempotency_key' => 'key123',
        ] );

        $this->assertIsArray( $result );
        $this->assertEquals( 'BR123', $result['billing_requests']['id'] );
    }

    /**
     * Test billing requests API creates IBP billing request.
     */
    public function test_create_for_instant_bank_pay() {
        $mock_client = $this->createMock( WC_GoCardless_API_Client::class );

        $mock_client->expects( $this->once() )
            ->method( 'post' )
            ->with(
                '/billing_requests',
                $this->callback( function( $body ) {
                    return isset( $body['billing_requests']['payment_request']['funds_settlement'] )
                        && $body['billing_requests']['payment_request']['funds_settlement'] === 'direct';
                } ),
                $this->anything()
            )
            ->willReturn( [
                'billing_requests' => [
                    'id' => 'BR456',
                    'status' => 'pending',
                ]
            ] );

        $api = new WC_GoCardless_API_Billing_Requests( $mock_client );
        $result = $api->create_for_instant_bank_pay( [
            'amount' => 2500,
            'currency' => 'GBP',
            'description' => 'IBP payment',
            'wc_order_id' => '456',
            'idempotency_key' => 'key456',
        ] );

        $this->assertIsArray( $result );
    }

    /**
     * Test billing requests API creates mandate-only billing request.
     */
    public function test_create_for_mandate_only() {
        $mock_client = $this->createMock( WC_GoCardless_API_Client::class );

        $mock_client->expects( $this->once() )
            ->method( 'post' )
            ->with(
                '/billing_requests',
                $this->callback( function( $body ) {
                    return isset( $body['billing_requests']['mandate_request'] )
                        && ! isset( $body['billing_requests']['payment_request'] );
                } ),
                $this->anything()
            )
            ->willReturn( [
                'billing_requests' => [
                    'id' => 'BR789',
                    'status' => 'pending',
                ]
            ] );

        $api = new WC_GoCardless_API_Billing_Requests( $mock_client );
        $result = $api->create_for_mandate_only( [
            'scheme' => 'bacs',
            'wc_order_id' => '789',
            'idempotency_key' => 'key789',
        ] );

        $this->assertIsArray( $result );
    }

    /**
     * Test billing request flow creation.
     */
    public function test_create_flow() {
        $mock_client = $this->createMock( WC_GoCardless_API_Client::class );

        $mock_client->expects( $this->once() )
            ->method( 'post' )
            ->willReturn( [
                'billing_request_flows' => [
                    'id' => 'BRF123',
                    'authorisation_url' => 'https://pay.gocardless.com/flow/BRF123',
                ]
            ] );

        $api = new WC_GoCardless_API_Billing_Requests( $mock_client );
        $result = $api->create_flow( 'BR123', 'https://example.com/return', 'https://example.com/cancel' );

        $this->assertIsArray( $result );
        $this->assertStringContainsString( 'pay.gocardless.com', $result['billing_request_flows']['authorisation_url'] );
    }

    /**
     * Test billing request cancellation.
     */
    public function test_cancel_billing_request() {
        $mock_client = $this->createMock( WC_GoCardless_API_Client::class );

        $mock_client->expects( $this->once() )
            ->method( 'post' )
            ->willReturn( [
                'billing_requests' => [
                    'id' => 'BR123',
                    'status' => 'cancelled',
                ]
            ] );

        $api = new WC_GoCardless_API_Billing_Requests( $mock_client );
        $result = $api->cancel( 'BR123' );

        $this->assertIsArray( $result );
    }
}
