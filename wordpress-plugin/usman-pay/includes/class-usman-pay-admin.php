<?php
/**
 * Settings screen: connect this site to the platform.
 *
 * The whole screen is one job — paste a setup token, press connect. There is
 * deliberately no field anywhere in this plugin for a Stripe key, because a
 * WordPress site is the single worst place to keep one: it is the most-attacked
 * surface in the stack, and every other plugin on the site can read the options
 * table.
 *
 * @package usman-pay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Usman_Pay_Admin {

	private const PAGE_SLUG = 'usman-pay';

	private const NONCE_ACTION = 'usman_pay_connect';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_usman_pay_connect', array( $this, 'handle_connect' ) );
		add_action( 'admin_post_usman_pay_disconnect', array( $this, 'handle_disconnect' ) );
	}

	public function add_menu(): void {
		add_options_page(
			__( 'Usman Pay', 'usman-pay' ),
			__( 'Usman Pay', 'usman-pay' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage this plugin.', 'usman-pay' ) );
		}

		$connected = Usman_Pay_Credentials::is_connected();
		$notice    = get_transient( 'usman_pay_notice' );

		if ( $notice ) {
			delete_transient( 'usman_pay_notice' );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Usman Pay', 'usman-pay' ); ?></h1>

			<?php if ( $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
					<p><?php echo esc_html( $notice['message'] ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( $connected ) : ?>
				<div class="notice notice-success inline">
					<p>
						<strong><?php esc_html_e( 'Connected', 'usman-pay' ); ?></strong> —
						<?php
						printf(
							/* translators: %s: site identifier on the platform. */
							esc_html__( 'This site is linked to the platform as %s.', 'usman-pay' ),
							'<code>' . esc_html( Usman_Pay_Credentials::site_id() ) . '</code>'
						);
						?>
					</p>
				</div>

				<p><?php esc_html_e( 'Payments from this site are processed by the platform. Stripe credentials are held there and are never sent to WordPress.', 'usman-pay' ); ?></p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( self::NONCE_ACTION ); ?>
					<input type="hidden" name="action" value="usman_pay_disconnect">
					<?php submit_button( __( 'Disconnect this site', 'usman-pay' ), 'delete' ); ?>
				</form>

			<?php else : ?>
				<p><?php esc_html_e( 'Generate a setup token in your platform dashboard, then paste it below. The token is valid for 15 minutes and can be used once.', 'usman-pay' ); ?></p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( self::NONCE_ACTION ); ?>
					<input type="hidden" name="action" value="usman_pay_connect">

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="usman_pay_setup_token"><?php esc_html_e( 'Setup token', 'usman-pay' ); ?></label>
							</th>
							<td>
								<input
									type="text"
									id="usman_pay_setup_token"
									name="setup_token"
									class="regular-text code"
									autocomplete="off"
									spellcheck="false"
									placeholder="ust_…"
									required>
								<p class="description">
									<?php esc_html_e( 'Your site must be reachable from the internet over HTTPS — the platform calls back to confirm the plugin is installed here.', 'usman-pay' ); ?>
								</p>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Connect to platform', 'usman-pay' ) ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Two-leg connect: exchange the token for credentials, then prove ownership.
	 */
	public function handle_connect(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'usman-pay' ) );
		}

		check_admin_referer( self::NONCE_ACTION );

		$token = isset( $_POST['setup_token'] )
			? sanitize_text_field( wp_unslash( $_POST['setup_token'] ) )
			: '';

		if ( '' === $token ) {
			$this->redirect_with_notice( 'error', __( 'Enter a setup token.', 'usman-pay' ) );
		}

		$client = new Usman_Pay_Client();
		$result = $client->register( $token );

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_notice( 'error', $result->get_error_message() );
		}

		// Store before verifying: the platform's challenge callback arrives
		// during the verify() call below, and cannot be answered without these.
		Usman_Pay_Credentials::store(
			$result['site_id'],
			$result['site_key'],
			$result['site_secret']
		);
		Usman_Pay_Credentials::mark_pending();

		$verified = $client->verify();

		if ( is_wp_error( $verified ) ) {
			// Credentials are kept so the operator can retry without burning a
			// second setup token, but the site stays unverified and cannot transact.
			$this->redirect_with_notice(
				'error',
				sprintf(
					/* translators: %s: error message from the platform. */
					__( 'Connected, but ownership could not be confirmed: %s', 'usman-pay' ),
					$verified->get_error_message()
				)
			);
		}

		Usman_Pay_Credentials::store(
			$result['site_id'],
			$result['site_key'],
			$result['site_secret']
		);

		$this->redirect_with_notice( 'success', __( 'This site is now connected and verified.', 'usman-pay' ) );
	}

	public function handle_disconnect(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'usman-pay' ) );
		}

		check_admin_referer( self::NONCE_ACTION );

		// Best effort: revoke on the platform first, but always clear locally.
		// A site that cannot reach the platform must still be able to disconnect.
		( new Usman_Pay_Client() )->disconnect();

		Usman_Pay_Credentials::forget();

		$this->redirect_with_notice( 'success', __( 'This site has been disconnected.', 'usman-pay' ) );
	}

	private function redirect_with_notice( string $type, string $message ): void {
		set_transient( 'usman_pay_notice', array( 'type' => $type, 'message' => $message ), 60 );

		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) );
		exit;
	}
}
