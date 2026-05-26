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
