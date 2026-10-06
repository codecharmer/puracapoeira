<?php
/**
 * Settings merge and lookup.
 */

declare( strict_types=1 );

namespace Pura\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pura\Core\Settings;

final class SettingsTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['pura_test_options'] = array();
	}

	public function test_all_merges_stored_values_over_defaults(): void {
		update_option( Settings::OPTION, array( 'motto' => 'Axé' ) );

		$all = Settings::all();

		$this->assertSame( 'Axé', $all['motto'] );
		$this->assertSame( Settings::defaults()['whatsapp_number'], $all['whatsapp_number'] );
		$this->assertSame( array_keys( Settings::defaults() ), array_keys( $all ) );
	}

	public function test_get_returns_fallback_for_unknown_key(): void {
		$this->assertSame( 'x', Settings::get( 'does_not_exist', 'x' ) );
		$this->assertSame( 'Capoeira · Cultura · Comunidade', Settings::get( 'motto' ) );
	}

	public function test_update_merges_partially(): void {
		Settings::update( array( 'motto' => 'A' ) );
		Settings::update( array( 'facebook_label' => 'B' ) );

		$this->assertSame( 'A', Settings::get( 'motto' ) );
		$this->assertSame( 'B', Settings::get( 'facebook_label' ) );
		$this->assertSame( 'option', Settings::source( 'motto' ) );
		$this->assertSame( 'default', Settings::source( 'tagline' ) );
	}

	public function test_constant_pins_a_value(): void {
		define( 'PURA_CONTACT_FROM_NAME', 'Pinned' );
		Settings::update( array( 'contact_from_name' => 'Stored' ) );

		$this->assertSame( 'Pinned', Settings::get( 'contact_from_name' ) );
		$this->assertSame( 'constant', Settings::source( 'contact_from_name' ) );
	}

	public function test_whatsapp_url_uses_digits_only(): void {
		Settings::update( array( 'whatsapp_number' => '+1 (805) 638-5603' ) );

		$this->assertSame( 'https://wa.me/18056385603', Settings::whatsapp_url() );
		$this->assertSame( 'https://wa.me/18056385603?text=Hola%20roda', Settings::whatsapp_url( 'Hola roda' ) );
	}
}
