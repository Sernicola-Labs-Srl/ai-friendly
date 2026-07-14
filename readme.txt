=== AI Friendly ===
Contributors: sernicolalabs
Tags: ai, llms, markdown, seo, content
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.9.0
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
* Optional Semantic Schema JSON-LD layer for Person/Organization identity, corporate identifiers, logo, address, contact point, founders, business context, OfferCatalog services, sameAs profiles, knowsAbout topics, known languages, ProfilePage, license, and Yoast/Rank Math graph extension.
* Native WordPress updates from public GitHub Releases.

== Installation ==

1. Upload the plugin zip from `Plugins > Add New > Upload Plugin`.
2. Activate the plugin.
3. Open `Settings > AI Friendly`.

== Frequently Asked Questions ==

= Can I exclude specific content? =

Yes. Use per-content exclusion in the metabox or global exclusion rules in settings.

= Can I use only custom llms.txt content? =

Yes. Fill the editor and disable automatic content listing.

= Does it replace Yoast or Rank Math schema? =

No. When Yoast or Rank Math are active, AI Friendly enriches their existing JSON-LD graph and merges nodes with the same @id to avoid duplicate Person or Organization entities.

== Changelog ==

= 1.9.0 =
* Added optional Organization legal and financial identifiers, including automatic ISO 6523 LEI output.
* Added a dedicated organization logo, postal address, public contact point, and founders.
* Fixed GitHub update version parsing for both `v1.9.0` and `v.1.9.0` tag formats.

= 1.8.3 =
* Fixed repeatable service add button in the Schema catalog editor and refreshed admin asset versioning.

= 1.8.2 =
* Added GitHub link to the WordPress plugin action links.
* Added optional Organization business context and repeatable OfferCatalog service schema fields.

= 1.8.1 =
* Added native WordPress update checks from public GitHub Releases.
* Added plugin information modal support for GitHub release details.

= 1.8.0 =
* Added Semantic Schema JSON-LD module with Person/Organization identity, sameAs, knowsAbout, language, image, license, and ProfilePage support.
* Added auto, standalone, Yoast extension, and Rank Math extension output modes.
* Added Schema admin section, Overview status card, and diagnostics.
* Added JSON-LD cleanup for duplicate sameAs URLs, invalid Organization jobTitle output, and empty image dimensions.

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
