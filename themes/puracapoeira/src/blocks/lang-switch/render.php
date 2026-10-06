<?php
/**
 * Block pura/lang-switch — server render.
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @package Pura
 */

declare( strict_types=1 );

if ( ! function_exists( 'pura_theme_lang_switch_markup' ) ) {
	return;
}

$pura_variant = (string) ( $attributes['variant'] ?? 'header' );
if ( ! in_array( $pura_variant, array( 'header', 'overlay' ), true ) ) {
	$pura_variant = 'header';
}
?>
<div <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>><?php echo pura_theme_lang_switch_markup( $pura_variant ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?></div>
