<?php
/**
 * Theme supports, assets, editor styles and small shared helpers.
 *
 * @package Pura
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Theme supports. Block themes get most of these implicitly; the explicit ones matter.
 */
function pura_theme_setup(): void {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'html5', array( 'script', 'style', 'search-form', 'gallery', 'caption' ) );
	add_theme_support( 'editor-styles' );
	add_editor_style( array( 'assets/css/theme.css', 'assets/css/editor.css' ) );

	load_theme_textdomain( 'pura', PURA_THEME_DIR . '/languages' );
}
add_action( 'after_setup_theme', 'pura_theme_setup' );

/**
 * Version string for theme assets: the file's mtime, so every deploy busts caches
 * (rsync from a fresh checkout gives new mtimes) without hand-bumping the theme version.
 */
function pura_theme_asset_version( string $relative_path ): string {
	$file = PURA_THEME_DIR . '/' . ltrim( $relative_path, '/' );

	if ( file_exists( $file ) ) {
		return (string) filemtime( $file );
	}

	return PURA_THEME_VERSION;
}

/**
 * Resolve a plugin post type / taxonomy name, falling back to the known default when the
 * companion plugin is not loaded (so templates still render something sensible).
 */
function pura_theme_post_type( string $key ): string {
	$map = array(
		'sede'        => array( 'Pura\Core\Data\Sede_Post_Type', 'POST_TYPE', 'pura_sede' ),
		'profesor'    => array( 'Pura\Core\Data\Profesor_Post_Type', 'POST_TYPE', 'pura_profesor' ),
		'evento'      => array( 'Pura\Core\Data\Evento_Post_Type', 'POST_TYPE', 'pura_evento' ),
		'galeria'     => array( 'Pura\Core\Data\Galeria_Post_Type', 'POST_TYPE', 'pura_galeria' ),
		'galeria_cat' => array( 'Pura\Core\Data\Galeria_Post_Type', 'TAX_CATEGORY', 'pura_galeria_cat' ),
		'galeria_loc' => array( 'Pura\Core\Data\Galeria_Post_Type', 'TAX_LOCATION', 'pura_galeria_loc' ),
	);

	if ( ! isset( $map[ $key ] ) ) {
		return '';
	}

	list( $class, $constant, $default ) = $map[ $key ];
	$name                               = $class . '::' . $constant;

	return ( class_exists( $class ) && defined( $name ) ) ? (string) constant( $name ) : $default;
}

/**
 * Front-end stylesheet, self-hosted fonts fallback, and the small behaviour module.
 */
function pura_theme_enqueue_assets(): void {
	wp_enqueue_style(
		'pura-theme',
		PURA_THEME_URI . '/assets/css/theme.css',
		array(),
		pura_theme_asset_version( 'assets/css/theme.css' )
	);

	// Fonts are self-hosted via theme.json; fall back to Google Fonts if the files are missing.
	if ( ! file_exists( PURA_THEME_DIR . '/assets/fonts/outfit-latin-wght-normal.woff2' )
		|| ! file_exists( PURA_THEME_DIR . '/assets/fonts/dm-sans-latin-wght-normal.woff2' ) ) {
		wp_enqueue_style(
			'pura-google-fonts',
			'https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap',
			array(),
			null // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		);
	}

	wp_enqueue_script_module(
		'pura-theme',
		PURA_THEME_URI . '/assets/js/theme.js',
		array(),
		pura_theme_asset_version( 'assets/js/theme.js' )
	);
}
add_action( 'wp_enqueue_scripts', 'pura_theme_enqueue_assets' );

/**
 * Mark the document as JS-capable as early as possible so the scroll-reveal CSS can hide
 * content without a flash for no-JS visitors or crawlers (`html.js-reveal .reveal-on-scroll`).
 */
function pura_theme_js_class(): void {
	echo "<script>document.documentElement.classList.add('js-reveal');</script>\n";
}
add_action( 'wp_head', 'pura_theme_js_class', 0 );

/**
 * Remove the core "classic" stylesheet noise we don't need on the front end.
 */
function pura_theme_dequeue_global_styles_noise(): void {
	wp_dequeue_style( 'classic-theme-styles' );
}
add_action( 'wp_enqueue_scripts', 'pura_theme_dequeue_global_styles_noise', 20 );

/**
 * Navigation blocks without a `ref` render the imported menu (slug `navegacion-principal`)
 * instead of core's "most recently published wp_navigation" guess.
 *
 * @param array<int, array<string, mixed>> $fallback_blocks Core's fallback blocks.
 * @return array<int, array<string, mixed>>
 */
function pura_theme_navigation_fallback( array $fallback_blocks ): array {
	$menu = get_page_by_path( 'navegacion-principal', OBJECT, 'wp_navigation' );

	if ( ! $menu instanceof WP_Post || 'publish' !== $menu->post_status || '' === trim( $menu->post_content ) ) {
		return $fallback_blocks;
	}

	$blocks = parse_blocks( $menu->post_content );
	if ( function_exists( 'block_core_navigation_filter_out_empty_blocks' ) ) {
		$blocks = block_core_navigation_filter_out_empty_blocks( $blocks );
	}

	return $blocks ? $blocks : $fallback_blocks;
}
add_filter( 'block_core_navigation_render_fallback', 'pura_theme_navigation_fallback' );
