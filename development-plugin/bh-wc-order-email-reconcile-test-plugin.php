<?php
/**
 * @link              https://bhwp.ie
 * @since             2.0.0
 * @package           brianhenryie/bh-wp-mailboxes
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
use BrianHenryIE\WP_Logger\Logger;
use Dotenv\Dotenv;
use Exception;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	throw new Exception( 'WPINC not defined' );
}

require_once plugin_dir_path( __FILE__ ) . '/../vendor/autoload.php';

define( 'BH_WP_ORDER_EMAIL_RECONCILE_TEST_PLUGIN_VERSION', '1.0.0' );
define( 'BH_WP_ORDER_EMAIL_RECONCILE_TEST_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );


function instantiate_bh_wp_order_email_reconcile_test_plugin() {

	$dotenv = Dotenv::createImmutable( __DIR__ . '/../', '.env.secret', true );
	$dotenv->load();

	$imap_mailbox_settings = new Mailbox_Settings();
	$extraction_settings   = new Extraction_Settings();

	$settings = new Settings( array( $imap_mailbox_settings ), array( $extraction_settings ) );

	$logger = Logger::instance( $settings );

	$order_email_reconcile = BH_WP_Order_Email_Reconcile::instance( $settings, $logger );

}
instantiate_bh_wp_order_email_reconcile_test_plugin();


// Fix for symlinks in local dev.
add_filter(
	'plugins_url',
	function( $url, $path, $plugin ) {

		$url = str_replace( 'Users/brianhenry/Sites', 'bh-wp-order-email-reconcile-test-plugin/vendor/brianhenryie', $url );

		return $url;
	},
	10,
	3
);
