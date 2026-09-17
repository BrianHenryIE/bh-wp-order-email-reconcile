<?php
/**
 * Single email view metabox showing what each extraction pattern set found in the email.
 *
 * Reads the {@see Extraction_Result} the library saved to the email's post meta when it processed
 * the email: for every `Email_Extract_Settings_Interface`, each regex and the value it matched
 * (and in which body), or that it did not match; then the merged values the reconciler used. Helps
 * when writing patterns for a new payment provider's emails.
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Order_Email_Reconcile_Test_Plugin\Admin;

use BrianHenryIE\WP_Order_Email_Reconcile\API\Email_Parser;
use BrianHenryIE\WP_Order_Email_Reconcile\API\Model\Extraction_Result;
use BrianHenryIE\WP_Order_Email_Reconcile\Email_Reconcile_Settings_Interface;
use WP_Post;

/**
 * Registers and renders the "Extraction patterns" metabox on the payment emails post type.
 */
class Email_Extraction_Metabox {

	const METABOX_ID = 'bh-wp-oer-extraction-patterns';

	/**
	 * Constructor.
	 *
	 * @param Email_Reconcile_Settings_Interface $settings Provides the emails post type.
	 */
	public function __construct(
		protected Email_Reconcile_Settings_Interface $settings,
	) {
	}

	/**
	 * Register the metabox on the emails post type.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'add_meta_boxes_' . $this->settings->get_emails_cpt_underscored_20(), array( $this, 'add_meta_box' ) );
	}

	/**
	 * Add the metabox.
	 *
	 * @hooked add_meta_boxes_{emails_cpt}
	 *
	 * @return void
	 */
	public function add_meta_box(): void {
		add_meta_box(
			self::METABOX_ID,
			__( 'Extraction patterns', 'bh-wp-order-email-reconcile' ),
			array( $this, 'render' ),
			$this->settings->get_emails_cpt_underscored_20(),
			'normal',
			'default'
		);
	}

	/**
	 * Render a table per pattern set (value name, regex, match) and the merged values.
	 *
	 * @param WP_Post $post The email post.
	 *
	 * @return void
	 */
	public function render( WP_Post $post ): void {
		$result = Extraction_Result::from_array( get_post_meta( $post->ID, Email_Parser::EMAIL_META_EXTRACTION, true ) );

		if ( is_null( $result ) ) {
			echo '<p class="bh-wp-oer-extraction-patterns__unprocessed">' . esc_html__( 'Not yet processed: no extraction result has been saved for this email.', 'bh-wp-order-email-reconcile' ) . '</p>';
			return;
		}

		if ( 0 === count( $result->matches ) ) {
			echo '<p>' . esc_html__( 'No extraction pattern sets were configured when this email was processed.', 'bh-wp-order-email-reconcile' ) . '</p>';
		}

		foreach ( $result->matches as $pattern_set => $matches ) {
			echo '<h4 class="bh-wp-oer-extraction-patterns__set">' . esc_html( $pattern_set ) . '</h4>';
			echo '<table class="widefat striped bh-wp-oer-extraction-patterns__table"><thead><tr>';
			echo '<th>' . esc_html__( 'Value', 'bh-wp-order-email-reconcile' ) . '</th>';
			echo '<th>' . esc_html__( 'Pattern', 'bh-wp-order-email-reconcile' ) . '</th>';
			echo '<th>' . esc_html__( 'Match', 'bh-wp-order-email-reconcile' ) . '</th>';
			echo '</tr></thead><tbody>';

			foreach ( $matches as $name => $match ) {
				$this->render_row( $name, $match );
			}

			echo '</tbody></table>';
		}

		$this->render_values( $result->get_values() );
	}

	/**
	 * One row: the value name, its regex (or a dash when the set has none), and the match.
	 *
	 * @param string                                              $name  The value name, e.g. "amount".
	 * @param array{regex:?string, value:?string, source:?string} $match What the pattern matched.
	 *
	 * @return void
	 */
	protected function render_row( string $name, array $match ): void {
		echo '<tr data-pattern="' . esc_attr( $name ) . '">';
		echo '<td><code>' . esc_html( $name ) . '</code></td>';

		if ( is_null( $match['regex'] ) ) {
			echo '<td>&mdash;</td><td class="bh-wp-oer-extraction-patterns__unset">' . esc_html__( 'Not set', 'bh-wp-order-email-reconcile' ) . '</td>';
			echo '</tr>';
			return;
		}

		echo '<td><code>' . esc_html( $match['regex'] ) . '</code></td>';

		if ( is_null( $match['value'] ) ) {
			echo '<td class="bh-wp-oer-extraction-patterns__no-match">' . esc_html__( 'No match', 'bh-wp-order-email-reconcile' ) . '</td>';
		} else {
			echo '<td class="bh-wp-oer-extraction-patterns__match"><strong>' . esc_html( $match['value'] ) . '</strong> <span class="description">(' . esc_html( str_replace( '_', ' ', (string) $match['source'] ) ) . ')</span></td>';
		}
		echo '</tr>';
	}

	/**
	 * The merged values (last pattern set wins, earlier ones fill in) the reconciler matched against orders.
	 *
	 * @param array<string, mixed> $values The saved values.
	 *
	 * @return void
	 */
	protected function render_values( array $values ): void {
		echo '<h4 class="bh-wp-oer-extraction-patterns__set">' . esc_html__( 'Values used for reconciliation', 'bh-wp-order-email-reconcile' ) . '</h4>';
		echo '<table class="widefat striped bh-wp-oer-extraction-patterns__values"><tbody>';
		foreach ( $values as $name => $value ) {
			echo '<tr data-value="' . esc_attr( (string) $name ) . '"><td><code>' . esc_html( (string) $name ) . '</code></td><td>';
			if ( is_array( $value ) ) {
				echo 0 === count( $value ) ? '&mdash;' : '<code>' . esc_html( (string) wp_json_encode( $value ) ) . '</code>';
			} elseif ( is_null( $value ) || '' === $value ) {
				echo '&mdash;';
			} else {
				echo '<strong>' . esc_html( (string) $value ) . '</strong>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}
}
