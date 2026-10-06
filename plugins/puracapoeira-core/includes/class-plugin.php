<?php
/**
 * Plugin bootstrap: wires every component to WordPress hooks.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private static ?Plugin $instance = null;

	private bool $booted = false;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		add_action( 'init', array( $this, 'register_data' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'print_front_config' ), 5 );

		if ( is_admin() ) {
			( new Admin\Menu() )->register();
			( new Admin\Settings_Page() )->register();
		}

		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( Cli\Cli::class ) ) {
			Cli\Cli::register();
		}
	}

	/**
	 * Post types, taxonomies, meta, the Block Bindings source and the translations meta.
	 */
	public function register_data(): void {
		( new Data\Sede_Post_Type() )->register();
		( new Data\Profesor_Post_Type() )->register();
		( new Data\Evento_Post_Type() )->register();
		( new Data\Galeria_Post_Type() )->register();
		( new Data\Block_Bindings() )->register();
		( new Data\I18n_Meta() )->register();
	}

	public function register_rest_routes(): void {
		( new Rest\Contact_Controller() )->register_routes();

		add_filter( 'rest_request_after_callbacks', array( Rest\Base_Controller::class, 'shape_wp_error' ), 10, 3 );
	}

	/**
	 * `window.puraConfig` for the theme's view scripts: REST root and contact details.
	 * Printed in <head> as an inline-only handle so deferred view scripts always find it.
	 */
	public function print_front_config(): void {
		wp_register_script( 'pura-core-config', false, array(), PURA_CORE_VERSION, false );
		wp_enqueue_script( 'pura-core-config' );
		wp_add_inline_script(
			'pura-core-config',
			'window.puraConfig = ' . wp_json_encode( Settings::public_config() ) . ';'
		);
	}
}
