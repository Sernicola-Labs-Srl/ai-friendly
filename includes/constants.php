<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════════
//  COSTANTI
// ═══════════════════════════════════════════════════════════════════════════════

if ( ! defined( 'SAIFR_PAGES_LIMIT' ) )         define( 'SAIFR_PAGES_LIMIT', 50 );
if ( ! defined( 'SAIFR_POSTS_LIMIT' ) )         define( 'SAIFR_POSTS_LIMIT', 30 );
if ( ! defined( 'SAIFR_EXCERPT_LEN' ) )         define( 'SAIFR_EXCERPT_LEN', 160 );
if ( ! defined( 'SAIFR_INCLUDE_METADATA' ) )    define( 'SAIFR_INCLUDE_METADATA', true );
if ( ! defined( 'SAIFR_NORMALIZE_HEADINGS' ) )  define( 'SAIFR_NORMALIZE_HEADINGS', true );

/**
 * Restituisce la directory upload riservata al plugin per il sito corrente.
 */
function saifr_storage_root(): string {
    $uploads = wp_upload_dir();
    return trailingslashit( (string) $uploads['basedir'] ) . 'sernicola-labs-ai-friendly';
}

function saifr_versions_dir(): string {
    return trailingslashit( saifr_storage_root() ) . 'versions';
}
