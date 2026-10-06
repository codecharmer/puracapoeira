<?php
/**
 * `pura_galeria` post type + category / location taxonomies. Replaces data/gallery.json.
 *
 * Items link out (iCloud albums, YouTube…), so the type has no single view.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Data;

defined( 'ABSPATH' ) || exit;

final class Galeria_Post_Type {

	public const POST_TYPE    = 'pura_galeria';
	public const TAX_CATEGORY = 'pura_galeria_cat';
	public const TAX_LOCATION = 'pura_galeria_loc';
	public const META_URL     = 'pura_item_url';

	public function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'               => __( 'Galería', 'pura' ),
					'singular_name'      => __( 'Elemento de galería', 'pura' ),
					'add_new'            => __( 'Añadir elemento', 'pura' ),
					'add_new_item'       => __( 'Añadir elemento', 'pura' ),
					'edit_item'          => __( 'Editar elemento', 'pura' ),
					'new_item'           => __( 'Nuevo elemento', 'pura' ),
					'all_items'          => __( 'Galería', 'pura' ),
					'search_items'       => __( 'Buscar en la galería', 'pura' ),
					'not_found'          => __( 'La galería está vacía.', 'pura' ),
					'featured_image'     => __( 'Miniatura', 'pura' ),
					'set_featured_image' => __( 'Elegir miniatura', 'pura' ),
				),
				'description'         => __( 'Fotos y videos de clases, rodas, música y eventos. El extracto es la descripción corta.', 'pura' ),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'pura',
				'show_in_rest'        => true,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'menu_icon'           => 'dashicons-format-gallery',
				'supports'            => array( 'title', 'excerpt', 'thumbnail', 'custom-fields' ),
				'capability_type'     => 'post',
			)
		);

		foreach ( array(
			self::TAX_CATEGORY => array( __( 'Categorías', 'pura' ), __( 'Categoría', 'pura' ) ),
			self::TAX_LOCATION => array( __( 'Sedes (galería)', 'pura' ), __( 'Sede', 'pura' ) ),
		) as $taxonomy => $labels ) {
			register_taxonomy(
				$taxonomy,
				self::POST_TYPE,
				array(
					'labels'            => array(
						'name'          => $labels[0],
						'singular_name' => $labels[1],
					),
					'public'            => false,
					'show_ui'           => true,
					'show_in_rest'      => true,
					'show_admin_column' => true,
					'hierarchical'      => false,
					'rewrite'           => false,
				)
			);
		}

		( new Meta_Fields(
			self::POST_TYPE,
			'pura_galeria_details',
			__( 'Enlace', 'pura' ),
			array(
				self::META_URL => array(
					'type'        => 'url',
					'label'       => __( 'Enlace del álbum o video', 'pura' ),
					'description' => __( 'Déjalo vacío si el elemento no enlaza a ningún sitio. La miniatura se toma de la imagen destacada.', 'pura' ),
				),
			)
		) )->register();

		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
	}

	/**
	 * Published items, newest first.
	 *
	 * @return \WP_Post[]
	 */
	public static function all( int $limit = 48 ): array {
		return get_posts(
			array(
				'post_type'              => self::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => $limit,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => true,
			)
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_data( \WP_Post $post ): array {
		$terms = static function ( string $taxonomy ) use ( $post ): array {
			$list = get_the_terms( $post, $taxonomy );

			return is_array( $list ) ? array_values( $list ) : array();
		};

		return array(
			'id'          => $post->ID,
			'title'       => get_the_title( $post ),
			'description' => (string) $post->post_excerpt,
			'date'        => get_the_date( 'Y-m-d', $post ),
			'url'         => (string) get_post_meta( $post->ID, self::META_URL, true ),
			'image_id'    => (int) get_post_thumbnail_id( $post ),
			'categories'  => $terms( self::TAX_CATEGORY ),
			'locations'   => $terms( self::TAX_LOCATION ),
		);
	}

	/**
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function columns( array $columns ): array {
		$new = array();
		foreach ( $columns as $key => $label ) {
			if ( 'title' === $key ) {
				$new['thumb'] = __( 'Miniatura', 'pura' );
			}
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['item_url'] = __( 'Enlace', 'pura' );
			}
		}

		return $new;
	}

	public function column_content( string $column, int $post_id ): void {
		if ( 'thumb' === $column ) {
			echo get_the_post_thumbnail( $post_id, array( 80, 60 ) );
		}
		if ( 'item_url' === $column ) {
			$url = (string) get_post_meta( $post_id, self::META_URL, true );
			if ( '' !== $url ) {
				echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( wp_parse_url( $url, PHP_URL_HOST ) ?: $url ) . '</a>';
			}
		}
	}
}
