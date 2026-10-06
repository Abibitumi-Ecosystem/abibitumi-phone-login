<?php
/** Ensure a verified SMS code cannot bypass site account approval. */

define( 'ABSPATH', __DIR__ . '/wordpress/' );

class WP_Error {
	private $message;
	public function __construct( $code, $message ) { $this->message = $message; }
	public function get_error_message() { return $this->message; }
}
class WP_User {
	public $ID = 42;
	public $user_login = 'phone_member';
}
class ABID_OTP_Store {
	public static function verify( $phone, $code ) { return (object) array( 'user_id' => 42 ); }
	public static function normalize_phone( $phone ) { return $phone; }
}
class ABID_Identity {
	public static function current_user_identity() { return array( 'user_id' => 42 ); }
}
class ABID_Phone_Management {
	public static function assign_verified_number( $user_id, $phone, $allow_transfer ) { return true; }
}

$GLOBALS['approval_result'] = null;
$GLOBALS['cookie_count'] = 0;
function __( $value ) { return $value; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function get_current_user_id() { return 0; }
function get_user_by( $field, $value ) { return new WP_User(); }
function apply_filters( $name, $user, $password ) { return $GLOBALS['approval_result']; }
function wp_set_current_user( $user_id ) {}
function wp_set_auth_cookie( $user_id, $remember, $secure ) { ++$GLOBALS['cookie_count']; }
function is_ssl() { return true; }
function do_action( $name, $login, $user ) {}

require __DIR__ . '/../../plugins/abibitumi-id/includes/class-abid-phone-login.php';

$GLOBALS['approval_result'] = new WP_Error( 'pending_approval', '<strong>Pending approval</strong>' );
$pending = ABID_Phone_Login::verify( '+233241234567', '123456' );
if ( ! is_wp_error( $pending ) || 0 !== $GLOBALS['cookie_count'] || 'Pending approval' !== $pending->get_error_message() ) {
	fwrite( STDERR, "Pending account was incorrectly signed in.\n" );
	exit( 1 );
}

$GLOBALS['approval_result'] = new WP_User();
$approved = ABID_Phone_Login::verify( '+233241234567', '123456' );
if ( is_wp_error( $approved ) || 1 !== $GLOBALS['cookie_count'] || empty( $approved['ok'] ) ) {
	fwrite( STDERR, "Approved account was not signed in.\n" );
	exit( 1 );
}

echo "Phone approval smoke tests passed.\n";
