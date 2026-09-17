=== Sernicola Labs AI Friendly – llms.txt, Markdown & Schema ===
Contributors: slabsit
Tags: ai, llms, markdown, seo, content
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 2.1.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Publish WordPress content for AI systems through llms.txt, Markdown endpoints, semantic schema, rules, and automation.

== Description ==

Sernicola Labs AI Friendly is a GEO/AEO content publishing toolkit, not only an llms.txt generator. It combines machine-readable content, selective publishing controls, versioned output, and semantic identity in one local WordPress workflow.

The plugin provides:

* Dynamic `/llms.txt` generation.
* Public `.md` endpoints for published content.
* Optional static Markdown files with regeneration workflows.
* Inclusion and exclusion rules for post types, taxonomy terms, templates, URL patterns, and noindex/password conditions.
* Admin tools for preview, snapshots, diagnostics, and bulk operations.
* Optional Semantic Schema JSON-LD layer with multi-type organizations, repeatable contacts, Place/Geo, opening hours, certifications, generic identifiers, automatic Breakdance FAQPage, WordPress-powered OfferCatalog sources, and Yoast/Rank Math graph extension.
* Local-first operation without external API calls, subscriptions, analytics, or transmitted usage data.
* Automatic migration of settings, content exclusions, custom schema, and snapshots from AI Friendly versions prior to 2.1.

= Privacy =

AI Friendly does not contact external services and does not transmit analytics or usage data. Its activity log is stored only in the local WordPress database, contains technical events without user identifiers, and retains at most 200 entries. Optional regeneration emails are sent through the site's configured WordPress mail system only when enabled by an administrator.

ACF field extraction is disabled by default. Enable it only when the text values of all ACF fields attached to included content are intended for public `.md` output.

When the plugin is deleted from WordPress, its settings, activity log, generated files, snapshots, scheduled events, and plugin-specific post metadata are removed.

== Installation ==

1. Upload the plugin zip from `Plugins > Add New > Upload Plugin`.
2. Activate the plugin.
3. Open `Settings > AI Friendly`.

When moving from the old `ai-friendly` package, leave it installed and activate `sernicola-labs-ai-friendly`. The new plugin imports the available data and safely deactivates the old version. The temporary Content Hub summary disappears automatically after an error-free regeneration and removal of the old plugin. A network-active legacy copy is deactivated automatically only when the new plugin is network-activated too.

== Frequently Asked Questions ==

= Can I exclude specific content? =

Yes. Use per-content exclusion in the metabox or global exclusion rules in settings.

= Can I use only custom llms.txt content? =

Yes. Fill the editor and disable automatic content listing.

= Are ACF fields included automatically? =

No. ACF field extraction is disabled by default because fields may contain information that is not displayed publicly. Administrators can explicitly enable it under the content protection settings after verifying that those values are safe for public output.

= Does it replace Yoast or Rank Math schema? =

No. When Yoast or Rank Math are active, AI Friendly enriches their existing JSON-LD graph and merges nodes with the same @id to avoid duplicate Person or Organization entities.

= Where can I get support or review the source? =

Use the WordPress.org support forum for support. The maintained development source is available at https://github.com/Sernicola-Labs-Srl/ai-friendly.

== Changelog ==

= 2.1.1 =
* Sanitized all submitted admin and AJAX values immediately according to their expected type before validation or storage.
* Added defense-in-depth sanitization when creating, reading, restoring, and migrating llms.txt snapshots.

= 2.1.0 =
* Renamed the directory identity to Sernicola Labs AI Friendly with the new `sernicola-labs-ai-friendly` slug and text domain.
* Replaced global declarations, stored data, hooks, AJAX actions, and asset handles with the distinctive `saifr` prefix.
* Resolved generated-file locations dynamically with `wp_upload_dir()` for custom upload paths and multisite.
* Added a non-destructive migration for settings, custom post metadata, and snapshots stored by pre-2.1 releases, including safe legacy-plugin deactivation and a clear Content Hub summary.
* Updated uninstall cleanup and release packaging for the new plugin identity.

= 2.0.1 =
* Added a discreet Sernicola Labs development credit to the plugin administration page.
* Standardized Content Hub card heights per row and aligned primary card actions.
* Made ACF extraction explicitly opt-in to prevent accidental exposure of non-public custom fields.
* Replaced telemetry naming with a local activity log, removed stored user identifiers, documented privacy behavior, and added complete uninstall cleanup.
* Aligned the declared GPLv3-or-later license with the bundled license text and added WordPress translation metadata.
* Removed the GitHub updater and Update URI header for wordpress.org directory compatibility.
* Addressed Plugin Check security and compatibility findings and excluded development files from the distribution package.

= 2.0.0 =
* Fixed a critical error on singular pages when automatic Breakdance FAQ detection was enabled.
* Refined the admin UI with WordPress-color-scheme-independent buttons, neutral cards, balanced Schema modules, compact media controls, contextual Person/Organization fields, and explicit empty states for repeaters.
* Unified the full AI Content Hub admin interface with shared cards, forms, repeaters, responsive behavior, accessible focus states, and an unsaved-changes bar.
* Replaced the placeholder onboarding with a five-step setup based on detected site data, session recovery, final review, protected AJAX saving, and initial static generation or dynamic validation.
* Added multi-type Organization output, repeatable contacts and opening hours.
* Added Place/Geo, certifications, and generic organization identifiers.
* Added per-content Course, Event, Service, and FAQPage schema metaboxes.
* Added automatic Breakdance FAQ extraction, including Global Blocks and manual FAQ merging; its default-enabled option is shown only while Breakdance is active.
* Added OfferCatalog sources resolved from WordPress term IDs, taxonomy references, and permalinks.

= 1.9.2 =
* Added editable WebSite creator attribution for a person or organization.
* Made static Markdown filenames unique and removed obsolete, excluded, renamed, and orphaned files.
* Preserved raw Markdown output while preventing MIME sniffing.
* Hardened standalone JSON-LD output against script element breakout.
* Moved llms.txt snapshots from public uploads to non-autoloaded database storage with legacy migration and retention cleanup.
* Removed the installation domain from the GitHub updater User-Agent.
* Made excerpt and metadata truncation UTF-8 safe.

= 1.9.1 =
* Refined Schema form placeholders with neutral, reusable example values.

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
