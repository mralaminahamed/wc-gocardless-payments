<?php
/**
 * Test case for API Customers.
 *
 * @package WC_GoCardless_Payments
 */

namespace WC_GoCardless_Payments\Tests\Api;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;

/**
 * API_Customers_Test.
 */
class API_Customers_Test extends TestCase {

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
     * Test customers API can create customer.
     */
    public function test_create_customer() {
        $mock_client = $this->createMock( WC_GoCardless_API_Client::class );
        
        $mock_client->expects( $this->once() )
            ->method( 'post' )
            ->willReturn( [
                'customers' => [
                    'id' => 'CU123',
                    'email' => 'test@example.com',
                ]
            ] );

        $api = new WC_GoCardless_API_Customers( $mock_client );
        $result = $api->create( [
            'email' => 'test@example.com',
            'given_name' => 'John',
            'family_name' => 'Doe',
        ] );

        $this->assertEquals( 'CU123', $result['customers']['id'] );
    }

    /**
     * Test customers API can get customer.
     */
    public function test_get_customer() {
        $mock_client = $this->createMock( WC_GoCardless_API_Client::class );
        
        $mock_client->expects( $this->once() )
            ->method( 'get' )
            ->willReturn( [
                'customers' => [
                    'id' => 'CU123',
                    'email' => 'test@example.com',
                ]
            ] );

        $api = new WC_GoCardless_API_Customers( $mock_client );
        $result = $api->get( 'CU123' );

        $this->assertEquals( 'CU123', $result['customers']['id'] );
    }

    /**
     * Test customers API can update customer.
     */
    public function test_update_customer() {
        $mock_client = $this->createMock( WC_GoCardless_API_Client::class );
        
        $mock_client->expects( $this->once() )
            ->method( 'put' )
            ->willReturn( [
                'customers' => [
                    'id' => 'CU123',
                    'email' => 'newemail@example.com',
                ]
            ] );

        $api = new WC_GoCardless_API_Customers( $mock_client );
        $result = $api->update( 'CU123', [ 'email' => 'newemail@example.com' ] );

        $this->assertEquals( 'newemail@example.com', $result['customers']['email'] );
    }
}
