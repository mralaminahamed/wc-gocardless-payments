<?php
/**
 * Test case for API Exception.
 *
 * @package WC_GoCardless_Payments
 */

namespace WC_GoCardless_Payments\Tests\Api;

use PHPUnit\Framework\TestCase;

/**
 * API_Exception_Test.
 */
class API_Exception_Test extends TestCase {

    /**
     * Test exception can be instantiated with message.
     */
    public function test_exception_instantiation_with_message() {
        $exception = new WC_GoCardless_API_Exception( 'Test error message' );
        
        $this->assertInstanceOf( WC_GoCardless_API_Exception::class, $exception );
        $this->assertEquals( 'Test error message', $exception->getMessage() );
    }

    /**
     * Test exception can be instantiated with message and error type.
     */
    public function test_exception_with_error_type() {
        $exception = new WC_GoCardless_API_Exception( 'Payment failed', 'payment_failed' );
        
        $this->assertEquals( 'payment_failed', $exception->get_error_type() );
    }

    /**
     * Test exception extends RuntimeException.
     */
    public function test_exception_extends_runtime() {
        $exception = new WC_GoCardless_API_Exception( 'Test' );
        
        $this->assertInstanceOf( \RuntimeException::class, $exception );
    }

    /**
     * Test exception can store API response data.
     */
    public function test_exception_can_store_response() {
        $response = [
            'error' => [
                'message' => 'Invalid token',
                'code'   => 'token_invalid',
            ],
        ];
        
        $exception = new WC_GoCardless_API_Exception( 'API Error', 'api_error', 0, null, $response );
        
        $this->assertEquals( $response, $exception->get_response() );
    }
}
