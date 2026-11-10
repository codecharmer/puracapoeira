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

// ---------- restore ----------
$mails  = array();
$result = Change_Actions::restore( $last['id'], get_user_by( 'id', $admin ) );
$check( 'the administrator restores the first change', array( $result['restored'], get_post_meta( $sede->ID, 'pura_address', true ), ( (array) get_post_meta( $sede->ID, 'pura_pricing', true ) )[0]['value'] ?? '' ), array( array( 'address', 'pricing' ), 'Calle Uno 1', '$500' ) );
$check( '  which is itself recorded and mailed', array( Change_Post_Type::to_array( (int) get_posts( array( 'post_type' => Change_Post_Type::POST_TYPE, 'post_status' => 'private', 'numberposts' => 1, 'fields' => 'ids' ) )[0] )['source'], count( $mails ) ), array( 'restore', 1 ) );

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
