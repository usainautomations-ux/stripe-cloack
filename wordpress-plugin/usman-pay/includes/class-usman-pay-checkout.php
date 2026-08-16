<?php
/**
 * Sending a shopper to the platform's hosted checkout.
 *
 * The redirect is the mechanism that keeps the client domain away from Stripe:
 * the card form is served by the platform, so the browser's origin and referrer
 * belong to the platform for the whole of the payment.
 *
 * @package usman-pay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Usman_Pay_Checkout {

	public function register(): void {
		// Exposed for themes and other plugins to call directly.
		add_action( 'usman_pay_start_checkout', array( $this, 'start' ), 10, 1 );
		add_shortcode( 'usman_pay_button', array( $this, 'render_button' ) );
		add_action( 'admin_post_nopriv_usman_pay_checkout', array( $this, 'handle_checkout_request' ) );
		add_action( 'admin_post_usman_pay_checkout', array( $this, 'handle_checkout_request' ) );
	}

	/**
	 * Open an order on the platform and return where to send the shopper.
	 *
	 * @param array $order See Usman_Pay_Client::create_payment().
	 * @return string|WP_Error Checkout URL.
	 */
	public function start( array $order ) {
		if ( ! Usman_Pay_Credentials::is_connected() ) {
			return new WP_Error(
				'usman_pay_not_connected',
				__( 'This site is not connected to the payment platform.', 'usman-pay' )
			);
		}

		$order = wp_parse_args(
			$order,
			array(
				'currency'   => 'usd',
				'return_url' => home_url( '/' ),
			)
		);

		if ( empty( $order['order_ref'] ) || empty( $order['amount'] ) ) {
			return new WP_Error(
				'usman_pay_invalid_order',
				__( 'An order needs both a reference and an amount.', 'usman-pay' )
			);
		}

		// Minor units, integer. Passing a float here is the classic way to
		// charge someone 4998 cents instead of 4999.
		$order['amount'] = (int) round( $order['amount'] );

		$response = ( new Usman_Pay_Client() )->create_payment( $order );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response['checkout_url'];
	}

	/**
	 * [usman_pay_button amount="4999" currency="usd" label="Buy now"]
	 */
	public function render_button( $atts ): string {
		$atts = shortcode_atts(
			array(
				'amount'   => '',
				'currency' => 'usd',
				'label'    => __( 'Pay now', 'usman-pay' ),
				'ref'      => '',
			),
			$atts,
			'usman_pay_button'
		);

		if ( ! Usman_Pay_Credentials::is_connected() || '' === $atts['amount'] ) {
			return '';
		}

		$order_ref = '' !== $atts['ref'] ? $atts['ref'] : 'wp-' . wp_generate_uuid4();

		ob_start();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'usman_pay_checkout_' . $order_ref ); ?>
			<input type="hidden" name="action" value="usman_pay_checkout">
			<input type="hidden" name="order_ref" value="<?php echo esc_attr( $order_ref ); ?>">
			<input type="hidden" name="amount" value="<?php echo esc_attr( $atts['amount'] ); ?>">
			<input type="hidden" name="currency" value="<?php echo esc_attr( $atts['currency'] ); ?>">
			<button type="submit"><?php echo esc_html( $atts['label'] ); ?></button>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Handle a shortcode button submission and redirect to hosted checkout.
	 */
	public function handle_checkout_request(): void {
		$order_ref = isset( $_POST['order_ref'] )
			? sanitize_text_field( wp_unslash( $_POST['order_ref'] ) )
			: '';

		check_admin_referer( 'usman_pay_checkout_' . $order_ref );

		// The amount is read from the form, so a shopper can tamper with it.
		// The platform is what ultimately validates and records the charge —
		// but sites handling real catalogues should call start() from server
		// code with a price looked up from the database, not from a form field.
		$amount   = isset( $_POST['amount'] ) ? (int) $_POST['amount'] : 0;
		$currency = isset( $_POST['currency'] )
			? sanitize_text_field( wp_unslash( $_POST['currency'] ) )
			: 'usd';

		$result = $this->start(
			array(
				'order_ref'  => $order_ref,
				'amount'     => $amount,
				'currency'   => $currency,
				'return_url' => home_url( '/' ),
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_die(
				esc_html( $result->get_error_message() ),
				esc_html__( 'Payment could not be started', 'usman-pay' ),
				array( 'back_link' => true )
			);
		}

		// wp_redirect, not wp_safe_redirect: the target is the platform, which
		// is by definition an external host and so would fail the safe-redirect
		// allowlist. The URL comes from our own authenticated API response.
		wp_redirect( $result );
		exit;
	}
}
