<?php
/**
 * Mailer::build_contact_body().
 */

declare( strict_types=1 );

namespace Pura\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pura\Core\Mailer;

final class MailerBodyTest extends TestCase {

	public function test_body_lists_filled_fields_and_message(): void {
		$body = Mailer::build_contact_body(
			array(
				'nombre'   => 'Ana',
				'ciudad'   => '',
				'telefono' => '777 123 4567',
				'email'    => 'ana@example.com',
				'mensaje'  => "Hola\nQuiero entrenar.",
			),
			'https://puracapoeira.com/',
			'2026-10-05 12:00:00'
		);

		$this->assertStringStartsWith( 'Nuevo mensaje del formulario de contacto de https://puracapoeira.com/', $body );
		$this->assertStringContainsString( "Nombre: Ana\n", $body );
		$this->assertStringContainsString( "Teléfono o WhatsApp: 777 123 4567\n", $body );
		$this->assertStringContainsString( "Correo: ana@example.com\n", $body );
		$this->assertStringNotContainsString( 'Ciudad:', $body );
		$this->assertStringEndsWith( "Mensaje:\nHola\nQuiero entrenar.\n", $body );
	}
}
