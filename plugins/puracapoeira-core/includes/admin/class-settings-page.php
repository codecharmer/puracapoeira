<?php
/**
 * "Pura Capoeira → Ajustes" settings page (Settings API).
 *
 * A setting pinned by a `PURA_*` constant in wp-config.php is shown locked.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Admin;

use Pura\Core\Mailer;
use Pura\Core\Settings;

defined( 'ABSPATH' ) || exit;

final class Settings_Page {

	public const GROUP = 'pura';

	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_pura_test_mail', array( $this, 'handle_test_mail' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_media' ) );
	}

	public function enqueue_media( string $hook ): void {
		if ( 'toplevel_page_' . Menu::SLUG !== $hook ) {
			return;
		}

		wp_enqueue_media();
		wp_add_inline_script(
			'media-editor',
			'(function(){document.addEventListener("click",function(e){var pick=e.target.closest(".pura-media-pick");var clear=e.target.closest(".pura-media-clear");if(!pick&&!clear){return;}e.preventDefault();var wrap=(pick||clear).closest(".pura-media");var input=wrap.querySelector("input[type=hidden]");var preview=wrap.querySelector(".pura-media-preview");if(clear){input.value="0";preview.innerHTML="";return;}var frame=wp.media({title:"Elegir imagen",multiple:false,library:{type:"image"}});frame.on("select",function(){var att=frame.state().get("selection").first().toJSON();input.value=att.id;var src=(att.sizes&&att.sizes.medium?att.sizes.medium.url:att.url);preview.innerHTML="<img src=\""+src+"\" alt=\"\" />";});frame.open();});})();'
		);
	}

	public function register_settings(): void {
		register_setting(
			self::GROUP,
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => Settings::defaults(),
			)
		);

		$sections = array(
			'contacto' => array(
				'title'  => __( 'Formulario de contacto', 'pura' ),
				'fields' => array(
					'contact_to_emails'  => array( 'text', __( 'Destinatarios', 'pura' ), __( 'Correos separados por comas. Reciben los mensajes del formulario.', 'pura' ) ),
					'contact_from_email' => array( 'email', __( 'Remitente', 'pura' ), __( 'Déjalo vacío para usar el remitente configurado en FluentSMTP.', 'pura' ) ),
					'contact_from_name'  => array( 'text', __( 'Nombre del remitente', 'pura' ) ),
					'_test_mail'         => array( 'test_mail', __( 'Correo de prueba', 'pura' ) ),
				),
			),
			'redes'    => array(
				'title'  => __( 'Redes y contacto general', 'pura' ),
				'fields' => array(
					'whatsapp_number'  => array( 'text', __( 'WhatsApp (número internacional, solo dígitos)', 'pura' ) ),
					'whatsapp_display' => array( 'text', __( 'WhatsApp (como se muestra)', 'pura' ) ),
					'instagram_url'    => array( 'url', __( 'Instagram (URL)', 'pura' ) ),
					'instagram_handle' => array( 'text', __( 'Instagram (usuario)', 'pura' ) ),
					'facebook_url'     => array( 'url', __( 'Facebook (URL)', 'pura' ) ),
					'facebook_label'   => array( 'text', __( 'Facebook (nombre)', 'pura' ) ),
					'youtube_url'      => array( 'url', __( 'YouTube (URL)', 'pura' ) ),
					'icloud_album_url' => array( 'url', __( 'Álbum compartido de iCloud (galería)', 'pura' ) ),
				),
			),
			'sitio'    => array(
				'title'  => __( 'Sitio', 'pura' ),
				'fields' => array(
					'tagline'     => array( 'textarea', __( 'Texto del pie de página', 'pura' ) ),
					'motto'       => array( 'text', __( 'Lema (pie de página)', 'pura' ) ),
					'og_image_id' => array( 'media', __( 'Imagen para compartir (Open Graph)', 'pura' ), __( 'Recomendado 1200 × 630 px. Se usa cuando la página no tiene imagen destacada.', 'pura' ) ),
				),
			),
		);

		foreach ( $sections as $id => $section ) {
			add_settings_section( 'pura_' . $id, $section['title'], '__return_null', self::GROUP );
			foreach ( $section['fields'] as $key => $def ) {
				add_settings_field(
					'pura_' . $key,
					$def[1],
					array( $this, 'render_field' ),
					self::GROUP,
					'pura_' . $id,
					array(
						'key'         => $key,
						'type'        => $def[0],
						'description' => $def[2] ?? '',
						'label_for'   => in_array( $def[0], array( 'test_mail', 'media' ), true ) ? '' : 'pura_' . $key,
					)
				);
			}
		}
	}

	/**
	 * @param array<string, string> $args Field args.
	 */
	public function render_field( array $args ): void {
		$key    = $args['key'];
		$type   = $args['type'];
		$id     = 'pura_' . $key;
		$name   = Settings::OPTION . '[' . $key . ']';
		$locked = 'constant' === Settings::source( $key );

		switch ( $type ) {
			case 'test_mail':
				$url = wp_nonce_url( admin_url( 'admin-post.php?action=pura_test_mail' ), 'pura_test_mail' );
				echo '<a href="' . esc_url( $url ) . '" class="button">' . esc_html__( 'Enviar correo de prueba a los destinatarios', 'pura' ) . '</a>';
				echo '<p class="description">' . esc_html__( 'Guarda los cambios antes de probar.', 'pura' ) . '</p>';
				return;

			case 'media':
				$image_id = (int) Settings::get( $key, 0 );
				echo '<div class="pura-media">';
				echo '<div class="pura-media-preview">';
				if ( $image_id ) {
					echo wp_get_attachment_image( $image_id, 'medium' );
				}
				echo '</div>';
				echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $image_id ) . '" />';
				echo '<button type="button" class="button pura-media-pick">' . esc_html__( 'Elegir imagen', 'pura' ) . '</button> ';
				echo '<button type="button" class="button-link pura-media-clear">' . esc_html__( 'Quitar', 'pura' ) . '</button>';
				echo '</div>';
				break;

			case 'textarea':
				echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="3" class="large-text"' . disabled( $locked, true, false ) . '>' . esc_textarea( (string) Settings::get( $key, '' ) ) . '</textarea>';
				break;

			case 'url':
			case 'email':
			case 'text':
			default:
				echo '<input type="' . esc_attr( 'text' === $type ? 'text' : $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) Settings::get( $key, '' ) ) . '" class="regular-text' . ( $locked ? ' pura-locked' : '' ) . '"' . disabled( $locked, true, false ) . ' />';
				break;
		}

		if ( $locked ) {
			echo '<p class="description">' . esc_html__( 'Definido en wp-config.php', 'pura' ) . ' (<code>' . esc_html( Settings::constant_name( $key ) ) . '</code>).</p>';
		} elseif ( ! empty( $args['description'] ) ) {
			echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
		}
	}

	/**
	 * @param mixed $input Submitted pura_settings.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$stored   = get_option( Settings::OPTION, array() );
		$current  = array_merge( Settings::defaults(), is_array( $stored ) ? $stored : array() );
		$defaults = Settings::defaults();
		$out      = array();

		foreach ( $defaults as $key => $default ) {
			// Locked (constant) fields are disabled in the form and never submitted: keep the stored value.
			if ( ! array_key_exists( $key, $input ) ) {
				$out[ $key ] = $current[ $key ];
				continue;
			}

			$raw = $input[ $key ];
			if ( 'og_image_id' === $key ) {
				$out[ $key ] = max( 0, (int) $raw );
			} elseif ( is_int( $default ) ) {
				$out[ $key ] = max( 0, (int) $raw );
			} elseif ( str_ends_with( $key, '_url' ) ) {
				$out[ $key ] = esc_url_raw( (string) $raw );
			} elseif ( 'contact_to_emails' === $key ) {
				$emails      = array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', (string) $raw ) ) ) );
				$out[ $key ] = implode( ', ', array_filter( $emails, 'is_email' ) );
			} elseif ( 'contact_from_email' === $key ) {
				$out[ $key ] = sanitize_email( (string) $raw );
			} elseif ( 'tagline' === $key ) {
				$out[ $key ] = sanitize_textarea_field( (string) $raw );
			} elseif ( 'whatsapp_number' === $key ) {
				$out[ $key ] = preg_replace( '/\D+/', '', (string) $raw );
			} else {
				$out[ $key ] = sanitize_text_field( (string) $raw );
			}
		}

		return $out;
	}

	public function handle_test_mail(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sin permisos.', 'pura' ) );
		}
		check_admin_referer( 'pura_test_mail' );

		$result  = Mailer::send_test();
		$message = $result['ok']
			/* translators: %d: number of recipients. */
			? sprintf( __( 'Correo de prueba enviado a %d destinatario(s).', 'pura' ), (int) $result['sent_count'] )
			: __( 'No se pudo enviar el correo de prueba: ', 'pura' ) . $result['message'];

		set_transient(
			'pura_settings_notice_' . get_current_user_id(),
			array(
				'ok'      => $result['ok'],
				'message' => $message,
			),
			60
		);
		wp_safe_redirect( admin_url( 'admin.php?page=' . Menu::SLUG ) );
		exit;
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$notice = get_transient( 'pura_settings_notice_' . get_current_user_id() );
		if ( is_array( $notice ) ) {
			delete_transient( 'pura_settings_notice_' . get_current_user_id() );
			echo '<div class="notice ' . ( $notice['ok'] ? 'notice-success' : 'notice-error' ) . ' is-dismissible"><p>' . esc_html( (string) $notice['message'] ) . '</p></div>';
		}
		?>
		<div class="wrap pura-settings">
			<h1><?php esc_html_e( 'Pura Capoeira — Ajustes', 'pura' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::GROUP );
				submit_button( __( 'Guardar cambios', 'pura' ) );
				?>
			</form>
		</div>
		<?php
	}
}
