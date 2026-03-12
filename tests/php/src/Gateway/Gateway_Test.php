<?php
/**
 * Test case for Gateway.
 *
 * @package WC_GoCardless_Payments
 */

namespace WC_GoCardless_Payments\Tests\Gateway;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use WC_GoCardless\Gateway\Gateway;

/**
 * Gateway_Test.
 */
class Gateway_Test extends TestCase {

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
     * Test gateway is instantiated correctly.
     */
    public function test_gateway_instantiation() {
        $gateway = new Gateway();
        $this->assertInstanceOf( Gateway::class, $gateway );
    }

    /**
     * Test gateway has correct ID.
     */
    public function test_gateway_has_correct_id() {
        $gateway = new Gateway();
        $this->assertEquals( 'wc_gocardless', $gateway->id );
    }

    /**
     * Test gateway has payment methods.
     */
    public function test_gateway_has_payment_methods() {
        $gateway = new Gateway();
        $this->assertTrue( $gateway->has_fields );
    }

    /**
     * Test process payment returns array.
     */
    public function test_process_payment_returns_array() {
        $order = $this->create_mock_order();

        Monkey\Functions::expect( 'WC' )->andReturn( true );

        $gateway = new Gateway();
        $result  = $gateway->process_payment( $order->get_id() );

        $this->assertIsArray( $result );
    }

    /**
     * Test refund processes correctly.
     */
    public function test_refund_processes_correctly() {
        $order = $this->create_mock_order();

        Monkey\Functions::expect( 'wp_remote_post' )
            ->once()
            ->andReturn(
                [
                    'body'     => json_encode(
                        [
                            'refunds' => [
                                [
                                    'id'     => 'RF123',
                                    'status' => 'submitted',
                                ],
                            ],
                        ]
                    ),
                    'response' => [ 'code' => 201 ],
                ]
            );

        $gateway = new Gateway();
        $result  = $gateway->process_refund( $order->get_id(), 1000, 'Test refund' );

        $this->assertTrue( $result );
    }

    /**
     * Test validate payment fields.
     */
    public function test_validate_payment_fields() {
        $gateway = new Gateway();

        $result = $gateway->validate_fields();
        $this->assertTrue( $result );
    }

    /**
     * Create mock order object.
     *
     * @return object
     */
    private function create_mock_order() {
        return new class {
            public function get_id() {
                return 123;
            }
            public function get_total() {
                return 100.00;
            }
            public function get_currency() {
                return 'GBP';
            }
            public function get_payment_method() {
                return 'wc_gocardless';
            }
            public function get_payment_method_title() {
                return 'Direct Debit';
            }
            public function get_billing_email() {
                return 'test@example.com';
            }
        };
    }
}
