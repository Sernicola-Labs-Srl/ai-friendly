# Changelog

## 1.6.3
- Fix robustezza serving `.md` per CPT pubblici con resolver URL piu affidabile
- Supporto archivio CPT in Markdown (es. `/podcast.md`) quando `has_archive` e attivo
- Estrazione contenuti migliorata per contenuti ACF (inclusi repeater/flexible content)
- Filtro anti-rumore su metadati media ACF (slug file, mime, status, timestamp tecnici)
- Gestione versioni statiche vuote/BOM: non vengono servite come output valido
- Hardening output HTTP su `.md` con no-cache e header diagnostici:
  - `X-AI-Friendly-Source`
  - `X-AI-Friendly-Version`
  - `X-AI-Friendly-MD-Length`
  - `X-AI-Friendly-Debug-Requested`
  - `X-AI-Friendly-Debug-Admin`
- Invalidation cache estesa a chiavi meta ACF
- Aggiornata label versione in pagina opzioni da valore hardcoded a costante plugin

## 1.6.2
- Editor llms con syntax highlighting via CodeMirror (core WordPress)
- Diff snapshot completo line-by-line affiancato con numerazione righe
- Paginazione tabella contenuti in Content Manager
- Notifiche errori rigenerazione: admin notice e email configurabile
- Diagnostica sitemap/robots estesa in Overview

## 1.6.1
- Wizard iniziale a 3 step: tipo sito, inclusioni iniziali, generazione bozza llms
- Diff snapshot llms a due colonne con confronto di 2 snapshot selezionati
- Validazione link markdown in preview live
- Note automatiche snapshot con delta linee/token rispetto allo snapshot precedente
- Nuovo endpoint AJAX: `ai_fr_compare_llms_snapshots`

## 1.6.0
- Nuova UI admin "AI Content Hub" con macro-sezioni: Overview, Content, Rules, Automation
- Dashboard overview con stato llms.txt, stato Markdown Pack e warning diagnostici
- Nuovi endpoint AJAX: overview stats, content items, toggle exclusion, timeline, diagnostics, preview, snapshot, restore, simulation
- Telemetry eventi su option `ai_fr_event_log` (ring buffer max 200)
- Snapshot llms in `wp-content/uploads/ai-friendly/llms-history/`
- Refactor `admin/settings-page.php` con asset separati CSS/JS

## 1.5.2
- Header HTTP `Link` canonical per le versioni .md (non sovrascrive altri Link header)
- `X-Robots-Tag` aggiornato a `noindex, follow`
- Nuovo filtro `ai_fr_md_canonical_url` per override del canonical

## 1.5.1
- Fix TypeError in meta cache invalidation (deleted_post_meta array)
- Cache invalidation: meta hooks + filters
- Filename whitelist per file .md statici
- Access checks per .md/llms.txt con filtro per regole custom

## 1.5.0
- Controllo granulare inclusioni/esclusioni (categorie, CPT, template, pattern URL, noindex)
- Pannello admin con tab organizzati
- Salvataggio versioni MD statiche su disco
- Scheduler per rigenerazione automatica (cron, on-save, checksum)

## 1.4.0
- Frontmatter YAML con metadati
- Normalizzazione heading (H1 unico)
- Rimozione shortcode intelligente
- Pulizia HTML avanzata
- Filtro immagini senza alt text

## 1.3.0
- Supporto page builder (Elementor, Breakdance, YOOtheme, ecc.)
- Metabox per esclusione singoli contenuti
- Pagina opzioni admin
