# Changelog

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
