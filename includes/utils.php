<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════════
//  6 — Utility
// ═══════════════════════════════════════════════════════════════════════════════

function saifr_permalink_to_md( string $permalink ): string {
    $home_path = saifr_normalize_url_path( home_url( '/' ) );
    $path      = saifr_normalize_url_path( $permalink );

    if ( $path === $home_path ) {
        return trailingslashit( home_url() ) . 'index.html.md';
    }

    return rtrim( $permalink, '/' ) . '.md';
}

function saifr_normalize_url_path( string $url ): string {
    $path = wp_parse_url( $url, PHP_URL_PATH );
    if ( ! is_string( $path ) || $path === '' ) {
        return '/';
    }

    $path = '/' . trim( rawurldecode( $path ), '/' );
    return $path === '/' ? '/' : rtrim( $path, '/' );
}

function saifr_excerpt( WP_Post $post ): string {
    $raw = $post->post_excerpt !== '' ? $post->post_excerpt : $post->post_content;
    $raw = preg_replace( '/\[[^\]]+\]/', '', $raw ) ?? $raw;
    $text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $raw ) ) ?? '' );

    return wp_html_excerpt( $text, SAIFR_EXCERPT_LEN, '…' );
}
