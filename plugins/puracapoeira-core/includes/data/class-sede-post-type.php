<?php
/**
 * `pura_sede` post type: a Pura Capoeira location. Replaces data/sedes.json.
 *
 * Public at /sedes/<slug>/ while the "Sedes" page lists them at /sedes/.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Data;

defined( 'ABSPATH' ) || exit;

final class Sede_Post_Type {

	public const POST_TYPE    = 'pura_sede';
	public const REWRITE_SLUG = 'sedes';

	public const META_CITY             = 'pura_city';
	public const META_REGION           = 'pura_region';
	public const META_COUNTRY          = 'pura_country';
	public const META_ADDRESS          = 'pura_address';
	public const META_RESPONSIBLE      = 'pura_responsible';
	public const META_WHATSAPP         = 'pura_whatsapp';
	public const META_WHATSAPP_DISPLAY = 'pura_whatsapp_display';
	public const META_INSTAGRAM        = 'pura_instagram';
	public const META_INSTAGRAM_HANDLE = 'pura_instagram_handle';
	public const META_FACEBOOK         = 'pura_facebook';
	public const META_SCHEDULE         = 'pura_schedule';
	public const META_PRICING          = 'pura_pricing';
	public const META_LAT              = 'pura_lat';
	public const META_LNG              = 'pura_lng';

	public const SCHEDULE_COLUMNS = array( 'group', 'days', 'time' );
	public const PRICING_COLUMNS  = array( 'label', 'value' );

	public function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'               => __( 'Sedes', 'pura' ),
					'singular_name'      => __( 'Sede', 'pura' ),
					'add_new'            => __( 'Añadir sede', 'pura' ),
					'add_new_item'       => __( 'Añadir sede', 'pura' ),
					'edit_item'          => __( 'Editar sede', 'pura' ),
					'new_item'           => __( 'Nueva sede', 'pura' ),
					'all_items'          => __( 'Sedes', 'pura' ),
					'view_item'          => __( 'Ver sede', 'pura' ),
					'search_items'       => __( 'Buscar sedes', 'pura' ),
					'not_found'          => __( 'No hay sedes.', 'pura' ),
					'featured_image'     => __( 'Foto de la sede', 'pura' ),
					'set_featured_image' => __( 'Elegir foto', 'pura' ),
				),
				'description'     => __( 'Sedes y núcleos de Pura Capoeira. El extracto es el texto corto de la tarjeta.', 'pura' ),
				'public'          => true,
				'show_ui'         => true,
				'show_in_menu'    => 'pura',
				'show_in_rest'    => true,
				'has_archive'     => false,
				'rewrite'         => array(
					'slug'       => self::REWRITE_SLUG,
					'with_front' => false,
				),
				'menu_icon'       => 'dashicons-location-alt',
				'supports'        => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes', 'custom-fields' ),
				'capability_type' => 'post',
				'template'        => array(),
			)
		);

		( new Meta_Fields(
			self::POST_TYPE,
			'pura_sede_details',
			__( 'Datos de la sede', 'pura' ),
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
			self::META_CITY             => array(
				'type'  => 'text',
				'label' => __( 'Ciudad', 'pura' ),
			),
			self::META_REGION           => array(
				'type'  => 'text',
				'label' => __( 'Estado / región', 'pura' ),
			),
			self::META_COUNTRY          => array(
				'type'  => 'text',
				'label' => __( 'País', 'pura' ),
			),
			self::META_ADDRESS          => array(
				'type'  => 'text',
				'label' => __( 'Dirección', 'pura' ),
			),
			self::META_RESPONSIBLE      => array(
				'type'  => 'text',
				'label' => __( 'Responsable', 'pura' ),
			),
			self::META_WHATSAPP         => array(
				'type'        => 'url',
				'label'       => __( 'WhatsApp (enlace)', 'pura' ),
				'description' => __( 'Por ejemplo https://wa.me/5217771234567', 'pura' ),
			),
			self::META_WHATSAPP_DISPLAY => array(
				'type'  => 'text',
				'label' => __( 'WhatsApp (como se muestra)', 'pura' ),
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
				'type'        => 'text',
				'label'       => __( 'Facebook', 'pura' ),
				'description' => __( 'URL de la página o su nombre (se enlaza a la búsqueda de Facebook).', 'pura' ),
			),
			self::META_SCHEDULE         => array(
				'type'    => 'rows',
				'label'   => __( 'Horarios', 'pura' ),
				'columns' => self::SCHEDULE_COLUMNS,
			),
			self::META_PRICING          => array(
				'type'    => 'rows',
				'label'   => __( 'Costos', 'pura' ),
				'columns' => self::PRICING_COLUMNS,
			),
			self::META_LAT              => array(
				'type'  => 'number',
				'label' => __( 'Latitud', 'pura' ),
			),
			self::META_LNG              => array(
				'type'  => 'number',
				'label' => __( 'Longitud', 'pura' ),
			),
		);
	}

	/**
	 * Published sedes in display order.
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
	 * Normalised view of a sede for templates and blocks.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_data( \WP_Post $post ): array {
		$meta = static fn ( string $key ): string => (string) get_post_meta( $post->ID, $key, true );

		$schedule = get_post_meta( $post->ID, self::META_SCHEDULE, true );
		$pricing  = get_post_meta( $post->ID, self::META_PRICING, true );
		$lat      = get_post_meta( $post->ID, self::META_LAT, true );
		$lng      = get_post_meta( $post->ID, self::META_LNG, true );
		$facebook = $meta( self::META_FACEBOOK );

		return array(
			'id'               => $post->ID,
			'slug'             => $post->post_name,
			'name'             => get_the_title( $post ),
			'url'              => (string) get_permalink( $post ),
			'blurb'            => (string) $post->post_excerpt,
			'image_id'         => (int) get_post_thumbnail_id( $post ),
			'city'             => $meta( self::META_CITY ),
			'region'           => $meta( self::META_REGION ),
			'country'          => $meta( self::META_COUNTRY ),
			'address'          => $meta( self::META_ADDRESS ),
			'responsible'      => $meta( self::META_RESPONSIBLE ),
			'whatsapp'         => $meta( self::META_WHATSAPP ),
			'whatsapp_display' => $meta( self::META_WHATSAPP_DISPLAY ),
			'instagram'        => $meta( self::META_INSTAGRAM ),
			'instagram_handle' => $meta( self::META_INSTAGRAM_HANDLE ),
			'facebook'         => $facebook,
			'facebook_url'     => self::facebook_url( $facebook ),
			'schedule'         => is_array( $schedule ) ? array_values( $schedule ) : array(),
			'pricing'          => is_array( $pricing ) ? array_values( $pricing ) : array(),
			'lat'              => is_numeric( $lat ) && 0.0 !== (float) $lat ? (float) $lat : null,
			'lng'              => is_numeric( $lng ) && 0.0 !== (float) $lng ? (float) $lng : null,
		);
	}

	/**
	 * A Facebook value may be a URL or a page name; the latter links to a Facebook search,
	 * which is what the static site did.
	 */
	public static function facebook_url( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '#^https?://#i', $value ) ) {
			return $value;
		}

		return 'https://www.facebook.com/search/top/?q=' . rawurlencode( $value );
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
				$new['city']        = __( 'Ciudad', 'pura' );
				$new['country']     = __( 'País', 'pura' );
				$new['responsible'] = __( 'Responsable', 'pura' );
				$new['order']       = __( 'Orden', 'pura' );
			}
		}

		return $new;
	}

	public function column_content( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'thumb':
				echo get_the_post_thumbnail( $post_id, array( 80, 60 ) );
				break;
			case 'city':
				echo esc_html( (string) get_post_meta( $post_id, self::META_CITY, true ) );
				break;
			case 'country':
				echo esc_html( (string) get_post_meta( $post_id, self::META_COUNTRY, true ) );
				break;
			case 'responsible':
				echo esc_html( (string) get_post_meta( $post_id, self::META_RESPONSIBLE, true ) );
				break;
			case 'order':
				echo esc_html( (string) get_post_field( 'menu_order', $post_id ) );
				break;
		}
	}
}
