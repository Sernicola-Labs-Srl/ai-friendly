<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Restituisce gli eventi registrati (piu' recenti per primi).
 */
function ai_fr_get_event_log(): array {
    $log = get_option( 'ai_fr_event_log', [] );
    if ( ! is_array( $log ) ) {
        return [];
    }
    return $log;
}

/**
 * Aggiunge un evento al log ring-buffer (max 200).
 */
function ai_fr_add_event( string $type, array $payload = [], string $level = 'info' ): void {
    $log = ai_fr_get_event_log();

    array_unshift(
        $log,
        [
            'id'      => wp_generate_uuid4(),
            'time'    => current_time( 'mysql' ),
            'type'    => sanitize_key( $type ),
            'level'   => sanitize_key( $level ),
            'payload' => $payload,
            'user_id' => get_current_user_id(),
        ]
    );

    if ( count( $log ) > 200 ) {
        $log = array_slice( $log, 0, 200 );
    }

    update_option( 'ai_fr_event_log', $log, false );
}
