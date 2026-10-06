<?php
/**
 * `pura_evento` post type: rodas, workshops and gatherings. Replaces data/eventos.json.
 *
 * Events link out (WhatsApp, external pages), so the type has no single view.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Data;

defined( 'ABSPATH' ) || exit;

final class Evento_Post_Type {

	public const POST_TYPE = 'pura_evento';

	public const META_DATE     = 'pura_date';
	public const META_TIME     = 'pura_time';
	public const META_LOCATION = 'pura_location';
	public const META_VENUE    = 'pura_venue';
	public const META_STATUS   = 'pura_status';
	public const META_URL      = 'pura_url';

	public function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Eventos', 'pura' ),
					'singular_name' => __( 'Evento', 'pura' ),
					'add_new'       => __( 'Añadir evento', 'pura' ),
					'add_new_item'  => __( 'Añadir evento', 'pura' ),
					'edit_item'     => __( 'Editar evento', 'pura' ),
					'new_item'      => __( 'Nuevo evento', 'pura' ),
					'all_items'     => __( 'Eventos', 'pura' ),
					'search_items'  => __( 'Buscar eventos', 'pura' ),
					'not_found'     => __( 'No hay eventos.', 'pura' ),
				),
				'description'         => __( 'Rodas, talleres y encuentros. El extracto es la descripción corta.', 'pura' ),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'pura',
				'show_in_rest'        => true,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'menu_icon'           => 'dashicons-calendar-alt',
				'supports'            => array( 'title', 'excerpt', 'custom-fields' ),
				'capability_type'     => 'post',
			)
		);

		( new Meta_Fields(
			self::POST_TYPE,
			'pura_evento_details',
			__( 'Datos del evento', 'pura' ),
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
			self::META_DATE     => array(
				'type'        => 'text',
				'label'       => __( 'Fecha (AAAA-MM-DD)', 'pura' ),
				'description' => __( 'Por ejemplo 2026-07-01. Ordena la lista de eventos.', 'pura' ),
			),
			self::META_TIME     => array(
				'type'  => 'text',
				'label' => __( 'Hora', 'pura' ),
			),
			self::META_LOCATION => array(
				'type'  => 'text',
				'label' => __( 'Ciudad / sede', 'pura' ),
			),
			self::META_VENUE    => array(
				'type'  => 'text',
				'label' => __( 'Lugar', 'pura' ),
			),
			self::META_STATUS   => array(
				'type'        => 'text',
				'label'       => __( 'Estado', 'pura' ),
				'description' => __( 'Por ejemplo Próximo, Confirmado, Agotado.', 'pura' ),
			),
			self::META_URL      => array(
				'type'  => 'url',
				'label' => __( 'Enlace (más información)', 'pura' ),
			),
		);
	}

	/**
	 * Published events ordered by date (soonest first).
	 *
	 * @return \WP_Post[]
	 */
	public static function all( int $limit = -1, bool $include_past = true ): array {
		$args = array(
			'post_type'              => self::POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => $limit,
			'meta_key'               => self::META_DATE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'orderby'                => 'meta_value',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		);

		if ( ! $include_past ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => self::META_DATE,
					'value'   => wp_date( 'Y-m-d' ),
					'compare' => '>=',
					'type'    => 'DATE',
				),
			);
		}

		return get_posts( $args );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_data( \WP_Post $post ): array {
		$meta = static fn ( string $key ): string => (string) get_post_meta( $post->ID, $key, true );

		return array(
			'id'          => $post->ID,
			'title'       => get_the_title( $post ),
			'description' => (string) $post->post_excerpt,
			'date'        => $meta( self::META_DATE ),
			'time'        => $meta( self::META_TIME ),
			'location'    => $meta( self::META_LOCATION ),
			'venue'       => $meta( self::META_VENUE ),
			'status'      => $meta( self::META_STATUS ),
			'url'         => $meta( self::META_URL ),
		);
	}

	/**
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function columns( array $columns ): array {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['event_date'] = __( 'Fecha', 'pura' );
				$new['location']   = __( 'Ciudad / sede', 'pura' );
				$new['status']     = __( 'Estado', 'pura' );
			}
		}
		unset( $new['date'] );

		return $new;
	}

	public function column_content( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'event_date':
				echo esc_html( trim( (string) get_post_meta( $post_id, self::META_DATE, true ) . ' ' . (string) get_post_meta( $post_id, self::META_TIME, true ) ) );
				break;
			case 'location':
				echo esc_html( (string) get_post_meta( $post_id, self::META_LOCATION, true ) );
				break;
			case 'status':
				echo esc_html( (string) get_post_meta( $post_id, self::META_STATUS, true ) );
				break;
		}
	}
}
