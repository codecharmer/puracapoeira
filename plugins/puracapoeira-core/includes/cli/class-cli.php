<?php
/**
 * `wp pura …` commands: diagnostics.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Cli;

use Pura\Core\Data\Evento_Post_Type;
use Pura\Core\Data\Galeria_Post_Type;
use Pura\Core\Data\Profesor_Post_Type;
use Pura\Core\Data\Sede_Post_Type;
use Pura\Core\Mailer;
use Pura\Core\Settings;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

final class Cli {

	public static function register(): void {
		WP_CLI::add_command( 'pura', self::class );
	}

	/**
	 * Check configuration: permalinks, pages, content counts, menu, logo, mail recipients, SMTP.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pura doctor
	 *
	 * @param string[]              $args       Positional.
	 * @param array<string, string> $assoc_args Named.
	 */
	public function doctor( array $args, array $assoc_args ): void {
		$checks = array();

		$permalinks           = (string) get_option( 'permalink_structure' );
		$checks['permalinks'] = array( '' !== $permalinks, $permalinks ?: 'plain (ejecuta: wp rewrite structure "/%postname%/" --hard)' );

		$theme           = wp_get_theme();
		$checks['theme'] = array( 'puracapoeira' === $theme->get_stylesheet(), $theme->get_stylesheet() );

		$missing = array();
		foreach ( array( 'inicio', 'grupo', 'profesores', 'sedes', 'galeria', 'eventos', 'contacto' ) as $slug ) {
			if ( ! get_page_by_path( $slug ) ) {
				$missing[] = $slug;
			}
		}
		$checks['pages'] = array( array() === $missing, $missing ? 'faltan: ' . implode( ', ', $missing ) : '7 páginas' );

		$front                = get_page_by_path( 'inicio' );
		$checks['front_page'] = array(
			'page' === get_option( 'show_on_front' ) && $front && (int) get_option( 'page_on_front' ) === $front->ID,
			'page_on_front=' . (string) get_option( 'page_on_front' ),
		);

		foreach ( array(
			'sedes'      => array( Sede_Post_Type::POST_TYPE, 6 ),
			'profesores' => array( Profesor_Post_Type::POST_TYPE, 9 ),
			'eventos'    => array( Evento_Post_Type::POST_TYPE, 1 ),
			'galeria'    => array( Galeria_Post_Type::POST_TYPE, 1 ),
		) as $name => [ $type, $min ] ) {
			$count           = (int) ( wp_count_posts( $type )->publish ?? 0 );
			$checks[ $name ] = array( $count >= $min, "{$count} publicados" );
		}

		$menu           = get_page_by_path( 'navegacion-principal', OBJECT, 'wp_navigation' );
		$checks['menu'] = array( $menu instanceof \WP_Post, $menu ? '#' . $menu->ID : 'sin menú navegacion-principal (wp pura-theme import menu)' );

		$checks['logo']     = array( (int) get_theme_mod( 'custom_logo' ) > 0, 'custom_logo=' . (string) get_theme_mod( 'custom_logo' ) );
		$checks['og_image'] = array( (int) Settings::get( 'og_image_id', 0 ) > 0, 'og_image_id=' . (string) Settings::get( 'og_image_id', 0 ) );

		$checks['contact_to'] = array( count( Mailer::recipients() ) > 0, implode( ', ', Mailer::recipients() ) ?: 'ninguno' );

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$checks['smtp_plugin'] = array( is_plugin_active( 'fluent-smtp/fluent-smtp.php' ), 'fluent-smtp' );

		$checks['file_edit'] = array( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT, 'DISALLOW_FILE_EDIT' );

		if ( 'production' === wp_get_environment_type() ) {
			$checks['blog_public'] = array( '1' === (string) get_option( 'blog_public' ), 'blog_public=' . (string) get_option( 'blog_public' ) );
		}

		$failures = 0;
		foreach ( $checks as $name => [ $ok, $detail ] ) {
			WP_CLI::log( sprintf( '%s %-14s %s', $ok ? WP_CLI::colorize( '%G✔%n' ) : WP_CLI::colorize( '%R✘%n' ), $name, $detail ) );
			if ( ! $ok ) {
				++$failures;
			}
		}

		if ( $failures ) {
			WP_CLI::warning( "{$failures} comprobación(es) requieren atención." );
		} else {
			WP_CLI::success( 'Todo en orden.' );
		}
	}
}
