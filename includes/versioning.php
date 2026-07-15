<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
//  VERSIONING MD - Salvataggio e gestione file statici
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

class AiFrVersioning {
    
    /**
     * Salva la versione MD di un post.
     * 
     * @return array{saved: bool, path: string, checksum: string, changed: bool}
     */
    public static function saveVersion( int $post_id, string $md_content ): array {
        
        $post = get_post( $post_id );
        if ( ! $post ) {
            return [ 'saved' => false, 'path' => '', 'checksum' => '', 'changed' => false ];
        }
        
        // Crea directory se non esiste
        if ( ! file_exists( AI_FR_VERSIONS_DIR ) ) {
            wp_mkdir_p( AI_FR_VERSIONS_DIR );
        }
        
        // Genera nome file basato su slug
        $filename = self::getFilename( $post );
        $filepath = AI_FR_VERSIONS_DIR . '/' . $filename;
        $previous_filename = (string) get_post_meta( $post_id, '_ai_fr_md_filename', true );
        
        // Calcola checksum nuovo contenuto
        $new_checksum = md5( $md_content );
        
        // Verifica se il contenuto Ã¨ cambiato
        $old_checksum = get_post_meta( $post_id, '_ai_fr_md_checksum', true );
        $changed = ( $old_checksum !== $new_checksum );
        
        // Salva solo se cambiato (o se non esiste)
        if ( $changed || ! file_exists( $filepath ) ) {
            $saved = file_put_contents( $filepath, $md_content ) !== false;
            
            if ( $saved ) {
                // Aggiorna meta con checksum e timestamp
                update_post_meta( $post_id, '_ai_fr_md_checksum', $new_checksum );
                update_post_meta( $post_id, '_ai_fr_md_generated', current_time( 'mysql' ) );
                update_post_meta( $post_id, '_ai_fr_md_filename', $filename );

                if ( $previous_filename !== '' && $previous_filename !== $filename ) {
                    self::deleteFileByFilename( $previous_filename );
                }
            }
            
            return [ 
                'saved' => $saved, 
                'path' => $filepath, 
                'checksum' => $new_checksum,
                'changed' => true 
            ];
        }
        
        return [ 
            'saved' => true, 
            'path' => $filepath, 
            'checksum' => $new_checksum,
            'changed' => false 
        ];
    }
    
    /**
     * Ottiene il contenuto MD salvato per un post.
     */
    public static function getVersion( int $post_id ): ?string {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return null;
        }

        $filename = get_post_meta( $post_id, '_ai_fr_md_filename', true );
        if ( empty( $filename ) || ! hash_equals( self::getFilename( $post ), (string) $filename ) ) {
            return null;
        }
        $filepath = self::getSafeFilepath( $filename );
        if ( ! $filepath || ! file_exists( $filepath ) ) {
            return null;
        }

        $content = file_get_contents( $filepath );
        if ( ! is_string( $content ) ) {
            return null;
        }

        // Empty or BOM-only payload should not shadow dynamic generation.
        return self::hasVisibleContent( $content ) ? $content : null;
    }
    
    /**
     * Verifica se esiste una versione salvata e valida.
     */
    public static function hasValidVersion( int $post_id ): bool {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return false;
        }

        $filename = get_post_meta( $post_id, '_ai_fr_md_filename', true );
        if ( empty( $filename ) || ! hash_equals( self::getFilename( $post ), (string) $filename ) ) {
            return false;
        }
        $filepath = self::getSafeFilepath( $filename );
        if ( ! $filepath || ! file_exists( $filepath ) ) {
            return false;
        }

        $content = file_get_contents( $filepath );
        return is_string( $content ) && self::hasVisibleContent( $content );
    }
    
    /**
     * Elimina la versione MD di un post.
     */
    public static function deleteVersion( int $post_id ): bool {
        $filename = get_post_meta( $post_id, '_ai_fr_md_filename', true );
        if ( empty( $filename ) ) {
            return true;
        }
        $filepath = self::getSafeFilepath( $filename );
        if ( $filepath && file_exists( $filepath ) ) {
            wp_delete_file( $filepath );
        }
        
        delete_post_meta( $post_id, '_ai_fr_md_checksum' );
        delete_post_meta( $post_id, '_ai_fr_md_generated' );
        delete_post_meta( $post_id, '_ai_fr_md_filename' );
        
        return true;
    }
    
    /**
     * Genera il nome file per un post.
     */
    private static function getFilename( WP_Post $post ): string {
        // L'ID rende univoco il file anche per contenuti gerarchici o tradotti
        // che condividono lo stesso slug finale.
        $slug = $post->post_name ?: 'post-' . $post->ID;
        return basename( sanitize_file_name( $post->post_type . '-' . $post->ID . '-' . $slug . '.md' ) );
    }
    
    /**
     * Restituisce un filepath sicuro all'interno della directory versioni.
     */
    private static function getSafeFilepath( string $filename ): ?string {
        $safe = basename( $filename );
        if ( $safe === '' ) {
            return null;
        }
        
        // Whitelist formato file: solo caratteri sicuri + estensione .md
        if ( ! preg_match( '/\A[a-z0-9._-]+\.md\z/i', $safe ) ) {
            return null;
        }
        
        $filepath = AI_FR_VERSIONS_DIR . '/' . $safe;
        if ( ! file_exists( $filepath ) ) {
            return $filepath;
        }
        
        $base = realpath( AI_FR_VERSIONS_DIR );
        $real = realpath( $filepath );
        if ( ! $base || ! $real ) {
            return null;
        }
        
        return str_starts_with( $real, $base ) ? $filepath : null;
    }

    /**
     * Elimina un singolo file solo se appartiene alla directory versioni.
     */
    private static function deleteFileByFilename( string $filename ): bool {
        $filepath = self::getSafeFilepath( $filename );
        if ( ! $filepath || ! file_exists( $filepath ) ) {
            return false;
        }

        wp_delete_file( $filepath );
        return ! file_exists( $filepath );
    }

    private static function hasVisibleContent( string $content ): bool {
        $probe = preg_replace( '/^\xEF\xBB\xBF/', '', $content ) ?? $content;
        return trim( $probe ) !== '';
    }
    
    /**
     * Ottiene statistiche sulle versioni salvate.
     */
    public static function getStats(): array {
        if ( ! file_exists( AI_FR_VERSIONS_DIR ) ) {
            return [ 'count' => 0, 'size' => 0, 'files' => [] ];
        }
        
        $files = glob( AI_FR_VERSIONS_DIR . '/*.md' );
        $files = is_array( $files ) ? $files : [];
        $total_size = 0;
        
        foreach ( $files as $file ) {
            $total_size += filesize( $file );
        }
        
        return [
            'count' => count( $files ),
            'size'  => $total_size,
            'files' => array_map( 'basename', $files ),
        ];
    }
    
    /**
     * Pulisce tutte le versioni salvate.
     */
    public static function clearAll(): int {
        if ( ! file_exists( AI_FR_VERSIONS_DIR ) ) {
            return 0;
        }
        
        $files = glob( AI_FR_VERSIONS_DIR . '/*.md' );
        $files = is_array( $files ) ? $files : [];
        $count = 0;
        
        foreach ( $files as $file ) {
            wp_delete_file( $file );
            if ( ! file_exists( $file ) ) {
                $count++;
            }
        }
        
        // Pulisci anche i meta
        delete_metadata( 'post', 0, '_ai_fr_md_checksum', '', true );
        delete_metadata( 'post', 0, '_ai_fr_md_generated', '', true );
        delete_metadata( 'post', 0, '_ai_fr_md_filename', '', true );
        
        return $count;
    }

    /**
     * Rimuove versioni non piu' servibili e file orfani rimasti sul disco.
     */
    public static function pruneObsoleteVersions(): int {
        $options = wp_parse_args( get_option( 'ai_fr_options', [] ), ai_fr_get_default_options() );
        if ( empty( $options['static_md_files'] ) ) {
            return self::clearAll();
        }

        $post_types = array_values( get_post_types( [], 'names' ) );
        $post_ids = get_posts(
            [
                'post_type'              => $post_types,
                'post_status'            => array_keys( get_post_stati() ),
                'posts_per_page'         => -1,
                'fields'                 => 'ids',
                'meta_key'               => '_ai_fr_md_filename',
                'no_found_rows'          => true,
                'suppress_filters'       => true,
                'update_post_meta_cache' => true,
                'update_post_term_cache' => false,
            ]
        );

        $filter = new AiFrContentFilter();
        $referenced = [];
        $deleted = 0;

        foreach ( $post_ids as $post_id ) {
            $post = get_post( $post_id );
            $filename = (string) get_post_meta( $post_id, '_ai_fr_md_filename', true );
            $is_current = $post instanceof WP_Post
                && $post->post_status === 'publish'
                && $filter->shouldInclude( $post )
                && $filename !== ''
                && hash_equals( self::getFilename( $post ), $filename );

            if ( ! $is_current ) {
                if ( self::deleteFileByFilename( $filename ) ) {
                    $deleted++;
                }
                delete_post_meta( $post_id, '_ai_fr_md_checksum' );
                delete_post_meta( $post_id, '_ai_fr_md_generated' );
                delete_post_meta( $post_id, '_ai_fr_md_filename' );
                continue;
            }

            $referenced[ $filename ] = true;
        }

        $version_files = glob( AI_FR_VERSIONS_DIR . '/*.md' );
        $version_files = is_array( $version_files ) ? $version_files : [];
        foreach ( $version_files as $file ) {
            if ( isset( $referenced[ basename( $file ) ] ) ) {
                continue;
            }
            wp_delete_file( $file );
            if ( ! file_exists( $file ) ) {
                $deleted++;
            }
        }

        return $deleted;
    }
}

