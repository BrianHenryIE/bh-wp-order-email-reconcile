<?php
/**
 * Unit tests for the email parser.
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 * @author  BrianHenryIE <BrianHenryIE@gmail.com>
 */

namespace BrianHenryIE\WP_Order_Email_Reconcile\API;

use BrianHenryIE\WP_Mailboxes\Models\BH_Email_Fixture;
use BrianHenryIE\WP_Order_Email_Reconcile\Email_Extract_Settings_Interface;
use BrianHenryIE\WP_Mailboxes\API\Model\BH_Email;
use Mockery;
use Psr\Log\NullLogger;
use ZBateson\MailMimeParser\IMessage;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Order_Email_Reconcile\API\Email_Parser
 */
class Email_Parser_Unit_Test extends \Codeception\Test\Unit {

	protected function _tearDown() {
		Mockery::close();
		parent::_tearDown();
	}

	/**
	 * A pattern set with capture groups for amount, email, transaction id and a note-based order id.
	 *
	 * @return Email_Extract_Settings_Interface
	 */
	protected function make_pattern_set() {

		$pattern_set = Mockery::mock( Email_Extract_Settings_Interface::class );
		$pattern_set->shouldReceive( 'get_amount_regex' )->andReturn( '~\$(\d+\.\d{2})~' );
		$pattern_set->shouldReceive( 'get_customer_email_regex' )->andReturn( '~Email: (\S+@\S+)~' );
		$pattern_set->shouldReceive( 'get_customer_name_regex' )->andReturn( null );
		$pattern_set->shouldReceive( 'get_customer_id_regex' )->andReturn( null );
		$pattern_set->shouldReceive( 'get_transaction_id_regex' )->andReturn( '~Txn: (\w+)~' );
		$pattern_set->shouldReceive( 'get_transaction_url_regex' )->andReturn( null );
		$pattern_set->shouldReceive( 'get_notes_array_regex' )->andReturn( array( 'note' => '~(Order \d+)~' ) );

		return $pattern_set;
	}

	/**
	 * The parser extracts amount, email, transaction id and the order id from the note.
	 *
	 * @covers ::parse_email
	 * @covers ::parse_emails
	 */
	public function test_parses_payment_values_from_plain_text(): void {

		$body  = 'Payment of $12.34 received. Email: jane@example.org Txn: ABC123 Order 42';
		$email = BH_Email_Fixture::create( body_plain_text: $body, body_html: '' );

		$parser = new Email_Parser( array( $this->make_pattern_set() ), new NullLogger() );

		$parsed = $parser->parse_email( $email );

		$this->assertSame( '12.34', $parsed->get_amount() );
		$this->assertSame( 'jane@example.org', $parsed->get_customer_email() );
		$this->assertSame( 'ABC123', $parsed->get_transaction_id() );
		$this->assertSame( array( 42 ), $parsed->get_order_id_from_notes() );
		$this->assertSame( $email, $parsed->get_bh_email() );
	}

	/**
	 * An email with no matching content still yields a Parsed_Email with null values.
	 *
	 * @covers ::parse_email
	 */
	public function test_no_matches_returns_empty_parsed_email(): void {

		$body  = 'Nothing of interest here.';
		$email = BH_Email_Fixture::create( body_plain_text: $body, body_html: '' );

		$parser = new Email_Parser( array( $this->make_pattern_set() ), new NullLogger() );

		$parsed = $parser->parse_email( $email );

		$this->assertNull( $parsed->get_amount() );
		$this->assertSame( array(), $parsed->get_order_id_from_notes() );
	}

	/**
	 * parse_emails() returns one Parsed_Email per input email.
	 *
	 * @covers ::parse_emails
	 */
	public function test_parse_emails_returns_one_result_per_email(): void {

		$emails = array(
			BH_Email_Fixture::create( body_plain_text: 'Payment of $1.00. Email: a@example.org', body_html: '' ),
			BH_Email_Fixture::create( body_plain_text: 'Payment of $2.00. Email: b@example.org', body_html: '' ),
		);

		$parser = new Email_Parser( array( $this->make_pattern_set() ), new NullLogger() );

		$parsed = $parser->parse_emails( $emails );

		$this->assertCount( 2, $parsed );
		$this->assertSame( '1.00', $parsed[0]->get_amount() );
		$this->assertSame( '2.00', $parsed[1]->get_amount() );
	}
}
