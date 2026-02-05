<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════════
//  4 — <head>
// ═══════════════════════════════════════════════════════════════════════════════

add_action( 'wp_head', function () {

    echo '<link rel="llms-txt" type="text/plain" href="'
       . esc_url( home_url( '/llms.txt' ) )
       . '" />' . "\n";

    if ( ( is_single() || is_page() ) && get_the_ID() ) {
        $filter = new AiFrContentFilter();
        $post = get_post( get_the_ID() );
        
        if ( $post && $filter->shouldInclude( $post ) ) {
            $md_url = ai_fr_permalink_to_md( get_permalink( get_the_ID() ) );

            echo '<link rel="alternate" type="text/markdown" title="Versione Markdown" href="'
               . esc_url( $md_url )
               . '" />' . "\n";
        }
    }
}, 2 );

