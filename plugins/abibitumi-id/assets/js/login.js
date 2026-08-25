( function () {
	function text( key ) {
		return ( window.ABIDLogin && window.ABIDLogin.i18n && window.ABIDLogin.i18n[ key ] ) || key;
	}

	function api( path, body ) {
		return fetch( window.ABIDLogin.api + path, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			credentials: 'same-origin',
			body: JSON.stringify( body )
		} ).then( function ( response ) {
			return response.json().then( function ( data ) {
				if ( ! response.ok ) {
					throw new Error( data.message || text( 'tryAgain' ) );
				}
				return data;
			} );
		} );
	}

	function init( root ) {
		var form = root.querySelector( 'form' );
		var phone = root.querySelector( '[name="phone"]' );
		var code = root.querySelector( '[name="code"]' );
		var codeWrap = root.querySelector( '.abid-login__code' );
		var submit = root.querySelector( '[type="submit"]' );
		var change = root.querySelector( '[data-action="change"]' );
		var status = root.querySelector( '.abid-login__status' );
		var sentPhone = '';
		var labelStart = submit.dataset.labelStart || 'Continue';
		var labelVerify = submit.dataset.labelVerify || 'Sign in';

		function setStep( step ) {
			form.dataset.step = step;
			codeWrap.hidden = step !== 'code';
			change.hidden = step !== 'code';
			submit.textContent = step === 'code' ? labelVerify : labelStart;
			if ( step === 'code' ) {
				code.focus();
			} else {
				phone.focus();
			}
		}

		function setBusy( busy, message ) {
			submit.disabled = busy;
			phone.disabled = busy || form.dataset.step === 'code';
			code.disabled = busy;
			status.textContent = message || '';
		}

		change.addEventListener( 'click', function () {
			sentPhone = '';
			code.value = '';
			phone.disabled = false;
			setStep( 'phone' );
			status.textContent = '';
		} );

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			if ( form.dataset.step !== 'code' ) {
				if ( ! phone.value.trim() ) {
					status.textContent = text( 'phoneRequired' );
					return;
				}
				setBusy( true, text( 'sending' ) );
				api( '/phone/start', { phone: phone.value } ).then( function ( data ) {
					sentPhone = data.phone || phone.value;
					phone.value = sentPhone;
					setStep( 'code' );
					setBusy( false, text( 'sent' ) );
				} ).catch( function ( error ) {
					setBusy( false, error.message );
				} );
				return;
			}

			if ( ! code.value.trim() ) {
				status.textContent = text( 'codeRequired' );
				return;
			}
			setBusy( true, text( 'verifying' ) );
			api( '/phone/verify', { phone: sentPhone || phone.value, code: code.value } ).then( function () {
				status.textContent = text( 'ready' );
				if ( window.ABIDLogin.redirect ) {
					window.location.href = window.ABIDLogin.redirect;
				} else {
					window.location.reload();
				}
			} ).catch( function ( error ) {
				setBusy( false, error.message );
			} );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '[data-abid-login]' ).forEach( init );
	} );
}() );
