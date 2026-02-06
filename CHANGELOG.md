# Changelog

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
