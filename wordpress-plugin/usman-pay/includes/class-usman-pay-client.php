<?php
/**
 * HTTP client for the Usman platform API.
 *
 * Every authenticated request carries four headers:
 *
 *   X-Usman-Key        this site's public identifier
 *   X-Usman-Timestamp  unix seconds
 *   X-Usman-Nonce      per-request nonce, cached server-side to block replay
 *   X-Usman-Signature  hex hmac-sha256 over the canonical string (see Usman_Pay_Signer)
 *
 * @package usman-pay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Usman_Pay_Client {

	private const TIMEOUT = 20;

	/**
	 * Step 1 of onboarding. Exchanges a short-lived setup token, generated in the
	 * dashboard, for this site's long-lived credentials.
	 *
	 * This is the one call that is NOT HMAC-signed, because the site has no
	 * secret yet. It is authenticated by the setup token, which is single-use
	 * and expires in 15 minutes.
	 *
	 * @return array{site_id:string,site_key:string,site_secret:string}|WP_Error
	 */
	public function register( string $setup_token ) {
		$response = wp_remote_post(
			$this->url( '/v1/website/register' ),
			array(
				'timeout' => self::TIMEOUT,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'setup_token'    => $setup_token,
						'site_url'       => home_url(),
						'challenge_url'  => rest_url( Usman_Pay_Rest::NAMESPACE . '/challenge' ),
						'plugin_version' => USMAN_PAY_VERSION,
						'wp_version'     => get_bloginfo( 'version' ),
						'php_version'    => PHP_VERSION,
					)
				),
			)
		);

		return $this->decode( $response, array( 'site_id', 'site_key', 'site_secret' ) );
	}

	/**
	 * Step 2 of onboarding. Tells the platform this site has stored its
	 * credentials and is ready to answer the domain-ownership challenge.
	 *
	 * The platform calls back to the challenge URL during this request, so the
	 * response reflects the final verification outcome.
	 *
	 * @return array{status:string}|WP_Error
	 */
	public function verify() {
		return $this->signed_request( 'POST', '/v1/website/' . Usman_Pay_Credentials::site_id() . '/verify', array() );
	}

	/**
	 * Periodic liveness ping. Lets the dashboard show plugin version drift and
	 * flag sites that have gone dark.
	 */
	public function handshake() {
		return $this->signed_request(
			'GET',
			'/v1/website/' . Usman_Pay_Credentials::site_id() . '/handshake',
			null
		);
	}

	/**
	 * Open an order on the platform and get back a hosted checkout URL.
	 *
	 * Note what is absent: no Stripe key, no payment method, no card data. The
	 * platform selects the Stripe account bound to this site and creates the
	 * PaymentIntent server-side.
	 *
	 * @param array $order {
	 *     @type int    $amount        Minor units, e.g. 4999 for $49.99.
	 *     @type string $currency      ISO 4217, lowercase.
	 *     @type string $order_ref     This site's own order identifier.
	 *     @type string $customer_email
	 *     @type string $return_url    Where to send the customer after payment.
	 * }
	 * @return array{checkout_url:string,order_id:string}|WP_Error
	 */
	public function create_payment( array $order ) {
		return $this->signed_request( 'POST', '/v1/payment/create', $order, $order['order_ref'] ?? null );
	}

	/**
	 * Read the authoritative status of an order from the platform.
	 */
	public function order_status( string $order_ref ) {
		return $this->signed_request( 'GET', '/v1/order/' . rawurlencode( $order_ref ) . '/status', null );
	}

	/**
	 * Revoke this site's credentials on the platform.
	 */
	public function disconnect() {
		return $this->signed_request( 'POST', '/v1/website/' . Usman_Pay_Credentials::site_id() . '/disconnect', array() );
	}

	/**
	 * Perform an HMAC-signed request.
	 *
	 * @param string     $method          HTTP verb.
	 * @param string     $path            Path beginning with a slash.
	 * @param array|null $payload         Body, or null for no body.
	 * @param string|null $idempotency_key Stable key so retries cannot double-charge.
	 * @return array|WP_Error
	 */
	private function signed_request( string $method, string $path, ?array $payload, ?string $idempotency_key = null ) {
		if ( ! Usman_Pay_Credentials::site_key() || ! Usman_Pay_Credentials::site_secret() ) {
			return new WP_Error( 'usman_pay_not_connected', __( 'This site is not connected to the Usman platform.', 'usman-pay' ) );
		}

		$body      = null === $payload ? '' : (string) wp_json_encode( $payload );
		$timestamp = (string) time();
		$nonce     = Usman_Pay_Signer::nonce();

		$signature = Usman_Pay_Signer::sign(
			Usman_Pay_Signer::canonical( $method, $path, $timestamp, $nonce, $body ),
			Usman_Pay_Credentials::site_secret()
		);

		$headers = array(
			'Accept'            => 'application/json',
			'X-Usman-Key'       => Usman_Pay_Credentials::site_key(),
			'X-Usman-Timestamp' => $timestamp,
			'X-Usman-Nonce'     => $nonce,
			'X-Usman-Signature' => $signature,
		);

		if ( null !== $payload ) {
			$headers['Content-Type'] = 'application/json';
		}
		if ( $idempotency_key ) {
			$headers['Idempotency-Key'] = $idempotency_key;
		}

		$args = array(
			'method'  => $method,
			'timeout' => self::TIMEOUT,
			'headers' => $headers,
		);
		if ( null !== $payload ) {
			$args['body'] = $body;
		}

		return $this->decode( wp_remote_request( $this->url( $path ), $args ) );
	}

	private function url( string $path ): string {
		return rtrim( USMAN_PAY_API_BASE, '/' ) . $path;
	}

	/**
	 * Turn a wp_remote_* response into an array or a WP_Error.
	 *
	 * @param array|WP_Error $response
	 * @param string[]       $required Keys that must be present in a success body.
	 * @return array|WP_Error
	 */
	private function decode( $response, array $required = array() ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'usman_pay_bad_response',
				/* translators: %d: HTTP status code. */
				sprintf( __( 'The platform returned an unreadable response (HTTP %d).', 'usman-pay' ), $code )
			);
		}

		if ( $code >= 400 ) {
			return new WP_Error(
				$data['error']['code'] ?? 'usman_pay_http_' . $code,
				$data['error']['message'] ?? __( 'The platform rejected this request.', 'usman-pay' ),
				array( 'status' => $code )
			);
		}

		foreach ( $required as $key ) {
			if ( ! isset( $data[ $key ] ) ) {
				return new WP_Error(
					'usman_pay_incomplete_response',
					/* translators: %s: missing field name. */
					sprintf( __( 'The platform response was missing the %s field.', 'usman-pay' ), $key )
				);
			}
		}

		return $data;
	}
}
