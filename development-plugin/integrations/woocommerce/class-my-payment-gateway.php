<?php
/**
 * A minimal manual payment gateway whose orders are reconciled from payment emails.
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 */

namespace BrianHenryIE\WP_Order_Email_Reconcile_Test_Plugin\WooCommerce;

use BrianHenryIE\WP_Order_Email_Reconcile\Integrations\WooCommerce\Credentials_Settings_Fields;
use WC_Payment_Gateway;

/**
 * Demonstration gateway. Orders placed with it stay unpaid until a matching payment email arrives.
 */
class My_Payment_Gateway extends WC_Payment_Gateway {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'my-payment-gateway-id';
		$this->method_title       = 'My Payment Gateway';
		$this->method_description = 'Manual gateway reconciled from payment emails.';
		$this->title              = 'My Payment Gateway';
		$this->has_fields         = false;

		$this->init_form_fields();
		$this->init_settings();

		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			function (): void {
				$this->process_admin_options();
			}
		);
	}

	/**
	 * Enable/disable field, plus the library's mailbox configuration fields.
	 *
	 * @return void
	 */
	public function init_form_fields(): void {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => 'Enable/Disable',
				'type'    => 'checkbox',
				'label'   => 'Enable My Payment Gateway',
				'default' => 'yes',
			),
		);

		// "Configure mailbox" button (opens the email accounts modal) and the after-reconcile action.
		$this->form_fields = ( new Credentials_Settings_Fields() )->append_imap_reconcile_fields( $this->form_fields );
	}
}
