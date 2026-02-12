<?php
/**
 * Plugin Name:        Sernicola Labs | AI Friendly — llms.txt & Markdown
 * Description:        Genera /llms.txt e versioni .md di post e pagine.
 * Version:            1.6.3
 * Changelog:          CHANGELOG.md
 * Author:             Sernicola Labs
 * Author URI:         https://sernicola-labs.com
 * License:            GPL v2 or later
 * Requires at least:  6.0
 * Requires PHP:       8.1
 *
 * Changelog 1.6.3:
 *   - Fix robustezza rendering .md per CPT/ACF (fallback contenuto + archive markdown)
 *   - Harden output: evita risposte body vuoto e aggiunge header diagnostici
 *   - Migliorata invalidazione cache su meta ACF e versioni statiche vuote
 *
 * Changelog 1.6.2:
 *   - Editor llms con syntax highlighting (CodeMirror WP)
 *   - Diff snapshot line-by-line affiancato (LCS) con numerazione righe
 *   - Paginazione nella tabella contenuti del Content Manager
 *   - Notifiche su errori rigenerazione (admin notice/email)
 *   - Diagnostica sitemap/robots estesa in overview
 *
 * Changelog 1.6.1:
 *   - Wizard iniziale a 3 step (tipo sito, inclusioni base, bozza editor)
 *   - Diff snapshot llms a due colonne (aggiunte/rimozioni) con confronto selezionati
 *   - Validazione link in anteprima llms e note automatiche su snapshot
 *
 * Changelog 1.6.0:
 *   - Nuova UX "AI Content Hub" con macro-sezioni Overview/Content/Rules/Automation
 *   - Dashboard overview con warning diagnostici e quick actions
 *   - Content manager con filtri e toggle inclusione/esclusione via AJAX
 *   - Timeline eventi e telemetry ring-buffer (option ai_fr_event_log)
 *   - Asset admin separati (CSS/JS) e endpoint AJAX estesi
 *   - Snapshot llms.txt + preview live + AI simulation locale (heuristics)
 *
 * Changelog 1.5.2:
 *   - HTTP Link canonical header for .md responses
 *   - X-Robots-Tag set to noindex, follow
 *   - New filter ai_fr_md_canonical_url
 *
 * Changelog 1.5.1:
 *   - Fix TypeError in meta cache invalidation (deleted_post_meta array)
 *   - Cache invalidation: meta hooks + filters
 *   - Filename whitelist for static .md files
 *   - Access checks for .md/llms.txt with filter support
 *
 * Changelog 1.5.0:
 *   - Controllo granulare inclusioni/esclusioni (categorie, CPT, template, pattern URL, noindex)
 *   - Pannello admin avanzato con checkbox per tipi di contenuto
 *   - Salvataggio versioni MD statiche in wp-content/uploads/ai-friendly/versions/
 *   - Scheduler per rigenerazione automatica (cron, on-save, checksum)
 *   - Sistema di checksum per evitare rigenerazioni inutili
 *
 * Changelog 1.4.0:
 *   - Frontmatter YAML con metadati
 *   - Normalizzazione heading
 *   - Rimozione shortcode intelligente
 *   - Pulizia HTML avanzata
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
    add_action( 'admin_notices', function () {
        echo '<div class="notice notice-error"><p>'
           . '<strong>AI Friendly</strong> richiede PHP ≥ 8.1 '
           . '(versione attuale: ' . PHP_VERSION . ').</p></div>';
    } );
    return;
}

if ( ! defined( 'AI_FR_PLUGIN_FILE' ) ) {
    define( 'AI_FR_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'AI_FR_PLUGIN_DIR' ) ) {
    define( 'AI_FR_PLUGIN_DIR', __DIR__ );
}
if ( ! defined( 'AI_FR_VERSION' ) ) {
    define( 'AI_FR_VERSION', '1.6.3' );
}

require_once AI_FR_PLUGIN_DIR . '/includes/boot.php';

