<?php
/**
 * Block pura/profesor-profile — hero text, photo + caption, or social grid of the current teacher.
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @var WP_Block             $block      Block instance.
 * @package Pura
 */

declare( strict_types=1 );

require_once PURA_THEME_DIR . '/src/shared/notice.php';

if ( pura_theme_plugin_notice( 'Pura\Core\Data\Profesor_Post_Type' ) ) {
	return;
}

$pura_post = pura_theme_current_post( $block, \Pura\Core\Data\Profesor_Post_Type::POST_TYPE );
if ( ! $pura_post ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<p class="pura-notice">' . esc_html__( 'Este bloque muestra el profesor actual; úsalo en la plantilla de profesor.', 'pura' ) . '</p>';
	}
	return;
}

$pura_p    = \Pura\Core\Data\Profesor_Post_Type::get_data( $pura_post );
$pura_part = (string) ( $attributes['part'] ?? 'photo' );
if ( ! in_array( $pura_part, array( 'hero-text', 'photo', 'social' ), true ) ) {
	$pura_part = 'photo';
}

if ( 'hero-text' === $pura_part ) :
	$pura_eyebrow = '' !== $pura_p['hero_eyebrow'] ? $pura_p['hero_eyebrow'] : trim( $pura_p['rank'] . ' · ' . $pura_p['city'] . ( '' !== $pura_p['country'] ? ', ' . $pura_p['country'] : '' ), ' ·' );
	?>
	<div <?php echo get_block_wrapper_attributes( array( 'class' => 'prof-hero' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<p class="eyebrow" data-i18n-key="hero-eyebrow"><?php echo esc_html( $pura_eyebrow ); ?></p>
		<h1 class="display"><?php echo esc_html( $pura_p['name'] ); ?></h1>
		<?php if ( '' !== $pura_p['subtitle'] ) : ?>
			<p data-i18n-key="hero-intro"><?php echo esc_html( $pura_p['subtitle'] ); ?></p>
		<?php endif; ?>
	</div>
	<?php
	return;
endif;

if ( 'photo' === $pura_part ) :
	$pura_caption = '' !== $pura_p['caption'] ? $pura_p['caption'] : trim( $pura_p['city'] . ( '' !== $pura_p['country'] ? ', ' . $pura_p['country'] : '' ), ', ' );
	?>
	<div <?php echo get_block_wrapper_attributes( array( 'class' => 'prof-profile__hero' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<div class="prof-profile__image">
			<?php
			echo pura_theme_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the helper.
				(int) $pura_p['image_id'],
				'large',
				array(
					'alt'     => $pura_p['name'],
					'loading' => 'eager',
				),
				'assets/images/profesor-placeholder.jpg'
			);
			?>
		</div>
		<div class="prof-profile__caption">
			<h2><?php echo esc_html( $pura_p['name'] ); ?></h2>
			<p data-i18n-key="caption"><?php echo esc_html( $pura_caption ); ?></p>
		</div>
	</div>
	<?php
	return;
endif;

$pura_icons = array(
	'instagram' => '<svg class="prof-social__icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5" stroke="currentColor" stroke-width="1.7"/><circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.7"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor"/></svg>',
	'youtube'   => '<svg class="prof-social__icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="4" stroke="currentColor" stroke-width="1.7"/><path d="M10 9L16 12L10 15V9Z" fill="currentColor"/></svg>',
	'facebook'  => '<svg class="prof-social__icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M14 8H16V5H14C11.8 5 10 6.8 10 9V11H8V14H10V19H13V14H15.2L16 11H13V9C13 8.45 13.45 8 14 8Z" fill="currentColor"/></svg>',
	'website'   => '<svg class="prof-social__icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.7"/><path d="M3.5 12H20.5" stroke="currentColor" stroke-width="1.7"/><path d="M12 3C14.8 5.4 14.8 18.6 12 21" stroke="currentColor" stroke-width="1.7"/><path d="M12 3C9.2 5.4 9.2 18.6 12 21" stroke="currentColor" stroke-width="1.7"/></svg>',
	'whatsapp'  => '<svg class="prof-social__icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M6 18L6.6 14.8C5.6 13.8 5 12.4 5 10.9C5 7.6 7.9 5 11.5 5C15.1 5 18 7.6 18 10.9C18 14.2 15.1 16.8 11.5 16.8C10.1 16.8 8.8 16.4 7.8 15.7L6 18Z" stroke="currentColor" stroke-width="1.7"/><path d="M9.3 9.2C9.4 8.9 9.6 8.8 9.8 8.8H10.3C10.5 8.8 10.7 9 10.8 9.2L11.2 10.4C11.3 10.7 11.2 11 11 11.2L10.6 11.7C11 12.4 11.6 13 12.3 13.4L12.8 13C13 12.8 13.3 12.7 13.6 12.8L14.8 13.2C15 13.3 15.2 13.5 15.2 13.7V14.2C15.2 14.4 15.1 14.6 14.8 14.7C14.4 14.9 14 15 13.6 15C11.1 15 9 12.9 9 10.4C9 10 9.1 9.6 9.3 9.2Z" fill="currentColor"/></svg>',
);

$pura_website_label = '';
if ( '' !== $pura_p['website'] ) {
	$pura_website_label = (string) ( wp_parse_url( $pura_p['website'], PHP_URL_HOST ) ?: $pura_p['website'] );
}

$pura_items = array(
	array( 'instagram', 'Instagram', $pura_p['instagram'], '' !== $pura_p['instagram_handle'] ? $pura_p['instagram_handle'] : 'Instagram', '' ),
	array( 'youtube', 'YouTube', $pura_p['youtube'], 'YouTube', '' ),
	array( 'facebook', 'Facebook', $pura_p['facebook'], __( 'Ver perfil', 'pura' ), 'common.seeProfile' ),
	array( 'website', __( 'Sitio web', 'pura' ), $pura_p['website'], $pura_website_label, '' ),
	array( 'whatsapp', 'WhatsApp', $pura_p['whatsapp'], '' !== $pura_p['whatsapp_display'] ? $pura_p['whatsapp_display'] : 'WhatsApp', '' ),
);
?>
<section <?php echo get_block_wrapper_attributes( array( 'class' => 'prof-social' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<h3 data-i18n="common.socialTitle"><?php esc_html_e( 'Redes y contacto', 'pura' ); ?></h3>
	<div class="prof-social__grid">
		<?php foreach ( $pura_items as [ $pura_key, $pura_label, $pura_href, $pura_text, $pura_text_i18n ] ) : ?>
			<div class="prof-social__item">
				<span class="prof-social__label"><?php echo $pura_icons[ $pura_key ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?> <span data-i18n="common.<?php echo esc_attr( $pura_key ); ?>"><?php echo esc_html( $pura_label ); ?></span></span>
				<?php if ( '' !== $pura_href ) : ?>
					<a href="<?php echo esc_url( $pura_href ); ?>" target="_blank" rel="noopener noreferrer"<?php echo '' !== $pura_text_i18n ? ' data-i18n="' . esc_attr( $pura_text_i18n ) . '"' : ''; ?>><?php echo esc_html( $pura_text ); ?></a>
				<?php else : ?>
					<span class="prof-social__pending" data-i18n="common.pendingLink"><?php esc_html_e( 'Enlace por confirmar', 'pura' ); ?></span>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
</section>
