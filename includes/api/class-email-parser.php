<?php
/**
 * Executes the regex searches for the payment information.
 *
 * @link       https://GitHub.com/BrianHenryIE/bh-wp-order-email-reconcile
 * @since      1.0.0
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Order_Email_Reconcile\API;

use BrianHenryIE\WP_Order_Email_Reconcile\API\Model\Extraction_Result;
use BrianHenryIE\WP_Order_Email_Reconcile\API\Model\Parsed_Email;
use BrianHenryIE\WP_Order_Email_Reconcile\Email_Extract_Settings_Interface;
use BrianHenryIE\WP_Mailboxes\API\Model\BH_Email;

use Exception;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Parses emails using multiple pattern sets then attempts to merge the results into one body of Parsed_Email information.
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
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
	 * The email post meta key the {@see Extraction_Result} is saved under by the API.
	 */
	const EMAIL_META_EXTRACTION = 'bh_wp_oer_extraction';

	/**
	 * The names of the values a pattern set can extract, in the order they are run.
	 */
	const VALUE_NAMES = array( 'amount', 'customer_email', 'customer_name', 'customer_id', 'order_id', 'transaction_id', 'transaction_url' );

	/**
	 * Parse the email: the merged values, without the per-pattern match details.
	 *
	 * @param BH_Email $email Text or HTML email body to search.
	 */
	public function parse_email( BH_Email $email ): Parsed_Email {
		$parsed_email = $this->extract( $email )->parsed_email;
		assert( $parsed_email instanceof Parsed_Email );

		return $parsed_email;
	}

	/**
	 * Run every pattern set over the email's plain-text and HTML bodies, merge the values, and
	 * record what each pattern matched.
	 *
	 * Values: the last pattern set (presumed newest, most likely correct) wins; earlier sets fill in
	 * what it missed. Matches: per pattern set, the plain-text pass wins and the HTML pass fills in.
	 *
	 * TODO: What to do if there are mis-matches between what patterns found?
	 *
	 * @param BH_Email $email Text or HTML email body to search.
	 */
	public function extract( BH_Email $email ): Extraction_Result {

		$parsed_values_arrays = array();
		$matches              = array();

		foreach ( $this->patterns as $pattern_set ) {
			$set_name             = $this->pattern_set_name( $pattern_set );
			$matches[ $set_name ] = array();

			$bodies = array(
				'plain_text' => $email->body_plain_text,
				'html'       => $email->body_html,
			);
			foreach ( $bodies as $source => $body ) {
				if ( empty( $body ) ) {
					continue;
				}
				$result                 = $this->parse_email_with_pattern_set( $body, $pattern_set, $source );
				$parsed_values_arrays[] = $result['properties'];
				foreach ( $result['matches'] as $name => $match ) {
					if ( ! isset( $matches[ $set_name ][ $name ] ) || is_null( $matches[ $set_name ][ $name ]['value'] ) ) {
						$matches[ $set_name ][ $name ] = $match;
					}
				}
			}
		}

		$parsed_email_array = array_pop( $parsed_values_arrays ) ?? array();
		$parsed_email_array = array_reduce(
			$parsed_values_arrays,
			function ( array $parsed_email, array $next_parsed_email ): array {
				foreach ( $next_parsed_email as $key => $value ) {
					if ( ! isset( $parsed_email[ $key ] ) ) {
						$parsed_email[ $key ] = $value;
					}
				}
				return $parsed_email;
			},
			$parsed_email_array
		);

		return new Extraction_Result( new Parsed_Email( $parsed_email_array, $email ), $matches );
	}

	/**
	 * A short name for a pattern set: its unqualified class name.
	 *
	 * @param Email_Extract_Settings_Interface $pattern_set The pattern set.
	 */
	protected function pattern_set_name( Email_Extract_Settings_Interface $pattern_set ): string {
		$parts = explode( '\\', get_class( $pattern_set ) );

		return (string) end( $parts );
	}

	/**
	 * Executes the regex search on the email for each of the settings.
	 *
	 * @param string                           $email_body  A text or HTML email expected to contain payment information.
	 * @param Email_Extract_Settings_Interface $pattern_set Regex patterns for extracting the payment information.
	 * @param string                           $source      Which body this is, `plain_text` or `html`, recorded on each match.
	 *
	 * @return array{properties: array<string, mixed>, matches: array<string, array{regex:?string, value:?string, source:?string}>}
	 * @throws Exception When new-line normalisation of the email body fails.
	 */
	protected function parse_email_with_pattern_set( string $email_body, Email_Extract_Settings_Interface $pattern_set, string $source ): array {

		$email_body = preg_replace( '/\s+/', ' ', $email_body );

		if ( is_null( $email_body ) ) {
			throw new Exception( 'Failed replacing new-lines in email body.' );
		}

		$email_properties = array();
		$matches          = array();

		$regex_array = array(
			'amount'          => $pattern_set->get_amount_regex(),
			'customer_email'  => $pattern_set->get_customer_email_regex(),
			'customer_name'   => $pattern_set->get_customer_name_regex(),
			'customer_id'     => $pattern_set->get_customer_id_regex(),
			'order_id'        => $pattern_set->get_order_id_regex(),
			'transaction_id'  => $pattern_set->get_transaction_id_regex(),
			'transaction_url' => $pattern_set->get_transaction_url_regex(),
		);

		foreach ( $regex_array as $name => $regex ) {
			$match = $this->match( $regex, $email_body, $source );

			$matches[ $name ] = $match;

			// order_id is recorded for display only: order ids are matched from the notes.
			if ( 'order_id' !== $name && ! is_null( $match['value'] ) ) {
				$email_properties[ $name ] = $match['value'];
			}
		}

		$email_properties['notes'] = array();
		foreach ( $pattern_set->get_notes_array_regex() as $name => $regex ) {
			$match = $this->match( $regex, $email_body, $source );

			$matches[ 'notes.' . $name ] = $match;

			if ( ! is_null( $match['value'] ) ) {
				$email_properties['notes'][ $name ] = $match['value'];
			}
		}

		// TODO: check was everything required found...

		$this->logger->debug( 'Email parsing complete', $email_properties );

		return array(
			'properties' => $email_properties,
			'matches'    => $matches,
		);
	}

	/**
	 * Run one regex, recording the trimmed first capture group.
	 *
	 * @param ?string $regex      The pattern; null when the pattern set has none for this value.
	 * @param string  $email_body The whitespace-normalised body.
	 * @param string  $source     `plain_text` or `html`.
	 *
	 * @return array{regex:?string, value:?string, source:?string}
	 */
	protected function match( ?string $regex, string $email_body, string $source ): array {
		if ( is_null( $regex ) || '' === $regex ) {
			return array(
				'regex'  => null,
				'value'  => null,
				'source' => null,
			);
		}

		$output_array = array();
		if ( 1 === preg_match( $regex, $email_body, $output_array ) && isset( $output_array[1] ) ) {
			return array(
				'regex'  => $regex,
				'value'  => trim( $output_array[1] ),
				'source' => $source,
			);
		}

		return array(
			'regex'  => $regex,
			'value'  => null,
			'source' => null,
		);
	}
}
