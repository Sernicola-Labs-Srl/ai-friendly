<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Restituisce il nome dell'opzione non autoloadata che contiene uno snapshot.
 */
function saifr_llms_snapshot_option_name( string $id ): string {
    return 'saifr_llms_snapshot_' . md5( $id );
}

/**
 * Elimina dal database il contenuto di uno snapshot.
 */
function saifr_delete_llms_snapshot_storage( array $entry ): void {
    $id = (string) ( $entry['id'] ?? '' );
    if ( $id !== '' ) {
        delete_option( saifr_llms_snapshot_option_name( $id ) );
    }
}

/**
 * Restituisce indice snapshot.
 */
function saifr_get_llms_history_index(): array {
    $index = get_option( 'saifr_llms_history_index', [] );
    if ( ! is_array( $index ) ) {
        return [];
    }

    // Versions prior to 2.0 stored the acting user ID, which is not needed.
    $changed = false;
    foreach ( $index as &$entry ) {
        if ( is_array( $entry ) && array_key_exists( 'user_id', $entry ) ) {
            unset( $entry['user_id'] );
            $changed = true;
        }
    }
    unset( $entry );
    if ( $changed ) {
        update_option( 'saifr_llms_history_index', $index, false );
    }

    return $index;
}

/**
 * Crea snapshot llms.txt.
 */
function saifr_create_llms_snapshot( string $content, string $reason = 'manual' ): array {
    $content = sanitize_textarea_field( $content );
    $reason  = sanitize_text_field( $reason );
    $id = 'llms-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 6, false, false );
    $option_name = saifr_llms_snapshot_option_name( $id );

    $saved = add_option( $option_name, $content, '', false );
    if ( ! $saved ) {
        return [ 'saved' => false ];
    }

    $index      = saifr_get_llms_history_index();
    $prev_entry = $index[0] ?? null;
    $prev_text  = null;
    if ( is_array( $prev_entry ) && ! empty( $prev_entry['id'] ) ) {
        $prev_text = saifr_get_llms_snapshot_content( (string) $prev_entry['id'] );
    }

    $entry = [
        'id'         => $id,
        'storage'    => 'option',
        'created_at' => current_time( 'mysql' ),
        'reason'     => $reason,
        'chars'      => strlen( $content ),
        'tokens'     => saifr_estimate_tokens( $content ),
        'checksum'   => md5( $content ),
    ];
    if ( is_string( $prev_text ) ) {
        $diff = saifr_diff_llms_content( $prev_text, $content );
        $entry['note'] = sprintf(
            /* translators: 1: added lines, 2: removed lines, 3: token delta sign, 4: token delta. */
            __( 'Linee +%1$d / -%2$d, token %3$s%4$d', 'sernicola-labs-ai-friendly' ),
            intval( $diff['summary']['added_lines'] ?? 0 ),
            intval( $diff['summary']['removed_lines'] ?? 0 ),
            ( ( $diff['summary']['token_delta'] ?? 0 ) >= 0 ? '+' : '' ),
            intval( $diff['summary']['token_delta'] ?? 0 )
        );
    } else {
        $entry['note'] = __( 'Primo snapshot disponibile.', 'sernicola-labs-ai-friendly' );
    }

    array_unshift( $index, $entry );
    if ( count( $index ) > 100 ) {
        $removed = array_slice( $index, 100 );
        $index = array_slice( $index, 0, 100 );
        foreach ( $removed as $removed_entry ) {
            if ( is_array( $removed_entry ) ) {
                saifr_delete_llms_snapshot_storage( $removed_entry );
            }
        }
    }
    update_option( 'saifr_llms_history_index', $index, false );

    return [ 'saved' => true, 'entry' => $entry ];
}

/**
 * Legge il contenuto snapshot.
 */
function saifr_get_llms_snapshot_content( string $id ): ?string {
    $index = saifr_get_llms_history_index();
    foreach ( $index as $entry ) {
        if ( ( $entry['id'] ?? '' ) === $id ) {
            $content = get_option( saifr_llms_snapshot_option_name( $id ), null );
            if ( is_string( $content ) ) {
                return sanitize_textarea_field( $content );
            }
            return null;
        }
    }
    return null;
}

/**
 * Ripristina snapshot nel campo llms_content.
 */
function saifr_restore_llms_snapshot( string $id ): array {
    $content = saifr_get_llms_snapshot_content( $id );
    if ( $content === null ) {
        return [ 'restored' => false, 'message' => __( 'Snapshot non trovato.', 'sernicola-labs-ai-friendly' ) ];
    }

    $options                 = wp_parse_args( get_option( 'saifr_options', [] ), saifr_get_default_options() );
    $options['llms_content'] = sanitize_textarea_field( $content );
    update_option( 'saifr_options', $options );

    return [ 'restored' => true, 'content' => $content ];
}

/**
 * Rende markdown in HTML essenziale per preview admin.
 */
function saifr_render_markdown_preview_html( string $content ): string {
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
 * Valida i link markdown nel contenuto llms.
 */
function saifr_validate_llms_links( string $content ): array {
    preg_match_all( '/\[[^\]]+\]\(([^)]+)\)/', $content, $matches );
    $links = array_unique( $matches[1] ?? [] );
    $issues = [];

    foreach ( $links as $url ) {
        $url = trim( (string) $url );
        if ( $url === '' ) {
            continue;
        }

        $is_http = preg_match( '#^https?://#i', $url ) === 1;
        $is_rel  = str_starts_with( $url, '/' );
        if ( ! $is_http && ! $is_rel ) {
            $issues[] = [
                'url'     => $url,
                'severity'=> 'warning',
                'message' => __( 'Link con formato non valido (atteso http/https o percorso relativo).', 'sernicola-labs-ai-friendly' ),
            ];
            continue;
        }

        if ( $is_http ) {
            $parts = wp_parse_url( $url );
            if ( empty( $parts['host'] ) ) {
                $issues[] = [
                    'url'      => $url,
                    'severity' => 'warning',
                    'message'  => __( 'URL non interpretabile.', 'sernicola-labs-ai-friendly' ),
                ];
                continue;
            }
        }

        if ( $is_rel && ! str_contains( $url, '.md' ) && ! str_contains( $url, 'llms.txt' ) ) {
            $issues[] = [
                'url'      => $url,
                'severity' => 'info',
                'message'  => __( 'Link relativo senza estensione .md o llms.txt.', 'sernicola-labs-ai-friendly' ),
            ];
        }
    }

    return [
        'count'  => count( $issues ),
        'issues' => $issues,
    ];
}

/**
 * Diff line-by-line semplificato tra due contenuti llms.
 */
function saifr_diff_llms_content( string $left, string $right ): array {
    $left_lines  = array_map( 'trim', explode( "\n", $left ) );
    $right_lines = array_map( 'trim', explode( "\n", $right ) );
    $left_lines  = array_values( array_filter( $left_lines, static fn( $x ) => $x !== '' ) );
    $right_lines = array_values( array_filter( $right_lines, static fn( $x ) => $x !== '' ) );

    $ops = saifr_build_diff_ops( $left_lines, $right_lines );

    $added_lines   = [];
    $removed_lines = [];
    $rows          = [];
    $left_num      = 1;
    $right_num     = 1;

    foreach ( $ops as $op ) {
        if ( $op['op'] === 'equal' ) {
            $rows[] = [
                'type'      => 'equal',
                'left_num'  => $left_num++,
                'right_num' => $right_num++,
                'left'      => $op['line'],
                'right'     => $op['line'],
            ];
            continue;
        }
        if ( $op['op'] === 'remove' ) {
            $removed_lines[] = $op['line'];
            $rows[]          = [
                'type'      => 'remove',
                'left_num'  => $left_num++,
                'right_num' => null,
                'left'      => $op['line'],
                'right'     => '',
            ];
            continue;
        }
        if ( $op['op'] === 'add' ) {
            $added_lines[] = $op['line'];
            $rows[]        = [
                'type'      => 'add',
                'left_num'  => null,
                'right_num' => $right_num++,
                'left'      => '',
                'right'     => $op['line'],
            ];
        }
    }

    return [
        'left' => [
            'content' => $left,
            'tokens'  => saifr_estimate_tokens( $left ),
            'lines'   => count( $left_lines ),
        ],
        'right' => [
            'content' => $right,
            'tokens'  => saifr_estimate_tokens( $right ),
            'lines'   => count( $right_lines ),
        ],
        'summary' => [
            'added_lines'   => count( $added_lines ),
            'removed_lines' => count( $removed_lines ),
            'token_delta'   => saifr_estimate_tokens( $right ) - saifr_estimate_tokens( $left ),
        ],
        'rows'            => $rows,
        'added_preview'   => array_slice( $added_lines, 0, 30 ),
        'removed_preview' => array_slice( $removed_lines, 0, 30 ),
    ];
}

/**
 * Crea operazioni diff line-by-line con LCS.
 *
 * @return array<int, array{op:string, line:string}>
 */
function saifr_build_diff_ops( array $left_lines, array $right_lines ): array {
    $n = count( $left_lines );
    $m = count( $right_lines );
    $dp = array_fill( 0, $n + 1, array_fill( 0, $m + 1, 0 ) );

    for ( $i = $n - 1; $i >= 0; $i-- ) {
        for ( $j = $m - 1; $j >= 0; $j-- ) {
            if ( $left_lines[ $i ] === $right_lines[ $j ] ) {
                $dp[ $i ][ $j ] = $dp[ $i + 1 ][ $j + 1 ] + 1;
            } else {
                $dp[ $i ][ $j ] = max( $dp[ $i + 1 ][ $j ], $dp[ $i ][ $j + 1 ] );
            }
        }
    }

    $ops = [];
    $i = 0;
    $j = 0;
    while ( $i < $n && $j < $m ) {
        if ( $left_lines[ $i ] === $right_lines[ $j ] ) {
            $ops[] = [ 'op' => 'equal', 'line' => $left_lines[ $i ] ];
            $i++;
            $j++;
        } elseif ( $dp[ $i + 1 ][ $j ] >= $dp[ $i ][ $j + 1 ] ) {
            $ops[] = [ 'op' => 'remove', 'line' => $left_lines[ $i ] ];
            $i++;
        } else {
            $ops[] = [ 'op' => 'add', 'line' => $right_lines[ $j ] ];
            $j++;
        }
    }
    while ( $i < $n ) {
        $ops[] = [ 'op' => 'remove', 'line' => $left_lines[ $i ] ];
        $i++;
    }
    while ( $j < $m ) {
        $ops[] = [ 'op' => 'add', 'line' => $right_lines[ $j ] ];
        $j++;
    }

    return $ops;
}

/**
 * Simulazione AI locale (heuristics) su contenuto llms.
 */
function saifr_run_ai_simulation( string $content ): array {
    $text       = trim( wp_strip_all_tags( $content ) );
    $lines      = array_values( array_filter( array_map( 'trim', explode( "\n", $text ) ) ) );
    $line_count = count( $lines );
    $tokens     = saifr_estimate_tokens( $text );

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
        $suggestions[] = __( 'Riduci frasi ripetute o simili per migliorare compattezza.', 'sernicola-labs-ai-friendly' );
    }
    if ( $tokens > 2500 ) {
        $suggestions[] = __( 'Token elevati: valuta sintesi di sezioni secondarie.', 'sernicola-labs-ai-friendly' );
    }
    if ( $headings < 2 ) {
        $suggestions[] = __( 'Aggiungi heading per migliorare leggibilità strutturale.', 'sernicola-labs-ai-friendly' );
    }
    if ( $links === 0 ) {
        $suggestions[] = __( 'Valuta link diretti alle risorse principali.', 'sernicola-labs-ai-friendly' );
    }
    if ( empty( $suggestions ) ) {
        $suggestions[] = __( 'Struttura buona: mantieni sezioni brevi e stabili.', 'sernicola-labs-ai-friendly' );
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
