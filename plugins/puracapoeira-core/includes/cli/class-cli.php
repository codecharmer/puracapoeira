<?php
/**
 * `wp pura …` commands: diagnostics.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Cli;

use Pura\Core\Auth\Member_Accounts;
use Pura\Core\Auth\Providers;
use Pura\Core\Data\Evento_Post_Type;
use Pura\Core\Data\Galeria_Post_Type;
use Pura\Core\Data\Profesor_Post_Type;
use Pura\Core\Data\Sede_Post_Type;
use Pura\Core\Mailer;
use Pura\Core\Photo_Uploads;
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
		$slugs   = array( 'inicio', 'grupo', 'profesores', 'sedes', 'galeria', 'eventos', 'contacto', 'vadiando-na-ladeira', 'privacidad', 'cuenta', 'fotos' );
		foreach ( $slugs as $slug ) {
			if ( ! get_page_by_path( $slug ) ) {
				$missing[] = $slug;
			}
		}
		$checks['pages'] = array( array() === $missing, $missing ? 'faltan: ' . implode( ', ', $missing ) : count( $slugs ) . ' páginas' );

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

		$account_page           = get_page_by_path( 'cuenta' );
		$checks['account_page'] = array( $account_page instanceof \WP_Post, $account_page ? Settings::page_url( 'account' ) : 'sin página /cuenta/ (wp pura-theme import pages)' );
		$checks['member_role']  = array( null !== get_role( Member_Accounts::ROLE ), Member_Accounts::ROLE );

		// Never print the key here: CI logs of a public repository are public (see `wp pura photo-link`).
		$photos_page           = get_page_by_path( 'fotos' );
		$checks['photos_page'] = array( $photos_page instanceof \WP_Post, $photos_page ? Photo_Uploads::page_url() . ' (clave en Ajustes → Fotos de profesores, o wp pura photo-link)' : 'sin página /fotos/ (se crea sola al cargar el sitio con el tema activo)' );
		foreach ( Providers::all() as $provider ) {
			$checks[ 'auth_' . $provider->id() ] = array(
				$provider->is_configured(),
				( $provider->is_configured() ? 'configurado · ' : 'sin configurar · ' ) . $provider->redirect_uri(),
			);
		}

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

	/**
	 * Print the secret link colleagues use to upload photos, or replace it.
	 *
	 * ## OPTIONS
	 *
	 * [--regenerate]
	 * : Generate a new key; every link handed out before stops working.
	 *
	 * [--quiet]
	 * : Do not print the link (for scripts whose output is logged).
	 *
	 * ## EXAMPLES
	 *
	 *     wp pura photo-link
	 *     wp pura photo-link --regenerate --quiet
	 *
	 * @subcommand photo-link
	 *
	 * @param string[]              $args       Positional.
	 * @param array<string, string> $assoc_args Named.
	 */
	public function photo_link( array $args, array $assoc_args ): void {
		if ( isset( $assoc_args['regenerate'] ) ) {
			Photo_Uploads::regenerate();
			WP_CLI::success( 'Clave nueva generada; los enlaces anteriores ya no funcionan.' );
		}
		if ( ! isset( $assoc_args['quiet'] ) ) {
			WP_CLI::log( Photo_Uploads::link() );
		}
	}
}
