<?php
/**
 * Phone-first login UI.
 *
 * @package AbibitumiID
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABID_Login_UI {
	public function init() {
		add_shortcode( 'abid_phone_login', array( $this, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_login_assets' ) );
		add_filter( 'login_message', array( $this, 'login_message' ) );
	}

	public function register_assets() {
		wp_register_style( 'abid-login', ABID_URL . 'assets/css/login.css', array(), ABID_VERSION );
		wp_register_script( 'abid-login', ABID_URL . 'assets/js/login.js', array(), ABID_VERSION, true );
	}

	public function enqueue_login_assets() {
		$this->register_assets();
		if ( ABID_Settings::get( 'show_on_wp_login', 1 ) ) {
			$this->enqueue_assets( ABID_Settings::get( 'login_redirect_url', '' ) );
		}
	}

	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'redirect' => ABID_Settings::get( 'login_redirect_url', '' ),
			),
			$atts,
			'abid_phone_login'
		);

		$this->enqueue_assets( $atts['redirect'] );
		return $this->render_widget();
	}

	public function login_message( $message ) {
		if ( ! ABID_Settings::get( 'show_on_wp_login', 1 ) ) {
			return $message;
		}
		return $message . $this->render_widget();
	}

	private function enqueue_assets( $redirect ) {
		wp_enqueue_style( 'abid-login' );
		wp_enqueue_script( 'abid-login' );
		wp_localize_script(
			'abid-login',
			'ABIDLogin',
			array(
				'api'      => esc_url_raw( rest_url( ABID_REST::NS ) ),
				'redirect' => esc_url_raw( $redirect ),
				'country'  => ABID_Settings::get( 'default_phone_country', 'GH' ),
				'i18n'     => array(
					'phoneRequired' => __( 'Enter your phone number.', 'abibitumi-id' ),
					'codeRequired'  => __( 'Enter the code we sent.', 'abibitumi-id' ),
					'sending'       => __( 'Sending code...', 'abibitumi-id' ),
					'verifying'     => __( 'Checking code...', 'abibitumi-id' ),
					'sent'          => __( 'Code sent. Enter it below.', 'abibitumi-id' ),
					'ready'         => __( 'You are signed in.', 'abibitumi-id' ),
					'tryAgain'      => __( 'Try again.', 'abibitumi-id' ),
				),
			)
		);
	}

	private function render_widget() {
		$sso_url = $this->ecosystem_sso_url();
		ob_start();
		?>
		<div class="abid-login" data-abid-login>
			<?php if ( $sso_url ) : ?>
				<a class="abid-login__sso" href="<?php echo esc_url( $sso_url ); ?>"><?php esc_html_e( 'Continue with Abibitumi ID', 'abibitumi-id' ); ?></a>
				<div class="abid-login__divider"><span><?php esc_html_e( 'or', 'abibitumi-id' ); ?></span></div>
			<?php endif; ?>
			<form class="abid-login__form" data-step="phone">
				<div class="abid-login__field">
					<label for="abid_phone"><?php esc_html_e( 'Phone number', 'abibitumi-id' ); ?></label>
					<input id="abid_phone" name="phone" type="tel" autocomplete="tel" inputmode="tel" placeholder="024 123 4567" required>
				</div>
				<div class="abid-login__field abid-login__code" hidden>
					<label for="abid_code"><?php esc_html_e( 'Code', 'abibitumi-id' ); ?></label>
					<input id="abid_code" name="code" type="text" autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]*" maxlength="8">
				</div>
				<button type="submit" class="abid-login__button" data-label-start="<?php esc_attr_e( 'Continue', 'abibitumi-id' ); ?>" data-label-verify="<?php esc_attr_e( 'Sign in', 'abibitumi-id' ); ?>"><?php esc_html_e( 'Continue', 'abibitumi-id' ); ?></button>
				<button type="button" class="abid-login__link" data-action="change" hidden><?php esc_html_e( 'Change number', 'abibitumi-id' ); ?></button>
				<p class="abid-login__status" role="status" aria-live="polite"></p>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	private function ecosystem_sso_url() {
		if ( ! ABID_Settings::get( 'ecosystem_sso_enabled', 1 ) ) {
			return '';
		}

		$identity_url = ABID_Settings::get( 'ecosystem_identity_url', 'https://abibitumi.com' );
		$identity_host = wp_parse_url( $identity_url, PHP_URL_HOST );
		$current_host  = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! $identity_url || ! $identity_host || strtolower( $identity_host ) === strtolower( (string) $current_host ) ) {
			return '';
		}

		$return_to = is_ssl() ? 'https://' : 'http://';
		$return_to .= isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : $current_host;
		$return_to .= isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

		return trailingslashit( $identity_url ) . 'wp-json/' . ABID_REST::NS . '/sso/start?return_to=' . rawurlencode( $return_to );
	}
}
