<?php
/**
 * WooCommerce Settings API fields for the email account credentials.
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Order_Email_Reconcile\Integrations\WooCommerce;

/**
 * Adds username/password/server fields to a WooCommerce Settings API screen.
 */
class Credentials_Settings_Fields {

	/**
	 * WooCommerce Settings API fields for username, password, server...
	 *
	 * Password auto-fill is disabled because saved passwords were being overwritten and inadvertently saved.
	 *
	 * TODO: autocomplete="off"
	 * $value['custom_attributes'] as $attribute => $attribute_value
	 * $value['custom_attributes'] as 'autocomplete' => 'off'
	 * 'custom_attributes' => array( 'autocomplete' => 'off' )
	 *
	 * @see \WC_Admin_Settings::output_fields()
	 *
	 * @param array<string, mixed> $form_fields Existing settings fields to append to.
	 * @return array<string, mixed>
	 */
	public function append_imap_reconcile_fields( array $form_fields ) {

		$form_fields['email_server'] = array(
			'title'             => __( 'Email server', 'bh-wp-order-email-reconcile' ),
			'type'              => 'text',
			'description'       => __( 'IMAP server or IP address.', 'bh-wp-order-email-reconcile' ),
			'desc_tip'          => true,
			'custom_attributes' => array(
				'autocomplete'   => 'off',
				'data-lpignore'  => 'true',
				'data-form-type' => 'text',
			),
			'id'                => 'email_server',
			'default'           => str_replace( 'mail.example.com', '', get_option( 'mailserver_url' ) ),
		);

		$form_fields['email_username'] = array(
			'title'             => __( 'Email username', 'bh-wp-order-email-reconcile' ),
			'type'              => 'text',
			'description'       => __( 'Login username for email address payment receipts are mailed to.', 'bh-wp-order-email-reconcile' ),
			'desc_tip'          => true,
			'custom_attributes' => array(
				'autocomplete'  => 'off',
				'data-lpignore' => 'true',
			),
			'id'                => 'email_username',
			'default'           => str_replace( 'mail.example.com', '', get_option( 'mailserver_url' ) ),
		);

		$form_fields['email_password'] = array(
			'title'             => __( 'Email account password', 'bh-wp-order-email-reconcile' ),
			'type'              => 'password',
			'custom_attributes' => array(
				'autocomplete'  => 'off',
				'data-lpignore' => 'true',
			),
			'id'                => 'email_password',
		);

		$form_fields['after_reconcile_email_action'] = array(
			'title'       => __( 'After reconcile action', 'bh-wp-order-email-reconcile' ),
			'type'        => 'select',
			'class'       => 'wc-enhanced-select',
			'description' => __( 'Action to take after an email is matched to an order.', 'bh-wp-order-email-reconcile' ),
			'default'     => 'mark_read',
			'desc_tip'    => true,
			'options'     => array(
				'nothing'   => __( 'Nothing', 'bh-wp-order-email-reconcile' ),
				'mark_read' => __( 'Mark email read', 'bh-wp-order-email-reconcile' ),
				'delete'    => __( 'Delete email', 'bh-wp-order-email-reconcile' ),
			),
		);

		// TODO: Add a link to view the email CPT.

		return $form_fields;
	}
}
