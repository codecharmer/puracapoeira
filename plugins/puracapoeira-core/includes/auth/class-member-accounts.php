<?php
/**
 * Capoeirista accounts: WordPress users with the `pura_capoeirista` role, linked to one or more
 * provider identities (Google / Apple) and to their event registrations.
 *
 * User meta:         _pura_identity_{provider} (= sub), _pura_verified_email, _pura_apple_private_email.
 * Registration meta: _pura_user_id.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Auth;

use Pura\Core\Data\Event_Registration_Post_Type;
use Pura\Core\Settings;
use WP_Error;
use WP_User;

defined( 'ABSPATH' ) || exit;

final class Member_Accounts {

	public const ROLE = 'pura_capoeirista';

	/** Post meta on `pura_event_reg`: the account that owns the registration. */
	public const META_USER = '_pura_user_id';

	/** User meta: the provider-verified email (never an Apple relay address). */
	public const META_VERIFIED_EMAIL = '_pura_verified_email';

	/** User meta: the account signed in through Apple's "Hide my email" relay. */
	public const META_PRIVATE_EMAIL = '_pura_apple_private_email';

	/**
	 * Role with the bare `read` capability. Idempotent; called on activation and on init.
	 */
	public static function register_role(): void {
		if ( ! get_role( self::ROLE ) ) {
			add_role( self::ROLE, __( 'Capoeirista', 'pura' ), array( 'read' => true ) );
		}
	}

	public static function register_hooks(): void {
		add_action( 'init', array( self::class, 'register_role' ) );
		add_action( 'admin_init', array( self::class, 'block_wp_admin' ) );
		add_filter( 'show_admin_bar', array( self::class, 'hide_admin_bar' ) );
		add_filter( 'login_redirect', array( self::class, 'login_redirect' ), 10, 3 );
		add_filter( 'logout_redirect', array( self::class, 'logout_redirect' ), 10, 3 );
	}

	/**
	 * Capoeiristas have nothing to do in wp-admin: send them to Mi cuenta.
	 */
	public static function block_wp_admin(): void {
		if ( wp_doing_ajax() || ! is_user_logged_in() || current_user_can( 'edit_posts' ) ) {
			return;
		}
		if ( self::is_member_user( wp_get_current_user() ) ) {
			wp_safe_redirect( Settings::page_url( 'account' ) );
			exit;
		}
	}

	public static function hide_admin_bar( bool $show ): bool {
		return is_user_logged_in() && self::is_member_user( wp_get_current_user() ) ? false : $show;
	}

	/**
	 * @param string           $redirect_to Requested redirect.
	 * @param string           $requested   Raw requested redirect.
	 * @param WP_User|WP_Error $user        User.
	 */
	public static function login_redirect( string $redirect_to, string $requested, $user ): string {
		return $user instanceof WP_User && self::is_member_user( $user ) ? Settings::page_url( 'account' ) : $redirect_to;
	}

	/**
	 * @param string           $redirect_to Requested redirect.
	 * @param string           $requested   Raw requested redirect.
	 * @param WP_User|WP_Error $user        User.
	 */
	public static function logout_redirect( string $redirect_to, string $requested, $user ): string {
		return $user instanceof WP_User && self::is_member_user( $user ) ? home_url( '/' ) : $redirect_to;
	}

	public static function is_member_user( WP_User $user ): bool {
		return in_array( self::ROLE, (array) $user->roles, true );
	}

	public static function identity_meta_key( string $provider ): string {
		return '_pura_identity_' . sanitize_key( $provider );
	}

	public static function find_user_by_identity( string $provider, string $sub ): ?WP_User {
		if ( '' === $sub ) {
			return null;
		}
		$users = get_users(
			array(
				'meta_key'    => self::identity_meta_key( $provider ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- single indexed lookup at login.
				'meta_value'  => $sub, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'number'      => 1,
				'count_total' => false,
			)
		);

		return $users ? $users[0] : null;
	}

	/**
	 * Resolve the signed-in identity to a WordPress user, creating one when needed, and claim the
	 * event registrations made with the verified email.
	 *
	 * @param array{sub:string,email:string,email_verified:bool,first_name:string,last_name:string,display_name:string,is_private_email:bool} $identity Identity.
	 * @return WP_User|WP_Error
	 */
	public static function find_or_create_user( array $identity, string $provider ) {
		$email    = $identity['email_verified'] && is_email( $identity['email'] ) ? strtolower( $identity['email'] ) : '';
		$user     = self::find_user_by_identity( $provider, $identity['sub'] );
		$existing = null;

		if ( ! $user && '' !== $email ) {
			$existing = get_user_by( 'email', $email );
			if ( $existing instanceof WP_User ) {
				if ( ! self::can_attach_identity( $existing ) ) {
					return new WP_Error( 'admin_account', 'Esta cuenta pertenece al equipo de Pura Capoeira; entra por el acceso de administración.' );
				}
				$user = $existing;
			}
		}

		if ( ! $user ) {
			$user = self::create_user( $identity, $email );
			if ( is_wp_error( $user ) ) {
				return $user;
			}
		}

		update_user_meta( $user->ID, self::identity_meta_key( $provider ), $identity['sub'] );
		if ( $identity['is_private_email'] ) {
			update_user_meta( $user->ID, self::META_PRIVATE_EMAIL, 1 );
		} elseif ( '' !== $email ) {
			// Stored apart from user_email so an admin edit can never widen what the account sees.
			update_user_meta( $user->ID, self::META_VERIFIED_EMAIL, $email );
		}

		// Names: fill in, never blank (Apple only sends them on the first authorization).
		$updates = array();
		if ( '' !== $identity['first_name'] && '' === (string) get_user_meta( $user->ID, 'first_name', true ) ) {
			$updates['first_name'] = $identity['first_name'];
		}
		if ( '' !== $identity['last_name'] && '' === (string) get_user_meta( $user->ID, 'last_name', true ) ) {
			$updates['last_name'] = $identity['last_name'];
		}
		if ( '' !== $identity['display_name'] && $user->display_name === $user->user_login ) {
			$updates['display_name'] = $identity['display_name'];
		}
		if ( '' === $user->user_email && '' !== $email && ! email_exists( $email ) ) {
			$updates['user_email'] = $email;
		}
		if ( $updates ) {
			wp_update_user( array_merge( array( 'ID' => $user->ID ), $updates ) );
			$user = get_user_by( 'id', $user->ID ) ?: $user;
		}

		self::claim_registrations( $user );

		return $user;
	}

	/**
	 * A provider-asserted email may only attach to an account whose sole role is Capoeirista: it
	 * must never open a staff account.
	 */
	public static function can_attach_identity( WP_User $user ): bool {
		return array( self::ROLE ) === array_values( (array) $user->roles );
	}

	/**
	 * Set the auth cookie for the browser and announce the login like wp_signon() would.
	 */
	public static function login( WP_User $user ): void {
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true, is_ssl() );
		do_action( 'wp_login', $user->user_login, $user ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core login hook, fired on purpose like wp_signon().
	}

	/**
	 * Provider IDs linked to the account.
	 *
	 * @return string[]
	 */
	public static function providers_for_user( int $user_id ): array {
		return array_values(
			array_filter(
				Providers::IDS,
				static fn ( string $provider ): bool => '' !== (string) get_user_meta( $user_id, self::identity_meta_key( $provider ), true )
			)
		);
	}

	/**
	 * The provider-verified email, or '' for relay addresses and unverified accounts.
	 */
	public static function verified_email_for_user( WP_User $user ): string {
		$email = (string) get_user_meta( $user->ID, self::META_VERIFIED_EMAIL, true );

		return is_email( $email ) ? strtolower( $email ) : '';
	}

	/**
	 * Event registrations the account may see: the ones it owns plus the ones made with its
	 * verified email while logged out. Newest first.
	 *
	 * @return int[] Post IDs.
	 */
	public static function registrations_for_user( WP_User $user, int $limit = 50 ): array {
		$meta_query = array(
			'relation' => 'OR',
			array(
				'key'   => self::META_USER,
				'value' => $user->ID,
			),
		);
		$email      = self::verified_email_for_user( $user );
		if ( '' !== $email ) {
			$meta_query[] = array(
				'key'   => '_pura_email',
				'value' => $email,
			);
		}

		$query = new \WP_Query(
			array(
				'post_type'              => Event_Registration_Post_Type::POST_TYPE,
				'post_status'            => 'any',
				'posts_per_page'         => $limit,
				'fields'                 => 'ids',
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'meta_query'             => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- per-account history, small volume.
			)
		);

		return array_map( 'intval', $query->posts );
	}

	/**
	 * Stamp `_pura_user_id` on registrations made with the verified email that nobody owns yet, so
	 * the history survives a later email change on the provider side. Never steals a registration
	 * from another account. Idempotent; runs at every login.
	 *
	 * @return int Registrations claimed.
	 */
	public static function claim_registrations( WP_User $user ): int {
		$email = self::verified_email_for_user( $user );
		if ( '' === $email ) {
			return 0;
		}

		$query = new \WP_Query(
			array(
				'post_type'              => Event_Registration_Post_Type::POST_TYPE,
				'post_status'            => 'any',
				'posts_per_page'         => 100,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one small query per login.
					array(
						'key'   => '_pura_email',
						'value' => $email,
					),
				),
			)
		);

		$claimed = 0;
		foreach ( $query->posts as $post_id ) {
			$owner = (int) get_post_meta( (int) $post_id, self::META_USER, true );
			if ( $owner > 0 ) {
				continue;
			}
			update_post_meta( (int) $post_id, self::META_USER, $user->ID );
			++$claimed;
		}

		return $claimed;
	}

	/**
	 * @param array{sub:string,email:string,first_name:string,last_name:string,display_name:string} $identity Identity.
	 * @return WP_User|WP_Error
	 */
	private static function create_user( array $identity, string $email ) {
		self::register_role();

		$login = '';
		for ( $i = 0; $i < 5; $i++ ) {
			$candidate = 'capoeirista-' . bin2hex( random_bytes( 5 ) );
			if ( ! username_exists( $candidate ) ) {
				$login = $candidate;
				break;
			}
		}
		if ( '' === $login ) {
			return new WP_Error( 'user_create', 'No se pudo crear la cuenta. Intenta de nuevo.' );
		}

		$display = '' !== $identity['display_name'] ? $identity['display_name'] : ( '' !== $email ? strstr( $email, '@', true ) : $login );

		$user_id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_pass'    => wp_generate_password( 32, true, true ),
				'user_email'   => $email,
				'display_name' => (string) $display,
				'first_name'   => $identity['first_name'],
				'last_name'    => $identity['last_name'],
				'role'         => self::ROLE,
			)
		);
		if ( is_wp_error( $user_id ) ) {
			return new WP_Error( 'user_create', 'No se pudo crear la cuenta: ' . $user_id->get_error_message() );
		}

		$user = get_user_by( 'id', (int) $user_id );

		return $user instanceof WP_User ? $user : new WP_Error( 'user_create', 'No se pudo crear la cuenta.' );
	}
}
