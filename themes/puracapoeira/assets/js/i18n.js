/**
 * Pura Capoeira — client-side language switcher.
 *
 * Spanish is server-rendered; English and Portuguese are applied in the browser from JSON
 * dictionaries. This module runs as a deferred script module, so the DOM is parsed when it
 * starts. The inline bootstrap printed by inc/i18n.php has already chosen the language and,
 * for en/pt, started the dictionary download as `window.puraI18nConfig.pending`.
 *
 * Public API (available synchronously as `window.puraI18n`):
 *
 *   lang            current code (`es` | `en` | `pt`)
 *   loaded          true once the initial language has been applied
 *   ready           Promise resolved with the code after that first apply
 *   t( path )       dotted lookup in the dictionary, e.g. `common.langSwitcher`
 *   tv( spanish )   value lookup (`values` section) by Spanish text
 *   key( k )        scoped lookup: post meta → pages[slug] → shared
 *   setLang( code ) switch language (persists, loads, applies, dispatches)
 *   apply( root )   (re)translate a subtree, e.g. after inserting dynamic content
 *   on( fn )        subscribe to language changes; returns an unsubscribe function
 *
 * Events on `document`: `pura:langchange` ({ lang, previous, initial }) and `pura:i18n-ready`.
 *
 * Markup conventions (see core.js for the lookup rules):
 *   class="i18n-<kebab-key>"      scoped key
 *   data-i18n="common.x"          dotted dictionary path
 *   data-i18n-key="<kebab-key>"   scoped key (when a class is not practical)
 *   data-tv[="Texto"]             translate the element's Spanish text through `values`
 *   data-i18n-attr="attr:path;…"  set attributes from dotted paths
 *   data-tv-attr="alt title"      translate attribute values through `values`
 *   data-i18n-skip                leave this subtree alone
 */

import {
	ORIG_ATTRS_ATTR,
	ORIG_MODE_ATTR,
	applyValue,
	buildValueIndex,
	getClassKey,
	getPath,
	inferMode,
	isSafeAttrName,
	isTranslation,
	lookupScoped,
	normalizeValueKey,
	parseAttrSpec,
	pickLang,
	rememberAttribute,
	rememberOriginal,
	resolveTarget,
	restoreOriginal,
	sourceText,
	substituteValue,
} from './i18n/core.js';

const config = window.puraI18nConfig || {};
const LANGS = Array.isArray( config.langs )
	? config.langs
	: [ 'es', 'en', 'pt' ];
const DEFAULT_LANG = config.defaultLang || 'es';
const STORAGE_KEY = config.storageKey || 'pc_lang';
const SCOPE = config.scope || { type: 'other', slug: '' };
const DICT_URLS = config.dict || {};

const SELECTOR =
	'[class*="i18n-"], [data-i18n], [data-i18n-key], [data-tv], [data-i18n-attr], [data-tv-attr]';
const RESTORE_SELECTOR = `[${ ORIG_MODE_ATTR }], [${ ORIG_ATTRS_ATTR }]`;
const BUTTON_SELECTOR = '.lang-btn[data-lang]';

/* -------------------------------------------------------------------------- */
/* State                                                                       */
/* -------------------------------------------------------------------------- */

const dicts = {}; // code → dictionary
const indexes = {}; // code → Map( normalised Spanish → translation )
const loading = {}; // code → Promise
const listeners = new Set();
let postDict; // parsed `#pura-i18n-post`, lazily
let sequence = 0;
let resolveReady;

const ready = new Promise( ( resolve ) => {
	resolveReady = resolve;
} );

/* -------------------------------------------------------------------------- */
/* Storage and URL                                                             */
/* -------------------------------------------------------------------------- */

function readSaved() {
	try {
		return window.localStorage.getItem( STORAGE_KEY );
	} catch ( err ) {
		return null; // Storage unavailable (private mode, blocked, quota).
	}
}

function persist( code ) {
	try {
		window.localStorage.setItem( STORAGE_KEY, code );
	} catch ( err ) {
		// Storage unavailable; the choice still applies to this page view.
	}
}

function stripLangParam() {
	try {
		const url = new URL( window.location.href );
		if ( ! url.searchParams.has( 'lang' ) ) {
			return;
		}
		url.searchParams.delete( 'lang' );
		window.history.replaceState( window.history.state, '', url.toString() );
	} catch ( err ) {
		// Leave the URL alone if history is unavailable.
	}
}

/* -------------------------------------------------------------------------- */
/* Dictionaries                                                                */
/* -------------------------------------------------------------------------- */

function fetchDict( code ) {
	const url = DICT_URLS[ code ];
	if ( ! url ) {
		return Promise.reject(
			new Error( `No dictionary URL for "${ code }"` )
		);
	}
	return window
		.fetch( url, { credentials: 'same-origin' } )
		.then( ( response ) => {
			if ( ! response.ok ) {
				throw new Error( `HTTP ${ response.status } for ${ url }` );
			}
			return response.json();
		} );
}

function loadDict( code ) {
	if ( dicts[ code ] ) {
		return Promise.resolve( dicts[ code ] );
	}
	if ( ! loading[ code ] ) {
		let promise;
		if ( config.pending && config.initialLang === code ) {
			promise = config.pending; // Started in <head>; reuse it once.
			config.pending = null;
		} else {
			promise = fetchDict( code );
		}
		loading[ code ] = promise
			.then( ( dict ) => {
				if ( ! dict || typeof dict !== 'object' ) {
					throw new Error(
						`Dictionary for "${ code }" is not an object`
					);
				}
				dicts[ code ] = dict;
				indexes[ code ] = buildValueIndex( dict.values );
				return dict;
			} )
			.catch( ( err ) => {
				delete loading[ code ];
				throw err;
			} );
	}
	return loading[ code ];
}

function getPostDict() {
	if ( postDict === undefined ) {
		postDict = {};
		const el = document.getElementById( 'pura-i18n-post' );
		if ( el ) {
			try {
				const parsed = JSON.parse( el.textContent );
				if ( parsed && typeof parsed === 'object' ) {
					postDict = parsed;
				}
			} catch ( err ) {
				postDict = {};
			}
		}
	}
	return postDict;
}

function currentDict() {
	return api.lang === DEFAULT_LANG ? undefined : dicts[ api.lang ];
}

function currentIndex() {
	return api.lang === DEFAULT_LANG ? undefined : indexes[ api.lang ];
}

function currentPostDict() {
	const all = getPostDict();
	return all && typeof all[ api.lang ] === 'object'
		? all[ api.lang ]
		: undefined;
}

function placeholderVars() {
	return {
		year: String( new Date().getFullYear() ),
		siteName: config.siteName || '',
	};
}

/* -------------------------------------------------------------------------- */
/* Applying translations                                                       */
/* -------------------------------------------------------------------------- */

function collect( root, selector ) {
	const nodes = Array.from( root.querySelectorAll( selector ) );
	if ( root.nodeType === 1 && root.matches( selector ) ) {
		nodes.unshift( root );
	}
	return nodes;
}

function restoreAll( root ) {
	collect( root, RESTORE_SELECTOR ).forEach( restoreOriginal );
}

function resolveContentValue( el, dict, index, post ) {
	const classKey = getClassKey( el );
	if ( classKey ) {
		return lookupScoped( dict, post, SCOPE, classKey );
	}
	if ( el.hasAttribute( 'data-i18n' ) ) {
		return getPath( dict, el.getAttribute( 'data-i18n' ) );
	}
	if ( el.hasAttribute( 'data-i18n-key' ) ) {
		return lookupScoped(
			dict,
			post,
			SCOPE,
			el.getAttribute( 'data-i18n-key' )
		);
	}
	if ( el.hasAttribute( 'data-tv' ) ) {
		const explicit = ( el.getAttribute( 'data-tv' ) || '' ).trim();
		const source = explicit || sourceText( resolveTarget( el ) );
		return index.get( normalizeValueKey( source ) );
	}
	return undefined;
}

function translateContent( el, dict, index, post, vars ) {
	const value = substituteValue(
		resolveContentValue( el, dict, index, post ),
		vars
	);
	if ( ! isTranslation( value ) ) {
		return;
	}
	const target = resolveTarget( el );
	const mode = inferMode( target, value );
	rememberOriginal( target, mode );
	applyValue( target, value, mode );
}

function setTranslatedAttribute( el, attr, value ) {
	if ( typeof value !== 'string' || value.trim() === '' ) {
		return;
	}
	rememberAttribute( el, attr );
	el.setAttribute( attr, value );
}

function translateAttributes( el, dict, index, vars ) {
	if ( el.hasAttribute( 'data-i18n-attr' ) ) {
		parseAttrSpec( el.getAttribute( 'data-i18n-attr' ) ).forEach(
			( { attr, path } ) => {
				const value = substituteValue( getPath( dict, path ), vars );
				setTranslatedAttribute( el, attr, value );
			}
		);
	}
	if ( el.hasAttribute( 'data-tv-attr' ) ) {
		( el.getAttribute( 'data-tv-attr' ) || '' )
			.split( /[\s,;]+/ )
			.map( ( name ) => name.trim().toLowerCase() )
			.filter( ( name ) => name && isSafeAttrName( name ) )
			.forEach( ( attr ) => {
				const current = el.getAttribute( attr );
				if ( current === null ) {
					return;
				}
				const value = index.get( normalizeValueKey( current ) );
				setTranslatedAttribute( el, attr, value );
			} );
	}
}

function translateAll( root, dict, index ) {
	const vars = placeholderVars();
	const post = currentPostDict();
	collect( root, SELECTOR ).forEach( ( el ) => {
		if ( el.closest( '[data-i18n-skip]' ) ) {
			return;
		}
		translateContent( el, dict, index, post, vars );
		translateAttributes( el, dict, index, vars );
	} );
}

function syncButtons( root ) {
	collect( root, BUTTON_SELECTOR ).forEach( ( btn ) => {
		const active = btn.dataset.lang === api.lang;
		btn.classList.toggle( 'active', active );
		btn.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
	} );
}

function setDocumentLang( code ) {
	document.documentElement.lang =
		code === DEFAULT_LANG ? config.serverLang || DEFAULT_LANG : code;
}

/* -------------------------------------------------------------------------- */
/* Public API                                                                  */
/* -------------------------------------------------------------------------- */

/**
 * Translate a subtree: restore remembered originals under `root`, then apply the current
 * language. Safe to call repeatedly and on dynamically inserted content.
 *
 * @param {Document|Element} [root=document] Subtree root.
 */
function apply( root = document ) {
	const scopeRoot =
		root && typeof root.querySelectorAll === 'function' ? root : document;
	restoreAll( scopeRoot );
	const dict = currentDict();
	if ( dict ) {
		translateAll( scopeRoot, dict, currentIndex() || new Map() );
	}
	syncButtons( scopeRoot );
}

function notify( previous, initial ) {
	const detail = { lang: api.lang, previous, initial };
	document.dispatchEvent(
		new window.CustomEvent( 'pura:langchange', { detail } )
	);
	listeners.forEach( ( fn ) => {
		try {
			fn( detail );
		} catch ( err ) {
			// eslint-disable-next-line no-console
			console.error( '[pura-i18n] listener failed', err );
		}
	} );
}

function markReady() {
	if ( api.loaded ) {
		return;
	}
	api.loaded = true;
	resolveReady( api.lang );
	document.dispatchEvent(
		new window.CustomEvent( 'pura:i18n-ready', {
			detail: { lang: api.lang },
		} )
	);
}

/**
 * Switch the page language.
 *
 * @param {string}  code              `es` | `en` | `pt`.
 * @param {Object}  [options]
 * @param {boolean} [options.initial] Internal: the automatic apply on page load.
 * @return {Promise<string>} The language in effect afterwards.
 */
async function setLang( code, { initial = false } = {} ) {
	const next = String( code ?? '' )
		.trim()
		.toLowerCase();
	if ( ! LANGS.includes( next ) ) {
		return api.lang;
	}

	persist( next );

	if ( ! initial && next === api.lang ) {
		return api.lang;
	}

	const previous = api.lang;
	const mySequence = ++sequence;
	let effective = next;

	if ( next !== DEFAULT_LANG ) {
		try {
			await loadDict( next );
		} catch ( err ) {
			// eslint-disable-next-line no-console
			console.warn(
				`[pura-i18n] Could not load the "${ next }" dictionary; staying in Spanish.`,
				err
			);
			effective = DEFAULT_LANG;
		}
		if ( mySequence !== sequence ) {
			return api.lang; // A later setLang() superseded this one.
		}
	}

	api.lang = effective;
	setDocumentLang( effective );
	apply();
	notify( previous, initial );
	markReady();
	return api.lang;
}

function t( path ) {
	const dict = currentDict();
	if ( ! dict ) {
		return undefined;
	}
	return substituteValue( getPath( dict, path ), placeholderVars() );
}

function tv( value ) {
	const index = currentIndex();
	if ( ! index ) {
		return undefined;
	}
	return index.get( normalizeValueKey( value ) );
}

function key( scopedKey ) {
	const dict = currentDict();
	if ( ! dict ) {
		return undefined;
	}
	return substituteValue(
		lookupScoped( dict, currentPostDict(), SCOPE, scopedKey ),
		placeholderVars()
	);
}

function on( fn ) {
	if ( typeof fn !== 'function' ) {
		return () => {};
	}
	listeners.add( fn );
	return () => listeners.delete( fn );
}

/* -------------------------------------------------------------------------- */
/* Boot                                                                        */
/* -------------------------------------------------------------------------- */

const query = new URLSearchParams( window.location.search ).get( 'lang' );
const initialLang = pickLang( {
	query,
	saved: readSaved(),
	navigatorLang: window.navigator.language,
	langs: LANGS,
	defaultLang: DEFAULT_LANG,
} );

if ( query !== null ) {
	if ( query.trim().toLowerCase() === initialLang ) {
		persist( initialLang );
	}
	stripLangParam();
}

const api = {
	lang: DEFAULT_LANG,
	loaded: false,
	ready,
	t,
	tv,
	key,
	setLang,
	apply,
	on,
};
window.puraI18n = api;

document.addEventListener( 'click', ( event ) => {
	const origin = event.target;
	if ( ! origin || typeof origin.closest !== 'function' ) {
		return;
	}
	const btn = origin.closest( BUTTON_SELECTOR );
	if ( ! btn ) {
		return;
	}
	event.preventDefault();
	setLang( btn.dataset.lang );
} );

if ( initialLang !== DEFAULT_LANG ) {
	setLang( initialLang, { initial: true } );
} else {
	syncButtons( document );
	markReady();
}
