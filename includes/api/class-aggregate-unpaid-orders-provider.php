<?php
/**
 * Combines the unpaid orders from every available integration provider into one list.
 *
 * This lets the core API depend on a single Unpaid_Orders_Provider_Interface while still supporting
 * several active integrations at once (e.g. WooCommerce and GiveWP). Providers whose backing plugin
 * is inactive are skipped.
 *
 * @link       https://GitHub.com/BrianHenryIE/bh-wp-order-email-reconcile
 * @since      1.0.0
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Order_Email_Reconcile\API;

use BrianHenryIE\WP_Order_Email_Reconcile\API\Model\Unpaid_Order;
use BrianHenryIE\WP_Order_Email_Reconcile\Email_Reconcile_Settings_Interface;
use BrianHenryIE\WP_Order_Email_Reconcile\Integrations\GiveWP\Give_Unpaid_Orders_Provider;
use BrianHenryIE\WP_Order_Email_Reconcile\Integrations\WooCommerce\WC_Unpaid_Orders_Provider;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Composites every integration's provider into one.
 */
class Aggregate_Unpaid_Orders_Provider implements Unpaid_Orders_Provider_Interface {
	use LoggerAwareTrait;

	/**
	 * Constructor.
	 *
	 * @param Email_Reconcile_Settings_Interface $settings Plugin settings.
	 * @param LoggerInterface                    $logger   PSR-3 logger.
	 */
	public function __construct(
		protected Email_Reconcile_Settings_Interface $settings,
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );
	}

	/**
	 * Build the provider list from every integration, filtered on each use so integrations registered
	 * after this class is instantiated (e.g. on a later hook than the plugin bootstrap) are included.
	 *
	 * @return Unpaid_Orders_Provider_Interface[]
	 */
	protected function get_providers(): array {

		$providers = array(
			new WC_Unpaid_Orders_Provider( $this->settings, $this->logger ),
			new Give_Unpaid_Orders_Provider( $this->settings, $this->logger ),
		);

		/**
		 * Filter the list of unpaid-orders providers, e.g. to add a custom integration.
		 *
		 * @param Unpaid_Orders_Provider_Interface[] $providers
		 * @param string                             $plugin_slug
		 * @param Email_Reconcile_Settings_Interface $settings
		 */
		return apply_filters( 'bh_wp_order_email_reconcile_unpaid_orders_providers', $providers, $this->settings->get_plugin_slug(), $this->settings );
	}

	/**
	 * Available when at least one integration provider is available.
	 */
	public function is_available(): bool {
		foreach ( $this->get_providers() as $provider ) {
			if ( $provider->is_available() ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Merge the unpaid orders from every available provider.
	 *
	 * @return Unpaid_Order[]
	 */
	public function get_unpaid_orders(): array {
		$providers     = $this->get_providers();
		$unpaid_orders = array();

		foreach ( $providers as $provider ) {
			if ( ! $provider->is_available() ) {
				continue;
			}
			$unpaid_orders = array_merge( $unpaid_orders, $provider->get_unpaid_orders() );
		}

		$this->logger->debug(
			'Aggregated ' . count( $unpaid_orders ) . ' unpaid orders from ' . count( $providers ) . ' provider(s).'
		);

		return $unpaid_orders;
	}
}
