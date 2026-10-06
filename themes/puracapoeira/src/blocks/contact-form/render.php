<?php
/**
 * Block pura/contact-form — posts JSON to pura/v1/contact (see view.js); the plugin emails it.
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @package Pura
 */

declare( strict_types=1 );

require_once PURA_THEME_DIR . '/src/shared/notice.php';

if ( pura_theme_plugin_notice( 'Pura\Core\Rest\Contact_Controller' ) ) {
	return;
}

$pura_id      = wp_unique_id( 'pura-contact-' );
$pura_submit  = (string) ( $attributes['submitLabel'] ?? 'Enviar mensaje' );
$pura_success = (string) ( $attributes['successText'] ?? '' );
$pura_error   = (string) ( $attributes['errorText'] ?? '' );
$pura_wrapper = get_block_wrapper_attributes( array( 'class' => 'contact-form' ) );
?>
<form <?php echo $pura_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> method="post" novalidate data-js="contact-form" data-endpoint="<?php echo esc_url( rest_url( 'pura/v1/contact' ) ); ?>" data-sending="<?php esc_attr_e( 'Enviando…', 'pura' ); ?>" data-sent="<?php echo esc_attr( $pura_success ); ?>" data-error="<?php echo esc_attr( $pura_error ); ?>">
	<label for="<?php echo esc_attr( $pura_id ); ?>-nombre">
		<span data-i18n="common.name"><?php esc_html_e( 'Nombre', 'pura' ); ?></span>
		<input type="text" id="<?php echo esc_attr( $pura_id ); ?>-nombre" name="nombre" required autocomplete="name" maxlength="120" />
	</label>
	<label for="<?php echo esc_attr( $pura_id ); ?>-ciudad">
		<span data-i18n="common.city"><?php esc_html_e( 'Ciudad', 'pura' ); ?></span>
		<input type="text" id="<?php echo esc_attr( $pura_id ); ?>-ciudad" name="ciudad" autocomplete="address-level2" maxlength="120" />
	</label>
	<label for="<?php echo esc_attr( $pura_id ); ?>-telefono">
		<span data-i18n="common.phone"><?php esc_html_e( 'Teléfono o WhatsApp', 'pura' ); ?></span>
		<input type="tel" id="<?php echo esc_attr( $pura_id ); ?>-telefono" name="telefono" autocomplete="tel" maxlength="40" />
	</label>
	<label for="<?php echo esc_attr( $pura_id ); ?>-email">
		<span data-i18n="common.email"><?php esc_html_e( 'Correo electrónico', 'pura' ); ?></span>
		<input type="email" id="<?php echo esc_attr( $pura_id ); ?>-email" name="email" autocomplete="email" maxlength="254" />
	</label>
	<label for="<?php echo esc_attr( $pura_id ); ?>-mensaje">
		<span data-i18n="common.message"><?php esc_html_e( 'Mensaje', 'pura' ); ?></span>
		<textarea id="<?php echo esc_attr( $pura_id ); ?>-mensaje" name="mensaje" required maxlength="3000"></textarea>
	</label>
	<div class="contact-form__hp" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;">
		<label for="<?php echo esc_attr( $pura_id ); ?>-website">Website<input type="text" id="<?php echo esc_attr( $pura_id ); ?>-website" name="website" tabindex="-1" autocomplete="off" /></label>
	</div>
	<input type="hidden" name="ts" value="" />
	<button type="submit" class="btn btn--primary" data-i18n="common.sendMessage"><?php echo esc_html( $pura_submit ); ?></button>
	<p class="contact-form__status" role="status" aria-live="polite" hidden></p>
	<p class="muted contact-form__hint" style="font-size:0.78rem; margin:0;" data-i18n="common.formHint"><?php esc_html_e( 'Te responderemos por correo o WhatsApp. También puedes contactarnos directamente por WhatsApp desde la tarjeta de cada sede.', 'pura' ); ?></p>
</form>
