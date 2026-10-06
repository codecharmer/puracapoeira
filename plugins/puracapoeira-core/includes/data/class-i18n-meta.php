<?php
/**
 * Per-post translations for the client-side language switcher.
 *
 * Meta `_pura_i18n` holds JSON `{ "en": { key: value }, "pt": { key: value } }` where value is a
 * string or `{ "html": "…" }`. Keys match the `i18n-<key>` classes / `data-i18n-key` attributes
 * in the post content (bio paragraphs, timeline items) and the profile hero (`hero-eyebrow`,
 * `hero-intro`, `caption`). The theme prints the JSON on singular views; the browser applies it.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Data;

defined( 'ABSPATH' ) || exit;

final class I18n_Meta {

	public const META_KEY = '_pura_i18n';

	public const LANGS = array( 'en', 'pt' );

	private const NONCE = 'pura_i18n_meta';

	/** @var array<string, array<string, bool>> wp_kses allowlist for `{html}` values. */
	private const ALLOWED_HTML = array(
		'br'     => array(),
		'em'     => array(),
		'strong' => array(),
		'b'      => array(),
		'i'      => array(),
		'sup'    => array(),
		'sub'    => array(),
		'span'   => array( 'class' => true ),
		'mark'   => array(
			'class' => true,
			'style' => true,
		),
		'a'      => array(
			'href'   => true,
			'target' => true,
			'rel'    => true,
			'class'  => true,
		),
		'h3'     => array( 'class' => true ),
		'h4'     => array( 'class' => true ),
		'p'      => array( 'class' => true ),
		'ul'     => array( 'class' => true ),
		'li'     => array( 'class' => true ),
	);

	/**
	 * @return string[]
	 */
	public static function post_types(): array {
		return array( 'page', Profesor_Post_Type::POST_TYPE, Sede_Post_Type::POST_TYPE );
	}

	public function register(): void {
		foreach ( self::post_types() as $post_type ) {
			register_post_meta(
				$post_type,
				self::META_KEY,
				array(
					'type'              => 'string',
					'single'            => true,
					'default'           => '',
					'show_in_rest'      => false,
					'sanitize_callback' => array( self::class, 'sanitize' ),
					'auth_callback'     => static fn () => current_user_can( 'edit_posts' ),
				)
			);
			add_action( 'add_meta_boxes_' . $post_type, array( $this, 'add_meta_box' ) );
			add_action( 'save_post_' . $post_type, array( $this, 'save' ), 10, 2 );
		}

		add_action( 'admin_notices', array( $this, 'admin_notice' ) );
	}

	public function add_meta_box(): void {
		add_meta_box(
			'pura_i18n',
			__( 'Traducciones (EN / PT)', 'pura' ),
			array( $this, 'render' ),
			null,
			'normal',
			'low'
		);
	}

	public function render( \WP_Post $post ): void {
		$data = self::decode( (string) get_post_meta( $post->ID, self::META_KEY, true ) );
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );

		echo '<p class="description">' . esc_html__( 'El contenido en español es el original. Cada idioma es un objeto JSON {"clave": "texto"} o {"clave": {"html": "…"}}. Las claves corresponden a las clases i18n-* del contenido.', 'pura' ) . '</p>';

		foreach ( self::LANGS as $lang ) {
			$json = isset( $data[ $lang ] ) && $data[ $lang ] ? wp_json_encode( $data[ $lang ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : '';
			echo '<p><label for="pura_i18n_' . esc_attr( $lang ) . '"><strong>' . esc_html( strtoupper( $lang ) ) . '</strong></label><br />';
			echo '<textarea id="pura_i18n_' . esc_attr( $lang ) . '" name="pura_i18n[' . esc_attr( $lang ) . ']" rows="8" class="large-text code">' . esc_textarea( (string) $json ) . '</textarea></p>';
		}

		$keys = self::keys_in_content( $post->post_content );
		if ( $keys ) {
			echo '<p class="pura-i18n-keys"><strong>' . esc_html__( 'Claves disponibles en el contenido:', 'pura' ) . '</strong><br />';
			foreach ( $keys as $key ) {
				echo '<code>' . esc_html( $key ) . '</code> ';
			}
			echo '</p>';
		}
	}

	public function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE . '_nonce' ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE . '_nonce' ] ) ), self::NONCE ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['pura_i18n'] ) || ! is_array( $_POST['pura_i18n'] ) ) {
			return;
		}

		$input    = wp_unslash( $_POST['pura_i18n'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON validated below.
		$existing = self::decode( (string) get_post_meta( $post_id, self::META_KEY, true ) );
		$invalid  = array();

		foreach ( self::LANGS as $lang ) {
			$raw = isset( $input[ $lang ] ) && is_string( $input[ $lang ] ) ? trim( $input[ $lang ] ) : '';
			if ( '' === $raw ) {
				unset( $existing[ $lang ] );
				continue;
			}
			$decoded = json_decode( $raw, true );
			if ( ! is_array( $decoded ) ) {
				$invalid[] = strtoupper( $lang );
				continue; // Keep the previous value.
			}
			$existing[ $lang ] = $decoded;
		}

		if ( $invalid ) {
			set_transient( 'pura_i18n_notice_' . get_current_user_id(), $invalid, 60 );
		}

		if ( ! $existing ) {
			delete_post_meta( $post_id, self::META_KEY );
			return;
		}

		// update_post_meta() unslashes its value; slash the JSON so its \" and \/ escapes survive.
		update_post_meta( $post_id, self::META_KEY, wp_slash( self::sanitize( (string) wp_json_encode( $existing, JSON_UNESCAPED_UNICODE ) ) ) );
	}

	public function admin_notice(): void {
		$invalid = get_transient( 'pura_i18n_notice_' . get_current_user_id() );
		if ( ! is_array( $invalid ) ) {
			return;
		}
		delete_transient( 'pura_i18n_notice_' . get_current_user_id() );
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( sprintf( /* translators: %s: language codes */ __( 'Traducciones: el JSON de %s no es válido; se conservó la versión anterior.', 'pura' ), implode( ', ', $invalid ) ) ) . '</p></div>';
	}

	/**
	 * Sanitize the JSON string: keep only en/pt, string or {html} values, allowlisted HTML.
	 *
	 * @param mixed $value Meta value.
	 */
	public static function sanitize( $value ): string {
		$data = self::decode( is_string( $value ) ? $value : '' );
		if ( ! $data ) {
			return '';
		}

		$clean = array();
		foreach ( self::LANGS as $lang ) {
			if ( empty( $data[ $lang ] ) || ! is_array( $data[ $lang ] ) ) {
				continue;
			}
			foreach ( $data[ $lang ] as $key => $item ) {
				$key = (string) $key;
				if ( ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $key ) ) {
					continue;
				}
				if ( is_string( $item ) ) {
					$clean[ $lang ][ $key ] = wp_kses( $item, array() );
				} elseif ( is_array( $item ) && isset( $item['html'] ) && is_string( $item['html'] ) ) {
					$clean[ $lang ][ $key ] = array( 'html' => wp_kses( $item['html'], self::ALLOWED_HTML ) );
				}
			}
		}

		return $clean ? (string) wp_json_encode( $clean, JSON_UNESCAPED_UNICODE ) : '';
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function decode( string $json ): array {
		if ( '' === trim( $json ) ) {
			return array();
		}
		$data = json_decode( $json, true );

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Translation keys referenced by the post content (`i18n-<key>` classes and `i18nKey` attributes).
	 *
	 * @return string[]
	 */
	public static function keys_in_content( string $content ): array {
		$keys = array();
		if ( preg_match_all( '/\bi18n-([a-z0-9]+(?:-[a-z0-9]+)*)/', $content, $m ) ) {
			$keys = $m[1];
		}
		if ( preg_match_all( '/"i18nKey":"([a-z0-9]+(?:-[a-z0-9]+)*)"/', $content, $m ) ) {
			foreach ( $m[1] as $base ) {
				$keys[] = $base . '-title';
				$keys[] = $base . '-text';
			}
		}

		return array_values( array_unique( $keys ) );
	}
}
