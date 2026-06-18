<?php
/**
 * Executes the regex searches for the payment information.
 *
 * @link       https://GitHub.com/BrianHenryIE/bh-wc-order-email-reconcile
 * @since      1.0.0
 *
 * @package brianhenryie/bh-wc-order-email-reconcile
 */

namespace BrianHenryIE\WC_Order_Email_Reconcile\API;

use BrianHenryIE\WC_Order_Email_Reconcile\API\Model\Parsed_Email;
use BrianHenryIE\WC_Order_Email_Reconcile\Email_Extract_Settings_Interface;
use BrianHenryIE\WP_Mailboxes\API\Model\BH_Email;

use Exception;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Parses emails using multiple pattern sets then attempts to merge the results into one body of Parsed_Email information.
 *
 * @package brianhenryie/bh-wc-order-email-reconcile
 */
class Email_Parser {

	use LoggerAwareTrait;

	/**
	 * Array of pattern-sets for extracting amount, transaction id etc. from emails.
	 *
	 * @var Email_Extract_Settings_Interface[]
	 */
	protected array $patterns;

	/**
	 * Email_Parser constructor.
	 *
	 * @param Email_Extract_Settings_Interface[] $patterns Array of regex patters for extracting data from the emails.
	 * @param LoggerInterface                    $logger Logger.
	 */
	public function __construct( array $patterns, LoggerInterface $logger ) {

		$this->patterns = $patterns;
		$this->logger   = $logger;
	}

	/**
	 * Loops through the array of emails and extracts the transaction details.
	 *
	 * @param BH_Email[] $emails The fetched emails to search for order data in.
	 * @return Parsed_Email[]
	 */
	public function parse_emails( array $emails ): array {
		$parsed_emails = array();
		foreach ( $emails as $email ) {
			$parsed_emails[] = $this->parse_email( $email );
		}

		return $parsed_emails;
	}

	/**
	 *
	 *
	 * // TODO: Loop through each until all are matched,
	 * // If one pattern set gets a full match, use it (what's a full match...?)
	 * // otherwise merge them together somehow
	 *
	 * @param BH_Email $email Text or HTML email body to search.
	 *
	 * @return ?Parsed_Email
	 */
	public function parse_email( BH_Email $email ): ?Parsed_Email {

		$parsed_values_arrays = array();

		// Run all patterns on the email plain-text and email html content.
		foreach ( $this->patterns as $pattern_set ) {
			if ( ! empty( $email->get_body_plain_text() ) ) {
				$parsed_values_arrays[] = $this->parse_email_with_pattern_set( $email->get_body_plain_text(), $pattern_set );
			}
			if ( ! empty( $email->get_body_html() ) ) {
				$parsed_values_arrays[] = $this->parse_email_with_pattern_set( $email->get_body_html(), $pattern_set );
			}
		}

		// Loop through each set of discovered properties and flatten into one.

		// TODO: What to do if there are mis-matches between what patterns' found?

		// I think array_merge or similar can do this.
		// Take the last one, presuming the newest pattern set is most likely to be correct.
		$parsed_email_array = array_pop( $parsed_values_arrays );
		// Then fill in any missing properties from earlier patterns.
		$parsed_email_array = array_reduce(
			$parsed_values_arrays,
			function( array $parsed_email, array $next_parsed_email ) {
				foreach ( $next_parsed_email as $key => $value ) {
					if ( ! isset( $parsed_email[ $key ] ) ) {
						$parsed_email[ $key ] = $value;
					}
				}
				return $parsed_email;
			},
			$parsed_email_array
		);

		return new Parsed_Email( $parsed_email_array, $email );
	}

	/**
	 * Executes the regex search on the email for each of the settings.
	 *
	 * @param string                           $email_body A text or HTML email expected to contain payment information.
	 * @param Email_Extract_Settings_Interface $pattern_set Regex patters for extracting the payment information.
	 * @return array
	 */
	protected function parse_email_with_pattern_set( string $email_body, Email_Extract_Settings_Interface $pattern_set ): array {

		$email_body = preg_replace( '/\s+/', ' ', $email_body );

		if ( is_null( $email_body ) ) {
			throw new Exception( 'Failed replacing new-lines in email body.' );
		}

		$email_properties = array();

		$regex_array                    = array();
		$regex_array['amount']          = $pattern_set->get_amount_regex();
		$regex_array['customer_email']  = $pattern_set->get_customer_email_regex();
		$regex_array['customer_name']   = $pattern_set->get_customer_name_regex();
		$regex_array['customer_id']     = $pattern_set->get_customer_id_regex();
		$regex_array['transaction_id']  = $pattern_set->get_transaction_id_regex();
		$regex_array['transaction_url'] = $pattern_set->get_transaction_url_regex();

		// Removes null entries. e.g. where customer_email is not in the email body (CashApp).
		$regex_array = array_filter( $regex_array );

		foreach ( $regex_array as $name => $regex ) {
			$output_array = array();

			if ( 1 === preg_match( $regex, $email_body, $output_array ) ) {

				$email_properties[ $name ] = trim( $output_array[1] );
			}
		}

		$email_properties['notes'] = array();
		foreach ( $pattern_set->get_notes_array_regex() as $name => $regex ) {

			$output_array = array();
			if ( 1 === preg_match( $regex, $email_body, $output_array ) ) {
				$email_properties['notes'][ $name ] = $output_array[1];
			}
		}

		// TODO: check was everything required found...

		$this->logger->debug( 'Email parsing complete', $email_properties );

		return $email_properties;
	}

}
