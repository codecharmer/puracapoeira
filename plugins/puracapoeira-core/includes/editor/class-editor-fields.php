<?php
/**
 * What a professor may change on their own sede and profile, and what a valid value is.
 *
 * This list is the security boundary of the editor: a field that is not here cannot be
 * written through it, whatever the request says. Titles, addresses of pages (slugs), city,
 * country, map position, rank, full name and the sede a profesor belongs to are the
 * administrator's and are deliberately absent.
 *
 * Pure: no WordPress calls, so the rules are unit-tested. The writer still passes every value
 * through the sanitizers registered for the underlying meta.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Editor;

defined( 'ABSPATH' ) || exit;

final class Editor_Fields {

	public const TYPE_SEDE     = 'sede';
	public const TYPE_PROFESOR = 'profesor';

	/** Where a value is stored when it is not post meta. */
	public const STORE_EXCERPT = '@excerpt';

	/** The post's content: a profesor's bio. */
	public const STORE_CONTENT = '@content';

	/** The post's main photo (featured image). */
	public const STORE_THUMBNAIL = '@thumbnail';

	public const MAX_ROWS = 12;
	public const MAX_CELL = 120;

	private const WHATSAPP_HOSTS  = array( 'wa.me', 'api.whatsapp.com', 'chat.whatsapp.com' );
	private const INSTAGRAM_HOSTS = array( 'instagram.com' );
	private const FACEBOOK_HOSTS  = array( 'facebook.com', 'fb.com', 'fb.me' );
	private const YOUTUBE_HOSTS   = array( 'youtube.com', 'youtu.be' );

	/**
	 * Field definitions per type: `store` (meta key or STORE_EXCERPT), `kind`, `max`, and for
	 * links `hosts` (empty = any https site), for rows `columns`.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function definitions( string $type ): array {
		$whatsapp = array(
			'whatsapp'         => array(
				'store' => 'pura_whatsapp',
				'kind'  => 'url',
				'hosts' => self::WHATSAPP_HOSTS,
			),
			'whatsapp_display' => array(
				'store' => 'pura_whatsapp_display',
				'kind'  => 'text',
				'max'   => 40,
			),
			'instagram'        => array(
				'store' => 'pura_instagram',
				'kind'  => 'url',
				'hosts' => self::INSTAGRAM_HOSTS,
			),
			'instagram_handle' => array(
				'store' => 'pura_instagram_handle',
				'kind'  => 'text',
				'max'   => 60,
			),
		);

		if ( self::TYPE_SEDE === $type ) {
			return array_merge(
				array(
					'excerpt'     => array(
						'store' => self::STORE_EXCERPT,
						'kind'  => 'text',
						'max'   => 600,
					),
					'address'     => array(
						'store' => 'pura_address',
						'kind'  => 'text',
						'max'   => 200,
					),
					'responsible' => array(
						'store' => 'pura_responsible',
						'kind'  => 'text',
						'max'   => 200,
					),
				),
				$whatsapp,
				array(
					// A sede's Facebook is a page address or just the page's name.
					'facebook' => array(
						'store' => 'pura_facebook',
						'kind'  => 'url_or_name',
						'max'   => 200,
						'hosts' => self::FACEBOOK_HOSTS,
					),
					'schedule' => array(
						'store'   => 'pura_schedule',
						'kind'    => 'rows',
						'columns' => array( 'group', 'days', 'time' ),
					),
					'pricing'  => array(
						'store'   => 'pura_pricing',
						'kind'    => 'rows',
						'columns' => array( 'label', 'value' ),
					),
				)
			);
		}

		if ( self::TYPE_PROFESOR === $type ) {
			return array_merge(
				array(
					'excerpt'  => array(
						'store' => self::STORE_EXCERPT,
						'kind'  => 'text',
						'max'   => 600,
					),
					'subtitle' => array(
						'store' => 'pura_subtitle',
						'kind'  => 'multiline',
						'max'   => 600,
					),
					'caption'  => array(
						'store' => 'pura_caption',
						'kind'  => 'text',
						'max'   => 200,
					),
				),
				$whatsapp,
				array(
					'facebook' => array(
						'store' => 'pura_facebook',
						'kind'  => 'url',
						'hosts' => self::FACEBOOK_HOSTS,
					),
					'website'  => array(
						'store' => 'pura_website',
						'kind'  => 'url',
						'hosts' => array(),
					),
					'youtube'  => array(
						'store' => 'pura_youtube',
						'kind'  => 'url',
						'hosts' => self::YOUTUBE_HOSTS,
					),
				)
			);
		}

		return array();
	}

	/**
	 * Photo fields. They are not in definitions() on purpose: a text save can never point a
	 * post at an arbitrary photo. They change only through the upload and gallery routes, and
	 * through a restore.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function photo_definitions( string $type ): array {
		$fields = array();
		if ( in_array( $type, array( self::TYPE_SEDE, self::TYPE_PROFESOR ), true ) ) {
			$fields['image'] = array(
				'store' => self::STORE_THUMBNAIL,
				'kind'  => 'photo',
			);
		}
		if ( self::TYPE_SEDE === $type ) {
			$fields['gallery'] = array(
				'store' => Editor_Photos::META_GALLERY,
				'kind'  => 'photos',
			);
		}

		return $fields;
	}

	/**
	 * The bio. Like the photos it is not in definitions(): what the page sends is a list of
	 * edits, which Bio_Document turns into the new content before the writer sees it.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function content_definitions( string $type ): array {
		return self::TYPE_PROFESOR === $type ? array(
			'bio' => array(
				'store' => self::STORE_CONTENT,
				'kind'  => 'bio',
			),
		) : array();
	}

	/**
	 * Checks a set of submitted values. One bad field refuses the whole request: nothing is
	 * half-saved. Returns the cleaned values, or the fields at fault with a reason code.
	 *
	 * @param array<string, mixed> $input Field name => submitted value.
	 * @return array{ok: bool, values: array<string, mixed>, errors: array<string, string>}
	 */
	public static function validate( string $type, array $input ): array {
		$definitions = self::definitions( $type );
		$values      = array();
		$errors      = array();

		foreach ( $input as $name => $raw ) {
			$name = (string) $name;
			if ( ! isset( $definitions[ $name ] ) ) {
				$errors[ $name ] = 'not_editable';
				continue;
			}
			$definition = $definitions[ $name ];

			if ( 'rows' === $definition['kind'] ) {
				$result = self::clean_rows( $raw, $definition['columns'] );
			} else {
				$result = self::clean_scalar( $raw, $definition );
			}

			if ( is_string( $result['error'] ) ) {
				$errors[ $name ] = $result['error'];
			} else {
				$values[ $name ] = $result['value'];
			}
		}

		return array(
			'ok'     => ! $errors,
			'values' => $errors ? array() : $values,
			'errors' => $errors,
		);
	}

	/**
	 * A message a professor can act on, in the site's language.
	 *
	 * @param array<string, string> $errors Field => reason code.
	 */
	public static function message( array $errors ): string {
		$reasons = array_values( $errors );
		if ( in_array( 'not_editable', $reasons, true ) ) {
			return 'Ese dato no se puede cambiar desde aquí.';
		}
		if ( in_array( 'bad_link', $reasons, true ) ) {
			return 'Revisa los enlaces: deben empezar con https:// y apuntar al sitio correcto (WhatsApp, Instagram, Facebook o YouTube).';
		}
		if ( in_array( 'event_incomplete', $reasons, true ) ) {
			return 'Cada entrada de la línea de tiempo necesita un año y un título.';
		}
		if ( in_array( 'fixed_missing', $reasons, true ) ) {
			return 'Hay partes de la trayectoria que solo cambia la administración y no se pueden quitar desde aquí.';
		}
		if ( in_array( 'too_many_rows', $reasons, true ) ) {
			return 'Hay demasiadas filas: el máximo es ' . self::MAX_ROWS . '.';
		}
		if ( in_array( 'too_long', $reasons, true ) ) {
			return 'Algún texto es demasiado largo.';
		}

		return 'Revisa los datos e inténtalo de nuevo.';
	}

	/**
	 * @param mixed                $raw        Submitted value.
	 * @param array<string, mixed> $definition Field definition.
	 * @return array{value: string, error: string|null}
	 */
	private static function clean_scalar( $raw, array $definition ): array {
		if ( ! is_string( $raw ) ) {
			return array(
				'value' => '',
				'error' => 'invalid',
			);
		}

		$multiline = 'multiline' === $definition['kind'];
		$value     = self::plain( $raw, $multiline );
		$kind      = (string) $definition['kind'];

		if ( 'url' === $kind || ( 'url_or_name' === $kind && 1 === preg_match( '#^[a-z][a-z0-9+.-]*:#i', $value ) ) ) {
			if ( '' !== $value && ! self::is_allowed_link( $value, $definition['hosts'] ) ) {
				return array(
					'value' => '',
					'error' => 'bad_link',
				);
			}
			$max = 300;
		} else {
			$max = (int) ( $definition['max'] ?? 200 );
		}

		if ( self::length( $value ) > $max ) {
			return array(
				'value' => '',
				'error' => 'too_long',
			);
		}

		return array(
			'value' => $value,
			'error' => null,
		);
	}

	/**
	 * @param mixed    $raw     Submitted value.
	 * @param string[] $columns Column names.
	 * @return array{value: array<int, array<string, string>>, error: string|null}
	 */
	private static function clean_rows( $raw, array $columns ): array {
		$fail = static fn ( string $code ): array => array(
			'value' => array(),
			'error' => $code,
		);

		if ( ! is_array( $raw ) || ( $raw && array_keys( $raw ) !== range( 0, count( $raw ) - 1 ) ) ) {
			return $fail( 'invalid' );
		}

		$rows = array();
		foreach ( $raw as $item ) {
			if ( ! is_array( $item ) || array_diff( array_keys( $item ), $columns ) ) {
				return $fail( 'invalid' );
			}
			$row = array();
			foreach ( $columns as $column ) {
				$cell = $item[ $column ] ?? '';
				if ( ! is_string( $cell ) ) {
					return $fail( 'invalid' );
				}
				$cell = self::plain( $cell, false );
				if ( self::length( $cell ) > self::MAX_CELL ) {
					return $fail( 'too_long' );
				}
				$row[ $column ] = $cell;
			}
			if ( '' !== implode( '', $row ) ) {
				$rows[] = $row;
			}
		}

		if ( count( $rows ) > self::MAX_ROWS ) {
			return $fail( 'too_many_rows' );
		}

		return array(
			'value' => $rows,
			'error' => null,
		);
	}

	/**
	 * An https link whose host is one of the expected sites (or a subdomain of one). An empty
	 * list accepts any https site.
	 *
	 * @param string[] $hosts Expected hosts.
	 */
	public static function is_allowed_link( string $url, array $hosts ): bool {
		$parts = parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- pure class, no WordPress.
		if ( ! is_array( $parts ) || 'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) ) || empty( $parts['host'] ) ) {
			return false;
		}
		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return false;
		}
		$host = strtolower( (string) $parts['host'] );
		if ( ! $hosts ) {
			return 1 === preg_match( '/^[a-z0-9.-]+\.[a-z]{2,}$/', $host );
		}
		foreach ( $hosts as $allowed ) {
			if ( $host === $allowed || str_ends_with( $host, '.' . $allowed ) ) {
				return true;
			}
		}

		return false;
	}

	/** Plain text: no markup, no control characters, whitespace collapsed (line breaks kept when asked). */
	private static function plain( string $value, bool $multiline ): string {
		$value = strip_tags( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- pure class, no WordPress.
		$value = str_replace( array( "\r\n", "\r" ), "\n", $value );
		$value = (string) preg_replace( '/[\x00-\x09\x0B-\x1F\x7F]/u', ' ', $value );

		if ( $multiline ) {
			$lines = array_map( static fn ( string $line ): string => trim( (string) preg_replace( '/[ \t]+/u', ' ', $line ) ), explode( "\n", $value ) );
			$value = (string) preg_replace( "/\n{3,}/", "\n\n", implode( "\n", $lines ) );
		} else {
			$value = (string) preg_replace( '/\s+/u', ' ', $value );
		}

		return trim( $value );
	}

	private static function length( string $value ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
	}
}
