<?php
/**
 * Block pura/sedes-map — Leaflet map of the sedes. Popups are server-rendered (hidden) so the language
 * layer can translate them; view.js copies them into the markers.
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @package Pura
 */

declare( strict_types=1 );

if ( pura_theme_plugin_notice( 'Pura\Core\Data\Sede_Post_Type' ) ) {
	return;
}

wp_enqueue_style( 'pura-leaflet', PURA_THEME_URI . '/assets/vendor/leaflet/leaflet.css', array(), '1.9.4' );

$pura_title  = (string) ( $attributes['title'] ?? '' );
$pura_hint   = (string) ( $attributes['hint'] ?? '' );
$pura_height = max( 0, (int) ( $attributes['height'] ?? 0 ) );
$pura_sedes  = \Pura\Core\Data\Sede_Post_Type::all();
$pura_style  = $pura_height > 0 ? ' style="height:' . esc_attr( (string) $pura_height ) . 'px"' : '';
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'sedes-map-panel' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="sedes-map-panel__head">
		<h2 data-i18n="common.mapTitle"><?php echo esc_html( $pura_title ); ?></h2>
		<p class="muted" data-i18n="common.mapHint"><?php echo esc_html( $pura_hint ); ?></p>
	</div>
	<div class="sedes-map" data-js="sedes-map"<?php echo $pura_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>></div>
	<div class="sede-map-popups" hidden>
		<?php
		foreach ( $pura_sedes as $pura_post ) :
			$pura_s = \Pura\Core\Data\Sede_Post_Type::get_data( $pura_post );
			if ( null === $pura_s['lat'] || null === $pura_s['lng'] ) {
				continue;
			}
			?>
			<div class="sede-map-popup" data-sede-slug="<?php echo esc_attr( $pura_s['slug'] ); ?>" data-lat="<?php echo esc_attr( (string) $pura_s['lat'] ); ?>" data-lng="<?php echo esc_attr( (string) $pura_s['lng'] ); ?>">
				<strong data-tv><?php echo esc_html( $pura_s['name'] ); ?></strong><br />
				<span data-tv><?php echo esc_html( $pura_s['city'] ); ?></span> · <span data-tv><?php echo esc_html( $pura_s['country'] ); ?></span><br />
				<span data-i18n="common.responsibleLabel"><?php esc_html_e( 'Responsable', 'pura' ); ?></span>: <span data-tv><?php echo esc_html( $pura_s['responsible'] ); ?></span><br />
				<?php if ( '' !== $pura_s['whatsapp'] ) : ?>
					<a href="<?php echo esc_url( $pura_s['whatsapp'] ); ?>" target="_blank" rel="noopener noreferrer" data-i18n="common.whatsapp">WhatsApp</a><br />
				<?php endif; ?>
				<a href="<?php echo esc_url( $pura_s['url'] ); ?>" data-i18n="common.seeLocation"><?php esc_html_e( 'Ver sede', 'pura' ); ?></a>
			</div>
		<?php endforeach; ?>
	</div>
	<p class="fallback sedes-map__unavailable" hidden data-i18n="common.mapUnavailable"><?php esc_html_e( 'No fue posible cargar el mapa en este navegador.', 'pura' ); ?></p>
	<p class="fallback sedes-map__nocoords" hidden data-i18n="common.mapNoCoords"><?php esc_html_e( 'Aún no hay coordenadas disponibles para mostrar en el mapa.', 'pura' ); ?></p>
</div>
