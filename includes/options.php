<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function ai_fr_get_default_options(): array {
    return [
        // Contenuto llms.txt
        'llms_content'      => '',
        'llms_include_auto' => '1',
        
        // Tipi di contenuto da includere
        'include_pages'     => '1',
        'include_posts'     => '1',
        'include_products'  => '',
        'include_cpt'       => [],  // Array di CPT custom da includere
        
        // Esclusioni
        'exclude_categories'    => [],      // ID categorie da escludere
        'exclude_tags'          => [],      // ID tag da escludere
        'exclude_templates'     => [],      // Template da escludere
        'exclude_url_patterns'  => '',      // Pattern URL (uno per riga)
        'exclude_noindex'       => '1',     // Escludi pagine con noindex
        'exclude_password'      => '1',     // Escludi contenuti protetti da password
        
        // Versioning MD
        'static_md_files'       => '',      // Salva e servi file MD statici (più veloce)
        
        // Scheduler
        'auto_regenerate'       => '',      // Attiva rigenerazione automatica
        'regenerate_interval'   => 24,      // Ore tra rigenerazioni
        'regenerate_on_save'    => '1',     // Rigenera quando un contenuto viene salvato
        'regenerate_on_change'  => '1',     // Rigenera solo se checksum cambiato
    ];
}
