<?php
/**
 * Verified phone ownership and account phone-number management.
 *
 * @package AbibitumiID
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABID_Phone_Management {
	const TABLE_SUFFIX = 'abid_verified_phones';

	public function init() {
		add_action( 'admin_menu', array( $this, 'register_management_page' ) );
		add_action( 'show_user_profile', array( $this, 'render_profile' ) );
		add_action( 'edit_user_profile', array( $this, 'render_profile' ) );
		add_action( 'admin_post_abid_phone_add_start', array( $this, 'start_add' ) );
		add_action( 'admin_post_abid_phone_add_verify', array( $this, 'verify_add' ) );
		add_action( 'admin_post_abid_phone_remove', array( $this, 'remove' ) );
		add_action( 'admin_post_abid_phone_primary', array( $this, 'set_primary' ) );
		add_action( 'admin_notices', array( $this, 'render_notice' ) );
	}

	public function register_management_page() {
		add_users_page(
			__( 'Abibitumi ID phone numbers', 'abibitumi-id' ),
			__( 'Abibitumi ID Phones', 'abibitumi-id' ),
			'edit_users',
			'abid-phone-management',
			array( $this, 'render_management_page' )
		);
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			phone_hash CHAR(64) NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			phone_e164 VARCHAR(32) NOT NULL,
			is_primary TINYINT(1) NOT NULL DEFAULT 0,
			verified_at DATETIME NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (phone_hash),
			KEY user_id (user_id)
		) {$charset};";
		dbDelta( $sql );
		self::migrate_legacy_numbers();
	}

	private static function migrate_legacy_numbers() {
		$users = get_users(
			array(
				'number'     => -1,
				'fields'     => 'ID',
				'meta_key'   => ABID_Identity::PHONE_HASH_META,
				'meta_compare' => 'EXISTS',
			)
		);
		foreach ( $users as $user_id ) {
			$phone = get_user_meta( $user_id, ABID_Identity::PHONE_META, true );
			$hash  = get_user_meta( $user_id, ABID_Identity::PHONE_HASH_META, true );
			$at    = get_user_meta( $user_id, ABID_Identity::VERIFIED_AT_META, true );
			if ( ! $phone || ! $hash || ! $at ) {
				continue;
			}
			self::insert_number( (int) $user_id, $phone, $at, true );
		}
	}

	private static function insert_number( $user_id, $phone, $verified_at, $primary = false ) {
		global $wpdb;
		$table = self::table();
		$hash  = ABID_OTP_Store::phone_hash( $phone );
		$found = $wpdb->get_row( $wpdb->prepare( "SELECT user_id, is_primary FROM {$table} WHERE phone_hash = %s", $hash ) );
		if ( $found ) {
			return (int) $found->user_id === (int) $user_id ? true : new WP_Error( 'abid_phone_claimed', __( 'That phone number is already linked to another account.', 'abibitumi-id' ), array( 'status' => 409 ) );
		}
		$has_primary = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT phone_hash FROM {$table} WHERE user_id = %d AND is_primary = 1 LIMIT 1", $user_id ) );
		$primary     = $primary || ! $has_primary;
		if ( $primary ) {
			$wpdb->update( $table, array( 'is_primary' => 0 ), array( 'user_id' => $user_id ), array( '%d' ), array( '%d' ) );
		}
		$inserted = $wpdb->insert(
			$table,
			array(
				'phone_hash'  => $hash,
				'user_id'     => (int) $user_id,
				'phone_e164'  => ABID_OTP_Store::normalize_phone( $phone ),
				'is_primary'  => $primary ? 1 : 0,
				'verified_at' => $verified_at,
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%s', '%d', '%s', '%s' )
		);
		if ( false === $inserted ) {
			return new WP_Error( 'abid_phone_store_failed', __( 'Could not save this verified phone number. Please try again.', 'abibitumi-id' ), array( 'status' => 500 ) );
		}
		if ( $primary ) {
			self::sync_legacy_primary( $user_id, $phone, $verified_at );
		}
		return true;
	}

	public static function assign_verified_number( $user_id, $phone, $allow_transfer = false ) {
		global $wpdb;
		$phone = ABID_OTP_Store::normalize_phone( $phone );
		if ( ! ABID_OTP_Store::is_valid_phone( $phone ) || ! get_user_by( 'id', (int) $user_id ) ) {
			return new WP_Error( 'abid_phone_assignment_invalid', __( 'The account or verified phone number is invalid.', 'abibitumi-id' ), array( 'status' => 400 ) );
		}
		$table = self::table();
		$hash  = ABID_OTP_Store::phone_hash( $phone );
		$owner = $wpdb->get_row( $wpdb->prepare( "SELECT user_id FROM {$table} WHERE phone_hash = %s", $hash ) );
		if ( $owner && (int) $owner->user_id !== (int) $user_id ) {
			if ( ! $allow_transfer ) {
				return new WP_Error( 'abid_phone_claimed', __( 'That phone number is already linked to another account.', 'abibitumi-id' ), array( 'status' => 409 ) );
			}
			$old_user_id = (int) $owner->user_id;
			$has_primary = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT phone_hash FROM {$table} WHERE user_id = %d AND is_primary = 1 LIMIT 1", $user_id ) );
			$at          = current_time( 'mysql', true );
			$updated     = $wpdb->update(
				$table,
				array(
					'user_id'     => (int) $user_id,
					'is_primary'  => $has_primary ? 0 : 1,
					'verified_at' => $at,
				),
				array( 'phone_hash' => $hash ),
				array( '%d', '%d', '%s' ),
				array( '%s' )
			);
			if ( false === $updated ) {
				return new WP_Error( 'abid_phone_transfer_failed', __( 'The verified phone number could not be transferred. No account link was changed.', 'abibitumi-id' ), array( 'status' => 500 ) );
			}
			self::clear_stale_legacy_number( $old_user_id, $phone );
			self::promote_primary_if_needed( $old_user_id );
			if ( ! $has_primary ) {
				self::sync_legacy_primary( $user_id, $phone, $at );
			}
			do_action( 'abid_phone_verified', (int) $user_id, $phone );
			return true;
		}
		$at     = current_time( 'mysql', true );
		$result = self::insert_number( (int) $user_id, $phone, $at );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! get_user_meta( $user_id, ABID_Identity::PHONE_HASH_META, true ) || self::is_primary( $user_id, $hash ) ) {
			self::sync_legacy_primary( $user_id, $phone, $at );
		}
		do_action( 'abid_phone_verified', (int) $user_id, $phone );
		return true;
	}

	private static function sync_legacy_primary( $user_id, $phone, $verified_at ) {
		update_user_meta( $user_id, ABID_Identity::PHONE_META, ABID_OTP_Store::normalize_phone( $phone ) );
		update_user_meta( $user_id, ABID_Identity::PHONE_HASH_META, ABID_OTP_Store::phone_hash( $phone ) );
		update_user_meta( $user_id, ABID_Identity::VERIFIED_AT_META, $verified_at );
	}

	private static function clear_stale_legacy_number( $user_id, $phone ) {
		if ( hash_equals( (string) get_user_meta( $user_id, ABID_Identity::PHONE_HASH_META, true ), ABID_OTP_Store::phone_hash( $phone ) ) ) {
			delete_user_meta( $user_id, ABID_Identity::PHONE_META );
			delete_user_meta( $user_id, ABID_Identity::PHONE_HASH_META );
			delete_user_meta( $user_id, ABID_Identity::VERIFIED_AT_META );
		}
	}

	private static function promote_primary_if_needed( $user_id ) {
		global $wpdb;
		$table = self::table();
		$has   = $wpdb->get_var( $wpdb->prepare( "SELECT phone_hash FROM {$table} WHERE user_id = %d AND is_primary = 1 LIMIT 1", $user_id ) );
		if ( $has ) {
			return;
		}
		$next = $wpdb->get_row( $wpdb->prepare( "SELECT phone_hash, phone_e164, verified_at FROM {$table} WHERE user_id = %d ORDER BY created_at ASC LIMIT 1", $user_id ) );
		if ( $next ) {
			$wpdb->update( $table, array( 'is_primary' => 1 ), array( 'phone_hash' => $next->phone_hash ), array( '%d' ), array( '%s' ) );
			self::sync_legacy_primary( $user_id, $next->phone_e164, $next->verified_at );
		}
	}

	private static function is_primary( $user_id, $hash ) {
		global $wpdb;
		$table = self::table();
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT phone_hash FROM {$table} WHERE user_id = %d AND phone_hash = %s AND is_primary = 1", $user_id, $hash ) );
	}

	public static function find_user_id( $phone ) {
		global $wpdb;
		$table = self::table();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$table} WHERE phone_hash = %s LIMIT 1", ABID_OTP_Store::phone_hash( $phone ) ) );
	}

	public static function user_phones( $user_id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT phone_hash, phone_e164, is_primary, verified_at FROM {$table} WHERE user_id = %d ORDER BY is_primary DESC, created_at ASC", $user_id ), ARRAY_A );
	}

	public static function has_verified_phone( $user_id ) {
		global $wpdb;
		$table = self::table();
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $user_id ) );
		return $count > 0 || (bool) get_user_meta( (int) $user_id, ABID_Identity::VERIFIED_AT_META, true );
	}

	public static function user_for_hash( $hash ) {
		global $wpdb;
		$table = self::table();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$table} WHERE phone_hash = %s LIMIT 1", $hash ) );
	}

	public function render_profile( $user ) {
		if ( ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}
		$numbers = self::user_phones( $user->ID );
		?>
		<h2><?php esc_html_e( 'Abibitumi ID phone numbers', 'abibitumi-id' ); ?></h2>
		<p><?php esc_html_e( 'Manage verified phone numbers for Abibitumi ID. New numbers are linked only after SMS verification.', 'abibitumi-id' ); ?></p>
		<table class="widefat striped" style="max-width:760px"><thead><tr><th><?php esc_html_e( 'Verified number', 'abibitumi-id' ); ?></th><th><?php esc_html_e( 'Status', 'abibitumi-id' ); ?></th></tr></thead><tbody>
		<?php if ( $numbers ) : foreach ( $numbers as $number ) : ?>
			<tr><td><?php echo esc_html( self::mask_phone( $number['phone_e164'] ) ); ?></td><td><?php echo ! empty( $number['is_primary'] ) ? esc_html__( 'Primary', 'abibitumi-id' ) : esc_html__( 'Verified', 'abibitumi-id' ); ?></td></tr>
		<?php endforeach; else : ?><tr><td colspan="2"><?php esc_html_e( 'No verified phone numbers are linked yet.', 'abibitumi-id' ); ?></td></tr><?php endif; ?>
		</tbody></table>
		<p><a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'abid-phone-management', 'user_id' => (int) $user->ID ), admin_url( 'users.php' ) ) ); ?>"><?php esc_html_e( 'Manage phone numbers', 'abibitumi-id' ); ?></a></p>
		<?php
	}

	public function render_management_page() {
		$user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : get_current_user_id();
		$user    = get_user_by( 'id', $user_id );
		if ( ! $user || ! current_user_can( 'edit_user', $user_id ) ) {
			wp_die( esc_html__( 'You are not allowed to manage this account.', 'abibitumi-id' ), 403 );
		}
		$numbers = self::user_phones( $user_id );
		$pending = get_transient( self::pending_key( get_current_user_id(), $user_id ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Abibitumi ID phone numbers', 'abibitumi-id' ); ?></h1>
			<p><?php esc_html_e( 'Add numbers only when you can receive their SMS verification code. Each number can belong to one account; changing ownership requires a fresh code.', 'abibitumi-id' ); ?></p>
			<table class="widefat striped" style="max-width:900px"><thead><tr><th><?php esc_html_e( 'Verified number', 'abibitumi-id' ); ?></th><th><?php esc_html_e( 'Status', 'abibitumi-id' ); ?></th><th><?php esc_html_e( 'Actions', 'abibitumi-id' ); ?></th></tr></thead><tbody>
			<?php if ( $numbers ) : foreach ( $numbers as $number ) : ?>
				<tr><td><?php echo esc_html( self::mask_phone( $number['phone_e164'] ) ); ?></td><td><?php echo ! empty( $number['is_primary'] ) ? esc_html__( 'Primary', 'abibitumi-id' ) : esc_html__( 'Verified', 'abibitumi-id' ); ?></td><td>
					<?php if ( empty( $number['is_primary'] ) ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block"><?php wp_nonce_field( 'abid_phone_manage_' . $user_id ); ?><input type="hidden" name="action" value="abid_phone_primary"><input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>"><input type="hidden" name="phone_hash" value="<?php echo esc_attr( $number['phone_hash'] ); ?>"><button class="button" type="submit"><?php esc_html_e( 'Make primary', 'abibitumi-id' ); ?></button></form>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-left:6px"><?php wp_nonce_field( 'abid_phone_manage_' . $user_id ); ?><input type="hidden" name="action" value="abid_phone_remove"><input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>"><input type="hidden" name="phone_hash" value="<?php echo esc_attr( $number['phone_hash'] ); ?>"><button class="button" type="submit"><?php esc_html_e( 'Remove', 'abibitumi-id' ); ?></button></form>
					<?php endif; ?>
				</td></tr>
			<?php endforeach; else : ?><tr><td colspan="3"><?php esc_html_e( 'No verified phone numbers are linked yet.', 'abibitumi-id' ); ?></td></tr><?php endif; ?>
			</tbody></table>
			<h2><?php esc_html_e( 'Add a phone number', 'abibitumi-id' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'abid_phone_manage_' . $user_id ); ?>
				<input type="hidden" name="action" value="abid_phone_add_start">
				<input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>">
				<label for="abid-phone-add-<?php echo esc_attr( $user_id ); ?>"><?php esc_html_e( 'Phone number', 'abibitumi-id' ); ?></label><br>
				<input class="regular-text" type="tel" name="phone" id="abid-phone-add-<?php echo esc_attr( $user_id ); ?>" required autocomplete="tel" placeholder="+233…">
				<?php submit_button( __( 'Send verification code', 'abibitumi-id' ), 'secondary', 'submit', false ); ?>
			</form>
			<?php if ( is_array( $pending ) && ! empty( $pending['phone'] ) ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px">
					<?php wp_nonce_field( 'abid_phone_manage_' . $user_id ); ?>
					<input type="hidden" name="action" value="abid_phone_add_verify">
					<input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>">
					<input type="hidden" name="phone" value="<?php echo esc_attr( $pending['phone'] ); ?>">
					<label for="abid-phone-code-<?php echo esc_attr( $user_id ); ?>"><?php esc_html_e( 'SMS code', 'abibitumi-id' ); ?></label><br>
					<input class="small-text" type="text" name="code" id="abid-phone-code-<?php echo esc_attr( $user_id ); ?>" inputmode="numeric" autocomplete="one-time-code" required>
					<?php submit_button( __( 'Verify and link number', 'abibitumi-id' ), 'primary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	private function authorize_target() {
		$user_id = absint( $_POST['user_id'] ?? 0 );
		check_admin_referer( 'abid_phone_manage_' . $user_id );
		if ( ! $user_id || ! current_user_can( 'edit_user', $user_id ) ) {
			wp_die( esc_html__( 'You are not allowed to manage this account.', 'abibitumi-id' ), 403 );
		}
		return $user_id;
	}

	public function start_add() {
		$user_id = $this->authorize_target();
		$phone   = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
		$result  = ABID_Phone_Login::start( $phone, $user_id );
		if ( is_wp_error( $result ) ) {
			$this->redirect_notice( $user_id, $result->get_error_message() );
		}
		$normalized = ABID_OTP_Store::normalize_phone( $phone );
		set_transient( self::pending_key( get_current_user_id(), $user_id ), array( 'phone' => $normalized ), 10 * MINUTE_IN_SECONDS );
		$this->redirect_notice( $user_id, __( 'Verification code sent. Enter it below to link the number.', 'abibitumi-id' ) );
	}

	public function verify_add() {
		$user_id = $this->authorize_target();
		$phone   = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
		$code    = preg_replace( '/\D/', '', (string) wp_unslash( $_POST['code'] ?? '' ) );
		$result  = ABID_Phone_Login::verify( $phone, $code, $user_id, false, true );
		if ( is_wp_error( $result ) ) {
			$this->redirect_notice( $user_id, $result->get_error_message() );
		}
		delete_transient( self::pending_key( get_current_user_id(), $user_id ) );
		$this->redirect_notice( $user_id, __( 'Phone number verified and linked to this Abibitumi ID account.', 'abibitumi-id' ), true );
	}

	public function remove() {
		$user_id = $this->authorize_target();
		$hash    = sanitize_text_field( wp_unslash( $_POST['phone_hash'] ?? '' ) );
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $hash ) ) {
			$this->redirect_notice( $user_id, __( 'Invalid phone number selection.', 'abibitumi-id' ) );
		}
		global $wpdb;
		$table = self::table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT is_primary FROM {$table} WHERE user_id = %d AND phone_hash = %s", $user_id, $hash ) );
		if ( ! $row || ! empty( $row->is_primary ) ) {
			$this->redirect_notice( $user_id, __( 'Select a secondary number to remove. The primary number must be changed first.', 'abibitumi-id' ) );
		}
		$wpdb->delete( $table, array( 'user_id' => $user_id, 'phone_hash' => $hash ), array( '%d', '%s' ) );
		$this->redirect_notice( $user_id, __( 'Phone number removed from this account.', 'abibitumi-id' ), true );
	}

	public function set_primary() {
		$user_id = $this->authorize_target();
		$hash    = sanitize_text_field( wp_unslash( $_POST['phone_hash'] ?? '' ) );
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $hash ) ) {
			$this->redirect_notice( $user_id, __( 'Invalid phone number selection.', 'abibitumi-id' ) );
		}
		global $wpdb;
		$table = self::table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT phone_e164, verified_at FROM {$table} WHERE user_id = %d AND phone_hash = %s", $user_id, $hash ) );
		if ( ! $row ) {
			$this->redirect_notice( $user_id, __( 'That verified number is not linked to this account.', 'abibitumi-id' ) );
		}
		$wpdb->update( $table, array( 'is_primary' => 0 ), array( 'user_id' => $user_id ), array( '%d' ), array( '%d' ) );
		$wpdb->update( $table, array( 'is_primary' => 1 ), array( 'user_id' => $user_id, 'phone_hash' => $hash ), array( '%d' ), array( '%d', '%s' ) );
		self::sync_legacy_primary( $user_id, $row->phone_e164, $row->verified_at );
		do_action( 'abid_phone_verified', (int) $user_id, $row->phone_e164 );
		$this->redirect_notice( $user_id, __( 'Primary sign-in number updated.', 'abibitumi-id' ), true );
	}

	public function render_notice() {
		$key    = 'abid_phone_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}
		delete_transient( $key );
		$class = ! empty( $notice['success'] ) ? 'notice notice-success is-dismissible' : 'notice notice-error';
		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $notice['message'] ) );
	}

	private static function pending_key( $actor_id, $target_id ) {
		return 'abid_phone_add_' . absint( $actor_id ) . '_' . absint( $target_id );
	}

	private static function mask_phone( $phone ) {
		$digits = preg_replace( '/\D/', '', (string) $phone );
		return '+' . str_repeat( '•', max( 0, strlen( $digits ) - 4 ) ) . substr( $digits, -4 );
	}

	private function redirect_notice( $user_id, $message, $success = false ) {
		set_transient( 'abid_phone_notice_' . get_current_user_id(), array( 'message' => $message, 'success' => $success ), MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'user_id', (int) $user_id, admin_url( 'user-edit.php' ) ) );
		exit;
	}
}
