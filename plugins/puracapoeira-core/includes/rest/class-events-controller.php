<?php
/**
 * POST pura/v1/events/register — registration to an event (no payment; the organisers follow up).
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Rest;

use Pura\Core\Data\Event_Registration_Repository;
use Pura\Core\Mailer;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Events_Controller extends Base_Controller {

	public const SHIRT_SIZES = array( '', 'XS', 'S', 'M', 'L', 'XL', 'XXL' );

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/events/register',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'register' ),
				'permission_callback' => $this->public_permission( 'events', 5 ),
				'args'                => array(
					'event'           => array(
						'type'     => 'string',
						'required' => true,
						'pattern'  => '^[a-z0-9-]{1,60}$',
					),
					'event_name'      => $this->text_arg( 120 ),
					'days'            => array(
						'type'    => 'array',
						'default' => array(),
						'items'   => array(
							'type'      => 'string',
							'maxLength' => 40,
						),
					),
					'days_offered'    => array(
						'type'    => 'array',
						'default' => array(),
						'items'   => array(
							'type'      => 'string',
							'maxLength' => 40,
						),
					),
					'first_name'      => $this->text_arg( 100 ),
					'last_name'       => $this->text_arg( 100 ),
					'email'           => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_email',
					),
					'phone'           => $this->text_arg( 40 ),
					'dob'             => array(
						'type'    => 'string',
						'pattern' => '^(\d{4}-\d{2}-\d{2})?$',
						'default' => '',
					),
					'parent_name'     => $this->text_arg( 150 ),
					'parent_phone'    => $this->text_arg( 40 ),
					'started_year'    => array(
						'type'    => array( 'integer', 'string' ),
						'default' => '',
					),
					'years_training'  => array(
						'type'    => array( 'integer', 'string' ),
						'default' => '',
					),
					'city'            => $this->text_arg( 100 ),
					'academy'         => $this->text_arg( 150 ),
					'teacher'         => $this->text_arg( 150 ),
					'graduation'      => $this->text_arg( 80 ),
					'shirt_size'      => array(
						'type'    => 'string',
						'enum'    => self::SHIRT_SIZES,
						'default' => '',
					),
					'emergency_name'  => $this->text_arg( 150 ),
					'emergency_phone' => $this->text_arg( 40 ),
					'notes'           => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => static fn ( $value ) => mb_substr( sanitize_textarea_field( (string) $value ), 0, 1000 ),
					),
					'website'         => $this->text_arg( 200 ), // Honeypot.
				),
			)
		);
	}

	public function register( WP_REST_Request $request ): WP_REST_Response {
		$event = (string) $request->get_param( 'event' );
		$email = Event_Registration_Repository::normalize_email( (string) $request->get_param( 'email' ) );

		if ( ! is_email( $email ) ) {
			return $this->error( 'Escribe un correo electrónico válido.', 400 );
		}

		$data = array(
			'event'           => $event,
			'event_name'      => (string) $request->get_param( 'event_name' ),
			'first_name'      => (string) $request->get_param( 'first_name' ),
			'last_name'       => (string) $request->get_param( 'last_name' ),
			'email'           => $email,
			'phone'           => (string) $request->get_param( 'phone' ),
			'dob'             => (string) $request->get_param( 'dob' ),
			'parent_name'     => (string) $request->get_param( 'parent_name' ),
			'parent_phone'    => (string) $request->get_param( 'parent_phone' ),
			'started_year'    => trim( (string) $request->get_param( 'started_year' ) ),
			'years_training'  => trim( (string) $request->get_param( 'years_training' ) ),
			'city'            => (string) $request->get_param( 'city' ),
			'academy'         => (string) $request->get_param( 'academy' ),
			'teacher'         => (string) $request->get_param( 'teacher' ),
			'graduation'      => (string) $request->get_param( 'graduation' ),
			'shirt_size'      => (string) $request->get_param( 'shirt_size' ),
			'emergency_name'  => (string) $request->get_param( 'emergency_name' ),
			'emergency_phone' => (string) $request->get_param( 'emergency_phone' ),
			'notes'           => (string) $request->get_param( 'notes' ),
		);

		$required = array(
			'first_name'      => 'Escribe tu nombre.',
			'last_name'       => 'Escribe tus apellidos.',
			'phone'           => 'Escribe un teléfono o WhatsApp de contacto.',
			'emergency_phone' => 'Escribe un teléfono de emergencia.',
		);
		foreach ( $required as $field => $message ) {
			if ( '' === trim( $data[ $field ] ) ) {
				return $this->error( $message, 400 );
			}
		}

		$today = ( new \DateTimeImmutable( 'today', wp_timezone() ) )->format( 'Y-m-d' );
		if ( ! $this->valid_date( $data['dob'] ) || $data['dob'] > $today ) {
			return $this->error( 'Indica una fecha de nacimiento válida.', 400 );
		}
		if ( Event_Registration_Repository::is_minor( $data['dob'], $today ) && '' === trim( $data['parent_phone'] ) ) {
			return $this->error( 'Para menores de edad necesitamos el teléfono del padre, madre o tutor.', 400 );
		}

		// Two different things: the year someone first stepped into a roda, and how many years
		// they have actually trained with consistency since.
		$this_year = (int) substr( $today, 0, 4 );
		if ( ! ctype_digit( $data['started_year'] ) || (int) $data['started_year'] < 1900 || (int) $data['started_year'] > $this_year ) {
			return $this->error( 'Indica el año en que empezaste capoeira (por ejemplo 2016).', 400 );
		}
		if ( ! ctype_digit( $data['years_training'] ) || (int) $data['years_training'] > 100 ) {
			return $this->error( 'Indica cuántos años has entrenado de forma constante (0 si acabas de empezar).', 400 );
		}
		if ( (int) $data['years_training'] > $this_year - (int) $data['started_year'] + 1 ) {
			return $this->error( 'Los años de entrenamiento constante no pueden ser más que los años desde que empezaste.', 400 );
		}

		// The form tells us which days it offered; only those can be chosen.
		$offered = array_values( array_filter( array_map( 'trim', (array) $request->get_param( 'days_offered' ) ) ) );
		$days    = Event_Registration_Repository::normalize_days( $request->get_param( 'days' ), $offered );
		if ( $offered && ! $days ) {
			return $this->error( 'Elige al menos un día.', 400 );
		}
		$data['days'] = $days;

		$proof = $this->proof_file( $request );
		if ( $proof instanceof WP_REST_Response ) {
			return $proof;
		}

		if ( Event_Registration_Repository::exists( $email, $event ) ) {
			return $this->error(
				'Ese correo ya tiene un registro para este evento. Si necesitas cambiar algo, escríbenos por WhatsApp.',
				409,
				array( 'duplicate' => true )
			);
		}

		$post_id = Event_Registration_Repository::create( $data );
		if ( ! $post_id ) {
			return $this->error( 'No se pudo guardar el registro. Intenta de nuevo.', 500 );
		}

		if ( $proof ) {
			$attachment_id = Event_Registration_Repository::attach_proof( $post_id, 'payment_proof' );
			if ( is_wp_error( $attachment_id ) ) {
				wp_delete_post( $post_id, true );
				return $this->error( 'No se pudo guardar el comprobante de pago (' . $attachment_id->get_error_message() . '). Intenta con otro archivo.', 400 );
			}
		}

		Mailer::notify_event_registration( $post_id );

		return $this->ok(
			array(
				'registration_id' => $post_id,
				'message'         => $proof
					? '¡Registro recibido junto con tu comprobante de pago! Te enviamos una copia a tu correo y te contactaremos con los detalles del evento.'
					: '¡Registro recibido! Te enviamos una copia a tu correo y te contactaremos con los detalles del evento.',
			)
		);
	}

	/**
	 * The optional proof of payment: null when none was sent, the `$_FILES` entry when it is
	 * acceptable, or an error response.
	 *
	 * @return array<string, mixed>|WP_REST_Response|null
	 */
	private function proof_file( WP_REST_Request $request ) {
		$files = $request->get_file_params();
		$file  = $files['payment_proof'] ?? null;
		if ( ! is_array( $file ) || UPLOAD_ERR_NO_FILE === (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			return null;
		}

		$max   = Event_Registration_Repository::proof_max_bytes();
		$error = (int) $file['error'];
		if ( in_array( $error, array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true ) || (int) ( $file['size'] ?? 0 ) > $max ) {
			return $this->error( sprintf( 'El comprobante es demasiado grande (máximo %s).', size_format( $max ) ), 400 );
		}
		if ( UPLOAD_ERR_OK !== $error || empty( $file['tmp_name'] ) || ! is_uploaded_file( (string) $file['tmp_name'] ) ) {
			return $this->error( 'No se pudo subir el comprobante. Intenta de nuevo.', 400 );
		}

		$check = wp_check_filetype_and_ext( (string) $file['tmp_name'], (string) $file['name'], Event_Registration_Repository::PROOF_MIMES );
		if ( empty( $check['type'] ) ) {
			return $this->error( 'El comprobante debe ser una imagen (JPG, PNG, WebP, HEIC) o un PDF.', 400 );
		}

		return $file;
	}

	private function valid_date( string $date ): bool {
		$dt = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date );

		return $dt instanceof \DateTimeImmutable && $dt->format( 'Y-m-d' ) === $date;
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
