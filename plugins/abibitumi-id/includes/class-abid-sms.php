<?php
/**
 * SMS delivery adapters.
 *
 * @package AbibitumiID
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABID_SMS {
	public static function send_otp( $phone, $code ) {
		$message  = sprintf( __( 'Your Abibitumi verification code is %s.', 'abibitumi-id' ), $code );
		$provider = (string) ABID_Settings::get( 'sms_provider', 'log' );
		$phone    = ABID_OTP_Store::normalize_phone( $phone );

		// Hubtel remains the Ghana-first route. When enabled, Twilio handles
		// non-Ghana destinations and retries Hubtel delivery failures.
		if ( 'hubtel' === $provider && ABID_Settings::get( 'twilio_fallback_enabled', 0 ) ) {
			if ( 0 !== strpos( $phone, '+233' ) ) {
				return self::twilio( $phone, $message );
			}
			$response = self::hubtel( $phone, $message );
			if ( is_wp_error( $response ) && self::twilio_is_configured() ) {
				return self::twilio( $phone, $message );
			}
			return $response;
		}

		if ( 'twilio' === $provider ) {
			return self::twilio( $phone, $message );
		}
		if ( 'africastalking' === $provider ) {
			return self::africas_talking( $phone, $message );
		}
		if ( 'hubtel' === $provider ) {
			return self::hubtel( $phone, $message );
		}
		if ( 'vonage' === $provider ) {
			return self::vonage( $phone, $message );
		}

		do_action( 'abid_log_otp', $phone, $code );
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Abibitumi ID OTP for ' . ABID_OTP_Store::normalize_phone( $phone ) . ': ' . $code ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		return true;
	}

	private static function twilio( $phone, $message ) {
		$account_sid = ABID_Settings::get( 'twilio_account_sid' ) ? ABID_Settings::get( 'twilio_account_sid' ) : ABID_Settings::get( 'twilio_sid' );
		$key_sid     = ABID_Settings::get( 'twilio_api_key_sid' );
		$key_secret  = ABID_Settings::get( 'twilio_api_key_secret' );
		$auth_token  = ABID_Settings::get( 'twilio_token' );
		$from        = ABID_Settings::get( 'twilio_from' );
		$use_api_key = $key_sid && $key_secret;
		if ( ! $account_sid || ! $from || ( ! $use_api_key && ! $auth_token ) ) {
			return new WP_Error( 'abid_twilio_unconfigured', __( 'Twilio is not configured.', 'abibitumi-id' ) );
		}
		$username = $use_api_key ? $key_sid : $account_sid;
		$password = $use_api_key ? $key_secret : $auth_token;
		$body     = array( 'To' => ABID_OTP_Store::normalize_phone( $phone ), 'Body' => $message );
		if ( 0 === strpos( $from, 'MG' ) ) {
			$body['MessagingServiceSid'] = $from;
		} else {
			$body['From'] = $from;
		}
		$response = wp_remote_post(
			'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode( $account_sid ) . '/Messages.json',
			array(
				'headers' => array( 'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password ) ),
				'body'    => $body,
				'timeout' => 15,
			)
		);
		return self::ok_response( $response, 201 );
	}

	private static function twilio_is_configured() {
		$account_sid = ABID_Settings::get( 'twilio_account_sid' ) ? ABID_Settings::get( 'twilio_account_sid' ) : ABID_Settings::get( 'twilio_sid' );
		$has_api_key = ABID_Settings::get( 'twilio_api_key_sid' ) && ABID_Settings::get( 'twilio_api_key_secret' );
		$has_legacy  = ABID_Settings::get( 'twilio_token' );
		return $account_sid && ABID_Settings::get( 'twilio_from' ) && ( $has_api_key || $has_legacy );
	}

	private static function africas_talking( $phone, $message ) {
		$user = ABID_Settings::get( 'africastalking_user' );
		$key  = ABID_Settings::get( 'africastalking_key' );
		$from = ABID_Settings::get( 'africastalking_from' );
		if ( ! $user || ! $key ) {
			return new WP_Error( 'abid_africastalking_unconfigured', __( "Africa's Talking is not configured.", 'abibitumi-id' ) );
		}
		$response = wp_remote_post(
			'https://api.africastalking.com/version1/messaging',
			array(
				'headers' => array( 'apiKey' => $key, 'Accept' => 'application/json' ),
				'body'    => array_filter( array( 'username' => $user, 'to' => ABID_OTP_Store::normalize_phone( $phone ), 'message' => $message, 'from' => $from ) ),
				'timeout' => 15,
			)
		);
		return self::ok_response( $response, 201 );
	}

	private static function hubtel( $phone, $message ) {
		$client_id     = ABID_Settings::get( 'hubtel_client_id' );
		$client_secret = ABID_Settings::get( 'hubtel_client_secret' );
		$from          = ABID_Settings::get( 'hubtel_from', 'ABIBITUMI' );
		$endpoint      = ABID_Settings::get( 'hubtel_endpoint', 'https://devp-sms03726-api.hubtel.com/v1/messages/send' );

		if ( ! $client_id || ! $client_secret || ! $from ) {
			return new WP_Error( 'abid_hubtel_unconfigured', __( 'Hubtel is not configured.', 'abibitumi-id' ) );
		}

		// This account's verified Hubtel SMSC endpoint accepts the client ID,
		// client secret, sender, recipient, and content as query parameters.
		// Keep this request on HTTPS and never log the resulting URL (it contains
		// the provider credentials).
		$url = add_query_arg(
			array(
				'clientsecret' => $client_secret,
				'clientid'     => $client_id,
				'from'         => $from,
				'to'           => ABID_OTP_Store::normalize_phone( $phone ),
				'content'      => $message,
			),
			esc_url_raw( $endpoint )
		);
		$response = wp_remote_get(
			$url,
			array(
				'headers' => array( 'Accept' => 'application/json' ),
				'timeout' => 15,
			)
		);
		return self::ok_response( $response, 200 );
	}

	private static function vonage( $phone, $message ) {
		$key    = ABID_Settings::get( 'vonage_key' );
		$secret = ABID_Settings::get( 'vonage_secret' );
		$from   = ABID_Settings::get( 'vonage_from', 'Abibitumi' );
		if ( ! $key || ! $secret ) {
			return new WP_Error( 'abid_vonage_unconfigured', __( 'Vonage is not configured.', 'abibitumi-id' ) );
		}
		$response = wp_remote_post(
			'https://rest.nexmo.com/sms/json',
			array(
				'body'    => array( 'api_key' => $key, 'api_secret' => $secret, 'from' => $from, 'to' => ltrim( ABID_OTP_Store::normalize_phone( $phone ), '+' ), 'text' => $message ),
				'timeout' => 15,
			)
		);
		return self::ok_response( $response, 200 );
	}

	private static function ok_response( $response, $expected ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 || ( $expected && $code !== $expected && 200 !== $expected ) ) {
			return new WP_Error( 'abid_sms_failed', __( 'The verification text could not be sent.', 'abibitumi-id' ), array( 'status' => $code ) );
		}
		return true;
	}
}
