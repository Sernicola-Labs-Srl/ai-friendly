<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registra una notice admin temporanea.
 */
function saifr_push_admin_notice( string $message, string $type = 'warning' ): void {
    set_transient(
        'saifr_admin_notice',
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
function saifr_maybe_notify_regeneration_errors( array $stats, string $trigger ): void {
    $options = wp_parse_args( get_option( 'saifr_options', [] ), saifr_get_default_options() );
    $errors  = intval( $stats['errors'] ?? 0 );
    if ( $errors <= 0 ) {
        return;
    }

    $message = sprintf(
        /* translators: 1: error count, 2: trigger, 3: processed items, 4: regenerated items, 5: skipped items. */
        __( 'AI Friendly: rigenerazione con %1$d errori (%2$s). Processati %3$d, rigenerati %4$d, saltati %5$d.', 'sernicola-labs-ai-friendly' ),
        $errors,
        $trigger,
        intval( $stats['processed'] ?? 0 ),
        intval( $stats['regenerated'] ?? 0 ),
        intval( $stats['skipped'] ?? 0 )
    );

    if ( ! empty( $options['notify_admin_notice'] ) ) {
        saifr_push_admin_notice( $message, 'warning' );
    }

    if ( ! empty( $options['notify_email'] ) ) {
        $to = sanitize_email( (string) ( $options['notify_email_to'] ?? '' ) );
        if ( $to === '' ) {
            $to = (string) get_option( 'admin_email', '' );
        }
        if ( is_email( $to ) ) {
            wp_mail( $to, __( 'AI Friendly - Rigenerazione con errori', 'sernicola-labs-ai-friendly' ), $message );
        }
    }
}

add_action(
    'admin_notices',
    function (): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $notice = get_transient( 'saifr_admin_notice' );
        if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
            return;
        }
        delete_transient( 'saifr_admin_notice' );

        $type = in_array( $notice['type'] ?? 'warning', [ 'warning', 'error', 'success', 'info' ], true )
            ? $notice['type']
            : 'warning';
        echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( (string) $notice['message'] ) . '</p></div>';
    }
);
