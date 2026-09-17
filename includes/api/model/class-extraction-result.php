<?php
/**
 * What the extraction patterns found in an email: the merged values and each pattern's match.
 *
 * Saved to the email's post meta by the API so admin UIs can show what matched without re-running
 * the regexes.
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Order_Email_Reconcile\API\Model;

/**
 * Result of {@see \BrianHenryIE\WP_Order_Email_Reconcile\API\Email_Parser::extract()}.
 *
 * @phpstan-type Match array{regex:?string, value:?string, source:?string}
 * @phpstan-type Matches array<string, array<string, Match>>
 */
readonly class Extraction_Result {

	/**
	 * Constructor.
	 *
	 * @param ?Parsed_Email        $parsed_email The merged values, as used for reconciliation; null when rebuilt from saved meta.
	 * @param Matches              $matches      Per pattern-set name, per value name: the regex, the matched value (null when
	 *                                           unmatched, or when the pattern set has no regex for it) and the body it
	 *                                           matched in (`plain_text`|`html`).
	 * @param array<string, mixed> $values The merged values (amount, customer_id, …) as saved; from the parsed email when present.
	 */
	public function __construct(
		public ?Parsed_Email $parsed_email,
		public array $matches,
		public array $values = array(),
	) {
	}

	/**
	 * The merged values, from the parsed email when it is present.
	 *
	 * @return array<string, mixed>
	 */
	public function get_values(): array {
		if ( is_null( $this->parsed_email ) ) {
			return $this->values;
		}

		return array(
			'amount'          => $this->parsed_email->get_amount(),
			'customer_id'     => $this->parsed_email->get_customer_id(),
			'customer_email'  => $this->parsed_email->get_customer_email(),
			'customer_name'   => $this->parsed_email->get_customer_name(),
			'transaction_id'  => $this->parsed_email->get_transaction_id(),
			'transaction_url' => $this->parsed_email->get_transaction_url(),
			'notes'           => $this->parsed_email->get_notes(),
		);
	}

	/**
	 * For saving as post meta.
	 *
	 * @return array{matches: Matches, values: array<string, mixed>}
	 */
	public function to_array(): array {
		return array(
			'matches' => $this->matches,
			'values'  => $this->get_values(),
		);
	}

	/**
	 * Rebuild from saved post meta; null when the value is not a saved result.
	 *
	 * @param mixed $data The saved meta value.
	 */
	public static function from_array( mixed $data ): ?Extraction_Result {
		if ( ! is_array( $data ) || ! isset( $data['matches'] ) || ! is_array( $data['matches'] ) ) {
			return null;
		}

		$matches = array();
		foreach ( $data['matches'] as $pattern_set => $set_matches ) {
			if ( ! is_string( $pattern_set ) || ! is_array( $set_matches ) ) {
				continue;
			}
			foreach ( $set_matches as $name => $match ) {
				if ( ! is_string( $name ) || ! is_array( $match ) ) {
					continue;
				}
				$matches[ $pattern_set ][ $name ] = array(
					'regex'  => isset( $match['regex'] ) && is_string( $match['regex'] ) ? $match['regex'] : null,
					'value'  => isset( $match['value'] ) && is_string( $match['value'] ) ? $match['value'] : null,
					'source' => isset( $match['source'] ) && is_string( $match['source'] ) ? $match['source'] : null,
				);
			}
		}

		$values = isset( $data['values'] ) && is_array( $data['values'] ) ? $data['values'] : array();

		return new self( null, $matches, $values );
	}
}
