<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
//  2 â€” llms.txt
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

function saifr_serve_llms_txt(): void {

    $body = saifr_build_llms_txt();
    $body = (string) apply_filters( 'saifr_llms_txt_response_body', $body );

    saifr_reset_output_buffers();

    status_header( 200 );
    header( 'Content-Type: text/plain; charset=UTF-8' );
    header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
    header( 'Pragma: no-cache' );
    header( 'Expires: 0' );
    header( 'X-Content-Type-Options: nosniff' );
    header( 'X-AI-Friendly-Version: ' . SAIFR_VERSION );
    header( 'X-AI-Friendly-LLMS-Length: ' . strlen( $body ) );
    header( 'Content-Length: ' . strlen( $body ) );
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markdown is served as text/plain and must remain unescaped.
    echo $body;
    exit;
}

/**
 * Scrive llms.txt nel document root, preservando eventuali file non gestiti.
 *
 * Il checksum salvato nell'opzione identifica il contenuto dell'ultimo file
 * pubblicato dal plugin e impedisce di sovrascrivere file esterni o modificati
 * manualmente.
 */
function saifr_sync_static_llms_txt(): bool {
    $target = trailingslashit( ABSPATH ) . 'llms.txt';
    $body   = (string) apply_filters( 'saifr_llms_txt_response_body', saifr_build_llms_txt() );
    $hash   = hash( 'sha256', $body );
    $known  = (string) get_option( 'saifr_static_llms_hash', '' );

    if ( is_file( $target ) ) {
        $current = @file_get_contents( $target );
        if ( ! is_string( $current ) ) {
            saifr_notify_static_llms_failure();
            return false;
        }

        $current_hash = hash( 'sha256', $current );
        if ( $known === '' || ! hash_equals( $known, $current_hash ) ) {
            // File preesistente o modificato fuori dal plugin: non prenderne possesso.
            saifr_notify_static_llms_external_file();
            return false;
        }

        if ( hash_equals( $current_hash, $hash ) ) {
            return true;
        }
    } elseif ( $known !== '' ) {
        // Il file gestito è stato rimosso: può essere ricreato dal plugin.
    }

    if ( ! is_dir( dirname( $target ) ) || ! is_writable( dirname( $target ) ) ) {
        saifr_notify_static_llms_failure();
        return false;
    }

    $temporary = @tempnam( dirname( $target ), '.saifr-llms-' );
    if ( ! is_string( $temporary ) ) {
        saifr_notify_static_llms_failure();
        return false;
    }

    $written = @file_put_contents( $temporary, $body, LOCK_EX );
    if ( $written !== strlen( $body ) || ! @chmod( $temporary, 0644 ) || ! @rename( $temporary, $target ) ) {
        @unlink( $temporary );
        saifr_notify_static_llms_failure();
        return false;
    }

    update_option( 'saifr_static_llms_hash', $hash, false );
    delete_transient( 'saifr_static_llms_notice' );
    return true;
}

function saifr_notify_static_llms_failure(): void {
    if ( function_exists( 'saifr_push_admin_notice' ) ) {
        saifr_push_admin_notice( __( 'AI Friendly: impossibile aggiornare il file statico llms.txt; resta disponibile la risposta dinamica.', 'sernicola-labs-ai-friendly' ), 'warning' );
    }
}

function saifr_notify_static_llms_external_file(): void {
    if ( function_exists( 'saifr_push_admin_notice' ) && ! get_transient( 'saifr_static_llms_notice' ) ) {
        saifr_push_admin_notice( __( 'AI Friendly: llms.txt esistente non è gestito dal plugin ed è stato lasciato intatto.', 'sernicola-labs-ai-friendly' ), 'info' );
        set_transient( 'saifr_static_llms_notice', 1, DAY_IN_SECONDS );
    }
}

add_action( 'save_post', static function ( int $post_id ): void {
    if ( ! wp_is_post_autosave( $post_id ) && ! wp_is_post_revision( $post_id ) ) {
        saifr_sync_static_llms_txt();
    }
}, 100 );

add_action( 'deleted_post', 'saifr_sync_static_llms_txt', 100 );
add_action( 'trashed_post', 'saifr_sync_static_llms_txt', 100 );
add_action( 'untrashed_post', 'saifr_sync_static_llms_txt', 100 );
add_action( 'transition_post_status', static function ( string $new_status, string $old_status ): void {
    if ( $new_status !== $old_status ) {
        saifr_sync_static_llms_txt();
    }
}, 100, 2 );

add_action( 'updated_option', static function ( string $option ): void {
    if ( $option === 'saifr_options' ) {
        saifr_sync_static_llms_txt();
    }
}, 10, 1 );

function saifr_build_llms_txt(): string {

    $options = wp_parse_args( get_option( 'saifr_options', [] ), saifr_get_default_options() );
    $filter = new SaifrContentFilter();

    $custom_content = trim( $options['llms_content'] ?? '' );
    $include_auto   = ! empty( $options['llms_include_auto'] );

    if ( $custom_content !== '' && ! $include_auto ) {
        return apply_filters( 'saifr_llms_txt_content', html_entity_decode( $custom_content, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
    }

    $out = '';

    if ( $custom_content !== '' ) {
        $out = $custom_content . "\n\n";
    } else {
        $name = get_bloginfo( 'blogname' );
        $desc = get_bloginfo( 'description' ) ?: 'Sito web';
        $out  = "# {$name}\n";
        $out .= "> {$desc}\n\n";
    }

    if ( $include_auto || $custom_content === '' ) {
        
        // Pagine
        if ( ! empty( $options['include_pages'] ) ) {
            $out .= saifr_section( __( 'Pagine', 'sernicola-labs-ai-friendly' ), [
                'post_type'      => 'page',
                'post_status'    => 'publish',
                'posts_per_page' => SAIFR_PAGES_LIMIT,
                'orderby'        => 'menu_order date',
                'order'          => 'ASC',
            ], $filter );
        }

        // Post
        if ( ! empty( $options['include_posts'] ) ) {
            $out .= saifr_section( __( 'Post', 'sernicola-labs-ai-friendly' ), [
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'posts_per_page' => SAIFR_POSTS_LIMIT,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ], $filter );
        }

        // Prodotti WooCommerce
        if ( ! empty( $options['include_products'] ) && class_exists( 'WooCommerce' ) ) {
            $out .= saifr_section( __( 'Prodotti', 'sernicola-labs-ai-friendly' ), [
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => 20,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ], $filter );
        }
        
        // CPT custom
        $enabled_cpt = $options['include_cpt'] ?? [];
        foreach ( (array) $enabled_cpt as $cpt ) {
            $cpt_obj = get_post_type_object( $cpt );
            $label = $cpt_obj ? $cpt_obj->labels->name : ucfirst( $cpt );
            
            $out .= saifr_section( $label, [
                'post_type'      => $cpt,
                'post_status'    => 'publish',
                'posts_per_page' => 20,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ], $filter );
        }
    }

    return apply_filters( 'saifr_llms_txt_content', html_entity_decode( $out, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
}

/** The full corpus is published only after a complete successful pass. */
function saifr_llms_full_target(): string {
    return trailingslashit( ABSPATH ) . 'llms-full.txt';
}

function saifr_llms_full_begin( string $job_id ): string {
    $target = saifr_llms_full_target();
    $temporary = dirname( $target ) . '/.saifr-llms-full-' . sanitize_file_name( $job_id ) . '.tmp';
    if ( ! is_dir( dirname( $target ) ) || ! is_writable( dirname( $target ) ) ) {
        return '';
    }
    return file_put_contents( $temporary, '', LOCK_EX ) === false ? '' : $temporary;
}

function saifr_llms_full_append( string $temporary, WP_Post $post, string $markdown ): bool {
    $entry = "\n\n---\n\nSource: " . get_permalink( $post ) . "\n\n" . trim( $markdown ) . "\n";
    return file_put_contents( $temporary, $entry, FILE_APPEND | LOCK_EX ) === strlen( $entry );
}

function saifr_llms_full_publish( string $temporary ): bool {
    $target = saifr_llms_full_target();
    $known = (string) get_option( 'saifr_static_llms_full_hash', '' );
    if ( is_file( $target ) ) {
        $current_hash = hash_file( 'sha256', $target );
        if ( ! is_string( $current_hash ) || $known === '' || ! hash_equals( $known, $current_hash ) ) {
            return false;
        }
    }
    $hash = hash_file( 'sha256', $temporary );
    if ( ! is_string( $hash ) || ! @rename( $temporary, $target ) ) {
        return false;
    }
    update_option( 'saifr_static_llms_full_hash', $hash, false );
    return true;
}

add_action( 'updated_option', static function ( string $name ): void {
    if ( $name !== 'saifr_options' ) return;
    $options = wp_parse_args( get_option( 'saifr_options', [] ), saifr_get_default_options() );
    if ( ! empty( $options['llms_full_enabled'] ) ) {
        saifr_request_corpus_refresh();
        return;
    }
    $target = saifr_llms_full_target();
    $known = (string) get_option( 'saifr_static_llms_full_hash', '' );
    if ( $known !== '' && is_file( $target ) ) {
        $hash = hash_file( 'sha256', $target );
        if ( is_string( $hash ) && hash_equals( $known, $hash ) ) wp_delete_file( $target );
    }
    delete_option( 'saifr_static_llms_full_hash' );
}, 20, 1 );

function saifr_serve_llms_full(): void {
    $options = wp_parse_args( get_option( 'saifr_options', [] ), saifr_get_default_options() );
    $file = saifr_llms_full_target();
    if ( empty( $options['llms_full_enabled'] ) || ! is_file( $file ) ) {
        saifr_404();
    }
    $size = filesize( $file );
    if ( $size === false ) {
        saifr_404();
    }
    saifr_reset_output_buffers();
    status_header( 200 );
    header( 'Content-Type: text/plain; charset=UTF-8' );
    header( 'X-Content-Type-Options: nosniff' );
    header( 'Content-Length: ' . $size );
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text response.
    readfile( $file );
    exit;
}

function saifr_section( string $heading, array $query_args, SaifrContentFilter $filter ): string {

    $posts = get_posts( $query_args );
    
    // Filtra con le regole di inclusione/esclusione
    $items = array_filter( $posts, fn( WP_Post $p ) => $filter->shouldInclude( $p ) );

    if ( empty( $items ) ) {
        return '';
    }

    $lines = "## {$heading}\n";

    foreach ( $items as $item ) {
        if ( ! saifr_can_serve_post( $item, 'llms' ) ) {
            continue;
        }
        $title   = get_the_title( $item->ID );
        $md_url  = saifr_permalink_to_md( get_permalink( $item->ID ) );
        $excerpt = saifr_excerpt( $item );
        $facts   = function_exists( 'saifr_schema_get_llms_facts' ) ? saifr_schema_get_llms_facts( $item ) : '';

        $lines .= "- [{$title}]({$md_url})";
        $lines .= $facts !== '' ? " ({$facts})" : '';
        $lines .= $excerpt !== '' ? ": {$excerpt}" : '';
        $lines .= "\n";
    }

    return $lines . "\n";
}


