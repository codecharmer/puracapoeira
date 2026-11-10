<?php
/**
 * Who may edit which sede and which profesor profile on the editor page.
 *
 * The administrator writes a professor's email on a profesor and on each sede they manage.
 * A signed-in member may edit a post when the email their provider verified (Google or Apple)
 * is one of those. Nothing else grants access: professors keep the plain member role, so they
 * never get the admin or WordPress's own editing rights, and a link made by email works
 * before the professor has ever signed in.
 *
 * The meta key starts with an underscore: WordPress treats it as protected, so it is not
 * exposed or writable through the REST API or the custom-fields panel.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Auth;

use Pura\Core\Data\Profesor_Post_Type;
use Pura\Core\Data\Sede_Post_Type;
use WP_Post;
use WP_User;

defined( 'ABSPATH' ) || exit;

final class Editor_Access {

	public const META_EMAIL = '_pura_editor_email';

	/** @var string[] Post types that can be linked to a professor. */
	public const POST_TYPES = array( Sede_Post_Type::POST_TYPE, Profesor_Post_Type::POST_TYPE );

	/** Lower-cased, trimmed address, or '' when it is not an email address. Pure. */
	public static function normalize_email( string $email ): string {
		$email = strtolower( trim( $email ) );

		return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : '';
	}

	/**
	 * Addresses typed one per line (or separated by commas, semicolons or spaces). Invalid
	 * ones are dropped, duplicates removed. Pure.
	 *
	 * @return string[]
	 */
	public static function parse_emails( string $text ): array {
		$emails = array();
		foreach ( preg_split( '/[\s,;]+/', $text ) ?: array() as $part ) {
			$email = self::normalize_email( (string) $part );
			if ( '' !== $email ) {
				$emails[ $email ] = true;
			}
		}

		return array_keys( $emails );
	}

	/**
	 * @return string[]
	 */
	public static function emails_for_post( int $post_id ): array {
		$emails = array();
		foreach ( (array) get_post_meta( $post_id, self::META_EMAIL, false ) as $value ) {
			$email = self::normalize_email( (string) $value );
			if ( '' !== $email ) {
				$emails[ $email ] = true;
			}
		}

		return array_keys( $emails );
	}

	/**
	 * @param string[] $emails Addresses that may edit the post.
	 */
	public static function set_emails( int $post_id, array $emails ): void {
		delete_post_meta( $post_id, self::META_EMAIL );
		foreach ( $emails as $email ) {
			$email = self::normalize_email( (string) $email );
			if ( '' !== $email ) {
				add_post_meta( $post_id, self::META_EMAIL, $email );
			}
		}
	}

	/** True when the post is a published sede or profesor linked to the user's verified email. */
	public static function can_edit( WP_User $user, WP_Post $post ): bool {
		if ( ! in_array( $post->post_type, self::POST_TYPES, true ) || 'publish' !== $post->post_status ) {
			return false;
		}
		$email = Member_Accounts::verified_email_for_user( $user );

		return '' !== $email && in_array( $email, self::emails_for_post( $post->ID ), true );
	}

	/**
	 * The posts a user may edit: their profesor profile (the first one linked) and their sedes.
	 *
	 * @return array{profesor: WP_Post|null, sedes: WP_Post[]}
	 */
	public static function posts_for_user( WP_User $user ): array {
		$out   = array(
			'profesor' => null,
			'sedes'    => array(),
		);
		$email = Member_Accounts::verified_email_for_user( $user );
		if ( '' === $email ) {
			return $out;
		}

		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPES,
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- a handful of posts.
					array(
						'key'   => self::META_EMAIL,
						'value' => $email,
					),
				),
			)
		);

		foreach ( $posts as $post ) {
			if ( Profesor_Post_Type::POST_TYPE === $post->post_type ) {
				$out['profesor'] = $out['profesor'] ?? $post;
			} else {
				$out['sedes'][] = $post;
			}
		}

		return $out;
	}

	public static function has_access( WP_User $user ): bool {
		$posts = self::posts_for_user( $user );

		return null !== $posts['profesor'] || array() !== $posts['sedes'];
	}

	/** How a person is named in the change record: their verified email, or their account email. */
	public static function actor_label( WP_User $user ): string {
		$email = Member_Accounts::verified_email_for_user( $user );

		return '' !== $email ? $email : strtolower( (string) $user->user_email );
	}

	/** The member who signed in with this verified email, if any. */
	public static function user_for_email( string $email ): ?WP_User {
		$email = self::normalize_email( $email );
		if ( '' === $email ) {
			return null;
		}
		$users = get_users(
			array(
				'meta_key'   => Member_Accounts::META_VERIFIED_EMAIL, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $email, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'number'     => 1,
			)
		);

		return $users && $users[0] instanceof WP_User ? $users[0] : null;
	}
}
