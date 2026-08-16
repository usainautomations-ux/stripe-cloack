<?php
/**
 * Request signing.
 *
 * The canonical string signed by both sides is exactly five newline-separated
 * fields, in this order:
 *
 *   METHOD          uppercase HTTP verb, e.g. POST
 *   PATH            request path only, no host, no query string, e.g. /v1/payment/create
 *   TIMESTAMP       unix seconds, integer, as a string
 *   NONCE           32 hex chars, unique per request
 *   BODY_SHA256     lowercase hex sha256 of the raw request body ('' body still hashes)
 *
 * signature = hex( hmac_sha256( canonical, site_secret ) )
 *
 * The backend recomputes this from the raw body it received. Any middleware that
 * rewrites the body will invalidate the signature by design.
 *
 * @package usman-pay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Usman_Pay_Signer {

	/**
	 * Requests older than this many seconds are rejected by the platform.
	 */
	public const MAX_CLOCK_SKEW = 300;

	/**
	 * Build the canonical string for a request.
	 */
	public static function canonical( string $method, string $path, string $timestamp, string $nonce, string $body ): string {
		return implode(
			"\n",
			array(
				strtoupper( $method ),
				$path,
				$timestamp,
				$nonce,
				hash( 'sha256', $body ),
			)
		);
	}

	/**
	 * Sign a canonical string with this site's secret.
	 */
	public static function sign( string $canonical, string $secret ): string {
		return hash_hmac( 'sha256', $canonical, $secret );
	}

	/**
	 * Verify a signature in constant time.
	 */
	public static function verify( string $canonical, string $secret, string $provided ): bool {
		return hash_equals( self::sign( $canonical, $secret ), $provided );
	}

	/**
	 * Cryptographically random nonce.
	 */
	public static function nonce(): string {
		return bin2hex( random_bytes( 16 ) );
	}
}
