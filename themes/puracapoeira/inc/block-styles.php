<?php
/**
 * Block style variations that map to the site's existing component classes.
 *
 * The CSS for each style lives in assets/css/theme.css (see "Core block adapters").
 *
 * @package Pura
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

function pura_theme_register_block_styles(): void {
	$styles = array(
		'core/button'    => array(
			'ghost'   => __( 'Fantasma', 'pura' ),
			'outline' => __( 'Contorno', 'pura' ),
		),
		'core/paragraph' => array(
			'eyebrow'       => __( 'Antetítulo', 'pura' ),
			'eyebrow-muted' => __( 'Antetítulo apagado', 'pura' ),
			'lead'          => __( 'Entrada', 'pura' ),
			'muted'         => __( 'Apagado', 'pura' ),
		),
		'core/group'     => array(
			'surface-band' => __( 'Franja clara', 'pura' ),
		),
	);

	foreach ( $styles as $block => $variations ) {
		foreach ( $variations as $name => $label ) {
			register_block_style(
				$block,
				array(
					'name'  => $name,
					'label' => $label,
				)
			);
		}
	}
}
add_action( 'init', 'pura_theme_register_block_styles' );
