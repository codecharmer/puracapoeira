<?php
/**
 * What changed between two sets of editor values, and how to say it to a person.
 *
 * Pure: no WordPress calls.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Editor;

defined( 'ABSPATH' ) || exit;

final class Change_Diff {

	private const LABELS = array(
		'excerpt'          => 'Resumen',
		'address'          => 'Dirección',
		'responsible'      => 'Responsable',
		'whatsapp'         => 'WhatsApp (enlace)',
		'whatsapp_display' => 'WhatsApp (número visible)',
		'instagram'        => 'Instagram (enlace)',
		'instagram_handle' => 'Instagram (usuario)',
		'facebook'         => 'Facebook',
		'website'          => 'Sitio web',
		'youtube'          => 'YouTube',
		'schedule'         => 'Horarios',
		'pricing'          => 'Precios',
		'subtitle'         => 'Presentación',
		'caption'          => 'Pie de foto',
		'bio'              => 'Trayectoria',
		'image'            => 'Foto principal',
		'gallery'          => 'Galería',
	);

	public static function label( string $field ): string {
		return self::LABELS[ $field ] ?? $field;
	}

	/**
	 * The fields of `$after` whose value differs from `$before`.
	 *
	 * @param array<string, mixed> $before Current values.
	 * @param array<string, mixed> $after  Submitted values.
	 * @return string[]
	 */
	public static function changed( array $before, array $after ): array {
		$changed = array();
		foreach ( $after as $field => $value ) {
			if ( ! array_key_exists( $field, $before ) || self::canonical( $before[ $field ] ) !== self::canonical( $value ) ) {
				$changed[] = (string) $field;
			}
		}

		return $changed;
	}

	/**
	 * Plain-text lines for a notice: one block per changed field, before then after.
	 *
	 * @param array<string, mixed> $before Values before the change (changed fields only).
	 * @param array<string, mixed> $after  Values after it.
	 * @return string[]
	 */
	public static function describe( array $before, array $after ): array {
		$lines = array();
		foreach ( $after as $field => $value ) {
			$lines[] = self::label( (string) $field ) . ':';
			$lines[] = '  Antes:   ' . self::show( $before[ $field ] ?? '' );
			$lines[] = '  Ahora:   ' . self::show( $value );
			$lines[] = '';
		}

		return $lines;
	}

	/**
	 * @param mixed $value Field value.
	 */
	public static function show( $value ): string {
		if ( is_array( $value ) ) {
			if ( ! $value ) {
				return '(vacío)';
			}
			$rows = array();
			foreach ( $value as $row ) {
				$rows[] = is_array( $row ) ? implode( ' | ', array_map( 'strval', $row ) ) : (string) $row;
			}

			return implode( "\n           ", $rows );
		}
		$text = trim( (string) $value );

		return '' === $text ? '(vacío)' : str_replace( "\n", "\n           ", $text );
	}

	/**
	 * @param mixed $value Field value.
	 */
	private static function canonical( $value ): string {
		return is_array( $value ) ? (string) json_encode( array_values( $value ) ) : (string) $value; // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- pure class, no WordPress.
	}
}
