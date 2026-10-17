<?php
/**
 * Global helpers for themes. Guard calls with function_exists() in theme code.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'pura_settings' ) ) {
	/**
	 * @return array<string, mixed>
	 */
	function pura_settings(): array {
		return Pura\Core\Settings::all();
	}
}

if ( ! function_exists( 'pura_setting' ) ) {
	/**
	 * @param mixed $fallback Fallback value.
	 * @return mixed
	 */
	function pura_setting( string $key, $fallback = null ) {
		return Pura\Core\Settings::get( $key, $fallback );
	}
}

if ( ! function_exists( 'pura_whatsapp_url' ) ) {
	function pura_whatsapp_url( string $text = '' ): string {
		return Pura\Core\Settings::whatsapp_url( $text );
	}
}

if ( ! function_exists( 'pura_privacy_url' ) ) {
	/**
	 * URL of the Aviso de privacidad.
	 */
	function pura_privacy_url(): string {
		return Pura\Core\Settings::privacy_url();
	}
}

if ( ! function_exists( 'pura_account_url' ) ) {
	/**
	 * URL of the "Mi cuenta" page.
	 */
	function pura_account_url(): string {
		return Pura\Core\Settings::page_url( 'account' );
	}
}

if ( ! function_exists( 'pura_auth_providers' ) ) {
	/**
	 * Configured sign-in providers for the front end.
	 *
	 * @return array<int, array{id:string,label:string,startUrl:string}>
	 */
	function pura_auth_providers(): array {
		return Pura\Core\Auth\Providers::configured();
	}
}
