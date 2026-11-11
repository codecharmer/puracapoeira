/**
 * The professors' editor: discovers the session, loads what the signed-in person may edit
 * (/pura/v1/editor/me) and saves one card at a time, sending only the fields that changed
 * together with the version the card was loaded with. Photos are saved as soon as they are
 * chosen, each through its own request.
 */

import { prepare } from '../../shared/prepare-photo';

const $ = ( sel, ctx = document ) => ctx.querySelector( sel );

const text = ( name, label, extra = {} ) => ( {
	name,
	label,
	kind: 'text',
	...extra,
} );

const SHARED_CONTACT = [
	text( 'whatsapp_display', 'WhatsApp: número como se muestra', {
		placeholder: '+52 777 123 4567',
		max: 40,
	} ),
	text( 'whatsapp', 'WhatsApp: enlace', {
		kind: 'url',
		placeholder: 'https://wa.me/5217771234567',
		help: 'Debe empezar con https://wa.me/ seguido del número con clave de país.',
	} ),
	text( 'instagram_handle', 'Instagram: usuario', {
		placeholder: '@puracapoeira',
		max: 60,
	} ),
	text( 'instagram', 'Instagram: enlace', {
		kind: 'url',
		placeholder: 'https://www.instagram.com/usuario/',
	} ),
];

const FIELDS = {
	sede: [
		text( 'excerpt', 'Resumen de la sede', {
			kind: 'textarea',
			max: 600,
			full: true,
			help: 'Aparece en la tarjeta de la sede.',
		} ),
		text( 'address', 'Dirección', { max: 200, full: true } ),
		text( 'responsible', 'Responsable', { max: 200 } ),
		...SHARED_CONTACT,
		text( 'facebook', 'Facebook: enlace o nombre de la página', {
			max: 200,
		} ),
		{
			name: 'schedule',
			label: 'Horarios',
			kind: 'rows',
			columns: [
				[ 'group', 'Grupo' ],
				[ 'days', 'Días' ],
				[ 'time', 'Hora' ],
			],
		},
		{
			name: 'pricing',
			label: 'Precios',
			kind: 'rows',
			columns: [
				[ 'label', 'Concepto' ],
				[ 'value', 'Precio' ],
			],
		},
	],
	profesor: [
		text( 'excerpt', 'Resumen', {
			kind: 'textarea',
			max: 600,
			full: true,
			help: 'Aparece en tu tarjeta en la lista de profesores.',
		} ),
		text( 'subtitle', 'Presentación', {
			kind: 'textarea',
			max: 600,
			full: true,
			help: 'El texto bajo tu nombre en tu página.',
		} ),
		text( 'caption', 'Pie de tu foto', { max: 200, full: true } ),
		...SHARED_CONTACT,
		text( 'facebook', 'Facebook: enlace', {
			kind: 'url',
			placeholder: 'https://www.facebook.com/usuario',
		} ),
		text( 'youtube', 'YouTube: enlace', {
			kind: 'url',
			placeholder: 'https://www.youtube.com/@canal',
		} ),
		text( 'website', 'Sitio web', {
			kind: 'url',
			placeholder: 'https://',
		} ),
	],
};

const MESSAGES = {
	saved: 'Guardado. El sitio y la app muestran el cambio en un minuto.',
	nothing: 'No hay cambios que guardar.',
	network: 'No se pudo guardar. Revisa tu conexión e inténtalo de nuevo.',
	session: 'Tu sesión terminó. Vuelve a entrar para guardar.',
	photoSaved: 'Foto guardada. El sitio y la app la muestran en un minuto.',
	gallerySaved: 'Galería actualizada.',
	photoUnreadable:
		'No pudimos leer esa foto. Prueba con una en formato JPG o PNG.',
	photoTooBig: 'Esa foto es demasiado grande.',
};

const DEFAULT_LIMITS = {
	rows: 12,
	cell: 120,
	gallery: 12,
	maxBytes: 15 * 1024 * 1024,
};

function el( tag, attrs = {}, children = [] ) {
	const node = document.createElement( tag );
	Object.entries( attrs ).forEach( ( [ key, value ] ) => {
		if ( value === false || value === null || value === undefined ) {
			return;
		}
		if ( key === 'text' ) {
			node.textContent = value;
		} else if ( key === 'class' ) {
			node.className = value;
		} else {
			node.setAttribute( key, value === true ? '' : value );
		}
	} );
	[]
		.concat( children )
		.forEach( ( child ) => child && node.appendChild( child ) );
	return node;
}

/**
 * Translate text added after the language layer ran (it only saw the first DOM).
 *
 * @param {HTMLElement} root Node to translate.
 */
function translate( root ) {
	const i18n = window.puraI18n;
	if ( i18n && i18n.loaded && typeof i18n.apply === 'function' ) {
		i18n.apply( root );
	}
}

/**
 * A signed request that survives an expired nonce: ask for a new one and try once more.
 *
 * @param {string} path    Route under pura/v1.
 * @param {Object} options Method and body.
 */
async function api( path, options ) {
	let out = await window.puraAuth.request( path, options );
	if (
		out.res.status === 403 &&
		out.json &&
		out.json.code === 'rest_cookie_invalid_nonce'
	) {
		await window.puraAuth.refreshNonce();
		out = await window.puraAuth.request( path, options );
	}
	return out;
}

/**
 * Uploads one file to an editor route, with the same nonce handling as api().
 *
 * @param {string} path Route under pura/v1.
 * @param {File}   file Photo, already prepared.
 */
async function sendFile( path, file ) {
	const base = ( window.puraConfig || {} ).restUrl || '/wp-json/pura/v1/';
	const attempt = async () => {
		const token = await window.puraAuth.nonce();
		if ( ! token ) {
			return { res: { ok: false, status: 401 }, json: {} };
		}
		const body = new window.FormData();
		body.append( 'file', file, file.name );
		const res = await window.fetch( base.replace( /\/?$/, '/' ) + path, {
			method: 'POST',
			credentials: 'same-origin',
			cache: 'no-store',
			headers: { Accept: 'application/json', 'X-WP-Nonce': token },
			body,
		} );
		let json = {};
		try {
			json = await res.json();
		} catch ( _err ) {
			json = {};
		}
		return { res, json };
	};
	let out = await attempt();
	if (
		out.res.status === 403 &&
		out.json.code === 'rest_cookie_invalid_nonce'
	) {
		await window.puraAuth.refreshNonce();
		out = await attempt();
	}
	return out;
}

/**
 * A status line and the function that fills it.
 */
function notice() {
	const node = el( 'div', {
		class: 'inscription-result',
		role: 'status',
		'aria-live': 'polite',
		hidden: true,
	} );
	const show = ( message, ok, link ) => {
		node.hidden = false;
		node.textContent = '';
		node.appendChild( el( 'span', { 'data-tv': true, text: message } ) );
		if ( link ) {
			node.appendChild( document.createTextNode( ' ' ) );
			node.appendChild(
				el( 'a', {
					href: link,
					target: '_blank',
					rel: 'noopener',
					'data-tv': true,
					text: 'Ver ahora',
				} )
			);
		}
		node.classList.toggle( 'is-success', !! ok );
		node.classList.toggle( 'is-error', ! ok );
		translate( node );
	};
	return { node, show };
}

/**
 * The photos of a card: the main photo (or portrait) and, for a sede, its gallery. Every
 * action is saved at once and updates `item` with the server's answer.
 *
 * @param {Object} item   Item from /editor/me.
 * @param {Object} limits Limits from /editor/me.
 */
function photoSection( item, limits ) {
	const isSede = item.type === 'sede';
	const status = notice();
	const button = ( label ) =>
		el( 'button', {
			type: 'button',
			class: 'btn btn--ghost',
			'data-tv': true,
			text: label,
		} );
	const picker = ( multiple ) =>
		el( 'input', {
			type: 'file',
			accept: 'image/*',
			multiple,
			hidden: true,
			tabindex: '-1',
		} );

	const preview = el( 'img', { class: 'editor-photo__img', alt: '' } );
	const empty = el( 'span', {
		class: 'editor-photo__empty',
		'data-tv': true,
		text: 'Sin foto',
	} );
	const mainInput = picker( false );
	const mainButton = button( isSede ? 'Cambiar foto' : 'Cambiar retrato' );
	const grid = el( 'div', { class: 'editor-gallery__grid' } );
	const count = el( 'span', { class: 'muted editor-gallery__count' } );
	const addInput = picker( true );
	const addButton = button( 'Añadir fotos' );

	const section = el( 'div', { class: 'editor-photos' }, [
		el( 'div', { class: 'editor-photo' }, [
			el( 'div', { class: 'editor-photo__frame' }, [ preview, empty ] ),
			el( 'div', { class: 'editor-photo__side' }, [
				el( 'span', {
					class: 'editor-rows__label',
					'data-tv': true,
					text: isSede ? 'Foto principal de la sede' : 'Tu retrato',
				} ),
				el( 'span', {
					class: 'field-help',
					'data-tv': true,
					text: 'Se guarda al elegirla. La foto anterior se conserva 30 días por si hay que volver a ella.',
				} ),
				mainButton,
				mainInput,
			] ),
		] ),
		isSede
			? el( 'div', { class: 'editor-gallery' }, [
					el( 'div', { class: 'editor-gallery__head' }, [
						el( 'span', {
							class: 'editor-rows__label',
							'data-tv': true,
							text: 'Galería de la sede',
						} ),
						count,
					] ),
					grid,
					addButton,
					addInput,
			  ] )
			: null,
		status.node,
	] );

	let busy = false;
	let render = () => {};
	const setBusy = ( state ) => {
		busy = state;
		section.classList.toggle( 'is-busy', state );
		if ( state ) {
			section.querySelectorAll( 'button' ).forEach( ( node ) => {
				node.disabled = true;
			} );
		} else {
			mainButton.disabled = false;
			addButton.disabled = false;
			render();
		}
	};

	const order = async ( ids ) => {
		setBusy( true );
		let out;
		try {
			out = await api( `editor/sede/${ item.id }/gallery`, {
				method: 'PUT',
				body: { base_modified: item.modified, ids },
			} );
		} catch ( _err ) {
			out = null;
		}
		if ( out && out.res.ok && out.json.ok ) {
			Object.assign( item, out.json.item );
			status.show( MESSAGES.gallerySaved, true );
		} else if ( out && out.res.status === 409 && out.json.current ) {
			Object.assign( item, out.json.current );
			status.show( out.json.error || MESSAGES.network, false );
		} else if ( out && out.res.status === 401 ) {
			status.show( MESSAGES.session, false );
		} else {
			status.show( ( out && out.json.error ) || MESSAGES.network, false );
		}
		setBusy( false );
	};

	const tile = ( photo, index, all ) => {
		const ids = all.map( ( entry ) => entry.id );
		const move = ( to ) => {
			const next = ids.slice();
			next.splice( to, 0, next.splice( index, 1 )[ 0 ] );
			order( next );
		};
		const small = ( label, glyph, handler, disabled ) => {
			const node = el( 'button', {
				type: 'button',
				class: 'editor-gallery__action',
				'aria-label': label,
				text: glyph,
				disabled,
			} );
			node.addEventListener( 'click', handler );
			return node;
		};
		return el( 'figure', { class: 'editor-gallery__item' }, [
			el( 'img', { src: photo.thumb || photo.url, alt: '' } ),
			el( 'figcaption', { class: 'editor-gallery__actions' }, [
				small(
					'Mover antes',
					'←',
					() => move( index - 1 ),
					index === 0
				),
				small(
					'Mover después',
					'→',
					() => move( index + 1 ),
					index === all.length - 1
				),
				small( 'Quitar de la galería', '×', () =>
					order( ids.filter( ( id ) => id !== photo.id ) )
				),
			] ),
		] );
	};

	render = () => {
		preview.hidden = ! item.image;
		empty.hidden = !! item.image;
		if ( item.image ) {
			preview.src = item.image.url;
		}
		if ( ! isSede ) {
			return;
		}
		const photos = item.gallery || [];
		grid.textContent = '';
		photos.forEach( ( photo, index ) =>
			grid.appendChild( tile( photo, index, photos ) )
		);
		count.textContent = `${ photos.length } / ${ limits.gallery }`;
		addButton.hidden = photos.length >= limits.gallery;
	};

	const send = async ( path, files, room ) => {
		setBusy( true );
		for ( const original of Array.from( files ).slice( 0, room ) ) {
			let file;
			try {
				file = await prepare( original, limits.maxBytes, true );
			} catch ( err ) {
				status.show(
					err && err.message === 'too-big'
						? MESSAGES.photoTooBig
						: MESSAGES.photoUnreadable,
					false
				);
				break;
			}
			let out;
			try {
				out = await sendFile( path, file );
			} catch ( _err ) {
				out = null;
			}
			if ( out && out.res.ok && out.json.ok ) {
				Object.assign( item, out.json.item );
				status.show( MESSAGES.photoSaved, true );
				continue;
			}
			let message = ( out && out.json.error ) || MESSAGES.network;
			if ( out && out.res.status === 401 ) {
				message = MESSAGES.session;
			}
			status.show( message, false );
			break;
		}
		setBusy( false );
	};

	const choose = ( input, path, room ) => {
		const files = input.files;
		if ( files && files.length && ! busy ) {
			send( path(), files, room() ).then( () => {
				input.value = '';
			} );
		}
	};
	mainButton.addEventListener( 'click', () => mainInput.click() );
	mainInput.addEventListener( 'change', () =>
		choose(
			mainInput,
			() => `editor/${ item.type }/${ item.id }/photo`,
			() => 1
		)
	);
	addButton.addEventListener( 'click', () => addInput.click() );
	addInput.addEventListener( 'change', () =>
		choose(
			addInput,
			() => `editor/sede/${ item.id }/gallery`,
			() => limits.gallery - ( item.gallery || [] ).length
		)
	);

	render();
	return section;
}

/**
 * What is sent for a bio, in one shape for both the loaded copy and the form, so the two can
 * be compared.
 *
 * @param {Object} bio Bio from /editor/me.
 */
function bioShape( bio ) {
	return {
		sections: ( ( bio && bio.sections ) || [] ).map( ( section ) => ( {
			id: section.id,
			items: section.items.map( ( item ) =>
				item.kind === 'event'
					? {
							ref: item.ref,
							year: item.year,
							title: item.title,
							text: item.text,
					  }
					: { ref: item.ref, text: item.text }
			),
		} ) ),
	};
}

/**
 * The bio of a profesor: the paragraphs of each section and the entries of the timeline.
 * Lists and sub-headings are shown but only the administration changes them.
 *
 * @param {Object} bio    Bio from /editor/me.
 * @param {Object} limits Limits from /editor/me.
 */
function bioEditor( bio, limits ) {
	const max = limits.bio || {};
	const body = el( 'div', { class: 'editor-bio__sections' } );
	const wrap = el(
		'div',
		{
			class: 'field field--full editor-bio',
			'data-field': 'bio',
		},
		[
			el( 'span', {
				class: 'editor-rows__label',
				'data-tv': true,
				text: 'Biografía y trayectoria',
			} ),
			el( 'span', {
				class: 'field-help',
				'data-tv': true,
				text: 'Lo que cambies se guarda como texto simple, y su traducción al inglés y al portugués se quita hasta que se vuelva a traducir. Lo que no toques queda exactamente igual.',
			} ),
			body,
		]
	);

	const action = ( label, glyph, handler ) => {
		const node = el( 'button', {
			type: 'button',
			class: 'editor-bio__action',
			'aria-label': label,
			text: glyph,
		} );
		node.addEventListener( 'click', handler );
		return node;
	};

	const row = ( item, kind ) => {
		const line = el( 'div', {
			class: `editor-bio__item editor-bio__item--${ kind }`,
			'data-item': kind,
			'data-ref':
				item.ref === undefined || item.ref === null ? '' : item.ref,
		} );
		const input = ( name, label, value, attrs = {} ) =>
			el( attrs.rows ? 'textarea' : 'input', {
				type: attrs.rows ? false : 'text',
				'data-part': name,
				'aria-label': label,
				placeholder: label,
				...attrs,
			} );
		const main = el( 'div', { class: 'editor-bio__main' } );
		if ( kind === 'fixed' ) {
			line.dataset.text = item.text;
			main.appendChild(
				el( 'p', { class: 'editor-bio__fixed', text: item.text } )
			);
			main.appendChild(
				el( 'span', {
					class: 'field-help',
					'data-tv': true,
					text: 'Esta parte solo la cambia la administración.',
				} )
			);
		} else if ( kind === 'event' ) {
			const year = input( 'year', 'Año', item.year, {
				maxlength: max.year || 20,
				class: 'editor-bio__year',
			} );
			const title = input( 'title', 'Título', item.title, {
				maxlength: max.title || 120,
			} );
			const detail = input( 'text', 'Descripción', item.text, {
				rows: 2,
				maxlength: max.eventText || 600,
			} );
			year.value = item.year || '';
			title.value = item.title || '';
			detail.value = item.text || '';
			main.appendChild(
				el( 'div', { class: 'editor-bio__event-head' }, [
					year,
					title,
				] )
			);
			main.appendChild( detail );
		} else {
			const paragraph = input( 'text', 'Párrafo', item.text, {
				rows: 4,
				maxlength: max.text || 1500,
			} );
			paragraph.value = item.text || '';
			main.appendChild( paragraph );
		}
		const actions = el( 'div', { class: 'editor-bio__actions' }, [
			action( 'Mover antes', '↑', () => {
				if ( line.previousElementSibling ) {
					line.parentNode.insertBefore(
						line,
						line.previousElementSibling
					);
				}
			} ),
			action( 'Mover después', '↓', () => {
				if ( line.nextElementSibling ) {
					line.parentNode.insertBefore(
						line.nextElementSibling,
						line
					);
				}
			} ),
			kind === 'fixed'
				? null
				: action( 'Quitar', '×', () => line.remove() ),
		] );
		line.appendChild( main );
		line.appendChild( actions );
		return line;
	};

	const build = ( next ) => {
		body.textContent = '';
		( ( next && next.sections ) || [] ).forEach( ( section ) => {
			const timeline = section.kind === 'timeline';
			const list = el( 'div', {
				class: 'editor-bio__list',
				'data-section': section.id,
			} );
			section.items.forEach( ( item ) =>
				list.appendChild( row( item, item.kind ) )
			);
			const add = el( 'button', {
				type: 'button',
				class: 'btn btn--ghost editor-rows__add',
				'data-tv': true,
				text: timeline ? 'Añadir entrada' : 'Añadir párrafo',
			} );
			add.addEventListener( 'click', () => {
				const cap = timeline ? max.events || 40 : max.items || 30;
				if ( list.children.length >= cap ) {
					return;
				}
				const line = row( {}, timeline ? 'event' : 'text' );
				list.appendChild( line );
				const first = line.querySelector( '[data-part]' );
				if ( first ) {
					first.focus();
				}
			} );
			body.appendChild(
				el( 'div', { class: 'editor-bio__section' }, [
					el( 'h3', {
						class: 'editor-bio__title',
						'data-tv': true,
						text: section.title,
					} ),
					list,
					add,
				] )
			);
		} );
		translate( body );
	};

	wrap.readBio = () => ( {
		sections: Array.from( body.querySelectorAll( '[data-section]' ) ).map(
			( list ) => ( {
				id: Number( list.dataset.section ),
				items: Array.from( list.querySelectorAll( '[data-item]' ) )
					.map( ( line ) => {
						const part = ( name ) =>
							clean(
								line.querySelector( `[data-part="${ name }"]` )
									.value,
								false
							);
						const item =
							line.dataset.ref === ''
								? {}
								: { ref: Number( line.dataset.ref ) };
						if ( line.dataset.item === 'fixed' ) {
							item.text = line.dataset.text;
						} else if ( line.dataset.item === 'event' ) {
							item.year = part( 'year' );
							item.title = part( 'title' );
							item.text = part( 'text' );
						} else {
							item.text = part( 'text' );
						}
						return item;
					} )
					.filter(
						( item ) =>
							item.ref !== undefined ||
							[ item.year, item.title, item.text ].join( '' ) !==
								''
					),
			} )
		),
	} );
	wrap.setBio = build;

	build( bio );
	return wrap;
}

let uid = 0;

function rowsEditor( field, rows, limits ) {
	const body = el( 'div', { class: 'editor-rows__body' } );
	const add = el( 'button', {
		type: 'button',
		class: 'btn btn--ghost editor-rows__add',
		'data-tv': true,
		text: 'Añadir fila',
	} );

	const sync = () => {
		add.hidden = body.children.length >= limits.rows;
	};
	const addRow = ( row = {} ) => {
		const line = el( 'div', {
			class: 'editor-rows__row',
			'data-row': true,
		} );
		field.columns.forEach( ( [ key, label ] ) => {
			line.appendChild(
				el( 'input', {
					type: 'text',
					class: 'editor-rows__cell',
					'data-col': key,
					'aria-label': label,
					placeholder: label,
					maxlength: limits.cell,
					value: row[ key ] || '',
				} )
			);
		} );
		const remove = el( 'button', {
			type: 'button',
			class: 'editor-rows__remove',
			'aria-label': 'Quitar fila',
			text: '×',
		} );
		remove.addEventListener( 'click', () => {
			line.remove();
			sync();
		} );
		line.appendChild( remove );
		body.appendChild( line );
		sync();
	};

	rows.forEach( addRow );
	add.addEventListener( 'click', () => addRow() );

	const wrap = el(
		'div',
		{
			class: 'field field--full editor-rows',
			'data-field': field.name,
			'data-kind': 'rows',
		},
		[
			el( 'span', {
				class: 'editor-rows__label',
				'data-tv': true,
				text: field.label,
			} ),
			body,
			add,
		]
	);
	wrap.readRows = () =>
		Array.from( body.querySelectorAll( '[data-row]' ) )
			.map( ( line ) => {
				const row = {};
				field.columns.forEach( ( [ key ] ) => {
					row[ key ] = line
						.querySelector( `[data-col="${ key }"]` )
						.value.replace( /\s+/g, ' ' )
						.trim();
				} );
				return row;
			} )
			.filter( ( row ) => Object.values( row ).join( '' ) !== '' );
	wrap.setRows = ( next ) => {
		body.innerHTML = '';
		next.forEach( addRow );
		sync();
	};
	return wrap;
}

function scalarField( field, value ) {
	const id = `editor-field-${ ++uid }`;
	const common = {
		id,
		name: field.name,
		'data-input': true,
		placeholder: field.placeholder || false,
		maxlength: field.kind === 'url' ? 300 : field.max || 200,
	};
	const input =
		field.kind === 'textarea'
			? el( 'textarea', { ...common, rows: 4 } )
			: el( 'input', {
					...common,
					type: field.kind === 'url' ? 'url' : 'text',
					inputmode: field.kind === 'url' ? 'url' : false,
			  } );
	input.value = value || '';
	return el(
		'label',
		{
			class: `field${ field.full ? ' field--full' : '' }`,
			for: id,
			'data-field': field.name,
			'data-kind': 'scalar',
		},
		[
			el( 'span', { 'data-tv': true, text: field.label } ),
			input,
			field.help
				? el( 'span', {
						class: 'field-help',
						'data-tv': true,
						text: field.help,
				  } )
				: null,
		]
	);
}

const clean = ( value, multiline ) =>
	multiline
		? value
				.replace( /\r\n?/g, '\n' )
				.split( '\n' )
				.map( ( line ) => line.replace( /[ \t]+/g, ' ' ).trim() )
				.join( '\n' )
				.replace( /\n{3,}/g, '\n\n' )
				.trim()
		: value.replace( /\s+/g, ' ' ).trim();

/**
 * One card: a sede or the profile, with its own Save.
 *
 * @param {Object} item   Item from /editor/me.
 * @param {Object} limits Row limits.
 */
function card( item, limits ) {
	const fields = FIELDS[ item.type ] || [];
	const about = Object.values( item.about || {} )
		.filter( Boolean )
		.join( ' · ' );
	const grid = el( 'div', { class: 'form-grid' } );
	const controls = {};
	fields.forEach( ( field ) => {
		const control =
			field.kind === 'rows'
				? rowsEditor( field, item.fields[ field.name ] || [], limits )
				: scalarField( field, item.fields[ field.name ] );
		controls[ field.name ] = control;
		grid.appendChild( control );
	} );

	const bio = item.bio ? bioEditor( item.bio, limits ) : null;
	if ( bio ) {
		grid.appendChild( bio );
	}

	const { node: result, show } = notice();
	const save = el( 'button', {
		type: 'submit',
		class: 'btn btn--primary',
		'data-tv': true,
		text: 'Guardar cambios',
	} );
	const view = el( 'a', {
		class: 'btn btn--ghost',
		href: item.url,
		target: '_blank',
		rel: 'noopener',
		'data-tv': true,
		text: 'Ver la página',
	} );
	const form = el(
		'form',
		{
			class: 'inscription-form editor-card',
			novalidate: true,
			'data-editor-card': item.id,
		},
		[
			el( 'p', {
				class: 'eyebrow',
				'data-tv': true,
				text: item.type === 'sede' ? 'Sede' : 'Tu perfil',
			} ),
			el( 'h2', {
				class: 'display editor-card__title',
				text: item.title,
			} ),
			about
				? el( 'p', { class: 'muted editor-card__about', text: about } )
				: null,
			photoSection( item, limits ),
			grid,
			result,
			el( 'div', { class: 'btn-row' }, [ save, view ] ),
		]
	);

	const read = () => {
		const values = {};
		fields.forEach( ( field ) => {
			const control = controls[ field.name ];
			values[ field.name ] =
				field.kind === 'rows'
					? control.readRows()
					: clean(
							$( '[data-input]', control ).value,
							field.kind === 'textarea'
					  );
		} );
		return values;
	};
	const fill = ( values ) => {
		fields.forEach( ( field ) => {
			const control = controls[ field.name ];
			if ( field.kind === 'rows' ) {
				control.setRows( values[ field.name ] || [] );
			} else {
				$( '[data-input]', control ).value = values[ field.name ] || '';
			}
		} );
	};
	const mark = ( errors ) => {
		fields.forEach( ( field ) =>
			controls[ field.name ].classList.toggle(
				'has-error',
				!! ( errors && errors[ field.name ] )
			)
		);
	};

	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();
		mark( null );
		const values = read();
		const changed = {};
		Object.keys( values ).forEach( ( name ) => {
			if (
				JSON.stringify( values[ name ] ) !==
				JSON.stringify( item.fields[ name ] )
			) {
				changed[ name ] = values[ name ];
			}
		} );
		if ( bio ) {
			const edits = bio.readBio();
			if (
				JSON.stringify( edits ) !==
				JSON.stringify( bioShape( item.bio ) )
			) {
				changed.bio = edits;
			}
		}
		if ( ! Object.keys( changed ).length ) {
			show( MESSAGES.nothing, true );
			return;
		}

		save.disabled = true;
		let out;
		try {
			out = await api( `editor/${ item.type }/${ item.id }`, {
				method: 'PATCH',
				body: { base_modified: item.modified, fields: changed },
			} );
		} catch ( _err ) {
			out = null;
		}
		save.disabled = false;

		if ( ! out ) {
			show( MESSAGES.network, false );
			return;
		}
		const { res, json } = out;
		if ( res.ok && json.ok ) {
			Object.assign( item, json.item );
			fill( item.fields );
			if ( bio ) {
				bio.setBio( item.bio );
			}
			view.href = item.url;
			show(
				MESSAGES.saved,
				true,
				`${ item.url }${
					item.url.includes( '?' ) ? '&' : '?'
				}v=${ Date.now() }`
			);
			return;
		}
		if ( res.status === 409 && json.current ) {
			// Someone else saved first. Keep what was typed on screen, but move to their version so the next
			// save is compared with the current data, and say so.
			Object.assign( item, json.current );
			if ( bio ) {
				// The stored bio changed, so the positions on screen no longer match it: show the current one.
				bio.setBio( item.bio );
			}
			show( json.error || MESSAGES.network, false );
			return;
		}
		if ( res.status === 401 ) {
			show( MESSAGES.session, false );
			return;
		}
		mark( json.fields );
		if ( bio ) {
			bio.classList.toggle(
				'has-error',
				!! ( json.fields && json.fields.bio )
			);
		}
		show( json.error || MESSAGES.network, false );
	} );

	return form;
}

async function init( root ) {
	const parts = {
		pending: $( '[data-editor-loading]', root ),
		'logged-out': $( '[data-editor-logged-out]', root ),
		'no-access': $( '[data-editor-no-access]', root ),
		editor: $( '[data-editor-forms]', root ),
	};
	const setState = ( state ) => {
		root.dataset.editorState = state;
		Object.entries( parts ).forEach( ( [ name, node ] ) => {
			if ( node ) {
				node.hidden = name !== state;
			}
		} );
	};

	if ( ! window.puraAuth || ! ( await window.puraAuth.isLoggedIn() ) ) {
		setState( 'logged-out' );
		return;
	}

	let out;
	try {
		out = await api( 'editor/me' );
	} catch ( _err ) {
		out = null;
	}
	if ( ! out || out.res.status === 401 ) {
		setState( 'logged-out' );
		return;
	}
	const data = out.json || {};
	const items = [].concat( data.profesor || [], data.sedes || [] );
	if ( ! out.res.ok || ! items.length ) {
		const email = $( '[data-editor-email]', root );
		if ( email ) {
			const me = await window.puraAuth.me().catch( () => null );
			const address =
				me && me.json && me.json.user ? me.json.user.email : '';
			email.textContent = address ? `Entraste como ${ address }.` : '';
		}
		setState( 'no-access' );
		return;
	}

	const limits = { ...DEFAULT_LIMITS, ...( data.limits || {} ) };
	items.forEach( ( item ) =>
		parts.editor.appendChild( card( item, limits ) )
	);
	translate( parts.editor );
	setState( 'editor' );
}

document.querySelectorAll( '[data-editor-root]' ).forEach( ( root ) => {
	init( root );
} );
