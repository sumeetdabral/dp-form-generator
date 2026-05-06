/**
 * DP-Forms-var01 — Frontend Form Handler
 *
 * Vanilla JS (ES2017+). No jQuery dependency.
 * Handles: client-side validation, FormData AJAX submit, inline messages.
 *
 * Localised data available on window.wpfbData (via wp_localize_script):
 *   ajaxUrl, allowedMimes[], maxSizeMb, i18n{}
 *
 * Per-form nonce comes from the <form data-nonce="..."> attribute so multiple
 * forms on the same page each have their own independent nonce.
 *
 * @package WPFB
 */
( function () {
	'use strict';

	const globalData  = window.wpfbData || {};
	const i18n        = globalData.i18n || {};
	const allowedExts = ( globalData.allowedMimes || [] ).map( function ( e ) {
		return e.toLowerCase().replace( /^\./, '' );
	} );
	const maxBytes = ( parseInt( globalData.maxSizeMb, 10 ) || 5 ) * 1024 * 1024;

	/* ------------------------------------------------------------------
	 * Initialise each form on the page
	 * ------------------------------------------------------------------ */
	document.querySelectorAll( '.wpfb-form' ).forEach( initForm );

	function initForm( form ) {
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			handleSubmit( form );
		} );
	}

	/* ------------------------------------------------------------------
	 * Submit handler
	 * ------------------------------------------------------------------ */
	function handleSubmit( form ) {
		clearErrors( form );

		if ( ! clientValidate( form ) ) {
			return;
		}

		const submitBtn = form.querySelector( '.wpfb-submit-btn' );
		const msgWrap   = form.querySelector( '.wpfb-form-messages' );
		const formId    = form.dataset.formId;
		const nonce     = form.dataset.nonce;

		if ( submitBtn ) {
			submitBtn.disabled   = true;
			submitBtn.textContent = i18n.submitting || 'Submitting…';
		}

		const fd = new FormData( form );
		fd.set( 'action',    'wpfb_submit' );
		fd.set( '_wpnonce',  nonce );
		fd.set( 'form_id',   formId );

		// Rename fields: HTML inputs are named by field_id; server expects the same.
		// FormData already captures the input name attributes, so no renaming needed.

		fetch( globalData.ajaxUrl, {
			method: 'POST',
			body:   fd,
		} )
		.then( function ( r ) { return r.json(); } )
		.then( function ( resp ) {
			if ( resp.success ) {
				showMessage( msgWrap, ( resp.data && resp.data.message ) || 'Thank you!', 'success' );
				form.reset();
			} else {
				const data = resp.data || {};
				// Show field-level errors if returned.
				if ( data.errors ) {
					Object.entries( data.errors ).forEach( function ( [ fieldId, msg ] ) {
						showFieldError( form, fieldId, msg );
					} );
				}
				showMessage( msgWrap, data.message || ( i18n.error || 'An error occurred.' ), 'error' );
			}
		} )
		.catch( function () {
			showMessage( msgWrap, i18n.error || 'An unexpected error occurred. Please try again.', 'error' );
		} )
		.finally( function () {
			if ( submitBtn ) {
				submitBtn.disabled    = false;
				submitBtn.textContent = 'Submit';
			}
		} );
	}

	/* ------------------------------------------------------------------
	 * Client-side validation
	 * Returns false if any error was found; true if all clear.
	 * ------------------------------------------------------------------ */
	function clientValidate( form ) {
		let valid = true;

		// Required text / email / select fields.
		form.querySelectorAll( '[required]' ).forEach( function ( field ) {
			if ( ! validateField( form, field ) ) {
				valid = false;
			}
		} );

		// File fields with extension/size constraints.
		form.querySelectorAll( 'input[type="file"]' ).forEach( function ( fileInput ) {
			if ( ! validateFileInput( form, fileInput ) ) {
				valid = false;
			}
		} );

		return valid;
	}

	function validateField( form, field ) {
		const name  = field.name;
		const value = field.value.trim();
		const type  = field.type;

		if ( field.required && '' === value ) {
			showFieldError( form, name, i18n.required || 'This field is required.' );
			return false;
		}

		if ( 'email' === type && '' !== value ) {
			if ( ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( value ) ) {
				showFieldError( form, name, i18n.invalidEmail || 'Please enter a valid email address.' );
				return false;
			}
		}

		return true;
	}

	function validateFileInput( form, fileInput ) {
		const name     = fileInput.name;
		const files    = fileInput.files;
		const required = fileInput.required;

		if ( required && ( ! files || 0 === files.length ) ) {
			showFieldError( form, name, i18n.required || 'This field is required.' );
			return false;
		}

		if ( ! files || 0 === files.length ) {
			return true; // Optional, no file chosen — ok.
		}

		const file = files[ 0 ];

		// Size check.
		if ( file.size > maxBytes ) {
			showFieldError( form, name, i18n.fileTooLarge || 'File is too large.' );
			return false;
		}

		// Extension check.
		if ( allowedExts.length > 0 ) {
			const ext = file.name.split( '.' ).pop().toLowerCase();
			if ( ! allowedExts.includes( ext ) ) {
				showFieldError( form, name, i18n.fileType || 'File type is not allowed.' );
				return false;
			}
		}

		return true;
	}

	/* ------------------------------------------------------------------
	 * UI helpers
	 * ------------------------------------------------------------------ */
	function showFieldError( form, fieldId, message ) {
		// Find the field wrapper by matching name or id.
		// For checkboxes the HTML name is "{fieldId}[]", so we try both selectors.
		const escaped = CSS.escape( fieldId );
		const input   = form.querySelector( '[name="' + escaped + '"], [name="' + escaped + '[]"]' );
		const wrapper = input ? input.closest( '.wpfb-field' ) : null;
		if ( ! wrapper ) return;

		const errorEl = wrapper.querySelector( '.wpfb-field-error' );
		if ( errorEl ) {
			errorEl.textContent = message;
		}

		if ( input ) {
			input.setAttribute( 'aria-invalid', 'true' );
		}
		wrapper.classList.add( 'wpfb-field--error' );
	}

	function clearErrors( form ) {
		form.querySelectorAll( '.wpfb-field-error' ).forEach( function ( el ) {
			el.textContent = '';
		} );
		form.querySelectorAll( '.wpfb-field--error' ).forEach( function ( el ) {
			el.classList.remove( 'wpfb-field--error' );
		} );
		form.querySelectorAll( '[aria-invalid]' ).forEach( function ( el ) {
			el.removeAttribute( 'aria-invalid' );
		} );
		const msgWrap = form.querySelector( '.wpfb-form-messages' );
		if ( msgWrap ) msgWrap.textContent = '';
	}

	function showMessage( container, message, type ) {
		if ( ! container ) return;
		container.textContent = '';
		container.className   = 'wpfb-form-messages wpfb-message-' + type;
		container.textContent = message;
		container.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
	}
}() );
