<?php
/**
 * `wp pura-theme import …` — seeds the site from the theme patterns and the legacy static site.
 *
 * Pages, the menu and settings only need the theme. Sedes, profesores, eventos, the gallery and
 * their images are read from the static site folder (`--source`, or wp-content/pura-source),
 * which holds `data/*.json`, the teacher profile HTML pages and `assets/images/`.
 *
 * Re-runnable: posts carry import keys; a post edited in the admin since its import is skipped
 * unless `--force` is given.
 *
 * @package Pura
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	return;
}

final class Pura_Theme_Import_Command {

	private const PAGES = array(
		'inicio'     => array(
			'title'   => 'Inicio',
			'excerpt' => 'Pura Capoeira es una escuela y comunidad de capoeira con sedes en México, Brasil, Angola y Estados Unidos. Clases, rodas, música, cultura afrobrasileña y eventos.',
		),
		'grupo'      => array(
			'title'   => 'El Grupo',
			'excerpt' => 'Conoce la filosofía, valores y comunidad de Pura Capoeira: lucha, arte, música, cultura afrobrasileña, disciplina y fundamento.',
		),
		'profesores' => array(
			'title'   => 'Profesores e instructores',
			'excerpt' => 'Conoce a los profesores, instructores y responsables de las sedes de Pura Capoeira en México, Brasil, Angola y Estados Unidos.',
		),
		'sedes'      => array(
			'title'   => 'Sedes',
			'excerpt' => 'Encuentra sedes de Pura Capoeira en Cuernavaca, Toluca, Guanajuato, Ceará, Angola y Austin. Horarios, contactos y ubicaciones.',
		),
		'galeria'    => array(
			'title'   => 'Galería',
			'excerpt' => 'Videos y fotos de clases, rodas, batizados, entrenamientos y eventos de Pura Capoeira.',
		),
		'eventos'    => array(
			'title'   => 'Eventos',
			'excerpt' => 'Rodas, batizados, talleres, encuentros y eventos de Pura Capoeira.',
		),
		'contacto'   => array(
			'title'   => 'Contacto',
			'excerpt' => 'Contacta a Pura Capoeira y encuentra una sede cerca de ti en México, Brasil, Angola o Estados Unidos.',
		),
	);

	private const NAV_LABELS = array(
		'inicio'     => 'Inicio',
		'grupo'      => 'El Grupo',
		'profesores' => 'Profesores',
		'sedes'      => 'Sedes',
		'galeria'    => 'Galería',
		'eventos'    => 'Eventos',
		'contacto'   => 'Contacto',
	);

	private const MENU_SLUG  = 'navegacion-principal';
	private const MENU_TITLE = 'Navegación principal';

	private const META_SOURCE_SHA1     = '_pura_source_sha1';
	private const META_IMPORT_KEY      = '_pura_import_key';
	private const META_IMPORT_MODIFIED = '_pura_import_modified';

	/** @var array<string, array<string, string>> Inline HTML kept inside imported paragraphs. */
	private const INLINE_HTML = array(
		'strong' => array(),
		'em'     => array(),
		'b'      => array(),
		'i'      => array(),
		'br'     => array(),
		'span'   => array( 'class' => true ),
		'a'      => array(
			'href'   => true,
			'target' => true,
			'rel'    => true,
		),
	);

	private ?string $source = null;

	private bool $source_resolved = false;

	private bool $force = false;

	/**
	 * Import everything: media, sedes, profesores, eventos, gallery, pages, menu, settings.
	 *
	 * ## OPTIONS
	 *
	 * [--source=<path>]
	 * : Folder of the legacy static site (contains data/, assets/images/ and the profile pages).
	 *   Defaults to wp-content/pura-source when it exists.
	 *
	 * [--force]
	 * : Overwrite posts that were edited in the admin after their import.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pura-theme import all --source=/home/user/public_html_static_backup
	 *
	 * @param string[]              $args       Positional args.
	 * @param array<string, string> $assoc_args Named args.
	 */
	public function all( array $args, array $assoc_args ): void {
		$this->media( $args, $assoc_args );
		$this->sedes( $args, $assoc_args );
		$this->profesores( $args, $assoc_args );
		$this->eventos( $args, $assoc_args );
		$this->gallery( $args, $assoc_args );
		$this->pages( $args, $assoc_args );
		$this->menu( $args, $assoc_args );
		$this->settings( $args, $assoc_args );
		WP_CLI::success( 'Import complete.' );
	}

	/**
	 * Import the logo (site logo + site icon) and the Open Graph image.
	 *
	 * ## OPTIONS
	 *
	 * [--source=<path>]
	 * : Folder of the legacy static site.
	 *
	 * @param string[]              $args       Positional args.
	 * @param array<string, string> $assoc_args Named args.
	 */
	public function media( array $args, array $assoc_args ): void {
		$this->prepare( $assoc_args );

		$logo = $this->import_local_file( $this->find_image( 'logo-capoeira.png' ), 'Logo Pura Capoeira' );
		if ( $logo ) {
			set_theme_mod( 'custom_logo', $logo );
			update_option( 'site_icon', $logo );
			WP_CLI::log( "media: logo → #{$logo} (custom_logo, site_icon)" );
		} else {
			WP_CLI::warning( 'media: logo-capoeira.png not found.' );
		}

		$og = $this->import_local_file( PURA_THEME_DIR . '/assets/images/og-image.jpg', 'Pura Capoeira' );
		if ( $og && class_exists( 'Pura\Core\Settings' ) ) {
			Pura\Core\Settings::update( array( 'og_image_id' => $og ) );
			WP_CLI::log( "media: og-image → #{$og} (og_image_id)" );
		} elseif ( ! $og ) {
			WP_CLI::warning( 'media: og-image.jpg not found in the theme.' );
		}
	}

	/**
	 * Import sedes from data/sedes.json.
	 *
	 * ## OPTIONS
	 *
	 * [--source=<path>]
	 * : Folder of the legacy static site.
	 *
	 * [--force]
	 * : Overwrite posts edited since their import.
	 *
	 * @param string[]              $args       Positional args.
	 * @param array<string, string> $assoc_args Named args.
	 */
	public function sedes( array $args, array $assoc_args ): void {
		$this->prepare( $assoc_args );
		if ( ! class_exists( 'Pura\Core\Data\Sede_Post_Type' ) ) {
			WP_CLI::warning( 'sedes: plugin not active; skipping.' );
			return;
		}
		$items = $this->read_json( 'data/sedes.json', 'sedes' );
		if ( null === $items ) {
			return;
		}

		$type    = Pura\Core\Data\Sede_Post_Type::POST_TYPE;
		$created = 0;
		$updated = 0;
		$skipped = 0;

		foreach ( $items as $index => $item ) {
			$slug = sanitize_title( (string) ( $item['slug'] ?? '' ) );
			if ( '' === $slug ) {
				continue;
			}
			$existing = get_page_by_path( $slug, OBJECT, $type );
			if ( $existing && $this->edited_since_import( $existing ) ) {
				WP_CLI::warning( "sedes: {$slug} was edited in the admin; skipping (use --force)." );
				++$skipped;
				continue;
			}

			$postarr = array(
				'post_type'    => $type,
				'post_status'  => 'publish',
				'post_name'    => $slug,
				'post_title'   => sanitize_text_field( (string) ( $item['name'] ?? $slug ) ),
				'post_excerpt' => sanitize_textarea_field( (string) ( $item['blurb'] ?? '' ) ),
				'menu_order'   => (int) $index,
			);
			$id      = $this->save_post( $postarr, $existing );
			if ( ! $id ) {
				continue;
			}

			$meta = array(
				Pura\Core\Data\Sede_Post_Type::META_CITY   => sanitize_text_field( (string) ( $item['city'] ?? '' ) ),
				Pura\Core\Data\Sede_Post_Type::META_REGION => sanitize_text_field( (string) ( $item['region'] ?? '' ) ),
				Pura\Core\Data\Sede_Post_Type::META_COUNTRY => sanitize_text_field( (string) ( $item['country'] ?? '' ) ),
				Pura\Core\Data\Sede_Post_Type::META_ADDRESS => sanitize_text_field( (string) ( $item['address'] ?? '' ) ),
				Pura\Core\Data\Sede_Post_Type::META_RESPONSIBLE => sanitize_text_field( (string) ( $item['responsible'] ?? '' ) ),
				Pura\Core\Data\Sede_Post_Type::META_WHATSAPP => esc_url_raw( (string) ( $item['whatsapp'] ?? '' ) ),
				Pura\Core\Data\Sede_Post_Type::META_WHATSAPP_DISPLAY => sanitize_text_field( (string) ( $item['whatsappDisplay'] ?? '' ) ),
				Pura\Core\Data\Sede_Post_Type::META_INSTAGRAM => esc_url_raw( (string) ( $item['instagram'] ?? '' ) ),
				Pura\Core\Data\Sede_Post_Type::META_INSTAGRAM_HANDLE => sanitize_text_field( (string) ( $item['instagramHandle'] ?? '' ) ),
				Pura\Core\Data\Sede_Post_Type::META_FACEBOOK => sanitize_text_field( (string) ( $item['facebook'] ?? '' ) ),
				Pura\Core\Data\Sede_Post_Type::META_SCHEDULE => Pura\Core\Data\Meta_Fields::sanitize_rows( $item['schedule'] ?? array(), Pura\Core\Data\Sede_Post_Type::SCHEDULE_COLUMNS ),
				Pura\Core\Data\Sede_Post_Type::META_PRICING => Pura\Core\Data\Meta_Fields::sanitize_rows( $item['pricing'] ?? array(), Pura\Core\Data\Sede_Post_Type::PRICING_COLUMNS ),
				Pura\Core\Data\Sede_Post_Type::META_LAT    => isset( $item['lat'] ) && is_numeric( $item['lat'] ) ? (float) $item['lat'] : 0.0,
				Pura\Core\Data\Sede_Post_Type::META_LNG    => isset( $item['lng'] ) && is_numeric( $item['lng'] ) ? (float) $item['lng'] : 0.0,
			);
			$this->save_meta( $id, $meta );
			$this->set_thumbnail( $id, (string) ( $item['image'] ?? '' ), (string) ( $item['name'] ?? $slug ) );
			$this->mark_imported( $id );

			$existing ? ++$updated : ++$created;
			WP_CLI::log( sprintf( 'sedes: %s → #%d (%s)', $slug, $id, $existing ? 'updated' : 'created' ) );
		}

		WP_CLI::log( "sedes: created {$created}, updated {$updated}, skipped {$skipped}." );
	}

	/**
	 * Import teachers from data/profesores.json and their static profile pages.
	 *
	 * ## OPTIONS
	 *
	 * [--source=<path>]
	 * : Folder of the legacy static site.
	 *
	 * [--force]
	 * : Overwrite posts edited since their import.
	 *
	 * @param string[]              $args       Positional args.
	 * @param array<string, string> $assoc_args Named args.
	 */
	public function profesores( array $args, array $assoc_args ): void {
		$this->prepare( $assoc_args );
		if ( ! class_exists( 'Pura\Core\Data\Profesor_Post_Type' ) ) {
			WP_CLI::warning( 'profesores: plugin not active; skipping.' );
			return;
		}
		$items = $this->read_json( 'data/profesores.json', 'profesores' );
		if ( null === $items ) {
			return;
		}

		$type    = Pura\Core\Data\Profesor_Post_Type::POST_TYPE;
		$created = 0;
		$updated = 0;
		$skipped = 0;

		foreach ( $items as $index => $item ) {
			$nick = sanitize_text_field( (string) ( $item['capoeiraName'] ?? $item['name'] ?? '' ) );
			$slug = sanitize_title( $nick );
			if ( '' === $slug ) {
				continue;
			}
			$existing = get_page_by_path( $slug, OBJECT, $type );
			if ( $existing && $this->edited_since_import( $existing ) ) {
				WP_CLI::warning( "profesores: {$slug} was edited in the admin; skipping (use --force)." );
				++$skipped;
				continue;
			}

			$profile = $this->parse_profile_page( (string) ( $item['profilePage'] ?? '' ) );

			$postarr = array(
				'post_type'    => $type,
				'post_status'  => 'publish',
				'post_name'    => $slug,
				'post_title'   => $nick,
				'post_excerpt' => sanitize_textarea_field( (string) ( $item['bio'] ?? '' ) ),
				'post_content' => $profile['content'],
				'menu_order'   => (int) $index,
			);
			$id      = $this->save_post( $postarr, $existing );
			if ( ! $id ) {
				continue;
			}

			$sede_slug = sanitize_title( pathinfo( (string) ( $item['locationPage'] ?? '' ), PATHINFO_FILENAME ) );
			$sede      = '' !== $sede_slug && class_exists( 'Pura\Core\Data\Sede_Post_Type' ) ? get_page_by_path( $sede_slug, OBJECT, Pura\Core\Data\Sede_Post_Type::POST_TYPE ) : null;

			$meta = array(
				Pura\Core\Data\Profesor_Post_Type::META_FULL_NAME        => sanitize_text_field( (string) ( $item['name'] ?? '' ) ),
				Pura\Core\Data\Profesor_Post_Type::META_RANK             => sanitize_text_field( (string) ( $item['title'] ?? '' ) ),
				Pura\Core\Data\Profesor_Post_Type::META_CITY             => sanitize_text_field( (string) ( $item['city'] ?? '' ) ),
				Pura\Core\Data\Profesor_Post_Type::META_COUNTRY          => sanitize_text_field( (string) ( $item['country'] ?? '' ) ),
				Pura\Core\Data\Profesor_Post_Type::META_SEDE_ID          => $sede ? $sede->ID : 0,
				Pura\Core\Data\Profesor_Post_Type::META_HERO_EYEBROW     => $profile['eyebrow'],
				Pura\Core\Data\Profesor_Post_Type::META_SUBTITLE         => $profile['subtitle'],
				Pura\Core\Data\Profesor_Post_Type::META_CAPTION          => $profile['caption'],
				Pura\Core\Data\Profesor_Post_Type::META_INSTAGRAM        => esc_url_raw( (string) ( $item['instagram'] ?? '' ) ),
				Pura\Core\Data\Profesor_Post_Type::META_INSTAGRAM_HANDLE => sanitize_text_field( (string) ( $item['instagramHandle'] ?? '' ) ),
				Pura\Core\Data\Profesor_Post_Type::META_FACEBOOK         => esc_url_raw( (string) ( $item['facebook'] ?? '' ) ),
				Pura\Core\Data\Profesor_Post_Type::META_WEBSITE          => esc_url_raw( (string) ( $item['website'] ?? '' ) ),
				Pura\Core\Data\Profesor_Post_Type::META_YOUTUBE          => esc_url_raw( (string) ( $item['youtube'] ?? '' ) ),
				Pura\Core\Data\Profesor_Post_Type::META_WHATSAPP         => esc_url_raw( (string) ( $item['whatsapp'] ?? '' ) ),
				Pura\Core\Data\Profesor_Post_Type::META_WHATSAPP_DISPLAY => sanitize_text_field( (string) ( $item['whatsappDisplay'] ?? '' ) ),
			);
			$this->save_meta( $id, $meta );
			$this->set_thumbnail( $id, (string) ( $item['image'] ?? '' ), $nick );
			$this->seed_translations( $id, $type, $slug );
			$this->mark_imported( $id );

			$existing ? ++$updated : ++$created;
			WP_CLI::log( sprintf( 'profesores: %s → #%d (%s)', $slug, $id, $existing ? 'updated' : 'created' ) );
		}

		WP_CLI::log( "profesores: created {$created}, updated {$updated}, skipped {$skipped}." );
	}

	/**
	 * Import events from data/eventos.json.
	 *
	 * ## OPTIONS
	 *
	 * [--source=<path>]
	 * : Folder of the legacy static site.
	 *
	 * [--force]
	 * : Overwrite posts edited since their import.
	 *
	 * @param string[]              $args       Positional args.
	 * @param array<string, string> $assoc_args Named args.
	 */
	public function eventos( array $args, array $assoc_args ): void {
		$this->prepare( $assoc_args );
		if ( ! class_exists( 'Pura\Core\Data\Evento_Post_Type' ) ) {
			WP_CLI::warning( 'eventos: plugin not active; skipping.' );
			return;
		}
		$items = $this->read_json( 'data/eventos.json', 'eventos' );
		if ( null === $items ) {
			return;
		}

		$type    = Pura\Core\Data\Evento_Post_Type::POST_TYPE;
		$created = 0;
		$updated = 0;
		$skipped = 0;

		foreach ( $items as $item ) {
			$title = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
			if ( '' === $title ) {
				continue;
			}
			$key      = md5( $title . '|' . (string) ( $item['date'] ?? '' ) );
			$existing = $this->find_by_import_key( $type, $key );
			if ( $existing && $this->edited_since_import( $existing ) ) {
				WP_CLI::warning( "eventos: \"{$title}\" was edited in the admin; skipping (use --force)." );
				++$skipped;
				continue;
			}

			$postarr = array(
				'post_type'    => $type,
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_excerpt' => sanitize_textarea_field( (string) ( $item['description'] ?? '' ) ),
			);
			$id      = $this->save_post( $postarr, $existing );
			if ( ! $id ) {
				continue;
			}

			$this->save_meta(
				$id,
				array(
					self::META_IMPORT_KEY => $key,
					Pura\Core\Data\Evento_Post_Type::META_DATE => sanitize_text_field( (string) ( $item['date'] ?? '' ) ),
					Pura\Core\Data\Evento_Post_Type::META_TIME => sanitize_text_field( (string) ( $item['time'] ?? '' ) ),
					Pura\Core\Data\Evento_Post_Type::META_LOCATION => sanitize_text_field( (string) ( $item['location'] ?? '' ) ),
					Pura\Core\Data\Evento_Post_Type::META_VENUE => sanitize_text_field( (string) ( $item['venue'] ?? '' ) ),
					Pura\Core\Data\Evento_Post_Type::META_STATUS => sanitize_text_field( (string) ( $item['status'] ?? '' ) ),
					Pura\Core\Data\Evento_Post_Type::META_URL => esc_url_raw( (string) ( $item['url'] ?? '' ) ),
				)
			);
			$this->mark_imported( $id );

			$existing ? ++$updated : ++$created;
		}

		WP_CLI::log( "eventos: created {$created}, updated {$updated}, skipped {$skipped}." );
	}

	/**
	 * Import gallery items from data/gallery.json.
	 *
	 * ## OPTIONS
	 *
	 * [--source=<path>]
	 * : Folder of the legacy static site.
	 *
	 * [--force]
	 * : Overwrite posts edited since their import.
	 *
	 * @param string[]              $args       Positional args.
	 * @param array<string, string> $assoc_args Named args.
	 */
	public function gallery( array $args, array $assoc_args ): void {
		$this->prepare( $assoc_args );
		if ( ! class_exists( 'Pura\Core\Data\Galeria_Post_Type' ) ) {
			WP_CLI::warning( 'gallery: plugin not active; skipping.' );
			return;
		}
		$items = $this->read_json( 'data/gallery.json', 'gallery' );
		if ( null === $items ) {
			return;
		}

		$type    = Pura\Core\Data\Galeria_Post_Type::POST_TYPE;
		$created = 0;
		$updated = 0;
		$skipped = 0;

		foreach ( $items as $item ) {
			$title = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
			if ( '' === $title ) {
				continue;
			}
			$url      = trim( (string) ( $item['url'] ?? '' ) );
			$url      = '#' === $url ? '' : esc_url_raw( $url );
			$key      = md5( $title . '|' . $url . '|' . (string) ( $item['thumbnail'] ?? '' ) );
			$existing = $this->find_by_import_key( $type, $key );
			if ( $existing && $this->edited_since_import( $existing ) ) {
				WP_CLI::warning( "gallery: \"{$title}\" was edited in the admin; skipping (use --force)." );
				++$skipped;
				continue;
			}

			$date    = isset( $item['date'] ) ? strtotime( (string) $item['date'] . ' 12:00:00' ) : false;
			$postarr = array(
				'post_type'    => $type,
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_excerpt' => sanitize_textarea_field( (string) ( $item['description'] ?? '' ) ),
			);
			if ( $date ) {
				$postarr['post_date']     = wp_date( 'Y-m-d H:i:s', $date );
				$postarr['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $date );
			}
			$id = $this->save_post( $postarr, $existing );
			if ( ! $id ) {
				continue;
			}

			$this->save_meta(
				$id,
				array(
					self::META_IMPORT_KEY => $key,
					Pura\Core\Data\Galeria_Post_Type::META_URL => $url,
				)
			);
			if ( ! empty( $item['category'] ) ) {
				wp_set_object_terms( $id, array( sanitize_text_field( (string) $item['category'] ) ), Pura\Core\Data\Galeria_Post_Type::TAX_CATEGORY );
			}
			if ( ! empty( $item['location'] ) ) {
				wp_set_object_terms( $id, array( sanitize_text_field( (string) $item['location'] ) ), Pura\Core\Data\Galeria_Post_Type::TAX_LOCATION );
			}
			$this->set_thumbnail( $id, (string) ( $item['thumbnail'] ?? '' ), $title );
			$this->mark_imported( $id );

			$existing ? ++$updated : ++$created;
		}

		WP_CLI::log( "gallery: created {$created}, updated {$updated}, skipped {$skipped}." );
	}

	/**
	 * Create or refresh the seven pages from the theme's page patterns.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Overwrite pages edited since their import.
	 *
	 * @param string[]              $args       Positional args.
	 * @param array<string, string> $assoc_args Named args.
	 */
	public function pages( array $args, array $assoc_args ): void {
		$this->prepare( $assoc_args );
		$registry = WP_Block_Patterns_Registry::get_instance();

		foreach ( self::PAGES as $slug => $meta ) {
			$pattern = $registry->get_registered( 'puracapoeira/page-' . $slug );
			if ( ! $pattern ) {
				WP_CLI::warning( "pages: pattern puracapoeira/page-{$slug} not registered; skipping." );
				continue;
			}

			$blocks  = parse_blocks( (string) $pattern['content'] );
			$blocks  = function_exists( 'resolve_pattern_blocks' ) ? resolve_pattern_blocks( $blocks ) : $blocks;
			$content = serialize_blocks( $blocks );

			$existing = get_page_by_path( $slug );
			if ( $existing && $this->edited_since_import( $existing ) ) {
				WP_CLI::warning( "pages: {$slug} was edited in the admin; skipping (use --force)." );
				continue;
			}

			$postarr = array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'post_name'      => $slug,
				'post_title'     => $meta['title'],
				'post_excerpt'   => $meta['excerpt'],
				'post_content'   => $content,
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			);
			$id      = $this->save_post( $postarr, $existing );
			if ( ! $id ) {
				continue;
			}
			$this->mark_imported( $id );
			WP_CLI::log( sprintf( 'pages: %s → #%d (%s)', $slug, $id, $existing ? 'updated' : 'created' ) );

			if ( 'inicio' === $slug ) {
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', $id );
			}
		}

		// Retire the sample page/post if they still exist.
		foreach ( array( 'sample-page', 'hello-world' ) as $sample ) {
			$post = get_page_by_path( $sample, OBJECT, array( 'page', 'post' ) );
			if ( $post ) {
				wp_trash_post( $post->ID );
			}
		}

		flush_rewrite_rules();
	}

	/**
	 * Create or refresh the main navigation (wp_navigation "navegacion-principal") from the pages.
	 *
	 * @param string[]              $args       Positional args.
	 * @param array<string, string> $assoc_args Named args.
	 */
	public function menu( array $args, array $assoc_args ): void {
		$links = array();
		foreach ( self::NAV_LABELS as $slug => $label ) {
			$page = get_page_by_path( $slug );
			if ( ! $page ) {
				continue;
			}
			$links[] = $this->block(
				'core/navigation-link',
				array(
					'label'     => $label,
					'type'      => 'page',
					'id'        => $page->ID,
					'url'       => get_permalink( $page ),
					'kind'      => 'post-type',
					'className' => 'i18n-nav-' . $slug,
				)
			);
		}

		if ( ! $links ) {
			WP_CLI::warning( 'menu: no pages found; run `pages` first.' );
			return;
		}

		$existing = get_page_by_path( self::MENU_SLUG, OBJECT, 'wp_navigation' );
		$postarr  = array(
			'post_type'    => 'wp_navigation',
			'post_status'  => 'publish',
			'post_name'    => self::MENU_SLUG,
			'post_title'   => self::MENU_TITLE,
			'post_content' => implode( "\n", $links ),
		);
		$id       = $this->save_post( $postarr, $existing );
		if ( ! $id ) {
			return;
		}
		WP_CLI::log( sprintf( 'menu: %s → #%d (%s)', self::MENU_SLUG, $id, $existing ? 'updated' : 'created' ) );

		// Other navigation posts would compete with ours in the editor's fallback picker.
		$others = get_posts(
			array(
				'post_type'      => 'wp_navigation',
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'exclude'        => array( $id ),
				'fields'         => 'ids',
			)
		);
		foreach ( $others as $other_id ) {
			wp_trash_post( (int) $other_id );
			WP_CLI::log( "menu: trashed stray navigation #{$other_id}" );
		}
	}

	/**
	 * Seed plugin settings that are still at their defaults from the first sede.
	 *
	 * @param string[]              $args       Positional args.
	 * @param array<string, string> $assoc_args Named args.
	 */
	public function settings( array $args, array $assoc_args ): void {
		$this->prepare( $assoc_args );
		if ( ! class_exists( 'Pura\Core\Settings' ) ) {
			WP_CLI::warning( 'settings: plugin not active; skipping.' );
			return;
		}
		$items = $this->read_json( 'data/sedes.json', 'settings', false );
		if ( ! $items ) {
			WP_CLI::log( 'settings: nothing to seed (no sedes.json).' );
			return;
		}

		$first  = $items[0];
		$update = array();
		$map    = array(
			'whatsapp_number'  => preg_replace( '/\D+/', '', (string) ( $first['whatsapp'] ?? '' ) ),
			'whatsapp_display' => (string) ( $first['whatsappDisplay'] ?? '' ),
			'instagram_url'    => (string) ( $first['instagram'] ?? '' ),
			'instagram_handle' => (string) ( $first['instagramHandle'] ?? '' ),
		);
		foreach ( $map as $key => $value ) {
			if ( '' !== $value && 'default' === Pura\Core\Settings::source( $key ) ) {
				$update[ $key ] = $value;
			}
		}
		if ( $update ) {
			Pura\Core\Settings::update( $update );
			WP_CLI::log( 'settings: seeded ' . implode( ', ', array_keys( $update ) ) );
		} else {
			WP_CLI::log( 'settings: nothing to change.' );
		}
	}

	/* ---------------------------------------------------------------- helpers */

	/**
	 * @param array<string, string> $assoc_args Named args.
	 */
	private function prepare( array $assoc_args ): void {
		$this->force = isset( $assoc_args['force'] );
		$this->resolve_source( $assoc_args );
	}

	/**
	 * @param array<string, string> $assoc_args Named args.
	 */
	private function resolve_source( array $assoc_args ): bool {
		if ( ! empty( $assoc_args['source'] ) ) {
			$dir = rtrim( (string) $assoc_args['source'], '/' );
			if ( ! is_dir( $dir ) ) {
				WP_CLI::error( "Source directory not found: {$dir}" );
			}
			$this->source          = $dir;
			$this->source_resolved = true;
			return true;
		}
		if ( $this->source_resolved ) {
			return null !== $this->source;
		}
		$this->source_resolved = true;
		$candidate             = rtrim( WP_CONTENT_DIR, '/' ) . '/pura-source';
		if ( is_dir( $candidate ) ) {
			$this->source = $candidate;
			return true;
		}
		$this->source = null;

		return false;
	}

	/**
	 * @return array<int, array<string, mixed>>|null Null when unavailable (already reported).
	 */
	private function read_json( string $relative, string $label, bool $warn = true ): ?array {
		if ( null === $this->source ) {
			if ( $warn ) {
				WP_CLI::warning( "{$label}: no static source folder (use --source=/path/to/static/site); skipping." );
			}
			return null;
		}
		$file = $this->source . '/' . $relative;
		if ( ! is_readable( $file ) ) {
			if ( $warn ) {
				WP_CLI::warning( "{$label}: {$file} not readable; skipping." );
			}
			return null;
		}
		$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_array( $data ) ) {
			if ( $warn ) {
				WP_CLI::warning( "{$label}: {$file} is not valid JSON; skipping." );
			}
			return null;
		}

		return array_values( array_filter( $data, 'is_array' ) );
	}

	/**
	 * Absolute path of a static-site image (`./assets/images/x.jpg?v=1` → `$source/assets/images/x.jpg`),
	 * or '' for remote URLs and missing files.
	 */
	private function find_image( string $reference ): string {
		$reference = trim( $reference );
		if ( '' === $reference || preg_match( '#^https?://#i', $reference ) ) {
			return '';
		}
		$path = (string) preg_replace( '/[?#].*$/', '', $reference );
		$path = ltrim( $path, './' );
		if ( false === strpos( $path, '/' ) ) {
			$path = 'assets/images/' . $path;
		}

		foreach ( array_filter( array( $this->source, PURA_THEME_DIR ) ) as $base ) {
			$candidate = $base . '/' . $path;
			if ( is_readable( $candidate ) ) {
				return $candidate;
			}
		}

		return '';
	}

	/**
	 * Sideload a local file into the media library once (deduped by content hash).
	 */
	private function import_local_file( string $path, string $title ): int {
		if ( '' === $path || ! is_readable( $path ) ) {
			return 0;
		}
		$hash     = (string) sha1_file( $path );
		$existing = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => self::META_SOURCE_SHA1, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $hash, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		if ( $existing ) {
			return (int) $existing[0];
		}

		$this->require_media_functions();
		$filename = sanitize_file_name( basename( $path ) );
		$upload   = wp_upload_bits( $filename, null, (string) file_get_contents( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! empty( $upload['error'] ) ) {
			WP_CLI::warning( "media: {$filename}: " . $upload['error'] );
			return 0;
		}

		$type = wp_check_filetype( $upload['file'] );
		$id   = wp_insert_attachment(
			array(
				'post_mime_type' => (string) ( $type['type'] ?? 'image/jpeg' ),
				'post_title'     => $title,
				'post_status'    => 'inherit',
			),
			$upload['file'],
			0,
			true
		);
		if ( is_wp_error( $id ) || ! $id ) {
			WP_CLI::warning( "media: {$filename}: " . ( is_wp_error( $id ) ? $id->get_error_message() : 'could not create the attachment' ) );
			return 0;
		}

		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
		update_post_meta( $id, self::META_SOURCE_SHA1, $hash );
		update_post_meta( $id, '_wp_attachment_image_alt', $title );

		return (int) $id;
	}

	private function set_thumbnail( int $post_id, string $reference, string $title ): void {
		$path = $this->find_image( $reference );
		if ( '' === $path ) {
			if ( preg_match( '#^https?://#i', $reference ) ) {
				WP_CLI::log( "  (remote image skipped for #{$post_id}: {$reference})" );
			}
			return;
		}
		$attachment = $this->import_local_file( $path, $title );
		if ( $attachment ) {
			set_post_thumbnail( $post_id, $attachment );
		}
	}

	private function require_media_functions(): void {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	/**
	 * @param array<string, mixed> $postarr Post array.
	 */
	private function save_post( array $postarr, ?WP_Post $existing ): int {
		if ( $existing ) {
			$postarr['ID'] = $existing->ID;
			$id            = wp_update_post( wp_slash( $postarr ), true );
		} else {
			$id = wp_insert_post( wp_slash( $postarr ), true );
		}
		if ( is_wp_error( $id ) ) {
			WP_CLI::warning( ( $postarr['post_type'] ?? 'post' ) . ': ' . ( $postarr['post_name'] ?? $postarr['post_title'] ?? '' ) . ': ' . $id->get_error_message() );
			return 0;
		}

		return (int) $id;
	}

	/**
	 * @param array<string, mixed> $meta Meta values; empty strings delete the key.
	 */
	private function save_meta( int $post_id, array $meta ): void {
		foreach ( $meta as $key => $value ) {
			if ( '' === $value || array() === $value ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
		}
	}

	private function mark_imported( int $post_id ): void {
		clean_post_cache( $post_id );
		update_post_meta( $post_id, self::META_IMPORT_MODIFIED, (string) get_post_field( 'post_modified_gmt', $post_id ) );
	}

	private function edited_since_import( WP_Post $post ): bool {
		if ( $this->force ) {
			return false;
		}
		$stored = (string) get_post_meta( $post->ID, self::META_IMPORT_MODIFIED, true );

		return '' !== $stored && $stored !== $post->post_modified_gmt;
	}

	private function find_by_import_key( string $type, string $key ): ?WP_Post {
		$found = get_posts(
			array(
				'post_type'      => $type,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'meta_key'       => self::META_IMPORT_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		return $found ? $found[0] : null;
	}

	/**
	 * Apply the generated translations (themes/…/import/i18n/<type>-<slug>.json) as `_pura_i18n`.
	 */
	private function seed_translations( int $post_id, string $type, string $slug ): void {
		$file = PURA_THEME_DIR . '/import/i18n/' . $type . '-' . $slug . '.json';
		if ( ! is_readable( $file ) ) {
			return;
		}
		$json = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_array( json_decode( $json, true ) ) ) {
			WP_CLI::warning( "profesores: {$file} is not valid JSON; translations skipped." );
			return;
		}
		if ( class_exists( 'Pura\Core\Data\I18n_Meta' ) ) {
			$json = Pura\Core\Data\I18n_Meta::sanitize( $json );
		}
		if ( '' !== $json ) {
			// Meta values are unslashed on save; JSON escapes (\" and \/) must survive that.
			update_post_meta( $post_id, '_pura_i18n', wp_slash( $json ) );
		}
	}

	/**
	 * Serialized block comment + markup.
	 *
	 * @param array<string, mixed> $attrs Attributes.
	 */
	private function block( string $name, array $attrs = array(), string $inner = '' ): string {
		$short = str_starts_with( $name, 'core/' ) ? substr( $name, 5 ) : $name;
		$json  = $attrs ? ' ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : '';
		if ( '' === $inner ) {
			return "<!-- wp:{$short}{$json} /-->";
		}

		return "<!-- wp:{$short}{$json} -->\n{$inner}\n<!-- /wp:{$short} -->";
	}

	/* --------------------------------------------------- profile HTML → blocks */

	/**
	 * Hero text, caption and the biography as block markup, from a static profile page.
	 *
	 * @return array{content:string,eyebrow:string,subtitle:string,caption:string}
	 */
	private function parse_profile_page( string $reference ): array {
		$empty = array(
			'content'  => '',
			'eyebrow'  => '',
			'subtitle' => '',
			'caption'  => '',
		);
		if ( null === $this->source || '' === $reference ) {
			return $empty;
		}
		$file = $this->source . '/' . ltrim( $reference, '/' );
		if ( ! is_readable( $file ) ) {
			WP_CLI::warning( "profesores: profile page {$file} not found; importing without biography." );
			return $empty;
		}

		$html = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$doc  = new DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		$xpath = new DOMXPath( $doc );

		$text = static function ( string $query ) use ( $xpath ): string {
			$node = $xpath->query( $query )->item( 0 );

			return $node ? sanitize_text_field( (string) preg_replace( '/\s+/u', ' ', $node->textContent ) ) : '';
		};

		$result             = $empty;
		$result['eyebrow']  = $text( '//section[contains(@class,"page-hero")]//*[contains(@class,"eyebrow")]' );
		$result['subtitle'] = $text( '//section[contains(@class,"page-hero")]//p' );
		$result['caption']  = $text( '//*[contains(@class,"prof-profile__caption")]//p' );

		$blocks   = array();
		$sections = $xpath->query( '//*[contains(@class,"prof-profile__content")]/section' );
		$info     = 0;
		foreach ( $sections as $section ) {
			$class = (string) $section->getAttribute( 'class' );
			if ( false !== strpos( $class, 'prof-social' ) ) {
				continue;
			}
			if ( false !== strpos( $class, 'prof-timeline' ) ) {
				$blocks[] = $this->timeline_blocks( $section, $xpath );
				continue;
			}
			if ( false !== strpos( $class, 'info-block' ) ) {
				++$info;
				$prefix   = 1 === $info ? 'bio' : ( 2 === $info ? 'traj' : 'sec' . $info );
				$blocks[] = $this->info_block( $section, $prefix );
			}
		}

		$result['content'] = implode( "\n\n", array_filter( $blocks ) );

		return $result;
	}

	private function info_block( DOMElement $section, string $prefix ): string {
		$inner = array();
		$p     = 0;
		$h     = 0;
		$list  = 0;
		foreach ( $section->childNodes as $node ) {
			if ( ! $node instanceof DOMElement ) {
				continue;
			}
			$tag = strtolower( $node->tagName );
			if ( 'h3' === $tag ) {
				$inner[] = $this->heading( 3, $this->inline_html( $node ), 'i18n-' . $prefix . '-title' );
			} elseif ( 'h4' === $tag ) {
				++$h;
				$inner[] = $this->heading( 4, $this->inline_html( $node ), 'i18n-' . $prefix . '-h-' . $h );
			} elseif ( 'p' === $tag ) {
				++$p;
				$inner[] = $this->paragraph( $this->inline_html( $node ), 'i18n-' . $prefix . '-' . $p );
			} elseif ( 'ul' === $tag || 'ol' === $tag ) {
				++$list;
				$items = array();
				$i     = 0;
				foreach ( $node->childNodes as $li ) {
					if ( $li instanceof DOMElement && 'li' === strtolower( $li->tagName ) ) {
						++$i;
						$class   = 'i18n-' . $prefix . '-li' . $list . '-' . $i;
						$items[] = $this->block( 'core/list-item', array( 'className' => $class ), '<li class="' . esc_attr( $class ) . '">' . $this->inline_html( $li ) . '</li>' );
					}
				}
				if ( $items ) {
					$attrs   = 'ol' === $tag ? array( 'ordered' => true ) : array();
					$inner[] = $this->block( 'core/list', $attrs, '<' . $tag . ' class="wp-block-list">' . "\n" . implode( "\n", $items ) . "\n" . '</' . $tag . '>' );
				}
			}
		}
		if ( ! $inner ) {
			return '';
		}

		return $this->block(
			'core/group',
			array(
				'tagName'   => 'section',
				'className' => 'info-block',
				'layout'    => array( 'type' => 'default' ),
			),
			'<section class="wp-block-group info-block">' . "\n" . implode( "\n", $inner ) . "\n" . '</section>'
		);
	}

	private function timeline_blocks( DOMElement $section, DOMXPath $xpath ): string {
		$inner   = array();
		$heading = $xpath->query( './/h3', $section )->item( 0 );
		if ( $heading ) {
			$inner[] = $this->heading( 3, $this->inline_html( $heading ), 'i18n-timeline-title' );
		}

		$items = array();
		$n     = 0;
		foreach ( $xpath->query( './/li[contains(@class,"timeline-item")]', $section ) as $li ) {
			++$n;
			$year  = $xpath->query( './/*[contains(@class,"timeline-item__year")]', $li )->item( 0 );
			$title = $xpath->query( './/h4', $li )->item( 0 );
			$text  = $xpath->query( './/p', $li )->item( 0 );
			$key   = 'tl-' . $n;

			$items[] = $this->block(
				'pura/timeline-item',
				array( 'i18nKey' => $key ),
				'<li class="wp-block-pura-timeline-item timeline-item">'
				. '<span class="timeline-item__year">' . ( $year ? $this->inline_html( $year ) : '' ) . '</span>'
				. '<div class="timeline-item__card">'
				. '<h4 data-i18n-key="' . esc_attr( $key ) . '-title">' . ( $title ? $this->inline_html( $title ) : '' ) . '</h4>'
				. '<p data-i18n-key="' . esc_attr( $key ) . '-text">' . ( $text ? $this->inline_html( $text ) : '' ) . '</p>'
				. '</div></li>'
			);
		}
		if ( $items ) {
			$inner[] = $this->block(
				'pura/timeline',
				array(),
				'<ol class="wp-block-pura-timeline timeline reveal-on-scroll" data-reveal-delay="90">' . "\n" . implode( "\n", $items ) . "\n" . '</ol>'
			);
		}
		if ( ! $inner ) {
			return '';
		}

		return $this->block(
			'core/group',
			array(
				'tagName'   => 'section',
				'className' => 'prof-timeline',
				'layout'    => array( 'type' => 'default' ),
			),
			'<section class="wp-block-group prof-timeline">' . "\n" . implode( "\n", $inner ) . "\n" . '</section>'
		);
	}

	private function heading( int $level, string $html, string $class ): string {
		return $this->block(
			'core/heading',
			array(
				'level'     => $level,
				'className' => $class,
			),
			'<h' . $level . ' class="wp-block-heading ' . esc_attr( $class ) . '">' . $html . '</h' . $level . '>'
		);
	}

	private function paragraph( string $html, string $class ): string {
		return $this->block(
			'core/paragraph',
			array( 'className' => $class ),
			'<p class="' . esc_attr( $class ) . '">' . $html . '</p>'
		);
	}

	/**
	 * Inner HTML of a node reduced to safe inline markup with normalised whitespace.
	 */
	private function inline_html( DOMNode $node ): string {
		$html = '';
		foreach ( $node->childNodes as $child ) {
			$html .= $node->ownerDocument->saveHTML( $child );
		}
		$html = html_entity_decode( $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$html = (string) preg_replace( '/\s+/u', ' ', $html );

		return trim( wp_kses( $html, self::INLINE_HTML ) );
	}
}

WP_CLI::add_command( 'pura-theme import', Pura_Theme_Import_Command::class );
