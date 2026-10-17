<?php
/**
 * GET /pura/v1/account/me — the signed-in capoeirista and their event registrations.
 *
 * Page HTML is cached, so the browser fetches this with the REST nonce it got from admin-ajax; a
 * valid nonce is what proves the session (and doubles as the CSRF check).
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Rest;

use Pura\Core\Auth\Member_Accounts;
use Pura\Core\Data\Event_Registration_Post_Type;
use Pura\Core\Data\Event_Registration_Repository;
use Pura\Core\Rate_Limiter;
use Pura\Core\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_User;

defined( 'ABSPATH' ) || exit;

final class Account_Controller extends Base_Controller {

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/account/me',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'me' ),
				'permission_callback' => $this->logged_in_permission( 'account-me', 60 ),
			)
		);
	}

	public function me( WP_REST_Request $request ): WP_REST_Response {
		return $this->ok( $this->payload( wp_get_current_user() ), 200, array( 'Cache-Control' => 'no-store' ) );
	}

	/**
	 * Signed-in users only; an invalid nonce never gets this far (core answers 403).
	 *
	 * @return callable(WP_REST_Request): (bool|WP_Error)
	 */
	private function logged_in_permission( string $bucket, int $limit ): callable {
		return static function () use ( $bucket, $limit ) {
			if ( ! is_user_logged_in() ) {
				return new WP_Error( 'pura_login_required', 'Inicia sesión para continuar.', array( 'status' => 401 ) );
			}
			if ( ! Rate_Limiter::check( $bucket, 'user-' . get_current_user_id(), $limit, 600 ) ) {
				return new WP_Error( 'pura_rate_limited', 'Demasiadas solicitudes. Intenta de nuevo en unos minutos.', array( 'status' => 429 ) );
			}

			return true;
		};
	}

	/**
	 * @return array<string, mixed>
	 */
	private function payload( WP_User $user ): array {
		$registrations = array();
		foreach ( Member_Accounts::registrations_for_user( $user ) as $post_id ) {
			$row = Event_Registration_Repository::to_array( $post_id );
			if ( ! $row ) {
				continue;
			}
			$registrations[] = array(
				'id'          => $post_id,
				'eventName'   => '' !== $row['event_name'] ? $row['event_name'] : $row['event'],
				'eventSlug'   => $row['event'],
				'date'        => $row['created_at'],
				'status'      => $row['status'],
				'statusLabel' => Event_Registration_Post_Type::status_label( $row['status'] ),
				'days'        => $row['days'],
				// The receipt itself stays private: rows matched by email come from unverified form
				// submissions, and a typo'd address must never hand a stranger a bank receipt.
				'hasProof'    => $row['payment_proof_id'] > 0,
			);
		}

		return array(
			'user'          => array(
				'displayName'  => $user->display_name,
				'firstName'    => (string) get_user_meta( $user->ID, 'first_name', true ),
				'lastName'     => (string) get_user_meta( $user->ID, 'last_name', true ),
				'email'        => $user->user_email,
				'privateEmail' => (bool) get_user_meta( $user->ID, Member_Accounts::META_PRIVATE_EMAIL, true ),
				'providers'    => Member_Accounts::providers_for_user( $user->ID ),
			),
			'registrations' => $registrations,
			// wp_logout_url() returns an HTML-escaped URL (&amp;); this one travels as JSON into a href.
			'logoutUrl'     => html_entity_decode( wp_logout_url( home_url( '/' ) ), ENT_QUOTES, 'UTF-8' ),
			'whatsappUrl'   => Settings::whatsapp_url( 'Hola, entré a Mi cuenta en puracapoeira.com y tengo una duda sobre mi registro a un evento.' ),
		);
	}
}
