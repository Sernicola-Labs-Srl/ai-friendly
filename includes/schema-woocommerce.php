<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════════
//  SCHEMA — Product WooCommerce per le schede commerciante
// ═══════════════════════════════════════════════════════════════════════════════

function saifr_woo_schema_is_enabled(): bool {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return false;
    }
    $options = saifr_schema_get_options();
    return (bool) apply_filters( 'saifr_woo_schema_enabled', ! empty( $options['woo_schema_enabled'] ), $options );
}

function saifr_woo_return_categories(): array {
    return [
        'finite'        => 'MerchantReturnFiniteReturnWindow',
        'unlimited'     => 'MerchantReturnUnlimitedWindow',
        'not_permitted' => 'MerchantReturnNotPermitted',
    ];
}

function saifr_woo_return_methods(): array {
    return [
        'mail'  => 'ReturnByMail',
        'store' => 'ReturnInStore',
        'kiosk' => 'ReturnAtKiosk',
    ];
}

function saifr_woo_return_fees(): array {
    return [
        'free'     => 'FreeReturn',
        'customer' => 'ReturnFeesCustomerResponsibility',
        'shipping' => 'ReturnShippingFees',
    ];
}

/**
 * Codici paese ISO 3166-1 alpha-2 da un elenco libero ("IT, SM").
 */
function saifr_woo_parse_countries( string $value ): array {
    preg_match_all( '/\b[A-Za-z]{2}\b/', $value, $matches );
    return array_values( array_unique( array_map( 'strtoupper', $matches[0] ?? [] ) ) );
}

function saifr_woo_store_currency(): string {
    return function_exists( 'get_woocommerce_currency' ) ? (string) get_woocommerce_currency() : '';
}

function saifr_woo_store_country(): string {
    if ( function_exists( 'WC' ) && WC()->countries ) {
        return (string) WC()->countries->get_base_country();
    }
    return '';
}

function saifr_woo_quantitative_days( string $min, string $max ): array {
    $value = [ '@type' => 'QuantitativeValue', 'unitCode' => 'DAY' ];
    if ( is_numeric( $min ) ) {
        $value['minValue'] = (int) $min;
    }
    if ( is_numeric( $max ) ) {
        $value['maxValue'] = (int) $max;
    }
    return count( $value ) > 2 ? $value : [];
}

/**
 * Nodi OfferShippingDetails per un'offerta al prezzo indicato.
 */
function saifr_woo_shipping_details( ?float $price, string $currency ): array {
    $options = saifr_schema_get_options();
    $rows    = isset( $options['woo_shipping_rules'] ) && is_array( $options['woo_shipping_rules'] ) ? $options['woo_shipping_rules'] : [];
    $details = [];

    foreach ( $rows as $row ) {
        if ( ! is_array( $row ) ) {
            continue;
        }
        $countries = saifr_woo_parse_countries( (string) ( $row['countries'] ?? '' ) );
        $rate      = saifr_schema_normalize_price( (string) ( $row['rate'] ?? '' ) );
        if ( empty( $countries ) || $rate === '' ) {
            continue;
        }

        $threshold = saifr_schema_normalize_price( (string) ( $row['freeThreshold'] ?? '' ) );
        if ( $threshold !== '' && $price !== null && $price >= (float) $threshold ) {
            $rate = '0';
        }

        $destinations = array_map(
            static fn( string $country ): array => [ '@type' => 'DefinedRegion', 'addressCountry' => $country ],
            $countries
        );
        $detail = [
            '@type'               => 'OfferShippingDetails',
            'shippingRate'        => [
                '@type'    => 'MonetaryAmount',
                'value'    => (float) $rate,
                'currency' => $currency,
            ],
            'shippingDestination' => count( $destinations ) === 1 ? $destinations[0] : $destinations,
        ];

        $handling = saifr_woo_quantitative_days( (string) ( $row['handlingMin'] ?? '' ), (string) ( $row['handlingMax'] ?? '' ) );
        $transit  = saifr_woo_quantitative_days( (string) ( $row['transitMin'] ?? '' ), (string) ( $row['transitMax'] ?? '' ) );
        if ( ! empty( $handling ) || ! empty( $transit ) ) {
            $detail['deliveryTime'] = [ '@type' => 'ShippingDeliveryTime' ];
            if ( ! empty( $handling ) ) {
                $detail['deliveryTime']['handlingTime'] = $handling;
            }
            if ( ! empty( $transit ) ) {
                $detail['deliveryTime']['transitTime'] = $transit;
            }
        }

        $details[] = $detail;
    }

    return $details;
}

function saifr_woo_return_policy( string $currency ): array {
    $options  = saifr_schema_get_options();
    $category = (string) ( $options['woo_return_category'] ?? '' );
    $categories = saifr_woo_return_categories();
    if ( ! isset( $categories[ $category ] ) ) {
        return [];
    }

    $countries = saifr_woo_parse_countries( (string) ( $options['woo_return_countries'] ?? '' ) );
    $store_country = saifr_woo_store_country();
    if ( empty( $countries ) && $store_country !== '' ) {
        $countries = [ $store_country ];
    }

    $policy = [ '@type' => 'MerchantReturnPolicy' ];
    if ( ! empty( $countries ) ) {
        $policy['applicableCountry'] = count( $countries ) === 1 ? $countries[0] : $countries;
    }
    if ( $store_country !== '' || ! empty( $countries ) ) {
        $policy['returnPolicyCountry'] = $store_country !== '' ? $store_country : $countries[0];
    }
    $policy['returnPolicyCategory'] = 'https://schema.org/' . $categories[ $category ];

    if ( $category === 'not_permitted' ) {
        return $policy;
    }

    $days = (string) ( $options['woo_return_days'] ?? '' );
    if ( $category === 'finite' && is_numeric( $days ) ) {
        $policy['merchantReturnDays'] = (int) $days;
    }

    $methods = saifr_woo_return_methods();
    $method  = (string) ( $options['woo_return_method'] ?? '' );
    if ( isset( $methods[ $method ] ) ) {
        $policy['returnMethod'] = 'https://schema.org/' . $methods[ $method ];
    }

    $fees = saifr_woo_return_fees();
    $fee  = (string) ( $options['woo_return_fees'] ?? '' );
    if ( isset( $fees[ $fee ] ) ) {
        $policy['returnFees'] = 'https://schema.org/' . $fees[ $fee ];
        $amount = saifr_schema_normalize_price( (string) ( $options['woo_return_fee_amount'] ?? '' ) );
        if ( $fee === 'shipping' && $amount !== '' ) {
            $policy['returnShippingFeesAmount'] = [
                '@type'    => 'MonetaryAmount',
                'value'    => (float) $amount,
                'currency' => $currency,
            ];
        }
    }

    return $policy;
}

/**
 * Brand del prodotto: sorgente configurata (es. `tax:product_brand`), altrimenti brand predefinito.
 */
function saifr_woo_brand_name( WC_Product $product ): string {
    $options = saifr_schema_get_options();
    $post    = get_post( $product->get_parent_id() ?: $product->get_id() );
    $source  = trim( (string) ( $options['woo_brand_source'] ?? '' ) );
    $brand   = $post instanceof WP_Post && $source !== '' ? saifr_schema_resolve_source( $post, $source ) : '';
    if ( $brand === '' ) {
        $brand = trim( (string) ( $options['woo_brand_name'] ?? '' ) );
    }
    return $brand;
}

/**
 * Data da cui vale il prezzo: inizio della promozione in corso, altrimenti ultima modifica del prodotto.
 */
function saifr_woo_valid_from( WC_Product $product ): string {
    $sale_from = $product->get_date_on_sale_from();
    $modified  = $product->get_date_modified();
    if ( $product->is_on_sale() && $sale_from ) {
        return $sale_from->date( 'Y-m-d' );
    }
    return $modified ? $modified->date( 'Y-m-d' ) : '';
}

/**
 * Completa un nodo Product (WooCommerce, Yoast o Rank Math) con brand, spedizioni, resi e validFrom.
 */
function saifr_woo_enrich_product_markup( array $markup, WC_Product $product ): array {
    $options = saifr_schema_get_options();

    $brand = saifr_woo_brand_name( $product );
    $current_brand = $markup['brand'] ?? null;
    $has_brand = is_array( $current_brand ) ? ! empty( $current_brand['name'] ) || ! empty( $current_brand[0] ) : ! empty( $current_brand );
    if ( $brand !== '' && ! $has_brand ) {
        $markup['brand'] = [ '@type' => 'Brand', 'name' => $brand ];
    }

    if ( empty( $markup['offers'] ) || ! is_array( $markup['offers'] ) ) {
        return (array) apply_filters( 'saifr_woo_product_markup', $markup, $product );
    }

    // WooCommerce usa una lista di offerte, Rank Math e Yoast spesso un singolo oggetto.
    $single = ! saifr_schema_is_list( $markup['offers'] );
    $offers = $single ? [ $markup['offers'] ] : $markup['offers'];
    $valid_from = ! empty( $options['woo_valid_from'] ) ? saifr_woo_valid_from( $product ) : '';

    foreach ( $offers as $index => $offer ) {
        if ( ! is_array( $offer ) ) {
            continue;
        }
        $currency = (string) ( $offer['priceCurrency'] ?? saifr_woo_store_currency() );
        $price = null;
        if ( isset( $offer['price'] ) && is_numeric( $offer['price'] ) ) {
            $price = (float) $offer['price'];
        } elseif ( isset( $offer['lowPrice'] ) && is_numeric( $offer['lowPrice'] ) ) {
            $price = (float) $offer['lowPrice'];
        }

        $shipping = saifr_woo_shipping_details( $price, $currency );
        if ( ! empty( $shipping ) && empty( $offer['shippingDetails'] ) ) {
            $offers[ $index ]['shippingDetails'] = count( $shipping ) === 1 ? $shipping[0] : $shipping;
        }

        $policy = saifr_woo_return_policy( $currency );
        if ( ! empty( $policy ) && empty( $offer['hasMerchantReturnPolicy'] ) ) {
            $offers[ $index ]['hasMerchantReturnPolicy'] = $policy;
        }

        if ( $valid_from !== '' ) {
            if ( empty( $offer['validFrom'] ) ) {
                $offers[ $index ]['validFrom'] = $valid_from;
            }
            $specifications = $offer['priceSpecification'] ?? null;
            if ( is_array( $specifications ) ) {
                $spec_list = saifr_schema_is_list( $specifications );
                foreach ( $spec_list ? $specifications : [ $specifications ] as $spec_index => $spec ) {
                    if ( ! is_array( $spec ) || ! empty( $spec['validFrom'] ) ) {
                        continue;
                    }
                    if ( $spec_list ) {
                        $offers[ $index ]['priceSpecification'][ $spec_index ]['validFrom'] = $valid_from;
                    } else {
                        $offers[ $index ]['priceSpecification']['validFrom'] = $valid_from;
                    }
                }
            }
        }
    }

    $markup['offers'] = $single ? $offers[0] : $offers;

    return (array) apply_filters( 'saifr_woo_product_markup', $markup, $product );
}

add_filter(
    'woocommerce_structured_data_product',
    function ( $markup, $product ) {
        if ( ! is_array( $markup ) || ! $product instanceof WC_Product || ! saifr_woo_schema_is_enabled() ) {
            return $markup;
        }
        return saifr_woo_enrich_product_markup( $markup, $product );
    },
    20,
    2
);

/**
 * Stessa logica per i nodi Product generati da Yoast WooCommerce SEO o Rank Math,
 * che sostituiscono i dati strutturati di WooCommerce.
 */
function saifr_woo_enrich_graph_products( array $graph ): array {
    if ( ! saifr_woo_schema_is_enabled() || ! is_singular( 'product' ) || ! function_exists( 'wc_get_product' ) ) {
        return $graph;
    }
    $product = wc_get_product( get_queried_object_id() );
    if ( ! $product instanceof WC_Product ) {
        return $graph;
    }

    foreach ( $graph as $key => $node ) {
        if ( ! is_array( $node ) || ! in_array( 'Product', (array) ( $node['@type'] ?? [] ), true ) ) {
            continue;
        }
        $graph[ $key ] = saifr_woo_enrich_product_markup( $node, $product );
    }

    return $graph;
}
add_filter( 'rank_math/json_ld', 'saifr_woo_enrich_graph_products', 120 );
add_filter( 'wpseo_schema_graph', 'saifr_woo_enrich_graph_products', 120 );
