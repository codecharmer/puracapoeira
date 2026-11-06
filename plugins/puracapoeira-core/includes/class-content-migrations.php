<?php
/**
 * One-off content changes that must land on the live site with a deploy (the importer runs only
 * by hand). Each migration runs once and is recorded in the `pura_content_migrations` option; one
 * that returns false is retried on the next request.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core;

defined( 'ABSPATH' ) || exit;

final class Content_Migrations {

	public const OPTION = 'pura_content_migrations';

	/** @var string[] Method names, in order. Append; never rename or remove. */
	private const MIGRATIONS = array(
		'create_fotos_page',
		'create_app_page',
		'add_app_nav_link',
	);

	/** @var array{slug:string,title:string,excerpt:string,pattern:string,option:string} */
	private const FOTOS_PAGE = array(
		'slug'    => 'fotos',
		'title'   => 'Sube tus fotos',
		'excerpt' => 'Página privada para que profesores e instructores de Pura Capoeira compartan su foto de perfil y fotos de su sede.',
		'pattern' => 'puracapoeira/page-fotos',
		'option'  => 'photo_upload_page_id',
	);

	/** @var array{slug:string,title:string,excerpt:string,pattern:string,option:string} */
	private const APP_PAGE = array(
		'slug'    => 'app',
		'title'   => 'La app',
		'excerpt' => 'Descarga la app de Pura Capoeira: avisos, eventos, sedes y galería desde tu teléfono. En App Store para iPhone y como APK directo para Android.',
		'pattern' => 'puracapoeira/page-app',
		'option'  => 'app_page_id',
	);

	public static function register(): void {
		// After the theme has registered its patterns (init, priority 10).
		add_action( 'init', array( self::class, 'run' ), 20 );
	}

	public static function run(): void {
		if ( wp_installing() ) {
			return;
		}

		$done    = get_option( self::OPTION, array() );
		$done    = is_array( $done ) ? $done : array();
		$pending = array_diff( self::MIGRATIONS, $done );
		if ( ! $pending ) {
			return;
		}

		foreach ( $pending as $migration ) {
			if ( ! is_callable( array( self::class, $migration ) ) ) {
				continue;
			}
			if ( true === call_user_func( array( self::class, $migration ) ) ) {
				$done[] = $migration;
				update_option( self::OPTION, array_values( $done ), true );
			}
		}
	}

	/**
	 * Publish /fotos/ from its pattern and remember its ID in settings.
	 */
	public static function create_fotos_page(): bool {
		return self::publish_page_from_pattern( self::FOTOS_PAGE );
	}

	/**
	 * Publish /app/ (the app download page) from its pattern and remember its ID in settings.
	 */
	public static function create_app_page(): bool {
		return self::publish_page_from_pattern( self::APP_PAGE );
	}

	/**
	 * Append the "App" item to the main navigation. The importer only rebuilds the menu when run
	 * by hand, so the live menu needs this once. Same link shape as the importer's menu().
	 */
	public static function add_app_nav_link(): bool {
		$page = get_page_by_path( self::APP_PAGE['slug'] );
		if ( ! $page ) {
			// create_app_page has not succeeded yet; try again on the next request.
			return false;
		}

		$menu = get_page_by_path( 'navegacion-principal', OBJECT, 'wp_navigation' );
		if ( ! $menu ) {
			// No imported menu to extend; the importer builds it with the link included.
			return true;
		}
		if ( false !== strpos( (string) $menu->post_content, 'i18n-nav-app' ) ) {
			return true;
		}

		$link = '<!-- wp:navigation-link ' . wp_json_encode(
			array(
				'label'     => 'App',
				'type'      => 'page',
				'id'        => $page->ID,
				'url'       => get_permalink( $page ),
				'kind'      => 'post-type',
				'className' => 'i18n-nav-app',
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		) . ' /-->';

		$result = wp_update_post(
			array(
				'ID'           => $menu->ID,
				'post_content' => $menu->post_content . "\n" . $link,
			),
			true
		);

		return ! is_wp_error( $result );
	}

	/**
	 * @param array{slug:string,title:string,excerpt:string,pattern:string,option:string} $page Page spec.
	 */
	private static function publish_page_from_pattern( array $page ): bool {
		$existing = get_page_by_path( $page['slug'] );
		if ( $existing ) {
			if ( 'publish' !== $existing->post_status ) {
				wp_publish_post( $existing );
			}
			Settings::update( array( $page['option'] => $existing->ID ) );

			return true;
		}

		$pattern = \WP_Block_Patterns_Registry::get_instance()->get_registered( $page['pattern'] );
		if ( ! $pattern ) {
			// Pattern cache not refreshed yet; try again on the next request.
			return false;
		}

		$blocks  = parse_blocks( (string) $pattern['content'] );
		$blocks  = function_exists( 'resolve_pattern_blocks' ) ? resolve_pattern_blocks( $blocks ) : $blocks;
		$post_id = wp_insert_post(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'post_name'      => $page['slug'],
				'post_title'     => $page['title'],
				'post_excerpt'   => $page['excerpt'],
				'post_content'   => serialize_blocks( $blocks ),
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			),
			true
		);
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return false;
		}

		Settings::update( array( $page['option'] => (int) $post_id ) );

		return true;
	}
}
