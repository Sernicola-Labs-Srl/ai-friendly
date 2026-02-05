<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════════
//  FILTRO CONTENUTI - Classe per gestire inclusioni/esclusioni
// ═══════════════════════════════════════════════════════════════════════════════

class AiFrContentFilter {
    
    private array $options;
    
    public function __construct() {
        $this->options = wp_parse_args( 
            get_option( 'ai_fr_options', [] ), 
            ai_fr_get_default_options() 
        );
    }
    
    /**
     * Verifica se un post deve essere incluso nell'output AI Friendly.
     */
    public function shouldInclude( WP_Post $post ): bool {
        
        // 1. Escluso manualmente via metabox
        if ( get_post_meta( $post->ID, '_ai_fr_exclude', true ) ) {
            return false;
        }
        
        // 2. Contenuto protetto da password
        if ( ! empty( $this->options['exclude_password'] ) && $post->post_password !== '' ) {
            return false;
        }
        
        // 3. Verifica tipo di contenuto abilitato
        if ( ! $this->isPostTypeEnabled( $post->post_type ) ) {
            return false;
        }
        
        // 4. Escludi per categoria
        if ( $this->isExcludedByTaxonomy( $post->ID, 'category', $this->options['exclude_categories'] ?? [] ) ) {
            return false;
        }
        
        // 5. Escludi per tag
        if ( $this->isExcludedByTaxonomy( $post->ID, 'post_tag', $this->options['exclude_tags'] ?? [] ) ) {
            return false;
        }
        
        // 6. Escludi per template
        if ( $this->isExcludedByTemplate( $post->ID ) ) {
            return false;
        }
        
        // 7. Escludi per pattern URL
        if ( $this->isExcludedByUrlPattern( $post->ID ) ) {
            return false;
        }
        
        // 8. Escludi pagine con noindex (da plugin SEO)
        if ( ! empty( $this->options['exclude_noindex'] ) && $this->hasNoIndex( $post->ID ) ) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Verifica se il tipo di post è abilitato.
     */
    public function isPostTypeEnabled( string $post_type ): bool {
        switch ( $post_type ) {
            case 'page':
                return ! empty( $this->options['include_pages'] );
            case 'post':
                return ! empty( $this->options['include_posts'] );
            case 'product':
                return ! empty( $this->options['include_products'] );
            default:
                // CPT custom
                $enabled_cpt = $this->options['include_cpt'] ?? [];
                return in_array( $post_type, (array) $enabled_cpt, true );
        }
    }
    
    /**
     * Ottiene tutti i post type abilitati.
     */
    public function getEnabledPostTypes(): array {
        $types = [];
        
        if ( ! empty( $this->options['include_pages'] ) ) {
            $types[] = 'page';
        }
        if ( ! empty( $this->options['include_posts'] ) ) {
            $types[] = 'post';
        }
        if ( ! empty( $this->options['include_products'] ) && class_exists( 'WooCommerce' ) ) {
            $types[] = 'product';
        }
        
        // CPT custom
        $enabled_cpt = $this->options['include_cpt'] ?? [];
        $types = array_merge( $types, (array) $enabled_cpt );
        
        return array_unique( $types );
    }
    
    /**
     * Verifica se il post è escluso per tassonomia (categoria/tag).
     */
    private function isExcludedByTaxonomy( int $post_id, string $taxonomy, array $excluded_ids ): bool {
        if ( empty( $excluded_ids ) ) {
            return false;
        }
        
        $terms = wp_get_post_terms( $post_id, $taxonomy, [ 'fields' => 'ids' ] );
        if ( is_wp_error( $terms ) ) {
            return false;
        }
        
        return ! empty( array_intersect( $terms, array_map( 'intval', $excluded_ids ) ) );
    }
    
    /**
     * Verifica se il post è escluso per template.
     */
    private function isExcludedByTemplate( int $post_id ): bool {
        $excluded_templates = $this->options['exclude_templates'] ?? [];
        if ( empty( $excluded_templates ) ) {
            return false;
        }
        
        $template = get_page_template_slug( $post_id );
        return in_array( $template, (array) $excluded_templates, true );
    }
    
    /**
     * Verifica se il post è escluso per pattern URL.
     */
    private function isExcludedByUrlPattern( int $post_id ): bool {
        $patterns = $this->options['exclude_url_patterns'] ?? '';
        if ( empty( trim( $patterns ) ) ) {
            return false;
        }
        
        $permalink = get_permalink( $post_id );
        $patterns_array = array_filter( array_map( 'trim', explode( "\n", $patterns ) ) );
        
        foreach ( $patterns_array as $pattern ) {
            // Supporta wildcard * e regex
            if ( str_starts_with( $pattern, '/' ) && str_ends_with( $pattern, '/' ) ) {
                // È una regex
                if ( @preg_match( $pattern, $permalink ) ) {
                    return true;
                }
            } else {
                // Pattern semplice con wildcard
                $regex = str_replace( [ '*', '?' ], [ '.*', '.' ], preg_quote( $pattern, '/' ) );
                if ( preg_match( '/' . $regex . '/i', $permalink ) ) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Verifica se il post ha meta noindex (da plugin SEO).
     */
    private function hasNoIndex( int $post_id ): bool {
        // Yoast SEO
        $yoast = get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true );
        if ( $yoast === '1' ) {
            return true;
        }
        
        // Rank Math
        $rankmath = get_post_meta( $post_id, 'rank_math_robots', true );
        if ( is_array( $rankmath ) && in_array( 'noindex', $rankmath, true ) ) {
            return true;
        }
        if ( is_string( $rankmath ) && str_contains( $rankmath, 'noindex' ) ) {
            return true;
        }
        
        // All in One SEO
        $aioseo = get_post_meta( $post_id, '_aioseo_noindex', true );
        if ( $aioseo === '1' || $aioseo === true ) {
            return true;
        }
        
        // SEOPress
        $seopress = get_post_meta( $post_id, '_seopress_robots_index', true );
        if ( $seopress === 'yes' ) {  // "yes" significa noindex in SEOPress
            return true;
        }
        
        return false;
    }
    
    /**
     * Ottiene le opzioni correnti.
     */
    public function getOptions(): array {
        return $this->options;
    }
}

