/**
 * The professors' editor: discovers the session, loads what the signed-in person may edit
 * (/pura/v1/editor/me) and saves one card at a time, sending only the fields that changed
 * together with the version the card was loaded with.
 */

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

	const result = el( 'div', {
		class: 'inscription-result',
		role: 'status',
		'aria-live': 'polite',
		hidden: true,
	} );
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
			grid,
			result,
			el( 'div', { class: 'btn-row' }, [ save, view ] ),
		]
	);

	const show = ( message, ok, link ) => {
		result.hidden = false;
		result.textContent = '';
		result.appendChild( el( 'span', { 'data-tv': true, text: message } ) );
		if ( link ) {
			result.appendChild( document.createTextNode( ' ' ) );
			result.appendChild(
				el( 'a', {
					href: link,
					target: '_blank',
					rel: 'noopener',
					'data-tv': true,
					text: 'Ver ahora',
				} )
			);
		}
		result.classList.toggle( 'is-success', !! ok );
		result.classList.toggle( 'is-error', ! ok );
		translate( result );
	};

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
			show( json.error || MESSAGES.network, false );
			return;
		}
		if ( res.status === 401 ) {
			show( MESSAGES.session, false );
			return;
		}
		mark( json.fields );
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

	const limits = data.limits || { rows: 12, cell: 120 };
	items.forEach( ( item ) =>
		parts.editor.appendChild( card( item, limits ) )
	);
	translate( parts.editor );
	setState( 'editor' );
}

document.querySelectorAll( '[data-editor-root]' ).forEach( ( root ) => {
	init( root );
} );
