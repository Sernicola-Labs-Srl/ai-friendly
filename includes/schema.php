<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function saifr_schema_get_options(): array {
    return wp_parse_args( get_option( 'saifr_options', [] ), saifr_get_default_options() );
}

function saifr_schema_is_enabled(): bool {
    $options = saifr_schema_get_options();
    return (bool) apply_filters( 'saifr_schema_enabled', ! empty( $options['schema_enabled'] ), $options );
}

function saifr_schema_detect_provider(): string {
    if ( defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' ) ) {
        return 'yoast';
    }

    if ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ) ) {
        return 'rank_math';
    }

    return 'none';
}

function saifr_schema_output_mode(): string {
    $options  = saifr_schema_get_options();
    $mode     = sanitize_key( (string) ( $options['schema_mode'] ?? 'auto' ) );
    $provider = saifr_schema_detect_provider();

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

function saifr_schema_split_lines( string $value ): array {
    $items = preg_split( '/\r\n|\r|\n|,/', $value );
    if ( ! is_array( $items ) ) {
        return [];
    }

    $items = array_map( 'trim', $items );
    $items = array_filter( $items, static fn( string $item ): bool => $item !== '' );
    return array_values( array_unique( $items ) );
}

function saifr_schema_dedupe_urls( array $urls ): array {
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

function saifr_schema_sanitize_schema_type( string $type ): string {
    $type = trim( $type );
    if ( $type === '' || ! preg_match( '/^[A-Z][A-Za-z0-9]*$/', $type ) ) {
        return '';
    }

    return $type;
}

function saifr_schema_home_id( string $fragment ): string {
    return trailingslashit( home_url( '/' ) ) . '#' . ltrim( sanitize_title( $fragment ), '#' );
}

function saifr_schema_get_identity_node(): array {
    $options = saifr_schema_get_options();

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
        $configured_types = isset( $options['schema_types'] ) && is_array( $options['schema_types'] ) ? $options['schema_types'] : [];
        if ( empty( $configured_types ) && ! empty( $options['schema_additional_type'] ) ) {
            $configured_types[] = $options['schema_additional_type'];
        }
        $configured_types = array_map( static fn( $item ): string => saifr_schema_sanitize_schema_type( (string) $item ), $configured_types );
        $configured_types = array_values( array_unique( array_filter( $configured_types ) ) );
        $configured_types = array_values( array_diff( $configured_types, [ $type ] ) );
        if ( ! empty( $configured_types ) ) {
            $schema_type = array_merge( [ $type ], $configured_types );
        }
    }

    $node = [
        '@type' => $schema_type,
        '@id'   => saifr_schema_home_id( strtolower( $type ) ),
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

    $same_as = saifr_schema_split_lines( (string) ( $options['schema_same_as'] ?? '' ) );
    $same_as = saifr_schema_dedupe_urls( $same_as );
    if ( ! empty( $same_as ) ) {
        $node['sameAs'] = $same_as;
    }

    $knows_about = saifr_schema_split_lines( (string) ( $options['schema_knows_about'] ?? '' ) );
    if ( ! empty( $knows_about ) ) {
        $node['knowsAbout'] = $knows_about;
    }

    $knows_language = saifr_schema_split_lines( (string) ( $options['schema_knows_language'] ?? '' ) );
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

        $logo = saifr_schema_get_image_object( intval( $options['schema_logo_id'] ?? 0 ), 'organization-logo' );
        if ( ! empty( $logo ) ) {
            $node['logo'] = $logo;
        }

        $address = saifr_schema_get_address( $options );
        if ( ! empty( $address ) ) {
            $node['address'] = $address;
        }

        $contacts = saifr_schema_get_contact_points( $options );
        if ( ! empty( $contacts ) ) {
            $node['contactPoint'] = $contacts;
        }

        if ( saifr_schema_has_place( $options ) ) {
            $node['location'] = [ '@id' => saifr_schema_home_id( 'place' ) ];
        }

        $certifications = saifr_schema_get_certifications( $options );
        if ( ! empty( $certifications ) ) {
            $node['hasCertification'] = $certifications;
        }

        $identifiers = saifr_schema_get_identifiers( $options );
        if ( ! empty( $identifiers ) ) {
            $node['identifier'] = $identifiers;
        }

        $founders = saifr_schema_parse_founders( (string) ( $options['schema_founders'] ?? '' ) );
        if ( ! empty( $founders ) ) {
            $node['founder'] = $founders;
        }

        $area_served = saifr_schema_parse_area_served( (string) ( $options['schema_area_served'] ?? '' ) );
        if ( ! empty( $area_served ) ) {
            $node['areaServed'] = $area_served;
        }

        if ( saifr_schema_has_offer_catalog( $options ) ) {
            $node['hasOfferCatalog'] = [ '@id' => saifr_schema_home_id( 'service-catalog' ) ];
        }

        foreach ( saifr_schema_get_related_entities( $options ) as $entity ) {
            if ( in_array( $entity['relation'], [ 'brand', 'subOrganization' ], true ) ) {
                $node[ $entity['relation'] ][] = [ '@id' => $entity['node']['@id'] ];
            }
        }
    }

    $image_id = intval( $options['schema_image_id'] ?? 0 );
    if ( $image_id > 0 ) {
        $image = wp_get_attachment_image_src( $image_id, 'full' );
        if ( is_array( $image ) && ! empty( $image[0] ) ) {
            $node['image'] = [
                '@type' => 'ImageObject',
                '@id'   => saifr_schema_home_id( strtolower( $type ) . '-image' ),
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

    return (array) apply_filters( 'saifr_schema_identity', $node, $options );
}

function saifr_schema_get_image_object( int $attachment_id, string $fragment ): array {
    if ( $attachment_id <= 0 ) {
        return [];
    }

    $image = wp_get_attachment_image_src( $attachment_id, 'full' );
    if ( ! is_array( $image ) || empty( $image[0] ) ) {
        return [];
    }

    $node = [
        '@type'      => 'ImageObject',
        '@id'        => saifr_schema_home_id( $fragment ),
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

function saifr_schema_get_address( array $options ): array {
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

function saifr_schema_get_contact_point( array $options ): array {
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
    $languages = saifr_schema_split_lines( (string) ( $options['schema_contact_languages'] ?? '' ) );
    if ( ! empty( $languages ) ) {
        $contact['availableLanguage'] = $languages;
    }
    return $contact;
}

function saifr_schema_get_contact_points( array $options ): array {
    $rows = isset( $options['schema_contacts'] ) && is_array( $options['schema_contacts'] ) ? $options['schema_contacts'] : [];
    if ( empty( $rows ) ) {
        $legacy = saifr_schema_get_contact_point( $options );
        return empty( $legacy ) ? [] : [ $legacy ];
    }

    $contacts = [];
    foreach ( $rows as $row ) {
        if ( ! is_array( $row ) ) {
            continue;
        }
        $contact = [ '@type' => 'ContactPoint' ];
        foreach ( [ 'contactType', 'telephone' ] as $key ) {
            $value = sanitize_text_field( (string) ( $row[ $key ] ?? '' ) );
            if ( $value !== '' ) {
                $contact[ $key ] = $value;
            }
        }
        $email = sanitize_email( (string) ( $row['email'] ?? '' ) );
        if ( $email !== '' ) {
            $contact['email'] = $email;
        }
        $contact_hours = sanitize_text_field( (string) ( $row['hoursAvailable'] ?? '' ) );
        if ( $contact_hours !== '' ) {
            $parsed_hours = saifr_schema_parse_hours_text( $contact_hours );
            $contact['hoursAvailable'] = ! empty( $parsed_hours )
                ? $parsed_hours
                : [ '@type' => 'OpeningHoursSpecification', 'description' => $contact_hours ];
        }
        $languages = saifr_schema_split_lines( (string) ( $row['availableLanguage'] ?? '' ) );
        if ( ! empty( $languages ) ) {
            $contact['availableLanguage'] = $languages;
        }
        if ( count( $contact ) > 1 ) {
            $contacts[] = $contact;
        }
    }
    return $contacts;
}

function saifr_schema_get_opening_hours( array $options ): array {
    $rows = isset( $options['schema_opening_hours'] ) && is_array( $options['schema_opening_hours'] ) ? $options['schema_opening_hours'] : [];
    $specifications = [];
    foreach ( $rows as $row ) {
        if ( ! is_array( $row ) ) {
            continue;
        }
        $spec = [ '@type' => 'OpeningHoursSpecification' ];
        $days = saifr_schema_split_lines( (string) ( $row['dayOfWeek'] ?? '' ) );
        if ( ! empty( $days ) ) {
            $spec['dayOfWeek'] = $days;
        }
        foreach ( [ 'opens', 'closes', 'validFrom', 'validThrough' ] as $key ) {
            $value = sanitize_text_field( (string) ( $row[ $key ] ?? '' ) );
            if ( $value !== '' ) {
                $spec[ $key ] = $value;
            }
        }
        if ( count( $spec ) > 1 ) {
            $specifications[] = $spec;
        }
    }
    return $specifications;
}

function saifr_schema_has_place( array $options ): bool {
    return saifr_schema_get_address( $options ) !== []
        || trim( (string) ( $options['schema_latitude'] ?? '' ) ) !== ''
        || trim( (string) ( $options['schema_longitude'] ?? '' ) ) !== '';
}

function saifr_schema_get_place_node( array $options ): array {
    if ( ! saifr_schema_has_place( $options ) ) {
        return [];
    }
    $place_type = saifr_schema_sanitize_schema_type( (string) ( $options['schema_place_type'] ?? 'Place' ) ) ?: 'Place';
    $node = [ '@type' => $place_type, '@id' => saifr_schema_home_id( 'place' ) ];
    $name = trim( (string) ( $options['schema_place_name'] ?? '' ) );
    if ( $name !== '' ) {
        $node['name'] = $name;
    }
    $address = saifr_schema_get_address( $options );
    if ( ! empty( $address ) ) {
        $node['address'] = $address;
    }
    $lat = trim( (string) ( $options['schema_latitude'] ?? '' ) );
    $lng = trim( (string) ( $options['schema_longitude'] ?? '' ) );
    if ( is_numeric( $lat ) && is_numeric( $lng ) ) {
        $node['geo'] = [ '@type' => 'GeoCoordinates', 'latitude' => (float) $lat, 'longitude' => (float) $lng ];
    }
    $transport = trim( (string) ( $options['schema_public_transportation_access'] ?? '' ) );
    if ( $transport !== '' ) {
        $node['publicAccess'] = true;
        $node['amenityFeature'] = [
            '@type' => 'LocationFeatureSpecification',
            'name'   => 'Public transportation access',
            'value'  => $transport,
        ];
    }
    $hours = saifr_schema_get_opening_hours( $options );
    if ( ! empty( $hours ) ) {
        $node['openingHoursSpecification'] = $hours;
    }
    return $node;
}

function saifr_schema_get_certifications( array $options ): array {
    $rows = isset( $options['schema_certifications'] ) && is_array( $options['schema_certifications'] ) ? $options['schema_certifications'] : [];
    $items = [];
    foreach ( $rows as $row ) {
        if ( ! is_array( $row ) || trim( (string) ( $row['name'] ?? '' ) ) === '' ) {
            continue;
        }
        $item = [ '@type' => 'Certification', 'name' => sanitize_text_field( (string) $row['name'] ) ];
        if ( ! empty( $row['identifier'] ) ) $item['certificationIdentification'] = sanitize_text_field( (string) $row['identifier'] );
        if ( ! empty( $row['issuedBy'] ) ) $item['issuedBy'] = [ '@type' => 'Organization', 'name' => sanitize_text_field( (string) $row['issuedBy'] ) ];
        if ( ! empty( $row['url'] ) ) $item['url'] = esc_url_raw( (string) $row['url'] );
        $items[] = $item;
    }
    return $items;
}

function saifr_schema_get_identifiers( array $options ): array {
    $rows = isset( $options['schema_identifiers'] ) && is_array( $options['schema_identifiers'] ) ? $options['schema_identifiers'] : [];
    $items = [];
    foreach ( $rows as $row ) {
        $property = sanitize_text_field( (string) ( $row['propertyID'] ?? '' ) );
        $value = sanitize_text_field( (string) ( $row['value'] ?? '' ) );
        if ( $property !== '' && $value !== '' ) {
            $items[] = [ '@type' => 'PropertyValue', 'propertyID' => $property, 'value' => $value ];
        }
    }
    return $items;
}

/**
 * Tipi di entità collegate gestibili da interfaccia e relazione con l'organizzazione principale.
 */
function saifr_schema_related_entity_types(): array {
    return (array) apply_filters(
        'saifr_schema_related_entity_types',
        [
            'Periodical'              => 'publisher',
            'Newspaper'               => 'publisher',
            'CreativeWorkSeries'      => 'publisher',
            'BookSeries'              => 'publisher',
            'PodcastSeries'           => 'publisher',
            'Book'                    => 'publisher',
            'WebSite'                 => 'publisher',
            'EventSeries'             => 'organizer',
            'Brand'                   => 'brand',
            'Organization'            => 'subOrganization',
            'NewsMediaOrganization'   => 'subOrganization',
            'EducationalOrganization' => 'subOrganization',
            'LocalBusiness'           => 'subOrganization',
        ]
    );
}

/**
 * Nodi delle entità collegate (pubblicazioni, cicli di eventi, brand, società del gruppo).
 *
 * @return array<int, array{node: array, relation: string}>
 */
function saifr_schema_get_related_entities( array $options ): array {
    if ( ( $options['schema_entity_type'] ?? '' ) !== 'Organization' ) {
        return [];
    }

    $rows        = isset( $options['schema_related_entities'] ) && is_array( $options['schema_related_entities'] ) ? $options['schema_related_entities'] : [];
    $types       = saifr_schema_related_entity_types();
    $identity_id = saifr_schema_home_id( 'organization' );
    $series      = [ 'Periodical', 'Newspaper', 'CreativeWorkSeries', 'BookSeries', 'PodcastSeries' ];
    $entities    = [];

    foreach ( $rows as $row ) {
        if ( ! is_array( $row ) ) {
            continue;
        }
        $type = saifr_schema_sanitize_schema_type( (string) ( $row['type'] ?? '' ) );
        $name = sanitize_text_field( (string) ( $row['name'] ?? '' ) );
        if ( $name === '' || ! isset( $types[ $type ] ) ) {
            continue;
        }

        $url  = esc_url_raw( (string) ( $row['url'] ?? '' ) );
        $node = [
            '@type' => $type,
            '@id'   => $url !== '' ? trailingslashit( $url ) . '#' . strtolower( $type ) : saifr_schema_home_id( 'related-' . $type . '-' . $name ),
            'name'  => $name,
        ];
        if ( $url !== '' ) {
            $node['url'] = $url;
        }

        $description = sanitize_textarea_field( (string) ( $row['description'] ?? '' ) );
        if ( $description !== '' ) {
            $node['description'] = $description;
        }

        $identifier = sanitize_text_field( (string) ( $row['identifier'] ?? '' ) );
        if ( $identifier !== '' ) {
            $identifier_key = in_array( $type, $series, true ) ? 'issn' : ( $type === 'Book' ? 'isbn' : 'identifier' );
            $node[ $identifier_key ] = $identifier;
        }

        $same_as = saifr_schema_dedupe_urls( saifr_schema_split_lines( (string) ( $row['sameAs'] ?? '' ) ) );
        if ( ! empty( $same_as ) ) {
            $node['sameAs'] = $same_as;
        }

        $relation = (string) $types[ $type ];
        if ( in_array( $relation, [ 'publisher', 'organizer' ], true ) ) {
            $node[ $relation ] = [ '@id' => $identity_id ];
        } elseif ( $relation === 'subOrganization' ) {
            $node['parentOrganization'] = [ '@id' => $identity_id ];
        }

        $entities[] = [
            'node'     => (array) apply_filters( 'saifr_schema_related_entity_node', $node, $row ),
            'relation' => $relation,
        ];
    }

    return $entities;
}

function saifr_schema_parse_founders( string $value ): array {
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

function saifr_schema_parse_area_served( string $value ): array {
    $items = saifr_schema_split_lines( $value );
    $areas = [];

    foreach ( $items as $item ) {
        $parts = array_map( 'trim', explode( ':', $item, 2 ) );
        if ( count( $parts ) === 2 ) {
            $place_type = saifr_schema_sanitize_schema_type( $parts[0] );
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

function saifr_schema_get_offer_catalog_node( array $options ): array {
    if ( ( $options['schema_entity_type'] ?? '' ) !== 'Organization' ) {
        return [];
    }

    $catalog_source = saifr_schema_get_offer_catalog_source( $options );
    $services       = $catalog_source['services'];

    $offers = [];
    foreach ( $services as $service ) {
        $offer = saifr_schema_normalize_service_offer( is_array( $service ) ? $service : [] );
        if ( ! empty( $offer ) ) {
            $offers[] = $offer;
        }
    }

    if ( empty( $offers ) ) {
        return [];
    }

    $catalog_name = ! empty( $catalog_source['name'] )
        ? sanitize_text_field( (string) $catalog_source['name'] )
        : sprintf(
            /* translators: %s: site name. */
            __( 'Servizi %s', 'sernicola-labs-ai-friendly' ),
            get_bloginfo( 'name' )
        );

    return [
        '@type'           => 'OfferCatalog',
        '@id'             => saifr_schema_home_id( 'service-catalog' ),
        'name'            => $catalog_name,
        'itemListElement' => $offers,
    ];
}

function saifr_schema_has_offer_catalog( array $options ): bool {
    return ! empty( saifr_schema_get_offer_catalog_source( $options )['services'] );
}

function saifr_schema_get_offer_catalog_source( array $options ): array {
    $services = [];
    $catalog_name = '';
    if ( isset( $options['schema_services'] ) && is_array( $options['schema_services'] ) ) {
        $services = $options['schema_services'];
    }

    if ( empty( $services ) ) {
        $legacy = saifr_schema_parse_legacy_offer_catalog( (string) ( $options['schema_offer_catalog'] ?? '' ) );
        if ( ! empty( $legacy['services'] ) ) {
            $services = $legacy['services'];
            $catalog_name = (string) ( $legacy['name'] ?? '' );
        }
    }

    $services = saifr_schema_normalize_service_inputs( $services );
    $sources = isset( $options['schema_offer_sources'] ) && is_array( $options['schema_offer_sources'] )
        ? $options['schema_offer_sources']
        : [];
    foreach ( $sources as $source ) {
        $resolved = saifr_schema_resolve_offer_source( (string) $source );
        if ( ! empty( $resolved ) ) {
            $services[] = $resolved;
        }
    }

    return [
        'name'     => $catalog_name,
        'services' => saifr_schema_dedupe_service_inputs( $services ),
    ];
}

function saifr_schema_resolve_offer_source( string $source ): array {
    $source = trim( $source );
    if ( $source === '' ) {
        return [];
    }

    $term = null;
    $post = null;
    if ( ctype_digit( $source ) ) {
        $candidate = get_term( intval( $source ) );
        $term = ! is_wp_error( $candidate ) && $candidate instanceof WP_Term ? $candidate : null;
    } elseif ( ! str_contains( $source, '://' ) && str_contains( $source, ':' ) ) {
        [ $taxonomy, $locator ] = array_map( 'trim', explode( ':', $source, 2 ) );
        if ( taxonomy_exists( $taxonomy ) && $locator !== '' ) {
            $candidate = ctype_digit( $locator )
                ? get_term( intval( $locator ), $taxonomy )
                : get_term_by( 'slug', sanitize_title( $locator ), $taxonomy );
            $term = ! is_wp_error( $candidate ) && $candidate instanceof WP_Term ? $candidate : null;
        }
    } elseif ( filter_var( $source, FILTER_VALIDATE_URL ) ) {
        $source_host = strtolower( (string) wp_parse_url( $source, PHP_URL_HOST ) );
        $site_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
        if ( $source_host === '' || $site_host === '' || $source_host !== $site_host ) {
            return [];
        }
        $post_id = url_to_postid( $source );
        if ( $post_id > 0 ) {
            $candidate = get_post( $post_id );
            $post = $candidate instanceof WP_Post ? $candidate : null;
        }
        if ( ! $post instanceof WP_Post ) {
            $term = saifr_schema_find_term_by_url( $source );
        }
    }

    if ( $term instanceof WP_Term ) {
        $url = get_term_link( $term );
        if ( is_wp_error( $url ) ) {
            return [];
        }
        $taxonomy = get_taxonomy( $term->taxonomy );
        return saifr_schema_normalize_service_input(
            [
                'name' => $term->name,
                'url' => $url,
                'description' => wp_strip_all_tags( term_description( $term ), true ),
                'serviceType' => $taxonomy && ! empty( $taxonomy->labels->singular_name ) ? $taxonomy->labels->singular_name : '',
            ]
        );
    }

    if ( $post instanceof WP_Post ) {
        $post_type = get_post_type_object( $post->post_type );
        $description = has_excerpt( $post )
            ? get_the_excerpt( $post )
            : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ), true ), 40 );
        return saifr_schema_normalize_service_input(
            [
                'name' => get_the_title( $post ),
                'url' => get_permalink( $post ),
                'description' => $description,
                'serviceType' => $post_type && ! empty( $post_type->labels->singular_name ) ? $post_type->labels->singular_name : '',
            ]
        );
    }

    return [];
}

function saifr_schema_find_term_by_url( string $url ): ?WP_Term {
    $path = (string) wp_parse_url( $url, PHP_URL_PATH );
    $slug = sanitize_title( rawurldecode( basename( untrailingslashit( $path ) ) ) );
    if ( $slug === '' ) {
        return null;
    }
    $target_path = untrailingslashit( strtolower( $path ) );
    foreach ( get_taxonomies( [ 'public' => true ], 'names' ) as $taxonomy ) {
        $terms = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false, 'slug' => $slug, 'number' => 5 ] );
        if ( is_wp_error( $terms ) ) {
            continue;
        }
        foreach ( $terms as $term ) {
            if ( ! $term instanceof WP_Term ) {
                continue;
            }
            $term_url = get_term_link( $term );
            if ( is_wp_error( $term_url ) ) {
                continue;
            }
            $term_path = (string) wp_parse_url( $term_url, PHP_URL_PATH );
            if ( untrailingslashit( strtolower( $term_path ) ) === $target_path ) {
                return $term;
            }
        }
    }
    return null;
}

function saifr_schema_dedupe_service_inputs( array $services ): array {
    $unique = [];
    $seen = [];
    foreach ( saifr_schema_normalize_service_inputs( $services ) as $service ) {
        $url = untrailingslashit( strtolower( (string) ( $service['url'] ?? '' ) ) );
        $fingerprint = $url !== '' ? 'url:' . $url : 'name:' . strtolower( (string) ( $service['name'] ?? '' ) );
        if ( isset( $seen[ $fingerprint ] ) ) {
            continue;
        }
        $seen[ $fingerprint ] = true;
        $unique[] = $service;
    }
    return $unique;
}

/**
 * Local equivalent of array_is_list() for WordPress compatibility checks.
 */
function saifr_schema_is_list( array $value ): bool {
    return [] === $value || array_keys( $value ) === range( 0, count( $value ) - 1 );
}

function saifr_schema_parse_legacy_offer_catalog( string $raw ): array {
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
    if ( ! saifr_schema_is_list( $decoded ) ) {
        $catalog_name = sanitize_text_field( (string) ( $decoded['name'] ?? '' ) );
        $services     = isset( $decoded['itemListElement'] ) && is_array( $decoded['itemListElement'] ) ? $decoded['itemListElement'] : [];
    }

    return [
        'name'     => $catalog_name,
        'services' => saifr_schema_normalize_service_inputs( $services ),
    ];
}

function saifr_schema_normalize_service_inputs( array $services ): array {
    $normalized = [];

    foreach ( $services as $service ) {
        if ( ! is_array( $service ) ) {
            continue;
        }

        $service = saifr_schema_normalize_service_input( $service );
        if ( ! empty( $service ) ) {
            $normalized[] = $service;
        }
    }

    return $normalized;
}

function saifr_schema_normalize_service_input( array $source ): array {
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
        'vatIncluded'   => '',
        'billingPeriod' => '',
    ];

    $vat_included = $offer_source['vatIncluded'] ?? ( $offer_source['priceSpecification']['valueAddedTaxIncluded'] ?? '' );
    if ( is_bool( $vat_included ) ) {
        $vat_included = $vat_included ? 'yes' : 'no';
    }
    if ( in_array( $vat_included, [ 'yes', 'no' ], true ) ) {
        $service['vatIncluded'] = $vat_included;
    }
    $billing_period = sanitize_key( (string) ( $offer_source['billingPeriod'] ?? '' ) );
    if ( in_array( $billing_period, [ 'month', 'year' ], true ) ) {
        $service['billingPeriod'] = $billing_period;
    }

    return $service['name'] !== '' || $service['url'] !== '' ? $service : [];
}

function saifr_schema_normalize_service_offer( array $source ): array {
    $service_source = saifr_schema_normalize_service_input( $source );

    $name = $service_source['name'] ?? '';
    $url  = $service_source['url'] ?? '';
    if ( $name === '' && $url === '' ) {
        return [];
    }

    $service = [
        '@type'    => 'Service',
        'provider' => [ '@id' => saifr_schema_home_id( 'organization' ) ],
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

    $priced = saifr_schema_build_offer(
        (string) ( $service_source['price'] ?? '' ),
        (string) ( $service_source['priceCurrency'] ?? '' ),
        '',
        (string) ( $service_source['vatIncluded'] ?? '' ),
        (string) ( $service_source['billingPeriod'] ?? '' )
    );
    unset( $priced['@type'] );

    return array_merge(
        [
            '@type'       => 'Offer',
            'itemOffered' => $service,
        ],
        $priced
    );
}

function saifr_schema_get_profile_page_node(): array {
    $options         = saifr_schema_get_options();
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
        'isPartOf'     => [ '@id' => saifr_schema_home_id( 'website' ) ],
        'mainEntity'   => [ '@id' => saifr_schema_get_identity_node()['@id'] ],
    ];
}

function saifr_schema_get_blog_node(): array {
    if ( ! is_home() ) {
        return [];
    }

    $options = saifr_schema_get_options();
    $license = trim( (string) ( $options['schema_license'] ?? '' ) );
    $node = [
        '@type'      => 'Blog',
        '@id'        => trailingslashit( get_post_type_archive_link( 'post' ) ?: home_url( '/' ) ) . '#blog',
        'name'       => get_bloginfo( 'name' ) . ' Blog',
        'inLanguage' => get_locale(),
        'isPartOf'   => [ '@id' => saifr_schema_home_id( 'website' ) ],
        'publisher'  => [ '@id' => saifr_schema_get_identity_node()['@id'] ],
    ];

    if ( $license !== '' ) {
        $node['license'] = esc_url_raw( $license );
    }

    return $node;
}

function saifr_schema_get_web_nodes(): array {
    $identity = saifr_schema_get_identity_node();
    $website  = [
        '@type'      => 'WebSite',
        '@id'        => saifr_schema_home_id( 'website' ),
        'url'        => home_url( '/' ),
        'name'       => get_bloginfo( 'name' ),
        'inLanguage' => get_locale(),
        'publisher'  => [ '@id' => $identity['@id'] ],
    ];

    $creator = saifr_schema_get_creator();
    if ( ! empty( $creator ) ) {
        $website['creator'] = $creator;
    }

    return [ $identity, $website ];
}

function saifr_schema_get_creator(): array {
    $options = saifr_schema_get_options();
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

function saifr_schema_get_current_webpage_node(): array {
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

    $meta = class_exists( 'SaifrMetadata' ) ? SaifrMetadata::extract( $post ) : [];

    $node = [
        '@type'        => 'WebPage',
        '@id'          => trailingslashit( $url ) . '#webpage',
        'url'          => $url,
        'name'         => $meta['title'] ?? get_the_title( $post ),
        'inLanguage'   => get_locale(),
        'datePublished' => get_post_time( DATE_W3C, true, $post ),
        'dateModified' => get_post_modified_time( DATE_W3C, true, $post ),
        'isPartOf'     => [ '@id' => saifr_schema_home_id( 'website' ) ],
    ];

    if ( ! empty( $meta['description'] ) ) {
        $node['description'] = $meta['description'];
    }

    if ( ! empty( $meta['featured_image'] ) ) {
        $node['image'] = esc_url_raw( (string) $meta['featured_image'] );
    }

    return $node;
}

function saifr_schema_get_graph(): array {
    if ( is_admin() || is_feed() || wp_doing_ajax() || ! saifr_schema_is_enabled() ) {
        return [];
    }

    $options = saifr_schema_get_options();
    $mode    = saifr_schema_output_mode();
    $graph   = $mode === 'standalone' ? saifr_schema_get_web_nodes() : [ saifr_schema_get_identity_node() ];

    if ( $mode !== 'standalone' ) {
        $creator = saifr_schema_get_creator();
        if ( ! empty( $creator ) ) {
            $graph[] = [
                '@type'   => 'WebSite',
                '@id'     => saifr_schema_home_id( 'website' ),
                'creator' => $creator,
            ];
        }
    }

    if ( $mode === 'standalone' ) {
        $webpage = saifr_schema_get_current_webpage_node();
        if ( ! empty( $webpage ) ) {
            $graph[] = $webpage;
        }
    }

    $profile = saifr_schema_get_profile_page_node();
    if ( ! empty( $profile ) ) {
        $graph[] = $profile;
    }

    $blog = saifr_schema_get_blog_node();
    if ( ! empty( $blog ) ) {
        $graph[] = $blog;
    }

    $offer_catalog = saifr_schema_get_offer_catalog_node( $options );
    if ( ! empty( $offer_catalog ) ) {
        $graph[] = $offer_catalog;
    }

    foreach ( saifr_schema_get_related_entities( $options ) as $entity ) {
        $graph[] = $entity['node'];
    }

    $place = saifr_schema_get_place_node( $options );
    if ( ! empty( $place ) ) {
        $graph[] = $place;
    }

    if ( is_singular() ) {
        $post = get_post();
        if ( $post instanceof WP_Post ) {
            $extra = saifr_schema_get_singular_extra_node( $post, $options );
            $breakdance_faq = function_exists( 'saifr_faq_get_node_for_post' ) ? saifr_faq_get_node_for_post( $post ) : [];
            if ( ! empty( $extra ) && ( $extra['@type'] ?? '' ) === 'FAQPage' && ! empty( $breakdance_faq ) ) {
                $extra = saifr_schema_merge_faq_nodes( $extra, $breakdance_faq );
                $breakdance_faq = [];
            }
            if ( ! empty( $extra ) ) {
                $graph[] = $extra;
            }
            if ( ! empty( $breakdance_faq ) ) {
                $graph[] = $breakdance_faq;
            }
        }
    }

    if ( $mode !== 'standalone' ) {
        $graph = saifr_schema_prepare_extension_graph( $graph );
    }

    return array_values( array_filter( (array) apply_filters( 'saifr_schema_graph', $graph, $options ) ) );
}

function saifr_schema_prepare_extension_graph( array $graph ): array {
    foreach ( $graph as &$node ) {
        if ( ! is_array( $node ) ) {
            continue;
        }
        unset( $node['isPartOf'] );
    }
    unset( $node );

    return $graph;
}

function saifr_schema_merge_graph_nodes( array $graph, array $additions ): array {
    foreach ( $additions as $addition ) {
        if ( ! is_array( $addition ) || empty( $addition['@id'] ) ) {
            continue;
        }

        $matched = false;
        foreach ( $graph as &$node ) {
            if ( ! is_array( $node ) || empty( $node['@id'] ) || $node['@id'] !== $addition['@id'] ) {
                continue;
            }

            $node    = saifr_schema_merge_node( $node, $addition );
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

function saifr_schema_merge_node( array $base, array $addition ): array {
    foreach ( $addition as $key => $value ) {
        if ( $value === '' || $value === [] || $value === null ) {
            continue;
        }

        if ( ! array_key_exists( $key, $base ) || $base[ $key ] === '' || $base[ $key ] === [] || $base[ $key ] === null ) {
            $base[ $key ] = $value;
            continue;
        }

        if ( is_array( $base[ $key ] ) && is_array( $value ) ) {
            $base[ $key ] = saifr_schema_merge_value( $base[ $key ], $value );
            continue;
        }

        if ( in_array( $key, [ '@type', 'sameAs', 'knowsAbout', 'knowsLanguage', 'alternateName' ], true ) ) {
            $base[ $key ] = saifr_schema_merge_list_values( $base[ $key ], $value );
        }
    }

    return $base;
}

function saifr_schema_merge_value( array $base, array $addition ): array {
    if ( saifr_schema_is_list( $base ) || saifr_schema_is_list( $addition ) ) {
        return saifr_schema_merge_list_values( $base, $addition );
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
            $base[ $key ] = saifr_schema_merge_value( $base[ $key ], $value );
        }
    }

    return $base;
}

function saifr_schema_merge_list_values( $base, $addition ): array {
    $items = [];
    foreach ( [ $base, $addition ] as $value ) {
        foreach ( is_array( $value ) ? $value : [ $value ] as $item ) {
            if ( $item === '' || $item === [] || $item === null ) {
                continue;
            }
            $items[] = $item;
        }
    }

    if ( saifr_schema_list_looks_like_urls( $items ) ) {
        return saifr_schema_dedupe_urls( $items );
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

function saifr_schema_list_looks_like_urls( array $items ): bool {
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

function saifr_schema_cleanup_node( $value ) {
    if ( ! is_array( $value ) ) {
        return $value;
    }

    foreach ( $value as $key => $item ) {
        if ( in_array( $key, [ 'width', 'height' ], true ) && ( $item === '' || $item === null ) ) {
            unset( $value[ $key ] );
            continue;
        }

        $value[ $key ] = saifr_schema_cleanup_node( $item );
    }

    return $value;
}

function saifr_schema_cleanup_graph( array $graph ): array {
    foreach ( $graph as $key => $node ) {
        $graph[ $key ] = saifr_schema_cleanup_node( $node );
    }

    return $graph;
}

function saifr_schema_get_singular_extra_node( WP_Post $post, array $options ): array {
    $configured = saifr_schema_get_content_node( $post );
    if ( ! empty( $configured ) ) {
        return $configured;
    }

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
        '@id'      => trailingslashit( $url ) . '#sernicola-labs-ai-friendly-work',
        'url'      => $url,
        'name'     => get_the_title( $post ),
        'license'  => esc_url_raw( $license ),
        'author'   => [ '@id' => saifr_schema_get_identity_node()['@id'] ],
        'isPartOf' => [ '@id' => saifr_schema_webpage_id( $url ) ],
    ];
}

function saifr_schema_get_content_node( WP_Post $post ): array {
    $data = saifr_schema_get_content_schema_data( $post );
    if ( empty( $data ) ) {
        return [];
    }
    $type = $data['type'];
    $values = $data['values'];
    $url = get_permalink( $post );
    if ( ! is_string( $url ) || $url === '' ) {
        return [];
    }
    // Un Event senza data di inizio non è valido per i motori di ricerca: meglio non emetterlo.
    if ( $type === 'Event' && empty( $values['startDate'] ) ) {
        return [];
    }
    $metadata = class_exists( 'SaifrMetadata' ) ? SaifrMetadata::extract( $post ) : [];
    $name = (string) ( $values['name'] ?? ( $metadata['title'] ?? get_the_title( $post ) ) );
    $description = (string) ( $values['description'] ?? ( $metadata['description'] ?? '' ) );
    $fragments = [ 'Course' => 'course', 'Event' => 'event', 'Service' => 'service', 'FAQPage' => 'faq' ];
    $identity_ref = [ '@id' => saifr_schema_get_identity_node()['@id'] ];
    $node = [
        '@type' => $type,
        '@id' => trailingslashit( $url ) . '#' . $fragments[ $type ],
        'url' => $url,
        'name' => $name,
        'inLanguage' => get_locale(),
        'mainEntityOfPage' => [ '@id' => saifr_schema_webpage_id( $url ) ],
    ];
    if ( $description !== '' ) {
        $node['description'] = $description;
    }
    if ( ! empty( $metadata['featured_image'] ) ) {
        $node['image'] = esc_url_raw( (string) $metadata['featured_image'] );
    }

    $offer = saifr_schema_build_offer( (string) ( $values['price'] ?? '' ), (string) ( $values['priceCurrency'] ?? '' ), $url );

    if ( $type === 'Course' ) {
        foreach ( [ 'courseCode', 'educationalLevel' ] as $key ) {
            if ( ! empty( $values[ $key ] ) ) {
                $node[ $key ] = $values[ $key ];
            }
        }
        $node['provider'] = $identity_ref;
        if ( ! empty( $values['startDate'] ) ) {
            $course_modes = [ 'offline' => 'Onsite', 'online' => 'Online', 'mixed' => 'Blended' ];
            $instance = [
                '@type' => 'CourseInstance',
                'courseMode' => $course_modes[ $data['attendanceMode'] ],
                'startDate' => $values['startDate'],
            ];
            if ( ! empty( $values['endDate'] ) ) {
                $instance['endDate'] = $values['endDate'];
            }
            $location = saifr_schema_get_content_location( $values, $data['attendanceMode'], $url );
            if ( ! empty( $location ) ) {
                $instance['location'] = $location;
            }
            $node['hasCourseInstance'] = [ $instance ];
        }
        if ( ! empty( $offer ) ) {
            $node['offers'] = [ $offer ];
        }
    } elseif ( $type === 'Event' ) {
        $attendance_modes = [ 'offline' => 'OfflineEventAttendanceMode', 'online' => 'OnlineEventAttendanceMode', 'mixed' => 'MixedEventAttendanceMode' ];
        $node['startDate'] = $values['startDate'];
        if ( ! empty( $values['endDate'] ) ) {
            $node['endDate'] = $values['endDate'];
        }
        $node['eventStatus'] = 'https://schema.org/EventScheduled';
        $node['eventAttendanceMode'] = 'https://schema.org/' . $attendance_modes[ $data['attendanceMode'] ];
        $location = saifr_schema_get_content_location( $values, $data['attendanceMode'], $url );
        if ( ! empty( $location ) ) {
            $node['location'] = $location;
        }
        $node['organizer'] = $identity_ref;
        if ( ! empty( $offer ) ) {
            $node['offers'] = [ $offer ];
            if ( $offer['price'] === '0' ) {
                $node['isAccessibleForFree'] = true;
            }
        }
    } elseif ( $type === 'Service' ) {
        foreach ( [ 'serviceType', 'areaServed' ] as $key ) {
            if ( ! empty( $values[ $key ] ) ) {
                $node[ $key ] = $values[ $key ];
            }
        }
        $node['provider'] = $identity_ref;
        if ( ! empty( $offer ) ) {
            $node['offers'] = [ $offer ];
        }
    } else {
        $node['mainEntity'] = [];
        $rows = preg_split( '/\r\n|\r|\n/', (string) ( $values['faq'] ?? '' ) );
        foreach ( is_array( $rows ) ? $rows : [] as $index => $row ) {
            $parts = array_map( 'trim', explode( '|', $row, 2 ) );
            if ( count( $parts ) !== 2 || $parts[0] === '' || $parts[1] === '' ) continue;
            $node['mainEntity'][] = [
                '@type' => 'Question',
                '@id' => trailingslashit( $url ) . '#faq-manual-' . ( $index + 1 ),
                'name' => sanitize_text_field( $parts[0] ),
                'acceptedAnswer' => [ '@type' => 'Answer', 'text' => wp_kses_post( $parts[1] ) ],
            ];
        }
        if ( empty( $node['mainEntity'] ) ) return [];
    }
    $schema = get_post_meta( $post->ID, '_saifr_schema', true );
    return (array) apply_filters( 'saifr_schema_content_node', $node, $post, is_array( $schema ) ? $schema : [], $data );
}

/**
 * Luogo di un Event o CourseInstance: sede indicata, sede principale o luogo virtuale.
 */
function saifr_schema_get_content_location( array $values, string $attendance_mode, string $url ) {
    $physical = [];
    $location_name = (string) ( $values['locationName'] ?? '' );
    $location_address = (string) ( $values['locationAddress'] ?? '' );
    if ( $location_name !== '' || $location_address !== '' ) {
        $physical = [ '@type' => 'Place' ];
        if ( $location_name !== '' ) $physical['name'] = $location_name;
        if ( $location_address !== '' ) $physical['address'] = $location_address;
    } elseif ( $attendance_mode !== 'online' && saifr_schema_has_place( saifr_schema_get_options() ) ) {
        $physical = [ '@id' => saifr_schema_home_id( 'place' ) ];
    }

    $virtual = [ '@type' => 'VirtualLocation', 'url' => $url ];
    if ( $attendance_mode === 'online' ) {
        return $virtual;
    }
    if ( $attendance_mode === 'mixed' ) {
        return empty( $physical ) ? $virtual : [ $physical, $virtual ];
    }
    return $physical;
}

function saifr_schema_merge_faq_nodes( array $manual, array $automatic ): array {
    $merged = $manual;
    foreach ( $automatic as $key => $value ) {
        if ( $key !== 'mainEntity' && ( ! isset( $merged[ $key ] ) || $merged[ $key ] === '' ) ) {
            $merged[ $key ] = $value;
        }
    }
    $entities = [];
    $seen = [];
    foreach ( [ (array) ( $manual['mainEntity'] ?? [] ), (array) ( $automatic['mainEntity'] ?? [] ) ] as $source ) {
        foreach ( $source as $entity ) {
            if ( ! is_array( $entity ) ) {
                continue;
            }
            $question = strtolower( trim( (string) ( $entity['name'] ?? '' ) ) );
            if ( $question === '' || isset( $seen[ $question ] ) ) {
                continue;
            }
            $seen[ $question ] = true;
            $entities[] = $entity;
        }
    }
    $merged['mainEntity'] = $entities;
    return $merged;
}

function saifr_schema_as_json_ld( array $graph ): array {
    if ( empty( $graph ) ) {
        return [];
    }

    return [
        '@context' => 'https://schema.org',
        '@graph'   => array_values( $graph ),
    ];
}

function saifr_schema_print_standalone(): void {
    if ( saifr_schema_output_mode() !== 'standalone' ) {
        return;
    }

    $data = saifr_schema_as_json_ld( saifr_schema_cleanup_graph( saifr_schema_get_graph() ) );
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
add_action( 'wp_head', 'saifr_schema_print_standalone', 20 );

add_filter(
    'rank_math/json_ld',
    function ( array $data ): array {
        if ( saifr_schema_output_mode() !== 'extend_rank_math' ) {
            return $data;
        }

        foreach ( saifr_schema_get_graph() as $index => $node ) {
            if ( ! is_array( $node ) || empty( $node['@id'] ) ) {
                continue;
            }

            $matched = false;
            foreach ( $data as $key => $existing ) {
                if ( ! is_array( $existing ) || empty( $existing['@id'] ) || $existing['@id'] !== $node['@id'] ) {
                    continue;
                }

                $data[ $key ] = saifr_schema_merge_node( $existing, $node );
                $matched = true;
                break;
            }

            if ( ! $matched ) {
                $key          = 'saifr_' . md5( (string) $node['@id'] . '_' . $index );
                $data[ $key ] = $node;
            }
        }

        return saifr_schema_cleanup_graph( $data );
    },
    99,
    1
);

add_filter(
    'wpseo_schema_graph',
    function ( array $graph ): array {
        if ( saifr_schema_output_mode() !== 'extend_yoast' ) {
            return $graph;
        }

        return saifr_schema_cleanup_graph( saifr_schema_merge_graph_nodes( $graph, saifr_schema_get_graph() ) );
    },
    99,
    1
);
