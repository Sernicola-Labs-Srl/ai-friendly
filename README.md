# Sernicola Labs AI Friendly

**Version:** 2.1.1
**Author:** Sernicola Labs
**Requirements:** WordPress 6.0+, PHP 8.1+
**License:** GPL v3 or later

---

## Indice

1. [Introduzione](#introduzione)
2. [Installazione e aggiornamento](#installazione-e-aggiornamento)
3. [Come funziona](#come-funziona)
4. [AI Content Hub (admin)](#ai-content-hub-admin)
5. [Regole di inclusione/esclusione](#regole-di-inclusioneesclusione)
6. [Output Markdown](#output-markdown)
7. [Semantic Schema JSON-LD](#semantic-schema-json-ld)
8. [Compatibilità](#compatibilità)
9. [Debug e diagnostica](#debug-e-diagnostica)
10. [Hook e filtri](#hook-e-filtri)
11. [FAQ](#faq)
12. [Release checklist](#release-checklist)
13. [Changelog](#changelog)

---

## Introduzione

Sernicola Labs AI Friendly espone contenuti WordPress in un formato più leggibile per sistemi AI/LLM:

- `/llms.txt` con indice dei contenuti
- endpoint `.md` per singoli contenuti pubblici
- (se disponibile) endpoint `.md` per archive CPT pubblici, ad esempio `/podcast.md`
- layer opzionale Semantic Schema JSON-LD per identità, profili e contesto AI-friendly
- aggiornamenti nativi tramite la directory ufficiale wordpress.org

Il plugin include anche un pannello admin (AI Content Hub) per gestire regole, rigenerazione, snapshot e diagnostica.

### Privacy e dati locali

AI Friendly non contatta servizi esterni e non trasmette telemetria o dati di utilizzo. Il registro attività contiene solo eventi tecnici, non include identificativi utente, resta nel database WordPress locale ed è limitato a 200 elementi. Le notifiche email sono opzionali e passano esclusivamente dal sistema email configurato nel sito.

L'estrazione dei campi ACF è disattivata per impostazione predefinita. Deve essere abilitata solo quando tutti i valori testuali ACF dei contenuti inclusi sono destinati all'output pubblico `.md`.

La rimozione del plugin da WordPress elimina opzioni, log, snapshot, file generati, eventi pianificati e metadati creati da AI Friendly.

---

## Installazione e aggiornamento

### Installazione

1. Carica lo zip del plugin da WordPress (`Plugin > Aggiungi nuovo > Carica plugin`)
2. Attiva il plugin
3. Apri `Impostazioni > AI Friendly`

### Configurazione guidata e interfaccia Hub

Al primo accesso, la configurazione guidata analizza il sito e accompagna attraverso cinque passaggi: rilevamento, contenuti inclusi, Markdown e automazione, Semantic Schema e riepilogo. Pagine e Articoli sono proposti come inclusione iniziale; CPT e prodotti devono essere scelti esplicitamente.

Le scelte restano nel browser durante la sessione e vengono salvate nel database solo con **Salva e genera**. Se i file statici sono attivi viene eseguita la prima generazione; altrimenti viene verificato l'output dinamico. **Configura più tardi** chiude il pannello senza modificare le impostazioni e la procedura può essere riaperta dall'header usando i valori correnti.

Le sezioni Overview, Content, Rules, Schema e Automation condividono lo stesso sistema visivo. Nelle sezioni modificabili, la barra inferiore segnala le modifiche non salvate.

La sezione Schema mostra soltanto i moduli pertinenti al tipo di entità selezionato (`Person` o `Organization`) senza cancellare i valori temporaneamente nascosti. I campi ripetibili partono da uno stato vuoto esplicito e possono essere aggiunti o rimossi senza righe segnaposto.

### Aggiornamento

1. Aggiorna lo zip del plugin
2. Verifica in admin la versione mostrata in alto (deve combaciare con il package)
3. Se usi file statici `.md`, esegui `Forza rigenerazione`

Per il passaggio dalla versione con slug `ai-friendly` al nuovo pacchetto `sernicola-labs-ai-friendly`:

1. Lascia installato il vecchio plugin e installa il nuovo pacchetto
2. Attiva `Sernicola Labs AI Friendly`
3. Il nuovo plugin importa i dati e disattiva automaticamente la precedente versione
4. Verifica il riepilogo della migrazione e le impostazioni nella Content Hub
5. Esegui `Forza rigenerazione` se usi file statici `.md`
6. Elimina il vecchio plugin soltanto dopo la verifica

La migrazione copia impostazioni, esclusioni dei contenuti, Schema personalizzati e snapshot. La Content Hub mostra un riepilogo temporaneo con lo stato della rigenerazione e del vecchio plugin; il riepilogo scompare automaticamente dopo una rigenerazione conclusa senza errori e la rimozione del precedente plugin. Le vecchie chiavi restano disponibili fino a quel momento. In multisite, una versione attiva a livello rete viene disattivata automaticamente solo durante l'attivazione a livello rete del nuovo plugin.

Note:
- Le opzioni restano nel database (`saifr_options`)
- In hosting con OPcache/FPM può servire un reload del pool PHP dopo update manuali
- Dopo la pubblicazione nella directory ufficiale, gli aggiornamenti automatici sono gestiti da wordpress.org

---

## Come funziona

### 1) `llms.txt`

`/llms.txt` viene generato dinamicamente e può combinare:

- contenuto custom scritto in admin
- lista automatica dei contenuti inclusi

### 2) Endpoint `.md`

Per i contenuti pubblici inclusi dalle regole:

- `https://sito.tld/pagina/` -> `https://sito.tld/pagina.md`
- `https://sito.tld/cpt/slug/` -> `https://sito.tld/cpt/slug.md`

Per CPT con archive pubblico (`has_archive`), se abilitati:

- `https://sito.tld/podcast/` -> `https://sito.tld/podcast.md`

### 3) Modalità statica (opzionale)

Se attivi "File MD statici":

- il plugin salva versioni `.md` nella directory upload del sito, sotto `sernicola-labs-ai-friendly/versions/`
- le richieste possono essere servite dal file salvato
- file vuoti/non validi non vengono considerati validi in serving

---

## AI Content Hub (admin)

Il pannello è diviso in 5 sezioni:

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
- **Schema**
  - identità `Person` / `Organization`
  - profili `sameAs`, competenze `knowsAbout`, lingue, immagine e contesto business
  - pagina `ProfilePage` e licenza contenuti
  - modalità auto/standalone/estensione Yoast/estensione Rank Math

---

## Regole di inclusione/esclusione

La pipeline controlla:

1. esclusione manuale (`_saifr_exclude`)
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

Se il contenuto principale è scarso, il plugin usa fallback:

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

## Semantic Schema JSON-LD

Il modulo **Semantic Schema** aggiunge un layer JSON-LD pensato per descrivere meglio l'identità del sito e renderla più chiara a crawler, motori di ricerca e sistemi AI.

Non sostituisce Yoast SEO o Rank Math: quando uno dei due è attivo, AI Friendly estende il grafo esistente e fonde i propri dati nei nodi già presenti con lo stesso `@id`. In questo modo evita duplicati come due nodi `Organization` o `Person` concorrenti.

### Modalità disponibili

- `auto`: estende Yoast o Rank Math se rilevati, altrimenti stampa JSON-LD standalone
- `standalone`: genera un grafo minimo AI Friendly
- `extend_yoast`: aggiunge o fonde nodi nel grafo Yoast
- `extend_rank_math`: aggiunge o fonde nodi nel JSON-LD Rank Math

### Campi configurabili

Dalla sezione **Schema** dell'AI Content Hub puoi configurare:

- entità principale: `Person` oppure `Organization`
- nome, nome alternativo e descrizione
- descrizione disambiguante (`disambiguatingDescription`)
- tipi aggiuntivi ripetibili per `Organization`, emessi come vero array `@type`
- slogan, data di fondazione e aree servite per `Organization`
- ragione sociale, partita IVA, codice fiscale, LEI con `iso6523Code` e ticker per `Organization`
- logo aziendale dedicato, sede `Place` con coordinate, trasporto pubblico e orari
- punti di contatto ripetibili per reparti con telefono, email, lingue e disponibilità
- certificazioni e identificatori generici come RUNTS, REA e ATECO
- fondatori opzionali, con ruolo separato e non obbligatorio
- catalogo servizi opzionale come `OfferCatalog`, compilabile manualmente o da ID/permalink di termini, pagine e CPT WordPress
- immagine identitaria
- profili esterni `sameAs`
- competenze o argomenti autorevoli `knowsAbout`
- lingue `knowsLanguage`
- pagina profilo `ProfilePage`
- URL di licenza dei contenuti

- entità collegate: testate (`Periodical`, `Newspaper`), collane (`BookSeries`, `CreativeWorkSeries`), podcast, siti, cicli di eventi (`EventSeries`), brand e società del gruppo
- IVA inclusa/esclusa e periodicità (mensile/annuale) sulle voci del catalogo servizi

Nel metabox del singolo post, pagina o CPT puoi inoltre attivare un nodo `Course`, `Event`, `Service` o `FAQPage`. Titolo, permalink, descrizione, immagine e date editoriali vengono riusati dal contenuto; il metabox richiede solo i campi specifici del tipo scelto.

### Schema automatico per tipo di contenuto

Quando un sito pubblica molti eventi, corsi o servizi come CPT, compilare il metabox di ogni contenuto non è sostenibile. Nella card **Schema automatico per tipo di contenuto** puoi assegnare a un post type il tipo `Event`, `Course` o `Service` e indicare da dove leggere ogni campo:

| Sorgente | Esempio | Legge |
|---|---|---|
| `meta:chiave` (o solo `chiave`) | `meta:_event_start` | un campo personalizzato |
| `acf:campo` | `acf:data_inizio` | un campo ACF (le date non formattate, il resto con il formato ACF) |
| `tax:tassonomia` | `tax:citta` | i nomi dei termini assegnati |
| `text:valore` | `text:EUR` | un valore fisso |

L'interfaccia suggerisce le chiavi realmente usate dai contenuti pubblicati di ogni post type. I campi mappabili sono data di inizio e fine, nome e indirizzo del luogo, prezzo e valuta, codice e livello del corso, tipo di servizio e area servita; la modalità di partecipazione (in presenza, online, mista) si imposta per post type.

Le date sono riconosciute nei formati ISO 8601, `Ymd` (ACF), `Y-m-d H:i:s`, timestamp Unix e `gg/mm/aaaa` con o senza orario; i prezzi accettano forme come `€ 1.200,50`, `600,00` o `Gratuito`. Un `Event` senza data di inizio non viene pubblicato.

Nel metabox del singolo contenuto i valori ricavati automaticamente sono mostrati come riepilogo: i campi compilati a mano hanno la precedenza e l'opzione **Disattiva per questo contenuto** esclude la pagina. Data, luogo e prezzo compaiono anche nelle righe di `llms.txt` e nel frontmatter dei file `.md`.

Quando il plugin Breakdance è installato e attivo, AI Friendly mostra l'opzione dedicata — attiva per impostazione predefinita — e legge automaticamente le coppie domanda/risposta dal tree del builder. Segue gli eventuali Global Block e genera `FAQPage` senza snippet. Se Breakdance non è attivo, l'opzione non viene mostrata e la lettura non viene eseguita. Le FAQ configurate nel metabox vengono fuse nello stesso nodo e hanno precedenza sulle domande duplicate.

Le sorgenti WordPress dell'`OfferCatalog` accettano un ID termine, una forma esplicita `taxonomy:slug`, il permalink di una categoria/tassonomia oppure il permalink di una pagina o CPT. Nome, URL, descrizione e tipo vengono ricavati dai dati WordPress correnti; le righe manuali possono completare il catalogo e prevalgono sui duplicati con lo stesso URL.

### Prodotti WooCommerce e schede commerciante

Con WooCommerce attivo compare la card **Prodotti WooCommerce: schede commerciante**, pensata per i campi che Search Console segnala come mancanti nello schema `Product`:

- **brand**: letto dal prodotto tramite una sorgente (`tax:product_brand`, `tax:pa_marca`, `meta:_brand`, `acf:brand`) oppure, in mancanza, dal brand predefinito
- **spedizioni**: una regola per area con paesi ISO (`IT, SM`), costo, soglia di gratuità calcolata sul prezzo dell'offerta (o sul prezzo minimo dei prodotti variabili) e giorni di preparazione e consegna, emessi come `OfferShippingDetails`
- **resi**: finestra in giorni, illimitata o resi non accettati, modalità (spedizione, negozio, punto di ritiro) e costi (gratuito, a carico del cliente, costo fisso), emessi come `MerchantReturnPolicy`; il paese di destinazione del reso è quello del negozio WooCommerce
- **validFrom**: data di inizio della promozione in corso, altrimenti data di ultima modifica del prodotto, aggiunta all'offerta e alle `priceSpecification`

L'arricchimento si applica ai dati strutturati di WooCommerce e ai nodi `Product` generati da Yoast WooCommerce SEO o Rank Math. I valori già presenti non vengono sovrascritti.

### Pulizia e compatibilità

Il modulo applica alcune normalizzazioni per mantenere il grafo pulito:

- deduplica gli URL `sameAs`, anche quando differiscono solo per lo slash finale
- evita `jobTitle` su `Organization`, mantenendolo solo per `Person`
- rimuove dimensioni immagine vuote (`width`/`height`) dal JSON-LD finale
- in modalità estensione lascia ai plugin SEO i nodi base come `WebSite`, `WebPage`, `Article`, `BreadcrumbList` e prodotti
- aggiunge il catalogo servizi solo come nodo separato collegato all'entità principale, evitando di duplicare `WebSite` o `WebPage`
- mantiene compatibilità con eventuali cataloghi legacy salvati come JSON, ma la UI usa campi ripetibili per evitare errori manuali

---

## Compatibilità

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

Supporto prodotti `product` se WooCommerce è attivo e abilitato nelle regole.

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

- viene richiesta modalità debug
- le informazioni debug nel body vengono mostrate solo ad admin
- la risposta HTTP resta no-cache per facilitare troubleshooting

---

## Hook e filtri

### `saifr_llms_txt_content`

Permette di modificare il contenuto finale di `llms.txt`.

### `saifr_md_cache_ttl`

Permette di modificare TTL cache markdown dinamica.

### `saifr_md_canonical_url`

Override del canonical header per endpoint `.md`.

### `saifr_md_cache_meta_keys`

Aggiunge chiavi meta che invalidano la cache markdown.

### `saifr_can_serve_post`

Controllo finale sulla possibilità di esporre un contenuto via `.md` / `llms.txt`.

### `saifr_schema_enabled`

Permette di abilitare/disabilitare programmaticamente il modulo Semantic Schema.

### `saifr_schema_identity`

Permette di modificare il nodo `Person` / `Organization` prima dell'output.

### `saifr_schema_graph`

Permette di modificare il grafo AI Friendly prima della stampa standalone o della fusione con Yoast/Rank Math.

### `saifr_schema_content_node`

Modifica il nodo `Event`, `Course`, `Service` o `FAQPage` del singolo contenuto. Riceve il nodo, il post, i valori salvati nel metabox e i dati risolti (tipo, valori, provenienza manuale/automatica).

### `saifr_schema_type_rule`, `saifr_schema_mapped_value`, `saifr_schema_parse_datetime`

Permettono di definire da codice la mappatura di un post type, trasformare il valore letto da una sorgente e interpretare formati data non standard.

### `saifr_schema_related_entity_types`, `saifr_schema_related_entity_node`

Estendono i tipi di entità collegate (con la relazione verso l'organizzazione) e modificano il singolo nodo prima dell'output.

### `saifr_woo_schema_enabled`, `saifr_woo_product_markup`

Attivano o disattivano da codice l'arricchimento dei prodotti WooCommerce e permettono di modificare il nodo `Product` finale (per esempio per regole di spedizione per categoria).

### `saifr_schema_llms_facts`

Modifica il riepilogo di data e luogo aggiunto alle righe di `llms.txt`.

### `saifr_faq_enabled`, `saifr_faq_items`, `saifr_faq_answer_html`, `saifr_faq_node`

Controllano rispettivamente l'attivazione della lettura FAQ Breakdance, le coppie estratte, il markup HTML ammesso nelle risposte e il nodo `FAQPage` finale.

### `saifr_breakdance_active`

Permette di personalizzare il rilevamento del plugin Breakdance attivo in installazioni con directory o bootstrap non standard.

---

## FAQ

### I file `.md` vengono indicizzati dai motori?

Di default viene inviato `X-Robots-Tag: noindex, follow`.

### Posso escludere singoli contenuti?

Sì, con metabox per singolo contenuto o con regole globali nel tab Rules.

### Posso usare solo contenuto custom per `llms.txt`?

Sì. Compila l'editor `llms.txt` e disattiva "Aggiungi lista automatica".

### Ho aggiornato il plugin ma il comportamento non cambia

Controlla:

1. versione mostrata in pagina opzioni
2. header `X-AI-Friendly-Version` in risposta `.md`
3. cache server/CDN/OPcache

### Funziona su CPT creati con ACF?

Sì. Sia in risoluzione URL `.md` sia in estrazione contenuto testuale.

---

## Release checklist

Usa questa checklist ad ogni nuova release.

1. **Versioning**
- Aggiorna `Version:` in `sernicola-labs-ai-friendly.php`
- Aggiorna `SAIFR_VERSION` in `sernicola-labs-ai-friendly.php`
- Aggiorna `CHANGELOG.md`
- Usa tag coerenti con la versione del plugin, nel formato `v2.1.0`

2. **Documentazione**
- Verifica coerenza `README.md` con feature reali
- Aggiorna eventuali note su header/debug/compatibilità

3. **Packaging**
- Il push di un tag `v*` avvia la GitHub Action che crea e allega `sernicola-labs-ai-friendly.zip` alla release
- Verifica che lo ZIP abbia come cartella radice `sernicola-labs-ai-friendly/` e contenga `sernicola-labs-ai-friendly.php`, `uninstall.php`, `includes/`, `admin/`, `README.md`, `readme.txt`, `CHANGELOG.md`
- Escludi file non necessari al runtime (es. `.git`, `.github`, `.agents`, `.gitignore` e file locali IDE)

4. **Deploy**
- Aggiorna plugin su ambiente test/staging
- Verifica versione mostrata in `Impostazioni > AI Friendly`
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
