<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════════
//  CONVERTER v2  |  HTML → Markdown
// ═══════════════════════════════════════════════════════════════════════════════

class AiFrConverter {

    private const NOISE_PATTERNS = [
        'previous step', 'next step', 'avanti', 'indietro',
        'step 1', 'step 2', 'step 3', 'step 4', 'step 5',
        'mostra di più', 'mostra meno', 'leggi di più', 'read more',
        'show more', 'show less', 'load more', 'carica altro',
        'scopri di più', 'learn more', 'click here', 'clicca qui',
        'invia', 'submit', 'send', 'reset',
        'menu', 'skip to content', 'vai al contenuto',
    ];

    private const REMOVE_SHORTCODES = [
        'contact-form-7', 'cf7', 'wpforms', 'gravityform', 'formidable',
        'ninja_form', 'caldera_form', 'mailchimp', 'newsletter',
        'vc_row', 'vc_column', 'vc_column_text', 'vc_section',
        'et_pb_section', 'et_pb_row', 'et_pb_column', 'et_pb_text',
        'fusion_builder_container', 'fusion_builder_row', 'fusion_builder_column',
        'fl_builder_insert_layout', 'elementor-template',
        'gallery', 'playlist', 'audio', 'video', 'caption', 'embed',
        'rev_slider', 'layerslider',
        'social_buttons', 'share', 'follow',
        'ads', 'ad', 'advertisement',
    ];

    private bool $normalizeHeadings;
    private int $headingShift = 0;

    public function __construct( bool $normalizeHeadings = true ) {
        $this->normalizeHeadings = $normalizeHeadings;
    }

    public function convert( string $html ): string {

        if ( trim( $html ) === '' ) {
            return '';
        }

        $s = $this->stripNoise( $html );
        $s = $this->stripUIElements( $s );
        $s = $this->processShortcodes( $s );

        if ( $this->normalizeHeadings ) {
            $this->headingShift = $this->calculateHeadingShift( $s );
        }

        $s = $this->preCodeBlocks( $s );
        $s = $this->headings( $s );
        $s = $this->blockquotes( $s );
        $s = $this->horizontalRules( $s );
        $s = $this->tables( $s );
        $s = $this->lists( $s );
        $s = $this->definitionLists( $s );
        $s = $this->images( $s );
        $s = $this->figcaptions( $s );
        $s = $this->links( $s );
        $s = $this->inlineCode( $s );
        $s = $this->bold( $s );
        $s = $this->italic( $s );
        $s = $this->marks( $s );
        $s = $this->breaks( $s );
        $s = $this->paragraphs( $s );
        $s = strip_tags( $s );
        $s = $this->removeNoiseText( $s );
        $s = $this->cleanup( $s );

        return $s;
    }

    private function stripNoise( string $s ): string {
        $s = preg_replace(
            '/<(script|style|noscript|svg|template|iframe)[^>]*>.*?<\/\1>/si',
            '',
            $s
        ) ?? $s;
        $s = preg_replace( '/<!--.*?-->/s', '', $s ) ?? $s;
        return $s;
    }

    private function stripUIElements( string $s ): string {
        $s = preg_replace( '/<form[^>]*>.*?<\/form>/si', '', $s ) ?? $s;
        $s = preg_replace( '/<(input|select|textarea|button|label)[^>]*>.*?<\/\1>/si', '', $s ) ?? $s;
        $s = preg_replace( '/<(input|select|textarea|button|label)[^>]*\/?>/i', '', $s ) ?? $s;
        $s = preg_replace( '/<nav[^>]*>.*?<\/nav>/si', '', $s ) ?? $s;
        $s = preg_replace( '/<aside[^>]*>.*?<\/aside>/si', '', $s ) ?? $s;
        $s = preg_replace( '/<footer[^>]*>.*?<\/footer>/si', '', $s ) ?? $s;
        $s = preg_replace( '/<(progress|meter)[^>]*>.*?<\/\1>/si', '', $s ) ?? $s;
        $s = preg_replace( '/<[^>]+(?:hidden|display:\s*none|visibility:\s*hidden)[^>]*>.*?<\/[a-z]+>/si', '', $s ) ?? $s;
        $s = preg_replace( '/<[^>]+aria-hidden=["\']true["\'][^>]*>.*?<\/[a-z]+>/si', '', $s ) ?? $s;
        return $s;
    }

    private function processShortcodes( string $s ): string {
        foreach ( self::REMOVE_SHORTCODES as $tag ) {
            $s = preg_replace(
                '/\[' . preg_quote( $tag, '/' ) . '[^\]]*\](.*?)\[\/' . preg_quote( $tag, '/' ) . '\]/si',
                '$1',
                $s
            ) ?? $s;
            $s = preg_replace(
                '/\[' . preg_quote( $tag, '/' ) . '[^\]]*\/?\]/i',
                '',
                $s
            ) ?? $s;
        }

        if ( function_exists( 'do_shortcode' ) ) {
            $s = preg_replace_callback(
                '/\[([a-z_-]+)[^\]]*\](?:.*?\[\/\1\])?/si',
                function( $match ) {
                    $tag = strtolower( $match[1] );
                    $skip = [ 'contact', 'form', 'subscribe', 'signup', 'login', 'register', 'cart', 'checkout' ];
                    foreach ( $skip as $word ) {
                        if ( str_contains( $tag, $word ) ) {
                            return '';
                        }
                    }
                    $result = do_shortcode( $match[0] );
                    if ( $result === $match[0] ) {
                        return '';
                    }
                    return $result;
                },
                $s
            ) ?? $s;
        }

        $s = preg_replace( '/\[[^\]]+\]/', '', $s ) ?? $s;
        return $s;
    }

    private function calculateHeadingShift( string $s ): int {
        if ( preg_match( '/<h1[^>]*>/i', $s ) ) {
            return 1;
        }
        return 0;
    }

    private function preCodeBlocks( string $s ): string {
        return preg_replace_callback(
            '/<pre[^>]*>(?:<code[^>]*(?:class=["\'][^"\']*language-([a-z]+)[^"\']*["\'])?[^>]*>)?(.*?)(?:<\/code>)?<\/pre>/si',
            function( $m ) {
                $lang = $m[1] ?? '';
                $code = html_entity_decode( strip_tags( $m[2] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
                return "\n```{$lang}\n" . trim( $code ) . "\n```\n";
            },
            $s
        ) ?? $s;
    }

    private function headings( string $s ): string {
        return preg_replace_callback(
            '/<h([1-6])[^>]*>(.*?)<\/h\1>/si',
            function( $m ) {
                $level = (int) $m[1] + $this->headingShift;
                $level = min( $level, 6 );
                $text = trim( strip_tags( $m[2] ) );
                if ( $text === '' ) {
                    return '';
                }
                return "\n\n" . str_repeat( '#', $level ) . ' ' . $text . "\n\n";
            },
            $s
        ) ?? $s;
    }

    private function blockquotes( string $s ): string {
        return preg_replace_callback(
            '/<blockquote[^>]*>(.*?)<\/blockquote>/si',
            function ( $m ) {
                $content = trim( strip_tags( $m[1] ) );
                if ( $content === '' ) return '';
                $lines = explode( "\n", $content );
                $quoted = array_map( fn( $l ) => '> ' . trim( $l ), $lines );
                return "\n\n" . implode( "\n", array_filter( $quoted, fn( $l ) => $l !== '> ' ) ) . "\n\n";
            },
            $s
        ) ?? $s;
    }

    private function horizontalRules( string $s ): string {
        return preg_replace( '/<hr\s*\/?>/i', "\n\n---\n\n", $s ) ?? $s;
    }

    private function tables( string $s ): string {
        return preg_replace_callback(
            '/<table[^>]*>(.*?)<\/table>/si',
            function( $m ) {
                $tableHtml = $m[1];
                $rows = [];
                preg_match_all( '/<tr[^>]*>(.*?)<\/tr>/si', $tableHtml, $trMatches );
                foreach ( $trMatches[1] as $i => $tr ) {
                    preg_match_all( '/<(th|td)[^>]*>(.*?)<\/\1>/si', $tr, $cellMatches );
                    $cells = array_map( fn( $c ) => trim( strip_tags( $c ) ), $cellMatches[2] );
                    if ( ! empty( $cells ) ) {
                        $rows[] = '| ' . implode( ' | ', $cells ) . ' |';
                        if ( $i === 0 ) {
                            $rows[] = '| ' . implode( ' | ', array_fill( 0, count( $cells ), '---' ) ) . ' |';
                        }
                    }
                }
                return empty( $rows ) ? '' : "\n\n" . implode( "\n", $rows ) . "\n\n";
            },
            $s
        ) ?? $s;
    }

    private function lists( string $s ): string {
        $s = preg_replace_callback(
            '/<li[^>]*>(.*?)<\/li>/si',
            function( $m ) {
                $content = trim( strip_tags( $m[1] ) );
                return $content !== '' ? "\n- " . $content : '';
            },
            $s
        ) ?? $s;
        $s = preg_replace( '/<\/?(?:ul|ol)[^>]*>/', '', $s ) ?? $s;
        return $s;
    }

    private function definitionLists( string $s ): string {
        $s = preg_replace( '/<dt[^>]*>(.*?)<\/dt>/si', "\n**$1**\n", $s ) ?? $s;
        $s = preg_replace( '/<dd[^>]*>(.*?)<\/dd>/si', ": $1\n", $s ) ?? $s;
        $s = preg_replace( '/<\/?dl[^>]*>/', '', $s ) ?? $s;
        return $s;
    }

    private function images( string $s ): string {
        return preg_replace_callback(
            '/<img\s[^>]*>/i',
            function ( $m ) {
                $src = $this->attr( $m[0], 'src' ) ?? '';
                $alt = $this->attr( $m[0], 'alt' ) ?? '';

                if ( $src === '' ) {
                    return '';
                }

                $alt = trim( $alt );
                if ( $alt === '' ) {
                    return '';
                }

                if ( str_contains( $src, 'pixel' ) || str_contains( $src, 'spacer' ) || str_contains( $src, 'blank.gif' ) ) {
                    return '';
                }

                if ( preg_match( '/icon|logo-small|favicon|loading|spinner/i', $src ) ) {
                    return '';
                }

                $altLower = strtolower( $alt );
                $genericAlts = [ 'image', 'img', 'photo', 'picture', 'foto', 'immagine', 'banner', 'header', 'hero' ];
                if ( in_array( $altLower, $genericAlts, true ) ) {
                    return '';
                }

                return "![{$alt}]({$src})";
            },
            $s
        ) ?? $s;
    }

    private function figcaptions( string $s ): string {
        return preg_replace_callback(
            '/<figcaption[^>]*>(.*?)<\/figcaption>/si',
            function( $m ) {
                $caption = trim( strip_tags( $m[1] ) );
                return $caption !== '' ? "\n*{$caption}*\n" : '';
            },
            $s
        ) ?? $s;
    }

    private function links( string $s ): string {
        return preg_replace_callback(
            '/<a\s[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/si',
            function ( $m ) {
                $url  = trim( $m[1] );
                $text = trim( strip_tags( $m[2] ) );

                if ( $text === '' && $url === '' ) {
                    return '';
                }

                if ( str_starts_with( $url, '#' ) ) {
                    return $text;
                }

                if ( str_starts_with( $url, 'javascript:' ) ) {
                    return $text;
                }

                $textLower = strtolower( $text );
                foreach ( self::NOISE_PATTERNS as $pattern ) {
                    if ( str_contains( $textLower, $pattern ) ) {
                        return '';
                    }
                }

                if ( $text !== '' ) {
                    return "[{$text}]({$url})";
                }

                return $url !== '' ? $url : '';
            },
            $s
        ) ?? $s;
    }

    private function inlineCode( string $s ): string {
        return preg_replace( '/<code[^>]*>(.*?)<\/code>/si', '`$1`', $s ) ?? $s;
    }

    private function bold( string $s ): string {
        $s = preg_replace( '/<strong[^>]*>(.*?)<\/strong>/si', '**$1**', $s ) ?? $s;
        $s = preg_replace( '/<b[^>]*>(.*?)<\/b>/si', '**$1**', $s ) ?? $s;
        return $s;
    }

    private function italic( string $s ): string {
        $s = preg_replace( '/<em[^>]*>(.*?)<\/em>/si', '*$1*', $s ) ?? $s;
        $s = preg_replace( '/<i[^>]*>(.*?)<\/i>/si', '*$1*', $s ) ?? $s;
        return $s;
    }

    private function marks( string $s ): string {
        return preg_replace( '/<mark[^>]*>(.*?)<\/mark>/si', '==$1==', $s ) ?? $s;
    }

    private function breaks( string $s ): string {
        return preg_replace( '/<br\s*\/?>/i', "\n", $s ) ?? $s;
    }

    private function paragraphs( string $s ): string {
        return preg_replace( '/<\/p>/i', "\n\n", $s ) ?? $s;
    }

    private function removeNoiseText( string $s ): string {
        $lines = explode( "\n", $s );
        $filtered = [];

        foreach ( $lines as $line ) {
            $trimmed = trim( $line );
            $lower = strtolower( $trimmed );

            if ( $trimmed === '' ) {
                $filtered[] = '';
                continue;
            }

            $isNoise = false;
            foreach ( self::NOISE_PATTERNS as $pattern ) {
                if ( $lower === $pattern || $lower === strtolower( $pattern ) ) {
                    $isNoise = true;
                    break;
                }
            }

            if ( ! $isNoise && strlen( $trimmed ) < 20 && str_word_count( $trimmed ) <= 2 ) {
                if ( ! preg_match( '/^(#|\-|\*|\d|[A-Z][a-z]+ \d)/', $trimmed ) ) {
                    foreach ( self::NOISE_PATTERNS as $pattern ) {
                        if ( str_contains( $lower, $pattern ) ) {
                            $isNoise = true;
                            break;
                        }
                    }
                }
            }

            if ( ! $isNoise ) {
                $filtered[] = $line;
            }
        }

        return implode( "\n", $filtered );
    }

    private function cleanup( string $s ): string {
        $s = html_entity_decode( $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $s = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $s ) ?? $s;
        $s = preg_replace( '/[^\S\n]+/', ' ', $s ) ?? $s;
        $s = preg_replace( '/^ +| +$/m', '', $s ) ?? $s;
        $s = preg_replace( '/\n{3,}/', "\n\n", $s ) ?? $s;
        $s = preg_replace( '/^\*\*\*\*$/m', '', $s ) ?? $s;
        $s = preg_replace( '/^\*\*$/m', '', $s ) ?? $s;
        $s = preg_replace( '/^\*$/m', '', $s ) ?? $s;
        return trim( $s );
    }

    private function attr( string $tag, string $name ): ?string {
        return preg_match(
            "/\\b{$name}=[\"']([^\"']*)[\"']/i",
            $tag,
            $m
        ) ? $m[1] : null;
    }
}


