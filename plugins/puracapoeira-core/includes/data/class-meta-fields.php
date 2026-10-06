<?php
/**
 * Declarative post meta: registers `register_post_meta()` entries for a post type and renders a
 * classic meta box with a nonce-protected save. Shared by the sede / profesor / evento / galería
 * post types so each one only lists its fields.
 *
 * Field definition: `key => array( 'type' => text|url|email|textarea|number|integer|post|rows,
 * 'label' => string, 'description' => string, 'post_type' => string (type post),
 * 'columns' => string[] (type rows) )`.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Data;

defined( 'ABSPATH' ) || exit;

final class Meta_Fields {

	/** @var array<string, array<string, mixed>> */
	private array $fields;

	private string $nonce;

	/**
	 * @param array<string, array<string, mixed>> $fields Field definitions keyed by meta key.
	 */
	public function __construct( private string $post_type, private string $box_id, private string $box_title, array $fields ) {
		$this->fields = $fields;
		$this->nonce  = $box_id . '_nonce';
	}

	public function register(): void {
		foreach ( $this->fields as $key => $def ) {
			register_post_meta( $this->post_type, $key, $this->meta_args( $def ) );
		}

		add_action( 'add_meta_boxes_' . $this->post_type, array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . $this->post_type, array( $this, 'save' ), 10, 2 );
	}

	/**
	 * @param array<string, mixed> $def Field definition.
	 * @return array<string, mixed>
	 */
	private function meta_args( array $def ): array {
		$type = (string) ( $def['type'] ?? 'text' );
		$args = array(
			'single'        => true,
			'show_in_rest'  => true,
			'auth_callback' => static fn () => current_user_can( 'edit_posts' ),
		);

		switch ( $type ) {
			case 'rows':
				$columns                   = (array) ( $def['columns'] ?? array() );
				$args['type']              = 'array';
				$args['default']           = array();
				$args['sanitize_callback'] = static fn ( $value ) => self::sanitize_rows( $value, $columns );
				$args['show_in_rest']      = array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type'                 => 'object',
							'properties'           => array_fill_keys( $columns, array( 'type' => 'string' ) ),
							'additionalProperties' => false,
						),
					),
				);
				break;
			case 'number':
				$args['type']              = 'number';
				$args['default']           = 0;
				$args['sanitize_callback'] = static fn ( $value ) => is_numeric( $value ) ? (float) $value : 0.0;
				break;
			case 'integer':
			case 'post':
				$args['type']              = 'integer';
				$args['default']           = 0;
				$args['sanitize_callback'] = 'absint';
				break;
			case 'url':
				$args['type']              = 'string';
				$args['default']           = '';
				$args['sanitize_callback'] = 'esc_url_raw';
				break;
			case 'email':
				$args['type']              = 'string';
				$args['default']           = '';
				$args['sanitize_callback'] = 'sanitize_email';
				break;
			case 'textarea':
				$args['type']              = 'string';
				$args['default']           = '';
				$args['sanitize_callback'] = 'sanitize_textarea_field';
				break;
			default:
				$args['type']              = 'string';
				$args['default']           = '';
				$args['sanitize_callback'] = 'sanitize_text_field';
		}

		return $args;
	}

	public function add_meta_box(): void {
		add_meta_box( $this->box_id, $this->box_title, array( $this, 'render' ), $this->post_type, 'normal', 'high' );
	}

	public function render( \WP_Post $post ): void {
		wp_nonce_field( $this->nonce, $this->nonce );
		echo '<table class="pura-meta-table">';
		foreach ( $this->fields as $key => $def ) {
			$type  = (string) ( $def['type'] ?? 'text' );
			$id    = 'pura_meta_' . $key;
			$value = get_post_meta( $post->ID, $key, true );
			echo '<tr><th><label for="' . esc_attr( $id ) . '">' . esc_html( (string) ( $def['label'] ?? $key ) ) . '</label></th><td>';

			switch ( $type ) {
				case 'rows':
					$columns = (array) ( $def['columns'] ?? array() );
					echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $key ) . '" rows="4" class="code">' . esc_textarea( self::rows_to_text( is_array( $value ) ? $value : array(), $columns ) ) . '</textarea>';
					echo '<p class="description">' . esc_html( sprintf( /* translators: %s: column names */ __( 'Una fila por línea: %s', 'pura' ), implode( ' | ', $columns ) ) ) . '</p>';
					break;
				case 'textarea':
					echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $key ) . '" rows="3">' . esc_textarea( (string) $value ) . '</textarea>';
					break;
				case 'post':
					$this->render_post_select( $id, $key, (int) $value, (string) ( $def['post_type'] ?? 'post' ) );
					break;
				case 'number':
					echo '<input type="number" step="any" id="' . esc_attr( $id ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( '' === $value || null === $value ? '' : (string) $value ) . '" />';
					break;
				case 'integer':
					echo '<input type="number" step="1" id="' . esc_attr( $id ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) (int) $value ) . '" />';
					break;
				default:
					echo '<input type="' . esc_attr( in_array( $type, array( 'url', 'email' ), true ) ? $type : 'text' ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) $value ) . '" />';
			}

			if ( ! empty( $def['description'] ) && 'rows' !== $type ) {
				echo '<p class="description">' . esc_html( (string) $def['description'] ) . '</p>';
			}
			echo '</td></tr>';
		}
		echo '</table>';
	}

	private function render_post_select( string $id, string $key, int $selected, string $post_type ): void {
		$posts = get_posts(
			array(
				'post_type'              => $post_type,
				'post_status'            => 'publish',
				'posts_per_page'         => 100,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $key ) . '">';
		echo '<option value="0">' . esc_html__( '— Ninguna —', 'pura' ) . '</option>';
		foreach ( $posts as $post ) {
			echo '<option value="' . esc_attr( (string) $post->ID ) . '"' . selected( $selected, $post->ID, false ) . '>' . esc_html( get_the_title( $post ) ) . '</option>';
		}
		echo '</select>';
	}

	public function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ $this->nonce ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $this->nonce ] ) ), $this->nonce ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		foreach ( $this->fields as $key => $def ) {
			if ( ! array_key_exists( $key, $_POST ) ) {
				continue;
			}
			$type = (string) ( $def['type'] ?? 'text' );
			$raw  = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per type below.

			if ( 'rows' === $type ) {
				$value = self::sanitize_rows( $raw, (array) ( $def['columns'] ?? array() ) );
				if ( array() === $value ) {
					delete_post_meta( $post_id, $key );
				} else {
					update_post_meta( $post_id, $key, $value );
				}
				continue;
			}

			$raw = is_scalar( $raw ) ? (string) $raw : '';
			if ( '' === trim( $raw ) ) {
				delete_post_meta( $post_id, $key );
				continue;
			}

			$args  = $this->meta_args( $def );
			$value = call_user_func( $args['sanitize_callback'], $raw );
			update_post_meta( $post_id, $key, $value );
		}
	}

	/**
	 * Accepts an array of rows (REST) or `|`-separated lines (textarea).
	 *
	 * @param mixed    $value   Submitted value.
	 * @param string[] $columns Column keys in order.
	 * @return array<int, array<string, string>>
	 */
	public static function sanitize_rows( $value, array $columns ): array {
		$rows = array();

		if ( is_string( $value ) ) {
			foreach ( preg_split( '/\r\n|\r|\n/', $value ) ?: array() as $line ) {
				if ( '' === trim( $line ) ) {
					continue;
				}
				$parts = array_map( 'trim', explode( '|', $line ) );
				$row   = array();
				foreach ( $columns as $i => $column ) {
					$row[ $column ] = $parts[ $i ] ?? '';
				}
				$rows[] = $row;
			}
		} elseif ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$row = array();
				foreach ( $columns as $column ) {
					$row[ $column ] = isset( $item[ $column ] ) && is_scalar( $item[ $column ] ) ? (string) $item[ $column ] : '';
				}
				$rows[] = $row;
			}
		}

		$clean = array();
		foreach ( $rows as $row ) {
			$row = array_map( static fn ( $cell ) => sanitize_text_field( (string) $cell ), $row );
			if ( '' === implode( '', $row ) ) {
				continue;
			}
			$clean[] = $row;
		}

		return $clean;
	}

	/**
	 * @param array<int, array<string, string>> $rows    Rows.
	 * @param string[]                          $columns Column keys in order.
	 */
	public static function rows_to_text( array $rows, array $columns ): string {
		$lines = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$cells = array();
			foreach ( $columns as $column ) {
				$cells[] = (string) ( $row[ $column ] ?? '' );
			}
			$lines[] = implode( ' | ', $cells );
		}

		return implode( "\n", $lines );
	}
}
