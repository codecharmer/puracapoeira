/**
 * Colleagues' photo upload: checks the key in the link, lets the person pick a profile photo and
 * any number of photos, shrinks the big ones in the browser, and uploads them one by one to
 * /pura/v1/photos/batches/{id}/files with per-file progress and one automatic retry.
 */

const $ = ( sel, ctx = document ) => ctx.querySelector( sel );

const KEY_PARAM_DEFAULT = 'clave';
const MAX_SIDE = 2400; // px — plenty for the web, a fraction of a phone's original.
const JPEG_QUALITY = 0.86;

function restUrl( path ) {
	const base =
		( window.puraConfig && window.puraConfig.restUrl ) ||
		'/wp-json/pura/v1/';
	return base.replace( /\/?$/, '/' ) + path;
}

/**
 * Translated string for the current language (page dictionary `fotos`), else the Spanish fallback.
 *
 * @param {string} key      Scoped key (without the i18n- prefix).
 * @param {string} fallback Spanish text.
 * @return {string} Text.
 */
function t( key, fallback ) {
	const api = window.puraI18n;
	const value =
		api && typeof api.key === 'function' ? api.key( key ) : undefined;
	return typeof value === 'string' && value !== '' ? value : fallback;
}

function fill( text, vars ) {
	return text.replace( /\{(\w+)\}/g, ( match, name ) =>
		name in vars ? String( vars[ name ] ) : match
	);
}

function formatBytes( bytes ) {
	return bytes >= 1048576
		? `${ Math.round( bytes / 1048576 ) } MB`
		: `${ Math.round( bytes / 1024 ) } KB`;
}

function keyFromUrl( param ) {
	const fromQuery = new URLSearchParams( window.location.search ).get(
		param
	);
	if ( fromQuery && fromQuery.trim() ) {
		return fromQuery.trim();
	}
	const fromHash = new URLSearchParams(
		window.location.hash.replace( /^#/, '' )
	).get( param );
	return fromHash ? fromHash.trim() : '';
}

async function getJson( path, params ) {
	const url = new URL( restUrl( path ), window.location.origin );
	Object.keys( params ).forEach( ( k ) =>
		url.searchParams.set( k, params[ k ] )
	);
	const res = await fetch( url.toString(), {
		credentials: 'same-origin',
		cache: 'no-store',
		headers: { Accept: 'application/json' },
	} );
	let json = {};
	try {
		json = await res.json();
	} catch ( _err ) {
		json = {};
	}
	return { res, json };
}

async function postJson( path, body ) {
	const res = await fetch( restUrl( path ), {
		method: 'POST',
		credentials: 'same-origin',
		cache: 'no-store',
		headers: {
			Accept: 'application/json',
			'Content-Type': 'application/json',
		},
		body: JSON.stringify( body ),
	} );
	let json = {};
	try {
		json = await res.json();
	} catch ( _err ) {
		json = {};
	}
	return { res, json };
}

/**
 * Multipart upload with progress (XHR: fetch cannot report upload progress yet).
 *
 * @param {string}   path       REST path.
 * @param {FormData} formData   Body.
 * @param {Function} onProgress Called with 0–100.
 * @return {Promise<{status:number,json:Object}>} Result.
 */
function upload( path, formData, onProgress ) {
	return new Promise( ( resolve, reject ) => {
		const xhr = new window.XMLHttpRequest();
		xhr.open( 'POST', restUrl( path ) );
		xhr.setRequestHeader( 'Accept', 'application/json' );
		xhr.upload.addEventListener( 'progress', ( e ) => {
			if ( e.lengthComputable ) {
				onProgress( Math.round( ( e.loaded / e.total ) * 100 ) );
			}
		} );
		xhr.addEventListener( 'load', () => {
			let json = {};
			try {
				json = JSON.parse( xhr.responseText || '{}' );
			} catch ( _err ) {
				json = {};
			}
			resolve( { status: xhr.status, json } );
		} );
		xhr.addEventListener( 'error', () => reject( new Error( 'network' ) ) );
		xhr.addEventListener( 'abort', () => reject( new Error( 'abort' ) ) );
		xhr.send( formData );
	} );
}

/**
 * Decode an image file honouring its EXIF orientation. Resolves to something drawImage accepts,
 * or rejects when the browser cannot decode it (HEIC outside Safari, for instance).
 *
 * @param {File} file Image.
 * @return {Promise<ImageBitmap|HTMLImageElement>} Decoded image.
 */
async function decode( file ) {
	if ( typeof window.createImageBitmap === 'function' ) {
		try {
			return await window.createImageBitmap( file, {
				imageOrientation: 'from-image',
			} );
		} catch ( _err ) {
			// fall through to <img>, which applies orientation in every modern browser
		}
	}
	return new Promise( ( resolve, reject ) => {
		const url = URL.createObjectURL( file );
		const img = new window.Image();
		img.onload = () => {
			URL.revokeObjectURL( url );
			resolve( img );
		};
		img.onerror = () => {
			URL.revokeObjectURL( url );
			reject( new Error( 'decode' ) );
		};
		img.src = url;
	} );
}

/**
 * The file to upload: the original when it is small enough, otherwise a JPEG no larger than
 * MAX_SIDE on its longest side. Throws when the file cannot be decoded and is over the limit.
 *
 * @param {File}   file     Original.
 * @param {number} maxBytes Server limit.
 * @return {Promise<File>} File to send.
 */
async function prepare( file, maxBytes ) {
	let image;
	try {
		image = await decode( file );
	} catch ( _err ) {
		if ( file.size <= maxBytes ) {
			return file;
		}
		throw new Error( 'too-big' );
	}

	const width = image.naturalWidth || image.width;
	const height = image.naturalHeight || image.height;
	const longest = Math.max( width, height );
	const needsResize = longest > MAX_SIDE || file.size > maxBytes;
	if ( ! needsResize ) {
		return file;
	}

	const scale = Math.min( 1, MAX_SIDE / longest );
	const canvas = document.createElement( 'canvas' );
	canvas.width = Math.round( width * scale );
	canvas.height = Math.round( height * scale );
	canvas
		.getContext( '2d' )
		.drawImage( image, 0, 0, canvas.width, canvas.height );
	if ( typeof image.close === 'function' ) {
		image.close();
	}

	const blob = await new Promise( ( resolve ) =>
		canvas.toBlob( resolve, 'image/jpeg', JPEG_QUALITY )
	);
	if ( ! blob ) {
		if ( file.size <= maxBytes ) {
			return file;
		}
		throw new Error( 'too-big' );
	}
	if ( blob.size > maxBytes ) {
		throw new Error( 'too-big' );
	}
	const name = file.name.replace( /\.[^.]+$/, '' ) + '.jpg';
	return new File( [ blob ], name, {
		type: 'image/jpeg',
		lastModified: file.lastModified,
	} );
}

function initPhotoUpload( root ) {
	const form = $( '[data-photo-form]', root );
	if ( ! form ) {
		return;
	}
	const checking = $( '[data-photo-checking]', root );
	const gate = $( '[data-photo-gate]', root );
	const resultEl = $( '[data-photo-result]', root );
	const submitBtn = $( '[data-photo-submit]', root );
	const retryBtn = $( '[data-photo-retry]', root );
	const done = $( '[data-photo-done]', root );
	const doneText = $( '[data-photo-done-text]', root );
	const moreLink = $( '[data-photo-more]', root );

	let config = {
		key_param: KEY_PARAM_DEFAULT,
		max_files: 40,
		max_bytes: 15728640,
	};
	try {
		config = Object.assign(
			config,
			JSON.parse( root.dataset.config || '{}' )
		);
	} catch ( _err ) {
		// keep defaults
	}

	const key = keyFromUrl( config.key_param );
	const items = [];
	let nextId = 1;
	let batch = null; // { id, token }
	let busy = false;

	const showResult = ( msg, ok ) => {
		if ( ! resultEl ) {
			return;
		}
		resultEl.hidden = ! msg;
		resultEl.textContent = msg;
		resultEl.classList.toggle( 'is-success', !! ok );
		resultEl.classList.toggle( 'is-error', ! ok );
	};

	const showGate = () => {
		if ( checking ) {
			checking.hidden = true;
		}
		if ( gate ) {
			gate.hidden = false;
		}
	};

	/* ---------------------------------------------------------------------- */
	/* Selection                                                               */
	/* ---------------------------------------------------------------------- */

	const lists = {};
	root.querySelectorAll( '[data-photo-list]' ).forEach( ( el ) => {
		lists[ el.dataset.photoList ] = el;
	} );

	function statusText( item ) {
		switch ( item.status ) {
			case 'preparing':
				return t( 'status-shrinking', 'Reduciendo…' );
			case 'uploading':
				return fill( t( 'status-uploading', 'Subiendo {pct}%' ), {
					pct: item.progress,
				} );
			case 'done':
				return t( 'status-done', 'Subida' );
			case 'error':
				return `${ t( 'status-error', 'Error' ) }${
					item.error ? ` · ${ item.error }` : ''
				}`;
			default:
				return t( 'status-pending', 'Pendiente' );
		}
	}

	function render() {
		Object.keys( lists ).forEach( ( kind ) => {
			const list = lists[ kind ];
			list.textContent = '';
			items
				.filter( ( item ) => item.kind === kind )
				.forEach( ( item ) => {
					const li = document.createElement( 'li' );
					li.className = `photo-item is-${ item.status }`;
					li.dataset.photoItem = String( item.id );

					const thumb = document.createElement( 'img' );
					thumb.className = 'photo-item__thumb';
					thumb.alt = '';
					thumb.src = item.previewUrl;

					const body = document.createElement( 'div' );
					body.className = 'photo-item__body';
					const name = document.createElement( 'span' );
					name.className = 'photo-item__name';
					name.textContent = item.file.name;
					const meta = document.createElement( 'span' );
					meta.className = 'photo-item__meta';
					meta.textContent = `${ formatBytes(
						item.file.size
					) } · ${ statusText( item ) }`;
					const bar = document.createElement( 'span' );
					bar.className = 'photo-item__bar';
					const fillEl = document.createElement( 'span' );
					fillEl.className = 'photo-item__bar-fill';
					fillEl.style.width = `${
						item.status === 'done' ? 100 : item.progress || 0
					}%`;
					bar.appendChild( fillEl );
					body.append( name, meta, bar );

					li.append( thumb, body );

					if (
						item.status === 'pending' ||
						item.status === 'error'
					) {
						const remove = document.createElement( 'button' );
						remove.type = 'button';
						remove.className = 'photo-item__remove';
						remove.textContent = t( 'btn-remove', 'Quitar' );
						remove.setAttribute(
							'aria-label',
							`${ t( 'btn-remove', 'Quitar' ) } ${
								item.file.name
							}`
						);
						remove.addEventListener( 'click', () =>
							removeItem( item.id )
						);
						li.appendChild( remove );
					}
					list.appendChild( li );
				} );
		} );
	}

	function removeItem( id ) {
		const index = items.findIndex( ( item ) => item.id === id );
		if ( index === -1 ) {
			return;
		}
		URL.revokeObjectURL( items[ index ].previewUrl );
		items.splice( index, 1 );
		render();
	}

	function addFiles( fileList, kind ) {
		const files = Array.from( fileList || [] );
		const errors = [];
		if ( kind === 'profile' ) {
			// One profile photo: a new pick replaces the previous one.
			items
				.filter(
					( item ) =>
						item.kind === 'profile' && item.status !== 'done'
				)
				.forEach( ( item ) => removeItem( item.id ) );
		}
		files.forEach( ( file ) => {
			if (
				! /^image\//.test( file.type ) &&
				! /\.(heic|heif)$/i.test( file.name )
			) {
				errors.push(
					fill( t( 'msg-not-image', '{name} no es una imagen.' ), {
						name: file.name,
					} )
				);
				return;
			}
			const duplicate = items.some(
				( item ) =>
					item.file.name === file.name &&
					item.file.size === file.size &&
					item.file.lastModified === file.lastModified
			);
			if ( duplicate ) {
				return;
			}
			if ( items.length >= config.max_files ) {
				errors.push(
					fill(
						t( 'msg-too-many', 'Máximo {max} fotos por envío.' ),
						{ max: config.max_files }
					)
				);
				return;
			}
			items.push( {
				id: nextId++,
				file,
				kind,
				status: 'pending',
				progress: 0,
				error: '',
				previewUrl: URL.createObjectURL( file ),
				attachmentId: 0,
			} );
			if ( kind === 'profile' ) {
				// only the first file of a profile pick counts
			}
		} );
		render();
		showResult( errors.length ? errors[ 0 ] : '', false );
	}

	root.querySelectorAll( '[data-photo-input]' ).forEach( ( input ) => {
		input.addEventListener( 'change', () => {
			addFiles( input.files, input.dataset.photoInput );
			input.value = '';
		} );
	} );

	/* ---------------------------------------------------------------------- */
	/* Upload                                                                  */
	/* ---------------------------------------------------------------------- */

	const setBusy = ( value ) => {
		busy = value;
		form.querySelectorAll( 'input, select, textarea' ).forEach( ( el ) => {
			el.disabled = value;
		} );
		if ( submitBtn ) {
			submitBtn.disabled = value;
		}
		if ( retryBtn ) {
			retryBtn.disabled = value;
		}
	};

	window.addEventListener( 'beforeunload', ( e ) => {
		if ( busy ) {
			e.preventDefault();
			e.returnValue = '';
		}
	} );

	// Read before the fields are disabled for the upload: FormData skips disabled controls.
	function readDetails() {
		const fields = new FormData( form );
		return {
			name: ( fields.get( 'name' ) || '' ).toString().trim(),
			contact: ( fields.get( 'contact' ) || '' ).toString().trim(),
			sede_id: Number( fields.get( 'sede_id' ) || 0 ),
			profesor_id: Number( fields.get( 'profesor_id' ) || 0 ),
			note: ( fields.get( 'note' ) || '' ).toString().trim(),
			website: ( fields.get( 'website' ) || '' ).toString(),
		};
	}

	async function ensureBatch( details ) {
		if ( batch ) {
			return batch;
		}
		const { res, json } = await postJson( 'photos/batches', {
			key,
			...details,
		} );
		if ( ! res.ok || ! json.ok ) {
			throw new Error(
				json.error ||
					t( 'msg-error', 'No se pudo enviar. Intenta de nuevo.' )
			);
		}
		batch = { id: json.batch_id, token: json.token };
		if ( json.max_bytes ) {
			config.max_bytes = json.max_bytes;
		}
		return batch;
	}

	async function uploadItem( item, position, total ) {
		item.status = 'preparing';
		item.error = '';
		render();
		showResult(
			fill( t( 'msg-uploading', 'Subiendo foto {n} de {total}…' ), {
				n: position,
				total,
			} ),
			true
		);

		let file;
		try {
			file = await prepare( item.file, config.max_bytes );
		} catch ( _err ) {
			item.status = 'error';
			item.error = fill(
				t( 'msg-too-big', '{name} pesa demasiado (máximo {max}).' ),
				{
					name: item.file.name,
					max: formatBytes( config.max_bytes ),
				}
			);
			render();
			return false;
		}

		const body = new FormData();
		body.append( 'token', batch.token );
		body.append( 'kind', item.kind );
		body.append( 'file', file, file.name );

		for ( let attempt = 1; attempt <= 2; attempt++ ) {
			item.status = 'uploading';
			item.progress = 0;
			render();
			try {
				const { status, json } = await upload(
					`photos/batches/${ batch.id }/files`,
					body,
					( pct ) => {
						item.progress = pct;
						render();
					}
				);
				if ( status >= 200 && status < 300 && json.ok ) {
					item.status = 'done';
					item.progress = 100;
					item.attachmentId = json.attachment_id || 0;
					render();
					return true;
				}
				item.error = json.error || `HTTP ${ status }`;
				if (
					json.expired ||
					( status >= 400 && status < 500 && status !== 429 )
				) {
					break; // the server said no; retrying the same request will not help
				}
			} catch ( _err ) {
				item.error = t(
					'msg-error',
					'No se pudo enviar. Intenta de nuevo.'
				);
			}
		}
		item.status = 'error';
		render();
		return false;
	}

	async function run( details ) {
		const queue = items.filter( ( item ) => item.status !== 'done' );
		const total = items.length;
		let position = items.length - queue.length;
		for ( const item of queue ) {
			position += 1;
			await uploadItem( item, position, total );
		}

		const failed = items.filter( ( item ) => item.status !== 'done' );
		const uploaded = items.length - failed.length;
		if ( uploaded === 0 ) {
			showResult(
				failed[ 0 ] && failed[ 0 ].error
					? failed[ 0 ].error
					: t( 'msg-error', 'No se pudo enviar. Intenta de nuevo.' ),
				false
			);
			if ( retryBtn ) {
				retryBtn.hidden = false;
			}
			return;
		}

		const { res, json } = await postJson(
			`photos/batches/${ batch.id }/finish`,
			{
				token: batch.token,
			}
		);
		if ( ! res.ok || ! json.ok ) {
			showResult(
				json.error ||
					t( 'msg-error', 'No se pudo enviar. Intenta de nuevo.' ),
				false
			);
			if ( retryBtn ) {
				retryBtn.hidden = false;
			}
			return;
		}

		if ( failed.length ) {
			showResult(
				fill(
					t(
						'msg-partial',
						'{failed} foto(s) no se pudieron subir. Revisa tu conexión y pulsa «Reintentar».'
					),
					{
						failed: failed.length,
					}
				),
				false
			);
			if ( retryBtn ) {
				retryBtn.hidden = false;
			}
			return;
		}

		const name = details.name;
		form.hidden = true;
		if ( doneText ) {
			doneText.textContent = `${ name ? `${ name }: ` : '' }${ fill(
				t(
					'msg-done',
					'Recibimos {count} foto(s). Las revisamos y las añadimos a tu perfil y a tu sede.'
				),
				{ count: json.count || uploaded }
			) }`;
		}
		if ( moreLink ) {
			moreLink.href = window.location.href;
		}
		if ( done ) {
			done.hidden = false;
			done.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		}
	}

	async function start() {
		if ( busy ) {
			return;
		}
		if ( ! form.reportValidity() ) {
			return;
		}
		const details = readDetails();
		if ( ! details.name ) {
			showResult(
				t( 'msg-need-name', 'Escribe tu nombre o apelido.' ),
				false
			);
			form.elements.name.focus();
			return;
		}
		if ( ! items.length ) {
			showResult(
				t( 'msg-need-file', 'Elige al menos una foto.' ),
				false
			);
			return;
		}

		setBusy( true );
		if ( retryBtn ) {
			retryBtn.hidden = true;
		}
		showResult( t( 'msg-starting', 'Preparando el envío…' ), true );
		try {
			await ensureBatch( details );
			await run( details );
		} catch ( err ) {
			showResult(
				err.message ||
					t( 'msg-error', 'No se pudo enviar. Intenta de nuevo.' ),
				false
			);
			if ( batch && retryBtn ) {
				retryBtn.hidden = false;
			}
		} finally {
			setBusy( false );
		}
	}

	form.addEventListener( 'submit', ( e ) => {
		e.preventDefault();
		start();
	} );
	if ( retryBtn ) {
		retryBtn.addEventListener( 'click', () => start() );
	}

	/* ---------------------------------------------------------------------- */
	/* Gate                                                                    */
	/* ---------------------------------------------------------------------- */

	( async () => {
		if ( ! key ) {
			showGate();
			return;
		}
		try {
			const { res, json } = await getJson( 'photos/access', { key } );
			if ( ! res.ok || ! json.ok ) {
				showGate();
				return;
			}
			if ( json.max_bytes ) {
				config.max_bytes = json.max_bytes;
			}
			if ( json.max_files ) {
				config.max_files = json.max_files;
			}
		} catch ( _err ) {
			showGate();
			return;
		}
		if ( checking ) {
			checking.hidden = true;
		}
		form.hidden = false;
	} )();
}

document.querySelectorAll( '[data-photo-root]' ).forEach( initPhotoUpload );
