<?php
/**
 * The only place the editor page changes a sede or a profesor.
 *
 * Every change goes through here so that it is always recorded (who, what, before and after)
 * and the administrator is always told. The values arrive already checked by Editor_Fields;
 * meta is still written through update_post_meta(), which runs the sanitizer registered for
 * each key.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Editor;

use Pura\Core\Data\Change_Post_Type;
use Pura\Core\Data\I18n_Meta;
use Pura\Core\Mailer;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Editor_Writer {

	/** Profesor fields whose translation is stored under a key of the post's own translations. */
	private const TRANSLATED = array(
		'subtitle' => 'hero-intro',
		'caption'  => 'caption',
	);

	/**
	 * Applies checked values to a post. Fields equal to the current value are ignored; when
	 * nothing differs nothing is written, recorded or mailed.
	 *
	 * @param array<string, mixed> $values Checked values (Editor_Fields::validate()); photo fields
	 *                                     (`image`, `gallery`) come from the photo routes or a restore.
	 * @param string               $source 'editor' or 'restore'.
	 * @return array{changed: string[], change_id: int}
	 */
	public static function apply( WP_Post $post, array $values, int $actor_id, string $actor_email, string $source = 'editor', int $restores = 0 ): array {
		$type        = Editor_Document::type_of( $post );
		$photos      = Editor_Fields::photo_definitions( $type );
		$definitions = Editor_Fields::definitions( $type ) + $photos + Editor_Fields::content_definitions( $type );
		$current     = Editor_Document::values( $post ) + Editor_Photos::values( $post ) + array( 'bio' => (string) $post->post_content );
		$changed     = array_values( array_intersect( Change_Diff::changed( $current, $values ), array_keys( $definitions ) ) );

		if ( ! $changed ) {
			return array(
				'changed'   => array(),
				'change_id' => 0,
			);
		}

		$before = array();
		$after  = array();
		$update = array( 'ID' => $post->ID );

		foreach ( $changed as $name ) {
			$definition      = $definitions[ $name ];
			$before[ $name ] = $current[ $name ];
			$after[ $name ]  = $values[ $name ];

			if ( Editor_Fields::STORE_EXCERPT === $definition['store'] ) {
				$update['post_excerpt'] = (string) $values[ $name ];
			} elseif ( Editor_Fields::STORE_CONTENT === $definition['store'] ) {
				$update['post_content'] = (string) $values[ $name ];
			} elseif ( Editor_Fields::STORE_THUMBNAIL === $definition['store'] ) {
				if ( (int) $values[ $name ] > 0 ) {
					set_post_thumbnail( $post->ID, (int) $values[ $name ] );
				} else {
					delete_post_thumbnail( $post->ID );
				}
			} elseif ( '' === $values[ $name ] || array() === $values[ $name ] ) {
				delete_post_meta( $post->ID, (string) $definition['store'] );
			} else {
				update_post_meta( $post->ID, (string) $definition['store'], wp_slash( $values[ $name ] ) );
			}
		}

		self::forget_translations( $post, $changed, isset( $after['bio'] ) ? array_diff( Bio_Document::translation_keys( (string) $before['bio'] ), Bio_Document::translation_keys( (string) $after['bio'] ) ) : array() );
		update_post_meta( $post->ID, Editor_Document::META_REVISION, 1 + (int) get_post_meta( $post->ID, Editor_Document::META_REVISION, true ) );
		self::touch( $update );

		Editor_Photos::track( array_intersect_key( $before, $photos ), array_intersect_key( $after, $photos ) );

		$change_id = Change_Post_Type::record( $post, $before, $after, $actor_id, $actor_email, $source, $restores );
		// A professor adding ten photos is one piece of news, not ten: photo-only changes wait for a joint notice.
		if ( 'editor' === $source && ! array_diff( $changed, array_keys( $photos ) ) ) {
			Editor_Photos::queue_notice( $post->ID, $change_id );
		} else {
			Mailer::notify_change( $change_id );
		}

		return array(
			'changed'   => $changed,
			'change_id' => $change_id,
		);
	}

	/**
	 * Saves the post row, which also moves its "last modified" time: a second person saving
	 * from an older copy is then told about the conflict, and the importer sees the post as
	 * edited. The post content is not ours to filter here (it is the bio as an administrator
	 * or the bio editor wrote it), so the member's content filters are lifted for this call.
	 *
	 * @param array<string, mixed> $update wp_update_post() arguments.
	 */
	public static function touch( array $update ): void {
		kses_remove_filters();
		wp_update_post( wp_slash( $update ) );
		kses_init();
	}

	/**
	 * A text that was translated and has now changed would keep showing its old English and
	 * Portuguese versions. Dropping those translations makes the site fall back to the new
	 * Spanish text until someone translates it.
	 *
	 * @param string[] $changed Changed field names.
	 * @param string[] $gone    Translation keys of bio blocks that were changed or removed.
	 */
	private static function forget_translations( WP_Post $post, array $changed, array $gone = array() ): void {
		$keys = array_merge( array_values( array_intersect_key( self::TRANSLATED, array_flip( $changed ) ) ), array_values( $gone ) );
		if ( ! $keys ) {
			return;
		}
		$json = (string) get_post_meta( $post->ID, I18n_Meta::META_KEY, true );
		$data = I18n_Meta::decode( $json );
		$next = self::without_keys( $data, $keys );
		if ( $next === $data ) {
			return;
		}
		if ( $next ) {
			update_post_meta( $post->ID, I18n_Meta::META_KEY, wp_slash( (string) wp_json_encode( $next, JSON_UNESCAPED_UNICODE ) ) );
		} else {
			delete_post_meta( $post->ID, I18n_Meta::META_KEY );
		}
	}

	/**
	 * Removes translation keys from every language. Pure.
	 *
	 * @param array<string, mixed> $data Translations by language.
	 * @param string[]             $keys Keys to remove.
	 * @return array<string, mixed>
	 */
	public static function without_keys( array $data, array $keys ): array {
		foreach ( $data as $lang => $strings ) {
			if ( ! is_array( $strings ) ) {
				continue;
			}
			foreach ( $keys as $key ) {
				unset( $strings[ $key ] );
			}
			if ( $strings ) {
				$data[ $lang ] = $strings;
			} else {
				unset( $data[ $lang ] );
			}
		}

		return $data;
	}
}
