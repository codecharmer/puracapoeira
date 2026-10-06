<?php
/**
 * Block pura/profesores-grid — teacher cards.
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @package Pura
 */

declare( strict_types=1 );

if ( pura_theme_plugin_notice( 'Pura\Core\Data\Profesor_Post_Type' ) ) {
	return;
}

$pura_limit = max( 0, (int) ( $attributes['limit'] ?? 0 ) );
$pura_profs = \Pura\Core\Data\Profesor_Post_Type::all( $pura_limit > 0 ? $pura_limit : -1 );
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'prof-grid' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( ! $pura_profs ) : ?>
		<p class="fallback" data-i18n="common.fallbackProfs"><?php esc_html_e( 'No fue posible cargar los profesores.', 'pura' ); ?></p>
	<?php endif; ?>
	<?php
	foreach ( $pura_profs as $pura_post ) :
		$pura_p = \Pura\Core\Data\Profesor_Post_Type::get_data( $pura_post );
		?>
		<article class="prof-card">
			<div class="prof-card__img">
				<?php
				echo pura_theme_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the helper.
					(int) $pura_p['image_id'],
					'large',
					array( 'alt' => $pura_p['name'] ),
					'assets/images/profesor-placeholder.jpg'
				);
				?>
			</div>
			<div class="prof-card__body">
				<div class="prof-card__title"><span data-tv><?php echo esc_html( $pura_p['rank'] ); ?></span> · <span data-tv><?php echo esc_html( $pura_p['city'] ); ?></span></div>
				<h3 class="prof-card__nick"><?php echo esc_html( $pura_p['name'] ); ?></h3>
				<?php if ( '' !== $pura_p['full_name'] && $pura_p['full_name'] !== $pura_p['name'] ) : ?>
					<p class="prof-card__name"><?php echo esc_html( $pura_p['full_name'] ); ?></p>
				<?php endif; ?>
				<p class="prof-card__bio" data-tv><?php echo esc_html( $pura_p['bio'] ); ?></p>
				<div class="prof-card__meta">
					<a href="<?php echo esc_url( $pura_p['url'] ); ?>"><span data-i18n="common.seeProfile"><?php esc_html_e( 'Ver perfil', 'pura' ); ?></span> →</a>
					<?php if ( '' !== $pura_p['sede_url'] ) : ?>
						<a href="<?php echo esc_url( $pura_p['sede_url'] ); ?>" data-i18n="common.seeLocation"><?php esc_html_e( 'Ver sede', 'pura' ); ?></a>
					<?php endif; ?>
					<?php if ( '' !== $pura_p['instagram'] ) : ?>
						<a href="<?php echo esc_url( $pura_p['instagram'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( '' !== $pura_p['instagram_handle'] ? $pura_p['instagram_handle'] : 'Instagram' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		</article>
	<?php endforeach; ?>
</div>
