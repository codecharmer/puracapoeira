<?php
/**
 * Title: El Grupo — Nuestra historia
 * Slug: puracapoeira/grupo-historia
 * Categories: puracapoeira
 * Description: Sección editorial con fotografía, texto de historia y cita destacada.
 * Viewport Width: 1400
 *
 * @package Pura
 */

?>
<!-- wp:group {"tagName":"section","className":"section","layout":{"type":"default"}} -->
<section class="wp-block-group section">
	<!-- wp:group {"className":"container","layout":{"type":"default"}} -->
	<div class="wp-block-group container">
		<!-- wp:group {"className":"editorial","layout":{"type":"default"}} -->
		<div class="wp-block-group editorial">
			<!-- wp:image {"className":"editorial__img","sizeSlug":"full"} -->
			<figure class="wp-block-image size-full editorial__img"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/capoeira-jogo-jardin.jpg' ) ); ?>" alt="Capoeirista en jogo"/></figure>
			<!-- /wp:image -->

			<!-- wp:group {"layout":{"type":"default"}} -->
			<div class="wp-block-group">
				<!-- wp:paragraph {"className":"eyebrow i18n-hist-eyebrow"} -->
				<p class="eyebrow i18n-hist-eyebrow">Nuestra historia</p>
				<!-- /wp:paragraph -->

				<!-- wp:heading {"level":2,"className":"display i18n-hist-title","style":{"spacing":{"margin":{"top":"0.75rem"}}}} -->
				<h2 class="wp-block-heading display i18n-hist-title" style="margin-top:0.75rem">Capoeira como camino de formación.</h2>
				<!-- /wp:heading -->

				<!-- wp:paragraph {"className":"lead i18n-hist-lead","style":{"spacing":{"margin":{"top":"1.25rem"}}}} -->
				<p class="lead i18n-hist-lead" style="margin-top:1.25rem">Pura Capoeira nace como una comunidad dedicada a vivir la capoeira de forma completa: como lucha, arte, música, historia, cultura y camino de formación personal.</p>
				<!-- /wp:paragraph -->

				<!-- wp:paragraph {"className":"muted i18n-hist-p1"} -->
				<p class="muted i18n-hist-p1">La escuela reúne profesores, instructores, alumnos y núcleos de entrenamiento en diferentes ciudades, manteniendo una base común de respeto, disciplina, musicalidad y fundamento.</p>
				<!-- /wp:paragraph -->

				<!-- wp:quote {"className":"pull-quote"} -->
				<blockquote class="wp-block-quote pull-quote"><!-- wp:paragraph {"className":"i18n-hist-quote"} -->
				<p class="i18n-hist-quote">"La capoeira se transmite en la roda, en el entrenamiento, en el canto, en el toque del berimbau y en la convivencia entre generaciones."</p>
				<!-- /wp:paragraph --></blockquote>
				<!-- /wp:quote -->

				<!-- wp:paragraph {"className":"muted i18n-hist-p2"} -->
				<p class="muted i18n-hist-p2">Por eso, Pura Capoeira no se limita a enseñar movimientos: busca formar capoeiristas con conciencia corporal, musical, cultural e histórica.</p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
