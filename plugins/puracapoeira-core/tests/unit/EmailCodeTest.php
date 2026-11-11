<?php
/**
 * Sign-in codes sent by email.
 */

declare( strict_types=1 );

namespace Pura\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pura\Core\Auth\Email_Code;
use Pura\Core\Mailer;

final class EmailCodeTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['pura_test_transients'] = array();
	}

	public function test_issue_returns_six_digits_and_an_unguessable_ticket(): void {
		$a = Email_Code::issue( 'ana@example.com' );
		$b = Email_Code::issue( 'ana@example.com' );

		self::assertMatchesRegularExpression( '/^\d{6}$/', $a['code'] );
		self::assertNotSame( $a['ticket'], $b['ticket'] );
		self::assertGreaterThanOrEqual( 43, strlen( $a['ticket'] ) );
	}

	public function test_the_code_is_not_stored_in_clear(): void {
		$issued = Email_Code::issue( 'ana@example.com' );

		self::assertStringNotContainsString( $issued['code'], serialize( $GLOBALS['pura_test_transients'] ) );
		self::assertStringNotContainsString( $issued['ticket'], serialize( $GLOBALS['pura_test_transients'] ) );
	}

	public function test_right_code_signs_in_once(): void {
		$issued = Email_Code::issue( 'ana@example.com', 'https://example.com/editar/' );

		$result = Email_Code::verify( $issued['ticket'], $issued['code'] );
		self::assertSame( Email_Code::OK, $result['status'] );
		self::assertSame( 'ana@example.com', $result['email'] );
		self::assertSame( 'https://example.com/editar/', $result['redirect_to'] );

		self::assertSame( Email_Code::EXPIRED, Email_Code::verify( $issued['ticket'], $issued['code'] )['status'], 'second use must fail' );
	}

	public function test_code_typed_with_spaces_or_a_dash_is_accepted(): void {
		$issued = Email_Code::issue( 'ana@example.com' );
		$typed  = ' ' . substr( $issued['code'], 0, 3 ) . '-' . substr( $issued['code'], 3 ) . ' ';

		self::assertSame( Email_Code::OK, Email_Code::verify( $issued['ticket'], $typed )['status'] );
	}

	public function test_wrong_code_counts_and_the_last_try_kills_the_ticket(): void {
		$issued = Email_Code::issue( 'ana@example.com' );
		$wrong  = '000000' === $issued['code'] ? '111111' : '000000';

		for ( $i = 1; $i < Email_Code::MAX_ATTEMPTS; $i++ ) {
			$result = Email_Code::verify( $issued['ticket'], $wrong );
			self::assertSame( Email_Code::WRONG, $result['status'] );
			self::assertSame( '', $result['email'] );
		}
		self::assertSame( Email_Code::LOCKED, Email_Code::verify( $issued['ticket'], $wrong )['status'] );
		self::assertSame( Email_Code::EXPIRED, Email_Code::verify( $issued['ticket'], $issued['code'] )['status'], 'the right code comes too late' );
	}

	public function test_right_code_still_works_after_a_wrong_try(): void {
		$issued = Email_Code::issue( 'ana@example.com' );
		$wrong  = '000000' === $issued['code'] ? '111111' : '000000';

		self::assertSame( Email_Code::WRONG, Email_Code::verify( $issued['ticket'], $wrong )['status'] );
		self::assertSame( Email_Code::OK, Email_Code::verify( $issued['ticket'], $issued['code'] )['status'] );
	}

	public function test_code_of_another_ticket_fails(): void {
		$a = Email_Code::issue( 'ana@example.com' );
		$b = Email_Code::issue( 'eva@example.com' );
		if ( $a['code'] === $b['code'] ) {
			self::markTestSkipped( 'Both tickets drew the same code.' );
		}

		self::assertSame( Email_Code::WRONG, Email_Code::verify( $a['ticket'], $b['code'] )['status'] );
	}

	public function test_expired_and_unknown_tickets_fail(): void {
		$issued = Email_Code::issue( 'ana@example.com' );
		foreach ( $GLOBALS['pura_test_transients'] as $name => $row ) {
			$row['value']['expires']                  = time() - 1;
			$GLOBALS['pura_test_transients'][ $name ] = $row;
		}

		self::assertSame( Email_Code::EXPIRED, Email_Code::verify( $issued['ticket'], $issued['code'] )['status'] );
		self::assertSame( Email_Code::EXPIRED, Email_Code::verify( '', '123456' )['status'] );
		self::assertSame( Email_Code::EXPIRED, Email_Code::verify( 'nope', '123456' )['status'] );
		self::assertSame( Email_Code::EXPIRED, Email_Code::verify( str_repeat( 'a', 200 ), '123456' )['status'] );
	}

	public function test_unusable_ticket_never_signs_in(): void {
		$issued = Email_Code::issue( 'admin@example.com', '', false );

		self::assertSame( Email_Code::WRONG, Email_Code::verify( $issued['ticket'], $issued['code'] )['status'] );
	}

	public function test_mail_carries_the_code_in_each_language(): void {
		$es = Mailer::build_login_code_mail( '048213', 'es', 'https://puracapoeira.com/', 10 );
		self::assertSame( '048213 es tu código de Pura Capoeira', $es['subject'] );
		self::assertStringContainsString( "\n    048213\n", $es['body'] );
		self::assertStringContainsString( 'Vale 10 minutos', $es['body'] );
		self::assertStringContainsString( 'https://puracapoeira.com/', $es['body'] );

		self::assertStringContainsString( 'is your Pura Capoeira code', Mailer::build_login_code_mail( '048213', 'en', 'https://puracapoeira.com/', 10 )['subject'] );
		self::assertStringContainsString( 'é o seu código', Mailer::build_login_code_mail( '048213', 'pt', 'https://puracapoeira.com/', 10 )['subject'] );
		self::assertSame( $es, Mailer::build_login_code_mail( '048213', 'fr', 'https://puracapoeira.com/', 10 ), 'unknown languages fall back to Spanish' );
	}
}
