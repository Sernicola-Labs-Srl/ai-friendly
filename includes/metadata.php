<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════════
//  METADATA EXTRACTOR
// ═══════════════════════════════════════════════════════════════════════════════

class AiFrMetadata {

    public static function frontmatter( WP_Post $post ): string {

        if ( ! AI_FR_INCLUDE_METADATA ) {
            return '';
        }

        $meta = self::extract( $post );

        $yaml  = "---\n";
        $yaml .= "title: " . self::yamlEscape( $meta['title'] ) . "\n";

        if ( ! empty( $meta['description'] ) ) {
            $yaml .= "description: " . self::yamlEscape( $meta['description'] ) . "\n";
        }

        if ( ! empty( $meta['featured_image'] ) ) {
            $yaml .= "featured_image: " . $meta['featured_image'] . "\n";
        }

        $yaml .= "date: " . $meta['date'] . "\n";

        if ( ! empty( $meta['modified'] ) && $meta['modified'] !== $meta['date'] ) {
            $yaml .= "modified: " . $meta['modified'] . "\n";
        }

        if ( ! empty( $meta['author'] ) ) {
            $yaml .= "author: " . self::yamlEscape( $meta['author'] ) . "\n";
        }

        $yaml .= "url: " . $meta['url'] . "\n";

        if ( ! empty( $meta['categories'] ) ) {
            $yaml .= "categories: [" . implode( ', ', array_map( [ self::class, 'yamlEscape' ], $meta['categories'] ) ) . "]\n";
        }

        if ( ! empty( $meta['tags'] ) ) {
            $yaml .= "tags: [" . implode( ', ', array_map( [ self::class, 'yamlEscape' ], $meta['tags'] ) ) . "]\n";
        }

        $yaml .= "---\n\n";

        return $yaml;
    }

    public static function extract( WP_Post $post ): array {

        $post_id = $post->ID;

        $title = self::getSeoTitle( $post_id ) ?: get_the_title( $post_id );
        $description = self::getSeoDescription( $post_id ) ?: self::generateExcerpt( $post );

        $featured_image = '';
        if ( has_post_thumbnail( $post_id ) ) {
            $featured_image = get_the_post_thumbnail_url( $post_id, 'large' ) ?: '';
        }

        $date = get_the_date( 'Y-m-d', $post_id );
        $modified = get_the_modified_date( 'Y-m-d', $post_id );
        $author = get_the_author_meta( 'display_name', $post->post_author );
        $url = get_permalink( $post_id );

        $categories = [];
        if ( $post->post_type === 'post' ) {
            $cats = get_the_category( $post_id );
            foreach ( $cats as $cat ) {
                if ( $cat->slug !== 'uncategorized' ) {
                    $categories[] = $cat->name;
                }
            }
        }

        $tags = [];
        $post_tags = get_the_tags( $post_id );
        if ( $post_tags ) {
            foreach ( $post_tags as $tag ) {
                $tags[] = $tag->name;
            }
        }

        return [
            'title'          => $title,
            'description'    => $description,
            'featured_image' => $featured_image,
            'date'           => $date,
            'modified'       => $modified,
            'author'         => $author,
            'url'            => $url,
            'categories'     => $categories,
            'tags'           => $tags,
        ];
    }

    private static function getSeoTitle( int $post_id ): string {
        $title = get_post_meta( $post_id, '_yoast_wpseo_title', true );
        if ( ! empty( $title ) ) return $title;

        $title = get_post_meta( $post_id, 'rank_math_title', true );
        if ( ! empty( $title ) ) return $title;

        $title = get_post_meta( $post_id, '_aioseo_title', true );
        if ( ! empty( $title ) ) return $title;

        $title = get_post_meta( $post_id, '_seopress_titles_title', true );
        if ( ! empty( $title ) ) return $title;

        return '';
    }

    private static function getSeoDescription( int $post_id ): string {
        $desc = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
        if ( ! empty( $desc ) ) return $desc;

        $desc = get_post_meta( $post_id, 'rank_math_description', true );
        if ( ! empty( $desc ) ) return $desc;

        $desc = get_post_meta( $post_id, '_aioseo_description', true );
        if ( ! empty( $desc ) ) return $desc;

        $desc = get_post_meta( $post_id, '_seopress_titles_desc', true );
        if ( ! empty( $desc ) ) return $desc;

        return '';
    }

    private static function generateExcerpt( WP_Post $post ): string {
        $raw = $post->post_excerpt !== '' ? $post->post_excerpt : $post->post_content;
        $text = preg_replace( '/\[[^\]]+\]/', '', $raw ) ?? $raw;
        $text = wp_strip_all_tags( $text );
        $text = preg_replace( '/\s+/', ' ', $text ) ?? $text;
        $text = trim( $text );

        if ( strlen( $text ) > 300 ) {
            $text = substr( $text, 0, 297 ) . '...';
        }

        return $text;
    }

    private static function yamlEscape( string $s ): string {
        if ( preg_match( '/[:\[\]{}#&*!|>\'"%@`]/', $s ) || str_contains( $s, "\n" ) ) {
            return '"' . str_replace( [ '"', "\n" ], [ '\\"', ' ' ], $s ) . '"';
        }
        return $s;
    }
}

