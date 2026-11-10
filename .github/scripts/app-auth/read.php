<?php
/**
 * Run through `wp eval-file read.php` on the server. Prints, one per line, `NAME=<base64 value>` for the
 * settings the Pura Capoeira app's sign-in needs: the SMTP mailbox WordPress already sends with, and the
 * Sign in with Google / Apple identifiers. The output goes over SSH to the workflow runner, which masks
 * every value before using it; nothing here is written to a log.
 *
 * @package Pura\Ops
 */

// No declare(strict_types): wp eval-file runs the file through eval(), which rejects it.
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.

$setting = static function ( string $key ): string {
	return class_exists( '\Pura\Core\Settings' ) ? (string) \Pura\Core\Settings::get( $key, '' ) : '';
};
$secret  = static function ( string $key ): string {
	return class_exists( '\Pura\Core\Settings' ) ? (string) \Pura\Core\Settings::get_secret( $key ) : '';
};

$values = array(
	'SMTP_USER'            => defined( 'FLUENTMAIL_SMTP_USERNAME' ) ? (string) FLUENTMAIL_SMTP_USERNAME : '',
	'SMTP_PASS'            => defined( 'FLUENTMAIL_SMTP_PASSWORD' ) ? (string) FLUENTMAIL_SMTP_PASSWORD : '',
	'GOOGLE_CLIENT_ID'     => $setting( 'google_client_id' ),
	'GOOGLE_CLIENT_SECRET' => $secret( 'google_client_secret' ),
);

foreach ( $values as $name => $value ) {
	echo 'PURA_APP_AUTH ' . $name . '=' . base64_encode( $value ) . "\n";
}
