<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════════
//  COSTANTI
// ═══════════════════════════════════════════════════════════════════════════════

if ( ! defined( 'AI_FR_PAGES_LIMIT' ) )         define( 'AI_FR_PAGES_LIMIT', 50 );
if ( ! defined( 'AI_FR_POSTS_LIMIT' ) )         define( 'AI_FR_POSTS_LIMIT', 30 );
if ( ! defined( 'AI_FR_EXCERPT_LEN' ) )         define( 'AI_FR_EXCERPT_LEN', 160 );
if ( ! defined( 'AI_FR_INCLUDE_METADATA' ) )    define( 'AI_FR_INCLUDE_METADATA', true );
if ( ! defined( 'AI_FR_NORMALIZE_HEADINGS' ) )  define( 'AI_FR_NORMALIZE_HEADINGS', true );

// Directory per versioni MD statiche
define( 'AI_FR_VERSIONS_DIR', WP_CONTENT_DIR . '/uploads/ai-friendly/versions' );
define( 'AI_FR_VERSIONS_URL', WP_CONTENT_URL . '/uploads/ai-friendly/versions' );

