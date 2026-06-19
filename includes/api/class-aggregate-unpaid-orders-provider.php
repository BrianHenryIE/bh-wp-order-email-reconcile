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
	 * @param Unpaid_Orders_Provider_Interface[] $providers One provider per integration.
	 * @param LoggerInterface                    $logger    PSR-3 logger.
	 */
	public function __construct(
		protected array $providers,
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );
	}

	/**
	 * Available when at least one integration provider is available.
	 */
	public function is_available(): bool {
		foreach ( $this->providers as $provider ) {
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
		$unpaid_orders = array();

		foreach ( $this->providers as $provider ) {
			if ( ! $provider->is_available() ) {
				continue;
			}
			$unpaid_orders = array_merge( $unpaid_orders, $provider->get_unpaid_orders() );
		}

		$this->logger->debug(
			'Aggregated ' . count( $unpaid_orders ) . ' unpaid orders from ' . count( $this->providers ) . ' provider(s).'
		);

		return $unpaid_orders;
	}
}
