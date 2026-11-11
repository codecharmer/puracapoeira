<?php
/**
 * Photos a professor manages from the editor page: the main photo of a sede, the portrait of
 * a profesor, and a sede's gallery.
 *
 * A photo that is replaced or removed is only unlinked, so a restore can put it back. Unlinked
 * photos that were uploaded through the editor are deleted after KEEP_DAYS; photos an
 * administrator put in the Media Library are never deleted from here.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Editor;

use Pura\Core\Data\Photo_Batch_Repository;
use Pura\Core\Data\Sede_Post_Type;
use Pura\Core\Mailer;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Editor_Photos {

	/** Ordered attachment ids of a sede's gallery. Protected: only the editor writes it. */
	public const META_GALLERY = '_pura_gallery';

	/** On an attachment: the sede or profesor it was uploaded for through the editor. */
	public const META_OWNER = '_pura_editor_owner';

	/** On an attachment: when it stopped being shown anywhere (Unix time). */
	public const META_UNLINKED = '_pura_editor_unlinked';

	/** On a sede or profesor: photo changes waiting for the next notice. */
	public const META_PENDING = '_pura_editor_pending_photos';

	public const MAX_GALLERY  = 12;
	public const KEEP_DAYS    = 30;
	public const DIGEST_DELAY = 600;

	public const HOOK_DIGEST = 'pura_editor_photo_digest';
	public const HOOK_PURGE  = 'pura_editor_photo_purge';

	/** The browser re-encodes every photo to JPEG; PNG and WebP are accepted for direct uploads. */
	public const MIMES = array(
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png'          => 'image/png',
		'webp'         => 'image/webp',
	);

	public static function register(): void {
		add_action( self::HOOK_DIGEST, array( self::class, 'send_digest' ) );
		add_action( self::HOOK_PURGE, array( self::class, 'purge' ) );
		add_action( 'init', array( self::class, 'schedule_purge' ) );
		add_action( 'rest_api_init', array( self::class, 'register_rest_field' ) );
	}

	public static function schedule_purge(): void {
		if ( ! wp_next_scheduled( self::HOOK_PURGE ) ) {
			wp_schedule_event( time() + 3600, 'daily', self::HOOK_PURGE );
		}
	}

	/** The gallery travels with the sede in the public REST data, read-only, so the app needs no second request. */
	public static function register_rest_field(): void {
		register_rest_field(
			Sede_Post_Type::POST_TYPE,
			'gallery',
			array(
				'get_callback' => static fn ( array $item ): array => self::gallery( (int) ( $item['id'] ?? 0 ) ),
				'schema'       => array(
					'description' => 'Fotos de la sede, en orden.',
					'type'        => 'array',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'id'     => array( 'type' => 'integer' ),
							'url'    => array( 'type' => 'string' ),
							'thumb'  => array( 'type' => 'string' ),
							'width'  => array( 'type' => 'integer' ),
							'height' => array( 'type' => 'integer' ),
							'alt'    => array( 'type' => 'string' ),
						),
					),
				),
			)
		);
	}

	// ---------- pure helpers ----------

	/**
	 * Positive, unique ids in the order given.
	 *
	 * @param mixed $raw Anything.
	 * @return int[]
	 */
	public static function clean_ids( $raw ): array {
		$ids = array();
		foreach ( is_array( $raw ) ? $raw : array() as $value ) {
			$id = is_numeric( $value ) ? (int) $value : 0;
			if ( $id > 0 && ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * A new order for a gallery may only reorder or remove what is already in it: it can never
	 * bring in a photo from elsewhere.
	 *
	 * @param int[] $current   Ids in the gallery now.
	 * @param mixed $requested What the browser sent.
	 */
	public static function is_valid_order( array $current, $requested ): bool {
		if ( ! is_array( $requested ) || array_values( $requested ) !== $requested ) {
			return false;
		}
		foreach ( $requested as $value ) {
			if ( ! is_int( $value ) || $value <= 0 ) {
				return false;
			}
		}

		return count( array_unique( $requested ) ) === count( $requested ) && ! array_diff( $requested, $current );
	}

	/** Whether an unlinked photo has waited long enough to be deleted. */
	public static function is_stale( int $unlinked_at, int $now ): bool {
		return $unlinked_at > 0 && $now - $unlinked_at >= self::KEEP_DAYS * 86400;
	}

	// ---------- reading ----------

	/**
	 * @return int[] Gallery ids of a sede whose files still exist.
	 */
	public static function gallery_ids( int $post_id ): array {
		return array_values( array_filter( self::clean_ids( get_post_meta( $post_id, self::META_GALLERY, true ) ), array( self::class, 'is_image' ) ) );
	}

	public static function is_image( int $attachment_id ): bool {
		return $attachment_id > 0 && 'attachment' === get_post_type( $attachment_id ) && wp_attachment_is_image( $attachment_id );
	}

	/**
	 * @return array<int, array{id:int, url:string, thumb:string, width:int, height:int, alt:string}>
	 */
	public static function gallery( int $post_id ): array {
		$items = array();
		foreach ( self::gallery_ids( $post_id ) as $id ) {
			$large = wp_get_attachment_image_src( $id, 'large' );
			if ( ! is_array( $large ) ) {
				continue;
			}
			$items[] = array(
				'id'     => $id,
				'url'    => (string) $large[0],
				'thumb'  => (string) wp_get_attachment_image_url( $id, 'medium' ),
				'width'  => (int) $large[1],
				'height' => (int) $large[2],
				'alt'    => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
			);
		}

		return $items;
	}

	/**
	 * Current values of the photo fields, in the shape the change log stores.
	 *
	 * @return array<string, mixed>
	 */
	public static function values( WP_Post $post ): array {
		$values = array( 'image' => (int) get_post_thumbnail_id( $post ) );
		if ( Sede_Post_Type::POST_TYPE === $post->post_type ) {
			$values['gallery'] = self::gallery_ids( $post->ID );
		}

		return $values;
	}

	/**
	 * Photo values of a recorded change that can still be put back: photos deleted since are left out.
	 *
	 * @param array<string, mixed> $recorded Values from a change's "before".
	 * @return array<string, mixed>
	 */
	public static function restorable( WP_Post $post, array $recorded ): array {
		$fields = Editor_Fields::photo_definitions( Editor_Document::type_of( $post ) );
		$values = array();
		if ( isset( $fields['image'] ) && array_key_exists( 'image', $recorded ) ) {
			$id = (int) $recorded['image'];
			if ( 0 === $id || self::is_image( $id ) ) {
				$values['image'] = $id;
			}
		}
		if ( isset( $fields['gallery'] ) && array_key_exists( 'gallery', $recorded ) ) {
			$values['gallery'] = array_slice( array_values( array_filter( self::clean_ids( $recorded['gallery'] ), array( self::class, 'is_image' ) ) ), 0, self::MAX_GALLERY );
		}

		return $values;
	}

	/**
	 * Photo ids as addresses, for a notice a person reads.
	 *
	 * @param array<string, mixed> $values Field => value of a change.
	 * @return array<string, mixed>
	 */
	public static function readable( array $values ): array {
		$url = static function ( int $id ): string {
			$address = $id > 0 ? (string) wp_get_attachment_url( $id ) : '';

			return '' !== $address ? $address : ( $id > 0 ? '(foto borrada)' : '' );
		};
		if ( array_key_exists( 'image', $values ) ) {
			$values['image'] = $url( (int) $values['image'] );
		}
		if ( array_key_exists( 'gallery', $values ) ) {
			$values['gallery'] = array_map( $url, self::clean_ids( $values['gallery'] ) );
		}

		return $values;
	}

	// ---------- uploading ----------

	/**
	 * Why an uploaded file is refused, or null when it may be stored.
	 *
	 * @param mixed $file Entry of the request's files.
	 */
	public static function upload_error( $file ): ?string {
		if ( ! is_array( $file ) || UPLOAD_ERR_NO_FILE === (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			return 'No llegó ninguna foto.';
		}
		$max   = Photo_Batch_Repository::max_bytes();
		$error = (int) $file['error'];
		if ( in_array( $error, array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true ) || (int) ( $file['size'] ?? 0 ) > $max ) {
			return sprintf( 'La foto es demasiado grande (máximo %s).', size_format( $max ) );
		}
		if ( UPLOAD_ERR_OK !== $error || empty( $file['tmp_name'] ) || ! is_uploaded_file( (string) $file['tmp_name'] ) ) {
			return 'No se pudo subir la foto. Intenta de nuevo.';
		}
		$check = wp_check_filetype_and_ext( (string) $file['tmp_name'], (string) ( $file['name'] ?? '' ), self::MIMES );
		if ( empty( $check['type'] ) ) {
			return 'Solo aceptamos fotos JPG, PNG o WebP.';
		}

		return null;
	}

	/**
	 * Stores `$_FILES[ $file_id ]` in the Media Library for a sede or profesor. The photo starts
	 * unlinked: it only counts once the writer shows it somewhere, so a failed save leaves
	 * nothing behind for good.
	 *
	 * @return int|\WP_Error Attachment id.
	 */
	public static function store( WP_Post $post, string $file_id, string $label ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$title = (string) get_the_title( $post );
		$id    = media_handle_upload(
			$file_id,
			$post->ID,
			array(
				'post_title'   => $title . ' — ' . $label,
				'post_excerpt' => '',
			),
			array(
				'test_form' => false,
				'mimes'     => self::MIMES,
			)
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		self::adopt( (int) $id, $post );

		return (int) $id;
	}

	/** Marks an attachment as uploaded through the editor for a post, and not yet shown anywhere. */
	public static function adopt( int $attachment_id, WP_Post $post ): void {
		update_post_meta( $attachment_id, self::META_OWNER, $post->ID );
		update_post_meta( $attachment_id, self::META_UNLINKED, time() );
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', wp_slash( (string) get_the_title( $post ) ) );
	}

	// ---------- unlinking and deleting ----------

	/**
	 * After a change: photos that left are stamped as unlinked, photos that are shown are not.
	 *
	 * @param array<string, mixed> $before Photo values before.
	 * @param array<string, mixed> $after  Photo values after.
	 */
	public static function track( array $before, array $after ): void {
		$ids = static fn ( array $values ): array => self::clean_ids( array_merge( array( $values['image'] ?? 0 ), (array) ( $values['gallery'] ?? array() ) ) );
		foreach ( $ids( $after ) as $id ) {
			delete_post_meta( $id, self::META_UNLINKED );
		}
		foreach ( array_diff( $ids( $before ), $ids( $after ) ) as $id ) {
			if ( ! self::in_use( (int) $id ) ) {
				update_post_meta( (int) $id, self::META_UNLINKED, time() );
			}
		}
	}

	/** Whether any post shows this photo as its main photo, or any sede has it in its gallery. */
	public static function in_use( int $attachment_id ): bool {
		$featured = get_posts(
			array(
				'post_type'   => 'any',
				'post_status' => 'any',
				'numberposts' => 1,
				'fields'      => 'ids',
				'meta_key'    => '_thumbnail_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one indexed lookup, on a rare path.
				'meta_value'  => (string) $attachment_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		if ( $featured ) {
			return true;
		}

		$sedes = get_posts(
			array(
				'post_type'   => Sede_Post_Type::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_key'    => self::META_GALLERY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			)
		);
		foreach ( $sedes as $sede_id ) {
			if ( in_array( $attachment_id, self::clean_ids( get_post_meta( (int) $sede_id, self::META_GALLERY, true ) ), true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Daily: deletes photos uploaded through the editor that have been unlinked for KEEP_DAYS.
	 *
	 * @return int Photos deleted.
	 */
	public static function purge( ?int $now = null ): int {
		$now     = $now ?? time();
		$deleted = 0;
		$ids     = get_posts(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'any',
				'numberposts' => 100,
				'fields'      => 'ids',
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- daily job.
					array(
						'key'     => self::META_UNLINKED,
						'compare' => 'EXISTS',
					),
					array(
						'key'     => self::META_OWNER,
						'compare' => 'EXISTS',
					),
				),
			)
		);
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( self::in_use( $id ) ) {
				delete_post_meta( $id, self::META_UNLINKED );
				continue;
			}
			if ( self::is_stale( (int) get_post_meta( $id, self::META_UNLINKED, true ), $now ) && wp_delete_attachment( $id, true ) ) {
				++$deleted;
			}
		}

		return $deleted;
	}

	// ---------- one notice for a batch of photo changes ----------

	/** Holds a photo change for the next notice instead of mailing each upload on its own. */
	public static function queue_notice( int $post_id, int $change_id ): void {
		add_post_meta( $post_id, self::META_PENDING, $change_id );
		if ( ! wp_next_scheduled( self::HOOK_DIGEST, array( $post_id ) ) ) {
			wp_schedule_single_event( time() + self::DIGEST_DELAY, self::HOOK_DIGEST, array( $post_id ) );
		}
	}

	/**
	 * @param int $post_id Sede or profesor whose photos changed.
	 */
	public static function send_digest( $post_id ): bool {
		$post_id = (int) $post_id;
		$changes = array_values( array_filter( array_map( 'intval', get_post_meta( $post_id, self::META_PENDING, false ) ) ) );
		delete_post_meta( $post_id, self::META_PENDING );

		return $changes ? Mailer::notify_photo_changes( $post_id, $changes ) : false;
	}
}
