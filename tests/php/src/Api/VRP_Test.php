<?php
/**
 * Test case for VRP API.
 *
 * @package WC_GoCardless_Payments
 */

namespace WC_GoCardless_Payments\Tests\Api;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use WC_GoCardless_API_Client;
use WC_GoCardless_API_VRP;

/**
 * VRP_Test.
 */
class VRP_Test extends TestCase {

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
     * Test VRP creates consent billing request with constraints.
     */
    public function test_create_consent_billing_request() {
        $mock_client = $this->createMock( WC_GoCardless_API_Client::class );

        $mock_client->expects( $this->once() )
            ->method( 'post' )
            ->willReturn( [
                'billing_requests' => [
                    'id' => 'BR123',
                    'status' => 'pending',
                ]
            ] );

        $api = new WC_GoCardless_API_VRP( $mock_client );
        $result = $api->create_consent_billing_request( [
            'max_amount_per_payment' => 50000,
            'currency' => 'GBP',
            'periodic_limits' => [
                [ 'period' => 'month', 'max_total_amount' => 200000 ],
            ],
            'wc_order_id' => '123',
            'idempotency_key' => 'key123',
        ] );

        $this->assertIsArray( $result );
    }

    /**
     * Test VRP creates payment against mandate.
     */
    public function test_create_payment() {
        $mock_client = $this->createMock( WC_GoCardless_API_Client::class );

        $mock_client->expects( $this->once() )
            ->method( 'post' )
            ->willReturn( [
                'payments' => [
                    'id' => 'PM123',
                    'status' => 'pending',
                ]
            ] );

        $api = new WC_GoCardless_API_VRP( $mock_client );
        $result = $api->create_payment( 'MD123', 5000, 'GBP', 'VRP payment' );

        $this->assertIsArray( $result );
    }

    /**
     * Test VRP gets consent (mandate).
     */
    public function test_get_consent() {
        $mock_client = $this->createMock( WC_GoCardless_API_Client::class );

        $mock_client->expects( $this->once() )
            ->method( 'get' )
            ->willReturn( [
                'mandates' => [
                    'id' => 'MD123',
                    'status' => 'active',
                ]
            ] );

        $api = new WC_GoCardless_API_VRP( $mock_client );
        $result = $api->get_consent( 'MD123' );

        $this->assertIsArray( $result );
    }

    /**
     * Test VRP cancels consent.
     */
    public function test_cancel_consent() {
        $mock_client = $this->createMock( WC_GoCardless_API_Client::class );

        $mock_client->expects( $this->once() )
            ->method( 'post' )
            ->willReturn( [
                'mandates' => [
                    'id' => 'MD123',
                    'status' => 'cancelled',
                ]
            ] );

        $api = new WC_GoCardless_API_VRP( $mock_client );
        $result = $api->cancel_consent( 'MD123', 'Customer requested' );

        $this->assertIsArray( $result );
    }

    /**
     * Test VRP checks if consent is active.
     */
    public function test_is_consent_active() {
        $mock_client = $this->createMock( WC_GoCardless_API_Client::class );

        $api = new WC_GoCardless_API_VRP( $mock_client );

        // Active VRP mandate should return true
        $active_mandate = [
            'mandates' => [
                'id' => 'MD123',
                'status' => 'active',
                'scheme' => 'faster_payments',
            ]
        ];
        $this->assertTrue( $api->is_consent_active( $active_mandate ) );

        // Inactive mandate should return false
        $inactive_mandate = [
            'mandates' => [
                'id' => 'MD456',
                'status' => 'cancelled',
                'scheme' => 'faster_payments',
            ]
        ];
        $this->assertFalse( $api->is_consent_active( $inactive_mandate ) );
    }
}
