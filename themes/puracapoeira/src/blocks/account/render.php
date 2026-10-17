<?php
/**
 * pura/account — sign-in buttons (Google / Apple) and the Mi cuenta shell.
 *
 * No user data is printed here: pages are cached for everyone. view.js discovers the session and
 * fills the portal from /pura/v1/account/me.
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @package Pura
 */

declare( strict_types=1 );

if ( pura_theme_plugin_notice( 'Pura\Core\Rest\Account_Controller' ) ) {
	return;
}

$pura_providers   = pura_auth_providers();
$pura_privacy_url = pura_privacy_url();
$pura_account_url = pura_account_url();
$pura_events_page = get_page_by_path( 'eventos' );
$pura_events_url  = $pura_events_page ? (string) get_permalink( $pura_events_page ) : home_url( '/eventos/' );
$pura_text        = static fn ( string $key, string $default ) => (string) ( $attributes[ $key ] ?? $default );

$pura_wrapper = get_block_wrapper_attributes( array( 'class' => 'account' ) );
?>
<div <?php echo $pura_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-account-root data-account-state="pending">
	<p class="account__loading muted i18n-loading" data-account-loading><?php esc_html_e( 'Comprobando tu sesión…', 'pura' ); ?></p>

	<div class="inscription-result account-notice" data-account-notice hidden role="status" aria-live="polite"></div>

	<section class="account-login" data-account-logged-out>
		<p class="lead i18n-login-intro"><?php echo esc_html( $pura_text( 'introLoggedOut', '' ) ); ?></p>
		<?php if ( $pura_providers ) : ?>
			<div class="account-login__providers">
				<?php foreach ( $pura_providers as $pura_provider ) : ?>
					<?php echo pura_theme_provider_form( $pura_provider, $pura_account_url, 'data-account-redirect' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the helper. ?>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p class="pura-notice i18n-login-unavailable"><?php esc_html_e( 'El inicio de sesión aún no está configurado. Escríbenos por WhatsApp si necesitas ayuda con tu registro.', 'pura' ); ?></p>
		<?php endif; ?>
		<p class="account-login__legal muted">
			<span class="i18n-login-legal"><?php esc_html_e( 'Solo recibimos tu nombre y correo del proveedor. Consulta el', 'pura' ); ?></span>
			<a class="i18n-login-legal-link" href="<?php echo esc_url( $pura_privacy_url ); ?>"><?php esc_html_e( 'Aviso de privacidad', 'pura' ); ?></a>.
		</p>
	</section>

	<section class="account-portal" data-account-portal hidden>
		<header class="account-portal__head">
			<h2 class="account-portal__title display"><span class="i18n-portal-hello"><?php esc_html_e( 'Hola,', 'pura' ); ?></span> <span data-account-name></span></h2>
			<p class="account-portal__email muted" data-account-email></p>
			<p class="account-portal__private muted i18n-portal-private" data-account-private hidden><?php esc_html_e( 'Entraste con Apple ocultando tu correo: aquí solo aparecen los registros hechos desde esta cuenta.', 'pura' ); ?></p>
			<a class="btn btn--ghost account-logout i18n-logout" data-account-logout href="#" rel="nofollow"><?php esc_html_e( 'Cerrar sesión', 'pura' ); ?></a>
		</header>

		<section class="account-history" data-account-history>
			<h3 class="account-history__title i18n-history-title"><?php esc_html_e( 'Mis registros a eventos', 'pura' ); ?></h3>
			<div class="account-history__table-wrap" data-account-history-table hidden>
				<table class="account-history__table">
					<thead>
						<tr>
							<th scope="col" class="i18n-col-event"><?php esc_html_e( 'Evento', 'pura' ); ?></th>
							<th scope="col" class="i18n-col-date"><?php esc_html_e( 'Fecha', 'pura' ); ?></th>
							<th scope="col" class="i18n-col-status"><?php esc_html_e( 'Estado', 'pura' ); ?></th>
							<th scope="col" class="i18n-col-proof"><?php esc_html_e( 'Comprobante', 'pura' ); ?></th>
						</tr>
					</thead>
					<tbody data-account-history-rows></tbody>
				</table>
			</div>
			<p class="account-history__empty muted i18n-history-empty" data-account-history-empty hidden><?php echo esc_html( $pura_text( 'emptyText', '' ) ); ?></p>
			<div class="btn-row account-actions">
				<a class="btn btn--outline i18n-see-events" href="<?php echo esc_url( $pura_events_url ); ?>"><?php esc_html_e( 'Ver eventos', 'pura' ); ?></a>
				<a class="btn btn--primary i18n-whatsapp" data-account-whatsapp href="#" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Escribir por WhatsApp', 'pura' ); ?></a>
			</div>
		</section>
	</section>
</div>
