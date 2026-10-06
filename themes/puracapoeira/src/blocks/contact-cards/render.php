<?php
/**
 * Block pura/contact-cards — one contact card per sede.
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @package Pura
 */

declare( strict_types=1 );

require_once PURA_THEME_DIR . '/src/shared/notice.php';

if ( pura_theme_plugin_notice( 'Pura\Core\Data\Sede_Post_Type' ) ) {
	return;
}

$pura_sedes = \Pura\Core\Data\Sede_Post_Type::all();
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'contact-card-list' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( ! $pura_sedes ) : ?>
		<p class="fallback" data-i18n="common.fallbackContacts"><?php esc_html_e( 'No fue posible cargar la información de contacto.', 'pura' ); ?></p>
	<?php endif; ?>
	<?php
	foreach ( $pura_sedes as $pura_post ) :
		$pura_s = \Pura\Core\Data\Sede_Post_Type::get_data( $pura_post );
		?>
		<article class="contact-card">
			<span class="eyebrow" data-tv><?php echo esc_html( $pura_s['country'] ); ?></span>
			<h3 data-tv><?php echo esc_html( $pura_s['name'] ); ?></h3>
			<p class="contact-card__meta" data-tv><?php echo esc_html( $pura_s['responsible'] ); ?></p>
			<p class="contact-card__meta" data-tv><?php echo esc_html( $pura_s['address'] ); ?></p>
			<div class="contact-card__links">
				<?php if ( '' !== $pura_s['whatsapp'] ) : ?>
					<a href="<?php echo esc_url( $pura_s['whatsapp'] ); ?>" target="_blank" rel="noopener noreferrer" data-i18n="common.whatsapp">WhatsApp</a>
				<?php else : ?>
					<span class="tag" data-i18n="common.pendingLink"><?php esc_html_e( 'Enlace por confirmar', 'pura' ); ?></span>
				<?php endif; ?>
				<?php if ( '' !== $pura_s['instagram'] ) : ?>
					<a href="<?php echo esc_url( $pura_s['instagram'] ); ?>" target="_blank" rel="noopener noreferrer" data-i18n="common.instagram">Instagram</a>
				<?php endif; ?>
				<?php if ( '' !== $pura_s['facebook_url'] ) : ?>
					<a href="<?php echo esc_url( $pura_s['facebook_url'] ); ?>" target="_blank" rel="noopener noreferrer" data-i18n="common.facebook">Facebook</a>
				<?php endif; ?>
				<a href="<?php echo esc_url( $pura_s['url'] ); ?>" data-i18n="common.seeLocation"><?php esc_html_e( 'Ver sede', 'pura' ); ?></a>
			</div>
		</article>
	<?php endforeach; ?>
</div>
