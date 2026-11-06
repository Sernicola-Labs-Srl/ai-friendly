<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════════
//  6 — Utility
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Converte un permalink nell'URL Markdown corrispondente.
 *
 * Se è noto l'ID del contenuto, la home viene riconosciuta da quello (anche per
 * le traduzioni WPML/Polylang) e non dal percorso del permalink, che nelle
 * installazioni multilingua può non coincidere con la home della lingua attiva.
 */
function saifr_permalink_to_md( string $permalink, int $post_id = 0 ): string {
    if ( $post_id > 0 && saifr_is_front_page_post( $post_id ) ) {
        return trailingslashit( saifr_front_page_url( $post_id ) ) . 'index.html.md';
    }

    $home_path = saifr_normalize_url_path( home_url( '/' ) );
    $path      = saifr_normalize_url_path( $permalink );

    if ( $path === $home_path ) {
        return trailingslashit( home_url() ) . 'index.html.md';
    }

    return rtrim( $permalink, '/' ) . '.md';
}

/** URL Markdown di un contenuto, con riconoscimento della home per ID. */
function saifr_post_md_url( WP_Post $post ): string {
    return saifr_permalink_to_md( saifr_post_public_url( $post ), (int) $post->ID );
}

/** URL pubblico di un contenuto; per la home è la home della sua lingua. */
function saifr_post_public_url( WP_Post $post ): string {
    if ( saifr_is_front_page_post( (int) $post->ID ) ) {
        return saifr_front_page_url( (int) $post->ID );
    }

    // In contesti senza lingua della richiesta (cron, admin, WP-CLI) WPML costruirebbe
    // il permalink nella lingua corrente: lo calcoliamo nella lingua del contenuto.
    $permalink = '';
    if ( saifr_wpml_is_available() ) {
        $lang = saifr_post_language_code( (int) $post->ID, (string) $post->post_type );
        // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML public API hooks.
        $previous = apply_filters( 'wpml_current_language', null );
        if ( $lang !== '' && is_string( $previous ) && $previous !== $lang ) {
            do_action( 'wpml_switch_language', $lang );
            $permalink = get_permalink( $post->ID );
            do_action( 'wpml_switch_language', $previous );
        }
        // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
    }
    if ( ! is_string( $permalink ) || $permalink === '' ) {
        $permalink = get_permalink( $post->ID );
    }
    return is_string( $permalink ) ? $permalink : '';
}

/** Codici lingua attivi (WPML o Polylang); vuoto se il sito non è multilingua. */
function saifr_active_language_codes(): array {
    if ( saifr_wpml_is_available() ) {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML public API hook.
        $languages = apply_filters( 'wpml_active_languages', null, [ 'skip_missing' => 0 ] );
        return is_array( $languages ) ? array_map( 'strval', array_keys( $languages ) ) : [];
    }
    if ( function_exists( 'pll_languages_list' ) ) {
        $languages = pll_languages_list( [ 'fields' => 'slug' ] );
        return is_array( $languages ) ? array_map( 'strval', $languages ) : [];
    }
    return [];
}

/** Lingua di un contenuto, o stringa vuota se non determinabile. */
function saifr_post_language_code( int $post_id, string $post_type = 'page' ): string {
    if ( saifr_wpml_is_available() ) {
        $post_type = str_starts_with( $post_type, 'post_' ) ? $post_type : 'post_' . $post_type;
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML public API hook.
        $code = apply_filters( 'wpml_element_language_code', null, [ 'element_id' => $post_id, 'element_type' => $post_type ] );
        return is_string( $code ) ? $code : '';
    }
    if ( function_exists( 'pll_get_post_language' ) ) {
        $code = pll_get_post_language( $post_id );
        return is_string( $code ) ? $code : '';
    }
    return '';
}

/** ID della home statica tradotta nella lingua indicata (0 se assente). */
function saifr_front_page_id_for_language( string $lang ): int {
    if ( get_option( 'show_on_front' ) !== 'page' ) {
        return 0;
    }
    $front_id = (int) get_option( 'page_on_front' );
    if ( $front_id <= 0 ) {
        return 0;
    }
    if ( $lang === '' ) {
        return $front_id;
    }
    if ( saifr_wpml_is_available() ) {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML public API hook.
        return (int) apply_filters( 'wpml_object_id', $front_id, 'page', false, $lang );
    }
    if ( function_exists( 'pll_get_post' ) ) {
        return (int) pll_get_post( $front_id, $lang );
    }
    return $front_id;
}

/** Vero se il contenuto è la home statica o una sua traduzione. */
function saifr_is_front_page_post( int $post_id ): bool {
    if ( $post_id <= 0 || get_option( 'show_on_front' ) !== 'page' ) {
        return false;
    }
    $front_id = (int) get_option( 'page_on_front' );
    if ( $front_id <= 0 ) {
        return false;
    }
    if ( $post_id === $front_id ) {
        return true;
    }
    foreach ( saifr_active_language_codes() as $lang ) {
        if ( saifr_front_page_id_for_language( $lang ) === $post_id ) {
            return true;
        }
    }
    return false;
}

/** URL della home nella lingua indicata (o nella lingua attiva se vuota). */
function saifr_home_url_for_language( string $lang = '' ): string {
    if ( $lang !== '' ) {
        if ( saifr_wpml_is_available() ) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML public API hook.
            $url = apply_filters( 'wpml_permalink', home_url( '/' ), $lang );
            if ( is_string( $url ) && $url !== '' ) {
                return $url;
            }
        }
        if ( function_exists( 'pll_home_url' ) ) {
            $url = pll_home_url( $lang );
            if ( is_string( $url ) && $url !== '' ) {
                return $url;
            }
        }
    }
    return home_url( '/' );
}

/** URL della home nella lingua del contenuto indicato. */
function saifr_front_page_url( int $post_id ): string {
    $lang = saifr_post_language_code( $post_id );
    return saifr_home_url_for_language( $lang );
}

/**
 * Argomenti di query che limitano un elenco alla lingua attiva.
 *
 * get_posts() imposta suppress_filters a true e WPML non filtrerebbe per lingua.
 */
function saifr_language_query_args(): array {
    if ( saifr_wpml_is_available() ) {
        return [ 'suppress_filters' => false ];
    }
    if ( function_exists( 'pll_current_language' ) ) {
        $lang = pll_current_language();
        if ( is_string( $lang ) && $lang !== '' ) {
            return [ 'lang' => $lang ];
        }
    }
    return [];
}

function saifr_normalize_url_path( string $url ): string {
    $path = wp_parse_url( $url, PHP_URL_PATH );
    if ( ! is_string( $path ) || $path === '' ) {
        return '/';
    }

    $path = '/' . trim( rawurldecode( $path ), '/' );
    return $path === '/' ? '/' : rtrim( $path, '/' );
}

/**
 * Toglie i tag HTML senza incollare le parole: <br> e la chiusura dei blocchi
 * diventano uno spazio, poi gli spazi consecutivi vengono compattati.
 */
function saifr_strip_tags_spaced( string $html ): string {
    $html = preg_replace( '#</(?:p|div|li|h[1-6]|td|th|tr|blockquote|section|article|dt|dd)>|<br\s*/?>#i', '$0 ', $html ) ?? $html;
    $text = wp_strip_all_tags( $html );
    return trim( preg_replace( '/\s+/u', ' ', $text ) ?? $text );
}

function saifr_excerpt( WP_Post $post ): string {
    $raw  = $post->post_excerpt !== '' ? $post->post_excerpt : $post->post_content;
    $raw  = preg_replace( '/\[[^\]]+\]/', '', $raw ) ?? $raw;
    $text = saifr_strip_tags_spaced( $raw );

    return wp_html_excerpt( $text, SAIFR_EXCERPT_LEN, '…' );
}
