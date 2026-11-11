<?php
/**
 * A sede or a profesor as the editor page sees it: the values a professor may change, the
 * ones shown for reference, and the time of the last change (used to detect that someone
 * else saved in the meantime).
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Editor;

use Pura\Core\Data\Profesor_Post_Type;
use Pura\Core\Data\Sede_Post_Type;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Editor_Document {

	/** Counts the saves made through the editor (the post's modified time alone has one-second resolution). */
	public const META_REVISION = '_pura_editor_rev';

	/**
	 * The token a save must send back: it changes whenever the post is saved, here or in the
	 * admin, so a save made from an older copy can be refused instead of undoing newer work.
	 */
	public static function version( WP_Post $post ): string {
		return $post->post_modified_gmt . '#' . (int) get_post_meta( $post->ID, self::META_REVISION, true );
	}

	/** 'sede' or 'profesor' for a post, '' for anything else. */
	public static function type_of( WP_Post $post ): string {
		if ( Sede_Post_Type::POST_TYPE === $post->post_type ) {
			return Editor_Fields::TYPE_SEDE;
		}
		if ( Profesor_Post_Type::POST_TYPE === $post->post_type ) {
			return Editor_Fields::TYPE_PROFESOR;
		}

		return '';
	}

	/**
	 * Current values of the editable fields.
	 *
	 * @return array<string, mixed>
	 */
	public static function values( WP_Post $post ): array {
		$values = array();
		foreach ( Editor_Fields::definitions( self::type_of( $post ) ) as $name => $definition ) {
			if ( Editor_Fields::STORE_EXCERPT === $definition['store'] ) {
				$values[ $name ] = (string) $post->post_excerpt;
				continue;
			}
			$stored = get_post_meta( $post->ID, (string) $definition['store'], true );
			if ( 'rows' === $definition['kind'] ) {
				$rows = array();
				foreach ( is_array( $stored ) ? $stored : array() as $row ) {
					$clean = array();
					foreach ( $definition['columns'] as $column ) {
						$clean[ $column ] = is_array( $row ) && isset( $row[ $column ] ) ? (string) $row[ $column ] : '';
					}
					$rows[] = $clean;
				}
				$values[ $name ] = $rows;
			} else {
				$values[ $name ] = is_scalar( $stored ) ? (string) $stored : '';
			}
		}

		return $values;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function for_post( WP_Post $post ): array {
		$type = self::type_of( $post );
		$meta = static fn ( string $key ): string => (string) get_post_meta( $post->ID, $key, true );

		if ( Editor_Fields::TYPE_SEDE === $type ) {
			$about = array(
				'city'    => $meta( Sede_Post_Type::META_CITY ),
				'region'  => $meta( Sede_Post_Type::META_REGION ),
				'country' => $meta( Sede_Post_Type::META_COUNTRY ),
			);
		} else {
			$sede_id = (int) get_post_meta( $post->ID, Profesor_Post_Type::META_SEDE_ID, true );
			$about   = array(
				'fullName' => $meta( Profesor_Post_Type::META_FULL_NAME ),
				'rank'     => $meta( Profesor_Post_Type::META_RANK ),
				'city'     => $meta( Profesor_Post_Type::META_CITY ),
				'sede'     => $sede_id ? (string) get_the_title( $sede_id ) : '',
			);
		}

		$image_id = (int) get_post_thumbnail_id( $post );

		return array(
			'id'       => $post->ID,
			'type'     => $type,
			'title'    => (string) get_the_title( $post ),
			'url'      => (string) get_permalink( $post ),
			// Sent back with a save to detect that someone else saved in the meantime.
			'modified' => self::version( $post ),
			'about'    => $about,
			'fields'   => self::values( $post ),
			'image'    => $image_id ? array(
				'id'  => $image_id,
				'url' => (string) wp_get_attachment_image_url( $image_id, 'medium_large' ),
			) : null,
			'gallery'  => Editor_Fields::TYPE_SEDE === $type ? Editor_Photos::gallery( $post->ID ) : null,
			'bio'      => Editor_Fields::TYPE_PROFESOR === $type ? Bio_Document::read( (string) $post->post_content ) : null,
		);
	}
}
