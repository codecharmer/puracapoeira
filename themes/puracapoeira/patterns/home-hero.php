<?php
/**
 * Title: Inicio — Hero
 * Slug: puracapoeira/home-hero
 * Categories: puracapoeira
 * Description: Portada de la página de inicio con foto de roda, logo, título, textos de apertura y llamadas a la acción.
 * Viewport Width: 1400
 *
 * @package Pura
 */

?>
<!-- wp:group {"tagName":"section","className":"hero","layout":{"type":"default"}} -->
<section class="wp-block-group hero">
	<!-- wp:image {"className":"hero__img","sizeSlug":"full"} -->
	<figure class="wp-block-image size-full hero__img"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/pura-capoeira-roda.jpg' ) ); ?>" alt="Roda de capoeira"/></figure>
	<!-- /wp:image -->

	<!-- wp:group {"className":"container hero__content","layout":{"type":"default"}} -->
	<div class="wp-block-group container hero__content">
		<!-- wp:group {"className":"hero__meta","layout":{"type":"default"}} -->
		<div class="wp-block-group hero__meta">
			<!-- wp:paragraph {"className":"eyebrow i18n-hero-eyebrow"} -->
			<p class="eyebrow i18n-hero-eyebrow">Escola internacional · est. comunidade</p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph {"className":"eyebrow eyebrow--muted i18n-hero-eyebrow-muted"} -->
			<p class="eyebrow eyebrow--muted i18n-hero-eyebrow-muted">México · Brasil · Angola · USA</p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"className":"hero__title","layout":{"type":"default"}} -->
		<div class="wp-block-group hero__title">
			<!-- wp:image {"className":"hero__title-logo","sizeSlug":"full"} -->
			<figure class="wp-block-image size-full hero__title-logo"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/logo-capoeira.png' ) ); ?>" alt="Logo Pura Capoeira"/></figure>
			<!-- /wp:image -->

			<!-- wp:heading {"level":1,"className":"display"} -->
			<h1 class="wp-block-heading display">Pura<br>Capoeira</h1>
			<!-- /wp:heading -->
		</div>
		<!-- /wp:group -->

		<!-- wp:paragraph {"className":"sub i18n-hero-sub"} -->
		<p class="sub i18n-hero-sub">Capoeira, cultura, música e movimento conectando comunidades.</p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph {"className":"lead i18n-hero-lead"} -->
		<p class="lead i18n-hero-lead">Pura Capoeira es una escuela y comunidad dedicada a preservar, enseñar y compartir la capoeira como arte, lucha, música, cultura afrobrasileña, disciplina y desarrollo personal.</p>
		<!-- /wp:paragraph -->

		<!-- wp:buttons {"className":"hero__buttons","layout":{"type":"flex"}} -->
		<div class="wp-block-buttons hero__buttons">
			<!-- wp:button {"className":"i18n-hero-cta-sedes"} -->
			<div class="wp-block-button i18n-hero-cta-sedes"><a class="wp-block-button__link wp-element-button" href="/sedes/">Encuentra una sede</a></div>
			<!-- /wp:button -->

			<!-- wp:button {"className":"is-style-ghost i18n-hero-cta-grupo"} -->
			<div class="wp-block-button is-style-ghost i18n-hero-cta-grupo"><a class="wp-block-button__link wp-element-button" href="/grupo/">Conoce el grupo</a></div>
			<!-- /wp:button -->

			<!-- wp:button {"className":"is-style-outline i18n-hero-cta-galeria"} -->
			<div class="wp-block-button is-style-outline i18n-hero-cta-galeria"><a class="wp-block-button__link wp-element-button" href="/galeria/">Ver galería</a></div>
			<!-- /wp:button -->
		</div>
		<!-- /wp:buttons -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
