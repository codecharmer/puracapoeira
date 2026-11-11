/**
 * window.puraAuth — session discovery and signed REST calls for Mi cuenta.
 *
 * Page HTML is cached for everyone, so nothing user-specific is printed in it. Instead the browser
 * asks admin-ajax (never cached, cookie-authenticated) for a fresh REST nonce: a nonce means the
 * visitor is signed in; "0"/400 means they are not.
 */
( function () {
	let noncePromise = null;

	function config() {
		return window.puraConfig || {};
	}

	function restUrl( path ) {
		const base = config().restUrl || '/wp-json/pura/v1/';
		return base.replace( /\/?$/, '/' ) + path;
	}

	function nonce() {
		if ( ! noncePromise ) {
			const url = ( config().ajaxUrl || '/wp-admin/admin-ajax.php' ) + '?action=rest-nonce';
			noncePromise = fetch( url, { credentials: 'same-origin', cache: 'no-store' } )
				.then( ( res ) => ( res.ok ? res.text() : '' ) )
				.then( ( text ) => {
					const value = ( text || '' ).trim();
					return value && value !== '0' && value !== '-1' ? value : null;
				} )
				.catch( () => null );
		}
		return noncePromise;
	}

	/**
	 * Forget the memoized nonce and ask again (after a 403 rest_cookie_invalid_nonce).
	 */
	function refreshNonce() {
		noncePromise = null;
		return nonce();
	}

	async function request( path, options ) {
		const opts = options || {};
		const token = await nonce();
		if ( ! token ) {
			return { res: { ok: false, status: 401 }, json: { ok: false, error: 'login_required', login_required: true } };
		}
		const headers = Object.assign(
			{ Accept: 'application/json', 'X-WP-Nonce': token },
			opts.body ? { 'Content-Type': 'application/json' } : {},
			opts.headers || {}
		);
		const res = await fetch( restUrl( path ), {
			method: opts.method || ( opts.body ? 'POST' : 'GET' ),
			credentials: 'same-origin',
			cache: 'no-store',
			headers,
			body: opts.body ? JSON.stringify( opts.body ) : undefined,
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
	 * POST without a session: the sign-in routes are public.
	 */
	async function publicPost( path, body ) {
		try {
			const res = await fetch( restUrl( path ), {
				method: 'POST',
				credentials: 'same-origin',
				cache: 'no-store',
				headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
				body: JSON.stringify( body ),
			} );
			let json = {};
			try {
				json = await res.json();
			} catch ( _err ) {
				json = {};
			}
			return { res, json };
		} catch ( _err ) {
			return { res: { ok: false, status: 0 }, json: { ok: false, error: 'No se pudo conectar. Revisa tu conexión e intenta de nuevo.' } };
		}
	}

	/**
	 * Sign in with a code sent by email: `[data-email-login]` holds two forms, one for the address
	 * and one for the code (markup from pura_theme_email_login_form()).
	 */
	function initEmailLogin( root ) {
		const emailForm = root.querySelector( '[data-email-step="email"]' );
		const codeForm = root.querySelector( '[data-email-step="code"]' );
		const status = root.querySelector( '[data-email-status]' );
		const shown = root.querySelector( '[data-email-shown]' );
		if ( ! emailForm || ! codeForm ) {
			return;
		}
		let ticket = '';

		const say = ( message ) => {
			if ( ! status ) {
				return;
			}
			status.hidden = ! message;
			status.textContent = message || '';
			status.setAttribute( 'data-tv', '' );
			const i18n = window.puraI18n;
			if ( message && i18n && i18n.loaded && typeof i18n.apply === 'function' ) {
				i18n.apply( root );
			}
		};
		const busy = ( form, on ) => {
			form.querySelectorAll( 'button' ).forEach( ( button ) => {
				button.disabled = on;
			} );
		};
		const step = ( name ) => {
			emailForm.hidden = name !== 'email';
			codeForm.hidden = name !== 'code';
		};
		const target = () =>
			new URLSearchParams( window.location.search ).get( 'redirect_to' ) || root.dataset.redirect || '';

		emailForm.addEventListener( 'submit', async ( event ) => {
			event.preventDefault();
			const email = emailForm.elements.email.value.trim();
			if ( ! email ) {
				emailForm.elements.email.focus();
				return;
			}
			say( '' );
			busy( emailForm, true );
			const { res, json } = await publicPost( 'auth/email-code/start', {
				email,
				redirect_to: target(),
				lang: ( window.puraI18n && window.puraI18n.lang ) || 'es',
			} );
			busy( emailForm, false );
			if ( ! res.ok || ! json.ok || ! json.ticket ) {
				say( json.error || 'No pudimos enviar el correo. Intenta de nuevo en unos minutos.' );
				return;
			}
			ticket = json.ticket;
			if ( shown ) {
				shown.textContent = email;
			}
			codeForm.elements.code.value = '';
			step( 'code' );
			codeForm.elements.code.focus();
		} );

		codeForm.addEventListener( 'submit', async ( event ) => {
			event.preventDefault();
			const code = codeForm.elements.code.value.replace( /\D+/g, '' );
			if ( code.length !== 6 ) {
				say( 'El código tiene 6 dígitos.' );
				codeForm.elements.code.focus();
				return;
			}
			say( '' );
			busy( codeForm, true );
			const { res, json } = await publicPost( 'auth/email-code/verify', { ticket, code } );
			if ( res.ok && json.ok ) {
				// Signed in: the cookie is set. Load the destination fresh so it sees the session.
				const to = json.redirectTo || window.location.href;
				if ( to.split( '#' )[ 0 ] === window.location.href.split( '#' )[ 0 ] ) {
					window.location.reload();
				} else {
					window.location.assign( to );
				}
				return;
			}
			busy( codeForm, false );
			if ( json.code === 'wrong' ) {
				say( json.error );
				codeForm.elements.code.select();
				return;
			}
			// Expired, used up or refused: start over with a new code.
			ticket = '';
			step( 'email' );
			say( json.error || 'El código ya no sirve. Pide uno nuevo.' );
		} );

		const back = root.querySelector( '[data-email-back]' );
		if ( back ) {
			back.addEventListener( 'click', () => {
				ticket = '';
				say( '' );
				step( 'email' );
				emailForm.elements.email.focus();
			} );
		}
	}

	window.puraAuth = {
		nonce,
		refreshNonce,
		request,
		me: () => request( 'account/me' ),
		isLoggedIn: () => nonce().then( ( token ) => !! token ),
	};

	document.querySelectorAll( '[data-email-login]' ).forEach( initEmailLogin );
} )();
