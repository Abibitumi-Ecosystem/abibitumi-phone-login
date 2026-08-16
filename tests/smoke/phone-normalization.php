<?php
/**
 * Smoke tests for Abibitumi ID phone normalization.
 */

define( 'ABSPATH', __DIR__ . '/wordpress/' );
define( 'MINUTE_IN_SECONDS', 60 );

function wp_salt( $scheme = 'auth' ) {
	return 'abid-test-salt-' . $scheme;
}

require __DIR__ . '/../../plugins/abibitumi-id/includes/class-abid-otp-store.php';

function abid_phone_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}
}

$cases = array(
	'024 123 4567'       => '+233241234567',
	'0241234567'         => '+233241234567',
	'241234567'          => '+233241234567',
	'233241234567'       => '+233241234567',
	'+233 24 123 4567'   => '+233241234567',
	'00233 24 123 4567'  => '+233241234567',
	'+233 (0)24 1234567' => '+233241234567',
	'+233241234567 ext 9' => '+233241234567',
);

foreach ( $cases as $input => $expected ) {
	$actual = ABID_OTP_Store::normalize_phone( $input, 'GH' );
	abid_phone_assert( $expected === $actual, "{$input} normalized to {$actual}, expected {$expected}." );
	abid_phone_assert( ABID_OTP_Store::is_valid_phone( $input, 'GH' ), "{$input} should be valid for Ghana." );
}

abid_phone_assert( ! ABID_OTP_Store::is_valid_phone( '12345', 'GH' ), 'Short Ghana phone should be invalid.' );
abid_phone_assert( ! ABID_OTP_Store::is_valid_phone( '+233991234567', 'GH' ), 'Unknown Ghana network prefix should be invalid.' );

echo "Phone normalization smoke tests passed.\n";
