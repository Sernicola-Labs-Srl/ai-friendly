<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registra una notice admin temporanea.
 */
function ai_fr_push_admin_notice( string $message, string $type = 'warning' ): void {
    set_transient(
        'ai_fr_admin_notice',
        [
            'message' => sanitize_text_field( $message ),
            'type'    => sanitize_key( $type ),
            'time'    => current_time( 'mysql' ),
        ],
        10 * MINUTE_IN_SECONDS
    );
}

/**
 * Notifica errori rigenerazione via notice e/o email.
 */
function ai_fr_maybe_notify_regeneration_errors( array $stats, string $trigger ): void {
    $options = wp_parse_args( get_option( 'ai_fr_options', [] ), ai_fr_get_default_options() );
    $errors  = intval( $stats['errors'] ?? 0 );
    if ( $errors <= 0 ) {
        return;
    }

    $message = sprintf(
        'AI Friendly: rigenerazione con %d errori (%s). Processati %d, rigenerati %d, saltati %d.',
        $errors,
        $trigger,
        intval( $stats['processed'] ?? 0 ),
        intval( $stats['regenerated'] ?? 0 ),
        intval( $stats['skipped'] ?? 0 )
    );

    if ( ! empty( $options['notify_admin_notice'] ) ) {
        ai_fr_push_admin_notice( $message, 'warning' );
    }

    if ( ! empty( $options['notify_email'] ) ) {
        $to = sanitize_email( (string) ( $options['notify_email_to'] ?? '' ) );
        if ( $to === '' ) {
            $to = (string) get_option( 'admin_email', '' );
        }
        if ( is_email( $to ) ) {
            wp_mail( $to, 'AI Friendly - Rigenerazione con errori', $message );
        }
    }
}

add_action(
    'admin_notices',
    function (): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $notice = get_transient( 'ai_fr_admin_notice' );
        if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
            return;
        }
        delete_transient( 'ai_fr_admin_notice' );

        $type = in_array( $notice['type'] ?? 'warning', [ 'warning', 'error', 'success', 'info' ], true )
            ? $notice['type']
            : 'warning';
        echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( (string) $notice['message'] ) . '</p></div>';
    }
);
