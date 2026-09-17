<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Statistiche aggregate per overview dashboard.
 */
function saifr_get_overview_stats(): array {
    $options      = wp_parse_args( get_option( 'saifr_options', [] ), saifr_get_default_options() );
    $llms_content = saifr_build_llms_txt();
    $versioning   = SaifrVersioning::getStats();
    $last_regen   = get_option( 'saifr_last_regeneration', [] );
    $next_cron    = wp_next_scheduled( 'saifr_cron_regenerate' );
    $diagnostics  = saifr_run_diagnostics();

    return [
        'llms'       => [
            'url'             => home_url( '/llms.txt' ),
            'chars'           => strlen( $llms_content ),
            'lines'           => substr_count( $llms_content, "\n" ) + 1,
            'last_regen_time' => $last_regen['time'] ?? '',
            'valid'           => trim( $llms_content ) !== '',
        ],
        'markdown'   => [
            'static_enabled' => ! empty( $options['static_md_files'] ),
            'count'          => intval( $versioning['count'] ?? 0 ),
            'size'           => intval( $versioning['size'] ?? 0 ),
        ],
        'automation' => [
            'cron_enabled' => ! empty( $options['auto_regenerate'] ),
            'next_cron'    => $next_cron ? date_i18n( 'Y-m-d H:i:s', $next_cron ) : '',
            'last_stats'   => $last_regen['stats'] ?? [],
        ],
        'diagnostics' => $diagnostics,
    ];
}
