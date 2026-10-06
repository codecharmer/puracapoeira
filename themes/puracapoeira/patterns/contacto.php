<?php
/**
 * Title: Contacto — Contactos locales y formulario
 * Slug: puracapoeira/contacto
 * Categories: puracapoeira
 * Description: Dos columnas: tarjetas de contacto por sede y formulario general.
 * Viewport Width: 1400
 *
 * @package Pura
 */

?>
<!-- wp:group {"tagName":"section","className":"section","layout":{"type":"default"}} -->
<section class="wp-block-group section">
	<!-- wp:group {"className":"container contact-grid","layout":{"type":"default"}} -->
	<div class="wp-block-group container contact-grid">
		<!-- wp:group {"layout":{"type":"default"}} -->
		<div class="wp-block-group">
			<!-- wp:paragraph {"className":"eyebrow i18n-local-eyebrow"} -->
			<p class="eyebrow i18n-local-eyebrow">Por sede</p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"level":2,"className":"display i18n-local-title","style":{"spacing":{"margin":{"top":"0.75rem","bottom":"2rem"}}}} -->
			<h2 class="wp-block-heading display i18n-local-title" style="margin-top:0.75rem;margin-bottom:2rem">Contactos<br>locales</h2>
			<!-- /wp:heading -->

			<!-- wp:pura/contact-cards /-->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"layout":{"type":"default"}} -->
		<div class="wp-block-group">
			<!-- wp:paragraph {"className":"eyebrow i18n-form-eyebrow"} -->
			<p class="eyebrow i18n-form-eyebrow">Formulario general</p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"level":2,"className":"display i18n-form-title","style":{"spacing":{"margin":{"top":"0.75rem","bottom":"2rem"}}}} -->
			<h2 class="wp-block-heading display i18n-form-title" style="margin-top:0.75rem;margin-bottom:2rem">Escríbenos</h2>
			<!-- /wp:heading -->

			<!-- wp:pura/contact-form /-->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
