<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Restituisce gli eventi registrati (piu' recenti per primi).
 */
function saifr_get_event_log(): array {
    $log = get_option( 'saifr_event_log', [] );
    if ( ! is_array( $log ) ) {
        return [];
    }

    // Versions prior to 2.0 stored the acting user ID. It is not needed for diagnostics.
    $changed = false;
    foreach ( $log as &$entry ) {
        if ( is_array( $entry ) && array_key_exists( 'user_id', $entry ) ) {
            unset( $entry['user_id'] );
            $changed = true;
        }
    }
    unset( $entry );
    if ( $changed ) {
        update_option( 'saifr_event_log', $log, false );
    }

    return $log;
}

/**
 * Aggiunge un evento al log ring-buffer (max 200).
 */
function saifr_add_event( string $type, array $payload = [], string $level = 'info' ): void {
    $log = saifr_get_event_log();

    array_unshift(
        $log,
        [
            'id'      => wp_generate_uuid4(),
            'time'    => current_time( 'mysql' ),
            'type'    => sanitize_key( $type ),
            'level'   => sanitize_key( $level ),
            'payload' => $payload,
        ]
    );

    if ( count( $log ) > 200 ) {
        $log = array_slice( $log, 0, 200 );
    }

    update_option( 'saifr_event_log', $log, false );
}
