<?php

namespace BrianHenryIE\WC_Order_Email_Reconcile\WooCommerce;

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
	 * @return array
	 */
	public function append_imap_reconcile_fields( array $form_fields ) {

		$form_fields['email_server'] = array(
			'title'             => __( 'Email server', 'bh-wc-order-email-reconcile' ),
			'type'              => 'text',
			'description'       => __( 'IMAP server or IP address.', 'bh-wc-order-email-reconcile' ),
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
			'title'             => __( 'Email username', 'bh-wc-order-email-reconcile' ),
			'type'              => 'text',
			'description'       => __( 'Login username for email address payment receipts are mailed to.', 'bh-wc-order-email-reconcile' ),
			'desc_tip'          => true,
			'custom_attributes' => array(
				'autocomplete'  => 'off',
				'data-lpignore' => 'true',
			),
			'id'                => 'email_username',
			'default'           => str_replace( 'mail.example.com', '', get_option( 'mailserver_url' ) ),
		);

		$form_fields['email_password'] = array(
			'title'             => __( 'Email account password', 'bh-wc-order-email-reconcile' ),
			'type'              => 'password',
			'custom_attributes' => array(
				'autocomplete'  => 'off',
				'data-lpignore' => 'true',
			),
			'id'                => 'email_password',
		);

		$form_fields['after_reconcile_email_action'] = array(
			'title'       => __( 'After reconcile action', 'bh-wc-order-email-reconcile' ),
			'type'        => 'select',
			'class'       => 'wc-enhanced-select',
			'description' => __( 'Action to take after an email is matched to an order.', 'bh-wc-order-email-reconcile' ),
			'default'     => 'mark_read',
			'desc_tip'    => true,
			'options'     => array(
				'nothing'   => __( 'Nothing', 'bh-wc-order-email-reconcile' ),
				'mark_read' => __( 'Mark email read', 'bh-wc-order-email-reconcile' ),
				'delete'    => __( 'Delete email', 'bh-wc-order-email-reconcile' ),
			),
		);

		// TODO: Add a link to view the email CPT.

		return $form_fields;
	}


}
