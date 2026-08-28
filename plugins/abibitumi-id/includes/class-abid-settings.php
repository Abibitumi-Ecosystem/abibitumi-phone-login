<?php
/**
 * Settings storage.
 *
 * @package AbibitumiID
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABID_Settings {
	const OPTION_KEY = 'abid_settings';

	public static function defaults() {
		return array(
			'sms_provider'        => 'log',
			'twilio_sid'          => '',
			'twilio_token'        => '',
			'twilio_from'         => '',
			'africastalking_user' => '',
			'africastalking_key'  => '',
			'africastalking_from' => '',
			'hubtel_client_id'    => '',
			'hubtel_client_secret' => '',
			'hubtel_from'         => 'ABIBITUMI',
			'hubtel_endpoint'     => 'https://devp-sms03726-api.hubtel.com/v1/messages/send',
			'vonage_key'          => '',
			'vonage_secret'       => '',
			'vonage_from'         => 'Abibitumi',
			'otp_ttl_minutes'     => 10,
			'otp_length'          => 6,
			'max_attempts'        => 5,
			'start_limit'         => 5,
			'start_window'        => 900,
			'default_phone_country' => 'GH',
			'allow_registration'  => 1,
			'login_redirect_url'  => '',
			'show_on_wp_login'    => 1,
			'push_login_enabled'  => 1,
			'firebase_project_id' => '',
			'firebase_client_email' => '',
			'firebase_private_key' => '',
			'ecosystem_sso_enabled' => 1,
			'ecosystem_identity_url' => 'https://abibitumi.com',
			'ecosystem_sso_secret' => '',
			'ecosystem_allowed_return_hosts' => "abibitumi.com\nabibiwiase.com",
			'ecosystem_auto_create_users' => 1,
			'ecosystem_site_slug' => '',
			'buddyboss_phone_field_id' => 0,
			'contact_discovery_enabled' => 1,
			'contact_discovery_limit' => 250,
			'better_messages_require_verified_phone' => 0,
			'better_messages_verified_badge' => 1,
			'admin_entitlements_enabled' => 0,
			'admin_entitlement_emails' => '',
			'admin_entitlement_phone_hashes' => '',
		);
	}

	public static function all() {
		$stored = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
	}

	public static function get( $key, $default = null ) {
		$settings = self::all();
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	public static function update( array $values ) {
		$current = self::all();
		update_option( self::OPTION_KEY, array_merge( $current, $values ), false );
	}
}
