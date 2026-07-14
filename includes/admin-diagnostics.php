<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Esegue controlli rapidi per la dashboard.
 */
function ai_fr_run_diagnostics(): array {
    $options = wp_parse_args( get_option( 'ai_fr_options', [] ), ai_fr_get_default_options() );
    $filter  = new AiFrContentFilter();

    $warnings = [];
    $errors   = [];

    $enabled_types = $filter->getEnabledPostTypes();
    if ( empty( $enabled_types ) ) {
        $warnings[] = [
            'code'    => 'no_post_types_enabled',
            'message' => 'Nessun tipo di contenuto abilitato.',
        ];
    }

    $included_count = 0;
    if ( ! empty( $enabled_types ) ) {
        $posts = get_posts(
            [
                'post_type'      => $enabled_types,
                'post_status'    => 'publish',
                'posts_per_page' => 200,
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ]
        );

        foreach ( $posts as $post_id ) {
            $post = get_post( $post_id );
            if ( $post && $filter->shouldInclude( $post ) ) {
                $included_count++;
            }
        }
    }

    if ( $included_count === 0 ) {
        $warnings[] = [
            'code'    => 'empty_scope',
            'message' => 'Zero contenuti inclusi con le regole correnti.',
        ];
    }

    $last_regen = get_option( 'ai_fr_last_regeneration', [] );
    if ( ! empty( $last_regen['stats']['errors'] ) ) {
        $warnings[] = [
            'code'    => 'last_regen_errors',
            'message' => 'Ultima rigenerazione con errori: ' . intval( $last_regen['stats']['errors'] ),
        ];
    }

    if ( empty( $options['auto_regenerate'] ) || empty( $options['static_md_files'] ) ) {
        $warnings[] = [
            'code'    => 'cron_disabled',
            'message' => 'Rigenerazione automatica non attiva (cron o file statici disabilitati).',
        ];
    }

    if ( ! empty( $options['schema_enabled'] ) && function_exists( 'ai_fr_schema_detect_provider' ) ) {
        $schema_provider = ai_fr_schema_detect_provider();
        $schema_mode     = function_exists( 'ai_fr_schema_output_mode' ) ? ai_fr_schema_output_mode() : 'standalone';

        if ( empty( trim( (string) ( $options['schema_name'] ?? '' ) ) ) ) {
            $warnings[] = [
                'code'    => 'schema_missing_name',
                'message' => 'Semantic Schema attivo: nome entita non impostato, verra usato il nome del sito.',
            ];
        }

        if ( empty( trim( (string) ( $options['schema_same_as'] ?? '' ) ) ) ) {
            $warnings[] = [
                'code'    => 'schema_missing_same_as',
                'message' => 'Semantic Schema attivo: aggiungi profili sameAs per migliorare la disambiguazione.',
            ];
        }

        if ( ( $options['schema_mode'] ?? 'auto' ) !== 'auto' && $schema_mode === 'standalone' && $schema_provider !== 'none' ) {
            $warnings[] = [
                'code'    => 'schema_mode_fallback',
                'message' => 'Semantic Schema usa standalone perche la modalita scelta non corrisponde al provider SEO rilevato.',
            ];
        }

        $offer_catalog = trim( (string) ( $options['schema_offer_catalog'] ?? '' ) );
        $schema_services = isset( $options['schema_services'] ) && is_array( $options['schema_services'] ) ? $options['schema_services'] : [];
        if ( empty( $schema_services ) && $offer_catalog !== '' && ! is_array( json_decode( $offer_catalog, true ) ) ) {
            $warnings[] = [
                'code'    => 'schema_offer_catalog_invalid',
                'message' => 'Semantic Schema: il catalogo servizi legacy non contiene JSON valido e non verra aggiunto al grafo.',
            ];
        }
    }

    if ( function_exists( 'ai_fr_updater_get_latest_release' ) ) {
        $release = ai_fr_updater_get_latest_release();
        if ( empty( $release ) ) {
            $warnings[] = [
                'code'    => 'updater_release_unavailable',
                'message' => 'Updater: impossibile leggere la release GitHub più recente.',
            ];
        } elseif ( empty( $release['package'] ) ) {
            $warnings[] = [
                'code'    => 'updater_package_missing',
                'message' => 'Updater: la release GitHub non contiene un asset ZIP installabile.',
            ];
        }
    }

    // Warning URL esclusioni duplicate.
    $patterns = array_filter( array_map( 'trim', explode( "\n", (string) ( $options['exclude_url_patterns'] ?? '' ) ) ) );
    if ( count( $patterns ) !== count( array_unique( $patterns ) ) ) {
        $warnings[] = [
            'code'    => 'duplicate_patterns',
            'message' => 'Sono presenti pattern URL duplicati nelle esclusioni.',
        ];
    }

    $sitemap_url = home_url( '/sitemap.xml' );
    $robots_url  = home_url( '/robots.txt' );
    $blog_public = get_option( 'blog_public', '1' );
    if ( $blog_public !== '1' ) {
        $warnings[] = [
            'code'    => 'discourage_search',
            'message' => 'Il sito scoraggia l\'indicizzazione (Impostazioni > Lettura).',
        ];
    }

    $robots_txt = (string) apply_filters( 'robots_txt', '', ( $blog_public === '1' ) );
    if ( stripos( $robots_txt, 'Disallow: /' ) !== false ) {
        $warnings[] = [
            'code'    => 'robots_disallow_all',
            'message' => 'robots.txt sembra bloccare tutto il sito (Disallow: /).',
        ];
    }

    return [
        'warnings'       => $warnings,
        'errors'         => $errors,
        'included_count' => $included_count,
        'sitemap_robots' => [
            'sitemap_url' => $sitemap_url,
            'robots_url'  => $robots_url,
            'blog_public' => $blog_public === '1',
        ],
    ];
}
