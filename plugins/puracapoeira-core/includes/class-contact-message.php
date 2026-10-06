<?php
/**
 * Contact form payload validation. Pure PHP (no WordPress calls) so it is unit-testable.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core;

final class Contact_Message {

	/** @var array<string, int> Maximum length per field. */
	public const MAX_LENGTH = array(
		'nombre'   => 120,
		'ciudad'   => 120,
		'telefono' => 40,
		'email'    => 254,
		'mensaje'  => 3000,
	);

	/** Submissions younger than this (seconds since the form was rendered) are treated as bots. */
	public const MIN_AGE_SECONDS = 3;

	/**
	 * @param array<string, mixed> $input Raw request body.
	 * @param int|null             $now   Unix timestamp (seconds); defaults to time().
	 * @return array{ok: bool, data: array<string, string>, errors: string[]}
	 */
	public static function from_array( array $input, ?int $now = null ): array {
		$now    = $now ?? time();
		$errors = array();

		$honeypot = isset( $input['website'] ) ? trim( (string) self::scalar( $input['website'] ) ) : '';
		if ( '' !== $honeypot ) {
			$errors[] = 'spam';
		}

		if ( isset( $input['ts'] ) && is_numeric( $input['ts'] ) ) {
			$ts = (float) $input['ts'];
			if ( $ts > 1.0e11 ) { // Milliseconds from Date.now().
				$ts /= 1000;
			}
			if ( $ts > 0 && ( $now - $ts ) < self::MIN_AGE_SECONDS ) {
				$errors[] = 'too_fast';
			}
		}

		$data = array();
		foreach ( self::MAX_LENGTH as $field => $max ) {
			$raw            = isset( $input[ $field ] ) ? self::scalar( $input[ $field ] ) : '';
			$data[ $field ] = self::clean( $raw, $max, 'mensaje' === $field );
		}

		if ( '' === $data['nombre'] ) {
			$errors[] = 'nombre';
		}
		if ( '' === $data['mensaje'] ) {
			$errors[] = 'mensaje';
		}
		if ( '' !== $data['email'] && false === filter_var( $data['email'], FILTER_VALIDATE_EMAIL ) ) {
			$errors[] = 'email';
		}

		return array(
			'ok'     => array() === $errors,
			'data'   => $data,
			'errors' => $errors,
		);
	}

	/**
	 * Human-readable (Spanish) message for a validation failure.
	 *
	 * @param string[] $errors Error codes from from_array().
	 */
	public static function message( array $errors ): string {
		if ( in_array( 'spam', $errors, true ) || in_array( 'too_fast', $errors, true ) ) {
			return 'Solicitud rechazada.';
		}
		if ( in_array( 'nombre', $errors, true ) || in_array( 'mensaje', $errors, true ) ) {
			return 'Escribe tu nombre y un mensaje.';
		}
		if ( in_array( 'email', $errors, true ) ) {
			return 'El correo electrónico no es válido.';
		}

		return 'No se pudo procesar el formulario.';
	}

	/**
	 * @param mixed $value Any JSON value.
	 */
	private static function scalar( $value ): string {
		if ( is_string( $value ) ) {
			return $value;
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}

		return '';
	}

	/**
	 * Strip control characters, normalise whitespace and cap the length.
	 */
	private static function clean( string $value, int $max, bool $multiline ): string {
		$value = (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value );
		$value = $multiline
			? (string) preg_replace( "/[ \t]+/", ' ', (string) preg_replace( "/\r\n?/", "\n", $value ) )
			: (string) preg_replace( '/\s+/u', ' ', $value );
		$value = trim( $value );

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $max );
		}

		return substr( $value, 0, $max );
	}
}
