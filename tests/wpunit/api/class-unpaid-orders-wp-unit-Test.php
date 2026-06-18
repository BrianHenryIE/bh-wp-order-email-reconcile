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

/**
 * @covers \BrianHenryIE\WP_Order_Email_Reconcile\API\WC_Unpaid_Orders
 */
class Unpaid_Orders_WP_Unit_Test extends \Codeception\TestCase\WPTestCase {

    /**
     * @covers \BrianHenryIE\WP_Order_Email_Reconcile\API\WC_Unpaid_Orders::get_unpaid_orders
     */
    public function test_unpaid_orders() {

        $logger = new ColorLogger();
        $settings = $this->makeEmpty(Email_Reconcile_Settings_Interface::class,
            array(
                'get_payment_method_ids' => array( 'my-gateway-id', 'my-gateway-id-2' )
            )
        );

        $sut = new WC_Unpaid_Orders( $settings, $logger );

        $o1 = new \WC_Order();
        $o1->set_status('on-hold');
        $o1->save();

        $o2 = new \WC_Order();
        $o2->set_status('on-hold');
        $o2->set_payment_method('my-gateway-id');
        $o2->save();

        $o3 = new \WC_Order();
        $o3->set_status('pending');
        $o3->set_payment_method('my-gateway-id-2');
        $o3->save();

        $o4 = new \WC_Order();
        $o4->set_status('completed');
        $o4->set_payment_method('my-gateway-id');
        $o4->save();

        $o5 = new \WC_Order();
        $o5->set_status('failed');
        $o5->set_payment_method('my-gateway-id');
        $o5->save();

        $result = $sut->get_unpaid_orders();

        $this->assertCount( 2, $result );
    }

}
