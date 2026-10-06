/**
 * Pura Capoeira — small front-end behaviour (ES module, loaded deferred).
 *
 * Scroll reveal, ported from the static site's initScrollReveal(): `.reveal-on-scroll` blocks
 * and `.timeline-item` entries get `is-visible` when they enter the viewport. The CSS only hides
 * them under `html.js-reveal` (added in <head> by inc/setup.php), so without JS everything is
 * visible. Navigation, the overlay and the language switch are handled elsewhere.
 */

function initScrollReveal() {
	const blocks = Array.from(
		document.querySelectorAll( '.reveal-on-scroll' )
	);
	const items = Array.from( document.querySelectorAll( '.timeline-item' ) );
	if ( ! blocks.length && ! items.length ) {
		return;
	}

	const reveal = ( el ) => el.classList.add( 'is-visible' );
	const reduced =
		window.matchMedia &&
		window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	if ( reduced || ! ( 'IntersectionObserver' in window ) ) {
		blocks.forEach( reveal );
		items.forEach( reveal );
		return;
	}

	// A very tall block (a long timeline) never reaches a high intersection ratio, so the
	// timeline container and its items reveal as soon as their leading edge enters the viewport.
	const normalBlocks = [];
	const edgeTargets = [];
	blocks.forEach( ( el ) => {
		( el.classList.contains( 'timeline' )
			? edgeTargets
			: normalBlocks
		).push( el );
	} );
	items.forEach( ( el ) => edgeTargets.push( el ) );

	const observe = ( targets, options ) => {
		if ( ! targets.length ) {
			return;
		}
		const observer = new window.IntersectionObserver( ( entries, obs ) => {
			entries.forEach( ( entry ) => {
				if ( ! entry.isIntersecting ) {
					return;
				}
				reveal( entry.target );
				obs.unobserve( entry.target );
			} );
		}, options );
		targets.forEach( ( el ) => observer.observe( el ) );
	};

	observe( normalBlocks, { threshold: 0.22, rootMargin: '0px 0px -8% 0px' } );
	observe( edgeTargets, { threshold: 0, rootMargin: '0px 0px -10% 0px' } );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initScrollReveal );
} else {
	initScrollReveal();
}
