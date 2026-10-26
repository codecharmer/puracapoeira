<?php
/**
 * Title: Página — Sube tus fotos (profesores)
 * Slug: puracapoeira/page-fotos
 * Categories: puracapoeira
 * Block Types: core/post-content
 * Post Types: page
 * Description: Página privada (enlace secreto) para que profesores e instructores suban su foto de perfil y fotos de su sede. No aparece en el menú ni en buscadores.
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
		<p class="eyebrow i18n-hero-eyebrow">Profesores e instructores</p>
		<!-- /wp:paragraph -->

		<!-- wp:heading {"level":1,"className":"display i18n-hero-title"} -->
		<h1 class="wp-block-heading display i18n-hero-title">Sube tus fotos</h1>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"className":"i18n-hero-lead"} -->
		<p class="i18n-hero-lead">Comparte tu foto de perfil y fotos de tu sede, tus clases y tus rodas. Nosotros las añadimos a tu perfil y a la página de tu sede en puracapoeira.com.</p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","className":"section","layout":{"type":"default"}} -->
<section class="wp-block-group section">
	<!-- wp:group {"className":"container","layout":{"type":"default"}} -->
	<div class="wp-block-group container">
		<!-- wp:pura/photo-upload /-->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
