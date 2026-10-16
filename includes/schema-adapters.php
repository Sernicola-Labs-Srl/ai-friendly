<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** Generic Schema adapters. Site values are supplied through filters. */
function saifr_schema_apply_site_adapters( array $graph ): array {
    if ( ! saifr_schema_is_enabled() ) return $graph;
    $post = is_singular() ? get_post() : null;
    $page_types = (array) apply_filters( 'saifr_schema_page_type_overrides', [] );
    $organization_rules = (array) apply_filters( 'saifr_schema_organization_post_rules', [] );
    $article_rules = (array) apply_filters( 'saifr_schema_article_rules', [] );
    $organization_rule = [];
    $article_rule = [];
    if ( $post instanceof WP_Post ) {
        foreach ( $organization_rules as $rule ) {
            if ( ! is_array( $rule ) || ( $rule['post_type'] ?? '' ) !== $post->post_type ) continue;
            $categories = (array) ( $rule['categories'] ?? [] );
            if ( $categories && ! array_filter( $categories, static fn( $category ) => has_category( $category, $post ) ) ) continue;
            $organization_rule = $rule;
            break;
        }
        foreach ( $article_rules as $rule ) {
            if ( ! is_array( $rule ) || ( $rule['post_type'] ?? '' ) !== $post->post_type ) continue;
            $categories = (array) ( $rule['categories'] ?? [] );
            if ( $categories && ! array_filter( $categories, static fn( $category ) => has_category( $category, $post ) ) ) continue;
            $article_rule = $rule;
            break;
        }
    }
    $url = $post instanceof WP_Post ? get_permalink( $post ) : '';
    $base = is_string( $url ) ? $url : '';
    $organization_fragment = sanitize_title( (string) ( $organization_rule['fragment'] ?? 'organization' ) );
    $organization_id = $base . '#' . $organization_fragment;
    $translations = (array) apply_filters( 'saifr_schema_global_localizations', [], get_locale() );
    $suppress_article = $post instanceof WP_Post && (bool) apply_filters( 'saifr_schema_suppress_article', isset( $page_types[ $post->ID ] ), $post );
    $has_faq = false;

    foreach ( $graph as $key => $node ) {
        if ( ! is_array( $node ) ) continue;
        $id = (string) ( $node['@id'] ?? '' );
        $types = (array) ( $node['@type'] ?? [] );
        if ( in_array( 'FAQPage', $types, true ) ) $has_faq = true;
        if ( $post instanceof WP_Post && isset( $page_types[ $post->ID ] ) && $id === $base . '#webpage' ) {
            $allowed = [ 'AboutPage', 'ContactPage', 'CollectionPage' ];
            if ( in_array( $page_types[ $post->ID ], $allowed, true ) ) $node['@type'] = $page_types[ $post->ID ];
        }
        if ( $organization_rule && ( $key === 'richSnippet' || $id === $base . '#richSnippet' || $id === $base . '#author' ) ) {
            unset( $graph[ $key ] );
            continue;
        }
        if ( $suppress_article && ( $key === 'richSnippet' || $id === $base . '#richSnippet' || $id === $base . '#author' ) ) {
            unset( $graph[ $key ] );
            continue;
        }
        if ( $organization_rule && $id === $base . '#webpage' ) {
            $node['about'] = [ '@id' => $organization_id ];
            $node['mainEntity'] = [ '@id' => $organization_id ];
        }
        if ( $article_rule && ( $key === 'richSnippet' || $id === $base . '#richSnippet' ) ) {
            $node['@type'] = 'Article';
            $node['headline'] = wp_html_excerpt( html_entity_decode( get_the_title( $post ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ), 110, '' );
            if ( ! empty( $article_rule['section'] ) ) $node['articleSection'] = sanitize_text_field( $article_rule['section'] );
        }
        if ( isset( $translations[ $id ] ) && is_array( $translations[ $id ] ) ) {
            $node = array_replace( $node, $translations[ $id ] );
        }
        $graph[ $key ] = $node;
    }

    if ( $organization_rule && $base !== '' ) {
        $exists = false;
        foreach ( $graph as $node ) {
            if ( is_array( $node ) && ( $node['@id'] ?? '' ) === $organization_id ) {
                $exists = true;
                break;
            }
        }
        if ( ! $exists ) {
            $organization = [ '@type' => 'Organization', '@id' => $organization_id, 'name' => get_the_title( $post ), 'mainEntityOfPage' => [ '@id' => $base . '#webpage' ] ];
            $site = $organization_rule['urls'][ $post->ID ] ?? '';
            if ( is_string( $site ) && wp_http_validate_url( $site ) ) {
                $organization['url'] = esc_url_raw( $site );
                $organization['sameAs'] = [ esc_url_raw( $site ) ];
            }
            $graph['saifrOrganizationPost'] = $organization;
        }
    }

    if ( ! $has_faq && $post instanceof WP_Post && $base !== '' ) {
        $config = (array) apply_filters( 'saifr_schema_acf_faq_config', [], $post );
        if ( ! empty( $config['enabled'] ) && ! empty( $config['flag_key'] ) && ! empty( $config['question_prefix'] ) && ! empty( $config['answer_prefix'] ) ) {
            $flag = (string) $config['flag_key'];
            $flag_value = (string) ( $config['flag_value'] ?? '1' );
            if ( get_post_meta( $post->ID, $flag, true ) === $flag_value ) {
                $items = [];
                $limit = min( 50, max( 1, (int) ( $config['limit'] ?? 10 ) ) );
                for ( $index = 1; $index <= $limit; $index++ ) {
                    $question = trim( wp_strip_all_tags( (string) get_post_meta( $post->ID, $config['question_prefix'] . $index, true ) ) );
                    $answer = trim( wp_strip_all_tags( (string) get_post_meta( $post->ID, $config['answer_prefix'] . $index, true ) ) );
                    if ( $question === '' || $answer === '' ) continue;
                    $items[] = [ '@type' => 'Question', '@id' => $base . '#faq-' . ( count( $items ) + 1 ), 'name' => $question, 'acceptedAnswer' => [ '@type' => 'Answer', 'text' => $answer ] ];
                }
                if ( $items ) $graph['saifrAcfFaq'] = [ '@type' => 'FAQPage', '@id' => $base . '#faq', 'url' => $url, 'inLanguage' => str_replace( '_', '-', get_locale() ), 'mainEntity' => $items ];
            }
        }
    }
    return $graph;
}

add_filter( 'saifr_schema_graph', static function ( array $graph ): array {
    return saifr_schema_output_mode() === 'standalone' ? saifr_schema_apply_site_adapters( $graph ) : $graph;
}, 1000 );
add_filter( 'rank_math/json_ld', static function ( $graph ) {
    return is_array( $graph ) ? saifr_schema_apply_site_adapters( $graph ) : $graph;
}, 998 );
add_filter( 'wpseo_schema_graph', 'saifr_schema_apply_site_adapters', 998 );

add_filter( 'pll_rel_hreflang_attributes', static function ( $links ) {
    $default = function_exists( 'pll_default_language' ) ? pll_default_language( 'slug' ) : '';
    if ( is_array( $links ) && ! isset( $links['x-default'] ) && count( $links ) > 1 && $default !== '' && isset( $links[ $default ] ) ) {
        $links['x-default'] = $links[ $default ];
    }
    return $links;
} );
