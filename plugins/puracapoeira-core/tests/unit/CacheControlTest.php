<?php
/**
 * Which REST reads get the short cache life.
 */

declare( strict_types=1 );

namespace Pura\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pura\Core\Cache_Control;

final class CacheControlTest extends TestCase {

	/**
	 * @dataProvider routes
	 */
	public function test_route( string $route, string $method, bool $expected ): void {
		$this->assertSame( $expected, Cache_Control::is_short_lived_route( $route, $method ) );
	}

	/**
	 * @return array<string, array{string, string, bool}>
	 */
	public static function routes(): array {
		return array(
			'sede collection'         => array( '/wp/v2/pura_sede', 'GET', true ),
			'one sede'                => array( '/wp/v2/pura_sede/8', 'GET', true ),
			'trailing slash'          => array( '/wp/v2/pura_sede/8/', 'GET', true ),
			'profesor collection'     => array( '/wp/v2/pura_profesor', 'GET', true ),
			'head request'            => array( '/wp/v2/pura_profesor/12', 'HEAD', true ),
			'lower-case method'       => array( '/wp/v2/pura_sede', 'get', true ),
			'a write is never cached' => array( '/wp/v2/pura_sede/8', 'POST', false ),
			'events keep the default' => array( '/wp/v2/pura_evento', 'GET', false ),
			'similar name'            => array( '/wp/v2/pura_sedes', 'GET', false ),
			'revisions sub-route'     => array( '/wp/v2/pura_sede/8/revisions', 'GET', false ),
			'own namespace'           => array( '/pura/v1/account/me', 'GET', false ),
			'not a post id'           => array( '/wp/v2/pura_sede/abc', 'GET', false ),
		);
	}
}
