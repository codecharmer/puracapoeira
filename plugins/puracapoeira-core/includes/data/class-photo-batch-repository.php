<?php
/**
 * Create and read `pura_photo_batch` posts (one per upload session) and their photos.
 *
 * Photos are ordinary Media Library attachments whose parent is the batch, so the admin can use
 * them anywhere (a profesor's profile, a sede, the gallery) with the normal media picker. Deleting
 * a batch detaches its photos rather than deleting them.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Data;

defined( 'ABSPATH' ) || exit;

final class Photo_Batch_Repository {

	/** Accepted photo types (extension regex => mime). HEIC is converted by WordPress when ImageMagick allows. */
	public const MIMES = array(
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png'          => 'image/png',
		'webp'         => 'image/webp',
		'gif'          => 'image/gif',
		'heic'         => 'image/heic',
		'heif'         => 'image/heif',
	);

	public const MAX_BYTES = 15 * MB_IN_BYTES;
	public const MAX_FILES = 40;

	/** Seconds an upload session stays open after it is created. */
	public const TOKEN_TTL = DAY_IN_SECONDS;

	public const KIND_PHOTO   = 'photo';
	public const KIND_PROFILE = 'profile';

	public const META_KIND = '_pura_photo_kind';

	/** @var string[] Fields copied verbatim into `_pura_*` meta. */
	private const TEXT_FIELDS = array( 'name', 'contact', 'note' );

	/**
	 * @param array<string, mixed> $data Keys: name, contact, note, sede_id, profesor_id.
	 * @return int Post ID, 0 on failure.
	 */
	public static function create( array $data ): int {
		$name    = trim( (string) ( $data['name'] ?? '' ) );
		$created = current_time( 'mysql' );

		$post_id = wp_insert_post(
			array(
				'post_type'     => Photo_Batch_Post_Type::POST_TYPE,
				'post_status'   => 'private',
				'post_title'    => trim( $name . ' — ' . substr( $created, 0, 10 ) ),
				'post_date'     => $created,
				'post_date_gmt' => get_gmt_from_date( $created ),
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return 0;
		}

		update_post_meta( $post_id, '_pura_status', Photo_Batch_Post_Type::STATUS_OPEN );
		foreach ( self::TEXT_FIELDS as $field ) {
			$value = trim( (string) ( $data[ $field ] ?? '' ) );
			if ( '' !== $value ) {
				update_post_meta( $post_id, '_pura_' . $field, $value );
			}
		}
		foreach ( array( 'sede_id', 'profesor_id' ) as $field ) {
			if ( (int) ( $data[ $field ] ?? 0 ) > 0 ) {
				update_post_meta( $post_id, '_pura_' . $field, (int) $data[ $field ] );
			}
		}

		return $post_id;
	}

	/**
	 * Issue the per-session token the browser sends with every file. Only its hash is stored.
	 */
	public static function issue_token( int $post_id ): string {
		$token = bin2hex( random_bytes( 20 ) );
		update_post_meta( $post_id, '_pura_token_hash', self::hash_token( $token ) );
		update_post_meta( $post_id, '_pura_expires', time() + self::TOKEN_TTL );

		return $token;
	}

	/**
	 * Whether $token opens this batch: right hash, not expired, batch exists.
	 */
	public static function verify_token( int $post_id, string $token ): bool {
		$post = get_post( $post_id );
		if ( ! $post || Photo_Batch_Post_Type::POST_TYPE !== $post->post_type || '' === trim( $token ) ) {
			return false;
		}
		$hash    = (string) get_post_meta( $post_id, '_pura_token_hash', true );
		$expires = (int) get_post_meta( $post_id, '_pura_expires', true );

		return '' !== $hash && $expires > time() && hash_equals( $hash, self::hash_token( $token ) );
	}

	private static function hash_token( string $token ): string {
		return hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
	}

	/**
	 * Largest photo the server accepts (our cap or PHP's upload limit, whichever is lower).
	 */
	public static function max_bytes(): int {
		$php_limit = (int) wp_max_upload_size();

		return $php_limit > 0 ? min( self::MAX_BYTES, $php_limit ) : self::MAX_BYTES;
	}

	/**
	 * 'profile' or 'photo'; anything else is a photo.
	 */
	public static function normalize_kind( string $kind ): string {
		return self::KIND_PROFILE === strtolower( trim( $kind ) ) ? self::KIND_PROFILE : self::KIND_PHOTO;
	}

	/**
	 * Media Library title for a photo: "Apelido — Sede — Foto 3" / "Apelido — Foto de perfil".
	 * Pure: no WordPress calls.
	 */
	public static function attachment_title( string $name, string $place, string $kind, int $index ): string {
		$parts = array_values( array_filter( array( trim( $name ), trim( $place ) ), static fn ( string $s ): bool => '' !== $s ) );
		if ( self::KIND_PROFILE === $kind ) {
			$parts[] = 'Foto de perfil';
		} else {
			$parts[] = 'Foto ' . max( 1, $index );
		}

		return implode( ' — ', $parts );
	}

	/**
	 * Store `$_FILES[ $file_id ]` as an attachment of the batch.
	 *
	 * @return int|\WP_Error Attachment ID.
	 */
	public static function attach_file( int $post_id, string $file_id, string $kind ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$kind  = self::normalize_kind( $kind );
		$name  = (string) get_post_meta( $post_id, '_pura_name', true );
		$place = self::place_label( $post_id );
		$index = self::KIND_PROFILE === $kind ? 0 : count( self::photo_ids( $post_id ) ) + 1;
		$title = self::attachment_title( $name, $place, $kind, $index );

		$attachment_id = media_handle_upload(
			$file_id,
			$post_id,
			array(
				'post_title'   => $title,
				'post_excerpt' => '',
			),
			array(
				'test_form' => false,
				'mimes'     => self::MIMES,
			)
		);
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		$attachment_id = (int) $attachment_id;
		update_post_meta( $attachment_id, self::META_KIND, $kind );
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', trim( $name . ( '' !== $place ? ', ' . $place : '' ) ) );

		if ( self::KIND_PROFILE === $kind ) {
			// A second profile photo in the same session replaces the first as "the" profile photo;
			// the earlier file stays in the batch as an ordinary photo.
			$previous = (int) get_post_meta( $post_id, '_pura_profile_photo', true );
			if ( $previous > 0 && $previous !== $attachment_id ) {
				update_post_meta( $previous, self::META_KIND, self::KIND_PHOTO );
			}
			update_post_meta( $post_id, '_pura_profile_photo', $attachment_id );
		}

		return $attachment_id;
	}

	/**
	 * Attachment IDs of the batch's ordinary photos (oldest first).
	 *
	 * @return int[]
	 */
	public static function photo_ids( int $post_id ): array {
		$profile = (int) get_post_meta( $post_id, '_pura_profile_photo', true );
		$ids     = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_parent'    => $post_id,
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);

		return array_values( array_filter( array_map( 'intval', $ids ), static fn ( int $id ): bool => $id !== $profile ) );
	}

	public static function profile_photo_id( int $post_id ): int {
		$id = (int) get_post_meta( $post_id, '_pura_profile_photo', true );

		return $id > 0 && 'attachment' === get_post_type( $id ) ? $id : 0;
	}

	/**
	 * Total files in the batch (profile photo included).
	 */
	public static function file_count( int $post_id ): int {
		return count( self::photo_ids( $post_id ) ) + ( self::profile_photo_id( $post_id ) > 0 ? 1 : 0 );
	}

	/**
	 * "Sede" display name for titles and mail; the sede post when linked, else the free text.
	 */
	public static function place_label( int $post_id ): string {
		$sede_id = (int) get_post_meta( $post_id, '_pura_sede_id', true );
		if ( $sede_id > 0 ) {
			$sede = get_post( $sede_id );
			if ( $sede ) {
				return (string) get_the_title( $sede );
			}
		}

		return '';
	}

	/**
	 * Flat array for mail and the admin details box.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function to_array( int $post_id ): ?array {
		$post = get_post( $post_id );
		if ( ! $post || Photo_Batch_Post_Type::POST_TYPE !== $post->post_type ) {
			return null;
		}

		$meta        = static fn ( string $key ): string => (string) get_post_meta( $post_id, '_pura_' . $key, true );
		$profesor_id = (int) get_post_meta( $post_id, '_pura_profesor_id', true );
		$profesor    = $profesor_id > 0 ? get_post( $profesor_id ) : null;
		$sede_id     = (int) get_post_meta( $post_id, '_pura_sede_id', true );
		$sede        = $sede_id > 0 ? get_post( $sede_id ) : null;
		$profile     = self::profile_photo_id( $post_id );
		$photos      = self::photo_ids( $post_id );

		return array(
			'id'                => $post_id,
			'status'            => $meta( 'status' ),
			'name'              => $meta( 'name' ),
			'contact'           => $meta( 'contact' ),
			'note'              => $meta( 'note' ),
			'profesor_id'       => $profesor ? $profesor_id : 0,
			'profesor_name'     => $profesor ? (string) get_the_title( $profesor ) : '',
			'profesor_url'      => $profesor ? (string) get_edit_post_link( $profesor_id, 'raw' ) : '',
			'sede_id'           => $sede ? $sede_id : 0,
			'sede_name'         => $sede ? (string) get_the_title( $sede ) : '',
			'sede_url'          => $sede ? (string) get_edit_post_link( $sede_id, 'raw' ) : '',
			'profile_photo_id'  => $profile,
			'profile_photo_url' => $profile ? (string) wp_get_attachment_url( $profile ) : '',
			'photo_ids'         => $photos,
			'photo_count'       => count( $photos ),
			'file_count'        => count( $photos ) + ( $profile ? 1 : 0 ),
			'created_at'        => $post->post_date,
			'admin_url'         => (string) get_edit_post_link( $post_id, 'raw' ),
		);
	}
}
