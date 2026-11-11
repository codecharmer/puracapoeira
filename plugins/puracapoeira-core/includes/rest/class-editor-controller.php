<?php
/**
 * The editor page's endpoints: a professor reads and changes their own profile and sedes.
 *
 * GET   pura/v1/editor/me                  what the signed-in person may edit
 * PATCH pura/v1/editor/{sede|profesor}/{id} change some fields of one of those
 * POST  pura/v1/editor/{sede|profesor}/{id}/photo  replace the main photo (multipart `file`)
 * POST  pura/v1/editor/sede/{id}/gallery   add a photo to the gallery (multipart `file`)
 * PUT   pura/v1/editor/sede/{id}/gallery   reorder the gallery or remove photos from it
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
use Pura\Core\Data\Photo_Batch_Repository;
use Pura\Core\Data\Profesor_Post_Type;
use Pura\Core\Data\Sede_Post_Type;
use Pura\Core\Editor\Bio_Document;
use Pura\Core\Editor\Editor_Document;
use Pura\Core\Editor\Editor_Fields;
use Pura\Core\Editor\Editor_Photos;
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

		register_rest_route(
			self::NAMESPACE,
			'/editor/(?P<type>sede|profesor)/(?P<id>\d+)/photo',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'set_photo' ),
				'permission_callback' => $this->logged_in_permission( 'editor-upload', 40 ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/editor/sede/(?P<id>\d+)/gallery',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_to_gallery' ),
					'permission_callback' => $this->logged_in_permission( 'editor-upload', 40 ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'order_gallery' ),
					'permission_callback' => $this->logged_in_permission( 'editor-write', 60 ),
				),
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
					'rows'     => Editor_Fields::MAX_ROWS,
					'cell'     => Editor_Fields::MAX_CELL,
					'gallery'  => Editor_Photos::MAX_GALLERY,
					'bio'      => array(
						'items'     => Bio_Document::MAX_ITEMS,
						'text'      => Bio_Document::MAX_TEXT,
						'events'    => Bio_Document::MAX_EVENTS,
						'year'      => Bio_Document::MAX_YEAR,
						'title'     => Bio_Document::MAX_TITLE,
						'eventText' => Bio_Document::MAX_EVENT_TEXT,
					),
					'maxBytes' => Photo_Batch_Repository::max_bytes(),
				),
			),
			200,
			self::NO_STORE
		);
	}

	public function update( WP_REST_Request $request ): WP_REST_Response {
		$post = $this->editable_post( (string) $request['type'], (int) $request['id'] );
		if ( ! $post ) {
			return $this->not_found();
		}

		$body   = $request->get_json_params();
		$fields = is_array( $body ) && isset( $body['fields'] ) && is_array( $body['fields'] ) ? $body['fields'] : null;
		$base   = is_array( $body ) && isset( $body['base_modified'] ) && is_string( $body['base_modified'] ) ? $body['base_modified'] : '';
		if ( null === $fields || ! $fields || '' === $base ) {
			return $this->error( 'Faltan datos para guardar.', 400, array( 'code' => 'bad_request' ) );
		}

		// Someone else (or another tab) saved since this copy was loaded: saving now would undo their change.
		if ( ! hash_equals( Editor_Document::version( $post ), $base ) ) {
			return $this->conflict( $post );
		}

		// The bio arrives as a list of edits; it becomes the new content here, or refuses the whole save.
		$bio = null;
		if ( array_key_exists( 'bio', $fields ) && Editor_Fields::content_definitions( Editor_Document::type_of( $post ) ) ) {
			$built = Bio_Document::apply( (string) $post->post_content, $fields['bio'] );
			if ( ! $built['ok'] ) {
				return $this->error(
					Editor_Fields::message( array( 'bio' => $built['error'] ) ),
					400,
					array(
						'code'   => 'invalid',
						'fields' => array( 'bio' => $built['error'] ),
					)
				);
			}
			$bio = $built['content'];
			unset( $fields['bio'] );
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

		return $this->applied( $post, null === $bio ? $check['values'] : $check['values'] + array( 'bio' => $bio ) );
	}

	/** Replaces the main photo of a sede or the portrait of a profesor with an uploaded file. */
	public function set_photo( WP_REST_Request $request ): WP_REST_Response {
		$post = $this->editable_post( (string) $request['type'], (int) $request['id'] );
		if ( ! $post ) {
			return $this->not_found();
		}
		$id = $this->stored_upload( $request, $post, Editor_Fields::TYPE_SEDE === $request['type'] ? 'Foto principal' : 'Retrato' );

		return $id instanceof WP_REST_Response ? $id : $this->applied( $post, array( 'image' => $id ), 201 );
	}

	/** Adds an uploaded file at the end of a sede's gallery. */
	public function add_to_gallery( WP_REST_Request $request ): WP_REST_Response {
		$post = $this->editable_post( Editor_Fields::TYPE_SEDE, (int) $request['id'] );
		if ( ! $post ) {
			return $this->not_found();
		}
		if ( count( Editor_Photos::gallery_ids( $post->ID ) ) >= Editor_Photos::MAX_GALLERY ) {
			return $this->error( sprintf( 'La galería ya tiene %d fotos, que es el máximo. Quita una para añadir otra.', Editor_Photos::MAX_GALLERY ), 400, array( 'code' => 'gallery_full' ) );
		}
		$id = $this->stored_upload( $request, $post, 'Galería' );
		if ( $id instanceof WP_REST_Response ) {
			return $id;
		}

		// Read again: another photo may have been added while this one was uploading.
		return $this->applied( $post, array( 'gallery' => array_merge( Editor_Photos::gallery_ids( $post->ID ), array( $id ) ) ), 201 );
	}

	/** New order of a sede's gallery; photos left out are removed from it. */
	public function order_gallery( WP_REST_Request $request ): WP_REST_Response {
		$post = $this->editable_post( Editor_Fields::TYPE_SEDE, (int) $request['id'] );
		if ( ! $post ) {
			return $this->not_found();
		}
		$body = $request->get_json_params();
		$base = is_array( $body ) && isset( $body['base_modified'] ) && is_string( $body['base_modified'] ) ? $body['base_modified'] : '';
		$ids  = is_array( $body ) ? ( $body['ids'] ?? null ) : null;
		if ( '' === $base || ! is_array( $ids ) ) {
			return $this->error( 'Faltan datos para guardar.', 400, array( 'code' => 'bad_request' ) );
		}
		if ( ! hash_equals( Editor_Document::version( $post ), $base ) ) {
			return $this->conflict( $post );
		}
		if ( ! Editor_Photos::is_valid_order( Editor_Photos::gallery_ids( $post->ID ), $ids ) ) {
			return $this->error( 'Esa lista de fotos no corresponde a la galería actual.', 400, array( 'code' => 'invalid' ) );
		}

		return $this->applied( $post, array( 'gallery' => $ids ) );
	}

	/**
	 * Checks and stores the request's `file` for a post.
	 *
	 * @return int|WP_REST_Response Attachment id, or the refusal to send back.
	 */
	private function stored_upload( WP_REST_Request $request, WP_Post $post, string $label ) {
		$files = $request->get_file_params();
		$error = Editor_Photos::upload_error( $files['file'] ?? null );
		if ( null !== $error ) {
			return $this->error( $error, 400, array( 'code' => 'bad_photo' ) );
		}
		$id = Editor_Photos::store( $post, 'file', $label );
		if ( is_wp_error( $id ) ) {
			return $this->error( 'No se pudo guardar la foto (' . $id->get_error_message() . ').', 400, array( 'code' => 'bad_photo' ) );
		}

		return $id;
	}

	/**
	 * Applies values through the writer and answers with the post as it now is.
	 *
	 * @param array<string, mixed> $values Checked values.
	 */
	private function applied( WP_Post $post, array $values, int $status = 200 ): WP_REST_Response {
		$user   = wp_get_current_user();
		$result = Editor_Writer::apply( $post, $values, (int) $user->ID, Editor_Access::actor_label( $user ) );
		$fresh  = get_post( $post->ID );

		return $this->ok(
			array(
				'changed' => $result['changed'],
				'item'    => Editor_Document::for_post( $fresh instanceof WP_Post ? $fresh : $post ),
			),
			$status,
			self::NO_STORE
		);
	}

	private function not_found(): WP_REST_Response {
		return $this->error( 'No encontramos esa ficha.', 404, array( 'code' => 'not_found' ) );
	}

	private function conflict( WP_Post $post ): WP_REST_Response {
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
