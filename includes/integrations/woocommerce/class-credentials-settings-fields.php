<?php
/**
 * WooCommerce Settings API fields for the email account credentials.
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Order_Email_Reconcile\Integrations\WooCommerce;

/**
 * Adds the mailbox configuration fields to a WooCommerce Settings API screen (e.g. a payment gateway).
 */
class Credentials_Settings_Fields {

	/**
	 * WooCommerce Settings API fields: the "Add account" button (bh-wp-mailboxes' add/edit account
	 * modal) with a link to the accounts table, and the after-reconcile action.
	 *
	 * The email accounts and their IMAP credentials are managed in the modal rather than as settings
	 * fields, so the password never passes through the settings form.
	 *
	 * @see Mailbox_Settings_Field Renders the button and modal.
	 * @see \WC_Admin_Settings::output_fields()
	 *
	 * @param array<string, mixed> $form_fields Existing settings fields to append to.
	 * @return array<string, mixed>
	 */
	public function append_imap_reconcile_fields( array $form_fields ) {

		$form_fields['mailbox_accounts'] = array(
			'title'       => __( 'Payment mailbox', 'bh-wp-order-email-reconcile' ),
			'type'        => Mailbox_Settings_Field::FIELD_TYPE,
			'description' => __( 'The email accounts payment receipts are sent to. Add an IMAP account here; enable, disable, edit, check or delete accounts from the emails list.', 'bh-wp-order-email-reconcile' ),
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

		return $form_fields;
	}
}
