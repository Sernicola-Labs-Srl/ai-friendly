<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action(
    'admin_menu',
    function () {
        add_options_page(
            'AI Friendly - AI Content Hub',
            'AI Friendly',
            'manage_options',
            'ai-friendly',
            'ai_fr_render_options_page'
        );
    }
);

add_action(
    'admin_enqueue_scripts',
    function ( string $hook ): void {
        if ( $hook !== 'settings_page_ai-friendly' ) {
            return;
        }

        $admin_css_path = AI_FR_PLUGIN_DIR . '/admin/assets/ai-fr-admin.css';
        $admin_js_path  = AI_FR_PLUGIN_DIR . '/admin/assets/ai-fr-admin.js';
        $admin_css_version = AI_FR_VERSION . '-' . (string) ( file_exists( $admin_css_path ) ? filemtime( $admin_css_path ) : '0' );
        $admin_js_version  = AI_FR_VERSION . '-' . (string) ( file_exists( $admin_js_path ) ? filemtime( $admin_js_path ) : '0' );

        wp_enqueue_style(
            'ai-fr-admin',
            plugins_url( 'admin/assets/ai-fr-admin.css', AI_FR_PLUGIN_FILE ),
            [],
            $admin_css_version
        );

        wp_enqueue_script(
            'ai-fr-admin',
            plugins_url( 'admin/assets/ai-fr-admin.js', AI_FR_PLUGIN_FILE ),
            [ 'jquery' ],
            $admin_js_version,
            true
        );

        wp_enqueue_media();

        $editor = wp_enqueue_code_editor( [ 'type' => 'text/x-markdown' ] );
        if ( $editor ) {
            wp_enqueue_script( 'wp-theme-plugin-editor' );
            wp_enqueue_style( 'wp-codemirror' );
            wp_localize_script(
                'ai-fr-admin',
                'AiFrCodeEditor',
                [ 'settings' => $editor ]
            );
        }

        wp_localize_script(
            'ai-fr-admin',
            'AiFrAdmin',
            [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'ai_fr_admin_nonce' ),
                'i18n'    => [
                    'loading'             => __( 'Caricamento...', 'ai-friendly' ),
                    'error'               => __( 'Si è verificato un errore.', 'ai-friendly' ),
                    'noHeadings'          => __( 'Nessun heading trovato.', 'ai-friendly' ),
                    'noWarnings'          => __( 'Nessun avviso.', 'ai-friendly' ),
                    'included'            => __( 'Inclusa', 'ai-friendly' ),
                    'excluded'            => __( 'Esclusa', 'ai-friendly' ),
                    'include'             => __( 'Includi', 'ai-friendly' ),
                    'excludeContent'      => __( 'Escludi', 'ai-friendly' ),
                    'untitled'            => __( '(Senza titolo)', 'ai-friendly' ),
                    'noContent'           => __( 'Nessun contenuto.', 'ai-friendly' ),
                    'page'                => __( 'Pagina', 'ai-friendly' ),
                    'noEvents'            => __( 'Nessun evento.', 'ai-friendly' ),
                    'restore'             => __( 'Ripristina', 'ai-friendly' ),
                    'noSnapshots'         => __( 'Nessuno snapshot.', 'ai-friendly' ),
                    'selectTwoSnapshots'  => __( 'Seleziona esattamente 2 snapshot.', 'ai-friendly' ),
                    'noDifferences'       => __( 'Nessuna differenza.', 'ai-friendly' ),
                    'remove'              => __( 'Rimuovi', 'ai-friendly' ),
                    'noImage'             => __( 'Nessuna immagine', 'ai-friendly' ),
                    'noLogo'              => __( 'Nessun logo', 'ai-friendly' ),
                    'saving'              => __( 'Salvataggio in corso…', 'ai-friendly' ),
                    'saveAndGenerate'      => __( 'Salva e genera', 'ai-friendly' ),
                    'setupComplete'        => __( 'Configurazione completata. L’output è stato verificato.', 'ai-friendly' ),
                    'requestFailed'        => __( 'Impossibile completare la richiesta. Riprova senza perdere le scelte effettuate.', 'ai-friendly' ),
                    'regenerationComplete' => __( 'Rigenerazione completata.', 'ai-friendly' ),
                    'forcedComplete'       => __( 'Rigenerazione forzata completata.', 'ai-friendly' ),
                    'confirmDeleteFiles'   => __( 'Eliminare tutti i file Markdown salvati?', 'ai-friendly' ),
                    'service'              => __( 'Servizio', 'ai-friendly' ),
                    'name'                 => __( 'Nome', 'ai-friendly' ),
                    'pageUrl'              => __( 'URL pagina', 'ai-friendly' ),
                    'serviceType'          => __( 'Tipo servizio', 'ai-friendly' ),
                    'areaServed'           => __( 'Area servita', 'ai-friendly' ),
                    'description'          => __( 'Descrizione', 'ai-friendly' ),
                    'price'                => __( 'Prezzo', 'ai-friendly' ),
                    'currency'             => __( 'Valuta', 'ai-friendly' ),
                    'noManualServices'     => __( 'Nessun servizio manuale configurato.', 'ai-friendly' ),
                    'additionalType'       => __( 'Tipo aggiuntivo', 'ai-friendly' ),
                    'schemaType'           => __( 'Tipo Schema.org', 'ai-friendly' ),
                    'contact'              => __( 'Contatto', 'ai-friendly' ),
                    'department'           => __( 'Reparto / funzione', 'ai-friendly' ),
                    'phone'                => __( 'Telefono', 'ai-friendly' ),
                    'languages'            => __( 'Lingue', 'ai-friendly' ),
                    'availability'         => __( 'Disponibilità', 'ai-friendly' ),
                    'timeSlot'             => __( 'Fascia oraria', 'ai-friendly' ),
                    'days'                 => __( 'Giorni', 'ai-friendly' ),
                    'opens'                => __( 'Apertura', 'ai-friendly' ),
                    'closes'               => __( 'Chiusura', 'ai-friendly' ),
                    'validFrom'            => __( 'Valida dal', 'ai-friendly' ),
                    'validThrough'         => __( 'Valida fino al', 'ai-friendly' ),
                    'certification'        => __( 'Certificazione', 'ai-friendly' ),
                    'identifier'           => __( 'Identificatore', 'ai-friendly' ),
                    'source'               => __( 'Sorgente WordPress', 'ai-friendly' ),
                    'noConfiguredItem'     => __( 'Nessun elemento configurato.', 'ai-friendly' ),
                    'none'                 => __( 'Nessuno', 'ai-friendly' ),
                    'staticOutput'         => __( 'File Markdown statici', 'ai-friendly' ),
                    'dynamicOutput'        => __( 'Output dinamico', 'ai-friendly' ),
                    'scheduledDisabled'    => __( 'Rigenerazione pianificata disattivata', 'ai-friendly' ),
                    'disabled'             => __( 'Disattivato', 'ai-friendly' ),
                    'enabled'              => __( 'Attivato', 'ai-friendly' ),
                    'acfFields'            => __( 'Campi ACF', 'ai-friendly' ),
                    'includedContent'      => __( 'Contenuti inclusi', 'ai-friendly' ),
                    'output'               => __( 'Output', 'ai-friendly' ),
                    'automation'           => __( 'Automazione', 'ai-friendly' ),
                    'semanticSchema'       => __( 'Semantic Schema', 'ai-friendly' ),
                    'selectIdentityImage'  => __( 'Seleziona immagine identitaria', 'ai-friendly' ),
                    'useImage'             => __( 'Usa questa immagine', 'ai-friendly' ),
                    'selectLogo'           => __( 'Seleziona logo aziendale', 'ai-friendly' ),
                    'useLogo'              => __( 'Usa questo logo', 'ai-friendly' ),
                    'issuesSingular'        => __( 'problema', 'ai-friendly' ),
                    'issuesPlural'          => __( 'problemi', 'ai-friendly' ),
                    'sitemap'               => __( 'Sitemap', 'ai-friendly' ),
                    'robots'                => __( 'Robots', 'ai-friendly' ),
                    'notAvailable'          => __( 'n/d', 'ai-friendly' ),
                    'token'                 => __( 'token', 'ai-friendly' ),
                    'lines'                 => __( 'Linee', 'ai-friendly' ),
                    'tokenDelta'            => __( 'Delta token', 'ai-friendly' ),
                    'score'                 => __( 'Punteggio', 'ai-friendly' ),
                    'duplicates'            => __( 'Duplicati', 'ai-friendly' ),
                    'certificateNumber'     => __( 'Numero certificato', 'ai-friendly' ),
                    'certificationIssuer'   => __( 'Ente certificatore', 'ai-friendly' ),
                    'identifierNumber'      => __( 'Numero identificativo', 'ai-friendly' ),
                    'sourceReference'       => __( 'ID termine, taxonomy:slug o permalink WordPress', 'ai-friendly' ),
                    'value'                 => __( 'Valore', 'ai-friendly' ),
                    'noAdditionalTypes'     => __( 'Nessun tipo aggiuntivo configurato.', 'ai-friendly' ),
                    'noContacts'            => __( 'Nessun contatto configurato.', 'ai-friendly' ),
                    'noTimeSlots'           => __( 'Nessuna fascia oraria configurata.', 'ai-friendly' ),
                    'noCertifications'      => __( 'Nessuna certificazione configurata.', 'ai-friendly' ),
                    'noIdentifiers'         => __( 'Nessun identificatore aggiuntivo configurato.', 'ai-friendly' ),
                    'noOfferSources'        => __( 'Nessuna sorgente WordPress configurata.', 'ai-friendly' ),
                    'every'                 => __( 'Ogni', 'ai-friendly' ),
                    'hoursBatch'            => __( 'ore, batch da', 'ai-friendly' ),
                    'nameNotProvided'       => __( 'nome non indicato', 'ai-friendly' ),
                    'savingAndVerifying'    => __( 'Salvataggio delle impostazioni e verifica dell’output in corso.', 'ai-friendly' ),
                    'generationFailed'      => __( 'Generazione non riuscita. Le impostazioni salvate restano attive.', 'ai-friendly' ),
                    'batchComplete'         => __( 'Batch completato.', 'ai-friendly' ),
                    'processed'             => __( 'Processati', 'ai-friendly' ),
                    'regenerated'           => __( 'rigenerati', 'ai-friendly' ),
                    'deletedFiles'          => __( 'File eliminati', 'ai-friendly' ),
                    'unsavedChanges'        => __( 'Modifiche non salvate', 'ai-friendly' ),
                    'allChangesSaved'       => __( 'Tutte le modifiche sono salvate', 'ai-friendly' ),
                    'serviceDescription'    => __( 'Descrizione breve del servizio.', 'ai-friendly' ),
                ],
            ]
        );
    }
);

function ai_fr_admin_require_permissions(): void {
    check_ajax_referer( 'ai_fr_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'Permessi insufficienti.', 'ai-friendly' ) );
    }
}

function ai_fr_post_raw( string $key, $default = '' ) {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is validated by caller; sanitization is applied in typed helper wrappers.
    return isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : $default;
}

function ai_fr_post_bool( string $key ): bool {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is validated in caller before using request data.
    return ! empty( $_POST[ $key ] );
}

function ai_fr_post_int( string $key, int $default = 0 ): int {
    return intval( ai_fr_post_raw( $key, $default ) );
}

function ai_fr_post_key( string $key, string $default = '' ): string {
    return sanitize_key( (string) ai_fr_post_raw( $key, $default ) );
}

function ai_fr_post_text( string $key, string $default = '' ): string {
    return sanitize_text_field( (string) ai_fr_post_raw( $key, $default ) );
}

function ai_fr_post_array( string $key ): array {
    $value = ai_fr_post_raw( $key, [] );
    return is_array( $value ) ? $value : [];
}

function ai_fr_admin_sanitize_schema_services( array $rows ): array {
    $services = [];

    foreach ( $rows as $row ) {
        if ( ! is_array( $row ) ) {
            continue;
        }

        $service = [
            'name'          => sanitize_text_field( (string) ( $row['name'] ?? '' ) ),
            'url'           => esc_url_raw( (string) ( $row['url'] ?? '' ) ),
            'serviceType'   => sanitize_text_field( (string) ( $row['serviceType'] ?? '' ) ),
            'description'   => sanitize_textarea_field( (string) ( $row['description'] ?? '' ) ),
            'areaServed'    => sanitize_text_field( (string) ( $row['areaServed'] ?? '' ) ),
            'price'         => sanitize_text_field( (string) ( $row['price'] ?? '' ) ),
            'priceCurrency' => sanitize_text_field( (string) ( $row['priceCurrency'] ?? '' ) ),
        ];

        if ( $service['name'] === '' && $service['url'] === '' ) {
            continue;
        }

        $services[] = $service;
    }

    return $services;
}

function ai_fr_admin_sanitize_schema_rows( array $rows, array $fields ): array {
    $clean = [];
    foreach ( $rows as $row ) {
        if ( ! is_array( $row ) ) {
            continue;
        }
        $item = [];
        foreach ( $fields as $key => $kind ) {
            $value = (string) ( $row[ $key ] ?? '' );
            if ( $kind === 'email' ) {
                $item[ $key ] = sanitize_email( $value );
            } elseif ( $kind === 'url' ) {
                $item[ $key ] = esc_url_raw( $value );
            } elseif ( $kind === 'textarea' ) {
                $item[ $key ] = sanitize_textarea_field( $value );
            } else {
                $item[ $key ] = sanitize_text_field( $value );
            }
        }
        if ( count( array_filter( $item, static fn( string $value ): bool => $value !== '' ) ) > 0 ) {
            $clean[] = $item;
        }
    }
    return $clean;
}

add_action(
    'wp_ajax_ai_fr_regenerate_all',
    function (): void {
        ai_fr_admin_require_permissions();
        $force = ai_fr_post_bool( 'force' );
        $mode  = ai_fr_post_key( 'mode', 'full' );
        if ( $mode === 'batch' ) {
            $options = wp_parse_args( get_option( 'ai_fr_options', [] ), ai_fr_get_default_options() );
            $batch_size = min( 1000, max( 10, intval( $options['regenerate_batch_size'] ?? 100 ) ) );
            $stats = ai_fr_regenerate_batch( $batch_size, $force, 'manual_ajax_batch' );
        } else {
            $stats = ai_fr_regenerate_all( $force, 'manual_ajax' );
        }
        wp_send_json_success( $stats );
    }
);

add_action(
    'wp_ajax_ai_fr_clear_versions',
    function (): void {
        ai_fr_admin_require_permissions();
        $count = AiFrVersioning::clearAll();
        ai_fr_add_event( 'clear_versions', [ 'deleted' => $count ] );
        wp_send_json_success( [ 'deleted' => $count ] );
    }
);

add_action(
    'wp_ajax_ai_fr_get_overview_stats',
    function (): void {
        ai_fr_admin_require_permissions();
        wp_send_json_success( ai_fr_get_overview_stats() );
    }
);

add_action(
    'wp_ajax_ai_fr_list_content_items',
    function (): void {
        ai_fr_admin_require_permissions();
        $result = ai_fr_list_content_items(
            [
                'page'      => ai_fr_post_int( 'page', 1 ),
                'per_page'  => ai_fr_post_int( 'per_page', 10 ),
                'search'    => ai_fr_post_text( 'search', '' ),
                'status'    => ai_fr_post_key( 'status', 'any' ),
                'post_type' => ai_fr_post_key( 'post_type', 'all' ),
            ]
        );
        wp_send_json_success( $result );
    }
);

add_action(
    'wp_ajax_ai_fr_toggle_content_exclusion',
    function (): void {
        ai_fr_admin_require_permissions();
        $post_id = ai_fr_post_int( 'post_id', 0 );
        $exclude = ai_fr_post_bool( 'exclude' );
        $post    = get_post( $post_id );
        if ( ! $post ) {
            wp_send_json_error( __( 'Contenuto non trovato.', 'ai-friendly' ) );
        }

        if ( $exclude ) {
            update_post_meta( $post_id, '_ai_fr_exclude', '1' );
        } else {
            delete_post_meta( $post_id, '_ai_fr_exclude' );
        }

        ai_fr_add_event(
            'toggle_exclusion',
            [
                'post_id'   => $post_id,
                'is_excluded' => $exclude ? 1 : 0,
                'post_type' => $post->post_type,
            ]
        );

        wp_send_json_success(
            [
                'post_id'  => $post_id,
                'excluded' => $exclude,
            ]
        );
    }
);

add_action(
    'wp_ajax_ai_fr_get_event_timeline',
    function (): void {
        ai_fr_admin_require_permissions();
        $limit = min( 100, max( 5, ai_fr_post_int( 'limit', 20 ) ) );
        wp_send_json_success(
            [
                'items' => array_slice( ai_fr_get_event_log(), 0, $limit ),
            ]
        );
    }
);

add_action(
    'wp_ajax_ai_fr_run_diagnostics',
    function (): void {
        ai_fr_admin_require_permissions();
        wp_send_json_success( ai_fr_run_diagnostics() );
    }
);

add_action(
    'wp_ajax_ai_fr_get_llms_preview',
    function (): void {
        ai_fr_admin_require_permissions();
        $content = (string) ai_fr_post_raw( 'content', ai_fr_build_llms_txt() );

        wp_send_json_success(
            [
                'html'       => ai_fr_render_markdown_preview_html( $content ),
                'tokens'     => ai_fr_estimate_tokens( $content ),
                'chars'      => strlen( $content ),
                'simulation' => ai_fr_run_ai_simulation( $content ),
                'validation' => ai_fr_validate_llms_links( $content ),
            ]
        );
    }
);

add_action(
    'wp_ajax_ai_fr_create_llms_snapshot',
    function (): void {
        ai_fr_admin_require_permissions();
        $reason  = ai_fr_post_text( 'reason', 'manual' );
        $content = (string) ai_fr_post_raw( 'content', ai_fr_build_llms_txt() );
        $result  = ai_fr_create_llms_snapshot( $content, $reason );

        if ( empty( $result['saved'] ) ) {
            wp_send_json_error( __( 'Impossibile creare snapshot.', 'ai-friendly' ) );
        }

        ai_fr_add_event(
            'llms_snapshot_create',
            [
                'reason' => $reason,
                'id'     => $result['entry']['id'] ?? '',
            ]
        );
        wp_send_json_success( $result );
    }
);

add_action(
    'wp_ajax_ai_fr_list_llms_snapshots',
    function (): void {
        ai_fr_admin_require_permissions();
        wp_send_json_success( [ 'items' => ai_fr_get_llms_history_index() ] );
    }
);

add_action(
    'wp_ajax_ai_fr_restore_llms_snapshot',
    function (): void {
        ai_fr_admin_require_permissions();
        $id     = ai_fr_post_text( 'id', '' );
        $result = ai_fr_restore_llms_snapshot( $id );

        if ( empty( $result['restored'] ) ) {
            wp_send_json_error( $result['message'] ?? __( 'Ripristino fallito.', 'ai-friendly' ) );
        }

        ai_fr_add_event( 'llms_snapshot_restore', [ 'id' => $id ] );
        wp_send_json_success( $result );
    }
);

add_action(
    'wp_ajax_ai_fr_compare_llms_snapshots',
    function (): void {
        ai_fr_admin_require_permissions();
        $left_id  = ai_fr_post_text( 'left_id', '' );
        $right_id = ai_fr_post_text( 'right_id', '' );

        if ( $left_id === '' || $right_id === '' ) {
            wp_send_json_error( __( 'Seleziona due snapshot da confrontare.', 'ai-friendly' ) );
        }

        $left_content  = ai_fr_get_llms_snapshot_content( $left_id );
        $right_content = ai_fr_get_llms_snapshot_content( $right_id );
        if ( ! is_string( $left_content ) || ! is_string( $right_content ) ) {
            wp_send_json_error( __( 'Uno o entrambi gli snapshot non sono disponibili.', 'ai-friendly' ) );
        }

        wp_send_json_success( ai_fr_diff_llms_content( $left_content, $right_content ) );
    }
);

add_action(
    'wp_ajax_ai_fr_set_onboarding_status',
    function (): void {
        ai_fr_admin_require_permissions();
        $done = ai_fr_post_bool( 'done' ) ? '1' : '';

        $options                    = wp_parse_args( get_option( 'ai_fr_options', [] ), ai_fr_get_default_options() );
        $options['onboarding_done'] = $done;
        update_option( 'ai_fr_options', $options );
        update_option( 'ai_fr_onboarding_done', $done, false );

        ai_fr_add_event(
            'onboarding_status',
            [
                'done' => $done === '1' ? 1 : 0,
            ]
        );

        wp_send_json_success(
            [
                'done' => $done,
            ]
        );
    }
);

add_action(
    'wp_ajax_ai_fr_complete_setup',
    function (): void {
        ai_fr_admin_require_permissions();

        $options = wp_parse_args( get_option( 'ai_fr_options', [] ), ai_fr_get_default_options() );
        $types   = array_values( array_unique( array_filter( array_map( 'sanitize_key', ai_fr_post_array( 'content_types' ) ) ) ) );
        $public  = get_post_types( [ 'public' => true ], 'names' );
        unset( $public['attachment'] );
        $types = array_values( array_intersect( $types, array_keys( $public ) ) );

        $options['include_pages']     = in_array( 'page', $types, true ) ? '1' : '';
        $options['include_posts']     = in_array( 'post', $types, true ) ? '1' : '';
        $options['include_products']  = class_exists( 'WooCommerce' ) && in_array( 'product', $types, true ) ? '1' : '';
        $options['include_cpt']       = array_values( array_diff( $types, [ 'page', 'post', 'product' ] ) );
        $options['include_acf_fields'] = ai_fr_post_bool( 'include_acf_fields' ) ? '1' : '';
        $options['exclude_noindex']   = ai_fr_post_bool( 'exclude_noindex' ) ? '1' : '';
        $options['exclude_password']  = ai_fr_post_bool( 'exclude_password' ) ? '1' : '';
        $options['llms_include_auto'] = ai_fr_post_bool( 'llms_include_auto' ) ? '1' : '';
        $options['static_md_files']   = ai_fr_post_bool( 'static_md_files' ) ? '1' : '';
        $options['auto_regenerate']   = ai_fr_post_bool( 'auto_regenerate' ) ? '1' : '';
        if ( ! empty( $options['auto_regenerate'] ) ) {
            $options['static_md_files'] = '1';
        }
        $options['regenerate_interval']   = min( 168, max( 1, ai_fr_post_int( 'regenerate_interval', 24 ) ) );
        $options['regenerate_batch_size'] = min( 1000, max( 10, ai_fr_post_int( 'regenerate_batch_size', 100 ) ) );
        $options['regenerate_on_save']    = ai_fr_post_bool( 'regenerate_on_save' ) ? '1' : '';
        $options['regenerate_on_change']  = ai_fr_post_bool( 'regenerate_on_change' ) ? '1' : '';
        $options['schema_enabled']        = ai_fr_post_bool( 'schema_enabled' ) ? '1' : '';
        $options['schema_mode']           = 'auto';
        $entity_type = ai_fr_post_text( 'schema_entity_type', 'Organization' );
        $options['schema_entity_type'] = in_array( $entity_type, [ 'Person', 'Organization' ], true ) ? $entity_type : 'Organization';
        $options['schema_name']        = ai_fr_post_text( 'schema_name', get_bloginfo( 'name' ) );
        $options['schema_same_as']     = sanitize_textarea_field( (string) ai_fr_post_raw( 'schema_same_as', '' ) );
        if ( ai_fr_is_breakdance_active() ) {
            $options['schema_breakdance_faq_enabled'] = ai_fr_post_bool( 'schema_breakdance_faq_enabled' ) ? '1' : '';
        }
        $options['onboarding_done'] = '1';
        $options['ui_version']      = 'hub-v2';

        update_option( 'ai_fr_options', $options );
        update_option( 'ai_fr_onboarding_done', '1', false );
        update_option( 'ai_fr_ui_version', 'hub-v2', false );
        delete_option( 'ai_fr_regeneration_cursor' );
        AiFrVersioning::pruneObsoleteVersions();
        ai_fr_schedule_cron();
        ai_fr_add_event( 'settings_saved', [ 'source' => 'setup_wizard' ] );

        try {
            if ( ! empty( $options['static_md_files'] ) ) {
                $stats = ai_fr_regenerate_all( false, 'setup_wizard' );
                if ( ! empty( $stats['errors'] ) ) {
                    throw new RuntimeException( 'La generazione ha restituito uno o piu errori.' );
                }
                $output = [
                    'mode'  => 'static',
                    'stats' => $stats,
                ];
            } else {
                $content = ai_fr_build_llms_txt();
                $output  = [
                    'mode'       => 'dynamic',
                    'validation' => ai_fr_validate_llms_links( $content ),
                    'tokens'     => ai_fr_estimate_tokens( $content ),
                    'chars'      => strlen( $content ),
                ];
            }
        } catch ( Throwable $error ) {
            ai_fr_add_event( 'setup_generation_error', [ 'message' => $error->getMessage() ] );
            wp_send_json_error(
                [
                    'saved'      => true,
                    'message'    => __( 'Le impostazioni sono state salvate, ma la prima generazione non è riuscita.', 'ai-friendly' ),
                    'diagnostics'=> ai_fr_run_diagnostics(),
                ]
            );
        }

        wp_send_json_success(
            [
                'saved'       => true,
                'settings'    => [
                    'content_types'  => $types,
                    'static_enabled' => ! empty( $options['static_md_files'] ),
                    'schema_enabled' => ! empty( $options['schema_enabled'] ),
                ],
                'output'      => $output,
                'diagnostics' => ai_fr_run_diagnostics(),
                'done'        => '1',
            ]
        );
    }
);

add_action(
    'wp_ajax_ai_fr_run_ai_simulation',
    function (): void {
        ai_fr_admin_require_permissions();
        $content = (string) ai_fr_post_raw( 'content', '' );
        wp_send_json_success( ai_fr_run_ai_simulation( $content ) );
    }
);

function ai_fr_render_options_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $defaults = ai_fr_get_default_options();
    $options  = wp_parse_args( get_option( 'ai_fr_options', [] ), $defaults );
    $settings_saved = false;

    if ( ai_fr_post_bool( 'ai_fr_save' ) && check_admin_referer( 'ai_fr_options_nonce' ) ) {
        $options['llms_content']         = sanitize_textarea_field( (string) ai_fr_post_raw( 'llms_content', '' ) );
        $options['llms_include_auto']    = ai_fr_post_bool( 'llms_include_auto' ) ? '1' : '';
        $options['include_pages']        = ai_fr_post_bool( 'include_pages' ) ? '1' : '';
        $options['include_posts']        = ai_fr_post_bool( 'include_posts' ) ? '1' : '';
        $options['include_products']     = ai_fr_post_bool( 'include_products' ) ? '1' : '';
        $options['include_cpt']          = array_map( 'sanitize_key', ai_fr_post_array( 'include_cpt' ) );
        $options['include_acf_fields']   = ai_fr_post_bool( 'include_acf_fields' ) ? '1' : '';
        $options['exclude_categories']   = array_map( 'intval', ai_fr_post_array( 'exclude_categories' ) );
        $options['exclude_tags']         = array_map( 'intval', ai_fr_post_array( 'exclude_tags' ) );
        $options['exclude_templates']    = array_map( 'sanitize_text_field', ai_fr_post_array( 'exclude_templates' ) );
        $options['exclude_url_patterns'] = sanitize_textarea_field( (string) ai_fr_post_raw( 'exclude_url_patterns', '' ) );
        $options['exclude_noindex']      = ai_fr_post_bool( 'exclude_noindex' ) ? '1' : '';
        $options['exclude_password']     = ai_fr_post_bool( 'exclude_password' ) ? '1' : '';
        $options['static_md_files']      = ai_fr_post_bool( 'static_md_files' ) ? '1' : '';
        $options['auto_regenerate']      = ai_fr_post_bool( 'auto_regenerate' ) ? '1' : '';
        if ( ! empty( $options['auto_regenerate'] ) ) {
            // La rigenerazione a intervallo richiede i file statici attivi.
            $options['static_md_files'] = '1';
        }
        $options['regenerate_interval']  = max( 1, ai_fr_post_int( 'regenerate_interval', 24 ) );
        $options['regenerate_batch_size'] = min( 1000, max( 10, ai_fr_post_int( 'regenerate_batch_size', 100 ) ) );
        $options['regenerate_on_save']   = ai_fr_post_bool( 'regenerate_on_save' ) ? '1' : '';
        $options['regenerate_on_change'] = ai_fr_post_bool( 'regenerate_on_change' ) ? '1' : '';
        $options['onboarding_done']      = ai_fr_post_bool( 'onboarding_done' ) ? '1' : '';
        $options['ui_version']           = 'hub-v2';
        $options['notify_admin_notice']  = ai_fr_post_bool( 'notify_admin_notice' ) ? '1' : '';
        $options['notify_email']         = ai_fr_post_bool( 'notify_email' ) ? '1' : '';
        $options['notify_email_to']      = sanitize_email( (string) ai_fr_post_raw( 'notify_email_to', '' ) );
        $options['schema_enabled']       = ai_fr_post_bool( 'schema_enabled' ) ? '1' : '';
        if ( ai_fr_is_breakdance_active() ) {
            $options['schema_breakdance_faq_enabled'] = ai_fr_post_bool( 'schema_breakdance_faq_enabled' ) ? '1' : '';
        }
        $schema_mode = ai_fr_post_key( 'schema_mode', 'auto' );
        $options['schema_mode'] = in_array( $schema_mode, [ 'auto', 'standalone', 'extend_yoast', 'extend_rank_math' ], true ) ? $schema_mode : 'auto';
        $schema_creator_type = (string) ai_fr_post_raw( 'schema_creator_type', 'Organization' );
        $options['schema_creator_type'] = in_array( $schema_creator_type, [ 'Person', 'Organization' ], true ) ? $schema_creator_type : 'Organization';
        $options['schema_creator_name'] = ai_fr_post_text( 'schema_creator_name', '' );
        $options['schema_creator_url'] = esc_url_raw( (string) ai_fr_post_raw( 'schema_creator_url', '' ) );
        $schema_entity_type = (string) ai_fr_post_raw( 'schema_entity_type', 'Person' );
        $options['schema_entity_type'] = in_array( $schema_entity_type, [ 'Person', 'Organization' ], true ) ? $schema_entity_type : 'Person';
        $options['schema_name'] = ai_fr_post_text( 'schema_name', '' );
        $options['schema_alternate_name'] = ai_fr_post_text( 'schema_alternate_name', '' );
        $options['schema_description'] = sanitize_textarea_field( (string) ai_fr_post_raw( 'schema_description', '' ) );
        $options['schema_disambiguating_description'] = ai_fr_post_text( 'schema_disambiguating_description', '' );
        $options['schema_job_title'] = ai_fr_post_text( 'schema_job_title', '' );
        $options['schema_additional_type'] = ai_fr_post_text( 'schema_additional_type', '' );
        $options['schema_types'] = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', ai_fr_post_array( 'schema_types' ) ) ) ) );
        $options['schema_slogan'] = ai_fr_post_text( 'schema_slogan', '' );
        $options['schema_founding_date'] = ai_fr_post_text( 'schema_founding_date', '' );
        $options['schema_legal_name'] = ai_fr_post_text( 'schema_legal_name', '' );
        $options['schema_vat_id'] = ai_fr_post_text( 'schema_vat_id', '' );
        $options['schema_tax_id'] = ai_fr_post_text( 'schema_tax_id', '' );
        $options['schema_lei_code'] = ai_fr_post_text( 'schema_lei_code', '' );
        $options['schema_ticker_symbol'] = ai_fr_post_text( 'schema_ticker_symbol', '' );
        $options['schema_logo_id'] = max( 0, ai_fr_post_int( 'schema_logo_id', 0 ) );
        $options['schema_street_address'] = ai_fr_post_text( 'schema_street_address', '' );
        $options['schema_postal_code'] = ai_fr_post_text( 'schema_postal_code', '' );
        $options['schema_address_locality'] = ai_fr_post_text( 'schema_address_locality', '' );
        $options['schema_address_region'] = ai_fr_post_text( 'schema_address_region', '' );
        $options['schema_address_country'] = ai_fr_post_text( 'schema_address_country', '' );
        $options['schema_contact_type'] = ai_fr_post_text( 'schema_contact_type', '' );
        $options['schema_contact_email'] = sanitize_email( (string) ai_fr_post_raw( 'schema_contact_email', '' ) );
        $options['schema_contact_languages'] = sanitize_textarea_field( (string) ai_fr_post_raw( 'schema_contact_languages', '' ) );
        $options['schema_contacts'] = ai_fr_admin_sanitize_schema_rows(
            ai_fr_post_array( 'schema_contacts' ),
            [ 'contactType' => 'text', 'telephone' => 'text', 'email' => 'email', 'availableLanguage' => 'textarea', 'hoursAvailable' => 'text' ]
        );
        $options['schema_opening_hours'] = ai_fr_admin_sanitize_schema_rows(
            ai_fr_post_array( 'schema_opening_hours' ),
            [ 'dayOfWeek' => 'text', 'opens' => 'text', 'closes' => 'text', 'validFrom' => 'text', 'validThrough' => 'text' ]
        );
        $place_type = ai_fr_post_text( 'schema_place_type', 'Place' );
        $options['schema_place_type'] = preg_match( '/^[A-Z][A-Za-z0-9]*$/', $place_type ) ? $place_type : 'Place';
        $options['schema_place_name'] = ai_fr_post_text( 'schema_place_name', '' );
        $options['schema_latitude'] = ai_fr_post_text( 'schema_latitude', '' );
        $options['schema_longitude'] = ai_fr_post_text( 'schema_longitude', '' );
        $options['schema_public_transportation_access'] = sanitize_textarea_field( (string) ai_fr_post_raw( 'schema_public_transportation_access', '' ) );
        $options['schema_certifications'] = ai_fr_admin_sanitize_schema_rows(
            ai_fr_post_array( 'schema_certifications' ),
            [ 'name' => 'text', 'identifier' => 'text', 'issuedBy' => 'text', 'url' => 'url' ]
        );
        $options['schema_identifiers'] = ai_fr_admin_sanitize_schema_rows(
            ai_fr_post_array( 'schema_identifiers' ),
            [ 'propertyID' => 'text', 'value' => 'text' ]
        );
        $options['schema_founders'] = sanitize_textarea_field( (string) ai_fr_post_raw( 'schema_founders', '' ) );
        $options['schema_area_served'] = sanitize_textarea_field( (string) ai_fr_post_raw( 'schema_area_served', '' ) );
        $options['schema_services'] = ai_fr_admin_sanitize_schema_services( ai_fr_post_array( 'schema_services' ) );
        $options['schema_offer_sources'] = array_values( array_filter( array_map( 'sanitize_text_field', ai_fr_post_array( 'schema_offer_sources' ) ) ) );
        $options['schema_offer_catalog'] = '';
        $options['schema_image_id'] = max( 0, ai_fr_post_int( 'schema_image_id', 0 ) );
        $options['schema_same_as'] = sanitize_textarea_field( (string) ai_fr_post_raw( 'schema_same_as', '' ) );
        $options['schema_knows_about'] = sanitize_textarea_field( (string) ai_fr_post_raw( 'schema_knows_about', '' ) );
        $options['schema_knows_language'] = sanitize_textarea_field( (string) ai_fr_post_raw( 'schema_knows_language', '' ) );
        $options['schema_license'] = esc_url_raw( (string) ai_fr_post_raw( 'schema_license', '' ) );
        $options['schema_profile_page_id'] = max( 0, ai_fr_post_int( 'schema_profile_page_id', 0 ) );

        update_option( 'ai_fr_options', $options );
        delete_option( 'ai_fr_regeneration_cursor' );
        update_option( 'ai_fr_onboarding_done', $options['onboarding_done'], false );
        update_option( 'ai_fr_ui_version', 'hub-v2', false );

        AiFrVersioning::pruneObsoleteVersions();
        ai_fr_schedule_cron();
        ai_fr_add_event( 'settings_saved', [ 'source' => 'admin_page' ] );
        $settings_saved = true;
    }

    $all_categories  = get_categories( [ 'hide_empty' => false ] );
    $all_tags        = get_tags( [ 'hide_empty' => false ] );
    $all_templates   = wp_get_theme()->get_page_templates();
    $all_cpt         = get_post_types( [ 'public' => true, '_builtin' => false ], 'objects' );
    $overview        = ai_fr_get_overview_stats();
    $version_stats   = AiFrVersioning::getStats();
    $last_regen      = get_option( 'ai_fr_last_regeneration', [] );
    $next_cron       = wp_next_scheduled( 'ai_fr_cron_regenerate' );
    $onboarding_done = ! empty( get_option( 'ai_fr_onboarding_done', $options['onboarding_done'] ?? '' ) );
    $services_md_url = ai_fr_permalink_to_md( home_url( '/servizi/' ) );
    $schema_provider = function_exists( 'ai_fr_schema_detect_provider' ) ? ai_fr_schema_detect_provider() : 'none';
    $schema_mode     = function_exists( 'ai_fr_schema_output_mode' ) ? ai_fr_schema_output_mode() : 'standalone';
    $schema_validator_url = 'https://validator.schema.org/#url=' . rawurlencode( home_url( '/' ) );
    $schema_image_url = '';
    if ( ! empty( $options['schema_image_id'] ) ) {
        $schema_image_src = wp_get_attachment_image_src( intval( $options['schema_image_id'] ), 'thumbnail' );
        $schema_image_url = is_array( $schema_image_src ) ? (string) $schema_image_src[0] : '';
    }
    $schema_logo_url = '';
    if ( ! empty( $options['schema_logo_id'] ) ) {
        $schema_logo_src = wp_get_attachment_image_src( intval( $options['schema_logo_id'] ), 'thumbnail' );
        $schema_logo_url = is_array( $schema_logo_src ) ? (string) $schema_logo_src[0] : '';
    }
    $schema_services = isset( $options['schema_services'] ) && is_array( $options['schema_services'] )
        ? ai_fr_schema_normalize_service_inputs( $options['schema_services'] )
        : [];
    if ( empty( $schema_services ) && function_exists( 'ai_fr_schema_parse_legacy_offer_catalog' ) ) {
        $legacy_services = ai_fr_schema_parse_legacy_offer_catalog( (string) ( $options['schema_offer_catalog'] ?? '' ) );
        $schema_services = is_array( $legacy_services['services'] ?? null ) ? $legacy_services['services'] : [];
    }
    $schema_types = ! empty( $options['schema_types'] ) && is_array( $options['schema_types'] ) ? $options['schema_types'] : [];
    if ( empty( $schema_types ) && ! empty( $options['schema_additional_type'] ) ) {
        $schema_types[] = $options['schema_additional_type'];
    }
    $schema_contacts = ! empty( $options['schema_contacts'] ) && is_array( $options['schema_contacts'] ) ? $options['schema_contacts'] : [];
    $schema_opening_hours = ! empty( $options['schema_opening_hours'] ) && is_array( $options['schema_opening_hours'] ) ? $options['schema_opening_hours'] : [];
    $schema_certifications = ! empty( $options['schema_certifications'] ) && is_array( $options['schema_certifications'] ) ? $options['schema_certifications'] : [];
    $schema_identifiers = ! empty( $options['schema_identifiers'] ) && is_array( $options['schema_identifiers'] ) ? $options['schema_identifiers'] : [];
    $schema_offer_sources = ! empty( $options['schema_offer_sources'] ) && is_array( $options['schema_offer_sources'] ) ? $options['schema_offer_sources'] : [];
    $wizard_post_types = get_post_types( [ 'public' => true ], 'objects' );
    unset( $wizard_post_types['attachment'] );
    $wizard_selected_types = array_values(
        array_filter(
            array_merge(
                ! empty( $options['include_pages'] ) ? [ 'page' ] : [],
                ! empty( $options['include_posts'] ) ? [ 'post' ] : [],
                ! empty( $options['include_products'] ) ? [ 'product' ] : [],
                (array) $options['include_cpt']
            )
        )
    );

    ?>
    <div class="wrap ai-fr-wrap">
        <div class="ai-fr-header">
            <div>
                <p class="ai-fr-eyebrow"><?php esc_html_e( 'AI Friendly', 'ai-friendly' ); ?></p>
                <h1><?php esc_html_e( 'AI Content Hub', 'ai-friendly' ); ?> <small class="ai-fr-version">v<?php echo esc_html( AI_FR_VERSION ); ?></small></h1>
            </div>
            <button type="button" class="button ai-fr-header-wizard<?php echo $onboarding_done ? '' : ' is-hidden'; ?>" id="ai-fr-reopen-wizard"><?php esc_html_e( 'Riapri configurazione guidata', 'ai-friendly' ); ?></button>
            <?php if ( $settings_saved ) : ?>
                <div class="ai-fr-save-notice" role="status" aria-live="polite">
                    <span class="ai-fr-save-notice-icon" aria-hidden="true"></span>
                    <span><?php esc_html_e( 'Impostazioni salvate', 'ai-friendly' ); ?></span>
                    <button type="button" class="ai-fr-save-notice-dismiss" aria-label="<?php esc_attr_e( 'Nascondi notifica', 'ai-friendly' ); ?>" onclick="this.closest('.ai-fr-save-notice').hidden = true;"><?php esc_html_e( '×', 'ai-friendly' ); ?></button>
                </div>
            <?php endif; ?>
        </div>

        <div class="ai-fr-onboarding<?php echo $onboarding_done ? ' is-hidden' : ''; ?>" id="ai-fr-onboarding" data-initial-step="1">
            <div class="ai-fr-wizard-topline">
                <div>
                    <p class="ai-fr-eyebrow"><?php esc_html_e( 'Configurazione guidata', 'ai-friendly' ); ?></p>
                    <h2><?php esc_html_e( 'Prepariamo l’Hub sui dati reali del sito.', 'ai-friendly' ); ?></h2>
                </div>
                <button type="button" class="button-link" id="ai-fr-onboarding-dismiss"><?php esc_html_e( 'Configura più tardi', 'ai-friendly' ); ?></button>
            </div>
            <ol class="ai-fr-stepper" aria-label="<?php esc_attr_e( 'Avanzamento configurazione', 'ai-friendly' ); ?>">
                <?php
                $wizard_steps = [
                    __( 'Analisi', 'ai-friendly' ),
                    __( 'Contenuti', 'ai-friendly' ),
                    __( 'Markdown', 'ai-friendly' ),
                    __( 'Schema', 'ai-friendly' ),
                    __( 'Riepilogo', 'ai-friendly' ),
                ];
                foreach ( $wizard_steps as $index => $label ) :
                ?>
                    <li data-wizard-marker="<?php echo esc_attr( $index + 1 ); ?>"><span><?php echo esc_html( $index + 1 ); ?></span><?php echo esc_html( $label ); ?></li>
                <?php endforeach; ?>
            </ol>

            <div class="ai-fr-wizard-panel" data-wizard-step="1">
                <div class="ai-fr-section-heading"><span>01</span><div><h3><?php esc_html_e( 'Analisi del sito', 'ai-friendly' ); ?></h3><p><?php esc_html_e( 'Questi dati sono stati rilevati da WordPress e dai plugin attivi.', 'ai-friendly' ); ?></p></div></div>
                <div class="ai-fr-analysis-grid">
                    <div><small><?php esc_html_e( 'Sito', 'ai-friendly' ); ?></small><strong><?php echo esc_html( get_bloginfo( 'name' ) ); ?></strong><code><?php echo esc_html( home_url( '/' ) ); ?></code></div>
                    <div><small><?php esc_html_e( 'Provider SEO', 'ai-friendly' ); ?></small><strong><?php echo esc_html( $schema_provider === 'none' ? __( 'Nessuno rilevato', 'ai-friendly' ) : $schema_provider ); ?></strong></div>
                    <div><small><?php esc_html_e( 'WooCommerce', 'ai-friendly' ); ?></small><strong><?php echo class_exists( 'WooCommerce' ) ? esc_html__( 'Attivo', 'ai-friendly' ) : esc_html__( 'Non rilevato', 'ai-friendly' ); ?></strong></div>
                    <div><small><?php esc_html_e( 'Breakdance', 'ai-friendly' ); ?></small><strong><?php echo ai_fr_is_breakdance_active() ? esc_html__( 'Attivo', 'ai-friendly' ) : esc_html__( 'Non rilevato', 'ai-friendly' ); ?></strong></div>
                </div>
                <div class="ai-fr-detected-types">
                    <?php foreach ( $wizard_post_types as $post_type ) : $counts = wp_count_posts( $post_type->name ); ?>
                        <span><strong><?php echo esc_html( $post_type->labels->name ); ?></strong> <?php
                        $published_count = intval( $counts->publish ?? 0 );
                        printf(
                            /* translators: %s: formatted number of published items. */
                            esc_html( _n( '%s pubblicato', '%s pubblicati', $published_count, 'ai-friendly' ) ),
                            esc_html( number_format_i18n( $published_count ) )
                        );
                        ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="ai-fr-wizard-panel" data-wizard-step="2" hidden>
                <div class="ai-fr-section-heading"><span>02</span><div><h3><?php esc_html_e( 'Contenuti da esporre', 'ai-friendly' ); ?></h3><p><?php esc_html_e( 'Scegli solo i tipi destinati alla consultazione pubblica.', 'ai-friendly' ); ?></p></div></div>
                <div class="ai-fr-choice-grid">
                    <?php foreach ( $wizard_post_types as $post_type ) :
                        $is_first_setup = ! $onboarding_done;
                        $selected = $is_first_setup ? in_array( $post_type->name, [ 'page', 'post' ], true ) : in_array( $post_type->name, $wizard_selected_types, true );
                        ?>
                        <label class="ai-fr-choice"><input type="checkbox" data-wizard-field="content_types" value="<?php echo esc_attr( $post_type->name ); ?>" <?php checked( $selected ); ?>><span><strong><?php echo esc_html( $post_type->labels->name ); ?></strong><small><?php echo esc_html( $post_type->name ); ?></small></span></label>
                    <?php endforeach; ?>
                </div>
                <div class="ai-fr-wizard-acf">
                    <label class="ai-fr-setting">
                        <input type="checkbox" data-wizard-field="include_acf_fields" <?php checked( $options['include_acf_fields'] ); ?>>
                        <span>
                            <strong><?php esc_html_e( 'Includi i campi ACF nell’output Markdown', 'ai-friendly' ); ?></strong>
                            <small><?php esc_html_e( 'Disattivato per impostazione predefinita: i valori estratti diventano pubblici negli endpoint .md.', 'ai-friendly' ); ?></small>
                        </span>
                    </label>
                    <p class="description"><strong><?php esc_html_e( 'Attenzione:', 'ai-friendly' ); ?></strong> <?php esc_html_e( 'attiva questa opzione solo se tutti i campi testuali ACF dei contenuti inclusi sono destinati alla pubblicazione.', 'ai-friendly' ); ?></p>
                </div>
                <div class="ai-fr-inline-options">
                    <label><input type="checkbox" data-wizard-field="exclude_noindex" <?php checked( $options['exclude_noindex'] ); ?>> <?php esc_html_e( 'Escludi contenuti noindex', 'ai-friendly' ); ?></label>
                    <label><input type="checkbox" data-wizard-field="exclude_password" <?php checked( $options['exclude_password'] ); ?>> <?php esc_html_e( 'Escludi contenuti protetti da password', 'ai-friendly' ); ?></label>
                </div>
            </div>

            <div class="ai-fr-wizard-panel" data-wizard-step="3" hidden>
                <div class="ai-fr-section-heading"><span>03</span><div><h3><?php esc_html_e( 'Markdown e automazione', 'ai-friendly' ); ?></h3><p><?php esc_html_e( 'Le impostazioni proposte mantengono l’output aggiornato ogni 24 ore.', 'ai-friendly' ); ?></p></div></div>
                <div class="ai-fr-settings-grid">
                    <label class="ai-fr-setting"><input type="checkbox" data-wizard-field="llms_include_auto" <?php checked( $onboarding_done ? $options['llms_include_auto'] : '1' ); ?>><span><strong><?php esc_html_e( 'Lista automatica', 'ai-friendly' ); ?></strong><small><?php esc_html_e( 'Aggiunge i contenuti selezionati a llms.txt.', 'ai-friendly' ); ?></small></span></label>
                    <label class="ai-fr-setting"><input type="checkbox" data-wizard-field="static_md_files" <?php checked( $onboarding_done ? $options['static_md_files'] : '1' ); ?>><span><strong><?php esc_html_e( 'File Markdown statici', 'ai-friendly' ); ?></strong><small><?php esc_html_e( 'Genera e serve copie .md persistenti.', 'ai-friendly' ); ?></small></span></label>
                    <label class="ai-fr-setting"><input type="checkbox" data-wizard-field="auto_regenerate" <?php checked( $onboarding_done ? $options['auto_regenerate'] : '1' ); ?>><span><strong><?php esc_html_e( 'Rigenerazione automatica', 'ai-friendly' ); ?></strong><small><?php esc_html_e( 'Esegue il processo tramite cron WordPress.', 'ai-friendly' ); ?></small></span></label>
                    <label class="ai-fr-setting"><input type="checkbox" data-wizard-field="regenerate_on_save" <?php checked( $options['regenerate_on_save'] ); ?>><span><strong><?php esc_html_e( 'Trigger su modifica', 'ai-friendly' ); ?></strong><small><?php esc_html_e( 'Avvia la rigenerazione quando salvi un contenuto.', 'ai-friendly' ); ?></small></span></label>
                </div>
                <div class="ai-fr-number-fields">
                    <label><?php esc_html_e( 'Intervallo (ore)', 'ai-friendly' ); ?><input type="number" min="1" max="168" data-wizard-field="regenerate_interval" value="<?php echo esc_attr( $onboarding_done ? $options['regenerate_interval'] : 24 ); ?>"></label>
                    <label><?php esc_html_e( 'Contenuti per esecuzione', 'ai-friendly' ); ?><input type="number" min="10" max="1000" data-wizard-field="regenerate_batch_size" value="<?php echo esc_attr( $onboarding_done ? $options['regenerate_batch_size'] : 100 ); ?>"></label>
                    <label class="ai-fr-compact-check"><input type="checkbox" data-wizard-field="regenerate_on_change" <?php checked( $options['regenerate_on_change'] ); ?>> <?php esc_html_e( 'Solo se il checksum cambia', 'ai-friendly' ); ?></label>
                </div>
            </div>

            <div class="ai-fr-wizard-panel" data-wizard-step="4" hidden>
                <div class="ai-fr-section-heading"><span>04</span><div><h3><?php esc_html_e( 'Semantic Schema', 'ai-friendly' ); ?></h3><p><?php esc_html_e( 'Puoi attivarlo ora e completare i dettagli nella sezione Schema.', 'ai-friendly' ); ?></p></div></div>
                <label class="ai-fr-setting ai-fr-setting-primary"><input type="checkbox" data-wizard-field="schema_enabled" <?php checked( $options['schema_enabled'] ); ?>><span><strong><?php esc_html_e( 'Abilita Semantic Schema', 'ai-friendly' ); ?></strong><small><?php esc_html_e( 'Modalità automatica, compatibile con il provider SEO rilevato.', 'ai-friendly' ); ?></small></span></label>
                <div class="ai-fr-number-fields ai-fr-schema-setup-fields">
                    <label><?php esc_html_e( 'Entità principale', 'ai-friendly' ); ?><select data-wizard-field="schema_entity_type"><option value="Organization" <?php selected( $options['schema_entity_type'], 'Organization' ); ?>><?php esc_html_e( 'Organization', 'ai-friendly' ); ?></option><option value="Person" <?php selected( $options['schema_entity_type'], 'Person' ); ?>><?php esc_html_e( 'Person', 'ai-friendly' ); ?></option></select></label>
                    <label><?php esc_html_e( 'Nome', 'ai-friendly' ); ?><input type="text" data-wizard-field="schema_name" value="<?php echo esc_attr( $options['schema_name'] ?: get_bloginfo( 'name' ) ); ?>"></label>
                    <label class="ai-fr-field-wide"><?php esc_html_e( 'sameAs, un URL per riga', 'ai-friendly' ); ?><textarea rows="4" data-wizard-field="schema_same_as"><?php echo esc_textarea( $options['schema_same_as'] ); ?></textarea></label>
                    <?php if ( ai_fr_is_breakdance_active() ) : ?><label class="ai-fr-compact-check"><input type="checkbox" data-wizard-field="schema_breakdance_faq_enabled" <?php checked( $options['schema_breakdance_faq_enabled'] ); ?>> <?php esc_html_e( 'Rileva automaticamente le FAQ di Breakdance', 'ai-friendly' ); ?></label><?php endif; ?>
                </div>
            </div>

            <div class="ai-fr-wizard-panel" data-wizard-step="5" hidden>
                <div class="ai-fr-section-heading"><span>05</span><div><h3><?php esc_html_e( 'Riepilogo', 'ai-friendly' ); ?></h3><p><?php esc_html_e( 'Controlla le scelte prima del salvataggio e della prima generazione.', 'ai-friendly' ); ?></p></div></div>
                <div id="ai-fr-wizard-summary" class="ai-fr-review-grid"></div>
                <div id="ai-fr-wizard-result" class="ai-fr-wizard-result" role="status" aria-live="polite"></div>
            </div>

            <div class="ai-fr-wizard-actions">
                <button type="button" class="button" id="ai-fr-wizard-prev" hidden><?php esc_html_e( 'Indietro', 'ai-friendly' ); ?></button>
                <span class="ai-fr-wizard-spacer"></span>
                <button type="button" class="button button-primary" id="ai-fr-wizard-next"><?php esc_html_e( 'Continua', 'ai-friendly' ); ?></button>
                <button type="button" class="button button-primary" id="ai-fr-wizard-complete" hidden><?php esc_html_e( 'Salva e genera', 'ai-friendly' ); ?></button>
                <button type="button" class="button" id="ai-fr-wizard-retry" hidden><?php esc_html_e( 'Riprova generazione', 'ai-friendly' ); ?></button>
            </div>
        </div>

        <form method="post" id="ai-fr-main-form" class="<?php echo $onboarding_done ? '' : 'is-hidden'; ?>">
            <?php wp_nonce_field( 'ai_fr_options_nonce' ); ?>
            <input type="hidden" name="onboarding_done" id="onboarding_done" value="<?php echo $onboarding_done ? '1' : ''; ?>">
            <input type="hidden" id="ai-fr-wizard-step" value="1">

            <nav class="ai-fr-nav" role="tablist" aria-label="<?php esc_attr_e( 'Sezioni AI Content Hub', 'ai-friendly' ); ?>">
                <button type="button" class="ai-fr-nav-item is-active" data-section="overview"><?php esc_html_e( 'Overview', 'ai-friendly' ); ?></button>
                <button type="button" class="ai-fr-nav-item" data-section="content"><?php esc_html_e( 'Content', 'ai-friendly' ); ?></button>
                <button type="button" class="ai-fr-nav-item" data-section="rules"><?php esc_html_e( 'Rules', 'ai-friendly' ); ?></button>
                <button type="button" class="ai-fr-nav-item" data-section="schema"><?php esc_html_e( 'Schema', 'ai-friendly' ); ?></button>
                <button type="button" class="ai-fr-nav-item" data-section="automation"><?php esc_html_e( 'Automation', 'ai-friendly' ); ?></button>
            </nav>

            <section id="ai-fr-section-overview" class="ai-fr-section is-active">
                <div class="ai-fr-section-heading"><span>01</span><div><h3><?php esc_html_e( 'Overview', 'ai-friendly' ); ?></h3><p><?php esc_html_e( 'Stato dell’output, diagnostica e azioni principali.', 'ai-friendly' ); ?></p></div></div>
                <div class="ai-fr-card-grid">
                    <article class="ai-fr-card">
                        <h3><?php esc_html_e( 'Stato llms.txt', 'ai-friendly' ); ?></h3>
                        <p><code><?php echo esc_html( $overview['llms']['url'] ); ?></code></p>
                        <p><?php esc_html_e( 'Caratteri:', 'ai-friendly' ); ?> <strong id="ai-fr-llms-chars"><?php echo intval( $overview['llms']['chars'] ); ?></strong></p>
                        <p><?php esc_html_e( 'Righe:', 'ai-friendly' ); ?> <strong id="ai-fr-llms-lines"><?php echo intval( $overview['llms']['lines'] ); ?></strong></p>
                        <p><?php esc_html_e( 'Ultima rigenerazione:', 'ai-friendly' ); ?> <strong id="ai-fr-last-regen"><?php echo esc_html( $overview['llms']['last_regen_time'] ?: __( 'n/d', 'ai-friendly' ) ); ?></strong></p>
                        <a href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>" target="_blank" class="button button-secondary"><?php esc_html_e( 'Anteprima llms.txt', 'ai-friendly' ); ?></a>
                    </article>

                    <article class="ai-fr-card">
                        <h3><?php esc_html_e( 'Markdown Pack', 'ai-friendly' ); ?></h3>
                        <p><?php esc_html_e( 'Static mode:', 'ai-friendly' ); ?>
                            <span class="ai-fr-badge <?php echo ! empty( $overview['markdown']['static_enabled'] ) ? 'is-ok' : 'is-muted'; ?>">
                                <?php echo ! empty( $overview['markdown']['static_enabled'] ) ? esc_html__( 'attivo', 'ai-friendly' ) : esc_html__( 'disattivo', 'ai-friendly' ); ?>
                            </span>
                        </p>
                        <p><?php esc_html_e( 'File:', 'ai-friendly' ); ?> <strong><?php echo intval( $overview['markdown']['count'] ); ?></strong></p>
                        <p><?php esc_html_e( 'Spazio:', 'ai-friendly' ); ?> <strong><?php echo esc_html( size_format( intval( $overview['markdown']['size'] ) ) ); ?></strong></p>
                        <button type="button" id="ai-fr-regenerate-overview" class="button button-secondary"><?php esc_html_e( 'Rigenera llms/MD', 'ai-friendly' ); ?></button>
                    </article>

                    <article class="ai-fr-card">
                        <h3><?php esc_html_e( 'Avvisi rapidi', 'ai-friendly' ); ?></h3>
                        <ul id="ai-fr-overview-warnings" class="ai-fr-list">
                            <?php foreach ( $overview['diagnostics']['warnings'] as $warning ) : ?>
                                <li><?php echo esc_html( $warning['message'] ?? '' ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="description" id="ai-fr-sr-info"></p>
                        <button type="button" id="ai-fr-refresh-diagnostics" class="button"><?php esc_html_e( 'Aggiorna diagnostica', 'ai-friendly' ); ?></button>
                    </article>

                    <article class="ai-fr-card">
                        <h3><?php esc_html_e( 'Semantic Schema', 'ai-friendly' ); ?></h3>
                        <p><?php esc_html_e( 'Stato:', 'ai-friendly' ); ?>
                            <span class="ai-fr-badge <?php echo ! empty( $options['schema_enabled'] ) ? 'is-ok' : 'is-muted'; ?>">
                                <?php echo ! empty( $options['schema_enabled'] ) ? esc_html__( 'attivo', 'ai-friendly' ) : esc_html__( 'disattivo', 'ai-friendly' ); ?>
                            </span>
                        </p>
                        <p><?php esc_html_e( 'Provider SEO:', 'ai-friendly' ); ?> <strong><?php echo esc_html( $schema_provider ); ?></strong></p>
                        <p><?php esc_html_e( 'Output:', 'ai-friendly' ); ?> <strong><?php echo esc_html( $schema_mode ); ?></strong></p>
                        <button type="button" class="button button-secondary" data-section-jump="schema"><?php esc_html_e( 'Configura Schema', 'ai-friendly' ); ?></button>
                    </article>
                </div>

                <div class="ai-fr-quick-actions">
                    <button type="button" class="button button-primary" data-section-jump="content"><?php esc_html_e( 'Modifica llms.txt', 'ai-friendly' ); ?></button>
                    <button type="button" class="button" id="ai-fr-refresh-overview"><?php esc_html_e( 'Aggiorna Overview', 'ai-friendly' ); ?></button>
                    <button type="button" class="button" id="ai-fr-run-now"><?php esc_html_e( 'Rigenera adesso', 'ai-friendly' ); ?></button>
                </div>
            </section>

            <section id="ai-fr-section-content" class="ai-fr-section">
                <div class="ai-fr-section-heading"><span>02</span><div><h3><?php esc_html_e( 'Content', 'ai-friendly' ); ?></h3><p><?php esc_html_e( 'Modifica llms.txt, controlla l’anteprima e gestisci i contenuti esposti.', 'ai-friendly' ); ?></p></div></div>
                <div class="ai-fr-editor-layout">
                    <aside class="ai-fr-panel ai-fr-panel-left">
                        <h3><?php esc_html_e( 'Struttura documento', 'ai-friendly' ); ?></h3>
                        <ul id="ai-fr-toc" class="ai-fr-list"></ul>
                    </aside>

                    <div class="ai-fr-panel ai-fr-panel-center">
                        <h3><?php esc_html_e( 'Editor llms.txt', 'ai-friendly' ); ?></h3>
                        <textarea
                            name="llms_content"
                            id="llms_content"
                            rows="16"
                            class="large-text code"
                            placeholder="<?php esc_attr_e( '# Nome sito
> Sintesi del sito', 'ai-friendly' ); ?>"
                        ><?php echo esc_textarea( $options['llms_content'] ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Contenuto custom Markdown. Se vuoto, il plugin genera in automatico.', 'ai-friendly' ); ?></p>
                        <label>
                            <input type="checkbox" name="llms_include_auto" value="1" <?php checked( $options['llms_include_auto'] ); ?>>
                            <?php esc_html_e( 'Aggiungi lista automatica dopo il contenuto custom', 'ai-friendly' ); ?>
                        </label>
                        <div class="ai-fr-preview-split">
                            <div class="ai-fr-preview-head"><?php esc_html_e( 'Anteprima live', 'ai-friendly' ); ?></div>
                            <div id="ai-fr-preview-pane"></div>
                        </div>
                    </div>

                    <aside class="ai-fr-panel ai-fr-panel-right">
                        <h3><?php esc_html_e( 'Helper', 'ai-friendly' ); ?></h3>
                        <p><?php esc_html_e( 'Token stimati:', 'ai-friendly' ); ?> <strong id="ai-fr-token-count">0</strong></p>
                        <p><?php esc_html_e( 'Validazione link:', 'ai-friendly' ); ?> <strong id="ai-fr-link-validation"><?php esc_html_e( '0 issue', 'ai-friendly' ); ?></strong></p>
                        <ul class="ai-fr-list">
                            <li><button type="button" class="button-link ai-fr-insert-snippet" data-snippet="# Chi siamo"><?php esc_html_e( '+ Heading', 'ai-friendly' ); ?></button></li>
                            <li><button type="button" class="button-link ai-fr-insert-snippet" data-snippet="<?php echo esc_attr( '- [Servizi](' . $services_md_url . ')' ); ?>"><?php esc_html_e( '+ Link sezione', 'ai-friendly' ); ?></button></li>
                            <li><button type="button" class="button-link ai-fr-insert-snippet" data-snippet="> <?php esc_html_e( 'Sintesi per AI in 1-2 frasi.">+ Sintesi', 'ai-friendly' ); ?></button></li>
                        </ul>
                        <p><?php esc_html_e( 'Variabili utili:', 'ai-friendly' ); ?></p>
                        <ul class="ai-fr-list ai-fr-small">
                            <li><code><?php echo esc_html( get_bloginfo( 'name' ) ); ?></code></li>
                            <li><code><?php echo esc_html( home_url() ); ?></code></li>
                            <li><code><?php echo esc_html( get_locale() ); ?></code></li>
                        </ul>
                        <button type="button" id="ai-fr-run-simulation" class="button"><?php esc_html_e( 'AI Simulation', 'ai-friendly' ); ?></button>
                        <div id="ai-fr-simulation-result" class="ai-fr-simulation"></div>
                    </aside>
                </div>

                <div class="ai-fr-history">
                    <h3><?php esc_html_e( 'Versioning llms', 'ai-friendly' ); ?></h3>
                    <div class="ai-fr-history-actions">
                        <button type="button" id="ai-fr-create-snapshot" class="button"><?php esc_html_e( 'Crea snapshot', 'ai-friendly' ); ?></button>
                        <button type="button" id="ai-fr-load-snapshots" class="button button-secondary"><?php esc_html_e( 'Aggiorna lista', 'ai-friendly' ); ?></button>
                        <button type="button" id="ai-fr-compare-snapshots" class="button"><?php esc_html_e( 'Confronta selezionati', 'ai-friendly' ); ?></button>
                    </div>
                    <ul id="ai-fr-snapshot-list" class="ai-fr-list"></ul>
                    <div class="ai-fr-diff-wrap">
                        <div class="ai-fr-diff-summary" id="ai-fr-diff-summary"></div>
                        <div class="ai-fr-diff-columns">
                            <div>
                                <h4><?php esc_html_e( 'Diff affiancato', 'ai-friendly' ); ?></h4>
                                <table class="widefat striped ai-fr-diff-table">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e( 'Sinistra', 'ai-friendly' ); ?></th>
                                            <th><?php esc_html_e( 'Destra', 'ai-friendly' ); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="ai-fr-diff-rows"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ai-fr-content-manager">
                    <h3><?php esc_html_e( 'Pagine del sito', 'ai-friendly' ); ?></h3>
                    <div class="ai-fr-filters">
                        <input type="text" id="ai-fr-content-search" placeholder="<?php esc_attr_e( 'Cerca titolo', 'ai-friendly' ); ?>">
                        <select id="ai-fr-content-type">
                            <option value="all"><?php esc_html_e( 'Tutti i tipi', 'ai-friendly' ); ?></option>
                            <option value="page"><?php esc_html_e( 'Pagine', 'ai-friendly' ); ?></option>
                            <option value="post"><?php esc_html_e( 'Post', 'ai-friendly' ); ?></option>
                            <option value="product"><?php esc_html_e( 'Prodotti', 'ai-friendly' ); ?></option>
                        </select>
                        <select id="ai-fr-content-status">
                            <option value="any"><?php esc_html_e( 'Tutti gli stati', 'ai-friendly' ); ?></option>
                            <option value="publish"><?php esc_html_e( 'Pubblicato', 'ai-friendly' ); ?></option>
                            <option value="draft"><?php esc_html_e( 'Bozza', 'ai-friendly' ); ?></option>
                            <option value="private"><?php esc_html_e( 'Privato', 'ai-friendly' ); ?></option>
                        </select>
                        <button type="button" id="ai-fr-content-apply" class="button"><?php esc_html_e( 'Filtra', 'ai-friendly' ); ?></button>
                    </div>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Inclusa/Esclusa', 'ai-friendly' ); ?></th>
                                <th><?php esc_html_e( 'Titolo', 'ai-friendly' ); ?></th>
                                <th><?php esc_html_e( 'Tipo', 'ai-friendly' ); ?></th>
                                <th><?php esc_html_e( 'Lingua', 'ai-friendly' ); ?></th>
                                <th><?php esc_html_e( 'Stato', 'ai-friendly' ); ?></th>
                                <th><?php esc_html_e( 'Token', 'ai-friendly' ); ?></th>
                                <th><?php esc_html_e( 'Azione', 'ai-friendly' ); ?></th>
                            </tr>
                        </thead>
                        <tbody id="ai-fr-content-tbody"></tbody>
                    </table>
                    <div class="ai-fr-pagination">
                        <button type="button" class="button" id="ai-fr-prev-page"><?php esc_html_e( 'Precedente', 'ai-friendly' ); ?></button>
                        <span id="ai-fr-page-info"><?php esc_html_e( 'Pagina 1', 'ai-friendly' ); ?></span>
                        <button type="button" class="button" id="ai-fr-next-page"><?php esc_html_e( 'Successiva', 'ai-friendly' ); ?></button>
                    </div>
                </div>
            </section>

            <section id="ai-fr-section-rules" class="ai-fr-section">
                <div class="ai-fr-section-heading"><span>03</span><div><h3><?php esc_html_e( 'Filtri & esclusioni', 'ai-friendly' ); ?></h3><p><?php esc_html_e( 'Definisci cosa entra nell’output pubblico e cosa deve restarne fuori.', 'ai-friendly' ); ?></p></div></div>
                <div class="ai-fr-settings-cards">
                    <article class="ai-fr-settings-card">
                        <div class="ai-fr-card-head"><span>01</span><div><h4><?php esc_html_e( 'Tipi di contenuto', 'ai-friendly' ); ?></h4><p><?php esc_html_e( 'Seleziona le raccolte pubbliche da esporre.', 'ai-friendly' ); ?></p></div></div>
                        <div class="ai-fr-option-list">
                            <label>
                                <input type="checkbox" name="include_pages" value="1" <?php checked( $options['include_pages'] ); ?>>
                                <?php esc_html_e( 'Pagine', 'ai-friendly' ); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="include_posts" value="1" <?php checked( $options['include_posts'] ); ?>>
                                <?php esc_html_e( 'Articoli (Post)', 'ai-friendly' ); ?>
                            </label>
                            <?php if ( class_exists( 'WooCommerce' ) ) : ?>
                                <label>
                                    <input type="checkbox" name="include_products" value="1" <?php checked( $options['include_products'] ); ?>>
                                    <?php esc_html_e( 'Prodotti WooCommerce', 'ai-friendly' ); ?>
                                </label>
                            <?php endif; ?>
                            <?php if ( ! empty( $all_cpt ) ) : ?>
                            <?php foreach ( $all_cpt as $cpt ) : ?>
                                <label>
                                    <input type="checkbox" name="include_cpt[]" value="<?php echo esc_attr( $cpt->name ); ?>"
                                        <?php checked( in_array( $cpt->name, (array) $options['include_cpt'], true ) ); ?>>
                                    <?php echo esc_html( $cpt->labels->name ); ?> <code>(<?php echo esc_html( $cpt->name ); ?>)</code>
                                </label>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </article>
                    <article class="ai-fr-settings-card">
                        <div class="ai-fr-card-head"><span>02</span><div><h4><?php esc_html_e( 'Protezioni', 'ai-friendly' ); ?></h4><p><?php esc_html_e( 'Rispetta indicazioni SEO e accessi riservati.', 'ai-friendly' ); ?></p></div></div>
                        <div class="ai-fr-option-list">
                            <label>
                                <input type="checkbox" name="exclude_noindex" value="1" <?php checked( $options['exclude_noindex'] ); ?>>
                                <?php esc_html_e( 'Escludi pagine con meta', 'ai-friendly' ); ?> <code><?php esc_html_e( 'noindex', 'ai-friendly' ); ?></code>
                            </label>
                            <label>
                                <input type="checkbox" name="exclude_password" value="1" <?php checked( $options['exclude_password'] ); ?>>
                                <?php esc_html_e( 'Escludi contenuti protetti da password', 'ai-friendly' ); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="include_acf_fields" value="1" <?php checked( $options['include_acf_fields'] ); ?>>
                                <?php esc_html_e( 'Includi i valori testuali dei campi ACF nell\'output pubblico', 'ai-friendly' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'Disattivato per impostazione predefinita: abilitalo solo se tutti i campi ACF dei contenuti inclusi sono destinati alla pubblicazione.', 'ai-friendly' ); ?></p>
                        </div>
                    </article>
                    <article class="ai-fr-settings-card ai-fr-settings-card-wide">
                        <div class="ai-fr-card-head"><span>03</span><div><h4><?php esc_html_e( 'Esclusioni granulari', 'ai-friendly' ); ?></h4><p><?php esc_html_e( 'Categorie, tag, template e pattern URL.', 'ai-friendly' ); ?></p></div></div>
                        <div class="ai-fr-field-grid">
                            <?php if ( ! empty( $all_categories ) ) : ?><label class="ai-fr-field"><span><?php esc_html_e( 'Categorie', 'ai-friendly' ); ?></span><select name="exclude_categories[]" multiple size="6">
                                <?php foreach ( $all_categories as $cat ) : ?>
                                    <option value="<?php echo esc_attr( $cat->term_id ); ?>"
                                        <?php selected( in_array( $cat->term_id, (array) $options['exclude_categories'], false ) ); ?>>
                                        <?php echo esc_html( $cat->name ); ?> (<?php echo intval( $cat->count ); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select></label><?php endif; ?>
                            <?php if ( ! empty( $all_tags ) ) : ?><label class="ai-fr-field"><span><?php esc_html_e( 'Tag', 'ai-friendly' ); ?></span><select name="exclude_tags[]" multiple size="6">
                                <?php foreach ( $all_tags as $tag ) : ?>
                                    <option value="<?php echo esc_attr( $tag->term_id ); ?>"
                                        <?php selected( in_array( $tag->term_id, (array) $options['exclude_tags'], false ) ); ?>>
                                        <?php echo esc_html( $tag->name ); ?> (<?php echo intval( $tag->count ); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select></label><?php endif; ?>
                            <?php if ( ! empty( $all_templates ) ) : ?><label class="ai-fr-field"><span><?php esc_html_e( 'Template', 'ai-friendly' ); ?></span><select name="exclude_templates[]" multiple size="6">
                                <?php foreach ( $all_templates as $file => $name ) : ?>
                                    <option value="<?php echo esc_attr( $file ); ?>"
                                        <?php selected( in_array( $file, (array) $options['exclude_templates'], true ) ); ?>>
                                        <?php echo esc_html( $name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select></label><?php endif; ?>
                            <label class="ai-fr-field ai-fr-field-wide"><span><?php esc_html_e( 'Pattern URL', 'ai-friendly' ); ?></span><textarea name="exclude_url_patterns" id="exclude_url_patterns" rows="5" class="code"><?php echo esc_textarea( $options['exclude_url_patterns'] ); ?></textarea><small><?php esc_html_e( 'Un pattern per riga, con wildcard', 'ai-friendly' ); ?> <code>*</code>.</small></label>
                        </div>
                    </article>
                </div>
            </section>

            <section id="ai-fr-section-schema" class="ai-fr-section">
                <div class="ai-fr-schema-head ai-fr-section-heading">
                    <span>04</span>
                    <div>
                        <h3><?php esc_html_e( 'Semantic Schema', 'ai-friendly' ); ?></h3>
                        <p class="description"><?php esc_html_e( 'Aggiunge identità, profili e contesto AI-friendly al JSON-LD, senza duplicare il lavoro del plugin SEO.', 'ai-friendly' ); ?></p>
                    </div>
                    <div class="ai-fr-schema-status" aria-label="<?php esc_attr_e( 'Stato Semantic Schema', 'ai-friendly' ); ?>">
                        <span class="ai-fr-badge <?php echo ! empty( $options['schema_enabled'] ) ? 'is-ok' : 'is-muted'; ?>">
                            <?php echo ! empty( $options['schema_enabled'] ) ? esc_html__( 'Attivo', 'ai-friendly' ) : esc_html__( 'Disattivo', 'ai-friendly' ); ?>
                        </span>
                        <span><?php esc_html_e( 'Provider:', 'ai-friendly' ); ?> <code><?php echo esc_html( $schema_provider ); ?></code></span>
                        <span><?php esc_html_e( 'Output:', 'ai-friendly' ); ?> <code><?php echo esc_html( $schema_mode ); ?></code></span>
                        <a class="button button-secondary" href="<?php echo esc_url( $schema_validator_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Apri Schema Validator', 'ai-friendly' ); ?></a>
                    </div>
                </div>

                <div class="ai-fr-schema-grid">
                    <article class="ai-fr-schema-card ai-fr-schema-card-wide">
                        <div class="ai-fr-schema-card-head">
                            <h4><?php esc_html_e( 'Output', 'ai-friendly' ); ?></h4>
                            <p><?php esc_html_e( 'Decidi se AI Friendly deve estendere Yoast/Rank Math o stampare un grafo autonomo.', 'ai-friendly' ); ?></p>
                        </div>
                        <div class="ai-fr-schema-fields ai-fr-schema-fields-inline">
                            <label class="ai-fr-field ai-fr-field-check">
                                <input type="checkbox" name="schema_enabled" value="1" <?php checked( $options['schema_enabled'] ); ?>>
                                <span><?php esc_html_e( 'Abilita JSON-LD semantico AI Friendly', 'ai-friendly' ); ?></span>
                            </label>
                            <?php if ( ai_fr_is_breakdance_active() ) : ?>
                                <label class="ai-fr-field ai-fr-field-check">
                                    <input type="checkbox" name="schema_breakdance_faq_enabled" value="1" <?php checked( $options['schema_breakdance_faq_enabled'] ); ?>>
                                    <span><?php esc_html_e( 'Rileva automaticamente le FAQ di Breakdance', 'ai-friendly' ); ?></span>
                                </label>
                            <?php endif; ?>
                            <label class="ai-fr-field">
                                <span><?php esc_html_e( 'Modalità', 'ai-friendly' ); ?></span>
                                <select name="schema_mode">
                                    <option value="auto" <?php selected( $options['schema_mode'], 'auto' ); ?>><?php esc_html_e( 'Auto', 'ai-friendly' ); ?></option>
                                    <option value="standalone" <?php selected( $options['schema_mode'], 'standalone' ); ?>><?php esc_html_e( 'Standalone', 'ai-friendly' ); ?></option>
                                    <option value="extend_yoast" <?php selected( $options['schema_mode'], 'extend_yoast' ); ?>><?php esc_html_e( 'Estendi Yoast', 'ai-friendly' ); ?></option>
                                    <option value="extend_rank_math" <?php selected( $options['schema_mode'], 'extend_rank_math' ); ?>><?php esc_html_e( 'Estendi Rank Math', 'ai-friendly' ); ?></option>
                                </select>
                            </label>
                        </div>
                    </article>

                    <article class="ai-fr-schema-card ai-fr-schema-card-wide ai-fr-schema-identity">
                        <div class="ai-fr-schema-card-head">
                            <h4><?php esc_html_e( 'Identità principale', 'ai-friendly' ); ?></h4>
                            <p><?php esc_html_e( 'Il nodo `Person` o `Organization` che rappresenta il sito o il brand.', 'ai-friendly' ); ?></p>
                        </div>
                        <div class="ai-fr-schema-fields">
                            <label class="ai-fr-field ai-fr-field-short">
                                <span><?php esc_html_e( 'Tipo', 'ai-friendly' ); ?></span>
                                <select name="schema_entity_type" id="ai-fr-schema-entity-type">
                                    <option value="Person" <?php selected( $options['schema_entity_type'], 'Person' ); ?>><?php esc_html_e( 'Person', 'ai-friendly' ); ?></option>
                                    <option value="Organization" <?php selected( $options['schema_entity_type'], 'Organization' ); ?>><?php esc_html_e( 'Organization', 'ai-friendly' ); ?></option>
                                </select>
                            </label>
                            <label class="ai-fr-field">
                                <span><?php esc_html_e( 'Nome', 'ai-friendly' ); ?></span>
                                <input type="text" name="schema_name" value="<?php echo esc_attr( $options['schema_name'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
                            </label>
                            <label class="ai-fr-field">
                                <span><?php esc_html_e( 'Nome alternativo', 'ai-friendly' ); ?></span>
                                <input type="text" name="schema_alternate_name" value="<?php echo esc_attr( $options['schema_alternate_name'] ); ?>">
                            </label>
                            <label class="ai-fr-field" data-entity-scope="person">
                                <span><?php esc_html_e( 'Ruolo / job title', 'ai-friendly' ); ?></span>
                                <input type="text" name="schema_job_title" value="<?php echo esc_attr( $options['schema_job_title'] ); ?>">
                            </label>
                            <div class="ai-fr-field" data-entity-scope="organization">
                                <span><?php esc_html_e( 'Tipi aggiuntivi', 'ai-friendly' ); ?></span>
                                <div class="ai-fr-schema-repeaters" data-repeater="types">
                                    <?php foreach ( $schema_types as $index => $schema_type ) : ?>
                                        <div class="ai-fr-schema-repeater-row">
                                            <input type="text" data-field="value" name="schema_types[<?php echo esc_attr( $index ); ?>]" value="<?php echo esc_attr( $schema_type ); ?>" placeholder="<?php esc_attr_e( 'EducationalOrganization', 'ai-friendly' ); ?>">
                                            <button type="button" class="button-link-delete ai-fr-repeater-remove"><?php esc_html_e( 'Rimuovi', 'ai-friendly' ); ?></button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="button button-secondary ai-fr-repeater-add" data-target="types"><?php esc_html_e( 'Aggiungi tipo', 'ai-friendly' ); ?></button>
                                <input type="hidden" name="schema_additional_type" value="">
                                <small><?php esc_html_e( 'Genera un vero array', 'ai-friendly' ); ?> <code><?php esc_html_e( '@type', 'ai-friendly' ); ?></code><?php esc_html_e( ', per esempio Organization + EducationalOrganization + NGO.', 'ai-friendly' ); ?></small>
                            </div>
                        </div>
                        <aside class="ai-fr-identity-assets" aria-label="<?php esc_attr_e( 'Immagini dell\'identità', 'ai-friendly' ); ?>">
                            <div class="ai-fr-media-block" data-entity-scope="organization">
                                <div class="ai-fr-media-block-head"><strong><?php esc_html_e( 'Logo aziendale', 'ai-friendly' ); ?></strong><span><?php esc_html_e( 'Usato nel nodo Organization.', 'ai-friendly' ); ?></span></div>
                                <input type="hidden" name="schema_logo_id" id="ai-fr-schema-logo-id" value="<?php echo esc_attr( intval( $options['schema_logo_id'] ) ); ?>">
                                <div class="ai-fr-schema-media">
                                    <div class="ai-fr-schema-image-preview" id="ai-fr-schema-logo-preview">
                                        <?php if ( $schema_logo_url !== '' ) : ?><img src="<?php echo esc_url( $schema_logo_url ); ?>" alt="" /><?php else : ?><span><?php esc_html_e( 'Nessun logo', 'ai-friendly' ); ?></span><?php endif; ?>
                                    </div>
                                    <div class="ai-fr-schema-media-actions">
                                        <button type="button" class="button" id="ai-fr-schema-logo-select"><?php esc_html_e( 'Seleziona', 'ai-friendly' ); ?></button>
                                        <button type="button" class="button ai-fr-button-danger" id="ai-fr-schema-logo-clear"><?php esc_html_e( 'Rimuovi', 'ai-friendly' ); ?></button>
                                    </div>
                                </div>
                            </div>
                            <div class="ai-fr-media-block">
                                <div class="ai-fr-media-block-head"><strong><?php esc_html_e( 'Immagine principale', 'ai-friendly' ); ?></strong><span><?php esc_html_e( 'Logo, ritratto o immagine identitaria.', 'ai-friendly' ); ?></span></div>
                                <input type="hidden" name="schema_image_id" id="ai-fr-schema-image-id" value="<?php echo esc_attr( intval( $options['schema_image_id'] ) ); ?>">
                                <div class="ai-fr-schema-media">
                                    <div class="ai-fr-schema-image-preview" id="ai-fr-schema-entity-image-preview">
                                        <?php if ( $schema_image_url !== '' ) : ?><img src="<?php echo esc_url( $schema_image_url ); ?>" alt="" /><?php else : ?><span><?php esc_html_e( 'Nessuna immagine', 'ai-friendly' ); ?></span><?php endif; ?>
                                    </div>
                                    <div class="ai-fr-schema-media-actions">
                                        <button type="button" class="button" id="ai-fr-schema-image-select"><?php esc_html_e( 'Seleziona', 'ai-friendly' ); ?></button>
                                        <button type="button" class="button ai-fr-button-danger" id="ai-fr-schema-image-clear"><?php esc_html_e( 'Rimuovi', 'ai-friendly' ); ?></button>
                                    </div>
                                </div>
                            </div>
                        </aside>
                    </article>

                    <article class="ai-fr-schema-card ai-fr-schema-card-wide" data-entity-scope="organization">
                        <div class="ai-fr-schema-card-head">
                            <h4><?php esc_html_e( 'Dati societari', 'ai-friendly' ); ?></h4>
                            <p><?php esc_html_e( 'Campi opzionali per', 'ai-friendly' ); ?> <code><?php esc_html_e( 'Organization', 'ai-friendly' ); ?></code><?php esc_html_e( '. Inserisci solo dati ufficiali e pubblicamente verificabili.', 'ai-friendly' ); ?></p>
                        </div>
                        <div class="ai-fr-schema-fields">
                            <label class="ai-fr-field"><span><?php esc_html_e( 'Ragione sociale', 'ai-friendly' ); ?></span><input type="text" name="schema_legal_name" value="<?php echo esc_attr( $options['schema_legal_name'] ); ?>" placeholder="<?php esc_attr_e( 'Azienda S.p.A.', 'ai-friendly' ); ?>"></label>
                            <label class="ai-fr-field"><span><?php esc_html_e( 'Partita IVA', 'ai-friendly' ); ?></span><input type="text" name="schema_vat_id" value="<?php echo esc_attr( $options['schema_vat_id'] ); ?>" placeholder="<?php esc_attr_e( 'IT01234567890', 'ai-friendly' ); ?>"></label>
                            <label class="ai-fr-field"><span><?php esc_html_e( 'Codice fiscale / taxID', 'ai-friendly' ); ?></span><input type="text" name="schema_tax_id" value="<?php echo esc_attr( $options['schema_tax_id'] ); ?>"></label>
                            <label class="ai-fr-field"><span><?php esc_html_e( 'Codice LEI', 'ai-friendly' ); ?></span><input type="text" name="schema_lei_code" value="<?php echo esc_attr( $options['schema_lei_code'] ); ?>" placeholder="<?php esc_attr_e( 'Codice LEI (20 caratteri)', 'ai-friendly' ); ?>"></label>
                            <label class="ai-fr-field"><span><?php esc_html_e( 'Simbolo di borsa', 'ai-friendly' ); ?></span><input type="text" name="schema_ticker_symbol" value="<?php echo esc_attr( $options['schema_ticker_symbol'] ); ?>" placeholder="<?php esc_attr_e( 'ACME', 'ai-friendly' ); ?>"></label>
                        </div>
                    </article>

                    <article class="ai-fr-schema-card ai-fr-schema-card-wide" data-entity-scope="organization">
                        <div class="ai-fr-schema-card-head">
                            <h4><?php esc_html_e( 'Sede fisica', 'ai-friendly' ); ?></h4>
                            <p><?php esc_html_e( 'Indirizzo, tipologia del luogo, coordinate e indicazioni per raggiungerlo.', 'ai-friendly' ); ?></p>
                        </div>
                        <div class="ai-fr-schema-fields">
                            <label class="ai-fr-field"><span><?php esc_html_e( 'Indirizzo', 'ai-friendly' ); ?></span><input type="text" name="schema_street_address" value="<?php echo esc_attr( $options['schema_street_address'] ); ?>" placeholder="<?php esc_attr_e( 'Via Esempio 10', 'ai-friendly' ); ?>"></label>
                            <label class="ai-fr-field"><span><?php esc_html_e( 'CAP', 'ai-friendly' ); ?></span><input type="text" name="schema_postal_code" value="<?php echo esc_attr( $options['schema_postal_code'] ); ?>" placeholder="00000"></label>
                            <label class="ai-fr-field"><span><?php esc_html_e( 'Città', 'ai-friendly' ); ?></span><input type="text" name="schema_address_locality" value="<?php echo esc_attr( $options['schema_address_locality'] ); ?>" placeholder="<?php esc_attr_e( 'Esempiopoli', 'ai-friendly' ); ?>"></label>
                            <label class="ai-fr-field"><span><?php esc_html_e( 'Provincia / regione', 'ai-friendly' ); ?></span><input type="text" name="schema_address_region" value="<?php echo esc_attr( $options['schema_address_region'] ); ?>" placeholder="<?php esc_attr_e( 'MI', 'ai-friendly' ); ?>"></label>
                            <label class="ai-fr-field"><span><?php esc_html_e( 'Paese (codice ISO)', 'ai-friendly' ); ?></span><input type="text" name="schema_address_country" value="<?php echo esc_attr( $options['schema_address_country'] ); ?>" placeholder="<?php esc_attr_e( 'IT', 'ai-friendly' ); ?>"></label>
                            <label class="ai-fr-field"><span><?php esc_html_e( 'Nome sede', 'ai-friendly' ); ?></span><input type="text" name="schema_place_name" value="<?php echo esc_attr( $options['schema_place_name'] ); ?>" placeholder="<?php esc_attr_e( 'Sede principale', 'ai-friendly' ); ?>"></label>
                            <label class="ai-fr-field"><span><?php esc_html_e( 'Tipo sede Schema.org', 'ai-friendly' ); ?></span><input type="text" name="schema_place_type" value="<?php echo esc_attr( $options['schema_place_type'] ); ?>" placeholder="<?php esc_attr_e( 'PerformingArtsTheater', 'ai-friendly' ); ?>"></label>
                            <label class="ai-fr-field"><span><?php esc_html_e( 'Latitudine', 'ai-friendly' ); ?></span><input type="text" name="schema_latitude" value="<?php echo esc_attr( $options['schema_latitude'] ); ?>" placeholder="45.4642"></label>
                            <label class="ai-fr-field"><span><?php esc_html_e( 'Longitudine', 'ai-friendly' ); ?></span><input type="text" name="schema_longitude" value="<?php echo esc_attr( $options['schema_longitude'] ); ?>" placeholder="9.1900"></label>
                            <label class="ai-fr-field"><span><?php esc_html_e( 'Accesso con trasporto pubblico', 'ai-friendly' ); ?></span><textarea name="schema_public_transportation_access" rows="3" placeholder="<?php esc_attr_e( 'Metro M2, fermata ...', 'ai-friendly' ); ?>"><?php echo esc_textarea( $options['schema_public_transportation_access'] ); ?></textarea></label>
                            <input type="hidden" name="schema_contact_type" value="">
                            <input type="hidden" name="schema_contact_email" value="">
                            <input type="hidden" name="schema_contact_languages" value="">
                        </div>
                    </article>

                    <article class="ai-fr-schema-card ai-fr-schema-card-wide" data-entity-scope="organization">
                        <div class="ai-fr-schema-card-head"><h4><?php esc_html_e( 'Reparti e contatti', 'ai-friendly' ); ?></h4><p><?php esc_html_e( 'ContactPoint ripetibili con telefono, email, lingue e orari specifici.', 'ai-friendly' ); ?></p></div>
                        <div class="ai-fr-schema-repeaters" data-repeater="contacts">
                            <?php foreach ( $schema_contacts as $index => $contact ) : ?>
                                <div class="ai-fr-schema-repeater-row ai-fr-schema-repeater-grid">
                                    <input data-field="contactType" name="schema_contacts[<?php echo esc_attr( $index ); ?>][contactType]" value="<?php echo esc_attr( $contact['contactType'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'segreteria corsi', 'ai-friendly' ); ?>">
                                    <input data-field="telephone" name="schema_contacts[<?php echo esc_attr( $index ); ?>][telephone]" value="<?php echo esc_attr( $contact['telephone'] ?? '' ); ?>" placeholder="+39 02 ...">
                                    <input type="email" data-field="email" name="schema_contacts[<?php echo esc_attr( $index ); ?>][email]" value="<?php echo esc_attr( $contact['email'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'email@example.com', 'ai-friendly' ); ?>">
                                    <input data-field="availableLanguage" name="schema_contacts[<?php echo esc_attr( $index ); ?>][availableLanguage]" value="<?php echo esc_attr( $contact['availableLanguage'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'it, en', 'ai-friendly' ); ?>">
                                    <input data-field="hoursAvailable" name="schema_contacts[<?php echo esc_attr( $index ); ?>][hoursAvailable]" value="<?php echo esc_attr( $contact['hoursAvailable'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Mo-Fr 09:00-18:00', 'ai-friendly' ); ?>">
                                    <button type="button" class="button-link-delete ai-fr-repeater-remove"><?php esc_html_e( 'Rimuovi', 'ai-friendly' ); ?></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="button button-secondary ai-fr-repeater-add" data-target="contacts"><?php esc_html_e( 'Aggiungi contatto', 'ai-friendly' ); ?></button>
                    </article>

                    <article class="ai-fr-schema-card ai-fr-schema-card-wide" data-entity-scope="organization">
                        <div class="ai-fr-schema-card-head"><h4><?php esc_html_e( 'Orari di apertura', 'ai-friendly' ); ?></h4><p><?php esc_html_e( 'Genera', 'ai-friendly' ); ?> <code><?php esc_html_e( 'openingHoursSpecification', 'ai-friendly' ); ?></code> <?php esc_html_e( 'sul nodo della sede fisica.', 'ai-friendly' ); ?></p></div>
                        <div class="ai-fr-schema-repeaters" data-repeater="hours">
                            <?php foreach ( $schema_opening_hours as $index => $hours ) : ?>
                                <div class="ai-fr-schema-repeater-row ai-fr-schema-repeater-grid">
                                    <input data-field="dayOfWeek" name="schema_opening_hours[<?php echo esc_attr( $index ); ?>][dayOfWeek]" value="<?php echo esc_attr( $hours['dayOfWeek'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Monday, Tuesday', 'ai-friendly' ); ?>">
                                    <input type="time" data-field="opens" name="schema_opening_hours[<?php echo esc_attr( $index ); ?>][opens]" value="<?php echo esc_attr( $hours['opens'] ?? '' ); ?>">
                                    <input type="time" data-field="closes" name="schema_opening_hours[<?php echo esc_attr( $index ); ?>][closes]" value="<?php echo esc_attr( $hours['closes'] ?? '' ); ?>">
                                    <input type="date" data-field="validFrom" name="schema_opening_hours[<?php echo esc_attr( $index ); ?>][validFrom]" value="<?php echo esc_attr( $hours['validFrom'] ?? '' ); ?>">
                                    <input type="date" data-field="validThrough" name="schema_opening_hours[<?php echo esc_attr( $index ); ?>][validThrough]" value="<?php echo esc_attr( $hours['validThrough'] ?? '' ); ?>">
                                    <button type="button" class="button-link-delete ai-fr-repeater-remove"><?php esc_html_e( 'Rimuovi', 'ai-friendly' ); ?></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="button button-secondary ai-fr-repeater-add" data-target="hours"><?php esc_html_e( 'Aggiungi fascia oraria', 'ai-friendly' ); ?></button>
                    </article>

                    <article class="ai-fr-schema-card ai-fr-schema-card-wide" data-entity-scope="organization">
                        <div class="ai-fr-schema-card-head">
                            <h4><?php esc_html_e( 'Fondatori', 'ai-friendly' ); ?></h4>
                            <p><?php esc_html_e( 'Uno per riga nel formato `Nome | ruolo attuale`. Il ruolo è opzionale: omettilo se non è verificato o aggiornato.', 'ai-friendly' ); ?></p>
                        </div>
                        <label class="ai-fr-field">
                            <span><?php esc_html_e( 'Persone fondatrici', 'ai-friendly' ); ?></span>
                            <textarea name="schema_founders" rows="4" placeholder="<?php esc_attr_e( 'Mario Rossi
Laura Bianchi | CEO', 'ai-friendly' ); ?>"><?php echo esc_textarea( $options['schema_founders'] ); ?></textarea>
                        </label>
                    </article>

                    <article class="ai-fr-schema-card ai-fr-schema-card-wide">
                        <div class="ai-fr-schema-card-head">
                            <h4><?php esc_html_e( 'Descrizioni', 'ai-friendly' ); ?></h4>
                            <p><?php esc_html_e( 'Usale per chiarire chi sei e distinguerti da entità simili.', 'ai-friendly' ); ?></p>
                        </div>
                        <div class="ai-fr-schema-fields">
                            <label class="ai-fr-field">
                                <span><?php esc_html_e( 'Descrizione', 'ai-friendly' ); ?></span>
                                <textarea name="schema_description" rows="4"><?php echo esc_textarea( $options['schema_description'] ); ?></textarea>
                            </label>
                            <label class="ai-fr-field">
                                <span><?php esc_html_e( 'Descrizione disambiguante', 'ai-friendly' ); ?></span>
                                <input type="text" name="schema_disambiguating_description" value="<?php echo esc_attr( $options['schema_disambiguating_description'] ); ?>">
                            </label>
                            <label class="ai-fr-field" data-entity-scope="organization">
                                <span><?php esc_html_e( 'Slogan', 'ai-friendly' ); ?></span>
                                <input type="text" name="schema_slogan" value="<?php echo esc_attr( $options['schema_slogan'] ); ?>" placeholder="<?php esc_attr_e( 'E-problem solving: sviluppo web fuori dagli schemi.', 'ai-friendly' ); ?>">
                            </label>
                            <label class="ai-fr-field" data-entity-scope="organization">
                                <span><?php esc_html_e( 'Data fondazione', 'ai-friendly' ); ?></span>
                                <input type="text" name="schema_founding_date" value="<?php echo esc_attr( $options['schema_founding_date'] ); ?>" placeholder="2015">
                            </label>
                        </div>
                    </article>

                    <article class="ai-fr-schema-card ai-fr-schema-card-wide">
                        <div class="ai-fr-schema-card-head">
                            <h4><?php esc_html_e( 'Profili e competenze', 'ai-friendly' ); ?></h4>
                            <p><?php esc_html_e( 'Una voce per riga. Sono i campi più utili per la disambiguazione.', 'ai-friendly' ); ?></p>
                        </div>
                        <div class="ai-fr-schema-fields">
                            <label class="ai-fr-field">
                                <span><?php esc_html_e( 'sameAs / profili esterni', 'ai-friendly' ); ?></span>
                                <textarea name="schema_same_as" rows="4" placeholder="<?php esc_attr_e( 'https://www.linkedin.com/in/...
https://github.com/...', 'ai-friendly' ); ?>"><?php echo esc_textarea( $options['schema_same_as'] ); ?></textarea>
                            </label>
                            <label class="ai-fr-field">
                                <span><?php esc_html_e( 'knowsAbout', 'ai-friendly' ); ?></span>
                                <textarea name="schema_knows_about" rows="4" placeholder="<?php esc_attr_e( 'SEO tecnico
AI content strategy', 'ai-friendly' ); ?>"><?php echo esc_textarea( $options['schema_knows_about'] ); ?></textarea>
                            </label>
                            <label class="ai-fr-field">
                                <span><?php esc_html_e( 'knowsLanguage', 'ai-friendly' ); ?></span>
                                <textarea name="schema_knows_language" rows="3" placeholder="<?php esc_attr_e( 'it-IT
en-US', 'ai-friendly' ); ?>"><?php echo esc_textarea( $options['schema_knows_language'] ); ?></textarea>
                            </label>
                            <label class="ai-fr-field" data-entity-scope="organization">
                                <span><?php esc_html_e( 'areaServed', 'ai-friendly' ); ?></span>
                                <textarea name="schema_area_served" rows="3" placeholder="<?php esc_attr_e( 'City: Esempiopoli
Country: Italia', 'ai-friendly' ); ?>"><?php echo esc_textarea( $options['schema_area_served'] ); ?></textarea>
                            </label>
                        </div>
                    </article>

                    <article class="ai-fr-schema-card ai-fr-schema-card-wide" data-entity-scope="organization">
                        <div class="ai-fr-schema-card-head"><h4><?php esc_html_e( 'Certificazioni', 'ai-friendly' ); ?></h4><p><?php esc_html_e( 'Accreditamenti, norme ISO e iscrizioni ad albi come nodi', 'ai-friendly' ); ?> <code><?php esc_html_e( 'Certification', 'ai-friendly' ); ?></code>.</p></div>
                        <div class="ai-fr-schema-repeaters" data-repeater="certifications">
                            <?php foreach ( $schema_certifications as $index => $certification ) : ?>
                                <div class="ai-fr-schema-repeater-row ai-fr-schema-repeater-grid">
                                    <input data-field="name" name="schema_certifications[<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $certification['name'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'ISO 9001', 'ai-friendly' ); ?>">
                                    <input data-field="identifier" name="schema_certifications[<?php echo esc_attr( $index ); ?>][identifier]" value="<?php echo esc_attr( $certification['identifier'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Certificato n.', 'ai-friendly' ); ?>">
                                    <input data-field="issuedBy" name="schema_certifications[<?php echo esc_attr( $index ); ?>][issuedBy]" value="<?php echo esc_attr( $certification['issuedBy'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Ente certificatore', 'ai-friendly' ); ?>">
                                    <input type="url" data-field="url" name="schema_certifications[<?php echo esc_attr( $index ); ?>][url]" value="<?php echo esc_attr( $certification['url'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'https://...', 'ai-friendly' ); ?>">
                                    <button type="button" class="button-link-delete ai-fr-repeater-remove"><?php esc_html_e( 'Rimuovi', 'ai-friendly' ); ?></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="button button-secondary ai-fr-repeater-add" data-target="certifications"><?php esc_html_e( 'Aggiungi certificazione', 'ai-friendly' ); ?></button>
                    </article>

                    <article class="ai-fr-schema-card ai-fr-schema-card-wide" data-entity-scope="organization">
                        <div class="ai-fr-schema-card-head"><h4><?php esc_html_e( 'Identificatori aggiuntivi', 'ai-friendly' ); ?></h4><p><?php esc_html_e( 'Coppie chiave-valore per RUNTS, REA, ATECO e registri di settore.', 'ai-friendly' ); ?></p></div>
                        <div class="ai-fr-schema-repeaters" data-repeater="identifiers">
                            <?php foreach ( $schema_identifiers as $index => $identifier ) : ?>
                                <div class="ai-fr-schema-repeater-row">
                                    <input data-field="propertyID" name="schema_identifiers[<?php echo esc_attr( $index ); ?>][propertyID]" value="<?php echo esc_attr( $identifier['propertyID'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'RUNTS', 'ai-friendly' ); ?>">
                                    <input data-field="value" name="schema_identifiers[<?php echo esc_attr( $index ); ?>][value]" value="<?php echo esc_attr( $identifier['value'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Numero identificativo', 'ai-friendly' ); ?>">
                                    <button type="button" class="button-link-delete ai-fr-repeater-remove"><?php esc_html_e( 'Rimuovi', 'ai-friendly' ); ?></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="button button-secondary ai-fr-repeater-add" data-target="identifiers"><?php esc_html_e( 'Aggiungi identificatore', 'ai-friendly' ); ?></button>
                    </article>

                    <article class="ai-fr-schema-card ai-fr-schema-card-wide" data-entity-scope="organization">
                        <div class="ai-fr-schema-card-head">
                            <h4><?php esc_html_e( 'Catalogo servizi', 'ai-friendly' ); ?></h4>
                            <p><?php esc_html_e( 'Aggiunge un', 'ai-friendly' ); ?> <code><?php esc_html_e( 'OfferCatalog', 'ai-friendly' ); ?></code><?php esc_html_e( '. Le sorgenti WordPress compilano automaticamente nome, URL e descrizione; le righe manuali restano disponibili per integrazioni.', 'ai-friendly' ); ?></p>
                        </div>
                        <div class="ai-fr-field ai-fr-schema-source-field">
                            <span><?php esc_html_e( 'Sorgenti WordPress', 'ai-friendly' ); ?></span>
                            <div class="ai-fr-schema-repeaters" data-repeater="offerSources">
                                <?php foreach ( $schema_offer_sources as $index => $source ) : ?>
                                    <div class="ai-fr-schema-repeater-row">
                                        <input data-field="value" name="schema_offer_sources[<?php echo esc_attr( $index ); ?>]" value="<?php echo esc_attr( $source ); ?>" placeholder="<?php esc_attr_e( 'ID termine, taxonomy:slug o permalink WordPress', 'ai-friendly' ); ?>">
                                        <button type="button" class="button-link-delete ai-fr-repeater-remove"><?php esc_html_e( 'Rimuovi', 'ai-friendly' ); ?></button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="button button-secondary ai-fr-repeater-add" data-target="offerSources"><?php esc_html_e( 'Aggiungi sorgente', 'ai-friendly' ); ?></button>
                            <small><?php esc_html_e( 'Accetta ID di termini,', 'ai-friendly' ); ?> <code><?php esc_html_e( 'taxonomy:slug', 'ai-friendly' ); ?></code><?php esc_html_e( ', permalink di categorie/tassonomie e permalink di pagine o CPT.', 'ai-friendly' ); ?></small>
                        </div>
                        <div class="ai-fr-schema-subsection-head">
                            <strong><?php esc_html_e( 'Voci manuali', 'ai-friendly' ); ?></strong>
                            <span><?php esc_html_e( 'Usale per completare o sostituire i dati ricavati dalle sorgenti WordPress.', 'ai-friendly' ); ?></span>
                        </div>
                        <div class="ai-fr-schema-services" id="ai-fr-schema-services">
                            <?php foreach ( $schema_services as $index => $service ) : ?>
                                <div class="ai-fr-schema-service" data-service-index="<?php echo esc_attr( $index ); ?>">
                                    <div class="ai-fr-schema-service-head">
                                        <strong><?php esc_html_e( 'Servizio', 'ai-friendly' ); ?></strong>
                                        <button type="button" class="button button-link-delete ai-fr-schema-service-remove"><?php esc_html_e( 'Rimuovi', 'ai-friendly' ); ?></button>
                                    </div>
                                    <div class="ai-fr-schema-service-grid">
                                        <label class="ai-fr-field">
                                            <span><?php esc_html_e( 'Nome', 'ai-friendly' ); ?></span>
                                            <input type="text" data-service-field="name" name="schema_services[<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $service['name'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'UX e Graphic Design', 'ai-friendly' ); ?>">
                                        </label>
                                        <label class="ai-fr-field">
                                            <span><?php esc_html_e( 'URL pagina', 'ai-friendly' ); ?></span>
                                            <input type="url" data-service-field="url" name="schema_services[<?php echo esc_attr( $index ); ?>][url]" value="<?php echo esc_attr( $service['url'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'https://example.com/servizio/', 'ai-friendly' ); ?>">
                                        </label>
                                        <label class="ai-fr-field">
                                            <span><?php esc_html_e( 'Tipo servizio', 'ai-friendly' ); ?></span>
                                            <input type="text" data-service-field="serviceType" name="schema_services[<?php echo esc_attr( $index ); ?>][serviceType]" value="<?php echo esc_attr( $service['serviceType'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Web design, UX/UI design', 'ai-friendly' ); ?>">
                                        </label>
                                        <label class="ai-fr-field">
                                            <span><?php esc_html_e( 'Area servita', 'ai-friendly' ); ?></span>
                                            <input type="text" data-service-field="areaServed" name="schema_services[<?php echo esc_attr( $index ); ?>][areaServed]" value="<?php echo esc_attr( $service['areaServed'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Italia', 'ai-friendly' ); ?>">
                                        </label>
                                        <label class="ai-fr-field ai-fr-schema-service-description">
                                            <span><?php esc_html_e( 'Descrizione', 'ai-friendly' ); ?></span>
                                            <textarea data-service-field="description" name="schema_services[<?php echo esc_attr( $index ); ?>][description]" rows="3" placeholder="<?php esc_attr_e( 'Descrizione breve del servizio.', 'ai-friendly' ); ?>"><?php echo esc_textarea( $service['description'] ?? '' ); ?></textarea>
                                        </label>
                                        <label class="ai-fr-field">
                                            <span><?php esc_html_e( 'Prezzo', 'ai-friendly' ); ?></span>
                                            <input type="text" data-service-field="price" name="schema_services[<?php echo esc_attr( $index ); ?>][price]" value="<?php echo esc_attr( $service['price'] ?? '' ); ?>" placeholder="0">
                                        </label>
                                        <label class="ai-fr-field">
                                            <span><?php esc_html_e( 'Valuta', 'ai-friendly' ); ?></span>
                                            <input type="text" data-service-field="priceCurrency" name="schema_services[<?php echo esc_attr( $index ); ?>][priceCurrency]" value="<?php echo esc_attr( $service['priceCurrency'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'EUR', 'ai-friendly' ); ?>">
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="button button-secondary" id="ai-fr-schema-service-add"><?php esc_html_e( 'Aggiungi servizio', 'ai-friendly' ); ?></button>
                    </article>

                    <article class="ai-fr-schema-card ai-fr-schema-card-seven">
                        <div class="ai-fr-schema-card-head">
                            <h4><?php esc_html_e( 'Realizzazione del sito', 'ai-friendly' ); ?></h4>
                            <p><?php esc_html_e( 'Indica facoltativamente la persona o l\'organizzazione che ha sviluppato il sito. Il dato viene pubblicato come', 'ai-friendly' ); ?> <code><?php esc_html_e( 'creator', 'ai-friendly' ); ?></code> <?php esc_html_e( 'del nodo', 'ai-friendly' ); ?> <code><?php esc_html_e( 'WebSite', 'ai-friendly' ); ?></code>.</p>
                        </div>
                        <div class="ai-fr-schema-fields">
                            <label class="ai-fr-field ai-fr-field-short">
                                <span><?php esc_html_e( 'Tipo', 'ai-friendly' ); ?></span>
                                <select name="schema_creator_type">
                                    <option value="Organization" <?php selected( $options['schema_creator_type'], 'Organization' ); ?>><?php esc_html_e( 'Organization', 'ai-friendly' ); ?></option>
                                    <option value="Person" <?php selected( $options['schema_creator_type'], 'Person' ); ?>><?php esc_html_e( 'Person', 'ai-friendly' ); ?></option>
                                </select>
                            </label>
                            <label class="ai-fr-field">
                                <span><?php esc_html_e( 'Nome sviluppatore / agenzia', 'ai-friendly' ); ?></span>
                                <input type="text" name="schema_creator_name" value="<?php echo esc_attr( $options['schema_creator_name'] ); ?>" placeholder="<?php esc_attr_e( 'Nome agenzia o professionista', 'ai-friendly' ); ?>">
                            </label>
                            <label class="ai-fr-field">
                                <span><?php esc_html_e( 'URL', 'ai-friendly' ); ?></span>
                                <input type="url" name="schema_creator_url" value="<?php echo esc_attr( $options['schema_creator_url'] ); ?>" placeholder="<?php esc_attr_e( 'https://www.esempio.it/', 'ai-friendly' ); ?>">
                            </label>
                        </div>
                    </article>

                    <article class="ai-fr-schema-card ai-fr-schema-card-five">
                        <div class="ai-fr-schema-card-head">
                            <h4><?php esc_html_e( 'Profilo e licenza', 'ai-friendly' ); ?></h4>
                            <p><?php esc_html_e( 'Collega una pagina profilo e una licenza riutilizzabile sui contenuti.', 'ai-friendly' ); ?></p>
                        </div>
                        <div class="ai-fr-schema-fields">
                            <label class="ai-fr-field">
                                <span><?php esc_html_e( 'Pagina ProfilePage', 'ai-friendly' ); ?></span>
                                <?php
                                wp_dropdown_pages(
                                    [
                                        'name'              => 'schema_profile_page_id',
                                        'show_option_none'  => 'Nessuna',
                                        'option_none_value' => 0,
                                        'selected'          => intval( $options['schema_profile_page_id'] ),
                                    ]
                                );
                                ?>
                            </label>
                            <label class="ai-fr-field">
                                <span><?php esc_html_e( 'License URL', 'ai-friendly' ); ?></span>
                                <input type="url" name="schema_license" value="<?php echo esc_attr( $options['schema_license'] ); ?>" placeholder="<?php esc_attr_e( 'https://creativecommons.org/licenses/by/4.0/', 'ai-friendly' ); ?>">
                            </label>
                        </div>
                    </article>
                </div>
            </section>

            <section id="ai-fr-section-automation" class="ai-fr-section">
                <div class="ai-fr-section-heading"><span>05</span><div><h3><?php esc_html_e( 'Automation', 'ai-friendly' ); ?></h3><p><?php esc_html_e( 'Controlla generazione, frequenza, notifiche e cronologia operativa.', 'ai-friendly' ); ?></p></div></div>
                <div class="ai-fr-settings-cards">
                    <article class="ai-fr-settings-card">
                        <div class="ai-fr-card-head"><span>01</span><div><h4><?php esc_html_e( 'Output statico', 'ai-friendly' ); ?></h4><p><?php esc_html_e( 'Persistenza e spazio utilizzato dai file Markdown.', 'ai-friendly' ); ?></p></div></div>
                        <div class="ai-fr-option-list">
                            <label>
                                <input type="checkbox" id="ai-fr-static-md-files" name="static_md_files" value="1" <?php checked( $options['static_md_files'] ); ?>>
                                <?php esc_html_e( 'Salva e servi file MD statici', 'ai-friendly' ); ?>
                            </label>
                            <p class="description ai-fr-statline">
                                <?php esc_html_e( 'File salvati:', 'ai-friendly' ); ?> <?php echo intval( $version_stats['count'] ); ?> |
                                <?php esc_html_e( 'Spazio:', 'ai-friendly' ); ?> <?php echo esc_html( size_format( intval( $version_stats['size'] ) ) ); ?>
                            </p>
                        </div>
                    </article>
                    <article class="ai-fr-settings-card">
                        <div class="ai-fr-card-head"><span>02</span><div><h4><?php esc_html_e( 'Rigenerazione automatica', 'ai-friendly' ); ?></h4><p><?php esc_html_e( 'Programmazione e dimensione dei batch.', 'ai-friendly' ); ?></p></div></div>
                        <div class="ai-fr-option-list">
                            <label>
                                <input type="checkbox" id="ai-fr-auto-regenerate" name="auto_regenerate" value="1" <?php checked( $options['auto_regenerate'] ); ?>>
                                <?php esc_html_e( 'Rigenera i file .md ad intervallo su tutto il sito (cron)', 'ai-friendly' ); ?>
                            </label>
                            <div class="ai-fr-number-fields">
                                <label><?php esc_html_e( 'Intervallo (ore)', 'ai-friendly' ); ?><input type="number" name="regenerate_interval" min="1" max="168" value="<?php echo esc_attr( $options['regenerate_interval'] ); ?>"></label>
                                <label><?php esc_html_e( 'Contenuti per esecuzione', 'ai-friendly' ); ?><input type="number" name="regenerate_batch_size" min="10" max="1000" value="<?php echo esc_attr( intval( $options['regenerate_batch_size'] ?? 100 ) ); ?>"></label>
                            </div>
                            <p class="description"><?php esc_html_e( 'Per siti molto grandi, il cron processa solo questo numero di contenuti per run e continua dal successivo.', 'ai-friendly' ); ?></p>
                            <?php if ( $next_cron ) : ?>
                                <p class="description"><?php esc_html_e( 'Prossima esecuzione:', 'ai-friendly' ); ?> <?php echo esc_html( date_i18n( 'Y-m-d H:i:s', $next_cron ) ); ?></p>
                            <?php endif; ?>
                        </div>
                    </article>
                    <article class="ai-fr-settings-card">
                        <div class="ai-fr-card-head"><span>03</span><div><h4><?php esc_html_e( 'Trigger su eventi', 'ai-friendly' ); ?></h4><p><?php esc_html_e( 'Decidi quando un contenuto deve aggiornare l’output.', 'ai-friendly' ); ?></p></div></div>
                        <div class="ai-fr-option-list">
                            <label>
                                <input type="checkbox" name="regenerate_on_save" value="1" <?php checked( $options['regenerate_on_save'] ); ?>>
                                <?php esc_html_e( 'Rigenera quando un contenuto viene salvato/aggiornato', 'ai-friendly' ); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="regenerate_on_change" value="1" <?php checked( $options['regenerate_on_change'] ); ?>>
                                <?php esc_html_e( 'Rigenera solo se il contenuto e cambiato (checksum)', 'ai-friendly' ); ?>
                            </label>
                        </div>
                    </article>
                    <article class="ai-fr-settings-card">
                        <div class="ai-fr-card-head"><span>04</span><div><h4><?php esc_html_e( 'Notifiche', 'ai-friendly' ); ?></h4><p><?php esc_html_e( 'Segnala gli errori agli amministratori.', 'ai-friendly' ); ?></p></div></div>
                        <div class="ai-fr-option-list">
                            <label>
                                <input type="checkbox" name="notify_admin_notice" value="1" <?php checked( $options['notify_admin_notice'] ?? '' ); ?>>
                                <?php esc_html_e( 'Mostra notice admin quando una rigenerazione ha errori', 'ai-friendly' ); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="notify_email" value="1" <?php checked( $options['notify_email'] ?? '' ); ?>>
                                <?php esc_html_e( 'Invia email in caso di errori rigenerazione', 'ai-friendly' ); ?>
                            </label>
                            <label class="ai-fr-stacked-label"><?php esc_html_e( 'Email destinatario', 'ai-friendly' ); ?>
                                <input type="email" name="notify_email_to" value="<?php echo esc_attr( $options['notify_email_to'] ?? '' ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
                            </label>
                        </div>
                    </article>
                    <article class="ai-fr-settings-card ai-fr-settings-card-wide">
                        <div class="ai-fr-card-head"><span>05</span><div><h4><?php esc_html_e( 'Azioni manuali', 'ai-friendly' ); ?></h4><p><?php esc_html_e( 'Genera, forza o rimuovi i file statici.', 'ai-friendly' ); ?></p></div></div>
                        <div class="ai-fr-action-row">
                            <button type="button" id="ai-fr-regenerate" class="button button-primary"><?php esc_html_e( 'Rigenera tutti i file MD', 'ai-friendly' ); ?></button>
                            <button type="button" id="ai-fr-regenerate-force" class="button"><?php esc_html_e( 'Forza rigenerazione', 'ai-friendly' ); ?></button>
                            <button type="button" id="ai-fr-clear-versions" class="button ai-fr-button-danger"><?php esc_html_e( 'Elimina tutti i file', 'ai-friendly' ); ?></button>
                            <p id="ai-fr-action-status" role="status" aria-live="polite"></p>
                            <?php if ( ! empty( $last_regen['stats'] ) ) : ?>
                                <p class="description">
                                    <?php esc_html_e( 'Ultima esecuzione:', 'ai-friendly' ); ?>
                                    <?php esc_html_e( 'Processati', 'ai-friendly' ); ?> <?php echo intval( $last_regen['stats']['processed'] ?? 0 ); ?>,
                                    <?php esc_html_e( 'Rigenerati', 'ai-friendly' ); ?> <?php echo intval( $last_regen['stats']['regenerated'] ?? 0 ); ?>,
                                    <?php esc_html_e( 'Saltati', 'ai-friendly' ); ?> <?php echo intval( $last_regen['stats']['skipped'] ?? 0 ); ?>,
                                    <?php esc_html_e( 'Errori', 'ai-friendly' ); ?> <?php echo intval( $last_regen['stats']['errors'] ?? 0 ); ?>.
                                </p>
                            <?php endif; ?>
                        </div>
                    </article>
                </div>

                <div class="ai-fr-timeline">
                    <h3><?php esc_html_e( 'Timeline aggiornamenti', 'ai-friendly' ); ?></h3>
                    <button type="button" id="ai-fr-refresh-timeline" class="button button-secondary"><?php esc_html_e( 'Aggiorna timeline', 'ai-friendly' ); ?></button>
                    <ul id="ai-fr-timeline-list" class="ai-fr-list"></ul>
                </div>
            </section>

            <div class="ai-fr-submit-wrap" id="ai-fr-submit-wrap" aria-live="polite">
                <span id="ai-fr-dirty-state"><?php esc_html_e( 'Tutte le modifiche sono salvate', 'ai-friendly' ); ?></span>
                <input type="submit" name="ai_fr_save" class="button button-primary" value="<?php esc_attr_e( 'Salva impostazioni', 'ai-friendly' ); ?>">
            </div>
        </form>
        <p class="ai-fr-credit">
            <?php esc_html_e( 'Sviluppato da', 'ai-friendly' ); ?>
            <a href="<?php echo esc_url( 'https://www.sernicola-labs.com/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Sernicola Labs', 'ai-friendly' ); ?></a>
        </p>
    </div>
    <?php
}

add_filter(
    'plugin_action_links_' . plugin_basename( AI_FR_PLUGIN_FILE ),
    function ( array $links ): array {
        $settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=ai-friendly' ) ) . '">' . esc_html__( 'Impostazioni', 'ai-friendly' ) . '</a>';
        $github_link   = '<a href="https://github.com/Sernicola-Labs-Srl/ai-friendly" target="_blank" rel="noopener noreferrer">GitHub</a>';
        array_unshift( $links, $settings_link, $github_link );
        return $links;
    }
);
