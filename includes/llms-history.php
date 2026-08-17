<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Restituisce il nome dell'opzione non autoloadata che contiene uno snapshot.
 */
function ai_fr_llms_snapshot_option_name( string $id ): string {
    return 'ai_fr_llms_snapshot_' . md5( $id );
}

/**
 * Blocca l'accesso web alla directory legacy durante la migrazione.
 */
function ai_fr_protect_llms_history_directory(): void {
    if ( ! is_dir( AI_FR_LLMS_HISTORY_DIR ) || ! wp_is_writable( AI_FR_LLMS_HISTORY_DIR ) ) {
        return;
    }

    $protection_files = [
        '.htaccess' => "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n",
        'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
        'index.php'  => "<?php\nexit;\n",
    ];

    foreach ( $protection_files as $filename => $content ) {
        $path = trailingslashit( AI_FR_LLMS_HISTORY_DIR ) . $filename;
        if ( ! file_exists( $path ) ) {
            file_put_contents( $path, $content );
        }
    }
}

/**
 * Elimina lo storage database e l'eventuale file legacy di uno snapshot.
 */
function ai_fr_delete_llms_snapshot_storage( array $entry ): void {
    $id = (string) ( $entry['id'] ?? '' );
    if ( $id !== '' ) {
        delete_option( ai_fr_llms_snapshot_option_name( $id ) );
    }

    $filename = basename( (string) ( $entry['filename'] ?? '' ) );
    if ( $filename === '' || ! preg_match( '/\A[a-z0-9._-]+\.txt\z/i', $filename ) ) {
        return;
    }

    $file = trailingslashit( AI_FR_LLMS_HISTORY_DIR ) . $filename;
    if ( file_exists( $file ) ) {
        wp_delete_file( $file );
        if ( file_exists( $file ) ) {
            delete_option( 'ai_fr_llms_storage_version' );
        }
    }
}

/**
 * Migra gli snapshot legacy da uploads a opzioni non autoloadate.
 */
function ai_fr_migrate_llms_history_storage(): void {
    ai_fr_protect_llms_history_directory();

    if ( get_option( 'ai_fr_llms_storage_version' ) === '2' ) {
        return;
    }

    $index = get_option( 'ai_fr_llms_history_index', [] );
    $index = is_array( $index ) ? $index : [];
    $migration_complete = true;
    $index_changed = false;
    $legacy_files = [];

    foreach ( $index as &$entry ) {
        if ( ! is_array( $entry ) || empty( $entry['id'] ) ) {
            continue;
        }

        $id = (string) $entry['id'];
        $option_name = ai_fr_llms_snapshot_option_name( $id );
        $stored = get_option( $option_name, null );
        $filename = basename( (string) ( $entry['filename'] ?? '' ) );
        $file = $filename !== '' ? trailingslashit( AI_FR_LLMS_HISTORY_DIR ) . $filename : '';

        if ( $file !== '' ) {
            $legacy_files[ $filename ] = true;
        }

        if ( ! is_string( $stored ) && $file !== '' && is_readable( $file ) ) {
            $content = file_get_contents( $file );
            if ( is_string( $content ) && add_option( $option_name, $content, '', false ) ) {
                $stored = $content;
            }
        }

        if ( ! is_string( $stored ) ) {
            $migration_complete = false;
            continue;
        }

        if ( $file !== '' && file_exists( $file ) ) {
            wp_delete_file( $file );
            if ( file_exists( $file ) ) {
                $migration_complete = false;
                continue;
            }
        }
        unset( $entry['filename'] );
        $entry['storage'] = 'option';
        $index_changed = true;
    }
    unset( $entry );

    if ( $index_changed ) {
        update_option( 'ai_fr_llms_history_index', $index, false );
    }

    // I file non presenti nell'indice sono snapshot oltre la retention legacy.
    $orphan_files = glob( AI_FR_LLMS_HISTORY_DIR . '/*.txt' );
    if ( is_array( $orphan_files ) ) {
        foreach ( $orphan_files as $file ) {
            if ( isset( $legacy_files[ basename( $file ) ] ) ) {
                continue;
            }
            wp_delete_file( $file );
            if ( file_exists( $file ) ) {
                $migration_complete = false;
            }
        }
    }

    if ( $migration_complete ) {
        update_option( 'ai_fr_llms_storage_version', '2', false );
    }
}

add_action( 'init', 'ai_fr_migrate_llms_history_storage', 1 );

/**
 * Restituisce indice snapshot.
 */
function ai_fr_get_llms_history_index(): array {
    $index = get_option( 'ai_fr_llms_history_index', [] );
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
        update_option( 'ai_fr_llms_history_index', $index, false );
    }

    return $index;
}

/**
 * Crea snapshot llms.txt.
 */
function ai_fr_create_llms_snapshot( string $content, string $reason = 'manual' ): array {
    ai_fr_migrate_llms_history_storage();

    $id = 'llms-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 6, false, false );
    $option_name = ai_fr_llms_snapshot_option_name( $id );

    $saved = add_option( $option_name, $content, '', false );
    if ( ! $saved ) {
        return [ 'saved' => false ];
    }

    $index      = ai_fr_get_llms_history_index();
    $prev_entry = $index[0] ?? null;
    $prev_text  = null;
    if ( is_array( $prev_entry ) && ! empty( $prev_entry['id'] ) ) {
        $prev_text = ai_fr_get_llms_snapshot_content( (string) $prev_entry['id'] );
    }

    $entry = [
        'id'         => $id,
        'storage'    => 'option',
        'created_at' => current_time( 'mysql' ),
        'reason'     => sanitize_text_field( $reason ),
        'chars'      => strlen( $content ),
        'tokens'     => ai_fr_estimate_tokens( $content ),
        'checksum'   => md5( $content ),
    ];
    if ( is_string( $prev_text ) ) {
        $diff = ai_fr_diff_llms_content( $prev_text, $content );
        $entry['note'] = sprintf(
            /* translators: 1: added lines, 2: removed lines, 3: token delta sign, 4: token delta. */
            __( 'Linee +%1$d / -%2$d, token %3$s%4$d', 'ai-friendly' ),
            intval( $diff['summary']['added_lines'] ?? 0 ),
            intval( $diff['summary']['removed_lines'] ?? 0 ),
            ( ( $diff['summary']['token_delta'] ?? 0 ) >= 0 ? '+' : '' ),
            intval( $diff['summary']['token_delta'] ?? 0 )
        );
    } else {
        $entry['note'] = __( 'Primo snapshot disponibile.', 'ai-friendly' );
    }

    array_unshift( $index, $entry );
    if ( count( $index ) > 100 ) {
        $removed = array_slice( $index, 100 );
        $index = array_slice( $index, 0, 100 );
        foreach ( $removed as $removed_entry ) {
            if ( is_array( $removed_entry ) ) {
                ai_fr_delete_llms_snapshot_storage( $removed_entry );
            }
        }
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
            $content = get_option( ai_fr_llms_snapshot_option_name( $id ), null );
            if ( is_string( $content ) ) {
                return $content;
            }

            // Compatibilita temporanea se un file legacy non e stato migrato.
            $filename = basename( (string) ( $entry['filename'] ?? '' ) );
            if ( $filename === '' || ! preg_match( '/\A[a-z0-9._-]+\.txt\z/i', $filename ) ) {
                return null;
            }
            $file = trailingslashit( AI_FR_LLMS_HISTORY_DIR ) . $filename;
            if ( file_exists( $file ) ) {
                $legacy_content = file_get_contents( $file );
                return is_string( $legacy_content ) ? $legacy_content : null;
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
        return [ 'restored' => false, 'message' => __( 'Snapshot non trovato.', 'ai-friendly' ) ];
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
 * Valida i link markdown nel contenuto llms.
 */
function ai_fr_validate_llms_links( string $content ): array {
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
                'message' => __( 'Link con formato non valido (atteso http/https o percorso relativo).', 'ai-friendly' ),
            ];
            continue;
        }

        if ( $is_http ) {
            $parts = wp_parse_url( $url );
            if ( empty( $parts['host'] ) ) {
                $issues[] = [
                    'url'      => $url,
                    'severity' => 'warning',
                    'message'  => __( 'URL non interpretabile.', 'ai-friendly' ),
                ];
                continue;
            }
        }

        if ( $is_rel && ! str_contains( $url, '.md' ) && ! str_contains( $url, 'llms.txt' ) ) {
            $issues[] = [
                'url'      => $url,
                'severity' => 'info',
                'message'  => __( 'Link relativo senza estensione .md o llms.txt.', 'ai-friendly' ),
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
function ai_fr_diff_llms_content( string $left, string $right ): array {
    $left_lines  = array_map( 'trim', explode( "\n", $left ) );
    $right_lines = array_map( 'trim', explode( "\n", $right ) );
    $left_lines  = array_values( array_filter( $left_lines, static fn( $x ) => $x !== '' ) );
    $right_lines = array_values( array_filter( $right_lines, static fn( $x ) => $x !== '' ) );

    $ops = ai_fr_build_diff_ops( $left_lines, $right_lines );

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
            'tokens'  => ai_fr_estimate_tokens( $left ),
            'lines'   => count( $left_lines ),
        ],
        'right' => [
            'content' => $right,
            'tokens'  => ai_fr_estimate_tokens( $right ),
            'lines'   => count( $right_lines ),
        ],
        'summary' => [
            'added_lines'   => count( $added_lines ),
            'removed_lines' => count( $removed_lines ),
            'token_delta'   => ai_fr_estimate_tokens( $right ) - ai_fr_estimate_tokens( $left ),
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
function ai_fr_build_diff_ops( array $left_lines, array $right_lines ): array {
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
        $suggestions[] = __( 'Riduci frasi ripetute o simili per migliorare compattezza.', 'ai-friendly' );
    }
    if ( $tokens > 2500 ) {
        $suggestions[] = __( 'Token elevati: valuta sintesi di sezioni secondarie.', 'ai-friendly' );
    }
    if ( $headings < 2 ) {
        $suggestions[] = __( 'Aggiungi heading per migliorare leggibilità strutturale.', 'ai-friendly' );
    }
    if ( $links === 0 ) {
        $suggestions[] = __( 'Valuta link diretti alle risorse principali.', 'ai-friendly' );
    }
    if ( empty( $suggestions ) ) {
        $suggestions[] = __( 'Struttura buona: mantieni sezioni brevi e stabili.', 'ai-friendly' );
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
