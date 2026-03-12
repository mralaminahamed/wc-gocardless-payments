<?php
/**
 * Test case for Subscriptions.
 *
 * @package WC_GoCardless_Payments
 */

namespace WC_GoCardless_Payments\Tests\Subscriptions;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use WC_GoCardless\Subscriptions\Subscriptions;

/**
 * Subscriptions_Test.
 */
class Subscriptions_Test extends TestCase {

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
     * Test subscriptions class is instantiated correctly.
     */
    public function test_subscriptions_instantiation() {
        $subscriptions = new Subscriptions();
        $this->assertInstanceOf( Subscriptions::class, $subscriptions );
    }

    /**
     * Test subscription payment processes correctly.
     */
    public function test_subscription_payment_processes() {
        $order_id = 123;
        $amount   = 1000;

        Monkey\Functions::expect( 'get_post_meta' )
            ->with( $order_id, '_gocardless_subscription_id', true )
            ->andReturn( 'sub_123' );

        Monkey\Functions::expect( 'wp_remote_post' )
            ->once()
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

        $subscriptions = new Subscriptions();
        $result        = $subscriptions->process_subscription_payment( $order_id, $amount );

        $this->assertTrue( $result );
    }

    /**
     * Test failed subscription payment is handled.
     */
    public function test_failed_subscription_payment() {
        $order_id = 123;

        Monkey\Functions::expect( 'get_post_meta' )
            ->with( $order_id, '_gocardless_subscription_id', true )
            ->andReturn( 'sub_123' );

        Monkey\Functions::expect( 'wp_remote_post' )
            ->once()
            ->andReturn(
                [
                    'body'     => json_encode(
                        [
                            'error' => [
                                'message' => 'Mandate inactive',
                            ],
                        ]
                    ),
                    'response' => [ 'code' => 422 ],
                ]
            );

        $subscriptions = new Subscriptions();
        $result        = $subscriptions->process_subscription_payment( $order_id, 1000 );

        $this->assertFalse( $result );
    }

    /**
     * Test subscription cancellation syncs with GoCardless.
     */
    public function test_cancel_subscription() {
        $order_id = 123;

        Monkey\Functions::expect( 'get_post_meta' )
            ->with( $order_id, '_gocardless_subscription_id', true )
            ->andReturn( 'sub_123' );

        Monkey\Functions::expect( 'wp_remote_post' )
            ->once()
            ->andReturn(
                [
                    'body'     => json_encode(
                        [
                            'subscriptions' => [
                                [
                                    'id'     => 'sub_123',
                                    'status' => 'cancelled',
                                ],
                            ],
                        ]
                    ),
                    'response' => [ 'code' => 200 ],
                ]
            );

        $subscriptions = new Subscriptions();
        $result        = $subscriptions->cancel_subscription( $order_id );

        $this->assertTrue( $result );
    }

    /**
     * Test subscription status check.
     */
    public function test_check_subscription_status() {
        Monkey\Functions::expect( 'wp_remote_get' )
            ->once()
            ->andReturn(
                [
                    'body'     => json_encode(
                        [
                            'subscriptions' => [
                                [
                                    'id'          => 'sub_123',
                                    'status'      => 'active',
                                    'next_payment_date' => '2024-01-01',
                                ],
                            ],
                        ]
                    ),
                    'response' => [ 'code' => 200 ],
                ]
            );

        $subscriptions = new Subscriptions();
        $status        = $subscriptions->get_subscription_status( 'sub_123' );

        $this->assertEquals( 'active', $status );
    }
}
