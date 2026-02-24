=== Sernicola Labs | AI Friendly - llms.txt & Markdown ===
Contributors: sernicolalabs
Tags: ai, llms, markdown, seo, content
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.7.0
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

= 1.7.0 =
* Migliorata estrazione contenuti per builder/ACF con scoring candidati, merge deduplicato e debug avanzato.
* Supporto migliorato per WooCommerce product (descrizione breve, descrizione, attributi visibili) nel Markdown.
* Fix multilingua WPML/Polylang per risoluzione URL `.md` e generazione statica nella lingua corretta del post.
* Fix collisioni file statici `.md` tra traduzioni con stesso slug (filename ora include post ID) con cleanup legacy sicuro.
* Migliorata diagnostica `robots.txt` (niente falsi positivi su `Disallow: /wp-admin/`, warning soft per restrizioni parziali).
* UX admin: stato/progresso rigenerazione visibile in Overview e sezione Markdown, severita visiva per warning/error.

= 1.6.4 =
* Plugin Check and coding standards compliance hardening.
* Direct file access protection aligned on the main plugin file.
* i18n translators comment fixes for scheduler placeholder strings.
* Cleanup for residual static analysis warnings in admin/scheduler paths.
