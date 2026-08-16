<?php
/**
 * Phone login workflow.
 *
 * @package AbibitumiID
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABID_Phone_Login {
	public static function start( $phone, $user_id = null ) {
		$phone = ABID_OTP_Store::normalize_phone( $phone );
		if ( ! ABID_OTP_Store::is_valid_phone( $phone ) ) {
			return new WP_Error( 'abid_bad_phone', __( 'Enter a valid international phone number.', 'abibitumi-id' ), array( 'status' => 400 ) );
		}
		if ( ABID_OTP_Store::start_rate_limited( $phone ) ) {
			return new WP_Error( 'abid_rate_limited', __( 'Too many verification requests. Please wait and try again.', 'abibitumi-id' ), array( 'status' => 429 ) );
		}

		$length = max( 4, min( 8, (int) ABID_Settings::get( 'otp_length', 6 ) ) );
		$code   = (string) random_int( (int) str_pad( '1', $length, '0' ), (int) str_repeat( '9', $length ) );
		ABID_OTP_Store::create( $phone, $code, $user_id );
		$sent = ABID_SMS::send_otp( $phone, $code );
		if ( is_wp_error( $sent ) ) {
			return $sent;
		}

		return array(
			'ok'         => true,
			'phone'      => $phone,
			'expires_in' => max( 1, (int) ABID_Settings::get( 'otp_ttl_minutes', 10 ) ) * MINUTE_IN_SECONDS,
		);
	}

	public static function verify( $phone, $code ) {
		$row = ABID_OTP_Store::verify( $phone, $code );
		if ( is_wp_error( $row ) ) {
			return $row;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			$user_id = $row->user_id ? (int) $row->user_id : ABID_Identity::find_user_by_phone( $phone );
		}
		if ( ! $user_id && ABID_Settings::get( 'allow_registration' ) ) {
			$user_id = self::create_user_for_phone( $phone );
		}
		if ( ! $user_id ) {
			return new WP_Error( 'abid_no_account', __( 'No account is linked to that verified phone number.', 'abibitumi-id' ), array( 'status' => 404 ) );
		}

		ABID_Identity::mark_phone_verified( $user_id, $phone );
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true, is_ssl() );

		return array(
			'ok'       => true,
			'user_id'  => (int) $user_id,
			'phone'    => ABID_OTP_Store::normalize_phone( $phone ),
			'identity' => ABID_Identity::current_user_identity(),
		);
	}

	private static function create_user_for_phone( $phone ) {
		$hash     = substr( ABID_OTP_Store::phone_hash( $phone ), 0, 12 );
		$username = 'abid_' . $hash;
		$email    = $username . '@phone.abibitumi.invalid';
		$user_id  = wp_insert_user(
			array(
				'user_login'   => $username,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 32, true, true ),
				'display_name' => ABID_OTP_Store::normalize_phone( $phone ),
				'role'         => get_option( 'default_role', 'subscriber' ),
			)
		);
		return is_wp_error( $user_id ) ? 0 : (int) $user_id;
	}
}
