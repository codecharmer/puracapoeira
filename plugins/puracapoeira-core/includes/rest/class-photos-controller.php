<?php
/**
 * REST pura/v1/photos/*: the colleagues' photo upload (/fotos/?clave=…).
 *
 *   GET  photos/access?key=            is this link valid?
 *   POST photos/batches                 open an upload session (who, where) → batch id + token
 *   POST photos/batches/{id}/files      one photo per request (multipart) with the session token
 *   POST photos/batches/{id}/finish     close the session and notify the recipients
 *
 * One file per request keeps every upload under PHP's post size limit and lets the browser show
 * progress and retry a single failed photo.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Rest;

use Pura\Core\Data\Photo_Batch_Post_Type;
use Pura\Core\Data\Photo_Batch_Repository;
use Pura\Core\Data\Profesor_Post_Type;
use Pura\Core\Data\Sede_Post_Type;
use Pura\Core\Mailer;
use Pura\Core\Photo_Uploads;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Photos_Controller extends Base_Controller {

	private const NO_STORE = array( 'Cache-Control' => 'no-store' );

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/photos/access',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'access' ),
				'permission_callback' => $this->public_permission( 'photos_access', 60 ),
				'args'                => array(
					'key' => $this->text_arg( 64 ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/photos/batches',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_batch' ),
				'permission_callback' => $this->public_permission( 'photos_batches', 20 ),
				'args'                => array(
					'key'         => $this->text_arg( 64 ),
					'name'        => $this->text_arg( 120 ),
					'contact'     => $this->text_arg( 160 ),
					'sede_id'     => array(
						'type'    => array( 'integer', 'string' ),
						'default' => 0,
					),
					'profesor_id' => array(
						'type'    => array( 'integer', 'string' ),
						'default' => 0,
					),
					'note'        => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => static fn ( $value ) => mb_substr( sanitize_textarea_field( (string) $value ), 0, 1000 ),
					),
					'website'     => $this->text_arg( 200 ), // Honeypot.
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/photos/batches/(?P<id>\d+)/files',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'add_file' ),
				'permission_callback' => $this->public_permission( 'photos_files', 400 ),
				'args'                => array(
					'id'    => array(
						'type'     => 'integer',
						'required' => true,
					),
					'token' => $this->text_arg( 64 ),
					'kind'  => array(
						'type'    => 'string',
						'enum'    => array( Photo_Batch_Repository::KIND_PHOTO, Photo_Batch_Repository::KIND_PROFILE ),
						'default' => Photo_Batch_Repository::KIND_PHOTO,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/photos/batches/(?P<id>\d+)/finish',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'finish' ),
				'permission_callback' => $this->public_permission( 'photos_finish', 40 ),
				'args'                => array(
					'id'    => array(
						'type'     => 'integer',
						'required' => true,
					),
					'token' => $this->text_arg( 64 ),
				),
			)
		);
	}

	public function access( WP_REST_Request $request ): WP_REST_Response {
		if ( ! Photo_Uploads::verify( (string) $request->get_param( 'key' ) ) ) {
			return $this->invalid_link();
		}

		return $this->ok(
			array(
				'max_bytes' => Photo_Batch_Repository::max_bytes(),
				'max_files' => Photo_Batch_Repository::MAX_FILES,
			),
			200,
			self::NO_STORE
		);
	}

	public function create_batch( WP_REST_Request $request ): WP_REST_Response {
		if ( ! Photo_Uploads::verify( (string) $request->get_param( 'key' ) ) ) {
			return $this->invalid_link();
		}

		$name = trim( (string) $request->get_param( 'name' ) );
		if ( '' === $name ) {
			return $this->error( 'Escribe tu nombre o apelido.', 400 );
		}

		$sede_id     = (int) $request->get_param( 'sede_id' );
		$profesor_id = (int) $request->get_param( 'profesor_id' );
		if ( $sede_id > 0 && Sede_Post_Type::POST_TYPE !== get_post_type( $sede_id ) ) {
			$sede_id = 0;
		}
		if ( $profesor_id > 0 && Profesor_Post_Type::POST_TYPE !== get_post_type( $profesor_id ) ) {
			$profesor_id = 0;
		}

		$post_id = Photo_Batch_Repository::create(
			array(
				'name'        => $name,
				'contact'     => (string) $request->get_param( 'contact' ),
				'note'        => (string) $request->get_param( 'note' ),
				'sede_id'     => $sede_id,
				'profesor_id' => $profesor_id,
			)
		);
		if ( ! $post_id ) {
			return $this->error( 'No se pudo iniciar el envío. Intenta de nuevo.', 500 );
		}

		return $this->ok(
			array(
				'batch_id'  => $post_id,
				'token'     => Photo_Batch_Repository::issue_token( $post_id ),
				'max_bytes' => Photo_Batch_Repository::max_bytes(),
				'max_files' => Photo_Batch_Repository::MAX_FILES,
			),
			201,
			self::NO_STORE
		);
	}

	public function add_file( WP_REST_Request $request ): WP_REST_Response {
		$post_id = (int) $request->get_param( 'id' );
		if ( ! Photo_Batch_Repository::verify_token( $post_id, (string) $request->get_param( 'token' ) ) ) {
			return $this->error( 'Este envío caducó. Recarga la página y vuelve a empezar.', 403, array( 'expired' => true ) );
		}

		if ( Photo_Batch_Repository::file_count( $post_id ) >= Photo_Batch_Repository::MAX_FILES ) {
			return $this->error( sprintf( 'Máximo %d fotos por envío. Puedes hacer otro envío después.', Photo_Batch_Repository::MAX_FILES ), 400 );
		}

		$files = $request->get_file_params();
		$file  = $files['file'] ?? null;
		if ( ! is_array( $file ) || UPLOAD_ERR_NO_FILE === (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			return $this->error( 'No llegó ninguna foto.', 400 );
		}

		$max   = Photo_Batch_Repository::max_bytes();
		$error = (int) $file['error'];
		if ( in_array( $error, array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true ) || (int) ( $file['size'] ?? 0 ) > $max ) {
			return $this->error( sprintf( 'La foto es demasiado grande (máximo %s).', size_format( $max ) ), 400 );
		}
		if ( UPLOAD_ERR_OK !== $error || empty( $file['tmp_name'] ) || ! is_uploaded_file( (string) $file['tmp_name'] ) ) {
			return $this->error( 'No se pudo subir la foto. Intenta de nuevo.', 400 );
		}

		$check = wp_check_filetype_and_ext( (string) $file['tmp_name'], (string) $file['name'], Photo_Batch_Repository::MIMES );
		if ( empty( $check['type'] ) ) {
			return $this->error( 'Solo aceptamos imágenes (JPG, PNG, WebP, HEIC).', 400 );
		}

		$attachment_id = Photo_Batch_Repository::attach_file( $post_id, 'file', (string) $request->get_param( 'kind' ) );
		if ( is_wp_error( $attachment_id ) ) {
			return $this->error( 'No se pudo guardar la foto (' . $attachment_id->get_error_message() . ').', 400 );
		}

		return $this->ok(
			array(
				'attachment_id' => $attachment_id,
				'url'           => (string) wp_get_attachment_image_url( $attachment_id, 'medium' ),
				'count'         => Photo_Batch_Repository::file_count( $post_id ),
			),
			201,
			self::NO_STORE
		);
	}

	public function finish( WP_REST_Request $request ): WP_REST_Response {
		$post_id = (int) $request->get_param( 'id' );
		if ( ! Photo_Batch_Repository::verify_token( $post_id, (string) $request->get_param( 'token' ) ) ) {
			return $this->error( 'Este envío caducó. Recarga la página y vuelve a empezar.', 403, array( 'expired' => true ) );
		}

		$count = Photo_Batch_Repository::file_count( $post_id );
		if ( 0 === $count ) {
			return $this->error( 'No llegó ninguna foto. Elige al menos una e inténtalo de nuevo.', 400 );
		}

		update_post_meta( $post_id, '_pura_status', Photo_Batch_Post_Type::STATUS_DONE );

		// Notify once per session; photos added after a retry still land in the same batch.
		if ( ! (int) get_post_meta( $post_id, '_pura_notified', true ) ) {
			update_post_meta( $post_id, '_pura_notified', time() );
			Mailer::notify_photo_batch( $post_id );
		}

		return $this->ok(
			array(
				'count'   => $count,
				'message' => sprintf( 'Recibimos %d %s. Las revisamos y las añadimos a tu perfil y a tu sede. ¡Gracias!', $count, 1 === $count ? 'foto' : 'fotos' ),
			),
			200,
			self::NO_STORE
		);
	}

	private function invalid_link(): WP_REST_Response {
		return $this->error( 'Este enlace no es válido. Pide el enlace para subir fotos por WhatsApp.', 403, array( 'invalid_key' => true ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function text_arg( int $max ): array {
		return array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => static fn ( $value ) => mb_substr( sanitize_text_field( (string) $value ), 0, $max ),
		);
	}
}
