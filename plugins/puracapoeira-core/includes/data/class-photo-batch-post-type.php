<?php
/**
 * `pura_photo_batch`: one post per upload session from the colleagues' photo page (/fotos/).
 *
 * Entries are only created by the upload form (REST); the admin reviews them under
 * Pura Capoeira → Fotos recibidas, where the photos can be sent straight to a profesor's profile
 * or a sede with one click. The photos themselves are Media Library attachments.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Data;

defined( 'ABSPATH' ) || exit;

final class Photo_Batch_Post_Type {

	public const POST_TYPE = 'pura_photo_batch';

	public const STATUS_OPEN = 'open';
	public const STATUS_DONE = 'done';

	public const STATUSES = array(
		self::STATUS_OPEN => 'Subiendo',
		self::STATUS_DONE => 'Recibido',
	);

	/** @var array<string, string> meta key => type */
	public const META = array(
		'_pura_status'        => 'string',
		'_pura_name'          => 'string',
		'_pura_contact'       => 'string',
		'_pura_note'          => 'string',
		'_pura_sede_id'       => 'integer',
		'_pura_profesor_id'   => 'integer',
		'_pura_profile_photo' => 'integer',
		'_pura_token_hash'    => 'string',
		'_pura_expires'       => 'integer',
		'_pura_notified'      => 'integer',
	);

	public function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Fotos recibidas', 'pura' ),
					'singular_name' => __( 'Envío de fotos', 'pura' ),
					'all_items'     => __( 'Fotos recibidas', 'pura' ),
					'edit_item'     => __( 'Envío de fotos', 'pura' ),
					'search_items'  => __( 'Buscar por nombre', 'pura' ),
					'not_found'     => __( 'Nadie ha subido fotos todavía. Comparte el enlace de Ajustes → Fotos de profesores.', 'pura' ),
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

		foreach ( self::META as $key => $type ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'type'          => $type,
					'single'        => true,
					'show_in_rest'  => false,
					'auth_callback' => static fn () => current_user_can( 'manage_options' ),
				)
			);
		}

		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'add_meta_boxes' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
		add_action( 'before_delete_post', array( $this, 'detach_photos' ), 10, 2 );
	}

	/**
	 * The photos may already be in use on profiles and sedes: deleting the batch only orphans them
	 * (they stay in the Media Library).
	 */
	public function detach_photos( int $post_id, ?\WP_Post $post = null ): void {
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return;
		}
		$ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_parent'    => $post_id,
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( $ids as $attachment_id ) {
			wp_update_post(
				array(
					'ID'          => (int) $attachment_id,
					'post_parent' => 0,
				)
			);
		}
	}

	public static function status_label( string $status ): string {
		return self::STATUSES[ $status ] ?? $status;
	}

	public function add_meta_boxes(): void {
		add_meta_box( 'pura_photo_batch_details', __( 'Envío', 'pura' ), array( $this, 'render_details_box' ), self::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'pura_photo_batch_photos', __( 'Fotos', 'pura' ), array( $this, 'render_photos_box' ), self::POST_TYPE, 'normal', 'high' );
	}

	public function render_details_box( \WP_Post $post ): void {
		$data = Photo_Batch_Repository::to_array( $post->ID );
		if ( ! $data ) {
			return;
		}

		echo '<table class="widefat striped"><tbody>';
		$this->row( __( 'Nombre', 'pura' ), $data['name'] );
		if ( $data['profesor_id'] > 0 ) {
			$this->row( __( 'Perfil', 'pura' ), '<a href="' . esc_url( $data['profesor_url'] ) . '">' . esc_html( $data['profesor_name'] ) . '</a>', true );
		}
		if ( $data['sede_id'] > 0 ) {
			$this->row( __( 'Sede', 'pura' ), '<a href="' . esc_url( $data['sede_url'] ) . '">' . esc_html( $data['sede_name'] ) . '</a>', true );
		}
		$this->row( __( 'Contacto', 'pura' ), $data['contact'] );
		$this->row( __( 'Nota', 'pura' ), $data['note'] );
		$this->row( __( 'Estado', 'pura' ), '<span class="pura-status pura-status--' . esc_attr( $data['status'] ) . '">' . esc_html( self::status_label( $data['status'] ) ) . '</span>', true );
		$this->row( __( 'Recibido el', 'pura' ), (string) $data['created_at'] );
		echo '</tbody></table>';
	}

	private function row( string $label, string $value, bool $is_html = false ): void {
		if ( '' === trim( $value ) ) {
			return;
		}
		echo '<tr><th style="width:12em">' . esc_html( $label ) . '</th><td>' . ( $is_html ? wp_kses_post( $value ) : nl2br( esc_html( $value ) ) ) . '</td></tr>';
	}

	public function render_photos_box( \WP_Post $post ): void {
		$data = Photo_Batch_Repository::to_array( $post->ID );
		if ( ! $data ) {
			return;
		}

		if ( 0 === $data['file_count'] ) {
			echo '<p>' . esc_html__( 'Todavía no llega ninguna foto de este envío.', 'pura' ) . '</p>';
			return;
		}

		if ( $data['profile_photo_id'] > 0 ) {
			echo '<h4 style="margin:0 0 8px">' . esc_html__( 'Foto de perfil', 'pura' ) . '</h4>';
			echo '<div class="pura-photo-grid">';
			$this->photo_card( (int) $data['profile_photo_id'], $data, true );
			echo '</div>';
		}

		if ( $data['photo_count'] > 0 ) {
			/* translators: %d: number of photos. */
			echo '<h4 style="margin:16px 0 8px">' . esc_html( sprintf( _n( '%d foto', '%d fotos', $data['photo_count'], 'pura' ), $data['photo_count'] ) ) . '</h4>';
			echo '<div class="pura-photo-grid">';
			foreach ( $data['photo_ids'] as $attachment_id ) {
				$this->photo_card( (int) $attachment_id, $data, false );
			}
			echo '</div>';
		}

		echo '<p class="description" style="margin-top:12px">' . esc_html__( 'Las fotos están en la Biblioteca de medios: puedes usarlas en cualquier perfil, sede o galería con el selector de imágenes. Borrar este envío no borra las fotos.', 'pura' ) . '</p>';
	}

	/**
	 * @param array<string, mixed> $batch Batch data (see Photo_Batch_Repository::to_array()).
	 */
	private function photo_card( int $attachment_id, array $batch, bool $is_profile ): void {
		$edit = (string) get_edit_post_link( $attachment_id, 'raw' );
		$full = (string) wp_get_attachment_url( $attachment_id );
		$meta = wp_get_attachment_metadata( $attachment_id );
		$dims = is_array( $meta ) && ! empty( $meta['width'] ) ? $meta['width'] . ' × ' . $meta['height'] : '';

		echo '<figure class="pura-photo-card">';
		echo '<a href="' . esc_url( $full ) . '" target="_blank" rel="noopener">' . wp_get_attachment_image( $attachment_id, 'medium' ) . '</a>';
		echo '<figcaption>';
		echo '<span class="pura-photo-card__meta">' . esc_html( $dims ) . '</span>';
		echo '<a href="' . esc_url( $edit ) . '">' . esc_html__( 'Editar', 'pura' ) . '</a>';
		if ( $batch['profesor_id'] > 0 ) {
			$this->use_button( $attachment_id, 'profesor', (int) $batch['profesor_id'], $is_profile ? __( 'Usar como foto de perfil', 'pura' ) : __( 'Usar en el perfil', 'pura' ) );
		}
		if ( $batch['sede_id'] > 0 ) {
			$this->use_button( $attachment_id, 'sede', (int) $batch['sede_id'], __( 'Usar como foto de la sede', 'pura' ) );
		}
		echo '</figcaption></figure>';
	}

	private function use_button( int $attachment_id, string $target_type, int $target_id, string $label ): void {
		$current = (int) get_post_thumbnail_id( $target_id );
		if ( $current === $attachment_id ) {
			echo '<span class="pura-photo-card__current">' . esc_html__( 'Es la foto actual', 'pura' ) . '</span>';
			return;
		}
		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action'     => \Pura\Core\Admin\Photo_Actions::ACTION_SET_THUMBNAIL,
					'attachment' => $attachment_id,
					'target'     => $target_type,
					'target_id'  => $target_id,
				),
				admin_url( 'admin-post.php' )
			),
			\Pura\Core\Admin\Photo_Actions::NONCE
		);
		echo '<a class="button button-small" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
	}

	/**
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function columns( array $columns ): array {
		return array(
			'cb'       => $columns['cb'] ?? '',
			'title'    => __( 'Nombre', 'pura' ),
			'photos'   => __( 'Fotos', 'pura' ),
			'profesor' => __( 'Perfil', 'pura' ),
			'sede'     => __( 'Sede', 'pura' ),
			'status'   => __( 'Estado', 'pura' ),
			'date'     => __( 'Fecha', 'pura' ),
		);
	}

	public function column_content( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'photos':
				$profile = Photo_Batch_Repository::profile_photo_id( $post_id );
				$ids     = Photo_Batch_Repository::photo_ids( $post_id );
				$all     = $profile ? array_merge( array( $profile ), $ids ) : $ids;
				if ( ! $all ) {
					echo '&mdash;';
					break;
				}
				echo '<span class="pura-photo-strip">';
				foreach ( array_slice( $all, 0, 4 ) as $attachment_id ) {
					echo wp_get_attachment_image( (int) $attachment_id, array( 48, 48 ) );
				}
				echo '</span> ';
				/* translators: %d: number of photos. */
				echo esc_html( sprintf( _n( '%d foto', '%d fotos', count( $all ), 'pura' ), count( $all ) ) );
				break;
			case 'profesor':
				$id = (int) get_post_meta( $post_id, '_pura_profesor_id', true );
				echo $id > 0 ? esc_html( (string) get_the_title( $id ) ) : '&mdash;';
				break;
			case 'sede':
				$id = (int) get_post_meta( $post_id, '_pura_sede_id', true );
				echo $id > 0 ? esc_html( (string) get_the_title( $id ) ) : '&mdash;';
				break;
			case 'status':
				$status = (string) get_post_meta( $post_id, '_pura_status', true );
				echo '<span class="pura-status pura-status--' . esc_attr( $status ) . '">' . esc_html( self::status_label( $status ) ) . '</span>';
				break;
		}
	}
}
