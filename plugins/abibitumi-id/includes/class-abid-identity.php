<?php
/**
 * Identity helpers and user meta ownership.
 *
 * @package AbibitumiID
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABID_Identity {
	const PHONE_META       = 'abid_phone_e164';
	const PHONE_HASH_META  = 'abid_phone_hash';
	const VERIFIED_AT_META = 'abid_phone_verified_at';

	public static function mark_phone_verified( $user_id, $phone ) {
		$phone = ABID_OTP_Store::normalize_phone( $phone );
		update_user_meta( $user_id, self::PHONE_META, $phone );
		update_user_meta( $user_id, self::PHONE_HASH_META, ABID_OTP_Store::phone_hash( $phone ) );
		update_user_meta( $user_id, self::VERIFIED_AT_META, current_time( 'mysql', true ) );
		do_action( 'abid_phone_verified', (int) $user_id, $phone );
	}

	public static function find_user_by_phone( $phone ) {
		$users = get_users(
			array(
				'number'     => 1,
				'fields'     => 'ID',
				'meta_key'   => self::PHONE_HASH_META,
				'meta_value' => ABID_OTP_Store::phone_hash( $phone ),
			)
		);
		return $users ? (int) $users[0] : 0;
	}

	public static function find_users_by_phone_hashes( array $phone_hashes ) {
		$phone_hashes = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $phone_hashes ) ) ) );
		if ( ! $phone_hashes ) {
			return array();
		}

		$users = get_users(
			array(
				'number'     => count( $phone_hashes ),
				'fields'     => array( 'ID', 'display_name' ),
				'meta_query' => array(
					array(
						'key'     => self::PHONE_HASH_META,
						'value'   => $phone_hashes,
						'compare' => 'IN',
					),
				),
			)
		);

		$matches = array();
		foreach ( $users as $user ) {
			$hash = get_user_meta( $user->ID, self::PHONE_HASH_META, true );
			if ( ! $hash ) {
				continue;
			}
			$matches[ $hash ] = array(
				'user_id'      => (int) $user->ID,
				'display_name' => $user->display_name,
				'avatar_url'   => get_avatar_url( $user->ID, array( 'size' => 96 ) ),
				'verified'     => self::user_has_verified_phone( $user->ID ),
			);
		}
		return $matches;
	}

	public static function user_has_verified_phone( $user_id ) {
		return (bool) get_user_meta( (int) $user_id, self::VERIFIED_AT_META, true );
	}

	public static function user_context( $user_id ) {
		$user_id = (int) $user_id;
		if ( ! $user_id ) {
			return null;
		}
		return array(
			'user_id'           => $user_id,
			'phone'             => get_user_meta( $user_id, self::PHONE_META, true ),
			'phone_verified'    => self::user_has_verified_phone( $user_id ),
			'phone_verified_at' => get_user_meta( $user_id, self::VERIFIED_AT_META, true ),
		);
	}

	public static function current_user_identity() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return null;
		}
		return self::user_context( $user_id );
	}
}
