<?php
/**
 * Contact-form mail via wp_mail. Plain text, Spanish.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core;

defined( 'ABSPATH' ) || exit;

final class Mailer {

	/** @var string|null Last wp_mail error message. */
	private static ?string $last_error = null;

	/**
	 * @return string[]
	 */
	public static function recipients(): array {
		$raw = (string) Settings::get( 'contact_to_emails', '' );

		return array_values( array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', $raw ) ) ), 'is_email' ) );
	}

	/**
	 * Send a validated contact message (see Contact_Message::from_array()) to the recipients.
	 *
	 * @param array<string, string> $data nombre, ciudad, telefono, email, mensaje.
	 */
	public static function send_contact( array $data ): bool {
		$recipients = self::recipients();
		if ( ! $recipients ) {
			self::$last_error = 'No hay destinatarios configurados.';
			return false;
		}

		$name    = trim( (string) ( $data['nombre'] ?? '' ) );
		$subject = 'Pura Capoeira — Mensaje de ' . $name;
		if ( '' !== trim( (string) ( $data['ciudad'] ?? '' ) ) ) {
			$subject .= ' (' . trim( (string) $data['ciudad'] ) . ')';
		}

		$body    = self::build_contact_body( $data, home_url( '/' ), wp_date( 'Y-m-d H:i:s' ) );
		$headers = self::headers();

		$email = sanitize_email( (string) ( $data['email'] ?? '' ) );
		if ( is_email( $email ) ) {
			$headers[] = 'Reply-To: ' . str_replace( array( "\r", "\n" ), '', $name ) . ' <' . $email . '>';
		}

		return self::send( $recipients, $subject, $body, $headers );
	}

	/**
	 * Plain-text body. Pure: no WordPress calls, so it is unit-tested.
	 *
	 * @param array<string, string> $data     Contact fields.
	 * @param string                $site_url Site URL for the first line.
	 * @param string                $when     Formatted timestamp.
	 */
	public static function build_contact_body( array $data, string $site_url, string $when ): string {
		$lines = array( 'Nuevo mensaje del formulario de contacto de ' . $site_url, '' );

		$rows = array(
			'Nombre'              => (string) ( $data['nombre'] ?? '' ),
			'Ciudad'              => (string) ( $data['ciudad'] ?? '' ),
			'Teléfono o WhatsApp' => (string) ( $data['telefono'] ?? '' ),
			'Correo'              => (string) ( $data['email'] ?? '' ),
			'Fecha'               => $when,
		);

		foreach ( $rows as $label => $value ) {
			if ( '' === trim( $value ) ) {
				continue;
			}
			$lines[] = $label . ': ' . $value;
		}

		$lines[] = '';
		$lines[] = 'Mensaje:';
		$lines[] = (string) ( $data['mensaje'] ?? '' );

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * @return array{ok:bool,sent_count:int,total:int,message:string}
	 */
	public static function send_test(): array {
		$recipients = self::recipients();
		if ( ! $recipients ) {
			return array(
				'ok'         => false,
				'sent_count' => 0,
				'total'      => 0,
				'message'    => 'No hay destinatarios configurados.',
			);
		}

		$subject = 'Prueba del formulario de contacto — Pura Capoeira';
		$body    = 'Este es un correo de prueba enviado desde ' . home_url( '/' ) . ' el ' . wp_date( 'Y-m-d H:i:s' ) . ".\n\nSi lo recibes, los mensajes del formulario de contacto llegarán a esta dirección.";
		$ok      = self::send( $recipients, $subject, $body, self::headers() );

		return array(
			'ok'         => $ok,
			'sent_count' => $ok ? count( $recipients ) : 0,
			'total'      => count( $recipients ),
			'message'    => $ok ? 'OK' : (string) ( self::$last_error ?? 'wp_mail devolvió false' ),
		);
	}

	public static function last_error(): ?string {
		return self::$last_error;
	}

	/**
	 * @return string[]
	 */
	private static function headers(): array {
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		$from_email = sanitize_email( (string) Settings::get( 'contact_from_email', '' ) );
		$from_name  = (string) Settings::get( 'contact_from_name', 'Pura Capoeira' );
		if ( is_email( $from_email ) ) {
			$headers[] = sprintf( 'From: %s <%s>', str_replace( array( "\r", "\n" ), '', $from_name ), $from_email );
		}

		return $headers;
	}

	/**
	 * @param string[] $to      Recipients.
	 * @param string[] $headers Headers.
	 */
	private static function send( array $to, string $subject, string $body, array $headers ): bool {
		self::$last_error = null;
		$capture          = static function ( \WP_Error $error ): void {
			self::$last_error = $error->get_error_message();
		};

		add_action( 'wp_mail_failed', $capture );
		$ok = wp_mail( $to, $subject, $body, $headers );
		remove_action( 'wp_mail_failed', $capture );

		if ( ! $ok ) {
			error_log( '[pura] wp_mail failed: ' . ( self::$last_error ?? 'unknown' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		return $ok;
	}
}
