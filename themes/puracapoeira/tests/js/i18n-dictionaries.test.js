/**
 * Structural checks for the generated i18n dictionaries
 * (`assets/i18n/{en,pt}.json` and `import/i18n/pura_profesor-*.json`).
 *
 * The files are produced by `node bin/i18n-convert.mjs` from the repository
 * root; these tests guard the schema the theme's i18n module relies on.
 */

/**
 * External dependencies
 */
const fs = require( 'fs' );
const path = require( 'path' );

const DICT_DIR = path.resolve( __dirname, '../../assets/i18n' );
const IMPORT_DIR = path.resolve( __dirname, '../../import/i18n' );
const LANGS = [ 'en', 'pt' ];

const INLINE_TAGS = [
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
const SECTION_TAGS = [ 'h3', 'h4', 'p', 'ul', 'li' ];

function readJson( filePath ) {
	return JSON.parse( fs.readFileSync( filePath, 'utf8' ) );
}

function sortedKeys( object ) {
	return Object.keys( object ).sort();
}

function isHtmlValue( value ) {
	return (
		value !== null &&
		typeof value === 'object' &&
		Object.prototype.hasOwnProperty.call( value, 'html' )
	);
}

function tagNames( html ) {
	return [ ...html.matchAll( /<\/?([a-zA-Z][a-zA-Z0-9]*)\b[^>]*>/g ) ].map(
		( match ) => match[ 1 ].toLowerCase()
	);
}

/**
 * Walks a dictionary section and yields `[ keyPath, value ]` leaves, where
 * a leaf is a string or an `{ html }` object.
 *
 * @param {Object} object Section to walk.
 * @param {string} prefix Key path prefix.
 * @return {Array} Leaves as `[ keyPath, value ]` pairs.
 */
function leaves( object, prefix = '' ) {
	const result = [];
	for ( const [ key, value ] of Object.entries( object ) ) {
		const keyPath = prefix ? `${ prefix }.${ key }` : key;
		if ( typeof value === 'string' || isHtmlValue( value ) ) {
			result.push( [ keyPath, value ] );
		} else if ( value && typeof value === 'object' ) {
			result.push( ...leaves( value, keyPath ) );
		} else {
			result.push( [ keyPath, value ] );
		}
	}
	return result;
}

/**
 * Lists the key paths whose value is not a non-empty string (or `{ html }`
 * with a non-empty string).
 *
 * @param {Object} object Section to check.
 * @return {string[]} Offending key paths.
 */
function emptyStrings( object ) {
	return leaves( object )
		.filter( ( [ , value ] ) => {
			const text = isHtmlValue( value ) ? value.html : value;
			return typeof text !== 'string' || text.trim() === '';
		} )
		.map( ( [ keyPath ] ) => keyPath );
}

/**
 * Lists the `{ html }` values that use tags outside the allowed set
 * (inline tags everywhere, plus block tags for `*-section` keys).
 *
 * @param {Object} object Section to check.
 * @return {string[]} Offending key paths with their disallowed tags.
 */
function disallowedHtml( object ) {
	const problems = [];
	for ( const [ keyPath, value ] of leaves( object ) ) {
		if ( ! isHtmlValue( value ) ) {
			continue;
		}
		const lastKey = keyPath.split( '.' ).pop();
		const allowed = lastKey.endsWith( '-section' )
			? [ ...INLINE_TAGS, ...SECTION_TAGS ]
			: INLINE_TAGS;
		const unexpected = [
			...new Set(
				tagNames( value.html ).filter(
					( tag ) => ! allowed.includes( tag )
				)
			),
		];
		if ( unexpected.length ) {
			problems.push( `${ keyPath }: ${ unexpected.join( ', ' ) }` );
		}
	}
	return problems;
}

describe( 'i18n dictionaries', () => {
	const dictionaries = {};

	beforeAll( () => {
		for ( const lang of LANGS ) {
			dictionaries[ lang ] = readJson(
				path.join( DICT_DIR, `${ lang }.json` )
			);
		}
	} );

	it.each( LANGS )( '%s.json parses and has the four sections', ( lang ) => {
		const dict = dictionaries[ lang ];
		expect( sortedKeys( dict ) ).toEqual( [
			'common',
			'pages',
			'shared',
			'values',
		] );
		for ( const section of [ 'common', 'values', 'shared', 'pages' ] ) {
			expect( Object.keys( dict[ section ] ).length ).toBeGreaterThan(
				0
			);
		}
	} );

	it( 'has identical key sets for common and shared in en and pt', () => {
		const { en, pt } = dictionaries;
		expect( sortedKeys( pt.common ) ).toEqual( sortedKeys( en.common ) );
		expect( sortedKeys( pt.shared ) ).toEqual( sortedKeys( en.shared ) );
	} );

	it( 'has the same pages with identical key sets in en and pt', () => {
		const { en, pt } = dictionaries;
		expect( sortedKeys( pt.pages ) ).toEqual( sortedKeys( en.pages ) );
		for ( const slug of Object.keys( en.pages ) ) {
			expect( sortedKeys( pt.pages[ slug ] ) ).toEqual(
				sortedKeys( en.pages[ slug ] )
			);
		}
	} );

	it.each( LANGS )( '%s.json has no empty strings', ( lang ) => {
		expect( emptyStrings( dictionaries[ lang ] ) ).toEqual( [] );
	} );

	it.each( LANGS )( '%s.json uses only allowed HTML tags', ( lang ) => {
		expect( disallowedHtml( dictionaries[ lang ] ) ).toEqual( [] );
	} );

	it.each( LANGS )(
		'%s.json has non-empty Spanish keys and string translations in values',
		( lang ) => {
			for ( const [ key, value ] of Object.entries(
				dictionaries[ lang ].values
			) ) {
				expect( key.trim() ).not.toBe( '' );
				expect( typeof value ).toBe( 'string' );
			}
		}
	);

	it.each( LANGS )( '%s.json common values are plain strings', ( lang ) => {
		for ( const value of Object.values( dictionaries[ lang ].common ) ) {
			expect( typeof value ).toBe( 'string' );
		}
	} );

	it.each( LANGS )(
		'%s.json keeps the {year} placeholder in footer-rights',
		( lang ) => {
			expect( dictionaries[ lang ].shared[ 'footer-rights' ] ).toContain(
				'{year}'
			);
		}
	);
} );

describe( 'per-teacher i18n files', () => {
	const files = fs
		.readdirSync( IMPORT_DIR )
		.filter( ( name ) => /^pura_profesor-.*\.json$/.test( name ) )
		.sort();

	it( 'exist for the teacher profiles', () => {
		expect( files.length ).toBeGreaterThan( 0 );
	} );

	it.each( files )( '%s parses with matching en / pt keys', ( name ) => {
		const data = readJson( path.join( IMPORT_DIR, name ) );
		const languages = sortedKeys( data );
		expect( languages.length ).toBeGreaterThan( 0 );
		for ( const lang of languages ) {
			expect( LANGS ).toContain( lang );
		}
		// Every language present must expose the same key set.
		const keySets = languages.map( ( lang ) => sortedKeys( data[ lang ] ) );
		for ( const keySet of keySets ) {
			expect( keySet ).toEqual( keySets[ 0 ] );
		}
	} );

	it.each( files )( '%s has no empty strings', ( name ) => {
		const data = readJson( path.join( IMPORT_DIR, name ) );
		expect( emptyStrings( data ) ).toEqual( [] );
	} );

	it.each( files )( '%s uses only allowed HTML tags', ( name ) => {
		const data = readJson( path.join( IMPORT_DIR, name ) );
		expect( disallowedHtml( data ) ).toEqual( [] );
	} );
} );
