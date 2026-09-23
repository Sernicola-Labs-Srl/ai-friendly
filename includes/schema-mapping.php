<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════════
//  SCHEMA — Mappatura automatica per tipo di contenuto
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Tipi Schema generabili per singolo contenuto (metabox).
 */
function saifr_schema_content_types(): array {
    return [ 'Course', 'Event', 'Service', 'FAQPage' ];
}

/**
 * Tipi Schema assegnabili in automatico a un intero post type.
 */
function saifr_schema_rule_types(): array {
    return [ 'Event', 'Course', 'Service' ];
}

function saifr_schema_attendance_modes(): array {
    return [ 'offline', 'online', 'mixed' ];
}

/**
 * Campi mappabili: chiave => tipi Schema che li usano.
 */
function saifr_schema_rule_fields(): array {
    return [
        'startDate'        => [ 'Event', 'Course' ],
        'endDate'          => [ 'Event', 'Course' ],
        'locationName'     => [ 'Event', 'Course' ],
        'locationAddress'  => [ 'Event', 'Course' ],
        'price'            => [ 'Event', 'Course', 'Service' ],
        'priceCurrency'    => [ 'Event', 'Course', 'Service' ],
        'courseCode'       => [ 'Course' ],
        'educationalLevel' => [ 'Course' ],
        'serviceType'      => [ 'Service' ],
        'areaServed'       => [ 'Service' ],
    ];
}

function saifr_schema_sanitize_type_rules( array $rows ): array {
    $public = get_post_types( [ 'public' => true ], 'names' );
    unset( $public['attachment'] );

    $rules = [];
    foreach ( $rows as $post_type => $row ) {
        $post_type = sanitize_key( (string) $post_type );
        if ( ! is_array( $row ) || ! isset( $public[ $post_type ] ) ) {
            continue;
        }

        $type = sanitize_text_field( (string) ( $row['type'] ?? '' ) );
        if ( ! in_array( $type, saifr_schema_rule_types(), true ) ) {
            continue;
        }

        $mode = sanitize_key( (string) ( $row['attendanceMode'] ?? 'offline' ) );
        $rule = [
            'type'           => $type,
            'attendanceMode' => in_array( $mode, saifr_schema_attendance_modes(), true ) ? $mode : 'offline',
        ];
        foreach ( array_keys( saifr_schema_rule_fields() ) as $field ) {
            $rule[ $field ] = substr( sanitize_text_field( (string) ( $row[ $field ] ?? '' ) ), 0, 191 );
        }
        $rules[ $post_type ] = $rule;
    }

    return $rules;
}

function saifr_schema_get_type_rule( string $post_type ): array {
    $options = saifr_schema_get_options();
    $rules   = isset( $options['schema_type_rules'] ) && is_array( $options['schema_type_rules'] ) ? $options['schema_type_rules'] : [];
    $rule    = isset( $rules[ $post_type ] ) && is_array( $rules[ $post_type ] ) ? $rules[ $post_type ] : [];

    if ( ! in_array( (string) ( $rule['type'] ?? '' ), saifr_schema_rule_types(), true ) ) {
        $rule = [];
    }

    return (array) apply_filters( 'saifr_schema_type_rule', $rule, $post_type );
}

/**
 * Legge un valore da una sorgente dichiarata nella mappatura.
 *
 * Sintassi: `meta:chiave` (o solo `chiave`), `acf:campo`, `tax:tassonomia`, `text:valore fisso`.
 */
function saifr_schema_resolve_source( WP_Post $post, string $source, string $kind = 'text' ): string {
    $source = trim( $source );
    if ( $source === '' ) {
        return '';
    }

    $prefix  = 'meta';
    $locator = $source;
    if ( preg_match( '/^(meta|acf|tax|text):(.*)$/s', $source, $matches ) ) {
        $prefix  = $matches[1];
        $locator = trim( $matches[2] );
    }
    if ( $locator === '' ) {
        return '';
    }

    $value = '';
    if ( $prefix === 'text' ) {
        $value = $locator;
    } elseif ( $prefix === 'tax' ) {
        $terms = taxonomy_exists( $locator ) ? get_the_terms( $post, $locator ) : false;
        if ( is_array( $terms ) ) {
            $value = implode( ', ', wp_list_pluck( $terms, 'name' ) );
        }
    } elseif ( $prefix === 'acf' && function_exists( 'get_field' ) ) {
        // Le date si leggono non formattate (Ymd / Y-m-d H:i:s), il resto con il formato di ACF.
        $value = get_field( $locator, $post->ID, $kind !== 'date' );
    } else {
        $value = get_post_meta( $post->ID, $locator, true );
    }

    $value = saifr_schema_flatten_source_value( $value );

    return trim( (string) apply_filters( 'saifr_schema_mapped_value', $value, $source, $post, $kind ) );
}

function saifr_schema_flatten_source_value( $value ): string {
    if ( $value instanceof WP_Post ) {
        return get_the_title( $value );
    }
    if ( $value instanceof WP_Term ) {
        return $value->name;
    }
    if ( is_array( $value ) ) {
        // Campi mappa (ACF Google Map, indirizzi strutturati) e oggetti con titolo.
        foreach ( [ 'address', 'name', 'title', 'label', 'value' ] as $key ) {
            if ( isset( $value[ $key ] ) && is_scalar( $value[ $key ] ) && trim( (string) $value[ $key ] ) !== '' ) {
                return (string) $value[ $key ];
            }
        }
        $parts = [];
        foreach ( $value as $item ) {
            $item = saifr_schema_flatten_source_value( $item );
            if ( $item !== '' ) {
                $parts[] = $item;
            }
        }
        return implode( ', ', $parts );
    }
    if ( is_bool( $value ) || $value === null ) {
        return '';
    }
    return is_scalar( $value ) ? wp_strip_all_tags( (string) $value, true ) : '';
}

/**
 * Converte i formati data più comuni (ISO, Ymd di ACF, timestamp, gg/mm/aaaa) in ISO 8601.
 * Restituisce `Y-m-d` per le sole date e un DateTime W3C quando è presente l'orario.
 */
function saifr_schema_parse_datetime( string $value ): string {
    $value  = trim( $value );
    $parsed = '';
    $tz     = wp_timezone();

    if ( $value === '' ) {
        return '';
    }

    if ( preg_match( '/^\d{9,11}$/', $value ) ) {
        $parsed = ( new DateTimeImmutable( '@' . $value ) )->setTimezone( $tz )->format( DATE_W3C );
    } elseif ( preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?(\.\d+)?(Z|[+-]\d{2}:?\d{2})$/', $value ) ) {
        try {
            $parsed = ( new DateTimeImmutable( $value ) )->format( DATE_W3C );
        } catch ( Exception $exception ) {
            $parsed = '';
        }
    } else {
        $formats = [
            'Y-m-d H:i:s' => true,
            'Y-m-d H:i'   => true,
            'Y-m-d\TH:i:s' => true,
            'Y-m-d\TH:i'  => true,
            'd/m/Y H:i:s' => true,
            'd/m/Y H:i'   => true,
            'j/n/Y H:i'   => true,
            'Ymd'         => false,
            'Y-m-d'       => false,
            'd/m/Y'       => false,
            'j/n/Y'       => false,
            'd-m-Y'       => false,
            'd.m.Y'       => false,
        ];
        foreach ( $formats as $format => $has_time ) {
            $date = DateTimeImmutable::createFromFormat( '!' . $format, $value, $tz );
            if ( $date instanceof DateTimeImmutable && $date->format( $format ) === $value ) {
                $parsed = $date->format( $has_time ? DATE_W3C : 'Y-m-d' );
                break;
            }
        }
    }

    return (string) apply_filters( 'saifr_schema_parse_datetime', $parsed, $value );
}

/**
 * Normalizza un prezzo scritto a mano ("€ 1.200,50", "600,00", "Gratuito") nel formato Schema.org.
 */
function saifr_schema_normalize_price( string $value ): string {
    $value = trim( $value );
    if ( $value === '' ) {
        return '';
    }
    if ( preg_match( '/^(gratuit|gratis|free)/i', $value ) ) {
        return '0';
    }

    $value = (string) preg_replace( '/[^\d.,]/', '', $value );
    if ( ! preg_match( '/\d/', $value ) ) {
        return '';
    }

    $last_comma = strrpos( $value, ',' );
    $last_dot   = strrpos( $value, '.' );
    if ( $last_comma !== false && $last_dot !== false ) {
        $thousands = $last_comma > $last_dot ? '.' : ',';
        $value     = str_replace( [ $thousands, ',' ], [ '', '.' ], $value );
    } elseif ( $last_comma !== false ) {
        $value = preg_match( '/^\d+,\d{1,2}$/', $value ) ? str_replace( ',', '.', $value ) : str_replace( ',', '', $value );
    } elseif ( $last_dot !== false && preg_match( '/^\d{1,3}(\.\d{3})+$/', $value ) ) {
        $value = str_replace( '.', '', $value );
    }

    return is_numeric( $value ) ? $value : '';
}

/**
 * Interpreta orari scritti come "Mo-Fr 09:00-13:00, 14:00-18:00; Sa 09:00-12:00"
 * (abbreviazioni inglesi o italiane). Restituisce [] se il testo non è riconosciuto.
 */
function saifr_schema_parse_hours_text( string $value ): array {
    $order = [ 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ];
    $map   = [
        'mo' => 0, 'tu' => 1, 'we' => 2, 'th' => 3, 'fr' => 4, 'sa' => 5, 'su' => 6,
        'lu' => 0, 'ma' => 1, 'me' => 2, 'gi' => 3, 've' => 4, 'do' => 6,
    ];

    $specs = [];
    foreach ( preg_split( '/\s*;\s*/', trim( $value ) ) ?: [] as $segment ) {
        if ( $segment === '' ) {
            continue;
        }
        if ( ! preg_match( '/^([A-Za-z]{2}(?:\s*[-,]\s*[A-Za-z]{2})*)\s+(.+)$/', $segment, $parts ) ) {
            return [];
        }

        $days = [];
        foreach ( preg_split( '/\s*,\s*/', $parts[1] ) ?: [] as $piece ) {
            $range = array_map( static fn( string $day ): string => strtolower( trim( $day ) ), explode( '-', $piece ) );
            if ( ! isset( $map[ $range[0] ] ) || ( isset( $range[1] ) && ! isset( $map[ $range[1] ] ) ) ) {
                return [];
            }
            $from = $map[ $range[0] ];
            $to   = isset( $range[1] ) ? $map[ $range[1] ] : $from;
            if ( $to < $from ) {
                return [];
            }
            for ( $index = $from; $index <= $to; $index++ ) {
                $days[] = $order[ $index ];
            }
        }

        $time_pattern = '/(\d{1,2})[:.](\d{2})\s*-\s*(\d{1,2})[:.](\d{2})/';
        if ( ! preg_match_all( $time_pattern, $parts[2], $times, PREG_SET_ORDER ) || trim( (string) preg_replace( $time_pattern, '', $parts[2] ), " ,e&\t" ) !== '' ) {
            return [];
        }

        foreach ( $times as $time ) {
            $specs[] = [
                '@type'     => 'OpeningHoursSpecification',
                'dayOfWeek' => array_values( array_unique( $days ) ),
                'opens'     => sprintf( '%02d:%s', (int) $time[1], $time[2] ),
                'closes'    => sprintf( '%02d:%s', (int) $time[3], $time[4] ),
            ];
        }
    }

    return $specs;
}

/**
 * Dati Schema effettivi di un contenuto: valori del metabox, completati dalla mappatura del post type.
 *
 * @return array{type:string, values:array, sources:array, attendanceMode:string}|array{}
 */
function saifr_schema_get_content_schema_data( WP_Post $post ): array {
    $manual = get_post_meta( $post->ID, '_saifr_schema', true );
    $manual = is_array( $manual ) ? $manual : [];
    $rule   = saifr_schema_get_type_rule( $post->post_type );

    $manual_type = (string) ( $manual['type'] ?? '' );
    if ( $manual_type === 'none' ) {
        return [];
    }
    $type = in_array( $manual_type, saifr_schema_content_types(), true ) ? $manual_type : (string) ( $rule['type'] ?? '' );
    if ( $type === '' ) {
        return [];
    }

    $values  = [];
    $sources = [];
    foreach ( saifr_schema_rule_fields() as $field => $types ) {
        $kind  = in_array( $field, [ 'startDate', 'endDate' ], true ) ? 'date' : 'text';
        $value = trim( (string) ( $manual[ $field ] ?? '' ) );
        $from  = 'manual';
        if ( $value === '' && ! empty( $rule[ $field ] ) ) {
            $value = saifr_schema_resolve_source( $post, (string) $rule[ $field ], $kind );
            $from  = 'auto';
        }
        if ( $kind === 'date' ) {
            $value = saifr_schema_parse_datetime( $value );
        } elseif ( $field === 'price' ) {
            $value = saifr_schema_normalize_price( $value );
        }
        if ( $value !== '' ) {
            $values[ $field ]  = $value;
            $sources[ $field ] = $from;
        }
    }

    foreach ( [ 'name', 'description', 'faq' ] as $field ) {
        $value = trim( (string) ( $manual[ $field ] ?? '' ) );
        if ( $value !== '' ) {
            $values[ $field ]  = $value;
            $sources[ $field ] = 'manual';
        }
    }

    $mode = (string) ( $manual['attendanceMode'] ?? '' );
    if ( ! in_array( $mode, saifr_schema_attendance_modes(), true ) ) {
        $mode = in_array( (string) ( $rule['attendanceMode'] ?? '' ), saifr_schema_attendance_modes(), true ) ? (string) $rule['attendanceMode'] : 'offline';
    }

    return [
        'type'           => $type,
        'values'         => $values,
        'sources'        => $sources,
        'attendanceMode' => $mode,
    ];
}

/**
 * Costruisce un Offer, con priceSpecification quando servono IVA o periodicità.
 */
function saifr_schema_build_offer( string $price, string $currency, string $url = '', string $vat_included = '', string $billing_period = '' ): array {
    $price = saifr_schema_normalize_price( $price );
    if ( $price === '' ) {
        return [];
    }

    $currency = strtoupper( sanitize_text_field( $currency ) );
    $offer    = [
        '@type' => 'Offer',
        'price' => $price,
    ];
    if ( $currency !== '' ) {
        $offer['priceCurrency'] = $currency;
    }
    if ( $url !== '' ) {
        $offer['url'] = esc_url_raw( $url );
    }

    $periods = [
        'month' => [ 'unitCode' => 'MON', 'billingDuration' => 'P1M' ],
        'year'  => [ 'unitCode' => 'ANN', 'billingDuration' => 'P1Y' ],
    ];
    if ( in_array( $vat_included, [ 'yes', 'no' ], true ) || isset( $periods[ $billing_period ] ) ) {
        $specification = [
            '@type' => 'UnitPriceSpecification',
            'price' => $price,
        ];
        if ( $currency !== '' ) {
            $specification['priceCurrency'] = $currency;
        }
        if ( in_array( $vat_included, [ 'yes', 'no' ], true ) ) {
            $specification['valueAddedTaxIncluded'] = $vat_included === 'yes';
        }
        if ( isset( $periods[ $billing_period ] ) ) {
            $specification = array_merge( $specification, $periods[ $billing_period ] );
        }
        $offer['priceSpecification'] = $specification;
    }

    return $offer;
}

/**
 * Riferimento al nodo WebPage della pagina, allineato al plugin SEO che si sta estendendo.
 * Yoast usa il permalink come @id della WebPage; Rank Math e l'output standalone usano `#webpage`.
 */
function saifr_schema_webpage_id( string $url ): string {
    if ( function_exists( 'saifr_schema_output_mode' ) && saifr_schema_output_mode() === 'extend_yoast' ) {
        return $url;
    }
    return trailingslashit( $url ) . '#webpage';
}

/**
 * Riepilogo leggibile di data e luogo, usato in llms.txt.
 */
function saifr_schema_get_llms_facts( WP_Post $post ): string {
    $data = saifr_schema_get_content_schema_data( $post );
    if ( empty( $data['values']['startDate'] ) || ! in_array( $data['type'], [ 'Event', 'Course' ], true ) ) {
        return '';
    }

    $values = $data['values'];
    $facts  = [];
    try {
        $has_time = strlen( $values['startDate'] ) > 10;
        $start    = new DateTimeImmutable( $values['startDate'], wp_timezone() );
        $format   = get_option( 'date_format' ) . ( $has_time ? ' ' . get_option( 'time_format' ) : '' );
        $facts[]  = wp_date( $format, $start->getTimestamp() );
    } catch ( Exception $exception ) {
        return '';
    }

    $place = $values['locationName'] ?? ( $values['locationAddress'] ?? '' );
    if ( $place === '' && $data['attendanceMode'] === 'online' ) {
        $place = __( 'online', 'sernicola-labs-ai-friendly' );
    }
    if ( $place !== '' ) {
        $facts[] = $place;
    }

    return (string) apply_filters( 'saifr_schema_llms_facts', implode( ' · ', $facts ), $post, $data );
}

/**
 * Chiavi meta e campi ACF usati da un post type, per suggerire le sorgenti nella UI.
 */
function saifr_schema_discover_sources( string $post_type ): array {
    $cache_key = 'saifr_schema_sources_' . md5( $post_type );
    $cached    = get_transient( $cache_key );
    if ( is_array( $cached ) ) {
        return $cached;
    }

    global $wpdb;
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin-only discovery of meta keys, cached in a transient below.
    $keys = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT DISTINCT pm.meta_key FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE p.post_type = %s AND p.post_status = 'publish'
             ORDER BY pm.meta_key ASC LIMIT 300",
            $post_type
        )
    );
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

    $keys    = is_array( $keys ) ? $keys : [];
    $sources = [];
    foreach ( $keys as $key ) {
        $key = (string) $key;
        if ( preg_match( '/^(_edit_|_wp_|_saifr|_oembed|_thumbnail_id$|_encloseme$|_pingme$)/', $key ) ) {
            continue;
        }
        // ACF salva `campo` e `_campo` (riferimento al campo): si suggerisce solo `acf:campo`.
        if ( str_starts_with( $key, '_' ) && in_array( substr( $key, 1 ), $keys, true ) ) {
            $sources[] = 'acf:' . substr( $key, 1 );
            continue;
        }
        if ( in_array( '_' . $key, $keys, true ) ) {
            continue;
        }
        $sources[] = 'meta:' . $key;
    }

    foreach ( get_object_taxonomies( $post_type, 'names' ) as $taxonomy ) {
        $sources[] = 'tax:' . $taxonomy;
    }

    $sources = array_values( array_unique( $sources ) );
    set_transient( $cache_key, $sources, HOUR_IN_SECONDS );

    return $sources;
}
