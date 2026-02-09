<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Stima semplice token da testo.
 */
function ai_fr_estimate_tokens( string $text ): int {
    $words = str_word_count( wp_strip_all_tags( $text ) );
    return (int) ceil( $words * 1.33 );
}

/**
 * Lingua best-effort: usa locale WP.
 */
function ai_fr_get_post_language( WP_Post $post ): string {
    $locale = get_locale();
    if ( is_string( $locale ) && $locale !== '' ) {
        return $locale;
    }
    return 'n/a';
}

/**
 * Lista contenuti per tabella admin.
 *
 * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
 */
function ai_fr_list_content_items( array $args = [] ): array {
    $filter  = new AiFrContentFilter();
    $options = wp_parse_args( get_option( 'ai_fr_options', [] ), ai_fr_get_default_options() );

    $page     = max( 1, intval( $args['page'] ?? 1 ) );
    $per_page = min( 50, max( 5, intval( $args['per_page'] ?? 10 ) ) );
    $search   = sanitize_text_field( (string) ( $args['search'] ?? '' ) );
    $status   = sanitize_key( (string) ( $args['status'] ?? 'any' ) );
    $type     = sanitize_key( (string) ( $args['post_type'] ?? 'all' ) );

    $enabled_post_types = $filter->getEnabledPostTypes();
    if ( empty( $enabled_post_types ) ) {
        $enabled_post_types = get_post_types( [ 'public' => true ], 'names' );
    }
    if ( $type !== 'all' ) {
        $enabled_post_types = [ $type ];
    }

    $query = [
        'post_type'      => $enabled_post_types,
        'post_status'    => $status === 'any' ? [ 'publish', 'draft', 'future', 'private' ] : [ $status ],
        'posts_per_page' => $per_page,
        'paged'          => $page,
        's'              => $search,
    ];

    $q = new WP_Query( $query );

    $items = [];
    foreach ( $q->posts as $post ) {
        $post = get_post( $post );
        if ( ! $post ) {
            continue;
        }

        $is_excluded  = (bool) get_post_meta( $post->ID, '_ai_fr_exclude', true );
        $included     = ! $is_excluded && $filter->shouldInclude( $post );
        $token_source = $post->post_excerpt !== '' ? $post->post_excerpt : $post->post_content;

        $permalink = get_permalink( $post->ID );
        $md_url    = is_string( $permalink ) ? ai_fr_permalink_to_md( $permalink ) : '';

        $items[] = [
            'id'          => $post->ID,
            'title'       => get_the_title( $post->ID ),
            'edit_url'    => get_edit_post_link( $post->ID, '' ),
            'included'    => $included,
            'excluded'    => $is_excluded,
            'post_type'   => $post->post_type,
            'status'      => $post->post_status,
            'language'    => ai_fr_get_post_language( $post ),
            'tokens'      => ai_fr_estimate_tokens( $token_source ),
            'md_url'      => $md_url,
            'auto_rules'  => [
                'noindex_filter'      => ! empty( $options['exclude_noindex'] ),
                'passwords_filter'    => ! empty( $options['exclude_password'] ),
                'manual_exclude_meta' => $is_excluded,
            ],
        ];
    }

    return [
        'items'    => $items,
        'total'    => intval( $q->found_posts ),
        'page'     => $page,
        'per_page' => $per_page,
    ];
}
