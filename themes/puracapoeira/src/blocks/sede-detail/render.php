<?php
/**
 * Block pura/sede-detail — hero text or body (image, schedule, pricing, contact) of the current sede.
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @var WP_Block             $block      Block instance.
 * @package Pura
 */

declare( strict_types=1 );

require_once PURA_THEME_DIR . '/src/shared/notice.php';

if ( pura_theme_plugin_notice( 'Pura\Core\Data\Sede_Post_Type' ) ) {
	return;
}

$pura_post = pura_theme_current_post( $block, \Pura\Core\Data\Sede_Post_Type::POST_TYPE );
if ( ! $pura_post ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<p class="pura-notice">' . esc_html__( 'Este bloque muestra la sede actual; úsalo en la plantilla de sede.', 'pura' ) . '</p>';
	}
	return;
}

$pura_s    = \Pura\Core\Data\Sede_Post_Type::get_data( $pura_post );
$pura_part = 'hero' === ( $attributes['part'] ?? 'body' ) ? 'hero' : 'body';

if ( 'hero' === $pura_part ) :
	?>
	<div <?php echo get_block_wrapper_attributes( array( 'class' => 'sede-hero' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<p class="eyebrow"><span data-i18n="common.sede"><?php esc_html_e( 'Sede', 'pura' ); ?></span> · <span data-tv><?php echo esc_html( $pura_s['city'] ); ?></span> · <span data-tv><?php echo esc_html( $pura_s['country'] ); ?></span></p>
		<h1 class="display" data-tv><?php echo esc_html( $pura_s['name'] ); ?></h1>
		<p class="muted"><span data-tv><?php echo esc_html( $pura_s['region'] ); ?></span> · <span data-tv><?php echo esc_html( $pura_s['country'] ); ?></span></p>
	</div>
	<?php
	return;
endif;
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'sede-detail' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-slug="<?php echo esc_attr( $pura_s['slug'] ); ?>">
	<div class="sede-detail__img">
		<?php
		echo pura_theme_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the helper.
			(int) $pura_s['image_id'],
			'large',
			array(
				'alt'          => $pura_s['name'],
				'data-tv-attr' => 'alt',
				'loading'      => 'eager',
			)
		);
		?>
	</div>
	<div class="sede-detail__content">
		<div class="info-block">
			<h3 data-i18n="common.schedules"><?php esc_html_e( 'Horarios', 'pura' ); ?></h3>
			<?php if ( $pura_s['schedule'] ) : ?>
				<ul>
					<?php foreach ( $pura_s['schedule'] as $pura_row ) : ?>
						<li><strong data-tv><?php echo esc_html( (string) ( $pura_row['group'] ?? '' ) ); ?></strong><span><span data-tv><?php echo esc_html( (string) ( $pura_row['days'] ?? '' ) ); ?></span> · <?php echo esc_html( (string) ( $pura_row['time'] ?? '' ) ); ?></span></li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="placeholder" data-i18n="common.schedulesPending"><?php esc_html_e( 'Horarios por confirmar. Contáctanos para conocer la próxima programación.', 'pura' ); ?></p>
			<?php endif; ?>
		</div>
		<div class="info-block">
			<h3 data-i18n="common.pricing"><?php esc_html_e( 'Costos', 'pura' ); ?></h3>
			<?php if ( $pura_s['pricing'] ) : ?>
				<ul>
					<?php foreach ( $pura_s['pricing'] as $pura_row ) : ?>
						<li><strong data-tv><?php echo esc_html( (string) ( $pura_row['label'] ?? '' ) ); ?></strong><span data-tv><?php echo esc_html( (string) ( $pura_row['value'] ?? '' ) ); ?></span></li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="placeholder" data-i18n="common.pricesPending"><?php esc_html_e( 'Costos por confirmar.', 'pura' ); ?></p>
			<?php endif; ?>
		</div>
		<div class="info-block">
			<h3 data-i18n="common.contact"><?php esc_html_e( 'Contacto', 'pura' ); ?></h3>
			<ul>
				<li><strong data-i18n="common.responsibleLabel"><?php esc_html_e( 'Responsable', 'pura' ); ?></strong><span data-tv><?php echo esc_html( $pura_s['responsible'] ); ?></span></li>
				<li><strong data-i18n="common.addressLabel"><?php esc_html_e( 'Dirección', 'pura' ); ?></strong><span data-tv><?php echo esc_html( $pura_s['address'] ); ?></span></li>
				<?php if ( '' !== $pura_s['whatsapp_display'] ) : ?>
					<li><strong>WhatsApp</strong><span data-tv><?php echo esc_html( $pura_s['whatsapp_display'] ); ?></span></li>
				<?php endif; ?>
				<?php if ( '' !== $pura_s['instagram_handle'] ) : ?>
					<li><strong>Instagram</strong><span><?php echo esc_html( $pura_s['instagram_handle'] ); ?></span></li>
				<?php endif; ?>
				<?php if ( '' !== $pura_s['facebook'] ) : ?>
					<li><strong>Facebook</strong><span><?php echo esc_html( $pura_s['facebook'] ); ?></span></li>
				<?php endif; ?>
			</ul>
		</div>
		<div class="btn-row sede-detail__actions">
			<?php if ( '' !== $pura_s['whatsapp'] ) : ?>
				<a class="btn btn--primary" href="<?php echo esc_url( $pura_s['whatsapp'] ); ?>" target="_blank" rel="noopener noreferrer" data-i18n="common.contactWhatsApp"><?php esc_html_e( 'Contactar por WhatsApp', 'pura' ); ?></a>
			<?php else : ?>
				<a class="btn btn--outline" href="<?php echo esc_url( home_url( '/contacto/' ) ); ?>" data-i18n="common.generalContact"><?php esc_html_e( 'Contacto general', 'pura' ); ?></a>
			<?php endif; ?>
			<?php if ( '' !== $pura_s['instagram'] ) : ?>
				<a class="btn btn--ghost" href="<?php echo esc_url( $pura_s['instagram'] ); ?>" target="_blank" rel="noopener noreferrer" data-i18n="common.instagram">Instagram</a>
			<?php endif; ?>
			<a class="btn btn--ghost" href="<?php echo esc_url( home_url( '/sedes/' ) ); ?>" data-i18n="common.backToLocations"><?php esc_html_e( 'Volver a sedes', 'pura' ); ?></a>
		</div>
	</div>
</div>
