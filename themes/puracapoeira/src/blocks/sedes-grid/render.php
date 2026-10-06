<?php
/**
 * Block pura/sedes-grid — sede cards (or a plain link list for the footer).
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @package Pura
 */

declare( strict_types=1 );

if ( pura_theme_plugin_notice( 'Pura\Core\Data\Sede_Post_Type' ) ) {
	return;
}

$pura_layout = 'links' === ( $attributes['layout'] ?? 'cards' ) ? 'links' : 'cards';
$pura_limit  = max( 0, (int) ( $attributes['limit'] ?? 0 ) );
$pura_sedes  = \Pura\Core\Data\Sede_Post_Type::all( $pura_limit > 0 ? $pura_limit : -1 );

if ( 'links' === $pura_layout ) :
	?>
	<ul <?php echo get_block_wrapper_attributes( array( 'class' => 'footer-sedes' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<?php
		foreach ( $pura_sedes as $pura_post ) :
			$pura_s    = \Pura\Core\Data\Sede_Post_Type::get_data( $pura_post );
			$pura_city = ( '' === $pura_s['city'] || false !== stripos( $pura_s['city'], 'por confirmar' ) || $pura_s['city'] === $pura_s['country'] ) ? '' : $pura_s['city'];
			?>
			<li><a href="<?php echo esc_url( $pura_s['url'] ); ?>">
			<?php
			if ( '' !== $pura_city ) :
				?>
				<span data-tv><?php echo esc_html( $pura_city ); ?></span> · <?php endif; ?><span data-tv><?php echo esc_html( $pura_s['country'] ); ?></span></a></li>
		<?php endforeach; ?>
	</ul>
	<?php
	return;
endif;
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'sede-grid' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( ! $pura_sedes ) : ?>
		<p class="fallback" data-i18n="common.fallbackSedes"><?php esc_html_e( 'No fue posible cargar las sedes.', 'pura' ); ?></p>
	<?php endif; ?>
	<?php
	foreach ( $pura_sedes as $pura_post ) :
		$pura_s = \Pura\Core\Data\Sede_Post_Type::get_data( $pura_post );
		?>
		<a class="sede-card" href="<?php echo esc_url( $pura_s['url'] ); ?>" data-sede-slug="<?php echo esc_attr( $pura_s['slug'] ); ?>">
			<div class="sede-card__img">
				<?php
				echo pura_theme_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the helper.
					(int) $pura_s['image_id'],
					'large',
					array(
						'alt'          => 'Pura Capoeira ' . $pura_s['city'],
						'data-tv-attr' => 'alt',
					)
				);
				?>
			</div>
			<div class="sede-card__body">
				<span class="sede-card__country"><span data-tv><?php echo esc_html( $pura_s['region'] ); ?></span> · <span data-tv><?php echo esc_html( $pura_s['country'] ); ?></span></span>
				<h3 class="sede-card__city" data-tv><?php echo esc_html( $pura_s['city'] ); ?></h3>
				<p class="sede-card__responsible" data-tv><?php echo esc_html( $pura_s['responsible'] ); ?></p>
				<p class="sede-card__blurb" data-tv><?php echo esc_html( $pura_s['blurb'] ); ?></p>
				<span class="sede-card__link" data-i18n="common.seeLocation"><?php esc_html_e( 'Ver sede', 'pura' ); ?></span>
			</div>
		</a>
	<?php endforeach; ?>
</div>
