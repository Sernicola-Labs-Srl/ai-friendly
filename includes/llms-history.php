<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Restituisce indice snapshot.
 */
function ai_fr_get_llms_history_index(): array {
    $index = get_option( 'ai_fr_llms_history_index', [] );
    return is_array( $index ) ? $index : [];
}

/**
 * Crea snapshot llms.txt.
 */
function ai_fr_create_llms_snapshot( string $content, string $reason = 'manual' ): array {
    if ( ! file_exists( AI_FR_LLMS_HISTORY_DIR ) ) {
        wp_mkdir_p( AI_FR_LLMS_HISTORY_DIR );
    }

    $id       = 'llms-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 6, false, false );
    $filename = sanitize_file_name( $id . '.txt' );
    $path     = trailingslashit( AI_FR_LLMS_HISTORY_DIR ) . $filename;

    $saved = file_put_contents( $path, $content ) !== false;
    if ( ! $saved ) {
        return [ 'saved' => false ];
    }

    $entry = [
        'id'         => $id,
        'filename'   => $filename,
        'created_at' => current_time( 'mysql' ),
        'user_id'    => get_current_user_id(),
        'reason'     => sanitize_text_field( $reason ),
        'chars'      => strlen( $content ),
        'tokens'     => ai_fr_estimate_tokens( $content ),
        'checksum'   => md5( $content ),
    ];

    $index = ai_fr_get_llms_history_index();
    array_unshift( $index, $entry );
    if ( count( $index ) > 100 ) {
        $index = array_slice( $index, 0, 100 );
    }
    update_option( 'ai_fr_llms_history_index', $index, false );

    return [ 'saved' => true, 'entry' => $entry ];
}

/**
 * Legge il contenuto snapshot.
 */
function ai_fr_get_llms_snapshot_content( string $id ): ?string {
    $index = ai_fr_get_llms_history_index();
    foreach ( $index as $entry ) {
        if ( ( $entry['id'] ?? '' ) === $id ) {
            $file = trailingslashit( AI_FR_LLMS_HISTORY_DIR ) . basename( (string) $entry['filename'] );
            if ( file_exists( $file ) ) {
                $content = file_get_contents( $file );
                return is_string( $content ) ? $content : null;
            }
        }
    }
    return null;
}

/**
 * Ripristina snapshot nel campo llms_content.
 */
function ai_fr_restore_llms_snapshot( string $id ): array {
    $content = ai_fr_get_llms_snapshot_content( $id );
    if ( $content === null ) {
        return [ 'restored' => false, 'message' => 'Snapshot non trovato.' ];
    }

    $options                 = wp_parse_args( get_option( 'ai_fr_options', [] ), ai_fr_get_default_options() );
    $options['llms_content'] = $content;
    update_option( 'ai_fr_options', $options );

    return [ 'restored' => true, 'content' => $content ];
}

/**
 * Rende markdown in HTML essenziale per preview admin.
 */
function ai_fr_render_markdown_preview_html( string $content ): string {
    $safe = esc_html( $content );
    $safe = preg_replace( '/^######\s(.+)$/m', '<h6>$1</h6>', $safe );
    $safe = preg_replace( '/^#####\s(.+)$/m', '<h5>$1</h5>', $safe );
    $safe = preg_replace( '/^####\s(.+)$/m', '<h4>$1</h4>', $safe );
    $safe = preg_replace( '/^###\s(.+)$/m', '<h3>$1</h3>', $safe );
    $safe = preg_replace( '/^##\s(.+)$/m', '<h2>$1</h2>', $safe );
    $safe = preg_replace( '/^#\s(.+)$/m', '<h1>$1</h1>', $safe );
    $safe = preg_replace( '/^\>\s(.+)$/m', '<blockquote>$1</blockquote>', $safe );
    $safe = preg_replace( '/^\-\s(.+)$/m', '<li>$1</li>', $safe );
    $safe = preg_replace( '/\[(.*?)\]\((https?:\/\/[^\s]+)\)/', '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>', $safe );

    $safe = preg_replace( '/(<li>.*<\/li>)/s', '<ul>$1</ul>', $safe, 1 );
    $safe = nl2br( $safe );

    return (string) $safe;
}

/**
 * Simulazione AI locale (heuristics) su contenuto llms.
 */
function ai_fr_run_ai_simulation( string $content ): array {
    $text       = trim( wp_strip_all_tags( $content ) );
    $lines      = array_values( array_filter( array_map( 'trim', explode( "\n", $text ) ) ) );
    $line_count = count( $lines );
    $tokens     = ai_fr_estimate_tokens( $text );

    $normalized = array_map(
        static function ( string $line ): string {
            return strtolower( preg_replace( '/\s+/', ' ', $line ) );
        },
        $lines
    );

    $duplicates = 0;
    if ( ! empty( $normalized ) ) {
        $duplicates = count( $normalized ) - count( array_unique( $normalized ) );
    }

    $headings = preg_match_all( '/^#{1,6}\s/m', $content );
    $lists    = preg_match_all( '/^\-\s/m', $content );
    $links    = preg_match_all( '/\[[^\]]+\]\([^)]+\)/', $content );

    $dup_ratio       = $line_count > 0 ? $duplicates / $line_count : 0;
    $token_penalty   = min( 35, (int) floor( $tokens / 180 ) );
    $dup_penalty     = min( 45, (int) floor( $dup_ratio * 100 ) );
    $structure_bonus = min( 15, $headings + $lists + ( $links > 0 ? 2 : 0 ) );
    $score           = max( 1, min( 100, 100 - $token_penalty - $dup_penalty + $structure_bonus ) );

    $suggestions = [];
    if ( $duplicates > 0 ) {
        $suggestions[] = 'Riduci frasi ripetute o simili per migliorare compattezza.';
    }
    if ( $tokens > 2500 ) {
        $suggestions[] = 'Token elevati: valuta sintesi di sezioni secondarie.';
    }
    if ( $headings < 2 ) {
        $suggestions[] = 'Aggiungi heading per migliorare leggibilita strutturale.';
    }
    if ( $links === 0 ) {
        $suggestions[] = 'Valuta link diretti alle risorse principali.';
    }
    if ( empty( $suggestions ) ) {
        $suggestions[] = 'Struttura buona: mantieni sezioni brevi e stabili.';
    }

    return [
        'score'      => $score,
        'tokens'     => $tokens,
        'duplicates' => $duplicates,
        'metrics'    => [
            'lines'    => $line_count,
            'headings' => intval( $headings ),
            'lists'    => intval( $lists ),
            'links'    => intval( $links ),
        ],
        'suggestions' => $suggestions,
    ];
}
