<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════════
//  3 — *.md
// ═══════════════════════════════════════════════════════════════════════════════

function ai_fr_serve_markdown( string $rel_path ): void {

    $display_errors = ini_get( 'display_errors' );
    ini_set( 'display_errors', '0' );

    $debug_mode = isset( $_GET['debug'] ) && current_user_can( 'manage_options' );
    $options = wp_parse_args( get_option( 'ai_fr_options', [] ), ai_fr_get_default_options() );

    try {
        $post_id = ai_fr_resolve_post( $rel_path );
        if ( ! $post_id ) {
            ai_fr_404();
        }

        $post = get_post( $post_id );
        if ( ! $post || $post->post_status !== 'publish' ) {
            ai_fr_404();
        }
        
        // Verifica filtri inclusione/esclusione
        $filter = new AiFrContentFilter();
        if ( ! $filter->shouldInclude( $post ) ) {
            ai_fr_404();
        }
        
        if ( ! ai_fr_can_serve_post( $post, 'serve' ) ) {
            ai_fr_404();
        }

        $canonical = get_permalink( $post_id );
        $canonical = apply_filters( 'ai_fr_md_canonical_url', $canonical, $post_id, $post );

        // Prova a servire versione statica se abilitato
        if ( ! empty( $options['static_md_files'] ) && ! $debug_mode ) {
            $static_content = AiFrVersioning::getVersion( $post_id );
            if ( $static_content !== null ) {
                ini_set( 'display_errors', $display_errors );
                
                status_header( 200 );
                header( 'Content-Type: text/markdown; charset=UTF-8' );
                header( 'X-Robots-Tag: noindex, follow' );
                header( 'Link: <' . $canonical . '>; rel="canonical"', false );
                header( 'Cache-Control: public, max-age=3600' );
                header( 'X-AI-Friendly-Source: static' );
                
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
            if ( is_string( $cached ) && $cached !== '' ) {
                $md = $cached;
            }
        }
        
        if ( $md === '' ) {
            $md = ai_fr_generate_markdown( $post );
            
            if ( ! $debug_mode && $md !== '' && $cache_key !== '' ) {
                $ttl = (int) apply_filters( 'ai_fr_md_cache_ttl', HOUR_IN_SECONDS, $post_id, $post );
                if ( $ttl < 0 ) {
                    $ttl = 0;
                }
                set_transient( $cache_key, $md, $ttl );
                update_post_meta( $post_id, '_ai_fr_md_cache_key', $cache_key );
            }
        }
        
        if ( $debug_mode ) {
            $debug_output = "---\n## DEBUG INFO\n\n";
            $debug_output .= "**Post ID:** {$post_id}\n\n";
            $debug_output .= "**Post Type:** {$post->post_type}\n\n";
            $debug_output .= "**Static version:** " . ( AiFrVersioning::hasValidVersion( $post_id ) ? 'Yes' : 'No' ) . "\n\n";
            $debug_output .= "**Checksum:** " . md5( $md ) . "\n\n";
            $debug_output .= "---\n\n";
            
            // Inserisci dopo frontmatter
            $md = preg_replace( '/^(---\n.*?\n---\n\n)/s', "$1" . $debug_output, $md ) ?? $debug_output . $md;
        }

        ini_set( 'display_errors', $display_errors );

        status_header( 200 );
        header( 'Content-Type: text/markdown; charset=UTF-8' );
        header( 'X-Robots-Tag: noindex, follow' );
        header( 'Link: <' . $canonical . '>; rel="canonical"', false );
        header( 'Cache-Control: public, max-age=3600' );
        header( 'X-AI-Friendly-Source: dynamic' );

        echo $md;
        exit;

    } catch ( Throwable $e ) {
        ini_set( 'display_errors', $display_errors );

        error_log( 'AI Friendly MD Error: ' . $e->getMessage() );

        status_header( 500 );
        header( 'Content-Type: text/plain; charset=UTF-8' );
        echo $debug_mode 
            ? "Errore: " . $e->getMessage() 
            : "Errore nella generazione del contenuto Markdown.";
        exit;
    }
}

/**
 * Verifica se un post può essere servito pubblicamente (llms.txt o .md).
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
     * Filtra la possibilità di servire un contenuto.
     *
     * @param bool    $can     True se il contenuto può essere servito.
     * @param WP_Post $post    Il post in esame.
     * @param string  $context Contesto: 'llms', 'serve', o altro.
     */
    return (bool) apply_filters( 'ai_fr_can_serve_post', $can, $post, $context );
}

function ai_fr_get_rendered_content_safe( WP_Post $post, bool $debug = false ): string {

    $content = $post->post_content;
    $post_id = $post->ID;

    $builder_content = ai_fr_try_page_builders( $post_id, $content, $debug );
    if ( ! empty( trim( strip_tags( $builder_content ) ) ) ) {
        return $builder_content;
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

    $text_check = trim( strip_tags( $html ) );
    if ( strlen( $text_check ) > 50 ) {
        return $html;
    }

    try {
        global $post;
        $old_post = $post;
        $post = get_post( $post_id );
        setup_postdata( $post );

        ob_start();
        $filtered = apply_filters( 'the_content', $content );
        ob_end_clean();

        wp_reset_postdata();
        $post = $old_post;

        if ( strlen( trim( strip_tags( $filtered ) ) ) > 50 ) {
            return $filtered;
        }
    } catch ( Throwable $e ) {
        // Ignora
    }

    $fallback = ai_fr_extract_text_from_raw( $content );
    if ( ! empty( $fallback ) ) {
        return wpautop( $fallback );
    }

    return '';
}

function ai_fr_try_page_builders( int $post_id, string $content, bool $debug = false ): string {

    $breakdance_data = get_post_meta( $post_id, '_breakdance_data', true );
    if ( ! empty( $breakdance_data ) ) {
        $extracted = ai_fr_extract_breakdance_text( $breakdance_data );
        if ( ! empty( $extracted ) ) {
            return wpautop( $extracted );
        }
    }

    $yootheme_data = get_post_meta( $post_id, '_yootheme', true );
    if ( ! empty( $yootheme_data ) ) {
        if ( is_string( $yootheme_data ) ) {
            $data = json_decode( $yootheme_data, true );
            if ( is_array( $data ) ) {
                $extracted = ai_fr_extract_yootheme_text( $data );
                if ( ! empty( trim( $extracted ) ) ) {
                    return wpautop( $extracted );
                }
            }
        }
    }

    $elementor_data = get_post_meta( $post_id, '_elementor_data', true );
    if ( ! empty( $elementor_data ) ) {
        if ( is_string( $elementor_data ) ) {
            $data = json_decode( $elementor_data, true );
            if ( is_array( $data ) ) {
                $extracted = ai_fr_extract_elementor_text( $data );
                if ( ! empty( trim( $extracted ) ) ) {
                    return wpautop( $extracted );
                }
            }
        }
    }

    $oxygen_data = get_post_meta( $post_id, 'ct_builder_shortcodes', true );
    if ( ! empty( $oxygen_data ) ) {
        $extracted = ai_fr_extract_text_from_raw( $oxygen_data );
        if ( ! empty( $extracted ) ) {
            return wpautop( $extracted );
        }
    }

    $bricks_data = get_post_meta( $post_id, '_bricks_page_content_2', true );
    if ( empty( $bricks_data ) ) {
        $bricks_data = get_post_meta( $post_id, '_bricks_page_content', true );
    }
    if ( ! empty( $bricks_data ) && is_array( $bricks_data ) ) {
        $extracted = ai_fr_extract_bricks_text( $bricks_data );
        if ( ! empty( $extracted ) ) {
            return wpautop( $extracted );
        }
    }

    return '';
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
    return ai_fr_recursive_text_extract( $data, [ 'content', 'text', 'title', 'lead', 'meta', 'heading', 'paragraph' ], $result );
}

function ai_fr_recursive_text_extract( array $data, array $keys, string $result = '' ): string {
    foreach ( $data as $key => $value ) {
        if ( is_string( $value ) && in_array( $key, $keys, true ) ) {
            $clean = trim( strip_tags( html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
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
    $path = trim( $path, '/' );
    if ( empty( $path ) ) {
        return 0;
    }

    $page = get_page_by_path( $path, OBJECT, [ 'page', 'post', 'product' ] );
    if ( $page && $page->post_status === 'publish' ) {
        return $page->ID;
    }

    $slug = basename( $path );
    if ( ! empty( $slug ) ) {
        $posts = get_posts( [
            'name'           => $slug,
            'post_type'      => 'any',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'no_found_rows'  => true,
        ] );
        if ( ! empty( $posts ) ) {
            return $posts[0]->ID;
        }
    }

    global $wpdb;
    $slug_sanitized = sanitize_title( $slug );

    $post_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_status = 'publish' LIMIT 1",
            $slug_sanitized
        )
    );

    if ( $post_id ) {
        return (int) $post_id;
    }

    return 0;
}

function ai_fr_404(): never {
    status_header( 404 );
    header( 'Content-Type: text/plain; charset=UTF-8' );
    echo "Contenuto non trovato.";
    exit;
}


