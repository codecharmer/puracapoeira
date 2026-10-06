<?php
/**
 * `pura_profesor` post type: mestres, profesores and instructors. Replaces data/profesores.json
 * and the static profile pages (the long biography lives in the post content).
 *
 * Public at /profesores/<slug>/ while the "Profesores" page lists them at /profesores/.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Data;

defined( 'ABSPATH' ) || exit;

final class Profesor_Post_Type {

	public const POST_TYPE    = 'pura_profesor';
	public const REWRITE_SLUG = 'profesores';

	public const META_FULL_NAME        = 'pura_full_name';
	public const META_RANK             = 'pura_rank';
	public const META_CITY             = 'pura_city';
	public const META_COUNTRY          = 'pura_country';
	public const META_SEDE_ID          = 'pura_sede_id';
	public const META_HERO_EYEBROW     = 'pura_hero_eyebrow';
	public const META_SUBTITLE         = 'pura_subtitle';
	public const META_CAPTION          = 'pura_caption';
	public const META_INSTAGRAM        = 'pura_instagram';
	public const META_INSTAGRAM_HANDLE = 'pura_instagram_handle';
	public const META_FACEBOOK         = 'pura_facebook';
	public const META_WEBSITE          = 'pura_website';
	public const META_YOUTUBE          = 'pura_youtube';
	public const META_WHATSAPP         = 'pura_whatsapp';
	public const META_WHATSAPP_DISPLAY = 'pura_whatsapp_display';

	public function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'               => __( 'Profesores', 'pura' ),
					'singular_name'      => __( 'Profesor', 'pura' ),
					'add_new'            => __( 'Añadir profesor', 'pura' ),
					'add_new_item'       => __( 'Añadir profesor', 'pura' ),
					'edit_item'          => __( 'Editar profesor', 'pura' ),
					'new_item'           => __( 'Nuevo profesor', 'pura' ),
					'all_items'          => __( 'Profesores', 'pura' ),
					'view_item'          => __( 'Ver perfil', 'pura' ),
					'search_items'       => __( 'Buscar profesores', 'pura' ),
					'not_found'          => __( 'No hay profesores.', 'pura' ),
					'featured_image'     => __( 'Foto de perfil', 'pura' ),
					'set_featured_image' => __( 'Elegir foto', 'pura' ),
				),
				'description'     => __( 'Mestres, profesores e instructores. El título es el apodo de capoeira; el extracto es la biografía corta de la tarjeta; el contenido es la biografía completa.', 'pura' ),
				'public'          => true,
				'show_ui'         => true,
				'show_in_menu'    => 'pura',
				'show_in_rest'    => true,
				'has_archive'     => false,
				'rewrite'         => array(
					'slug'       => self::REWRITE_SLUG,
					'with_front' => false,
				),
				'menu_icon'       => 'dashicons-groups',
				'supports'        => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes', 'custom-fields' ),
				'capability_type' => 'post',
			)
		);

		( new Meta_Fields(
			self::POST_TYPE,
			'pura_profesor_details',
			__( 'Datos del profesor', 'pura' ),
			self::fields()
		) )->register();

		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function fields(): array {
		return array(
			self::META_FULL_NAME        => array(
				'type'  => 'text',
				'label' => __( 'Nombre completo', 'pura' ),
			),
			self::META_RANK             => array(
				'type'        => 'text',
				'label'       => __( 'Grado', 'pura' ),
				'description' => __( 'Mestre, Contramestre, Profesor, Profesora, Instructor, Instructora…', 'pura' ),
			),
			self::META_CITY             => array(
				'type'  => 'text',
				'label' => __( 'Ciudad', 'pura' ),
			),
			self::META_COUNTRY          => array(
				'type'  => 'text',
				'label' => __( 'País', 'pura' ),
			),
			self::META_SEDE_ID          => array(
				'type'      => 'post',
				'label'     => __( 'Sede', 'pura' ),
				'post_type' => Sede_Post_Type::POST_TYPE,
			),
			self::META_HERO_EYEBROW     => array(
				'type'        => 'text',
				'label'       => __( 'Antetítulo del perfil', 'pura' ),
				'description' => __( 'Por ejemplo: Profesor · Cuernavaca, Morelos, México', 'pura' ),
			),
			self::META_SUBTITLE         => array(
				'type'  => 'textarea',
				'label' => __( 'Entradilla del perfil', 'pura' ),
			),
			self::META_CAPTION          => array(
				'type'  => 'text',
				'label' => __( 'Pie de foto', 'pura' ),
			),
			self::META_INSTAGRAM        => array(
				'type'  => 'url',
				'label' => __( 'Instagram (URL)', 'pura' ),
			),
			self::META_INSTAGRAM_HANDLE => array(
				'type'  => 'text',
				'label' => __( 'Instagram (usuario)', 'pura' ),
			),
			self::META_FACEBOOK         => array(
				'type'  => 'url',
				'label' => __( 'Facebook (URL)', 'pura' ),
			),
			self::META_WEBSITE          => array(
				'type'  => 'url',
				'label' => __( 'Sitio web', 'pura' ),
			),
			self::META_YOUTUBE          => array(
				'type'  => 'url',
				'label' => __( 'YouTube (URL)', 'pura' ),
			),
			self::META_WHATSAPP         => array(
				'type'  => 'url',
				'label' => __( 'WhatsApp (enlace)', 'pura' ),
			),
			self::META_WHATSAPP_DISPLAY => array(
				'type'  => 'text',
				'label' => __( 'WhatsApp (como se muestra)', 'pura' ),
			),
		);
	}

	/**
	 * Published teachers in display order.
	 *
	 * @return \WP_Post[]
	 */
	public static function all( int $limit = -1 ): array {
		return get_posts(
			array(
				'post_type'              => self::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => $limit,
				'orderby'                => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);
	}

	/**
	 * Normalised view of a teacher for templates and blocks.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_data( \WP_Post $post ): array {
		$meta    = static fn ( string $key ): string => (string) get_post_meta( $post->ID, $key, true );
		$sede_id = (int) get_post_meta( $post->ID, self::META_SEDE_ID, true );
		$sede    = $sede_id ? get_post( $sede_id ) : null;

		return array(
			'id'               => $post->ID,
			'slug'             => $post->post_name,
			'name'             => get_the_title( $post ),
			'url'              => (string) get_permalink( $post ),
			'bio'              => (string) $post->post_excerpt,
			'image_id'         => (int) get_post_thumbnail_id( $post ),
			'full_name'        => $meta( self::META_FULL_NAME ),
			'rank'             => $meta( self::META_RANK ),
			'city'             => $meta( self::META_CITY ),
			'country'          => $meta( self::META_COUNTRY ),
			'sede_id'          => $sede_id,
			'sede_url'         => $sede instanceof \WP_Post && 'publish' === $sede->post_status ? (string) get_permalink( $sede ) : '',
			'sede_name'        => $sede instanceof \WP_Post ? get_the_title( $sede ) : '',
			'hero_eyebrow'     => $meta( self::META_HERO_EYEBROW ),
			'subtitle'         => $meta( self::META_SUBTITLE ),
			'caption'          => $meta( self::META_CAPTION ),
			'instagram'        => $meta( self::META_INSTAGRAM ),
			'instagram_handle' => $meta( self::META_INSTAGRAM_HANDLE ),
			'facebook'         => $meta( self::META_FACEBOOK ),
			'website'          => $meta( self::META_WEBSITE ),
			'youtube'          => $meta( self::META_YOUTUBE ),
			'whatsapp'         => $meta( self::META_WHATSAPP ),
			'whatsapp_display' => $meta( self::META_WHATSAPP_DISPLAY ),
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
				$new['thumb'] = __( 'Foto', 'pura' );
			}
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['rank']  = __( 'Grado', 'pura' );
				$new['city']  = __( 'Ciudad', 'pura' );
				$new['sede']  = __( 'Sede', 'pura' );
				$new['order'] = __( 'Orden', 'pura' );
			}
		}

		return $new;
	}

	public function column_content( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'thumb':
				echo get_the_post_thumbnail( $post_id, array( 60, 75 ) );
				break;
			case 'rank':
				echo esc_html( (string) get_post_meta( $post_id, self::META_RANK, true ) );
				break;
			case 'city':
				echo esc_html( (string) get_post_meta( $post_id, self::META_CITY, true ) );
				break;
			case 'sede':
				$sede_id = (int) get_post_meta( $post_id, self::META_SEDE_ID, true );
				echo $sede_id ? esc_html( get_the_title( $sede_id ) ) : '—';
				break;
			case 'order':
				echo esc_html( (string) get_post_field( 'menu_order', $post_id ) );
				break;
		}
	}
}
