<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'AI_FR_GITHUB_REPO' ) ) {
    define( 'AI_FR_GITHUB_REPO', 'Sernicola-Labs-Srl/ai-friendly' );
}

if ( ! defined( 'AI_FR_GITHUB_RELEASES_API' ) ) {
    define( 'AI_FR_GITHUB_RELEASES_API', 'https://api.github.com/repos/' . AI_FR_GITHUB_REPO . '/releases/latest' );
}

if ( ! defined( 'AI_FR_UPDATE_URI' ) ) {
    define( 'AI_FR_UPDATE_URI', 'https://github.com/' . AI_FR_GITHUB_REPO );
}

function ai_fr_updater_plugin_basename(): string {
    return plugin_basename( AI_FR_PLUGIN_FILE );
}

function ai_fr_updater_get_latest_release( bool $force = false ): array {
    $cache_key = 'ai_fr_github_latest_release';

    if ( ! $force ) {
        $cached = get_site_transient( $cache_key );
        if ( is_array( $cached ) ) {
            return $cached;
        }
    }

    $response = wp_remote_get(
        AI_FR_GITHUB_RELEASES_API,
        [
            'timeout' => 8,
            'headers' => [
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'AI-Friendly-Updater/' . AI_FR_VERSION . '; ' . home_url( '/' ),
            ],
        ]
    );

    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
        set_site_transient( $cache_key, [], HOUR_IN_SECONDS );
        return [];
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ! is_array( $body ) ) {
        set_site_transient( $cache_key, [], HOUR_IN_SECONDS );
        return [];
    }

    $release = ai_fr_updater_normalize_release( $body );
    set_site_transient( $cache_key, $release, 6 * HOUR_IN_SECONDS );

    return $release;
}

function ai_fr_updater_normalize_release( array $release ): array {
    $version = ltrim( (string) ( $release['tag_name'] ?? '' ), 'vV' );
    if ( $version === '' ) {
        return [];
    }

    $package = '';
    $assets  = isset( $release['assets'] ) && is_array( $release['assets'] ) ? $release['assets'] : [];

    foreach ( $assets as $asset ) {
        if ( ! is_array( $asset ) ) {
            continue;
        }

        $name = strtolower( (string) ( $asset['name'] ?? '' ) );
        $url  = esc_url_raw( (string) ( $asset['browser_download_url'] ?? '' ) );
        if ( $url === '' || ! str_ends_with( $name, '.zip' ) ) {
            continue;
        }

        $package = $url;
        if ( $name === 'ai-friendly.zip' ) {
            break;
        }
    }

    return [
        'version'      => $version,
        'name'         => sanitize_text_field( (string) ( $release['name'] ?? $release['tag_name'] ?? $version ) ),
        'body'         => wp_kses_post( (string) ( $release['body'] ?? '' ) ),
        'published_at' => sanitize_text_field( (string) ( $release['published_at'] ?? '' ) ),
        'html_url'     => esc_url_raw( (string) ( $release['html_url'] ?? AI_FR_UPDATE_URI . '/releases/latest' ) ),
        'package'      => $package,
    ];
}

function ai_fr_updater_build_update_payload( array $release ): ?array {
    if ( empty( $release['version'] ) || empty( $release['package'] ) ) {
        return null;
    }

    if ( ! version_compare( (string) $release['version'], AI_FR_VERSION, '>' ) ) {
        return null;
    }

    return [
        'id'           => AI_FR_UPDATE_URI,
        'slug'         => 'ai-friendly',
        'plugin'       => ai_fr_updater_plugin_basename(),
        'version'      => (string) $release['version'],
        'url'          => $release['html_url'],
        'package'      => $release['package'],
        'requires'     => '6.0',
        'requires_php' => '8.1',
        'tested'       => '6.9',
    ];
}

add_filter(
    'update_plugins_github.com',
    function ( $update, array $plugin_data, string $plugin_file, array $_locales ) {
        if ( $plugin_file !== ai_fr_updater_plugin_basename() ) {
            return $update;
        }

        $payload = ai_fr_updater_build_update_payload( ai_fr_updater_get_latest_release() );
        if ( ! is_array( $payload ) ) {
            return false;
        }

        return $payload;
    },
    10,
    4
);

add_filter(
    'plugins_api',
    function ( $result, string $action, $args ) {
        if ( $action !== 'plugin_information' || ( $args->slug ?? '' ) !== 'ai-friendly' ) {
            return $result;
        }

        $release = ai_fr_updater_get_latest_release();
        if ( empty( $release['version'] ) ) {
            return $result;
        }

        return (object) [
            'name'          => 'Sernicola Labs | AI Friendly - llms.txt & Markdown',
            'slug'          => 'ai-friendly',
            'version'       => (string) $release['version'],
            'author'        => '<a href="https://sernicola-labs.com">Sernicola Labs</a>',
            'homepage'      => AI_FR_UPDATE_URI,
            'requires'      => '6.0',
            'requires_php'  => '8.1',
            'tested'        => '6.9',
            'download_link' => (string) ( $release['package'] ?? '' ),
            'sections'      => [
                'description' => 'AI Friendly espone contenuti WordPress per sistemi AI tramite llms.txt, endpoint Markdown e Semantic Schema JSON-LD.',
                'changelog'   => $release['body'] !== '' ? $release['body'] : 'Consulta la release GitHub per i dettagli.',
            ],
            'banners'       => [],
            'icons'         => [],
        ];
    },
    10,
    3
);

add_action(
    'upgrader_process_complete',
    function (): void {
        delete_site_transient( 'ai_fr_github_latest_release' );
    }
);
