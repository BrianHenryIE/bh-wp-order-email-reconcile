<?php
/**
 * Unit tests for the development plugin's "Check emails" notice.
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 * @author  BrianHenryIE <BrianHenryIE@gmail.com>
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Order_Email_Reconcile_Test_Plugin\Admin;

use Mockery;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use WP_Mock;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Order_Email_Reconcile_Test_Plugin\Admin\Check_Emails_Notice
 */
class Check_Emails_Notice_Unit_Test extends \Codeception\Test\Unit {

	protected function _before() {
		WP_Mock::setUp();
		WP_Mock::userFunction( 'add_action' );
		WP_Mock::passthruFunction( 'esc_html' );
		WP_Mock::passthruFunction( 'esc_url' );
		WP_Mock::passthruFunction( '__' );
		WP_Mock::userFunction( '_n' )->andReturnUsing( fn( $single, $plural, $number ) => 1 === $number ? $single : $plural );
	}

	protected function _tearDown() {
		WP_Mock::tearDown();
		Mockery::close();
		parent::_tearDown();
	}

	protected function make_sut( ?LoggerInterface $logger = null ): Check_Emails_Notice {
		return new Check_Emails_Notice( $logger ?? new NullLogger() );
	}

	/**
	 * When an email reconciled the order: a green notice linking to the email, with the counts.
	 *
	 * @covers ::render
	 */
	public function test_success_notice_links_to_the_matched_email(): void {
		WP_Mock::userFunction( 'get_edit_post_link' )->with( 42, 'raw' )->andReturn( 'https://example.org/wp-admin/post.php?post=42&action=edit' );

		$html = $this->make_sut()->render(
			array(
				'new_emails'        => 3,
				'reconciled_emails' => 2,
				'matched_orders'    => 2,
				'matched_email_id'  => 42,
				'failed_accounts'   => 0,
			)
		);

		$this->assertStringContainsString( 'notice-success', $html );
		$this->assertStringContainsString( '<a href="https://example.org/wp-admin/post.php?post=42&action=edit">email #42</a>', $html );
		$this->assertStringContainsString( '3 new emails downloaded, 2 reconciled.', $html );
	}

	/**
	 * When nothing matched this order: a blue notice with the new-email count, that none matched,
	 * how many orders were reconciled, and any accounts that could not be checked.
	 *
	 * @covers ::render
	 */
	public function test_info_notice_when_no_email_matched_this_order(): void {
		$html = $this->make_sut()->render(
			array(
				'new_emails'        => 1,
				'reconciled_emails' => 1,
				'matched_orders'    => 1,
				'matched_email_id'  => 0,
				'failed_accounts'   => 2,
			)
		);

		$this->assertStringContainsString( 'notice-info', $html );
		$this->assertStringContainsString( '1 new email downloaded. No emails matched this order; 1 order was reconciled.', $html );
		$this->assertStringContainsString( '2 accounts could not be checked.', $html, 'A NullLogger has no logs to point to.' );
		$this->assertStringNotContainsString( 'see the logs', $html );
		$this->assertStringNotContainsString( '<a ', $html );
	}

	/**
	 * With a real logger, failed accounts point the user to the logs.
	 *
	 * @covers ::render
	 */
	public function test_failed_accounts_point_to_the_logs_when_there_is_a_logger(): void {
		$html = $this->make_sut( Mockery::mock( LoggerInterface::class ) )->render(
			array(
				'new_emails'      => 0,
				'failed_accounts' => 1,
			)
		);

		$this->assertStringContainsString( '1 account could not be checked; see the logs.', $html );
	}
}
