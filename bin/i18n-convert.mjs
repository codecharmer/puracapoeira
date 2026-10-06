#!/usr/bin/env node
/**
 * i18n-convert.mjs
 *
 * Extracts the English / Portuguese dictionaries that the legacy static site
 * keeps inline in `public/assets/js/main.js` and converts them into the JSON
 * dictionaries consumed by the WordPress theme:
 *
 *   themes/puracapoeira/assets/i18n/en.json
 *   themes/puracapoeira/assets/i18n/pt.json
 *   themes/puracapoeira/import/i18n/pura_profesor-<slug>.json
 *
 * Usage (Node 20, no dependencies), from the repository root:
 *
 *   node bin/i18n-convert.mjs
 *
 * Spanish is the canonical server-rendered content in WordPress; these
 * dictionaries are applied in the browser. The script fails (exit code 1)
 * whenever it meets a selector it does not know how to place, so nothing is
 * dropped silently. Selectors mapped to `null` (DROP) are discarded on
 * purpose: their text is rendered from `common` or from data values.
 *
 * Dictionary schema:
 *
 *   {
 *     "common": { "<camelKey>": "text" },
 *     "values": { "<Spanish string>": "translation" },
 *     "shared": { "<kebab-key>": <value> },
 *     "pages":  { "<page-slug>": { "<kebab-key>": <value> } }
 *   }
 *
 * where `<value>` is a string or `{ "html": "<string>" }`. Per-teacher files
 * are `{ "en": { "<key>": <value> }, "pt": { ... } }`.
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(
	path.dirname( fileURLToPath( import.meta.url ) ),
	'..'
);
const MAIN_JS = path.join( ROOT, 'public/assets/js/main.js' );
const PUBLIC_DIR = path.join( ROOT, 'public' );
const THEME_DIR = path.join( ROOT, 'themes/puracapoeira' );
const DICT_DIR = path.join( THEME_DIR, 'assets/i18n' );
const IMPORT_DIR = path.join( THEME_DIR, 'import/i18n' );

const LANGS = [ 'en', 'pt' ];

/* ------------------------------------------------------------------ */
/* MAPPING                                                             */
/* ------------------------------------------------------------------ */

/** Sentinel: the selector is intentionally discarded. */
const DROP = null;
/** Sentinel: the selector feeds a COMPOSERS entry instead of a key. */
const COMPOSED = Symbol( 'composed' );

/** Static pages: legacy file → slug under `pages`. */
const PAGE_SLUGS = {
	'index.html': 'inicio',
	'grupo.html': 'grupo',
	'profesores.html': 'profesores',
	'sedes.html': 'sedes',
	'galeria.html': 'galeria',
	'eventos.html': 'eventos',
	'contacto.html': 'contacto',
};

/** Sede detail pages: everything they translate comes from `common` + data. */
const SEDE_FILES = [
	'cuernavaca.html',
	'toluca.html',
	'guanajuato.html',
	'ceara.html',
	'angola.html',
	'austin.html',
];

/** Teacher profile pages: legacy file → import slug. */
const TEACHER_SLUGS = {
	'profesor-mestre-madona.html': 'mestre-madona',
	'profesor-mestre-romim.html': 'mestre-romim',
	'profesor-mestre-junior-paludo.html': 'mestre-junior-paludo',
	'profesor-contramestre-pepe-mortales.html': 'contramestre-pepe-mortales',
	'profesor-malandro.html': 'profesor-malandro',
	'profesora-laura.html': 'profesora-laura',
	'instructora-palito.html': 'instructora-palito',
	'instructor-dudu.html': 'instructor-dudu',
	'instructor-chino.html': 'instructor-chino',
};

/**
 * Teachers whose legacy translations are partly stale: only the listed keys
 * are emitted, the rest is dropped with a note.
 */
const TEACHER_KEEP_ONLY = {
	// The Spanish page was rewritten; the legacy bio / trajectory are stale.
	'instructora-palito.html': [ 'hero-eyebrow' ],
};

/** Selector → key for each static page (`map` first, then `extras`). */
const PAGE_SELECTORS = {
	'index.html': {
		// `map` entries: overridden by `extras` on the live site.
		'main .hero .eyebrow': DROP,
		'main .hero .sub': DROP,
		'main .hero .btn--primary': DROP,
		'main .hero .btn--outline': DROP,
		// `extras` entries.
		'.hero__meta .eyebrow:nth-of-type(1)': 'hero-eyebrow',
		'.hero__meta .eyebrow--muted': 'hero-eyebrow-muted',
		'.hero .sub': 'hero-sub',
		'.hero .lead': 'hero-lead',
		"[data-testid='hero-cta-sedes']": 'hero-cta-sedes',
		"[data-testid='hero-cta-grupo']": 'hero-cta-grupo',
		"[data-testid='hero-cta-galeria']": 'hero-cta-galeria',
		".section[data-testid='home-intro'] .eyebrow": 'intro-eyebrow',
		".section[data-testid='home-intro'] h2.display": 'intro-title',
		".section[data-testid='home-intro'] .editorial > div:nth-of-type(2) .lead":
			'intro-lead',
		".section[data-testid='home-intro'] .editorial > div:nth-of-type(2) .muted:nth-of-type(1)":
			'intro-p1',
		".section[data-testid='home-intro'] .editorial > div:nth-of-type(2) .muted:nth-of-type(2)":
			'intro-p2',
		"[data-testid='intro-cta-grupo']": 'intro-cta-grupo',
		".section[data-testid='home-sedes-section'] .eyebrow": 'sedes-eyebrow',
		".section[data-testid='home-sedes-section'] h2.display": 'sedes-title',
		".section[data-testid='home-sedes-section'] .section-head > p":
			'sedes-lead',
		'#home-sedes .muted': DROP,
		'.cta-band h2.display': 'cta-title',
		'.cta-band p': 'cta-text',
		"[data-testid='cta-band-contacto']": 'cta-contacto',
		"[data-testid='cta-band-sedes']": 'cta-sedes',
	},
	'grupo.html': {
		// `map` entries.
		'.page-hero .eyebrow': 'hero-eyebrow',
		'.page-hero h1': 'hero-title',
		'.page-hero p': 'hero-lead',
		"[data-testid='grupo-cta-sedes']": 'vision-cta-sedes',
		"[data-testid='grupo-cta-profesores']": 'vision-cta-profesores',
		// `extras` entries: history section.
		'main > section:nth-of-type(2) .editorial > div:nth-of-type(2) .eyebrow':
			'hist-eyebrow',
		'main > section:nth-of-type(2) .editorial > div:nth-of-type(2) h2.display':
			'hist-title',
		'main > section:nth-of-type(2) .editorial > div:nth-of-type(2) p.lead':
			'hist-lead',
		'main > section:nth-of-type(2) .editorial > div:nth-of-type(2) p.muted:nth-of-type(1)':
			'hist-p1',
		'main > section:nth-of-type(2) .editorial > div:nth-of-type(2) blockquote.pull-quote':
			'hist-quote',
		'main > section:nth-of-type(2) .editorial > div:nth-of-type(2) p.muted:nth-of-type(2)':
			'hist-p2',
		// Values section.
		'main > section:nth-of-type(3) .section-head .eyebrow':
			'values-eyebrow',
		'main > section:nth-of-type(3) .section-head h2.display':
			'values-title',
		'main > section:nth-of-type(3) .section-head > p': 'values-lead',
		...valueCells( 8 ),
		// Vision section.
		'main > section:nth-of-type(4) .editorial > div:nth-of-type(1) .eyebrow':
			'vision-eyebrow',
		'main > section:nth-of-type(4) .editorial > div:nth-of-type(1) h2.display':
			'vision-title',
		'main > section:nth-of-type(4) .editorial > div:nth-of-type(2) p.lead':
			'vision-lead',
		'main > section:nth-of-type(4) .editorial > div:nth-of-type(2) p.muted':
			'vision-text',
	},
	'profesores.html': {
		...pageHero(),
		'#prof-grid .muted': DROP,
	},
	'sedes.html': {
		...pageHero(),
		'#sedes-grid .muted': DROP,
	},
	'galeria.html': {
		...pageHero(),
		'.filter-group:nth-of-type(1) .filter-group__label': DROP,
		'.filter-group:nth-of-type(2) .filter-group__label': DROP,
		'#gallery-grid .muted': DROP,
		'main > section.section .container > p.muted': COMPOSED,
		'#icloud-link': COMPOSED,
	},
	'eventos.html': {
		...pageHero(),
		'#event-list > p.muted': DROP,
	},
	'contacto.html': {
		...pageHero(),
		'.contact-grid > div:first-child .eyebrow': 'local-eyebrow',
		'.contact-grid > div:first-child h2': 'local-title',
		'.contact-grid > div:last-child .eyebrow': 'form-eyebrow',
		'.contact-grid > div:last-child h2': 'form-title',
		'#contact-cards .muted': DROP,
	},
};

/** Selector → key for every sede detail page. */
const SEDE_SELECTORS = {
	'.page-hero .eyebrow': DROP,
	'#sede-sub': DROP,
};

/**
 * Keys built from several selectors. `parts` are read from the merged page
 * translations (string or `{ firstText }`), `build` returns the final value.
 */
const ICLOUD_ALBUM = 'https://www.icloud.com/sharedalbum/#B1g5qXGF1qQGwbR';
const COMPOSERS = {
	'galeria.html': {
		'more-text': {
			parts: [
				'main > section.section .container > p.muted',
				'#icloud-link',
			],
			build: ( [ text, label ] ) => ( {
				html:
					`${ escapeHtml( text ) } ` +
					`<a href="${ ICLOUD_ALBUM }" target="_blank" rel="noopener noreferrer">` +
					`${ escapeHtml( label ) }</a>`,
			} ),
		},
	},
};

/**
 * Plain-text translations that must become `{ html }` with a `<br>` because
 * the Spanish markup has a line break. `source` locates the Spanish element
 * in `public/<file>` (its first capture group must contain `<br`), and the
 * per-language number is the word count before the break.
 */
const HTML_BREAKS = {
	'profesores.html': {
		'hero-title': { source: /<h1[^>]*>([\s\S]*?)<\/h1>/, en: 1, pt: 1 },
	},
	'sedes.html': {
		'hero-title': { source: /<h1[^>]*>([\s\S]*?)<\/h1>/, en: 2, pt: 2 },
	},
	'contacto.html': {
		'local-title': {
			source: /contact-grid[\s\S]*?<h2[^>]*>([\s\S]*?)<\/h2>/,
			en: 1,
			pt: 1,
		},
	},
};

/**
 * Selector rules for teacher profile pages. The first matching rule wins:
 *  - `key`     → per-teacher key (or DROP)
 *  - `shared`  → identical across teachers, emitted once under `shared`
 *  - `section` → whole-section `{ html }` to split into paragraphs
 */
const PROFILE_RULES = [
	{ test: /^\.page-hero \.eyebrow$/, key: () => 'hero-eyebrow' },
	{ test: /^\.page-hero p$/, key: () => 'hero-intro' },
	{ test: /^\.prof-profile__caption p$/, key: () => 'caption' },
	{
		test: /^\.info-block:nth-of-type\(1\) p:nth-of-type\((\d+)\)$/,
		key: ( m ) => `bio-${ m[ 1 ] }`,
	},
	{
		test: /^\.info-block:nth-of-type\(2\) p:nth-of-type\((\d+)\)$/,
		key: ( m ) => `traj-${ m[ 1 ] }`,
	},
	{
		test: /^\.timeline-item:nth-of-type\((\d+)\) h4$/,
		key: ( m ) => `tl-${ m[ 1 ] }-title`,
	},
	{
		test: /^\.timeline-item:nth-of-type\((\d+)\) (?:\.timeline-item__card )?p$/,
		key: ( m ) => `tl-${ m[ 1 ] }-text`,
	},
	{ test: /^\.info-block:nth-of-type\(1\) h3$/, shared: 'bio-title' },
	{ test: /^\.info-block:nth-of-type\(2\) h3$/, shared: 'traj-title' },
	{ test: /^#madona-timeline-title$/, shared: 'timeline-title' },
	{ test: /^\.info-block:nth-of-type\(1\)$/, section: 'bio' },
	{ test: /^\.info-block:nth-of-type\(2\)$/, section: 'traj' },
	// Social block labels are emitted from `common.*`.
	{ test: /^\.prof-social/, key: () => DROP },
];

/** New `common` keys that the legacy site did not have. */
const EXTRA_COMMON = {
	en: {
		email: 'Email',
		formHint:
			"We will reply by email or WhatsApp. You can also reach us directly on WhatsApp from each location's card.",
		sending: 'Sending…',
		sent: 'Message sent! We will get back to you soon.',
		sendError:
			'The message could not be sent. Please try again or write to us on WhatsApp.',
		sede: 'Location',
		socialTitle: 'Social and contact',
		langSwitcher: 'Language switcher',
		category: 'Category',
		location: 'Location',
		menuLabel: 'Menu',
	},
	pt: {
		email: 'E-mail',
		formHint:
			'Responderemos por e-mail ou WhatsApp. Você também pode falar conosco diretamente pelo WhatsApp no cartão de cada sede.',
		sending: 'Enviando…',
		sent: 'Mensagem enviada! Responderemos em breve.',
		sendError:
			'Não foi possível enviar a mensagem. Tente novamente ou fale conosco pelo WhatsApp.',
		sede: 'Sede',
		socialTitle: 'Redes e contato',
		langSwitcher: 'Seletor de idioma',
		category: 'Categoria',
		location: 'Sede',
		menuLabel: 'Menu',
	},
};

/** Extra `values` entries (Spanish → translation), added when absent. */
const EXTRA_VALUES = {
	en: {
		Ene: 'Jan',
		Feb: 'Feb',
		Mar: 'Mar',
		Abr: 'Apr',
		May: 'May',
		Jun: 'Jun',
		Jul: 'Jul',
		Ago: 'Aug',
		Sep: 'Sep',
		Oct: 'Oct',
		Nov: 'Nov',
		Dic: 'Dec',
		Próximo: 'Upcoming',
		Instagram: 'Instagram',
		YouTube: 'YouTube',
		Facebook: 'Facebook',
		WhatsApp: 'WhatsApp',
		'Sitio web': 'Website',
		'Enlace por confirmar': 'Link coming soon',
		'Ver perfil': 'View profile',
	},
	pt: {
		Ene: 'Jan',
		Feb: 'Fev',
		Mar: 'Mar',
		Abr: 'Abr',
		May: 'Mai',
		Jun: 'Jun',
		Jul: 'Jul',
		Ago: 'Ago',
		Sep: 'Set',
		Oct: 'Out',
		Nov: 'Nov',
		Dic: 'Dez',
		Próximo: 'Próximo',
		Instagram: 'Instagram',
		YouTube: 'YouTube',
		Facebook: 'Facebook',
		WhatsApp: 'WhatsApp',
		'Sitio web': 'Site',
		'Enlace por confirmar': 'Link em breve',
		'Ver perfil': 'Ver perfil',
	},
};

/** `shared` keys that come from `I18N.nav` (in order) and `I18N.footer`. */
const NAV_KEYS = [
	'nav-inicio',
	'nav-grupo',
	'nav-profesores',
	'nav-sedes',
	'nav-galeria',
	'nav-eventos',
	'nav-contacto',
];
const FOOTER_KEYS = {
	'footer-about-title': 'aboutTitle',
	'footer-about-text': 'aboutText',
	'footer-nav-title': 'navTitle',
	'footer-sedes-title': 'sedesTitle',
	'footer-motto': 'motto',
};

function pageHero() {
	return {
		'.page-hero .eyebrow': 'hero-eyebrow',
		'.page-hero h1': 'hero-title',
		'.page-hero p': 'hero-lead',
	};
}

function valueCells( count ) {
	const cells = {};
	for ( let n = 1; n <= count; n += 1 ) {
		cells[
			`#valores-grid .value-cell:nth-of-type(${ n }) h3`
		] = `value-${ n }-title`;
		cells[
			`#valores-grid .value-cell:nth-of-type(${ n }) p`
		] = `value-${ n }-text`;
	}
	return cells;
}

/* ------------------------------------------------------------------ */
/* Helpers                                                             */
/* ------------------------------------------------------------------ */

const warnings = [];
const notes = [];

function fail( message ) {
	throw new Error( message );
}

function warn( message ) {
	warnings.push( message );
}

function note( message ) {
	notes.push( message );
}

function hasOwn( object, key ) {
	return Object.prototype.hasOwnProperty.call( object, key );
}

function escapeHtml( text ) {
	return String( text )
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' );
}

function decodeEntities( text ) {
	return text
		.replace( /&nbsp;/g, ' ' )
		.replace( /&quot;/g, '"' )
		.replace( /&#39;|&apos;/g, "'" )
		.replace( /&lt;/g, '<' )
		.replace( /&gt;/g, '>' )
		.replace( /&amp;/g, '&' );
}

function sortObject( object ) {
	const sorted = {};
	for ( const key of Object.keys( object ).sort() ) {
		sorted[ key ] = object[ key ];
	}
	return sorted;
}

function readPublic( file ) {
	const filePath = path.join( PUBLIC_DIR, file );
	if ( ! fs.existsSync( filePath ) ) {
		fail( `Spanish page not found: ${ path.relative( ROOT, filePath ) }` );
	}
	return fs.readFileSync( filePath, 'utf8' );
}

/**
 * Normalizes legacy HTML snippets to the markup WordPress renders:
 *  - `<br/>` → `<br>`
 *  - the green `<em>` accent → `<mark class="has-inline-color">`
 */
function normalizeHtml( html ) {
	return html
		.replace( /<br\s*\/>/g, '<br>' )
		.replace(
			/<em style="color:var\(--green-deep\);">([\s\S]*?)<\/em>/g,
			'<mark style="color:var(--green-deep)" class="has-inline-color">$1</mark>'
		);
}

/** Converts a legacy translation value into a dictionary value. */
function convertValue( value, where ) {
	if ( typeof value === 'string' ) {
		return value.trim();
	}
	if ( value && typeof value === 'object' && hasOwn( value, 'html' ) ) {
		return { html: normalizeHtml( String( value.html ).trim() ) };
	}
	if ( value && typeof value === 'object' && hasOwn( value, 'firstText' ) ) {
		fail(
			`${ where }: { firstText } values must be handled by a COMPOSERS entry`
		);
	}
	return fail( `${ where }: unsupported value ${ JSON.stringify( value ) }` );
}

/** Raw text of a legacy value (string or `{ firstText }`). */
function rawText( value, where ) {
	if ( typeof value === 'string' ) {
		return value.trim();
	}
	if ( value && typeof value === 'object' && hasOwn( value, 'firstText' ) ) {
		return String( value.firstText ).trim();
	}
	return fail(
		`${ where }: expected a text value, got ${ JSON.stringify( value ) }`
	);
}

/** Inserts `<br>` after the first `afterWord` words of a plain string. */
function insertBreak( text, afterWord, where ) {
	if ( typeof text !== 'string' ) {
		fail(
			`${ where }: cannot insert a line break into a non-string value`
		);
	}
	const words = text.split( ' ' );
	if ( words.length <= afterWord ) {
		fail(
			`${ where }: "${ text }" has fewer than ${ afterWord + 1 } words`
		);
	}
	return {
		html:
			escapeHtml( words.slice( 0, afterWord ).join( ' ' ) ) +
			'<br>' +
			escapeHtml( words.slice( afterWord ).join( ' ' ) ),
	};
}

/* ------------------------------------------------------------------ */
/* Source extraction                                                   */
/* ------------------------------------------------------------------ */

/**
 * Returns the index of the `}` matching the `{` at `open`, tracking string
 * state (double / single quotes, template literals with `${}` expressions,
 * escapes) and comments.
 */
function findMatchingBrace( source, open ) {
	let depth = 0;
	let quote = null;
	const templateStack = [];
	let i = open;

	while ( i < source.length ) {
		const ch = source[ i ];

		if ( quote ) {
			if ( ch === '\\' ) {
				i += 2;
				continue;
			}
			if ( quote === '`' && ch === '$' && source[ i + 1 ] === '{' ) {
				templateStack.push( depth );
				quote = null;
				depth += 1;
				i += 2;
				continue;
			}
			if ( ch === quote ) {
				quote = null;
			}
			i += 1;
			continue;
		}

		if ( ch === '/' && source[ i + 1 ] === '/' ) {
			const end = source.indexOf( '\n', i );
			i = end === -1 ? source.length : end;
			continue;
		}
		if ( ch === '/' && source[ i + 1 ] === '*' ) {
			const end = source.indexOf( '*/', i + 2 );
			if ( end === -1 ) {
				fail(
					'Unterminated block comment while scanning object literal'
				);
			}
			i = end + 2;
			continue;
		}
		if ( ch === '"' || ch === "'" || ch === '`' ) {
			quote = ch;
			i += 1;
			continue;
		}
		if ( ch === '{' ) {
			depth += 1;
		} else if ( ch === '}' ) {
			depth -= 1;
			if (
				templateStack.length &&
				depth === templateStack[ templateStack.length - 1 ]
			) {
				templateStack.pop();
				quote = '`';
				i += 1;
				continue;
			}
			if ( depth === 0 ) {
				return i;
			}
		}
		i += 1;
	}
	return fail( 'Unbalanced braces while scanning object literal' );
}

/**
 * Extracts the object literal assigned by `declaration` (e.g. `const map =`),
 * optionally searching only after the `within` marker (a function header).
 */
function extractObjectLiteral( source, declaration, within ) {
	let from = 0;
	if ( within ) {
		from = source.indexOf( within );
		if ( from === -1 ) {
			fail( `Could not find "${ within }" in main.js` );
		}
	}
	const declIndex = source.indexOf( declaration, from );
	if ( declIndex === -1 ) {
		fail( `Could not find "${ declaration }" in main.js` );
	}
	const open = source.indexOf( '{', declIndex + declaration.length );
	const between = source.slice( declIndex + declaration.length, open );
	if ( open === -1 || between.trim() !== '' ) {
		fail( `"${ declaration }" is not followed by an object literal` );
	}
	const close = findMatchingBrace( source, open );
	return source.slice( open, close + 1 );
}

function evaluateObject( literal, scope = {} ) {
	const names = Object.keys( scope );
	const values = names.map( ( name ) => scope[ name ] );
	// The slices are plain data literals from our own source tree.
	// eslint-disable-next-line no-new-func
	return new Function( ...names, `"use strict"; return (${ literal });` )(
		...values
	);
}

/* ------------------------------------------------------------------ */
/* Spanish page inspection                                             */
/* ------------------------------------------------------------------ */

/** Paragraph count inside each `<section class="info-block">` of a page. */
function spanishParagraphCounts( file ) {
	const html = readPublic( file );
	const blocks = [
		...html.matchAll(
			/<section class="info-block">([\s\S]*?)<\/section>/g
		),
	];
	return blocks.map( ( m ) => ( m[ 1 ].match( /<p[\s>]/g ) || [] ).length );
}

function spanishTimelineCount( file ) {
	const html = readPublic( file );
	return ( html.match( /class="timeline-item"/g ) || [] ).length;
}

/**
 * Checks that HTML_BREAKS agrees with the Spanish markup: every `.page-hero
 * h1` with a `<br/>` needs an entry, and every entry's source has a `<br`.
 */
function checkHtmlBreaks( file, table ) {
	const html = readPublic( file );
	const breaks = HTML_BREAKS[ file ] || {};

	for ( const [ key, spec ] of Object.entries( breaks ) ) {
		const match = html.match( spec.source );
		if ( ! match || ! /<br/.test( match[ 1 ] ) ) {
			fail(
				`${ file }: HTML_BREAKS["${ key }"] but the Spanish element has no <br/>`
			);
		}
	}

	if ( table[ '.page-hero h1' ] && ! breaks[ table[ '.page-hero h1' ] ] ) {
		const h1 = html.match(
			/<section class="page-hero">[\s\S]*?<h1[^>]*>([\s\S]*?)<\/h1>/
		);
		if ( h1 && /<br/.test( h1[ 1 ] ) ) {
			fail(
				`${ file }: the Spanish <h1> has a <br/> but HTML_BREAKS has no entry`
			);
		}
	}
}

/* ------------------------------------------------------------------ */
/* Builders                                                            */
/* ------------------------------------------------------------------ */

function emptyDictionary() {
	return { common: {}, values: {}, shared: {}, pages: {} };
}

function buildCommon( I18N, dict ) {
	for ( const lang of LANGS ) {
		const common = { ...I18N.common[ lang ] };
		for ( const [ key, value ] of Object.entries( EXTRA_COMMON[ lang ] ) ) {
			if ( hasOwn( common, key ) ) {
				fail(
					`common.${ key } already exists in main.js; EXTRA_COMMON must not override it`
				);
			}
			common[ key ] = value;
		}
		if ( ! common.seeProfile ) {
			fail( `common.seeProfile is missing for "${ lang }"` );
		}
		common.viewProfile = common.seeProfile;
		dict[ lang ].common = common;
	}
}

function buildValues( VALUE_I18N, dict ) {
	for ( const lang of LANGS ) {
		const values = { ...VALUE_I18N[ lang ] };
		for ( const [ key, value ] of Object.entries( EXTRA_VALUES[ lang ] ) ) {
			if ( hasOwn( values, key ) ) {
				if ( values[ key ] !== value ) {
					note(
						`values["${ key }"] (${ lang }) kept as "${ values[ key ] }" from main.js (EXTRA_VALUES has "${ value }")`
					);
				}
				continue;
			}
			values[ key ] = value;
		}
		dict[ lang ].values = values;
	}
}

function buildShared( I18N, dict ) {
	for ( const lang of LANGS ) {
		const shared = dict[ lang ].shared;
		const nav = I18N.nav[ lang ];
		if ( ! Array.isArray( nav ) || nav.length !== NAV_KEYS.length ) {
			fail( `I18N.nav.${ lang } must have ${ NAV_KEYS.length } entries` );
		}
		NAV_KEYS.forEach( ( key, index ) => {
			shared[ key ] = nav[ index ];
		} );
		const footer = I18N.footer[ lang ];
		for ( const [ key, source ] of Object.entries( FOOTER_KEYS ) ) {
			if ( ! footer[ source ] ) {
				fail( `I18N.footer.${ lang }.${ source } is missing` );
			}
			shared[ key ] = footer[ source ];
		}
		// Mirrors renderFooter(): `© ${year} Pura Capoeira. ${rights}`.
		shared[
			'footer-rights'
		] = `© {year} Pura Capoeira. ${ footer.rights }`;
	}
}

/** Adds a value shared across teacher profiles, refusing conflicts. */
function addShared( dict, lang, key, value, where ) {
	const shared = dict[ lang ].shared;
	if ( hasOwn( shared, key ) && shared[ key ] !== value ) {
		fail(
			`${ where }: shared.${ key } (${ lang }) is "${ shared[ key ] }" but this page says "${ value }"`
		);
	}
	shared[ key ] = value;
}

/** Merges `map` and `extras` for one page and language; `extras` wins. */
function mergedTranslations( map, extras, file, lang ) {
	return {
		...( ( map[ file ] && map[ file ][ lang ] ) || {} ),
		...( ( extras[ file ] && extras[ file ][ lang ] ) || {} ),
	};
}

function setKey( target, key, value, where ) {
	if ( hasOwn( target, key ) ) {
		fail(
			`${ where }: key "${ key }" is produced by more than one selector`
		);
	}
	target[ key ] = value;
}

/** Converts one static page (or sede page) using a selector table. */
function convertStaticPage( { map, extras, file, table, dict, slug, stats } ) {
	const seen = new Set();
	const unmapped = [];
	const composeParts = { en: {}, pt: {} };

	for ( const lang of LANGS ) {
		const page = {};
		const merged = mergedTranslations( map, extras, file, lang );

		for ( const [ selector, value ] of Object.entries( merged ) ) {
			seen.add( selector );
			if ( ! hasOwn( table, selector ) ) {
				unmapped.push( `${ file } (${ lang }): ${ selector }` );
				continue;
			}
			const target = table[ selector ];
			if ( target === DROP ) {
				stats.dropped += 1;
				continue;
			}
			if ( target === COMPOSED ) {
				composeParts[ lang ][ selector ] = value;
				continue;
			}
			const where = `${ file } (${ lang }) ${ selector }`;
			setKey( page, target, convertValue( value, where ), where );
		}

		for ( const [ key, composer ] of Object.entries(
			COMPOSERS[ file ] || {}
		) ) {
			const parts = composer.parts.map( ( selector ) => {
				if ( ! hasOwn( composeParts[ lang ], selector ) ) {
					fail(
						`${ file } (${ lang }): composer "${ key }" needs ${ selector }`
					);
				}
				return rawText(
					composeParts[ lang ][ selector ],
					`${ file } ${ selector }`
				);
			} );
			setKey(
				page,
				key,
				composer.build( parts ),
				`${ file } (${ lang }) ${ key }`
			);
		}

		for ( const [ key, spec ] of Object.entries(
			HTML_BREAKS[ file ] || {}
		) ) {
			if ( ! hasOwn( page, key ) ) {
				fail(
					`${ file } (${ lang }): HTML_BREAKS["${ key }"] but the key was not produced`
				);
			}
			page[ key ] = insertBreak(
				page[ key ],
				spec[ lang ],
				`${ file } (${ lang }) ${ key }`
			);
		}

		if ( slug ) {
			dict[ lang ].pages[ slug ] = sortObject( page );
		} else if ( Object.keys( page ).length ) {
			fail(
				`${ file } (${ lang }): sede pages must not produce keys (${ Object.keys(
					page
				) })`
			);
		}
	}

	for ( const selector of Object.keys( table ) ) {
		if ( ! seen.has( selector ) ) {
			warn(
				`${ file }: mapping entry "${ selector }" matches nothing in main.js`
			);
		}
	}

	return unmapped;
}

/**
 * Splits a whole-section `{ html }` (`<h3>…</h3><p>…</p>…`) into paragraph
 * values, or returns null when the markup is not a plain paragraph list.
 */
function splitSectionHtml( html ) {
	let rest = html.trim();
	const heading = rest.match( /^<h3>[\s\S]*?<\/h3>/ );
	if ( heading ) {
		rest = rest.slice( heading[ 0 ].length ).trim();
	}
	const paragraphRe = /<p>([\s\S]*?)<\/p>/g;
	const paragraphs = [ ...rest.matchAll( paragraphRe ) ].map( ( m ) =>
		m[ 1 ].trim()
	);
	const leftover = rest.replace( paragraphRe, '' ).trim();
	if ( ! paragraphs.length || leftover ) {
		return null;
	}
	return paragraphs.map( ( paragraph ) =>
		/<[a-z]/i.test( paragraph )
			? { html: normalizeHtml( paragraph ) }
			: decodeEntities( paragraph )
	);
}

/** Converts one teacher profile into `{ en, pt }` dictionaries. */
function convertTeacher( { map, extras, file, dict, stats } ) {
	const slug = TEACHER_SLUGS[ file ];
	const unmapped = [];
	const result = {};
	const routes = [];

	for ( const lang of LANGS ) {
		const teacher = {};
		const merged = mergedTranslations( map, extras, file, lang );

		for ( const [ selector, value ] of Object.entries( merged ) ) {
			const rule = PROFILE_RULES.find( ( candidate ) =>
				candidate.test.test( selector )
			);
			if ( ! rule ) {
				unmapped.push( `${ file } (${ lang }): ${ selector }` );
				continue;
			}
			const where = `${ file } (${ lang }) ${ selector }`;
			const match = selector.match( rule.test );

			if ( rule.shared ) {
				addShared(
					dict,
					lang,
					rule.shared,
					rawText( value, where ),
					where
				);
				continue;
			}

			if ( rule.section ) {
				routes.push(
					convertSection( {
						teacher,
						value,
						rule,
						file,
						lang,
						where,
					} )
				);
				continue;
			}

			const key = rule.key( match );
			if ( key === DROP ) {
				stats.dropped += 1;
				continue;
			}
			setKey( teacher, key, convertValue( value, where ), where );
		}

		if ( TEACHER_KEEP_ONLY[ file ] ) {
			const keep = TEACHER_KEEP_ONLY[ file ];
			const removed = Object.keys( teacher ).filter(
				( key ) => ! keep.includes( key )
			);
			for ( const key of removed ) {
				delete teacher[ key ];
			}
			note(
				`${ slug } (${ lang }): legacy translations are stale, kept only [${ keep
					.filter( ( key ) => hasOwn( teacher, key ) )
					.join( ', ' ) }] and dropped [${ removed.join( ', ' ) }]`
			);
		}

		if ( Object.keys( teacher ).length ) {
			result[ lang ] = sortObject( teacher );
		} else {
			note( `${ slug }: no "${ lang }" translations, language omitted` );
		}
	}

	const timelineKeys = Object.keys( result.en || result.pt || {} ).filter(
		( key ) => /^tl-\d+-title$/.test( key )
	);
	if ( timelineKeys.length ) {
		const spanish = spanishTimelineCount( file );
		if ( spanish !== timelineKeys.length ) {
			warn(
				`${ file }: ${ timelineKeys.length } timeline items translated but the Spanish page has ${ spanish }`
			);
		}
	}

	return { slug, result, unmapped, routes };
}

/** Handles a whole-section `{ html }` value (Mestre Madona's bio / trajectory). */
function convertSection( { teacher, value, rule, file, lang, where } ) {
	if ( ! value || typeof value !== 'object' || ! hasOwn( value, 'html' ) ) {
		fail( `${ where }: whole-section selectors must carry { html }` );
	}
	const blockIndex = rule.section === 'bio' ? 0 : 1;
	const expected = spanishParagraphCounts( file )[ blockIndex ];
	const paragraphs = splitSectionHtml( value.html );
	const label = `${ TEACHER_SLUGS[ file ] } ${ rule.section } (${ lang })`;

	if ( paragraphs && paragraphs.length === expected ) {
		paragraphs.forEach( ( paragraph, index ) => {
			setKey(
				teacher,
				`${ rule.section }-${ index + 1 }`,
				paragraph,
				where
			);
		} );
		return `${ label }: split into ${ paragraphs.length } paragraphs`;
	}

	const reason = paragraphs
		? `${ paragraphs.length } paragraphs vs ${ expected } in the Spanish page`
		: 'markup is not a plain <h3> + <p> list';
	warn(
		`${ where }: ${ reason }; emitted a single ${ rule.section }-section value`
	);
	setKey(
		teacher,
		`${ rule.section }-section`,
		{ html: normalizeHtml( value.html.trim() ) },
		where
	);
	return `${ label }: fallback to ${ rule.section }-section (${ reason })`;
}

/* ------------------------------------------------------------------ */
/* Output                                                              */
/* ------------------------------------------------------------------ */

function writeJson( filePath, data ) {
	fs.mkdirSync( path.dirname( filePath ), { recursive: true } );
	const json = `${ JSON.stringify( data, null, '\t' ) }\n`;
	const current = fs.existsSync( filePath )
		? fs.readFileSync( filePath, 'utf8' )
		: null;
	if ( current !== json ) {
		fs.writeFileSync( filePath, json, 'utf8' );
	}
	return current === json ? 'unchanged' : 'written';
}

function count( object ) {
	return Object.keys( object ).length;
}

/* ------------------------------------------------------------------ */
/* Main                                                                */
/* ------------------------------------------------------------------ */

function main() {
	const source = fs.readFileSync( MAIN_JS, 'utf8' );

	const I18N = evaluateObject(
		extractObjectLiteral( source, 'const I18N =' )
	);
	const VALUE_I18N = evaluateObject(
		extractObjectLiteral( source, 'const VALUE_I18N =' )
	);
	const extras = evaluateObject(
		extractObjectLiteral(
			source,
			'const extras =',
			'function applyExtraStaticTranslations('
		)
	);
	const profileSocial = evaluateObject(
		extractObjectLiteral(
			source,
			'const profileSocial =',
			'function translateStaticPage('
		)
	);
	const map = evaluateObject(
		extractObjectLiteral(
			source,
			'const map =',
			'function translateStaticPage('
		),
		{ profileSocial }
	);

	const dict = { en: emptyDictionary(), pt: emptyDictionary() };
	const stats = { dropped: 0 };
	const unmapped = [];
	const teachers = [];
	const routes = [];

	buildCommon( I18N, dict );
	buildValues( VALUE_I18N, dict );
	buildShared( I18N, dict );

	const files = [
		...new Set( [ ...Object.keys( map ), ...Object.keys( extras ) ] ),
	];
	for ( const file of files ) {
		if ( hasOwn( PAGE_SLUGS, file ) ) {
			const table = PAGE_SELECTORS[ file ];
			if ( ! table ) {
				fail( `No PAGE_SELECTORS table for ${ file }` );
			}
			checkHtmlBreaks( file, table );
			unmapped.push(
				...convertStaticPage( {
					map,
					extras,
					file,
					table,
					dict,
					slug: PAGE_SLUGS[ file ],
					stats,
				} )
			);
		} else if ( SEDE_FILES.includes( file ) ) {
			unmapped.push(
				...convertStaticPage( {
					map,
					extras,
					file,
					table: SEDE_SELECTORS,
					dict,
					slug: null,
					stats,
				} )
			);
		} else if ( hasOwn( TEACHER_SLUGS, file ) ) {
			const teacher = convertTeacher( {
				map,
				extras,
				file,
				dict,
				stats,
			} );
			unmapped.push( ...teacher.unmapped );
			routes.push( ...teacher.routes );
			teachers.push( teacher );
		} else {
			fail( `Unknown legacy page in main.js translations: ${ file }` );
		}
	}

	for ( const file of Object.keys( PAGE_SELECTORS ) ) {
		if ( ! files.includes( file ) ) {
			warn(
				`PAGE_SELECTORS has "${ file }" but main.js has no translations for it`
			);
		}
	}
	for ( const file of Object.keys( TEACHER_SLUGS ) ) {
		if ( ! files.includes( file ) ) {
			warn(
				`TEACHER_SLUGS has "${ file }" but main.js has no translations for it`
			);
		}
	}

	if ( unmapped.length ) {
		fail(
			`Unmapped selectors (add them to the MAPPING tables or map them to DROP):\n  ${ unmapped.join(
				'\n  '
			) }`
		);
	}

	const enKeys = Object.keys( dict.en.shared ).sort();
	const ptKeys = Object.keys( dict.pt.shared ).sort();
	if ( enKeys.join( '|' ) !== ptKeys.join( '|' ) ) {
		fail(
			`shared keys differ between en and pt:\n  en: ${ enKeys }\n  pt: ${ ptKeys }`
		);
	}

	// Write dictionaries.
	const written = [];
	for ( const lang of LANGS ) {
		const output = {
			common: sortObject( dict[ lang ].common ),
			values: sortObject( dict[ lang ].values ),
			shared: sortObject( dict[ lang ].shared ),
			pages: sortObject( dict[ lang ].pages ),
		};
		const filePath = path.join( DICT_DIR, `${ lang }.json` );
		written.push(
			`${ path.relative( ROOT, filePath ) } (${ writeJson(
				filePath,
				output
			) })`
		);
	}

	const produced = new Set();
	for ( const teacher of teachers ) {
		if ( ! Object.keys( teacher.result ).length ) {
			note(
				`${ teacher.slug }: no translations at all, no import file written`
			);
			continue;
		}
		const fileName = `pura_profesor-${ teacher.slug }.json`;
		produced.add( fileName );
		const filePath = path.join( IMPORT_DIR, fileName );
		written.push(
			`${ path.relative( ROOT, filePath ) } (${ writeJson(
				filePath,
				teacher.result
			) })`
		);
	}
	if ( fs.existsSync( IMPORT_DIR ) ) {
		for ( const existing of fs.readdirSync( IMPORT_DIR ) ) {
			if (
				/^pura_profesor-.*\.json$/.test( existing ) &&
				! produced.has( existing )
			) {
				warn(
					`${ path.relative(
						ROOT,
						path.join( IMPORT_DIR, existing )
					) } was not produced by this run (stale?)`
				);
			}
		}
	}

	// Summary.
	const lines = [];
	lines.push(
		'i18n-convert: dictionaries generated from public/assets/js/main.js'
	);
	lines.push( '' );
	lines.push( 'Files:' );
	for ( const entry of written ) {
		lines.push( `  ${ entry }` );
	}
	lines.push( '' );
	lines.push( 'Sections (en / pt):' );
	for ( const section of [ 'common', 'values', 'shared' ] ) {
		lines.push(
			`  ${ section }: ${ count( dict.en[ section ] ) } / ${ count(
				dict.pt[ section ]
			) }`
		);
	}
	for ( const slug of Object.keys( dict.en.pages ).sort() ) {
		lines.push(
			`  pages.${ slug }: ${ count( dict.en.pages[ slug ] ) } / ${ count(
				dict.pt.pages[ slug ]
			) }`
		);
	}
	for ( const teacher of teachers ) {
		lines.push(
			`  teacher ${ teacher.slug }: ${ count(
				teacher.result.en || {}
			) } / ${ count( teacher.result.pt || {} ) }`
		);
	}
	lines.push( `  discarded selectors (DROP): ${ stats.dropped }` );
	if ( routes.length ) {
		lines.push( '' );
		lines.push( 'Whole-section routes:' );
		for ( const route of routes ) {
			lines.push( `  ${ route }` );
		}
	}
	if ( notes.length ) {
		lines.push( '' );
		lines.push( 'Notes:' );
		for ( const entry of notes ) {
			lines.push( `  ${ entry }` );
		}
	}
	if ( warnings.length ) {
		lines.push( '' );
		lines.push( 'Warnings:' );
		for ( const entry of warnings ) {
			lines.push( `  ${ entry }` );
		}
	}
	process.stdout.write( `${ lines.join( '\n' ) }\n` );
}

try {
	main();
} catch ( error ) {
	process.stderr.write( `i18n-convert: ${ error.message }\n` );
	process.exit( 1 );
}
