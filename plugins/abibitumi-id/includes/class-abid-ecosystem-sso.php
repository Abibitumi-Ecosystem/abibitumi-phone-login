<?php
/**
 * Ecosystem single sign-on between Abibitumi WordPress properties.
 *
 * @package AbibitumiID
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABID_Ecosystem_SSO {
	const TICKET_PREFIX = 'abid_sso_ticket_';

	public function init() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_action( 'template_redirect', array( $this, 'maybe_consume_callback' ), 1 );
	}

	public function routes() {
		register_rest_route(
			ABID_REST::NS,
			'/sso/start',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'start' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'return_to' => array( 'required' => true, 'type' => 'string' ),
				),
			)
		);
		register_rest_route(
			ABID_REST::NS,
			'/sso/validate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'validate' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'ticket' => array( 'required' => true, 'type' => 'string' ),
				),
			)
		);
	}

	public function start( WP_REST_Request $request ) {
		if ( ! $this->enabled() ) {
			return new WP_Error( 'abid_sso_disabled', __( 'Abibitumi ID ecosystem sign-in is not enabled.', 'abibitumi-id' ), array( 'status' => 403 ) );
		}

		$return_to = esc_url_raw( $request->get_param( 'return_to' ) );
		if ( ! $this->return_url_allowed( $return_to ) ) {
			return new WP_Error( 'abid_sso_bad_return', __( 'That ecosystem return URL is not allowed.', 'abibitumi-id' ), array( 'status' => 400 ) );
		}

		if ( ! is_user_logged_in() ) {
			return $this->redirect( wp_login_url( rest_url( ABID_REST::NS . '/sso/start?return_to=' . rawurlencode( $return_to ) ) ) );
		}

		$ticket = bin2hex( random_bytes( 32 ) );
		set_transient(
			self::TICKET_PREFIX . $this->hash( $ticket ),
			array(
				'user_id'    => get_current_user_id(),
				'return_to'  => $return_to,
				'created_at' => time(),
			),
			5 * MINUTE_IN_SECONDS
		);

		return $this->redirect(
			add_query_arg(
				array(
					'abid_sso_ticket' => $ticket,
					'abid_sso_issuer' => home_url( '', 'https' ),
				),
				$return_to
			)
		);
	}

	public function validate( WP_REST_Request $request ) {
		if ( ! $this->enabled() ) {
			return new WP_Error( 'abid_sso_disabled', __( 'Abibitumi ID ecosystem sign-in is not enabled.', 'abibitumi-id' ), array( 'status' => 403 ) );
		}
		if ( ! $this->shared_secret_matches( $request ) ) {
			return new WP_Error( 'abid_sso_forbidden', __( 'Ecosystem sign-in could not be validated.', 'abibitumi-id' ), array( 'status' => 403 ) );
		}

		$ticket = sanitize_text_field( $request->get_param( 'ticket' ) );
		$key    = self::TICKET_PREFIX . $this->hash( $ticket );
		$data   = get_transient( $key );
		delete_transient( $key );

		if ( empty( $data['user_id'] ) ) {
			return new WP_Error( 'abid_sso_bad_ticket', __( 'This Abibitumi ID sign-in ticket is expired or invalid.', 'abibitumi-id' ), array( 'status' => 400 ) );
		}

		return new WP_REST_Response(
			array(
				'ok'       => true,
				'issuer'   => home_url( '', 'https' ),
				'identity' => $this->identity_payload( (int) $data['user_id'] ),
			),
			200
		);
	}

	public function maybe_consume_callback() {
		if ( empty( $_GET['abid_sso_ticket'] ) || empty( $_GET['abid_sso_issuer'] ) || ! $this->enabled() ) {
			return;
		}

		$ticket   = sanitize_text_field( wp_unslash( $_GET['abid_sso_ticket'] ) );
		$issuer   = esc_url_raw( rawurldecode( sanitize_text_field( wp_unslash( $_GET['abid_sso_issuer'] ) ) ) );
		$identity = $this->validate_remote_ticket( $issuer, $ticket );

		if ( is_wp_error( $identity ) ) {
			wp_safe_redirect( remove_query_arg( array( 'abid_sso_ticket', 'abid_sso_issuer' ) ) );
			exit;
		}

		$user_id = $this->local_user_for_identity( $identity );
		if ( ! is_wp_error( $user_id ) && $user_id ) {
			wp_set_current_user( (int) $user_id );
			wp_set_auth_cookie( (int) $user_id, true, is_ssl() );
			do_action( 'abid_ecosystem_sso_login', (int) $user_id, $identity, $issuer );
		}

		wp_safe_redirect( remove_query_arg( array( 'abid_sso_ticket', 'abid_sso_issuer' ) ) );
		exit;
	}

	private function validate_remote_ticket( $issuer, $ticket ) {
		$secret = ABID_Settings::get( 'ecosystem_sso_secret', '' );
		if ( ! $secret || ! wp_http_validate_url( $issuer ) || ! $this->issuer_allowed( $issuer ) ) {
			return new WP_Error( 'abid_sso_unconfigured' );
		}

		$response = wp_remote_post(
			trailingslashit( $issuer ) . 'wp-json/' . ABID_REST::NS . '/sso/validate',
			array(
				'headers' => array( 'X-Abibitumi-ID-Secret' => $secret ),
				'body'    => array( 'ticket' => $ticket ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'abid_sso_validation_failed' );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['identity'] ) || ! is_array( $body['identity'] ) ) {
			return new WP_Error( 'abid_sso_bad_identity' );
		}

		return $body['identity'];
	}

	private function local_user_for_identity( array $identity ) {
		$user_id = 0;
		if ( ! empty( $identity['phone_hash'] ) ) {
			$users = get_users(
				array(
					'number'     => 1,
					'fields'     => 'ID',
					'meta_key'   => ABID_Identity::PHONE_HASH_META,
					'meta_value' => sanitize_text_field( $identity['phone_hash'] ),
				)
			);
			$user_id = $users ? (int) $users[0] : 0;
		}

		if ( ! $user_id && ! empty( $identity['email'] ) ) {
			$user = get_user_by( 'email', sanitize_email( $identity['email'] ) );
			$user_id = $user instanceof WP_User ? (int) $user->ID : 0;
		}

		if ( ! $user_id ) {
			if ( ! ABID_Settings::get( 'ecosystem_auto_create_users', 1 ) ) {
				return new WP_Error( 'abid_sso_no_user' );
			}
			$user_id = wp_insert_user(
				array(
					'user_login'   => $this->unique_login( $identity ),
					'user_email'   => ! empty( $identity['email'] ) ? sanitize_email( $identity['email'] ) : '',
					'user_pass'    => wp_generate_password( 32, true, true ),
					'display_name' => ! empty( $identity['display_name'] ) ? sanitize_text_field( $identity['display_name'] ) : __( 'Abibitumi Member', 'abibitumi-id' ),
					'role'         => get_option( 'default_role', 'subscriber' ),
				)
			);
			if ( is_wp_error( $user_id ) ) {
				return $user_id;
			}
		}

		$this->sync_identity_meta( (int) $user_id, $identity );
		return (int) $user_id;
	}

	private function sync_identity_meta( $user_id, array $identity ) {
		if ( ! empty( $identity['phone'] ) ) {
			update_user_meta( $user_id, ABID_Identity::PHONE_META, ABID_OTP_Store::normalize_phone( $identity['phone'] ) );
		}
		if ( ! empty( $identity['phone_hash'] ) ) {
			update_user_meta( $user_id, ABID_Identity::PHONE_HASH_META, sanitize_text_field( $identity['phone_hash'] ) );
		}
		if ( ! empty( $identity['phone_verified'] ) ) {
			update_user_meta( $user_id, ABID_Identity::VERIFIED_AT_META, ! empty( $identity['phone_verified_at'] ) ? sanitize_text_field( $identity['phone_verified_at'] ) : current_time( 'mysql', true ) );
			do_action( 'abid_phone_verified', (int) $user_id, get_user_meta( $user_id, ABID_Identity::PHONE_META, true ) );
		}
	}

	private function identity_payload( $user_id ) {
		$user = get_user_by( 'id', $user_id );
		return array_merge(
			ABID_Identity::user_context( $user_id ),
			array(
				'email'        => $user instanceof WP_User ? $user->user_email : '',
				'user_login'   => $user instanceof WP_User ? $user->user_login : '',
				'display_name' => $user instanceof WP_User ? $user->display_name : '',
				'phone_hash'   => get_user_meta( $user_id, ABID_Identity::PHONE_HASH_META, true ),
			)
		);
	}

	private function return_url_allowed( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $host ) {
			return false;
		}
		$allowed = $this->allowed_hosts();
		return in_array( strtolower( $host ), $allowed, true );
	}

	private function issuer_allowed( $issuer ) {
		$issuer_host = wp_parse_url( $issuer, PHP_URL_HOST );
		$idp_host    = wp_parse_url( ABID_Settings::get( 'ecosystem_identity_url', 'https://abibitumi.com' ), PHP_URL_HOST );
		return $issuer_host && $idp_host && strtolower( $issuer_host ) === strtolower( $idp_host );
	}

	private function allowed_hosts() {
		$hosts = preg_split( '/[\s,]+/', (string) ABID_Settings::get( 'ecosystem_allowed_return_hosts', '' ) );
		$hosts[] = wp_parse_url( home_url(), PHP_URL_HOST );
		$out = array();
		foreach ( $hosts as $host ) {
			$host = strtolower( trim( (string) $host ) );
			if ( $host ) {
				$out[] = $host;
			}
		}
		return array_values( array_unique( $out ) );
	}

	private function shared_secret_matches( WP_REST_Request $request ) {
		$secret = (string) ABID_Settings::get( 'ecosystem_sso_secret', '' );
		$sent   = (string) $request->get_header( 'x-abibitumi-id-secret' );
		if ( ! $sent ) {
			$sent = (string) $request->get_param( 'secret' );
		}
		return $secret && $sent && hash_equals( $secret, $sent );
	}

	private function unique_login( array $identity ) {
		$base = ! empty( $identity['user_login'] ) ? sanitize_user( $identity['user_login'], true ) : '';
		if ( ! $base && ! empty( $identity['phone_hash'] ) ) {
			$base = 'abid_' . substr( sanitize_text_field( $identity['phone_hash'] ), 0, 12 );
		}
		if ( ! $base ) {
			$base = 'abid_' . wp_generate_password( 8, false, false );
		}

		$login = $base;
		$i     = 2;
		while ( username_exists( $login ) ) {
			$login = $base . '_' . $i;
			$i++;
		}
		return $login;
	}

	private function redirect( $url ) {
		$response = new WP_REST_Response( null, 302 );
		$response->header( 'Location', $url );
		return $response;
	}

	private function enabled() {
		return (bool) ABID_Settings::get( 'ecosystem_sso_enabled', 1 );
	}

	private function hash( $value ) {
		return hash_hmac( 'sha256', (string) $value, wp_salt( 'auth' ) );
	}
}
