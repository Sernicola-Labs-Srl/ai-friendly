<?php
/**
 * Remove data created by AI Friendly when the plugin is deleted.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

function saifr_uninstall_site_data(): void {
    global $wpdb;

    $history = get_option( 'saifr_llms_history_index', [] );
    if ( is_array( $history ) ) {
        foreach ( $history as $entry ) {
            $id = is_array( $entry ) ? (string) ( $entry['id'] ?? '' ) : '';
            if ( $id !== '' ) {
                delete_option( 'saifr_llms_snapshot_' . md5( $id ) );
            }
        }
    }

    // Also remove snapshot options left behind by an incomplete or legacy index.
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup must discover dynamically named plugin options.
    $snapshot_options = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like( 'saifr_llms_snapshot_' ) . '%'
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
            $wpdb->esc_like( '_transient_saifr_' ) . '%'
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
            'saifr_options',
            'saifr_onboarding_done',
            'saifr_ui_version',
            'saifr_event_log',
            'saifr_last_regeneration',
            'saifr_regeneration_cursor',
            'saifr_llms_history_index',
            'saifr_faq_cache_version',
            'saifr_legacy_migration_version',
            'saifr_legacy_migration_notice',
            'saifr_legacy_migration_status',
        ] as $option
    ) {
        delete_option( $option );
    }

    delete_metadata( 'post', 0, '_saifr_exclude', '', true );
    delete_metadata( 'post', 0, '_saifr_schema', '', true );
    delete_metadata( 'post', 0, '_saifr_md_cache_key', '', true );
    delete_metadata( 'post', 0, '_saifr_md_checksum', '', true );
    delete_metadata( 'post', 0, '_saifr_md_generated', '', true );
    delete_metadata( 'post', 0, '_saifr_md_filename', '', true );
    wp_clear_scheduled_hook( 'saifr_cron_regenerate' );

    $uploads      = wp_upload_dir();
    $storage_root = trailingslashit( (string) $uploads['basedir'] ) . 'sernicola-labs-ai-friendly';
    $versions_dir = trailingslashit( $storage_root ) . 'versions';
    $files        = glob( trailingslashit( $versions_dir ) . '*' );

    if ( is_array( $files ) ) {
        foreach ( $files as $file ) {
            if ( is_file( $file ) ) {
                wp_delete_file( $file );
            }
        }
    }

    $htaccess = trailingslashit( $versions_dir ) . '.htaccess';
    if ( is_file( $htaccess ) ) {
        wp_delete_file( $htaccess );
    }

    foreach ( [ $versions_dir, $storage_root ] as $directory ) {
        if ( is_dir( $directory ) ) {
            rmdir( $directory ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Exact plugin-owned directory, removed only when empty.
        }
    }
}

if ( is_multisite() ) {
    foreach ( get_sites( [ 'fields' => 'ids', 'number' => 0 ] ) as $saifr_site_id ) {
        switch_to_blog( (int) $saifr_site_id );
        saifr_uninstall_site_data();
        restore_current_blog();
    }
} else {
    saifr_uninstall_site_data();
}
