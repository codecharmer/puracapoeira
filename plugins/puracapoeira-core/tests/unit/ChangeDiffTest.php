<?php
/**
 * What counts as a change, how it reads in the notice, and dropping stale translations.
 */

declare( strict_types=1 );

namespace Pura\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pura\Core\Editor\Change_Diff;
use Pura\Core\Editor\Editor_Writer;
use Pura\Core\Mailer;

final class ChangeDiffTest extends TestCase {

	public function test_changed_fields_only(): void {
		$row    = array(
			'label' => 'Mes',
			'value' => '$500',
		);
		$before = array(
			'address' => 'Calle 1',
			'pricing' => array( $row ),
			'excerpt' => '',
		);
		$after  = array(
			'address' => 'Calle 1',
			'pricing' => array(
				array(
					'label' => 'Mes',
					'value' => '$600',
				),
			),
			'excerpt' => 'Nuevo',
		);
		$this->assertSame( array( 'pricing', 'excerpt' ), Change_Diff::changed( $before, $after ) );
		$this->assertSame( array(), Change_Diff::changed( $before, $before ) );
		$this->assertSame( array(), Change_Diff::changed( $before, array( 'pricing' => array( 3 => $row ) ) ), 'row keys do not matter' );
		$this->assertSame( array( 'website' ), Change_Diff::changed( $before, array( 'website' => 'https://x.io' ) ), 'a field with no previous value' );
	}

	public function test_notice_body(): void {
		$body = Mailer::build_change_body(
			array(
				'target_title' => 'Toluca Centro',
				'target_url'   => 'https://example.test/sedes/toluca/',
				'actor_email'  => 'laura@example.com',
				'when'         => '2026-11-10 18:00:00',
				'source'       => 'editor',
				'before'       => array(
					'address' => '',
					'pricing' => array(
						array(
							'label' => 'Mes',
							'value' => '$500',
						),
					),
				),
				'after'        => array(
					'address' => 'Calle 2',
					'pricing' => array(
						array(
							'label' => 'Mes',
							'value' => '$600',
						),
						array(
							'label' => 'Clase suelta',
							'value' => '$80',
						),
					),
				),
			),
			'https://example.test/',
			'https://example.test/wp-admin/post.php?action=edit&post=9'
		);

		$this->assertStringContainsString( 'Un profesor cambió datos en https://example.test/', $body );
		$this->assertStringContainsString( 'Ficha:  Toluca Centro', $body );
		$this->assertStringContainsString( 'Quién:  laura@example.com', $body );
		$this->assertStringContainsString( "Dirección:\n  Antes:   (vacío)\n  Ahora:   Calle 2", $body );
		$this->assertStringContainsString( "Precios:\n  Antes:   Mes | \$500\n  Ahora:   Mes | \$600\n           Clase suelta | \$80", $body );
		$this->assertStringContainsString( 'post.php?action=edit&post=9', $body );
	}

	public function test_restore_notice_says_so(): void {
		$body = Mailer::build_change_body(
			array(
				'source' => 'restore',
				'before' => array( 'address' => 'b' ),
				'after'  => array( 'address' => 'a' ),
			),
			'https://example.test/',
			'x'
		);
		$this->assertStringContainsString( 'Se restauraron datos en', $body );
	}

	public function test_stale_translations_are_dropped(): void {
		$data = array(
			'en' => array(
				'hero-intro' => 'Old intro',
				'bio-1'      => 'Bio',
			),
			'pt' => array( 'hero-intro' => 'Intro antiga' ),
		);
		$this->assertSame(
			array( 'en' => array( 'bio-1' => 'Bio' ) ),
			Editor_Writer::without_keys( $data, array( 'hero-intro' ) )
		);
		$this->assertSame( $data, Editor_Writer::without_keys( $data, array( 'caption' ) ) );
		$this->assertSame( array(), Editor_Writer::without_keys( array( 'pt' => array( 'caption' => 'x' ) ), array( 'caption' ) ) );
	}
}
