<?php
/**
 * OTP persistence and verification.
 *
 * @package AbibitumiID
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABID_OTP_Store {
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'abid_phone_otps';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			phone_hash CHAR(64) NOT NULL,
			user_id BIGINT UNSIGNED DEFAULT NULL,
			otp_hash VARCHAR(255) NOT NULL,
			ip_hash CHAR(64) NOT NULL,
			attempts TINYINT UNSIGNED DEFAULT 0 NOT NULL,
			expires_at DATETIME NOT NULL,
			consumed_at DATETIME DEFAULT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY phone_hash (phone_hash),
			KEY ip_hash (ip_hash),
			KEY expires_at (expires_at),
			KEY consumed_at (consumed_at)
		) {$charset};";
		dbDelta( $sql );
	}

	public static function phone_hash( $phone ) {
		return hash_hmac( 'sha256', self::normalize_phone( $phone ), wp_salt( 'auth' ) );
	}

	public static function normalize_phone( $phone, $country = null ) {
		$country = $country ? strtoupper( (string) $country ) : self::default_phone_country();
		$phone   = trim( (string) $phone );
		$phone   = str_replace( array( '(0)', 'ext.', 'extension' ), array( '', 'x', 'x' ), strtolower( $phone ) );
		$phone   = preg_replace( '/(?:x|#).*$/', '', $phone );
		$phone   = preg_replace( '/[^\d+]/', '', $phone );

		if ( 0 === strpos( $phone, '00' ) ) {
			$phone = '+' . substr( $phone, 2 );
		}

		if ( '+' !== substr( $phone, 0, 1 ) ) {
			$phone = self::national_to_e164( $phone, $country );
		}

		$phone = '+' . preg_replace( '/\D/', '', $phone );
		return $phone;
	}

	public static function is_valid_phone( $phone, $country = null ) {
		$phone = self::normalize_phone( $phone, $country );
		if ( ! preg_match( '/^\+\d{7,15}$/', $phone ) ) {
			return false;
		}

		if ( 0 === strpos( $phone, '+233' ) ) {
			return (bool) preg_match( '/^\+233[235]\d{8}$/', $phone );
		}

		return true;
	}

	private static function default_phone_country() {
		if ( class_exists( 'ABID_Settings' ) ) {
			$country = ABID_Settings::get( 'default_phone_country', 'GH' );
			return $country ? strtoupper( (string) $country ) : 'GH';
		}
		return 'GH';
	}

	private static function national_to_e164( $phone, $country ) {
		$digits = preg_replace( '/\D/', '', (string) $phone );
		if ( '' === $digits ) {
			return '+';
		}

		if ( 'GH' === $country ) {
			if ( 10 === strlen( $digits ) && '0' === substr( $digits, 0, 1 ) ) {
				return '+233' . substr( $digits, 1 );
			}
			if ( 9 === strlen( $digits ) ) {
				return '+233' . $digits;
			}
			if ( 0 === strpos( $digits, '233' ) ) {
				return '+' . $digits;
			}
		}

		$country_codes = array(
			'US' => '1',
			'CA' => '1',
			'GB' => '44',
			'NG' => '234',
		);

		if ( isset( $country_codes[ $country ] ) ) {
			$national = ltrim( $digits, '0' );
			return '+' . $country_codes[ $country ] . $national;
		}

		return '+' . $digits;
	}

	public static function create( $phone, $code, $user_id = null ) {
		global $wpdb;
		$ttl = max( 1, (int) ABID_Settings::get( 'otp_ttl_minutes', 10 ) );

		$wpdb->insert(
			self::table(),
			array(
				'phone_hash' => self::phone_hash( $phone ),
				'user_id'    => $user_id ? (int) $user_id : null,
				'otp_hash'   => wp_hash_password( (string) $code ),
				'ip_hash'    => self::ip_hash(),
				'expires_at' => gmdate( 'Y-m-d H:i:s', time() + ( $ttl * MINUTE_IN_SECONDS ) ),
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	public static function verify( $phone, $code ) {
		global $wpdb;
		$table    = self::table();
		$max      = max( 1, (int) ABID_Settings::get( 'max_attempts', 5 ) );
		$phone_id = self::phone_hash( $phone );
		$row      = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE phone_hash = %s AND consumed_at IS NULL AND expires_at >= %s ORDER BY id DESC LIMIT 1",
				$phone_id,
				current_time( 'mysql', true )
			)
		);

		if ( ! $row ) {
			return new WP_Error( 'abid_otp_missing', __( 'The verification code is expired or invalid.', 'abibitumi-id' ), array( 'status' => 400 ) );
		}
		if ( (int) $row->attempts >= $max ) {
			return new WP_Error( 'abid_otp_locked', __( 'Too many verification attempts.', 'abibitumi-id' ), array( 'status' => 429 ) );
		}

		$wpdb->update( $table, array( 'attempts' => (int) $row->attempts + 1 ), array( 'id' => (int) $row->id ), array( '%d' ), array( '%d' ) );

		if ( ! wp_check_password( (string) $code, $row->otp_hash ) ) {
			return new WP_Error( 'abid_otp_bad_code', __( 'The verification code is invalid.', 'abibitumi-id' ), array( 'status' => 400 ) );
		}

		$wpdb->update( $table, array( 'consumed_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $row->id ), array( '%s' ), array( '%d' ) );
		return $row;
	}

	public static function start_rate_limited( $phone ) {
		global $wpdb;
		$limit  = max( 1, (int) ABID_Settings::get( 'start_limit', 5 ) );
		$window = max( 60, (int) ABID_Settings::get( 'start_window', 900 ) );
		$since  = gmdate( 'Y-m-d H:i:s', time() - $window );
		$count  = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::table() . ' WHERE (phone_hash = %s OR ip_hash = %s) AND created_at >= %s',
				self::phone_hash( $phone ),
				self::ip_hash(),
				$since
			)
		);
		return $count >= $limit;
	}

	private static function ip_hash() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) );
	}
}
