<?php

namespace BrianHenryIE\WC_Order_Email_Reconcile;

use BrianHenryIE\WC_Order_Email_Reconcile\API\API;
use BrianHenryIE\WC_Order_Email_Reconcile\API\Container;
use BrianHenryIE\WP_Mailboxes\API\API as BH_WP_Mailboxes;
use BrianHenryIE\WP_Mailboxes\WP_Includes\BH_WP_Mailboxes_Hooks;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class BH_WC_Order_Email_Reconcile extends API {

	protected static BH_WC_Order_Email_Reconcile $instance;

	public static function instance( Email_Reconcile_Settings_Interface $settings, ?LoggerInterface $logger = null ): BH_WC_Order_Email_Reconcile {

		if ( empty( self::$instance ) ) {
			$logger = $logger ?? new NullLogger();

			$container = new Container( $settings, $logger );

			$bh_wp_mailboxes = new BH_WP_Mailboxes( $settings, null, $logger );
			new BH_WP_Mailboxes_Hooks( $bh_wp_mailboxes, $settings, $logger );

			self::$instance = new BH_WC_Order_Email_Reconcile( $container );

		}

		return self::$instance;
	}

}
