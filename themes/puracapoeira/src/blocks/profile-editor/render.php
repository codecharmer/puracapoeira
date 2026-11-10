<?php
/**
 * pura/profile-editor — the page where a professor edits their own profile and sedes.
 *
 * Only a shell is printed here: pages are cached for everyone, so nothing personal may be in
 * the HTML. view.js discovers the session and builds the forms from /pura/v1/editor/me.
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @package Pura
 */

declare( strict_types=1 );

if ( pura_theme_plugin_notice( 'Pura\Core\Rest\Editor_Controller' ) ) {
	return;
}

$pura_providers   = pura_auth_providers();
$pura_editor_url  = class_exists( 'Pura\Core\Settings' ) ? \Pura\Core\Settings::page_url( 'editor' ) : home_url( '/editar/' );
$pura_account_url = pura_account_url();
$pura_wrapper     = get_block_wrapper_attributes( array( 'class' => 'profile-editor' ) );
?>
<div <?php echo $pura_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-editor-root data-editor-state="pending">
	<p class="muted i18n-loading" data-editor-loading><?php esc_html_e( 'Comprobando tu sesión…', 'pura' ); ?></p>

	<section class="account-login" data-editor-logged-out hidden>
		<p class="lead i18n-editor-login"><?php esc_html_e( 'Entra con la cuenta de Google o Apple del correo que diste a la administración para editar tu perfil y tu sede.', 'pura' ); ?></p>
		<?php if ( $pura_providers ) : ?>
			<div class="account-login__providers">
				<?php foreach ( $pura_providers as $pura_provider ) : ?>
					<?php echo pura_theme_provider_form( $pura_provider, $pura_editor_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the helper. ?>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p class="pura-notice i18n-login-unavailable"><?php esc_html_e( 'El inicio de sesión aún no está configurado.', 'pura' ); ?></p>
		<?php endif; ?>
	</section>

	<section data-editor-no-access hidden>
		<p class="lead i18n-editor-no-access"><?php esc_html_e( 'Tu cuenta todavía no está vinculada a un perfil o a una sede.', 'pura' ); ?></p>
		<p class="muted i18n-editor-no-access-help"><?php esc_html_e( 'Pide a la administración que agregue el correo con el que entraste. Si entraste con Apple ocultando tu correo, entra con Google o comparte tu correo con Apple.', 'pura' ); ?></p>
		<p class="muted" data-editor-email></p>
		<div class="btn-row">
			<a class="btn btn--outline i18n-editor-account" href="<?php echo esc_url( $pura_account_url ); ?>"><?php esc_html_e( 'Mi cuenta', 'pura' ); ?></a>
		</div>
	</section>

	<div data-editor-forms hidden></div>
</div>
