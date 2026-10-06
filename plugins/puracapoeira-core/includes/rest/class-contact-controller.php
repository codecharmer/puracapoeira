<?php
/**
 * POST pura/v1/contact — the public contact form.
 *
 * No nonce by design: the pages sit behind cPanel's NGINX page cache, so a nonce baked into
 * cached HTML would go stale. Protection is the JSON content type, a honeypot, a minimum form
 * age and a per-IP rate limit (see Base_Controller::public_permission()).
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Rest;

use Pura\Core\Contact_Message;
use Pura\Core\Mailer;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Contact_Controller extends Base_Controller {

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/contact',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => $this->public_permission( 'contact', 5, 600 ),
			)
		);
	}

	public function handle( WP_REST_Request $request ): WP_REST_Response {
		$body   = $request->get_json_params();
		$result = Contact_Message::from_array( is_array( $body ) ? $body : array() );

		if ( ! $result['ok'] ) {
			return $this->error( Contact_Message::message( $result['errors'] ), 400 );
		}

		$data = array(
			'nombre'   => sanitize_text_field( $result['data']['nombre'] ),
			'ciudad'   => sanitize_text_field( $result['data']['ciudad'] ),
			'telefono' => sanitize_text_field( $result['data']['telefono'] ),
			'email'    => sanitize_email( $result['data']['email'] ),
			'mensaje'  => sanitize_textarea_field( $result['data']['mensaje'] ),
		);

		if ( ! Mailer::send_contact( $data ) ) {
			return $this->error( 'No se pudo enviar el mensaje. Inténtalo de nuevo o escríbenos por WhatsApp.', 500 );
		}

		return $this->ok();
	}
}
