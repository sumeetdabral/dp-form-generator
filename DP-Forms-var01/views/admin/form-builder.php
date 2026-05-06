<?php
/**
 * View: Form builder admin page (create / edit).
 *
 * Variables available:
 *   $form    object|null  Form row if editing, null for new.
 *   $form_id int          Resolved form ID (0 for new).
 *
 * JS picks up initial state from the wpfbBuilder localised object and from
 * data attributes on the page wrapper.
 *
 * @package WPFB
 */

defined( 'ABSPATH' ) || exit;

$is_edit     = ! empty( $form );
$form_id     = $is_edit ? (int) $form->id : 0;
$title       = $is_edit ? esc_attr( $form->title ) : '';
$fields_json = $is_edit ? esc_attr( $form->fields_json ) : '[]';
$shortcode   = $is_edit ? '[wpfb_form id="' . $form_id . '"]' : '';

$admin_emails    = $is_edit ? esc_attr( $form->admin_emails ) : '';
$mail_subject    = $is_edit ? esc_attr( $form->mail_subject ) : '';
$attach_files    = ! $is_edit || (int) $form->attach_files ? 'checked' : '';
$form_html_before = $is_edit ? ( $form->form_html_before ?? '' ) : '';
$form_html_after  = $is_edit ? ( $form->form_html_after ?? '' ) : '';
$form_css_class   = $is_edit ? esc_attr( $form->form_css_class ?? '' ) : '';
?>
<div class="wrap">
	<h1><?php echo $is_edit ? esc_html__( 'Edit Form', 'wpfb' ) : esc_html__( 'Add New Form', 'wpfb' ); ?></h1>

	<?php if ( $is_edit ) : ?>
		<div class="wpfb-shortcode-notice notice notice-info inline">
			<p>
				<strong><?php esc_html_e( 'Shortcode:', 'wpfb' ); ?></strong>
				<code class="wpfb-shortcode-display" id="wpfb-builder-shortcode"><?php echo esc_html( $shortcode ); ?></code>
				<button type="button" class="button button-small wpfb-copy-shortcode"
					data-shortcode="<?php echo esc_attr( $shortcode ); ?>">
					<?php esc_html_e( 'Copy', 'wpfb' ); ?>
				</button>
			</p>
		</div>
	<?php endif; ?>

	<div id="wpfb-builder-wrap"
		data-form-id="<?php echo esc_attr( $form_id ); ?>"
		data-fields="<?php echo $fields_json; ?>">

		<table class="form-table wpfb-builder-meta">
			<tbody>
				<tr>
					<th scope="row"><label for="wpfb-form-title"><?php esc_html_e( 'Form Title', 'wpfb' ); ?> <span class="required">*</span></label></th>
					<td><input type="text" id="wpfb-form-title" class="regular-text" value="<?php echo $title; ?>" required></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpfb-admin-emails"><?php esc_html_e( 'Notification Emails', 'wpfb' ); ?></label></th>
					<td>
						<input type="text" id="wpfb-admin-emails" class="large-text" value="<?php echo $admin_emails; ?>">
						<p class="description"><?php esc_html_e( 'Comma-separated. Leave blank to use the global default.', 'wpfb' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wpfb-mail-subject"><?php esc_html_e( 'Email Subject', 'wpfb' ); ?></label></th>
					<td>
						<input type="text" id="wpfb-mail-subject" class="regular-text" value="<?php echo $mail_subject; ?>">
						<p class="description"><?php esc_html_e( 'Leave blank to auto-generate from form title.', 'wpfb' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Attach Files', 'wpfb' ); ?></th>
					<td>
						<label>
							<input type="checkbox" id="wpfb-attach-files" <?php echo $attach_files; ?>>
							<?php esc_html_e( 'Attach uploaded files to the notification email', 'wpfb' ); ?>
						</label>
					</td>
				</tr>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Form Fields', 'wpfb' ); ?></h2>

		<div class="wpfb-add-field-buttons">
			<span><?php esc_html_e( 'Add field:', 'wpfb' ); ?></span>
			<button type="button" class="button wpfb-add-field" data-type="text"><?php esc_html_e( 'Text', 'wpfb' ); ?></button>
			<button type="button" class="button wpfb-add-field" data-type="email"><?php esc_html_e( 'Email', 'wpfb' ); ?></button>
			<button type="button" class="button wpfb-add-field" data-type="select"><?php esc_html_e( 'Select', 'wpfb' ); ?></button>
			<button type="button" class="button wpfb-add-field" data-type="file"><?php esc_html_e( 'File Upload', 'wpfb' ); ?></button>
			<button type="button" class="button wpfb-add-field" data-type="url"><?php esc_html_e( 'URL', 'wpfb' ); ?></button>
			<button type="button" class="button wpfb-add-field" data-type="tel"><?php esc_html_e( 'Phone', 'wpfb' ); ?></button>
			<button type="button" class="button wpfb-add-field" data-type="number"><?php esc_html_e( 'Number', 'wpfb' ); ?></button>
			<button type="button" class="button wpfb-add-field" data-type="date"><?php esc_html_e( 'Date', 'wpfb' ); ?></button>
			<button type="button" class="button wpfb-add-field" data-type="textarea"><?php esc_html_e( 'Textarea', 'wpfb' ); ?></button>
			<button type="button" class="button wpfb-add-field" data-type="radio"><?php esc_html_e( 'Radio', 'wpfb' ); ?></button>
			<button type="button" class="button wpfb-add-field" data-type="checkboxes"><?php esc_html_e( 'Checkboxes', 'wpfb' ); ?></button>
		</div>

		<ul id="wpfb-fields-list" class="wpfb-fields-list">
			<!-- Fields injected by admin-builder.js on page load -->
		</ul>

		<p class="wpfb-no-fields" id="wpfb-no-fields-notice" style="display:none;color:#888">
			<?php esc_html_e( 'No fields added yet. Use the buttons above to add fields.', 'wpfb' ); ?>
		</p>

		<h2><?php esc_html_e( 'Form Layout', 'wpfb' ); ?></h2>

		<table class="form-table wpfb-builder-meta">
			<tbody>
				<tr>
					<th scope="row"><label for="wpfb-form-html-before"><?php esc_html_e( 'HTML Before Fields', 'wpfb' ); ?></label></th>
					<td>
						<textarea id="wpfb-form-html-before" class="large-text" rows="3"><?php echo esc_textarea( $form_html_before ); ?></textarea>
						<p class="description"><?php esc_html_e( 'HTML rendered just inside the &lt;form&gt; tag, before the fields. Allowed tags: divs, paragraphs, headings, etc. (script/iframe stripped).', 'wpfb' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wpfb-form-html-after"><?php esc_html_e( 'HTML After Fields', 'wpfb' ); ?></label></th>
					<td>
						<textarea id="wpfb-form-html-after" class="large-text" rows="3"><?php echo esc_textarea( $form_html_after ); ?></textarea>
						<p class="description"><?php esc_html_e( 'HTML rendered after the submit button, just before the closing &lt;/form&gt;.', 'wpfb' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wpfb-form-css-class"><?php esc_html_e( 'Form CSS Class', 'wpfb' ); ?></label></th>
					<td>
						<input type="text" id="wpfb-form-css-class" class="regular-text" value="<?php echo $form_css_class; ?>">
						<p class="description"><?php esc_html_e( 'Space-separated class names applied to the &lt;form&gt; element.', 'wpfb' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<button type="button" id="wpfb-save-form" class="button button-primary">
				<?php esc_html_e( 'Save Form', 'wpfb' ); ?>
			</button>
			<span id="wpfb-save-status" class="wpfb-save-status"></span>
		</p>

		<div id="wpfb-shortcode-result" class="wpfb-shortcode-result" style="display:none">
			<strong><?php esc_html_e( 'Your shortcode:', 'wpfb' ); ?></strong>
			<code id="wpfb-result-shortcode"></code>
			<button type="button" class="button button-small wpfb-copy-result"><?php esc_html_e( 'Copy', 'wpfb' ); ?></button>
		</div>
	</div>
</div>
