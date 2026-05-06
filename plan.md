# DP-Forms-var01 — Architecture & Implementation Plan

> **Reference doc only.** This file lives at the project root for our shared reference during implementation; it is **not** to be bundled inside the `DP-Forms-var01/` plugin folder or its zip.

## Status

- **v1.0.0** — initial 4-field-type build, shipped 2026-05-05 / 2026-05-06.
- **v1.1.0 (current)** — shipped 2026-05-06. Added 7 new field types, per-field & form-level wrapper HTML / CSS-class system, `plugins_loaded` schema-upgrade routine, author updated to Sumeet Dabral. See §"v1.1.0 additions" near the bottom for the delta-only summary.

## Context

WordPress plugin in working directory `D:\Project 2026\WPContactForm2026`. The product is a self-contained form builder named **DP-Forms-var01** that lets a WordPress admin compose forms in a drag-style UI, render them anywhere via shortcode, persist submissions, and deliver notification emails through Brevo's transactional HTTP API. The plugin ships as a single, install-ready folder that follows WordPress Coding Standards, is i18n-ready, and is hardened against the OWASP Top 10 patterns common in form/upload plugins (XSS, CSRF, SQLi, arbitrary file upload, IDOR on submissions/uploads).

Decisions confirmed with the user:
- **Mail transport**: Brevo HTTP API (`POST https://api.brevo.com/v3/smtp/email`) with `api-key` header — not SMTP. (SMTP transport is architecturally feasible via `Mailer_Interface` but deferred — see Out of Scope.)
- **Submission**: AJAX via `admin-ajax.php` with inline success/error rendering; falls back to a graceful no-JS error message.
- **Uploads**: WordPress Media Library via `wp_handle_upload` + `wp_insert_attachment`. Each upload becomes a private attachment owned by the submitter (or system) and is referenced by attachment ID in the submission row.
- **Submissions persistence**: Yes, with an admin viewer (list + detail + CSV export).

Text prefix: `wpfb_` for everything (functions, classes, hooks, options, table names, asset handles, nonces, transients). Display name is "DP-Forms-var01" — internal slug stays `wpfb` to match the spec'd shortcode `[wpfb_form id="X"]`.

---

## Architecture Overview

OOP, PSR-4-style autoloader (lightweight, namespace `WPFB\`), single front-controller boot file. No Composer dependency — autoload handled in `includes/class-wpfb-autoloader.php` to keep the plugin drop-in installable.

```
Plugin Boot (DP-Forms-var01.php)
   │
   ├── Activator / Deactivator / Uninstaller   (dbDelta + cleanup)
   ├── Autoloader                               (maps WPFB\Foo\Bar → includes/foo/class-wpfb-bar.php)
   ├── i18n loader                              (load_plugin_textdomain on plugins_loaded)
   │
   ├── Admin\Menu               → registers top-level "DP-Forms-var01" menu + 4 subpages
   │     ├── Forms_List_Page    (WP_List_Table)
   │     ├── Form_Builder_Page  (new/edit, JS-driven)
   │     ├── Submissions_Page   (WP_List_Table + detail view + CSV export)
   │     └── Settings_Page      (Settings API: Brevo creds, admin recipients, file rules)
   │
   ├── Admin\Ajax\Form_Save     → wp_ajax_wpfb_save_form
   ├── Admin\Ajax\Form_Delete   → wp_ajax_wpfb_delete_form
   │
   ├── Frontend\Shortcode       → [wpfb_form id="X"] → views/frontend-form.php
   ├── Frontend\Submission      → wp_ajax(_nopriv)_wpfb_submit
   │     ├── Validator          (per-field-type validation, file rules)
   │     ├── Uploader           (wp_handle_upload + wp_insert_attachment)
   │     ├── Repository\Submissions::insert()
   │     └── Mail\Brevo_Mailer::send()
   │
   ├── Repository\Forms         → CRUD on wpfb_forms ($wpdb->prepare everywhere)
   ├── Repository\Submissions   → CRUD on wpfb_submissions
   │
   └── Mail\Brevo_Mailer        → wp_remote_post() to Brevo /v3/smtp/email
         ├── builds HTML body from field labels + sanitized values
         ├── attaches uploaded files (base64, capped at 10 MB total per Brevo limit)
         └── logs failures via WPFB\Support\Logger (option-backed ring buffer, last 50 events)
```

### Why this shape
- A thin autoloader + clear namespace boundary makes every class trivially testable and findable, without forcing the user to install Composer.
- Repositories isolate all SQL behind `prepare()` calls — controllers/views never touch `$wpdb` directly.
- The Mailer is decoupled from the submission flow so a future SMTP/Mailgun/SES backend is a one-class swap.

---

## File Tree

```
DP-Forms-var01/
├── DP-Forms-var01.php          # Main plugin file (headers + boot)
├── uninstall.php                         # Drops tables + options if user opts in
├── readme.txt                            # WP.org-style readme
├── languages/
│   └── wpfb.pot                          # Generated translation template
├── includes/
│   ├── class-wpfb-autoloader.php
│   ├── class-wpfb-plugin.php             # Singleton boot, registers all hooks
│   ├── class-wpfb-activator.php          # dbDelta on activation
│   ├── class-wpfb-deactivator.php
│   ├── admin/
│   │   ├── class-wpfb-menu.php
│   │   ├── class-wpfb-forms-list-page.php
│   │   ├── class-wpfb-form-builder-page.php
│   │   ├── class-wpfb-submissions-page.php
│   │   ├── class-wpfb-settings-page.php
│   │   ├── class-wpfb-forms-list-table.php       # extends WP_List_Table
│   │   ├── class-wpfb-submissions-list-table.php # extends WP_List_Table
│   │   └── ajax/
│   │       ├── class-wpfb-ajax-form-save.php
│   │       └── class-wpfb-ajax-form-delete.php
│   ├── frontend/
│   │   ├── class-wpfb-shortcode.php
│   │   ├── class-wpfb-submission-handler.php
│   │   ├── class-wpfb-validator.php
│   │   └── class-wpfb-uploader.php
│   ├── repository/
│   │   ├── class-wpfb-forms-repository.php
│   │   └── class-wpfb-submissions-repository.php
│   ├── mail/
│   │   ├── interface-wpfb-mailer.php
│   │   └── class-wpfb-brevo-mailer.php
│   └── support/
│       ├── class-wpfb-options.php       # typed wrappers around get_option/update_option
│       ├── class-wpfb-logger.php
│       └── class-wpfb-field-types.php   # registry of allowed types + per-type schema
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   └── frontend.css
│   └── js/
│       ├── admin-builder.js              # vanilla JS, no jQuery dependency beyond WP's bundled
│       └── frontend.js                   # client validation + AJAX submit (FormData)
└── views/
    ├── admin/
    │   ├── forms-list.php
    │   ├── form-builder.php             # 11 add-field buttons + Form Layout panel (v1.1.0)
    │   ├── submissions-list.php
    │   ├── submission-detail.php        # uses Field_Types::format_for_display dispatch
    │   └── settings.php
    └── frontend/
        ├── form.php                      # rendered by shortcode; emits form-level + per-field wrappers
        └── partials/                     # 11 partials, one per field type (v1.1.0)
            ├── field-text.php
            ├── field-email.php
            ├── field-url.php
            ├── field-tel.php
            ├── field-number.php
            ├── field-date.php
            ├── field-textarea.php
            ├── field-select.php
            ├── field-radio.php
            ├── field-checkboxes.php      # input name="{id}[]" — required for PHP array auto-build
            └── field-file.php
```

---

## Database Schema

Two custom tables, created via `dbDelta()` in `WPFB\Activator::activate()`. Both use the site's `$wpdb->prefix`.

### `{prefix}wpfb_forms`
| Column          | Type                | Notes |
|-----------------|---------------------|-------|
| `id`               | BIGINT UNSIGNED PK AUTO_INCREMENT | |
| `title`            | VARCHAR(191) NOT NULL | sanitized via `sanitize_text_field` |
| `fields_json`      | LONGTEXT NOT NULL | JSON array of `{id, type, label, required, options[]?, html_before?, html_after?, css_class?, wrapper_class?, min?, max?, step?, rows?}` (wrapper + type-specific keys added in v1.1.0) |
| `admin_emails`     | TEXT NOT NULL DEFAULT '' | comma-separated, validated per-address |
| `mail_subject`     | VARCHAR(191) NOT NULL DEFAULT '' | optional override per form |
| `attach_files`     | TINYINT(1) NOT NULL DEFAULT 1 | per-form toggle |
| `form_html_before` | LONGTEXT NULL | v1.1.0 — HTML rendered just inside `<form>`, kses-sanitized |
| `form_html_after`  | LONGTEXT NULL | v1.1.0 — HTML rendered after submit button, kses-sanitized |
| `form_css_class`   | VARCHAR(191) NOT NULL DEFAULT '' | v1.1.0 — class attribute on the `<form>` element |
| `created_at`       | DATETIME NOT NULL | UTC |
| `updated_at`       | DATETIME NOT NULL | UTC |

Indexes: PK only (table is small).

Schema upgrades on existing sites are handled by `Activator::maybe_upgrade()` on `plugins_loaded` priority 5 — it compares the `wpfb_db_version` option against `WPFB_VERSION` and re-runs `dbDelta` when the option is older. `register_activation_hook` does NOT fire on dashboard updates, so the `plugins_loaded` path is the load-bearing one for all live upgrades.

### `{prefix}wpfb_submissions`
| Column           | Type                | Notes |
|------------------|---------------------|-------|
| `id`             | BIGINT UNSIGNED PK AUTO_INCREMENT | |
| `form_id`        | BIGINT UNSIGNED NOT NULL | FK → wpfb_forms.id (logical only) |
| `payload_json`   | LONGTEXT NOT NULL | `{field_id: {label, type, value, attachment_id?}}` |
| `attachment_ids` | VARCHAR(255) NOT NULL DEFAULT '' | comma-separated WP attachment IDs |
| `submitter_ip`   | VARCHAR(45) NOT NULL DEFAULT '' | IPv6-safe |
| `user_agent`     | VARCHAR(255) NOT NULL DEFAULT '' | |
| `mail_status`    | VARCHAR(20) NOT NULL DEFAULT 'pending' | `pending|sent|failed` |
| `mail_error`     | TEXT NULL | last Brevo error message |
| `created_at`     | DATETIME NOT NULL | UTC |

Indexes: PK, `KEY form_id (form_id)`, `KEY created_at (created_at)`.

Brevo credentials & global settings live in **`wp_options`** under a single serialized option `wpfb_settings`:
```php
[
  'brevo_api_key'      => '',   // stored encrypted-at-rest using wp_salt() XOR base64 wrapper
  'from_email'         => '',
  'from_name'          => '',
  'global_admin_emails'=> '',   // fallback if per-form is empty
  'allowed_mime_types' => ['pdf','jpg','jpeg','png','doc','docx'],
  'max_upload_mb'      => 5,
  'delete_data_on_uninstall' => false,
]
```

Why obfuscate the API key: WP doesn't ship a secret store. XOR-with-`wp_salt()` is not real crypto, but it stops casual disclosure (DB dump, options viewer plugins) — clearly documented in code comments.

---

## Brevo Integration

`WPFB\Mail\Brevo_Mailer implements WPFB\Mail\Mailer_Interface`

```php
public function send( int $form_id, array $payload, array $attachment_ids ): array {
    $settings = WPFB\Support\Options::get();
    $form     = WPFB\Repository\Forms_Repository::find( $form_id );
    $to_list  = $this->resolve_recipients( $form, $settings );

    $body = [
        'sender'      => [ 'name' => $settings['from_name'], 'email' => $settings['from_email'] ],
        'to'          => array_map( fn( $e ) => [ 'email' => $e ], $to_list ),
        'subject'     => $this->build_subject( $form, $payload ),
        'htmlContent' => $this->render_html( $form, $payload ),
        'replyTo'     => $this->extract_reply_to( $payload ),  // first email field, if present
    ];

    if ( $form->attach_files && $attachment_ids ) {
        $body['attachment'] = $this->build_attachments( $attachment_ids );  // capped at ~10MB total
    }

    $response = wp_remote_post( 'https://api.brevo.com/v3/smtp/email', [
        'timeout' => 15,
        'headers' => [
            'accept'       => 'application/json',
            'content-type' => 'application/json',
            'api-key'      => WPFB\Support\Options::decrypt( $settings['brevo_api_key'] ),
        ],
        'body'    => wp_json_encode( $body ),
    ] );
    // ...parse 201 = success; everything else → log + return [ok=>false, error=>...]
}
```

Reply-To: when the form contains an email field, use the first one's value so admins can reply to the submitter directly.

Attachments: Brevo's `attachment[]` accepts `{name, content}` where `content` is base64. Mailer streams each file via `file_get_contents` on the attachment's local path, base64-encodes, and aborts gracefully (logs warning, sends without attachments) if total size > 9.5 MB.

Failure handling: `mail_status` on the submission row is set to `failed` with the Brevo error captured in `mail_error`; the Submissions admin page surfaces a "Resend" row action that re-invokes the mailer.

### Brevo credential gotcha (caught 2026-05-06)

Brevo issues two credentials under "SMTP & API" that are easy to confuse and produce identical-looking failures:

| Prefix | What it is | Tab in Brevo dashboard | Used by this plugin? |
|---|---|---|---|
| `xkeysib-...` | Transactional HTTP API key | **API keys** tab | **YES** — sent as `api-key` header |
| `xsmtpsib-...` | SMTP master password | **SMTP** tab | No — only valid for SMTP relay on `smtp-relay.brevo.com:587` |

If the user pastes an `xsmtpsib-` value into our API Key field, Brevo returns `HTTP 401 {"message":"Key not found","code":"unauthorized"}` because that token isn't in the v3 API key registry. Document this in any future debugging — first thing to check when a 401 appears is whether the saved key starts with `xkeysib-`.

---

## Form Builder UX (Admin)

`views/admin/form-builder.php` renders an empty container; `assets/js/admin-builder.js` does the rest.

- **Add field** buttons (v1.1.0): Text · Email · URL · Tel · Number · Date · Textarea · Select · Radio · Checkboxes · File. Eleven types total; each appends a `<li>` row to the `<ul>` field list.
- **Per-row controls**: drag handle (HTML5 drag-and-drop, no jQuery UI), label input, "Required" checkbox, type-specific extras (options textarea for select/radio/checkboxes; min/max/step for number; min/max for date; rows for textarea), and a collapsed `<details>Advanced</details>` block (v1.1.0) with four wrapper inputs:
  - HTML Before (textarea — kses-sanitized on save)
  - HTML After (textarea — kses-sanitized on save)
  - Field CSS class (input — `sanitize_html_class` per token)
  - Wrapper CSS class (input — same)
- **Form Layout panel** (v1.1.0): below the form-meta table, three inputs control form-level wrapping — `form_html_before` textarea, `form_html_after` textarea, `form_css_class` input. Stored in dedicated table columns (see schema).
- **Reorder**: drag-and-drop updates a hidden `order` index. Final order is whatever order the rows appear in at save time.
- **Save**: serializes the form definition to JSON, posts via fetch() to `wp_ajax_wpfb_save_form` with nonce. Server runs `Field_Types::validate_definition()` then `Field_Types::sanitize_field_definition()` on each field before persisting.
- **Edit**: same page hydrated with `fields_json` decoded into the same DOM structure. `hydrateFields` uses `JSON.parse(raw)` only — no manual entity decode (the browser already decodes `data-*` attributes).
- **Shortcode display**: top of the builder shows `[wpfb_form id="X"]` with a copy-to-clipboard button after a form is saved.

No build step. Plain ES2017+ vanilla JS; targets evergreen browsers and IE-free WP 6.x admin.

---

## Frontend Render & Submit

Shortcode handler:
```php
add_shortcode( 'wpfb_form', [ WPFB\Frontend\Shortcode::class, 'render' ] );
```
- Reads `id` attr, fetches form via repository, returns escaped HTML.
- Enqueues `frontend.css` + `frontend.js` only on pages that actually render the shortcode (uses `has_shortcode()` check in `wp_enqueue_scripts`, plus a fallback enqueue inside the render method for dynamic loads).
- Localizes JS with `wp_localize_script` to expose `ajax_url`, `nonce`, and i18n strings.

Submit flow (`assets/js/frontend.js`):
1. Intercept submit, run client-side validation (required, email pattern, file extension/size against the localized allowlist).
2. POST `FormData` to `admin-ajax.php?action=wpfb_submit`, including `_wpnonce` and `form_id`.
3. Render success or error message inline; on success, optionally reset the form.

Server (`WPFB\Frontend\Submission_Handler`):
1. `check_ajax_referer( 'wpfb_submit_' . $form_id )` — nonce action is form-scoped to make replay across forms harder.
2. Load form definition; iterate declared fields only (ignores extra POST keys → drops attempts to inject unknown fields).
3. Per-field validation via `WPFB\Frontend\Validator`.
4. File fields go through `WPFB\Frontend\Uploader` which:
   - Calls `wp_handle_upload` with `test_form => false` and a strict `mimes` allowlist from settings.
   - Calls `wp_insert_attachment` + `wp_generate_attachment_metadata`.
   - Returns the attachment ID. Aborts the whole submission if any file fails.
5. Persists submission row.
6. Hands off to mailer; updates `mail_status` from result.
7. Returns JSON: `{ success: bool, message: string }`.

---

## Security Checklist (per file responsibility)

| Concern | Where it's enforced |
|---------|---------------------|
| CSRF (admin save/delete) | `check_admin_referer( 'wpfb_save_form' )` in each ajax handler |
| CSRF (frontend submit) | `check_ajax_referer( 'wpfb_submit_'.$form_id )` |
| Capability | `current_user_can( 'manage_options' )` gate at top of every admin handler + menu callback |
| SQLi | All queries via `$wpdb->prepare()` in repositories; no string concat |
| XSS (admin output) | `esc_html`, `esc_attr`, `esc_textarea`, `wp_kses_post` for HTML emails |
| XSS (frontend) | `esc_attr` on every field attribute; `esc_html` on labels |
| File upload | Mime allowlist from settings + `wp_check_filetype_and_ext` + size cap; rejects double extensions |
| IDOR on attachments | Submission detail page only loads attachments whose IDs are listed in that submission row |
| Open redirect | None — all admin redirects use `admin_url()` with whitelisted query args |
| API key disclosure | Stored XOR-obfuscated; never echoed in any admin field (input is `type=password` with "Update key" toggle) |
| Email injection | All header-bound values (from name, subject, recipients) sanitized; CRLF stripped |

---

## Critical Files (with one-line responsibility)

- `DP-Forms-var01.php` — plugin headers, defines `WPFB_VERSION` / `WPFB_DIR` / `WPFB_URL`, registers activation/deactivation hooks, calls `WPFB\Plugin::instance()->boot()`.
- `includes/class-wpfb-plugin.php` — wires every hook; nothing else does `add_action`/`add_filter` outside of class constructors it instantiates.
- `includes/class-wpfb-activator.php` — `dbDelta` for both tables; sets default `wpfb_settings` if absent.
- `includes/repository/class-wpfb-forms-repository.php` — `find/all/insert/update/delete`, all `prepare()`d.
- `includes/repository/class-wpfb-submissions-repository.php` — same pattern, plus `paginate()` for the list table.
- `includes/frontend/class-wpfb-submission-handler.php` — orchestrates nonce → validate → upload → persist → mail.
- `includes/mail/class-wpfb-brevo-mailer.php` — single source of truth for hitting Brevo; mockable via interface.
- `includes/support/class-wpfb-field-types.php` — declarative registry the builder, validator, and email renderer all read from (one place to add a field type later).
- `assets/js/admin-builder.js` — entire builder UI; no inline scripts in PHP views.
- `assets/js/frontend.js` — client validation + AJAX submit.
- `views/frontend/form.php` — the only file that renders the public form markup; partials per field type for clarity.

---

## Verification Plan

End-to-end checks the user (or a clean WP test site) should run after install:

1. **Install** — Upload zip via Plugins → Add New → Upload → Activate. Confirm "DP-Forms-var01" menu appears.
2. **Tables** — In phpMyAdmin (or `wp db query`), confirm `wp_wpfb_forms` and `wp_wpfb_submissions` exist with the columns above.
3. **Settings** — Settings page saves a Brevo API key, From email, From name, default admin emails. Reload page; key field should show "•••••• (saved)" not the raw key.
4. **Build** — Create a form titled "Contact" with: Name (text, required), Email (email, required), Topic (select: Sales, Support, Other), Attachment (file, optional). Save. Shortcode `[wpfb_form id="1"]` displays at top of the builder.
5. **Render** — Paste shortcode into a page, view it logged out. Fields render, required markers show, CSS is intact.
6. **Client validation** — Submit empty → inline errors. Bad email → email error. >5MB file → size error. .exe upload → blocked.
7. **Submit happy path** — Fill in valid data with a small PDF. Get success message. Confirm:
   - New row in `wp_wpfb_submissions` with `mail_status='sent'`.
   - PDF appears in Media Library, linked from the submission detail page.
   - Email arrives at admin recipients with all field labels + values, PDF attached, Reply-To set to submitter's email.
8. **Brevo failure** — Set an invalid API key; submit again. User still sees success message (or "saved, mail pending" — TBD), submission row records `mail_status='failed'` with Brevo's error string. "Resend" row action retries.
9. **Edit & delete** — Edit form, reorder fields, save, re-render — order persists. Delete a form; its shortcode now renders "Form not found." (escaped).
10. **Submissions UI** — List shows pagination, per-form filter, date sort. Detail view shows all fields. CSV export downloads with correct headers.
11. **Uninstall** — With `delete_data_on_uninstall=true`, deactivate + delete plugin → tables and `wpfb_settings` are gone. With it false → data preserved (verify by reactivating).
12. **Security smoke** — Logged out, hit `admin-ajax.php?action=wpfb_save_form` → returns 0/403. Hit submit endpoint with wrong/missing nonce → 403.

---

## Out of Scope (explicitly)

- Multisite-wide settings (plugin works per-site only).
- Anti-spam (honeypot/recaptcha) — easy follow-up but not in this build.
- Conditional logic / multi-page forms.
- REST API endpoints (admin-ajax is sufficient for the spec).
- Composer / build pipeline.
- **SMTP transport** — architecturally feasible (the `Mailer_Interface` was designed for this; `SMTP_Mailer` would slot in next to `Brevo_Mailer` and reuse PHPMailer via `phpmailer_init`). Deferred unless a user requests it. Estimated ~250-400 lines if added.

These are noted so the user can request them as follow-ups without me silently expanding scope here.

---

## v1.1.0 additions (delta from v1.0.0)

Implemented 2026-05-06. Plan file for the implementation: `C:\Users\Lenovo\.claude\plans\did-you-get-what-robust-kernighan.md`.

- **7 new field types**: url, tel, number, date, textarea, radio, checkboxes. Registry-driven — each is one entry in `WPFB\Support\Field_Types::get_all()`.
- **Per-field wrapper system** (4 new optional keys per field): `html_before`, `html_after`, `css_class`, `wrapper_class`. All sanitized on save (`wp_kses_post` for HTML, `sanitize_html_class` per token for class names) and re-sanitized on render (defense-in-depth). Stored inside `fields_json` — no schema change for these.
- **Form-level wrapper system** (3 new columns on `wpfb_forms`): `form_html_before`, `form_html_after`, `form_css_class`. Same sanitization rules.
- **Registry refactor**: each type now exposes `sanitize_input` (lets `textarea` preserve newlines via `sanitize_textarea_field`, lets `checkboxes` accept arrays). The submission handler dispatches via this callback instead of branching on type. The display callback was renamed `format_for_email` → `format_for_display` and is now used by both `Brevo_Mailer` (email body) and `views/admin/submission-detail.php` (admin viewer) — single source of truth.
- **Schema upgrade routine**: `Activator::maybe_upgrade()` on `plugins_loaded` priority 5. Adds the 3 new columns idempotently via `dbDelta` when `wpfb_db_version` option is older than `WPFB_VERSION`. Required because `register_activation_hook` does NOT fire on dashboard plugin updates.
- **frontend.js fix**: `showFieldError` now matches both `[name="x"]` and `[name="x[]"]` — needed for checkboxes whose name attribute carries the trailing `[]`.
- **Author** changed everywhere from "1Apollo" to "Sumeet Dabral". Author URI (`https://1apollo.co/`), Plugin URI, and contact email left as-is.
- **WPFB_VERSION** bumped 1.0.0 → 1.1.0.
