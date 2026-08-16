<?php
/**
 * The plugin's own REST surface.
 *
 * Exactly one public route, and it does exactly one thing: prove that whoever
 * is serving this domain also holds the secret the platform issued.
 *
 * @package usman-pay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Usman_Pay_Rest {

	public const NAMESPACE = 'usman-pay/v1';

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/challenge',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'answer_challenge' ),
				// Intentionally public. The route proves possession of a secret;
				// requiring a logged-in WordPress user would make it useless,
				// since the caller is our platform, not a person.
				'permission_callback' => '__return_true',
				'args'                => array(
					'nonce' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Sign the platform's nonce with this site's secret.
	 *
	 * Only the HMAC is returned — never the secret, and never anything derived
	 * from it that would survive across nonces.
	 */
	public function answer_challenge( WP_REST_Request $request ) {
		$secret = Usman_Pay_Credentials::site_secret();

		if ( '' === $secret ) {
			return new WP_Error(
				'usman_pay_not_provisioned',
				__( 'This site has no platform credentials yet.', 'usman-pay' ),
				array( 'status' => 409 )
			);
		}

		$nonce = (string) $request->get_param( 'nonce' );

		// A short nonce would let a caller build a table of answers, so anything
		// under 32 characters is refused rather than signed.
		if ( strlen( $nonce ) < 32 ) {
			return new WP_Error(
				'usman_pay_bad_challenge',
				__( 'Challenge nonce is too short.', 'usman-pay' ),
				array( 'status' => 400 )
			);
		}

		return rest_ensure_response(
			array(
				'signature'      => Usman_Pay_Signer::sign( $nonce, $secret ),
				'plugin_version' => USMAN_PAY_VERSION,
				'site_id'        => Usman_Pay_Credentials::site_id(),
			)
		);
	}
}
