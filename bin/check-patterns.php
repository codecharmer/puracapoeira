<?php
/**
 * Validates the theme's block markup without WordPress: patterns (PHP), template parts and
 * templates (HTML). Checks that every `<!-- wp:x -->` has a matching closer, that block attribute
 * JSON parses, that referenced `wp:pattern` slugs exist, and that `pura/*` blocks exist in src/.
 *
 * Usage: php bin/check-patterns.php   (exit code 1 on failure)
 */

declare( strict_types=1 );

$root  = dirname( __DIR__ ) . '/themes/puracapoeira';
$fails = array();

// Stubs for the WordPress functions patterns may call.
function esc_url( string $url ): string { return $url; }
function esc_attr( string $text ): string { return htmlspecialchars( $text, ENT_QUOTES ); }
function esc_html( string $text ): string { return htmlspecialchars( $text, ENT_QUOTES ); }
function esc_html__( string $text, string $domain = '' ): string { return $text; }
function esc_attr__( string $text, string $domain = '' ): string { return $text; }
function __( string $text, string $domain = '' ): string { return $text; }
function esc_html_e( string $text, string $domain = '' ): void { echo $text; }
function esc_attr_e( string $text, string $domain = '' ): void { echo $text; }
function _e( string $text, string $domain = '' ): void { echo $text; }
function get_theme_file_uri( string $file = '' ): string { return 'https://example.test/wp-content/themes/puracapoeira/' . ltrim( $file, '/' ); }
function get_template_directory_uri(): string { return 'https://example.test/wp-content/themes/puracapoeira'; }
function home_url( string $path = '' ): string { return 'https://example.test' . $path; }
define( 'ABSPATH', $root . '/' );
define( 'PURA_THEME_DIR', $root );
define( 'PURA_THEME_URI', get_template_directory_uri() );

$block_names = array();
foreach ( glob( $root . '/src/blocks/*/block.json' ) ?: array() as $json_file ) {
	$meta = json_decode( (string) file_get_contents( $json_file ), true );
	if ( ! is_array( $meta ) || empty( $meta['name'] ) ) {
		$fails[] = "$json_file: invalid block.json";
		continue;
	}
	$block_names[] = (string) $meta['name'];
}

$pattern_slugs = array();
$pattern_files = glob( $root . '/patterns/*.php' ) ?: array();
foreach ( $pattern_files as $file ) {
	$head = (string) file_get_contents( $file );
	if ( preg_match( '/^\s*\*\s*Slug:\s*(\S+)/m', $head, $m ) ) {
		$pattern_slugs[ $m[1] ] = $file;
	} else {
		$fails[] = "$file: missing Slug header";
	}
}

/**
 * Tokenise block comments, validate JSON and nesting.
 *
 * @return string[] Errors.
 */
function check_markup( string $label, string $html, array $pattern_slugs, array $block_names ): array {
	$errors = array();
	$stack  = array();
	$offset = 0;
	$len    = strlen( $html );

	while ( false !== ( $pos = strpos( $html, '<!-- ', $offset ) ) ) {
		$end = strpos( $html, '-->', $pos );
		if ( false === $end ) {
			$errors[] = "$label: unterminated comment at byte $pos";
			break;
		}
		$comment = substr( $html, $pos + 5, $end - $pos - 5 );
		$offset  = $end + 3;

		if ( preg_match( '#^/wp:([a-z0-9-]+(?:/[a-z0-9-]+)?)\s*$#', $comment, $m ) ) {
			$name = str_contains( $m[1], '/' ) ? $m[1] : 'core/' . $m[1];
			$open = array_pop( $stack );
			if ( null === $open ) {
				$errors[] = "$label: closing $name without an opener";
			} elseif ( $open !== $name ) {
				$errors[] = "$label: expected closing $open, found $name";
			}
			continue;
		}

		if ( ! preg_match( '#^wp:([a-z0-9-]+(?:/[a-z0-9-]+)?)(?:\s+(\{.*\}))?\s*(/)?\s*$#s', $comment, $m ) ) {
			if ( str_starts_with( $comment, 'wp:' ) || str_starts_with( $comment, '/wp:' ) ) {
				$errors[] = "$label: malformed block comment: " . substr( trim( $comment ), 0, 80 );
			}
			continue;
		}

		$name         = str_contains( $m[1], '/' ) ? $m[1] : 'core/' . $m[1];
		$attrs        = $m[2] ?? '';
		$self_closing = ! empty( $m[3] );

		if ( '' !== $attrs ) {
			$decoded = json_decode( $attrs, true );
			if ( ! is_array( $decoded ) ) {
				$errors[] = "$label: invalid JSON in $name: " . substr( $attrs, 0, 80 );
			} elseif ( 'core/pattern' === $name ) {
				$slug = (string) ( $decoded['slug'] ?? '' );
				if ( ! isset( $pattern_slugs[ $slug ] ) ) {
					$errors[] = "$label: unknown pattern slug '$slug'";
				}
			}
		}

		if ( str_starts_with( $name, 'pura/' ) && ! in_array( $name, $block_names, true ) ) {
			$errors[] = "$label: unknown block $name";
		}

		if ( ! $self_closing ) {
			$stack[] = $name;
		}
	}

	foreach ( $stack as $unclosed ) {
		$errors[] = "$label: unclosed block $unclosed";
	}

	return $errors;
}

foreach ( $pattern_files as $file ) {
	ob_start();
	try {
		include $file;
		$markup = (string) ob_get_clean();
	} catch ( Throwable $e ) {
		ob_end_clean();
		$fails[] = "$file: " . $e->getMessage();
		continue;
	}
	$fails = array_merge( $fails, check_markup( basename( $file ), $markup, $pattern_slugs, $block_names ) );
}

foreach ( array_merge( glob( $root . '/parts/*.html' ) ?: array(), glob( $root . '/templates/*.html' ) ?: array() ) as $file ) {
	$fails = array_merge( $fails, check_markup( basename( dirname( $file ) ) . '/' . basename( $file ), (string) file_get_contents( $file ), $pattern_slugs, $block_names ) );
}

$theme_json = json_decode( (string) file_get_contents( $root . '/theme.json' ), true );
if ( ! is_array( $theme_json ) ) {
	$fails[] = 'theme.json: invalid JSON';
}
foreach ( glob( $root . '/assets/i18n/*.json' ) ?: array() as $file ) {
	if ( ! is_array( json_decode( (string) file_get_contents( $file ), true ) ) ) {
		$fails[] = "$file: invalid JSON";
	}
}

if ( $fails ) {
	fwrite( STDERR, implode( "\n", $fails ) . "\n" );
	exit( 1 );
}

echo sprintf( "OK: %d patterns, %d blocks, parts and templates validated.\n", count( $pattern_files ), count( $block_names ) );
