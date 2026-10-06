<?php
/**
 * Body classes that identify the current page for CSS and the client-side language layer:
 * `pura-front`, `pura-page-<slug>`, `pura-sede-<slug>`, `pura-profesor-<slug>`.
 *
 * @package Pura
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * @param string[] $classes Body classes.
 * @return string[]
 */
function pura_theme_body_class( array $classes ): array {
	if ( is_front_page() ) {
		$classes[] = 'pura-front';
	}

	$post = get_queried_object();
	if ( ! $post instanceof WP_Post ) {
		return $classes;
	}

	$slug = sanitize_html_class( $post->post_name );
	if ( '' === $slug ) {
		return $classes;
	}

	if ( is_page() ) {
		$classes[] = 'pura-page-' . $slug;
	} elseif ( is_singular( pura_theme_post_type( 'sede' ) ) ) {
		$classes[] = 'pura-sede-' . $slug;
	} elseif ( is_singular( pura_theme_post_type( 'profesor' ) ) ) {
		$classes[] = 'pura-profesor-' . $slug;
	}

	return $classes;
}
add_filter( 'body_class', 'pura_theme_body_class' );
