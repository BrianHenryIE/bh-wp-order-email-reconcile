<?php
/**
 * Admin cross-linking between a reconciled WooCommerce order and the payment email that reconciled it.
 *
 * - On the order edit screen, JS turns the email Message-ID printed in the order notes into a link to
 *   the email post — but only while that post still exists.
 * - On the emails (log) list, a column links each reconciled email to its order.
 *
 * The reconciliation note and the cross-link meta are written by the integration-agnostic
 * Email_Reconciler; this class only renders them for WooCommerce.
 *
 * @link       https://GitHub.com/BrianHenryIE/bh-wp-order-email-reconcile
 * @since      1.0.0
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Order_Email_Reconcile\Integrations\WooCommerce;

use BrianHenryIE\WP_Order_Email_Reconcile\API\Email_Reconciler;
use BrianHenryIE\WP_Order_Email_Reconcile\Email_Reconcile_Settings_Interface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use WC_Order;
use WP_Screen;

/**
 * Renders the order <-> email links in wp-admin.
 */
class WC_Reconciliation_Admin {

	use LoggerAwareTrait;

	const EMAIL_LINK_CLASS = 'bh-wp-oer-email-link';

	/**
	 * Constructor. Registers the admin hooks.
	 *
	 * @param Email_Reconcile_Settings_Interface $settings Provides the emails CPT name.
	 * @param LoggerInterface                    $logger   PSR-3 logger.
	 */
	public function __construct(
		protected Email_Reconcile_Settings_Interface $settings,
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );

		add_action( 'admin_footer', array( $this, 'print_order_email_link_script' ) );

		$emails_cpt = $this->settings->get_emails_cpt_underscored_20();
		add_filter( "manage_{$emails_cpt}_posts_columns", array( $this, 'add_email_order_column' ) );
		add_action( "manage_{$emails_cpt}_posts_custom_column", array( $this, 'render_email_order_column' ), 10, 2 );
	}

	/**
	 * On the order edit screen, link the email Message-ID in the order notes to the email post,
	 * provided that post still exists.
	 *
	 * @hooked admin_footer
	 *
	 * @return void
	 */
	public function print_order_email_link_script(): void {

		$screen = get_current_screen();
		if ( ! $screen instanceof WP_Screen
			|| ! in_array( $screen->id, array( 'woocommerce_page_wc-orders', 'shop_order' ), true ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading the order id to render only.
		$order_id = isset( $_GET['id'] )
			? absint( wp_unslash( $_GET['id'] ) )
			: ( isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0 );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( $order_id <= 0 ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$message_id    = (string) $order->get_meta( Email_Reconciler::ORDER_META_EMAIL_MESSAGE_ID );
		$email_post_id = (int) $order->get_meta( Email_Reconciler::ORDER_META_EMAIL_POST_ID );

		// Only link while the email post still exists in the database.
		if ( '' === $message_id || $email_post_id <= 0 || false === get_post_status( $email_post_id ) ) {
			return;
		}

		$url = get_edit_post_link( $email_post_id, 'raw' );
		if ( empty( $url ) ) {
			return;
		}

		$js = sprintf(
			'(function(){var m=%s,u=%s,c=%s;'
			. 'document.querySelectorAll(".order_notes .note_content").forEach(function(el){'
			. 'if(el.querySelector("a."+c))return;if(el.innerHTML.indexOf(m)===-1)return;'
			. 'el.innerHTML=el.innerHTML.replace(m,\'<a class="\'+c+\'" href="\'+u+\'">\'+m+\'</a>\');});})();',
			wp_json_encode( $message_id ),
			wp_json_encode( $url ),
			wp_json_encode( self::EMAIL_LINK_CLASS )
		);

		wp_print_inline_script_tag( $js );
	}

	/**
	 * Add a "Reconciled order" column to the emails list table.
	 *
	 * @hooked manage_{emails_cpt}_posts_columns
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public function add_email_order_column( array $columns ): array {
		$columns['bh_wp_oer_reconciled_order'] = __( 'Reconciled order', 'bh-wp-order-email-reconcile' );
		return $columns;
	}

	/**
	 * Render the reconciled-order link in the emails list column.
	 *
	 * @hooked manage_{emails_cpt}_posts_custom_column
	 *
	 * @param string $column  The column being rendered.
	 * @param int    $post_id The email post id.
	 * @return void
	 */
	public function render_email_order_column( string $column, int $post_id ): void {

		if ( 'bh_wp_oer_reconciled_order' !== $column ) {
			return;
		}

		$order_id    = (int) get_post_meta( $post_id, Email_Reconciler::EMAIL_META_ORDER_ID, true );
		$integration = (string) get_post_meta( $post_id, Email_Reconciler::EMAIL_META_ORDER_INTEGRATION, true );

		if ( $order_id <= 0 || 'woocommerce' !== $integration ) {
			return;
		}

		$url = admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order_id );

		printf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			/* translators: %d: order id. */
			esc_html( sprintf( __( 'Order #%d', 'bh-wp-order-email-reconcile' ), $order_id ) )
		);
	}
}
