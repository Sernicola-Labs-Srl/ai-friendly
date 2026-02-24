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
            'severity'=> 'warning',
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
            'severity'=> 'warning',
            'message' => 'Zero contenuti inclusi con le regole correnti.',
        ];
    }

    $last_regen = get_option( 'ai_fr_last_regeneration', [] );
    if ( ! empty( $last_regen['stats']['errors'] ) ) {
        $warnings[] = [
            'code'    => 'last_regen_errors',
            'severity'=> 'warning',
            'message' => 'Ultima rigenerazione con errori: ' . intval( $last_regen['stats']['errors'] ),
        ];
    }

    if ( empty( $options['auto_regenerate'] ) || empty( $options['static_md_files'] ) ) {
        $warnings[] = [
            'code'    => 'cron_disabled',
            'severity'=> 'info',
            'message' => 'Rigenerazione automatica non attiva (cron o file statici disabilitati).',
        ];
    }

    // Warning URL esclusioni duplicate.
    $patterns = array_filter( array_map( 'trim', explode( "\n", (string) ( $options['exclude_url_patterns'] ?? '' ) ) ) );
    if ( count( $patterns ) !== count( array_unique( $patterns ) ) ) {
        $warnings[] = [
            'code'    => 'duplicate_patterns',
            'severity'=> 'warning',
            'message' => 'Sono presenti pattern URL duplicati nelle esclusioni.',
        ];
    }

    $sitemap_url = home_url( '/sitemap.xml' );
    $robots_url  = home_url( '/robots.txt' );
    $blog_public = get_option( 'blog_public', '1' );
    if ( $blog_public !== '1' ) {
        $warnings[] = [
            'code'    => 'discourage_search',
            'severity'=> 'warning',
            'message' => 'Il sito scoraggia l\'indicizzazione (Impostazioni > Lettura).',
        ];
    }

    $robots_txt = (string) apply_filters( 'robots_txt', '', ( $blog_public === '1' ) );
    if ( ai_fr_robots_disallow_all_for_generic_user_agent( $robots_txt ) ) {
        $warnings[] = [
            'code'    => 'robots_disallow_all',
            'severity'=> 'critical',
            'message' => 'robots.txt sembra bloccare tutto il sito (Disallow: /).',
        ];
    } else {
        $restrictive_rules = ai_fr_robots_generic_disallow_rules( $robots_txt );
        if ( ! empty( $restrictive_rules ) ) {
            $preview = implode( ', ', array_slice( $restrictive_rules, 0, 3 ) );
            if ( count( $restrictive_rules ) > 3 ) {
                $preview .= '...';
            }
            $warnings[] = [
                'code'    => 'robots_restrictive_paths',
                'severity'=> 'info',
                'message' => 'robots.txt contiene restrizioni per User-agent:* (non blocco totale): ' . $preview,
            ];
        }
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

/**
 * Rileva un blocco globale reale nel robots.txt per User-agent: *
 * evitando falsi positivi su path tipo /wp-admin/.
 */
function ai_fr_robots_disallow_all_for_generic_user_agent( string $robots_txt ): bool {
    if ( trim( $robots_txt ) === '' ) {
        return false;
    }

    $lines = preg_split( "/\r\n|\r|\n/", $robots_txt ) ?: [];
    $current_user_agents = [];
    $has_global_disallow = false;
    $has_global_allow_root = false;

    foreach ( $lines as $raw_line ) {
        $line = trim( preg_replace( '/\s*#.*$/', '', $raw_line ) ?? $raw_line );
        if ( $line === '' ) {
            $current_user_agents = [];
            continue;
        }

        if ( ! str_contains( $line, ':' ) ) {
            continue;
        }

        [ $field, $value ] = array_map( 'trim', explode( ':', $line, 2 ) );
        $field = strtolower( $field );

        if ( $field === 'user-agent' ) {
            // Multiple consecutive User-agent lines belong to the same group.
            if ( empty( $current_user_agents ) ) {
                $current_user_agents = [];
            }
            $current_user_agents[] = strtolower( $value );
            continue;
        }

        $is_generic_group = in_array( '*', $current_user_agents, true );
        if ( ! $is_generic_group ) {
            continue;
        }

        if ( $field === 'disallow' && trim( $value ) === '/' ) {
            $has_global_disallow = true;
        }

        if ( $field === 'allow' && trim( $value ) === '/' ) {
            $has_global_allow_root = true;
        }
    }

    return $has_global_disallow && ! $has_global_allow_root;
}

/**
 * Restituisce i disallow "significativi" per User-agent:* (esclude i path tecnici comuni).
 *
 * @return string[]
 */
function ai_fr_robots_generic_disallow_rules( string $robots_txt ): array {
    if ( trim( $robots_txt ) === '' ) {
        return [];
    }

    $lines = preg_split( "/\r\n|\r|\n/", $robots_txt ) ?: [];
    $current_user_agents = [];
    $disallows = [];

    foreach ( $lines as $raw_line ) {
        $line = trim( preg_replace( '/\s*#.*$/', '', $raw_line ) ?? $raw_line );
        if ( $line === '' ) {
            $current_user_agents = [];
            continue;
        }

        if ( ! str_contains( $line, ':' ) ) {
            continue;
        }

        [ $field, $value ] = array_map( 'trim', explode( ':', $line, 2 ) );
        $field = strtolower( $field );

        if ( $field === 'user-agent' ) {
            $current_user_agents[] = strtolower( $value );
            continue;
        }

        if ( $field !== 'disallow' || ! in_array( '*', $current_user_agents, true ) ) {
            continue;
        }

        $path = trim( $value );
        if ( $path === '' || $path === '/' ) {
            continue;
        }

        if ( ai_fr_robots_is_common_technical_disallow( $path ) ) {
            continue;
        }

        $disallows[] = $path;
    }

    return array_values( array_unique( $disallows ) );
}

function ai_fr_robots_is_common_technical_disallow( string $path ): bool {
    $normalized = '/' . ltrim( trim( $path ), '/' );

    $common_prefixes = [
        '/wp-admin/',
        '/wp-login.php',
        '/cart/',
        '/checkout/',
        '/my-account/',
        '/cgi-bin/',
    ];

    foreach ( $common_prefixes as $prefix ) {
        if ( str_starts_with( $normalized, $prefix ) ) {
            return true;
        }
    }

    return false;
}
