<?php

namespace BrianHenryIE\WC_Order_Email_Reconcile\API;

use BrianHenryIE\WC_Order_Email_Reconcile\Email_Reconcile_Settings_Interface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Container implements ContainerInterface {

	protected array $entries = array();

	public function __construct( Email_Reconcile_Settings_Interface $settings, ?LoggerInterface $logger = null ) {

		$this->set(
			LoggerInterface::class,
			function( Container $container ) use ( $logger ) {
				return $logger ?? new NullLogger();
			}
		);

		$this->set(
			Email_Reconcile_Settings_Interface::class,
			function( Container $container ) use ( $settings ) {
				return $settings;
			}
		);

		$this->set(
			\BrianHenryIE\WC_Order_Email_Reconcile\API\Email_Parser::class,
			function( Container $container ) {
				/** @var Email_Reconcile_Settings_Interface $settings */
				$settings = $container->get( Email_Reconcile_Settings_Interface::class );

				$patterns = $settings->get_patterns();
				$logger   = $container->get( LoggerInterface::class );

				return new Email_Parser( $patterns, $logger );
			}
		);

		$this->set(
			Email_Reconciler::class,
			function( Container $container ) {
				$settings = $container->get( Email_Reconcile_Settings_Interface::class );
				$logger   = $container->get( LoggerInterface::class );
				return new Email_Reconciler( $settings, $logger );
			}
		);

		$this->set(
			Unpaid_Orders::class,
			function( Container $container ) {
				$settings = $container->get( Email_Reconcile_Settings_Interface::class );
				$logger   = $container->get( LoggerInterface::class );
				return new Unpaid_Orders( $settings, $logger );
			}
		);
	}

	/**
	 * Finds an entry of the container by its identifier and returns it.
	 *
	 * @param string $id Identifier of the entry to look for.
	 *
	 * @return mixed Entry.
	 * @throws \Psr\Container\ContainerExceptionInterface Error while retrieving the entry.
	 *
	 * @throws \Psr\Container\NotFoundExceptionInterface  No entry was found for **this** identifier.
	 */
	public function get( $id ) {
		if ( ! $this->has( $id ) ) {
			throw new class() extends \Exception implements \Psr\Container\NotFoundExceptionInterface {};
		}

		$entry = $this->entries[ $id ];

		return $entry( $this );
	}

	/**
	 * Returns true if the container can return an entry for the given identifier.
	 * Returns false otherwise.
	 *
	 * `has($id)` returning true does not mean that `get($id)` will not throw an exception.
	 * It does however mean that `get($id)` will not throw a `NotFoundExceptionInterface`.
	 *
	 * @param string $id Identifier of the entry to look for.
	 *
	 * @return bool
	 */
	public function has( $id ): bool {
		return isset( $this->entries[ $id ] );
	}

	public function set( string $id, callable $concrete ) {
		$this->entries[ $id ] = $concrete;
	}
}
