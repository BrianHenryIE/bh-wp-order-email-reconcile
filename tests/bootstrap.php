<?php
/**
 * @package brianhenryie/bh-wp-order-email-reconcile
 */

$GLOBALS['project_root_dir']   = $project_root_dir  = dirname( __DIR__, 1 );
$GLOBALS['plugin_root_dir']    = $plugin_root_dir   = $project_root_dir . '/includes';
$GLOBALS['plugin_name']        = $plugin_name       = basename( $project_root_dir );
$GLOBALS['plugin_name_php']    = $plugin_name_php   = $plugin_name . '.php';
$GLOBALS['plugin_path_php']    = $project_root_dir . '/' . $plugin_name_php;
$GLOBALS['plugin_basename']    = $plugin_name . '/' . $plugin_name_php;
$GLOBALS['wordpress_root_dir'] = $project_root_dir . '/wordpress';

/**
 *
 * ```json
 *   "config": {
 *     "preferred-install": {
 *        "brianhenryie/bh-wp-mailboxes": "source"
 *     }
 *   }
 * ```
 */

$seeders_directory = codecept_root_dir( '../bh-wp-mailboxes/tests/_support/models' );
if ( is_dir( $seeders_directory ) ) {
	foreach ( glob( $seeders_directory . '/*.php' ) as $seeders_file ) {
		require_once $seeders_file;
	}
}
