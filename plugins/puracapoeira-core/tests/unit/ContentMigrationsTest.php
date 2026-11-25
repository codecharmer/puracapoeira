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

	private const PATTERN = 'puracapoeira/page-privacidad';
	private const EARLIER = array( 'create_fotos_page', 'create_app_page', 'add_app_nav_link', 'create_editar_page' );

	// The privacy notice as it was first, with member accounts, and with attendance.
	private const FIRST_TEXT      = '<p>Avisos. No está vinculado a tu nombre.</p>';
	private const ACCOUNTS_TEXT   = '<!-- wp:paragraph {"className":"a--b"} --><p>Cuenta de miembro. "Pura Capoeira" → Más</p><!-- /wp:paragraph -->';
	private const ATTENDANCE_TEXT = '<!-- wp:paragraph {"className":"a--b"} --><p>Cuenta de miembro. Asistencia ("Registrar mi asistencia") → Más</p><!-- /wp:paragraph -->';

	/**
	 * Each refresh of the privacy page: its name, what the site has already run, the text on the
	 * page and the text of the pattern.
	 *
	 * @return array<string, array{string, string[], string, string}>
	 */
	public static function refreshes(): array {
		return array(
			'member accounts' => array(
				'refresh_privacy_page_member_accounts',
				self::EARLIER,
				self::FIRST_TEXT,
				self::ACCOUNTS_TEXT,
			),
			'attendance'      => array(
				'refresh_privacy_page_attendance',
				array_merge( self::EARLIER, array( 'refresh_privacy_page_member_accounts' ) ),
				self::ACCOUNTS_TEXT,
				self::ATTENDANCE_TEXT,
			),
		);
	}

	/**
	 * @param string[] $already_run Migrations recorded as done.
	 */
	private function a_site( array $already_run, string $page_text, string $pattern_text ): void {
		$GLOBALS['pura_test_options']      = array( Content_Migrations::OPTION => $already_run );
		$GLOBALS['pura_test_pages']        = array(
			'privacidad' => (object) array(
				'ID'           => 7,
				'post_content' => $page_text,
			),
		);
		$GLOBALS['pura_test_patterns']     = array( self::PATTERN => array( 'content' => $pattern_text ) );
		$GLOBALS['pura_test_updates']      = 0;
		$GLOBALS['pura_test_update_fails'] = false;
	}

	private function done( string $migration ): bool {
		return in_array( $migration, (array) get_option( Content_Migrations::OPTION, array() ), true );
	}

	private function page_text(): string {
		return $GLOBALS['pura_test_pages']['privacidad']->post_content;
	}

	/**
	 * @dataProvider refreshes
	 * @param string[] $already_run Migrations recorded as done.
	 */
	public function test_the_page_takes_the_pattern_text_once( string $migration, array $already_run, string $old_text, string $new_text ): void {
		$this->a_site( $already_run, $old_text, $new_text );

		Content_Migrations::run();

		// Backslashes and quotes of the block markup arrive as they are in the pattern.
		$this->assertSame( $new_text, $this->page_text() );
		$this->assertTrue( $this->done( $migration ) );

		Content_Migrations::run();
		$this->assertSame( 1, $GLOBALS['pura_test_updates'] );
	}

	/**
	 * @dataProvider refreshes
	 * @param string[] $already_run Migrations recorded as done.
	 */
	public function test_it_waits_while_an_earlier_copy_of_the_pattern_is_read( string $migration, array $already_run, string $old_text, string $new_text ): void {
		$this->a_site( $already_run, $old_text, $old_text );

		Content_Migrations::run();
		$this->assertSame( $old_text, $this->page_text() );
		$this->assertSame( 0, $GLOBALS['pura_test_updates'] );
		$this->assertFalse( $this->done( $migration ) );

		$GLOBALS['pura_test_patterns'][ self::PATTERN ]['content'] = $new_text;
		Content_Migrations::run();
		$this->assertSame( $new_text, $this->page_text() );
		$this->assertTrue( $this->done( $migration ) );
	}

	/**
	 * @dataProvider refreshes
	 * @param string[] $already_run Migrations recorded as done.
	 */
	public function test_it_waits_for_the_pattern_to_be_registered( string $migration, array $already_run, string $old_text, string $new_text ): void {
		$this->a_site( $already_run, $old_text, $new_text );
		$GLOBALS['pura_test_patterns'] = array();

		Content_Migrations::run();

		$this->assertSame( 0, $GLOBALS['pura_test_updates'] );
		$this->assertFalse( $this->done( $migration ) );
	}

	/**
	 * @dataProvider refreshes
	 * @param string[] $already_run Migrations recorded as done.
	 */
	public function test_a_site_without_the_page_has_nothing_to_refresh( string $migration, array $already_run, string $old_text, string $new_text ): void {
		$this->a_site( $already_run, $old_text, $new_text );
		$GLOBALS['pura_test_pages'] = array();

		Content_Migrations::run();

		$this->assertSame( 0, $GLOBALS['pura_test_updates'] );
		$this->assertTrue( $this->done( $migration ) );
	}

	/**
	 * @dataProvider refreshes
	 * @param string[] $already_run Migrations recorded as done.
	 */
	public function test_a_save_that_fails_is_tried_again( string $migration, array $already_run, string $old_text, string $new_text ): void {
		$this->a_site( $already_run, $old_text, $new_text );

		$GLOBALS['pura_test_update_fails'] = true;
		Content_Migrations::run();
		$this->assertSame( $old_text, $this->page_text() );
		$this->assertFalse( $this->done( $migration ) );

		$GLOBALS['pura_test_update_fails'] = false;
		Content_Migrations::run();
		$this->assertSame( $new_text, $this->page_text() );
		$this->assertTrue( $this->done( $migration ) );
	}

	public function test_a_site_that_ran_neither_refresh_ends_with_the_current_text(): void {
		$this->a_site( self::EARLIER, self::FIRST_TEXT, self::ATTENDANCE_TEXT );

		Content_Migrations::run();

		$this->assertSame( self::ATTENDANCE_TEXT, $this->page_text() );
		$this->assertTrue( $this->done( 'refresh_privacy_page_member_accounts' ) );
		$this->assertTrue( $this->done( 'refresh_privacy_page_attendance' ) );
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
