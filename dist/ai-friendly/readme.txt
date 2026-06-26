=== Sernicola Labs | AI Friendly - llms.txt & Markdown ===
Contributors: sernicolalabs
Tags: ai, llms, markdown, seo, content
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Expose WordPress content for AI systems through llms.txt and Markdown endpoints, with rules, automation, and versioning tools.

== Description ==

AI Friendly provides:

* Dynamic `/llms.txt` generation.
* Public `.md` endpoints for published content.
* Optional static Markdown files with regeneration workflows.
* Inclusion and exclusion rules for post types, taxonomy terms, templates, URL patterns, and noindex/password conditions.
* Admin tools for preview, snapshots, diagnostics, and bulk operations.
* Optional Semantic Schema JSON-LD layer for identity, sameAs profiles, AI-friendly context, and Yoast/Rank Math extension.

== Installation ==

1. Upload the plugin zip from `Plugins > Add New > Upload Plugin`.
2. Activate the plugin.
3. Open `Settings > Sernicola Labs | AI Friendly`.

== Frequently Asked Questions ==

= Can I exclude specific content? =

Yes. Use per-content exclusion in the metabox or global exclusion rules in settings.

= Can I use only custom llms.txt content? =

Yes. Fill the editor and disable automatic content listing.

== Changelog ==

= 1.8.0 =
* Added Semantic Schema JSON-LD module with Person/Organization identity, sameAs, knowsAbout, language, image, license, and ProfilePage support.
* Added auto, standalone, Yoast extension, and Rank Math extension output modes.
* Added Schema admin section, Overview status card, and diagnostics.

= 1.7.1 =
* Fix serving homepage in Markdown via `/index.html.md`.
* Hardening `/llms.txt` output against empty/stale proxy cache responses.
* Explicit raw text/plain body and Content-Length for `/llms.txt`.
* Improved saved-settings toast placement and moved "Riapri Wizard" into the admin navigation bar.

= 1.7.0 =
* More robust builder/ACF/WooCommerce content extraction.
* Improved multilingual `.md` resolution for WPML/Polylang.
* Static Markdown filename collision fix for translated content.
* Raw Markdown output for `.md` endpoints.
* Improved robots.txt diagnostics and admin regeneration progress UI.

= 1.6.4 =
* Plugin Check and coding standards compliance hardening.
* Direct file access protection aligned on the main plugin file.
* i18n translators comment fixes for scheduler placeholder strings.
* Cleanup for residual static analysis warnings in admin/scheduler paths.
