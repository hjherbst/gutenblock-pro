/**
 * GutenBlock Pro – Contact Form frontend.
 *
 * Handles client-side validation, AJAX submit, double-submit protection and
 * replacing the form with the success message. Supports multiple forms per
 * page. Configuration comes from the localized `gutenblockProContactForm`.
 */
( function () {
	'use strict';

	var config = window.gutenblockProContactForm || {};
	var strings = config.strings || {};

	function setFieldError( field, message ) {
		clearFieldError( field );
		field.setAttribute( 'aria-invalid', 'true' );
		var msg = document.createElement( 'span' );
		msg.className = 'gbp-cf-error';
		msg.textContent = message;
		var wrap = field.closest( '.gbp-cf-field' ) || field.parentNode;
		wrap.appendChild( msg );
	}

	function clearFieldError( field ) {
		field.removeAttribute( 'aria-invalid' );
		var wrap = field.closest( '.gbp-cf-field' ) || field.parentNode;
		var existing = wrap.querySelector( '.gbp-cf-error' );
		if ( existing ) {
			existing.remove();
		}
	}

	function isValidEmail( value ) {
		return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( value );
	}

	function validate( form ) {
		var valid = true;
		var firstInvalid = null;

		var requiredFields = form.querySelectorAll( '[required]' );
		requiredFields.forEach( function ( field ) {
			clearFieldError( field );

			if ( field.type === 'checkbox' ) {
				if ( ! field.checked ) {
					setFieldError( field, strings.errConsent || 'Required' );
					valid = false;
					firstInvalid = firstInvalid || field;
				}
				return;
			}

			var value = ( field.value || '' ).trim();
			if ( value === '' ) {
				setFieldError( field, strings.errRequired || 'Required' );
				valid = false;
				firstInvalid = firstInvalid || field;
				return;
			}

			if ( field.type === 'email' && ! isValidEmail( value ) ) {
				setFieldError( field, strings.errEmail || 'Invalid email' );
				valid = false;
				firstInvalid = firstInvalid || field;
			}
		} );

		// Email field may not be marked required in markup edge cases; double-check.
		var email = form.querySelector( 'input[name="email"]' );
		if ( email && ( email.value || '' ).trim() !== '' && ! isValidEmail( email.value.trim() ) ) {
			setFieldError( email, strings.errEmail || 'Invalid email' );
			valid = false;
			firstInvalid = firstInvalid || email;
		}

		if ( firstInvalid ) {
			firstInvalid.focus();
		}
		return valid;
	}

	function showFeedback( wrap, message, isError ) {
		var box = wrap.querySelector( '.gbp-cf-feedback' );
		if ( ! box ) {
			return;
		}
		box.textContent = message;
		box.classList.toggle( 'gbp-cf-feedback--error', !! isError );
		box.classList.toggle( 'gbp-cf-feedback--success', ! isError );
		box.hidden = false;
	}

	function replaceWithSuccess( wrap, message ) {
		var success = document.createElement( 'div' );
		success.className = 'gbp-cf-success';
		success.setAttribute( 'role', 'status' );
		success.setAttribute( 'tabindex', '-1' );
		success.textContent = message;
		wrap.innerHTML = '';
		wrap.appendChild( success );
		success.focus();
	}

	function handleSubmit( e ) {
		e.preventDefault();
		var form = e.currentTarget;
		var wrap = form.closest( '.gbp-contact-form-wrap' );
		var button = form.querySelector( '.gbp-cf-submit' );

		if ( form.dataset.submitting === '1' ) {
			return;
		}
		if ( ! validate( form ) ) {
			return;
		}

		// Double-submit protection.
		form.dataset.submitting = '1';
		if ( button ) {
			button.disabled = true;
			button.setAttribute( 'aria-busy', 'true' );
			button.classList.add( 'is-busy' );
			var label = button.querySelector( '.gbp-cf-submit-label' );
			if ( label && strings.sending ) {
				button.dataset.originalLabel = label.textContent;
				label.textContent = strings.sending;
			}
		}

		var data = new FormData( form );

		fetch( config.restUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': config.nonce || '' },
			body: data
		} )
			.then( function ( res ) {
				return res.json().then( function ( body ) {
					return { ok: res.ok, status: res.status, body: body };
				} );
			} )
			.then( function ( result ) {
				if ( result.ok ) {
					var successMsg = ( wrap && wrap.dataset.success ) || ( result.body && result.body.message ) || '';
					replaceWithSuccess( wrap, successMsg );
					return;
				}

				// Field-level errors.
				if ( result.body && result.body.errors ) {
					Object.keys( result.body.errors ).forEach( function ( key ) {
						var field = form.querySelector( '[name="' + key + '"]' );
						if ( field ) {
							setFieldError( field, result.body.errors[ key ] );
						}
					} );
				}
				var msg = ( result.body && result.body.message ) || strings.errGeneric || 'Error';
				showFeedback( wrap, msg, true );
				resetButton( form, button );
			} )
			.catch( function () {
				showFeedback( wrap, strings.errGeneric || 'Error', true );
				resetButton( form, button );
			} );
	}

	function resetButton( form, button ) {
		form.dataset.submitting = '';
		if ( ! button ) {
			return;
		}
		button.disabled = false;
		button.removeAttribute( 'aria-busy' );
		button.classList.remove( 'is-busy' );
		var label = button.querySelector( '.gbp-cf-submit-label' );
		if ( label && button.dataset.originalLabel ) {
			label.textContent = button.dataset.originalLabel;
		}
	}

	function init() {
		var forms = document.querySelectorAll( '.gbp-contact-form-wrap .gbp-contact-form' );
		forms.forEach( function ( form ) {
			form.addEventListener( 'submit', handleSubmit );
			// Clear field error as the user corrects it.
			form.addEventListener( 'input', function ( ev ) {
				if ( ev.target.matches( 'input, textarea' ) ) {
					clearFieldError( ev.target );
				}
			} );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
