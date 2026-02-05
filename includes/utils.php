<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════════
//  6 — Utility
// ═══════════════════════════════════════════════════════════════════════════════

function ai_fr_permalink_to_md( string $permalink ): string {
    return rtrim( $permalink, '/' ) . '.md';
}

function ai_fr_excerpt( WP_Post $post ): string {
    $raw = $post->post_excerpt !== '' ? $post->post_excerpt : $post->post_content;
    $raw = preg_replace( '/\[[^\]]+\]/', '', $raw ) ?? $raw;
    $text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $raw ) ) ?? '' );

    return strlen( $text ) > AI_FR_EXCERPT_LEN
        ? substr( $text, 0, AI_FR_EXCERPT_LEN ) . '…'
        : $text;
}
