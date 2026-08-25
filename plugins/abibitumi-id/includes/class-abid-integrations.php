<?php
/**
 * BuddyBoss and Better Messages integration.
 *
 * @package AbibitumiID
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABID_Integrations {
	public function init() {
		add_action( 'abid_phone_verified', array( $this, 'sync_buddyboss_phone' ), 10, 2 );

		add_filter( 'better_messages_is_verified', array( $this, 'better_messages_verified' ), 10, 3 );
		add_filter( 'bp_better_messages_is_verified', array( $this, 'better_messages_verified' ), 10, 3 );
		add_filter( 'better_messages_can_send_message', array( $this, 'better_messages_can_send' ), 10, 4 );
		add_filter( 'bp_better_messages_can_send_message', array( $this, 'better_messages_can_send' ), 10, 4 );
	}

	/**
	 * Sync the verified phone into a configured BuddyBoss/BuddyPress xProfile
	 * field. The field ID is site-local and intentionally configurable.
	 *
	 * @param int    $user_id User ID.
	 * @param string $phone   E.164 phone number.
	 * @return void
	 */
	public function sync_buddyboss_phone( $user_id, $phone ) {
		$field_id = absint( ABID_Settings::get( 'buddyboss_phone_field_id', 0 ) );
		if ( ! $field_id || ! function_exists( 'xprofile_set_field_data' ) ) {
			return;
		}

		xprofile_set_field_data( $field_id, (int) $user_id, ABID_OTP_Store::normalize_phone( $phone ) );
	}

	/**
	 * Let Better Messages use Abibitumi ID phone verification as a verified
	 * user badge source.
	 *
	 * @param bool  $verified Current verification result.
	 * @param int   $user_id  User ID.
	 * @param mixed $context  Optional Better Messages context.
	 * @return bool
	 */
	public function better_messages_verified( $verified, $user_id = 0, $context = null ) {
		unset( $context );
		if ( ! ABID_Settings::get( 'better_messages_verified_badge', 1 ) ) {
			return $verified;
		}

		$user_id = $this->resolve_user_id( $user_id );
		return $user_id && ABID_Identity::user_has_verified_phone( $user_id ) ? true : $verified;
	}

	/**
	 * Optionally require phone verification before users can send messages.
	 *
	 * @param bool  $can_send Current permission result.
	 * @param int   $user_id  Sender user ID.
	 * @param mixed $thread   Better Messages thread/context.
	 * @param mixed $message  Message/context.
	 * @return bool
	 */
	public function better_messages_can_send( $can_send, $user_id = 0, $thread = null, $message = null ) {
		unset( $thread, $message );
		if ( ! $can_send || ! ABID_Settings::get( 'better_messages_require_verified_phone', 0 ) ) {
			return $can_send;
		}

		$user_id = $this->resolve_user_id( $user_id );
		return $user_id ? ABID_Identity::user_has_verified_phone( $user_id ) : false;
	}

	private function resolve_user_id( $user ) {
		if ( is_numeric( $user ) ) {
			return (int) $user;
		}
		if ( is_object( $user ) && isset( $user->ID ) ) {
			return (int) $user->ID;
		}
		if ( is_array( $user ) && isset( $user['ID'] ) ) {
			return (int) $user['ID'];
		}
		return get_current_user_id();
	}
}
