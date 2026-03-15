<?php
/**
 * Test case for API Mandates.
 *
 * @package WC_GoCardless_Payments
 */

namespace WC_GoCardless_Payments\Tests\Api;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;

/**
 * API_Mandates_Test.
 */
class API_Mandates_Test extends TestCase {

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
     * Test mandates API can get mandate.
     */
    public function test_get_mandate() {
        $mock_client = $this->createMock( WC_GoCardless_API_Client::class );
        
        $mock_client->expects( $this->once() )
            ->method( 'get' )
            ->willReturn( [
                'mandates' => [
                    'id' => 'MD123',
                    'status' => 'active',
                ]
            ] );

        $api = new WC_GoCardless_API_Mandates( $mock_client );
        $result = $api->get( 'MD123' );

        $this->assertEquals( 'MD123', $result['mandates']['id'] );
    }

    /**
     * Test mandates API can list mandates.
     */
    public function test_list_mandates() {
        $mock_client = $this->createMock( WC_GoCardless_API_Client::class );
        
        $mock_client->expects( $this->once() )
            ->method( 'get' )
            ->willReturn( [
                'mandates' => [
                    [ 'id' => 'MD123', 'status' => 'active' ],
                    [ 'id' => 'MD456', 'status' => 'pending' ],
                ]
            ] );

        $api = new WC_GoCardless_API_Mandates( $mock_client );
        $result = $api->list( [ 'customer' => 'CU123' ] );

        $this->assertCount( 2, $result['mandates'] );
    }

    /**
     * Test mandates API can cancel mandate.
     */
    public function test_cancel_mandate() {
        $mock_client = $this->createMock( WC_GoCardless_API_Client::class );
        
        $mock_client->expects( $this->once() )
            ->method( 'post' )
            ->willReturn( [
                'mandates' => [
                    'id' => 'MD123',
                    'status' => 'cancelled',
                ]
            ] );

        $api = new WC_GoCardless_API_Mandates( $mock_client );
        $result = $api->cancel( 'MD123' );

        $this->assertEquals( 'cancelled', $result['mandates']['status'] );
    }

    /**
     * Test mandates API can reinstate mandate.
     */
    public function test_reinstate_mandate() {
        $mock_client = $this->createMock( WC_GoCardless_API_Client::class );
        
        $mock_client->expects( $this->once() )
            ->method( 'post' )
            ->willReturn( [
                'mandates' => [
                    'id' => 'MD123',
                    'status' => 'active',
                ]
            ] );

        $api = new WC_GoCardless_API_Mandates( $mock_client );
        $result = $api->reinstate( 'MD123' );

        $this->assertEquals( 'active', $result['mandates']['status'] );
    }
}
