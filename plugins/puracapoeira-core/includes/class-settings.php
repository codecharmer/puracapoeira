<?php
/**
 * Plugin settings.
 *
 * Values live in the autoloaded `pura_settings` option, merged over the defaults so new keys
 * appear without a migration. Any key can be pinned from wp-config.php with a constant named
 * `PURA_<KEY>` (e.g. `PURA_CONTACT_TO_EMAILS`); constants win over the stored option.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core;

defined( 'ABSPATH' ) || exit;

final class Settings {

	public const OPTION = 'pura_settings';

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			// Formulario de contacto.
			'contact_to_emails'  => 'contacto@puracapoeira.com',
			'contact_from_email' => '',
			'contact_from_name'  => 'Pura Capoeira',
			'cc_registrant'      => true,

			// Redes y contacto.
			'whatsapp_number'    => '18056385603',
			'whatsapp_display'   => '+1 (805) 638-5603',
			'instagram_url'      => 'https://www.instagram.com/profesor.malandro/',
			'instagram_handle'   => '@profesor.malandro',
			'facebook_url'       => 'https://www.facebook.com/share/192u2wDfbR/?mibextid=wwXIfr',
			'facebook_label'     => 'Pura Capoeira',
			'youtube_url'        => '',
			'icloud_album_url'   => 'https://www.icloud.com/sharedalbum/#B1g5qXGF1qQGwbR',

			// Sitio.
			'tagline'            => 'Escuela y comunidad internacional de capoeira. Cultura afrobrasileña, música, disciplina y movimiento conectando comunidades en México, Brasil, Angola y Estados Unidos.',
			'motto'              => 'Capoeira · Cultura · Comunidade',
			'og_image_id'        => 0,
		);
	}

	/**
	 * Stored values merged over the defaults, with wp-config constants applied on top.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );
		$all    = array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );

		foreach ( $all as $key => $value ) {
			$constant = self::constant_name( $key );
			if ( defined( $constant ) ) {
				$all[ $key ] = constant( $constant );
			}
		}

		return $all;
	}

	/**
	 * @param mixed $fallback Fallback when the key is unknown.
	 * @return mixed
	 */
	public static function get( string $key, $fallback = null ) {
		$all = self::all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	/**
	 * @param array<string, mixed> $values Partial settings to merge and persist.
	 */
	public static function update( array $values ): void {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		update_option( self::OPTION, array_merge( $stored, $values ), true );
	}

	/**
	 * 'constant' when pinned in wp-config.php, 'option' when stored, otherwise 'default'.
	 */
	public static function source( string $key ): string {
		if ( defined( self::constant_name( $key ) ) ) {
			return 'constant';
		}

		$stored = get_option( self::OPTION, array() );

		return is_array( $stored ) && array_key_exists( $key, $stored ) ? 'option' : 'default';
	}

	public static function constant_name( string $key ): string {
		return 'PURA_' . strtoupper( $key );
	}

	/**
	 * Digits-only WhatsApp number from the setting.
	 */
	public static function whatsapp_number(): string {
		return (string) preg_replace( '/\D+/', '', (string) self::get( 'whatsapp_number', '' ) );
	}

	public static function whatsapp_url( string $text = '' ): string {
		$url = 'https://wa.me/' . self::whatsapp_number();

		if ( '' !== $text ) {
			$url .= '?text=' . rawurlencode( $text );
		}

		return $url;
	}

	/**
	 * Values safe to expose to the browser.
	 *
	 * @return array<string, mixed>
	 */
	public static function public_config(): array {
		return array(
			'restUrl'  => esc_url_raw( rest_url( 'pura/v1/' ) ),
			'whatsapp' => self::whatsapp_number(),
		);
	}
}
