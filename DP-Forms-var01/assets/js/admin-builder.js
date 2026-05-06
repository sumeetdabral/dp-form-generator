/**
 * DP-Forms-var01 — Admin Form Builder
 *
 * Vanilla JS (ES2017+). No jQuery dependency.
 * Handles: add/remove fields, drag-and-drop reorder, save via fetch().
 *
 * Localised data is available on window.wpfbBuilder (via wp_localize_script):
 *   ajaxUrl, nonce, fieldTypes[], i18n{}
 *
 * @package WPFB
 */
( function () {
	'use strict';

	/* ------------------------------------------------------------------
	 * DOM references
	 * ------------------------------------------------------------------ */
	const wrap        = document.getElementById( 'wpfb-builder-wrap' );
	const fieldsList  = document.getElementById( 'wpfb-fields-list' );
	const saveBtn     = document.getElementById( 'wpfb-save-form' );
	const saveStatus  = document.getElementById( 'wpfb-save-status' );
	const noNotice    = document.getElementById( 'wpfb-no-fields-notice' );
	const scResult    = document.getElementById( 'wpfb-shortcode-result' );
	const scCode      = document.getElementById( 'wpfb-result-shortcode' );
	const copyResult  = document.querySelector( '.wpfb-copy-result' );

	if ( ! wrap ) return; // Not on the builder page.

	const data       = window.wpfbBuilder || {};
	const i18n       = data.i18n || {};
	const formId     = parseInt( wrap.dataset.formId, 10 ) || 0;
	let   savedFormId = formId;

	/* ------------------------------------------------------------------
	 * Counter for generating unique temporary field IDs
	 * ------------------------------------------------------------------ */
	let fieldCounter = 0;

	function uniqueId() {
		return 'field_' + Date.now() + '_' + ( ++fieldCounter );
	}

	/* ------------------------------------------------------------------
	 * Types that need an options textarea (one per line)
	 * ------------------------------------------------------------------ */
	const OPTION_TYPES = [ 'select', 'radio', 'checkboxes' ];

	/* ------------------------------------------------------------------
	 * Render a field row <li> into the list
	 * ------------------------------------------------------------------ */
	function createFieldRow( type, fieldDef ) {
		const id           = fieldDef.id           || uniqueId();
		const label        = fieldDef.label        || '';
		const required     = !! fieldDef.required;
		const options      = fieldDef.options      || [];
		const htmlBefore   = fieldDef.html_before  || '';
		const htmlAfter    = fieldDef.html_after   || '';
		const cssClass     = fieldDef.css_class    || '';
		const wrapperClass = fieldDef.wrapper_class || '';

		const li = document.createElement( 'li' );
		li.className    = 'wpfb-field-row';
		li.dataset.type = type;
		li.dataset.id   = id;
		li.draggable    = true;

		/* -- Type-specific extras (options textarea, number min/max/step, etc.) -- */
		let typeExtras = '';

		if ( OPTION_TYPES.includes( type ) ) {
			typeExtras = '<div class="wpfb-field-options">'
				+ '<label>Options <small>(one per line)</small></label>'
				+ '<textarea class="wpfb-select-options" rows="4">' + escapeHtml( options.join( '\n' ) ) + '</textarea>'
				+ '</div>';
		} else if ( 'number' === type ) {
			typeExtras = '<div class="wpfb-field-options">'
				+ '<label>Min</label>'
				+ '<input type="number" class="wpfb-number-min small-text" value="' + escapeHtml( String( fieldDef.min != null ? fieldDef.min : '' ) ) + '">'
				+ '&nbsp;<label>Max</label>'
				+ '<input type="number" class="wpfb-number-max small-text" value="' + escapeHtml( String( fieldDef.max != null ? fieldDef.max : '' ) ) + '">'
				+ '&nbsp;<label>Step</label>'
				+ '<input type="number" class="wpfb-number-step small-text" value="' + escapeHtml( String( fieldDef.step != null ? fieldDef.step : '' ) ) + '">'
				+ '</div>';
		} else if ( 'date' === type ) {
			typeExtras = '<div class="wpfb-field-options">'
				+ '<label>Min date</label>'
				+ '<input type="date" class="wpfb-date-min" value="' + escapeHtml( String( fieldDef.min || '' ) ) + '">'
				+ '&nbsp;<label>Max date</label>'
				+ '<input type="date" class="wpfb-date-max" value="' + escapeHtml( String( fieldDef.max || '' ) ) + '">'
				+ '</div>';
		} else if ( 'textarea' === type ) {
			typeExtras = '<div class="wpfb-field-options">'
				+ '<label>Rows</label>'
				+ '<input type="number" class="wpfb-textarea-rows small-text" min="1" max="20" value="' + escapeHtml( String( fieldDef.rows || 4 ) ) + '">'
				+ '</div>';
		}

		/* -- Advanced wrapper block (all types) -- */
		const advanced = '<details class="wpfb-advanced-block">'
			+ '<summary>Advanced</summary>'
			+ '<div class="wpfb-advanced-inner">'
			+ '<label class="wpfb-advanced-label">HTML Before'
			+ '<textarea class="wpfb-html-before" rows="2">' + escapeHtml( htmlBefore ) + '</textarea>'
			+ '</label>'
			+ '<label class="wpfb-advanced-label">HTML After'
			+ '<textarea class="wpfb-html-after" rows="2">' + escapeHtml( htmlAfter ) + '</textarea>'
			+ '</label>'
			+ '<label class="wpfb-advanced-label">Field CSS Class'
			+ '<input type="text" class="wpfb-css-class" value="' + escapeHtml( cssClass ) + '">'
			+ '</label>'
			+ '<label class="wpfb-advanced-label">Wrapper CSS Class'
			+ '<input type="text" class="wpfb-wrapper-class" value="' + escapeHtml( wrapperClass ) + '">'
			+ '</label>'
			+ '</div>'
			+ '</details>';

		li.innerHTML = '<span class="wpfb-drag-handle dashicons dashicons-menu" title="Drag to reorder"></span>'
			+ '<span class="wpfb-field-type-badge">' + escapeHtml( type ) + '</span>'
			+ '<input type="text" class="wpfb-field-label" placeholder="Field label" value="' + escapeHtml( label ) + '">'
			+ '<label class="wpfb-required-wrap">'
			+ '<input type="checkbox" class="wpfb-required"' + ( required ? ' checked' : '' ) + '>'
			+ ' Required'
			+ '</label>'
			+ '<button type="button" class="button wpfb-remove-field">Remove</button>'
			+ typeExtras
			+ advanced;

		return li;
	}

	/* ------------------------------------------------------------------
	 * Add field buttons
	 * ------------------------------------------------------------------ */
	document.querySelectorAll( '.wpfb-add-field' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			const type = btn.dataset.type;
			const row  = createFieldRow( type, {} );
			fieldsList.appendChild( row );
			updateNoFieldsNotice();
			// Focus the new label input.
			const input = row.querySelector( '.wpfb-field-label' );
			if ( input ) input.focus();
		} );
	} );

	/* ------------------------------------------------------------------
	 * Remove field (event delegation)
	 * ------------------------------------------------------------------ */
	fieldsList.addEventListener( 'click', function ( e ) {
		if ( e.target.classList.contains( 'wpfb-remove-field' ) ) {
			if ( window.confirm( i18n.confirmDel || 'Remove this field?' ) ) {
				e.target.closest( 'li' ).remove();
				updateNoFieldsNotice();
			}
		}
	} );

	/* ------------------------------------------------------------------
	 * Drag-and-drop reorder (HTML5 native)
	 * ------------------------------------------------------------------ */
	let dragSrc = null;

	fieldsList.addEventListener( 'dragstart', function ( e ) {
		dragSrc = e.target.closest( 'li' );
		if ( ! dragSrc ) return;
		e.dataTransfer.effectAllowed = 'move';
		dragSrc.classList.add( 'wpfb-dragging' );
	} );

	fieldsList.addEventListener( 'dragover', function ( e ) {
		e.preventDefault();
		e.dataTransfer.dropEffect = 'move';
		const target = e.target.closest( 'li' );
		if ( target && target !== dragSrc ) {
			const rect    = target.getBoundingClientRect();
			const midY    = rect.top + rect.height / 2;
			if ( e.clientY < midY ) {
				fieldsList.insertBefore( dragSrc, target );
			} else {
				fieldsList.insertBefore( dragSrc, target.nextSibling );
			}
		}
	} );

	fieldsList.addEventListener( 'dragend', function () {
		if ( dragSrc ) {
			dragSrc.classList.remove( 'wpfb-dragging' );
			dragSrc = null;
		}
	} );

	/* ------------------------------------------------------------------
	 * Serialise fields to JSON array
	 * ------------------------------------------------------------------ */
	function serializeFields() {
		const rows   = fieldsList.querySelectorAll( 'li.wpfb-field-row' );
		const fields = [];

		rows.forEach( function ( row ) {
			const type     = row.dataset.type;
			const id       = row.dataset.id || uniqueId();
			const label    = ( row.querySelector( '.wpfb-field-label' ) || {} ).value || '';
			const required = !! ( row.querySelector( '.wpfb-required' ) || {} ).checked;
			const entry    = { id: id, type: type, label: label, required: required };

			/* -- Type-specific keys -- */
			if ( OPTION_TYPES.includes( type ) ) {
				const ta = row.querySelector( '.wpfb-select-options' );
				if ( ta ) {
					entry.options = ta.value
						.split( /[\n,]/ )
						.map( function ( s ) { return s.trim(); } )
						.filter( Boolean );
				}
			} else if ( 'number' === type ) {
				const minEl  = row.querySelector( '.wpfb-number-min' );
				const maxEl  = row.querySelector( '.wpfb-number-max' );
				const stepEl = row.querySelector( '.wpfb-number-step' );
				if ( minEl  && '' !== minEl.value.trim()  ) entry.min  = minEl.value.trim();
				if ( maxEl  && '' !== maxEl.value.trim()  ) entry.max  = maxEl.value.trim();
				if ( stepEl && '' !== stepEl.value.trim() ) entry.step = stepEl.value.trim();
			} else if ( 'date' === type ) {
				const minEl = row.querySelector( '.wpfb-date-min' );
				const maxEl = row.querySelector( '.wpfb-date-max' );
				if ( minEl && '' !== minEl.value ) entry.min = minEl.value;
				if ( maxEl && '' !== maxEl.value ) entry.max = maxEl.value;
			} else if ( 'textarea' === type ) {
				const rowsEl = row.querySelector( '.wpfb-textarea-rows' );
				if ( rowsEl && '' !== rowsEl.value.trim() ) entry.rows = parseInt( rowsEl.value, 10 ) || 4;
			}

			/* -- Common wrapper keys -- */
			const htmlBeforeEl   = row.querySelector( '.wpfb-html-before' );
			const htmlAfterEl    = row.querySelector( '.wpfb-html-after' );
			const cssClassEl     = row.querySelector( '.wpfb-css-class' );
			const wrapperClassEl = row.querySelector( '.wpfb-wrapper-class' );

			if ( htmlBeforeEl   ) entry.html_before   = htmlBeforeEl.value;
			if ( htmlAfterEl    ) entry.html_after    = htmlAfterEl.value;
			if ( cssClassEl     ) entry.css_class     = cssClassEl.value.trim();
			if ( wrapperClassEl ) entry.wrapper_class = wrapperClassEl.value.trim();

			// Ensure the row's data-id stays consistent.
			row.dataset.id = id;
			fields.push( entry );
		} );

		return fields;
	}

	/* ------------------------------------------------------------------
	 * Save form via fetch()
	 * ------------------------------------------------------------------ */
	saveBtn.addEventListener( 'click', function () {
		const titleInput = document.getElementById( 'wpfb-form-title' );
		const title      = titleInput ? titleInput.value.trim() : '';

		if ( ! title ) {
			showStatus( i18n.noTitle || 'Please enter a form title.', 'error' );
			if ( titleInput ) titleInput.focus();
			return;
		}

		const fields = serializeFields();
		if ( 0 === fields.length ) {
			showStatus( i18n.noFields || 'Please add at least one field.', 'error' );
			return;
		}

		const adminEmails   = ( document.getElementById( 'wpfb-admin-emails' ) || {} ).value || '';
		const mailSubject   = ( document.getElementById( 'wpfb-mail-subject' ) || {} ).value || '';
		const attachFiles   = ( document.getElementById( 'wpfb-attach-files' ) || {} ).checked ? 1 : 0;
		const formHtmlBefore = ( document.getElementById( 'wpfb-form-html-before' ) || {} ).value || '';
		const formHtmlAfter  = ( document.getElementById( 'wpfb-form-html-after' ) || {} ).value || '';
		const formCssClass   = ( document.getElementById( 'wpfb-form-css-class' ) || {} ).value || '';

		const body = new URLSearchParams();
		body.set( 'action',           'wpfb_save_form' );
		body.set( '_wpnonce',         data.nonce );
		body.set( 'form_id',          savedFormId );
		body.set( 'title',            title );
		body.set( 'fields_json',      JSON.stringify( fields ) );
		body.set( 'admin_emails',     adminEmails );
		body.set( 'mail_subject',     mailSubject );
		body.set( 'attach_files',     attachFiles );
		body.set( 'form_html_before', formHtmlBefore );
		body.set( 'form_html_after',  formHtmlAfter );
		body.set( 'form_css_class',   formCssClass );

		showStatus( i18n.saving || 'Saving…', 'info' );
		saveBtn.disabled = true;

		fetch( data.ajaxUrl, {
			method:  'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body:    body.toString(),
		} )
		.then( function ( r ) { return r.json(); } )
		.then( function ( resp ) {
			if ( resp.success ) {
				savedFormId = resp.data.form_id;
				wrap.dataset.formId = savedFormId;
				showStatus( i18n.saved || 'Form saved.', 'success' );

				// Show the shortcode result.
				const shortcode = resp.data.shortcode;
				if ( scCode ) scCode.textContent = shortcode;
				if ( scResult ) scResult.style.display = '';

				// Update existing shortcode badge (if on edit page).
				const existing = document.getElementById( 'wpfb-builder-shortcode' );
				if ( existing ) existing.textContent = shortcode;
			} else {
				const msg = ( resp.data && resp.data.message ) ? resp.data.message : ( i18n.error || 'Error saving form.' );
				showStatus( msg, 'error' );
			}
		} )
		.catch( function () {
			showStatus( i18n.error || 'Could not save form. Please try again.', 'error' );
		} )
		.finally( function () {
			saveBtn.disabled = false;
		} );
	} );

	/* ------------------------------------------------------------------
	 * Copy shortcode to clipboard
	 * ------------------------------------------------------------------ */
	function bindCopyButton( btn ) {
		btn.addEventListener( 'click', function () {
			const sc = btn.dataset.shortcode || ( scCode && scCode.textContent ) || '';
			if ( ! sc ) return;
			navigator.clipboard.writeText( sc ).then( function () {
				const orig = btn.textContent;
				btn.textContent = i18n.copied || 'Copied!';
				setTimeout( function () { btn.textContent = orig; }, 1500 );
			} );
		} );
	}

	document.querySelectorAll( '.wpfb-copy-shortcode' ).forEach( bindCopyButton );
	if ( copyResult ) {
		copyResult.addEventListener( 'click', function () {
			const sc = scCode ? scCode.textContent : '';
			if ( ! sc ) return;
			navigator.clipboard.writeText( sc ).then( function () {
				const orig = copyResult.textContent;
				copyResult.textContent = i18n.copied || 'Copied!';
				setTimeout( function () { copyResult.textContent = orig; }, 1500 );
			} );
		} );
	}

	/* ------------------------------------------------------------------
	 * Hydrate existing fields on edit page load
	 * ------------------------------------------------------------------ */
	function hydrateFields() {
		const raw = wrap.dataset.fields || '[]';
		let fields;
		try {
			// dataset returns the already HTML-decoded attribute value, so a
			// single JSON.parse is correct. No entity decode pass needed.
			fields = JSON.parse( raw );
		} catch ( e ) {
			fields = [];
		}

		fields.forEach( function ( f ) {
			const row = createFieldRow( f.type, f );
			fieldsList.appendChild( row );
		} );

		updateNoFieldsNotice();
	}

	/* ------------------------------------------------------------------
	 * Show/hide the "no fields" notice
	 * ------------------------------------------------------------------ */
	function updateNoFieldsNotice() {
		if ( ! noNotice ) return;
		const hasFields = fieldsList.querySelectorAll( 'li.wpfb-field-row' ).length > 0;
		noNotice.style.display = hasFields ? 'none' : '';
	}

	/* ------------------------------------------------------------------
	 * Status message helper
	 * ------------------------------------------------------------------ */
	function showStatus( msg, type ) {
		if ( ! saveStatus ) return;
		saveStatus.textContent = msg;
		saveStatus.className   = 'wpfb-save-status wpfb-status-' + type;
	}

	/* ------------------------------------------------------------------
	 * Utility: escape HTML for safe innerHTML insertion
	 * ------------------------------------------------------------------ */
	function escapeHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	/* ------------------------------------------------------------------
	 * Init
	 * ------------------------------------------------------------------ */
	hydrateFields();
}() );
