<?php
/**
 * Plugin Name:       Usman Pay
 * Plugin URI:        https://usman.com/plugin
 * Description:       Connects this site to the Usman payment platform. Payments are created and processed entirely on usman.com — this plugin never stores or transmits Stripe credentials.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      8.0
 * Author:            Usman
 * Author URI:        https://usman.com
 * License:           GPL-2.0-or-later
 * Text Domain:       usman-pay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'USMAN_PAY_VERSION', '0.1.0' );
define( 'USMAN_PAY_FILE', __FILE__ );
define( 'USMAN_PAY_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Default platform endpoint. Overridable via wp-config.php for staging:
 *   define( 'USMAN_PAY_API_BASE', 'https://api.staging.usman.com' );
 */
if ( ! defined( 'USMAN_PAY_API_BASE' ) ) {
	define( 'USMAN_PAY_API_BASE', 'https://api.usman.com' );
}

require_once USMAN_PAY_PATH . 'includes/class-usman-pay-signer.php';
require_once USMAN_PAY_PATH . 'includes/class-usman-pay-credentials.php';
require_once USMAN_PAY_PATH . 'includes/class-usman-pay-client.php';
require_once USMAN_PAY_PATH . 'includes/class-usman-pay-rest.php';
require_once USMAN_PAY_PATH . 'includes/class-usman-pay-admin.php';
require_once USMAN_PAY_PATH . 'includes/class-usman-pay-checkout.php';

/**
 * Boot the plugin once WordPress has loaded.
 */
function usman_pay_boot(): void {
	( new Usman_Pay_Rest() )->register();
	( new Usman_Pay_Checkout() )->register();

	if ( is_admin() ) {
		( new Usman_Pay_Admin() )->register();
	}
}
add_action( 'plugins_loaded', 'usman_pay_boot' );

/**
 * On activation, record the version. No credentials are provisioned here —
 * the site is not connected until an operator completes the setup-token
 * handshake on the settings screen.
 */
function usman_pay_activate(): void {
	add_option( 'usman_pay_version', USMAN_PAY_VERSION );
}
register_activation_hook( __FILE__, 'usman_pay_activate' );

/**
 * Deactivation leaves stored credentials in place so that reactivating does not
 * force a re-handshake. Use the "Disconnect" button on the settings screen to
 * revoke this site's credentials on the platform and erase them locally.
 */
function usman_pay_deactivate(): void {
	wp_clear_scheduled_hook( 'usman_pay_heartbeat' );
}
register_deactivation_hook( __FILE__, 'usman_pay_deactivate' );
