<?php
/**
 * Content migrations: a page that holds a copy of its pattern takes the pattern's current text, once.
 * WordPress is replaced by the stand-ins at the end of this file.
 */

declare( strict_types=1 );

namespace Pura\Core\Tests\Unit {

use PHPUnit\Framework\TestCase;
use Pura\Core\Content_Migrations;

final class ContentMigrationsTest extends TestCase {

	private const MIGRATION = 'refresh_privacy_page_member_accounts';
	private const PATTERN   = 'puracapoeira/page-privacidad';
	private const OLD_TEXT  = '<p>Avisos. No está vinculado a tu nombre.</p>';
	private const NEW_TEXT  = '<!-- wp:paragraph {"className":"a--b"} --><p>Cuenta de miembro. "Pura Capoeira" → Más</p><!-- /wp:paragraph -->';

	protected function setUp(): void {
		// Everything that ran before this change is done; only the privacy page is pending.
		$GLOBALS['pura_test_options']      = array(
			Content_Migrations::OPTION => array( 'create_fotos_page', 'create_app_page', 'add_app_nav_link', 'create_editar_page' ),
		);
		$GLOBALS['pura_test_pages']        = array(
			'privacidad' => (object) array(
				'ID'           => 7,
				'post_content' => self::OLD_TEXT,
			),
		);
		$GLOBALS['pura_test_patterns']     = array( self::PATTERN => array( 'content' => self::NEW_TEXT ) );
		$GLOBALS['pura_test_updates']      = 0;
		$GLOBALS['pura_test_update_fails'] = false;
	}

	private function done(): bool {
		return in_array( self::MIGRATION, (array) get_option( Content_Migrations::OPTION, array() ), true );
	}

	public function test_the_page_takes_the_pattern_text_once(): void {
		Content_Migrations::run();

		// Backslashes and quotes of the block markup arrive as they are in the pattern.
		$this->assertSame( self::NEW_TEXT, $GLOBALS['pura_test_pages']['privacidad']->post_content );
		$this->assertTrue( $this->done() );

		Content_Migrations::run();
		$this->assertSame( 1, $GLOBALS['pura_test_updates'] );
	}

	public function test_it_waits_while_an_earlier_copy_of_the_pattern_is_read(): void {
		$GLOBALS['pura_test_patterns'][ self::PATTERN ]['content'] = self::OLD_TEXT;

		Content_Migrations::run();
		$this->assertSame( self::OLD_TEXT, $GLOBALS['pura_test_pages']['privacidad']->post_content );
		$this->assertFalse( $this->done() );

		$GLOBALS['pura_test_patterns'][ self::PATTERN ]['content'] = self::NEW_TEXT;
		Content_Migrations::run();
		$this->assertSame( self::NEW_TEXT, $GLOBALS['pura_test_pages']['privacidad']->post_content );
		$this->assertTrue( $this->done() );
	}

	public function test_it_waits_for_the_pattern_to_be_registered(): void {
		$GLOBALS['pura_test_patterns'] = array();

		Content_Migrations::run();

		$this->assertSame( 0, $GLOBALS['pura_test_updates'] );
		$this->assertFalse( $this->done() );
	}

	public function test_a_site_without_the_page_has_nothing_to_refresh(): void {
		$GLOBALS['pura_test_pages'] = array();

		Content_Migrations::run();

		$this->assertSame( 0, $GLOBALS['pura_test_updates'] );
		$this->assertTrue( $this->done() );
	}

	public function test_a_save_that_fails_is_tried_again(): void {
		$GLOBALS['pura_test_update_fails'] = true;
		Content_Migrations::run();
		$this->assertSame( self::OLD_TEXT, $GLOBALS['pura_test_pages']['privacidad']->post_content );
		$this->assertFalse( $this->done() );

		$GLOBALS['pura_test_update_fails'] = false;
		Content_Migrations::run();
		$this->assertSame( self::NEW_TEXT, $GLOBALS['pura_test_pages']['privacidad']->post_content );
		$this->assertTrue( $this->done() );
	}
}
}

// Stand-ins for the WordPress functions the migration calls. Blocks are not parsed here: the text goes through as it is.
namespace Pura\Core {

function wp_installing(): bool {
	return false;
}

function get_page_by_path( string $slug ) {
	return $GLOBALS['pura_test_pages'][ $slug ] ?? null;
}

/** @return array<int, array{raw:string}> */
function parse_blocks( string $content ): array {
	return array( array( 'raw' => $content ) );
}

/**
 * @param array<int, array{raw:string}> $blocks Blocks.
 * @return array<int, array{raw:string}>
 */
function resolve_pattern_blocks( array $blocks ): array {
	return $blocks;
}

/** @param array<int, array{raw:string}> $blocks Blocks. */
function serialize_blocks( array $blocks ): string {
	return $blocks[0]['raw'];
}

function wp_slash( string $value ): string {
	return addslashes( $value );
}

/**
 * Like WordPress, it takes slashed data and stores it unslashed.
 *
 * @param array{ID:int,post_content:string} $postarr Post fields.
 * @return int|\WP_Error
 */
function wp_update_post( array $postarr, bool $wp_error = false ) {
	if ( $GLOBALS['pura_test_update_fails'] ) {
		return new \WP_Error();
	}
	foreach ( $GLOBALS['pura_test_pages'] as $page ) {
		if ( $page->ID === $postarr['ID'] ) {
			$page->post_content = stripslashes( $postarr['post_content'] );
			++$GLOBALS['pura_test_updates'];

			return $page->ID;
		}
	}

	return 0;
}

function is_wp_error( $thing ): bool {
	return $thing instanceof \WP_Error;
}

}

namespace {

if ( ! class_exists( 'WP_Error' ) ) {
	// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound, Generic.Classes.OpeningBraceSameLine.ContentAfterBrace
	final class WP_Error {}
}

if ( ! class_exists( 'WP_Block_Patterns_Registry' ) ) {
	// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
	final class WP_Block_Patterns_Registry {

		public static function get_instance(): self {
			return new self();
		}

		/** @return array{content:string}|null */
		public function get_registered( string $name ): ?array {
			return $GLOBALS['pura_test_patterns'][ $name ] ?? null;
		}
	}
}
}
