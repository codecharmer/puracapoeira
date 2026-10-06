<?php
/**
 * Block pura/gallery — gallery cards with category and location filter pills.
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @package Pura
 */

declare( strict_types=1 );

require_once PURA_THEME_DIR . '/src/shared/notice.php';

if ( pura_theme_plugin_notice( 'Pura\Core\Data\Galeria_Post_Type' ) ) {
	return;
}

$pura_show_filters = ! isset( $attributes['showFilters'] ) || ! empty( $attributes['showFilters'] );
$pura_limit        = max( 1, min( 200, (int) ( $attributes['limit'] ?? 48 ) ) );
$pura_items        = \Pura\Core\Data\Galeria_Post_Type::all( $pura_limit );

$pura_terms_of = static function ( string $taxonomy ): array {
	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
			'orderby'    => 'name',
		)
	);

	return is_array( $terms ) ? $terms : array();
};
$pura_cats     = $pura_show_filters ? $pura_terms_of( \Pura\Core\Data\Galeria_Post_Type::TAX_CATEGORY ) : array();
$pura_locs     = $pura_show_filters ? $pura_terms_of( \Pura\Core\Data\Galeria_Post_Type::TAX_LOCATION ) : array();
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'gallery' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( $pura_items && $pura_cats ) : ?>
		<div class="filter-group">
			<span class="filter-group__label" data-i18n="common.category"><?php esc_html_e( 'Categoría', 'pura' ); ?></span>
			<div class="filters" role="group">
				<button type="button" class="filter-pill" data-filter-cat="" data-active="true" aria-pressed="true" data-i18n="common.all"><?php esc_html_e( 'Todos', 'pura' ); ?></button>
				<?php foreach ( $pura_cats as $pura_term ) : ?>
					<button type="button" class="filter-pill" data-filter-cat="<?php echo esc_attr( $pura_term->slug ); ?>" data-active="false" aria-pressed="false" data-tv><?php echo esc_html( $pura_term->name ); ?></button>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>
	<?php if ( $pura_items && $pura_locs ) : ?>
		<div class="filter-group">
			<span class="filter-group__label" data-i18n="common.location"><?php esc_html_e( 'Sede', 'pura' ); ?></span>
			<div class="filters" role="group">
				<button type="button" class="filter-pill" data-filter-loc="" data-active="true" aria-pressed="true" data-i18n="common.allLocations"><?php esc_html_e( 'Todas las sedes', 'pura' ); ?></button>
				<?php foreach ( $pura_locs as $pura_term ) : ?>
					<button type="button" class="filter-pill" data-filter-loc="<?php echo esc_attr( $pura_term->slug ); ?>" data-active="false" aria-pressed="false" data-tv><?php echo esc_html( $pura_term->name ); ?></button>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( ! $pura_items ) : ?>
		<p class="fallback" data-i18n="common.fallbackGallery"><?php esc_html_e( 'Nuestra galería completa estará disponible próximamente.', 'pura' ); ?></p>
	<?php else : ?>
		<div class="gallery-grid" data-js="gallery-grid">
			<?php
			foreach ( $pura_items as $pura_post ) :
				$pura_it   = \Pura\Core\Data\Galeria_Post_Type::get_data( $pura_post );
				$pura_cat  = $pura_it['categories'] ? $pura_it['categories'][0]->name : '';
				$pura_loc  = $pura_it['locations'] ? $pura_it['locations'][0]->name : '';
				$pura_tag  = '' !== $pura_it['url'] ? 'a' : 'div';
				$pura_href = '' !== $pura_it['url'] ? ' href="' . esc_url( $pura_it['url'] ) . '" target="_blank" rel="noopener noreferrer"' : '';
				?>
				<<?php echo $pura_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal tag name. ?> class="gallery-item"<?php echo $pura_href; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?> data-cat="<?php echo esc_attr( implode( ' ', wp_list_pluck( $pura_it['categories'], 'slug' ) ) ); ?>" data-loc="<?php echo esc_attr( implode( ' ', wp_list_pluck( $pura_it['locations'], 'slug' ) ) ); ?>">
					<?php
					echo pura_theme_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the helper.
						(int) $pura_it['image_id'],
						'large',
						array(
							'alt'          => $pura_it['title'],
							'data-tv-attr' => 'alt',
						)
					);
					?>
					<div class="gallery-item__overlay">
						<span class="gallery-item__cat"><span data-tv><?php echo esc_html( $pura_cat ); ?></span>
						<?php
						if ( '' !== $pura_loc ) :
							?>
							· <span data-tv><?php echo esc_html( $pura_loc ); ?></span><?php endif; ?></span>
						<h3 class="gallery-item__title" data-tv><?php echo esc_html( $pura_it['title'] ); ?></h3>
						<span class="gallery-item__date"><?php echo esc_html( $pura_it['date'] ); ?></span>
					</div>
				</<?php echo $pura_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php endforeach; ?>
		</div>
		<p class="fallback gallery-empty" hidden data-i18n="common.fallbackGallery"><?php esc_html_e( 'Nuestra galería completa estará disponible próximamente.', 'pura' ); ?></p>
	<?php endif; ?>
</div>
