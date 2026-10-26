<?php
/**
 * Pure helpers of the colleagues' photo upload: Media Library titles, kinds, the mail body.
 */

declare( strict_types=1 );

namespace Pura\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pura\Core\Data\Photo_Batch_Repository;
use Pura\Core\Mailer;

final class PhotoUploadsTest extends TestCase {

	public function test_attachment_titles_name_the_person_the_place_and_the_kind(): void {
		$this->assertSame( 'Instructora Palito — Toluca — Foto 3', Photo_Batch_Repository::attachment_title( 'Instructora Palito', 'Toluca', 'photo', 3 ) );
		$this->assertSame( 'Instructora Palito — Toluca — Foto de perfil', Photo_Batch_Repository::attachment_title( 'Instructora Palito', 'Toluca', 'profile', 0 ) );
	}

	public function test_attachment_titles_skip_empty_parts_and_never_number_below_one(): void {
		$this->assertSame( 'Dudu — Foto 1', Photo_Batch_Repository::attachment_title( ' Dudu ', '', 'photo', 0 ) );
		$this->assertSame( 'Austin — Foto de perfil', Photo_Batch_Repository::attachment_title( '', 'Austin', 'profile', 9 ) );
	}

	public function test_only_profile_is_a_special_kind(): void {
		$this->assertSame( 'profile', Photo_Batch_Repository::normalize_kind( ' Profile ' ) );
		$this->assertSame( 'photo', Photo_Batch_Repository::normalize_kind( 'photo' ) );
		$this->assertSame( 'photo', Photo_Batch_Repository::normalize_kind( 'thumbnail' ) );
		$this->assertSame( 'photo', Photo_Batch_Repository::normalize_kind( '' ) );
	}

	public function test_mail_body_lists_who_where_how_many_and_the_admin_link(): void {
		$body = Mailer::build_photo_body(
			array(
				'name'             => 'Instructor Chino',
				'profesor_name'    => 'Instructor Chino',
				'sede_name'        => 'Guanajuato',
				'contact'          => '+52 473 000 0000',
				'note'             => "Roda de octubre.\nSalimos todos.",
				'file_count'       => 7,
				'profile_photo_id' => 12,
				'created_at'       => '2026-10-26 18:00:00',
				'admin_url'        => 'https://puracapoeira.com/wp-admin/post.php?post=40&action=edit',
			),
			'https://puracapoeira.com/',
			'2026-10-26 18:05:00'
		);

		$this->assertStringStartsWith( "Nuevas fotos de un profesor o instructor en https://puracapoeira.com/\n\n", $body );
		$this->assertStringContainsString( "Nombre: Instructor Chino\n", $body );
		$this->assertStringContainsString( "Sede: Guanajuato\n", $body );
		$this->assertStringContainsString( "Fotos: 7 (incluye foto de perfil)\n", $body );
		$this->assertStringContainsString( "Nota: Roda de octubre.\nSalimos todos.\n", $body );
		$this->assertStringEndsWith( "Ver las fotos y aplicarlas al perfil o a la sede: https://puracapoeira.com/wp-admin/post.php?post=40&action=edit\n", $body );
	}

	public function test_mail_body_omits_empty_rows_and_falls_back_to_the_given_time(): void {
		$body = Mailer::build_photo_body(
			array(
				'name'       => 'Laura',
				'file_count' => 1,
			),
			'https://puracapoeira.com/',
			'2026-10-26 18:05:00'
		);

		$this->assertStringNotContainsString( 'Sede:', $body );
		$this->assertStringNotContainsString( 'Nota:', $body );
		$this->assertStringContainsString( "Fotos: 1\n", $body );
		$this->assertStringContainsString( "Fecha de envío: 2026-10-26 18:05:00\n", $body );
	}
}
