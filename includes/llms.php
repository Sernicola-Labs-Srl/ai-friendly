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
 * Rimuove i file statici llms.txt e llms-full.txt pubblicati dalle versioni
 * precedenti nel document root.
 *
 * I file fisici vengono serviti direttamente dal web server senza charset, con
 * gli accenti rovinati: i contenuti ora escono sempre dalle risposte dinamiche.
 * Un file viene cancellato solo se il suo hash coincide con quello salvato dal
 * plugin; un file di altri o modificato a mano resta intatto.
 */
function saifr_cleanup_legacy_static_files(): void {
    $remaining = false;

    foreach ( [
        'llms.txt'      => 'saifr_static_llms_hash',
        'llms-full.txt' => 'saifr_static_llms_full_hash',
    ] as $file_name => $option ) {
        $target = trailingslashit( ABSPATH ) . $file_name;
        $known  = (string) get_option( $option, '' );

        if ( is_file( $target ) ) {
            $current_hash = hash_file( 'sha256', $target );
            if ( $known !== '' && is_string( $current_hash ) && hash_equals( $known, $current_hash ) ) {
                // Il corpus completo viene spostato nella nuova posizione protetta,
                // così resta disponibile senza attendere una nuova rigenerazione.
                if ( $file_name === 'llms-full.txt' && saifr_llms_full_prepare_dir() && ! is_file( saifr_llms_full_target() ) ) {
                    if ( ! @rename( $target, saifr_llms_full_target() ) && @copy( $target, saifr_llms_full_target() ) ) {
                        wp_delete_file( $target );
                    }
                }
                if ( is_file( $target ) ) {
                    wp_delete_file( $target );
                }
            }
            if ( is_file( $target ) ) {
                $remaining = true;
            }
        }

        delete_option( $option );
    }

    delete_transient( 'saifr_static_llms_notice' );
    update_option( 'saifr_static_files_cleaned', '1' );

    if ( $remaining ) {
        saifr_notify_static_llms_external_file();
    }
}

function saifr_notify_static_llms_external_file(): void {
    if ( function_exists( 'saifr_push_admin_notice' ) && ! get_transient( 'saifr_static_llms_notice' ) ) {
        saifr_push_admin_notice( __( 'AI Friendly: nella cartella principale del sito esiste un file llms.txt o llms-full.txt non gestito dal plugin. Il web server lo serve al posto della risposta dinamica, spesso senza charset UTF-8: rimuovilo o spostalo per ripristinare gli accenti corretti.', 'sernicola-labs-ai-friendly' ), 'warning' );
        set_transient( 'saifr_static_llms_notice', 1, DAY_IN_SECONDS );
    }
}

// Esegue la pulizia una sola volta dopo l'aggiornamento (opzione con autoload).
add_action( 'init', static function (): void {
    if ( get_option( 'saifr_static_files_cleaned', '' ) === '' ) {
        saifr_cleanup_legacy_static_files();
    }
}, 5 );

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

/**
 * Il corpus completo vive in uploads, fuori dalla radice pubblica: così il web
 * server non lo serve come file statico e /llms-full.txt esce sempre dalla
 * risposta dinamica, con il charset corretto.
 */
function saifr_llms_full_dir(): string {
    return trailingslashit( saifr_storage_root() ) . 'corpus';
}

function saifr_llms_full_target(): string {
    // Estensione volutamente non testuale: non deve sembrare un file servibile.
    return trailingslashit( saifr_llms_full_dir() ) . 'llms-full.corpus';
}

function saifr_llms_full_prepare_dir(): bool {
    $dir = saifr_llms_full_dir();
    if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
        return false;
    }
    $htaccess = trailingslashit( $dir ) . '.htaccess';
    if ( ! is_file( $htaccess ) ) {
        @file_put_contents( $htaccess, "Options -Indexes
<IfModule mod_authz_core.c>
Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
Deny from all
</IfModule>
" );
    }
    $index = trailingslashit( $dir ) . 'index.php';
    if ( ! is_file( $index ) ) {
        @file_put_contents( $index, "<?php
// Silence is golden.
" );
    }
    return is_writable( $dir );
}

function saifr_llms_full_begin( string $job_id ): string {
    if ( ! saifr_llms_full_prepare_dir() ) {
        return '';
    }
    // Elimina i temporanei di lavori interrotti da più di un giorno.
    foreach ( (array) glob( trailingslashit( saifr_llms_full_dir() ) . '.saifr-llms-full-*.tmp' ) as $stale ) {
        if ( is_string( $stale ) && is_file( $stale ) && filemtime( $stale ) < time() - DAY_IN_SECONDS ) {
            wp_delete_file( $stale );
        }
    }
    $temporary = trailingslashit( saifr_llms_full_dir() ) . '.saifr-llms-full-' . sanitize_file_name( $job_id ) . '.tmp';
    return file_put_contents( $temporary, '', LOCK_EX ) === false ? '' : $temporary;
}

function saifr_llms_full_append( string $temporary, WP_Post $post, string $markdown ): bool {
    $entry = "

---

Source: " . saifr_post_public_url( $post ) . "

" . trim( $markdown ) . "
";
    return file_put_contents( $temporary, $entry, FILE_APPEND | LOCK_EX ) === strlen( $entry );
}

function saifr_llms_full_publish( string $temporary ): bool {
    return is_file( $temporary ) && @rename( $temporary, saifr_llms_full_target() );
}

add_action( 'updated_option', static function ( string $name ): void {
    if ( $name !== 'saifr_options' ) return;
    $options = wp_parse_args( get_option( 'saifr_options', [] ), saifr_get_default_options() );
    if ( ! empty( $options['llms_full_enabled'] ) ) {
        saifr_request_corpus_refresh();
        return;
    }
    $target = saifr_llms_full_target();
    if ( is_file( $target ) ) {
        wp_delete_file( $target );
    }
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
    header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
    header( 'X-Content-Type-Options: nosniff' );
    header( 'X-AI-Friendly-Version: ' . SAIFR_VERSION );
    header( 'Content-Length: ' . $size );
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text response.
    readfile( $file );
    exit;
}

function saifr_section( string $heading, array $query_args, SaifrContentFilter $filter ): string {

    $posts = get_posts( array_merge( $query_args, saifr_language_query_args() ) );
    
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
        $md_url  = saifr_post_md_url( $item );
        $excerpt = saifr_excerpt( $item );
        $facts   = function_exists( 'saifr_schema_get_llms_facts' ) ? saifr_schema_get_llms_facts( $item ) : '';

        $lines .= "- [{$title}]({$md_url})";
        $lines .= $facts !== '' ? " ({$facts})" : '';
        $lines .= $excerpt !== '' ? ": {$excerpt}" : '';
        $lines .= "\n";
    }

    return $lines . "\n";
}


