<?php
/** Phone-only registration must not fabricate an email address. */

define( 'ABSPATH', __DIR__ . '/wordpress/' );

class WP_Error {
	public function __construct( $code, $message ) {}
}
class ABID_OTP_Store {
	public static function verify( $phone, $code ) { return (object) array( 'user_id' => 0 ); }
	public static function normalize_phone( $phone ) { return $phone; }
	public static function phone_hash( $phone ) { return str_repeat( 'a', 64 ); }
}
class ABID_Identity {
	public static function find_user_by_phone( $phone ) { return 0; }
	public static function current_user_identity() { return null; }
}
class ABID_Phone_Management {
	public static function assign_verified_number( $user_id, $phone, $allow_transfer ) { return true; }
}
class ABID_Settings {
	public static function get( $name ) { return true; }
}

$GLOBALS['inserted_user'] = null;
function __( $value ) { return $value; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function get_current_user_id() { return 0; }
function wp_insert_user( $user ) { $GLOBALS['inserted_user'] = $user; return 73; }
function wp_generate_password() { return 'test-password'; }
function get_option( $name, $default ) { return $default; }

require __DIR__ . '/../../plugins/abibitumi-id/includes/class-abid-phone-login.php';

$result = ABID_Phone_Login::verify( '+233241234567', '123456', null, false );
if ( empty( $result['ok'] ) || 73 !== $result['user_id'] ||
	! isset( $GLOBALS['inserted_user']['user_email'] ) || '' !== $GLOBALS['inserted_user']['user_email'] ) {
	fwrite( STDERR, "Phone registration fabricated an email or failed.\n" );
	exit( 1 );
}

echo "Phone-only registration smoke test passed.\n";
