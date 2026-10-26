<?php
/**
 * Block pura/photo-upload — colleagues' photo upload. The form stays hidden until view.js has
 * checked the key in the link against /pura/v1/photos/access; nothing here is user-specific, so the
 * page can be cached.
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @package Pura
 */

declare( strict_types=1 );

if ( pura_theme_plugin_notice( 'Pura\Core\Rest\Photos_Controller' ) ) {
	return;
}

// The page exists for people holding the link, not for search engines.
add_filter( 'wp_robots', 'wp_robots_no_robots' );

$pura_ask_profile = ! empty( $attributes['askProfile'] );
$pura_footnote    = trim( (string) ( $attributes['footnote'] ?? '' ) );
$pura_max_files   = Pura\Core\Data\Photo_Batch_Repository::MAX_FILES;
$pura_max_bytes   = Pura\Core\Data\Photo_Batch_Repository::max_bytes();
$pura_whatsapp    = Pura\Core\Settings::whatsapp_url( 'Hola, necesito el enlace para subir mis fotos a puracapoeira.com' );
$pura_profesores  = Pura\Core\Data\Profesor_Post_Type::all();
$pura_sedes       = Pura\Core\Data\Sede_Post_Type::all();
$pura_config      = array(
	'key_param' => Pura\Core\Photo_Uploads::KEY_PARAM,
	'max_files' => $pura_max_files,
	'max_bytes' => $pura_max_bytes,
);

$pura_wrapper = get_block_wrapper_attributes( array( 'class' => 'inscription-layout photo-upload' ) );
?>
<div <?php echo $pura_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-photo-root data-config="<?php echo esc_attr( (string) wp_json_encode( $pura_config ) ); ?>">
	<p class="photo-upload__checking i18n-checking" data-photo-checking><?php esc_html_e( 'Comprobando el enlace…', 'pura' ); ?></p>

	<div class="inscription-result is-error photo-upload__gate" data-photo-gate hidden role="alert">
		<p class="i18n-gate-invalid"><?php esc_html_e( 'Este enlace no es válido. Pide el enlace para subir fotos por WhatsApp.', 'pura' ); ?></p>
		<p><a class="btn btn--outline i18n-gate-whatsapp" href="<?php echo esc_url( $pura_whatsapp ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Escribir por WhatsApp', 'pura' ); ?></a></p>
	</div>

	<form class="inscription-form photo-form" data-photo-form hidden novalidate>
		<fieldset class="inscription-block">
			<legend class="i18n-step-who"><?php esc_html_e( '1. ¿Quién eres?', 'pura' ); ?></legend>
			<div class="form-grid">
				<label class="field"><span class="i18n-field-name"><?php esc_html_e( 'Nombre o apelido *', 'pura' ); ?></span><input type="text" name="name" autocomplete="name" required maxlength="120" /></label>
				<label class="field"><span class="i18n-field-contact"><?php esc_html_e( 'WhatsApp o correo (por si tenemos dudas)', 'pura' ); ?></span><input type="text" name="contact" autocomplete="email" maxlength="160" /></label>
				<?php if ( $pura_profesores ) : ?>
					<label class="field"><span class="i18n-field-profesor"><?php esc_html_e( 'Tu perfil en el sitio', 'pura' ); ?></span>
						<select name="profesor_id">
							<option value="0" class="i18n-opt-profesor-none"><?php esc_html_e( 'Elige tu perfil (si lo tienes)', 'pura' ); ?></option>
							<?php foreach ( $pura_profesores as $pura_profesor ) : ?>
								<option value="<?php echo esc_attr( (string) $pura_profesor->ID ); ?>"><?php echo esc_html( (string) get_the_title( $pura_profesor ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				<?php endif; ?>
				<?php if ( $pura_sedes ) : ?>
					<label class="field"><span class="i18n-field-sede"><?php esc_html_e( 'Tu sede', 'pura' ); ?></span>
						<select name="sede_id">
							<option value="0" class="i18n-opt-sede-none"><?php esc_html_e( 'Elige tu sede', 'pura' ); ?></option>
							<?php foreach ( $pura_sedes as $pura_sede ) : ?>
								<option value="<?php echo esc_attr( (string) $pura_sede->ID ); ?>"><?php echo esc_html( (string) get_the_title( $pura_sede ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				<?php endif; ?>
			</div>
			<label class="field" style="position:absolute;left:-9999px;opacity:0;" aria-hidden="true" tabindex="-1"><span>Sitio web</span><input type="text" name="website" tabindex="-1" autocomplete="off" /></label>
		</fieldset>

		<?php if ( $pura_ask_profile ) : ?>
			<fieldset class="inscription-block">
				<legend class="i18n-step-profile"><?php esc_html_e( '2. Tu foto de perfil', 'pura' ); ?></legend>
				<p class="field-help photo-help i18n-profile-help"><?php esc_html_e( 'Una foto vertical o cuadrada, con buena luz, donde se te vea bien la cara. Es la que aparece en tu perfil y en la lista de profesores.', 'pura' ); ?></p>
				<label class="photo-pick">
					<input type="file" accept="image/*" data-photo-input="profile" />
					<span class="btn btn--outline i18n-pick-profile"><?php esc_html_e( 'Elegir foto de perfil', 'pura' ); ?></span>
				</label>
				<ul class="photo-list" data-photo-list="profile"></ul>
			</fieldset>
		<?php endif; ?>

		<fieldset class="inscription-block">
			<legend class="i18n-step-photos"><?php echo esc_html( $pura_ask_profile ? __( '3. Fotos de tu sede, tus clases y tus rodas', 'pura' ) : __( '2. Fotos de tu sede, tus clases y tus rodas', 'pura' ) ); ?></legend>
			<p class="field-help photo-help">
				<span class="i18n-photos-help"><?php esc_html_e( 'Puedes elegir varias a la vez y añadir más después. Las fotos muy grandes se reducen solas antes de subirse.', 'pura' ); ?></span>
				<span class="i18n-photos-max-prefix"><?php esc_html_e( 'Hasta', 'pura' ); ?></span> <?php echo esc_html( (string) $pura_max_files ); ?> <span class="i18n-photos-max-suffix"><?php esc_html_e( 'fotos por envío.', 'pura' ); ?></span>
			</p>
			<label class="photo-pick">
				<input type="file" accept="image/*" multiple data-photo-input="photo" />
				<span class="btn btn--outline i18n-pick-photos"><?php esc_html_e( 'Elegir fotos', 'pura' ); ?></span>
			</label>
			<ul class="photo-list" data-photo-list="photo"></ul>
		</fieldset>

		<fieldset class="inscription-block">
			<legend class="i18n-step-note"><?php echo esc_html( $pura_ask_profile ? __( '4. Nota (opcional)', 'pura' ) : __( '3. Nota (opcional)', 'pura' ) ); ?></legend>
			<div class="form-grid">
				<label class="field field--full"><span class="i18n-field-note"><?php esc_html_e( '¿Quiénes aparecen, dónde y cuándo? Lo que quieras que sepamos.', 'pura' ); ?></span><textarea name="note" rows="3" maxlength="1000"></textarea></label>
			</div>
		</fieldset>

		<div class="inscription-result" data-photo-result hidden role="status" aria-live="polite"></div>

		<div class="photo-form__actions">
			<button type="submit" class="btn btn--primary inscription-submit i18n-submit" data-photo-submit><?php esc_html_e( 'Enviar fotos', 'pura' ); ?></button>
			<button type="button" class="btn btn--ghost i18n-btn-retry" data-photo-retry hidden><?php esc_html_e( 'Reintentar', 'pura' ); ?></button>
		</div>
		<?php if ( '' !== $pura_footnote ) : ?>
			<p class="store-footnote i18n-footnote"><?php echo esc_html( $pura_footnote ); ?></p>
		<?php endif; ?>
	</form>

	<div class="photo-upload__done" data-photo-done hidden>
		<h3 class="i18n-done-title"><?php esc_html_e( '¡Gracias!', 'pura' ); ?></h3>
		<p data-photo-done-text></p>
		<p><a class="btn btn--outline i18n-done-more" href="" data-photo-more><?php esc_html_e( 'Subir más fotos', 'pura' ); ?></a></p>
	</div>
</div>
