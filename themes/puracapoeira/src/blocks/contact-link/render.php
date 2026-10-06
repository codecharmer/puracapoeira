<?php
/**
 * Block pura/contact-link — a link to a configured channel (WhatsApp, Instagram, Facebook, YouTube, iCloud).
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @package Pura
 */

declare( strict_types=1 );

require_once PURA_THEME_DIR . '/src/shared/notice.php';

if ( ! function_exists( 'pura_setting' ) ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<p class="pura-notice">' . esc_html__( 'Activa el plugin Pura Capoeira Core para mostrar este bloque.', 'pura' ) . '</p>';
	}
	return;
}

$pura_channel = (string) ( $attributes['channel'] ?? 'whatsapp' );
$pura_label   = trim( (string) ( $attributes['label'] ?? '' ) );
$pura_show    = ! empty( $attributes['showValue'] );
$pura_variant = (string) ( $attributes['variant'] ?? 'link' );
$pura_message = (string) ( $attributes['message'] ?? '' );

switch ( $pura_channel ) {
	case 'instagram':
		$pura_url   = (string) pura_setting( 'instagram_url', '' );
		$pura_value = (string) pura_setting( 'instagram_handle', '' );
		$pura_name  = 'Instagram';
		break;
	case 'facebook':
		$pura_url   = (string) pura_setting( 'facebook_url', '' );
		$pura_value = (string) pura_setting( 'facebook_label', '' );
		$pura_name  = 'Facebook';
		break;
	case 'youtube':
		$pura_url   = (string) pura_setting( 'youtube_url', '' );
		$pura_value = '';
		$pura_name  = 'YouTube';
		break;
	case 'icloud':
		$pura_url   = (string) pura_setting( 'icloud_album_url', '' );
		$pura_value = '';
		$pura_name  = __( 'Álbum de iCloud', 'pura' );
		break;
	default:
		$pura_channel = 'whatsapp';
		$pura_url     = '' !== \Pura\Core\Settings::whatsapp_number() ? pura_whatsapp_url( $pura_message ) : '';
		$pura_value   = (string) pura_setting( 'whatsapp_display', '' );
		$pura_name    = 'WhatsApp';
}

if ( '' === $pura_url ) {
	return;
}

$pura_classes = array(
	'btn-primary' => 'btn btn--primary',
	'btn-ghost'   => 'btn btn--ghost',
	'btn-outline' => 'btn btn--outline',
);
$pura_text    = '' !== $pura_label ? $pura_label : $pura_name;
if ( $pura_show && '' !== $pura_value ) {
	$pura_text .= ': ' . $pura_value;
}
?>
<a <?php echo get_block_wrapper_attributes( array( 'class' => $pura_classes[ $pura_variant ] ?? 'contact-link' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> href="<?php echo esc_url( $pura_url ); ?>" target="_blank" rel="noopener noreferrer"<?php echo '' === $pura_label ? ' data-i18n="common.' . esc_attr( $pura_channel ) . '"' : ''; ?>><?php echo esc_html( $pura_text ); ?></a>
