<?php
/**
 * Plugin Name: VendiPay WooCommerce Extension
 * Plugin URI:  https://github.com/CollinsLenjo/vendipay-woocommerce
 * Description: VendiPay WooCommerce Payment Plugin.
 * Version: 1.0.0
 * Author: VendiPay
 * Author URI: https://github.com/CollinsLenjo
 * Developer: VendiPay
 * Developer URI: https://github.com/CollinsLenjo
 * Text Domain: woocommerce-extension
 *
 * Woo: 12345:342928dfsfhsf8429842374wdf4234sfd
 * WC requires at least: 4.0
 * WC tested up to: 4.2.2
 *
 * Requires at least: 5.2
 * Requires PHP:      7.0
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */


/**
 * Check if WooCommerce is active
 **/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	# Hooks for adding/ removing the database table, and the wp_cron to check them
	register_activation_hook( __FILE__, 'install_vendipay_lipa_na_mpesa' );
	register_uninstall_hook( __FILE__, 'lipa_na_mpesa_on_uninstall' );

	define( 'LIPANAMPESA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
	define( 'LIPANAMPESA_PLUGIN_DIR', WP_PLUGIN_DIR . '/' . dirname( plugin_basename( __FILE__ ) ) );

	function install_vendipay_lipa_na_mpesa() {
		require_once 'lib/create_database_tables.php';
		create_database_tables();
	}

	function lipa_na_mpesa_on_uninstall() {
		global $wpdb;
		$table_name = $wpdb->prefix . "vendipay_lipa_na_mpesa";
		$wpdb->query( "DROP TABLE IF EXISTS $table_name" );
	}

	require_once( 'lib/vendipay-gateway-init.php' );
}
