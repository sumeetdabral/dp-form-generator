# Session History — DP-Forms-var01

> **For future Claude sessions:** Paste or `@-attach` this file at the start of a new session in this project (`D:\Project 2026\WPContactForm2026`) and you'll have full context for continuing the work. Companion docs in this directory: `plan.md` (architecture/spec), `DP-Forms-var01/` (the plugin code), `DP-Forms-var01.zip` (installable build).

---

## Project at a glance

- **Product:** WordPress plugin called **DP-Forms-var01** — admin-built forms with 11 field types (text, email, url, tel, number, date, textarea, select, radio, checkboxes, file), per-field & form-level HTML wrappers + CSS classes (CF7-style layout control, added in v1.1.0), shortcode-rendered (`[wpfb_form id="X"]`), submissions persisted to a custom table, notification emails sent via Brevo's HTTP transactional API.
- **Working directory:** `D:\Project 2026\WPContactForm2026`
- **Plugin folder (deliverable):** `D:\Project 2026\WPContactForm2026\DP-Forms-var01\`
- **Installable zip:** built locally from the `DP-Forms-var01/` folder when needed (user creates the zip themselves now — see PowerShell command below).
- **Status as of 2026-05-06:** **v1.1.0 implemented and deployed to a live host (Crazy Themes site).** Day's work: (1) Fixed a fatal namespace-ordering bug across 23 files — every namespaced PHP file had `defined( 'ABSPATH' ) || exit;` *before* the `namespace` declaration, which is invalid PHP. (2) Renamed the plugin from "DP Form Generator" (with spaces) to slug-style **DP-Forms-var01** + updated all display strings. (3) Implemented v1.1.0: added 7 new field types (url, tel, number, date, textarea, radio, checkboxes), added per-field `html_before`/`html_after`/`css_class`/`wrapper_class` and form-level `form_html_before`/`form_html_after`/`form_css_class` (3 new DB columns), added a `plugins_loaded` upgrade routine for safe schema migrations on dashboard updates. (4) Changed plugin author from 1Apollo → Sumeet Dabral. **Currently testing live email delivery** — first attempt got Brevo HTTP 401 because the user pasted an `xsmtpsib-` (SMTP password) instead of an `xkeysib-` (API key); waiting on retry with the correct key. See "Known gotchas" below.

## User profile

- Email: `oliver@1apollo.co` (Author URL `https://1apollo.co/` is in the plugin headers).
- Wants production-ready, copy-paste-ready WordPress plugin code with a senior-dev feel — no placeholder stubs, no half-finished features.
- Communicates in short, direct messages and trusts judgment but corrects when I drift. Has corrected me twice this session: once about `plan.md` not belonging in the deliverable, once about literal naming. **Take corrections seriously.**

## Key user preferences and decisions (saved to auto memory)

These are persisted in `C:\Users\Lenovo\.claude\projects\D--Project-2026-WPContactForm2026\memory\` and will load automatically in future sessions in this project — but worth having here as well:

1. **`plan.md` is reference-only.** Never bundle it into the plugin folder, the zip, or any deliverable. It lives at the project root for shared reference between us.
2. **Plugin name is `DP-Forms-var01`** — slug-style, hyphenated. Folder = `DP-Forms-var01/`, main file = `DP-Forms-var01.php`. The Plugin Header `Plugin Name`, top-level admin menu label, settings page heading, forms list heading, and every user-facing display string all use this exact literal. Internal `wpfb_` prefix (functions, hooks, options, table names, asset handles, nonce actions) and `WPFB\` namespace are unchanged — those are internal identifiers, not the plugin name. (The earlier "DP Form Generator" with spaces was superseded on 2026-05-06.)

## Architectural decisions confirmed by the user

| Question | Decision |
|---|---|
| Brevo SMTP vs HTTP API? | **HTTP API** — `POST https://api.brevo.com/v3/smtp/email` with `api-key` header. No SMTP path implemented. |
| Form submission method? | **AJAX** via `admin-ajax.php` (action `wpfb_submit`), inline success/error rendering via vanilla JS (`assets/js/frontend.js`). |
| File upload destination? | **WordPress Media Library** via `wp_handle_upload` + `wp_insert_attachment`. Attachment IDs stored on the submission row. |
| Persist submissions? | **Yes, with admin viewer** — `wpfb_submissions` table, list table + detail view + CSV export. |

Full architecture, schema, security checklist, and file tree are in `plan.md` at the project root. Don't re-derive — read `plan.md` first.

## Conventions to preserve

- **Display name:** "DP-Forms-var01"
- **Author:** Sumeet Dabral (was "1Apollo" before 2026-05-06; Author URI `https://1apollo.co/` and contact email `oliver@1apollo.co` are kept).
- **Current version:** 1.1.0 (`WPFB_VERSION` constant in main file).
- **Internal prefix:** `wpfb_` (functions, hooks, options, table names, asset handles, nonce actions, transients)
- **Shortcode:** `[wpfb_form id="X"]`
- **Text domain:** `wpfb` (Domain Path: `/languages`)
- **Namespace:** `WPFB\` with sub-namespaces `Admin`, `Admin\Ajax`, `Frontend`, `Repository`, `Mail`, `Support`
- **Namespace ordering:** in every PHP file with a `namespace WPFB...;` declaration, the `namespace` line MUST come BEFORE `defined( 'ABSPATH' ) || exit;` — reverse order is a fatal PHP parse error. This was the bug that broke v1.0.0 first deploy.
- **Autoloader:** lightweight, no Composer — `includes/class-wpfb-autoloader.php`
- **Settings option key:** `wpfb_settings`
- **DB schema version:** `wpfb_db_version` option (separate from `WPFB_VERSION` constant). `Activator::maybe_upgrade()` runs on `plugins_loaded` priority 5 and re-runs `dbDelta` when the option is older than the constant. This is the load-bearing migration path — `register_activation_hook` does NOT fire on dashboard updates.
- **Tables:** `{$wpdb->prefix}wpfb_forms` (10 columns post-v1.1.0), `{$wpdb->prefix}wpfb_submissions` (9 columns).
- **Database changes:** when you change the schema, bump `WPFB_VERSION` in the main file. `dbDelta` is the only DDL path.
- **Brevo API key storage:** XOR-with-`wp_salt('secure_auth')` obfuscation in `WPFB\Support\Options` — explicitly NOT real encryption (commented as such). Field in admin is `type=password`; existing keys show `••••••••` and only update when admin types something new.
- **Per-field wrapper data** (v1.1.0): four optional keys (`html_before`, `html_after`, `css_class`, `wrapper_class`) live inside `fields_json` — no schema change. Sanitized at save (`wp_kses_post` for HTML, `sanitize_html_class` per token for classes) and re-sanitized at render.
- **Field-type registry** (`Field_Types::get_all()`): each entry has `label`, `validate`, `sanitize_input`, `render_frontend` (partial filename), `format_for_display`. The `render_admin_row` PHP key still exists from v1.0.0 but is dead code — the builder is JS-driven. Don't add new `admin_row_*` PHP methods.
- **No build step.** Vanilla ES2017+ JS. WP's bundled jQuery is allowed but not required.

## Current file layout

```
D:\Project 2026\WPContactForm2026\
├── plan.md                       # Architecture spec — read this first if planning changes
├── history.md                    # This file — session handoff doc
├── DP-Forms-var01.zip         # Installable, regenerate with the PowerShell command below
├── DP-Forms-var01\            # The plugin folder — what you'd see in wp-content/plugins/
│   ├── DP-Forms-var01.php     # Main file (plugin headers + boot)
│   ├── uninstall.php
│   ├── readme.txt
│   ├── languages\wpfb.pot
│   ├── includes\
│   │   ├── class-wpfb-autoloader.php
│   │   ├── class-wpfb-plugin.php
│   │   ├── class-wpfb-activator.php
│   │   ├── class-wpfb-deactivator.php
│   │   ├── admin\               # Menu, list tables, builder/settings/submissions pages, ajax handlers
│   │   ├── frontend\            # Shortcode, submission handler, validator, uploader
│   │   ├── repository\          # Forms + Submissions repositories (all queries via $wpdb->prepare)
│   │   ├── mail\                # Mailer interface + Brevo implementation
│   │   └── support\             # Options, Logger, Field_Types registry
│   ├── assets\
│   │   ├── css\admin.css, frontend.css
│   │   └── js\admin-builder.js, frontend.js
│   └── views\
│       ├── admin\               # forms-list, form-builder (11 add-field buttons + Form Layout panel), submissions-list, submission-detail, settings
│       └── frontend\            # form.php + 11 partials: field-{text,email,url,tel,number,date,textarea,select,radio,checkboxes,file}.php
└── .claude\agent-memory\wp-plugin-architect\
    ├── MEMORY.md
    └── project_dpformgenerator.md   # Agent's own notes on architecture decisions (folder name historical; not renamed)
```

49 PHP/asset files total (post-v1.1.0). Largest are `includes/support/class-wpfb-field-types.php` (~792 lines, doubled in v1.1.0 for 7 new types + new dispatch helpers), `assets/js/admin-builder.js` (~410 lines), `includes/mail/class-wpfb-brevo-mailer.php` (~315 lines).

## How to rebuild the zip after code changes

PowerShell, from `D:\Project 2026\WPContactForm2026\`:

```powershell
Compress-Archive -Path '.\DP-Forms-var01' -DestinationPath '.\DP-Forms-var01.zip' -Force
```

The `-Force` flag overwrites the existing zip. The folder `DP-Forms-var01/` ends up at the zip root, which is the layout WordPress's plugin uploader expects.

## How to install on a WordPress site

1. WP admin → **Plugins → Add New → Upload Plugin** → pick `DP-Forms-var01.zip` → Install → Activate.
2. **DP-Forms-var01 → Settings**: paste Brevo API key (from Brevo dashboard → SMTP & API → API Keys), set From Email (must be a verified Brevo sender), From Name, default admin emails (comma-separated), allowed file types, max upload MB.
3. **DP-Forms-var01 → Add New** to build a form. The shortcode appears at the top after the first save.
4. Paste the shortcode into any post/page/widget.

### Pre-flight requirements

- WordPress ≥ 6.0
- PHP ≥ 7.4
- Brevo account with at least one verified sender email/domain (sends will fail otherwise — Brevo returns 400 with `code: invalid_parameter`)
- PHP `upload_max_filesize` and `post_max_size` ≥ the "Max Upload MB" set in Settings (default 5 MB)

## Verification checklist (untested — see plan.md §Verification)

The plugin has not been activated on a live WP install yet. The 12-step verification plan is in `plan.md` under **Verification Plan**. Highlights:
- Confirm `wp_wpfb_forms` and `wp_wpfb_submissions` tables exist after activation.
- Submit a form with a small PDF; confirm submission row gets `mail_status='sent'`, attachment appears in Media Library, email lands at admin recipients with Reply-To set to the submitter's email field.
- With invalid API key: submission row should record `mail_status='failed'` with Brevo's error string. "Resend" row action retries.

## Known gotchas / things to watch for

- **Brevo credential prefixes are NOT interchangeable.** This bit us on 2026-05-06. Brevo issues two tokens under "SMTP & API":
  - `xkeysib-...` — transactional HTTP API key, lives on the **API keys** tab — this is what our plugin needs (sent as `api-key` header).
  - `xsmtpsib-...` — SMTP master password, lives on the **SMTP** tab next to login `XXXXXX@smtp-brevo.com` and port 587 — only valid for SMTP relay, not for the v3 HTTP API.
  - If a user pastes `xsmtpsib-` into our API Key field, Brevo returns `HTTP 401 {"message":"Key not found","code":"unauthorized"}`. First diagnostic for any Brevo 401 should be: confirm the saved key starts with `xkeysib-`. If it doesn't, the user has the wrong credential type — not a plugin bug.
- **Namespace-before-ABSPATH ordering.** Every PHP file with a `namespace WPFB...;` MUST declare it before `defined( 'ABSPATH' ) || exit;`. Reverse order is a fatal parse error. Don't regress when adding new namespaced files.
- **Schema upgrades on plugin update.** `register_activation_hook` does NOT fire on the WP dashboard update path. Schema changes must be handled in `Activator::maybe_upgrade()` (hooked on `plugins_loaded` priority 5), gated by comparing `wpfb_db_version` option vs `WPFB_VERSION` constant. Bumping the constant alone is not enough.
- **Checkboxes input name carries `[]`.** `field-checkboxes.php` renders `name="{field_id}[]"` so PHP auto-arrays the POST. The `frontend.js` `showFieldError` selector accepts both `[name="x"]` and `[name="x[]"]` to handle this. Any new place that does `form.querySelector('[name="X"]')` must do the same dual lookup or it'll silently fail to find checkbox groups.
- **Folder name is hyphenated (no spaces).** Avoids the FTP/cPanel space-rewriting problem. All internal paths use `__FILE__` / `plugin_dir_path()` / `plugin_dir_url()` so the folder could be renamed again later with zero code changes.
- **Admin-page hook suffixes depend on the menu label.** WP generates subpage hook names as `<sanitize_title($menu_label)>_page_<submenu_slug>`. Current value: `dp-forms-var01_page_*` (sanitized form of "DP-Forms-var01"). If the menu label is ever changed again, the four hardcoded hook strings in `WPFB\Admin\Menu::enqueue_admin_assets()` must be updated to match — otherwise admin assets stop loading.
- **API key obfuscation is not encryption.** Anyone with DB read access can recover the key. Documented as such in `WPFB\Support\Options`. Acceptable per spec; flag if upgrading to real secret storage is needed.
- **CSV export with checkbox values.** `payload_json` for `checkboxes` fields stores a `string[]`. CSV export dumps `payload_json` as a single column, so checkbox values appear as a JSON literal `["a","b"]` in the cell — not a fatal, but worth documenting if a user expects fanned-out columns.
- **CSV export endpoint** is in the Submissions admin page (`?action=export`) and is gated by `manage_options` + nonce. If you add new export columns, update both the export and the detail view to keep them aligned.
- **Brevo attachment cap:** mailer skips attachments (with logged warning) if total base64-encoded payload would exceed ~9.5 MB. The submission still saves and email still goes out, just without files.
- **Hooks are wired in `WPFB\Plugin::boot()` only.** Don't add `add_action`/`add_filter` calls scattered across other classes — keep registration centralized.
- **`hydrateFields` in admin-builder.js uses `JSON.parse(raw)` only.** Do NOT add a manual HTML-entity-decode pass — the browser already decodes `data-*` attributes. Adding a decode pass double-decodes and corrupts wrapper HTML on round-trip (caught mid-implementation during v1.1.0 by the wp-plugin-agent's advisor review).

## Specialized agent in this project

- **`wp-plugin-architect`** subagent (configured at user level via `/agents`) wrote the initial v1.0.0 build. Agent-memory snapshot lives at `.claude\agent-memory\wp-plugin-architect\project_dpformgenerator.md` — sub-agent will load that automatically when re-invoked in this project. Suitable for future plugin-side changes (new field types, new mailers, security audits, etc.).

## Out of scope (intentionally — re-read before suggesting)

These were explicitly deferred in `plan.md`. Don't silently add them:
- Multisite-wide settings
- Anti-spam (honeypot/recaptcha)
- Conditional logic / multi-page forms
- Public REST API endpoints (admin-ajax is sufficient)
- Composer / build pipeline

---

## Open questions / next likely tasks

Active at end of 2026-05-06 session:
- **Live email delivery test pending.** User is generating an `xkeysib-` key on Brevo and retrying form submission. If submission row flips to `mail_status='sent'`, v1.1.0 is fully verified end-to-end. If it still fails after a confirmed `xkeysib-` key, add the temporary "Test Connection" diagnostic (one-shot `GET /v3/account` to Brevo) to isolate obfuscation/deobfuscation correctness.

Likely follow-ups when the user resumes:

- **SMTP transport** — explicitly proposed but deferred today. Architecturally feasible (the `Mailer_Interface` was designed for swap-in transports). Would let users use `xsmtpsib-` SMTP credentials directly. ~250-400 lines, uses WP's bundled PHPMailer via `phpmailer_init`. New "Mail transport" radio in Settings to pick HTTP API vs SMTP. SMTP password gets the same XOR-obfuscation as the API key.
- **Anti-spam** (honeypot/recaptcha) — contact forms attract bots immediately.
- **Per-form custom email templates** — currently the body is auto-built from field labels + values; admin can't customize.
- **Iron out anything that surfaces during real-world use of the new field types** (date pickers in older browsers, checkbox group accessibility, textarea XSS edge cases via wrapper HTML, etc.).
- **CSV export fan-out for array fields** — currently checkbox values appear as JSON literal in a single cell. If users complain, fan out to multiple columns.

When resuming: re-read `plan.md` for design intent, then this `history.md` for status. The auto-memory in `.claude\projects\D--Project-2026-WPContactForm2026\memory\` loads automatically and contains the plan-file preference captured there. The agent-memory snapshot for `wp-plugin-agent` at `.claude\agent-memory\wp-plugin-architect\project_dpformgenerator.md` (folder name is historical, not renamed) loads automatically when that subagent is invoked.
