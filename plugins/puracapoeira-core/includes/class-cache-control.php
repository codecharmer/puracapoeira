<?php
/**
 * Short cache life for the content professors edit.
 *
 * The site sits behind cPanel's NGINX page cache, which is only cleared on deploy. A sede or a
 * profesor that is edited (in the admin, from the app or on the editor page) must show up
 * without a deploy, on the website and in the mobile app, which reads the same posts through
 * the REST API. PHP cannot purge that cache (it needs root), so these responses ask NGINX to
 * keep them for one minute only (`X-Accel-Expires`); every other page keeps the default.
 *
 * `X-Pura-Generated` is the time PHP built the response: two requests that show the same value
 * were served from the cache, which makes the real cache life measurable from outside.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core;

use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Cache_Control {

	/** Seconds NGINX may keep a response that shows sede or profesor content. */
	public const TTL = 60;

	/** @var string[] Post types whose single pages and REST collections are kept short. */
	public const POST_TYPES = array( 'pura_sede', 'pura_profesor' );

	/** @var string[] Slugs of the pages that list sedes or profesores. */
	public const LISTING_PAGES = array( 'sedes', 'profesores', 'contacto' );

	public static function register(): void {
		add_action( 'template_redirect', array( self::class, 'front_headers' ) );
		add_filter( 'rest_post_dispatch', array( self::class, 'rest_headers' ), 10, 3 );
	}

	/**
	 * True for a REST read of sedes or profesores: `/wp/v2/pura_sede`, `/wp/v2/pura_sede/8`.
	 *
	 * @param string $route  REST route, e.g. `/wp/v2/pura_sede/8`.
	 * @param string $method HTTP method.
	 */
	public static function is_short_lived_route( string $route, string $method ): bool {
		if ( ! in_array( strtoupper( $method ), array( 'GET', 'HEAD' ), true ) ) {
			return false;
		}

		foreach ( self::POST_TYPES as $type ) {
			if ( 1 === preg_match( '#^/wp/v2/' . preg_quote( $type, '#' ) . '(/\d+)?/?$#', $route ) ) {
				return true;
			}
		}

		return false;
	}

	/** Single sede and profesor pages, the home page and the pages that list them. */
	public static function front_headers(): void {
		if ( headers_sent() ) {
			return;
		}
		if ( ! is_singular( self::POST_TYPES ) && ! is_front_page() && ! is_page( self::LISTING_PAGES ) ) {
			return;
		}

		header( 'X-Accel-Expires: ' . self::TTL );
		header( 'X-Pura-Generated: ' . time() );
	}

	/**
	 * @param WP_HTTP_Response $response Response about to be sent.
	 * @param WP_REST_Server   $server   REST server.
	 * @param WP_REST_Request  $request  Request that produced it.
	 */
	public static function rest_headers( $response, $server, $request ) {
		if ( $response instanceof WP_HTTP_Response && $request instanceof WP_REST_Request
			&& self::is_short_lived_route( (string) $request->get_route(), (string) $request->get_method() ) ) {
			$response->header( 'X-Accel-Expires', (string) self::TTL );
			$response->header( 'X-Pura-Generated', (string) time() );
		}

		return $response;
	}
}
