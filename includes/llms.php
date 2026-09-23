<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
//  2 â€” llms.txt
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

function saifr_serve_llms_txt(): void {

    $body = saifr_build_llms_txt();
    $body = (string) apply_filters( 'saifr_llms_txt_response_body', $body );

    saifr_reset_output_buffers();

    status_header( 200 );
    header( 'Content-Type: text/plain; charset=UTF-8' );
    header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
    header( 'Pragma: no-cache' );
    header( 'Expires: 0' );
    header( 'X-Content-Type-Options: nosniff' );
    header( 'X-AI-Friendly-Version: ' . SAIFR_VERSION );
    header( 'X-AI-Friendly-LLMS-Length: ' . strlen( $body ) );
    header( 'Content-Length: ' . strlen( $body ) );
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markdown is served as text/plain and must remain unescaped.
    echo $body;
    exit;
}

function saifr_build_llms_txt(): string {

    $options = wp_parse_args( get_option( 'saifr_options', [] ), saifr_get_default_options() );
    $filter = new SaifrContentFilter();

    $custom_content = trim( $options['llms_content'] ?? '' );
    $include_auto   = ! empty( $options['llms_include_auto'] );

    if ( $custom_content !== '' && ! $include_auto ) {
        return apply_filters( 'saifr_llms_txt_content', $custom_content );
    }

    $out = '';

    if ( $custom_content !== '' ) {
        $out = $custom_content . "\n\n";
    } else {
        $name = get_bloginfo( 'blogname' );
        $desc = get_bloginfo( 'description' ) ?: 'Sito web';
        $out  = "# {$name}\n";
        $out .= "> {$desc}\n\n";
    }

    if ( $include_auto || $custom_content === '' ) {
        
        // Pagine
        if ( ! empty( $options['include_pages'] ) ) {
            $out .= saifr_section( __( 'Pagine', 'sernicola-labs-ai-friendly' ), [
                'post_type'      => 'page',
                'post_status'    => 'publish',
                'posts_per_page' => SAIFR_PAGES_LIMIT,
                'orderby'        => 'menu_order date',
                'order'          => 'ASC',
            ], $filter );
        }

        // Post
        if ( ! empty( $options['include_posts'] ) ) {
            $out .= saifr_section( __( 'Post', 'sernicola-labs-ai-friendly' ), [
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'posts_per_page' => SAIFR_POSTS_LIMIT,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ], $filter );
        }

        // Prodotti WooCommerce
        if ( ! empty( $options['include_products'] ) && class_exists( 'WooCommerce' ) ) {
            $out .= saifr_section( __( 'Prodotti', 'sernicola-labs-ai-friendly' ), [
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => 20,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ], $filter );
        }
        
        // CPT custom
        $enabled_cpt = $options['include_cpt'] ?? [];
        foreach ( (array) $enabled_cpt as $cpt ) {
            $cpt_obj = get_post_type_object( $cpt );
            $label = $cpt_obj ? $cpt_obj->labels->name : ucfirst( $cpt );
            
            $out .= saifr_section( $label, [
                'post_type'      => $cpt,
                'post_status'    => 'publish',
                'posts_per_page' => 20,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ], $filter );
        }
    }

    return apply_filters( 'saifr_llms_txt_content', $out );
}

function saifr_section( string $heading, array $query_args, SaifrContentFilter $filter ): string {

    $posts = get_posts( $query_args );
    
    // Filtra con le regole di inclusione/esclusione
    $items = array_filter( $posts, fn( WP_Post $p ) => $filter->shouldInclude( $p ) );

    if ( empty( $items ) ) {
        return '';
    }

    $lines = "## {$heading}\n";

    foreach ( $items as $item ) {
        if ( ! saifr_can_serve_post( $item, 'llms' ) ) {
            continue;
        }
        $title   = get_the_title( $item->ID );
        $md_url  = saifr_permalink_to_md( get_permalink( $item->ID ) );
        $excerpt = saifr_excerpt( $item );
        $facts   = function_exists( 'saifr_schema_get_llms_facts' ) ? saifr_schema_get_llms_facts( $item ) : '';

        $lines .= "- [{$title}]({$md_url})";
        $lines .= $facts !== '' ? " ({$facts})" : '';
        $lines .= $excerpt !== '' ? ": {$excerpt}" : '';
        $lines .= "\n";
    }

    return $lines . "\n";
}


