<?php
/**
 * REST API.
 *
 * @package AbibitumiID
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABID_REST {
	const NS = 'abid/v1';

	public function init() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes() {
		register_rest_route(
			self::NS,
			'/phone/normalize',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'phone_normalize' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'phone'   => array( 'required' => true, 'type' => 'string' ),
					'country' => array( 'required' => false, 'type' => 'string' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/phone/start',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'phone_start' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'phone' => array( 'required' => true, 'type' => 'string' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/phone/verify',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'phone_verify' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'phone' => array( 'required' => true, 'type' => 'string' ),
					'code'  => array( 'required' => true, 'type' => 'string' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/me',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'me' ),
				'permission_callback' => function () {
					return is_user_logged_in();
				},
			)
		);
		register_rest_route(
			self::NS,
			'/contacts/lookup',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'contacts_lookup' ),
				'permission_callback' => array( $this, 'can_discover_contacts' ),
				'args'                => array(
					'contacts' => array(
						'required' => true,
						'type'     => 'array',
					),
				),
			)
		);
	}

	public function phone_normalize( WP_REST_Request $request ) {
		$country = $request->get_param( 'country' );
		$phone   = ABID_OTP_Store::normalize_phone( $request->get_param( 'phone' ), $country );
		return new WP_REST_Response(
			array(
				'phone' => $phone,
				'valid' => ABID_OTP_Store::is_valid_phone( $phone, $country ),
			),
			200
		);
	}

	public function phone_start( WP_REST_Request $request ) {
		$result = ABID_Phone_Login::start( $request->get_param( 'phone' ), get_current_user_id() ?: null );
		return is_wp_error( $result ) ? $result : new WP_REST_Response( $result, 200 );
	}

	public function phone_verify( WP_REST_Request $request ) {
		$result = ABID_Phone_Login::verify( $request->get_param( 'phone' ), $request->get_param( 'code' ) );
		return is_wp_error( $result ) ? $result : new WP_REST_Response( $result, 200 );
	}

	public function me() {
		return new WP_REST_Response( ABID_Identity::current_user_identity(), 200 );
	}

	public function can_discover_contacts() {
		if ( ! is_user_logged_in() || ! ABID_Settings::get( 'contact_discovery_enabled', 1 ) ) {
			return false;
		}
		return ABID_Identity::user_has_verified_phone( get_current_user_id() );
	}

	public function contacts_lookup( WP_REST_Request $request ) {
		$raw_contacts = $request->get_param( 'contacts' );
		$limit        = max( 1, min( 1000, (int) ABID_Settings::get( 'contact_discovery_limit', 250 ) ) );
		$contacts     = array_slice( is_array( $raw_contacts ) ? $raw_contacts : array(), 0, $limit );
		$lookup       = array();

		foreach ( $contacts as $index => $contact ) {
			$phone = is_array( $contact ) && isset( $contact['phone'] ) ? $contact['phone'] : $contact;
			$phone = ABID_OTP_Store::normalize_phone( $phone );
			if ( ! ABID_OTP_Store::is_valid_phone( $phone ) ) {
				continue;
			}
			$hash = ABID_OTP_Store::phone_hash( $phone );
			if ( isset( $lookup[ $hash ] ) ) {
				continue;
			}
			$lookup[ $hash ] = array(
				'index' => (int) $index,
				'hash'  => $hash,
			);
		}

		$matches = ABID_Identity::find_users_by_phone_hashes( array_keys( $lookup ) );
		$out     = array();
		foreach ( $lookup as $hash => $contact ) {
			if ( empty( $matches[ $hash ] ) ) {
				continue;
			}
			$out[] = array_merge(
				array( 'contact_index' => $contact['index'] ),
				$matches[ $hash ]
			);
		}

		/**
		 * Filters contact discovery matches before returning them to a verified
		 * user. Use this to remove blocked users or enrich Better Messages data.
		 *
		 * @param array           $out     Matched contacts.
		 * @param WP_REST_Request $request Current request.
		 */
		$out = apply_filters( 'abid_contact_discovery_matches', $out, $request );

		return new WP_REST_Response(
			array(
				'matches' => array_values( $out ),
				'count'   => count( $out ),
			),
			200
		);
	}
}
