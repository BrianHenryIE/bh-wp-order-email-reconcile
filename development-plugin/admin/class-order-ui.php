<?php
/**
 * Admin order-edit UI for the development/test plugin.
 *
 * Adds an editable "customer payment id" field to the order billing panel and, for unpaid orders, a
 * "Check emails" link beside the order status that checks the mailbox (via the dev REST endpoint)
 * and shows the outcome as an admin notice, reloading the page first when the order was reconciled.
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Order_Email_Reconcile_Test_Plugin\Admin;

use BrianHenryIE\WP_Order_Email_Reconcile\Email_Reconcile_Settings_Interface;
use BrianHenryIE\WP_Order_Email_Reconcile_Test_Plugin\REST\REST_Controller;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use WC_Order;
use WP_Screen;

/**
 * Renders and saves the order-edit additions.
 */
class Order_UI {

	use LoggerAwareTrait;

	/**
	 * Constructor. Registers the admin hooks.
	 *
	 * @param Email_Reconcile_Settings_Interface $settings Provides the customer-payment-id meta key.
	 * @param LoggerInterface                    $logger   PSR-3 logger.
	 */
	public function __construct(
		protected Email_Reconcile_Settings_Interface $settings,
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );

		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'print_order_fields' ) );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'print_check_emails_link' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_order_fields' ) );
		add_action( 'admin_notices', array( $this, 'print_check_emails_notice' ) );
	}

	/**
	 * The transient the REST check-emails endpoint leaves its summary in for the reloaded order
	 * screen to display. Per order and user.
	 *
	 * @param int $order_id The order.
	 */
	public static function check_emails_result_transient( int $order_id ): string {
		return 'bh_wp_oer_check_emails_' . $order_id . '_' . get_current_user_id();
	}

	/**
	 * Print the customer payment id (as text, or as an input while the billing address is being
	 * edited).
	 *
	 * WooCommerce's pencil/"Edit" toggle on the billing column hides every `div.address` and shows
	 * every `div.edit_address` in the column, so the field is rendered in both forms and follows it.
	 *
	 * @hooked woocommerce_admin_order_data_after_billing_address
	 *
	 * @param WC_Order $order The order being edited.
	 * @return void
	 */
	public function print_order_fields( WC_Order $order ): void {

		$meta_key = $this->settings->get_customer_payment_id_meta_key();

		if ( ! empty( $meta_key ) ) {
			$value = (string) $order->get_meta( $meta_key );
			?>
			<div class="address bh-wp-oer-customer-payment-id">
				<p>
					<strong><?php esc_html_e( 'Customer payment id', 'bh-wp-order-email-reconcile' ); ?>:</strong>
					<span class="bh-wp-oer-customer-payment-id__value"><?php echo '' === $value ? '&mdash;' : esc_html( $value ); ?></span>
				</p>
			</div>
			<div class="edit_address">
				<?php
				woocommerce_wp_text_input(
					array(
						'id'            => 'customer_payment_id',
						'label'         => __( 'Customer payment id', 'bh-wp-order-email-reconcile' ) . ':',
						'value'         => $value,
						'wrapper_class' => 'form-field-wide',
					)
				);
				?>
			</div>
			<?php
		}
	}

	/**
	 * For unpaid orders, a "Check emails" link (with the `update` dashicon) to the right of the
	 * "Status" label in the order's general column.
	 *
	 * The hook fires below the general column's fields, so the link is printed there hidden and
	 * moved into the status label by a small script, which then shows it (so it never flashes in the
	 * wrong place); WooCommerce's stylesheet floats links in that label right (it puts its own
	 * "Customer payment page" link there the same way). Clicking it POSTs to the
	 * dev REST endpoint, which checks the mailbox; when this order was reconciled the page reloads
	 * (the endpoint leaves the notice for it), otherwise the summary notice is shown in place.
	 *
	 * @hooked woocommerce_admin_order_data_after_order_details
	 *
	 * @param WC_Order $order The order being edited.
	 * @return void
	 */
	public function print_check_emails_link( WC_Order $order ): void {
		if ( $order->is_paid() ) {
			return;
		}

		$url = rest_url( REST_Controller::REST_NAMESPACE . '/orders/' . $order->get_id() . '/check-emails' );
		?>
		<a href="#" id="bh-wp-oer-check-emails" class="bh-wp-oer-check-emails" hidden data-url="<?php echo esc_url( $url ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>" title="<?php esc_attr_e( 'Check the payment mailbox now and reconcile any new emails', 'bh-wp-order-email-reconcile' ); ?>">
			<span class="bh-wp-oer-check-emails__text"><?php esc_html_e( 'Check emails', 'bh-wp-order-email-reconcile' ); ?></span><span class="dashicons dashicons-update" aria-hidden="true"></span>
		</a>
		<style>
			.bh-wp-oer-check-emails { text-decoration: none; }
			.bh-wp-oer-check-emails[hidden] { display: none; }
			.bh-wp-oer-check-emails:hover .bh-wp-oer-check-emails__text,
			.bh-wp-oer-check-emails:focus .bh-wp-oer-check-emails__text { text-decoration: underline; }
			.bh-wp-oer-check-emails .dashicons { font-size: 16px; width: 16px; height: 16px; vertical-align: text-bottom; margin-left: 2px; }
			.bh-wp-oer-check-emails.is-checking .dashicons { animation: bh-wp-oer-spin 1s linear infinite; }
			@keyframes bh-wp-oer-spin { to { transform: rotate( 360deg ); } }
		</style>
		<script>
			document.addEventListener( 'DOMContentLoaded', function () {
				var link  = document.getElementById( 'bh-wp-oer-check-emails' );
				var label = document.querySelector( '.wc-order-status label[for="order_status"]' );
				if ( ! link ) {
					return;
				}
				if ( ! label ) {
					// Nowhere to put it: leave it hidden.
					return;
				}
				label.appendChild( link );
				link.hidden = false;
				link.addEventListener( 'click', function ( event ) {
					event.preventDefault();
					if ( link.classList.contains( 'is-checking' ) ) {
						return;
					}
					link.classList.add( 'is-checking' );
					link.setAttribute( 'aria-busy', 'true' );
					fetch( link.dataset.url, {
						method: 'POST',
						credentials: 'same-origin',
						headers: { 'X-WP-Nonce': link.dataset.nonce },
					} ).then( function ( response ) {
						return response.json();
					} ).then( function ( summary ) {
						if ( summary.matched_email_id > 0 ) {
							// The order changed: reload; the endpoint left the notice for the new page.
							window.location.reload();
							return;
						}
						// Nothing changed for this order: show the summary in place.
						var existing = document.querySelector( '.bh-wp-oer-check-emails-notice' );
						if ( existing ) {
							existing.remove();
						}
						var anchor = document.querySelector( '.wp-header-end' );
						if ( anchor && summary.notice_html ) {
							anchor.insertAdjacentHTML( 'afterend', summary.notice_html );
							if ( window.jQuery ) {
								// Lets core's common.js add the dismiss button to the new notice.
								window.jQuery( document ).trigger( 'wp-updates-notice-added' );
							}
						}
						link.classList.remove( 'is-checking' );
						link.removeAttribute( 'aria-busy' );
					} ).catch( function () {
						link.classList.remove( 'is-checking' );
						link.removeAttribute( 'aria-busy' );
					} );
				} );
			} );
		</script>
		<?php
	}

	/**
	 * After "Check emails" reconciled this order and reloaded the screen, show the green notice the
	 * REST endpoint left (linking to the email). Unmatched results are shown in place by the JS and
	 * never reach here.
	 *
	 * @hooked admin_notices
	 *
	 * @return void
	 */
	public function print_check_emails_notice(): void {
		$order_id = $this->get_current_order_id();
		if ( $order_id <= 0 ) {
			return;
		}

		$transient = self::check_emails_result_transient( $order_id );
		$summary   = get_transient( $transient );
		if ( ! is_array( $summary ) ) {
			return;
		}
		delete_transient( $transient );

		echo new Check_Emails_Notice( $this->logger )->render( $summary ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the renderer.
	}

	/**
	 * The order being edited, on the order edit screen (HPOS or posts), or 0.
	 */
	protected function get_current_order_id(): int {
		$screen = get_current_screen();
		if ( ! $screen instanceof WP_Screen || ! in_array( $screen->id, array( 'woocommerce_page_wc-orders', 'shop_order' ), true ) ) {
			return 0;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading the order id to render only.
		return isset( $_GET['id'] )
			? absint( wp_unslash( $_GET['id'] ) )
			: ( isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0 );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Save the customer payment id field.
	 *
	 * @hooked woocommerce_process_shop_order_meta
	 *
	 * @param int $order_id The order being saved.
	 * @return void
	 */
	public function save_order_fields( int $order_id ): void {

		$meta_key = $this->settings->get_customer_payment_id_meta_key();
		if ( empty( $meta_key ) ) {
			return;
		}

		// Nonce is verified by WooCommerce before this hook fires as part of the order save.
		if ( ! isset( $_POST['customer_payment_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		$value = sanitize_text_field( wp_unslash( $_POST['customer_payment_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$order->update_meta_data( $meta_key, $value );
		$order->save();
	}
}
