<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════════
//  SCHEDULER - Rigenerazione automatica
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Configura/aggiorna il cron job.
 */
function ai_fr_schedule_cron(): void {
    $options = wp_parse_args( get_option( 'ai_fr_options', [] ), ai_fr_get_default_options() );
    
    // Rimuovi eventuali cron esistenti (tutti) per evitare duplicati.
    wp_clear_scheduled_hook( 'ai_fr_cron_regenerate' );
    
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
        
        if ( ! wp_next_scheduled( 'ai_fr_cron_regenerate' ) ) {
            wp_schedule_event( time(), 'ai_fr_interval', 'ai_fr_cron_regenerate' );
        }
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
add_action(
    'ai_fr_cron_regenerate',
    function (): void {
        $options = wp_parse_args( get_option( 'ai_fr_options', [] ), ai_fr_get_default_options() );
        $batch_size = min( 1000, max( 10, intval( $options['regenerate_batch_size'] ?? 100 ) ) );
        ai_fr_regenerate_batch( $batch_size, false, 'cron' );
    }
);

/**
 * Rigenera un batch di versioni MD e mantiene un cursore persistente.
 */
function ai_fr_regenerate_batch( int $batch_size = 100, bool $force = false, string $trigger = 'cron' ): array {
    $batch_size = min( 1000, max( 10, $batch_size ) );
    $options = wp_parse_args( get_option( 'ai_fr_options', [] ), ai_fr_get_default_options() );
    $filter = new AiFrContentFilter();
    $post_types = $filter->getEnabledPostTypes();

    $stats = [
        'processed' => 0,
        'regenerated' => 0,
        'skipped' => 0,
        'errors' => 0,
        'batch_size' => $batch_size,
        'mode' => 'batch',
    ];

    if ( empty( $post_types ) ) {
        ai_fr_finalize_regeneration_stats( $stats, $trigger, $force );
        return $stats;
    }

    $state = get_option( 'ai_fr_regeneration_cursor', [] );
    $last_id = max( 0, intval( $state['last_id'] ?? 0 ) );

    $ids = ai_fr_get_regeneration_batch_post_ids( $post_types, $last_id, $batch_size );
    if ( empty( $ids ) && $last_id > 0 ) {
        // Nuovo ciclo completo: riparte dall'inizio all'esecuzione successiva.
        update_option( 'ai_fr_regeneration_cursor', [ 'last_id' => 0 ], false );
        $stats['cycle_reset'] = 1;
        ai_fr_finalize_regeneration_stats( $stats, $trigger, $force );
        return $stats;
    }

    if ( empty( $ids ) ) {
        ai_fr_finalize_regeneration_stats( $stats, $trigger, $force );
        return $stats;
    }

    $posts = get_posts(
        [
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'post__in'       => $ids,
            'orderby'        => 'post__in',
            'posts_per_page' => count( $ids ),
            'no_found_rows'  => true,
        ]
    );

    $new_last_id = ai_fr_process_regeneration_posts( $posts, $filter, $options, $force, $stats );
    update_option( 'ai_fr_regeneration_cursor', [ 'last_id' => $new_last_id ], false );

    ai_fr_finalize_regeneration_stats( $stats, $trigger, $force );
    return $stats;
}

/**
 * Restituisce gli ID del prossimo blocco di post da processare.
 *
 * @param string[] $post_types
 * @return int[]
 */
function ai_fr_get_regeneration_batch_post_ids( array $post_types, int $last_id, int $batch_size ): array {
    global $wpdb;

    $post_types = array_values( array_filter( array_map( 'sanitize_key', $post_types ) ) );
    if ( empty( $post_types ) ) {
        return [];
    }

    $type_placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
    $query = "
        SELECT ID
        FROM {$wpdb->posts}
        WHERE post_status = 'publish'
          AND post_type IN ($type_placeholders)
          AND ID > %d
        ORDER BY ID ASC
        LIMIT %d
    ";

    $params = array_merge( $post_types, [ $last_id, $batch_size ] );
    $prepared = $wpdb->prepare( $query, ...$params );
    if ( ! is_string( $prepared ) || $prepared === '' ) {
        return [];
    }

    $ids = $wpdb->get_col( $prepared );
    return array_map( 'intval', $ids );
}

/**
 * Rigenera tutte le versioni MD processando in chunk per ridurre il carico memoria.
 */
/**
 * Rigenera tutte le versioni MD.
 */
function ai_fr_regenerate_all( bool $force = false, string $trigger = 'manual' ): array {
    $options = wp_parse_args( get_option( 'ai_fr_options', [] ), ai_fr_get_default_options() );
    $filter = new AiFrContentFilter();

    $stats = [
        'processed' => 0,
        'regenerated' => 0,
        'skipped' => 0,
        'errors' => 0,
        'mode' => 'full',
    ];

    // Ottieni tutti i post dei tipi abilitati
    $post_types = $filter->getEnabledPostTypes();
    if ( empty( $post_types ) ) {
        ai_fr_finalize_regeneration_stats( $stats, $trigger, $force );
        return $stats;
    }

    $last_id = 0;
    $full_chunk_size = 300;
    while ( true ) {
        $ids = ai_fr_get_regeneration_batch_post_ids( $post_types, $last_id, $full_chunk_size );
        if ( empty( $ids ) ) {
            break;
        }

        $posts = get_posts(
            [
                'post_type'      => $post_types,
                'post_status'    => 'publish',
                'post__in'       => $ids,
                'orderby'        => 'post__in',
                'posts_per_page' => count( $ids ),
                'no_found_rows'  => true,
            ]
        );

        $next_last_id = ai_fr_process_regeneration_posts( $posts, $filter, $options, $force, $stats );
        if ( $next_last_id <= $last_id ) {
            break;
        }
        $last_id = $next_last_id;
    }

    delete_option( 'ai_fr_regeneration_cursor' );

    ai_fr_finalize_regeneration_stats( $stats, $trigger, $force );
    return $stats;
}

/**
 * Processa una lista di post e aggiorna le statistiche aggregate.
 *
 * @param WP_Post[] $posts
 */
function ai_fr_process_regeneration_posts( array $posts, AiFrContentFilter $filter, array $options, bool $force, array &$stats ): int {
    $last_processed_id = 0;

    foreach ( $posts as $post ) {
        if ( ! $post instanceof WP_Post ) {
            continue;
        }

        $last_processed_id = max( $last_processed_id, intval( $post->ID ) );
        $stats['processed']++;

        // Verifica se deve essere incluso.
        if ( ! $filter->shouldInclude( $post ) ) {
            $stats['skipped']++;
            continue;
        }

        try {
            $md_content = ai_fr_generate_markdown( $post );

            if ( empty( $md_content ) ) {
                AiFrVersioning::deleteVersion( $post->ID );
                $stats['skipped']++;
                continue;
            }

            // Verifica checksum se non forzato.
            if ( ! $force && ! empty( $options['regenerate_on_change'] ) ) {
                $current_checksum = md5( $md_content );
                $saved_checksum = get_post_meta( $post->ID, '_ai_fr_md_checksum', true );

                if ( $current_checksum === $saved_checksum && AiFrVersioning::hasValidVersion( $post->ID ) ) {
                    $stats['skipped']++;
                    continue;
                }
            }

            $result = AiFrVersioning::saveVersion( $post->ID, $md_content );
            if ( ! empty( $result['saved'] ) ) {
                if ( ! empty( $result['changed'] ) ) {
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

    return $last_processed_id;
}

/**
 * Salva timestamp ultima rigenerazione, log evento e notifiche.
 */
function ai_fr_finalize_regeneration_stats( array $stats, string $trigger, bool $force ): void {
    update_option(
        'ai_fr_last_regeneration',
        [
            'time'  => current_time( 'mysql' ),
            'stats' => $stats,
        ]
    );

    if ( function_exists( 'ai_fr_add_event' ) ) {
        ai_fr_add_event(
            'regenerate_all',
            [
                'trigger' => sanitize_key( $trigger ),
                'force'   => $force ? 1 : 0,
                'stats'   => $stats,
            ],
            $stats['errors'] > 0 ? 'warning' : 'info'
        );
    }

    if ( function_exists( 'ai_fr_maybe_notify_regeneration_errors' ) ) {
        ai_fr_maybe_notify_regeneration_errors( $stats, $trigger );
    }
}

/**
 * Genera il contenuto Markdown per un singolo post.
 */
function ai_fr_generate_markdown( WP_Post $post ): string {
    
    $post_id = $post->ID;
    
    // Ottieni contenuto HTML
    $html = ai_fr_get_rendered_content_safe( $post, false );
    $text_html = trim( strip_tags( $html ) );
    $fallback_plain = trim( wp_strip_all_tags( (string) $post->post_excerpt ) );
    if ( $fallback_plain === '' ) {
        $fallback_plain = ai_fr_extract_text_from_raw( (string) $post->post_content );
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
    
    if ( $text_html !== '' ) {
        $md .= $converter->convert( $html ) . "\n";
    } elseif ( $fallback_plain !== '' ) {
        $md .= $fallback_plain . "\n";
    } else {
        $md .= "_Contenuto non disponibile._\n";
    }
    
    return $md;
}

// Hook per rigenerazione su salvataggio post
add_action( 'save_post', function( int $post_id, WP_Post $post, bool $update ): void {
    
    // Skip autosave e revisioni
    if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
        return;
    }
    
    // Invalida cache MD dinamica
    ai_fr_invalidate_md_cache( $post_id );
    
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
            $result = AiFrVersioning::saveVersion( $post_id, $md_content );
            if ( ! empty( $result['saved'] ) && ! empty( $result['changed'] ) && function_exists( 'ai_fr_add_event' ) ) {
                ai_fr_add_event(
                    'save_post_regenerate',
                    [
                        'post_id'   => $post_id,
                        'post_type' => $post->post_type,
                    ]
                );
            }
        } else {
            AiFrVersioning::deleteVersion( $post_id );
        }
    } catch ( Throwable $e ) {
        error_log( 'AI Friendly save_post error for ' . $post_id . ': ' . $e->getMessage() );
    }
    
}, 20, 3 );

// Hook per eliminazione post
add_action( 'before_delete_post', function( int $post_id ): void {
    ai_fr_invalidate_md_cache( $post_id );
    AiFrVersioning::deleteVersion( $post_id );
} );

/**
 * Invalida la cache MD dinamica per un post.
 */
function ai_fr_invalidate_md_cache( int $post_id ): void {
    $cache_key = get_post_meta( $post_id, '_ai_fr_md_cache_key', true );
    if ( ! empty( $cache_key ) ) {
        delete_transient( $cache_key );
        delete_post_meta( $post_id, '_ai_fr_md_cache_key' );
    }
}

// Invalida cache quando cambiano meta rilevanti per l'output
add_action( 'added_post_meta', 'ai_fr_maybe_invalidate_cache_on_meta', 10, 4 );
add_action( 'updated_post_meta', 'ai_fr_maybe_invalidate_cache_on_meta', 10, 4 );
add_action( 'deleted_post_meta', 'ai_fr_maybe_invalidate_cache_on_meta', 10, 4 );

function ai_fr_maybe_invalidate_cache_on_meta( $meta_id, int $post_id, string $meta_key, $meta_value ): void {
    $keys = [
        // SEO title/description/noindex
        '_yoast_wpseo_title',
        '_yoast_wpseo_metadesc',
        '_yoast_wpseo_meta-robots-noindex',
        'rank_math_title',
        'rank_math_description',
        'rank_math_robots',
        '_aioseo_title',
        '_aioseo_description',
        '_aioseo_noindex',
        '_seopress_titles_title',
        '_seopress_titles_desc',
        '_seopress_robots_index',
        
        // Featured image + alt
        '_thumbnail_id',
        '_wp_attachment_image_alt',
        
        // Page builders
        '_breakdance_data',
        '_yootheme',
        '_elementor_data',
        'ct_builder_shortcodes',
        '_bricks_page_content_2',
        '_bricks_page_content',
        
        // AI Friendly specific
        '_ai_fr_exclude',
    ];
    
    $keys = apply_filters( 'ai_fr_md_cache_meta_keys', $keys, $post_id, $meta_key );
    
    if ( in_array( $meta_key, $keys, true ) ) {
        ai_fr_invalidate_md_cache( $post_id );
        return;
    }

    // ACF fields are stored in arbitrary meta keys and companion _key refs.
    if ( ai_fr_is_acf_meta_key( $post_id, $meta_key, $meta_value ) ) {
        ai_fr_invalidate_md_cache( $post_id );
    }
}

function ai_fr_is_acf_meta_key( int $post_id, string $meta_key, $meta_value ): bool {
    if ( $meta_key === '' ) {
        return false;
    }

    if ( str_starts_with( $meta_key, '_' ) ) {
        return is_string( $meta_value ) && str_starts_with( $meta_value, 'field_' );
    }

    $acf_ref = get_post_meta( $post_id, '_' . $meta_key, true );
    return is_string( $acf_ref ) && str_starts_with( $acf_ref, 'field_' );
}


