/**
 * Gallery filters: AND-combine the active category and location pills; cards are server-rendered.
 */

function initGallery( root ) {
	const cards = Array.from( root.querySelectorAll( '.gallery-item' ) );
	const empty = root.querySelector( '.gallery-empty' );
	const state = { cat: '', loc: '' };

	function tokens( value ) {
		return ( value || '' ).split( /\s+/ ).filter( Boolean );
	}

	function render() {
		let visible = 0;
		cards.forEach( ( card ) => {
			const matchCat =
				state.cat === '' ||
				tokens( card.dataset.cat ).includes( state.cat );
			const matchLoc =
				state.loc === '' ||
				tokens( card.dataset.loc ).includes( state.loc );
			const show = matchCat && matchLoc;
			card.hidden = ! show;
			if ( show ) {
				visible += 1;
			}
		} );
		if ( empty ) {
			empty.hidden = visible > 0;
		}
	}

	function bind( attr, key ) {
		const buttons = Array.from(
			root.querySelectorAll( `button[${ attr }]` )
		);
		buttons.forEach( ( btn ) => {
			btn.addEventListener( 'click', () => {
				buttons.forEach( ( other ) => {
					const active = other === btn;
					other.setAttribute(
						'data-active',
						active ? 'true' : 'false'
					);
					other.setAttribute(
						'aria-pressed',
						active ? 'true' : 'false'
					);
				} );
				state[ key ] = btn.getAttribute( attr ) || '';
				render();
			} );
		} );
	}

	bind( 'data-filter-cat', 'cat' );
	bind( 'data-filter-loc', 'loc' );
}

document.querySelectorAll( '.wp-block-pura-gallery' ).forEach( initGallery );
