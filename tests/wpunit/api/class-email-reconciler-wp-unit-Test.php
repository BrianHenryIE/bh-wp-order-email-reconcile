<?php
/**
 *
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 * @author  BrianHenryIE <BrianHenryIE@gmail.com>
 */

namespace BrianHenryIE\WP_Order_Email_Reconcile\API;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Order_Email_Reconcile\Email_Reconcile_Settings_Interface;
use BrianHenryIE\WP_Order_Email_Reconcile\API\Model\Email;
use BrianHenryIE\WP_Order_Email_Reconcile\API\Model\Parsed_Email;
use Codeception\Stub\Expected;
use Exception;
use Psr\Log\NullLogger;
use WC_Order;

/**
 * @coversDefaultClass  \BrianHenryIE\WP_Order_Email_Reconcile\API\Email_Reconciler
 */
class Email_Reconciler_WP_Unit_Test extends \Codeception\TestCase\WPTestCase {

    /**
     * If we ask for their $CashTag, Venmo username etc at checkout, we can
     * use that to match.
     *
     * @throws Exception
     */
    public function test_match_by_customer_payment_id(): void {

        $logger = new ColorLogger();
        $settings = $this->makeEmpty(Email_Reconcile_Settings_Interface::class, array(
            'get_customer_payment_id_meta_key' => Expected::once( function() { return 'cashapp_username'; } ),
        ));

        $sut = new Email_Reconciler( $settings, $logger );

        $orders = array();

        $order = $this->make( WC_Order::class, array(
            'get_meta' => Expected::once( function() { return 'my_cashtag'; } ),
            'get_billing_email' => Expected::atLeastOnce( function() { return 'customer@example.org'; } ),
            'get_billing_first_name' => Expected::once( function() { return 'firstname'; } ),
            'get_billing_last_name' => Expected::once( function() { return 'lastname'; } ),
            'get_id' => Expected::atLeastOnce( function() { return '123'; } ),
            'is_paid' => Expected::once( function() { return false; } ),
            'get_total' => Expected::once( function() { return '99.99'; } ),
            'payment_complete' => Expected::once()
        ));

        $orders[] = $order;

        $sut->index_orders( $orders );

        $parsed_email = $this->make( Parsed_Email::class, array(
            'get_customer_id' => 'my_cashtag',
            'get_amount' => '99.99',
            'get_notes' => array(),
            'get_transaction_id' => 'transaction_id_axby',
            'get_transaction_url' => null,

        ));

        // If after_reconcile() is called, then it was successful.
        $result = $sut->reconcile_email( $parsed_email );

		$this->assertTrue( $result['reconciled']);

    }

	/**
	 * @covers ::reconcile_emails
	 */
	public function test_email_reconciler_returns_stats(): void {

		$settings = $this->makeEmpty(Email_Reconcile_Settings_Interface::class, array(
			'get_customer_payment_id_meta_key' => 'cashapp_username'
		));
		$logger = new NullLogger();

		$sut = new Email_Reconciler( $settings, $logger );

		$orders = array();

		$order = $this->make( WC_Order::class, array(
			'get_meta' => 'my_cashtag',
			'get_billing_email' => 'customer@example.org',
			'get_billing_first_name' => 'firstname',
			'get_billing_last_name' => 'lastname',
			'get_id' => 123,
			'is_paid' => false,
			'get_total' => '99.99',
			'payment_complete' => Expected::once()

		));

		$orders[] = $order;

		$sut->index_orders( $orders );

		$parsed_email = $this->make( Parsed_Email::class, array(
			'get_customer_id' => 'my_cashtag',
			'get_amount' => '99.99',
			'get_notes' => array(),
			'get_transaction_id' => 'transaction_id_axby',
			'get_transaction_url' => null,
		));

		$result = $sut->reconcile_emails( array( $parsed_email ) );

		$this->assertArrayHasKey( 'reconciled_emails', $result );
		$this->assertCount( 1, $result['reconciled_emails'] );
		// Check the order id is in the key.
		$this->assertArrayHasKey( 123, $result['reconciled_emails'] );

	}
}
