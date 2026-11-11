<?php
/**
 * Sign-in codes sent by email: six digits, single use, ten minutes, five tries.
 *
 * The browser gets an opaque ticket when the code is sent and returns it with the code. Only a
 * keyed hash of the code is stored (transients), so neither a database dump nor the ticket alone
 * is enough to sign in.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Auth;

defined( 'ABSPATH' ) || exit;

final class Email_Code {

	public const TTL          = 600;
	public const MAX_ATTEMPTS = 5;
	public const DIGITS       = 6;

	public const OK      = 'ok';
	public const WRONG   = 'wrong';
	public const EXPIRED = 'expired';
	public const LOCKED  = 'locked';

	private const PREFIX = 'pura_email_code_';

	/**
	 * Create a code for an address.
	 *
	 * @param string $email       Normalized address the code is sent to.
	 * @param string $redirect_to Where to go after signing in (already validated).
	 * @param bool   $usable      False for a ticket that can never be redeemed (the address may not
	 *                            sign in this way); it behaves like a wrong code.
	 * @return array{ticket:string,code:string}
	 */
	public static function issue( string $email, string $redirect_to = '', bool $usable = true ): array {
		$ticket = Jwt::base64url_encode( random_bytes( 32 ) );
		$code   = str_pad( (string) random_int( 0, ( 10 ** self::DIGITS ) - 1 ), self::DIGITS, '0', STR_PAD_LEFT );

		set_transient(
			self::key( $ticket ),
			array(
				'email'       => $email,
				'hash'        => $usable ? self::hash( $ticket, $code ) : '',
				'attempts'    => 0,
				'redirect_to' => $redirect_to,
				'expires'     => time() + self::TTL,
			),
			self::TTL
		);

		return array(
			'ticket' => $ticket,
			'code'   => $code,
		);
	}

	/**
	 * Check a code. A right code is consumed; a wrong one counts as a try, and the ticket dies
	 * with the last try.
	 *
	 * @return array{status:string,email:string,redirect_to:string}
	 */
	public static function verify( string $ticket, string $code ): array {
		$fail = static fn ( string $status ): array => array(
			'status'      => $status,
			'email'       => '',
			'redirect_to' => '',
		);

		if ( '' === $ticket || strlen( $ticket ) > 128 ) {
			return $fail( self::EXPIRED );
		}

		$key  = self::key( $ticket );
		$data = get_transient( $key );
		$left = is_array( $data ) ? (int) ( $data['expires'] ?? 0 ) - time() : 0;
		if ( ! is_array( $data ) || $left <= 0 ) {
			delete_transient( $key );

			return $fail( self::EXPIRED );
		}

		$code = self::digits( $code );
		$hash = (string) ( $data['hash'] ?? '' );
		if ( '' !== $hash && self::DIGITS === strlen( $code ) && hash_equals( $hash, self::hash( $ticket, $code ) ) ) {
			delete_transient( $key );

			return array(
				'status'      => self::OK,
				'email'       => (string) ( $data['email'] ?? '' ),
				'redirect_to' => (string) ( $data['redirect_to'] ?? '' ),
			);
		}

		$data['attempts'] = (int) ( $data['attempts'] ?? 0 ) + 1;
		if ( $data['attempts'] >= self::MAX_ATTEMPTS ) {
			delete_transient( $key );

			return $fail( self::LOCKED );
		}
		set_transient( $key, $data, $left );

		return $fail( self::WRONG );
	}

	/** The digits of what was typed: people paste codes with spaces or a dash. Pure. */
	public static function digits( string $code ): string {
		return (string) preg_replace( '/\D+/', '', $code );
	}

	private static function hash( string $ticket, string $code ): string {
		return hash_hmac( 'sha256', $code, $ticket );
	}

	private static function key( string $ticket ): string {
		return self::PREFIX . hash( 'sha256', $ticket );
	}
}
