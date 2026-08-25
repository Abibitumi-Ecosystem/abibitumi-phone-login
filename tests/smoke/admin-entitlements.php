<?php
/**
 * Smoke tests for Abibitumi ID admin entitlement parsing.
 */

define( 'ABSPATH', __DIR__ . '/wordpress/' );
define( 'MINUTE_IN_SECONDS', 60 );

function sanitize_email( $email ) {
	return filter_var( strtolower( trim( (string) $email ) ), FILTER_VALIDATE_EMAIL ) ?: '';
}

function wp_salt( $scheme = 'auth' ) {
	return 'abid-test-salt-' . $scheme;
}

require __DIR__ . '/../../plugins/abibitumi-id/includes/class-abid-otp-store.php';
require __DIR__ . '/../../plugins/abibitumi-id/includes/class-abid-admin-entitlements.php';

function abid_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}
}

$emails = ABID_Admin_Entitlements::parse_email_list(
	"Obadele.Kambon@gmail.com\nbad-address\nadmin@example.com, ADMIN@example.com"
);

abid_assert(
	array( 'obadele.kambon@gmail.com', 'admin@example.com' ) === $emails,
	'Email aliases should be sanitized, lowercased, and deduplicated.'
);

$hash   = str_repeat( 'a', 64 );
$phones = ABID_Admin_Entitlements::parse_phone_hash_list(
	"sha256:{$hash}\n+1 (555) 555-5555\n"
);

abid_assert( 2 === count( $phones ), 'Phone/hash parser should keep two unique hashes.' );
abid_assert( $hash === $phones[0], 'Explicit sha256 hashes should be accepted without mutation.' );
abid_assert(
	ABID_OTP_Store::phone_hash( '+15555555555' ) === $phones[1],
	'Raw phone numbers should be normalized and stored as hashes.'
);

$ghana = ABID_Admin_Entitlements::parse_phone_hash_list( "024 123 4567" );
abid_assert(
	ABID_OTP_Store::phone_hash( '+233241234567' ) === $ghana[0],
	'Ghana admin phone aliases should be normalized before hashing.'
);

echo "Admin entitlement smoke tests passed.\n";
