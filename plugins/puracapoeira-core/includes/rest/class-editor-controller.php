<?php
/**
 * The editor page's endpoints: a professor reads and changes their own profile and sedes.
 *
 * GET   pura/v1/editor/me                  what the signed-in person may edit
 * PATCH pura/v1/editor/{sede|profesor}/{id} change some fields of one of those
 *
 * Every route needs a signed-in member and the REST nonce. Which post may be touched is
 * decided per request by Editor_Access (the member's verified email is on that post); which
 * fields, by Editor_Fields. WordPress's own editing capabilities are never consulted, so a
 * professor gains nothing outside these routes. A post the member is not linked to answers
 * "not found", the same as one that does not exist.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Rest;

use Pura\Core\Auth\Editor_Access;
use Pura\Core\Data\Profesor_Post_Type;
use Pura\Core\Data\Sede_Post_Type;
use Pura\Core\Editor\Editor_Document;
use Pura\Core\Editor\Editor_Fields;
use Pura\Core\Editor\Editor_Writer;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Editor_Controller extends Base_Controller {

	private const NO_STORE = array( 'Cache-Control' => 'no-store' );

	/** @var array<string, string> Route segment => post type. */
	private const POST_TYPES = array(
		Editor_Fields::TYPE_SEDE     => Sede_Post_Type::POST_TYPE,
		Editor_Fields::TYPE_PROFESOR => Profesor_Post_Type::POST_TYPE,
	);

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/editor/me',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'me' ),
				'permission_callback' => $this->logged_in_permission( 'editor-read', 120 ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/editor/(?P<type>sede|profesor)/(?P<id>\d+)',
			array(
				'methods'             => 'PATCH',
				'callback'            => array( $this, 'update' ),
				'permission_callback' => $this->logged_in_permission( 'editor-write', 60 ),
			)
		);
	}

	public function me(): WP_REST_Response {
		$posts = Editor_Access::posts_for_user( wp_get_current_user() );

		return $this->ok(
			array(
				'profesor' => $posts['profesor'] ? Editor_Document::for_post( $posts['profesor'] ) : null,
				'sedes'    => array_map( array( Editor_Document::class, 'for_post' ), $posts['sedes'] ),
				'limits'   => array(
					'rows' => Editor_Fields::MAX_ROWS,
					'cell' => Editor_Fields::MAX_CELL,
				),
			),
			200,
			self::NO_STORE
		);
	}

	public function update( WP_REST_Request $request ): WP_REST_Response {
		$post = $this->editable_post( (string) $request['type'], (int) $request['id'] );
		if ( ! $post ) {
			return $this->error( 'No encontramos esa ficha.', 404, array( 'code' => 'not_found' ) );
		}

		$body   = $request->get_json_params();
		$fields = is_array( $body ) && isset( $body['fields'] ) && is_array( $body['fields'] ) ? $body['fields'] : null;
		$base   = is_array( $body ) && isset( $body['base_modified'] ) && is_string( $body['base_modified'] ) ? $body['base_modified'] : '';
		if ( null === $fields || ! $fields || '' === $base ) {
			return $this->error( 'Faltan datos para guardar.', 400, array( 'code' => 'bad_request' ) );
		}

		// Someone else (or another tab) saved since this copy was loaded: saving now would undo their change.
		if ( ! hash_equals( Editor_Document::version( $post ), $base ) ) {
			return new WP_REST_Response(
				array(
					'ok'      => false,
					'error'   => 'Alguien más guardó cambios en esta ficha. Revisa los datos actuales y vuelve a guardar.',
					'code'    => 'conflict',
					'current' => Editor_Document::for_post( $post ),
				),
				409,
				self::NO_STORE
			);
		}

		$check = Editor_Fields::validate( Editor_Document::type_of( $post ), $fields );
		if ( ! $check['ok'] ) {
			return $this->error(
				Editor_Fields::message( $check['errors'] ),
				400,
				array(
					'code'   => 'invalid',
					'fields' => $check['errors'],
				)
			);
		}

		$user   = wp_get_current_user();
		$result = Editor_Writer::apply( $post, $check['values'], (int) $user->ID, Editor_Access::actor_label( $user ) );
		$fresh  = get_post( $post->ID );

		return $this->ok(
			array(
				'changed' => $result['changed'],
				'item'    => Editor_Document::for_post( $fresh instanceof WP_Post ? $fresh : $post ),
			),
			200,
			self::NO_STORE
		);
	}

	/** The post behind a route, when it exists, is published, is of the route's type and the caller may edit it. */
	private function editable_post( string $type, int $id ): ?WP_Post {
		$post = $id > 0 ? get_post( $id ) : null;
		if ( ! $post instanceof WP_Post || ! isset( self::POST_TYPES[ $type ] ) || self::POST_TYPES[ $type ] !== $post->post_type || 'publish' !== $post->post_status ) {
			return null;
		}
		$user = wp_get_current_user();

		return Editor_Access::can_edit( $user, $post ) || user_can( $user, 'manage_options' ) ? $post : null;
	}
}
