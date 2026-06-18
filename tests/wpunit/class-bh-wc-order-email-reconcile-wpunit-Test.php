<?php

namespace BrianHenryIE\WP_Order_Email_Reconcile;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Order_Email_Reconcile\BH_WP_Order_Email_Reconcile
 */
class BH_WP_Order_Email_Reconcile_WPUnit_Test extends \Codeception\TestCase\WPTestCase {

	/**
	 * @covers ::instance
	 */
	public function test_singleton() {

		$settings = $this->makeEmpty( Email_Reconcile_Settings_Interface::class );

		$sut = BH_WP_Order_Email_Reconcile::instance( $settings );
	}

}
