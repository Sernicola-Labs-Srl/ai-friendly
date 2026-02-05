<?php
/**
 * Plugin Name:        Sernicola Labs | AI Friendly — llms.txt & Markdown
 * Description:        Genera /llms.txt e versioni .md di post e pagine.
 * Version:            1.5.0
 * Author:             Sernicola Labs
 * Author URI:         https://sernicola-labs.com
 * License:            GPL v2 or later
 * Requires at least:  6.0
 * Requires PHP:       8.1
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


// ═══════════════════════════════════════════════════════════════════════════════
//  COSTANTI
// ═══════════════════════════════════════════════════════════════════════════════

if ( ! defined( 'AI_FR_PAGES_LIMIT' ) )         define( 'AI_FR_PAGES_LIMIT', 50 );
if ( ! defined( 'AI_FR_POSTS_LIMIT' ) )         define( 'AI_FR_POSTS_LIMIT', 30 );
if ( ! defined( 'AI_FR_EXCERPT_LEN' ) )         define( 'AI_FR_EXCERPT_LEN', 160 );
if ( ! defined( 'AI_FR_INCLUDE_METADATA' ) )    define( 'AI_FR_INCLUDE_METADATA', true );
if ( ! defined( 'AI_FR_NORMALIZE_HEADINGS' ) )  define( 'AI_FR_NORMALIZE_HEADINGS', true );

// Directory per versioni MD statiche
define( 'AI_FR_VERSIONS_DIR', WP_CONTENT_DIR . '/uploads/ai-friendly/versions' );
define( 'AI_FR_VERSIONS_URL', WP_CONTENT_URL . '/uploads/ai-friendly/versions' );


// ═══════════════════════════════════════════════════════════════════════════════
//  ATTIVAZIONE / DISATTIVAZIONE
// ═══════════════════════════════════════════════════════════════════════════════

register_activation_hook( __FILE__, 'ai_fr_activate' );
register_deactivation_hook( __FILE__, 'ai_fr_deactivate' );

function ai_fr_activate(): void {
    // Crea directory per versioni MD
    if ( ! file_exists( AI_FR_VERSIONS_DIR ) ) {
        wp_mkdir_p( AI_FR_VERSIONS_DIR );
    }
    
    // Crea .htaccess per protezione (opzionale, i file sono pubblici ma evitiamo listing)
    $htaccess = AI_FR_VERSIONS_DIR . '/.htaccess';
    if ( ! file_exists( $htaccess ) ) {
        file_put_contents( $htaccess, "Options -Indexes\n" );
    }
    
    // Imposta opzioni di default
    $defaults = ai_fr_get_default_options();
    if ( ! get_option( 'ai_fr_options' ) ) {
        update_option( 'ai_fr_options', $defaults );
    }
    
    // Schedula cron se necessario
    ai_fr_schedule_cron();
}

function ai_fr_deactivate(): void {
    // Rimuovi cron
    wp_clear_scheduled_hook( 'ai_fr_cron_regenerate' );
}

function ai_fr_get_default_options(): array {
    return [
        // Contenuto llms.txt
        'llms_content'      => '',
        'llms_include_auto' => '1',
        
        // Tipi di contenuto da includere
        'include_pages'     => '1',
        'include_posts'     => '1',
        'include_products'  => '',
        'include_cpt'       => [],  // Array di CPT custom da includere
        
        // Esclusioni
        'exclude_categories'    => [],      // ID categorie da escludere
        'exclude_tags'          => [],      // ID tag da escludere
        'exclude_templates'     => [],      // Template da escludere
        'exclude_url_patterns'  => '',      // Pattern URL (uno per riga)
        'exclude_noindex'       => '1',     // Escludi pagine con noindex
        'exclude_password'      => '1',     // Escludi contenuti protetti da password
        
        // Versioning MD
        'static_md_files'       => '',      // Salva e servi file MD statici (più veloce)
        
        // Scheduler
        'auto_regenerate'       => '',      // Attiva rigenerazione automatica
        'regenerate_interval'   => 24,      // Ore tra rigenerazioni
        'regenerate_on_save'    => '1',     // Rigenera quando un contenuto viene salvato
        'regenerate_on_change'  => '1',     // Rigenera solo se checksum cambiato
    ];
}


// ═══════════════════════════════════════════════════════════════════════════════
//  FILTRO CONTENUTI - Classe per gestire inclusioni/esclusioni
// ═══════════════════════════════════════════════════════════════════════════════

class AiFrContentFilter {
    
    private array $options;
    
    public function __construct() {
        $this->options = wp_parse_args( 
            get_option( 'ai_fr_options', [] ), 
            ai_fr_get_default_options() 
        );
    }
    
    /**
     * Verifica se un post deve essere incluso nell'output AI Friendly.
     */
    public function shouldInclude( WP_Post $post ): bool {
        
        // 1. Escluso manualmente via metabox
        if ( get_post_meta( $post->ID, '_ai_fr_exclude', true ) ) {
            return false;
        }
        
        // 2. Contenuto protetto da password
        if ( ! empty( $this->options['exclude_password'] ) && $post->post_password !== '' ) {
            return false;
        }
        
        // 3. Verifica tipo di contenuto abilitato
        if ( ! $this->isPostTypeEnabled( $post->post_type ) ) {
            return false;
        }
        
        // 4. Escludi per categoria
        if ( $this->isExcludedByTaxonomy( $post->ID, 'category', $this->options['exclude_categories'] ?? [] ) ) {
            return false;
        }
        
        // 5. Escludi per tag
        if ( $this->isExcludedByTaxonomy( $post->ID, 'post_tag', $this->options['exclude_tags'] ?? [] ) ) {
            return false;
        }
        
        // 6. Escludi per template
        if ( $this->isExcludedByTemplate( $post->ID ) ) {
            return false;
        }
        
        // 7. Escludi per pattern URL
        if ( $this->isExcludedByUrlPattern( $post->ID ) ) {
            return false;
        }
        
        // 8. Escludi pagine con noindex (da plugin SEO)
        if ( ! empty( $this->options['exclude_noindex'] ) && $this->hasNoIndex( $post->ID ) ) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Verifica se il tipo di post è abilitato.
     */
    public function isPostTypeEnabled( string $post_type ): bool {
        switch ( $post_type ) {
            case 'page':
                return ! empty( $this->options['include_pages'] );
            case 'post':
                return ! empty( $this->options['include_posts'] );
            case 'product':
                return ! empty( $this->options['include_products'] );
            default:
                // CPT custom
                $enabled_cpt = $this->options['include_cpt'] ?? [];
                return in_array( $post_type, (array) $enabled_cpt, true );
        }
    }
    
    /**
     * Ottiene tutti i post type abilitati.
     */
    public function getEnabledPostTypes(): array {
        $types = [];
        
        if ( ! empty( $this->options['include_pages'] ) ) {
            $types[] = 'page';
        }
        if ( ! empty( $this->options['include_posts'] ) ) {
            $types[] = 'post';
        }
        if ( ! empty( $this->options['include_products'] ) && class_exists( 'WooCommerce' ) ) {
            $types[] = 'product';
        }
        
        // CPT custom
        $enabled_cpt = $this->options['include_cpt'] ?? [];
        $types = array_merge( $types, (array) $enabled_cpt );
        
        return array_unique( $types );
    }
    
    /**
     * Verifica se il post è escluso per tassonomia (categoria/tag).
     */
    private function isExcludedByTaxonomy( int $post_id, string $taxonomy, array $excluded_ids ): bool {
        if ( empty( $excluded_ids ) ) {
            return false;
        }
        
        $terms = wp_get_post_terms( $post_id, $taxonomy, [ 'fields' => 'ids' ] );
        if ( is_wp_error( $terms ) ) {
            return false;
        }
        
        return ! empty( array_intersect( $terms, array_map( 'intval', $excluded_ids ) ) );
    }
    
    /**
     * Verifica se il post è escluso per template.
     */
    private function isExcludedByTemplate( int $post_id ): bool {
        $excluded_templates = $this->options['exclude_templates'] ?? [];
        if ( empty( $excluded_templates ) ) {
            return false;
        }
        
        $template = get_page_template_slug( $post_id );
        return in_array( $template, (array) $excluded_templates, true );
    }
    
    /**
     * Verifica se il post è escluso per pattern URL.
     */
    private function isExcludedByUrlPattern( int $post_id ): bool {
        $patterns = $this->options['exclude_url_patterns'] ?? '';
        if ( empty( trim( $patterns ) ) ) {
            return false;
        }
        
        $permalink = get_permalink( $post_id );
        $patterns_array = array_filter( array_map( 'trim', explode( "\n", $patterns ) ) );
        
        foreach ( $patterns_array as $pattern ) {
            // Supporta wildcard * e regex
            if ( str_starts_with( $pattern, '/' ) && str_ends_with( $pattern, '/' ) ) {
                // È una regex
                if ( @preg_match( $pattern, $permalink ) ) {
                    return true;
                }
            } else {
                // Pattern semplice con wildcard
                $regex = str_replace( [ '*', '?' ], [ '.*', '.' ], preg_quote( $pattern, '/' ) );
                if ( preg_match( '/' . $regex . '/i', $permalink ) ) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Verifica se il post ha meta noindex (da plugin SEO).
     */
    private function hasNoIndex( int $post_id ): bool {
        // Yoast SEO
        $yoast = get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true );
        if ( $yoast === '1' ) {
            return true;
        }
        
        // Rank Math
        $rankmath = get_post_meta( $post_id, 'rank_math_robots', true );
        if ( is_array( $rankmath ) && in_array( 'noindex', $rankmath, true ) ) {
            return true;
        }
        if ( is_string( $rankmath ) && str_contains( $rankmath, 'noindex' ) ) {
            return true;
        }
        
        // All in One SEO
        $aioseo = get_post_meta( $post_id, '_aioseo_noindex', true );
        if ( $aioseo === '1' || $aioseo === true ) {
            return true;
        }
        
        // SEOPress
        $seopress = get_post_meta( $post_id, '_seopress_robots_index', true );
        if ( $seopress === 'yes' ) {  // "yes" significa noindex in SEOPress
            return true;
        }
        
        return false;
    }
    
    /**
     * Ottiene le opzioni correnti.
     */
    public function getOptions(): array {
        return $this->options;
    }
}


// ═══════════════════════════════════════════════════════════════════════════════
//  VERSIONING MD - Salvataggio e gestione file statici
// ═══════════════════════════════════════════════════════════════════════════════

class AiFrVersioning {
    
    /**
     * Salva la versione MD di un post.
     * 
     * @return array{saved: bool, path: string, checksum: string, changed: bool}
     */
    public static function saveVersion( int $post_id, string $md_content ): array {
        
        $post = get_post( $post_id );
        if ( ! $post ) {
            return [ 'saved' => false, 'path' => '', 'checksum' => '', 'changed' => false ];
        }
        
        // Crea directory se non esiste
        if ( ! file_exists( AI_FR_VERSIONS_DIR ) ) {
            wp_mkdir_p( AI_FR_VERSIONS_DIR );
        }
        
        // Genera nome file basato su slug
        $filename = self::getFilename( $post );
        $filepath = AI_FR_VERSIONS_DIR . '/' . $filename;
        
        // Calcola checksum nuovo contenuto
        $new_checksum = md5( $md_content );
        
        // Verifica se il contenuto è cambiato
        $old_checksum = get_post_meta( $post_id, '_ai_fr_md_checksum', true );
        $changed = ( $old_checksum !== $new_checksum );
        
        // Salva solo se cambiato (o se non esiste)
        if ( $changed || ! file_exists( $filepath ) ) {
            $saved = file_put_contents( $filepath, $md_content ) !== false;
            
            if ( $saved ) {
                // Aggiorna meta con checksum e timestamp
                update_post_meta( $post_id, '_ai_fr_md_checksum', $new_checksum );
                update_post_meta( $post_id, '_ai_fr_md_generated', current_time( 'mysql' ) );
                update_post_meta( $post_id, '_ai_fr_md_filename', $filename );
            }
            
            return [ 
                'saved' => $saved, 
                'path' => $filepath, 
                'checksum' => $new_checksum,
                'changed' => true 
            ];
        }
        
        return [ 
            'saved' => true, 
            'path' => $filepath, 
            'checksum' => $new_checksum,
            'changed' => false 
        ];
    }
    
    /**
     * Ottiene il contenuto MD salvato per un post.
     */
    public static function getVersion( int $post_id ): ?string {
        $filename = get_post_meta( $post_id, '_ai_fr_md_filename', true );
        if ( empty( $filename ) ) {
            return null;
        }
        
        $filepath = AI_FR_VERSIONS_DIR . '/' . $filename;
        if ( ! file_exists( $filepath ) ) {
            return null;
        }
        
        return file_get_contents( $filepath );
    }
    
    /**
     * Verifica se esiste una versione salvata e valida.
     */
    public static function hasValidVersion( int $post_id ): bool {
        $filename = get_post_meta( $post_id, '_ai_fr_md_filename', true );
        if ( empty( $filename ) ) {
            return false;
        }
        
        return file_exists( AI_FR_VERSIONS_DIR . '/' . $filename );
    }
    
    /**
     * Elimina la versione MD di un post.
     */
    public static function deleteVersion( int $post_id ): bool {
        $filename = get_post_meta( $post_id, '_ai_fr_md_filename', true );
        if ( empty( $filename ) ) {
            return true;
        }
        
        $filepath = AI_FR_VERSIONS_DIR . '/' . $filename;
        if ( file_exists( $filepath ) ) {
            unlink( $filepath );
        }
        
        delete_post_meta( $post_id, '_ai_fr_md_checksum' );
        delete_post_meta( $post_id, '_ai_fr_md_generated' );
        delete_post_meta( $post_id, '_ai_fr_md_filename' );
        
        return true;
    }
    
    /**
     * Genera il nome file per un post.
     */
    private static function getFilename( WP_Post $post ): string {
        // Usa post_type/slug.md per organizzazione
        $slug = $post->post_name ?: 'post-' . $post->ID;
        return sanitize_file_name( $post->post_type . '-' . $slug . '.md' );
    }
    
    /**
     * Ottiene statistiche sulle versioni salvate.
     */
    public static function getStats(): array {
        if ( ! file_exists( AI_FR_VERSIONS_DIR ) ) {
            return [ 'count' => 0, 'size' => 0, 'files' => [] ];
        }
        
        $files = glob( AI_FR_VERSIONS_DIR . '/*.md' );
        $total_size = 0;
        
        foreach ( $files as $file ) {
            $total_size += filesize( $file );
        }
        
        return [
            'count' => count( $files ),
            'size'  => $total_size,
            'files' => array_map( 'basename', $files ),
        ];
    }
    
    /**
     * Pulisce tutte le versioni salvate.
     */
    public static function clearAll(): int {
        if ( ! file_exists( AI_FR_VERSIONS_DIR ) ) {
            return 0;
        }
        
        $files = glob( AI_FR_VERSIONS_DIR . '/*.md' );
        $count = 0;
        
        foreach ( $files as $file ) {
            if ( unlink( $file ) ) {
                $count++;
            }
        }
        
        // Pulisci anche i meta
        global $wpdb;
        $wpdb->delete( $wpdb->postmeta, [ 'meta_key' => '_ai_fr_md_checksum' ] );
        $wpdb->delete( $wpdb->postmeta, [ 'meta_key' => '_ai_fr_md_generated' ] );
        $wpdb->delete( $wpdb->postmeta, [ 'meta_key' => '_ai_fr_md_filename' ] );
        
        return $count;
    }
}


// ═══════════════════════════════════════════════════════════════════════════════
//  SCHEDULER - Rigenerazione automatica
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Configura/aggiorna il cron job.
 */
function ai_fr_schedule_cron(): void {
    $options = wp_parse_args( get_option( 'ai_fr_options', [] ), ai_fr_get_default_options() );
    
    // Rimuovi cron esistente
    $timestamp = wp_next_scheduled( 'ai_fr_cron_regenerate' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'ai_fr_cron_regenerate' );
    }
    
    // Schedula nuovo cron se abilitato
    if ( ! empty( $options['auto_regenerate'] ) && ! empty( $options['static_md_files'] ) ) {
        $interval_hours = max( 1, intval( $options['regenerate_interval'] ) );
        
        // Registra intervallo custom se necessario
        add_filter( 'cron_schedules', function( $schedules ) use ( $interval_hours ) {
            $schedules['ai_fr_interval'] = [
                'interval' => $interval_hours * HOUR_IN_SECONDS,
                'display'  => sprintf( __( 'Ogni %d ore' ), $interval_hours ),
            ];
            return $schedules;
        } );
        
        wp_schedule_event( time(), 'ai_fr_interval', 'ai_fr_cron_regenerate' );
    }
}

// Registra intervalli cron custom
add_filter( 'cron_schedules', function( $schedules ) {
    $options = wp_parse_args( get_option( 'ai_fr_options', [] ), ai_fr_get_default_options() );
    $interval_hours = max( 1, intval( $options['regenerate_interval'] ?? 24 ) );
    
    $schedules['ai_fr_interval'] = [
        'interval' => $interval_hours * HOUR_IN_SECONDS,
        'display'  => sprintf( 'AI Friendly: ogni %d ore', $interval_hours ),
    ];
    
    return $schedules;
} );

// Hook per cron
add_action( 'ai_fr_cron_regenerate', 'ai_fr_regenerate_all' );

/**
 * Rigenera tutte le versioni MD.
 */
function ai_fr_regenerate_all( bool $force = false ): array {
    $options = wp_parse_args( get_option( 'ai_fr_options', [] ), ai_fr_get_default_options() );
    $filter = new AiFrContentFilter();
    
    $stats = [
        'processed' => 0,
        'regenerated' => 0,
        'skipped' => 0,
        'errors' => 0,
    ];
    
    // Ottieni tutti i post dei tipi abilitati
    $post_types = $filter->getEnabledPostTypes();
    if ( empty( $post_types ) ) {
        return $stats;
    }
    
    $posts = get_posts( [
        'post_type'      => $post_types,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'no_found_rows'  => true,
    ] );
    
    foreach ( $posts as $post ) {
        $stats['processed']++;
        
        // Verifica se deve essere incluso
        if ( ! $filter->shouldInclude( $post ) ) {
            $stats['skipped']++;
            continue;
        }
        
        try {
            // Genera MD
            $md_content = ai_fr_generate_markdown( $post );
            
            if ( empty( $md_content ) ) {
                $stats['skipped']++;
                continue;
            }
            
            // Verifica checksum se non forzato
            if ( ! $force && ! empty( $options['regenerate_on_change'] ) ) {
                $current_checksum = md5( $md_content );
                $saved_checksum = get_post_meta( $post->ID, '_ai_fr_md_checksum', true );
                
                if ( $current_checksum === $saved_checksum && AiFrVersioning::hasValidVersion( $post->ID ) ) {
                    $stats['skipped']++;
                    continue;
                }
            }
            
            // Salva versione
            $result = AiFrVersioning::saveVersion( $post->ID, $md_content );
            
            if ( $result['saved'] ) {
                if ( $result['changed'] ) {
                    $stats['regenerated']++;
                } else {
                    $stats['skipped']++;
                }
            } else {
                $stats['errors']++;
            }
            
        } catch ( Throwable $e ) {
            $stats['errors']++;
            error_log( 'AI Friendly regeneration error for post ' . $post->ID . ': ' . $e->getMessage() );
        }
    }
    
    // Salva timestamp ultima rigenerazione
    update_option( 'ai_fr_last_regeneration', [
        'time'  => current_time( 'mysql' ),
        'stats' => $stats,
    ] );
    
    return $stats;
}

/**
 * Genera il contenuto Markdown per un singolo post.
 */
function ai_fr_generate_markdown( WP_Post $post ): string {
    
    $post_id = $post->ID;
    
    // Ottieni contenuto HTML
    $html = ai_fr_get_rendered_content_safe( $post, false );
    
    if ( empty( trim( strip_tags( $html ) ) ) ) {
        return '';
    }
    
    // Converter
    $converter = new AiFrConverter( AI_FR_NORMALIZE_HEADINGS );
    
    // Costruisci output
    $md = AiFrMetadata::frontmatter( $post );
    $md .= "# " . get_the_title( $post_id ) . "\n\n";
    
    // Featured image
    if ( AI_FR_INCLUDE_METADATA && has_post_thumbnail( $post_id ) ) {
        $featured_url = get_the_post_thumbnail_url( $post_id, 'large' );
        $featured_alt = get_post_meta( get_post_thumbnail_id( $post_id ), '_wp_attachment_image_alt', true );
        if ( $featured_url && ! str_contains( $html, $featured_url ) ) {
            $alt = ! empty( $featured_alt ) ? $featured_alt : get_the_title( $post_id );
            $md .= "![{$alt}]({$featured_url})\n\n";
        }
    }
    
    $md .= $converter->convert( $html ) . "\n";
    
    return $md;
}

// Hook per rigenerazione su salvataggio post
add_action( 'save_post', function( int $post_id, WP_Post $post, bool $update ): void {
    
    // Skip autosave e revisioni
    if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
        return;
    }
    
    // Skip se non pubblicato
    if ( $post->post_status !== 'publish' ) {
        // Se era pubblicato e ora non più, elimina versione
        AiFrVersioning::deleteVersion( $post_id );
        return;
    }
    
    $options = wp_parse_args( get_option( 'ai_fr_options', [] ), ai_fr_get_default_options() );
    
    // Verifica se rigenerazione su save è abilitata
    if ( empty( $options['static_md_files'] ) || empty( $options['regenerate_on_save'] ) ) {
        return;
    }
    
    // Verifica se il post deve essere incluso
    $filter = new AiFrContentFilter();
    if ( ! $filter->shouldInclude( $post ) ) {
        // Elimina eventuale versione esistente
        AiFrVersioning::deleteVersion( $post_id );
        return;
    }
    
    // Genera e salva
    try {
        $md_content = ai_fr_generate_markdown( $post );
        if ( ! empty( $md_content ) ) {
            AiFrVersioning::saveVersion( $post_id, $md_content );
        }
    } catch ( Throwable $e ) {
        error_log( 'AI Friendly save_post error for ' . $post_id . ': ' . $e->getMessage() );
    }
    
}, 20, 3 );

// Hook per eliminazione post
add_action( 'before_delete_post', function( int $post_id ): void {
    AiFrVersioning::deleteVersion( $post_id );
} );


// ═══════════════════════════════════════════════════════════════════════════════
//  CONVERTER v2  |  HTML → Markdown
// ═══════════════════════════════════════════════════════════════════════════════

class AiFrConverter {

    private const NOISE_PATTERNS = [
        'previous step', 'next step', 'avanti', 'indietro',
        'step 1', 'step 2', 'step 3', 'step 4', 'step 5',
        'mostra di più', 'mostra meno', 'leggi di più', 'read more',
        'show more', 'show less', 'load more', 'carica altro',
        'scopri di più', 'learn more', 'click here', 'clicca qui',
        'invia', 'submit', 'send', 'reset',
        'menu', 'skip to content', 'vai al contenuto',
    ];

    private const REMOVE_SHORTCODES = [
        'contact-form-7', 'cf7', 'wpforms', 'gravityform', 'formidable',
        'ninja_form', 'caldera_form', 'mailchimp', 'newsletter',
        'vc_row', 'vc_column', 'vc_column_text', 'vc_section',
        'et_pb_section', 'et_pb_row', 'et_pb_column', 'et_pb_text',
        'fusion_builder_container', 'fusion_builder_row', 'fusion_builder_column',
        'fl_builder_insert_layout', 'elementor-template',
        'gallery', 'playlist', 'audio', 'video', 'caption', 'embed',
        'rev_slider', 'layerslider',
        'social_buttons', 'share', 'follow',
        'ads', 'ad', 'advertisement',
    ];

    private bool $normalizeHeadings;
    private int $headingShift = 0;

    public function __construct( bool $normalizeHeadings = true ) {
        $this->normalizeHeadings = $normalizeHeadings;
    }

    public function convert( string $html ): string {

        if ( trim( $html ) === '' ) {
            return '';
        }

        $s = $this->stripNoise( $html );
        $s = $this->stripUIElements( $s );
        $s = $this->processShortcodes( $s );

        if ( $this->normalizeHeadings ) {
            $this->headingShift = $this->calculateHeadingShift( $s );
        }

        $s = $this->preCodeBlocks( $s );
        $s = $this->headings( $s );
        $s = $this->blockquotes( $s );
        $s = $this->horizontalRules( $s );
        $s = $this->tables( $s );
        $s = $this->lists( $s );
        $s = $this->definitionLists( $s );
        $s = $this->images( $s );
        $s = $this->figcaptions( $s );
        $s = $this->links( $s );
        $s = $this->inlineCode( $s );
        $s = $this->bold( $s );
        $s = $this->italic( $s );
        $s = $this->marks( $s );
        $s = $this->breaks( $s );
        $s = $this->paragraphs( $s );
        $s = strip_tags( $s );
        $s = $this->removeNoiseText( $s );
        $s = $this->cleanup( $s );

        return $s;
    }

    private function stripNoise( string $s ): string {
        $s = preg_replace(
            '/<(script|style|noscript|svg|template|iframe)[^>]*>.*?<\/\1>/si',
            '',
            $s
        ) ?? $s;
        $s = preg_replace( '/<!--.*?-->/s', '', $s ) ?? $s;
        return $s;
    }

    private function stripUIElements( string $s ): string {
        $s = preg_replace( '/<form[^>]*>.*?<\/form>/si', '', $s ) ?? $s;
        $s = preg_replace( '/<(input|select|textarea|button|label)[^>]*>.*?<\/\1>/si', '', $s ) ?? $s;
        $s = preg_replace( '/<(input|select|textarea|button|label)[^>]*\/?>/i', '', $s ) ?? $s;
        $s = preg_replace( '/<nav[^>]*>.*?<\/nav>/si', '', $s ) ?? $s;
        $s = preg_replace( '/<aside[^>]*>.*?<\/aside>/si', '', $s ) ?? $s;
        $s = preg_replace( '/<footer[^>]*>.*?<\/footer>/si', '', $s ) ?? $s;
        $s = preg_replace( '/<(progress|meter)[^>]*>.*?<\/\1>/si', '', $s ) ?? $s;
        $s = preg_replace( '/<[^>]+(?:hidden|display:\s*none|visibility:\s*hidden)[^>]*>.*?<\/[a-z]+>/si', '', $s ) ?? $s;
        $s = preg_replace( '/<[^>]+aria-hidden=["\']true["\'][^>]*>.*?<\/[a-z]+>/si', '', $s ) ?? $s;
        return $s;
    }

    private function processShortcodes( string $s ): string {
        foreach ( self::REMOVE_SHORTCODES as $tag ) {
            $s = preg_replace(
                '/\[' . preg_quote( $tag, '/' ) . '[^\]]*\](.*?)\[\/' . preg_quote( $tag, '/' ) . '\]/si',
                '$1',
                $s
            ) ?? $s;
            $s = preg_replace(
                '/\[' . preg_quote( $tag, '/' ) . '[^\]]*\/?\]/i',
                '',
                $s
            ) ?? $s;
        }

        if ( function_exists( 'do_shortcode' ) ) {
            $s = preg_replace_callback(
                '/\[([a-z_-]+)[^\]]*\](?:.*?\[\/\1\])?/si',
                function( $match ) {
                    $tag = strtolower( $match[1] );
                    $skip = [ 'contact', 'form', 'subscribe', 'signup', 'login', 'register', 'cart', 'checkout' ];
                    foreach ( $skip as $word ) {
                        if ( str_contains( $tag, $word ) ) {
                            return '';
                        }
                    }
                    $result = do_shortcode( $match[0] );
                    if ( $result === $match[0] ) {
                        return '';
                    }
                    return $result;
                },
                $s
            ) ?? $s;
        }

        $s = preg_replace( '/\[[^\]]+\]/', '', $s ) ?? $s;
        return $s;
    }

    private function calculateHeadingShift( string $s ): int {
        if ( preg_match( '/<h1[^>]*>/i', $s ) ) {
            return 1;
        }
        return 0;
    }

    private function preCodeBlocks( string $s ): string {
        return preg_replace_callback(
            '/<pre[^>]*>(?:<code[^>]*(?:class=["\'][^"\']*language-([a-z]+)[^"\']*["\'])?[^>]*>)?(.*?)(?:<\/code>)?<\/pre>/si',
            function( $m ) {
                $lang = $m[1] ?? '';
                $code = html_entity_decode( strip_tags( $m[2] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
                return "\n```{$lang}\n" . trim( $code ) . "\n```\n";
            },
            $s
        ) ?? $s;
    }

    private function headings( string $s ): string {
        return preg_replace_callback(
            '/<h([1-6])[^>]*>(.*?)<\/h\1>/si',
            function( $m ) {
                $level = (int) $m[1] + $this->headingShift;
                $level = min( $level, 6 );
                $text = trim( strip_tags( $m[2] ) );
                if ( $text === '' ) {
                    return '';
                }
                return "\n\n" . str_repeat( '#', $level ) . ' ' . $text . "\n\n";
            },
            $s
        ) ?? $s;
    }

    private function blockquotes( string $s ): string {
        return preg_replace_callback(
            '/<blockquote[^>]*>(.*?)<\/blockquote>/si',
            function ( $m ) {
                $content = trim( strip_tags( $m[1] ) );
                if ( $content === '' ) return '';
                $lines = explode( "\n", $content );
                $quoted = array_map( fn( $l ) => '> ' . trim( $l ), $lines );
                return "\n\n" . implode( "\n", array_filter( $quoted, fn( $l ) => $l !== '> ' ) ) . "\n\n";
            },
            $s
        ) ?? $s;
    }

    private function horizontalRules( string $s ): string {
        return preg_replace( '/<hr\s*\/?>/i', "\n\n---\n\n", $s ) ?? $s;
    }

    private function tables( string $s ): string {
        return preg_replace_callback(
            '/<table[^>]*>(.*?)<\/table>/si',
            function( $m ) {
                $tableHtml = $m[1];
                $rows = [];
                preg_match_all( '/<tr[^>]*>(.*?)<\/tr>/si', $tableHtml, $trMatches );
                foreach ( $trMatches[1] as $i => $tr ) {
                    preg_match_all( '/<(th|td)[^>]*>(.*?)<\/\1>/si', $tr, $cellMatches );
                    $cells = array_map( fn( $c ) => trim( strip_tags( $c ) ), $cellMatches[2] );
                    if ( ! empty( $cells ) ) {
                        $rows[] = '| ' . implode( ' | ', $cells ) . ' |';
                        if ( $i === 0 ) {
                            $rows[] = '| ' . implode( ' | ', array_fill( 0, count( $cells ), '---' ) ) . ' |';
                        }
                    }
                }
                return empty( $rows ) ? '' : "\n\n" . implode( "\n", $rows ) . "\n\n";
            },
            $s
        ) ?? $s;
    }

    private function lists( string $s ): string {
        $s = preg_replace_callback(
            '/<li[^>]*>(.*?)<\/li>/si',
            function( $m ) {
                $content = trim( strip_tags( $m[1] ) );
                return $content !== '' ? "\n- " . $content : '';
            },
            $s
        ) ?? $s;
        $s = preg_replace( '/<\/?(?:ul|ol)[^>]*>/', '', $s ) ?? $s;
        return $s;
    }

    private function definitionLists( string $s ): string {
        $s = preg_replace( '/<dt[^>]*>(.*?)<\/dt>/si', "\n**$1**\n", $s ) ?? $s;
        $s = preg_replace( '/<dd[^>]*>(.*?)<\/dd>/si', ": $1\n", $s ) ?? $s;
        $s = preg_replace( '/<\/?dl[^>]*>/', '', $s ) ?? $s;
        return $s;
    }

    private function images( string $s ): string {
        return preg_replace_callback(
            '/<img\s[^>]*>/i',
            function ( $m ) {
                $src = $this->attr( $m[0], 'src' ) ?? '';
                $alt = $this->attr( $m[0], 'alt' ) ?? '';

                if ( $src === '' ) {
                    return '';
                }

                $alt = trim( $alt );
                if ( $alt === '' ) {
                    return '';
                }

                if ( str_contains( $src, 'pixel' ) || str_contains( $src, 'spacer' ) || str_contains( $src, 'blank.gif' ) ) {
                    return '';
                }

                if ( preg_match( '/icon|logo-small|favicon|loading|spinner/i', $src ) ) {
                    return '';
                }

                $altLower = strtolower( $alt );
                $genericAlts = [ 'image', 'img', 'photo', 'picture', 'foto', 'immagine', 'banner', 'header', 'hero' ];
                if ( in_array( $altLower, $genericAlts, true ) ) {
                    return '';
                }

                return "![{$alt}]({$src})";
            },
            $s
        ) ?? $s;
    }

    private function figcaptions( string $s ): string {
        return preg_replace_callback(
            '/<figcaption[^>]*>(.*?)<\/figcaption>/si',
            function( $m ) {
                $caption = trim( strip_tags( $m[1] ) );
                return $caption !== '' ? "\n*{$caption}*\n" : '';
            },
            $s
        ) ?? $s;
    }

    private function links( string $s ): string {
        return preg_replace_callback(
            '/<a\s[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/si',
            function ( $m ) {
                $url  = trim( $m[1] );
                $text = trim( strip_tags( $m[2] ) );

                if ( $text === '' && $url === '' ) {
                    return '';
                }

                if ( str_starts_with( $url, '#' ) ) {
                    return $text;
                }

                if ( str_starts_with( $url, 'javascript:' ) ) {
                    return $text;
                }

                $textLower = strtolower( $text );
                foreach ( self::NOISE_PATTERNS as $pattern ) {
                    if ( str_contains( $textLower, $pattern ) ) {
                        return '';
                    }
                }

                if ( $text !== '' ) {
                    return "[{$text}]({$url})";
                }

                return $url !== '' ? $url : '';
            },
            $s
        ) ?? $s;
    }

    private function inlineCode( string $s ): string {
        return preg_replace( '/<code[^>]*>(.*?)<\/code>/si', '`$1`', $s ) ?? $s;
    }

    private function bold( string $s ): string {
        $s = preg_replace( '/<strong[^>]*>(.*?)<\/strong>/si', '**$1**', $s ) ?? $s;
        $s = preg_replace( '/<b[^>]*>(.*?)<\/b>/si', '**$1**', $s ) ?? $s;
        return $s;
    }

    private function italic( string $s ): string {
        $s = preg_replace( '/<em[^>]*>(.*?)<\/em>/si', '*$1*', $s ) ?? $s;
        $s = preg_replace( '/<i[^>]*>(.*?)<\/i>/si', '*$1*', $s ) ?? $s;
        return $s;
    }

    private function marks( string $s ): string {
        return preg_replace( '/<mark[^>]*>(.*?)<\/mark>/si', '==$1==', $s ) ?? $s;
    }

    private function breaks( string $s ): string {
        return preg_replace( '/<br\s*\/?>/i', "\n", $s ) ?? $s;
    }

    private function paragraphs( string $s ): string {
        return preg_replace( '/<\/p>/i', "\n\n", $s ) ?? $s;
    }

    private function removeNoiseText( string $s ): string {
        $lines = explode( "\n", $s );
        $filtered = [];

        foreach ( $lines as $line ) {
            $trimmed = trim( $line );
            $lower = strtolower( $trimmed );

            if ( $trimmed === '' ) {
                $filtered[] = '';
                continue;
            }

            $isNoise = false;
            foreach ( self::NOISE_PATTERNS as $pattern ) {
                if ( $lower === $pattern || $lower === strtolower( $pattern ) ) {
                    $isNoise = true;
                    break;
                }
            }

            if ( ! $isNoise && strlen( $trimmed ) < 20 && str_word_count( $trimmed ) <= 2 ) {
                if ( ! preg_match( '/^(#|\-|\*|\d|[A-Z][a-z]+ \d)/', $trimmed ) ) {
                    foreach ( self::NOISE_PATTERNS as $pattern ) {
                        if ( str_contains( $lower, $pattern ) ) {
                            $isNoise = true;
                            break;
                        }
                    }
                }
            }

            if ( ! $isNoise ) {
                $filtered[] = $line;
            }
        }

        return implode( "\n", $filtered );
    }

    private function cleanup( string $s ): string {
        $s = html_entity_decode( $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $s = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $s ) ?? $s;
        $s = preg_replace( '/[^\S\n]+/', ' ', $s ) ?? $s;
        $s = preg_replace( '/^ +| +$/m', '', $s ) ?? $s;
        $s = preg_replace( '/\n{3,}/', "\n\n", $s ) ?? $s;
        $s = preg_replace( '/^\*\*\*\*$/m', '', $s ) ?? $s;
        $s = preg_replace( '/^\*\*$/m', '', $s ) ?? $s;
        $s = preg_replace( '/^\*$/m', '', $s ) ?? $s;
        return trim( $s );
    }

    private function attr( string $tag, string $name ): ?string {
        return preg_match(
            "/\\b{$name}=[\"']([^\"']*)[\"']/i",
            $tag,
            $m
        ) ? $m[1] : null;
    }
}


// ═══════════════════════════════════════════════════════════════════════════════
//  METADATA EXTRACTOR
// ═══════════════════════════════════════════════════════════════════════════════

class AiFrMetadata {

    public static function frontmatter( WP_Post $post ): string {

        if ( ! AI_FR_INCLUDE_METADATA ) {
            return '';
        }

        $meta = self::extract( $post );

        $yaml  = "---\n";
        $yaml .= "title: " . self::yamlEscape( $meta['title'] ) . "\n";

        if ( ! empty( $meta['description'] ) ) {
            $yaml .= "description: " . self::yamlEscape( $meta['description'] ) . "\n";
        }

        if ( ! empty( $meta['featured_image'] ) ) {
            $yaml .= "featured_image: " . $meta['featured_image'] . "\n";
        }

        $yaml .= "date: " . $meta['date'] . "\n";

        if ( ! empty( $meta['modified'] ) && $meta['modified'] !== $meta['date'] ) {
            $yaml .= "modified: " . $meta['modified'] . "\n";
        }

        if ( ! empty( $meta['author'] ) ) {
            $yaml .= "author: " . self::yamlEscape( $meta['author'] ) . "\n";
        }

        $yaml .= "url: " . $meta['url'] . "\n";

        if ( ! empty( $meta['categories'] ) ) {
            $yaml .= "categories: [" . implode( ', ', array_map( [ self::class, 'yamlEscape' ], $meta['categories'] ) ) . "]\n";
        }

        if ( ! empty( $meta['tags'] ) ) {
            $yaml .= "tags: [" . implode( ', ', array_map( [ self::class, 'yamlEscape' ], $meta['tags'] ) ) . "]\n";
        }

        $yaml .= "---\n\n";

        return $yaml;
    }

    public static function extract( WP_Post $post ): array {

        $post_id = $post->ID;

        $title = self::getSeoTitle( $post_id ) ?: get_the_title( $post_id );
        $description = self::getSeoDescription( $post_id ) ?: self::generateExcerpt( $post );

        $featured_image = '';
        if ( has_post_thumbnail( $post_id ) ) {
            $featured_image = get_the_post_thumbnail_url( $post_id, 'large' ) ?: '';
        }

        $date = get_the_date( 'Y-m-d', $post_id );
        $modified = get_the_modified_date( 'Y-m-d', $post_id );
        $author = get_the_author_meta( 'display_name', $post->post_author );
        $url = get_permalink( $post_id );

        $categories = [];
        if ( $post->post_type === 'post' ) {
            $cats = get_the_category( $post_id );
            foreach ( $cats as $cat ) {
                if ( $cat->slug !== 'uncategorized' ) {
                    $categories[] = $cat->name;
                }
            }
        }

        $tags = [];
        $post_tags = get_the_tags( $post_id );
        if ( $post_tags ) {
            foreach ( $post_tags as $tag ) {
                $tags[] = $tag->name;
            }
        }

        return [
            'title'          => $title,
            'description'    => $description,
            'featured_image' => $featured_image,
            'date'           => $date,
            'modified'       => $modified,
            'author'         => $author,
            'url'            => $url,
            'categories'     => $categories,
            'tags'           => $tags,
        ];
    }

    private static function getSeoTitle( int $post_id ): string {
        $title = get_post_meta( $post_id, '_yoast_wpseo_title', true );
        if ( ! empty( $title ) ) return $title;

        $title = get_post_meta( $post_id, 'rank_math_title', true );
        if ( ! empty( $title ) ) return $title;

        $title = get_post_meta( $post_id, '_aioseo_title', true );
        if ( ! empty( $title ) ) return $title;

        $title = get_post_meta( $post_id, '_seopress_titles_title', true );
        if ( ! empty( $title ) ) return $title;

        return '';
    }

    private static function getSeoDescription( int $post_id ): string {
        $desc = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
        if ( ! empty( $desc ) ) return $desc;

        $desc = get_post_meta( $post_id, 'rank_math_description', true );
        if ( ! empty( $desc ) ) return $desc;

        $desc = get_post_meta( $post_id, '_aioseo_description', true );
        if ( ! empty( $desc ) ) return $desc;

        $desc = get_post_meta( $post_id, '_seopress_titles_desc', true );
        if ( ! empty( $desc ) ) return $desc;

        return '';
    }

    private static function generateExcerpt( WP_Post $post ): string {
        $raw = $post->post_excerpt !== '' ? $post->post_excerpt : $post->post_content;
        $text = preg_replace( '/\[[^\]]+\]/', '', $raw ) ?? $raw;
        $text = wp_strip_all_tags( $text );
        $text = preg_replace( '/\s+/', ' ', $text ) ?? $text;
        $text = trim( $text );

        if ( strlen( $text ) > 300 ) {
            $text = substr( $text, 0, 297 ) . '...';
        }

        return $text;
    }

    private static function yamlEscape( string $s ): string {
        if ( preg_match( '/[:\[\]{}#&*!|>\'"%@`]/', $s ) || str_contains( $s, "\n" ) ) {
            return '"' . str_replace( [ '"', "\n" ], [ '\\"', ' ' ], $s ) . '"';
        }
        return $s;
    }
}


// ═══════════════════════════════════════════════════════════════════════════════
//  1 — INTERCEPT
// ═══════════════════════════════════════════════════════════════════════════════

add_action( 'template_redirect', function () {

    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    if ( empty( $request_uri ) ) {
        return;
    }

    $parsed = parse_url( $request_uri, PHP_URL_PATH );
    if ( $parsed === false || $parsed === null ) {
        return;
    }

    $full = rawurldecode( $parsed );

    $wp_base = rtrim( parse_url( home_url(), PHP_URL_PATH ) ?? '', '/' );
    $rel     = ( $wp_base !== '' && str_starts_with( $full, $wp_base ) )
             ? substr( $full, strlen( $wp_base ) )
             : $full;
    $rel = $rel ?: '/';

    if ( $rel === '/llms.txt' ) {
        ai_fr_serve_llms_txt();
        exit;
    }

    if ( preg_match( '#^(.+?)(?:/index\.html\.md|\.md)$#i', $rel, $m ) ) {
        ai_fr_serve_markdown( $m[1] );
        exit;
    }

}, 1 );


// ═══════════════════════════════════════════════════════════════════════════════
//  2 — llms.txt
// ═══════════════════════════════════════════════════════════════════════════════

function ai_fr_serve_llms_txt(): void {

    $body = ai_fr_build_llms_txt();

    status_header( 200 );
    header( 'Content-Type: text/plain; charset=UTF-8' );
    header( 'Cache-Control: public, max-age=3600' );
    echo $body;
    exit;
}

function ai_fr_build_llms_txt(): string {

    $options = wp_parse_args( get_option( 'ai_fr_options', [] ), ai_fr_get_default_options() );
    $filter = new AiFrContentFilter();

    $custom_content = trim( $options['llms_content'] ?? '' );
    $include_auto   = ! empty( $options['llms_include_auto'] );

    if ( $custom_content !== '' && ! $include_auto ) {
        return apply_filters( 'ai_fr_llms_txt_content', $custom_content );
    }

    $out = '';

    if ( $custom_content !== '' ) {
        $out = $custom_content . "\n\n";
    } else {
        $name = get_bloginfo( 'blogname' );
        $desc = get_bloginfo( 'description' ) ?: 'Sito web';
        $out  = "# {$name}\n";
        $out .= "> {$desc}\n\n";
    }

    if ( $include_auto || $custom_content === '' ) {
        
        // Pagine
        if ( ! empty( $options['include_pages'] ) ) {
            $out .= ai_fr_section( 'Pagine', [
                'post_type'      => 'page',
                'post_status'    => 'publish',
                'posts_per_page' => AI_FR_PAGES_LIMIT,
                'orderby'        => 'menu_order date',
                'order'          => 'ASC',
            ], $filter );
        }

        // Post
        if ( ! empty( $options['include_posts'] ) ) {
            $out .= ai_fr_section( 'Post', [
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'posts_per_page' => AI_FR_POSTS_LIMIT,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ], $filter );
        }

        // Prodotti WooCommerce
        if ( ! empty( $options['include_products'] ) && class_exists( 'WooCommerce' ) ) {
            $out .= ai_fr_section( 'Prodotti', [
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => 20,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ], $filter );
        }
        
        // CPT custom
        $enabled_cpt = $options['include_cpt'] ?? [];
        foreach ( (array) $enabled_cpt as $cpt ) {
            $cpt_obj = get_post_type_object( $cpt );
            $label = $cpt_obj ? $cpt_obj->labels->name : ucfirst( $cpt );
            
            $out .= ai_fr_section( $label, [
                'post_type'      => $cpt,
                'post_status'    => 'publish',
                'posts_per_page' => 20,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ], $filter );
        }
    }

    return apply_filters( 'ai_fr_llms_txt_content', $out );
}

function ai_fr_section( string $heading, array $query_args, AiFrContentFilter $filter ): string {

    $posts = get_posts( $query_args );
    
    // Filtra con le regole di inclusione/esclusione
    $items = array_filter( $posts, fn( WP_Post $p ) => $filter->shouldInclude( $p ) );

    if ( empty( $items ) ) {
        return '';
    }

    $lines = "## {$heading}\n";

    foreach ( $items as $item ) {
        $title   = get_the_title( $item->ID );
        $md_url  = ai_fr_permalink_to_md( get_permalink( $item->ID ) );
        $excerpt = ai_fr_excerpt( $item );

        $lines .= "- [{$title}]({$md_url})";
        $lines .= $excerpt !== '' ? ": {$excerpt}" : '';
        $lines .= "\n";
    }

    return $lines . "\n";
}


// ═══════════════════════════════════════════════════════════════════════════════
//  3 — *.md
// ═══════════════════════════════════════════════════════════════════════════════

function ai_fr_serve_markdown( string $rel_path ): void {

    $display_errors = ini_get( 'display_errors' );
    ini_set( 'display_errors', '0' );

    $debug_mode = isset( $_GET['debug'] ) && current_user_can( 'manage_options' );
    $options = wp_parse_args( get_option( 'ai_fr_options', [] ), ai_fr_get_default_options() );

    try {
        $post_id = ai_fr_resolve_post( $rel_path );
        if ( ! $post_id ) {
            ai_fr_404();
        }

        $post = get_post( $post_id );
        if ( ! $post || $post->post_status !== 'publish' ) {
            ai_fr_404();
        }
        
        // Verifica filtri inclusione/esclusione
        $filter = new AiFrContentFilter();
        if ( ! $filter->shouldInclude( $post ) ) {
            ai_fr_404();
        }

        // Prova a servire versione statica se abilitato
        if ( ! empty( $options['static_md_files'] ) && ! $debug_mode ) {
            $static_content = AiFrVersioning::getVersion( $post_id );
            if ( $static_content !== null ) {
                ini_set( 'display_errors', $display_errors );
                
                status_header( 200 );
                header( 'Content-Type: text/markdown; charset=UTF-8' );
                header( 'X-Robots-Tag: noindex, nofollow' );
                header( 'Cache-Control: public, max-age=3600' );
                header( 'X-AI-Friendly-Source: static' );
                
                echo $static_content;
                exit;
            }
        }

        // Genera dinamicamente
        $md = ai_fr_generate_markdown( $post );
        
        if ( $debug_mode ) {
            $debug_output = "---\n## DEBUG INFO\n\n";
            $debug_output .= "**Post ID:** {$post_id}\n\n";
            $debug_output .= "**Post Type:** {$post->post_type}\n\n";
            $debug_output .= "**Static version:** " . ( AiFrVersioning::hasValidVersion( $post_id ) ? 'Yes' : 'No' ) . "\n\n";
            $debug_output .= "**Checksum:** " . md5( $md ) . "\n\n";
            $debug_output .= "---\n\n";
            
            // Inserisci dopo frontmatter
            $md = preg_replace( '/^(---\n.*?\n---\n\n)/s', "$1" . $debug_output, $md ) ?? $debug_output . $md;
        }

        ini_set( 'display_errors', $display_errors );

        status_header( 200 );
        header( 'Content-Type: text/markdown; charset=UTF-8' );
        header( 'X-Robots-Tag: noindex, nofollow' );
        header( 'Cache-Control: public, max-age=3600' );
        header( 'X-AI-Friendly-Source: dynamic' );

        echo $md;
        exit;

    } catch ( Throwable $e ) {
        ini_set( 'display_errors', $display_errors );

        error_log( 'AI Friendly MD Error: ' . $e->getMessage() );

        status_header( 500 );
        header( 'Content-Type: text/plain; charset=UTF-8' );
        echo $debug_mode 
            ? "Errore: " . $e->getMessage() 
            : "Errore nella generazione del contenuto Markdown.";
        exit;
    }
}

function ai_fr_get_rendered_content_safe( WP_Post $post, bool $debug = false ): string {

    $content = $post->post_content;
    $post_id = $post->ID;

    $builder_content = ai_fr_try_page_builders( $post_id, $content, $debug );
    if ( ! empty( trim( strip_tags( $builder_content ) ) ) ) {
        return $builder_content;
    }

    $html = $content;

    if ( function_exists( 'has_blocks' ) && has_blocks( $html ) ) {
        if ( function_exists( 'do_blocks' ) ) {
            $html = do_blocks( $html );
        }
    }

    if ( function_exists( 'do_shortcode' ) ) {
        $html = do_shortcode( $html );
    }

    if ( function_exists( 'wpautop' ) ) {
        $html = wpautop( $html );
    }

    $text_check = trim( strip_tags( $html ) );
    if ( strlen( $text_check ) > 50 ) {
        return $html;
    }

    try {
        global $post;
        $old_post = $post;
        $post = get_post( $post_id );
        setup_postdata( $post );

        ob_start();
        $filtered = apply_filters( 'the_content', $content );
        ob_end_clean();

        wp_reset_postdata();
        $post = $old_post;

        if ( strlen( trim( strip_tags( $filtered ) ) ) > 50 ) {
            return $filtered;
        }
    } catch ( Throwable $e ) {
        // Ignora
    }

    $fallback = ai_fr_extract_text_from_raw( $content );
    if ( ! empty( $fallback ) ) {
        return wpautop( $fallback );
    }

    return '';
}

function ai_fr_try_page_builders( int $post_id, string $content, bool $debug = false ): string {

    $breakdance_data = get_post_meta( $post_id, '_breakdance_data', true );
    if ( ! empty( $breakdance_data ) ) {
        $extracted = ai_fr_extract_breakdance_text( $breakdance_data );
        if ( ! empty( $extracted ) ) {
            return wpautop( $extracted );
        }
    }

    $yootheme_data = get_post_meta( $post_id, '_yootheme', true );
    if ( ! empty( $yootheme_data ) ) {
        if ( is_string( $yootheme_data ) ) {
            $data = json_decode( $yootheme_data, true );
            if ( is_array( $data ) ) {
                $extracted = ai_fr_extract_yootheme_text( $data );
                if ( ! empty( trim( $extracted ) ) ) {
                    return wpautop( $extracted );
                }
            }
        }
    }

    $elementor_data = get_post_meta( $post_id, '_elementor_data', true );
    if ( ! empty( $elementor_data ) ) {
        if ( is_string( $elementor_data ) ) {
            $data = json_decode( $elementor_data, true );
            if ( is_array( $data ) ) {
                $extracted = ai_fr_extract_elementor_text( $data );
                if ( ! empty( trim( $extracted ) ) ) {
                    return wpautop( $extracted );
                }
            }
        }
    }

    $oxygen_data = get_post_meta( $post_id, 'ct_builder_shortcodes', true );
    if ( ! empty( $oxygen_data ) ) {
        $extracted = ai_fr_extract_text_from_raw( $oxygen_data );
        if ( ! empty( $extracted ) ) {
            return wpautop( $extracted );
        }
    }

    $bricks_data = get_post_meta( $post_id, '_bricks_page_content_2', true );
    if ( empty( $bricks_data ) ) {
        $bricks_data = get_post_meta( $post_id, '_bricks_page_content', true );
    }
    if ( ! empty( $bricks_data ) && is_array( $bricks_data ) ) {
        $extracted = ai_fr_extract_bricks_text( $bricks_data );
        if ( ! empty( $extracted ) ) {
            return wpautop( $extracted );
        }
    }

    return '';
}

function ai_fr_extract_breakdance_text( $data ): string {
    if ( is_string( $data ) ) {
        $data = json_decode( $data, true );
        if ( ! is_array( $data ) ) {
            $data = maybe_unserialize( $data );
        }
    }
    if ( ! is_array( $data ) ) {
        return '';
    }
    return ai_fr_recursive_text_extract( $data, [ 'text', 'content', 'title', 'heading', 'paragraph', 'html', 'value' ] );
}

function ai_fr_extract_elementor_text( array $data ): string {
    return ai_fr_recursive_text_extract( $data, [ 'title', 'description', 'content', 'text', 'editor', 'html', 'heading_title' ] );
}

function ai_fr_extract_bricks_text( array $data ): string {
    return ai_fr_recursive_text_extract( $data, [ 'text', 'content', 'title', 'heading', 'paragraph', 'html' ] );
}

function ai_fr_extract_yootheme_text( array $data, string $result = '' ): string {
    return ai_fr_recursive_text_extract( $data, [ 'content', 'text', 'title', 'lead', 'meta', 'heading', 'paragraph' ], $result );
}

function ai_fr_recursive_text_extract( array $data, array $keys, string $result = '' ): string {
    foreach ( $data as $key => $value ) {
        if ( is_string( $value ) && in_array( $key, $keys, true ) ) {
            $clean = trim( strip_tags( html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
            if ( ! empty( $clean ) && strlen( $clean ) > 2 ) {
                $result .= $clean . "\n\n";
            }
        } elseif ( is_array( $value ) ) {
            $result = ai_fr_recursive_text_extract( $value, $keys, $result );
        }
    }
    return $result;
}

function ai_fr_extract_text_from_raw( string $content ): string {
    $text = preg_replace( '/\[[^\]]+\]/', '', $content ) ?? $content;
    $text = preg_replace( '/\{[^}]+\}/', '', $text ) ?? $text;
    $text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    $text = wp_strip_all_tags( $text );
    $text = preg_replace( '/\s+/', ' ', $text ) ?? $text;
    return trim( $text );
}

function ai_fr_resolve_post( string $path ): int {
    $path = trim( $path, '/' );
    if ( empty( $path ) ) {
        return 0;
    }

    $page = get_page_by_path( $path, OBJECT, [ 'page', 'post', 'product' ] );
    if ( $page && $page->post_status === 'publish' ) {
        return $page->ID;
    }

    $slug = basename( $path );
    if ( ! empty( $slug ) ) {
        $posts = get_posts( [
            'name'           => $slug,
            'post_type'      => 'any',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'no_found_rows'  => true,
        ] );
        if ( ! empty( $posts ) ) {
            return $posts[0]->ID;
        }
    }

    global $wpdb;
    $slug_sanitized = sanitize_title( $slug );

    $post_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_status = 'publish' LIMIT 1",
            $slug_sanitized
        )
    );

    if ( $post_id ) {
        return (int) $post_id;
    }

    return 0;
}

function ai_fr_404(): never {
    status_header( 404 );
    header( 'Content-Type: text/plain; charset=UTF-8' );
    echo "Contenuto non trovato.";
    exit;
}


// ═══════════════════════════════════════════════════════════════════════════════
//  4 — <head>
// ═══════════════════════════════════════════════════════════════════════════════

add_action( 'wp_head', function () {

    echo '<link rel="llms-txt" type="text/plain" href="'
       . esc_url( home_url( '/llms.txt' ) )
       . '" />' . "\n";

    if ( ( is_single() || is_page() ) && get_the_ID() ) {
        $filter = new AiFrContentFilter();
        $post = get_post( get_the_ID() );
        
        if ( $post && $filter->shouldInclude( $post ) ) {
            $md_url = ai_fr_permalink_to_md( get_permalink( get_the_ID() ) );

            echo '<link rel="alternate" type="text/markdown" title="Versione Markdown" href="'
               . esc_url( $md_url )
               . '" />' . "\n";
        }
    }
}, 2 );


// ═══════════════════════════════════════════════════════════════════════════════
//  5 — Metabox
// ═══════════════════════════════════════════════════════════════════════════════

add_action( 'add_meta_boxes', function () {

    $types = [ 'post', 'page' ];
    if ( class_exists( 'WooCommerce' ) ) {
        $types[] = 'product';
    }
    
    // Aggiungi CPT abilitati
    $options = wp_parse_args( get_option( 'ai_fr_options', [] ), ai_fr_get_default_options() );
    $types = array_merge( $types, (array) ( $options['include_cpt'] ?? [] ) );

    foreach ( array_unique( $types ) as $type ) {
        add_meta_box(
            'ai_fr_meta',
            'AI Friendly',
            'ai_fr_render_metabox',
            $type,
            'side',
            'low'
        );
    }
} );

function ai_fr_render_metabox( WP_Post $post ): void {
    wp_nonce_field( 'ai_fr_save_meta', 'ai_fr_nonce' );
    $excluded = get_post_meta( $post->ID, '_ai_fr_exclude', true );
    $last_generated = get_post_meta( $post->ID, '_ai_fr_md_generated', true );
    ?>
    <label style="display:flex; align-items:center; gap:8px; margin-top:4px;">
        <input type="checkbox"
               name="_ai_fr_exclude"
               value="1"
               <?php checked( $excluded, '1' ); ?> />
        Escludi da llms.txt e versione .md
    </label>
    
    <?php if ( $last_generated ) : ?>
    <p style="margin-top:10px; color:#666; font-size:12px;">
        <strong>Ultima generazione MD:</strong><br>
        <?php echo esc_html( $last_generated ); ?>
    </p>
    <?php endif; ?>
    <?php
}

add_action( 'save_post', function ( int $post_id ): void {

    if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;
    if ( ! isset( $_POST['ai_fr_nonce'] )
      || ! wp_verify_nonce( $_POST['ai_fr_nonce'], 'ai_fr_save_meta' ) ) return;

    isset( $_POST['_ai_fr_exclude'] )
        ? update_post_meta( $post_id, '_ai_fr_exclude', '1' )
        : delete_post_meta( $post_id, '_ai_fr_exclude' );
} );


// ═══════════════════════════════════════════════════════════════════════════════
//  6 — Utility
// ═══════════════════════════════════════════════════════════════════════════════

function ai_fr_permalink_to_md( string $permalink ): string {
    return rtrim( $permalink, '/' ) . '.md';
}

function ai_fr_excerpt( WP_Post $post ): string {
    $raw = $post->post_excerpt !== '' ? $post->post_excerpt : $post->post_content;
    $raw = preg_replace( '/\[[^\]]+\]/', '', $raw ) ?? $raw;
    $text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $raw ) ) ?? '' );

    return strlen( $text ) > AI_FR_EXCERPT_LEN
        ? substr( $text, 0, AI_FR_EXCERPT_LEN ) . '…'
        : $text;
}


// ═══════════════════════════════════════════════════════════════════════════════
//  7 — ADMIN
// ═══════════════════════════════════════════════════════════════════════════════

add_action( 'admin_menu', function () {
    add_options_page(
        'AI Friendly',
        'AI Friendly',
        'manage_options',
        'ai-friendly',
        'ai_fr_render_options_page'
    );
} );

// AJAX per rigenerazione manuale
add_action( 'wp_ajax_ai_fr_regenerate_all', function() {
    check_ajax_referer( 'ai_fr_admin_nonce', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permessi insufficienti' );
    }
    
    $force = ! empty( $_POST['force'] );
    $stats = ai_fr_regenerate_all( $force );
    
    wp_send_json_success( $stats );
} );

// AJAX per pulizia versioni
add_action( 'wp_ajax_ai_fr_clear_versions', function() {
    check_ajax_referer( 'ai_fr_admin_nonce', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permessi insufficienti' );
    }
    
    $count = AiFrVersioning::clearAll();
    
    wp_send_json_success( [ 'deleted' => $count ] );
} );

function ai_fr_render_options_page(): void {

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $defaults = ai_fr_get_default_options();
    $options = wp_parse_args( get_option( 'ai_fr_options', [] ), $defaults );

    // Salvataggio
    if ( isset( $_POST['ai_fr_save'] ) && check_admin_referer( 'ai_fr_options_nonce' ) ) {
        $options['llms_content']        = sanitize_textarea_field( wp_unslash( $_POST['llms_content'] ?? '' ) );
        $options['llms_include_auto']   = ! empty( $_POST['llms_include_auto'] ) ? '1' : '';
        
        // Tipi di contenuto
        $options['include_pages']       = ! empty( $_POST['include_pages'] ) ? '1' : '';
        $options['include_posts']       = ! empty( $_POST['include_posts'] ) ? '1' : '';
        $options['include_products']    = ! empty( $_POST['include_products'] ) ? '1' : '';
        $options['include_cpt']         = array_map( 'sanitize_key', (array) ( $_POST['include_cpt'] ?? [] ) );
        
        // Esclusioni
        $options['exclude_categories']  = array_map( 'intval', (array) ( $_POST['exclude_categories'] ?? [] ) );
        $options['exclude_tags']        = array_map( 'intval', (array) ( $_POST['exclude_tags'] ?? [] ) );
        $options['exclude_templates']   = array_map( 'sanitize_text_field', (array) ( $_POST['exclude_templates'] ?? [] ) );
        $options['exclude_url_patterns'] = sanitize_textarea_field( wp_unslash( $_POST['exclude_url_patterns'] ?? '' ) );
        $options['exclude_noindex']     = ! empty( $_POST['exclude_noindex'] ) ? '1' : '';
        $options['exclude_password']    = ! empty( $_POST['exclude_password'] ) ? '1' : '';
        
        // Versioning
        $options['static_md_files']     = ! empty( $_POST['static_md_files'] ) ? '1' : '';
        
        // Scheduler
        $options['auto_regenerate']     = ! empty( $_POST['auto_regenerate'] ) ? '1' : '';
        $options['regenerate_interval'] = max( 1, intval( $_POST['regenerate_interval'] ?? 24 ) );
        $options['regenerate_on_save']  = ! empty( $_POST['regenerate_on_save'] ) ? '1' : '';
        $options['regenerate_on_change'] = ! empty( $_POST['regenerate_on_change'] ) ? '1' : '';

        update_option( 'ai_fr_options', $options );
        
        // Aggiorna cron
        ai_fr_schedule_cron();

        echo '<div class="notice notice-success"><p>Impostazioni salvate.</p></div>';
    }

    // Dati per il form
    $all_categories = get_categories( [ 'hide_empty' => false ] );
    $all_tags = get_tags( [ 'hide_empty' => false ] );
    $all_templates = wp_get_theme()->get_page_templates();
    $all_cpt = get_post_types( [ 'public' => true, '_builtin' => false ], 'objects' );
    
    // Statistiche versioni
    $version_stats = AiFrVersioning::getStats();
    $last_regen = get_option( 'ai_fr_last_regeneration', [] );
    
    // Prossimo cron
    $next_cron = wp_next_scheduled( 'ai_fr_cron_regenerate' );

    $name = get_bloginfo( 'blogname' );
    $desc = get_bloginfo( 'description' ) ?: 'Descrizione del sito';

    ?>
    <div class="wrap">
        <h1>AI Friendly — Impostazioni <small style="font-weight:normal; color:#666;">v1.5.0</small></h1>

        <form method="post">
            <?php wp_nonce_field( 'ai_fr_options_nonce' ); ?>
            
            <!-- Tab Navigation -->
            <h2 class="nav-tab-wrapper">
                <a href="#tab-content" class="nav-tab nav-tab-active" data-tab="content">Contenuto llms.txt</a>
                <a href="#tab-filters" class="nav-tab" data-tab="filters">Filtri & Esclusioni</a>
                <a href="#tab-versioning" class="nav-tab" data-tab="versioning">Versioning MD</a>
                <a href="#tab-scheduler" class="nav-tab" data-tab="scheduler">Scheduler</a>
            </h2>

            <!-- TAB: Contenuto llms.txt -->
            <div id="tab-content" class="ai-fr-tab-content" style="display:block;">
                <table class="form-table">
                    <tr>
                        <th><label for="llms_content">Contenuto llms.txt</label></th>
                        <td>
                            <textarea name="llms_content" id="llms_content" rows="15" cols="100" class="large-text code"
                                placeholder="# <?php echo esc_attr( $name ); ?>&#10;> <?php echo esc_attr( $desc ); ?>"
                            ><?php echo esc_textarea( $options['llms_content'] ); ?></textarea>
                            <p class="description">Contenuto custom in Markdown. Lascia vuoto per generazione automatica.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Lista automatica</th>
                        <td>
                            <label>
                                <input type="checkbox" name="llms_include_auto" value="1" <?php checked( $options['llms_include_auto'] ); ?> />
                                Aggiungi lista automatica dopo il contenuto custom
                            </label>
                        </td>
                    </tr>
                </table>
                
                <h3>Anteprima</h3>
                <p>
                    <code><?php echo esc_html( home_url( '/llms.txt' ) ); ?></code>
                    <a href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>" target="_blank" class="button button-secondary">Visualizza →</a>
                </p>
            </div>

            <!-- TAB: Filtri & Esclusioni -->
            <div id="tab-filters" class="ai-fr-tab-content" style="display:none;">
                
                <h3>Tipi di contenuto da includere</h3>
                <table class="form-table">
                    <tr>
                        <th>Contenuti standard</th>
                        <td>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="checkbox" name="include_pages" value="1" <?php checked( $options['include_pages'] ); ?> />
                                Pagine
                            </label>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="checkbox" name="include_posts" value="1" <?php checked( $options['include_posts'] ); ?> />
                                Articoli (Post)
                            </label>
                            <?php if ( class_exists( 'WooCommerce' ) ) : ?>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="checkbox" name="include_products" value="1" <?php checked( $options['include_products'] ); ?> />
                                Prodotti WooCommerce
                            </label>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <?php if ( ! empty( $all_cpt ) ) : ?>
                    <tr>
                        <th>Custom Post Types</th>
                        <td>
                            <?php foreach ( $all_cpt as $cpt ) : ?>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="checkbox" name="include_cpt[]" value="<?php echo esc_attr( $cpt->name ); ?>" 
                                    <?php checked( in_array( $cpt->name, (array) $options['include_cpt'], true ) ); ?> />
                                <?php echo esc_html( $cpt->labels->name ); ?> <code>(<?php echo esc_html( $cpt->name ); ?>)</code>
                            </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
                
                <h3>Esclusioni</h3>
                <table class="form-table">
                    <tr>
                        <th>Opzioni generali</th>
                        <td>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="checkbox" name="exclude_noindex" value="1" <?php checked( $options['exclude_noindex'] ); ?> />
                                Escludi pagine con meta <code>noindex</code> (Yoast, Rank Math, etc.)
                            </label>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="checkbox" name="exclude_password" value="1" <?php checked( $options['exclude_password'] ); ?> />
                                Escludi contenuti protetti da password
                            </label>
                        </td>
                    </tr>
                    
                    <?php if ( ! empty( $all_categories ) ) : ?>
                    <tr>
                        <th><label>Escludi categorie</label></th>
                        <td>
                            <select name="exclude_categories[]" multiple size="6" style="min-width:300px;">
                                <?php foreach ( $all_categories as $cat ) : ?>
                                <option value="<?php echo esc_attr( $cat->term_id ); ?>"
                                    <?php selected( in_array( $cat->term_id, (array) $options['exclude_categories'], false ) ); ?>>
                                    <?php echo esc_html( $cat->name ); ?> (<?php echo $cat->count; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Tieni premuto Ctrl/Cmd per selezione multipla</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if ( ! empty( $all_tags ) ) : ?>
                    <tr>
                        <th><label>Escludi tag</label></th>
                        <td>
                            <select name="exclude_tags[]" multiple size="6" style="min-width:300px;">
                                <?php foreach ( $all_tags as $tag ) : ?>
                                <option value="<?php echo esc_attr( $tag->term_id ); ?>"
                                    <?php selected( in_array( $tag->term_id, (array) $options['exclude_tags'], false ) ); ?>>
                                    <?php echo esc_html( $tag->name ); ?> (<?php echo $tag->count; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if ( ! empty( $all_templates ) ) : ?>
                    <tr>
                        <th><label>Escludi template</label></th>
                        <td>
                            <select name="exclude_templates[]" multiple size="5" style="min-width:300px;">
                                <?php foreach ( $all_templates as $file => $name ) : ?>
                                <option value="<?php echo esc_attr( $file ); ?>"
                                    <?php selected( in_array( $file, (array) $options['exclude_templates'], true ) ); ?>>
                                    <?php echo esc_html( $name ); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <tr>
                        <th><label for="exclude_url_patterns">Escludi pattern URL</label></th>
                        <td>
                            <textarea name="exclude_url_patterns" id="exclude_url_patterns" rows="5" cols="50" class="code"
                                placeholder="/landing/*&#10;/promo-*&#10;/test/"
                            ><?php echo esc_textarea( $options['exclude_url_patterns'] ); ?></textarea>
                            <p class="description">
                                Un pattern per riga. Supporta <code>*</code> come wildcard.<br>
                                Esempio: <code>/landing/*</code> esclude tutte le pagine sotto /landing/
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- TAB: Versioning MD -->
            <div id="tab-versioning" class="ai-fr-tab-content" style="display:none;">
                <table class="form-table">
                    <tr>
                        <th>File MD statici</th>
                        <td>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="checkbox" name="static_md_files" value="1" <?php checked( $options['static_md_files'] ); ?> />
                                Salva e servi file MD statici (più veloce)
                            </label>
                            <p class="description">
                                Se attivo, i file .md vengono salvati su disco e serviti direttamente.<br>
                                Se disattivo, vengono generati dinamicamente ad ogni richiesta.<br>
                                Directory: <code><?php echo esc_html( AI_FR_VERSIONS_DIR ); ?></code>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th>Statistiche</th>
                        <td>
                            <div style="background:#f5f5f5; padding:15px; border-radius:4px;">
                                <strong>File salvati:</strong> <?php echo $version_stats['count']; ?><br>
                                <strong>Spazio utilizzato:</strong> <?php echo size_format( $version_stats['size'] ); ?><br>
                                <?php if ( ! empty( $last_regen['time'] ) ) : ?>
                                <strong>Ultima rigenerazione:</strong> <?php echo esc_html( $last_regen['time'] ); ?>
                                    <?php if ( ! empty( $last_regen['stats'] ) ) : ?>
                                    <br><small>
                                        Processati: <?php echo $last_regen['stats']['processed']; ?> |
                                        Rigenerati: <?php echo $last_regen['stats']['regenerated']; ?> |
                                        Saltati: <?php echo $last_regen['stats']['skipped']; ?> |
                                        Errori: <?php echo $last_regen['stats']['errors']; ?>
                                    </small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th>Azioni</th>
                        <td>
                            <button type="button" id="ai-fr-regenerate" class="button button-primary">
                                🔄 Rigenera tutti i file MD
                            </button>
                            <button type="button" id="ai-fr-regenerate-force" class="button">
                                ⚡ Forza rigenerazione (ignora checksum)
                            </button>
                            <button type="button" id="ai-fr-clear-versions" class="button" style="color:#a00;">
                                🗑️ Elimina tutti i file
                            </button>
                            <p id="ai-fr-action-status" style="margin-top:10px;"></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- TAB: Scheduler -->
            <div id="tab-scheduler" class="ai-fr-tab-content" style="display:none;">
                <table class="form-table">
                    <tr>
                        <th>Rigenerazione automatica</th>
                        <td>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="checkbox" name="auto_regenerate" value="1" <?php checked( $options['auto_regenerate'] ); ?> />
                                Attiva rigenerazione automatica via cron
                            </label>
                            <label style="display:block; margin-top:10px;">
                                Intervallo: 
                                <input type="number" name="regenerate_interval" value="<?php echo esc_attr( $options['regenerate_interval'] ); ?>" 
                                    min="1" max="168" style="width:60px;" /> ore
                            </label>
                            <?php if ( $next_cron ) : ?>
                            <p class="description" style="margin-top:10px;">
                                <strong>Prossima esecuzione:</strong> <?php echo esc_html( date( 'Y-m-d H:i:s', $next_cron ) ); ?>
                            </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Trigger su eventi</th>
                        <td>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="checkbox" name="regenerate_on_save" value="1" <?php checked( $options['regenerate_on_save'] ); ?> />
                                Rigenera quando un contenuto viene salvato/aggiornato
                            </label>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="checkbox" name="regenerate_on_change" value="1" <?php checked( $options['regenerate_on_change'] ); ?> />
                                Rigenera solo se il contenuto è effettivamente cambiato (checksum)
                            </label>
                            <p class="description">
                                Il checksum confronta il nuovo MD con quello salvato per evitare scritture inutili.
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <p class="submit">
                <input type="submit" name="ai_fr_save" class="button button-primary" value="Salva impostazioni">
            </p>
        </form>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Tab switching
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            var tab = $(this).data('tab');
            
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            $('.ai-fr-tab-content').hide();
            $('#tab-' + tab).show();
        });
        
        // AJAX actions
        var nonce = '<?php echo wp_create_nonce( 'ai_fr_admin_nonce' ); ?>';
        
        $('#ai-fr-regenerate').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).text('⏳ Rigenerazione in corso...');
            
            $.post(ajaxurl, {
                action: 'ai_fr_regenerate_all',
                nonce: nonce,
                force: 0
            }, function(response) {
                $btn.prop('disabled', false).text('🔄 Rigenera tutti i file MD');
                if (response.success) {
                    var s = response.data;
                    $('#ai-fr-action-status').html(
                        '<span style="color:green;">✓ Completato! ' +
                        'Processati: ' + s.processed + ', Rigenerati: ' + s.regenerated + 
                        ', Saltati: ' + s.skipped + ', Errori: ' + s.errors + '</span>'
                    );
                } else {
                    $('#ai-fr-action-status').html('<span style="color:red;">✗ Errore: ' + response.data + '</span>');
                }
            });
        });
        
        $('#ai-fr-regenerate-force').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).text('⏳ Rigenerazione forzata...');
            
            $.post(ajaxurl, {
                action: 'ai_fr_regenerate_all',
                nonce: nonce,
                force: 1
            }, function(response) {
                $btn.prop('disabled', false).text('⚡ Forza rigenerazione');
                if (response.success) {
                    var s = response.data;
                    $('#ai-fr-action-status').html(
                        '<span style="color:green;">✓ Rigenerazione forzata completata! ' +
                        'Rigenerati: ' + s.regenerated + ', Errori: ' + s.errors + '</span>'
                    );
                }
            });
        });
        
        $('#ai-fr-clear-versions').on('click', function() {
            if (!confirm('Eliminare tutti i file MD salvati?')) return;
            
            var $btn = $(this);
            $btn.prop('disabled', true);
            
            $.post(ajaxurl, {
                action: 'ai_fr_clear_versions',
                nonce: nonce
            }, function(response) {
                $btn.prop('disabled', false);
                if (response.success) {
                    $('#ai-fr-action-status').html(
                        '<span style="color:green;">✓ Eliminati ' + response.data.deleted + ' file</span>'
                    );
                }
            });
        });
    });
    </script>
    <?php
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( array $links ): array {
    $settings_link = '<a href="' . admin_url( 'options-general.php?page=ai-friendly' ) . '">Impostazioni</a>';
    array_unshift( $links, $settings_link );
    return $links;
} );
