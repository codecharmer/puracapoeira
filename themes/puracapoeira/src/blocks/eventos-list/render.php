<?php
/**
 * Block pura/eventos-list — events with a date badge, soonest first.
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @package Pura
 */

declare( strict_types=1 );

require_once PURA_THEME_DIR . '/src/shared/notice.php';

if ( pura_theme_plugin_notice( 'Pura\Core\Data\Evento_Post_Type' ) ) {
	return;
}

$pura_limit   = max( 0, (int) ( $attributes['limit'] ?? 0 ) );
$pura_past    = ! isset( $attributes['showPast'] ) || ! empty( $attributes['showPast'] );
$pura_events  = \Pura\Core\Data\Evento_Post_Type::all( $pura_limit > 0 ? $pura_limit : -1, $pura_past );
$pura_today   = wp_date( 'Y-m-d' );
$pura_months  = array( 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic' );
$pura_wrapper = get_block_wrapper_attributes( array( 'class' => 'event-list' ) );
?>
<div <?php echo $pura_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( ! $pura_events ) : ?>
		<p class="fallback" data-i18n="common.fallbackEvents"><?php esc_html_e( 'Próximamente anunciaremos nuevas rodas, talleres y encuentros de Pura Capoeira.', 'pura' ); ?></p>
	<?php endif; ?>
	<?php
	foreach ( $pura_events as $pura_post ) :
		$pura_ev      = \Pura\Core\Data\Evento_Post_Type::get_data( $pura_post );
		$pura_time    = '' !== $pura_ev['date'] ? strtotime( $pura_ev['date'] . ' 00:00:00' ) : false;
		$pura_day     = $pura_time ? gmdate( 'j', $pura_time ) : '—';
		$pura_month   = $pura_time ? $pura_months[ (int) gmdate( 'n', $pura_time ) - 1 ] : '';
		$pura_year    = $pura_time ? gmdate( 'Y', $pura_time ) : '';
		$pura_is_past = '' !== $pura_ev['date'] && $pura_ev['date'] < $pura_today;
		?>
		<div class="event-row" data-date="<?php echo esc_attr( $pura_ev['date'] ); ?>">
			<div class="event-row__date">
				<span class="day"><?php echo esc_html( $pura_day ); ?></span>
				<span class="month"><span data-tv><?php echo esc_html( $pura_month ); ?></span> <?php echo esc_html( $pura_year ); ?></span>
			</div>
			<div class="event-row__main">
				<h3 data-tv><?php echo esc_html( $pura_ev['title'] ); ?></h3>
				<div class="event-row__meta">
					<?php
					if ( '' !== $pura_ev['time'] ) :
						?>
						<span><?php echo esc_html( $pura_ev['time'] ); ?></span><?php endif; ?>
					<?php
					if ( '' !== $pura_ev['location'] ) :
						?>
						<span data-tv><?php echo esc_html( $pura_ev['location'] ); ?></span><?php endif; ?>
					<?php
					if ( '' !== $pura_ev['venue'] ) :
						?>
						<span data-tv><?php echo esc_html( $pura_ev['venue'] ); ?></span><?php endif; ?>
				</div>
				<?php if ( '' !== $pura_ev['description'] ) : ?>
					<p class="event-row__desc" data-tv><?php echo esc_html( $pura_ev['description'] ); ?></p>
				<?php endif; ?>
			</div>
			<div class="event-row__aside">
				<?php if ( '' !== $pura_ev['status'] ) : ?>
					<span class="event-status<?php echo $pura_is_past ? ' event-status--past' : ''; ?>" data-tv><?php echo esc_html( $pura_ev['status'] ); ?></span>
				<?php else : ?>
					<span class="event-status<?php echo $pura_is_past ? ' event-status--past' : ''; ?>" data-i18n="common.next"><?php esc_html_e( 'Próximo', 'pura' ); ?></span>
				<?php endif; ?>
				<?php if ( '' !== $pura_ev['url'] ) : ?>
					<div class="event-row__cta"><a class="btn btn--outline" href="<?php echo esc_url( $pura_ev['url'] ); ?>" target="_blank" rel="noopener noreferrer" data-i18n="common.moreInfo"><?php esc_html_e( 'Más info', 'pura' ); ?></a></div>
				<?php endif; ?>
			</div>
		</div>
	<?php endforeach; ?>
</div>
