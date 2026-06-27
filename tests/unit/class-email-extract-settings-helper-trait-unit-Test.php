<?php

namespace BrianHenryIE\WP_Order_Email_Reconcile;

class Email_Extract_Settings_Helper_Trait_Unit_Test extends \Codeception\Test\Unit {

	public function test_null_values(): void {

		$sut = new class() {
			use Email_Extract_Settings_Helper_Trait;
		};

		$this->assertNull( $sut->get_customer_email_regex() );
		$this->assertNull( $sut->get_customer_id_regex() );
		$this->assertNull( $sut->get_order_id_regex() );
		$this->assertNull( $sut->get_customer_name_regex() );
		$this->assertEmpty( $sut->get_notes_array_regex() );
		$this->assertNull( $sut->get_transaction_id_regex() );
		$this->assertNull( $sut->get_transaction_url_regex() );
	}
}
