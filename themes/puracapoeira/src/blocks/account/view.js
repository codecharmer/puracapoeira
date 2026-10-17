/**
 * Mi cuenta: discover the session (puraAuth), render the portal from /account/me and show the
 * sign-in error banners the auth callback redirects back with.
 */

const $ = ( sel, ctx = document ) => ctx.querySelector( sel );

const AUTH_ERRORS = {
	denied: 'Cancelaste el inicio de sesión. Puedes intentarlo de nuevo cuando quieras.',
	state: 'La sesión de inicio expiró. Vuelve a intentarlo.',
	code: 'No recibimos la autorización del proveedor. Vuelve a intentarlo.',
	exchange:
		'El proveedor no confirmó tu identidad. Vuelve a intentarlo en unos minutos.',
	token: 'No pudimos verificar tu identidad con el proveedor.',
	admin_account:
		'Ese correo pertenece a una cuenta del equipo. Entra por el acceso de administración.',
	user_create: 'No se pudo crear tu cuenta. Escríbenos por WhatsApp.',
	provider: 'Este método de acceso no está disponible.',
};

const DATE_LOCALES = { es: 'es-MX', en: 'en-US', pt: 'pt-BR' };

function dateLocale() {
	const lang = ( window.puraI18n && window.puraI18n.lang ) || 'es';
	return DATE_LOCALES[ lang ] || DATE_LOCALES.es;
}

function formatDate( mysql ) {
	if ( ! mysql ) {
		return '—';
	}
	const date = new Date( mysql.replace( ' ', 'T' ) );
	if ( Number.isNaN( date.getTime() ) ) {
		return mysql.slice( 0, 10 );
	}
	return date.toLocaleDateString( dateLocale(), {
		year: 'numeric',
		month: 'short',
		day: 'numeric',
	} );
}

/**
 * Re-translate dynamic rows once the language layer is up (it only sees the initial DOM).
 *
 * @param {HTMLElement} root Block root.
 */
function translate( root ) {
	const i18n = window.puraI18n;
	if ( i18n && i18n.loaded && typeof i18n.apply === 'function' ) {
		i18n.apply( root );
	}
}

function initAccount( root ) {
	const notice = $( '[data-account-notice]', root );
	const loading = $( '[data-account-loading]', root );
	const loggedOut = $( '[data-account-logged-out]', root );
	const portal = $( '[data-account-portal]', root );

	const show = ( message, ok ) => {
		if ( ! notice ) {
			return;
		}
		notice.hidden = false;
		notice.textContent = message;
		notice.setAttribute( 'data-tv', '' );
		notice.classList.toggle( 'is-success', !! ok );
		notice.classList.toggle( 'is-error', ! ok );
		translate( notice );
	};

	const setState = ( state ) => {
		root.dataset.accountState = state;
		if ( loading ) {
			loading.hidden = state !== 'pending';
		}
		if ( loggedOut ) {
			loggedOut.hidden = state !== 'logged-out';
		}
		if ( portal ) {
			portal.hidden = state !== 'logged-in';
		}
	};

	// Return banners (auth errors) and the redirect target for the provider forms.
	const params = new URLSearchParams( window.location.search );
	const authError = params.get( 'auth_error' );
	const redirectTo = params.get( 'redirect_to' );
	if ( authError ) {
		show(
			AUTH_ERRORS[ authError ] ||
				'No se pudo iniciar sesión. Vuelve a intentarlo.',
			false
		);
	}
	if ( redirectTo ) {
		root.querySelectorAll( '[data-account-redirect]' ).forEach(
			( input ) => {
				input.value = redirectTo;
			}
		);
	}
	if ( authError ) {
		params.delete( 'auth_error' );
		const query = params.toString();
		window.history.replaceState(
			{},
			'',
			window.location.pathname + ( query ? `?${ query }` : '' )
		);
	}

	if ( ! window.puraAuth ) {
		setState( 'logged-out' );
		return;
	}

	function renderRows( rows ) {
		const body = $( '[data-account-history-rows]', root );
		if ( ! body ) {
			return;
		}
		const table = $( '[data-account-history-table]', root );
		const empty = $( '[data-account-history-empty]', root );
		body.textContent = '';
		rows.forEach( ( row ) => {
			const tr = document.createElement( 'tr' );

			const event = document.createElement( 'td' );
			event.className = 'account-history__event';
			event.textContent = row.eventName || row.eventSlug || '—';
			tr.appendChild( event );

			const date = document.createElement( 'td' );
			date.textContent = formatDate( row.date );
			tr.appendChild( date );

			const status = document.createElement( 'td' );
			const pill = document.createElement( 'span' );
			pill.className = `status-pill status-pill--${ row.status }`;
			pill.textContent = row.statusLabel || row.status;
			pill.setAttribute( 'data-tv', '' );
			status.appendChild( pill );
			tr.appendChild( status );

			const proof = document.createElement( 'td' );
			const proofText = document.createElement( 'span' );
			proofText.className = row.hasProof
				? 'account-history__proof is-attached'
				: 'account-history__proof';
			proofText.textContent = row.hasProof ? 'Adjuntado' : 'Pendiente';
			proofText.setAttribute( 'data-tv', '' );
			proof.appendChild( proofText );
			tr.appendChild( proof );

			body.appendChild( tr );
		} );
		if ( table ) {
			table.hidden = rows.length === 0;
		}
		if ( empty ) {
			empty.hidden = rows.length > 0;
		}
	}

	function render( data ) {
		const name = $( '[data-account-name]', root );
		const email = $( '[data-account-email]', root );
		const privateNote = $( '[data-account-private]', root );
		const logout = $( '[data-account-logout]', root );
		const whatsapp = $( '[data-account-whatsapp]', root );
		const user = data.user || {};

		if ( name ) {
			name.textContent = user.firstName || user.displayName || '';
		}
		if ( email ) {
			email.textContent = user.email || '';
			email.hidden = ! user.email;
		}
		if ( privateNote ) {
			privateNote.hidden = ! user.privateEmail;
		}
		if ( logout && data.logoutUrl ) {
			logout.href = data.logoutUrl;
		}
		if ( whatsapp && data.whatsappUrl ) {
			whatsapp.href = data.whatsappUrl;
		}
		renderRows( data.registrations || [] );
		setState( 'logged-in' );
		translate( root );
	}

	async function load() {
		const { res, json } = await window.puraAuth.me();
		if ( ! res.ok || ! json.ok ) {
			setState( 'logged-out' );
			return;
		}
		render( json );
	}

	load();
}

document.querySelectorAll( '[data-account-root]' ).forEach( initAccount );
