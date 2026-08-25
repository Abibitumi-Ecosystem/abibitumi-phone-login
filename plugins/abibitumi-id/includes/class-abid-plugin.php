<?php
/**
 * Plugin orchestrator.
 *
 * @package AbibitumiID
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABID_Plugin {
	public static function activate() {
		ABID_OTP_Store::install();
		ABID_Push_Login::install();
		if ( false === get_option( ABID_Settings::OPTION_KEY ) ) {
			add_option( ABID_Settings::OPTION_KEY, ABID_Settings::defaults(), '', false );
		}
		update_option( 'abid_db_version', ABID_VERSION, false );
	}

	public static function boot() {
		load_plugin_textdomain( 'abibitumi-id', false, dirname( plugin_basename( ABID_FILE ) ) . '/languages' );
		self::maybe_upgrade();
		( new ABID_Login_UI() )->init();
		( new ABID_Integrations() )->init();
		( new ABID_Admin_Entitlements() )->init();
		( new ABID_REST() )->init();
		if ( is_admin() ) {
			( new ABID_Admin() )->init();
		}
	}

	private static function maybe_upgrade() {
		if ( get_option( 'abid_db_version' ) === ABID_VERSION ) {
			return;
		}
		ABID_OTP_Store::install();
		ABID_Push_Login::install();
		update_option( 'abid_db_version', ABID_VERSION, false );
	}
}
