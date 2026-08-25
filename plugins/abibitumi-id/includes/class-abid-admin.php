<?php
/**
 * Admin settings.
 *
 * @package AbibitumiID
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABID_Admin {
	public function init() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_abid_save_settings', array( $this, 'save' ) );
	}

	public function menu() {
		add_options_page(
			__( 'Abibitumi ID', 'abibitumi-id' ),
			__( 'Abibitumi ID', 'abibitumi-id' ),
			'manage_options',
			'abibitumi-id',
			array( $this, 'render' )
		);
	}

	public function render() {
		$s = ABID_Settings::all();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Abibitumi ID', 'abibitumi-id' ); ?></h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="abid_save_settings">
				<?php wp_nonce_field( 'abid_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="sms_provider"><?php esc_html_e( 'SMS provider', 'abibitumi-id' ); ?></label></th>
						<td>
							<select id="sms_provider" name="sms_provider">
								<?php foreach ( array( 'log', 'hubtel', 'africastalking', 'twilio', 'vonage' ) as $provider ) : ?>
									<option value="<?php echo esc_attr( $provider ); ?>" <?php selected( $s['sms_provider'], $provider ); ?>><?php echo esc_html( $provider ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr><th scope="row"><?php esc_html_e( 'Hubtel', 'abibitumi-id' ); ?></th><td>
						<input class="regular-text" name="hubtel_client_id" placeholder="Client ID" value="<?php echo esc_attr( $s['hubtel_client_id'] ); ?>"><br>
						<input class="regular-text" name="hubtel_client_secret" placeholder="Client Secret" type="password" value="<?php echo esc_attr( $s['hubtel_client_secret'] ); ?>"><br>
						<input class="regular-text" name="hubtel_from" placeholder="Sender ID" maxlength="11" value="<?php echo esc_attr( $s['hubtel_from'] ); ?>"><br>
						<input class="regular-text" name="hubtel_endpoint" placeholder="Endpoint" value="<?php echo esc_attr( $s['hubtel_endpoint'] ); ?>">
						<p class="description"><?php esc_html_e( 'Best Ghana-first option. Sender ID must be approved by Hubtel and no more than 11 characters.', 'abibitumi-id' ); ?></p>
					</td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Twilio', 'abibitumi-id' ); ?></th><td>
						<input class="regular-text" name="twilio_sid" placeholder="Account SID" value="<?php echo esc_attr( $s['twilio_sid'] ); ?>"><br>
						<input class="regular-text" name="twilio_token" placeholder="Auth token" type="password" value="<?php echo esc_attr( $s['twilio_token'] ); ?>"><br>
						<input class="regular-text" name="twilio_from" placeholder="From" value="<?php echo esc_attr( $s['twilio_from'] ); ?>">
					</td></tr>
					<tr><th scope="row"><?php esc_html_e( "Africa's Talking", 'abibitumi-id' ); ?></th><td>
						<input class="regular-text" name="africastalking_user" placeholder="Username" value="<?php echo esc_attr( $s['africastalking_user'] ); ?>"><br>
						<input class="regular-text" name="africastalking_key" placeholder="API key" type="password" value="<?php echo esc_attr( $s['africastalking_key'] ); ?>"><br>
						<input class="regular-text" name="africastalking_from" placeholder="From" value="<?php echo esc_attr( $s['africastalking_from'] ); ?>">
					</td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Vonage', 'abibitumi-id' ); ?></th><td>
						<input class="regular-text" name="vonage_key" placeholder="API key" value="<?php echo esc_attr( $s['vonage_key'] ); ?>"><br>
						<input class="regular-text" name="vonage_secret" placeholder="API secret" type="password" value="<?php echo esc_attr( $s['vonage_secret'] ); ?>"><br>
						<input class="regular-text" name="vonage_from" placeholder="From" value="<?php echo esc_attr( $s['vonage_from'] ); ?>">
					</td></tr>
					<tr><th scope="row"><?php esc_html_e( 'OTP policy', 'abibitumi-id' ); ?></th><td>
						<label><?php esc_html_e( 'Default phone country', 'abibitumi-id' ); ?> <input class="small-text" maxlength="2" name="default_phone_country" value="<?php echo esc_attr( $s['default_phone_country'] ); ?>"></label>
						<p class="description"><?php esc_html_e( 'Used to normalize local numbers. Use GH for Ghana so 0241234567 becomes +233241234567.', 'abibitumi-id' ); ?></p>
						<label><?php esc_html_e( 'TTL minutes', 'abibitumi-id' ); ?> <input type="number" min="1" name="otp_ttl_minutes" value="<?php echo esc_attr( $s['otp_ttl_minutes'] ); ?>"></label><br>
						<label><?php esc_html_e( 'Max attempts', 'abibitumi-id' ); ?> <input type="number" min="1" name="max_attempts" value="<?php echo esc_attr( $s['max_attempts'] ); ?>"></label><br>
						<label><input type="checkbox" name="allow_registration" value="1" <?php checked( $s['allow_registration'] ); ?>> <?php esc_html_e( 'Create accounts automatically for newly verified phone numbers', 'abibitumi-id' ); ?></label><br>
						<label><?php esc_html_e( 'Login redirect URL', 'abibitumi-id' ); ?> <input class="regular-text" name="login_redirect_url" value="<?php echo esc_attr( $s['login_redirect_url'] ); ?>"></label>
						<p class="description"><?php esc_html_e( 'Use the [abid_phone_login] shortcode on a login page. Leave redirect blank to reload the page after login.', 'abibitumi-id' ); ?></p>
						<label><input type="checkbox" name="show_on_wp_login" value="1" <?php checked( $s['show_on_wp_login'] ); ?>> <?php esc_html_e( 'Show phone login on the standard WordPress login screen', 'abibitumi-id' ); ?></label>
					</td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Push login', 'abibitumi-id' ); ?></th><td>
						<label><input type="checkbox" name="push_login_enabled" value="1" <?php checked( $s['push_login_enabled'] ); ?>> <?php esc_html_e( 'Allow trusted-device push approval for repeat login', 'abibitumi-id' ); ?></label>
						<p class="description"><?php esc_html_e( 'First login still requires phone verification. After that, app clients can register a Firebase token and approve future sign-ins by push notification.', 'abibitumi-id' ); ?></p>
						<input class="regular-text" name="firebase_project_id" placeholder="Firebase project ID" value="<?php echo esc_attr( $s['firebase_project_id'] ); ?>"><br>
						<input class="regular-text" name="firebase_client_email" placeholder="Firebase service account client email" value="<?php echo esc_attr( $s['firebase_client_email'] ); ?>"><br>
						<textarea class="large-text code" name="firebase_private_key" rows="5" placeholder="Firebase service account private key"><?php echo esc_textarea( $s['firebase_private_key'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Store a Firebase service account for Cloud Messaging only. Keep this key private.', 'abibitumi-id' ); ?></p>
					</td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Platform integrations', 'abibitumi-id' ); ?></th><td>
						<label><?php esc_html_e( 'BuddyBoss phone profile field ID', 'abibitumi-id' ); ?> <input type="number" min="0" name="buddyboss_phone_field_id" value="<?php echo esc_attr( $s['buddyboss_phone_field_id'] ); ?>"></label>
						<p class="description"><?php esc_html_e( 'When set, verified phone numbers are synced into this BuddyBoss/BuddyPress xProfile field.', 'abibitumi-id' ); ?></p>
						<label><input type="checkbox" name="contact_discovery_enabled" value="1" <?php checked( $s['contact_discovery_enabled'] ); ?>> <?php esc_html_e( 'Allow verified users to find contacts who already have Abibitumi accounts', 'abibitumi-id' ); ?></label><br>
						<label><?php esc_html_e( 'Contact lookup limit', 'abibitumi-id' ); ?> <input type="number" min="1" max="1000" name="contact_discovery_limit" value="<?php echo esc_attr( $s['contact_discovery_limit'] ); ?>"></label><br>
						<label><input type="checkbox" name="better_messages_verified_badge" value="1" <?php checked( $s['better_messages_verified_badge'] ); ?>> <?php esc_html_e( 'Use verified phone status as a Better Messages verified badge source', 'abibitumi-id' ); ?></label><br>
						<label><input type="checkbox" name="better_messages_require_verified_phone" value="1" <?php checked( $s['better_messages_require_verified_phone'] ); ?>> <?php esc_html_e( 'Require verified phone before sending Better Messages messages', 'abibitumi-id' ); ?></label>
					</td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Admin entitlements', 'abibitumi-id' ); ?></th><td>
						<label><input type="checkbox" name="admin_entitlements_enabled" value="1" <?php checked( $s['admin_entitlements_enabled'] ); ?>> <?php esc_html_e( 'Grant administrator role to matched Abibitumi ID identities on this site', 'abibitumi-id' ); ?></label>
						<p class="description"><?php esc_html_e( 'This promotes matched users after login or phone verification. It does not grant network super admin.', 'abibitumi-id' ); ?></p>
						<label for="admin_entitlement_emails"><?php esc_html_e( 'Admin email aliases', 'abibitumi-id' ); ?></label><br>
						<textarea id="admin_entitlement_emails" name="admin_entitlement_emails" class="large-text code" rows="4"><?php echo esc_textarea( $s['admin_entitlement_emails'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One trusted admin email per line. Use this for known alias emails across sites.', 'abibitumi-id' ); ?></p>
						<label for="admin_entitlement_phones"><?php esc_html_e( 'Admin verified phones or hashes', 'abibitumi-id' ); ?></label><br>
						<textarea id="admin_entitlement_phones" name="admin_entitlement_phones" class="large-text code" rows="4"><?php echo esc_textarea( $s['admin_entitlement_phone_hashes'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Enter E.164 phone numbers or sha256 hashes. Phone numbers are converted to hashes when saved.', 'abibitumi-id' ); ?></p>
					</td></tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'abibitumi-id' ) );
		}
		check_admin_referer( 'abid_settings' );
		$input = wp_unslash( $_POST );
		ABID_Settings::update(
			array(
				'sms_provider'        => sanitize_key( $input['sms_provider'] ?? 'log' ),
				'twilio_sid'          => sanitize_text_field( $input['twilio_sid'] ?? '' ),
				'twilio_token'        => sanitize_text_field( $input['twilio_token'] ?? '' ),
				'twilio_from'         => sanitize_text_field( $input['twilio_from'] ?? '' ),
				'africastalking_user' => sanitize_text_field( $input['africastalking_user'] ?? '' ),
				'africastalking_key'  => sanitize_text_field( $input['africastalking_key'] ?? '' ),
				'africastalking_from' => sanitize_text_field( $input['africastalking_from'] ?? '' ),
				'hubtel_client_id'    => sanitize_text_field( $input['hubtel_client_id'] ?? '' ),
				'hubtel_client_secret' => sanitize_text_field( $input['hubtel_client_secret'] ?? '' ),
				'hubtel_from'         => substr( sanitize_text_field( $input['hubtel_from'] ?? 'ABIBITUMI' ), 0, 11 ),
				'hubtel_endpoint'     => esc_url_raw( $input['hubtel_endpoint'] ?? 'https://devp-sms03726-api.hubtel.com/v1/messages/send' ),
				'vonage_key'          => sanitize_text_field( $input['vonage_key'] ?? '' ),
				'vonage_secret'       => sanitize_text_field( $input['vonage_secret'] ?? '' ),
				'vonage_from'         => sanitize_text_field( $input['vonage_from'] ?? '' ),
				'otp_ttl_minutes'     => max( 1, absint( $input['otp_ttl_minutes'] ?? 10 ) ),
				'default_phone_country' => strtoupper( substr( sanitize_key( $input['default_phone_country'] ?? 'GH' ), 0, 2 ) ) ?: 'GH',
				'max_attempts'        => max( 1, absint( $input['max_attempts'] ?? 5 ) ),
				'allow_registration'  => empty( $input['allow_registration'] ) ? 0 : 1,
				'login_redirect_url'  => esc_url_raw( $input['login_redirect_url'] ?? '' ),
				'show_on_wp_login'    => empty( $input['show_on_wp_login'] ) ? 0 : 1,
				'push_login_enabled'  => empty( $input['push_login_enabled'] ) ? 0 : 1,
				'firebase_project_id' => sanitize_text_field( $input['firebase_project_id'] ?? '' ),
				'firebase_client_email' => sanitize_email( $input['firebase_client_email'] ?? '' ),
				'firebase_private_key' => sanitize_textarea_field( $input['firebase_private_key'] ?? '' ),
				'buddyboss_phone_field_id' => absint( $input['buddyboss_phone_field_id'] ?? 0 ),
				'contact_discovery_enabled' => empty( $input['contact_discovery_enabled'] ) ? 0 : 1,
				'contact_discovery_limit' => max( 1, min( 1000, absint( $input['contact_discovery_limit'] ?? 250 ) ) ),
				'better_messages_verified_badge' => empty( $input['better_messages_verified_badge'] ) ? 0 : 1,
				'better_messages_require_verified_phone' => empty( $input['better_messages_require_verified_phone'] ) ? 0 : 1,
				'admin_entitlements_enabled' => empty( $input['admin_entitlements_enabled'] ) ? 0 : 1,
				'admin_entitlement_emails' => implode( "\n", ABID_Admin_Entitlements::parse_email_list( $input['admin_entitlement_emails'] ?? '' ) ),
				'admin_entitlement_phone_hashes' => implode( "\n", ABID_Admin_Entitlements::parse_phone_hash_list( $input['admin_entitlement_phones'] ?? '' ) ),
			)
		);
		wp_safe_redirect( add_query_arg( array( 'page' => 'abibitumi-id', 'updated' => '1' ), admin_url( 'options-general.php' ) ) );
		exit;
	}
}
