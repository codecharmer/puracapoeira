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
