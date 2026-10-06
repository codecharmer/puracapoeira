import {
	applyValue,
	buildValueIndex,
	firstTextNode,
	getClassKey,
	getPath,
	inferMode,
	isTranslation,
	lookupScoped,
	normalizeLang,
	normalizeValueKey,
	parseAttrSpec,
	pickLang,
	rememberAttribute,
	rememberOriginal,
	resolveTarget,
	restoreAttributes,
	restoreOriginal,
	sanitizeHtml,
	sourceText,
	substitute,
	substituteValue,
} from '../../assets/js/i18n/core.js';

function mount( html ) {
	document.body.innerHTML = html;
	return document.body.firstElementChild;
}

describe( 'normalizeLang', () => {
	it( 'maps BCP 47 tags to supported codes', () => {
		expect( normalizeLang( 'en-US' ) ).toBe( 'en' );
		expect( normalizeLang( 'EN' ) ).toBe( 'en' );
		expect( normalizeLang( 'pt-BR' ) ).toBe( 'pt' );
		expect( normalizeLang( 'fr' ) ).toBe( 'es' );
		expect( normalizeLang( 'es-MX' ) ).toBe( 'es' );
	} );

	it( 'falls back to the default for empty input', () => {
		expect( normalizeLang( '' ) ).toBe( 'es' );
		expect( normalizeLang( undefined ) ).toBe( 'es' );
		expect( normalizeLang( null, 'pt' ) ).toBe( 'pt' );
	} );
} );

describe( 'pickLang', () => {
	it( 'prefers the query parameter', () => {
		expect(
			pickLang( { query: 'pt', saved: 'en', navigatorLang: 'en-US' } )
		).toBe( 'pt' );
	} );

	it( 'is case-insensitive for query and saved values', () => {
		expect( pickLang( { query: 'EN' } ) ).toBe( 'en' );
		expect( pickLang( { saved: ' Pt ' } ) ).toBe( 'pt' );
	} );

	it( 'falls back to the saved value when the query is invalid', () => {
		expect(
			pickLang( { query: 'fr', saved: 'en', navigatorLang: 'pt-BR' } )
		).toBe( 'en' );
	} );

	it( 'falls back to the browser language when nothing is saved', () => {
		expect( pickLang( { saved: 'xx', navigatorLang: 'pt-BR' } ) ).toBe(
			'pt'
		);
		expect( pickLang( { navigatorLang: 'fr-FR' } ) ).toBe( 'es' );
	} );

	it( 'returns the default when no hint is usable', () => {
		expect( pickLang() ).toBe( 'es' );
		expect( pickLang( { query: null, saved: null } ) ).toBe( 'es' );
	} );

	it( 'honours a custom language list', () => {
		expect(
			pickLang( {
				navigatorLang: 'pt-BR',
				langs: [ 'es', 'en' ],
				defaultLang: 'es',
			} )
		).toBe( 'es' );
	} );
} );

describe( 'getPath', () => {
	const dict = {
		common: { langSwitcher: 'Language switcher', nested: { a: 1 } },
	};

	it( 'walks dotted paths', () => {
		expect( getPath( dict, 'common.langSwitcher' ) ).toBe(
			'Language switcher'
		);
		expect( getPath( dict, 'common.nested.a' ) ).toBe( 1 );
		expect( getPath( dict, 'common' ) ).toBe( dict.common );
	} );

	it( 'returns undefined for missing segments or bad input', () => {
		expect( getPath( dict, 'common.missing' ) ).toBeUndefined();
		expect( getPath( dict, 'nope.x' ) ).toBeUndefined();
		expect( getPath( dict, '' ) ).toBeUndefined();
		expect( getPath( undefined, 'common' ) ).toBeUndefined();
		expect( getPath( dict, 'common.langSwitcher.length' ) ).toBeUndefined();
	} );
} );

describe( 'normalizeValueKey', () => {
	it( 'ignores accents, case and whitespace', () => {
		expect( normalizeValueKey( 'México' ) ).toBe(
			normalizeValueKey( 'Mexico' )
		);
		expect( normalizeValueKey( '  Música   y   Clases ' ) ).toBe(
			'musica y clases'
		);
		expect( normalizeValueKey( 'Todas\n las sedes' ) ).toBe(
			'todas las sedes'
		);
	} );

	it( 'handles empty input', () => {
		expect( normalizeValueKey( '' ) ).toBe( '' );
		expect( normalizeValueKey( undefined ) ).toBe( '' );
	} );
} );

describe( 'buildValueIndex', () => {
	it( 'indexes by normalised Spanish key', () => {
		const index = buildValueIndex( {
			México: 'Mexico',
			Clases: 'Classes',
			'Todas las sedes': 'All locations',
		} );
		expect( index ).toBeInstanceOf( Map );
		expect( index.get( normalizeValueKey( 'mexico' ) ) ).toBe( 'Mexico' );
		expect( index.get( normalizeValueKey( 'CLASES' ) ) ).toBe( 'Classes' );
		expect( index.get( normalizeValueKey( ' todas  las sedes ' ) ) ).toBe(
			'All locations'
		);
		expect( index.get( 'nope' ) ).toBeUndefined();
	} );

	it( 'tolerates a missing values section', () => {
		expect( buildValueIndex( undefined ).size ).toBe( 0 );
		expect( buildValueIndex( null ).size ).toBe( 0 );
	} );
} );

describe( 'lookupScoped', () => {
	const dict = {
		shared: { 'cta-title': 'Shared title', 'only-shared': 'Only shared' },
		pages: {
			inicio: { 'cta-title': 'Home title', 'only-page': 'Only page' },
		},
	};
	const post = { 'cta-title': 'Post title' };

	it( 'prefers the post dictionary', () => {
		expect(
			lookupScoped(
				dict,
				post,
				{ type: 'page', slug: 'inicio' },
				'cta-title'
			)
		).toBe( 'Post title' );
	} );

	it( 'falls back to the page section, then shared', () => {
		const scope = { type: 'page', slug: 'inicio' };
		expect( lookupScoped( dict, undefined, scope, 'cta-title' ) ).toBe(
			'Home title'
		);
		expect( lookupScoped( dict, {}, scope, 'only-page' ) ).toBe(
			'Only page'
		);
		expect( lookupScoped( dict, {}, scope, 'only-shared' ) ).toBe(
			'Only shared'
		);
	} );

	it( 'skips the pages section outside page scope', () => {
		expect(
			lookupScoped(
				dict,
				undefined,
				{ type: 'pura_sede', slug: 'inicio' },
				'only-page'
			)
		).toBeUndefined();
		expect(
			lookupScoped(
				dict,
				undefined,
				{ type: 'pura_sede', slug: 'inicio' },
				'cta-title'
			)
		).toBe( 'Shared title' );
	} );

	it( 'returns undefined for unknown keys and missing dictionaries', () => {
		expect(
			lookupScoped( dict, undefined, { type: 'other', slug: '' }, 'nope' )
		).toBeUndefined();
		expect(
			lookupScoped( undefined, undefined, undefined, 'cta-title' )
		).toBeUndefined();
		expect( lookupScoped( dict, post, undefined, '' ) ).toBeUndefined();
	} );

	it( 'passes `{ html }` values through untouched', () => {
		const html = { html: '<strong>Hi</strong>' };
		expect(
			lookupScoped(
				{ shared: { intro: html } },
				undefined,
				undefined,
				'intro'
			)
		).toBe( html );
	} );
} );

describe( 'substitute', () => {
	it( 'replaces known placeholders and keeps unknown ones', () => {
		expect(
			substitute( '© {year} {siteName} · {unknown}', {
				year: 2026,
				siteName: 'Pura Capoeira',
			} )
		).toBe( '© 2026 Pura Capoeira · {unknown}' );
	} );

	it( 'leaves strings without placeholders alone', () => {
		expect( substitute( 'Hello', { year: 2026 } ) ).toBe( 'Hello' );
		expect( substitute( undefined ) ).toBe( '' );
	} );

	it( 'escapes placeholder values inside html values', () => {
		expect(
			substituteValue(
				{ html: '<em>{siteName}</em>' },
				{ siteName: 'A & <B>' }
			)
		).toEqual( { html: '<em>A &amp; &lt;B&gt;</em>' } );
		expect( substituteValue( '{siteName}', { siteName: 'A & B' } ) ).toBe(
			'A & B'
		);
		expect( substituteValue( 42, {} ) ).toBe( 42 );
	} );
} );

describe( 'isTranslation', () => {
	it( 'accepts non-empty strings and html objects only', () => {
		expect( isTranslation( 'Hola' ) ).toBe( true );
		expect( isTranslation( { html: '<b>x</b>' } ) ).toBe( true );
		expect( isTranslation( '' ) ).toBe( false );
		expect( isTranslation( '   ' ) ).toBe( false );
		expect( isTranslation( { html: '' } ) ).toBe( false );
		expect( isTranslation( undefined ) ).toBe( false );
		expect( isTranslation( 3 ) ).toBe( false );
	} );
} );

describe( 'sanitizeHtml', () => {
	it( 'drops scripts with their content and event handlers', () => {
		expect(
			sanitizeHtml(
				'Hi <script>alert(1)</script><strong onclick="x()">there</strong>'
			)
		).toBe( 'Hi <strong>there</strong>' );
		expect(
			sanitizeHtml( '<style>b{}</style><template>x</template>ok' )
		).toBe( 'ok' );
	} );

	it( 'drops style except the colour token on mark', () => {
		expect( sanitizeHtml( '<span style="color:red">a</span>' ) ).toBe(
			'<span>a</span>'
		);
		expect(
			sanitizeHtml(
				'<mark class="hl" style="color:var(--green-deep)">a</mark>'
			)
		).toBe( '<mark class="hl" style="color:var(--green-deep)">a</mark>' );
		expect(
			sanitizeHtml( '<mark style="color: var(--x); ">a</mark>' )
		).toBe( '<mark style="color: var(--x); ">a</mark>' );
		expect( sanitizeHtml( '<mark style="color:red">a</mark>' ) ).toBe(
			'<mark>a</mark>'
		);
		expect(
			sanitizeHtml(
				'<mark style="color:var(--x);background:url(x)">a</mark>'
			)
		).toBe( '<mark>a</mark>' );
	} );

	it( 'keeps inline formatting and safe links', () => {
		expect(
			sanitizeHtml( 'a<br>b <em>c</em> <b>d</b> <i>e</i> x<sup>2</sup>' )
		).toBe( 'a<br>b <em>c</em> <b>d</b> <i>e</i> x<sup>2</sup>' );
		expect( sanitizeHtml( '<a href="/sedes/" class="x">Sedes</a>' ) ).toBe(
			'<a href="/sedes/" class="x">Sedes</a>'
		);
		expect( sanitizeHtml( '<a href="#map">Map</a>' ) ).toBe(
			'<a href="#map">Map</a>'
		);
		expect( sanitizeHtml( '<a href="contacto">C</a>' ) ).toBe(
			'<a href="contacto">C</a>'
		);
		expect(
			sanitizeHtml(
				'<a href="https://example.com" target="_blank" rel="noopener">E</a>'
			)
		).toBe(
			'<a href="https://example.com" target="_blank" rel="noopener">E</a>'
		);
		expect(
			sanitizeHtml(
				'<a href="mailto:a@b.c">m</a><a href="tel:+52">t</a>'
			)
		).toBe( '<a href="mailto:a@b.c">m</a><a href="tel:+52">t</a>' );
	} );

	it( 'drops unsafe hrefs (and target/rel with them)', () => {
		expect( sanitizeHtml( '<a href="javascript:alert(1)">x</a>' ) ).toBe(
			'<a>x</a>'
		);
		expect(
			sanitizeHtml(
				'<a href="JavaScript:alert(1)" target="_blank">x</a>'
			)
		).toBe( '<a>x</a>' );
		expect(
			sanitizeHtml( '<a href="java&#9;script:alert(1)">x</a>' )
		).toBe( '<a>x</a>' );
		expect( sanitizeHtml( '<a href="data:text/html,x">x</a>' ) ).toBe(
			'<a>x</a>'
		);
		expect( sanitizeHtml( '<a target="_blank">x</a>' ) ).toBe( '<a>x</a>' );
	} );

	it( 'unwraps disallowed elements but keeps their text', () => {
		expect(
			sanitizeHtml( '<div class="x">Hola <span>mundo</span></div>' )
		).toBe( 'Hola <span>mundo</span>' );
		expect( sanitizeHtml( '<h3>Title</h3><p>Body</p>' ) ).toBe(
			'TitleBody'
		);
		expect( sanitizeHtml( '<img src="x.png" alt="x">text' ) ).toBe(
			'text'
		);
		expect( sanitizeHtml( '<!-- c -->text' ) ).toBe( 'text' );
	} );

	it( 'allows section tags only when asked', () => {
		const html =
			'<h3 class="t">Title</h3><p>Body</p><ul><li>One</li></ul><h2>no</h2>';
		expect( sanitizeHtml( html, { allowSections: true } ) ).toBe(
			'<h3 class="t">Title</h3><p>Body</p><ul><li>One</li></ul>no'
		);
	} );

	it( 'handles empty input', () => {
		expect( sanitizeHtml( '' ) ).toBe( '' );
		expect( sanitizeHtml( undefined ) ).toBe( '' );
	} );
} );

describe( 'resolveTarget', () => {
	it( 'resolves a core button wrapper to its link', () => {
		const el = mount(
			'<div class="wp-block-button i18n-cta"><a class="wp-block-button__link">Ver</a></div>'
		);
		expect( resolveTarget( el ) ).toBe( el.querySelector( 'a' ) );
	} );

	it( 'resolves a navigation item to its label', () => {
		const el = mount(
			'<li class="wp-block-navigation-item i18n-nav-home"><a href="/"><span class="wp-block-navigation-item__label">Inicio</span></a></li>'
		);
		expect( resolveTarget( el ).textContent ).toBe( 'Inicio' );
		expect(
			resolveTarget( el ).classList.contains(
				'wp-block-navigation-item__label'
			)
		).toBe( true );
	} );

	it( 'returns the element itself otherwise, or when the inner part is missing', () => {
		const plain = mount( '<p class="i18n-x">Hola</p>' );
		expect( resolveTarget( plain ) ).toBe( plain );
		const emptyButton = mount( '<div class="wp-block-button"></div>' );
		expect( resolveTarget( emptyButton ) ).toBe( emptyButton );
	} );
} );

describe( 'firstTextNode', () => {
	it( 'skips whitespace, svg and void siblings', () => {
		const el = mount(
			'<button>\n  <svg viewBox="0 0 1 1"><title>Icon</title></svg> Ver mapa <input type="hidden"></button>'
		);
		const node = firstTextNode( el );
		expect( node ).not.toBeNull();
		expect( node.nodeValue ).toBe( ' Ver mapa ' );
	} );

	it( 'descends into child elements', () => {
		const el = mount(
			'<a><span class="icon"></span><span>Texto</span></a>'
		);
		expect( firstTextNode( el ).nodeValue ).toBe( 'Texto' );
	} );

	it( 'returns null when there is no visible text', () => {
		expect(
			firstTextNode( mount( '<a>  <svg><title>x</title></svg></a>' ) )
		).toBeNull();
		expect( firstTextNode( null ) ).toBeNull();
	} );
} );

describe( 'inferMode and sourceText', () => {
	it( 'picks html for html values, text for leaf elements and first otherwise', () => {
		const leaf = mount( '<p>Hola</p>' );
		const mixed = mount( '<a><svg></svg> Ver mapa</a>' );
		expect( inferMode( leaf, { html: '<b>x</b>' } ) ).toBe( 'html' );
		expect( inferMode( leaf, 'Hello' ) ).toBe( 'text' );
		expect( inferMode( mixed, 'Hello' ) ).toBe( 'first' );
		expect( sourceText( leaf ) ).toBe( 'Hola' );
		expect( sourceText( mixed ) ).toBe( ' Ver mapa' );
	} );
} );

describe( 'apply / restore round trip', () => {
	it( 'text mode', () => {
		const el = mount( '<p class="i18n-intro">Hola &amp; adiós</p>' );
		const original = el.innerHTML;
		rememberOriginal( el, 'text' );
		applyValue( el, 'Hello & goodbye', 'text' );
		expect( el.textContent ).toBe( 'Hello & goodbye' );
		expect( el.getAttribute( 'data-pc-orig-mode' ) ).toBe( 'text' );
		restoreOriginal( el );
		expect( el.innerHTML ).toBe( original );
		expect( el.hasAttribute( 'data-pc-orig-mode' ) ).toBe( false );
		expect( el.hasAttribute( 'data-pc-orig' ) ).toBe( false );
	} );

	it( 'first mode preserves surrounding spaces and siblings', () => {
		const el = mount(
			'<a class="btn"><svg viewBox="0 0 1 1"><path d="M0 0"></path></svg> Ver mapa <span class="arrow">→</span></a>'
		);
		const original = el.innerHTML;
		rememberOriginal( el, 'first' );
		applyValue( el, 'View map', 'first' );
		expect( el.innerHTML ).toBe(
			'<svg viewBox="0 0 1 1"><path d="M0 0"></path></svg> View map <span class="arrow">→</span>'
		);
		restoreOriginal( el );
		expect( el.innerHTML ).toBe( original );
		expect( el.outerHTML ).not.toMatch( /data-pc-orig/ );
	} );

	it( 'first mode appends text when none existed and removes it on restore', () => {
		const el = mount( '<a class="btn"><svg></svg></a>' );
		const original = el.innerHTML;
		rememberOriginal( el, 'first' );
		applyValue( el, 'Map', 'first' );
		expect( el.textContent ).toBe( 'Map' );
		restoreOriginal( el );
		expect( el.innerHTML ).toBe( original );
	} );

	it( 'html mode sanitises and restores the exact markup', () => {
		const el = mount(
			'<div class="i18n-body"><p>Uno <b>dos</b></p><ul><li>tres</li></ul></div>'
		);
		const original = el.innerHTML;
		rememberOriginal( el, 'html' );
		applyValue(
			el,
			{
				html: '<p>One <b>two</b></p><ul><li>three</li></ul><script>x()</script>',
			},
			'html'
		);
		expect( el.innerHTML ).toBe(
			'<p>One <b>two</b></p><ul><li>three</li></ul>'
		);
		restoreOriginal( el );
		expect( el.innerHTML ).toBe( original );
	} );

	it( 'html mode on an inline element unwraps block tags', () => {
		const el = mount( '<p class="i18n-x">Hola</p>' );
		rememberOriginal( el, 'html' );
		applyValue(
			el,
			{ html: '<h3>Hi</h3> <strong>there</strong>' },
			'html'
		);
		expect( el.innerHTML ).toBe( 'Hi <strong>there</strong>' );
		restoreOriginal( el );
		expect( el.innerHTML ).toBe( 'Hola' );
	} );

	it( 'keeps the earliest original across repeated applies', () => {
		const el = mount( '<p>Hola</p>' );
		rememberOriginal( el, 'text' );
		applyValue( el, 'Hello', 'text' );
		rememberOriginal( el, 'text' );
		applyValue( el, 'Olá', 'text' );
		expect( el.getAttribute( 'data-pc-orig' ) ).toBe( 'Hola' );
		restoreOriginal( el );
		expect( el.textContent ).toBe( 'Hola' );
	} );

	it( 'restores attributes, removing ones that did not exist', () => {
		const el = mount( '<img src="x.png" alt="Foto de México">' );
		rememberAttribute( el, 'alt' );
		el.setAttribute( 'alt', 'Photo of Mexico' );
		rememberAttribute( el, 'title' );
		el.setAttribute( 'title', 'Added' );
		rememberAttribute( el, 'alt' ); // no-op: already remembered
		expect( el.getAttribute( 'data-pc-orig-attr-alt' ) ).toBe(
			'Foto de México'
		);
		expect( el.getAttribute( 'data-pc-orig-attrs' ) ).toBe( 'alt title' );
		restoreAttributes( el );
		expect( el.getAttribute( 'alt' ) ).toBe( 'Foto de México' );
		expect( el.hasAttribute( 'title' ) ).toBe( false );
		expect( el.outerHTML ).toBe( '<img src="x.png" alt="Foto de México">' );
	} );

	it( 'restoreOriginal also restores attributes and is a no-op on clean elements', () => {
		const el = mount( '<div aria-label="Selector de idioma">x</div>' );
		rememberAttribute( el, 'aria-label' );
		el.setAttribute( 'aria-label', 'Language switcher' );
		restoreOriginal( el );
		expect( el.getAttribute( 'aria-label' ) ).toBe( 'Selector de idioma' );
		const before = el.outerHTML;
		restoreOriginal( el );
		expect( el.outerHTML ).toBe( before );
	} );
} );

describe( 'markup conventions', () => {
	it( 'parseAttrSpec splits pairs and drops invalid ones', () => {
		expect(
			parseAttrSpec(
				'aria-label:common.langSwitcher; title : common.x ;;'
			)
		).toEqual( [
			{ attr: 'aria-label', path: 'common.langSwitcher' },
			{ attr: 'title', path: 'common.x' },
		] );
		expect(
			parseAttrSpec( 'onclick:common.x;nocolon;:common.y;title:' )
		).toEqual( [] );
		expect( parseAttrSpec( '' ) ).toEqual( [] );
		expect( parseAttrSpec( undefined ) ).toEqual( [] );
	} );

	it( 'getClassKey reads the first i18n-* class only', () => {
		expect(
			getClassKey(
				mount( '<p class="lead i18n-hero-title other">x</p>' )
			)
		).toBe( 'hero-title' );
		expect(
			getClassKey( mount( '<p class="i18n-Bad i18n-ok">x</p>' ) )
		).toBe( 'ok' );
		expect( getClassKey( mount( '<p class="lead">x</p>' ) ) ).toBeNull();
	} );
} );
