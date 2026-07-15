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

function ai_fr_schema_sanitize_schema_type( string $type ): string {
    $type = trim( $type );
    if ( $type === '' || ! preg_match( '/^[A-Z][A-Za-z0-9]*$/', $type ) ) {
        return '';
    }

    return $type;
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

    $schema_type = $type;
    if ( $type === 'Organization' ) {
        $additional_type = ai_fr_schema_sanitize_schema_type( (string) ( $options['schema_additional_type'] ?? '' ) );
        if ( $additional_type !== '' && $additional_type !== $type ) {
            $schema_type = [ $type, $additional_type ];
        }
    }

    $node = [
        '@type' => $schema_type,
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
    } else {
        $simple_fields['slogan']       = 'schema_slogan';
        $simple_fields['foundingDate'] = 'schema_founding_date';
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

    if ( $type === 'Organization' ) {
        $organization_fields = [
            'legalName'    => 'schema_legal_name',
            'vatID'        => 'schema_vat_id',
            'taxID'        => 'schema_tax_id',
            'leiCode'      => 'schema_lei_code',
            'tickerSymbol' => 'schema_ticker_symbol',
        ];
        foreach ( $organization_fields as $schema_key => $option_key ) {
            $value = trim( (string) ( $options[ $option_key ] ?? '' ) );
            if ( $value !== '' ) {
                $node[ $schema_key ] = $value;
            }
        }

        $lei_code = trim( (string) ( $options['schema_lei_code'] ?? '' ) );
        if ( $lei_code !== '' ) {
            $node['iso6523Code'] = '0199:' . preg_replace( '/\s+/', '', $lei_code );
        }

        $logo = ai_fr_schema_get_image_object( intval( $options['schema_logo_id'] ?? 0 ), 'organization-logo' );
        if ( ! empty( $logo ) ) {
            $node['logo'] = $logo;
        }

        $address = ai_fr_schema_get_address( $options );
        if ( ! empty( $address ) ) {
            $node['address'] = $address;
        }

        $contact = ai_fr_schema_get_contact_point( $options );
        if ( ! empty( $contact ) ) {
            $node['contactPoint'] = [ $contact ];
        }

        $founders = ai_fr_schema_parse_founders( (string) ( $options['schema_founders'] ?? '' ) );
        if ( ! empty( $founders ) ) {
            $node['founder'] = $founders;
        }

        $area_served = ai_fr_schema_parse_area_served( (string) ( $options['schema_area_served'] ?? '' ) );
        if ( ! empty( $area_served ) ) {
            $node['areaServed'] = $area_served;
        }

        if ( ai_fr_schema_has_offer_catalog( $options ) ) {
            $node['hasOfferCatalog'] = [ '@id' => ai_fr_schema_home_id( 'service-catalog' ) ];
        }
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

function ai_fr_schema_get_image_object( int $attachment_id, string $fragment ): array {
    if ( $attachment_id <= 0 ) {
        return [];
    }

    $image = wp_get_attachment_image_src( $attachment_id, 'full' );
    if ( ! is_array( $image ) || empty( $image[0] ) ) {
        return [];
    }

    $node = [
        '@type'      => 'ImageObject',
        '@id'        => ai_fr_schema_home_id( $fragment ),
        'url'        => esc_url_raw( $image[0] ),
        'contentUrl' => esc_url_raw( $image[0] ),
    ];
    if ( ! empty( $image[1] ) ) {
        $node['width'] = intval( $image[1] );
    }
    if ( ! empty( $image[2] ) ) {
        $node['height'] = intval( $image[2] );
    }
    return $node;
}

function ai_fr_schema_get_address( array $options ): array {
    $fields = [
        'streetAddress'   => 'schema_street_address',
        'postalCode'      => 'schema_postal_code',
        'addressLocality' => 'schema_address_locality',
        'addressRegion'   => 'schema_address_region',
        'addressCountry'  => 'schema_address_country',
    ];
    $address = [ '@type' => 'PostalAddress' ];
    foreach ( $fields as $schema_key => $option_key ) {
        $value = trim( (string) ( $options[ $option_key ] ?? '' ) );
        if ( $value !== '' ) {
            $address[ $schema_key ] = $value;
        }
    }
    return count( $address ) > 1 ? $address : [];
}

function ai_fr_schema_get_contact_point( array $options ): array {
    $type  = trim( (string) ( $options['schema_contact_type'] ?? '' ) );
    $email = sanitize_email( (string) ( $options['schema_contact_email'] ?? '' ) );
    if ( $type === '' && $email === '' ) {
        return [];
    }

    $contact = [ '@type' => 'ContactPoint' ];
    if ( $type !== '' ) {
        $contact['contactType'] = $type;
    }
    if ( $email !== '' ) {
        $contact['email'] = $email;
    }
    $languages = ai_fr_schema_split_lines( (string) ( $options['schema_contact_languages'] ?? '' ) );
    if ( ! empty( $languages ) ) {
        $contact['availableLanguage'] = $languages;
    }
    return $contact;
}

function ai_fr_schema_parse_founders( string $value ): array {
    $rows = preg_split( '/\r\n|\r|\n/', $value );
    if ( ! is_array( $rows ) ) {
        return [];
    }

    $founders = [];
    foreach ( $rows as $row ) {
        $parts = array_map( 'trim', explode( '|', $row, 2 ) );
        if ( $parts[0] === '' ) {
            continue;
        }
        $person = [ '@type' => 'Person', 'name' => sanitize_text_field( $parts[0] ) ];
        if ( ! empty( $parts[1] ) ) {
            $person['jobTitle'] = sanitize_text_field( $parts[1] );
        }
        $founders[] = $person;
    }
    return $founders;
}

function ai_fr_schema_parse_area_served( string $value ): array {
    $items = ai_fr_schema_split_lines( $value );
    $areas = [];

    foreach ( $items as $item ) {
        $parts = array_map( 'trim', explode( ':', $item, 2 ) );
        if ( count( $parts ) === 2 ) {
            $place_type = ai_fr_schema_sanitize_schema_type( $parts[0] );
            if ( in_array( $place_type, [ 'City', 'Country', 'AdministrativeArea', 'Place' ], true ) && $parts[1] !== '' ) {
                $areas[] = [
                    '@type' => $place_type,
                    'name'  => sanitize_text_field( $parts[1] ),
                ];
                continue;
            }
        }

        $areas[] = sanitize_text_field( $item );
    }

    return array_values( array_filter( $areas ) );
}

function ai_fr_schema_get_offer_catalog_node( array $options ): array {
    if ( ( $options['schema_entity_type'] ?? '' ) !== 'Organization' ) {
        return [];
    }

    $catalog_source = ai_fr_schema_get_offer_catalog_source( $options );
    $services       = $catalog_source['services'];

    $offers = [];
    foreach ( $services as $service ) {
        $offer = ai_fr_schema_normalize_service_offer( is_array( $service ) ? $service : [] );
        if ( ! empty( $offer ) ) {
            $offers[] = $offer;
        }
    }

    if ( empty( $offers ) ) {
        return [];
    }

    $catalog_name = ! empty( $catalog_source['name'] )
        ? sanitize_text_field( (string) $catalog_source['name'] )
        : sprintf( 'Servizi %s', get_bloginfo( 'name' ) );

    return [
        '@type'           => 'OfferCatalog',
        '@id'             => ai_fr_schema_home_id( 'service-catalog' ),
        'name'            => $catalog_name,
        'itemListElement' => $offers,
    ];
}

function ai_fr_schema_has_offer_catalog( array $options ): bool {
    return ! empty( ai_fr_schema_get_offer_catalog_source( $options )['services'] );
}

function ai_fr_schema_get_offer_catalog_source( array $options ): array {
    $services = [];
    if ( isset( $options['schema_services'] ) && is_array( $options['schema_services'] ) ) {
        $services = $options['schema_services'];
    }

    if ( empty( $services ) ) {
        $legacy = ai_fr_schema_parse_legacy_offer_catalog( (string) ( $options['schema_offer_catalog'] ?? '' ) );
        if ( ! empty( $legacy['services'] ) ) {
            return $legacy;
        }
    }

    return [
        'name'     => '',
        'services' => ai_fr_schema_normalize_service_inputs( $services ),
    ];
}

function ai_fr_schema_parse_legacy_offer_catalog( string $raw ): array {
    $raw = trim( $raw );
    if ( $raw === '' ) {
        return [ 'name' => '', 'services' => [] ];
    }

    $decoded = json_decode( $raw, true );
    if ( ! is_array( $decoded ) ) {
        return [ 'name' => '', 'services' => [] ];
    }

    $catalog_name = '';
    $services     = $decoded;
    if ( ! array_is_list( $decoded ) ) {
        $catalog_name = sanitize_text_field( (string) ( $decoded['name'] ?? '' ) );
        $services     = isset( $decoded['itemListElement'] ) && is_array( $decoded['itemListElement'] ) ? $decoded['itemListElement'] : [];
    }

    return [
        'name'     => $catalog_name,
        'services' => ai_fr_schema_normalize_service_inputs( $services ),
    ];
}

function ai_fr_schema_normalize_service_inputs( array $services ): array {
    $normalized = [];

    foreach ( $services as $service ) {
        if ( ! is_array( $service ) ) {
            continue;
        }

        $service = ai_fr_schema_normalize_service_input( $service );
        if ( ! empty( $service ) ) {
            $normalized[] = $service;
        }
    }

    return $normalized;
}

function ai_fr_schema_normalize_service_input( array $source ): array {
    $offer_source   = $source;
    $service_source = isset( $source['itemOffered'] ) && is_array( $source['itemOffered'] ) ? $source['itemOffered'] : $source;

    $area_served = '';
    if ( ! empty( $service_source['areaServed']['name'] ) ) {
        $area_served = (string) $service_source['areaServed']['name'];
    } elseif ( ! empty( $service_source['areaServed'] ) && is_string( $service_source['areaServed'] ) ) {
        $area_served = $service_source['areaServed'];
    }

    $service = [
        'name'          => sanitize_text_field( (string) ( $service_source['name'] ?? '' ) ),
        'url'           => esc_url_raw( (string) ( $service_source['url'] ?? '' ) ),
        'serviceType'   => sanitize_text_field( (string) ( $service_source['serviceType'] ?? '' ) ),
        'description'   => sanitize_textarea_field( (string) ( $service_source['description'] ?? '' ) ),
        'areaServed'    => sanitize_text_field( $area_served ),
        'price'         => sanitize_text_field( (string) ( $offer_source['price'] ?? '' ) ),
        'priceCurrency' => sanitize_text_field( (string) ( $offer_source['priceCurrency'] ?? '' ) ),
    ];

    return $service['name'] !== '' || $service['url'] !== '' ? $service : [];
}

function ai_fr_schema_normalize_service_offer( array $source ): array {
    $service_source = ai_fr_schema_normalize_service_input( $source );

    $name = $service_source['name'] ?? '';
    $url  = $service_source['url'] ?? '';
    if ( $name === '' && $url === '' ) {
        return [];
    }

    $service = [
        '@type'    => 'Service',
        'provider' => [ '@id' => ai_fr_schema_home_id( 'organization' ) ],
    ];

    if ( $url !== '' ) {
        $service['@id'] = trailingslashit( $url ) . '#service';
        $service['url'] = $url;
    }

    foreach ( [ 'name', 'serviceType', 'description' ] as $key ) {
        $value = (string) ( $service_source[ $key ] ?? '' );
        if ( $value !== '' ) {
            $service[ $key ] = $value;
        }
    }

    if ( ! empty( $service_source['areaServed'] ) ) {
        $service['areaServed'] = [
            '@type' => 'Country',
            'name'  => $service_source['areaServed'],
        ];
    }

    $offer = [
        '@type'       => 'Offer',
        'itemOffered' => $service,
    ];

    foreach ( [ 'price', 'priceCurrency' ] as $key ) {
        $value = (string) ( $service_source[ $key ] ?? '' );
        if ( $value !== '' ) {
            $offer[ $key ] = $value;
        }
    }

    return $offer;
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

    $creator = ai_fr_schema_get_creator();
    if ( ! empty( $creator ) ) {
        $website['creator'] = $creator;
    }

    return [ $identity, $website ];
}

function ai_fr_schema_get_creator(): array {
    $options = ai_fr_schema_get_options();
    $name    = trim( (string) ( $options['schema_creator_name'] ?? '' ) );

    if ( $name === '' ) {
        return [];
    }

    $type = (string) ( $options['schema_creator_type'] ?? 'Organization' );
    if ( ! in_array( $type, [ 'Person', 'Organization' ], true ) ) {
        $type = 'Organization';
    }

    $creator = [
        '@type' => $type,
        'name'  => $name,
    ];

    $url = esc_url_raw( (string) ( $options['schema_creator_url'] ?? '' ) );
    if ( $url !== '' ) {
        $creator['url'] = $url;
    }

    return $creator;
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

    if ( $mode !== 'standalone' ) {
        $creator = ai_fr_schema_get_creator();
        if ( ! empty( $creator ) ) {
            $graph[] = [
                '@type'   => 'WebSite',
                '@id'     => ai_fr_schema_home_id( 'website' ),
                'creator' => $creator,
            ];
        }
    }

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

    $offer_catalog = ai_fr_schema_get_offer_catalog_node( $options );
    if ( ! empty( $offer_catalog ) ) {
        $graph[] = $offer_catalog;
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

        if ( in_array( $key, [ '@type', 'sameAs', 'knowsAbout', 'knowsLanguage', 'alternateName' ], true ) ) {
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

    $json = wp_json_encode(
        $data,
        JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );
    if ( ! is_string( $json ) || $json === '' ) {
        return;
    }

    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON_HEX_* prevents breaking out of the script element.
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
