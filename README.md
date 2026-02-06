# AI Friendly — Documentazione Plugin

**Versione:** 1.5.2  
**Autore:** Sernicola Labs  
**Requisiti:** WordPress 6.0+, PHP 8.1+  
**Licenza:** GPL v2 or later

---

## Indice

1. [Introduzione](#introduzione)
2. [Installazione](#installazione)
3. [Come funziona](#come-funziona)
4. [Pannello di Controllo](#pannello-di-controllo)
   - [Contenuto llms.txt](#tab-contenuto-llmstxt)
   - [Filtri & Esclusioni](#tab-filtri--esclusioni)
   - [Versioning MD](#tab-versioning-md)
   - [Scheduler](#tab-scheduler)
5. [Metabox per singoli contenuti](#metabox-per-singoli-contenuti)
6. [Costanti configurabili](#costanti-configurabili)
7. [Struttura output Markdown](#struttura-output-markdown)
8. [Compatibilità](#compatibilità)
9. [Struttura del plugin](#struttura-del-plugin)
10. [Hook e Filtri per sviluppatori](#hook-e-filtri-per-sviluppatori)
11. [FAQ](#faq)
12. [Changelog](#changelog)

---

## Introduzione

**AI Friendly** è un plugin WordPress che rende i contenuti del tuo sito facilmente accessibili e interpretabili dai modelli di intelligenza artificiale (LLM).

### Cosa fa il plugin

1. **Genera `/llms.txt`** — Un file di testo che elenca e descrive tutti i contenuti del sito, pensato per essere letto da crawler AI e assistenti virtuali.

2. **Genera versioni `.md`** — Ogni pagina e articolo diventa accessibile in formato Markdown aggiungendo `.md` all'URL (es. `tuosito.com/chi-siamo.md`).

### Perché è utile

- **Migliora la visibilità AI** — I modelli di linguaggio possono comprendere meglio la struttura e i contenuti del tuo sito.
- **Contenuto pulito** — L'output Markdown è privo di elementi UI, form, script e altri "rumori" che confondono gli LLM.
- **Standard emergente** — Il formato `llms.txt` sta diventando uno standard de facto per l'indicizzazione AI, simile a `robots.txt` per i motori di ricerca tradizionali.

---

## Installazione

### Metodo 1: Upload manuale

1. Scarica il file `ai-friendly.php`
2. Caricalo nella cartella `wp-content/plugins/ai-friendly/`
3. Attiva il plugin da **WordPress → Plugin**

### Metodo 2: Upload da admin

1. Vai su **Plugin → Aggiungi nuovo → Carica plugin**
2. Seleziona il file `.zip` del plugin
3. Clicca **Installa ora** e poi **Attiva**

### Verifica installazione

Dopo l'attivazione, visita:
- `tuosito.com/llms.txt` — Dovresti vedere l'indice dei contenuti
- `tuosito.com/qualsiasi-pagina.md` — Dovresti vedere la versione Markdown

---

## Come funziona

### File llms.txt

Il file `/llms.txt` viene generato dinamicamente e contiene:

```markdown
# Nome del Sito

> Descrizione del sito (tagline WordPress)

## Pagine

- [Home](https://tuosito.com/home.md): Descrizione breve...
- [Chi siamo](https://tuosito.com/chi-siamo.md): La nostra storia...
- [Servizi](https://tuosito.com/servizi.md): I nostri servizi...

## Post

- [Articolo recente](https://tuosito.com/articolo.md): Estratto...
```

### Versioni Markdown (.md)

Ogni contenuto pubblicato è accessibile in Markdown:

| URL originale | URL Markdown |
|---------------|--------------|
| `tuosito.com/chi-siamo/` | `tuosito.com/chi-siamo.md` |
| `tuosito.com/blog/articolo/` | `tuosito.com/blog/articolo.md` |
| `tuosito.com/prodotto/nome/` | `tuosito.com/prodotto/nome.md` |

### Tag HTML nel `<head>`

Il plugin aggiunge automaticamente riferimenti nel codice HTML:

```html
<!-- Su tutte le pagine -->
<link rel="llms-txt" type="text/plain" href="https://tuosito.com/llms.txt" />

<!-- Su singole pagine/post -->
<link rel="alternate" type="text/markdown" title="Versione Markdown" 
      href="https://tuosito.com/pagina.md" />
```

---

## Pannello di Controllo

Accedi alle impostazioni da **Impostazioni → AI Friendly**.

Il pannello è organizzato in 4 tab:

---

### Tab: Contenuto llms.txt

#### Contenuto personalizzato

Puoi scrivere manualmente il contenuto del file `llms.txt` in formato Markdown:

```markdown
# La Mia Azienda

> Siamo leader nel settore XYZ dal 1990.

## Chi siamo

Descrizione dettagliata dell'azienda, mission, valori...

## Servizi principali

- Consulenza strategica
- Sviluppo software
- Formazione
```

Se lasci il campo vuoto, il plugin genera automaticamente l'indice basandosi sui contenuti pubblicati.

#### Lista automatica

**☑️ Aggiungi lista automatica dopo il contenuto custom**

Se attivato, il plugin aggiunge automaticamente le sezioni `## Pagine`, `## Post`, `## Prodotti` dopo il tuo contenuto personalizzato.

Utile se vuoi scrivere un'introduzione custom ma mantenere l'indice aggiornato automaticamente.

---

### Tab: Filtri & Esclusioni

Controllo granulare su quali contenuti includere nell'output AI Friendly.

#### Tipi di contenuto da includere

| Opzione | Descrizione |
|---------|-------------|
| ☑️ Pagine | Include le pagine WordPress standard |
| ☑️ Articoli (Post) | Include i post del blog |
| ☐ Prodotti WooCommerce | Include i prodotti (visibile solo se WooCommerce è attivo) |
| ☐ Custom Post Types | Lista dinamica di tutti i CPT registrati nel sito |

#### Esclusioni

##### Opzioni generali

| Opzione | Descrizione |
|---------|-------------|
| ☑️ Escludi pagine con meta `noindex` | Esclude contenuti marcati come noindex dai plugin SEO (Yoast, Rank Math, AIOSEO, SEOPress) |
| ☑️ Escludi contenuti protetti da password | Esclude pagine/post con password |

##### Escludi categorie

Seleziona una o più categorie da escludere completamente. Tutti i post appartenenti a queste categorie non appariranno in `llms.txt` e non avranno versione `.md`.

**Casi d'uso:**
- Categoria "Bozze" o "Draft"
- Categoria "Area riservata"
- Categoria "Landing temporanee"

##### Escludi tag

Stesso funzionamento delle categorie, ma per i tag.

##### Escludi template

Seleziona template di pagina da escludere:
- Landing Page
- Full Width
- Blank Template
- ecc.

##### Escludi pattern URL

Inserisci pattern URL (uno per riga) per escludere contenuti in base al loro permalink:

```
/landing/*
/promo-*
/test/
/temp/*
```

**Sintassi supportata:**
- `*` — Wildcard (qualsiasi sequenza di caratteri)
- `?` — Singolo carattere
- `/regex/` — Espressione regolare (racchiusa tra slash)

**Esempi:**

| Pattern | Esclude |
|---------|---------|
| `/landing/*` | Tutte le pagine sotto `/landing/` |
| `/promo-*` | URL che iniziano con `/promo-` |
| `*-test` | URL che finiscono con `-test` |
| `/^\/temp\//` | Regex: tutto ciò che inizia con `/temp/` |

---

### Tab: Versioning MD

Gestione dei file Markdown statici.

#### File MD statici

**☑️ Salva e servi file MD statici (più veloce)**

| Stato | Comportamento |
|-------|---------------|
| ☐ Disattivo | I file `.md` vengono generati dinamicamente ad ogni richiesta |
| ☑️ Attivo | I file `.md` vengono salvati su disco e serviti direttamente |

**Vantaggi della modalità statica:**
- ⚡ Più veloce (nessuna elaborazione PHP ad ogni richiesta)
- 💾 Cache automatica
- 📊 Possibilità di analizzare i file generati

**Directory di salvataggio:**
```
wp-content/uploads/ai-friendly/versions/
```

**Nota sicurezza (whitelist file):**  
I file serviti dal plugin devono rispettare il formato `a-z0-9._-` + estensione `.md`.  
Qualsiasi filename diverso viene scartato per evitare path traversal.

#### Statistiche

Il pannello mostra:
- **File salvati:** Numero totale di file `.md` generati
- **Spazio utilizzato:** Dimensione totale su disco
- **Ultima rigenerazione:** Data/ora con statistiche dettagliate

#### Azioni manuali

| Pulsante | Azione |
|----------|--------|
| 🔄 **Rigenera tutti i file MD** | Rigenera i file, ma solo se il contenuto è cambiato (checksum) |
| ⚡ **Forza rigenerazione** | Rigenera tutti i file ignorando il checksum |
| 🗑️ **Elimina tutti i file** | Rimuove tutti i file `.md` dalla directory |

---

### Tab: Scheduler

Automazione della rigenerazione dei file MD.

#### Rigenerazione automatica

**☑️ Attiva rigenerazione automatica via cron**

Quando attivo, WordPress eseguirà la rigenerazione di tutti i file MD ad intervalli regolari.

**Intervallo:** Configurabile da 1 a 168 ore (1 settimana)

Il pannello mostra la data/ora della prossima esecuzione programmata.

#### Trigger su eventi

| Opzione | Descrizione |
|---------|-------------|
| ☑️ **Rigenera quando un contenuto viene salvato** | Ogni volta che salvi/aggiorni un post o una pagina, il file `.md` corrispondente viene rigenerato |
| ☑️ **Rigenera solo se il contenuto è cambiato** | Usa un checksum MD5 per verificare se il contenuto è effettivamente cambiato prima di riscrivere il file |

**Nota:** L'opzione checksum è consigliata per evitare scritture inutili su disco e ridurre il carico del server.

---

## Metabox per singoli contenuti

In ogni pagina, post o prodotto, troverai un metabox "AI Friendly" nella sidebar:

```
┌─────────────────────────────────┐
│ AI Friendly                     │
├─────────────────────────────────┤
│ ☐ Escludi da llms.txt e        │
│   versione .md                  │
│                                 │
│ Ultima generazione MD:          │
│ 2025-01-15 14:30:22            │
└─────────────────────────────────┘
```

Questo permette di escludere singoli contenuti senza dover modificare le regole globali.

---

## Costanti configurabili

Puoi sovrascrivere le impostazioni di default aggiungendo queste costanti nel file `wp-config.php`:

```php
// Limiti per la lista automatica in llms.txt
define( 'AI_FR_PAGES_LIMIT', 50 );   // Max pagine (default: 50)
define( 'AI_FR_POSTS_LIMIT', 30 );   // Max post (default: 30)
define( 'AI_FR_EXCERPT_LEN', 160 );  // Lunghezza excerpt in caratteri (default: 160)

// Output Markdown
define( 'AI_FR_INCLUDE_METADATA', true );     // Includi frontmatter YAML (default: true)
define( 'AI_FR_NORMALIZE_HEADINGS', true );   // Normalizza heading H1 unico (default: true)
```

---

## Struttura output Markdown

Ogni file `.md` generato ha questa struttura:

### 1. Frontmatter YAML (metadati)

```yaml
---
title: Titolo della pagina
description: Meta description o estratto del contenuto
featured_image: https://tuosito.com/wp-content/uploads/immagine.jpg
date: 2025-01-15
modified: 2025-01-20
author: Nome Autore
url: https://tuosito.com/pagina/
categories: [Categoria 1, Categoria 2]
tags: [Tag1, Tag2, Tag3]
---
```

### 2. Titolo H1

```markdown
# Titolo della pagina
```

### 3. Immagine in evidenza

```markdown
![Titolo della pagina](https://tuosito.com/featured-image.jpg)
```

### 4. Contenuto convertito

Il contenuto HTML viene convertito in Markdown pulito:

| Elemento HTML | Conversione Markdown |
|---------------|----------------------|
| `<h1>`...`<h6>` | `#` ... `######` (con normalizzazione) |
| `<p>` | Paragrafi separati da righe vuote |
| `<strong>`, `<b>` | `**testo**` |
| `<em>`, `<i>` | `*testo*` |
| `<a href="...">` | `[testo](url)` |
| `<img>` | `![alt](src)` (solo se ha alt text) |
| `<ul>`, `<ol>` | Liste con `-` |
| `<blockquote>` | `> citazione` |
| `<pre><code>` | Blocchi ``` fenced ``` |
| `<code>` inline | `` `codice` `` |
| `<table>` | Tabelle Markdown |
| `<hr>` | `---` |

### Cosa viene rimosso automaticamente

- Script, style, SVG, iframe
- Form e tutti gli elementi di input
- Elementi di navigazione (`<nav>`, `<aside>`, `<footer>`)
- Elementi nascosti (`hidden`, `display:none`, `aria-hidden`)
- Shortcode di form (Contact Form 7, WPForms, ecc.)
- Shortcode di slider, gallery, ads
- Link anchor interni (`#sezione`)
- Bottoni e CTA non informativi ("Scopri di più", "Invia", "Avanti", ecc.)
- Immagini senza alt text o con alt generico ("image", "foto", "banner")
- Commenti HTML (inclusi quelli di Gutenberg)

---

## Compatibilità

### Page Builder supportati

Il plugin estrae correttamente il contenuto da:

- ✅ Gutenberg (editor a blocchi)
- ✅ Classic Editor
- ✅ Elementor
- ✅ Breakdance
- ✅ YOOtheme
- ✅ Oxygen
- ✅ Bricks
- ✅ Divi (parziale)
- ✅ WPBakery (parziale)

### Plugin SEO supportati

Per l'estrazione di metadati (title, description) e rilevamento noindex:

- ✅ Yoast SEO
- ✅ Rank Math
- ✅ All in One SEO
- ✅ SEOPress

**Nota:** Il plugin funziona perfettamente anche SENZA alcun plugin SEO installato. In quel caso usa semplicemente il titolo WordPress standard e genera l'excerpt dal contenuto.

### WooCommerce

Supporto completo per i prodotti WooCommerce (attivabile dalle impostazioni).

---

## Struttura del plugin

Il plugin è organizzato in moduli per rendere manutenzione e sviluppo più chiari.

```
ai-friendly.php
includes/
  boot.php
  constants.php
  options.php
  activation.php
  content-filter.php
  versioning.php
  scheduler.php
  converter.php
  metadata.php
  intercept.php
  llms.php
  markdown.php
  head.php
  utils.php
admin/
  metabox.php
  settings-page.php
```

- `ai-friendly.php` fa da bootstrap e carica `includes/boot.php`.
- `includes/` contiene la logica core del plugin.
- `admin/` contiene UI di admin, metabox e AJAX.

---

## Hook e Filtri per sviluppatori

### Filtro: contenuto llms.txt

```php
add_filter( 'ai_fr_llms_txt_content', function( $content ) {
    // Modifica il contenuto di llms.txt
    $content .= "\n\n## Sezione personalizzata\n";
    $content .= "Contenuto aggiuntivo...";
    return $content;
} );
```

### Filtro: TTL cache Markdown

```php
add_filter( 'ai_fr_md_cache_ttl', function( int $ttl, int $post_id, WP_Post $post ) {
    return 6 * HOUR_IN_SECONDS; // es. 6 ore
}, 10, 3 );
```

### Filtro: canonical URL per .md

```php
add_filter( 'ai_fr_md_canonical_url', function( string $canonical, int $post_id, WP_Post $post ) {
    // Sovrascrivi il canonical per la versione .md
    return 'https://tuosito.com/canonical-custom/';
}, 10, 3 );
```

### Filtro: meta che invalidano la cache

```php
add_filter( 'ai_fr_md_cache_meta_keys', function( array $keys, int $post_id, string $meta_key ) {
    $keys[] = '_my_custom_meta';
    return $keys;
}, 10, 3 );
```

### Filtro: accesso contenuti (.md / llms.txt)

```php
add_filter( 'ai_fr_can_serve_post', function( bool $can, WP_Post $post, string $context ) {
    // Esempio: blocca tutti i contenuti "private"
    if ( $post->post_status === 'private' ) {
        return false;
    }

    return $can;
}, 10, 3 );
```

#### Integrazioni membership (esempi pronti)

```php
// MemberPress
add_filter( 'ai_fr_can_serve_post', function( bool $can, WP_Post $post, string $context ) {
    if ( function_exists( 'mepr_is_protected' ) && mepr_is_protected( $post->ID ) ) {
        return false;
    }
    return $can;
}, 10, 3 );

// Restrict Content Pro
add_filter( 'ai_fr_can_serve_post', function( bool $can, WP_Post $post, string $context ) {
    if ( function_exists( 'rcp_is_restricted_content' ) && rcp_is_restricted_content( $post->ID ) ) {
        return false;
    }
    return $can;
}, 10, 3 );

// Paid Memberships Pro
add_filter( 'ai_fr_can_serve_post', function( bool $can, WP_Post $post, string $context ) {
    if ( function_exists( 'pmpro_has_membership_access' )
      && ! pmpro_has_membership_access( $post->ID, null, false ) ) {
        return false;
    }
    return $can;
}, 10, 3 );
```

### Verifica inclusione post

```php
// Verifica se un post sarà incluso
$filter = new AiFrContentFilter();
$post = get_post( 123 );

if ( $filter->shouldInclude( $post ) ) {
    // Il post sarà incluso in AI Friendly
}
```

### Genera Markdown programmaticamente

```php
// Genera il markdown di un post
$post = get_post( 123 );
$markdown = ai_fr_generate_markdown( $post );
```

### Gestione versioni

```php
// Salva versione MD
$result = AiFrVersioning::saveVersion( $post_id, $md_content );
// Ritorna: ['saved' => bool, 'path' => string, 'checksum' => string, 'changed' => bool]

// Leggi versione salvata
$content = AiFrVersioning::getVersion( $post_id );

// Verifica se esiste versione valida
$exists = AiFrVersioning::hasValidVersion( $post_id );

// Elimina versione
AiFrVersioning::deleteVersion( $post_id );

// Statistiche
$stats = AiFrVersioning::getStats();
// Ritorna: ['count' => int, 'size' => int, 'files' => array]

// Pulisci tutto
$deleted_count = AiFrVersioning::clearAll();
```

### Rigenera tutti i file

```php
// Rigenera rispettando checksum
$stats = ai_fr_regenerate_all( false );

// Forza rigenerazione
$stats = ai_fr_regenerate_all( true );

// Ritorna: ['processed' => int, 'regenerated' => int, 'skipped' => int, 'errors' => int]
```

---

## FAQ

### Il plugin rallenta il sito?

No. Il file `llms.txt` e le versioni `.md` vengono generati solo quando richiesti. Con la modalità "file statici" attiva, vengono serviti direttamente da disco senza elaborazione PHP.

### Posso escludere singole pagine?

Sì, in due modi:
1. Usa il metabox "AI Friendly" nel singolo post/pagina
2. Configura le esclusioni globali (categorie, tag, template, pattern URL)

### I file .md vengono indicizzati da Google?

No. Il plugin aggiunge automaticamente l'header `X-Robots-Tag: noindex, nofollow` per evitare contenuti duplicati.

### Funziona con siti multilingua?

Sì. Ogni versione linguistica della pagina avrà la sua versione `.md` corrispondente.

### Posso personalizzare completamente llms.txt?

Sì. Scrivi il contenuto nel campo "Contenuto llms.txt" nelle impostazioni. Se vuoi anche la lista automatica, attiva l'opzione corrispondente.

### Come verifico che funzioni?

1. Visita `tuosito.com/llms.txt`
2. Visita `tuosito.com/una-pagina-qualsiasi.md`
3. Aggiungi `?debug=1` all'URL `.md` (da admin) per vedere informazioni di debug

### Il plugin ha dipendenze?

No. AI Friendly è completamente standalone e non richiede altri plugin. L'integrazione con plugin SEO è opzionale e funziona in loro assenza.

### Come aggiorno il plugin?

Sostituisci il file `ai-friendly.php` con la nuova versione. Le impostazioni vengono mantenute.

---

## Changelog

Il changelog completo è in `CHANGELOG.md`.

---

## Supporto

Per segnalare bug o richiedere funzionalità:

**Sernicola Labs**  
https://sernicola-labs.com

---

*Documentazione aggiornata alla versione 1.5.2*
