<?php

namespace BrianHenryIE\WC_Order_Email_Reconcile\API;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WC_Order_Email_Reconcile\Email_Reconcile_Settings_Interface;
use BrianHenryIE\WP_Mailboxes\BH_Email;
use BrianHenryIE\WP_Mailboxes\Mailbox_Settings_Interface;
use BrianHenryIE\WP_Mailboxes\BH_WP_Mailboxes_Settings_Interface;

/**
 * @coversDefaultClass \BrianHenryIE\WC_Order_Email_Reconcile\API\API
 */
class API_WPUnit_Test extends \Codeception\TestCase\WPTestCase {

	/**
	 * @covers ::process_new_emails
	 */
	public function test_process_emails_no_unpaid_orders(): void {

		$settings = $this->makeEmpty( Email_Reconcile_Settings_Interface::class );
		$logger = new ColorLogger();

		$sut = new API( $settings, $logger );

		$emails = array(
			$this->makeEmpty( BH_Email::class ),
		);
		$mailboxes = $this->makeEmpty( BH_WP_Mailboxes_Settings_Interface::class );
		$account = $this->makeEmpty( Mailbox_Settings_Interface::class );

		$result = $sut->process_new_emails( $emails, $mailboxes, $account );
	}
}