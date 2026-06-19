<?php
/**
 * Tests the BH_WP_Order_Email_Reconcile::make()/instance() wiring.
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 */

namespace BrianHenryIE\WP_Order_Email_Reconcile;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Order_Email_Reconcile\BH_WP_Order_Email_Reconcile
 */
class BH_WP_Order_Email_Reconcile_WPUnit_Test extends \Codeception\TestCase\WPTestCase {

	/**
	 * A settings double with distinct, valid CPT slugs so bh-wp-mailboxes boots cleanly.
	 *
	 * @return Email_Reconcile_Settings_Interface
	 */
	protected function make_settings() {
		return $this->makeEmpty(
			Email_Reconcile_Settings_Interface::class,
			array(
				'get_plugin_slug'                       => 'test-plugin',
				'get_emails_cpt_friendly_name'          => 'Test Payment Emails',
				'get_email_accounts_cpt_friendly_name'  => 'Test Email Accounts',
				'get_emails_cpt_underscored_20'         => 'test_payment_emails',
				'get_email_accounts_cpt_underscored_20' => 'test_email_accounts',
				'get_emails_cpt_dashed'                 => 'test-payment-emails',
				'get_email_accounts_cpt_dashed'         => 'test-email-accounts',
				'get_private_uploads_directory_name'    => null,
				'get_cron_schedules'                    => array(),
				'get_patterns'                          => array(),
				'get_payment_method_ids'                => array(),
				'get_customer_payment_id_meta_key'      => null,
			)
		);
	}

	/**
	 * make()/instance() boots the library and hooks the reconcile action.
	 *
	 * @covers ::make
	 * @covers ::instance
	 */
	public function test_make_registers_reconcile_hook(): void {

		$settings = $this->make_settings();

		$sut = BH_WP_Order_Email_Reconcile::instance( $settings );

		$this->assertInstanceOf( BH_WP_Order_Email_Reconcile::class, $sut );

		$this->assertNotFalse(
			has_action( 'bh_wp_mailboxes_fetch_emails_saved_test-plugin' ),
			'The reconcile action should be hooked for the plugin slug.'
		);
	}
}
