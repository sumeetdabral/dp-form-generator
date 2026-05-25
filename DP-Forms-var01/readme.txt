=== DP-Forms-var01 ===
Contributors: sumeetdabral
Tags: contact form, form builder, Brevo, email, file upload
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Drag-and-drop form builder with Brevo transactional email, Media Library file uploads, and a full submissions admin viewer.

== Description ==

DP-Forms-var01 lets WordPress site administrators build contact forms through a visual builder in the admin, render them anywhere via a simple shortcode, and receive transactional notification emails through Brevo's REST API — with no SMTP configuration required.

Key features:

* **Visual form builder** — Add Text, Email, Select, File Upload, URL, Phone, Number, Date, Textarea, Radio, and Checkboxes fields. Reorder with drag-and-drop. Per-field required toggle.
* **Shortcode rendering** — Place `[wpfb_form id="X"]` on any page, post, or widget area.
* **Brevo email delivery** — Notifications sent via Brevo's `POST /v3/smtp/email` API. Uploaded files are attached (up to 9.5 MB base64 total). Reply-To is automatically set to the submitter's email address.
* **Submissions viewer** — Admin list with per-form filtering, pagination, date sorting, and CSV export. Full detail view shows all fields and uploaded files. Resend action retries failed emails.
* **Media Library uploads** — Every uploaded file becomes a private WordPress attachment. Mime allowlist and file size cap are configurable in Settings.
* **Security hardened** — Nonce verification on every action, capability checks, prepared SQL, mime-type verification with double-extension rejection, optional `wp-config.php` API key storage, CRLF-stripped email headers.
* **No build step** — Drop into any WordPress install and activate. No Composer, no npm.

== Installation ==

1. Upload the `DP-Forms-var01` folder to `/wp-content/plugins/`.
2. Activate the plugin through **Plugins → Installed Plugins**.
3. Navigate to **DP-Forms-var01 → Settings** and enter your Brevo API key, sender email, sender name, and default notification recipients.
4. Go to **DP-Forms-var01 → Add New** to create your first form.
5. Copy the shortcode shown at the top of the builder and paste it into any post or page.

== Frequently Asked Questions ==

= Where do I get a Brevo API key? =

Create a free account at brevo.com, then navigate to **SMTP & API → API Keys** and generate a new key. Paste it into **DP-Forms-var01 → Settings**.

= What file types can visitors upload? =

By default: pdf, jpg, jpeg, png, doc, docx. You can change the allowed extensions and the maximum file size in **DP-Forms-var01 → Settings**.

= Is the Brevo API key stored securely? =

By default the key is stored in the database (in the `wpfb_settings` option), which is standard practice for WordPress plugins that integrate third-party APIs. A leaked key only allows sending email through your Brevo account and can be revoked/regenerated instantly in the Brevo dashboard. For stronger security, define the key in `wp-config.php` instead — `define( 'WPFB_BREVO_API_KEY', 'your-key-here' );`. When that constant is present it takes precedence, the key is kept out of the database entirely, and the Settings field is disabled.

= Can I delete all data when I uninstall? =

Yes. Enable **Delete Data on Uninstall** in Settings before deleting the plugin. This drops both custom tables and removes all plugin options.

= Does this work with the block editor (Gutenberg)? =

Yes. The shortcode block in Gutenberg renders the form correctly, and assets are enqueued on render.

= Does it support multi-site? =

The plugin works per-site. There is no network-wide settings screen in this version.

= What HTML is allowed in the Form Layout wrapper fields? =

Wrapper HTML (HTML Before Fields / HTML After Fields) is filtered through WordPress's `wp_kses_post` allowlist. This permits block-level elements such as `<div>`, `<p>`, headings, lists, links, and `<span>`, and strips dangerous tags including `<script>`, `<style>`, `<form>`, and `<iframe>`. Do not rely on arbitrary HTML being preserved.

= How are Checkboxes values exported in CSV? =

The CSV export stores each submission's field data as a JSON-encoded string in the "Fields (JSON)" column. For a Checkboxes field that value is a JSON array, e.g. `["Option A","Option B"]`. Open the CSV in a spreadsheet tool that handles JSON cell values; checkboxes are not fanned out into multiple columns.

== Changelog ==

= 1.1.0 =
* Added 7 new field types: URL, Phone (tel), Number, Date, Textarea, Radio, Checkboxes.
* Added CF7-style layout control: per-field HTML Before/After and CSS class, plus form-level HTML Before/After and CSS class.
* DB upgrade routine via `plugins_loaded` — no manual reactivation needed after dashboard update.
* Registry-driven sanitization and display formatting for all field types.
* Checkboxes: correct `name="field_id[]"` array handling throughout (submission, email, detail view).
* Author changed to Sumeet Dabral.

= 1.0.0 =
* Initial release.
* Form builder with Text, Email, Select, and File Upload field types.
* Brevo HTTP API mail transport.
* Submissions admin viewer with CSV export and Resend action.
* Settings: Brevo credentials, allowed file types, size cap, uninstall toggle.
