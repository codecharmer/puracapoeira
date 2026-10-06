<?php
/**
 * Activation and deactivation.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core;

defined( 'ABSPATH' ) || exit;

final class Activator {

	public static function activate(): void {
		add_option( Settings::OPTION, Settings::defaults(), '', true );

		( new Data\Sede_Post_Type() )->register();
		( new Data\Profesor_Post_Type() )->register();
		( new Data\Evento_Post_Type() )->register();
		( new Data\Galeria_Post_Type() )->register();

		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
