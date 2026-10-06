<?php
/**
 * CSV export from the event registrations admin list ("Exportar CSV" button).
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Admin;

use Pura\Core\Data\Event_Registration_Post_Type;
use Pura\Core\Data\Event_Registration_Repository;

defined( 'ABSPATH' ) || exit;

final class Csv_Export {

	private const ACTION_EVENTS = 'pura_export_event_registrations';
	private const NONCE         = 'pura_export';

	public function register(): void {
		add_action( 'admin_post_' . self::ACTION_EVENTS, array( $this, 'handle_events' ) );
		add_action( 'manage_posts_extra_tablenav', array( $this, 'button' ) );
	}

	public function button( string $which ): void {
		if ( 'top' !== $which || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || Event_Registration_Post_Type::POST_TYPE !== $screen->post_type ) {
			return;
		}

		// Carry the current list filters into the export.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters.
		$args = array(
			'action'      => self::ACTION_EVENTS,
			'pura_status' => isset( $_GET['pura_status'] ) ? sanitize_key( wp_unslash( $_GET['pura_status'] ) ) : '',
			'pura_event'  => isset( $_GET['pura_event'] ) ? sanitize_key( wp_unslash( $_GET['pura_event'] ) ) : '',
		);
		// phpcs:enable
		$url = wp_nonce_url( add_query_arg( array_filter( $args ), admin_url( 'admin-post.php' ) ), self::NONCE );

		echo '<div class="alignleft actions"><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Exportar CSV', 'pura' ) . '</a></div>';
	}

	public function handle_events(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sin permisos.', 'pura' ) );
		}
		check_admin_referer( self::NONCE );

		$status = isset( $_GET['pura_status'] ) ? sanitize_key( wp_unslash( $_GET['pura_status'] ) ) : '';
		$event  = isset( $_GET['pura_event'] ) ? sanitize_key( wp_unslash( $_GET['pura_event'] ) ) : '';

		$meta_query = array();
		if ( '' !== $status && isset( Event_Registration_Post_Type::STATUSES[ $status ] ) ) {
			$meta_query[] = array(
				'key'   => '_pura_status',
				'value' => $status,
			);
		}
		if ( '' !== $event ) {
			$meta_query[] = array(
				'key'   => '_pura_event',
				'value' => $event,
			);
		}

		$columns = array( 'id', 'created_at', 'status', 'event', 'event_name', 'first_name', 'last_name', 'email', 'phone', 'dob', 'parent_name', 'parent_phone', 'city', 'academy', 'teacher', 'graduation', 'started_year', 'years_training', 'days', 'shirt_size', 'emergency_name', 'emergency_phone', 'notes' );

		$query_args = array(
			'post_type'      => Event_Registration_Post_Type::POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		);
		if ( $meta_query ) {
			$query_args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- admin export, small volume.
		}
		$ids = array_map( 'intval', ( new \WP_Query( $query_args ) )->posts );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . ( '' !== $event ? 'registros-' . $event : 'registros-eventos' ) . '-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- UTF-8 BOM for spreadsheets.
		fputcsv( $out, $columns );
		foreach ( $ids as $id ) {
			$row = Event_Registration_Repository::to_array( $id );
			if ( ! $row ) {
				continue;
			}
			$row['status'] = Event_Registration_Post_Type::status_label( (string) $row['status'] );
			fputcsv( $out, array_map( static fn ( $col ) => (string) ( $row[ $col ] ?? '' ), $columns ) );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}
