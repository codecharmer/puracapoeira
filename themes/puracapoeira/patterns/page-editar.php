<?php
/**
 * Title: Página — Edición para profesores
 * Slug: puracapoeira/page-editar
 * Categories: puracapoeira
 * Block Types: core/post-content
 * Post Types: page
 * Description: Página donde cada profesor edita su propio perfil y su sede después de entrar con Google o Apple.
 * Viewport Width: 1400
 *
 * @package Pura
 */

?>
<!-- wp:group {"tagName":"section","className":"page-hero","layout":{"type":"default"}} -->
<section class="wp-block-group page-hero">
	<!-- wp:group {"className":"container","layout":{"type":"default"}} -->
	<div class="wp-block-group container">
		<!-- wp:paragraph {"className":"eyebrow i18n-hero-eyebrow"} -->
		<p class="eyebrow i18n-hero-eyebrow">Profesores</p>
		<!-- /wp:paragraph -->

		<!-- wp:heading {"level":1,"className":"display i18n-hero-title"} -->
		<h1 class="wp-block-heading display i18n-hero-title">Edita tu perfil y tu sede</h1>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"className":"i18n-hero-lead"} -->
		<p class="i18n-hero-lead">Mantén al día tus horarios, precios y datos de contacto. Lo que guardes aquí se ve en el sitio y en la app.</p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","className":"section","layout":{"type":"default"}} -->
<section class="wp-block-group section">
	<!-- wp:group {"className":"container","layout":{"type":"default"}} -->
	<div class="wp-block-group container">
		<!-- wp:pura/profile-editor /-->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
