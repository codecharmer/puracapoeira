/**
 * Pure helpers for the client-side language switcher.
 *
 * Nothing here reads page state on its own: callers pass language hints and dictionaries in,
 * and the DOM helpers only touch the element they are handed, so everything runs under jsdom.
 *
 * Original Spanish content is remembered on the element itself so it can be restored when the
 * visitor switches back:
 *
 * - `data-pc-orig-mode`  one of `text` | `first` | `html` (how the content was replaced);
 * - `data-pc-orig`       the original textContent, first text node value, or innerHTML;
 * - `data-pc-orig-attrs` space-separated attribute names that were translated;
 * - `data-pc-orig-attr-<name>` the original value of each translated attribute.
 */

export const LANGS = [ 'es', 'en', 'pt' ];
export const DEFAULT_LANG = 'es';

export const ORIG_MODE_ATTR = 'data-pc-orig-mode';
export const ORIG_VALUE_ATTR = 'data-pc-orig';
export const ORIG_ATTRS_ATTR = 'data-pc-orig-attrs';
export const ORIG_ATTR_PREFIX = 'data-pc-orig-attr-';

const ELEMENT_NODE = 1;
const TEXT_NODE = 3;

const CLASS_KEY_RE = /^i18n-([a-z0-9]+(?:-[a-z0-9]+)*)$/;
const ATTR_NAME_RE = /^[a-z][a-z0-9-]*$/;
const PLACEHOLDER_RE = /\{([a-zA-Z0-9_]+)\}/g;

const ALLOWED_INLINE_TAGS = [
	'br',
	'em',
	'strong',
	'b',
	'i',
	'mark',
	'span',
	'a',
	'sup',
	'sub',
];
const ALLOWED_SECTION_TAGS = [ 'h3', 'h4', 'p', 'ul', 'li' ];
const DROP_WITH_CONTENT = [ 'script', 'style', 'template' ];
const MARK_STYLE_RE = /^color:\s*var\(--[a-z0-9-]+\)\s*;?$/;
const SAFE_SCHEME_RE = /^(?:https?|mailto|tel):/i;
const ANY_SCHEME_RE = /^[a-z][a-z0-9+.-]*:/i;

/** Elements whose innerHTML may contain block-level markup (`h3 h4 p ul li`). */
const SECTION_HOSTS = [
	'div',
	'section',
	'article',
	'aside',
	'blockquote',
	'details',
	'figure',
	'footer',
	'header',
	'main',
	'nav',
];

/** Subtrees that never hold translatable text. */
const SKIP_SUBTREES = [ 'svg', 'script', 'style', 'template', 'noscript' ];

/* -------------------------------------------------------------------------- */
/* Language selection                                                          */
/* -------------------------------------------------------------------------- */

/**
 * Collapse a BCP 47 tag (or anything string-like) to one of the supported codes.
 *
 * @param {*}      value       `navigator.language`-style value.
 * @param {string} defaultLang Code to use when nothing matches.
 * @return {string} `en`, `pt` or the default.
 */
export function normalizeLang( value, defaultLang = DEFAULT_LANG ) {
	const v = String( value ?? '' )
		.trim()
		.toLowerCase();
	if ( v.startsWith( 'en' ) ) {
		return 'en';
	}
	if ( v.startsWith( 'pt' ) ) {
		return 'pt';
	}
	return defaultLang;
}

/**
 * Decide the language: `?lang=` → saved preference → browser language → default.
 *
 * Query and saved values must be exact supported codes (case-insensitive); the browser
 * language is normalised.
 *
 * @param {Object}   hints
 * @param {*}        hints.query         Value of the `lang` query parameter.
 * @param {*}        hints.saved         Value read from storage.
 * @param {*}        hints.navigatorLang `navigator.language`.
 * @param {string[]} hints.langs         Supported codes.
 * @param {string}   hints.defaultLang   Fallback code.
 * @return {string} Supported language code.
 */
export function pickLang( {
	query,
	saved,
	navigatorLang,
	langs = LANGS,
	defaultLang = DEFAULT_LANG,
} = {} ) {
	const exact = ( value ) => {
		const code = String( value ?? '' )
			.trim()
			.toLowerCase();
		return langs.includes( code ) ? code : null;
	};

	const fromQuery = exact( query );
	if ( fromQuery ) {
		return fromQuery;
	}

	const fromSaved = exact( saved );
	if ( fromSaved ) {
		return fromSaved;
	}

	if ( navigatorLang ) {
		const fromNavigator = normalizeLang( navigatorLang, defaultLang );
		if ( langs.includes( fromNavigator ) ) {
			return fromNavigator;
		}
	}

	return defaultLang;
}

/* -------------------------------------------------------------------------- */
/* Dictionary lookups                                                           */
/* -------------------------------------------------------------------------- */

function has( obj, key ) {
	return (
		obj !== null &&
		typeof obj === 'object' &&
		Object.prototype.hasOwnProperty.call( obj, key )
	);
}

/**
 * Read a dotted path from a nested object.
 *
 * @param {*}      obj  Source object.
 * @param {string} path Dotted path such as `common.langSwitcher`.
 * @return {*} The value, or `undefined` when any segment is missing.
 */
export function getPath( obj, path ) {
	const segments = String( path ?? '' )
		.split( '.' )
		.filter( Boolean );
	if ( ! segments.length ) {
		return undefined;
	}
	let current = obj;
	for ( const segment of segments ) {
		if ( ! has( current, segment ) ) {
			return undefined;
		}
		current = current[ segment ];
	}
	return current;
}

/**
 * Key used to match a Spanish value regardless of accents, case and spacing.
 *
 * @param {*} str Source text.
 * @return {string} NFD-stripped, lower-cased, whitespace-collapsed key.
 */
export function normalizeValueKey( str ) {
	return String( str ?? '' )
		.normalize( 'NFD' )
		.replace( /[̀-ͯ]/g, '' )
		.toLowerCase()
		.replace( /\s+/g, ' ' )
		.trim();
}

/**
 * Build the Spanish-value → translation index from a dictionary's `values` map.
 *
 * @param {Object<string, string>|undefined} values The `values` section.
 * @return {Map<string, string>} Map keyed by `normalizeValueKey( spanish )`.
 */
export function buildValueIndex( values ) {
	const index = new Map();
	if ( values && typeof values === 'object' ) {
		Object.keys( values ).forEach( ( spanish ) => {
			const key = normalizeValueKey( spanish );
			if ( key && ! index.has( key ) ) {
				index.set( key, values[ spanish ] );
			}
		} );
	}
	return index;
}

/**
 * Resolve a scoped key: post dictionary → `pages[slug]` (page scope only) → `shared`.
 *
 * @param {Object|undefined} dict     Loaded language dictionary.
 * @param {Object|undefined} postDict Per-post translations for the current language.
 * @param {Object|undefined} scope    `{ type, slug }` describing the current view.
 * @param {string}           key      Kebab-case key.
 * @return {*} The translation (string or `{ html }`), or `undefined`.
 */
export function lookupScoped( dict, postDict, scope, key ) {
	if ( ! key ) {
		return undefined;
	}
	if ( has( postDict, key ) ) {
		return postDict[ key ];
	}
	if ( scope && scope.type === 'page' && scope.slug && dict ) {
		const page = getPath( dict, `pages.${ scope.slug }` );
		if ( has( page, key ) ) {
			return page[ key ];
		}
	}
	if ( dict && has( dict.shared, key ) ) {
		return dict.shared[ key ];
	}
	return undefined;
}

/**
 * Whether a dictionary value can be applied to the page.
 *
 * @param {*} value Candidate value.
 * @return {boolean} True for a non-empty string or `{ html: string }`.
 */
export function isTranslation( value ) {
	if ( typeof value === 'string' ) {
		return value.trim() !== '';
	}
	return (
		value !== null &&
		typeof value === 'object' &&
		typeof value.html === 'string' &&
		value.html.trim() !== ''
	);
}

/* -------------------------------------------------------------------------- */
/* Placeholders                                                                */
/* -------------------------------------------------------------------------- */

/**
 * Replace `{name}` placeholders. Unknown placeholders are left untouched.
 *
 * @param {string} str  Template string.
 * @param {Object} vars Placeholder values, e.g. `{ year, siteName }`.
 * @return {string} Substituted string.
 */
export function substitute( str, vars = {} ) {
	return String( str ?? '' ).replace( PLACEHOLDER_RE, ( match, name ) => {
		if ( ! has( vars, name ) || vars[ name ] === undefined ) {
			return match;
		}
		return String( vars[ name ] ?? '' );
	} );
}

function escapeHtml( str ) {
	return String( str ).replace( /[&<>"']/g, ( ch ) => {
		switch ( ch ) {
			case '&':
				return '&amp;';
			case '<':
				return '&lt;';
			case '>':
				return '&gt;';
			case '"':
				return '&quot;';
			default:
				return '&#39;';
		}
	} );
}

/**
 * `substitute()` for either value shape. Placeholder values are HTML-escaped inside `{ html }`.
 *
 * @param {*}      value Dictionary value.
 * @param {Object} vars  Placeholder values.
 * @return {*} Substituted copy, or the input when it is neither a string nor `{ html }`.
 */
export function substituteValue( value, vars = {} ) {
	if ( typeof value === 'string' ) {
		return substitute( value, vars );
	}
	if (
		value &&
		typeof value === 'object' &&
		typeof value.html === 'string'
	) {
		const escaped = {};
		Object.keys( vars ).forEach( ( name ) => {
			escaped[ name ] =
				vars[ name ] === undefined
					? undefined
					: escapeHtml( vars[ name ] ?? '' );
		} );
		return { html: substitute( value.html, escaped ) };
	}
	return value;
}

/* -------------------------------------------------------------------------- */
/* HTML sanitiser                                                              */
/* -------------------------------------------------------------------------- */

function isSafeHref( raw ) {
	// Browsers strip tab/CR/LF from URLs before parsing, so `java\tscript:` must not slip by.
	const href = String( raw )
		.replace( /[\t\n\r]/g, '' )
		.trim();
	if ( href === '' || href.startsWith( '/' ) || href.startsWith( '#' ) ) {
		return true;
	}
	if ( ! ANY_SCHEME_RE.test( href ) ) {
		return true;
	}
	return SAFE_SCHEME_RE.test( href );
}

function isAllowedAttribute( el, name, value ) {
	if ( name === 'class' ) {
		return true;
	}
	if ( name === 'style' ) {
		return el.localName === 'mark' && MARK_STYLE_RE.test( value.trim() );
	}
	if ( el.localName === 'a' ) {
		if ( name === 'href' ) {
			return isSafeHref( value );
		}
		if ( name === 'target' || name === 'rel' ) {
			return el.hasAttribute( 'href' );
		}
	}
	return false;
}

function cleanAttributes( el ) {
	// `href` first so `target`/`rel` can depend on whether it survived.
	const names = Array.from( el.attributes, ( attr ) => attr.name ).sort(
		( a, b ) => {
			if ( a === 'href' ) {
				return -1;
			}
			return b === 'href' ? 1 : 0;
		}
	);
	names.forEach( ( name ) => {
		if ( ! isAllowedAttribute( el, name, el.getAttribute( name ) ?? '' ) ) {
			el.removeAttribute( name );
		}
	} );
}

function unwrap( el ) {
	const parent = el.parentNode;
	while ( el.firstChild ) {
		parent.insertBefore( el.firstChild, el );
	}
	el.remove();
}

function cleanChildren( parent, allowed ) {
	Array.from( parent.childNodes ).forEach( ( node ) =>
		cleanNode( node, allowed )
	);
}

function cleanNode( node, allowed ) {
	if ( node.nodeType === TEXT_NODE ) {
		return;
	}
	if ( node.nodeType !== ELEMENT_NODE ) {
		node.remove();
		return;
	}
	const tag = node.localName;
	if ( DROP_WITH_CONTENT.includes( tag ) ) {
		node.remove();
		return;
	}
	if ( ! allowed.has( tag ) ) {
		cleanChildren( node, allowed );
		unwrap( node );
		return;
	}
	cleanAttributes( node );
	cleanChildren( node, allowed );
}

/**
 * Reduce dictionary HTML to a small inline whitelist.
 *
 * Allowed tags: `br em strong b i mark span a sup sub`, plus `h3 h4 p ul li` with
 * `allowSections`. Allowed attributes: `class` on any allowed tag; `style` on `mark` only
 * when it is `color: var(--token)`; `href`/`target`/`rel` on `a` with relative, `/`, `#`,
 * http(s), mailto or tel hrefs. Disallowed elements are unwrapped (text kept);
 * `script`/`style`/`template` are removed with their contents.
 *
 * @param {string}  html                          Untrusted-ish HTML.
 * @param {Object}  [options]
 * @param {boolean} [options.allowSections=false] Also allow `h3 h4 p ul li`.
 * @return {string} Sanitised HTML.
 */
export function sanitizeHtml( html, { allowSections = false } = {} ) {
	const source = String( html ?? '' );
	if ( source.trim() === '' ) {
		return '';
	}
	const doc = new window.DOMParser().parseFromString( source, 'text/html' );
	const allowed = new Set(
		allowSections
			? [ ...ALLOWED_INLINE_TAGS, ...ALLOWED_SECTION_TAGS ]
			: ALLOWED_INLINE_TAGS
	);
	cleanChildren( doc.body, allowed );
	return doc.body.innerHTML;
}

/**
 * Whether an element may receive block-level markup (`allowSections`).
 *
 * @param {Element} target Element about to receive HTML.
 * @return {boolean} True for container elements such as `div`/`section`.
 */
export function allowsSections( target ) {
	return SECTION_HOSTS.includes( target.localName );
}

/* -------------------------------------------------------------------------- */
/* DOM targets                                                                 */
/* -------------------------------------------------------------------------- */

/**
 * Element whose content is actually replaced for a marked element.
 *
 * Core block wrappers carry the editor-assigned class, but the text lives deeper.
 *
 * @param {Element} el Marked element.
 * @return {Element} Element to translate.
 */
export function resolveTarget( el ) {
	if ( el.matches( 'div.wp-block-button' ) ) {
		return el.querySelector( '.wp-block-button__link' ) || el;
	}
	if ( el.matches( 'li.wp-block-navigation-item' ) ) {
		return el.querySelector( '.wp-block-navigation-item__label' ) || el;
	}
	return el;
}

/**
 * Depth-first first text node with visible characters, skipping `svg`/`script`/`style`
 * subtrees.
 *
 * @param {Node} el Root to search.
 * @return {Text|null} The text node, or `null`.
 */
export function firstTextNode( el ) {
	if ( ! el ) {
		return null;
	}
	for ( const child of Array.from( el.childNodes ) ) {
		if ( child.nodeType === TEXT_NODE ) {
			if ( child.nodeValue.trim() !== '' ) {
				return child;
			}
			continue;
		}
		if (
			child.nodeType === ELEMENT_NODE &&
			! SKIP_SUBTREES.includes( child.localName )
		) {
			const found = firstTextNode( child );
			if ( found ) {
				return found;
			}
		}
	}
	return null;
}

/**
 * How a value should be written into a target.
 *
 * @param {Element} target Resolved target.
 * @param {*}       value  Dictionary value.
 * @return {'text'|'first'|'html'} Replacement mode.
 */
export function inferMode( target, value ) {
	if (
		value &&
		typeof value === 'object' &&
		typeof value.html === 'string'
	) {
		return 'html';
	}
	return target.children.length ? 'first' : 'text';
}

/**
 * Text that a `first`/`text` replacement would overwrite; used as the `data-tv` lookup key.
 *
 * @param {Element} target Resolved target.
 * @return {string} Current Spanish text.
 */
export function sourceText( target ) {
	if ( target.children.length ) {
		const node = firstTextNode( target );
		return node ? node.nodeValue : '';
	}
	return target.textContent;
}

/**
 * Write a value into the target.
 *
 * `first` keeps one leading/trailing space when the original text node had one, so icon
 * spacing survives (`<svg/> Ver mapa` → `<svg/> View map`).
 *
 * @param {Element}               target Resolved target.
 * @param {string|{html: string}} value  Translation.
 * @param {'text'|'first'|'html'} mode   Replacement mode.
 */
export function applyValue( target, value, mode ) {
	const str =
		value && typeof value === 'object'
			? String( value.html ?? '' )
			: String( value ?? '' );

	if ( mode === 'html' ) {
		target.innerHTML = sanitizeHtml( str, {
			allowSections: allowsSections( target ),
		} );
		return;
	}

	if ( mode === 'first' ) {
		const node = firstTextNode( target );
		if ( ! node ) {
			target.appendChild( target.ownerDocument.createTextNode( str ) );
			return;
		}
		const original = node.nodeValue;
		const lead = /^\s/.test( original ) ? ' ' : '';
		const trail = /\s$/.test( original ) ? ' ' : '';
		node.nodeValue = lead + str + trail;
		return;
	}

	target.textContent = str;
}

/**
 * Store the original content before the first replacement. Later calls are no-ops until
 * `restoreOriginal()` runs, so the earliest Spanish state always wins.
 *
 * @param {Element}               target Resolved target.
 * @param {'text'|'first'|'html'} mode   Replacement mode about to be used.
 */
export function rememberOriginal( target, mode ) {
	if ( target.hasAttribute( ORIG_MODE_ATTR ) ) {
		return;
	}
	let original;
	if ( mode === 'html' ) {
		original = target.innerHTML;
	} else if ( mode === 'first' ) {
		const node = firstTextNode( target );
		original = node ? node.nodeValue : '';
	} else {
		original = target.textContent;
	}
	target.setAttribute( ORIG_MODE_ATTR, mode );
	target.setAttribute( ORIG_VALUE_ATTR, original );
}

/**
 * Remember an attribute's original value before translating it. An attribute that did not
 * exist is recorded as empty and removed again on restore.
 *
 * @param {Element} target Element carrying the attribute.
 * @param {string}  name   Attribute name.
 */
export function rememberAttribute( target, name ) {
	const remembered = ( target.getAttribute( ORIG_ATTRS_ATTR ) || '' )
		.split( /\s+/ )
		.filter( Boolean );
	if ( remembered.includes( name ) ) {
		return;
	}
	target.setAttribute(
		ORIG_ATTR_PREFIX + name,
		target.getAttribute( name ) ?? ''
	);
	target.setAttribute( ORIG_ATTRS_ATTR, [ ...remembered, name ].join( ' ' ) );
}

/**
 * Put translated attributes back and drop the markers.
 *
 * @param {Element} target Element to restore.
 */
export function restoreAttributes( target ) {
	const names = ( target.getAttribute( ORIG_ATTRS_ATTR ) || '' )
		.split( /\s+/ )
		.filter( Boolean );
	names.forEach( ( name ) => {
		const original = target.getAttribute( ORIG_ATTR_PREFIX + name );
		if ( original ) {
			target.setAttribute( name, original );
		} else {
			target.removeAttribute( name );
		}
		target.removeAttribute( ORIG_ATTR_PREFIX + name );
	} );
	target.removeAttribute( ORIG_ATTRS_ATTR );
}

/**
 * Put the Spanish content (and attributes) back and drop every marker, leaving the element
 * exactly as it was before `rememberOriginal()`.
 *
 * @param {Element} target Element to restore.
 */
export function restoreOriginal( target ) {
	const mode = target.getAttribute( ORIG_MODE_ATTR );
	if ( mode ) {
		const original = target.getAttribute( ORIG_VALUE_ATTR ) ?? '';
		if ( mode === 'html' ) {
			target.innerHTML = original;
		} else if ( mode === 'first' ) {
			const node = firstTextNode( target );
			if ( node && original === '' ) {
				node.remove();
			} else if ( node ) {
				node.nodeValue = original;
			}
		} else {
			target.textContent = original;
		}
		target.removeAttribute( ORIG_MODE_ATTR );
		target.removeAttribute( ORIG_VALUE_ATTR );
	}
	restoreAttributes( target );
}

/* -------------------------------------------------------------------------- */
/* Markup conventions                                                          */
/* -------------------------------------------------------------------------- */

/**
 * The key carried by the first `i18n-<kebab-key>` class, if any.
 *
 * @param {Element} el Element to inspect.
 * @return {string|null} The key without the `i18n-` prefix.
 */
export function getClassKey( el ) {
	for ( const className of Array.from( el.classList ) ) {
		const match = CLASS_KEY_RE.exec( className );
		if ( match ) {
			return match[ 1 ];
		}
	}
	return null;
}

/**
 * Whether an attribute name may be written from a dictionary (no event handlers).
 *
 * @param {string} name Attribute name.
 * @return {boolean} True when safe.
 */
export function isSafeAttrName( name ) {
	return ATTR_NAME_RE.test( name ) && ! name.startsWith( 'on' );
}

/**
 * Parse `data-i18n-attr="aria-label:common.langSwitcher;title:common.x"`.
 *
 * @param {string} spec Attribute value.
 * @return {Array<{attr: string, path: string}>} Valid `{ attr, path }` pairs, in order.
 */
export function parseAttrSpec( spec ) {
	return String( spec ?? '' )
		.split( ';' )
		.map( ( part ) => part.trim() )
		.filter( Boolean )
		.map( ( part ) => {
			const colon = part.indexOf( ':' );
			if ( colon < 1 ) {
				return null;
			}
			const attr = part.slice( 0, colon ).trim().toLowerCase();
			const path = part.slice( colon + 1 ).trim();
			if ( ! isSafeAttrName( attr ) || path === '' ) {
				return null;
			}
			return { attr, path };
		} )
		.filter( Boolean );
}
