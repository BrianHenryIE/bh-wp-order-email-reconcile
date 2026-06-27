<?php
/**
 * Development/test plugin demonstrating order-email reconciliation.
 *
 * @link              https://bhwp.ie
 * @since             2.0.0
 * @package           brianhenryie/bh-wp-order-email-reconcile
 *
 * @wordpress-plugin
 * Plugin Name:       BH WP Order Email Reconcile Test Plugin
 * Plugin URI:        http://github.com/BrianHenryIE/bh-wp-order-email-reconcile/
 * Description:       A test plugin to demonstrate reconciling order payments from emails to WooCommerce orders.
 * Version:           1.0.0
 * Requires PHP:      7.4
 * Author:            BrianHenryIE
 * Author URI:        http://brianhenry.ie
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       bh-wp-order-email-reconcile
 * Domain Path:       /languages
 */

namespace BrianHenryIE\WP_Order_Email_Reconcile_Test_Plugin;

use BrianHenryIE\WP_Order_Email_Reconcile\BH_WP_Order_Email_Reconcile;
use BrianHenryIE\WP_Order_Email_Reconcile\Integrations\WooCommerce\WC_Unpaid_Orders_Provider;
use BrianHenryIE\WP_Order_Email_Reconcile\WP_Includes\Cron_Scheduler;
use BrianHenryIE\WP_Order_Email_Reconcile_Test_Plugin\REST\REST_Controller;
use BrianHenryIE\WP_Logger\Logger;
use Dotenv\Dotenv;
use Exception;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	throw new Exception( 'WPINC not defined' );
}

// The Composer autoloader location differs between the repo layout (plugin nested under the repo)
// and wp-env (the repo root is mapped into wp-content/uploads/bh-wp-order-email-reconcile).
$bh_wp_oer_autoload_candidates = array(
	plugin_dir_path( __FILE__ ) . '/../vendor/autoload.php',
	WP_CONTENT_DIR . '/uploads/bh-wp-order-email-reconcile/vendor/autoload.php',
);
foreach ( $bh_wp_oer_autoload_candidates as $bh_wp_oer_autoload ) {
	if ( file_exists( $bh_wp_oer_autoload ) ) {
		require_once $bh_wp_oer_autoload;
		break;
	}
}
unset( $bh_wp_oer_autoload_candidates, $bh_wp_oer_autoload );

define( 'BH_WP_ORDER_EMAIL_RECONCILE_TEST_PLUGIN_VERSION', '1.0.0' );
define( 'BH_WP_ORDER_EMAIL_RECONCILE_TEST_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

new Mappings()->register_hooks();

/**
 * Instantiate the library with hard-coded test settings.
 *
 * @return void
 */
function instantiate_bh_wp_order_email_reconcile_test_plugin() {

	// Secrets are optional in CI/wp-env; the email account is configured through the admin UI.
	$dotenv = Dotenv::createImmutable( __DIR__ . '/../', '.env.secret', true );
	$dotenv->safeLoad();

	$extraction_settings = new Extraction_Settings();

	$settings = new Settings( array( $extraction_settings ) );

	$logger = Logger::instance( $settings );

	$order_email_reconcile = BH_WP_Order_Email_Reconcile::instance( $settings, $logger );

	// Test-only login shortcut for Playwright.
	new Authentication();

	// Development REST endpoints used by the e2e tests.
	$mailboxes_api = $order_email_reconcile->get_mailboxes_api();
	if ( null !== $mailboxes_api ) {
		$cron_scheduler = new Cron_Scheduler(
			$settings,
			new WC_Unpaid_Orders_Provider( $settings, $logger ),
			$logger
		);
		new REST_Controller( $mailboxes_api, $cron_scheduler, $settings, $logger );
	}

	// Register the demo payment gateway whose orders this plugin reconciles.
	add_filter(
		'woocommerce_payment_gateways',
		function ( $gateways ) {
			$gateways[] = WooCommerce\My_Payment_Gateway::class;
			return $gateways;
		}
	);
}
instantiate_bh_wp_order_email_reconcile_test_plugin();


// Fix for symlinks in local dev.
add_filter(
	'plugins_url',
	function ( $url, $path, $plugin ) {

		$url = str_replace( 'Users/brianhenry/Sites', 'bh-wp-order-email-reconcile-test-plugin/vendor/brianhenryie', $url );

		return $url;
	},
	10,
	3
);
