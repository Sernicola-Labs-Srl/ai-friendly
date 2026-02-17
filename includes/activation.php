<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
//  ATTIVAZIONE / DISATTIVAZIONE
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

register_activation_hook( AI_FR_PLUGIN_FILE, 'ai_fr_activate' );
register_deactivation_hook( AI_FR_PLUGIN_FILE, 'ai_fr_deactivate' );

function ai_fr_activate(): void {
    // Crea directory per versioni MD
    if ( ! file_exists( AI_FR_VERSIONS_DIR ) ) {
        wp_mkdir_p( AI_FR_VERSIONS_DIR );
    }
    
    // Crea .htaccess per protezione (opzionale, i file sono pubblici ma evitiamo listing)
    $htaccess = AI_FR_VERSIONS_DIR . '/.htaccess';
    if ( ! file_exists( $htaccess ) ) {
        file_put_contents( $htaccess, "Options -Indexes\n" );
    }
    
    // Imposta opzioni di default
    $defaults = ai_fr_get_default_options();
    if ( ! get_option( 'ai_fr_options' ) ) {
        update_option( 'ai_fr_options', $defaults );
    }
    
    // Schedula cron se necessario
    ai_fr_schedule_cron();
}

function ai_fr_deactivate(): void {
    // Rimuovi cron
    wp_clear_scheduled_hook( 'ai_fr_cron_regenerate' );
}

