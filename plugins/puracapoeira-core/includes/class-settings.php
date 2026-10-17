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

	public const OPTION  = 'pura_settings';
	public const SECRETS = 'pura_secrets';

	/** @var string[] Keys stored in pura_secrets (not autoloaded) and overridable by constants. */
	public const SECRET_KEYS = array(
		'google_client_secret',
		'apple_private_key',
	);

	/** @var string[] Secrets that may span several lines (PEM keys). */
	public const MULTILINE_SECRET_KEYS = array(
		'apple_private_key',
	);

	/** @var array<string, array{option:string,slug:string}> Pages the plugin links to. */
	private const PAGES = array(
		'account' => array(
			'option' => 'account_page_id',
			'slug'   => 'cuenta',
		),
	);

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
			'event_to_emails'    => '',

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

			// Cuentas (Google / Apple). Secrets live in pura_secrets.
			'account_page_id'    => 0,
			'google_client_id'   => '',
			'apple_services_id'  => '',
			'apple_team_id'      => '',
			'apple_key_id'       => '',
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
	 * Secret: the constant wins over the stored option. '' when neither is set.
	 */
	public static function get_secret( string $key ): string {
		$constant = self::secret_constant( $key );
		if ( defined( $constant ) ) {
			return (string) constant( $constant );
		}

		$secrets = get_option( self::SECRETS, array() );

		return is_array( $secrets ) && isset( $secrets[ $key ] ) ? (string) $secrets[ $key ] : '';
	}

	/**
	 * 'constant', 'option' or 'missing'.
	 */
	public static function secret_source( string $key ): string {
		if ( defined( self::secret_constant( $key ) ) ) {
			return 'constant';
		}

		return '' !== self::get_secret( $key ) ? 'option' : 'missing';
	}

	public static function secret_constant( string $key ): string {
		return 'PURA_' . strtoupper( $key );
	}

	public static function delete_secret( string $key ): void {
		$secrets = get_option( self::SECRETS, array() );
		if ( is_array( $secrets ) && isset( $secrets[ $key ] ) ) {
			unset( $secrets[ $key ] );
			update_option( self::SECRETS, $secrets, false );
		}
	}

	/**
	 * Permalink of a configured page, with a slug-based fallback.
	 *
	 * @param string $which 'account'.
	 */
	public static function page_url( string $which ): string {
		$spec = self::PAGES[ $which ] ?? self::PAGES['account'];
		$id   = (int) self::get( $spec['option'], 0 );

		if ( $id > 0 ) {
			$url = get_permalink( $id );
			if ( $url ) {
				return $url;
			}
		}

		$page = get_page_by_path( $spec['slug'] );
		if ( $page ) {
			return (string) get_permalink( $page );
		}

		return home_url( '/' . $spec['slug'] . '/' );
	}

	/**
	 * URL of the Aviso de privacidad: WordPress' privacy page, else the `privacidad` slug.
	 */
	public static function privacy_url(): string {
		$url = (string) get_privacy_policy_url();
		if ( '' !== $url ) {
			return $url;
		}

		$page = get_page_by_path( 'privacidad' );
		if ( $page ) {
			return (string) get_permalink( $page );
		}

		return home_url( '/privacidad/' );
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
	 * Values safe to expose to the browser (no secrets).
	 *
	 * @return array<string, mixed>
	 */
	public static function public_config(): array {
		return array(
			'restUrl'    => esc_url_raw( rest_url( 'pura/v1/' ) ),
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'accountUrl' => self::page_url( 'account' ),
			'privacyUrl' => self::privacy_url(),
			'providers'  => Auth\Providers::configured(),
			'whatsapp'   => self::whatsapp_number(),
		);
	}
}
