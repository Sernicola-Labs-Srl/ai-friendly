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
        'static_md_files'       => '',      // Salva e servi file MD statici (piÃ¹ veloce)
        
        // Scheduler
        'auto_regenerate'       => '',      // Attiva rigenerazione automatica
        'regenerate_interval'   => 24,      // Ore tra rigenerazioni
        'regenerate_batch_size' => 100,     // Numero max contenuti processati per singolo run cron
        'regenerate_on_save'    => '1',     // Rigenera quando un contenuto viene salvato
        'regenerate_on_change'  => '1',     // Rigenera solo se checksum cambiato

        // UI Hub
        'onboarding_done'       => '',
        'ui_version'            => 'hub-v1',

        // Notifiche
        'notify_admin_notice'   => '1',
        'notify_email'          => '',
        'notify_email_to'       => '',

        // JSON-LD / Semantic identity
        'schema_enabled'        => '',
        'schema_mode'           => 'auto',
        'schema_entity_type'    => 'Person',
        'schema_name'           => '',
        'schema_alternate_name' => '',
        'schema_description'    => '',
        'schema_disambiguating_description' => '',
        'schema_job_title'      => '',
        'schema_additional_type' => '',
        'schema_slogan'         => '',
        'schema_founding_date'  => '',
        'schema_legal_name'     => '',
        'schema_vat_id'         => '',
        'schema_tax_id'         => '',
        'schema_lei_code'       => '',
        'schema_ticker_symbol'  => '',
        'schema_logo_id'        => 0,
        'schema_street_address' => '',
        'schema_postal_code'    => '',
        'schema_address_locality' => '',
        'schema_address_region' => '',
        'schema_address_country' => '',
        'schema_contact_type'   => '',
        'schema_contact_email'  => '',
        'schema_contact_languages' => '',
        'schema_founders'       => '',
        'schema_area_served'    => '',
        'schema_services'       => [],
        'schema_offer_catalog'  => '',
        'schema_image_id'       => 0,
        'schema_same_as'        => '',
        'schema_knows_about'    => '',
        'schema_knows_language' => '',
        'schema_license'        => '',
        'schema_profile_page_id' => 0,
    ];
}
