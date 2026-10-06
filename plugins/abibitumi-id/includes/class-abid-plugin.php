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
		ABID_Phone_Management::install();
		ABID_Push_Login::install();
		if ( false === get_option( ABID_Settings::OPTION_KEY ) ) {
			add_option( ABID_Settings::OPTION_KEY, ABID_Settings::defaults(), '', false );
		}
		update_option( 'abid_db_version', ABID_VERSION, false );
	}

	public static function boot() {
		load_plugin_textdomain( 'abibitumi-id', false, dirname( plugin_basename( ABID_FILE ) ) . '/languages' );
		self::maybe_upgrade();
		self::maybe_remove_placeholder_emails();
		( new ABID_Login_UI() )->init();
		( new ABID_Ecosystem_SSO() )->init();
		( new ABID_Integrations() )->init();
		( new ABID_Admin_Entitlements() )->init();
		( new ABID_Phone_Management() )->init();
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
		ABID_Phone_Management::install();
		ABID_Push_Login::install();
		update_option( 'abid_db_version', ABID_VERSION, false );
	}

	private static function maybe_remove_placeholder_emails() {
		if ( get_option( 'abid_placeholder_email_cleanup_v1' ) ) {
			return;
		}
		$users = get_users(
			array(
				'number'         => -1,
				'fields'         => array( 'ID', 'user_login', 'user_email' ),
				'search'         => '*@phone.abibitumi.invalid',
				'search_columns' => array( 'user_email' ),
			)
		);
		$complete = true;
		foreach ( $users as $user ) {
			if ( ! preg_match( '/^abid_[a-f0-9]{12}$/', $user->user_login ) ||
				$user->user_email !== $user->user_login . '@phone.abibitumi.invalid' ) {
				continue;
			}
			$result = wp_update_user( array( 'ID' => (int) $user->ID, 'user_email' => '' ) );
			if ( is_wp_error( $result ) ) {
				$complete = false;
			}
		}
		if ( $complete ) {
			update_option( 'abid_placeholder_email_cleanup_v1', 1, false );
		}
	}
}
