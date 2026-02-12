# AI Friendly - Documentazione Plugin

**Versione:** 1.6.3  
**Autore:** Sernicola Labs  
**Requisiti:** WordPress 6.0+, PHP 8.1+  
**Licenza:** GPL v2 or later

---

## Indice

1. Introduzione
2. Installazione e aggiornamento
3. Come funziona
4. AI Content Hub (admin)
5. Regole di inclusione/esclusione
6. Output Markdown
7. Compatibilita
8. Debug e diagnostica
9. Hook e filtri
10. FAQ
11. Release checklist
12. Changelog

---

## Introduzione

AI Friendly espone contenuti WordPress in un formato piu leggibile per sistemi AI/LLM:

- `/llms.txt` con indice dei contenuti
- endpoint `.md` per singoli contenuti pubblici
- (se disponibile) endpoint `.md` per archive CPT pubblici, ad esempio `/podcast.md`

Il plugin include anche un pannello admin (AI Content Hub) per gestire regole, rigenerazione, snapshot e diagnostica.

---

## Installazione e aggiornamento

### Installazione

1. Carica lo zip del plugin da WordPress (`Plugin > Aggiungi nuovo > Carica plugin`)
2. Attiva il plugin
3. Apri `Impostazioni > Sernicola Labs | AI Friendly`

### Aggiornamento

1. Aggiorna lo zip del plugin
2. Verifica in admin la versione mostrata in alto (deve combaciare con il package)
3. Se usi file statici `.md`, esegui `Forza rigenerazione`

Note:
- Le opzioni restano nel database (`ai_fr_options`)
- In hosting con OPcache/FPM puo servire un reload del pool PHP dopo update manuali

---

## Come funziona

### 1) `llms.txt`

`/llms.txt` viene generato dinamicamente e puo combinare:

- contenuto custom scritto in admin
- lista automatica dei contenuti inclusi

### 2) Endpoint `.md`

Per i contenuti pubblici inclusi dalle regole:

- `https://sito.tld/pagina/` -> `https://sito.tld/pagina.md`
- `https://sito.tld/cpt/slug/` -> `https://sito.tld/cpt/slug.md`

Per CPT con archive pubblico (`has_archive`), se abilitati:

- `https://sito.tld/podcast/` -> `https://sito.tld/podcast.md`

### 3) Modalita statica (opzionale)

Se attivi "File MD statici":

- il plugin salva versioni `.md` in `wp-content/uploads/ai-friendly/versions/`
- le richieste possono essere servite dal file salvato
- file vuoti/non validi non vengono considerati validi in serving

---

## AI Content Hub (admin)

Il pannello e diviso in 4 sezioni:

- **Overview**
  - stato `llms.txt`
  - stato Markdown Pack
  - warning diagnostici
  - quick actions
- **Content**
  - editor `llms.txt` con CodeMirror
  - anteprima live
  - snapshot + diff
  - content manager con filtri e toggle esclusione
- **Rules**
  - inclusioni per page/post/product/CPT
  - esclusioni per categoria/tag/template/pattern URL/noindex/password
- **Automation**
  - static md on/off
  - cron rigenerazione
  - trigger su save
  - timeline eventi
  - notifiche errore rigenerazione

---

## Regole di inclusione/esclusione

La pipeline controlla:

1. esclusione manuale (`_ai_fr_exclude`)
2. contenuto protetto da password
3. tipo contenuto abilitato
4. esclusioni tassonomiche/template/pattern URL
5. contenuti marcati `noindex` (se opzione attiva)

Questo vale sia per `llms.txt` sia per `.md`.

---

## Output Markdown

Formato tipico:

1. frontmatter YAML (titolo, date, autore, url, metadati disponibili)
2. `#` titolo documento
3. contenuto convertito in markdown

Se il contenuto principale e scarso, il plugin usa fallback:

- testo builder supportati
- estrazione campi ACF
- excerpt/contenuto raw
- fallback minimo "_Contenuto non disponibile._"

### Estrazione ACF

Per CPT creati/gestiti con ACF:

- il plugin scansiona ricorsivamente i campi (`get_fields`)
- include testo utile (headline, paragrafi, descrizioni)
- esclude metadati media tecnici (filename, mime, status, timestamp, ecc.)

---

## Compatibilita

### Editor/Page builder

Supporto estrazione contenuti per:

- Gutenberg
- Classic Editor
- Elementor
- Breakdance
- YOOtheme
- Oxygen
- Bricks
- ACF (fallback testuale da field values)

### SEO plugin (metadati/noindex)

- Yoast SEO
- Rank Math
- All in One SEO
- SEOPress

### WooCommerce

Supporto prodotti `product` se WooCommerce e attivo e abilitato nelle regole.

---

## Debug e diagnostica

### Header risposta `.md`

Il plugin espone header utili:

- `X-AI-Friendly-Source`: `dynamic` | `static` | `archive`
- `X-AI-Friendly-Version`: versione plugin
- `X-AI-Friendly-MD-Length`: lunghezza markdown calcolato
- `X-AI-Friendly-Debug-Requested`: `1/0`
- `X-AI-Friendly-Debug-Admin`: `1/0`
- `X-Robots-Tag`: `noindex, follow`

### `?debug=1`

Con `?debug=1`:

- viene richiesta modalita debug
- le informazioni debug nel body vengono mostrate solo ad admin
- la risposta HTTP resta no-cache per facilitare troubleshooting

---

## Hook e filtri

### `ai_fr_llms_txt_content`

Permette di modificare il contenuto finale di `llms.txt`.

### `ai_fr_md_cache_ttl`

Permette di modificare TTL cache markdown dinamica.

### `ai_fr_md_canonical_url`

Override del canonical header per endpoint `.md`.

### `ai_fr_md_cache_meta_keys`

Aggiunge chiavi meta che invalidano la cache markdown.

### `ai_fr_can_serve_post`

Controllo finale sulla possibilita di esporre un contenuto via `.md` / `llms.txt`.

---

## FAQ

### I file `.md` vengono indicizzati dai motori?

Di default viene inviato `X-Robots-Tag: noindex, follow`.

### Posso escludere singoli contenuti?

Si, con metabox per singolo contenuto o con regole globali nel tab Rules.

### Posso usare solo contenuto custom per `llms.txt`?

Si. Compila l'editor `llms.txt` e disattiva "Aggiungi lista automatica".

### Ho aggiornato il plugin ma il comportamento non cambia

Controlla:

1. versione mostrata in pagina opzioni
2. header `X-AI-Friendly-Version` in risposta `.md`
3. cache server/CDN/OPcache

### Funziona su CPT creati con ACF?

Si. Sia in risoluzione URL `.md` sia in estrazione contenuto testuale.

---

## Release checklist

Usa questa checklist ad ogni nuova release.

1. **Versioning**
- Aggiorna `Version:` in `ai-friendly.php`
- Aggiorna `AI_FR_VERSION` in `ai-friendly.php`
- Aggiorna `CHANGELOG.md`

2. **Documentazione**
- Verifica coerenza `README.md` con feature reali
- Aggiorna eventuali note su header/debug/compatibilita

3. **Packaging**
- Crea zip release includendo `ai-friendly.php`, `includes/`, `admin/`, `README.md`, `CHANGELOG.md`
- Escludi file non necessari al runtime (es. `.git`, file locali IDE)

4. **Deploy**
- Aggiorna plugin su ambiente test/staging
- Verifica versione mostrata in `Impostazioni > Sernicola Labs | AI Friendly`
- Se necessario, riavvia PHP-FPM/OPcache

5. **Post-deploy**
- Esegui `Forza rigenerazione` da tab Automation
- Verifica `llms.txt` (`/llms.txt`)
- Verifica almeno:
  - una pagina standard `.md`
  - un contenuto CPT `.md`
  - un archive CPT `.md` (se `has_archive` attivo)

6. **Header smoke test (`.md`)**
- Controlla presenza:
  - `X-AI-Friendly-Version`
  - `X-AI-Friendly-Source`
  - `X-AI-Friendly-MD-Length`
  - `X-Robots-Tag: noindex, follow`

7. **Debug smoke test**
- Richiama endpoint con `?debug=1`
- Controlla:
  - `X-AI-Friendly-Debug-Requested: 1`
  - `X-AI-Friendly-Debug-Admin: 1` (solo admin loggato)

8. **Cache**
- Se output inatteso: svuota cache plugin/CDN/reverse proxy
- Se comportamento invariato dopo update: verifica OPcache/FPM e header `X-AI-Friendly-Version`

---

## Changelog

Vedi `CHANGELOG.md`.

---

## Supporto

Sernicola Labs  
https://sernicola-labs.com
