<?php
/**
 * Photo rules that need no WordPress: gallery order, clean ids, when a photo is deleted, the joint notice.
 */

declare( strict_types=1 );

namespace Pura\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pura\Core\Editor\Editor_Fields;
use Pura\Core\Editor\Editor_Photos;
use Pura\Core\Mailer;

final class EditorPhotosTest extends TestCase {

	public function test_clean_ids(): void {
		$this->assertSame( array( 5, 3, 9 ), Editor_Photos::clean_ids( array( '5', 3, 5, 0, -2, 'x', 9.0, null ) ) );
		$this->assertSame( array(), Editor_Photos::clean_ids( 'nope' ) );
	}

	/**
	 * @dataProvider orders
	 * @param mixed $requested Submitted list.
	 */
	public function test_gallery_order( $requested, bool $expected ): void {
		$this->assertSame( $expected, Editor_Photos::is_valid_order( array( 10, 11, 12 ), $requested ) );
	}

	/**
	 * @return array<string, array{mixed, bool}>
	 */
	public static function orders(): array {
		return array(
			'same order'             => array( array( 10, 11, 12 ), true ),
			'reordered'              => array( array( 12, 10, 11 ), true ),
			'one removed'            => array( array( 12, 10 ), true ),
			'all removed'            => array( array(), true ),
			'a photo from elsewhere' => array( array( 10, 99 ), false ),
			'repeated'               => array( array( 10, 10 ), false ),
			'ids as text'            => array( array( '10', '11' ), false ),
			'keyed list'             => array( array( 'a' => 10 ), false ),
			'not a list'             => array( '10,11', false ),
			'zero'                   => array( array( 0 ), false ),
		);
	}

	public function test_unlinked_photos_wait_thirty_days(): void {
		$now = 2_000_000_000;
		$this->assertFalse( Editor_Photos::is_stale( $now - 29 * 86400, $now ) );
		$this->assertTrue( Editor_Photos::is_stale( $now - 30 * 86400, $now ) );
		$this->assertFalse( Editor_Photos::is_stale( 0, $now ), 'a photo with no date is never deleted' );
	}

	public function test_photo_fields_are_not_text_fields(): void {
		foreach ( array( Editor_Fields::TYPE_SEDE, Editor_Fields::TYPE_PROFESOR ) as $type ) {
			$this->assertSame( array(), array_intersect_key( Editor_Fields::photo_definitions( $type ), Editor_Fields::definitions( $type ) ) );
			$check = Editor_Fields::validate( $type, array( 'image' => 7 ) );
			$this->assertSame( array( 'image' => 'not_editable' ), $check['errors'], 'a text save cannot point at a photo' );
		}
		$this->assertSame( array( 'image', 'gallery' ), array_keys( Editor_Fields::photo_definitions( Editor_Fields::TYPE_SEDE ) ) );
		$this->assertSame( array( 'image' ), array_keys( Editor_Fields::photo_definitions( Editor_Fields::TYPE_PROFESOR ) ) );
		$this->assertSame( array(), Editor_Fields::photo_definitions( 'evento' ) );
	}

	public function test_joint_notice_body(): void {
		$body = Mailer::build_photo_changes_body(
			array(
				'title'   => 'Toluca Centro',
				'url'     => 'https://example.com/sedes/toluca/',
				'actors'  => array( 'prof@example.com' ),
				'changes' => 3,
				'image'   => 'https://example.com/a.jpg',
				'gallery' => array( 'https://example.com/b.jpg', 'https://example.com/c.jpg' ),
			),
			'https://example.com/',
			'https://example.com/wp-admin/edit.php?post_type=pura_change'
		);
		$this->assertStringContainsString( 'Ficha:   Toluca Centro', $body );
		$this->assertStringContainsString( 'Quién:   prof@example.com', $body );
		$this->assertStringContainsString( 'Cambios: 3', $body );
		$this->assertStringContainsString( 'Galería ahora (2):', $body );
		$this->assertStringContainsString( '  https://example.com/c.jpg', $body );
		$this->assertStringContainsString( 'edit.php?post_type=pura_change', $body );

		$profile = Mailer::build_photo_changes_body(
			array(
				'title'   => 'Mestre X',
				'image'   => '',
				'gallery' => null,
			),
			'https://example.com/',
			'https://example.com/wp-admin/'
		);
		$this->assertStringContainsString( '(sin foto)', $profile );
		$this->assertStringNotContainsString( 'Galería', $profile );
	}
}
