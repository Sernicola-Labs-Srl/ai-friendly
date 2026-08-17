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
            'message' => __( 'Nessun tipo di contenuto abilitato.', 'ai-friendly' ),
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
            'message' => __( 'Zero contenuti inclusi con le regole correnti.', 'ai-friendly' ),
        ];
    }

    $last_regen = get_option( 'ai_fr_last_regeneration', [] );
    if ( ! empty( $last_regen['stats']['errors'] ) ) {
        $warnings[] = [
            'code'    => 'last_regen_errors',
            'message' => sprintf(
                /* translators: %d: regeneration error count. */
                __( 'Ultima rigenerazione con errori: %d', 'ai-friendly' ),
                intval( $last_regen['stats']['errors'] )
            ),
        ];
    }

    if ( empty( $options['auto_regenerate'] ) || empty( $options['static_md_files'] ) ) {
        $warnings[] = [
            'code'    => 'cron_disabled',
            'message' => __( 'Rigenerazione automatica non attiva (cron o file statici disabilitati).', 'ai-friendly' ),
        ];
    }

    if ( ! empty( $options['schema_enabled'] ) && function_exists( 'ai_fr_schema_detect_provider' ) ) {
        $schema_provider = ai_fr_schema_detect_provider();
        $schema_mode     = function_exists( 'ai_fr_schema_output_mode' ) ? ai_fr_schema_output_mode() : 'standalone';

        if ( empty( trim( (string) ( $options['schema_name'] ?? '' ) ) ) ) {
            $warnings[] = [
                'code'    => 'schema_missing_name',
                'message' => __( 'Semantic Schema attivo: nome entità non impostato, verrà usato il nome del sito.', 'ai-friendly' ),
            ];
        }

        if ( empty( trim( (string) ( $options['schema_same_as'] ?? '' ) ) ) ) {
            $warnings[] = [
                'code'    => 'schema_missing_same_as',
                'message' => __( 'Semantic Schema attivo: aggiungi profili sameAs per migliorare la disambiguazione.', 'ai-friendly' ),
            ];
        }

        if ( ( $options['schema_mode'] ?? 'auto' ) !== 'auto' && $schema_mode === 'standalone' && $schema_provider !== 'none' ) {
            $warnings[] = [
                'code'    => 'schema_mode_fallback',
                'message' => __( 'Semantic Schema usa standalone perché la modalità scelta non corrisponde al provider SEO rilevato.', 'ai-friendly' ),
            ];
        }

        $offer_catalog = trim( (string) ( $options['schema_offer_catalog'] ?? '' ) );
        $schema_services = isset( $options['schema_services'] ) && is_array( $options['schema_services'] ) ? $options['schema_services'] : [];
        if ( empty( $schema_services ) && $offer_catalog !== '' && ! is_array( json_decode( $offer_catalog, true ) ) ) {
            $warnings[] = [
                'code'    => 'schema_offer_catalog_invalid',
                'message' => __( 'Semantic Schema: il catalogo servizi legacy non contiene JSON valido e non verrà aggiunto al grafo.', 'ai-friendly' ),
            ];
        }

        $offer_sources = isset( $options['schema_offer_sources'] ) && is_array( $options['schema_offer_sources'] ) ? $options['schema_offer_sources'] : [];
        $unresolved_sources = [];
        foreach ( $offer_sources as $source ) {
            if ( function_exists( 'ai_fr_schema_resolve_offer_source' ) && empty( ai_fr_schema_resolve_offer_source( (string) $source ) ) ) {
                $unresolved_sources[] = (string) $source;
            }
        }
        if ( ! empty( $unresolved_sources ) ) {
            $warnings[] = [
                'code'    => 'schema_offer_sources_unresolved',
                'message' => sprintf(
                    /* translators: %s: comma-separated unresolved sources. */
                    __( 'Semantic Schema: sorgenti OfferCatalog non risolte: %s', 'ai-friendly' ),
                    implode( ', ', array_slice( $unresolved_sources, 0, 3 ) )
                ),
            ];
        }
    }

    // Warning URL esclusioni duplicate.
    $patterns = array_filter( array_map( 'trim', explode( "\n", (string) ( $options['exclude_url_patterns'] ?? '' ) ) ) );
    if ( count( $patterns ) !== count( array_unique( $patterns ) ) ) {
        $warnings[] = [
            'code'    => 'duplicate_patterns',
            'message' => __( 'Sono presenti pattern URL duplicati nelle esclusioni.', 'ai-friendly' ),
        ];
    }

    $sitemap_url = home_url( '/sitemap.xml' );
    $robots_url  = home_url( '/robots.txt' );
    $blog_public = get_option( 'blog_public', '1' );
    if ( $blog_public !== '1' ) {
        $warnings[] = [
            'code'    => 'discourage_search',
            'message' => __( 'Il sito scoraggia l\'indicizzazione (Impostazioni > Lettura).', 'ai-friendly' ),
        ];
    }

    $robots_txt = (string) apply_filters( 'robots_txt', '', ( $blog_public === '1' ) );
    if ( stripos( $robots_txt, 'Disallow: /' ) !== false ) {
        $warnings[] = [
            'code'    => 'robots_disallow_all',
            'message' => __( 'robots.txt sembra bloccare tutto il sito (Disallow: /).', 'ai-friendly' ),
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
