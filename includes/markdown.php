<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
//  3 â€” *.md
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

function ai_fr_serve_markdown( string $rel_path ): void {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public debug flag, restricted to admins before enabling debug mode.
    $debug_requested = isset( $_GET['debug'] ) && sanitize_text_field( wp_unslash( $_GET['debug'] ) ) !== '';
    $debug_mode = $debug_requested && current_user_can( 'manage_options' );
    $options = wp_parse_args( get_option( 'ai_fr_options', [] ), ai_fr_get_default_options() );
    $normalized_rel_path = ai_fr_normalize_relative_path( $rel_path );
    $archive_post_type = ai_fr_resolve_archive_post_type( $normalized_rel_path );

    try {
        $post_id = ai_fr_resolve_post( $rel_path );
        if ( ! $post_id ) {
            if ( $archive_post_type !== '' ) {
                ai_fr_serve_archive_markdown( $archive_post_type, $debug_mode, $debug_requested );
            }
            ai_fr_404();
        }

        $post = get_post( $post_id );
        if ( ! $post || $post->post_status !== 'publish' ) {
            if ( $archive_post_type !== '' ) {
                ai_fr_serve_archive_markdown( $archive_post_type, $debug_mode, $debug_requested );
            }
            ai_fr_404();
        }
        
        // Verifica filtri inclusione/esclusione
        $filter = new AiFrContentFilter();
        if ( ! $filter->shouldInclude( $post ) ) {
            if ( $archive_post_type !== '' ) {
                ai_fr_serve_archive_markdown( $archive_post_type, $debug_mode, $debug_requested );
            }
            ai_fr_404();
        }
        
        if ( ! ai_fr_can_serve_post( $post, 'serve' ) ) {
            if ( $archive_post_type !== '' ) {
                ai_fr_serve_archive_markdown( $archive_post_type, $debug_mode, $debug_requested );
            }
            ai_fr_404();
        }

        $canonical = get_permalink( $post_id );
        $canonical = apply_filters( 'ai_fr_md_canonical_url', $canonical, $post_id, $post );

        // Prova a servire versione statica se abilitato
        if ( ! empty( $options['static_md_files'] ) && ! $debug_mode ) {
            $static_content = AiFrVersioning::getVersion( $post_id );
            if ( is_string( $static_content ) && ai_fr_markdown_has_visible_content( $static_content ) ) {
                ai_fr_reset_output_buffers();
                
                status_header( 200 );
                header( 'Content-Type: text/markdown; charset=UTF-8' );
                header( 'X-Robots-Tag: noindex, follow' );
                header( 'Link: <' . $canonical . '>; rel="canonical"', false );
                header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
                header( 'Pragma: no-cache' );
                header( 'Expires: 0' );
                header( 'X-AI-Friendly-Source: static' );
                header( 'X-AI-Friendly-MD-Length: ' . strlen( $static_content ) );
                header( 'X-AI-Friendly-Version: ' . AI_FR_VERSION );
                
                echo $static_content;
                exit;
            }
        }

        // Genera dinamicamente (con cache)
        $md = '';
        $cache_key = '';
        if ( ! $debug_mode ) {
            $modified = get_post_modified_time( 'U', true, $post );
            $cache_key = 'ai_fr_md_' . $post_id . '_' . ( $modified ?: time() );
            $cached = get_transient( $cache_key );
            if ( is_string( $cached ) && ai_fr_markdown_has_visible_content( $cached ) ) {
                $md = $cached;
            }
        }
        
        if ( $md === '' ) {
            $md = ai_fr_generate_markdown( $post, $debug_mode );
            
            if ( ! ai_fr_markdown_has_visible_content( $md ) ) {
                $md = ai_fr_fallback_markdown( $post );
            }

            if ( ! $debug_mode && ai_fr_markdown_has_visible_content( $md ) && $cache_key !== '' ) {
                $ttl = (int) apply_filters( 'ai_fr_md_cache_ttl', HOUR_IN_SECONDS, $post_id, $post );
                if ( $ttl < 0 ) {
                    $ttl = 0;
                }
                set_transient( $cache_key, $md, $ttl );
                update_post_meta( $post_id, '_ai_fr_md_cache_key', $cache_key );
            }
        }
        
        if ( $debug_mode ) {
            $render_trace = ai_fr_debug_get_render_trace();
            $debug_output = "---\n## DEBUG INFO\n\n";
            $debug_output .= "**Post ID:** {$post_id}\n\n";
            $debug_output .= "**Post Type:** {$post->post_type}\n\n";
            $debug_output .= "**Static version:** " . ( AiFrVersioning::hasValidVersion( $post_id ) ? 'Yes' : 'No' ) . "\n\n";
            $debug_output .= "**Checksum:** " . md5( $md ) . "\n\n";
            $debug_output .= '**Path:** ' . ai_fr_normalize_relative_path( $rel_path ) . "\n\n";
            $resolve_trace = ai_fr_debug_get_resolve_trace();
            if ( ! empty( $resolve_trace ) ) {
                $debug_output .= "## Language Resolution\n\n";
                $debug_output .= '**Translation engine:** `' . (string) ( $resolve_trace['engine'] ?? 'none' ) . "`\n\n";
                $debug_output .= '**Translation matched path:** ' . ( ! empty( $resolve_trace['matched'] ) ? 'Yes' : 'No' ) . "\n\n";
                if ( isset( $resolve_trace['before_id'] ) ) {
                    $debug_output .= '**Resolved ID before translation:** ' . (int) $resolve_trace['before_id'] . "\n\n";
                }
                if ( isset( $resolve_trace['after_id'] ) ) {
                    $debug_output .= '**Resolved ID after translation:** ' . (int) $resolve_trace['after_id'] . "\n\n";
                }
                if ( isset( $resolve_trace['selected_permalink_path'] ) ) {
                    $debug_output .= '**Selected permalink path:** `' . (string) $resolve_trace['selected_permalink_path'] . "`\n\n";
                }
            }
            if ( ! empty( $render_trace ) ) {
                $debug_output .= "## Render Selection\n\n";
                if ( isset( $render_trace['selected_source'] ) ) {
                    $debug_output .= '**Selected source:** `' . $render_trace['selected_source'] . "`\n\n";
                }
                if ( isset( $render_trace['builder_candidate_present'] ) ) {
                    $debug_output .= '**Builder candidate present:** ' . ( $render_trace['builder_candidate_present'] ? 'Yes' : 'No' ) . "\n\n";
                }
                if ( isset( $render_trace['builder_candidate_usable'] ) ) {
                    $debug_output .= '**Builder candidate usable:** ' . ( $render_trace['builder_candidate_usable'] ? 'Yes' : 'No' ) . "\n\n";
                }
                if ( ! empty( $render_trace['builder_details'] ) && is_array( $render_trace['builder_details'] ) ) {
                    $debug_output .= "**Builder extraction details:**\n\n";
                    foreach ( $render_trace['builder_details'] as $key => $value ) {
                        if ( is_bool( $value ) ) {
                            $value = $value ? 'true' : 'false';
                        } elseif ( is_array( $value ) ) {
                            $value = wp_json_encode( $value );
                        }
                        $debug_output .= '- `' . $key . '`: ' . $value . "\n";
                    }
                    $debug_output .= "\n";
                }
                if ( ! empty( $render_trace['candidates'] ) && is_array( $render_trace['candidates'] ) ) {
                    $debug_output .= "## Candidate Scores\n\n";
                    foreach ( $render_trace['candidates'] as $name => $info ) {
                        if ( ! is_array( $info ) ) {
                            continue;
                        }
                        $debug_output .= '### `' . $name . "`\n\n";
                        $debug_output .= '- score: ' . (int) ( $info['score'] ?? 0 ) . "\n";
                        $debug_output .= '- html_length: ' . (int) ( $info['html_length'] ?? 0 ) . "\n";
                        $debug_output .= '- text_length: ' . (int) ( $info['text_length'] ?? 0 ) . "\n";
                        $debug_output .= '- line_count: ' . (int) ( $info['line_count'] ?? 0 ) . "\n";
                        $debug_output .= '- paragraph_count: ' . (int) ( $info['paragraph_count'] ?? 0 ) . "\n";
                        $debug_output .= '- sentence_like: ' . (int) ( $info['sentence_like'] ?? 0 ) . "\n";
                        $debug_output .= '- suspicious_ratio: ' . number_format( (float) ( $info['suspicious_ratio'] ?? 1 ), 2, '.', '' ) . "\n\n";
                    }
                }
            }
            $debug_output .= "---\n\n";
            
            // Inserisci dopo frontmatter
            $md = preg_replace( '/^(---\n.*?\n---\n\n)/s', "$1" . $debug_output, $md ) ?? $debug_output . $md;
        }

        if ( ! ai_fr_markdown_has_visible_content( $md ) ) {
            $md = ai_fr_fallback_markdown( $post );
        }

        ai_fr_reset_output_buffers();

        status_header( 200 );
        header( 'Content-Type: text/markdown; charset=UTF-8' );
        header( 'X-Robots-Tag: noindex, follow' );
        header( 'Link: <' . $canonical . '>; rel="canonical"', false );
        header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );
        header( 'X-AI-Friendly-Source: dynamic' );
        header( 'X-AI-Friendly-MD-Length: ' . strlen( $md ) );
        header( 'X-AI-Friendly-Version: ' . AI_FR_VERSION );
        header( 'X-AI-Friendly-Debug-Requested: ' . ( $debug_requested ? '1' : '0' ) );
        header( 'X-AI-Friendly-Debug-Admin: ' . ( $debug_mode ? '1' : '0' ) );

        echo $md;
        exit;

    } catch ( Throwable $e ) {
        status_header( 500 );
        header( 'Content-Type: text/plain; charset=UTF-8' );
        echo $debug_mode
            ? "Errore: " . esc_html( $e->getMessage() )
            : "Errore nella generazione del contenuto Markdown.";
        exit;
    }
}

/**
 * Verifica se un post puÃ² essere servito pubblicamente (llms.txt o .md).
 * Consente override tramite filtro.
 */
function ai_fr_can_serve_post( WP_Post $post, string $context = 'public' ): bool {
    $can = true;
    
    // Contenuto protetto da password
    if ( post_password_required( $post ) ) {
        $can = false;
    }
    
    // Verifica capability solo per il contesto di serving diretto
    if ( $can && $context === 'serve' ) {
        // I contenuti pubblicati devono essere accessibili anche agli utenti non loggati.
        // La capability serve solo per contenuti non pubblici.
        if ( $post->post_status !== 'publish' && ! current_user_can( 'read_post', $post->ID ) ) {
            $can = false;
        }
    }
    
    /**
     * Filtra la possibilitÃ  di servire un contenuto.
     *
     * @param bool    $can     True se il contenuto puÃ² essere servito.
     * @param WP_Post $post    Il post in esame.
     * @param string  $context Contesto: 'llms', 'serve', o altro.
     */
    return (bool) apply_filters( 'ai_fr_can_serve_post', $can, $post, $context );
}

function ai_fr_get_rendered_content_safe( WP_Post $source_post, bool $debug = false ): string {
    $content = $source_post->post_content;
    $post_id = $source_post->ID;
    $candidates = [];
    $builder_content = '';
    $builder_is_usable = false;
    $trace = [
        'selected_source' => '',
        'builder_candidate_present' => false,
        'builder_candidate_usable' => false,
        'builder_details' => [],
        'candidates' => [],
    ];
    ai_fr_debug_set_render_trace( [] );

    $builder_content = ai_fr_try_page_builders( $post_id, $content, $debug );
    $trace['builder_details'] = ai_fr_debug_get_builder_trace();
    $trace['builder_candidate_present'] = trim( wp_strip_all_tags( $builder_content ) ) !== '';
    $builder_is_usable = $trace['builder_candidate_present'] && ai_fr_builder_content_is_usable( $builder_content );
    $trace['builder_candidate_usable'] = $builder_is_usable;
    if ( $builder_is_usable ) {
        $candidates['builder'] = $builder_content;
    }

    $html = $content;

    if ( function_exists( 'has_blocks' ) && has_blocks( $html ) ) {
        if ( function_exists( 'do_blocks' ) ) {
            $html = do_blocks( $html );
        }
    }

    if ( function_exists( 'do_shortcode' ) ) {
        $html = do_shortcode( $html );
    }

    if ( function_exists( 'wpautop' ) ) {
        $html = wpautop( $html );
    }

    $text_check = trim( wp_strip_all_tags( $html ) );
    if ( strlen( $text_check ) > 50 ) {
        $candidates['rendered'] = $html;
    }

    $filtered = '';
    $old_global_post = null;
    global $post;
    try {
        $old_global_post = $post;
        $post = get_post( $post_id );
        setup_postdata( $post );

        ob_start();
        $filtered = apply_filters( 'the_content', $content );
        ob_end_clean();
    } catch ( Throwable $e ) {
        // Ignora
    } finally {
        wp_reset_postdata();
        if ( $old_global_post instanceof WP_Post ) {
            $post = $old_global_post;
        }
    }

    if ( strlen( trim( wp_strip_all_tags( $filtered ) ) ) > 50 ) {
        $candidates['the_content'] = $filtered;
    }

    $fallback = ai_fr_extract_text_from_raw( $content );
    if ( ! empty( $fallback ) ) {
        $candidates['fallback'] = wpautop( $fallback );
    }

    if ( $builder_is_usable ) {
        $merge_parts = [ ai_fr_html_to_plain_text_for_merge( $builder_content ) ];
        foreach ( [ 'rendered', 'the_content', 'fallback' ] as $candidate_key ) {
            if ( ! empty( $candidates[ $candidate_key ] ) && is_string( $candidates[ $candidate_key ] ) ) {
                $merge_parts[] = ai_fr_html_to_plain_text_for_merge( $candidates[ $candidate_key ] );
            }
        }

        $merged_text = ai_fr_merge_builder_text_parts( $merge_parts );
        if ( $merged_text !== '' ) {
            $merged_html = function_exists( 'wpautop' ) ? wpautop( $merged_text ) : $merged_text;
            if ( trim( wp_strip_all_tags( $merged_html ) ) !== trim( wp_strip_all_tags( $builder_content ) ) ) {
                $candidates['builder_merged'] = $merged_html;
            }
        }
    }

    $result = ai_fr_pick_best_rendered_content( $candidates, $trace );
    if ( $debug ) {
        ai_fr_debug_set_render_trace( $trace );
    }
    return $result;
}

function ai_fr_html_to_plain_text_for_merge( string $html ): string {
    $text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    $text = preg_replace( "/\r\n?/", "\n", $text ) ?? $text;
    $text = preg_replace( "/[ \t]+/", ' ', $text ) ?? $text;
    return trim( $text );
}

function ai_fr_builder_content_is_usable( string $html ): bool {
    $metrics = ai_fr_rendered_content_metrics( $html );

    if ( $metrics['total_chars'] < 80 ) {
        return false;
    }

    if ( $metrics['sentence_like'] >= 3 ) {
        return $metrics['suspicious_ratio'] < 0.6;
    }

    if ( $metrics['sentence_like'] >= 2 ) {
        return $metrics['suspicious_ratio'] < 0.45 || $metrics['total_chars'] >= 300;
    }

    return $metrics['sentence_like'] >= 1 && $metrics['suspicious_ratio'] < 0.25 && $metrics['total_chars'] >= 180;
}

function ai_fr_pick_best_rendered_content( array $candidates, array &$trace = [] ): string {
    $best_content = '';
    $best_score = -1;
    $best_source = '';

    foreach ( $candidates as $name => $candidate ) {
        if ( ! is_string( $candidate ) || trim( wp_strip_all_tags( $candidate ) ) === '' ) {
            continue;
        }

        $score = ai_fr_content_quality_score( $candidate );
        $metrics = ai_fr_rendered_content_metrics( $candidate );
        $trace['candidates'][ $name ] = [
            'score' => $score,
            'html_length' => strlen( $candidate ),
            'text_length' => (int) $metrics['total_chars'],
            'line_count' => (int) $metrics['line_count'],
            'paragraph_count' => (int) $metrics['paragraph_count'],
            'sentence_like' => (int) $metrics['sentence_like'],
            'suspicious_ratio' => (float) $metrics['suspicious_ratio'],
        ];
        if ( $score > $best_score ) {
            $best_score = $score;
            $best_content = $candidate;
            $best_source = (string) $name;
        }
    }

    $trace['selected_source'] = $best_source;
    return $best_content;
}

function ai_fr_content_quality_score( string $html ): int {
    $m = ai_fr_rendered_content_metrics( $html );
    if ( $m['line_count'] === 0 || $m['total_chars'] === 0 ) {
        return 0;
    }

    $score = 0;
    $score += min( 40, (int) floor( $m['total_chars'] / 20 ) );
    $score += min( 35, $m['sentence_like'] * 10 );
    $score += min( 20, $m['paragraph_count'] * 5 );

    if ( $m['sentence_like'] === 0 ) {
        $score -= 20;
    }

    if ( $m['line_count'] > 0 ) {
        $score -= (int) round( $m['suspicious_ratio'] * 45 );
    }

    if ( $m['suspicious_ratio'] > 0.65 ) {
        $score -= 15;
    }

    return max( 0, min( 100, $score ) );
}

function ai_fr_rendered_content_metrics( string $html ): array {
    $text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    $text = preg_replace( "/\r\n?/", "\n", $text ) ?? $text;
    $text = preg_replace( "/[ \t]+/", ' ', $text ) ?? $text;
    $text = trim( $text );

    if ( $text === '' ) {
        return [
            'line_count' => 0,
            'paragraph_count' => 0,
            'total_chars' => 0,
            'sentence_like' => 0,
            'suspicious_ratio' => 1.0,
        ];
    }

    $paragraphs = preg_split( "/\n{2,}/", $text ) ?: [];
    $paragraphs = array_values(
        array_filter(
            array_map( 'trim', $paragraphs ),
            static fn( string $line ): bool => $line !== ''
        )
    );

    $lines = preg_split( "/\n+/", $text ) ?: [];
    $lines = array_values(
        array_filter(
            array_map( 'trim', $lines ),
            static fn( string $line ): bool => $line !== ''
        )
    );

    $sentence_like = 0;
    $suspicious = 0;
    foreach ( $lines as $line ) {
        $line_lower = strtolower( $line );
        $word_count = str_word_count( preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $line ) ?? $line );

        $looks_email = (bool) preg_match( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $line );
        $looks_datetime = (bool) preg_match( '/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/', $line );
        $looks_filename = (bool) preg_match( '/\b[a-z0-9._-]+\.(?:jpg|jpeg|png|webp|gif|svg|pdf|docx?|xlsx?)\b/i', $line );
        $looks_slug = (bool) preg_match( '/^[a-z0-9._-]{3,}$/', $line ) && ! preg_match( '/\s/u', $line );

        if ( $looks_email || $looks_datetime || $looks_filename || $looks_slug ) {
            $suspicious++;
            continue;
        }

        if ( $word_count >= 7 || preg_match( '/[.!?;:]/', $line_lower ) ) {
            $sentence_like++;
            continue;
        }

        if ( $word_count <= 2 && strlen( $line ) < 24 ) {
            $suspicious++;
        }
    }

    $line_count = count( $lines );
    $total_chars = strlen( preg_replace( '/\s+/', '', $text ) ?? $text );

    return [
        'line_count' => $line_count,
        'paragraph_count' => count( $paragraphs ),
        'total_chars' => $total_chars,
        'sentence_like' => $sentence_like,
        'suspicious_ratio' => $line_count > 0 ? ( $suspicious / $line_count ) : 1.0,
    ];
}

function ai_fr_try_page_builders( int $post_id, string $content, bool $debug = false ): string {
    $acf_text = null;
    $woo_text = '';
    $builder_trace = [
        'selected_builder' => '',
        'breakdance_present' => false,
        'yootheme_present' => false,
        'elementor_present' => false,
        'oxygen_present' => false,
        'bricks_present' => false,
        'acf_present' => false,
        'breakdance_text_len' => 0,
        'yootheme_text_len' => 0,
        'elementor_text_len' => 0,
        'oxygen_text_len' => 0,
        'bricks_text_len' => 0,
        'acf_text_len' => 0,
        'woo_text_len' => 0,
        'woo_attributes_count' => 0,
        'yootheme_acf_merged_len' => 0,
    ];
    ai_fr_debug_set_builder_trace( [] );

    if ( get_post_type( $post_id ) === 'product' ) {
        $woo_data = ai_fr_extract_woocommerce_product_text( $post_id );
        if ( is_array( $woo_data ) ) {
            $woo_text = (string) ( $woo_data['text'] ?? '' );
            $builder_trace['woo_text_len'] = strlen( trim( $woo_text ) );
            $builder_trace['woo_attributes_count'] = (int) ( $woo_data['attributes_count'] ?? 0 );
        }
    }

    $breakdance_data = get_post_meta( $post_id, '_breakdance_data', true );
    $builder_trace['breakdance_present'] = ! empty( $breakdance_data );
    if ( ! empty( $breakdance_data ) ) {
        $extracted = ai_fr_extract_breakdance_text( $breakdance_data );
        $builder_trace['breakdance_text_len'] = strlen( trim( $extracted ) );
        $merged = ai_fr_merge_builder_text_parts( [ $extracted, $woo_text ] );
        if ( ! empty( $merged ) ) {
            $builder_trace['selected_builder'] = $woo_text !== '' ? 'breakdance+woo' : 'breakdance';
            if ( $debug ) {
                ai_fr_debug_set_builder_trace( $builder_trace );
            }
            return wpautop( $merged );
        }
    }

    $yootheme_data = get_post_meta( $post_id, '_yootheme', true );
    $builder_trace['yootheme_present'] = ! empty( $yootheme_data );
    if ( ! empty( $yootheme_data ) ) {
        if ( is_string( $yootheme_data ) ) {
            $data = json_decode( $yootheme_data, true );
            if ( is_array( $data ) ) {
                $extracted = ai_fr_extract_yootheme_text( $data );
                $builder_trace['yootheme_text_len'] = strlen( trim( $extracted ) );
                if ( $acf_text === null ) {
                    $acf_text = ai_fr_extract_acf_text( $post_id );
                    $builder_trace['acf_present'] = $acf_text !== '';
                    $builder_trace['acf_text_len'] = strlen( trim( $acf_text ) );
                }
                $merged = ai_fr_merge_builder_text_parts( [ $extracted, $acf_text, $woo_text ] );
                $builder_trace['yootheme_acf_merged_len'] = strlen( trim( $merged ) );
                if ( $merged !== '' ) {
                    $builder_trace['selected_builder'] = $woo_text !== '' ? 'yootheme+acf+woo' : 'yootheme+acf';
                    if ( $debug ) {
                        ai_fr_debug_set_builder_trace( $builder_trace );
                    }
                    return wpautop( $merged );
                }
            }
        }
    }

    $elementor_data = get_post_meta( $post_id, '_elementor_data', true );
    $builder_trace['elementor_present'] = ! empty( $elementor_data );
    if ( ! empty( $elementor_data ) ) {
        if ( is_string( $elementor_data ) ) {
            $data = json_decode( $elementor_data, true );
            if ( is_array( $data ) ) {
                $extracted = ai_fr_extract_elementor_text( $data );
                $builder_trace['elementor_text_len'] = strlen( trim( $extracted ) );
                $merged = ai_fr_merge_builder_text_parts( [ $extracted, $woo_text ] );
                if ( ! empty( $merged ) ) {
                    $builder_trace['selected_builder'] = $woo_text !== '' ? 'elementor+woo' : 'elementor';
                    if ( $debug ) {
                        ai_fr_debug_set_builder_trace( $builder_trace );
                    }
                    return wpautop( $merged );
                }
            }
        }
    }

    $oxygen_data = get_post_meta( $post_id, 'ct_builder_shortcodes', true );
    $builder_trace['oxygen_present'] = ! empty( $oxygen_data );
    if ( ! empty( $oxygen_data ) ) {
        $extracted = ai_fr_extract_text_from_raw( $oxygen_data );
        $builder_trace['oxygen_text_len'] = strlen( trim( $extracted ) );
        $merged = ai_fr_merge_builder_text_parts( [ $extracted, $woo_text ] );
        if ( ! empty( $merged ) ) {
            $builder_trace['selected_builder'] = $woo_text !== '' ? 'oxygen+woo' : 'oxygen';
            if ( $debug ) {
                ai_fr_debug_set_builder_trace( $builder_trace );
            }
            return wpautop( $merged );
        }
    }

    $bricks_data = get_post_meta( $post_id, '_bricks_page_content_2', true );
    if ( empty( $bricks_data ) ) {
        $bricks_data = get_post_meta( $post_id, '_bricks_page_content', true );
    }
    $builder_trace['bricks_present'] = ! empty( $bricks_data );
    if ( ! empty( $bricks_data ) && is_array( $bricks_data ) ) {
        $extracted = ai_fr_extract_bricks_text( $bricks_data );
        $builder_trace['bricks_text_len'] = strlen( trim( $extracted ) );
        $merged = ai_fr_merge_builder_text_parts( [ $extracted, $woo_text ] );
        if ( ! empty( $merged ) ) {
            $builder_trace['selected_builder'] = $woo_text !== '' ? 'bricks+woo' : 'bricks';
            if ( $debug ) {
                ai_fr_debug_set_builder_trace( $builder_trace );
            }
            return wpautop( $merged );
        }
    }

    if ( $acf_text === null ) {
        $acf_text = ai_fr_extract_acf_text( $post_id );
        $builder_trace['acf_present'] = $acf_text !== '';
        $builder_trace['acf_text_len'] = strlen( trim( $acf_text ) );
    }
    if ( $acf_text !== '' ) {
        $merged = ai_fr_merge_builder_text_parts( [ $acf_text, $woo_text ] );
        $builder_trace['selected_builder'] = $woo_text !== '' ? 'acf+woo' : 'acf';
        if ( $debug ) {
            ai_fr_debug_set_builder_trace( $builder_trace );
        }
        return wpautop( $merged !== '' ? $merged : $acf_text );
    }

    if ( $woo_text !== '' ) {
        $builder_trace['selected_builder'] = 'woo';
        if ( $debug ) {
            ai_fr_debug_set_builder_trace( $builder_trace );
        }
        return wpautop( $woo_text );
    }

    if ( $debug ) {
        ai_fr_debug_set_builder_trace( $builder_trace );
    }
    return '';
}

function ai_fr_debug_set_render_trace( array $trace ): void {
    $GLOBALS['ai_fr_render_trace'] = $trace;
}

function ai_fr_debug_get_render_trace(): array {
    $trace = $GLOBALS['ai_fr_render_trace'] ?? [];
    return is_array( $trace ) ? $trace : [];
}

function ai_fr_debug_set_builder_trace( array $trace ): void {
    $GLOBALS['ai_fr_builder_trace'] = $trace;
}

function ai_fr_debug_get_builder_trace(): array {
    $trace = $GLOBALS['ai_fr_builder_trace'] ?? [];
    return is_array( $trace ) ? $trace : [];
}

function ai_fr_debug_set_resolve_trace( array $trace ): void {
    $GLOBALS['ai_fr_resolve_trace'] = $trace;
}

function ai_fr_debug_get_resolve_trace(): array {
    $trace = $GLOBALS['ai_fr_resolve_trace'] ?? [];
    return is_array( $trace ) ? $trace : [];
}

function ai_fr_extract_breakdance_text( $data ): string {
    if ( is_string( $data ) ) {
        $data = json_decode( $data, true );
        if ( ! is_array( $data ) ) {
            $data = maybe_unserialize( $data );
        }
    }
    if ( ! is_array( $data ) ) {
        return '';
    }
    return ai_fr_recursive_text_extract( $data, [ 'text', 'content', 'title', 'heading', 'paragraph', 'html', 'value' ] );
}

function ai_fr_extract_elementor_text( array $data ): string {
    return ai_fr_recursive_text_extract( $data, [ 'title', 'description', 'content', 'text', 'editor', 'html', 'heading_title' ] );
}

function ai_fr_extract_bricks_text( array $data ): string {
    return ai_fr_recursive_text_extract( $data, [ 'text', 'content', 'title', 'heading', 'paragraph', 'html' ] );
}

function ai_fr_extract_yootheme_text( array $data, string $result = '' ): string {
    return ai_fr_recursive_text_extract(
        $data,
        [ 'content', 'text', 'title', 'lead', 'heading', 'paragraph', 'description', 'subtitle', 'intro', 'html' ],
        $result
    );
}

function ai_fr_merge_builder_text_parts( array $parts ): string {
    $seen = [];
    $merged = [];
    $merged_norm = [];

    foreach ( $parts as $part ) {
        if ( ! is_string( $part ) || trim( $part ) === '' ) {
            continue;
        }

        $chunks = preg_split( "/\n{2,}/", trim( $part ) ) ?: [];
        foreach ( $chunks as $chunk ) {
            $clean = trim( preg_replace( "/[ \t]+/", ' ', $chunk ) ?? $chunk );
            if ( $clean === '' ) {
                continue;
            }

            $probe = ai_fr_normalize_text_for_dedupe( $clean );
            if ( $probe === '' ) {
                continue;
            }

            if ( isset( $seen[ $probe ] ) ) {
                continue;
            }

            if ( ai_fr_is_duplicate_merged_chunk( $probe, $merged_norm ) ) {
                continue;
            }

            $seen[ $probe ] = true;
            $merged[] = $clean;
            $merged_norm[] = $probe;
        }
    }

    return implode( "\n\n", $merged );
}

function ai_fr_normalize_text_for_dedupe( string $text ): string {
    $text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    $text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
    $text = preg_replace( '/[\p{P}\p{S}]+/u', ' ', $text ) ?? $text;
    $text = preg_replace( '/\s+/u', ' ', $text ) ?? $text;
    return trim( $text );
}

function ai_fr_is_duplicate_merged_chunk( string $probe, array $existing_probes ): bool {
    foreach ( $existing_probes as $existing ) {
        if ( ! is_string( $existing ) || $existing === '' ) {
            continue;
        }

        if ( $probe === $existing ) {
            return true;
        }

        $probe_len = strlen( $probe );
        $existing_len = strlen( $existing );

        // Avoid aggressive collapsing for short labels/headings.
        if ( $probe_len < 60 || $existing_len < 60 ) {
            continue;
        }

        if ( str_contains( $existing, $probe ) || str_contains( $probe, $existing ) ) {
            return true;
        }
    }

    return false;
}

function ai_fr_extract_acf_text( int $post_id ): string {
    if ( ! function_exists( 'get_fields' ) ) {
        return '';
    }

    $fields = get_fields( $post_id );
    if ( ! is_array( $fields ) || empty( $fields ) ) {
        return '';
    }

    $text = ai_fr_recursive_mixed_text_extract( $fields );
    $text = preg_replace( "/\n{3,}/", "\n\n", $text ) ?? $text;
    return trim( $text );
}

function ai_fr_recursive_mixed_text_extract( $value, int $depth = 0, string $result = '', string $current_key = '', bool $media_context = false ): string {
    if ( $depth > 8 ) {
        return $result;
    }

    if ( is_string( $value ) ) {
        $clean = trim( wp_strip_all_tags( html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
        if (
            $clean !== ''
            && strlen( $clean ) > 2
            && ! ai_fr_should_skip_acf_string_value( $clean, $current_key, $media_context )
        ) {
            $result .= $clean . "\n\n";
        }
        return $result;
    }

    if ( is_array( $value ) ) {
        $media_context = $media_context || ai_fr_is_media_like_array( $value );

        foreach ( $value as $key => $item ) {
            if ( is_string( $key ) ) {
                if ( ai_fr_should_skip_acf_key( $key, $media_context ) ) {
                    continue;
                }
                $result = ai_fr_recursive_mixed_text_extract( $item, $depth + 1, $result, $key, $media_context );
                continue;
            }
            $result = ai_fr_recursive_mixed_text_extract( $item, $depth + 1, $result, '', $media_context );
        }
        return $result;
    }

    if ( is_object( $value ) ) {
        return ai_fr_recursive_mixed_text_extract( get_object_vars( $value ), $depth + 1, $result, $current_key, $media_context );
    }

    return $result;
}

function ai_fr_should_skip_acf_key( string $key, bool $media_context = false ): bool {
    if ( $key === '' || str_starts_with( $key, '_' ) ) {
        return true;
    }

    $normalized = strtolower( $key );
    $ignored_generic = [
        'acf_fc_layout',
        'id',
        'key',
        'menu_order',
        'guid',
    ];
    if ( in_array( $normalized, $ignored_generic, true ) ) {
        return true;
    }

    if ( ! $media_context ) {
        return false;
    }

    $ignored_media = [
        'name',
        'filename',
        'filesize',
        'status',
        'post_status',
        'post_type',
        'type',
        'subtype',
        'mime_type',
        'filemime',
        'extension',
        'date',
        'date_gmt',
        'modified',
        'modified_gmt',
        'uploaded_to',
        'width',
        'height',
        'sizes',
        'icon',
    ];

    return in_array( $normalized, $ignored_media, true );
}

function ai_fr_should_skip_acf_string_value( string $value, string $current_key = '', bool $media_context = false ): bool {
    if ( preg_match( '#^https?://\S+$#i', $value ) ) {
        return true;
    }

    if ( $media_context ) {
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}:\d{2})?$/', $value ) ) {
            return true;
        }
        if ( preg_match( '/^[a-z]+\/[a-z0-9.+-]+$/i', $value ) ) {
            return true;
        }
        if ( preg_match( '/^[a-z0-9._-]+\.(?:jpg|jpeg|png|webp|gif|svg|pdf|docx?|xlsx?)$/i', $value ) ) {
            return true;
        }
        if ( preg_match( '/^[a-z0-9._-]{3,}$/i', $value ) && ! preg_match( '/\s/u', $value ) ) {
            $key = strtolower( $current_key );
            if ( in_array( $key, [ 'name', 'filename', 'slug', 'post_name' ], true ) ) {
                return true;
            }
        }
    }

    return false;
}

function ai_fr_is_media_like_array( array $value ): bool {
    $keys = array_map(
        static fn( $k ) => is_string( $k ) ? strtolower( $k ) : '',
        array_keys( $value )
    );
    $signals = [
        'mime_type',
        'subtype',
        'filename',
        'filesize',
        'sizes',
        'width',
        'height',
        'icon',
        'uploaded_to',
    ];

    $count = 0;
    foreach ( $signals as $signal ) {
        if ( in_array( $signal, $keys, true ) ) {
            $count++;
        }
    }

    return $count >= 2;
}

function ai_fr_recursive_text_extract( array $data, array $keys, string $result = '' ): string {
    foreach ( $data as $key => $value ) {
        if ( is_string( $value ) && in_array( $key, $keys, true ) ) {
            $clean = trim( wp_strip_all_tags( html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
            if ( ! empty( $clean ) && strlen( $clean ) > 2 ) {
                $result .= $clean . "\n\n";
            }
        } elseif ( is_array( $value ) ) {
            $result = ai_fr_recursive_text_extract( $value, $keys, $result );
        }
    }
    return $result;
}

function ai_fr_extract_text_from_raw( string $content ): string {
    $text = preg_replace( '/\[[^\]]+\]/', '', $content ) ?? $content;
    $text = preg_replace( '/\{[^}]+\}/', '', $text ) ?? $text;
    $text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    $text = wp_strip_all_tags( $text );
    $text = preg_replace( '/\s+/', ' ', $text ) ?? $text;
    return trim( $text );
}

function ai_fr_resolve_post( string $path ): int {
    $path = ai_fr_normalize_relative_path( $path );
    ai_fr_debug_set_resolve_trace( [ 'requested_path' => $path ] );
    if ( empty( $path ) ) {
        return 0;
    }

    $public_types = get_post_types( [ 'public' => true ], 'names' );
    if ( empty( $public_types ) ) {
        $public_types = [ 'page', 'post', 'product' ];
    }

    // First pass: let WordPress resolve the URL exactly as routed.
    $url_candidates = [
        home_url( '/' . $path ),
        home_url( '/' . $path . '/' ),
    ];
    foreach ( $url_candidates as $candidate ) {
        $candidate_id = url_to_postid( $candidate );
        if ( $candidate_id > 0 ) {
            $candidate_post = get_post( $candidate_id );
            if ( $candidate_post && $candidate_post->post_status === 'publish' ) {
                return ai_fr_match_translated_post_to_path( (int) $candidate_id, $path );
            }
        }
    }

    $page = get_page_by_path( $path, OBJECT, $public_types );
    if ( $page && $page->post_status === 'publish' ) {
        return ai_fr_match_translated_post_to_path( (int) $page->ID, $path );
    }

    $slug = basename( $path );
    if ( ! empty( $slug ) ) {
        $slug_candidates = array_values(
            array_unique(
                array_filter(
                    [
                        $slug,
                        sanitize_title( $slug ),
                    ]
                )
            )
        );

        foreach ( $slug_candidates as $slug_candidate ) {
            $posts = get_posts( [
                'name'           => $slug_candidate,
                'post_type'      => $public_types,
                'post_status'    => 'publish',
                'posts_per_page' => 20,
                'no_found_rows'  => true,
            ] );

            if ( empty( $posts ) ) {
                continue;
            }

            foreach ( $posts as $post ) {
                $permalink = get_permalink( $post->ID );
                if ( ! is_string( $permalink ) || $permalink === '' ) {
                    continue;
                }
                if ( ai_fr_relative_path_from_url( $permalink ) === $path ) {
                    return ai_fr_match_translated_post_to_path( (int) $post->ID, $path );
                }
            }

            // Evita match ambigui: fallback solo se c'e un candidato unico.
            if ( count( $posts ) === 1 ) {
                return ai_fr_match_translated_post_to_path( (int) $posts[0]->ID, $path );
            }
        }
    }

    return 0;
}

/**
 * Estrae testo "canonico" da un prodotto WooCommerce se disponibile.
 *
 * @return array{text:string,attributes_count:int}
 */
function ai_fr_extract_woocommerce_product_text( int $post_id ): array {
    if ( ! function_exists( 'wc_get_product' ) ) {
        return [ 'text' => '', 'attributes_count' => 0 ];
    }

    $product = wc_get_product( $post_id );
    if ( ! $product instanceof WC_Product ) {
        return [ 'text' => '', 'attributes_count' => 0 ];
    }

    $parts = [];

    $short_description = method_exists( $product, 'get_short_description' ) ? (string) $product->get_short_description() : '';
    $short_description = trim( wp_strip_all_tags( html_entity_decode( $short_description, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
    if ( $short_description !== '' ) {
        $parts[] = $short_description;
    }

    $description = method_exists( $product, 'get_description' ) ? (string) $product->get_description() : '';
    $description = trim( wp_strip_all_tags( html_entity_decode( $description, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
    if ( $description !== '' ) {
        $parts[] = $description;
    }

    $attributes_count = 0;
    if ( method_exists( $product, 'get_attributes' ) ) {
        $attributes = $product->get_attributes();
        if ( is_array( $attributes ) ) {
            foreach ( $attributes as $attribute ) {
                if ( ! $attribute instanceof WC_Product_Attribute ) {
                    continue;
                }

                if ( method_exists( $attribute, 'get_visible' ) && ! $attribute->get_visible() ) {
                    continue;
                }

                $values = [];
                if ( method_exists( $attribute, 'is_taxonomy' ) && $attribute->is_taxonomy() ) {
                    $taxonomy = method_exists( $attribute, 'get_name' ) ? (string) $attribute->get_name() : '';
                    if ( $taxonomy !== '' && function_exists( 'wc_get_product_terms' ) ) {
                        $values = wc_get_product_terms( $post_id, $taxonomy, [ 'fields' => 'names' ] );
                    }
                } else {
                    $options = method_exists( $attribute, 'get_options' ) ? $attribute->get_options() : [];
                    if ( is_array( $options ) ) {
                        $values = array_map( 'strval', $options );
                    }
                }

                $values = array_values(
                    array_filter(
                        array_map(
                            static fn( $v ) => trim( wp_strip_all_tags( html_entity_decode( (string) $v, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ),
                            is_array( $values ) ? $values : []
                        ),
                        static fn( string $v ): bool => $v !== ''
                    )
                );

                if ( empty( $values ) ) {
                    continue;
                }

                $label = '';
                if ( function_exists( 'wc_attribute_label' ) && method_exists( $attribute, 'get_name' ) ) {
                    $label = trim( (string) wc_attribute_label( (string) $attribute->get_name(), $product ) );
                }

                $line = implode( ', ', array_unique( $values ) );
                if ( $label !== '' ) {
                    $line = $label . ': ' . $line;
                }

                if ( trim( $line ) !== '' ) {
                    $parts[] = $line;
                    $attributes_count++;
                }
            }
        }
    }

    return [
        'text' => ai_fr_merge_builder_text_parts( $parts ),
        'attributes_count' => $attributes_count,
    ];
}

function ai_fr_match_translated_post_to_path( int $post_id, string $requested_path ): int {
    if ( $post_id <= 0 || $requested_path === '' ) {
        return $post_id;
    }

    $requested_path = ai_fr_normalize_relative_path( $requested_path );
    $trace = ai_fr_debug_get_resolve_trace();
    $trace['before_id'] = $post_id;
    $trace['after_id'] = $post_id;
    $trace['engine'] = 'none';
    $trace['matched'] = false;

    $direct_permalink = get_permalink( $post_id );
    if ( is_string( $direct_permalink ) && $direct_permalink !== '' ) {
        $direct_path = ai_fr_relative_path_from_url( $direct_permalink );
        $trace['selected_permalink_path'] = $direct_path;
        if ( $direct_path === $requested_path ) {
            ai_fr_debug_set_resolve_trace( $trace );
            return $post_id;
        }
    }

    if ( ai_fr_wpml_is_available() ) {
        $matched_id = ai_fr_match_wpml_translation_to_path( $post_id, $requested_path, $trace );
        $trace['engine'] = 'wpml';
        if ( $matched_id > 0 ) {
            $trace['matched'] = true;
            $trace['after_id'] = $matched_id;
            ai_fr_debug_set_resolve_trace( $trace );
            return $matched_id;
        }
        ai_fr_debug_set_resolve_trace( $trace );
        return $post_id;
    }

    if ( ai_fr_polylang_is_available() ) {
        $matched_id = ai_fr_match_polylang_translation_to_path( $post_id, $requested_path, $trace );
        $trace['engine'] = 'polylang';
        if ( $matched_id > 0 ) {
            $trace['matched'] = true;
            $trace['after_id'] = $matched_id;
            ai_fr_debug_set_resolve_trace( $trace );
            return $matched_id;
        }
        ai_fr_debug_set_resolve_trace( $trace );
        return $post_id;
    }

    ai_fr_debug_set_resolve_trace( $trace );
    return $post_id;
}

function ai_fr_match_wpml_translation_to_path( int $post_id, string $requested_path, array &$trace ): int {
    $post = get_post( $post_id );
    if ( ! $post || ! is_string( $post->post_type ) || $post->post_type === '' ) {
        return 0;
    }

    $languages = apply_filters( 'wpml_active_languages', null, [ 'skip_missing' => 0 ] );
    if ( ! is_array( $languages ) || empty( $languages ) ) {
        return 0;
    }

    $original_lang = apply_filters( 'wpml_current_language', null );
    $checked_ids = [];

    foreach ( $languages as $lang => $lang_info ) {
        $translated_id = apply_filters( 'wpml_object_id', $post_id, $post->post_type, false, $lang );
        $translated_id = (int) $translated_id;
        if ( $translated_id <= 0 || isset( $checked_ids[ $translated_id ] ) ) {
            continue;
        }
        $checked_ids[ $translated_id ] = true;

        if ( is_string( $lang ) && $lang !== '' ) {
            do_action( 'wpml_switch_language', $lang );
        }

        $translated_permalink = get_permalink( $translated_id );
        if ( is_string( $translated_permalink ) && $translated_permalink !== '' ) {
            $translated_path = ai_fr_relative_path_from_url( $translated_permalink );
            $trace['selected_permalink_path'] = $translated_path;
            if ( $translated_path === $requested_path ) {
                if ( is_string( $original_lang ) && $original_lang !== '' ) {
                    do_action( 'wpml_switch_language', $original_lang );
                }
                return $translated_id;
            }
        }
    }

    if ( is_string( $original_lang ) && $original_lang !== '' ) {
        do_action( 'wpml_switch_language', $original_lang );
    }

    return 0;
}

function ai_fr_wpml_is_available(): bool {
    return has_filter( 'wpml_object_id' ) || defined( 'ICL_SITEPRESS_VERSION' ) || function_exists( 'icl_object_id' );
}

function ai_fr_match_polylang_translation_to_path( int $post_id, string $requested_path, array &$trace ): int {
    if ( ! function_exists( 'pll_get_post' ) || ! function_exists( 'pll_languages_list' ) ) {
        return 0;
    }

    $languages = pll_languages_list( [ 'fields' => 'slug' ] );
    if ( ! is_array( $languages ) || empty( $languages ) ) {
        return 0;
    }

    $checked_ids = [];
    foreach ( $languages as $lang ) {
        if ( ! is_string( $lang ) || $lang === '' ) {
            continue;
        }

        $translated_id = (int) pll_get_post( $post_id, $lang );
        if ( $translated_id <= 0 || isset( $checked_ids[ $translated_id ] ) ) {
            continue;
        }
        $checked_ids[ $translated_id ] = true;

        $translated_permalink = get_permalink( $translated_id );
        if ( ! is_string( $translated_permalink ) || $translated_permalink === '' ) {
            continue;
        }

        $translated_path = ai_fr_relative_path_from_url( $translated_permalink );
        $trace['selected_permalink_path'] = $translated_path;
        if ( $translated_path === $requested_path ) {
            return $translated_id;
        }
    }

    return 0;
}

function ai_fr_polylang_is_available(): bool {
    return function_exists( 'pll_get_post' ) && function_exists( 'pll_languages_list' );
}

function ai_fr_resolve_archive_post_type( string $path ): string {
    $path = ai_fr_normalize_relative_path( $path );
    if ( $path === '' ) {
        return '';
    }

    $filter = new AiFrContentFilter();
    $post_types = get_post_types( [ 'public' => true ], 'objects' );

    foreach ( $post_types as $post_type => $obj ) {
        if ( empty( $obj->has_archive ) ) {
            continue;
        }
        if ( ! $filter->isPostTypeEnabled( $post_type ) ) {
            continue;
        }

        $archive_url = get_post_type_archive_link( $post_type );
        if ( is_string( $archive_url ) && $archive_url !== '' ) {
            if ( ai_fr_relative_path_from_url( $archive_url ) === $path ) {
                return $post_type;
            }
        }

        $archive_slug = '';
        if ( is_string( $obj->has_archive ) && $obj->has_archive !== '' ) {
            $archive_slug = $obj->has_archive;
        } elseif ( isset( $obj->rewrite['slug'] ) && is_string( $obj->rewrite['slug'] ) ) {
            $archive_slug = $obj->rewrite['slug'];
        } else {
            $archive_slug = $post_type;
        }

        if ( ai_fr_normalize_relative_path( $archive_slug ) === $path ) {
            return $post_type;
        }
    }

    return '';
}

function ai_fr_serve_archive_markdown( string $post_type, bool $debug_mode = false, bool $debug_requested = false ): never {
    $obj = get_post_type_object( $post_type );
    if ( ! $obj ) {
        ai_fr_404();
    }

    $title = $obj->labels->name ?? ucfirst( $post_type );
    $archive_url = get_post_type_archive_link( $post_type );
    if ( ! is_string( $archive_url ) || $archive_url === '' ) {
        ai_fr_404();
    }

    $posts = get_posts( [
        'post_type'      => $post_type,
        'post_status'    => 'publish',
        'posts_per_page' => 50,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'no_found_rows'  => true,
    ] );

    $filter = new AiFrContentFilter();
    $items = array_filter(
        $posts,
        static fn( WP_Post $p ): bool => $filter->shouldInclude( $p ) && ai_fr_can_serve_post( $p, 'llms' )
    );

    $md = "# {$title}\n\n";
    foreach ( $items as $item ) {
        $item_title = get_the_title( $item->ID );
        $item_url = get_permalink( $item->ID );
        if ( ! is_string( $item_url ) || $item_url === '' ) {
            continue;
        }
        $md_url = ai_fr_permalink_to_md( $item_url );
        $excerpt = ai_fr_excerpt( $item );
        $md .= "- [{$item_title}]({$md_url})";
        $md .= $excerpt !== '' ? ": {$excerpt}" : '';
        $md .= "\n";
    }

    if ( $debug_mode ) {
        $md .= "\n\n---\n\n";
        $md .= "Post type: `{$post_type}`\n";
        $md .= 'Items: ' . count( $items ) . "\n";
    }
    if ( ! ai_fr_markdown_has_visible_content( $md ) ) {
        $md = "# {$title}\n\n_Contenuto non disponibile._\n";
    }

    ai_fr_reset_output_buffers();
    status_header( 200 );
    header( 'Content-Type: text/markdown; charset=UTF-8' );
    header( 'X-Robots-Tag: noindex, follow' );
    header( 'Link: <' . $archive_url . '>; rel="canonical"', false );
    header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
    header( 'Pragma: no-cache' );
    header( 'Expires: 0' );
    header( 'X-AI-Friendly-Source: archive' );
    header( 'X-AI-Friendly-MD-Length: ' . strlen( $md ) );
    header( 'X-AI-Friendly-Version: ' . AI_FR_VERSION );
    header( 'X-AI-Friendly-Debug-Requested: ' . ( $debug_requested ? '1' : '0' ) );
    header( 'X-AI-Friendly-Debug-Admin: ' . ( $debug_mode ? '1' : '0' ) );
    echo $md;
    exit;
}

function ai_fr_relative_path_from_url( string $url ): string {
    $parsed_path = wp_parse_url( $url, PHP_URL_PATH );
    if ( ! is_string( $parsed_path ) || $parsed_path === '' ) {
        return '';
    }

    $parsed_path = rawurldecode( $parsed_path );
    $wp_base = rtrim( wp_parse_url( home_url(), PHP_URL_PATH ) ?? '', '/' );

    if ( $wp_base !== '' && str_starts_with( $parsed_path, $wp_base ) ) {
        $parsed_path = substr( $parsed_path, strlen( $wp_base ) );
    }

    return ai_fr_normalize_relative_path( $parsed_path );
}

function ai_fr_normalize_relative_path( string $path ): string {
    $normalized = trim( rawurldecode( $path ), '/' );
    $normalized = preg_replace( '#/+#', '/', $normalized ) ?? $normalized;
    return $normalized;
}

function ai_fr_markdown_has_visible_content( string $content ): bool {
    $probe = preg_replace( '/^\xEF\xBB\xBF/', '', $content ) ?? $content;
    return trim( $probe ) !== '';
}

function ai_fr_fallback_markdown( WP_Post $post ): string {
    $md = AiFrMetadata::frontmatter( $post );
    $md .= '# ' . get_the_title( $post->ID ) . "\n\n";
    $excerpt = ai_fr_excerpt( $post );
    $md .= $excerpt !== '' ? $excerpt . "\n" : "_Contenuto non disponibile._\n";
    if ( ! ai_fr_markdown_has_visible_content( $md ) ) {
        $md = "# Documento\n\n_Contenuto non disponibile._\n";
    }
    return $md;
}

function ai_fr_reset_output_buffers(): void {
    while ( ob_get_level() > 0 ) {
        @ob_end_clean();
    }
}

function ai_fr_404(): never {
    status_header( 404 );
    header( 'Content-Type: text/plain; charset=UTF-8' );
    echo "Contenuto non trovato.";
    exit;
}


