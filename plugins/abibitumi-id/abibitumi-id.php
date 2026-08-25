<?php
/**
 * Plugin Name:       Abibitumi ID
 * Plugin URI:        https://abibitumi.com/
 * Description:       Identity layer and phone OTP login for Abibitumi WordPress/BuddyBoss properties.
 * Version:           0.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Abibitumi
 * Author URI:        https://abibitumi.com/
 * Text Domain:       abibitumi-id
 *
 * @package AbibitumiID
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ABID_VERSION', '0.2.0' );
define( 'ABID_FILE', __FILE__ );
define( 'ABID_DIR', plugin_dir_path( __FILE__ ) );
define( 'ABID_URL', plugin_dir_url( __FILE__ ) );

require_once ABID_DIR . 'includes/class-abid-settings.php';
require_once ABID_DIR . 'includes/class-abid-otp-store.php';
require_once ABID_DIR . 'includes/class-abid-sms.php';
require_once ABID_DIR . 'includes/class-abid-identity.php';
require_once ABID_DIR . 'includes/class-abid-phone-login.php';
require_once ABID_DIR . 'includes/class-abid-push-login.php';
require_once ABID_DIR . 'includes/class-abid-login-ui.php';
require_once ABID_DIR . 'includes/class-abid-integrations.php';
require_once ABID_DIR . 'includes/class-abid-admin-entitlements.php';
require_once ABID_DIR . 'includes/class-abid-rest.php';
require_once ABID_DIR . 'includes/class-abid-admin.php';
require_once ABID_DIR . 'includes/class-abid-plugin.php';

register_activation_hook( __FILE__, array( 'ABID_Plugin', 'activate' ) );

add_action( 'plugins_loaded', array( 'ABID_Plugin', 'boot' ) );
