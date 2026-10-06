/**
 * Sedes map: Leaflet + OpenStreetMap tiles, one marker per sede, popups copied from the hidden
 * server-rendered sources (so the language layer can translate them), card ↔ marker hover sync.
 */

import L from 'leaflet';

function initSedesMap( root ) {
	const mapEl = root.querySelector( '[data-js="sedes-map"]' );
	if ( ! mapEl ) {
		return;
	}
	if ( typeof L === 'undefined' || typeof L.map !== 'function' ) {
		const unavailable = root.querySelector( '.sedes-map__unavailable' );
		if ( unavailable ) {
			unavailable.hidden = false;
		}
		return;
	}

	const sources = Array.from( root.querySelectorAll( '.sede-map-popup' ) );
	const noCoords = root.querySelector( '.sedes-map__nocoords' );

	const map = L.map( mapEl, { scrollWheelZoom: false, zoomControl: true } );

	L.tileLayer( 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
		maxZoom: 19,
		attribution:
			'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
	} ).addTo( map );

	const icon = L.divIcon( {
		className: 'sede-marker',
		html: '<span></span>',
		iconSize: [ 20, 20 ],
		iconAnchor: [ 10, 10 ],
		popupAnchor: [ 0, -12 ],
	} );

	const markers = new Map();
	const bounds = L.latLngBounds();

	sources.forEach( ( source ) => {
		const lat = Number( source.dataset.lat );
		const lng = Number( source.dataset.lng );
		if ( ! Number.isFinite( lat ) || ! Number.isFinite( lng ) ) {
			return;
		}
		const slug = source.dataset.sedeSlug || '';
		const marker = L.marker( [ lat, lng ], { icon } ).addTo( map );
		marker.bindPopup( source.innerHTML, { className: 'sede-map-popup' } );
		marker.on( 'click', () => setActiveCard( slug ) );
		markers.set( slug, { marker, source } );
		bounds.extend( [ lat, lng ] );
	} );

	if ( markers.size === 0 ) {
		map.setView( [ 20, -30 ], 2 );
		if ( noCoords ) {
			noCoords.hidden = false;
		}
	} else {
		map.fitBounds( bounds, { padding: [ 36, 36 ] } );
	}

	function setActiveCard( slug ) {
		document
			.querySelectorAll( '.sede-card--active' )
			.forEach( ( el ) => el.classList.remove( 'sede-card--active' ) );
		if ( ! slug ) {
			return;
		}
		const card = document.querySelector(
			`.sede-card[data-sede-slug="${ slug }"]`
		);
		if ( card ) {
			card.classList.add( 'sede-card--active' );
		}
	}

	function focusMarker( slug ) {
		const entry = markers.get( slug );
		if ( ! entry ) {
			return;
		}
		map.panTo( entry.marker.getLatLng(), { animate: true, duration: 0.5 } );
		setActiveCard( slug );
	}

	document
		.querySelectorAll( '.sede-card[data-sede-slug]' )
		.forEach( ( card ) => {
			const slug = card.getAttribute( 'data-sede-slug' );
			card.addEventListener( 'mouseenter', () => focusMarker( slug ) );
			card.addEventListener( 'focusin', () => focusMarker( slug ) );
		} );

	// Re-copy popup content after a language change (the hidden sources were translated in place).
	document.addEventListener( 'pura:langchange', () => {
		markers.forEach( ( { marker, source } ) => {
			marker.setPopupContent( source.innerHTML );
		} );
	} );

	window.setTimeout( () => map.invalidateSize(), 80 );
}

document.querySelectorAll( '.wp-block-pura-sedes-map' ).forEach( initSedesMap );
