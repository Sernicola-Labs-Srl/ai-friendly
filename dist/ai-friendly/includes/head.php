<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
//  4 â€” <head>
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

add_action( 'wp_head', function () {

    echo '<link rel="alternate" type="text/plain" title="LLM Instructions" href="'
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

