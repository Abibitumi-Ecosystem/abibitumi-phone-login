<?php
/**
 * Admin role entitlements for trusted Abibitumi identities.
 *
 * @package AbibitumiID
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABID_Admin_Entitlements {
	const ENTITLED_AT_META = 'abid_admin_entitled_at';

	public function init() {
		add_action( 'wp_login', array( $this, 'maybe_grant_on_login' ), 10, 2 );
		add_action( 'abid_phone_verified', array( $this, 'maybe_grant_admin' ), 20, 1 );
	}

	public function maybe_grant_on_login( $user_login, $user ) {
		if ( $user instanceof WP_User ) {
			$this->maybe_grant_admin( $user->ID );
		}
	}

	public function maybe_grant_admin( $user_id ) {
		$user_id = (int) $user_id;
		if ( ! $this->user_is_entitled_admin( $user_id ) ) {
			return false;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user instanceof WP_User ) {
			return false;
		}

		if ( ! in_array( 'administrator', (array) $user->roles, true ) ) {
			$user->add_role( 'administrator' );
		}

		update_user_meta( $user_id, self::ENTITLED_AT_META, current_time( 'mysql', true ) );
		do_action( 'abid_admin_entitlement_granted', $user_id );

		return true;
	}

	public function user_is_entitled_admin( $user_id ) {
		if ( ! ABID_Settings::get( 'admin_entitlements_enabled', 0 ) ) {
			return false;
		}

		$user = get_user_by( 'id', (int) $user_id );
		if ( ! $user instanceof WP_User ) {
			return false;
		}

		$emails = self::parse_email_list( ABID_Settings::get( 'admin_entitlement_emails', '' ) );
		if ( $emails && in_array( strtolower( $user->user_email ), $emails, true ) ) {
			return true;
		}

		$phone_hash = get_user_meta( $user->ID, ABID_Identity::PHONE_HASH_META, true );
		$hashes     = self::parse_phone_hash_list( ABID_Settings::get( 'admin_entitlement_phone_hashes', '' ) );

		return $phone_hash && in_array( $phone_hash, $hashes, true );
	}

	public static function parse_email_list( $value ) {
		$items  = preg_split( '/[\s,]+/', (string) $value );
		$emails = array();
		foreach ( $items as $item ) {
			$email = sanitize_email( $item );
			if ( $email ) {
				$emails[] = strtolower( $email );
			}
		}
		return array_values( array_unique( $emails ) );
	}

	public static function parse_phone_hash_list( $value ) {
		$items  = preg_split( '/[\r\n,]+/', (string) $value );
		$hashes = array();
		foreach ( $items as $item ) {
			$item = trim( (string) $item );
			if ( '' === $item ) {
				continue;
			}

			if ( 0 === strpos( $item, 'sha256:' ) ) {
				$item = substr( $item, 7 );
			}

			if ( preg_match( '/^[a-f0-9]{64}$/i', $item ) ) {
				$hashes[] = strtolower( $item );
				continue;
			}

			$phone = ABID_OTP_Store::normalize_phone( $item );
			if ( $phone ) {
				$hashes[] = ABID_OTP_Store::phone_hash( $phone );
			}
		}

		return array_values( array_unique( $hashes ) );
	}
}
