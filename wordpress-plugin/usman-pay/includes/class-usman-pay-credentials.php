<?php
/**
 * Credential storage.
 *
 * This plugin stores exactly two values, and neither is a Stripe credential:
 *
 *   usman_pay_site_key     public identifier for this site, sent in the clear
 *   usman_pay_site_secret  HMAC key, never transmitted after the initial handshake
 *
 * Compromising these lets an attacker create orders for THIS site only. They
 * grant no access to Stripe, to other tenants, or to other sites' secrets.
 *
 * @package usman-pay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Usman_Pay_Credentials {

	private const OPT_KEY     = 'usman_pay_site_key';
	private const OPT_SECRET  = 'usman_pay_site_secret';
	private const OPT_SITE_ID = 'usman_pay_site_id';
	private const OPT_STATUS  = 'usman_pay_status';

	public static function site_key(): string {
		return (string) get_option( self::OPT_KEY, '' );
	}

	public static function site_secret(): string {
		return (string) get_option( self::OPT_SECRET, '' );
	}

	public static function site_id(): string {
		return (string) get_option( self::OPT_SITE_ID, '' );
	}

	public static function status(): string {
		return (string) get_option( self::OPT_STATUS, 'disconnected' );
	}

	public static function is_connected(): bool {
		return 'connected' === self::status()
			&& '' !== self::site_key()
			&& '' !== self::site_secret();
	}

	/**
	 * Persist credentials issued by the platform during registration.
	 *
	 * autoload is disabled on the secret so it is not loaded into every page
	 * request that has no need for it.
	 */
	public static function store( string $site_id, string $key, string $secret ): void {
		update_option( self::OPT_SITE_ID, $site_id, false );
		update_option( self::OPT_KEY, $key, false );
		update_option( self::OPT_SECRET, $secret, false );
		update_option( self::OPT_STATUS, 'connected', false );
	}

	/**
	 * Mark the site as pending verification: the platform has issued credentials
	 * but has not yet completed its challenge callback.
	 */
	public static function mark_pending(): void {
		update_option( self::OPT_STATUS, 'pending', false );
	}

	/**
	 * Erase all locally held credentials.
	 */
	public static function forget(): void {
		delete_option( self::OPT_SITE_ID );
		delete_option( self::OPT_KEY );
		delete_option( self::OPT_SECRET );
		update_option( self::OPT_STATUS, 'disconnected', false );
	}
}
