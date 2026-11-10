<?php
/**
 * The editor's field rules: what a professor may change and what a valid value is.
 */

declare( strict_types=1 );

namespace Pura\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pura\Core\Auth\Editor_Access;
use Pura\Core\Editor\Editor_Fields;

final class EditorFieldsTest extends TestCase {

	public function test_admin_only_fields_are_not_editable(): void {
		foreach ( array( 'title', 'slug', 'pura_city', 'city', 'country', 'lat', 'pura_sede_id', 'rank', 'full_name', '_pura_editor_email', '_thumbnail_id', 'status' ) as $field ) {
			foreach ( array( Editor_Fields::TYPE_SEDE, Editor_Fields::TYPE_PROFESOR ) as $type ) {
				$result = Editor_Fields::validate( $type, array( $field => 'x' ) );
				$this->assertFalse( $result['ok'], "$type.$field" );
				$this->assertSame( 'not_editable', $result['errors'][ $field ] );
			}
		}
	}

	public function test_one_bad_field_refuses_everything(): void {
		$result = Editor_Fields::validate(
			Editor_Fields::TYPE_SEDE,
			array(
				'address' => 'Calle 1',
				'title'   => 'Otro nombre',
			)
		);
		$this->assertFalse( $result['ok'] );
		$this->assertSame( array(), $result['values'] );
	}

	public function test_text_is_made_plain(): void {
		$result = Editor_Fields::validate( Editor_Fields::TYPE_SEDE, array( 'address' => "  <b>Calle</b>   1\n<script>alert(1)</script> " ) );
		$this->assertTrue( $result['ok'] );
		$this->assertSame( 'Calle 1 alert(1)', $result['values']['address'] );
	}

	public function test_multiline_keeps_paragraphs(): void {
		$result = Editor_Fields::validate( Editor_Fields::TYPE_PROFESOR, array( 'subtitle' => "Uno  dos\r\n\r\n\r\n\r\nTres " ) );
		$this->assertSame( "Uno dos\n\nTres", $result['values']['subtitle'] );
	}

	public function test_length_limits(): void {
		$this->assertTrue( Editor_Fields::validate( Editor_Fields::TYPE_SEDE, array( 'address' => str_repeat( 'ñ', 200 ) ) )['ok'] );
		$result = Editor_Fields::validate( Editor_Fields::TYPE_SEDE, array( 'address' => str_repeat( 'ñ', 201 ) ) );
		$this->assertSame( 'too_long', $result['errors']['address'] );
	}

	/**
	 * @dataProvider links
	 */
	public function test_links( string $type, string $field, string $value, bool $ok ): void {
		$result = Editor_Fields::validate( $type, array( $field => $value ) );
		$this->assertSame( $ok, $result['ok'], "$field=$value" );
		if ( ! $ok ) {
			$this->assertSame( 'bad_link', $result['errors'][ $field ] );
		}
	}

	/**
	 * @return array<string, array{string, string, string, bool}>
	 */
	public static function links(): array {
		$s = Editor_Fields::TYPE_SEDE;
		$p = Editor_Fields::TYPE_PROFESOR;

		return array(
			'whatsapp wa.me'               => array( $s, 'whatsapp', 'https://wa.me/5215512345678', true ),
			'whatsapp api'                 => array( $s, 'whatsapp', 'https://api.whatsapp.com/send?phone=521', true ),
			'whatsapp cleared'             => array( $s, 'whatsapp', '', true ),
			'whatsapp http'                => array( $s, 'whatsapp', 'http://wa.me/521', false ),
			'whatsapp elsewhere'           => array( $s, 'whatsapp', 'https://evil.example/wa.me', false ),
			'whatsapp look-alike host'     => array( $s, 'whatsapp', 'https://wa.me.evil.example/521', false ),
			'whatsapp suffix trick'        => array( $s, 'whatsapp', 'https://notwa.me/521', false ),
			'javascript scheme'            => array( $s, 'whatsapp', 'javascript:alert(1)', false ),
			'credentials in link'          => array( $s, 'instagram', 'https://instagram.com@evil.example/x', false ),
			'instagram www'                => array( $s, 'instagram', 'https://www.instagram.com/puracapoeira/', true ),
			'instagram elsewhere'          => array( $s, 'instagram', 'https://instagr.am/x', false ),
			'sede facebook as a page name' => array( $s, 'facebook', 'Pura Capoeira Toluca', true ),
			'sede facebook as a link'      => array( $s, 'facebook', 'https://www.facebook.com/puracapoeira', true ),
			'sede facebook bad link'       => array( $s, 'facebook', 'https://evil.example/fb', false ),
			'sede facebook data scheme'    => array( $s, 'facebook', 'data:text/html,x', false ),
			'profesor youtube'             => array( $p, 'youtube', 'https://youtu.be/abc', true ),
			'profesor youtube elsewhere'   => array( $p, 'youtube', 'https://vimeo.com/1', false ),
			'profesor website any https'   => array( $p, 'website', 'https://mestre.example.com.br/sobre', true ),
			'profesor website http'        => array( $p, 'website', 'http://mestre.example.com', false ),
			'profesor website no dot'      => array( $p, 'website', 'https://localhost/x', false ),
		);
	}

	public function test_rows(): void {
		$result = Editor_Fields::validate(
			Editor_Fields::TYPE_SEDE,
			array(
				'schedule' => array(
					array(
						'group' => ' Adultos ',
						'days'  => 'Lun y <i>Mié</i>',
						'time'  => '19:00',
					),
					array(
						'group' => '',
						'days'  => ' ',
						'time'  => '',
					),
				),
				'pricing'  => array(),
			)
		);
		$this->assertTrue( $result['ok'] );
		$this->assertSame(
			array(
				array(
					'group' => 'Adultos',
					'days'  => 'Lun y Mié',
					'time'  => '19:00',
				),
			),
			$result['values']['schedule']
		);
		$this->assertSame( array(), $result['values']['pricing'] );
	}

	public function test_rows_reject_bad_shapes(): void {
		$row = array(
			'label' => 'Mes',
			'value' => '$500',
		);
		$bad = array(
			'not a list'      => 'Mes | $500',
			'keyed list'      => array( 'a' => $row ),
			'unknown column'  => array( $row + array( 'extra' => 'x' ) ),
			'non-string cell' => array(
				array(
					'label' => array( 'x' ),
					'value' => '1',
				),
			),
		);
		foreach ( $bad as $label => $value ) {
			$this->assertSame( 'invalid', Editor_Fields::validate( Editor_Fields::TYPE_SEDE, array( 'pricing' => $value ) )['errors']['pricing'], $label );
		}

		$this->assertSame( 'too_many_rows', Editor_Fields::validate( Editor_Fields::TYPE_SEDE, array( 'pricing' => array_fill( 0, 13, $row ) ) )['errors']['pricing'] );
		$this->assertTrue( Editor_Fields::validate( Editor_Fields::TYPE_SEDE, array( 'pricing' => array_fill( 0, 12, $row ) ) )['ok'] );
		$long = array(
			array(
				'label' => str_repeat( 'a', 121 ),
				'value' => '',
			),
		);
		$this->assertSame( 'too_long', Editor_Fields::validate( Editor_Fields::TYPE_SEDE, array( 'pricing' => $long ) )['errors']['pricing'] );
	}

	public function test_non_string_values_are_refused(): void {
		$this->assertSame( 'invalid', Editor_Fields::validate( Editor_Fields::TYPE_SEDE, array( 'address' => array( 'x' ) ) )['errors']['address'] );
		$this->assertSame( 'invalid', Editor_Fields::validate( Editor_Fields::TYPE_SEDE, array( 'address' => 5 ) )['errors']['address'] );
	}

	public function test_unknown_type_has_no_fields(): void {
		$this->assertSame( array(), Editor_Fields::definitions( 'evento' ) );
		$this->assertFalse( Editor_Fields::validate( 'evento', array( 'excerpt' => 'x' ) )['ok'] );
	}

	public function test_email_matching_helpers(): void {
		$this->assertSame( 'laura@example.com', Editor_Access::normalize_email( '  Laura@Example.COM ' ) );
		$this->assertSame( '', Editor_Access::normalize_email( 'laura' ) );
		$this->assertSame(
			array( 'a@x.io', 'b@x.io' ),
			Editor_Access::parse_emails( "A@x.io\n b@x.io, a@X.io; nope\n\n" )
		);
		$this->assertSame( array(), Editor_Access::parse_emails( '' ) );
	}
}
