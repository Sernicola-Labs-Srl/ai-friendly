<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════════
//  1 — INTERCEPT
// ═══════════════════════════════════════════════════════════════════════════════

add_action( 'template_redirect', function () {

    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    if ( empty( $request_uri ) ) {
        return;
    }

    $parsed = parse_url( $request_uri, PHP_URL_PATH );
    if ( $parsed === false || $parsed === null ) {
        return;
    }

    $full = rawurldecode( $parsed );

    $wp_base = rtrim( parse_url( home_url(), PHP_URL_PATH ) ?? '', '/' );
    $rel     = ( $wp_base !== '' && str_starts_with( $full, $wp_base ) )
             ? substr( $full, strlen( $wp_base ) )
             : $full;
    $rel = $rel ?: '/';

    if ( $rel === '/llms.txt' ) {
        ai_fr_serve_llms_txt();
        exit;
    }

    if ( preg_match( '#^(.+?)(?:/index\.html\.md|\.md)$#i', $rel, $m ) ) {
        ai_fr_serve_markdown( $m[1] );
        exit;
    }

}, 1 );

