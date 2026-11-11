<?php
/**
 * The record of every change made on the editor page: who changed what, with the values
 * before and after. Only administrators see it; nothing here is public.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Data;

use Pura\Core\Admin\Change_Actions;
use Pura\Core\Editor\Change_Diff;
use Pura\Core\Editor\Editor_Photos;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Change_Post_Type {

	public const POST_TYPE = 'pura_change';

	public const META_ACTOR_ID    = '_pura_actor_id';
	public const META_ACTOR_EMAIL = '_pura_actor_email';
	public const META_TARGET_ID   = '_pura_target_id';
	public const META_BEFORE      = '_pura_before';
	public const META_AFTER       = '_pura_after';
	public const META_SOURCE      = '_pura_source';
	public const META_RESTORES    = '_pura_restores';

	public function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Cambios de profesores', 'pura' ),
					'singular_name' => __( 'Cambio', 'pura' ),
					'all_items'     => __( 'Cambios de profesores', 'pura' ),
					'edit_item'     => __( 'Cambio', 'pura' ),
					'search_items'  => __( 'Buscar cambios', 'pura' ),
					'not_found'     => __( 'Todavía nadie ha editado su sede o su perfil.', 'pura' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'pura',
				'show_in_rest'        => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'supports'            => array( 'title' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
			)
		);

		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'add_meta_boxes' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
	}

	/**
	 * @param array<string, mixed> $before Values before (changed fields only).
	 * @param array<string, mixed> $after  Values after.
	 * @return int Change id, 0 on failure.
	 */
	public static function record( WP_Post $target, array $before, array $after, int $actor_id, string $actor_email, string $source, int $restores = 0 ): int {
		$labels = array_map( array( Change_Diff::class, 'label' ), array_keys( $after ) );
		$id     = wp_insert_post(
			wp_slash(
				array(
					'post_type'   => self::POST_TYPE,
					'post_status' => 'private',
					'post_title'  => sprintf( '%s — %s', get_the_title( $target ), implode( ', ', $labels ) ),
				)
			)
		);
		if ( ! is_int( $id ) || $id <= 0 ) {
			return 0;
		}

		update_post_meta( $id, self::META_ACTOR_ID, $actor_id );
		update_post_meta( $id, self::META_ACTOR_EMAIL, wp_slash( $actor_email ) );
		update_post_meta( $id, self::META_TARGET_ID, $target->ID );
		update_post_meta( $id, self::META_BEFORE, wp_slash( $before ) );
		update_post_meta( $id, self::META_AFTER, wp_slash( $after ) );
		update_post_meta( $id, self::META_SOURCE, $source );
		if ( $restores > 0 ) {
			update_post_meta( $id, self::META_RESTORES, $restores );
		}

		return $id;
	}

	/**
	 * @return array{id:int, target_id:int, target_title:string, target_url:string, actor_email:string, source:string, restores:int, before:array<string,mixed>, after:array<string,mixed>, when:string}|null
	 */
	public static function to_array( int $change_id ): ?array {
		$post = get_post( $change_id );
		if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}
		$target_id = (int) get_post_meta( $change_id, self::META_TARGET_ID, true );
		$before    = get_post_meta( $change_id, self::META_BEFORE, true );
		$after     = get_post_meta( $change_id, self::META_AFTER, true );

		return array(
			'id'           => $change_id,
			'target_id'    => $target_id,
			'target_title' => $target_id ? (string) get_the_title( $target_id ) : '',
			'target_url'   => $target_id ? (string) get_permalink( $target_id ) : '',
			'actor_email'  => (string) get_post_meta( $change_id, self::META_ACTOR_EMAIL, true ),
			'source'       => (string) get_post_meta( $change_id, self::META_SOURCE, true ),
			'restores'     => (int) get_post_meta( $change_id, self::META_RESTORES, true ),
			'before'       => is_array( $before ) ? $before : array(),
			'after'        => is_array( $after ) ? $after : array(),
			'when'         => (string) get_post_time( 'Y-m-d H:i:s', false, $post ),
		);
	}

	public function add_meta_boxes(): void {
		add_meta_box( 'pura_change_details', __( 'Qué cambió', 'pura' ), array( $this, 'render_details' ), self::POST_TYPE, 'normal', 'high' );
	}

	public function render_details( WP_Post $post ): void {
		$change = self::to_array( $post->ID );
		if ( ! $change ) {
			return;
		}

		echo '<p><strong>' . esc_html( $change['target_title'] ) . '</strong> — ' . esc_html( $change['actor_email'] ) . ' — ' . esc_html( $change['when'] );
		if ( 'restore' === $change['source'] ) {
			echo ' — ' . esc_html__( 'restauración', 'pura' );
		}
		echo '</p>';

		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Dato', 'pura' ) . '</th><th>' . esc_html__( 'Antes', 'pura' ) . '</th><th>' . esc_html__( 'Ahora', 'pura' ) . '</th></tr></thead><tbody>';
		foreach ( $change['after'] as $field => $value ) {
			echo '<tr><td>' . esc_html( Change_Diff::label( (string) $field ) ) . '</td>';
			if ( in_array( $field, array( 'image', 'gallery' ), true ) ) {
				echo '<td>' . self::thumbnails( $change['before'][ $field ] ?? array() ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from wp_get_attachment_image().
				echo '<td>' . self::thumbnails( $value ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				continue;
			}
			echo '<td style="white-space:pre-wrap">' . esc_html( Change_Diff::show( $change['before'][ $field ] ?? '' ) ) . '</td>';
			echo '<td style="white-space:pre-wrap">' . esc_html( Change_Diff::show( $value ) ) . '</td></tr>';
		}
		echo '</tbody></table>';

		if ( $change['target_id'] && current_user_can( 'manage_options' ) ) {
			echo '<p><a class="button" href="' . esc_url( Change_Actions::restore_url( $post->ID ) ) . '">' . esc_html__( 'Restaurar los valores de «Antes»', 'pura' ) . '</a> ';
			echo '<a class="button-link" href="' . esc_url( $change['target_url'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Ver la página', 'pura' ) . '</a></p>';
		}
	}

	/**
	 * Small pictures for a photo value of a change (one id or a list of ids).
	 *
	 * @param mixed $value Recorded value.
	 */
	private static function thumbnails( $value ): string {
		$html = '';
		foreach ( Editor_Photos::clean_ids( (array) $value ) as $id ) {
			$image = wp_get_attachment_image( $id, array( 80, 80 ), false, array( 'style' => 'margin:0 4px 4px 0;object-fit:cover;width:80px;height:80px' ) );
			$html .= '' !== $image ? $image : '<em>' . esc_html__( '(foto borrada)', 'pura' ) . '</em> ';
		}

		return '' !== $html ? $html : esc_html__( '(sin foto)', 'pura' );
	}

	/**
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function columns( array $columns ): array {
		return array(
			'cb'          => $columns['cb'] ?? '',
			'title'       => __( 'Cambio', 'pura' ),
			'pura_actor'  => __( 'Quién', 'pura' ),
			'pura_source' => __( 'Origen', 'pura' ),
			'date'        => __( 'Fecha', 'pura' ),
		);
	}

	public function column_content( string $column, int $post_id ): void {
		if ( 'pura_actor' === $column ) {
			echo esc_html( (string) get_post_meta( $post_id, self::META_ACTOR_EMAIL, true ) );
		} elseif ( 'pura_source' === $column ) {
			echo esc_html( 'restore' === get_post_meta( $post_id, self::META_SOURCE, true ) ? __( 'Restauración', 'pura' ) : __( 'Página de edición', 'pura' ) );
		}
	}
}
