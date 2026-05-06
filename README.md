# DP-Forms-var01 — Project Repository

This repository holds the **DP-Forms-var01** WordPress plugin together with its design and session-history docs.

- **Plugin version:** 1.1.0
- **Author:** Sumeet Dabral
- **License:** [GPL-2.0-or-later](LICENSE)

## Repository layout

```
.
├── DP-Forms-var01/      ← the plugin (drop this folder into wp-content/plugins/)
│   ├── DP-Forms-var01.php
│   ├── readme.txt
│   ├── LICENSE
│   ├── .gitignore
│   ├── includes/
│   ├── views/
│   ├── assets/
│   └── languages/
├── plan.md              ← architecture spec — read first when planning changes
├── history.md           ← session handoff doc — current state, gotchas, decisions
├── README.md            ← this file
└── LICENSE              ← GPL-2.0 (matches the plugin's declared license)
```

`.claude/` is intentionally `.gitignore`d — that folder is per-machine Claude Code tooling state and not project content.

## What the plugin does

A WordPress form-builder plugin with **Brevo transactional email**, **WordPress Media Library uploads**, and **CF7-style HTML/CSS layout control** on every field.

Field types (11): `text`, `email`, `url`, `tel`, `number`, `date`, `textarea`, `select`, `radio`, `checkboxes`, `file`.

Each field can carry an optional CSS class on the input, a wrapper class on the surrounding `<div>`, and free-form HTML rendered before and after the field — so you can build column grids (e.g. Bootstrap rows) without us shipping a layout system. The whole `<form>` element has the same options at the form level. Admin-entered HTML is sanitized through `wp_kses_post`; CSS class names are sanitized per token via `sanitize_html_class`.

Full feature list, FAQ, and changelog: see [`DP-Forms-var01/readme.txt`](DP-Forms-var01/readme.txt).

## Installing the plugin from this repo

**Option 1 — clone and copy:**
```bash
git clone https://github.com/sumeetdabral/dp-form-generator.git
# Then copy the DP-Forms-var01/ folder into your site's wp-content/plugins/ directory
```

**Option 2 — download a release zip** (when one is published): WP Admin → Plugins → Add New → Upload Plugin.

After install:
1. Activate the plugin.
2. **DP-Forms-var01 → Settings**: paste your Brevo API key (the **`xkeysib-…`** key from the *API keys* tab in your Brevo dashboard — **not** the `xsmtpsib-…` SMTP password). Set From Email (must be a Brevo-verified sender), From Name, default admin recipients.
3. **DP-Forms-var01 → Add New** to build your first form. Paste the generated `[wpfb_form id="X"]` shortcode anywhere.

## Architecture & development docs

- [`plan.md`](plan.md) — full architecture spec, DB schema, security checklist, file tree, verification plan, and the v1.1.0 delta.
- [`history.md`](history.md) — session handoff document covering the current state of the plugin, conventions to preserve, known gotchas, and likely next tasks.

## Reporting issues

Open an issue with: WordPress version, PHP version, the form definition (export `fields_json` from the DB), and the Brevo response captured on the submission row's **Mail Status** column.

## Out of scope (intentionally deferred)

Multisite-wide settings · anti-spam (honeypot/recaptcha) · conditional logic / multi-page forms · public REST API · Composer / build pipeline · SMTP transport (architecturally feasible — `Mailer_Interface` was designed for this — but not yet implemented).
