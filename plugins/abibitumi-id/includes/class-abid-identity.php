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
		return ABID_Phone_Management::assign_verified_number( (int) $user_id, $phone );
	}

	public static function find_user_by_phone( $phone ) {
		$user_id = ABID_Phone_Management::find_user_id( $phone );
		if ( $user_id ) {
			return $user_id;
		}
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
		global $wpdb;
		$table        = ABID_Phone_Management::table();
		$placeholders = implode( ', ', array_fill( 0, count( $phone_hashes ), '%s' ) );
		$query        = $wpdb->prepare( "SELECT phone_hash, user_id FROM {$table} WHERE phone_hash IN ({$placeholders})", $phone_hashes );
		$rows         = $wpdb->get_results( $query );
		foreach ( $rows as $row ) {
			$user = get_user_by( 'id', (int) $row->user_id );
			if ( $user ) {
				$matches[ $row->phone_hash ] = array(
					'user_id'      => (int) $user->ID,
					'display_name' => $user->display_name,
					'avatar_url'   => get_avatar_url( $user->ID, array( 'size' => 96 ) ),
					'verified'     => true,
				);
			}
		}
		foreach ( $users as $user ) {
			$hash = get_user_meta( $user->ID, self::PHONE_HASH_META, true );
			if ( ! $hash ) {
				continue;
			}
			if ( isset( $matches[ $hash ] ) ) {
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
		return ABID_Phone_Management::has_verified_phone( (int) $user_id );
	}

	public static function user_context( $user_id ) {
		$user_id = (int) $user_id;
		if ( ! $user_id ) {
			return null;
		}
		$phones = ABID_Phone_Management::user_phones( $user_id );
		foreach ( $phones as &$phone ) {
			unset( $phone['phone_hash'] );
		}
		unset( $phone );
		return array(
			'user_id'           => $user_id,
			'phone'             => get_user_meta( $user_id, self::PHONE_META, true ),
			'phones'            => $phones,
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
