<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function ai_fr_schema_get_options(): array {
    return wp_parse_args( get_option( 'ai_fr_options', [] ), ai_fr_get_default_options() );
}

function ai_fr_schema_is_enabled(): bool {
    $options = ai_fr_schema_get_options();
    return (bool) apply_filters( 'ai_fr_schema_enabled', ! empty( $options['schema_enabled'] ), $options );
}

function ai_fr_schema_detect_provider(): string {
    if ( defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' ) ) {
        return 'yoast';
    }

    if ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ) ) {
        return 'rank_math';
    }

    return 'none';
}

function ai_fr_schema_output_mode(): string {
    $options  = ai_fr_schema_get_options();
    $mode     = sanitize_key( (string) ( $options['schema_mode'] ?? 'auto' ) );
    $provider = ai_fr_schema_detect_provider();

    if ( ! in_array( $mode, [ 'auto', 'standalone', 'extend_yoast', 'extend_rank_math' ], true ) ) {
        $mode = 'auto';
    }

    if ( $mode === 'auto' ) {
        if ( $provider === 'yoast' ) {
            return 'extend_yoast';
        }
        if ( $provider === 'rank_math' ) {
            return 'extend_rank_math';
        }
        return 'standalone';
    }

    if ( $mode === 'extend_yoast' && $provider !== 'yoast' ) {
        return 'standalone';
    }

    if ( $mode === 'extend_rank_math' && $provider !== 'rank_math' ) {
        return 'standalone';
    }

    return $mode;
}

function ai_fr_schema_split_lines( string $value ): array {
    $items = preg_split( '/\r\n|\r|\n|,/', $value );
    if ( ! is_array( $items ) ) {
        return [];
    }

    $items = array_map( 'trim', $items );
    $items = array_filter( $items, static fn( string $item ): bool => $item !== '' );
    return array_values( array_unique( $items ) );
}

function ai_fr_schema_dedupe_urls( array $urls ): array {
    $unique = [];
    $seen   = [];

    foreach ( $urls as $url ) {
        $url = esc_url_raw( trim( (string) $url ) );
        if ( $url === '' ) {
            continue;
        }

        $fingerprint = untrailingslashit( strtolower( $url ) );
        if ( isset( $seen[ $fingerprint ] ) ) {
            continue;
        }

        $seen[ $fingerprint ] = true;
        $unique[] = $url;
    }

    return $unique;
}

function ai_fr_schema_home_id( string $fragment ): string {
    return trailingslashit( home_url( '/' ) ) . '#' . ltrim( sanitize_title( $fragment ), '#' );
}

function ai_fr_schema_get_identity_node(): array {
    $options = ai_fr_schema_get_options();

    $type = (string) ( $options['schema_entity_type'] ?? 'Person' );
    if ( ! in_array( $type, [ 'Person', 'Organization' ], true ) ) {
        $type = 'Person';
    }

    $name = trim( (string) ( $options['schema_name'] ?? '' ) );
    if ( $name === '' ) {
        $name = get_bloginfo( 'name' );
    }

    $node = [
        '@type' => $type,
        '@id'   => ai_fr_schema_home_id( strtolower( $type ) ),
        'url'   => home_url( '/' ),
        'name'  => $name,
    ];

    $simple_fields = [
        'alternateName'             => 'schema_alternate_name',
        'description'               => 'schema_description',
        'disambiguatingDescription' => 'schema_disambiguating_description',
    ];

    if ( $type === 'Person' ) {
        $simple_fields['jobTitle'] = 'schema_job_title';
    }

    foreach ( $simple_fields as $schema_key => $option_key ) {
        $value = trim( (string) ( $options[ $option_key ] ?? '' ) );
        if ( $value !== '' ) {
            $node[ $schema_key ] = $value;
        }
    }

    $same_as = ai_fr_schema_split_lines( (string) ( $options['schema_same_as'] ?? '' ) );
    $same_as = ai_fr_schema_dedupe_urls( $same_as );
    if ( ! empty( $same_as ) ) {
        $node['sameAs'] = $same_as;
    }

    $knows_about = ai_fr_schema_split_lines( (string) ( $options['schema_knows_about'] ?? '' ) );
    if ( ! empty( $knows_about ) ) {
        $node['knowsAbout'] = $knows_about;
    }

    $knows_language = ai_fr_schema_split_lines( (string) ( $options['schema_knows_language'] ?? '' ) );
    if ( ! empty( $knows_language ) ) {
        $node['knowsLanguage'] = $knows_language;
    }

    $image_id = intval( $options['schema_image_id'] ?? 0 );
    if ( $image_id > 0 ) {
        $image = wp_get_attachment_image_src( $image_id, 'full' );
        if ( is_array( $image ) && ! empty( $image[0] ) ) {
            $node['image'] = [
                '@type' => 'ImageObject',
                '@id'   => ai_fr_schema_home_id( strtolower( $type ) . '-image' ),
                'url'   => esc_url_raw( $image[0] ),
            ];
            if ( ! empty( $image[1] ) ) {
                $node['image']['width'] = intval( $image[1] );
            }
            if ( ! empty( $image[2] ) ) {
                $node['image']['height'] = intval( $image[2] );
            }
        }
    }

    return (array) apply_filters( 'ai_fr_schema_identity', $node, $options );
}

function ai_fr_schema_get_profile_page_node(): array {
    $options         = ai_fr_schema_get_options();
    $profile_page_id = intval( $options['schema_profile_page_id'] ?? 0 );
    if ( $profile_page_id <= 0 || ! is_page( $profile_page_id ) ) {
        return [];
    }

    $post = get_post( $profile_page_id );
    if ( ! $post instanceof WP_Post ) {
        return [];
    }

    $url = get_permalink( $profile_page_id );
    if ( ! is_string( $url ) || $url === '' ) {
        return [];
    }

    return [
        '@type'        => 'ProfilePage',
        '@id'          => trailingslashit( $url ) . '#profilepage',
        'url'          => $url,
        'name'         => get_the_title( $profile_page_id ),
        'inLanguage'   => get_locale(),
        'dateCreated'  => get_post_time( DATE_W3C, true, $post ),
        'dateModified' => get_post_modified_time( DATE_W3C, true, $post ),
        'isPartOf'     => [ '@id' => ai_fr_schema_home_id( 'website' ) ],
        'mainEntity'   => [ '@id' => ai_fr_schema_get_identity_node()['@id'] ],
    ];
}

function ai_fr_schema_get_blog_node(): array {
    if ( ! is_home() ) {
        return [];
    }

    $options = ai_fr_schema_get_options();
    $license = trim( (string) ( $options['schema_license'] ?? '' ) );
    $node = [
        '@type'      => 'Blog',
        '@id'        => trailingslashit( get_post_type_archive_link( 'post' ) ?: home_url( '/' ) ) . '#blog',
        'name'       => get_bloginfo( 'name' ) . ' Blog',
        'inLanguage' => get_locale(),
        'isPartOf'   => [ '@id' => ai_fr_schema_home_id( 'website' ) ],
        'publisher'  => [ '@id' => ai_fr_schema_get_identity_node()['@id'] ],
    ];

    if ( $license !== '' ) {
        $node['license'] = esc_url_raw( $license );
    }

    return $node;
}

function ai_fr_schema_get_web_nodes(): array {
    $identity = ai_fr_schema_get_identity_node();
    $website  = [
        '@type'      => 'WebSite',
        '@id'        => ai_fr_schema_home_id( 'website' ),
        'url'        => home_url( '/' ),
        'name'       => get_bloginfo( 'name' ),
        'inLanguage' => get_locale(),
        'publisher'  => [ '@id' => $identity['@id'] ],
    ];

    return [ $identity, $website ];
}

function ai_fr_schema_get_current_webpage_node(): array {
    if ( ! is_singular() ) {
        return [];
    }

    $post = get_post();
    if ( ! $post instanceof WP_Post ) {
        return [];
    }

    $url = get_permalink( $post );
    if ( ! is_string( $url ) || $url === '' ) {
        return [];
    }

    $meta = class_exists( 'AiFrMetadata' ) ? AiFrMetadata::extract( $post ) : [];

    $node = [
        '@type'        => 'WebPage',
        '@id'          => trailingslashit( $url ) . '#webpage',
        'url'          => $url,
        'name'         => $meta['title'] ?? get_the_title( $post ),
        'inLanguage'   => get_locale(),
        'datePublished' => get_post_time( DATE_W3C, true, $post ),
        'dateModified' => get_post_modified_time( DATE_W3C, true, $post ),
        'isPartOf'     => [ '@id' => ai_fr_schema_home_id( 'website' ) ],
    ];

    if ( ! empty( $meta['description'] ) ) {
        $node['description'] = $meta['description'];
    }

    if ( ! empty( $meta['featured_image'] ) ) {
        $node['image'] = esc_url_raw( (string) $meta['featured_image'] );
    }

    return $node;
}

function ai_fr_schema_get_graph(): array {
    if ( is_admin() || is_feed() || wp_doing_ajax() || ! ai_fr_schema_is_enabled() ) {
        return [];
    }

    $options = ai_fr_schema_get_options();
    $mode    = ai_fr_schema_output_mode();
    $graph   = $mode === 'standalone' ? ai_fr_schema_get_web_nodes() : [ ai_fr_schema_get_identity_node() ];

    if ( $mode === 'standalone' ) {
        $webpage = ai_fr_schema_get_current_webpage_node();
        if ( ! empty( $webpage ) ) {
            $graph[] = $webpage;
        }
    }

    $profile = ai_fr_schema_get_profile_page_node();
    if ( ! empty( $profile ) ) {
        $graph[] = $profile;
    }

    $blog = ai_fr_schema_get_blog_node();
    if ( ! empty( $blog ) ) {
        $graph[] = $blog;
    }

    if ( is_singular() ) {
        $post = get_post();
        if ( $post instanceof WP_Post ) {
            $extra = ai_fr_schema_get_singular_extra_node( $post, $options );
            if ( ! empty( $extra ) ) {
                $graph[] = $extra;
            }
        }
    }

    if ( $mode !== 'standalone' ) {
        $graph = ai_fr_schema_prepare_extension_graph( $graph );
    }

    return array_values( array_filter( (array) apply_filters( 'ai_fr_schema_graph', $graph, $options ) ) );
}

function ai_fr_schema_prepare_extension_graph( array $graph ): array {
    foreach ( $graph as &$node ) {
        if ( ! is_array( $node ) ) {
            continue;
        }
        unset( $node['isPartOf'] );
    }
    unset( $node );

    return $graph;
}

function ai_fr_schema_merge_graph_nodes( array $graph, array $additions ): array {
    foreach ( $additions as $addition ) {
        if ( ! is_array( $addition ) || empty( $addition['@id'] ) ) {
            continue;
        }

        $matched = false;
        foreach ( $graph as &$node ) {
            if ( ! is_array( $node ) || empty( $node['@id'] ) || $node['@id'] !== $addition['@id'] ) {
                continue;
            }

            $node    = ai_fr_schema_merge_node( $node, $addition );
            $matched = true;
            break;
        }
        unset( $node );

        if ( ! $matched ) {
            $graph[] = $addition;
        }
    }

    return $graph;
}

function ai_fr_schema_merge_node( array $base, array $addition ): array {
    foreach ( $addition as $key => $value ) {
        if ( $value === '' || $value === [] || $value === null ) {
            continue;
        }

        if ( ! array_key_exists( $key, $base ) || $base[ $key ] === '' || $base[ $key ] === [] || $base[ $key ] === null ) {
            $base[ $key ] = $value;
            continue;
        }

        if ( is_array( $base[ $key ] ) && is_array( $value ) ) {
            $base[ $key ] = ai_fr_schema_merge_value( $base[ $key ], $value );
            continue;
        }

        if ( in_array( $key, [ 'sameAs', 'knowsAbout', 'knowsLanguage', 'alternateName' ], true ) ) {
            $base[ $key ] = ai_fr_schema_merge_list_values( $base[ $key ], $value );
        }
    }

    return $base;
}

function ai_fr_schema_merge_value( array $base, array $addition ): array {
    if ( array_is_list( $base ) || array_is_list( $addition ) ) {
        return ai_fr_schema_merge_list_values( $base, $addition );
    }

    foreach ( $addition as $key => $value ) {
        if ( $value === '' || $value === [] || $value === null ) {
            continue;
        }

        if ( ! array_key_exists( $key, $base ) || $base[ $key ] === '' || $base[ $key ] === [] || $base[ $key ] === null ) {
            $base[ $key ] = $value;
            continue;
        }

        if ( is_array( $base[ $key ] ) && is_array( $value ) ) {
            $base[ $key ] = ai_fr_schema_merge_value( $base[ $key ], $value );
        }
    }

    return $base;
}

function ai_fr_schema_merge_list_values( $base, $addition ): array {
    $items = [];
    foreach ( [ $base, $addition ] as $value ) {
        foreach ( is_array( $value ) ? $value : [ $value ] as $item ) {
            if ( $item === '' || $item === [] || $item === null ) {
                continue;
            }
            $items[] = $item;
        }
    }

    if ( ai_fr_schema_list_looks_like_urls( $items ) ) {
        return ai_fr_schema_dedupe_urls( $items );
    }

    $seen = [];
    $unique = [];
    foreach ( $items as $item ) {
        $fingerprint = is_array( $item ) ? wp_json_encode( $item ) : (string) $item;
        if ( ! is_string( $fingerprint ) || isset( $seen[ $fingerprint ] ) ) {
            continue;
        }
        $seen[ $fingerprint ] = true;
        $unique[] = $item;
    }

    return $unique;
}

function ai_fr_schema_list_looks_like_urls( array $items ): bool {
    if ( empty( $items ) ) {
        return false;
    }

    foreach ( $items as $item ) {
        if ( ! is_string( $item ) || ! preg_match( '#^https?://#i', $item ) ) {
            return false;
        }
    }

    return true;
}

function ai_fr_schema_cleanup_node( $value ) {
    if ( ! is_array( $value ) ) {
        return $value;
    }

    foreach ( $value as $key => $item ) {
        if ( in_array( $key, [ 'width', 'height' ], true ) && ( $item === '' || $item === null ) ) {
            unset( $value[ $key ] );
            continue;
        }

        $value[ $key ] = ai_fr_schema_cleanup_node( $item );
    }

    return $value;
}

function ai_fr_schema_cleanup_graph( array $graph ): array {
    foreach ( $graph as $key => $node ) {
        $graph[ $key ] = ai_fr_schema_cleanup_node( $node );
    }

    return $graph;
}

function ai_fr_schema_get_singular_extra_node( WP_Post $post, array $options ): array {
    $license = trim( (string) ( $options['schema_license'] ?? '' ) );
    if ( $license === '' || $post->post_type !== 'post' ) {
        return [];
    }

    $url = get_permalink( $post );
    if ( ! is_string( $url ) || $url === '' ) {
        return [];
    }

    return [
        '@type'    => 'CreativeWork',
        '@id'      => trailingslashit( $url ) . '#ai-friendly-work',
        'url'      => $url,
        'name'     => get_the_title( $post ),
        'license'  => esc_url_raw( $license ),
        'author'   => [ '@id' => ai_fr_schema_get_identity_node()['@id'] ],
        'isPartOf' => [ '@id' => trailingslashit( $url ) . '#webpage' ],
    ];
}

function ai_fr_schema_as_json_ld( array $graph ): array {
    if ( empty( $graph ) ) {
        return [];
    }

    return [
        '@context' => 'https://schema.org',
        '@graph'   => array_values( $graph ),
    ];
}

function ai_fr_schema_print_standalone(): void {
    if ( ai_fr_schema_output_mode() !== 'standalone' ) {
        return;
    }

    $data = ai_fr_schema_as_json_ld( ai_fr_schema_cleanup_graph( ai_fr_schema_get_graph() ) );
    if ( empty( $data ) ) {
        return;
    }

    $json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    if ( ! is_string( $json ) || $json === '' ) {
        return;
    }

    echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
}
add_action( 'wp_head', 'ai_fr_schema_print_standalone', 20 );

add_filter(
    'rank_math/json_ld',
    function ( array $data ): array {
        if ( ai_fr_schema_output_mode() !== 'extend_rank_math' ) {
            return $data;
        }

        foreach ( ai_fr_schema_get_graph() as $index => $node ) {
            if ( ! is_array( $node ) || empty( $node['@id'] ) ) {
                continue;
            }

            $matched = false;
            foreach ( $data as $key => $existing ) {
                if ( ! is_array( $existing ) || empty( $existing['@id'] ) || $existing['@id'] !== $node['@id'] ) {
                    continue;
                }

                $data[ $key ] = ai_fr_schema_merge_node( $existing, $node );
                $matched = true;
                break;
            }

            if ( ! $matched ) {
                $key          = 'ai_fr_' . md5( (string) $node['@id'] . '_' . $index );
                $data[ $key ] = $node;
            }
        }

        return ai_fr_schema_cleanup_graph( $data );
    },
    99,
    1
);

add_filter(
    'wpseo_schema_graph',
    function ( array $graph ): array {
        if ( ai_fr_schema_output_mode() !== 'extend_yoast' ) {
            return $graph;
        }

        return ai_fr_schema_cleanup_graph( ai_fr_schema_merge_graph_nodes( $graph, ai_fr_schema_get_graph() ) );
    },
    99,
    1
);
