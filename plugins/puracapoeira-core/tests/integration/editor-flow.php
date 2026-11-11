<?php
/**
 * Integration check of the professors' editor against a real WordPress.
 *
 * Run in the local environment (it creates test users and changes content, never run it on
 * the live site):
 *
 *   npx wp-env run cli wp eval-file wp-content/plugins/puracapoeira-core/tests/integration/editor-flow.php
 *
 * It refuses to run unless WP_ENVIRONMENT_TYPE is "local".
 */

use Pura\Core\Admin\Change_Actions;
use Pura\Core\Auth\Editor_Access;
use Pura\Core\Auth\Member_Accounts;
use Pura\Core\Data\Change_Post_Type;
use Pura\Core\Data\I18n_Meta;
use Pura\Core\Data\Profesor_Post_Type;
use Pura\Core\Data\Sede_Post_Type;
use Pura\Core\Editor\Bio_Document;
use Pura\Core\Editor\Editor_Photos;
use Pura\Core\Editor\Editor_Writer;

if ( 'local' !== wp_get_environment_type() ) {
	WP_CLI::error( 'This check only runs on a local environment.' );
}

// Earlier runs count against the per-user rate limit; start from zero (local environment only).
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_rl\_%' OR option_name LIKE '\_transient\_timeout\_rl\_%'" );
wp_cache_flush();

$failures = 0;
$check    = static function ( string $label, $got, $want ) use ( &$failures ): void {
	$ok = wp_json_encode( $got ) === wp_json_encode( $want );
	if ( ! $ok ) {
		++$failures;
	}
	WP_CLI::line( ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok ? '' : "\n      got  " . wp_json_encode( $got ) . "\n      want " . wp_json_encode( $want ) ) );
};

$mails = array();
add_filter(
	'pre_wp_mail',
	static function ( $short, $atts ) use ( &$mails ) {
		$mails[] = $atts;
		return true;
	},
	10,
	2
);

$member = static function ( string $login, string $email ): int {
	$user = get_user_by( 'login', $login );
	$id   = $user ? $user->ID : wp_insert_user(
		array(
			'user_login' => $login,
			'user_pass'  => wp_generate_password( 32 ),
			'user_email' => $login . '@accounts.test',
			'role'       => Member_Accounts::ROLE,
		)
	);
	update_user_meta( $id, Member_Accounts::META_VERIFIED_EMAIL, $email );
	return (int) $id;
};

$call = static function ( int $user_id, string $method, string $route, ?array $body = null ): array {
	wp_set_current_user( $user_id );
	$request = new WP_REST_Request( $method, $route );
	if ( null !== $body ) {
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );
	}
	$response = rest_do_request( $request );
	$response = apply_filters( 'rest_request_after_callbacks', $response, array(), $request );
	if ( is_wp_error( $response ) ) {
		$response = rest_convert_error_to_response( $response );
	}
	return array( $response->get_status(), (array) $response->get_data() );
};

// ---------- fixtures ----------
$prof  = $member( 'pura_test_prof', 'prof@editor.test' );
$other = $member( 'pura_test_other', 'other@editor.test' );
$admin = (int) get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
)[0];

$sedes     = Sede_Post_Type::all();
$profes    = Profesor_Post_Type::all();
$sede      = $sedes[0];
$sede_b    = $sedes[1];
$profesor  = $profes[0];
$sede_path = '/pura/v1/editor/sede/' . $sede->ID;

Editor_Access::set_emails( $sede->ID, array( 'prof@editor.test', 'second@editor.test' ) );
Editor_Access::set_emails( $profesor->ID, array( 'PROF@editor.test' ) );
Editor_Access::set_emails( $sede_b->ID, array() );
update_post_meta( $sede->ID, 'pura_address', 'Calle Uno 1' );
// A known starting point, so the check can be run again and again.
update_post_meta( $profesor->ID, 'pura_subtitle', 'Presentación de antes.' );
delete_post_meta( $profesor->ID, 'pura_website' );
update_post_meta( $sede_b->ID, 'pura_responsible', 'Responsable de antes' );
update_post_meta(
	$sede->ID,
	'pura_pricing',
	array(
		array(
			'label' => 'Mes',
			'value' => '$500',
		),
	)
);
update_post_meta(
	$profesor->ID,
	I18n_Meta::META_KEY,
	wp_slash(
		wp_json_encode(
			array(
				'en' => array(
					'hero-intro' => 'Old intro',
					'bio-1'      => 'Kept',
				),
			)
		)
	)
);
$bio_before = get_post( $profesor->ID )->post_content;
$token      = static fn ( int $id ): string => \Pura\Core\Editor\Editor_Document::version( get_post( $id ) );
$changes    = static fn (): int => (int) wp_count_posts( Change_Post_Type::POST_TYPE )->private;
$count0     = $changes();

// ---------- reading ----------
list( $status ) = $call( 0, 'GET', '/pura/v1/editor/me' );
$check( 'signed out cannot read', $status, 401 );

list( $status, $data ) = $call( $other, 'GET', '/pura/v1/editor/me' );
$check( 'a member with no link sees nothing to edit', array( $status, $data['profesor'], $data['sedes'] ), array( 200, null, array() ) );

list( $status, $data ) = $call( $prof, 'GET', '/pura/v1/editor/me' );
$check( 'a linked professor sees their profile and their sede', array( $status, $data['profesor']['id'], wp_list_pluck( $data['sedes'], 'id' ) ), array( 200, $profesor->ID, array( $sede->ID ) ) );
$check( '  with the editable values', array( $data['sedes'][0]['fields']['address'], $data['sedes'][0]['fields']['pricing'][0]['value'] ), array( 'Calle Uno 1', '$500' ) );
$check( '  and never the list of editor emails', false !== strpos( wp_json_encode( $data ), 'second@editor.test' ), false );

list( , $me ) = $call( $prof, 'GET', '/pura/v1/account/me' );
list( , $me2 ) = $call( $other, 'GET', '/pura/v1/account/me' );
$check( 'the account page knows who is an editor', array( $me['editor'], $me2['editor'] ), array( true, false ) );

// ---------- who may write ----------
$patch = static fn ( int $id, array $fields ) => array(
	'base_modified' => $token( $id ),
	'fields'        => $fields,
);

list( $status ) = $call( 0, 'PATCH', $sede_path, $patch( $sede->ID, array( 'address' => 'x' ) ) );
$check( 'signed out cannot write', $status, 401 );
list( $status, $data ) = $call( $other, 'PATCH', $sede_path, $patch( $sede->ID, array( 'address' => 'Hacked' ) ) );
$check( 'another member gets "not found" for a sede that is not theirs', array( $status, $data['code'] ?? '' ), array( 404, 'not_found' ) );
list( $status ) = $call( $prof, 'PATCH', '/pura/v1/editor/sede/' . $sede_b->ID, $patch( $sede_b->ID, array( 'address' => 'Hacked' ) ) );
$check( 'the professor gets "not found" for another sede', $status, 404 );
list( $status ) = $call( $prof, 'PATCH', '/pura/v1/editor/profesor/' . $sede->ID, $patch( $sede->ID, array( 'excerpt' => 'x' ) ) );
$check( 'a sede cannot be reached through the profesor route', $status, 404 );
list( $status ) = $call( $prof, 'PATCH', '/pura/v1/editor/sede/999999', array( 'base_modified' => 'x', 'fields' => array( 'address' => 'x' ) ) );
$check( 'a sede that does not exist is "not found" too', $status, 404 );
$check( '  nothing changed so far', array( get_post_meta( $sede->ID, 'pura_address', true ), get_post_meta( $sede_b->ID, 'pura_address', true ) === 'Hacked', $changes() - $count0 ), array( 'Calle Uno 1', false, 0 ) );

// ---------- what may be written ----------
list( $status, $data ) = $call( $prof, 'PATCH', $sede_path, $patch( $sede->ID, array( 'address' => 'Calle Dos 2', 'title' => 'Mi sede' ) ) );
$check( 'an admin-only field refuses the whole save', array( $status, $data['fields']['title'] ?? '', get_post_meta( $sede->ID, 'pura_address', true ), get_post( $sede->ID )->post_title === $sede->post_title ), array( 400, 'not_editable', 'Calle Uno 1', true ) );
list( $status, $data ) = $call( $prof, 'PATCH', $sede_path, $patch( $sede->ID, array( 'pura_city' => 'Otra', '_pura_editor_email' => 'me@x.io' ) ) );
$check( 'neither the city nor the editor list can be written', array( $status, array_keys( $data['fields'] ?? array() ) ), array( 400, array( 'pura_city', '_pura_editor_email' ) ) );
list( $status, $data ) = $call( $prof, 'PATCH', $sede_path, $patch( $sede->ID, array( 'whatsapp' => 'https://evil.example/wa' ) ) );
$check( 'a link to the wrong site is refused', array( $status, $data['fields']['whatsapp'] ?? '' ), array( 400, 'bad_link' ) );
list( $status ) = $call( $prof, 'PATCH', $sede_path, array( 'fields' => array( 'address' => 'x' ) ) );
$check( 'a save without the version token is refused', $status, 400 );

// ---------- a real save ----------
$stale = $token( $sede->ID );
$mails = array();
list( $status, $data ) = $call(
	$prof,
	'PATCH',
	$sede_path,
	$patch(
		$sede->ID,
		array(
			'address' => ' Calle <b>Dos</b> 2 ',
			'pricing' => array(
				array(
					'label' => 'Mes',
					'value' => '$600',
				),
				array(
					'label' => 'Clase suelta',
					'value' => '$80',
				),
			),
			'excerpt' => (string) $sede->post_excerpt,
		)
	)
);
$check( 'the professor saves an address and prices', array( $status, $data['changed'] ?? null ), array( 200, array( 'address', 'pricing' ) ) );
$check( '  stored as plain text', array( get_post_meta( $sede->ID, 'pura_address', true ), count( (array) get_post_meta( $sede->ID, 'pura_pricing', true ) ) ), array( 'Calle Dos 2', 2 ) );
$check( '  the version token moved', $data['item']['modified'] !== $stale, true );
$check( '  one change is recorded with before and after', array( $changes() - $count0 ), array( 1 ) );
$last = Change_Post_Type::to_array( (int) get_posts( array( 'post_type' => Change_Post_Type::POST_TYPE, 'post_status' => 'private', 'numberposts' => 1, 'fields' => 'ids' ) )[0] );
$check( '  by whom, on what', array( $last['actor_email'], $last['target_id'], $last['before']['address'], $last['after']['address'], $last['source'] ), array( 'prof@editor.test', $sede->ID, 'Calle Uno 1', 'Calle Dos 2', 'editor' ) );
$check( '  and one email goes to the administrator', array( count( $mails ), false !== strpos( (string) ( $mails[0]['message'] ?? '' ), 'Calle Dos 2' ), false !== strpos( (string) ( $mails[0]['subject'] ?? '' ), 'Dirección' ) ), array( 1, true, true ) );

list( $status, $data ) = $call( $prof, 'PATCH', $sede_path, array( 'base_modified' => $stale, 'fields' => array( 'address' => 'Calle Tres 3' ) ) );
$check( 'a save from an older copy is refused with the current values', array( $status, $data['code'] ?? '', $data['current']['fields']['address'] ?? '', get_post_meta( $sede->ID, 'pura_address', true ) ), array( 409, 'conflict', 'Calle Dos 2', 'Calle Dos 2' ) );

$mails = array();
list( $status, $data ) = $call( $prof, 'PATCH', $sede_path, $patch( $sede->ID, array( 'address' => 'Calle Dos 2' ) ) );
$check( 'saving the same value changes nothing, records nothing, mails nothing', array( $status, $data['changed'], $changes() - $count0, count( $mails ) ), array( 200, array(), 1, 0 ) );

list( $status, $data ) = $call( $prof, 'PATCH', $sede_path, $patch( $sede->ID, array( 'pricing' => array() ) ) );
$check( 'clearing the price list removes it', array( $status, empty( get_post_meta( $sede->ID, 'pura_pricing', true ) ), metadata_exists( 'post', $sede->ID, 'pura_pricing' ) ), array( 200, true, false ) );

// ---------- profile ----------
list( $status, $data ) = $call( $prof, 'PATCH', '/pura/v1/editor/profesor/' . $profesor->ID, $patch( $profesor->ID, array( 'subtitle' => "Nueva presentación.\n\nSegunda línea.", 'website' => 'https://mestre.example.com/' ) ) );
$check( 'the professor edits their own profile', array( $status, $data['changed'] ?? null ), array( 200, array( 'subtitle', 'website' ) ) );
$i18n = I18n_Meta::decode( (string) get_post_meta( $profesor->ID, I18n_Meta::META_KEY, true ) );
$check( '  the stale translation of the changed text is dropped, others are kept', $i18n, array( 'en' => array( 'bio-1' => 'Kept' ) ) );
$check( '  the bio is untouched, byte for byte', get_post( $profesor->ID )->post_content === $bio_before, true );
list( $status ) = $call( $prof, 'PATCH', '/pura/v1/editor/profesor/' . $profesor->ID, $patch( $profesor->ID, array( 'rank' => 'Mestre' ) ) );
$check( 'a professor cannot change their own rank', array( $status, get_post_meta( $profesor->ID, 'pura_rank', true ) === 'Mestre' ), array( 400, false ) );

// ---------- bio and timeline ----------
$with      = static function ( string $needle ) use ( $profes ): WP_Post {
	foreach ( $profes as $candidate ) {
		if ( false !== strpos( $candidate->post_content, $needle ) ) {
			return get_post( $candidate->ID );
		}
	}
	WP_CLI::error( "no profesor with $needle" );
};
$plain_bio = get_post( $profesor->ID );
$originals = array();
$bio_patch = static function ( WP_Post $target, array $sections ) use ( $call, $prof, $patch ) {
	return $call( $prof, 'PATCH', '/pura/v1/editor/profesor/' . $target->ID, $patch( $target->ID, array( 'bio' => array( 'sections' => $sections ) ) ) );
};
$keep      = static fn ( array $section ): array => array(
	'id'    => $section['id'],
	'items' => $section['items'],
);

$originals[ $plain_bio->ID ] = $plain_bio->post_content;
update_post_meta( $plain_bio->ID, I18n_Meta::META_KEY, wp_slash( wp_json_encode( array( 'en' => array( 'bio-1' => 'Kept', 'bio-2' => 'Stale' ) ) ) ) );
$view = Bio_Document::read( $plain_bio->post_content );
list( , $data ) = $call( $prof, 'GET', '/pura/v1/editor/me' );
$check( 'the editor offers the bio in sections of paragraphs', array( $data['profesor']['bio']['sections'][0]['kind'] ?? '', count( $data['profesor']['bio']['sections'] ?? array() ) === count( $view['sections'] ), $data['limits']['bio']['text'] ?? 0 ), array( 'text', true, Bio_Document::MAX_TEXT ) );

list( $status, $data ) = $bio_patch( $plain_bio, array_map( $keep, $view['sections'] ) );
$check( 'saving a bio with no edits changes nothing', array( $status, $data['changed'] ?? null, get_post( $plain_bio->ID )->post_content === $originals[ $plain_bio->ID ] ), array( 200, array(), true ) );

$first             = $view['sections'][0];
$items             = $first['items'];
$items[1]['text']  = 'Segundo párrafo reescrito <script>alert(1)</script> con <b>marcas</b> & símbolos.';
$items[]           = array( 'text' => 'Un párrafo nuevo al final.' );
$mails             = array();
list( $status, $data ) = $bio_patch( $plain_bio, array( array( 'id' => $first['id'], 'items' => $items ) ) );
$bio_change        = (int) get_posts( array( 'post_type' => Change_Post_Type::POST_TYPE, 'post_status' => 'private', 'numberposts' => 1, 'fields' => 'ids' ) )[0];
$saved             = get_post( $plain_bio->ID )->post_content;
preg_match( '#<!-- wp:paragraph \{"className":"i18n-bio-1"\} -->.*?<!-- /wp:paragraph -->#s', $originals[ $plain_bio->ID ], $kept );
$check( 'a professor rewrites one paragraph and adds another', array( $status, $data['changed'] ?? null ), array( 200, array( 'bio' ) ) );
$check( '  the untouched paragraph is stored byte for byte', false !== strpos( $saved, $kept[0] ), true );
$check( '  new text is stored as plain text, never as markup', array( false !== strpos( $saved, '<p>Segundo párrafo reescrito con marcas &amp; símbolos.</p>' ), false !== strpos( $saved, '<p>Un párrafo nuevo al final.</p>' ), false === strpos( $saved, '<script' ) ), array( true, true, true ) );
$check( '  the content is still clean block content', array( Bio_Document::is_stable( $saved ), count( parse_blocks( $saved ) ) === count( parse_blocks( $originals[ $plain_bio->ID ] ) ) ), array( true, true ) );
$check( '  only the rewritten paragraph loses its translation', I18n_Meta::decode( (string) get_post_meta( $plain_bio->ID, I18n_Meta::META_KEY, true ) ), array( 'en' => array( 'bio-1' => 'Kept' ) ) );
$check( '  the notice shows only the lines that changed', array( count( $mails ), false !== strpos( (string) ( $mails[0]['message'] ?? '' ), 'Un párrafo nuevo al final.' ), false === strpos( (string) ( $mails[0]['message'] ?? '' ), Bio_Document::read( $originals[ $plain_bio->ID ] )['sections'][0]['items'][0]['text'] ) ), array( 1, true, true ) );

list( $status, $data ) = $bio_patch( $plain_bio, array( array( 'id' => 999, 'items' => array() ) ) );
$check( 'a section that does not exist is refused', $status, 400 );
list( $status ) = $bio_patch( $plain_bio, array( array( 'id' => $first['id'], 'items' => array( array( 'text' => str_repeat( 'a', Bio_Document::MAX_TEXT + 1 ) ) ) ) ) );
$check( 'a paragraph that is too long is refused', $status, 400 );
list( $status ) = $call( $prof, 'PATCH', $sede_path, $patch( $sede->ID, array( 'bio' => array( 'sections' => array() ) ) ) );
$check( 'a sede has no bio to edit', $status, 400 );

$result = Change_Actions::restore( $bio_change, get_user_by( 'id', $admin ) );
$check( 'the administrator restores the bio exactly as it was', array( $result['restored'], get_post( $plain_bio->ID )->post_content === $originals[ $plain_bio->ID ] ), array( array( 'bio' ), true ) );

$listed = $with( '<!-- wp:list -->' );
$originals[ $listed->ID ] = $listed->post_content;
Editor_Access::set_emails( $listed->ID, array( 'prof@editor.test' ) );
$view   = Bio_Document::read( $listed->post_content );
$fixed  = null;
foreach ( $view['sections'] as $section ) {
	if ( in_array( 'fixed', array_column( $section['items'], 'kind' ), true ) ) {
		$fixed = $section;
	}
}
list( $status, $data ) = $bio_patch( $listed, array( array( 'id' => $fixed['id'], 'items' => array_values( array_filter( $fixed['items'], static fn ( array $item ): bool => 'fixed' !== $item['kind'] ) ) ) ) );
$check( 'lists and sub-headings cannot be removed from the editor', array( $status, $data['fields']['bio'] ?? '', get_post( $listed->ID )->post_content === $originals[ $listed->ID ] ), array( 400, 'fixed_missing', true ) );
$fixed_at                      = array_search( 'fixed', array_column( $fixed['items'], 'kind' ), true );
$tampered                      = array_values( $fixed['items'] );
$tampered[ $fixed_at ]['text'] = 'Intento de cambiar una lista';
list( $status, $data ) = $bio_patch( $listed, array( array( 'id' => $fixed['id'], 'items' => $tampered ) ) );
$check( '  nor rewritten: the stored list stays as it is', array( $status, $data['changed'] ?? null, get_post( $listed->ID )->post_content === $originals[ $listed->ID ] ), array( 200, array(), true ) );
Editor_Access::set_emails( $listed->ID, array() );

$timed = $with( '<!-- wp:pura/timeline -->' );
$originals[ $timed->ID ] = $timed->post_content;
Editor_Access::set_emails( $timed->ID, array( 'prof@editor.test' ) );
$view   = Bio_Document::read( $timed->post_content );
$line   = array_values( array_filter( $view['sections'], static fn ( array $section ): bool => 'timeline' === $section['kind'] ) )[0];
$events = $line['items'];
$total  = count( $events );
$events[1]['title'] = 'Título corregido';
array_pop( $events );
$events[] = array( 'year' => '2026', 'title' => 'Nueva etapa', 'text' => 'Texto de la nueva etapa.' );
list( $status, $data ) = $bio_patch( $timed, array( array( 'id' => $line['id'], 'items' => $events ) ) );
$saved  = get_post( $timed->ID )->post_content;
$after  = array_values( array_filter( Bio_Document::read( $saved )['sections'], static fn ( array $section ): bool => 'timeline' === $section['kind'] ) )[0]['items'];
preg_match( '#<!-- wp:pura/timeline-item \{"i18nKey":"tl-1"\} -->.*?<!-- /wp:pura/timeline-item -->#s', $originals[ $timed->ID ], $kept );
$check( 'a professor corrects, removes and adds timeline entries', array( $status, count( $after ), $after[1]['title'], $after[ $total - 1 ]['year'], $after[ $total - 1 ]['title'] ), array( 200, $total, 'Título corregido', '2026', 'Nueva etapa' ) );
$check( '  untouched entries are stored byte for byte; new ones in the markup the block saves', array( false !== strpos( $saved, $kept[0] ), false !== strpos( $saved, '<li class="wp-block-pura-timeline-item timeline-item"><span class="timeline-item__year">2026</span><div class="timeline-item__card"><h4>Nueva etapa</h4><p>Texto de la nueva etapa.</p></div></li>' ), Bio_Document::is_stable( $saved ) ), array( true, true, true ) );
$events = Bio_Document::read( $saved )['sections'];
$line   = array_values( array_filter( $events, static fn ( array $section ): bool => 'timeline' === $section['kind'] ) )[0];
$bad    = $line['items'];
$bad[]  = array( 'year' => '', 'title' => 'Sin año', 'text' => '' );
list( $status, $data ) = $bio_patch( $timed, array( array( 'id' => $line['id'], 'items' => $bad ) ) );
$check( 'a timeline entry needs a year and a title', array( $status, $data['fields']['bio'] ?? '' ), array( 400, 'event_incomplete' ) );
list( $status ) = $bio_patch( get_post( $timed->ID ), array( array( 'id' => $line['id'], 'items' => array( array( 'ref' => 0, 'year' => 'x', 'title' => 'y', 'text' => '' ), array( 'ref' => 0, 'year' => 'x', 'title' => 'y', 'text' => '' ) ) ) ) );
$check( 'the same entry cannot be sent twice', $status, 400 );
Editor_Access::set_emails( $timed->ID, array() );

foreach ( $originals as $id => $content ) {
	kses_remove_filters();
	wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $content ) ) );
	kses_init();
}
$check( 'every bio is back as it was', array_map( static fn ( int $id ): bool => get_post( $id )->post_content === $originals[ $id ], array_keys( $originals ) ), array_fill( 0, count( $originals ), true ) );
$last = Change_Post_Type::to_array( (int) $last['id'] );

// ---------- restore ----------
$mails  = array();
$result = Change_Actions::restore( $last['id'], get_user_by( 'id', $admin ) );
$check( 'the administrator restores the first change', array( $result['restored'], get_post_meta( $sede->ID, 'pura_address', true ), ( (array) get_post_meta( $sede->ID, 'pura_pricing', true ) )[0]['value'] ?? '' ), array( array( 'address', 'pricing' ), 'Calle Uno 1', '$500' ) );
$check( '  which is itself recorded and mailed', array( Change_Post_Type::to_array( (int) get_posts( array( 'post_type' => Change_Post_Type::POST_TYPE, 'post_status' => 'private', 'numberposts' => 1, 'fields' => 'ids' ) )[0] )['source'], count( $mails ) ), array( 'restore', 1 ) );

// ---------- photos ----------
// A real upload needs a real HTTP request (see tests/integration/editor-upload.sh); here the photos are made
// on disk and go through the same writer and the same gallery route.
require_once ABSPATH . 'wp-admin/includes/image.php';
$made  = array();
$photo = static function ( WP_Post $for, string $name ) use ( &$made ): int {
	$dir  = wp_upload_dir();
	$file = trailingslashit( $dir['path'] ) . wp_unique_filename( $dir['path'], $name . '.jpg' );
	imagejpeg( imagecreatetruecolor( 64, 48 ), $file );
	$id = (int) wp_insert_attachment(
		array(
			'post_mime_type' => 'image/jpeg',
			'post_title'     => $name,
			'post_status'    => 'inherit',
		),
		$file,
		$for->ID
	);
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
	Editor_Photos::adopt( $id, $for );
	$made[] = $id;
	return $id;
};
$as_prof = static fn ( array $values ): array => Editor_Writer::apply( get_post( $sede->ID ), $values, $prof, 'prof@editor.test' );
$put     = static fn ( int $user, array $ids, ?string $base = null ) => $call( $user, 'PUT', $sede_path . '/gallery', array( 'base_modified' => $base ?? \Pura\Core\Editor\Editor_Document::version( get_post( $sede->ID ) ), 'ids' => $ids ) );

delete_post_meta( $sede->ID, Editor_Photos::META_GALLERY );
delete_post_meta( $sede->ID, Editor_Photos::META_PENDING );
wp_clear_scheduled_hook( Editor_Photos::HOOK_DIGEST, array( $sede->ID ) );
$original = (int) get_post_thumbnail_id( $sede->ID );
$mails    = array();

$main        = $photo( $sede, 'pura-test-main' );
$main_change = $as_prof( array( 'image' => $main ) );
$check( 'a new main photo is shown and recorded', array( $main_change['changed'], (int) get_post_thumbnail_id( $sede->ID ), Change_Post_Type::to_array( $main_change['change_id'] )['before']['image'] ), array( array( 'image' ), $main, $original ) );
$check( '  it is no longer marked as unlinked; the old one is, but only as unlinked', array( metadata_exists( 'post', $main, Editor_Photos::META_UNLINKED ), $original ? metadata_exists( 'post', $original, Editor_Photos::META_UNLINKED ) : true, $original ? metadata_exists( 'post', $original, Editor_Photos::META_OWNER ) : false ), array( false, true, false ) );
$check( '  no email yet: photo changes wait for one joint notice', array( count( $mails ), (bool) wp_next_scheduled( Editor_Photos::HOOK_DIGEST, array( $sede->ID ) ) ), array( 0, true ) );

$g1 = $photo( $sede, 'pura-test-g1' );
$g2 = $photo( $sede, 'pura-test-g2' );
$g3 = $photo( $sede, 'pura-test-g3' );
$as_prof( array( 'gallery' => array( $g1 ) ) );
$as_prof( array( 'gallery' => array( $g1, $g2 ) ) );
$filled = $as_prof( array( 'gallery' => array( $g1, $g2, $g3 ) ) );
$check( 'photos are added to the gallery in order', Editor_Photos::gallery_ids( $sede->ID ), array( $g1, $g2, $g3 ) );

list( $status, $data ) = $put( $prof, array( $g3, $g1 ) );
$order_change          = (int) get_posts( array( 'post_type' => Change_Post_Type::POST_TYPE, 'post_status' => 'private', 'numberposts' => 1, 'fields' => 'ids' ) )[0];
$check( 'the professor reorders the gallery and removes a photo', array( $status, array_column( $data['item']['gallery'] ?? array(), 'id' ), Editor_Photos::gallery_ids( $sede->ID ) ), array( 200, array( $g3, $g1 ), array( $g3, $g1 ) ) );
$check( '  the removed photo is kept, marked as unlinked', array( 'attachment' === get_post_type( $g2 ), metadata_exists( 'post', $g2, Editor_Photos::META_UNLINKED ) ), array( true, true ) );

$foreign       = $photo( $sede_b, 'pura-test-foreign' );
list( $status ) = $put( $prof, array( $g3, $foreign ) );
$check( 'a gallery order cannot bring in a photo from elsewhere', array( $status, Editor_Photos::gallery_ids( $sede->ID ) ), array( 400, array( $g3, $g1 ) ) );
list( $status ) = $put( $prof, array( (string) $g1, (string) $g3 ) );
$check( 'nor take ids that are not whole numbers', $status, 400 );
list( $status, $data ) = $put( $prof, array( $g1, $g3 ), 'stale#0' );
$check( 'an order sent from an older copy is refused', array( $status, $data['code'] ?? '' ), array( 409, 'conflict' ) );
list( $status ) = $put( $other, array( $g1 ) );
$check( 'another member cannot touch the gallery', array( $status, Editor_Photos::gallery_ids( $sede->ID ) ), array( 404, array( $g3, $g1 ) ) );
list( $status, $data ) = $call( $prof, 'PATCH', $sede_path, $patch( $sede->ID, array( 'image' => $foreign ) ) );
$check( 'a text save cannot set the main photo', array( $status, (int) get_post_thumbnail_id( $sede->ID ) ), array( 400, $main ) );
list( $status ) = $call( $prof, 'POST', $sede_path . '/photo' );
$check( 'the photo route refuses a request with no file', $status, 400 );

list( , $data ) = $call( 0, 'GET', '/wp/v2/pura_sede/' . $sede->ID );
$check( 'the public sede data carries the gallery, read-only', array( array_column( $data['gallery'] ?? array(), 'id' ), '' !== ( $data['gallery'][0]['url'] ?? '' ), isset( $data['meta'][ Editor_Photos::META_GALLERY ] ) ), array( array( $g3, $g1 ), true, false ) );
list( , $data ) = $call( $prof, 'GET', '/pura/v1/editor/me' );
$check( 'the editor lists the gallery, the main photo and the limits', array( array_column( $data['sedes'][0]['gallery'] ?? array(), 'id' ), $data['sedes'][0]['image']['id'] ?? 0, $data['limits']['gallery'] ?? 0, $data['profesor']['gallery'] ?? 'none' ), array( array( $g3, $g1 ), $main, 12, 'none' ) );

$sent = Editor_Photos::send_digest( $sede->ID );
$check( 'the joint notice is one email for all of it', array( $sent, count( $mails ), 0 === strpos( (string) ( $mails[0]['subject'] ?? '' ), 'Fotos:' ), false !== strpos( (string) ( $mails[0]['message'] ?? '' ), 'Galería ahora (2)' ), get_post_meta( $sede->ID, Editor_Photos::META_PENDING, false ) ), array( true, 1, true, true, array() ) );

$old = time() - 31 * DAY_IN_SECONDS;
update_post_meta( $g2, Editor_Photos::META_UNLINKED, $old );
update_post_meta( $g1, Editor_Photos::META_UNLINKED, $old );
if ( $original ) {
	update_post_meta( $original, Editor_Photos::META_UNLINKED, $old );
}
$purged = Editor_Photos::purge();
$check( 'after 30 days only the unlinked photo uploaded here is deleted', array( $purged >= 1, get_post( $g2 ), 'attachment' === get_post_type( $g1 ), metadata_exists( 'post', $g1, Editor_Photos::META_UNLINKED ), $original ? 'attachment' === get_post_type( $original ) : true ), array( true, null, true, false, true ) );

$mails  = array();
$result = Change_Actions::restore( $main_change['change_id'], get_user_by( 'id', $admin ) );
$check( 'the administrator restores the previous main photo', array( $result['restored'], (int) get_post_thumbnail_id( $sede->ID ), $original ? metadata_exists( 'post', $original, Editor_Photos::META_UNLINKED ) : false, count( $mails ) ), array( array( 'image' ), $original, false, 1 ) );
$result = Change_Actions::restore( $order_change, get_user_by( 'id', $admin ) );
$check( 'restoring an older gallery leaves out the photo that no longer exists', array( $result['restored'], Editor_Photos::gallery_ids( $sede->ID ) ), array( array( 'gallery' ), array( $g1, $g3 ) ) );

delete_post_meta( $sede->ID, Editor_Photos::META_GALLERY );
delete_post_meta( $sede->ID, Editor_Photos::META_PENDING );
wp_clear_scheduled_hook( Editor_Photos::HOOK_DIGEST, array( $sede->ID ) );
foreach ( $made as $id ) {
	wp_delete_attachment( $id, true );
}

// ---------- states ----------
wp_update_post( array( 'ID' => $sede->ID, 'post_status' => 'draft' ) );
list( $status ) = $call( $prof, 'PATCH', $sede_path, $patch( $sede->ID, array( 'address' => 'x' ) ) );
list( , $data ) = $call( $prof, 'GET', '/pura/v1/editor/me' );
$check( 'an unpublished sede cannot be edited or listed', array( $status, $data['sedes'] ), array( 404, array() ) );
wp_update_post( array( 'ID' => $sede->ID, 'post_status' => 'publish' ) );

list( $status, $data ) = $call( $admin, 'PATCH', '/pura/v1/editor/sede/' . $sede_b->ID, $patch( $sede_b->ID, array( 'responsible' => 'Prueba admin' ) ) );
$check( 'an administrator may use the editor on any sede', array( $status, $data['changed'] ?? null ), array( 200, array( 'responsible' ) ) );

Editor_Access::set_emails( $sede->ID, array() );
list( $status ) = $call( $prof, 'PATCH', $sede_path, $patch( $sede->ID, array( 'address' => 'x' ) ) );
$check( 'removing the email removes the access at once', $status, 404 );

// ---------- WordPress's own doors stay shut ----------
list( $status ) = $call( $prof, 'POST', '/wp/v2/pura_sede/' . $sede->ID, array( 'meta' => array( 'pura_address' => 'Via core' ) ) );
$check( 'the professor cannot write through the standard REST API', in_array( $status, array( 401, 403 ), true ), true );
list( , $data ) = $call( 0, 'GET', '/wp/v2/pura_sede/' . $sede_b->ID );
$check( 'the editor list and the save counter are not in the public REST data', array( isset( $data['meta'][ Editor_Access::META_EMAIL ] ), isset( $data['meta']['_pura_editor_rev'] ) ), array( false, false ) );
$check( 'a professor still has no admin capabilities', array( user_can( $prof, 'edit_posts' ), user_can( $prof, 'upload_files' ), get_userdata( $prof )->roles ), array( false, false, array( Member_Accounts::ROLE ) ) );

WP_CLI::line( '' );
if ( $failures ) {
	WP_CLI::error( "failures: $failures" );
}
WP_CLI::success( 'all checks passed' );
