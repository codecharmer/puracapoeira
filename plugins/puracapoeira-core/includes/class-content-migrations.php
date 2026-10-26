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
	);

	/** @var array{slug:string,title:string,excerpt:string,pattern:string,option:string} */
	private const FOTOS_PAGE = array(
		'slug'    => 'fotos',
		'title'   => 'Sube tus fotos',
		'excerpt' => 'Página privada para que profesores e instructores de Pura Capoeira compartan su foto de perfil y fotos de su sede.',
		'pattern' => 'puracapoeira/page-fotos',
		'option'  => 'photo_upload_page_id',
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
