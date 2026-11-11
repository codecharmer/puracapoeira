<?php
/**
 * Sedes that share a city are kept together.
 */

declare( strict_types=1 );

namespace Pura\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pura\Core\Data\City_Groups;

final class CityGroupsTest extends TestCase {

	/**
	 * @return array<string, mixed>
	 */
	private static function sede( int $id, string $city, string $country = 'México' ): array {
		return array(
			'id'      => $id,
			'city'    => $city,
			'country' => $country,
		);
	}

	public function test_sedes_of_one_city_follow_the_first(): void {
		$groups = City_Groups::group(
			array(
				self::sede( 1, 'Cuernavaca' ),
				self::sede( 2, 'Toluca' ),
				self::sede( 3, 'Austin', 'Estados Unidos' ),
				self::sede( 4, ' toluca ' ),
				self::sede( 5, 'Tolúca' ),
			)
		);
		$this->assertSame( array( array( 1 ), array( 2, 4, 5 ), array( 3 ) ), array_map( static fn ( array $g ): array => array_column( $g['sedes'], 'id' ), $groups ) );
		$this->assertSame( array( false, true, false ), array_column( $groups, 'shared' ) );
		$this->assertSame( 'Toluca', $groups[1]['city'], 'the group is named as the first sede writes the city' );
	}

	public function test_same_name_in_another_country_is_another_city(): void {
		$groups = City_Groups::group( array( self::sede( 1, 'Guanajuato' ), self::sede( 2, 'Guanajuato', 'Otro' ) ) );
		$this->assertCount( 2, $groups );
	}

	public function test_sedes_without_a_city_are_never_grouped(): void {
		$groups = City_Groups::group( array( self::sede( 1, '' ), self::sede( 2, '' ), self::sede( 3, 'Ciudad por confirmar' ), self::sede( 4, 'Ciudad por confirmar' ) ) );
		$this->assertCount( 4, $groups );
		$this->assertSame( array(), City_Groups::sisters( self::sede( 1, '' ), array( self::sede( 2, '' ) ) ) );
	}

	public function test_sisters(): void {
		$all = array( self::sede( 1, 'Toluca' ), self::sede( 2, 'Toluca' ), self::sede( 3, 'Cuernavaca' ), self::sede( 4, 'TOLUCA' ) );
		$this->assertSame( array( 2, 4 ), array_column( City_Groups::sisters( $all[0], $all ), 'id' ) );
		$this->assertSame( array(), City_Groups::sisters( $all[2], $all ) );
	}
}
