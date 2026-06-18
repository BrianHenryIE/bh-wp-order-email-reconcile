<?php

namespace BrianHenryIE\WP_Order_Email_Reconcile\API\Model;

interface Unpaid_Order {

	/**
	 * To later match back up
	 */
	public function get_integration(): string;

	/**
	 * @return numeric-string
	 */
	public function get_amount(): string;

	/**
	 * The Venmo username etc. if applicable and provided.
	 */
	public function get_customer_payment_id(): ?string;

	public function get_email_address(): string;

	public function get_customer_name(): string;

}