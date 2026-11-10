<?php
/**
 * Admin box on a sede and on a profesor: the emails of the people who may edit it on the
 * editor page. Only an administrator sees or changes it.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Admin;

use Pura\Core\Auth\Editor_Access;
use Pura\Core\Settings;

defined( 'ABSPATH' ) || exit;

final class Editor_Links {

	private const NONCE = 'pura_editor_links';
	private const FIELD = 'pura_editor_emails';

	public function register(): void {
		foreach ( Editor_Access::POST_TYPES as $post_type ) {
			add_action( 'add_meta_boxes_' . $post_type, array( $this, 'add_meta_box' ) );
			add_action( 'save_post_' . $post_type, array( $this, 'save' ) );
		}
	}

	public function add_meta_box(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		add_meta_box( 'pura_editor_links', __( 'Quién puede editar esto', 'pura' ), array( $this, 'render' ), null, 'side', 'default' );
	}

	public function render( \WP_Post $post ): void {
		$emails = Editor_Access::emails_for_post( $post->ID );
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );

		echo '<p class="description">' . esc_html__( 'Un correo por línea. La persona entra con Google o Apple usando ese correo y edita esta ficha en la página de edición del sitio. No obtiene acceso a este panel.', 'pura' ) . '</p>';
		echo '<textarea name="' . esc_attr( self::FIELD ) . '" rows="4" class="widefat code">' . esc_textarea( implode( "\n", $emails ) ) . '</textarea>';

		if ( $emails ) {
			echo '<ul style="margin:.6em 0 0">';
			foreach ( $emails as $email ) {
				$signed_in = null !== Editor_Access::user_for_email( $email );
				echo '<li><code>' . esc_html( $email ) . '</code> — ' . esc_html( $signed_in ? __( 'ya entró', 'pura' ) : __( 'aún no ha entrado', 'pura' ) ) . '</li>';
			}
			echo '</ul>';
		}

		$url = Settings::page_url( 'editor' );
		if ( '' !== $url ) {
			echo '<p><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Página de edición', 'pura' ) . '</a></p>';
		}
	}

	public function save( int $post_id ): void {
		if ( ! isset( $_POST[ self::NONCE . '_nonce' ], $_POST[ self::FIELD ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE . '_nonce' ] ) ), self::NONCE ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		Editor_Access::set_emails( $post_id, Editor_Access::parse_emails( sanitize_textarea_field( wp_unslash( $_POST[ self::FIELD ] ) ) ) );
	}
}
