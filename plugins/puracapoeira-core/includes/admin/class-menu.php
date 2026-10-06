<?php
/**
 * Top-level "Pura Capoeira" admin menu. Post types attach themselves via show_in_menu => 'pura'.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Admin;

defined( 'ABSPATH' ) || exit;

final class Menu {

	public const SLUG = 'pura';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_styles' ) );
	}

	public function add_menu(): void {
		add_menu_page(
			__( 'Pura Capoeira', 'pura' ),
			__( 'Pura Capoeira', 'pura' ),
			'manage_options',
			self::SLUG,
			array( Settings_Page::class, 'render' ),
			'dashicons-universal-access',
			25
		);

		// Rename the auto-created first submenu (which mirrors the top-level page).
		add_submenu_page(
			self::SLUG,
			__( 'Ajustes', 'pura' ),
			__( 'Ajustes', 'pura' ),
			'manage_options',
			self::SLUG,
			array( Settings_Page::class, 'render' ),
			99
		);
	}

	public function admin_styles(): void {
		$css = '
		.pura-settings .form-table th { width: 260px; }
		.pura-settings input[type=text], .pura-settings input[type=url], .pura-settings input[type=email] { width: 28em; max-width: 100%; }
		.pura-settings .pura-locked { opacity: .7; }
		.pura-media-preview img { max-width: 240px; height: auto; display: block; margin-bottom: 8px; border: 1px solid #ddd; }
		.pura-meta-table th { text-align: left; width: 200px; padding: 6px 8px 6px 0; vertical-align: top; }
		.pura-meta-table td { padding: 6px 0; }
		.pura-meta-table input[type=text], .pura-meta-table input[type=url], .pura-meta-table input[type=email], .pura-meta-table input[type=number], .pura-meta-table select { width: 100%; max-width: 32em; }
		.pura-meta-table textarea { width: 100%; max-width: 48em; }
		.pura-i18n-keys code { margin-right: 6px; }
		';
		wp_add_inline_style( 'wp-admin', $css );
	}
}
