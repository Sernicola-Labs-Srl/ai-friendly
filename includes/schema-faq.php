<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'SAIFR_FAQ_VERSION' ) ) {
    define( 'SAIFR_FAQ_VERSION', '2.0.0' );
}

// Evita redeclaration se il precedente snippet autonomo e ancora attivo.
// Non controllare una funzione dichiarata anche in questo file: PHP registra
// le funzioni top-level prima di eseguire il file e il guard scatterebbe sempre.
if ( function_exists( 'saifr_faq_extend_graph' ) ) {
    return;
}

function saifr_faq_is_enabled( ?WP_Post $post = null ): bool {
    $options = saifr_schema_get_options();
    $enabled = saifr_is_breakdance_active() && ! empty( $options['schema_breakdance_faq_enabled'] );
    return (bool) apply_filters( 'saifr_faq_enabled', $enabled, $post );
}

function saifr_faq_source_post(): ?WP_Post {
    if ( is_admin() || is_feed() || wp_doing_ajax() || ! is_singular() ) {
        return null;
    }
    $post = get_post();
    return $post instanceof WP_Post ? $post : null;
}

function saifr_faq_get_tree( int $post_id ): array {
    $raw = get_post_meta( $post_id, '_breakdance_data', true );
    if ( is_string( $raw ) ) {
        $raw = json_decode( $raw, true );
    }
    if ( ! is_array( $raw ) ) {
        return [];
    }

    $tree = $raw['tree_json_string'] ?? $raw;
    if ( is_string( $tree ) ) {
        $tree = json_decode( $tree, true );
    }
    if ( ! is_array( $tree ) || empty( $tree['root'] ) || ! is_array( $tree['root'] ) ) {
        return [];
    }
    return $tree['root'];
}

function saifr_faq_collect( array $node, array &$items, array &$seen_blocks ): void {
    $type = (string) ( $node['data']['type'] ?? '' );
    $properties = isset( $node['data']['properties'] ) && is_array( $node['data']['properties'] )
        ? $node['data']['properties']
        : [];

    if ( $type === 'EssentialElements\\FrequentlyAskedQuestions' ) {
        $rows = $properties['content']['settings']['items'] ?? [];
        foreach ( is_array( $rows ) ? $rows : [] as $row ) {
            if ( is_array( $row ) ) {
                $items[] = [
                    'question' => (string) ( $row['question'] ?? '' ),
                    'answer'   => (string) ( $row['answer'] ?? '' ),
                ];
            }
        }
    }

    if ( $type === 'EssentialElements\\Globalblock' ) {
        $block_id = intval( $properties['content']['content']['block'] ?? 0 );
        if ( $block_id > 0 && empty( $seen_blocks[ $block_id ] ) ) {
            $seen_blocks[ $block_id ] = true;
            $block_root = saifr_faq_get_tree( $block_id );
            if ( ! empty( $block_root ) ) {
                saifr_faq_collect( $block_root, $items, $seen_blocks );
            }
        }
    }

    foreach ( (array) ( $node['children'] ?? [] ) as $child ) {
        if ( is_array( $child ) ) {
            saifr_faq_collect( $child, $items, $seen_blocks );
        }
    }
}

function saifr_faq_clean_question( $value ): string {
    $value = (string) $value;
    if ( str_contains( $value, '[' ) ) {
        $value = strip_shortcodes( $value );
    }
    $value = wp_strip_all_tags( $value, true );
    return trim( (string) preg_replace( '/\s+/u', ' ', $value ) );
}

function saifr_faq_clean_answer( $value ): string {
    $value = (string) $value;
    if ( str_contains( $value, '[' ) ) {
        $value = strip_shortcodes( $value );
    }
    $allowed = apply_filters(
        'saifr_faq_answer_html',
        [
            'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [],
            'ul' => [], 'ol' => [], 'li' => [],
            'a' => [ 'href' => [], 'title' => [] ],
        ]
    );
    $value = wp_kses( $value, is_array( $allowed ) ? $allowed : [] );
    return trim( (string) preg_replace( '/\s+/u', ' ', $value ) );
}

function saifr_faq_get_items( WP_Post $post ): array {
    if ( ! saifr_faq_is_enabled( $post ) ) {
        return [];
    }
    $cache_version = (string) get_option( 'saifr_faq_cache_version', '' );
    $cache_key = 'saifr_faq_' . $post->ID . '_' . md5( $post->post_modified_gmt . '|' . $cache_version . '|' . SAIFR_FAQ_VERSION );
    $items = get_transient( $cache_key );

    if ( ! is_array( $items ) ) {
        $raw_items = [];
        $seen_blocks = [];
        $root = saifr_faq_get_tree( $post->ID );
        if ( ! empty( $root ) ) {
            saifr_faq_collect( $root, $raw_items, $seen_blocks );
        }

        $items = [];
        $seen_questions = [];
        foreach ( $raw_items as $row ) {
            $question = saifr_faq_clean_question( $row['question'] ?? '' );
            $answer = saifr_faq_clean_answer( $row['answer'] ?? '' );
            $fingerprint = strtolower( $question );
            if ( $question === '' || $answer === '' || isset( $seen_questions[ $fingerprint ] ) ) {
                continue;
            }
            $seen_questions[ $fingerprint ] = true;
            $items[] = [ 'question' => $question, 'answer' => $answer ];
        }
        set_transient( $cache_key, $items, DAY_IN_SECONDS );
    }

    return (array) apply_filters( 'saifr_faq_items', $items, $post );
}

function saifr_faq_get_node_for_post( WP_Post $post ): array {
    $items = saifr_faq_get_items( $post );
    $url = get_permalink( $post );
    if ( empty( $items ) || ! is_string( $url ) || $url === '' ) {
        return [];
    }
    $base = trailingslashit( $url );
    $entities = [];
    foreach ( $items as $index => $item ) {
        $entities[] = [
            '@type' => 'Question',
            '@id' => $base . '#faq-' . ( $index + 1 ),
            'name' => $item['question'],
            'acceptedAnswer' => [ '@type' => 'Answer', 'text' => $item['answer'] ],
        ];
    }
    $node = [
        '@type' => 'FAQPage',
        '@id' => $base . '#faq',
        'url' => $url,
        'inLanguage' => str_replace( '_', '-', get_locale() ),
        'mainEntityOfPage' => [ '@id' => $base . '#webpage' ],
        'mainEntity' => $entities,
    ];
    return (array) apply_filters( 'saifr_faq_node', $node, $post );
}

function saifr_faq_get_node( ?WP_Post $post = null ): array {
    $post ??= saifr_faq_source_post();
    return $post instanceof WP_Post ? saifr_faq_get_node_for_post( $post ) : [];
}

function saifr_faq_bump_cache_version( $meta_id, $post_id, $meta_key ): void {
    if ( $meta_key === '_breakdance_data' ) {
        update_option( 'saifr_faq_cache_version', (string) microtime( true ), false );
    }
}
add_action( 'added_post_meta', 'saifr_faq_bump_cache_version', 10, 3 );
add_action( 'updated_post_meta', 'saifr_faq_bump_cache_version', 10, 3 );
add_action( 'deleted_post_meta', 'saifr_faq_bump_cache_version', 10, 3 );
