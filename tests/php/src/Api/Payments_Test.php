<?php
/**
 * Test case for Payments API.
 *
 * @package WC_GoCardless_Payments
 */

namespace WC_GoCardless_Payments\Tests\Api;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;

/**
 * Payments_Test.
 */
class Payments_Test extends TestCase {

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
     * Test payments API creates payment.
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

        $api = new WC_GoCardless_API_Payments( $mock_client );
        $result = $api->create( 'MD123', 1000, 'GBP', 'Test payment' );

        $this->assertIsArray( $result );
        $this->assertEquals( 'PM123', $result['payments']['id'] );
    }

    /**
     * Test payments API gets payment.
     */
    public function test_get_payment() {
        $mock_client = $this->createMock( WC_GoCardless_API_Client::class );
        
        $mock_client->expects( $this->once() )
            ->method( 'get' )
            ->willReturn( [
                'payments' => [
                    'id' => 'PM123',
                    'status' => 'confirmed',
                ]
            ] );

        $api = new WC_GoCardless_API_Payments( $mock_client );
        $result = $api->get( 'PM123' );

        $this->assertIsArray( $result );
    }

    /**
     * Test payments API creates refund.
     */
    public function test_create_refund() {
        $mock_client = $this->createMock( WC_GoCardless_API_Client::class );
        
        $mock_client->expects( $this->once() )
            ->method( 'post' )
            ->willReturn( [
                'refunds' => [
                    'id' => 'RF123',
                    'status' => 'paid',
                ]
            ] );

        $api = new WC_GoCardless_API_Payments( $mock_client );
        $result = $api->create_refund( 'PM123', 500, 'GBP', 'Test refund' );

        $this->assertIsArray( $result );
    }

    /**
     * Test payments API cancels payment.
     */
    public function test_cancel_payment() {
        $mock_client = $this->createMock( WC_GoCardless_API_Client::class );
        
        $mock_client->expects( $this->once() )
            ->method( 'post' )
            ->willReturn( [
                'payments' => [
                    'id' => 'PM123',
                    'status' => 'cancelled',
                ]
            ] );

        $api = new WC_GoCardless_API_Payments( $mock_client );
        $result = $api->cancel( 'PM123' );

        $this->assertIsArray( $result );
    }
}
