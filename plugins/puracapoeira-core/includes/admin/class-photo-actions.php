<?php
/**
 * One-click actions from "Fotos recibidas": send a received photo to a profesor's profile or a
 * sede (it becomes the featured image), and regenerate the shared upload link.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Admin;

use Pura\Core\Data\Profesor_Post_Type;
use Pura\Core\Data\Sede_Post_Type;
use Pura\Core\Photo_Uploads;

defined( 'ABSPATH' ) || exit;

final class Photo_Actions {

	public const ACTION_SET_THUMBNAIL = 'pura_photo_set_thumbnail';
	public const ACTION_REGENERATE    = 'pura_photo_regenerate_key';
	public const NONCE                = 'pura_photo_action';

	public function register(): void {
		add_action( 'admin_post_' . self::ACTION_SET_THUMBNAIL, array( $this, 'handle_set_thumbnail' ) );
		add_action( 'admin_post_' . self::ACTION_REGENERATE, array( $this, 'handle_regenerate' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	public function handle_set_thumbnail(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sin permisos.', 'pura' ) );
		}
		check_admin_referer( self::NONCE );

		$attachment_id = isset( $_GET['attachment'] ) ? (int) $_GET['attachment'] : 0;
		$target_type   = isset( $_GET['target'] ) ? sanitize_key( wp_unslash( $_GET['target'] ) ) : '';
		$target_id     = isset( $_GET['target_id'] ) ? (int) $_GET['target_id'] : 0;

		$post_types = array(
			'profesor' => Profesor_Post_Type::POST_TYPE,
			'sede'     => Sede_Post_Type::POST_TYPE,
		);

		$ok = isset( $post_types[ $target_type ] )
			&& $target_id > 0
			&& get_post_type( $target_id ) === $post_types[ $target_type ]
			&& $attachment_id > 0
			&& wp_attachment_is_image( $attachment_id )
			&& set_post_thumbnail( $target_id, $attachment_id );

		$this->remember(
			$ok,
			$ok
				/* translators: %s: profesor or sede title. */
				? sprintf( __( 'Foto aplicada a «%s».', 'pura' ), (string) get_the_title( $target_id ) )
				: __( 'No se pudo aplicar la foto.', 'pura' )
		);

		$back = (string) wp_get_referer();
		wp_safe_redirect( '' !== $back ? $back : admin_url( 'edit.php?post_type=pura_photo_batch' ) );
		exit;
	}

	public function handle_regenerate(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sin permisos.', 'pura' ) );
		}
		check_admin_referer( self::NONCE );

		Photo_Uploads::regenerate();
		$this->remember( true, __( 'Enlace nuevo generado. Los enlaces anteriores ya no funcionan.', 'pura' ) );

		wp_safe_redirect( admin_url( 'admin.php?page=' . Menu::SLUG ) );
		exit;
	}

	private function remember( bool $ok, string $message ): void {
		set_transient(
			'pura_photo_notice_' . get_current_user_id(),
			array(
				'ok'      => $ok,
				'message' => $message,
			),
			60
		);
	}

	public function notice(): void {
		$notice = get_transient( 'pura_photo_notice_' . get_current_user_id() );
		if ( ! is_array( $notice ) ) {
			return;
		}
		delete_transient( 'pura_photo_notice_' . get_current_user_id() );
		echo '<div class="notice ' . ( $notice['ok'] ? 'notice-success' : 'notice-error' ) . ' is-dismissible"><p>' . esc_html( (string) $notice['message'] ) . '</p></div>';
	}
}
