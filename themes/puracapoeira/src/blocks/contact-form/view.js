/**
 * Contact form: POST JSON to the plugin's REST endpoint and show the result inline.
 */

function translate( key, fallback ) {
	const api = window.puraI18n;
	if ( api && typeof api.t === 'function' ) {
		const value = api.t( 'common.' + key );
		if ( typeof value === 'string' && value !== '' ) {
			return value;
		}
	}
	return fallback;
}

function initContactForm( form ) {
	const endpoint = form.dataset.endpoint;
	const status = form.querySelector( '.contact-form__status' );
	const button = form.querySelector( 'button[type="submit"]' );
	const ts = form.querySelector( 'input[name="ts"]' );

	if ( ts ) {
		ts.value = String( Date.now() );
	}

	function setStatus( text, variant ) {
		if ( ! status ) {
			return;
		}
		status.textContent = text;
		status.hidden = text === '';
		status.classList.toggle( 'is-success', variant === 'success' );
		status.classList.toggle( 'is-error', variant === 'error' );
	}

	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();
		if ( ! form.reportValidity() ) {
			return;
		}

		const data = Object.fromEntries( new FormData( form ).entries() );
		data.ts = Number( data.ts ) || 0;

		setStatus( translate( 'sending', form.dataset.sending ), '' );
		if ( button ) {
			button.disabled = true;
		}

		try {
			const response = await fetch( endpoint, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					Accept: 'application/json',
				},
				body: JSON.stringify( data ),
			} );
			const json = await response.json().catch( () => ( {} ) );

			if ( response.ok && json && json.ok ) {
				form.reset();
				if ( ts ) {
					ts.value = String( Date.now() );
				}
				setStatus( translate( 'sent', form.dataset.sent ), 'success' );
			} else {
				setStatus(
					( json && json.error ) ||
						translate( 'sendError', form.dataset.error ),
					'error'
				);
			}
		} catch ( e ) {
			setStatus( translate( 'sendError', form.dataset.error ), 'error' );
		} finally {
			if ( button ) {
				button.disabled = false;
			}
		}
	} );
}

document
	.querySelectorAll( 'form[data-js="contact-form"]' )
	.forEach( initContactForm );
