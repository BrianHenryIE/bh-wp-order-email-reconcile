<?php

namespace BrianHenryIE\WC_Order_Email_Reconcile\API;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WC_Order_Email_Reconcile\Email_Reconcile_Settings_Interface;
use BrianHenryIE\WP_Mailboxes\BH_Email;
use BrianHenryIE\WP_Mailboxes\Mailbox_Settings_Interface;
use BrianHenryIE\WP_Mailboxes\BH_WP_Mailboxes_Settings_Interface;
use Codeception\Stub;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \BrianHenryIE\WC_Order_Email_Reconcile\API\API
 */
class API_WPUnit_Test extends \Codeception\TestCase\WPTestCase {

	/**
	 * @covers ::process_new_emails
	 */
	public function test_process_emails_no_unpaid_orders(): void {

		$container = new class() implements ContainerInterface {

			public function get( $id ) {

				$logger = new ColorLogger();

				switch($id){
					case LoggerInterface::class:
						return $logger;
					case Unpaid_Orders::class:
						return Stub::makeEmpty( Unpaid_Orders::class,
						array(
							'get_unpaid_orders' => Stub\Expected::once(function(){
								return array();
							})
						));
					default:
						return Stub::makeEmpty( $id );

				}

			}

			public function has( $id ): bool {
				return true;
			}
		};

		$sut = new API( $container );

		$emails = array(
			$this->makeEmpty( BH_Email::class ),
		);
		$mailboxes = $this->makeEmpty( BH_WP_Mailboxes_Settings_Interface::class );
		$account = $this->makeEmpty( Mailbox_Settings_Interface::class );

		$result = $sut->process_new_emails( $emails, $mailboxes, $account );

		$this->assertEquals( 0, $result['num_unpaid_orders'] );
	}
}