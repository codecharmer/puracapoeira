<?php
/**
 * Shared helpers for the theme's dynamic blocks (loaded by the blocks that need them).
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
