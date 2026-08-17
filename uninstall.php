<?php
/**
 * Remove data created by AI Friendly when the plugin is deleted.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

function ai_fr_uninstall_site_data(): void {
    global $wpdb;

    $history = get_option( 'ai_fr_llms_history_index', [] );
    if ( is_array( $history ) ) {
        foreach ( $history as $entry ) {
            $id = is_array( $entry ) ? (string) ( $entry['id'] ?? '' ) : '';
            if ( $id !== '' ) {
                delete_option( 'ai_fr_llms_snapshot_' . md5( $id ) );
            }
        }
    }

    // Also remove snapshot options left behind by an incomplete or legacy index.
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup must discover dynamically named plugin options.
    $snapshot_options = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like( 'ai_fr_llms_snapshot_' ) . '%'
        )
    );
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    foreach ( $snapshot_options as $snapshot_option ) {
        delete_option( (string) $snapshot_option );
    }

    // Remove dynamically named Markdown, FAQ, and admin-notice transients.
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup must discover dynamically named plugin transients.
    $transient_options = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like( '_transient_ai_fr_' ) . '%'
        )
    );
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    foreach ( $transient_options as $transient_option ) {
        $transient_name = substr( (string) $transient_option, strlen( '_transient_' ) );
        if ( $transient_name !== '' ) {
            delete_transient( $transient_name );
        }
    }

    foreach (
        [
            'ai_fr_options',
            'ai_fr_onboarding_done',
            'ai_fr_ui_version',
            'ai_fr_event_log',
            'ai_fr_last_regeneration',
            'ai_fr_regeneration_cursor',
            'ai_fr_llms_history_index',
            'ai_fr_llms_storage_version',
            'ai_fr_faq_cache_version',
        ] as $option
    ) {
        delete_option( $option );
    }

    delete_metadata( 'post', 0, '_ai_fr_exclude', '', true );
    delete_metadata( 'post', 0, '_ai_fr_schema', '', true );
    delete_metadata( 'post', 0, '_ai_fr_md_cache_key', '', true );
    delete_metadata( 'post', 0, '_ai_fr_md_checksum', '', true );
    delete_metadata( 'post', 0, '_ai_fr_md_generated', '', true );
    delete_metadata( 'post', 0, '_ai_fr_md_filename', '', true );
    wp_clear_scheduled_hook( 'ai_fr_cron_regenerate' );
}

if ( is_multisite() ) {
    foreach ( get_sites( [ 'fields' => 'ids', 'number' => 0 ] ) as $site_id ) {
        switch_to_blog( (int) $site_id );
        ai_fr_uninstall_site_data();
        restore_current_blog();
    }
} else {
    ai_fr_uninstall_site_data();
}

$storage_root = WP_CONTENT_DIR . '/uploads/ai-friendly';
$patterns = [
    $storage_root . '/versions/*',
    $storage_root . '/llms-history/*',
];
foreach ( $patterns as $pattern ) {
    $files = glob( $pattern );
    if ( ! is_array( $files ) ) {
        continue;
    }
    foreach ( $files as $file ) {
        if ( is_file( $file ) ) {
            wp_delete_file( $file );
        }
    }
}

foreach ( [ $storage_root . '/versions', $storage_root . '/llms-history', $storage_root ] as $directory ) {
    if ( is_dir( $directory ) ) {
        rmdir( $directory ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Exact plugin-owned directory, removed only when empty.
    }
}
