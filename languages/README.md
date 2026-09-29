# Translations

`sernicola-labs-ai-friendly.pot` is the source template for the plugin text
domain. The Italian catalogue uses the `it_IT` locale and is published through
Translate WordPress after the corresponding SVN release is available.

Run the following commands with WP-CLI and GNU gettext installed before each
release:

```powershell
wp i18n make-pot . languages/sernicola-labs-ai-friendly.pot --domain=sernicola-labs-ai-friendly --exclude=node_modules,vendor,.git
msgfmt -o languages/sernicola-labs-ai-friendly-it_IT.mo languages/sernicola-labs-ai-friendly-it_IT.po
```

Keep every placeholder, HTML tag, schema property and URL unchanged in the
translation. Submit the reviewed Italian strings to the Stable project at
Translate WordPress; a language pack is issued after 90% of the Stable strings
are approved.
