<?php
/**
 * Contact-form mail via wp_mail. Plain text, Spanish.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core;

defined( 'ABSPATH' ) || exit;

final class Mailer {

	/** @var string|null Last wp_mail error message. */
	private static ?string $last_error = null;

	/**
	 * @return string[]
	 */
	public static function recipients(): array {
		$raw = (string) Settings::get( 'contact_to_emails', '' );

		return array_values( array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', $raw ) ) ), 'is_email' ) );
	}

	/**
	 * Recipients of event registration notices: `event_to_emails` when set, else the contact recipients.
	 *
	 * @return string[]
	 */
	public static function event_recipients(): array {
		$raw  = (string) Settings::get( 'event_to_emails', '' );
		$list = array_values( array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', $raw ) ) ), 'is_email' ) );

		return $list ? $list : self::recipients();
	}

	/**
	 * Recipients of "fotos recibidas" notices: `photo_to_emails` when set, else the contact recipients.
	 *
	 * @return string[]
	 */
	public static function photo_recipients(): array {
		$raw  = (string) Settings::get( 'photo_to_emails', '' );
		$list = array_values( array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', $raw ) ) ), 'is_email' ) );

		return $list ? $list : self::recipients();
	}

	/**
	 * Recipients of "a professor changed something" notices: `editor_to_emails` when set, else the contact recipients.
	 *
	 * @return string[]
	 */
	public static function editor_recipients(): array {
		$raw  = (string) Settings::get( 'editor_to_emails', '' );
		$list = array_values( array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', $raw ) ) ), 'is_email' ) );

		return $list ? $list : self::recipients();
	}

	/**
	 * Tell the administrator what a professor changed on their sede or profile.
	 */
	public static function notify_change( int $change_id ): bool {
		$change = Data\Change_Post_Type::to_array( $change_id );
		if ( ! $change ) {
			return false;
		}

		$recipients = self::editor_recipients();
		if ( ! $recipients ) {
			self::$last_error = 'No hay destinatarios configurados.';
			return false;
		}

		$labels           = implode( ', ', array_map( array( Editor\Change_Diff::class, 'label' ), array_keys( $change['after'] ) ) );
		$subject          = sprintf( '%s: %s — %s', 'restore' === $change['source'] ? 'Restaurado' : 'Cambio', $change['target_title'], $labels );
		$change['before'] = Editor\Editor_Photos::readable( $change['before'] );
		$change['after']  = Editor\Editor_Photos::readable( $change['after'] );
		if ( isset( $change['after']['bio'] ) ) {
			// Only the lines of the bio that differ, as text.
			$bio                     = Editor\Bio_Document::changed_text( (string) ( $change['before']['bio'] ?? '' ), (string) $change['after']['bio'] );
			$change['before']['bio'] = $bio['before'];
			$change['after']['bio']  = $bio['after'];
		}
		$body = self::build_change_body( $change, home_url( '/' ), admin_url( 'post.php?action=edit&post=' . $change_id ) );

		return self::send( $recipients, $subject, $body, self::headers() );
	}

	/**
	 * One notice for the photos a professor changed in the last few minutes on one sede or profile.
	 *
	 * @param int[] $change_ids Recorded changes covered by this notice.
	 */
	public static function notify_photo_changes( int $post_id, array $change_ids ): bool {
		$recipients = self::editor_recipients();
		if ( ! $recipients ) {
			self::$last_error = 'No hay destinatarios configurados.';
			return false;
		}

		$actors = array();
		foreach ( $change_ids as $change_id ) {
			$change = Data\Change_Post_Type::to_array( (int) $change_id );
			if ( $change && '' !== $change['actor_email'] ) {
				$actors[ $change['actor_email'] ] = true;
			}
		}
		$photos  = Editor\Editor_Photos::readable( Editor\Editor_Photos::values( get_post( $post_id ) ) );
		$title   = (string) get_the_title( $post_id );
		$summary = array(
			'title'   => $title,
			'url'     => (string) get_permalink( $post_id ),
			'actors'  => array_keys( $actors ),
			'changes' => count( $change_ids ),
			'image'   => (string) ( $photos['image'] ?? '' ),
			'gallery' => isset( $photos['gallery'] ) ? (array) $photos['gallery'] : null,
		);

		return self::send(
			$recipients,
			sprintf( 'Fotos: %s', $title ),
			self::build_photo_changes_body( $summary, home_url( '/' ), admin_url( 'edit.php?post_type=' . Data\Change_Post_Type::POST_TYPE ) ),
			self::headers()
		);
	}

	/**
	 * Plain-text body of the joint photo notice. Pure: no WordPress calls.
	 *
	 * @param array<string, mixed> $summary   Keys: title, url, actors, changes, image, gallery (null when the post has none).
	 * @param string               $site_url  Site URL for the first line.
	 * @param string               $admin_url The list of changes, where each one can be restored.
	 */
	public static function build_photo_changes_body( array $summary, string $site_url, string $admin_url ): string {
		$lines = array(
			'Un profesor cambió fotos en ' . $site_url,
			'',
			'Ficha:   ' . (string) ( $summary['title'] ?? '' ),
			'Página:  ' . (string) ( $summary['url'] ?? '' ),
			'Quién:   ' . implode( ', ', (array) ( $summary['actors'] ?? array() ) ),
			'Cambios: ' . (int) ( $summary['changes'] ?? 0 ),
			'',
			'Foto principal ahora:',
			'  ' . ( '' !== (string) ( $summary['image'] ?? '' ) ? (string) $summary['image'] : '(sin foto)' ),
			'',
		);
		if ( is_array( $summary['gallery'] ?? null ) ) {
			$lines[] = sprintf( 'Galería ahora (%d):', count( $summary['gallery'] ) );
			foreach ( $summary['gallery'] ? $summary['gallery'] : array( '(vacía)' ) as $photo ) {
				$lines[] = '  ' . (string) $photo;
			}
			$lines[] = '';
		}
		$lines[] = 'Las fotos ya están publicadas. Cada cambio, con su antes y después, se puede revisar y restaurar aquí:';
		$lines[] = $admin_url;

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Plain-text body of the change notice. Pure: no WordPress calls.
	 *
	 * @param array<string, mixed> $change    Change (see Change_Post_Type::to_array()).
	 * @param string               $site_url  Site URL for the first line.
	 * @param string               $admin_url Where the administrator can review and restore it.
	 */
	public static function build_change_body( array $change, string $site_url, string $admin_url ): string {
		$restore = 'restore' === ( $change['source'] ?? '' );
		$lines   = array(
			( $restore ? 'Se restauraron datos en ' : 'Un profesor cambió datos en ' ) . $site_url,
			'',
			'Ficha:  ' . (string) ( $change['target_title'] ?? '' ),
			'Página: ' . (string) ( $change['target_url'] ?? '' ),
			'Quién:  ' . (string) ( $change['actor_email'] ?? '' ),
			'Cuándo: ' . (string) ( $change['when'] ?? '' ),
			'',
		);
		$lines   = array_merge(
			$lines,
			Editor\Change_Diff::describe(
				is_array( $change['before'] ?? null ) ? $change['before'] : array(),
				is_array( $change['after'] ?? null ) ? $change['after'] : array()
			)
		);
		$lines[] = 'El cambio ya está publicado. Para revisarlo o restaurar los valores anteriores:';
		$lines[] = $admin_url;

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Tell the recipients that a colleague finished uploading photos.
	 */
	public static function notify_photo_batch( int $post_id ): bool {
		$data = Data\Photo_Batch_Repository::to_array( $post_id );
		if ( ! $data ) {
			return false;
		}

		$recipients = self::photo_recipients();
		if ( ! $recipients ) {
			self::$last_error = 'No hay destinatarios configurados.';
			return false;
		}

		$where   = '' !== $data['sede_name'] ? ' (' . $data['sede_name'] . ')' : '';
		$subject = sprintf( 'Fotos recibidas: %s%s — %d %s', $data['name'], $where, $data['file_count'], 1 === (int) $data['file_count'] ? 'foto' : 'fotos' );
		$body    = self::build_photo_body( $data, home_url( '/' ), wp_date( 'Y-m-d H:i:s' ) );
		$headers = self::headers();

		$contact = trim( (string) $data['contact'] );
		if ( is_email( $contact ) ) {
			$headers[] = 'Reply-To: ' . str_replace( array( "\r", "\n" ), '', (string) $data['name'] ) . ' <' . sanitize_email( $contact ) . '>';
		}

		return self::send( $recipients, $subject, $body, $headers );
	}

	/**
	 * Plain-text body of the "fotos recibidas" mail. Pure: no WordPress calls.
	 *
	 * @param array<string, mixed> $data     Batch (see Photo_Batch_Repository::to_array()).
	 * @param string               $site_url Site URL for the first line.
	 * @param string               $when     Fallback timestamp.
	 */
	public static function build_photo_body( array $data, string $site_url, string $when ): string {
		$count = (int) ( $data['file_count'] ?? 0 );
		$lines = array( 'Nuevas fotos de un profesor o instructor en ' . $site_url, '' );

		$rows = array(
			'Nombre'         => (string) ( $data['name'] ?? '' ),
			'Perfil'         => (string) ( $data['profesor_name'] ?? '' ),
			'Sede'           => (string) ( $data['sede_name'] ?? '' ),
			'Contacto'       => (string) ( $data['contact'] ?? '' ),
			'Fotos'          => $count . ( ! empty( $data['profile_photo_id'] ) ? ' (incluye foto de perfil)' : '' ),
			'Nota'           => (string) ( $data['note'] ?? '' ),
			'Fecha de envío' => (string) ( $data['created_at'] ?? $when ),
		);

		foreach ( $rows as $label => $value ) {
			if ( '' === trim( $value ) ) {
				continue;
			}
			$lines[] = $label . ': ' . $value;
		}

		if ( ! empty( $data['admin_url'] ) ) {
			$lines[] = '';
			$lines[] = 'Ver las fotos y aplicarlas al perfil o a la sede: ' . $data['admin_url'];
		}

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Send the six-digit sign-in code to the person who asked for it.
	 *
	 * @param string $lang es, en or pt.
	 */
	public static function send_login_code( string $email, string $code, string $lang = 'es' ): bool {
		$mail = self::build_login_code_mail( $code, $lang, home_url( '/' ), (int) ( Auth\Email_Code::TTL / 60 ) );

		return self::send( array( $email ), $mail['subject'], $mail['body'], self::headers() );
	}

	/**
	 * Subject and plain-text body of the sign-in code mail. Pure: no WordPress calls.
	 *
	 * @return array{subject:string,body:string}
	 */
	public static function build_login_code_mail( string $code, string $lang, string $site_url, int $minutes ): array {
		$texts = array(
			'es' => array(
				'%s es tu código de Pura Capoeira',
				'Tu código para entrar en %s',
				'Vale %d minutos y solo se puede usar una vez.',
				'Si no lo pediste, ignora este correo: nadie entra sin el código.',
			),
			'en' => array(
				'%s is your Pura Capoeira code',
				'Your code to sign in to %s',
				'It is valid for %d minutes and can be used only once.',
				'If you did not ask for it, ignore this email: nobody can sign in without the code.',
			),
			'pt' => array(
				'%s é o seu código da Pura Capoeira',
				'Seu código para entrar em %s',
				'Vale por %d minutos e só pode ser usado uma vez.',
				'Se você não pediu, ignore este e-mail: ninguém entra sem o código.',
			),
		);
		$text  = $texts[ $lang ] ?? $texts['es'];

		return array(
			'subject' => sprintf( $text[0], $code ),
			'body'    => implode(
				"\n",
				array(
					sprintf( $text[1], $site_url ),
					'',
					'    ' . $code,
					'',
					sprintf( $text[2], $minutes ),
					$text[3],
				)
			) . "\n",
		);
	}

	/**
	 * Answer a sign-in request for a staff address: those accounts do not sign in with a code.
	 * Sent instead of a code, so the page never tells a stranger which addresses are staff.
	 */
	public static function send_staff_login_notice( string $email ): bool {
		$body = implode(
			"\n",
			array(
				'Alguien pidió un código para entrar en ' . home_url( '/' ) . ' con este correo.',
				'',
				'Este correo pertenece a una cuenta del equipo de Pura Capoeira, que no entra con código.',
				'Entra por el acceso de administración: ' . wp_login_url(),
				'',
				'Para editar una sede o un perfil como profesor, usa otro correo y pide a la administración que lo vincule.',
				'Si no fuiste tú, ignora este correo.',
			)
		) . "\n";

		return self::send( array( $email ), 'Pura Capoeira: este correo entra por el acceso de administración', $body, self::headers() );
	}

	/**
	 * Send a validated contact message (see Contact_Message::from_array()) to the recipients.
	 *
	 * @param array<string, string> $data nombre, ciudad, telefono, email, mensaje.
	 */
	public static function send_contact( array $data ): bool {
		$recipients = self::recipients();
		if ( ! $recipients ) {
			self::$last_error = 'No hay destinatarios configurados.';
			return false;
		}

		$name    = trim( (string) ( $data['nombre'] ?? '' ) );
		$subject = 'Pura Capoeira — Mensaje de ' . $name;
		if ( '' !== trim( (string) ( $data['ciudad'] ?? '' ) ) ) {
			$subject .= ' (' . trim( (string) $data['ciudad'] ) . ')';
		}

		$body    = self::build_contact_body( $data, home_url( '/' ), wp_date( 'Y-m-d H:i:s' ) );
		$headers = self::headers();

		$email = sanitize_email( (string) ( $data['email'] ?? '' ) );
		if ( is_email( $email ) ) {
			$headers[] = 'Reply-To: ' . str_replace( array( "\r", "\n" ), '', $name ) . ' <' . $email . '>';
		}

		return self::send( $recipients, $subject, $body, $headers );
	}

	/**
	 * Notify the recipients about an event registration, with a copy to the person who registered.
	 */
	public static function notify_event_registration( int $post_id ): bool {
		$data = Data\Event_Registration_Repository::to_array( $post_id );
		if ( ! $data ) {
			return false;
		}

		$recipients = self::event_recipients();
		if ( ! $recipients ) {
			self::$last_error = 'No hay destinatarios configurados.';
			return false;
		}

		$event   = '' !== $data['event_name'] ? $data['event_name'] : $data['event'];
		$name    = '' !== $data['name'] ? $data['name'] : $data['email'];
		$subject = sprintf( 'Registro al evento %s — %s', $event, $name );
		$body    = self::build_event_body( $data, home_url( '/' ), wp_date( 'Y-m-d H:i:s' ) );
		$headers = self::headers();

		$email = sanitize_email( (string) $data['email'] );
		if ( is_email( $email ) ) {
			$headers[] = 'Reply-To: ' . str_replace( array( "\r", "\n" ), '', (string) $name ) . ' <' . $email . '>';
			if ( Settings::get( 'cc_registrant', true ) && ! in_array( strtolower( $email ), array_map( 'strtolower', $recipients ), true ) ) {
				$headers[] = 'Cc: ' . $email;
			}
		}

		$attachments = array();
		if ( ! empty( $data['payment_proof_id'] ) ) {
			$path = (string) get_attached_file( (int) $data['payment_proof_id'] );
			if ( '' !== $path && is_readable( $path ) ) {
				$attachments[] = $path;
			}
		}

		return self::send( $recipients, $subject, $body, $headers, $attachments );
	}

	/**
	 * Plain-text body of the event registration mail. Pure: no WordPress calls.
	 *
	 * @param array<string, mixed> $data     Registration (see Event_Registration_Repository::to_array()).
	 * @param string               $site_url Site URL for the first line.
	 * @param string               $when     Fallback timestamp.
	 */
	public static function build_event_body( array $data, string $site_url, string $when ): string {
		$event = '' !== (string) ( $data['event_name'] ?? '' ) ? (string) $data['event_name'] : (string) ( $data['event'] ?? '' );
		$lines = array( 'Nuevo registro al evento ' . $event . ' recibido en ' . $site_url, '' );

		$rows = array(
			'Evento'                 => $event,
			'Nombre'                 => (string) ( $data['name'] ?? '' ),
			'Apelido'                => (string) ( $data['nickname'] ?? '' ),
			'Correo'                 => (string) ( $data['email'] ?? '' ),
			'Teléfono / WhatsApp'    => (string) ( $data['phone'] ?? '' ),
			'Fecha de nacimiento'    => (string) ( $data['dob'] ?? '' ),
			'Padre/madre/tutor'      => (string) ( $data['parent_name'] ?? '' ),
			'Teléfono del tutor'     => (string) ( $data['parent_phone'] ?? '' ),
			'Ciudad'                 => (string) ( $data['city'] ?? '' ),
			'Grupo / academia'       => (string) ( $data['academy'] ?? '' ),
			'Mestre / Professor'     => (string) ( $data['teacher'] ?? '' ),
			'Graduación'             => (string) ( $data['graduation'] ?? '' ),
			'Empezó capoeira en'     => (string) ( $data['started_year'] ?? '' ),
			'Años de entrenamiento'  => (string) ( $data['years_training'] ?? '' ),
			'Días'                   => (string) ( $data['days'] ?? '' ),
			'Talla de playera'       => (string) ( $data['shirt_size'] ?? '' ),
			'Contacto de emergencia' => (string) ( $data['emergency_name'] ?? '' ),
			'Teléfono de emergencia' => (string) ( $data['emergency_phone'] ?? '' ),
			'Comentarios'            => (string) ( $data['notes'] ?? '' ),
			'Comprobante de pago'    => (string) ( $data['payment_proof_url'] ?? '' ),
			'Fecha de registro'      => (string) ( $data['created_at'] ?? $when ),
		);

		foreach ( $rows as $label => $value ) {
			if ( '' === trim( $value ) ) {
				continue;
			}
			$lines[] = $label . ': ' . $value;
		}

		if ( ! empty( $data['admin_url'] ) ) {
			$lines[] = '';
			$lines[] = 'Ver en el panel: ' . $data['admin_url'];
		}

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Plain-text body. Pure: no WordPress calls, so it is unit-tested.
	 *
	 * @param array<string, string> $data     Contact fields.
	 * @param string                $site_url Site URL for the first line.
	 * @param string                $when     Formatted timestamp.
	 */
	public static function build_contact_body( array $data, string $site_url, string $when ): string {
		$lines = array( 'Nuevo mensaje del formulario de contacto de ' . $site_url, '' );

		$rows = array(
			'Nombre'              => (string) ( $data['nombre'] ?? '' ),
			'Ciudad'              => (string) ( $data['ciudad'] ?? '' ),
			'Teléfono o WhatsApp' => (string) ( $data['telefono'] ?? '' ),
			'Correo'              => (string) ( $data['email'] ?? '' ),
			'Fecha'               => $when,
		);

		foreach ( $rows as $label => $value ) {
			if ( '' === trim( $value ) ) {
				continue;
			}
			$lines[] = $label . ': ' . $value;
		}

		$lines[] = '';
		$lines[] = 'Mensaje:';
		$lines[] = (string) ( $data['mensaje'] ?? '' );

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * @return array{ok:bool,sent_count:int,total:int,message:string}
	 */
	public static function send_test(): array {
		$recipients = self::recipients();
		if ( ! $recipients ) {
			return array(
				'ok'         => false,
				'sent_count' => 0,
				'total'      => 0,
				'message'    => 'No hay destinatarios configurados.',
			);
		}

		$subject = 'Prueba del formulario de contacto — Pura Capoeira';
		$body    = 'Este es un correo de prueba enviado desde ' . home_url( '/' ) . ' el ' . wp_date( 'Y-m-d H:i:s' ) . ".\n\nSi lo recibes, los mensajes del formulario de contacto llegarán a esta dirección.";
		$ok      = self::send( $recipients, $subject, $body, self::headers() );

		return array(
			'ok'         => $ok,
			'sent_count' => $ok ? count( $recipients ) : 0,
			'total'      => count( $recipients ),
			'message'    => $ok ? 'OK' : (string) ( self::$last_error ?? 'wp_mail devolvió false' ),
		);
	}

	public static function last_error(): ?string {
		return self::$last_error;
	}

	/**
	 * @return string[]
	 */
	private static function headers(): array {
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		$from_email = sanitize_email( (string) Settings::get( 'contact_from_email', '' ) );
		$from_name  = (string) Settings::get( 'contact_from_name', 'Pura Capoeira' );
		if ( is_email( $from_email ) ) {
			$headers[] = sprintf( 'From: %s <%s>', str_replace( array( "\r", "\n" ), '', $from_name ), $from_email );
		}

		return $headers;
	}

	/**
	 * @param string[] $to          Recipients.
	 * @param string[] $headers     Headers.
	 * @param string[] $attachments Absolute paths.
	 */
	private static function send( array $to, string $subject, string $body, array $headers, array $attachments = array() ): bool {
		self::$last_error = null;
		$capture          = static function ( \WP_Error $error ): void {
			self::$last_error = $error->get_error_message();
		};

		add_action( 'wp_mail_failed', $capture );
		$ok = wp_mail( $to, $subject, $body, $headers, $attachments );
		remove_action( 'wp_mail_failed', $capture );

		if ( ! $ok ) {
			error_log( '[pura] wp_mail failed: ' . ( self::$last_error ?? 'unknown' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		return $ok;
	}
}
