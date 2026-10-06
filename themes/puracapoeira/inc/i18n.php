<?php
/**
 * Client-side language switcher (Spanish server-rendered, English/Portuguese applied in the browser).
 *
 * The browser work lives in assets/js/i18n.js (+ assets/js/i18n/core.js). This file:
 *
 * - enqueues that script module;
 * - prints `window.puraI18nConfig` plus a tiny ES5 bootstrap in <head> (priority 0) that picks the
 *   language and starts the dictionary download before the body is parsed;
 * - prints the per-post dictionary (`_pura_i18n` post meta, registered and sanitised by the core
 *   plugin) as `<script type="application/json" id="pura-i18n-post">` on singular views;
 * - renders the language-switch button group and injects it into the `site-nav` navigation block.
 *
 * @package Pura
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Supported language codes. The first one is the server-rendered default.
 *
 * @return string[]
 */
function pura_theme_i18n_langs(): array {
	return array( 'es', 'en', 'pt' );
}

/**
 * Cache-busting version for a theme file: its mtime, falling back to the theme version.
 *
 * Dictionaries change with content, so this always prefers the mtime (unlike the WP_DEBUG-only
 * helper in setup.php).
 *
 * @param string $relative_path Path relative to the theme root.
 */
function pura_theme_i18n_file_version( string $relative_path ): string {
	$file  = PURA_THEME_DIR . '/' . ltrim( $relative_path, '/' );
	$mtime = file_exists( $file ) ? filemtime( $file ) : false;

	return false !== $mtime ? (string) $mtime : PURA_THEME_VERSION;
}

/**
 * Dictionary scope of the current view: `page` + slug, `pura_profesor`/`pura_sede` + slug, or `other`.
 *
 * @return array{type: string, slug: string}
 */
function pura_theme_i18n_scope(): array {
	$type = 'other';

	if ( is_page() ) {
		$type = 'page';
	} elseif ( is_singular( array( pura_theme_post_type( 'profesor' ), pura_theme_post_type( 'sede' ) ) ) ) {
		$type = (string) get_post_type( get_queried_object_id() );
	}

	$slug = 'other' === $type ? '' : (string) get_post_field( 'post_name', get_queried_object_id() );

	return array(
		'type' => $type,
		'slug' => $slug,
	);
}

/**
 * Configuration printed as `window.puraI18nConfig`.
 *
 * @return array<string, mixed>
 */
function pura_theme_i18n_config(): array {
	$langs = pura_theme_i18n_langs();
	$dict  = array();

	foreach ( $langs as $lang ) {
		if ( 'es' === $lang ) {
			continue;
		}
		$relative      = 'assets/i18n/' . $lang . '.json';
		$dict[ $lang ] = add_query_arg(
			'ver',
			pura_theme_i18n_file_version( $relative ),
			PURA_THEME_URI . '/' . $relative
		);
	}

	return array(
		'langs'       => $langs,
		'defaultLang' => 'es',
		'dict'        => $dict,
		'scope'       => pura_theme_i18n_scope(),
		'serverLang'  => (string) get_bloginfo( 'language' ),
		'siteName'    => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
		'storageKey'  => 'pc_lang',
	);
}

/**
 * Front-end module. `assets/js/i18n/core.js` is imported relatively by the module itself.
 */
function pura_theme_i18n_enqueue(): void {
	wp_enqueue_script_module(
		'pura-i18n',
		PURA_THEME_URI . '/assets/js/i18n.js',
		array(),
		pura_theme_i18n_file_version( 'assets/js/i18n.js' )
	);
}
add_action( 'wp_enqueue_scripts', 'pura_theme_i18n_enqueue' );

/**
 * Inline bootstrap, as early as possible in <head>.
 *
 * Picks the language (`?lang=` → localStorage → navigator.language → es), sets `<html lang>` for
 * en/pt and starts the dictionary fetch as `puraI18nConfig.pending` so the download overlaps HTML
 * parsing. Plain ES5 on purpose; every storage access is wrapped in try/catch.
 */
function pura_theme_i18n_head_script(): void {
	$config = wp_json_encode(
		pura_theme_i18n_config(),
		JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	);

	if ( false === $config ) {
		return;
	}

	$bootstrap = <<<'JS'
(function () {
	var c = window.puraI18nConfig;
	var lang = null;
	var m = /[?&]lang=([A-Za-z]{2})(?:&|$|#)/.exec(window.location.search);
	if (m && c.langs.indexOf(m[1].toLowerCase()) !== -1) {
		lang = m[1].toLowerCase();
	}
	if (!lang) {
		try {
			var saved = window.localStorage.getItem(c.storageKey);
			if (saved && c.langs.indexOf(saved) !== -1) {
				lang = saved;
			}
		} catch (e) {}
	}
	if (!lang) {
		var nav = String((window.navigator && window.navigator.language) || '').toLowerCase();
		lang = nav.indexOf('en') === 0 ? 'en' : (nav.indexOf('pt') === 0 ? 'pt' : c.defaultLang);
	}
	c.initialLang = lang;
	if (lang !== c.defaultLang && c.dict[lang] && typeof window.fetch === 'function') {
		document.documentElement.lang = lang;
		c.pending = window.fetch(c.dict[lang], { credentials: 'same-origin' }).then(function (r) {
			if (!r.ok) {
				throw new Error('HTTP ' + r.status);
			}
			return r.json();
		});
		c.pending['catch'](function () {});
	}
})();
JS;

	wp_print_inline_script_tag( 'window.puraI18nConfig = ' . $config . ";\n" . $bootstrap );
}
add_action( 'wp_head', 'pura_theme_i18n_head_script', 0 );

/**
 * Per-post translations (`_pura_i18n` meta, JSON `{ "en": {…}, "pt": {…} }`) for the module to read.
 */
function pura_theme_i18n_print_post_dictionary(): void {
	if ( ! is_singular() ) {
		return;
	}

	$raw = get_post_meta( get_queried_object_id(), '_pura_i18n', true );
	if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
		return;
	}

	$decoded = json_decode( $raw, true );
	if ( ! is_array( $decoded ) || array() === $decoded ) {
		return;
	}

	$json = wp_json_encode( $decoded, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE );
	if ( false === $json ) {
		return;
	}

	wp_print_inline_script_tag(
		$json,
		array(
			'type' => 'application/json',
			'id'   => 'pura-i18n-post',
		)
	);
}
add_action( 'wp_footer', 'pura_theme_i18n_print_post_dictionary' );

/**
 * The language-switch button group. Spanish is pressed by default; the module syncs the rest.
 *
 * @param string $variant `header` (desktop bar) or `overlay` (inside the mobile navigation overlay).
 */
function pura_theme_lang_switch_markup( string $variant = 'header' ): string {
	if ( ! in_array( $variant, array( 'header', 'overlay' ), true ) ) {
		$variant = 'header';
	}

	$languages = array(
		'en' => array(
			'flag'  => '🇺🇸',
			'label' => 'English',
		),
		'es' => array(
			'flag'  => '🇲🇽',
			'label' => 'Español',
		),
		'pt' => array(
			'flag'  => '🇧🇷',
			'label' => 'Português',
		),
	);

	$html = sprintf(
		'<div class="%s" role="group" aria-label="%s" data-i18n-attr="aria-label:common.langSwitcher">',
		esc_attr( 'lang-switch lang-switch--' . $variant ),
		esc_attr__( 'Selector de idioma', 'pura' )
	);

	foreach ( $languages as $code => $language ) {
		$active = 'es' === $code;
		$html  .= sprintf(
			'<button type="button" class="%1$s" data-lang="%2$s" lang="%2$s" aria-label="%3$s" title="%3$s" aria-pressed="%4$s"><span aria-hidden="true">%5$s</span></button>',
			esc_attr( $active ? 'lang-btn active' : 'lang-btn' ),
			esc_attr( $code ),
			esc_attr( $language['label'] ),
			$active ? 'true' : 'false',
			esc_html( $language['flag'] )
		);
	}

	return $html . '</div>';
}

/**
 * Insert markup right after the last `</ul>` of a navigation block's HTML (i.e. after the whole
 * menu, inside the responsive container). Returns the HTML unchanged when there is no list.
 *
 * @param string $html   Rendered navigation HTML.
 * @param string $markup Markup to inject.
 */
function pura_theme_inject_overlay_lang_switch( string $html, string $markup ): string {
	$needle   = '</ul>';
	$position = strrpos( $html, $needle );

	if ( false === $position ) {
		return $html;
	}

	$position += strlen( $needle );

	return substr( $html, 0, $position ) . $markup . substr( $html, $position );
}

/**
 * Add the overlay language switch to the main navigation block (the one with class `site-nav`).
 *
 * @param string               $block_content Rendered block HTML.
 * @param array<string, mixed> $block         Parsed block.
 */
function pura_theme_navigation_lang_switch( string $block_content, array $block ): string {
	$class_name = (string) ( $block['attrs']['className'] ?? '' );

	if ( '' === $class_name || ! str_contains( $class_name, 'site-nav' ) ) {
		return $block_content;
	}

	return pura_theme_inject_overlay_lang_switch(
		$block_content,
		pura_theme_lang_switch_markup( 'overlay' )
	);
}
add_filter( 'render_block_core/navigation', 'pura_theme_navigation_lang_switch', 10, 2 );
