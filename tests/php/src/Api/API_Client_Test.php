<?php
/**
 * Test case for API Client.
 *
 * @package WC_GoCardless_Payments
 */

namespace WC_GoCardless_Payments\Tests\Api;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use WC_GoCardless_API_Client;

/**
 * API_Client_Test.
 */
class API_Client_Test extends TestCase {

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
     * Test client is instantiated correctly.
     */
    public function test_client_instantiation() {
        $client = new WC_GoCardless_API_Client();
        $this->assertInstanceOf( WC_GoCardless_API_Client::class, $client );
    }

    /**
     * Test get method returns array on success.
     */
    public function test_get_returns_array_on_success() {
        Monkey\Functions::expect( 'get_option' )
            ->once()
            ->andReturn( [] );

        Monkey\Functions::expect( 'wp_remote_get' )
            ->once()
            ->with(
                'https://api.gocardless.com/payments/payment_xxx',
                Mockery::any()
            )
            ->andReturn(
                [
                    'body'     => json_encode(
                        [
                            'payments' => [
                                [
                                    'id'     => 'payment_xxx',
                                    'status' => 'confirmed',
                                ],
                            ],
                        ]
                    ),
                    'response' => [ 'code' => 200 ],
                ]
            );

        $client = new WC_GoCardless_API_Client();
        $result = $client->get( 'payments/payment_xxx' );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'payments', $result );
    }

    /**
     * Test post method returns array on success.
     */
    public function test_post_returns_array_on_success() {
        Monkey\Functions::expect( 'get_option' )
            ->once()
            ->andReturn( [] );

        Monkey\Functions::expect( 'wp_remote_post' )
            ->once()
            ->with(
                'https://api.gocardless.com/payments',
                Mockery::any()
            )
            ->andReturn(
                [
                    'body'     => json_encode(
                        [
                            'payments' => [
                                [
                                    'id'     => 'payment_new',
                                    'status' => 'pending',
                                ],
                            ],
                        ]
                    ),
                    'response' => [ 'code' => 201 ],
                ]
            );

        $client = new WC_GoCardless_API_Client();
        $result = $client->post( 'payments', [ 'payments' => [ 'amount' => 1000 ] ] );

        $this->assertIsArray( $result );
    }

    /**
     * Test API throws exception on error response.
     */
    public function test_throws_exception_on_api_error() {
        Monkey\Functions::expect( 'get_option' )
            ->once()
            ->andReturn( [] );

        Monkey\Functions::expect( 'wp_remote_get' )
            ->once()
            ->andReturn(
                [
                    'body'     => json_encode(
                        [
                            'error' => [
                                'message' => 'Resource not found',
                            ],
                        ]
                    ),
                    'response' => [ 'code' => 404 ],
                ]
            );

        $this->expectException( WC_GoCardless_API_Exception::class );

        $client = new WC_GoCardless_API_Client();
        $client->get( 'invalid/resource' );
    }

    /**
     * Test API throws exception on connection error.
     */
    public function test_throws_exception_on_connection_error() {
        Monkey\Functions::expect( 'get_option' )
            ->once()
            ->andReturn( [] );

        Monkey\Functions::expect( 'wp_remote_get' )
            ->once()
            ->andReturn( new \WP_Error( 'http_error', 'Connection timed out' ) );

        $this->expectException( WC_GoCardless_API_Exception::class );

        $client = new WC_GoCardless_API_Client();
        $client->get( 'payments' );
    }

    /**
     * Test headers include idempotency key for POST requests.
     */
    public function test_post_includes_idempotency_key() {
        Monkey\Functions::expect( 'get_option' )
            ->once()
            ->andReturn( [] );

        Monkey\Functions::expect( 'wp_remote_post' )
            ->once()
            ->with(
                Mockery::any(),
                Mockery::on(
                    function ( $args ) {
                        return isset( $args['headers']['Idempotency-Key'] );
                    }
                )
            )
            ->andReturn(
                [
                    'body'     => json_encode( [ 'payments' => [] ] ),
                    'response' => [ 'code' => 201 ],
                ]
            );

        $client = new WC_GoCardless_API_Client();
        $client->post( 'payments', [] );
    }

    /**
     * Test API uses correct base URL.
     */
    public function test_uses_correct_base_url() {
        Monkey\Functions::expect( 'get_option' )
            ->twice()
            ->andReturn( [] );

        $client = new WC_GoCardless_API_Client();

        // Test production URL
        $this->assertStringContainsString( 'api.gocardless.com', $client->get_api_url() );
    }

    /**
     * Test is_sandbox returns false by default.
     */
    public function test_is_sandbox_returns_false_by_default() {
        Monkey\Functions::expect( 'get_option' )
            ->once()
            ->andReturn( [] );

        $client = new WC_GoCardless_API_Client();
        $this->assertFalse( $client->is_sandbox() );
    }
}
