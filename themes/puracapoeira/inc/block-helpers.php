<?php
/**
 * Shared helpers for the theme's dynamic blocks (loaded by functions.php; src/ is not deployed).
 *
 * @package Pura
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'pura_theme_plugin_notice' ) ) {
	/**
	 * Editors-only notice when the companion plugin is inactive. Returns true when the notice
	 * was printed (i.e. the block should stop rendering).
	 */
	function pura_theme_plugin_notice( string $class_name ): bool {
		if ( class_exists( $class_name ) ) {
			return false;
		}
		if ( current_user_can( 'edit_posts' ) ) {
			echo '<p class="pura-notice">' . esc_html__( 'Activa el plugin Pura Capoeira Core para mostrar este bloque.', 'pura' ) . '</p>';
		}

		return true;
	}
}

if ( ! function_exists( 'pura_theme_image' ) ) {
	/**
	 * Featured image markup with a theme placeholder fallback.
	 *
	 * @param array<string, string> $attr Extra attributes (alt, class…).
	 */
	function pura_theme_image( int $attachment_id, string $size, array $attr = array(), string $placeholder = '' ): string {
		$attr = array_merge( array( 'loading' => 'lazy' ), $attr );
		if ( $attachment_id > 0 ) {
			$html = wp_get_attachment_image( $attachment_id, $size, false, $attr );
			if ( '' !== $html ) {
				return $html;
			}
		}
		if ( '' === $placeholder ) {
			return '';
		}

		$attributes = '';
		foreach ( $attr as $name => $value ) {
			$attributes .= ' ' . esc_attr( (string) $name ) . '="' . esc_attr( (string) $value ) . '"';
		}

		return '<img src="' . esc_url( get_theme_file_uri( $placeholder ) ) . '"' . $attributes . ' />';
	}
}

if ( ! function_exists( 'pura_theme_current_post' ) ) {
	/**
	 * The post a template block renders for: block context first, then the global post.
	 *
	 * @param WP_Block|null $block Block instance.
	 */
	function pura_theme_current_post( ?WP_Block $block, string $post_type ): ?WP_Post {
		$post = null;
		if ( $block instanceof WP_Block && ! empty( $block->context['postId'] ) ) {
			$post = get_post( (int) $block->context['postId'] );
		}
		if ( ! $post instanceof WP_Post ) {
			$post = get_post();
		}

		return ( $post instanceof WP_Post && $post->post_type === $post_type ) ? $post : null;
	}
}

if ( ! function_exists( 'pura_theme_provider_form' ) ) {
	/**
	 * One "Continuar con Google/Apple" button: a tiny POST form to /pura/v1/auth/{provider}/start
	 * (POST-only, so a page cache can never replay a state). Prints no user data.
	 *
	 * @param array{id:string,label:string,startUrl:string} $provider      Configured provider.
	 * @param string                                       $redirect_to   Where the callback should land.
	 * @param string                                       $redirect_attr Data attribute for the hidden redirect input ('' for none).
	 */
	function pura_theme_provider_form( array $provider, string $redirect_to, string $redirect_attr = '' ): string {
		$id = sanitize_key( (string) ( $provider['id'] ?? '' ) );
		if ( 'google' === $id ) {
			$icon = '<svg aria-hidden="true" viewBox="0 0 48 48" width="18" height="18"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>';
		} else {
			$icon = '<svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M16.4 12.7c0-2.5 2.1-3.7 2.2-3.8-1.2-1.7-3-2-3.7-2-1.6-.2-3.1.9-3.9.9-.8 0-2-.9-3.4-.9-1.7 0-3.4 1-4.3 2.6-1.8 3.2-.5 7.9 1.3 10.5.9 1.3 1.9 2.7 3.3 2.6 1.3-.1 1.8-.8 3.4-.8 1.6 0 2 .8 3.4.8 1.4 0 2.3-1.3 3.2-2.5 1-1.4 1.4-2.8 1.4-2.9-.1 0-2.9-1.1-2.9-4.5zM14 5.2c.7-.9 1.2-2.1 1.1-3.2-1 0-2.3.7-3 1.6-.7.8-1.3 2-1.1 3.1 1.1.1 2.3-.6 3-1.5z"/></svg>';
		}

		$redirect = '' !== $redirect_attr ? ' ' . sanitize_key( $redirect_attr ) : '';

		return sprintf(
			'<form method="post" action="%1$s" class="account-login__form"><input type="hidden" name="redirect_to" value="%2$s"%3$s /><button type="submit" class="btn btn--provider btn--%4$s">%5$s<span data-tv>%6$s</span></button></form>',
			esc_url( (string) ( $provider['startUrl'] ?? '' ) ),
			esc_url( $redirect_to ),
			$redirect,
			esc_attr( $id ),
			$icon,
			esc_html( (string) ( $provider['label'] ?? '' ) )
		);
	}
}
