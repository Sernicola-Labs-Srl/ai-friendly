<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
//  SCHEDULER - Rigenerazione automatica
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

/**
 * Configura/aggiorna il cron job.
 */
function saifr_schedule_cron(): void {
    $options = wp_parse_args( get_option( 'saifr_options', [] ), saifr_get_default_options() );
    
    // Rimuovi eventuali cron esistenti (tutti) per evitare duplicati.
    wp_clear_scheduled_hook( 'saifr_cron_regenerate' );
    
    // Schedula nuovo cron se abilitato
    if ( ! empty( $options['auto_regenerate'] ) && ! empty( $options['static_md_files'] ) ) {
        $interval_hours = max( 1, intval( $options['regenerate_interval'] ) );
        
        // Registra intervallo custom se necessario
        add_filter( 'cron_schedules', function( $schedules ) use ( $interval_hours ) {
            $schedules['saifr_interval'] = [
                'interval' => $interval_hours * HOUR_IN_SECONDS,
                // translators: %d: regeneration interval in hours.
                'display'  => sprintf( __( 'Ogni %d ore', 'sernicola-labs-ai-friendly' ), $interval_hours ),
            ];
            return $schedules;
        } );
        
        if ( ! wp_next_scheduled( 'saifr_cron_regenerate' ) ) {
            wp_schedule_event( time(), 'saifr_interval', 'saifr_cron_regenerate' );
        }
    }
}

// Registra intervalli cron custom
add_filter( 'cron_schedules', function( $schedules ) {
    $options = wp_parse_args( get_option( 'saifr_options', [] ), saifr_get_default_options() );
    $interval_hours = max( 1, intval( $options['regenerate_interval'] ?? 24 ) );
    
    $schedules['saifr_interval'] = [
        'interval' => $interval_hours * HOUR_IN_SECONDS,
        'display'  => sprintf(
            /* translators: %d: regeneration interval in hours. */
            __( 'AI Friendly: ogni %d ore', 'sernicola-labs-ai-friendly' ),
            $interval_hours
        ),
    ];
    
    return $schedules;
} );

// Hook per cron
add_action(
    'saifr_cron_regenerate',
    function (): void {
        $options = wp_parse_args( get_option( 'saifr_options', [] ), saifr_get_default_options() );
        $batch_size = min( 1000, max( 1, intval( $options['regenerate_batch_size'] ?? 100 ) ) );
        saifr_regenerate_batch( $batch_size, false, 'cron' );
    }
);

/**
 * Rigenera un batch di versioni MD e mantiene un cursore persistente.
 */
function saifr_regenerate_batch( int $batch_size = 100, bool $force = false, string $trigger = 'cron' ): array {
    $batch_size = min( 1000, max( 1, $batch_size ) );
    $options = wp_parse_args( get_option( 'saifr_options', [] ), saifr_get_default_options() );
    $filter = new SaifrContentFilter();
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
        saifr_finalize_regeneration_stats( $stats, $trigger, $force );
        return $stats;
    }

    $state = get_option( 'saifr_regeneration_cursor', [] );
    $last_id = max( 0, intval( $state['last_id'] ?? 0 ) );

    $query_started = microtime( true );
    $ids = saifr_get_regeneration_batch_post_ids( $post_types, $last_id, $batch_size );
    $stats['query_seconds'] = round( microtime( true ) - $query_started, 4 );
    if ( empty( $ids ) && $last_id > 0 ) {
        // Nuovo ciclo completo: riparte dall'inizio all'esecuzione successiva.
        update_option( 'saifr_regeneration_cursor', [ 'last_id' => 0 ], false );
        $stats['cycle_reset'] = 1;
        $cleanup_started = microtime( true );
        $stats['deleted_obsolete'] = SaifrVersioning::pruneObsoleteVersions();
        $stats['cleanup_seconds'] = round( microtime( true ) - $cleanup_started, 4 );
        saifr_finalize_regeneration_stats( $stats, $trigger, $force );
        return $stats;
    }

    if ( empty( $ids ) ) {
        saifr_finalize_regeneration_stats( $stats, $trigger, $force );
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

    $generate_started = microtime( true );
    saifr_process_regeneration_posts( $posts, $filter, $options, $force, $stats );
    $stats['generation_seconds'] = round( microtime( true ) - $generate_started, 4 );
    // Advance by the queried IDs even if another hook hid or removed a post.
    $new_last_id = max( $ids );
    update_option( 'saifr_regeneration_cursor', [ 'last_id' => $new_last_id ], false );

    saifr_finalize_regeneration_stats( $stats, $trigger, $force, false );
    return $stats;
}

/**
 * Restituisce gli ID del prossimo blocco di post da processare.
 *
 * @param string[] $post_types
 * @return int[]
 */
function saifr_get_regeneration_batch_post_ids( array $post_types, int $last_id, int $batch_size ): array {
    global $wpdb;

    $post_types = array_values( array_filter( array_map( 'sanitize_key', $post_types ) ) );
    if ( empty( $post_types ) ) {
        return [];
    }

    $type_placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
    // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
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
    // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
    if ( ! is_string( $prepared ) || $prepared === '' ) {
        return [];
    }

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared statement executed for batch cursor query over post IDs.
    $ids = $wpdb->get_col( $prepared );
    return array_map( 'intval', $ids );
}

function saifr_count_regeneration_posts( array $post_types ): int {
    global $wpdb;
    $post_types = array_values( array_filter( array_map( 'sanitize_key', $post_types ) ) );
    if ( ! $post_types ) return 0;
    $placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The dynamic fragment contains only placeholders.
    $sql = $wpdb->prepare( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ($placeholders)", ...$post_types );
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    return (int) $wpdb->get_var( $sql );
}

/**
 * Avvia una rigenerazione completa persistente e restituisce lo stato del lavoro.
 */
function saifr_regenerate_all( bool $force = false, string $trigger = 'manual' ): array {
    $job = get_option( 'saifr_regeneration_job', [] );
    if ( is_array( $job ) && ( $job['status'] ?? '' ) === 'running' ) {
        return $job;
    }
    $job = [
        'id' => wp_generate_uuid4(), 'status' => 'running', 'last_id' => 0,
        'force' => $force, 'trigger' => $trigger, 'started' => time(),
        'total' => saifr_count_regeneration_posts( ( new SaifrContentFilter() )->getEnabledPostTypes() ),
        'stats' => [ 'processed' => 0, 'regenerated' => 0, 'skipped' => 0, 'errors' => 0, 'mode' => 'full' ],
    ];
    $options = wp_parse_args( get_option( 'saifr_options', [] ), saifr_get_default_options() );
    if ( ! empty( $options['llms_full_enabled'] ) ) {
        $job['corpus_tmp'] = saifr_llms_full_begin( $job['id'] );
        if ( $job['corpus_tmp'] === '' ) {
            $job['status'] = 'failed';
            $job['error'] = 'Unable to create the full corpus temporary file.';
        }
    }
    update_option( 'saifr_regeneration_job', $job, false );
    if ( $job['status'] === 'running' ) saifr_schedule_regeneration_step();
    return $job;
}

function saifr_schedule_regeneration_step(): void {
    if ( ! wp_next_scheduled( 'saifr_regeneration_step' ) ) {
        wp_schedule_single_event( time() + 1, 'saifr_regeneration_step' );
    }
}

/** One bounded slice, callable by AJAX or WP-Cron; the transient is a cross-request lock. */
function saifr_run_regeneration_step(): array {
    $job = get_option( 'saifr_regeneration_job', [] );
    if ( ! is_array( $job ) || ( $job['status'] ?? '' ) !== 'running' ) {
        return is_array( $job ) ? $job : [];
    }
    $lock_time = (int) get_option( 'saifr_regeneration_lock', 0 );
    if ( $lock_time > 0 && $lock_time < time() - 2 * MINUTE_IN_SECONDS ) {
        delete_option( 'saifr_regeneration_lock' );
    }
    if ( ! add_option( 'saifr_regeneration_lock', time(), '', false ) ) {
        if ( ! wp_next_scheduled( 'saifr_regeneration_step' ) ) wp_schedule_single_event( time() + 2 * MINUTE_IN_SECONDS, 'saifr_regeneration_step' );
        return $job;
    }
    if ( ! wp_next_scheduled( 'saifr_regeneration_step' ) ) wp_schedule_single_event( time() + 2 * MINUTE_IN_SECONDS, 'saifr_regeneration_step' );
    try {
        $options = wp_parse_args( get_option( 'saifr_options', [] ), saifr_get_default_options() );
        if ( empty( $options['llms_full_enabled'] ) && ! empty( $job['corpus_tmp'] ) ) {
            @unlink( $job['corpus_tmp'] );
            unset( $job['corpus_tmp'] );
        }
        $filter = new SaifrContentFilter();
        $started = microtime( true );
        $ids = saifr_get_regeneration_batch_post_ids( $filter->getEnabledPostTypes(), (int) $job['last_id'], min( 25, max( 1, (int) $options['regenerate_batch_size'] ) ) );
        $job['stats']['query_seconds'] = ( $job['stats']['query_seconds'] ?? 0 ) + microtime( true ) - $started;
        if ( ! $ids ) {
            $started = microtime( true );
            $job['stats']['deleted_obsolete'] = SaifrVersioning::pruneObsoleteVersions();
            $job['stats']['cleanup_seconds'] = round( microtime( true ) - $started, 4 );
            if ( ! empty( $job['corpus_tmp'] ) ) {
                if ( (int) $job['stats']['errors'] > 0 || ! saifr_llms_full_publish( $job['corpus_tmp'] ) ) {
                    $job['status'] = 'failed';
                    $job['error'] = 'Full corpus publication failed; the previous file was preserved.';
                    @unlink( $job['corpus_tmp'] );
                }
            }
            if ( $job['status'] === 'running' ) $job['status'] = 'complete';
            $job['finished'] = time();
            delete_option( 'saifr_regeneration_cursor' );
            wp_clear_scheduled_hook( 'saifr_regeneration_step' );
            if ( $job['status'] === 'complete' ) saifr_finalize_regeneration_stats( $job['stats'], (string) $job['trigger'], (bool) $job['force'] );
        } else {
            $posts = get_posts( [ 'post_type' => $filter->getEnabledPostTypes(), 'post_status' => 'publish', 'post__in' => $ids, 'orderby' => 'post__in', 'posts_per_page' => count( $ids ), 'no_found_rows' => true ] );
            $started = microtime( true );
            $on_markdown = ! empty( $job['corpus_tmp'] ) ? static function ( WP_Post $post, string $markdown ) use ( $job ): void {
                if ( ! saifr_can_serve_post( $post, 'serve' ) ) return;
                if ( ! saifr_llms_full_append( $job['corpus_tmp'], $post, $markdown ) ) {
                    throw new RuntimeException( 'Unable to append post to full corpus.' );
                }
            } : null;
            saifr_process_regeneration_posts( $posts, $filter, $options, (bool) $job['force'], $job['stats'], $on_markdown );
            $job['stats']['generation_seconds'] = ( $job['stats']['generation_seconds'] ?? 0 ) + microtime( true ) - $started;
            $job['last_id'] = max( $ids );
            wp_clear_scheduled_hook( 'saifr_regeneration_step' );
            saifr_schedule_regeneration_step();
        }
        update_option( 'saifr_regeneration_job', $job, false );
        if ( $job['status'] !== 'running' && get_option( 'saifr_corpus_dirty', false ) ) {
            delete_option( 'saifr_corpus_dirty' );
            if ( ! empty( $options['llms_full_enabled'] ) ) saifr_regenerate_all( false, 'corpus_refresh' );
        }
    } catch ( Throwable $error ) {
        $job['status'] = 'failed';
        $job['error'] = $error->getMessage();
        wp_clear_scheduled_hook( 'saifr_regeneration_step' );
        update_option( 'saifr_regeneration_job', $job, false );
        do_action( 'saifr_runtime_error', 'regeneration_job_error', 0, $error->getMessage() );
        if ( ! empty( $job['corpus_tmp'] ) ) @unlink( $job['corpus_tmp'] );
    } finally {
        delete_option( 'saifr_regeneration_lock' );
    }
    return $job;
}
add_action( 'saifr_regeneration_step', 'saifr_run_regeneration_step' );

add_action( 'profile_update', function ( int $user_id, WP_User $old_user ): void {
    $current = get_userdata( $user_id );
    if ( ! $current || $current->display_name === $old_user->display_name ) {
        return;
    }
    saifr_request_corpus_refresh();
    update_option( 'saifr_author_regeneration_' . $user_id, 0, false );
    if ( ! wp_next_scheduled( 'saifr_author_regeneration', [ $user_id ] ) ) {
        wp_schedule_single_event( time() + 1, 'saifr_author_regeneration', [ $user_id ] );
    }
}, 10, 2 );

add_action( 'saifr_author_regeneration', function ( int $user_id ): void {
    global $wpdb;
    $last_id = (int) get_option( 'saifr_author_regeneration_' . $user_id, 0 );
    $filter = new SaifrContentFilter();
    $types = $filter->getEnabledPostTypes();
    if ( ! $types ) {
        delete_option( 'saifr_author_regeneration_' . $user_id );
        return;
    }
    $placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
    $args = array_merge( [ $user_id ], $types, [ $last_id, 10 ] );
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Only sanitized post type placeholders enter the SQL fragment.
    $sql = $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_author = %d AND post_type IN ($placeholders) AND post_status = 'publish' AND ID > %d ORDER BY ID ASC LIMIT %d", ...$args );
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    $ids = array_map( 'intval', $wpdb->get_col( $sql ) );
    if ( ! $ids ) {
        delete_option( 'saifr_author_regeneration_' . $user_id );
        return;
    }
    $options = wp_parse_args( get_option( 'saifr_options', [] ), saifr_get_default_options() );
    foreach ( $ids as $post_id ) {
        saifr_invalidate_md_cache( $post_id );
        $post = get_post( $post_id );
        if ( ! $post instanceof WP_Post || ! $filter->shouldInclude( $post ) || ! saifr_can_serve_post( $post, 'serve' ) ) {
            continue;
        }
        if ( ! empty( $options['static_md_files'] ) ) {
            try {
                SaifrVersioning::saveVersion( $post_id, saifr_generate_markdown( $post ) );
            } catch ( Throwable $error ) {
                do_action( 'saifr_runtime_error', 'author_regeneration_error', $post_id, $error->getMessage() );
            }
        }
    }
    update_option( 'saifr_author_regeneration_' . $user_id, max( $ids ), false );
    wp_schedule_single_event( time() + 1, 'saifr_author_regeneration', [ $user_id ] );
} );

function saifr_request_corpus_refresh(): void {
    $options = wp_parse_args( get_option( 'saifr_options', [] ), saifr_get_default_options() );
    if ( empty( $options['llms_full_enabled'] ) ) return;
    update_option( 'saifr_corpus_dirty', 1, false );
    if ( ! wp_next_scheduled( 'saifr_start_corpus_refresh' ) ) {
        wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'saifr_start_corpus_refresh' );
    }
}
add_action( 'save_post', 'saifr_request_corpus_refresh', 120 );
add_action( 'deleted_post', 'saifr_request_corpus_refresh', 120 );
add_action( 'trashed_post', 'saifr_request_corpus_refresh', 120 );
add_action( 'untrashed_post', 'saifr_request_corpus_refresh', 120 );
add_action( 'saifr_start_corpus_refresh', static function (): void {
    $options = wp_parse_args( get_option( 'saifr_options', [] ), saifr_get_default_options() );
    if ( empty( $options['llms_full_enabled'] ) ) return;
    $job = get_option( 'saifr_regeneration_job', [] );
    if ( is_array( $job ) && ( $job['status'] ?? '' ) === 'running' ) return;
    delete_option( 'saifr_corpus_dirty' );
    saifr_regenerate_all( false, 'corpus_refresh' );
} );

/**
 * Processa una lista di post e aggiorna le statistiche aggregate.
 *
 * @param WP_Post[] $posts
 */
function saifr_process_regeneration_posts( array $posts, SaifrContentFilter $filter, array $options, bool $force, array &$stats, ?callable $on_markdown = null ): int {
    $last_processed_id = 0;

    foreach ( $posts as $post ) {
        if ( ! $post instanceof WP_Post ) {
            continue;
        }

        $last_processed_id = max( $last_processed_id, intval( $post->ID ) );
        $stats['processed']++;

        // Verifica se deve essere incluso.
        if ( ! $filter->shouldInclude( $post ) ) {
            SaifrVersioning::deleteVersion( $post->ID );
            $stats['skipped']++;
            continue;
        }

        try {
            $md_content = saifr_generate_markdown( $post );

            if ( empty( $md_content ) ) {
                SaifrVersioning::deleteVersion( $post->ID );
                $stats['skipped']++;
                continue;
            }

            if ( $on_markdown ) $on_markdown( $post, $md_content );

            if ( empty( $options['static_md_files'] ) ) {
                $stats['skipped']++;
                continue;
            }

            // Verifica checksum se non forzato.
            if ( ! $force && ! empty( $options['regenerate_on_change'] ) ) {
                $current_checksum = md5( $md_content );
                $saved_checksum = get_post_meta( $post->ID, '_saifr_md_checksum', true );

                if ( $current_checksum === $saved_checksum && SaifrVersioning::hasValidVersion( $post->ID ) ) {
                    $stats['skipped']++;
                    continue;
                }
            }

            $result = SaifrVersioning::saveVersion( $post->ID, $md_content );
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
            do_action( 'saifr_runtime_error', 'regeneration_error', $post->ID, $e->getMessage() );
        }
    }

    return $last_processed_id;
}

/**
 * Salva timestamp ultima rigenerazione, log evento e notifiche.
 */
function saifr_finalize_regeneration_stats( array $stats, string $trigger, bool $force, bool $sync_llms = true ): void {
    $started = microtime( true );
    if ( $sync_llms ) saifr_sync_static_llms_txt();
    $stats['llms_seconds'] = round( microtime( true ) - $started, 4 );

    update_option(
        'saifr_last_regeneration',
        [
            'time'  => current_time( 'mysql' ),
            'stats' => $stats,
        ]
    );

    if ( function_exists( 'saifr_add_event' ) ) {
        saifr_add_event(
            'regenerate_all',
            [
                'trigger' => sanitize_key( $trigger ),
                'force'   => $force ? 1 : 0,
                'stats'   => $stats,
            ],
            $stats['errors'] > 0 ? 'warning' : 'info'
        );
    }

    if ( function_exists( 'saifr_maybe_notify_regeneration_errors' ) ) {
        saifr_maybe_notify_regeneration_errors( $stats, $trigger );
    }

    do_action( 'saifr_regeneration_completed', $stats, $trigger, $force );
}

/**
 * Genera il contenuto Markdown per un singolo post.
 */
function saifr_generate_markdown( WP_Post $post ): string {
    
    $post_id = $post->ID;
    
    // Ottieni contenuto HTML
    $html = saifr_get_rendered_content_safe( $post, false );
    $text_html = trim( wp_strip_all_tags( $html ) );
    $fallback_plain = trim( wp_strip_all_tags( (string) $post->post_excerpt ) );
    if ( $fallback_plain === '' ) {
        $fallback_plain = saifr_extract_text_from_raw( (string) $post->post_content );
    }
    
    // Converter
    $converter = new SaifrConverter( SAIFR_NORMALIZE_HEADINGS );
    
    // Costruisci output
    $md = SaifrMetadata::frontmatter( $post );
    $md .= "# " . get_the_title( $post_id ) . "\n\n";
    
    // Featured image
    if ( SAIFR_INCLUDE_METADATA && has_post_thumbnail( $post_id ) ) {
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
    saifr_invalidate_md_cache( $post_id );
    
    // Skip se non pubblicato
    if ( $post->post_status !== 'publish' ) {
        // Se era pubblicato e ora non piÃ¹, elimina versione
        SaifrVersioning::deleteVersion( $post_id );
        return;
    }
    
    $options = wp_parse_args( get_option( 'saifr_options', [] ), saifr_get_default_options() );
    
    // Verifica se rigenerazione su save Ã¨ abilitata
    if ( empty( $options['static_md_files'] ) || empty( $options['regenerate_on_save'] ) ) {
        return;
    }
    
    // Verifica se il post deve essere incluso
    $filter = new SaifrContentFilter();
    if ( ! $filter->shouldInclude( $post ) ) {
        // Elimina eventuale versione esistente
        SaifrVersioning::deleteVersion( $post_id );
        return;
    }
    
    // Genera e salva
    try {
        $md_content = saifr_generate_markdown( $post );
        if ( ! empty( $md_content ) ) {
            $result = SaifrVersioning::saveVersion( $post_id, $md_content );
            if ( ! empty( $result['saved'] ) && ! empty( $result['changed'] ) && function_exists( 'saifr_add_event' ) ) {
                saifr_add_event(
                    'save_post_regenerate',
                    [
                        'post_id'   => $post_id,
                        'post_type' => $post->post_type,
                    ]
                );
            }
        } else {
            SaifrVersioning::deleteVersion( $post_id );
        }
    } catch ( Throwable $e ) {
        do_action( 'saifr_runtime_error', 'save_post_error', $post_id, $e->getMessage() );
    }
    
}, 20, 3 );

// Hook per eliminazione post
add_action( 'before_delete_post', function( int $post_id ): void {
    saifr_invalidate_md_cache( $post_id );
    SaifrVersioning::deleteVersion( $post_id );
} );

/**
 * Invalida la cache MD dinamica per un post.
 */
function saifr_invalidate_md_cache( int $post_id ): void {
    $cache_key = get_post_meta( $post_id, '_saifr_md_cache_key', true );
    if ( ! empty( $cache_key ) ) {
        delete_transient( $cache_key );
        delete_post_meta( $post_id, '_saifr_md_cache_key' );
    }
}

// Invalida cache quando cambiano meta rilevanti per l'output
add_action( 'added_post_meta', 'saifr_maybe_invalidate_cache_on_meta', 10, 4 );
add_action( 'updated_post_meta', 'saifr_maybe_invalidate_cache_on_meta', 10, 4 );
add_action( 'deleted_post_meta', 'saifr_maybe_invalidate_cache_on_meta', 10, 4 );

function saifr_maybe_invalidate_cache_on_meta( $meta_id, int $post_id, string $meta_key, $meta_value ): void {
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
        '_saifr_exclude',
    ];
    
    $keys = apply_filters( 'saifr_md_cache_meta_keys', $keys, $post_id, $meta_key );
    
    if ( in_array( $meta_key, $keys, true ) ) {
        saifr_invalidate_md_cache( $post_id );
        return;
    }

    // ACF fields are stored in arbitrary meta keys and companion _key refs.
    if ( saifr_is_acf_meta_key( $post_id, $meta_key, $meta_value ) ) {
        saifr_invalidate_md_cache( $post_id );
    }
}

function saifr_is_acf_meta_key( int $post_id, string $meta_key, $meta_value ): bool {
    if ( $meta_key === '' ) {
        return false;
    }

    if ( str_starts_with( $meta_key, '_' ) ) {
        return is_string( $meta_value ) && str_starts_with( $meta_value, 'field_' );
    }

    $acf_ref = get_post_meta( $post_id, '_' . $meta_key, true );
    return is_string( $acf_ref ) && str_starts_with( $acf_ref, 'field_' );
}
