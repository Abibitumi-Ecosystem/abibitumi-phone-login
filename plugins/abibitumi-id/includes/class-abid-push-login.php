<?php
/**
 * Trusted-device push login.
 *
 * @package AbibitumiID
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABID_Push_Login {
	const DEVICE_TABLE_SUFFIX    = 'abid_push_devices';
	const CHALLENGE_TABLE_SUFFIX = 'abid_push_challenges';

	public static function devices_table() {
		global $wpdb;
		return $wpdb->prefix . self::DEVICE_TABLE_SUFFIX;
	}

	public static function challenges_table() {
		global $wpdb;
		return $wpdb->prefix . self::CHALLENGE_TABLE_SUFFIX;
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset    = $wpdb->get_charset_collate();
		$devices    = self::devices_table();
		$challenges = self::challenges_table();

		dbDelta(
			"CREATE TABLE {$devices} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id BIGINT UNSIGNED NOT NULL,
				provider VARCHAR(32) DEFAULT 'firebase' NOT NULL,
				token_hash CHAR(64) NOT NULL,
				token LONGTEXT NOT NULL,
				device_name VARCHAR(191) DEFAULT '' NOT NULL,
				last_seen_at DATETIME DEFAULT NULL,
				revoked_at DATETIME DEFAULT NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY token_hash (token_hash),
				KEY user_id (user_id),
				KEY revoked_at (revoked_at)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$challenges} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				challenge_id CHAR(64) NOT NULL,
				poll_hash CHAR(64) NOT NULL,
				user_id BIGINT UNSIGNED NOT NULL,
				phone_hash CHAR(64) NOT NULL,
				status VARCHAR(20) DEFAULT 'pending' NOT NULL,
				expires_at DATETIME NOT NULL,
				approved_at DATETIME DEFAULT NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY challenge_id (challenge_id),
				KEY poll_hash (poll_hash),
				KEY user_id (user_id),
				KEY status (status),
				KEY expires_at (expires_at)
			) {$charset};"
		);
	}

	public static function register_device( $user_id, $token, $device_name = '' ) {
		global $wpdb;
		$user_id = (int) $user_id;
		$token   = trim( (string) $token );

		if ( ! $user_id || '' === $token ) {
			return new WP_Error( 'abid_push_bad_device', __( 'A valid push token is required.', 'abibitumi-id' ), array( 'status' => 400 ) );
		}
		if ( ! ABID_Identity::user_has_verified_phone( $user_id ) ) {
			return new WP_Error( 'abid_push_unverified', __( 'Verify your phone before trusting this device.', 'abibitumi-id' ), array( 'status' => 403 ) );
		}

		$now  = current_time( 'mysql', true );
		$hash = self::token_hash( $token );
		$wpdb->replace(
			self::devices_table(),
			array(
				'user_id'      => $user_id,
				'provider'     => 'firebase',
				'token_hash'   => $hash,
				'token'        => $token,
				'device_name'  => sanitize_text_field( $device_name ),
				'last_seen_at' => $now,
				'revoked_at'   => null,
				'created_at'   => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return array( 'ok' => true );
	}

	public static function start( $phone ) {
		global $wpdb;
		if ( ! ABID_Settings::get( 'push_login_enabled', 1 ) ) {
			return new WP_Error( 'abid_push_disabled', __( 'Push login is not enabled.', 'abibitumi-id' ), array( 'status' => 403 ) );
		}

		$phone = ABID_OTP_Store::normalize_phone( $phone );
		if ( ! ABID_OTP_Store::is_valid_phone( $phone ) ) {
			return new WP_Error( 'abid_push_bad_phone', __( 'Enter a valid phone number.', 'abibitumi-id' ), array( 'status' => 400 ) );
		}
		if ( self::start_rate_limited( $phone ) ) {
			return new WP_Error( 'abid_push_rate_limited', __( 'Too many login notifications. Please wait and try again.', 'abibitumi-id' ), array( 'status' => 429 ) );
		}

		$user_id = ABID_Identity::find_user_by_phone( $phone );
		if ( ! $user_id ) {
			return new WP_Error( 'abid_push_no_account', __( 'No trusted device is linked to that phone number.', 'abibitumi-id' ), array( 'status' => 404 ) );
		}

		$devices = self::active_devices_for_user( $user_id );
		if ( ! $devices ) {
			return new WP_Error( 'abid_push_no_device', __( 'No trusted device is ready for push login.', 'abibitumi-id' ), array( 'status' => 404 ) );
		}

		$challenge_id = self::random_id();
		$poll_token   = self::random_id();
		$expires_at   = gmdate( 'Y-m-d H:i:s', time() + ( 5 * MINUTE_IN_SECONDS ) );
		$wpdb->insert(
			self::challenges_table(),
			array(
				'challenge_id' => $challenge_id,
				'poll_hash'    => self::token_hash( $poll_token ),
				'user_id'      => $user_id,
				'phone_hash'   => ABID_OTP_Store::phone_hash( $phone ),
				'status'       => 'pending',
				'expires_at'   => $expires_at,
				'created_at'   => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		$sent = self::send_challenge( $devices, $challenge_id );
		if ( is_wp_error( $sent ) ) {
			return $sent;
		}

		return array(
			'ok'           => true,
			'challenge_id' => $challenge_id,
			'poll_token'   => $poll_token,
			'expires_in'   => 5 * MINUTE_IN_SECONDS,
		);
	}

	public static function approve( $user_id, $challenge_id ) {
		global $wpdb;
		$row = self::challenge_by_id( $challenge_id );
		if ( ! $row || 'pending' !== $row->status || strtotime( $row->expires_at ) < time() ) {
			return new WP_Error( 'abid_push_expired', __( 'This login request is expired or invalid.', 'abibitumi-id' ), array( 'status' => 400 ) );
		}
		if ( (int) $row->user_id !== (int) $user_id ) {
			return new WP_Error( 'abid_push_wrong_user', __( 'This login request belongs to another account.', 'abibitumi-id' ), array( 'status' => 403 ) );
		}

		$wpdb->update(
			self::challenges_table(),
			array(
				'status'      => 'approved',
				'approved_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $row->id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return array( 'ok' => true );
	}

	public static function status( $challenge_id, $poll_token ) {
		$row = self::challenge_by_id( $challenge_id );
		if ( ! $row || ! hash_equals( (string) $row->poll_hash, self::token_hash( $poll_token ) ) ) {
			return new WP_Error( 'abid_push_not_found', __( 'Login request not found.', 'abibitumi-id' ), array( 'status' => 404 ) );
		}
		if ( strtotime( $row->expires_at ) < time() ) {
			return array( 'status' => 'expired' );
		}
		if ( 'approved' !== $row->status ) {
			return array( 'status' => $row->status );
		}

		wp_set_current_user( (int) $row->user_id );
		wp_set_auth_cookie( (int) $row->user_id, true, is_ssl() );

		return array(
			'status'   => 'approved',
			'user_id'  => (int) $row->user_id,
			'identity' => ABID_Identity::current_user_identity(),
		);
	}

	private static function active_devices_for_user( $user_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::devices_table() . ' WHERE user_id = %d AND revoked_at IS NULL ORDER BY last_seen_at DESC LIMIT 5',
				(int) $user_id
			)
		);
	}

	private static function challenge_by_id( $challenge_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::challenges_table() . ' WHERE challenge_id = %s LIMIT 1',
				sanitize_text_field( $challenge_id )
			)
		);
	}

	private static function send_challenge( array $devices, $challenge_id ) {
		$access_token = self::firebase_access_token();
		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		$project_id = ABID_Settings::get( 'firebase_project_id' );
		$sent       = 0;
		foreach ( $devices as $device ) {
			$response = wp_remote_post(
				'https://fcm.googleapis.com/v1/projects/' . rawurlencode( $project_id ) . '/messages:send',
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $access_token,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode(
						array(
							'message' => array(
								'token'        => $device->token,
								'notification' => array(
									'title' => __( 'Approve Abibitumi login', 'abibitumi-id' ),
									'body'  => __( 'Tap to approve this sign-in request.', 'abibitumi-id' ),
								),
								'data'         => array(
									'action'       => 'abid_push_login',
									'challenge_id' => $challenge_id,
								),
							),
						)
					),
					'timeout' => 15,
				)
			);
			if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
				$sent++;
			}
		}

		if ( ! $sent ) {
			return new WP_Error( 'abid_push_delivery_failed', __( 'The login notification could not be sent.', 'abibitumi-id' ), array( 'status' => 502 ) );
		}

		return true;
	}

	private static function firebase_access_token() {
		$project_id    = ABID_Settings::get( 'firebase_project_id' );
		$client_email  = ABID_Settings::get( 'firebase_client_email' );
		$private_key   = ABID_Settings::get( 'firebase_private_key' );
		$cached        = get_transient( 'abid_firebase_access_token' );

		if ( $cached ) {
			return $cached;
		}
		if ( ! $project_id || ! $client_email || ! $private_key ) {
			return new WP_Error( 'abid_firebase_unconfigured', __( 'Firebase push login is not configured.', 'abibitumi-id' ), array( 'status' => 400 ) );
		}

		$now    = time();
		$claims = array(
			'iss'   => $client_email,
			'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
			'aud'   => 'https://oauth2.googleapis.com/token',
			'iat'   => $now,
			'exp'   => $now + 3600,
		);
		$jwt    = self::jwt( $claims, $private_key );
		if ( is_wp_error( $jwt ) ) {
			return $jwt;
		}

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'body'    => array(
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $jwt,
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			return new WP_Error( 'abid_firebase_token_failed', __( 'Firebase access token request failed.', 'abibitumi-id' ), array( 'status' => 502 ) );
		}

		set_transient( 'abid_firebase_access_token', $body['access_token'], 50 * MINUTE_IN_SECONDS );
		return $body['access_token'];
	}

	private static function jwt( array $claims, $private_key ) {
		$private_key = str_replace( '\n', "\n", (string) $private_key );
		$segments    = array(
			self::base64url( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) ),
			self::base64url( wp_json_encode( $claims ) ),
		);
		$signing     = implode( '.', $segments );
		$signature   = '';
		if ( ! openssl_sign( $signing, $signature, $private_key, OPENSSL_ALGO_SHA256 ) ) {
			return new WP_Error( 'abid_firebase_key_failed', __( 'Firebase private key could not sign the request.', 'abibitumi-id' ), array( 'status' => 400 ) );
		}
		$segments[] = self::base64url( $signature );
		return implode( '.', $segments );
	}

	private static function base64url( $value ) {
		return rtrim( strtr( base64_encode( (string) $value ), '+/', '-_' ), '=' );
	}

	private static function random_id() {
		return bin2hex( random_bytes( 32 ) );
	}

	private static function start_rate_limited( $phone ) {
		$key    = 'abid_push_start_' . self::request_hash( $phone );
		$limit  = max( 1, (int) ABID_Settings::get( 'start_limit', 5 ) );
		$window = max( 60, (int) ABID_Settings::get( 'start_window', 900 ) );
		$count  = (int) get_transient( $key );

		if ( $count >= $limit ) {
			return true;
		}

		set_transient( $key, $count + 1, $window );
		return false;
	}

	private static function request_hash( $phone ) {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return hash_hmac( 'sha256', ABID_OTP_Store::phone_hash( $phone ) . '|' . $ip, wp_salt( 'nonce' ) );
	}

	private static function token_hash( $token ) {
		return hash_hmac( 'sha256', (string) $token, wp_salt( 'auth' ) );
	}
}
