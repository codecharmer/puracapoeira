<?php
/**
 * The secret upload link for colleagues (profesores e instructores): one shared key, stored in
 * settings (`photo_upload_key`, pinnable as PURA_PHOTO_UPLOAD_KEY), appended to the /fotos/ page.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core;

defined( 'ABSPATH' ) || exit;

final class Photo_Uploads {

	/** Query parameter carrying the key: /fotos/?clave=… */
	public const KEY_PARAM = 'clave';

	private const KEY_LENGTH = 20;

	/**
	 * The current key, generated and stored on first use.
	 */
	public static function key(): string {
		$key = trim( (string) Settings::get( 'photo_upload_key', '' ) );
		if ( '' !== $key ) {
			return $key;
		}

		return self::regenerate();
	}

	/**
	 * A fresh key; every link handed out before stops working.
	 */
	public static function regenerate(): string {
		$key = self::generate();
		Settings::update( array( 'photo_upload_key' => $key ) );

		return $key;
	}

	/**
	 * Letters and digits only, so the link survives WhatsApp, mail clients and copy-paste.
	 */
	public static function generate(): string {
		return (string) wp_generate_password( self::KEY_LENGTH, false, false );
	}

	/**
	 * Constant-time comparison; an empty key never matches.
	 */
	public static function verify( string $candidate ): bool {
		$candidate = trim( $candidate );
		$key       = self::key();

		return '' !== $candidate && '' !== $key && hash_equals( $key, $candidate );
	}

	public static function page_url(): string {
		return Settings::page_url( 'photos' );
	}

	/**
	 * The link to share: the page plus the key.
	 */
	public static function link(): string {
		return add_query_arg( self::KEY_PARAM, self::key(), self::page_url() );
	}
}
