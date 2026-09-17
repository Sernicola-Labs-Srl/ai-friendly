<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
//  ATTIVAZIONE / DISATTIVAZIONE
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

register_activation_hook( SAIFR_PLUGIN_FILE, 'saifr_activate' );
register_deactivation_hook( SAIFR_PLUGIN_FILE, 'saifr_deactivate' );

function saifr_activate( bool $network_wide = false ): void {
    // Importa i dati legacy e disattiva in sicurezza la precedente identità.
    saifr_run_legacy_upgrade( $network_wide );

    // Crea directory per versioni MD
    if ( ! file_exists( saifr_versions_dir() ) ) {
        wp_mkdir_p( saifr_versions_dir() );
    }
    
    // Crea .htaccess per protezione (opzionale, i file sono pubblici ma evitiamo listing)
    $htaccess = saifr_versions_dir() . '/.htaccess';
    if ( ! file_exists( $htaccess ) ) {
        file_put_contents( $htaccess, "Options -Indexes\n" );
    }
    
    // Imposta opzioni di default
    $defaults = saifr_get_default_options();
    if ( ! get_option( 'saifr_options' ) ) {
        update_option( 'saifr_options', $defaults );
    }
    
    // Schedula cron se necessario
    saifr_schedule_cron();
}

function saifr_deactivate(): void {
    // Rimuovi cron
    wp_clear_scheduled_hook( 'saifr_cron_regenerate' );
}
