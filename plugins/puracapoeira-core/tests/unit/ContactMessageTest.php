<?php
/**
 * Contact_Message validation.
 */

declare( strict_types=1 );

namespace Pura\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pura\Core\Contact_Message;

final class ContactMessageTest extends TestCase {

	private const NOW = 1_800_000_000;

	public function test_valid_payload_is_accepted_and_normalised(): void {
		$result = Contact_Message::from_array(
			array(
				'nombre'   => "  Ana   López\t",
				'ciudad'   => 'Cuernavaca',
				'telefono' => '+52 777 123 4567',
				'email'    => 'ana@example.com',
				'mensaje'  => "Hola,\r\n\r\nquiero  información.  ",
				'website'  => '',
				'ts'       => ( self::NOW - 30 ) * 1000,
			),
			self::NOW
		);

		$this->assertTrue( $result['ok'] );
		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( 'Ana López', $result['data']['nombre'] );
		$this->assertSame( "Hola,\n\nquiero información.", $result['data']['mensaje'] );
		$this->assertSame( 'ana@example.com', $result['data']['email'] );
	}

	public function test_missing_required_fields(): void {
		$result = Contact_Message::from_array( array( 'ciudad' => 'Toluca' ), self::NOW );

		$this->assertFalse( $result['ok'] );
		$this->assertContains( 'nombre', $result['errors'] );
		$this->assertContains( 'mensaje', $result['errors'] );
		$this->assertSame( 'Escribe tu nombre y un mensaje.', Contact_Message::message( $result['errors'] ) );
	}

	public function test_honeypot_rejects(): void {
		$result = Contact_Message::from_array(
			array(
				'nombre'  => 'Bot',
				'mensaje' => 'Hi',
				'website' => 'http://spam.example',
			),
			self::NOW
		);

		$this->assertFalse( $result['ok'] );
		$this->assertContains( 'spam', $result['errors'] );
		$this->assertSame( 'Solicitud rechazada.', Contact_Message::message( $result['errors'] ) );
	}

	public function test_too_fast_submission_rejects_in_seconds_and_milliseconds(): void {
		$seconds = Contact_Message::from_array(
			array(
				'nombre'  => 'A',
				'mensaje' => 'B',
				'ts'      => self::NOW - 1,
			),
			self::NOW
		);
		$millis  = Contact_Message::from_array(
			array(
				'nombre'  => 'A',
				'mensaje' => 'B',
				'ts'      => ( self::NOW - 1 ) * 1000,
			),
			self::NOW
		);
		$old     = Contact_Message::from_array(
			array(
				'nombre'  => 'A',
				'mensaje' => 'B',
				'ts'      => ( self::NOW - 10 ) * 1000,
			),
			self::NOW
		);

		$this->assertContains( 'too_fast', $seconds['errors'] );
		$this->assertContains( 'too_fast', $millis['errors'] );
		$this->assertTrue( $old['ok'] );
	}

	public function test_invalid_email_rejects_but_empty_email_is_fine(): void {
		$bad = Contact_Message::from_array(
			array(
				'nombre'  => 'A',
				'mensaje' => 'B',
				'email'   => 'not-an-email',
			),
			self::NOW
		);
		$none = Contact_Message::from_array(
			array(
				'nombre'  => 'A',
				'mensaje' => 'B',
			),
			self::NOW
		);

		$this->assertContains( 'email', $bad['errors'] );
		$this->assertTrue( $none['ok'] );
	}

	public function test_lengths_are_capped_and_control_characters_removed(): void {
		$result = Contact_Message::from_array(
			array(
				'nombre'  => str_repeat( 'x', 500 ) . "\x00\x07",
				'mensaje' => str_repeat( 'y', 5000 ),
			),
			self::NOW
		);

		$this->assertTrue( $result['ok'] );
		$this->assertSame( 120, mb_strlen( $result['data']['nombre'] ) );
		$this->assertSame( 3000, mb_strlen( $result['data']['mensaje'] ) );
		$this->assertStringNotContainsString( "\x07", $result['data']['nombre'] );
	}

	public function test_non_scalar_values_are_ignored(): void {
		$result = Contact_Message::from_array(
			array(
				'nombre'  => array( 'evil' ),
				'mensaje' => 'ok',
			),
			self::NOW
		);

		$this->assertFalse( $result['ok'] );
		$this->assertContains( 'nombre', $result['errors'] );
	}
}
