<?php
/**
 * A profesor's bio as the editor page sees it.
 *
 * The bio is block content written in the admin: sections (a heading and paragraphs, lists,
 * sub-headings) and, for some, a timeline. A professor edits the paragraphs and the timeline
 * entries. Everything else is kept exactly as stored: a block that was not touched is written
 * back byte for byte, with its formatting and its translations. A paragraph or an entry that
 * was changed or added is saved as plain text.
 *
 * A bio is only offered for editing when reading it and writing it back unchanged gives the
 * same bytes (see `wp pura editor-roundtrip`).
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Editor;

defined( 'ABSPATH' ) || exit;

final class Bio_Document {

	public const MAX_ITEMS      = 30;
	public const MAX_TEXT       = 1500;
	public const MAX_EVENTS     = 40;
	public const MAX_YEAR       = 20;
	public const MAX_TITLE      = 120;
	public const MAX_EVENT_TEXT = 600;

	private const TIMELINE = 'pura/timeline';
	private const EVENT    = 'pura/timeline-item';

	/**
	 * The editable view of a bio, or null when it has nothing a professor can edit or cannot
	 * be written back safely.
	 *
	 * @return array{sections: array<int, array<string, mixed>>}|null
	 */
	public static function read( string $content ): ?array {
		if ( '' === trim( $content ) || ! self::is_stable( $content ) ) {
			return null;
		}
		$sections = array();
		foreach ( parse_blocks( $content ) as $id => $block ) {
			$section = self::section( $block );
			if ( $section ) {
				$sections[] = array( 'id' => $id ) + $section['view'];
			}
		}

		return $sections ? array( 'sections' => $sections ) : null;
	}

	/** Whether parsing and serialising the content gives back the same bytes. */
	public static function is_stable( string $content ): bool {
		return serialize_blocks( parse_blocks( $content ) ) === $content;
	}

	/**
	 * Applies what the editor page sent to the stored bio.
	 *
	 * @param mixed $payload `{sections: [{id, items: [{ref?, text} | {ref?, year, title, text}]}]}`.
	 * @return array{ok: bool, content: string, error: string}
	 */
	public static function apply( string $content, $payload ): array {
		$fail = static fn ( string $reason ): array => array(
			'ok'      => false,
			'content' => '',
			'error'   => $reason,
		);
		if ( ! self::is_stable( $content ) || ! is_array( $payload ) || ! isset( $payload['sections'] ) || ! is_array( $payload['sections'] ) ) {
			return $fail( 'bad_request' );
		}

		$blocks = parse_blocks( $content );
		$seen   = array();
		foreach ( $payload['sections'] as $sent ) {
			$id = is_array( $sent ) && isset( $sent['id'] ) && is_int( $sent['id'] ) ? $sent['id'] : -1;
			if ( ! isset( $blocks[ $id ] ) || isset( $seen[ $id ] ) || ! isset( $sent['items'] ) || ! is_array( $sent['items'] ) ) {
				return $fail( 'bad_request' );
			}
			$seen[ $id ] = true;
			$section     = self::section( $blocks[ $id ] );
			if ( ! $section ) {
				return $fail( 'bad_request' );
			}

			$built = 'timeline' === $section['view']['kind']
				? self::build_events( $blocks[ $id ], $section, $sent['items'] )
				: self::build_text( $blocks[ $id ], $section, $sent['items'] );
			if ( is_string( $built ) ) {
				return $fail( $built );
			}
			$blocks[ $id ] = $built;
		}

		return array(
			'ok'      => true,
			'content' => serialize_blocks( $blocks ),
			'error'   => '',
		);
	}

	/**
	 * The bio as plain text, for a notice or the change log.
	 */
	public static function to_text( string $content ): string {
		$lines = array();
		foreach ( parse_blocks( $content ) as $block ) {
			$section = self::section( $block );
			if ( ! $section ) {
				$text = self::plain( (string) render_block( $block ) );
				if ( '' !== $text ) {
					$lines[] = $text;
					$lines[] = '';
				}
				continue;
			}
			$lines[] = '== ' . $section['view']['title'] . ' ==';
			foreach ( $section['view']['items'] as $item ) {
				$lines[] = 'event' === $item['kind'] ? trim( $item['year'] . ' — ' . $item['title'] . ': ' . $item['text'] ) : $item['text'];
			}
			$lines[] = '';
		}

		return trim( implode( "\n", $lines ) );
	}

	/**
	 * Only the lines that differ between two bios, for a notice: what was there and is gone,
	 * and what is new.
	 *
	 * @return array{before: string, after: string}
	 */
	public static function changed_text( string $before, string $after ): array {
		$old = explode( "\n", self::to_text( $before ) );
		$new = explode( "\n", self::to_text( $after ) );

		return array(
			'before' => implode( "\n", array_diff( $old, $new ) ),
			'after'  => implode( "\n", array_diff( $new, $old ) ),
		);
	}

	/**
	 * Translation keys used by a bio. A key that a save removes has a stale translation.
	 *
	 * @return string[]
	 */
	public static function translation_keys( string $content ): array {
		$keys = array();
		$walk = static function ( array $blocks ) use ( &$walk, &$keys ): void {
			foreach ( $blocks as $block ) {
				foreach ( preg_split( '/\s+/', (string) ( $block['attrs']['className'] ?? '' ) ) as $class ) {
					if ( 0 === strpos( (string) $class, 'i18n-' ) ) {
						$keys[] = substr( (string) $class, 5 );
					}
				}
				$key = (string) ( $block['attrs']['i18nKey'] ?? '' );
				if ( '' !== $key ) {
					$keys[] = $key . '-title';
					$keys[] = $key . '-text';
				}
				$walk( $block['innerBlocks'] ?? array() );
			}
		};
		$walk( parse_blocks( $content ) );

		return array_values( array_unique( $keys ) );
	}

	// ---------- reading ----------

	/**
	 * A top-level block as an editable section: a group that starts with a heading.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @return array{view: array<string, mixed>, title_at: int, timeline_at: int}|null
	 */
	private static function section( array $block ): ?array {
		$inner = $block['innerBlocks'] ?? array();
		if ( 'core/group' !== ( $block['blockName'] ?? '' ) || ! $inner || 'core/heading' !== ( $inner[0]['blockName'] ?? '' ) ) {
			return null;
		}
		$title = self::plain( (string) $inner[0]['innerHTML'] );

		foreach ( $inner as $at => $child ) {
			if ( self::TIMELINE === ( $child['blockName'] ?? '' ) ) {
				$items = array();
				foreach ( $child['innerBlocks'] ?? array() as $ref => $event ) {
					if ( self::EVENT === ( $event['blockName'] ?? '' ) ) {
						$items[] = array(
							'ref'  => $ref,
							'kind' => 'event',
						) + self::event_values( $event );
					}
				}

				return array(
					'view'        => array(
						'title' => $title,
						'kind'  => 'timeline',
						'items' => $items,
					),
					'title_at'    => 0,
					'timeline_at' => $at,
				);
			}
		}

		$items = array();
		foreach ( $inner as $ref => $child ) {
			if ( 0 === $ref ) {
				continue;
			}
			$paragraph = 'core/paragraph' === ( $child['blockName'] ?? '' );
			$items[]   = array(
				'ref'  => $ref,
				'kind' => $paragraph ? 'text' : 'fixed',
				'text' => self::plain( $paragraph ? (string) $child['innerHTML'] : (string) render_block( $child ) ),
			);
		}

		return array(
			'view'        => array(
				'title' => $title,
				'kind'  => 'text',
				'items' => $items,
			),
			'title_at'    => 0,
			'timeline_at' => -1,
		);
	}

	/**
	 * @param array<string, mixed> $event Parsed timeline-item block.
	 * @return array{year: string, title: string, text: string}
	 */
	private static function event_values( array $event ): array {
		$html = (string) $event['innerHTML'];
		$part = static function ( string $pattern ) use ( $html ): string {
			return 1 === preg_match( $pattern, $html, $m ) ? self::plain( $m[1] ) : '';
		};

		return array(
			'year'  => $part( '#<span class="timeline-item__year">(.*?)</span>#s' ),
			'title' => $part( '#<h4[^>]*>(.*?)</h4>#s' ),
			'text'  => $part( '#<p[^>]*>(.*?)</p>#s' ),
		);
	}

	// ---------- writing ----------

	/**
	 * @param array<string, mixed> $group   Parsed section block.
	 * @param array<string, mixed> $section Result of section().
	 * @param array<int, mixed>    $sent    Submitted items.
	 * @return array<string, mixed>|string New block, or a reason code.
	 */
	private static function build_text( array $group, array $section, array $sent ) {
		if ( count( $sent ) > self::MAX_ITEMS ) {
			return 'too_many_rows';
		}
		$known = array_column( $section['view']['items'], null, 'ref' );
		$used  = array();
		$inner = array( $group['innerBlocks'][0] );

		foreach ( $sent as $item ) {
			if ( ! is_array( $item ) ) {
				return 'bad_request';
			}
			$ref  = $item['ref'] ?? null;
			$text = self::clean( $item['text'] ?? '' );
			if ( null === $text || self::length( $text ) > self::MAX_TEXT ) {
				return null === $text ? 'bad_request' : 'too_long';
			}

			if ( null !== $ref ) {
				if ( ! is_int( $ref ) || ! isset( $known[ $ref ] ) || isset( $used[ $ref ] ) ) {
					return 'bad_request';
				}
				$used[ $ref ] = true;
				if ( 'fixed' === $known[ $ref ]['kind'] || $text === $known[ $ref ]['text'] ) {
					$inner[] = $group['innerBlocks'][ $ref ];
					continue;
				}
			}
			if ( '' !== $text ) {
				$inner[] = self::paragraph( $text );
			}
		}

		foreach ( $known as $ref => $item ) {
			if ( 'fixed' === $item['kind'] && ! isset( $used[ $ref ] ) ) {
				return 'fixed_missing';
			}
		}

		// Nothing moved, changed or left: the stored block is returned as it is.
		return $inner === $group['innerBlocks'] ? $group : self::with_inner( $group, $inner );
	}

	/**
	 * @param array<string, mixed> $group   Parsed section block.
	 * @param array<string, mixed> $section Result of section().
	 * @param array<int, mixed>    $sent    Submitted entries.
	 * @return array<string, mixed>|string New block, or a reason code.
	 */
	private static function build_events( array $group, array $section, array $sent ) {
		if ( count( $sent ) > self::MAX_EVENTS ) {
			return 'too_many_rows';
		}
		$at       = (int) $section['timeline_at'];
		$timeline = $group['innerBlocks'][ $at ];
		$known    = array_column( $section['view']['items'], null, 'ref' );
		$used     = array();
		$inner    = array();

		foreach ( $sent as $item ) {
			if ( ! is_array( $item ) ) {
				return 'bad_request';
			}
			$values = array(
				'year'  => self::clean( $item['year'] ?? '' ),
				'title' => self::clean( $item['title'] ?? '' ),
				'text'  => self::clean( $item['text'] ?? '' ),
			);
			if ( in_array( null, $values, true ) ) {
				return 'bad_request';
			}
			if ( self::length( $values['year'] ) > self::MAX_YEAR || self::length( $values['title'] ) > self::MAX_TITLE || self::length( $values['text'] ) > self::MAX_EVENT_TEXT ) {
				return 'too_long';
			}

			$ref = $item['ref'] ?? null;
			if ( null !== $ref ) {
				if ( ! is_int( $ref ) || ! isset( $known[ $ref ] ) || isset( $used[ $ref ] ) ) {
					return 'bad_request';
				}
				$used[ $ref ] = true;
				if ( array_intersect_key( $known[ $ref ], $values ) === $values ) {
					$inner[] = $timeline['innerBlocks'][ $ref ];
					continue;
				}
			}
			if ( '' === implode( '', $values ) ) {
				continue;
			}
			if ( '' === $values['year'] || '' === $values['title'] ) {
				return 'event_incomplete';
			}
			$inner[] = self::event( $values );
		}

		if ( $inner !== $timeline['innerBlocks'] ) {
			$group['innerBlocks'][ $at ] = self::with_inner( $timeline, $inner );
		}

		return $group;
	}

	/**
	 * A block with a new list of inner blocks, keeping its own opening and closing markup.
	 *
	 * @param array<string, mixed>             $block Parsed block.
	 * @param array<int, array<string, mixed>> $inner New inner blocks.
	 * @return array<string, mixed>
	 */
	private static function with_inner( array $block, array $inner ): array {
		$chunks = array_values( array_filter( $block['innerContent'], 'is_string' ) );
		$open   = (string) ( $chunks[0] ?? '' );
		$close  = count( $chunks ) > 1 ? (string) end( $chunks ) : '';
		// Opening and closing were one chunk when the block had no inner blocks.
		if ( count( $chunks ) < 2 && 1 === preg_match( '#^(.*>\s*)(</[a-z0-9]+>\s*)$#s', $open, $m ) ) {
			$open  = $m[1];
			$close = $m[2];
		}

		$content = array( $open );
		foreach ( array_keys( $inner ) as $i ) {
			if ( $i > 0 ) {
				$content[] = "\n";
			}
			$content[] = null;
		}
		$content[] = $close;

		$block['innerBlocks']  = array_values( $inner );
		$block['innerContent'] = $content;
		$block['innerHTML']    = $open . $close;

		return $block;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function paragraph( string $text ): array {
		$html = "\n<p>" . self::escape( $text ) . "</p>\n";

		return array(
			'blockName'    => 'core/paragraph',
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * The markup the timeline-item block saves for an entry with no translation key.
	 *
	 * @param array{year: string, title: string, text: string} $values Entry.
	 * @return array<string, mixed>
	 */
	private static function event( array $values ): array {
		$html = "\n" . '<li class="wp-block-pura-timeline-item timeline-item"><span class="timeline-item__year">' . self::escape( $values['year'] )
			. '</span><div class="timeline-item__card"><h4>' . self::escape( $values['title'] ) . '</h4><p>' . self::escape( $values['text'] ) . '</p></div></li>' . "\n";

		return array(
			'blockName'    => self::EVENT,
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	// ---------- text ----------

	/** Visible text of a piece of markup, on one line. */
	private static function plain( string $html ): string {
		$text = html_entity_decode( wp_strip_all_tags( (string) preg_replace( '#<br\s*/?>|</(p|li|h[1-6]|div)>#i', ' ', $html ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Submitted text on one line, or null when it is not text.
	 *
	 * @param mixed $raw Submitted value.
	 */
	private static function clean( $raw ): ?string {
		if ( ! is_string( $raw ) ) {
			return null;
		}

		return trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( wp_check_invalid_utf8( $raw ) ) ) );
	}

	/** Text as the block editor stores it inside a paragraph. */
	private static function escape( string $text ): string {
		return str_replace( array( '&', '<', '>' ), array( '&amp;', '&lt;', '&gt;' ), $text );
	}

	private static function length( string $text ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
	}
}
