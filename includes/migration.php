<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Import data stored by AI Friendly releases prior to the 2.1 rename.
 *
 * Legacy values are copied, not deleted, so a rollback remains possible. Data
 * already customized with the new prefix always takes precedence.
 *
 * @return array{legacy_found: bool, options: int, post_meta: int, snapshots: int}
 */
function saifr_maybe_migrate_legacy_data(): array {
    $summary = [
        'legacy_found' => false,
        'options'      => 0,
        'post_meta'    => 0,
        'snapshots'    => 0,
    ];

    if ( (string) get_option( 'saifr_legacy_migration_version', '' ) === '2' ) {
        return $summary;
    }

    $missing        = new stdClass();
    $legacy_options = get_option( 'ai_fr_options', $missing );
    if ( is_array( $legacy_options ) ) {
        $summary['legacy_found'] = true;
        $defaults                = saifr_get_default_options();
        $migrated_options        = wp_parse_args( $legacy_options, $defaults );
        $current_options         = get_option( 'saifr_options', $missing );

        if ( is_array( $current_options ) ) {
            foreach ( $current_options as $key => $value ) {
                if ( ! array_key_exists( $key, $defaults ) || $value !== $defaults[ $key ] ) {
                    $migrated_options[ $key ] = $value;
                }
            }
        }

        if ( $current_options === $missing ) {
            if ( add_option( 'saifr_options', $migrated_options, '', true ) ) {
                ++$summary['options'];
            }
        } elseif ( is_array( $current_options ) && $migrated_options !== $current_options ) {
            if ( update_option( 'saifr_options', $migrated_options ) ) {
                ++$summary['options'];
            }
        }
    }

    $legacy_option_map = [
        'ai_fr_onboarding_done' => [ 'saifr_onboarding_done', true ],
        'ai_fr_ui_version'      => [ 'saifr_ui_version', true ],
        'ai_fr_event_log'       => [ 'saifr_event_log', false ],
        'ai_fr_last_regeneration' => [ 'saifr_last_regeneration', false ],
    ];

    foreach ( $legacy_option_map as $legacy_name => [ $current_name, $autoload ] ) {
        $legacy_value = get_option( $legacy_name, $missing );
        if ( $legacy_value === $missing ) {
            continue;
        }

        $summary['legacy_found'] = true;
        if ( get_option( $current_name, $missing ) === $missing && add_option( $current_name, $legacy_value, '', $autoload ) ) {
            ++$summary['options'];
        }
    }

    $history_summary = saifr_migrate_legacy_history();
    if ( $history_summary['legacy_found'] ) {
        $summary['legacy_found'] = true;
    }
    $summary['snapshots'] = $history_summary['snapshots'];

    $summary['post_meta'] = saifr_migrate_legacy_post_meta();
    if ( $summary['post_meta'] > 0 ) {
        $summary['legacy_found'] = true;
    }

    update_option( 'saifr_legacy_migration_version', '2', false );

    return $summary;
}

/**
 * Import legacy llms.txt snapshots that are already stored as options.
 *
 * @return array{legacy_found: bool, snapshots: int}
 */
function saifr_migrate_legacy_history(): array {
    $result = [
        'legacy_found' => false,
        'snapshots'    => 0,
    ];
    $missing = new stdClass();
    $legacy_index = get_option( 'ai_fr_llms_history_index', $missing );
    if ( ! is_array( $legacy_index ) ) {
        return $result;
    }

    $result['legacy_found'] = true;
    $current_index = get_option( 'saifr_llms_history_index', [] );
    $current_index = is_array( $current_index ) ? $current_index : [];
    $known_ids = [];
    foreach ( $current_index as $entry ) {
        if ( is_array( $entry ) && ! empty( $entry['id'] ) ) {
            $known_ids[ (string) $entry['id'] ] = true;
        }
    }

    foreach ( $legacy_index as $entry ) {
        if ( ! is_array( $entry ) || empty( $entry['id'] ) ) {
            continue;
        }

        $id = (string) $entry['id'];
        if ( isset( $known_ids[ $id ] ) ) {
            continue;
        }

        $legacy_content = get_option( 'ai_fr_llms_snapshot_' . md5( $id ), $missing );
        if ( ! is_string( $legacy_content ) ) {
            continue;
        }
        $legacy_content = sanitize_textarea_field( $legacy_content );

        $current_name    = 'saifr_llms_snapshot_' . md5( $id );
        $current_content = get_option( $current_name, $missing );
        if ( $current_content === $missing ) {
            add_option( $current_name, $legacy_content, '', false );
            $current_content = get_option( $current_name, $missing );
        }
        if ( ! is_string( $current_content ) ) {
            continue;
        }

        unset( $entry['filename'], $entry['user_id'] );
        $entry['storage'] = 'option';
        $current_index[]  = $entry;
        $known_ids[ $id ] = true;
        ++$result['snapshots'];
    }

    if ( $result['snapshots'] > 0 ) {
        update_option( 'saifr_llms_history_index', $current_index, false );
    }

    return $result;
}

/**
 * Import user-authored post metadata without copying generated cache metadata.
 */
function saifr_migrate_legacy_post_meta(): int {
    $migrated = 0;
    $meta_map = [
        '_ai_fr_exclude' => '_saifr_exclude',
        '_ai_fr_schema'  => '_saifr_schema',
    ];
    $post_types = array_values( get_post_types( [], 'names' ) );

    foreach ( $meta_map as $legacy_key => $current_key ) {
        $page = 1;
        do {
            $post_ids = get_posts(
                [
                    'post_type'              => $post_types,
                    'post_status'            => 'any',
                    'posts_per_page'         => 100,
                    'paged'                  => $page,
                    'orderby'                => 'ID',
                    'order'                  => 'ASC',
                    'fields'                 => 'ids',
                    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-time, paginated lookup required to migrate legacy post metadata through WordPress APIs.
                    'meta_key'               => $legacy_key,
                    'update_post_meta_cache' => false,
                    'update_post_term_cache' => false,
                ]
            );

            foreach ( $post_ids as $post_id ) {
                $post_id = (int) $post_id;
                if ( metadata_exists( 'post', $post_id, $current_key ) ) {
                    continue;
                }

                $legacy_value = get_post_meta( $post_id, $legacy_key, true );
                if ( add_post_meta( $post_id, $current_key, $legacy_value, true ) ) {
                    ++$migrated;
                }
            }

            ++$page;
        } while ( count( $post_ids ) === 100 );
    }

    return $migrated;
}

/**
 * Load the WordPress plugin administration API when needed.
 */
function saifr_load_plugin_admin_api(): void {
    if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'deactivate_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
}

/**
 * Find installed copies of the plugin that used the old identity.
 *
 * @return string[]
 */
function saifr_find_legacy_plugins(): array {
    saifr_load_plugin_admin_api();

    $current_plugin = plugin_basename( SAIFR_PLUGIN_FILE );
    $legacy_plugins = [];
    foreach ( get_plugins() as $plugin_file => $plugin_data ) {
        if ( $plugin_file === $current_plugin || strtolower( basename( $plugin_file ) ) !== 'ai-friendly.php' ) {
            continue;
        }

        $name        = strtolower( trim( (string) ( $plugin_data['Name'] ?? '' ) ) );
        $text_domain = strtolower( trim( (string) ( $plugin_data['TextDomain'] ?? '' ) ) );
        if ( $name === 'ai friendly' || $text_domain === 'ai-friendly' ) {
            $legacy_plugins[] = $plugin_file;
        }
    }

    return $legacy_plugins;
}

/**
 * Deactivate the old plugin after its data has been copied successfully.
 *
 * Network-active copies are changed only when the new plugin is being
 * network-activated too.
 *
 * @return array{found: int, deactivated: int, remaining_active: int}
 */
function saifr_deactivate_legacy_plugins( bool $network_wide = false ): array {
    saifr_load_plugin_admin_api();

    $result = [
        'found'            => 0,
        'deactivated'      => 0,
        'remaining_active' => 0,
    ];
    $legacy_plugins = saifr_find_legacy_plugins();
    $result['found'] = count( $legacy_plugins );
    $current_network_active = is_multisite() && is_plugin_active_for_network( plugin_basename( SAIFR_PLUGIN_FILE ) );

    foreach ( $legacy_plugins as $plugin_file ) {
        $was_network_active = is_multisite() && is_plugin_active_for_network( $plugin_file );
        $was_site_active    = ! $was_network_active && is_plugin_active( $plugin_file );

        if ( $was_network_active && ( $network_wide || $current_network_active ) && current_user_can( 'manage_network_plugins' ) ) {
            deactivate_plugins( $plugin_file, false, true );
        } elseif ( $was_site_active && current_user_can( 'activate_plugins' ) ) {
            deactivate_plugins( $plugin_file, false, false );
        }

        $is_still_active = is_plugin_active( $plugin_file ) || ( is_multisite() && is_plugin_active_for_network( $plugin_file ) );
        if ( $is_still_active ) {
            ++$result['remaining_active'];
        } elseif ( $was_network_active || $was_site_active ) {
            ++$result['deactivated'];
        }
    }

    return $result;
}

/**
 * Run the complete legacy upgrade and retain a user-facing summary.
 *
 * @return array<string, int|string|bool>
 */
function saifr_run_legacy_upgrade( bool $network_wide = false ): array {
    $migration   = saifr_maybe_migrate_legacy_data();
    $deactivation = saifr_deactivate_legacy_plugins( $network_wide );
    $status = [
        'completed_at'            => current_time( 'mysql' ),
        'data_found'              => $migration['legacy_found'],
        'options_imported'        => $migration['options'],
        'post_meta_imported'      => $migration['post_meta'],
        'snapshots_imported'      => $migration['snapshots'],
        'legacy_plugin_found'     => $deactivation['found'],
        'legacy_deactivated'      => $deactivation['deactivated'],
        'legacy_remaining_active' => $deactivation['remaining_active'],
        'regeneration_completed'  => false,
    ];

    if ( $migration['legacy_found'] || $deactivation['found'] > 0 ) {
        update_option( 'saifr_legacy_migration_status', $status, false );
        update_option( 'saifr_legacy_migration_notice', $status, false );
    }

    return $status;
}

/**
 * Run migration version upgrades for already active installations.
 */
function saifr_admin_maybe_run_legacy_upgrade(): void {
    if ( (string) get_option( 'saifr_legacy_migration_version', '' ) !== '2' ) {
        saifr_run_legacy_upgrade();
    }
}

/**
 * Check whether a successful regeneration happened after the migration.
 */
function saifr_legacy_regeneration_is_complete( array $status ): bool {
    if ( ! empty( $status['regeneration_completed'] ) ) {
        return true;
    }

    $last_regeneration = get_option( 'saifr_last_regeneration', [] );
    if ( ! is_array( $last_regeneration ) || empty( $last_regeneration['time'] ) || ! empty( $last_regeneration['stats']['errors'] ) ) {
        return false;
    }

    $migration_time    = strtotime( (string) ( $status['completed_at'] ?? '' ) );
    $regeneration_time = strtotime( (string) $last_regeneration['time'] );
    return $migration_time !== false && $regeneration_time !== false && $regeneration_time >= $migration_time;
}

/**
 * Remove the transition summary after its two final tasks are complete.
 */
function saifr_maybe_complete_legacy_transition(): bool {
    $status = get_option( 'saifr_legacy_migration_status', [] );
    if ( ! is_array( $status ) || empty( $status ) ) {
        return false;
    }

    $regeneration_complete = saifr_legacy_regeneration_is_complete( $status );
    $legacy_plugin_removed = empty( saifr_find_legacy_plugins() );
    if ( $regeneration_complete && $legacy_plugin_removed ) {
        delete_option( 'saifr_legacy_migration_status' );
        delete_option( 'saifr_legacy_migration_notice' );
        return true;
    }

    if ( $regeneration_complete && empty( $status['regeneration_completed'] ) ) {
        $status['regeneration_completed'] = true;
        update_option( 'saifr_legacy_migration_status', $status, false );
    }

    return false;
}

/**
 * Mark a completed error-free regeneration in the transition status.
 */
function saifr_mark_legacy_regeneration_complete( array $stats ): void {
    if ( ! empty( $stats['errors'] ) ) {
        return;
    }

    $status = get_option( 'saifr_legacy_migration_status', [] );
    if ( ! is_array( $status ) || empty( $status ) ) {
        return;
    }

    $status['regeneration_completed'] = true;
    $status['regenerated_at']         = current_time( 'mysql' );
    update_option( 'saifr_legacy_migration_status', $status, false );
    saifr_maybe_complete_legacy_transition();
}

/**
 * Confirm the completed transition once in the standard WordPress notices.
 */
function saifr_show_legacy_migration_notice(): void {
    $status = get_option( 'saifr_legacy_migration_notice', [] );
    if ( ! current_user_can( 'manage_options' ) || ! is_array( $status ) || empty( $status ) ) {
        return;
    }

    echo '<div class="notice notice-success is-dismissible"><p><strong>'
        . esc_html__( 'Passaggio a Sernicola Labs AI Friendly completato.', 'sernicola-labs-ai-friendly' )
        . '</strong> ';
    if ( ! empty( $status['legacy_deactivated'] ) ) {
        esc_html_e( 'I dati sono stati importati e il vecchio plugin è stato disattivato automaticamente.', 'sernicola-labs-ai-friendly' );
    } elseif ( ! empty( $status['legacy_remaining_active'] ) ) {
        esc_html_e( 'I dati sono stati importati, ma il vecchio plugin richiede la disattivazione manuale.', 'sernicola-labs-ai-friendly' );
    } else {
        esc_html_e( 'I dati della versione precedente sono disponibili nel nuovo plugin.', 'sernicola-labs-ai-friendly' );
    }
    echo ' ';
    esc_html_e( 'Il riepilogo nella Content Hub scomparirà automaticamente dopo una rigenerazione riuscita e la rimozione manuale del vecchio plugin.', 'sernicola-labs-ai-friendly' );
    echo '</p></div>';
    delete_option( 'saifr_legacy_migration_notice' );
}

/**
 * Render a persistent transition summary in the Content Hub overview.
 */
function saifr_render_legacy_migration_status(): void {
    if ( saifr_maybe_complete_legacy_transition() ) {
        return;
    }

    $status = get_option( 'saifr_legacy_migration_status', [] );
    if ( ! is_array( $status ) || empty( $status ) ) {
        return;
    }

    $legacy_plugins = saifr_find_legacy_plugins();
    $active_legacy  = array_filter(
        $legacy_plugins,
        static fn( string $plugin_file ): bool => is_plugin_active( $plugin_file ) || ( is_multisite() && is_plugin_active_for_network( $plugin_file ) )
    );
    $regeneration_complete = saifr_legacy_regeneration_is_complete( $status );
    ?>
    <section class="saifr-migration-panel" aria-labelledby="saifr-migration-title">
        <div class="saifr-migration-intro">
            <p class="saifr-eyebrow"><?php esc_html_e( 'Passaggio dalla versione precedente', 'sernicola-labs-ai-friendly' ); ?></p>
            <h3 id="saifr-migration-title"><?php esc_html_e( 'Migrazione completata', 'sernicola-labs-ai-friendly' ); ?></h3>
            <p><?php esc_html_e( 'Questo riepilogo scomparirà automaticamente dopo una nuova rigenerazione completata senza errori e la rimozione del vecchio plugin.', 'sernicola-labs-ai-friendly' ); ?></p>
        </div>
        <div class="saifr-migration-steps">
            <div>
                <span>01</span>
                <strong><?php esc_html_e( 'Dati', 'sernicola-labs-ai-friendly' ); ?></strong>
                <small class="saifr-migration-state is-done"><?php esc_html_e( 'Completato', 'sernicola-labs-ai-friendly' ); ?></small>
                <p><?php esc_html_e( 'Impostazioni, esclusioni, Schema personalizzati e snapshot disponibili sono stati collegati alla nuova versione.', 'sernicola-labs-ai-friendly' ); ?></p>
            </div>
            <div>
                <span>02</span>
                <strong><?php esc_html_e( 'Rigenerazione', 'sernicola-labs-ai-friendly' ); ?></strong>
                <small class="saifr-migration-state <?php echo $regeneration_complete ? 'is-done' : 'is-pending'; ?>">
                    <?php echo $regeneration_complete ? esc_html__( 'Completata', 'sernicola-labs-ai-friendly' ) : esc_html__( 'Da completare', 'sernicola-labs-ai-friendly' ); ?>
                </small>
                <p><?php echo $regeneration_complete ? esc_html__( 'La nuova generazione è terminata senza errori.', 'sernicola-labs-ai-friendly' ) : esc_html__( 'Forza una nuova rigenerazione dei contenuti dalla sezione Automation.', 'sernicola-labs-ai-friendly' ); ?></p>
            </div>
            <div>
                <span>03</span>
                <strong><?php esc_html_e( 'Vecchio plugin', 'sernicola-labs-ai-friendly' ); ?></strong>
                <?php if ( empty( $legacy_plugins ) ) : ?>
                    <small class="saifr-migration-state is-done"><?php esc_html_e( 'Rimosso', 'sernicola-labs-ai-friendly' ); ?></small>
                    <p><?php esc_html_e( 'Il vecchio plugin non è più installato.', 'sernicola-labs-ai-friendly' ); ?></p>
                <?php elseif ( ! empty( $active_legacy ) ) : ?>
                    <small class="saifr-migration-state is-pending"><?php esc_html_e( 'Richiede intervento', 'sernicola-labs-ai-friendly' ); ?></small>
                    <p><?php esc_html_e( 'Il vecchio plugin è ancora attivo e deve essere disattivato manualmente.', 'sernicola-labs-ai-friendly' ); ?></p>
                <?php else : ?>
                    <small class="saifr-migration-state is-pending"><?php esc_html_e( 'Da rimuovere', 'sernicola-labs-ai-friendly' ); ?></small>
                    <p><?php esc_html_e( 'Il vecchio plugin è disattivato. Puoi eliminarlo dopo aver verificato i dati.', 'sernicola-labs-ai-friendly' ); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="saifr-migration-actions">
            <button type="button" class="button button-primary" data-section-jump="automation"><?php esc_html_e( 'Vai alla rigenerazione', 'sernicola-labs-ai-friendly' ); ?></button>
            <a class="button" href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'Apri i plugin installati', 'sernicola-labs-ai-friendly' ); ?></a>
        </div>
    </section>
    <?php
}

add_action( 'admin_init', 'saifr_admin_maybe_run_legacy_upgrade', 1 );
add_action( 'admin_init', 'saifr_maybe_complete_legacy_transition', 20 );
add_action( 'admin_notices', 'saifr_show_legacy_migration_notice' );
add_action( 'saifr_regeneration_completed', 'saifr_mark_legacy_regeneration_complete' );
