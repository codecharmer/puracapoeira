<?php
/**
 * Cities with more than one sede.
 *
 * A city such as Toluca can have several teaching groups, each its own sede with its own
 * schedule, prices and professor. Lists keep those sedes together and call each one by its
 * own name; a city with a single sede keeps being called by the city, as before.
 *
 * Pure: works on the arrays returned by Sede_Post_Type::get_data(), no WordPress calls.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Data;

defined( 'ABSPATH' ) || exit;

final class City_Groups {

	/**
	 * What makes two sedes "the same city": city and country, ignoring case, accents and spacing.
	 * Empty for a sede with no usable city, which is never grouped.
	 *
	 * @param array<string, mixed> $sede Sede data.
	 */
	public static function key( array $sede ): string {
		$city = self::fold( (string) ( $sede['city'] ?? '' ) );
		if ( '' === $city || false !== strpos( $city, 'por confirmar' ) ) {
			return '';
		}

		return $city . '|' . self::fold( (string) ( $sede['country'] ?? '' ) );
	}

	/**
	 * Sedes in their display order, except that sedes of the same city follow the first one of
	 * that city.
	 *
	 * @param array<int, array<string, mixed>> $sedes Sede data in display order.
	 * @return array<int, array{city: string, shared: bool, sedes: array<int, array<string, mixed>>}>
	 */
	public static function group( array $sedes ): array {
		$groups = array();
		$index  = array();
		foreach ( $sedes as $sede ) {
			$key = self::key( $sede );
			if ( '' !== $key && isset( $index[ $key ] ) ) {
				$groups[ $index[ $key ] ]['sedes'][] = $sede;
				$groups[ $index[ $key ] ]['shared']  = true;
				continue;
			}
			if ( '' !== $key ) {
				$index[ $key ] = count( $groups );
			}
			$groups[] = array(
				'city'   => (string) ( $sede['city'] ?? '' ),
				'shared' => false,
				'sedes'  => array( $sede ),
			);
		}

		return $groups;
	}

	/**
	 * The other sedes of a sede's city.
	 *
	 * @param array<string, mixed>             $sede  Sede data.
	 * @param array<int, array<string, mixed>> $sedes All sedes.
	 * @return array<int, array<string, mixed>>
	 */
	public static function sisters( array $sede, array $sedes ): array {
		$key = self::key( $sede );
		if ( '' === $key ) {
			return array();
		}

		return array_values(
			array_filter(
				$sedes,
				static fn ( array $other ): bool => ( $other['id'] ?? null ) !== ( $sede['id'] ?? null ) && self::key( $other ) === $key
			)
		);
	}

	private static function fold( string $text ): string {
		$text = strtr(
			mb_strtolower( trim( $text ) ),
			array(
				'á' => 'a',
				'é' => 'e',
				'í' => 'i',
				'ó' => 'o',
				'ú' => 'u',
				'ü' => 'u',
				'ñ' => 'n',
				'ã' => 'a',
				'â' => 'a',
				'ê' => 'e',
				'ô' => 'o',
				'õ' => 'o',
				'ç' => 'c',
			)
		);

		return (string) preg_replace( '/\s+/', ' ', $text );
	}
}
