<?php
/**
 * Restoring a change: an administrator puts back the values a sede or a profesor had before
 * a professor's edit. The restoration is itself recorded as a change.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Admin;

use Pura\Core\Data\Change_Post_Type;
use Pura\Core\Editor\Change_Diff;
use Pura\Core\Editor\Editor_Document;
use Pura\Core\Editor\Editor_Fields;
use Pura\Core\Editor\Editor_Photos;
use Pura\Core\Editor\Editor_Writer;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Change_Actions {

	public const ACTION = 'pura_change_restore';
	public const NONCE  = 'pura_change_restore';
	private const FLASH = 'pura_change_notice';

	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_restore' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	public static function restore_url( int $change_id ): string {
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION . '&change=' . $change_id ), self::NONCE );
	}

	public function handle_restore(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sin permisos.', 'pura' ) );
		}
		check_admin_referer( self::NONCE );

		$change_id = isset( $_GET['change'] ) ? (int) $_GET['change'] : 0;
		$message   = self::restore( $change_id, wp_get_current_user() )['message'];

		set_transient( self::FLASH . '_' . get_current_user_id(), $message, 60 );
		$back = (string) wp_get_referer();
		wp_safe_redirect( '' !== $back ? $back : admin_url( 'edit.php?post_type=' . Change_Post_Type::POST_TYPE ) );
		exit;
	}

	/**
	 * Puts back the values recorded as "before" in a change. Only the fields a professor can
	 * edit are restored, and they pass the same checks as an edit.
	 *
	 * @return array{restored: string[], message: string}
	 */
	public static function restore( int $change_id, \WP_User $user ): array {
		$change = Change_Post_Type::to_array( $change_id );
		$target = $change ? get_post( $change['target_id'] ) : null;
		$failed = array(
			'restored' => array(),
			'message'  => __( 'No se pudo restaurar.', 'pura' ),
		);
		if ( ! $change || ! $target instanceof WP_Post ) {
			return $failed;
		}

		$type   = Editor_Document::type_of( $target );
		$fields = array_intersect_key( $change['before'], Editor_Fields::definitions( $type ) );
		$check  = Editor_Fields::validate( $type, $fields );
		$photos = Editor_Photos::restorable( $target, $change['before'] );
		if ( ! $check['ok'] || ! ( $check['values'] || $photos ) ) {
			return $failed;
		}
		$fields += $photos;

		// Were these fields changed again after the change being undone? Then that later work is replaced too.
		$drifted = Change_Diff::changed( Editor_Document::values( $target ) + Editor_Photos::values( $target ), array_intersect_key( $change['after'], $fields ) );
		$result  = Editor_Writer::apply( $target, $check['values'] + $photos, (int) $user->ID, strtolower( (string) $user->user_email ), 'restore', $change_id );
		$message = $result['changed']
			/* translators: %s: sede or profesor title. */
			? sprintf( __( 'Se restauraron los valores anteriores de «%s».', 'pura' ), (string) get_the_title( $target ) )
			: __( 'Esos valores ya eran los actuales; no se cambió nada.', 'pura' );
		if ( $result['changed'] && $drifted ) {
			$message .= ' ' . __( 'Ojo: desde ese cambio hubo otros en los mismos datos, que también se reemplazaron.', 'pura' );
		}

		return array(
			'restored' => $result['changed'],
			'message'  => $message,
		);
	}

	public function notice(): void {
		$key     = self::FLASH . '_' . get_current_user_id();
		$message = get_transient( $key );
		if ( ! is_string( $message ) || '' === $message ) {
			return;
		}
		delete_transient( $key );
		echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}
}
