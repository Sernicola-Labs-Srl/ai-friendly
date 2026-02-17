<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
//  1 â€” INTERCEPT
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

add_action( 'template_redirect', function () {

    $request_uri = isset( $_SERVER['REQUEST_URI'] )
        ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
        : '';
    if ( empty( $request_uri ) ) {
        return;
    }

    $parsed = wp_parse_url( $request_uri, PHP_URL_PATH );
    if ( $parsed === false || $parsed === null ) {
        return;
    }

    $full = rawurldecode( $parsed );

    $wp_base = rtrim( wp_parse_url( home_url(), PHP_URL_PATH ) ?? '', '/' );
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

