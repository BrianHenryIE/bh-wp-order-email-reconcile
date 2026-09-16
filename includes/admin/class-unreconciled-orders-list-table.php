<?php
/**
 * WP_List_Table of the orders/donations still waiting for a payment email.
 *
 * Rows are pre-fetched {@see Unpaid_Order}s (the providers return every unpaid order, so the table
 * paginates in memory). No checkbox column and no bulk actions; the table nav carries pagination
 * only. The rows link to the integration's order edit screen.
 *
 * @see Unreconciled_Orders_Page Fetches the orders and renders this table.
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Order_Email_Reconcile\Admin;

use BrianHenryIE\WP_Order_Email_Reconcile\API\Model\Unpaid_Order;
use BrianHenryIE\WP_Order_Email_Reconcile\Integrations\WooCommerce\WC_Unpaid_Order;
use WP_List_Table;

/**
 * Renders {@see Unpaid_Order} items.
 */
class Unreconciled_Orders_List_Table extends WP_List_Table {

	const PER_PAGE = 20;

	/**
	 * All unreconciled orders, before pagination.
	 *
	 * @var Unpaid_Order[]
	 */
	protected array $orders;

	/**
	 * Constructor.
	 *
	 * @param Unpaid_Order[] $orders The orders to list (all of them; paginated in {@see prepare_items()}).
	 */
	public function __construct( array $orders ) {
		parent::__construct(
			array(
				'singular' => 'bh_wp_oer_unreconciled_order',
				'plural'   => 'bh_wp_oer_unreconciled_orders',
				'ajax'     => false,
			)
		);

		$this->orders = array_values( $orders );
	}

	/**
	 * Set the column headers and the current page of items.
	 */
	public function prepare_items(): void {
		$this->_column_headers = array( $this->get_columns(), array(), array(), 'order' );

		$total        = count( $this->orders );
		$current_page = max( 1, $this->get_pagenum() );

		$this->items = array_slice( $this->orders, ( $current_page - 1 ) * self::PER_PAGE, self::PER_PAGE );

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => self::PER_PAGE,
				'total_pages' => (int) ceil( $total / self::PER_PAGE ),
			)
		);
	}

	/**
	 * The columns. No `cb` entry, so no checkbox column is rendered.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return array(
			'order'               => __( 'Order', 'bh-wp-order-email-reconcile' ),
			'date'                => __( 'Date', 'bh-wp-order-email-reconcile' ),
			'customer'            => __( 'Customer', 'bh-wp-order-email-reconcile' ),
			'customer_payment_id' => __( 'Customer payment id', 'bh-wp-order-email-reconcile' ),
			'amount'              => __( 'Amount', 'bh-wp-order-email-reconcile' ),
		);
	}

	/**
	 * Drop core's `fixed` and view-mode classes.
	 *
	 * @return string[]
	 */
	protected function get_table_classes(): array {
		return array( 'widefat', 'striped', 'bh-wp-oer-unreconciled-orders' );
	}

	/**
	 * Table nav with pagination only: no bulk actions (and so no bulk-action nonce).
	 *
	 * @param string $which Top or bottom.
	 */
	protected function display_tablenav( $which ): void {
		if ( 'bottom' === $which && ! $this->has_items() ) {
			return;
		}
		?>
		<div class="tablenav <?php echo esc_attr( $which ); ?>">
			<?php $this->pagination( 'top' === $which ? 'top' : 'bottom' ); ?>
			<br class="clear" />
		</div>
		<?php
	}

	/**
	 * Shown when there are no unreconciled orders.
	 */
	public function no_items(): void {
		esc_html_e( 'No orders are waiting for a payment email.', 'bh-wp-order-email-reconcile' );
	}

	/**
	 * A row, tagged with the order id and integration.
	 *
	 * @param Unpaid_Order $item The order.
	 */
	public function single_row( $item ): void {
		echo '<tr data-order-id="' . esc_attr( (string) $item->get_order_id() ) . '" data-integration="' . esc_attr( $item->get_integration() ) . '">';
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	/**
	 * Order id (linked to the order's edit screen) with the integration beneath.
	 *
	 * @param Unpaid_Order $item The order.
	 */
	protected function column_order( Unpaid_Order $item ): string {
		$label = '#' . $item->get_order_id();
		$url   = $item->get_edit_url();

		$html = is_null( $url )
			? '<strong>' . esc_html( $label ) . '</strong>'
			: '<strong><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></strong>';

		return $html . '<br><span class="bh-wp-oer-unreconciled-orders__integration">' . esc_html( $this->integration_label( $item->get_integration() ) ) . '</span>';
	}

	/**
	 * "View" row action on the primary column, plus core's responsive toggle.
	 *
	 * @param Unpaid_Order $item        The order.
	 * @param string       $column_name The column being rendered.
	 * @param string       $primary     The primary column.
	 */
	protected function handle_row_actions( $item, $column_name, $primary ): string {
		if ( $primary !== $column_name ) {
			return '';
		}

		$actions = array();
		$url     = $item->get_edit_url();
		if ( ! is_null( $url ) ) {
			$actions['view'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'View', 'bh-wp-order-email-reconcile' ) . '</a>';
		}

		return $this->row_actions( $actions ) . parent::handle_row_actions( $item, $column_name, $primary );
	}

	/**
	 * Order date, in the site's date/time format.
	 *
	 * @param Unpaid_Order $item The order.
	 */
	protected function column_date( Unpaid_Order $item ): string {
		$date = $item->get_date_created();
		if ( is_null( $date ) ) {
			return '&mdash;';
		}

		$format = (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' );

		return '<time datetime="' . esc_attr( $date->format( \DateTimeInterface::ATOM ) ) . '">'
			. esc_html( (string) wp_date( $format, $date->getTimestamp() ) ) . '</time>';
	}

	/**
	 * Customer name with email address beneath.
	 *
	 * @param Unpaid_Order $item The order.
	 */
	protected function column_customer( Unpaid_Order $item ): string {
		$name  = $item->get_customer_display_name();
		$email = $item->get_email_address();

		$html = '' === $name ? '' : esc_html( $name ) . '<br>';

		return $html . '<a href="' . esc_url( 'mailto:' . $email ) . '">' . esc_html( $email ) . '</a>';
	}

	/**
	 * The customer's payment-platform id, e.g. Venmo username, if captured.
	 *
	 * @param Unpaid_Order $item The order.
	 */
	protected function column_customer_payment_id( Unpaid_Order $item ): string {
		$id = $item->get_customer_payment_id();

		return is_null( $id ) || '' === $id ? '&mdash;' : '<code>' . esc_html( $id ) . '</code>';
	}

	/**
	 * Amount outstanding, formatted by WooCommerce for its orders.
	 *
	 * @param Unpaid_Order $item The order.
	 */
	protected function column_amount( Unpaid_Order $item ): string {
		if ( $item instanceof WC_Unpaid_Order && function_exists( 'wc_price' ) ) {
			return wp_kses_post( wc_price( (float) $item->get_amount(), array( 'currency' => $item->get_wc_order()->get_currency() ) ) );
		}

		return esc_html( $item->get_amount() );
	}

	/**
	 * A display name for the integration key, e.g. "WooCommerce".
	 *
	 * @param string $integration The integration key from {@see Unpaid_Order::get_integration()}.
	 */
	protected function integration_label( string $integration ): string {
		return match ( $integration ) {
			'woocommerce' => 'WooCommerce',
			'givewp' => 'GiveWP',
			default => ucfirst( $integration ),
		};
	}
}
