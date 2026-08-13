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
                    'loading' => 'Caricamento...',
                    'error'   => 'Si e verificato un errore.',
                ],
            ]
        );
    }
);

function ai_fr_admin_require_permissions(): void {
    check_ajax_referer( 'ai_fr_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permessi insufficienti' );
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
            wp_send_json_error( 'Contenuto non trovato.' );
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
            wp_send_json_error( 'Impossibile creare snapshot.' );
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
            wp_send_json_error( $result['message'] ?? 'Ripristino fallito.' );
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
            wp_send_json_error( 'Seleziona due snapshot da confrontare.' );
        }

        $left_content  = ai_fr_get_llms_snapshot_content( $left_id );
        $right_content = ai_fr_get_llms_snapshot_content( $right_id );
        if ( ! is_string( $left_content ) || ! is_string( $right_content ) ) {
            wp_send_json_error( 'Uno o entrambi gli snapshot non sono disponibili.' );
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
                    'message'    => 'Le impostazioni sono state salvate, ma la prima generazione non e riuscita.',
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
                <p class="ai-fr-eyebrow">AI Friendly</p>
                <h1>AI Content Hub <small class="ai-fr-version">v<?php echo esc_html( AI_FR_VERSION ); ?></small></h1>
            </div>
            <button type="button" class="button ai-fr-header-wizard<?php echo $onboarding_done ? '' : ' is-hidden'; ?>" id="ai-fr-reopen-wizard">Riapri configurazione guidata</button>
            <?php if ( $settings_saved ) : ?>
                <div class="ai-fr-save-notice" role="status" aria-live="polite">
                    <span class="ai-fr-save-notice-icon" aria-hidden="true"></span>
                    <span>Impostazioni salvate</span>
                    <button type="button" class="ai-fr-save-notice-dismiss" aria-label="Nascondi notifica" onclick="this.closest('.ai-fr-save-notice').hidden = true;">&times;</button>
                </div>
            <?php endif; ?>
        </div>

        <div class="ai-fr-onboarding<?php echo $onboarding_done ? ' is-hidden' : ''; ?>" id="ai-fr-onboarding" data-initial-step="1">
            <div class="ai-fr-wizard-topline">
                <div>
                    <p class="ai-fr-eyebrow">Configurazione guidata</p>
                    <h2>Prepariamo l’Hub sui dati reali del sito.</h2>
                </div>
                <button type="button" class="button-link" id="ai-fr-onboarding-dismiss">Configura più tardi</button>
            </div>
            <ol class="ai-fr-stepper" aria-label="Avanzamento configurazione">
                <?php foreach ( [ 'Analisi', 'Contenuti', 'Markdown', 'Schema', 'Riepilogo' ] as $index => $label ) : ?>
                    <li data-wizard-marker="<?php echo esc_attr( $index + 1 ); ?>"><span><?php echo esc_html( $index + 1 ); ?></span><?php echo esc_html( $label ); ?></li>
                <?php endforeach; ?>
            </ol>

            <div class="ai-fr-wizard-panel" data-wizard-step="1">
                <div class="ai-fr-section-heading"><span>01</span><div><h3>Analisi del sito</h3><p>Questi dati sono stati rilevati da WordPress e dai plugin attivi.</p></div></div>
                <div class="ai-fr-analysis-grid">
                    <div><small>Sito</small><strong><?php echo esc_html( get_bloginfo( 'name' ) ); ?></strong><code><?php echo esc_html( home_url( '/' ) ); ?></code></div>
                    <div><small>Provider SEO</small><strong><?php echo esc_html( $schema_provider === 'none' ? 'Nessuno rilevato' : $schema_provider ); ?></strong></div>
                    <div><small>WooCommerce</small><strong><?php echo class_exists( 'WooCommerce' ) ? 'Attivo' : 'Non rilevato'; ?></strong></div>
                    <div><small>Breakdance</small><strong><?php echo ai_fr_is_breakdance_active() ? 'Attivo' : 'Non rilevato'; ?></strong></div>
                </div>
                <div class="ai-fr-detected-types">
                    <?php foreach ( $wizard_post_types as $post_type ) : $counts = wp_count_posts( $post_type->name ); ?>
                        <span><strong><?php echo esc_html( $post_type->labels->name ); ?></strong> <?php echo intval( $counts->publish ?? 0 ); ?> pubblicati</span>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="ai-fr-wizard-panel" data-wizard-step="2" hidden>
                <div class="ai-fr-section-heading"><span>02</span><div><h3>Contenuti da esporre</h3><p>Scegli solo i tipi destinati alla consultazione pubblica.</p></div></div>
                <div class="ai-fr-choice-grid">
                    <?php foreach ( $wizard_post_types as $post_type ) :
                        $is_first_setup = ! $onboarding_done;
                        $selected = $is_first_setup ? in_array( $post_type->name, [ 'page', 'post' ], true ) : in_array( $post_type->name, $wizard_selected_types, true );
                        ?>
                        <label class="ai-fr-choice"><input type="checkbox" data-wizard-field="content_types" value="<?php echo esc_attr( $post_type->name ); ?>" <?php checked( $selected ); ?>><span><strong><?php echo esc_html( $post_type->labels->name ); ?></strong><small><?php echo esc_html( $post_type->name ); ?></small></span></label>
                    <?php endforeach; ?>
                </div>
                <div class="ai-fr-inline-options">
                    <label><input type="checkbox" data-wizard-field="exclude_noindex" <?php checked( $options['exclude_noindex'] ); ?>> Escludi contenuti noindex</label>
                    <label><input type="checkbox" data-wizard-field="exclude_password" <?php checked( $options['exclude_password'] ); ?>> Escludi contenuti protetti da password</label>
                </div>
            </div>

            <div class="ai-fr-wizard-panel" data-wizard-step="3" hidden>
                <div class="ai-fr-section-heading"><span>03</span><div><h3>Markdown e automazione</h3><p>Le impostazioni proposte mantengono l’output aggiornato ogni 24 ore.</p></div></div>
                <div class="ai-fr-settings-grid">
                    <label class="ai-fr-setting"><input type="checkbox" data-wizard-field="llms_include_auto" <?php checked( $onboarding_done ? $options['llms_include_auto'] : '1' ); ?>><span><strong>Lista automatica</strong><small>Aggiunge i contenuti selezionati a llms.txt.</small></span></label>
                    <label class="ai-fr-setting"><input type="checkbox" data-wizard-field="static_md_files" <?php checked( $onboarding_done ? $options['static_md_files'] : '1' ); ?>><span><strong>File Markdown statici</strong><small>Genera e serve copie .md persistenti.</small></span></label>
                    <label class="ai-fr-setting"><input type="checkbox" data-wizard-field="auto_regenerate" <?php checked( $onboarding_done ? $options['auto_regenerate'] : '1' ); ?>><span><strong>Rigenerazione automatica</strong><small>Esegue il processo tramite cron WordPress.</small></span></label>
                    <label class="ai-fr-setting"><input type="checkbox" data-wizard-field="regenerate_on_save" <?php checked( $options['regenerate_on_save'] ); ?>><span><strong>Trigger su modifica</strong><small>Avvia la rigenerazione quando salvi un contenuto.</small></span></label>
                </div>
                <div class="ai-fr-number-fields">
                    <label>Intervallo (ore)<input type="number" min="1" max="168" data-wizard-field="regenerate_interval" value="<?php echo esc_attr( $onboarding_done ? $options['regenerate_interval'] : 24 ); ?>"></label>
                    <label>Contenuti per esecuzione<input type="number" min="10" max="1000" data-wizard-field="regenerate_batch_size" value="<?php echo esc_attr( $onboarding_done ? $options['regenerate_batch_size'] : 100 ); ?>"></label>
                    <label class="ai-fr-compact-check"><input type="checkbox" data-wizard-field="regenerate_on_change" <?php checked( $options['regenerate_on_change'] ); ?>> Solo se il checksum cambia</label>
                </div>
            </div>

            <div class="ai-fr-wizard-panel" data-wizard-step="4" hidden>
                <div class="ai-fr-section-heading"><span>04</span><div><h3>Semantic Schema</h3><p>Puoi attivarlo ora e completare i dettagli nella sezione Schema.</p></div></div>
                <label class="ai-fr-setting ai-fr-setting-primary"><input type="checkbox" data-wizard-field="schema_enabled" <?php checked( $options['schema_enabled'] ); ?>><span><strong>Abilita Semantic Schema</strong><small>Modalità automatica, compatibile con il provider SEO rilevato.</small></span></label>
                <div class="ai-fr-number-fields ai-fr-schema-setup-fields">
                    <label>Entità principale<select data-wizard-field="schema_entity_type"><option value="Organization" <?php selected( $options['schema_entity_type'], 'Organization' ); ?>>Organization</option><option value="Person" <?php selected( $options['schema_entity_type'], 'Person' ); ?>>Person</option></select></label>
                    <label>Nome<input type="text" data-wizard-field="schema_name" value="<?php echo esc_attr( $options['schema_name'] ?: get_bloginfo( 'name' ) ); ?>"></label>
                    <label class="ai-fr-field-wide">sameAs, un URL per riga<textarea rows="4" data-wizard-field="schema_same_as"><?php echo esc_textarea( $options['schema_same_as'] ); ?></textarea></label>
                    <?php if ( ai_fr_is_breakdance_active() ) : ?><label class="ai-fr-compact-check"><input type="checkbox" data-wizard-field="schema_breakdance_faq_enabled" <?php checked( $options['schema_breakdance_faq_enabled'] ); ?>> Rileva automaticamente le FAQ di Breakdance</label><?php endif; ?>
                </div>
            </div>

            <div class="ai-fr-wizard-panel" data-wizard-step="5" hidden>
                <div class="ai-fr-section-heading"><span>05</span><div><h3>Riepilogo</h3><p>Controlla le scelte prima del salvataggio e della prima generazione.</p></div></div>
                <div id="ai-fr-wizard-summary" class="ai-fr-review-grid"></div>
                <div id="ai-fr-wizard-result" class="ai-fr-wizard-result" role="status" aria-live="polite"></div>
            </div>

            <div class="ai-fr-wizard-actions">
                <button type="button" class="button" id="ai-fr-wizard-prev" hidden>Indietro</button>
                <span class="ai-fr-wizard-spacer"></span>
                <button type="button" class="button button-primary" id="ai-fr-wizard-next">Continua</button>
                <button type="button" class="button button-primary" id="ai-fr-wizard-complete" hidden>Salva e genera</button>
                <button type="button" class="button" id="ai-fr-wizard-retry" hidden>Riprova generazione</button>
            </div>
        </div>

        <form method="post" id="ai-fr-main-form" class="<?php echo $onboarding_done ? '' : 'is-hidden'; ?>">
            <?php wp_nonce_field( 'ai_fr_options_nonce' ); ?>
            <input type="hidden" name="onboarding_done" id="onboarding_done" value="<?php echo $onboarding_done ? '1' : ''; ?>">
            <input type="hidden" id="ai-fr-wizard-step" value="1">

            <nav class="ai-fr-nav" role="tablist" aria-label="Sezioni AI Content Hub">
                <button type="button" class="ai-fr-nav-item is-active" data-section="overview">Overview</button>
                <button type="button" class="ai-fr-nav-item" data-section="content">Content</button>
                <button type="button" class="ai-fr-nav-item" data-section="rules">Rules</button>
                <button type="button" class="ai-fr-nav-item" data-section="schema">Schema</button>
                <button type="button" class="ai-fr-nav-item" data-section="automation">Automation</button>
            </nav>

            <section id="ai-fr-section-overview" class="ai-fr-section is-active">
                <div class="ai-fr-section-heading"><span>01</span><div><h3>Overview</h3><p>Stato dell’output, diagnostica e azioni principali.</p></div></div>
                <div class="ai-fr-card-grid">
                    <article class="ai-fr-card">
                        <h3>Stato llms.txt</h3>
                        <p><code><?php echo esc_html( $overview['llms']['url'] ); ?></code></p>
                        <p>Caratteri: <strong id="ai-fr-llms-chars"><?php echo intval( $overview['llms']['chars'] ); ?></strong></p>
                        <p>Righe: <strong id="ai-fr-llms-lines"><?php echo intval( $overview['llms']['lines'] ); ?></strong></p>
                        <p>Ultima rigenerazione: <strong id="ai-fr-last-regen"><?php echo esc_html( $overview['llms']['last_regen_time'] ?: 'n/d' ); ?></strong></p>
                        <a href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>" target="_blank" class="button button-secondary">Anteprima llms.txt</a>
                    </article>

                    <article class="ai-fr-card">
                        <h3>Markdown Pack</h3>
                        <p>Static mode:
                            <span class="ai-fr-badge <?php echo ! empty( $overview['markdown']['static_enabled'] ) ? 'is-ok' : 'is-muted'; ?>">
                                <?php echo ! empty( $overview['markdown']['static_enabled'] ) ? 'attivo' : 'disattivo'; ?>
                            </span>
                        </p>
                        <p>File: <strong><?php echo intval( $overview['markdown']['count'] ); ?></strong></p>
                        <p>Spazio: <strong><?php echo esc_html( size_format( intval( $overview['markdown']['size'] ) ) ); ?></strong></p>
                        <button type="button" id="ai-fr-regenerate-overview" class="button button-secondary">Rigenera llms/MD</button>
                    </article>

                    <article class="ai-fr-card">
                        <h3>Avvisi rapidi</h3>
                        <ul id="ai-fr-overview-warnings" class="ai-fr-list">
                            <?php foreach ( $overview['diagnostics']['warnings'] as $warning ) : ?>
                                <li><?php echo esc_html( $warning['message'] ?? '' ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" id="ai-fr-refresh-diagnostics" class="button">Aggiorna diagnostica</button>
                        <p class="description" id="ai-fr-sr-info"></p>
                    </article>

                    <article class="ai-fr-card">
                        <h3>Semantic Schema</h3>
                        <p>Stato:
                            <span class="ai-fr-badge <?php echo ! empty( $options['schema_enabled'] ) ? 'is-ok' : 'is-muted'; ?>">
                                <?php echo ! empty( $options['schema_enabled'] ) ? 'attivo' : 'disattivo'; ?>
                            </span>
                        </p>
                        <p>Provider SEO: <strong><?php echo esc_html( $schema_provider ); ?></strong></p>
                        <p>Output: <strong><?php echo esc_html( $schema_mode ); ?></strong></p>
                        <button type="button" class="button button-secondary" data-section-jump="schema">Configura Schema</button>
                    </article>
                </div>

                <div class="ai-fr-quick-actions">
                    <button type="button" class="button button-primary" data-section-jump="content">Modifica llms.txt</button>
                    <button type="button" class="button" id="ai-fr-refresh-overview">Aggiorna Overview</button>
                    <button type="button" class="button" id="ai-fr-run-now">Rigenera adesso</button>
                </div>
            </section>

            <section id="ai-fr-section-content" class="ai-fr-section">
                <div class="ai-fr-section-heading"><span>02</span><div><h3>Content</h3><p>Modifica llms.txt, controlla l’anteprima e gestisci i contenuti esposti.</p></div></div>
                <div class="ai-fr-editor-layout">
                    <aside class="ai-fr-panel ai-fr-panel-left">
                        <h3>Struttura documento</h3>
                        <ul id="ai-fr-toc" class="ai-fr-list"></ul>
                    </aside>

                    <div class="ai-fr-panel ai-fr-panel-center">
                        <h3>Editor llms.txt</h3>
                        <textarea
                            name="llms_content"
                            id="llms_content"
                            rows="16"
                            class="large-text code"
                            placeholder="# Nome sito&#10;> Sintesi del sito"
                        ><?php echo esc_textarea( $options['llms_content'] ); ?></textarea>
                        <p class="description">Contenuto custom Markdown. Se vuoto, il plugin genera in automatico.</p>
                        <label>
                            <input type="checkbox" name="llms_include_auto" value="1" <?php checked( $options['llms_include_auto'] ); ?>>
                            Aggiungi lista automatica dopo il contenuto custom
                        </label>
                        <div class="ai-fr-preview-split">
                            <div class="ai-fr-preview-head">Anteprima live</div>
                            <div id="ai-fr-preview-pane"></div>
                        </div>
                    </div>

                    <aside class="ai-fr-panel ai-fr-panel-right">
                        <h3>Helper</h3>
                        <p>Token stimati: <strong id="ai-fr-token-count">0</strong></p>
                        <p>Validazione link: <strong id="ai-fr-link-validation">0 issue</strong></p>
                        <ul class="ai-fr-list">
                            <li><button type="button" class="button-link ai-fr-insert-snippet" data-snippet="# Chi siamo">+ Heading</button></li>
                            <li><button type="button" class="button-link ai-fr-insert-snippet" data-snippet="<?php echo esc_attr( '- [Servizi](' . $services_md_url . ')' ); ?>">+ Link sezione</button></li>
                            <li><button type="button" class="button-link ai-fr-insert-snippet" data-snippet="> Sintesi per AI in 1-2 frasi.">+ Sintesi</button></li>
                        </ul>
                        <p>Variabili utili:</p>
                        <ul class="ai-fr-list ai-fr-small">
                            <li><code><?php echo esc_html( get_bloginfo( 'name' ) ); ?></code></li>
                            <li><code><?php echo esc_html( home_url() ); ?></code></li>
                            <li><code><?php echo esc_html( get_locale() ); ?></code></li>
                        </ul>
                        <button type="button" id="ai-fr-run-simulation" class="button">AI Simulation</button>
                        <div id="ai-fr-simulation-result" class="ai-fr-simulation"></div>
                    </aside>
                </div>

                <div class="ai-fr-history">
                    <h3>Versioning llms</h3>
                    <div class="ai-fr-history-actions">
                        <button type="button" id="ai-fr-create-snapshot" class="button">Crea snapshot</button>
                        <button type="button" id="ai-fr-load-snapshots" class="button button-secondary">Aggiorna lista</button>
                        <button type="button" id="ai-fr-compare-snapshots" class="button">Confronta selezionati</button>
                    </div>
                    <ul id="ai-fr-snapshot-list" class="ai-fr-list"></ul>
                    <div class="ai-fr-diff-wrap">
                        <div class="ai-fr-diff-summary" id="ai-fr-diff-summary"></div>
                        <div class="ai-fr-diff-columns">
                            <div>
                                <h4>Diff affiancato</h4>
                                <table class="widefat striped ai-fr-diff-table">
                                    <thead>
                                        <tr>
                                            <th>Sinistra</th>
                                            <th>Destra</th>
                                        </tr>
                                    </thead>
                                    <tbody id="ai-fr-diff-rows"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ai-fr-content-manager">
                    <h3>Pagine del sito</h3>
                    <div class="ai-fr-filters">
                        <input type="text" id="ai-fr-content-search" placeholder="Cerca titolo">
                        <select id="ai-fr-content-type">
                            <option value="all">Tutti i tipi</option>
                            <option value="page">Pagine</option>
                            <option value="post">Post</option>
                            <option value="product">Prodotti</option>
                        </select>
                        <select id="ai-fr-content-status">
                            <option value="any">Tutti gli stati</option>
                            <option value="publish">Pubblicato</option>
                            <option value="draft">Bozza</option>
                            <option value="private">Privato</option>
                        </select>
                        <button type="button" id="ai-fr-content-apply" class="button">Filtra</button>
                    </div>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>Inclusa/Esclusa</th>
                                <th>Titolo</th>
                                <th>Tipo</th>
                                <th>Lingua</th>
                                <th>Stato</th>
                                <th>Token</th>
                                <th>Azione</th>
                            </tr>
                        </thead>
                        <tbody id="ai-fr-content-tbody"></tbody>
                    </table>
                    <div class="ai-fr-pagination">
                        <button type="button" class="button" id="ai-fr-prev-page">Precedente</button>
                        <span id="ai-fr-page-info">Pagina 1</span>
                        <button type="button" class="button" id="ai-fr-next-page">Successiva</button>
                    </div>
                </div>
            </section>

            <section id="ai-fr-section-rules" class="ai-fr-section">
                <div class="ai-fr-section-heading"><span>03</span><div><h3>Filtri & esclusioni</h3><p>Definisci cosa entra nell’output pubblico e cosa deve restarne fuori.</p></div></div>
                <div class="ai-fr-settings-cards">
                    <article class="ai-fr-settings-card">
                        <div class="ai-fr-card-head"><span>01</span><div><h4>Tipi di contenuto</h4><p>Seleziona le raccolte pubbliche da esporre.</p></div></div>
                        <div class="ai-fr-option-list">
                            <label>
                                <input type="checkbox" name="include_pages" value="1" <?php checked( $options['include_pages'] ); ?>>
                                Pagine
                            </label>
                            <label>
                                <input type="checkbox" name="include_posts" value="1" <?php checked( $options['include_posts'] ); ?>>
                                Articoli (Post)
                            </label>
                            <?php if ( class_exists( 'WooCommerce' ) ) : ?>
                                <label>
                                    <input type="checkbox" name="include_products" value="1" <?php checked( $options['include_products'] ); ?>>
                                    Prodotti WooCommerce
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
                        <div class="ai-fr-card-head"><span>02</span><div><h4>Protezioni</h4><p>Rispetta indicazioni SEO e accessi riservati.</p></div></div>
                        <div class="ai-fr-option-list">
                            <label>
                                <input type="checkbox" name="exclude_noindex" value="1" <?php checked( $options['exclude_noindex'] ); ?>>
                                Escludi pagine con meta <code>noindex</code>
                            </label>
                            <label>
                                <input type="checkbox" name="exclude_password" value="1" <?php checked( $options['exclude_password'] ); ?>>
                                Escludi contenuti protetti da password
                            </label>
                        </div>
                    </article>
                    <article class="ai-fr-settings-card ai-fr-settings-card-wide">
                        <div class="ai-fr-card-head"><span>03</span><div><h4>Esclusioni granulari</h4><p>Categorie, tag, template e pattern URL.</p></div></div>
                        <div class="ai-fr-field-grid">
                            <?php if ( ! empty( $all_categories ) ) : ?><label class="ai-fr-field"><span>Categorie</span><select name="exclude_categories[]" multiple size="6">
                                <?php foreach ( $all_categories as $cat ) : ?>
                                    <option value="<?php echo esc_attr( $cat->term_id ); ?>"
                                        <?php selected( in_array( $cat->term_id, (array) $options['exclude_categories'], false ) ); ?>>
                                        <?php echo esc_html( $cat->name ); ?> (<?php echo intval( $cat->count ); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select></label><?php endif; ?>
                            <?php if ( ! empty( $all_tags ) ) : ?><label class="ai-fr-field"><span>Tag</span><select name="exclude_tags[]" multiple size="6">
                                <?php foreach ( $all_tags as $tag ) : ?>
                                    <option value="<?php echo esc_attr( $tag->term_id ); ?>"
                                        <?php selected( in_array( $tag->term_id, (array) $options['exclude_tags'], false ) ); ?>>
                                        <?php echo esc_html( $tag->name ); ?> (<?php echo intval( $tag->count ); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select></label><?php endif; ?>
                            <?php if ( ! empty( $all_templates ) ) : ?><label class="ai-fr-field"><span>Template</span><select name="exclude_templates[]" multiple size="6">
                                <?php foreach ( $all_templates as $file => $name ) : ?>
                                    <option value="<?php echo esc_attr( $file ); ?>"
                                        <?php selected( in_array( $file, (array) $options['exclude_templates'], true ) ); ?>>
                                        <?php echo esc_html( $name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select></label><?php endif; ?>
                            <label class="ai-fr-field ai-fr-field-wide"><span>Pattern URL</span><textarea name="exclude_url_patterns" id="exclude_url_patterns" rows="5" class="code"><?php echo esc_textarea( $options['exclude_url_patterns'] ); ?></textarea><small>Un pattern per riga, con wildcard <code>*</code>.</small></label>
                        </div>
                    </article>
                </div>
            </section>

            <section id="ai-fr-section-schema" class="ai-fr-section">
                <div class="ai-fr-schema-head ai-fr-section-heading">
                    <span>04</span>
                    <div>
                        <h3>Semantic Schema</h3>
                        <p class="description">Aggiunge identità, profili e contesto AI-friendly al JSON-LD, senza duplicare il lavoro del plugin SEO.</p>
                    </div>
                    <div class="ai-fr-schema-status" aria-label="Stato Semantic Schema">
                        <span class="ai-fr-badge <?php echo ! empty( $options['schema_enabled'] ) ? 'is-ok' : 'is-muted'; ?>">
                            <?php echo ! empty( $options['schema_enabled'] ) ? 'Attivo' : 'Disattivo'; ?>
                        </span>
                        <span>Provider: <code><?php echo esc_html( $schema_provider ); ?></code></span>
                        <span>Output: <code><?php echo esc_html( $schema_mode ); ?></code></span>
                        <a class="button button-secondary" href="<?php echo esc_url( $schema_validator_url ); ?>" target="_blank" rel="noopener noreferrer">Apri Schema Validator</a>
                    </div>
                </div>

                <div class="ai-fr-schema-grid">
                    <article class="ai-fr-schema-card ai-fr-schema-card-wide">
                        <div class="ai-fr-schema-card-head">
                            <h4>Output</h4>
                            <p>Decidi se AI Friendly deve estendere Yoast/Rank Math o stampare un grafo autonomo.</p>
                        </div>
                        <div class="ai-fr-schema-fields ai-fr-schema-fields-inline">
                            <label class="ai-fr-field ai-fr-field-check">
                                <input type="checkbox" name="schema_enabled" value="1" <?php checked( $options['schema_enabled'] ); ?>>
                                <span>Abilita JSON-LD semantico AI Friendly</span>
                            </label>
                            <?php if ( ai_fr_is_breakdance_active() ) : ?>
                                <label class="ai-fr-field ai-fr-field-check">
                                    <input type="checkbox" name="schema_breakdance_faq_enabled" value="1" <?php checked( $options['schema_breakdance_faq_enabled'] ); ?>>
                                    <span>Rileva automaticamente le FAQ di Breakdance</span>
                                </label>
                            <?php endif; ?>
                            <label class="ai-fr-field">
                                <span>Modalità</span>
                                <select name="schema_mode">
                                    <option value="auto" <?php selected( $options['schema_mode'], 'auto' ); ?>>Auto</option>
                                    <option value="standalone" <?php selected( $options['schema_mode'], 'standalone' ); ?>>Standalone</option>
                                    <option value="extend_yoast" <?php selected( $options['schema_mode'], 'extend_yoast' ); ?>>Estendi Yoast</option>
                                    <option value="extend_rank_math" <?php selected( $options['schema_mode'], 'extend_rank_math' ); ?>>Estendi Rank Math</option>
                                </select>
                            </label>
                        </div>
                    </article>

                    <article class="ai-fr-schema-card ai-fr-schema-card-wide ai-fr-schema-identity">
                        <div class="ai-fr-schema-card-head">
                            <h4>Identità principale</h4>
                            <p>Il nodo `Person` o `Organization` che rappresenta il sito o il brand.</p>
                        </div>
                        <div class="ai-fr-schema-fields">
                            <label class="ai-fr-field ai-fr-field-short">
                                <span>Tipo</span>
                                <select name="schema_entity_type" id="ai-fr-schema-entity-type">
                                    <option value="Person" <?php selected( $options['schema_entity_type'], 'Person' ); ?>>Person</option>
                                    <option value="Organization" <?php selected( $options['schema_entity_type'], 'Organization' ); ?>>Organization</option>
                                </select>
                            </label>
                            <label class="ai-fr-field">
                                <span>Nome</span>
                                <input type="text" name="schema_name" value="<?php echo esc_attr( $options['schema_name'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
                            </label>
                            <label class="ai-fr-field">
                                <span>Nome alternativo</span>
                                <input type="text" name="schema_alternate_name" value="<?php echo esc_attr( $options['schema_alternate_name'] ); ?>">
                            </label>
                            <label class="ai-fr-field" data-entity-scope="person">
                                <span>Ruolo / job title</span>
                                <input type="text" name="schema_job_title" value="<?php echo esc_attr( $options['schema_job_title'] ); ?>">
                            </label>
                            <div class="ai-fr-field" data-entity-scope="organization">
                                <span>Tipi aggiuntivi</span>
                                <div class="ai-fr-schema-repeaters" data-repeater="types">
                                    <?php foreach ( $schema_types as $index => $schema_type ) : ?>
                                        <div class="ai-fr-schema-repeater-row">
                                            <input type="text" data-field="value" name="schema_types[<?php echo esc_attr( $index ); ?>]" value="<?php echo esc_attr( $schema_type ); ?>" placeholder="EducationalOrganization">
                                            <button type="button" class="button-link-delete ai-fr-repeater-remove">Rimuovi</button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="button button-secondary ai-fr-repeater-add" data-target="types">Aggiungi tipo</button>
                                <input type="hidden" name="schema_additional_type" value="">
                                <small>Genera un vero array <code>@type</code>, per esempio Organization + EducationalOrganization + NGO.</small>
                            </div>
                        </div>
                        <aside class="ai-fr-identity-assets" aria-label="Immagini dell'identità">
                            <div class="ai-fr-media-block" data-entity-scope="organization">
                                <div class="ai-fr-media-block-head"><strong>Logo aziendale</strong><span>Usato nel nodo Organization.</span></div>
                                <input type="hidden" name="schema_logo_id" id="ai-fr-schema-logo-id" value="<?php echo esc_attr( intval( $options['schema_logo_id'] ) ); ?>">
                                <div class="ai-fr-schema-media">
                                    <div class="ai-fr-schema-image-preview" id="ai-fr-schema-logo-preview">
                                        <?php if ( $schema_logo_url !== '' ) : ?><img src="<?php echo esc_url( $schema_logo_url ); ?>" alt="" /><?php else : ?><span>Nessun logo</span><?php endif; ?>
                                    </div>
                                    <div class="ai-fr-schema-media-actions">
                                        <button type="button" class="button" id="ai-fr-schema-logo-select">Seleziona</button>
                                        <button type="button" class="button ai-fr-button-danger" id="ai-fr-schema-logo-clear">Rimuovi</button>
                                    </div>
                                </div>
                            </div>
                            <div class="ai-fr-media-block">
                                <div class="ai-fr-media-block-head"><strong>Immagine principale</strong><span>Logo, ritratto o immagine identitaria.</span></div>
                                <input type="hidden" name="schema_image_id" id="ai-fr-schema-image-id" value="<?php echo esc_attr( intval( $options['schema_image_id'] ) ); ?>">
                                <div class="ai-fr-schema-media">
                                    <div class="ai-fr-schema-image-preview" id="ai-fr-schema-entity-image-preview">
                                        <?php if ( $schema_image_url !== '' ) : ?><img src="<?php echo esc_url( $schema_image_url ); ?>" alt="" /><?php else : ?><span>Nessuna immagine</span><?php endif; ?>
                                    </div>
                                    <div class="ai-fr-schema-media-actions">
                                        <button type="button" class="button" id="ai-fr-schema-image-select">Seleziona</button>
                                        <button type="button" class="button ai-fr-button-danger" id="ai-fr-schema-image-clear">Rimuovi</button>
                                    </div>
                                </div>
                            </div>
                        </aside>
                    </article>

                    <article class="ai-fr-schema-card ai-fr-schema-card-wide" data-entity-scope="organization">
                        <div class="ai-fr-schema-card-head">
                            <h4>Dati societari</h4>
                            <p>Campi opzionali per <code>Organization</code>. Inserisci solo dati ufficiali e pubblicamente verificabili.</p>
                        </div>
                        <div class="ai-fr-schema-fields">
                            <label class="ai-fr-field"><span>Ragione sociale</span><input type="text" name="schema_legal_name" value="<?php echo esc_attr( $options['schema_legal_name'] ); ?>" placeholder="Azienda S.p.A."></label>
                            <label class="ai-fr-field"><span>Partita IVA</span><input type="text" name="schema_vat_id" value="<?php echo esc_attr( $options['schema_vat_id'] ); ?>" placeholder="IT01234567890"></label>
                            <label class="ai-fr-field"><span>Codice fiscale / taxID</span><input type="text" name="schema_tax_id" value="<?php echo esc_attr( $options['schema_tax_id'] ); ?>"></label>
                            <label class="ai-fr-field"><span>Codice LEI</span><input type="text" name="schema_lei_code" value="<?php echo esc_attr( $options['schema_lei_code'] ); ?>" placeholder="Codice LEI (20 caratteri)"></label>
                            <label class="ai-fr-field"><span>Simbolo di borsa</span><input type="text" name="schema_ticker_symbol" value="<?php echo esc_attr( $options['schema_ticker_symbol'] ); ?>" placeholder="ACME"></label>
                        </div>
                    </article>

                    <article class="ai-fr-schema-card ai-fr-schema-card-wide" data-entity-scope="organization">
                        <div class="ai-fr-schema-card-head">
                            <h4>Sede fisica</h4>
                            <p>Indirizzo, tipologia del luogo, coordinate e indicazioni per raggiungerlo.</p>
                        </div>
                        <div class="ai-fr-schema-fields">
                            <label class="ai-fr-field"><span>Indirizzo</span><input type="text" name="schema_street_address" value="<?php echo esc_attr( $options['schema_street_address'] ); ?>" placeholder="Via Esempio 10"></label>
                            <label class="ai-fr-field"><span>CAP</span><input type="text" name="schema_postal_code" value="<?php echo esc_attr( $options['schema_postal_code'] ); ?>" placeholder="00000"></label>
                            <label class="ai-fr-field"><span>Città</span><input type="text" name="schema_address_locality" value="<?php echo esc_attr( $options['schema_address_locality'] ); ?>" placeholder="Esempiopoli"></label>
                            <label class="ai-fr-field"><span>Provincia / regione</span><input type="text" name="schema_address_region" value="<?php echo esc_attr( $options['schema_address_region'] ); ?>" placeholder="MI"></label>
                            <label class="ai-fr-field"><span>Paese (codice ISO)</span><input type="text" name="schema_address_country" value="<?php echo esc_attr( $options['schema_address_country'] ); ?>" placeholder="IT"></label>
                            <label class="ai-fr-field"><span>Nome sede</span><input type="text" name="schema_place_name" value="<?php echo esc_attr( $options['schema_place_name'] ); ?>" placeholder="Sede principale"></label>
                            <label class="ai-fr-field"><span>Tipo sede Schema.org</span><input type="text" name="schema_place_type" value="<?php echo esc_attr( $options['schema_place_type'] ); ?>" placeholder="PerformingArtsTheater"></label>
                            <label class="ai-fr-field"><span>Latitudine</span><input type="text" name="schema_latitude" value="<?php echo esc_attr( $options['schema_latitude'] ); ?>" placeholder="45.4642"></label>
                            <label class="ai-fr-field"><span>Longitudine</span><input type="text" name="schema_longitude" value="<?php echo esc_attr( $options['schema_longitude'] ); ?>" placeholder="9.1900"></label>
                            <label class="ai-fr-field"><span>Accesso con trasporto pubblico</span><textarea name="schema_public_transportation_access" rows="3" placeholder="Metro M2, fermata ..."><?php echo esc_textarea( $options['schema_public_transportation_access'] ); ?></textarea></label>
                            <input type="hidden" name="schema_contact_type" value="">
                            <input type="hidden" name="schema_contact_email" value="">
                            <input type="hidden" name="schema_contact_languages" value="">
                        </div>
                    </article>

                    <article class="ai-fr-schema-card ai-fr-schema-card-wide" data-entity-scope="organization">
                        <div class="ai-fr-schema-card-head"><h4>Reparti e contatti</h4><p>ContactPoint ripetibili con telefono, email, lingue e orari specifici.</p></div>
                        <div class="ai-fr-schema-repeaters" data-repeater="contacts">
                            <?php foreach ( $schema_contacts as $index => $contact ) : ?>
                                <div class="ai-fr-schema-repeater-row ai-fr-schema-repeater-grid">
                                    <input data-field="contactType" name="schema_contacts[<?php echo esc_attr( $index ); ?>][contactType]" value="<?php echo esc_attr( $contact['contactType'] ?? '' ); ?>" placeholder="segreteria corsi">
                                    <input data-field="telephone" name="schema_contacts[<?php echo esc_attr( $index ); ?>][telephone]" value="<?php echo esc_attr( $contact['telephone'] ?? '' ); ?>" placeholder="+39 02 ...">
                                    <input type="email" data-field="email" name="schema_contacts[<?php echo esc_attr( $index ); ?>][email]" value="<?php echo esc_attr( $contact['email'] ?? '' ); ?>" placeholder="email@example.com">
                                    <input data-field="availableLanguage" name="schema_contacts[<?php echo esc_attr( $index ); ?>][availableLanguage]" value="<?php echo esc_attr( $contact['availableLanguage'] ?? '' ); ?>" placeholder="it, en">
                                    <input data-field="hoursAvailable" name="schema_contacts[<?php echo esc_attr( $index ); ?>][hoursAvailable]" value="<?php echo esc_attr( $contact['hoursAvailable'] ?? '' ); ?>" placeholder="Mo-Fr 09:00-18:00">
                                    <button type="button" class="button-link-delete ai-fr-repeater-remove">Rimuovi</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="button button-secondary ai-fr-repeater-add" data-target="contacts">Aggiungi contatto</button>
                    </article>

                    <article class="ai-fr-schema-card ai-fr-schema-card-wide" data-entity-scope="organization">
                        <div class="ai-fr-schema-card-head"><h4>Orari di apertura</h4><p>Genera <code>openingHoursSpecification</code> sul nodo della sede fisica.</p></div>
                        <div class="ai-fr-schema-repeaters" data-repeater="hours">
                            <?php foreach ( $schema_opening_hours as $index => $hours ) : ?>
                                <div class="ai-fr-schema-repeater-row ai-fr-schema-repeater-grid">
                                    <input data-field="dayOfWeek" name="schema_opening_hours[<?php echo esc_attr( $index ); ?>][dayOfWeek]" value="<?php echo esc_attr( $hours['dayOfWeek'] ?? '' ); ?>" placeholder="Monday, Tuesday">
                                    <input type="time" data-field="opens" name="schema_opening_hours[<?php echo esc_attr( $index ); ?>][opens]" value="<?php echo esc_attr( $hours['opens'] ?? '' ); ?>">
                                    <input type="time" data-field="closes" name="schema_opening_hours[<?php echo esc_attr( $index ); ?>][closes]" value="<?php echo esc_attr( $hours['closes'] ?? '' ); ?>">
                                    <input type="date" data-field="validFrom" name="schema_opening_hours[<?php echo esc_attr( $index ); ?>][validFrom]" value="<?php echo esc_attr( $hours['validFrom'] ?? '' ); ?>">
                                    <input type="date" data-field="validThrough" name="schema_opening_hours[<?php echo esc_attr( $index ); ?>][validThrough]" value="<?php echo esc_attr( $hours['validThrough'] ?? '' ); ?>">
                                    <button type="button" class="button-link-delete ai-fr-repeater-remove">Rimuovi</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="button button-secondary ai-fr-repeater-add" data-target="hours">Aggiungi fascia oraria</button>
                    </article>

                    <article class="ai-fr-schema-card ai-fr-schema-card-wide" data-entity-scope="organization">
                        <div class="ai-fr-schema-card-head">
                            <h4>Fondatori</h4>
                            <p>Uno per riga nel formato `Nome | ruolo attuale`. Il ruolo è opzionale: omettilo se non è verificato o aggiornato.</p>
                        </div>
                        <label class="ai-fr-field">
                            <span>Persone fondatrici</span>
                            <textarea name="schema_founders" rows="4" placeholder="Mario Rossi&#10;Laura Bianchi | CEO"><?php echo esc_textarea( $options['schema_founders'] ); ?></textarea>
                        </label>
                    </article>

                    <article class="ai-fr-schema-card ai-fr-schema-card-wide">
                        <div class="ai-fr-schema-card-head">
                            <h4>Descrizioni</h4>
                            <p>Usale per chiarire chi sei e distinguerti da entità simili.</p>
                        </div>
                        <div class="ai-fr-schema-fields">
                            <label class="ai-fr-field">
                                <span>Descrizione</span>
                                <textarea name="schema_description" rows="4"><?php echo esc_textarea( $options['schema_description'] ); ?></textarea>
                            </label>
                            <label class="ai-fr-field">
                                <span>Descrizione disambiguante</span>
                                <input type="text" name="schema_disambiguating_description" value="<?php echo esc_attr( $options['schema_disambiguating_description'] ); ?>">
                            </label>
                            <label class="ai-fr-field" data-entity-scope="organization">
                                <span>Slogan</span>
                                <input type="text" name="schema_slogan" value="<?php echo esc_attr( $options['schema_slogan'] ); ?>" placeholder="E-problem solving: sviluppo web fuori dagli schemi.">
                            </label>
                            <label class="ai-fr-field" data-entity-scope="organization">
                                <span>Data fondazione</span>
                                <input type="text" name="schema_founding_date" value="<?php echo esc_attr( $options['schema_founding_date'] ); ?>" placeholder="2015">
                            </label>
                        </div>
                    </article>

                    <article class="ai-fr-schema-card ai-fr-schema-card-wide">
                        <div class="ai-fr-schema-card-head">
                            <h4>Profili e competenze</h4>
                            <p>Una voce per riga. Sono i campi più utili per la disambiguazione.</p>
                        </div>
                        <div class="ai-fr-schema-fields">
                            <label class="ai-fr-field">
                                <span>sameAs / profili esterni</span>
                                <textarea name="schema_same_as" rows="4" placeholder="https://www.linkedin.com/in/...&#10;https://github.com/..."><?php echo esc_textarea( $options['schema_same_as'] ); ?></textarea>
                            </label>
                            <label class="ai-fr-field">
                                <span>knowsAbout</span>
                                <textarea name="schema_knows_about" rows="4" placeholder="SEO tecnico&#10;AI content strategy"><?php echo esc_textarea( $options['schema_knows_about'] ); ?></textarea>
                            </label>
                            <label class="ai-fr-field">
                                <span>knowsLanguage</span>
                                <textarea name="schema_knows_language" rows="3" placeholder="it-IT&#10;en-US"><?php echo esc_textarea( $options['schema_knows_language'] ); ?></textarea>
                            </label>
                            <label class="ai-fr-field" data-entity-scope="organization">
                                <span>areaServed</span>
                                <textarea name="schema_area_served" rows="3" placeholder="City: Esempiopoli&#10;Country: Italia"><?php echo esc_textarea( $options['schema_area_served'] ); ?></textarea>
                            </label>
                        </div>
                    </article>

                    <article class="ai-fr-schema-card ai-fr-schema-card-wide" data-entity-scope="organization">
                        <div class="ai-fr-schema-card-head"><h4>Certificazioni</h4><p>Accreditamenti, norme ISO e iscrizioni ad albi come nodi <code>Certification</code>.</p></div>
                        <div class="ai-fr-schema-repeaters" data-repeater="certifications">
                            <?php foreach ( $schema_certifications as $index => $certification ) : ?>
                                <div class="ai-fr-schema-repeater-row ai-fr-schema-repeater-grid">
                                    <input data-field="name" name="schema_certifications[<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $certification['name'] ?? '' ); ?>" placeholder="ISO 9001">
                                    <input data-field="identifier" name="schema_certifications[<?php echo esc_attr( $index ); ?>][identifier]" value="<?php echo esc_attr( $certification['identifier'] ?? '' ); ?>" placeholder="Certificato n.">
                                    <input data-field="issuedBy" name="schema_certifications[<?php echo esc_attr( $index ); ?>][issuedBy]" value="<?php echo esc_attr( $certification['issuedBy'] ?? '' ); ?>" placeholder="Ente certificatore">
                                    <input type="url" data-field="url" name="schema_certifications[<?php echo esc_attr( $index ); ?>][url]" value="<?php echo esc_attr( $certification['url'] ?? '' ); ?>" placeholder="https://...">
                                    <button type="button" class="button-link-delete ai-fr-repeater-remove">Rimuovi</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="button button-secondary ai-fr-repeater-add" data-target="certifications">Aggiungi certificazione</button>
                    </article>

                    <article class="ai-fr-schema-card ai-fr-schema-card-wide" data-entity-scope="organization">
                        <div class="ai-fr-schema-card-head"><h4>Identificatori aggiuntivi</h4><p>Coppie chiave-valore per RUNTS, REA, ATECO e registri di settore.</p></div>
                        <div class="ai-fr-schema-repeaters" data-repeater="identifiers">
                            <?php foreach ( $schema_identifiers as $index => $identifier ) : ?>
                                <div class="ai-fr-schema-repeater-row">
                                    <input data-field="propertyID" name="schema_identifiers[<?php echo esc_attr( $index ); ?>][propertyID]" value="<?php echo esc_attr( $identifier['propertyID'] ?? '' ); ?>" placeholder="RUNTS">
                                    <input data-field="value" name="schema_identifiers[<?php echo esc_attr( $index ); ?>][value]" value="<?php echo esc_attr( $identifier['value'] ?? '' ); ?>" placeholder="Numero identificativo">
                                    <button type="button" class="button-link-delete ai-fr-repeater-remove">Rimuovi</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="button button-secondary ai-fr-repeater-add" data-target="identifiers">Aggiungi identificatore</button>
                    </article>

                    <article class="ai-fr-schema-card ai-fr-schema-card-wide" data-entity-scope="organization">
                        <div class="ai-fr-schema-card-head">
                            <h4>Catalogo servizi</h4>
                            <p>Aggiunge un <code>OfferCatalog</code>. Le sorgenti WordPress compilano automaticamente nome, URL e descrizione; le righe manuali restano disponibili per integrazioni.</p>
                        </div>
                        <div class="ai-fr-field ai-fr-schema-source-field">
                            <span>Sorgenti WordPress</span>
                            <div class="ai-fr-schema-repeaters" data-repeater="offerSources">
                                <?php foreach ( $schema_offer_sources as $index => $source ) : ?>
                                    <div class="ai-fr-schema-repeater-row">
                                        <input data-field="value" name="schema_offer_sources[<?php echo esc_attr( $index ); ?>]" value="<?php echo esc_attr( $source ); ?>" placeholder="ID termine, taxonomy:slug o permalink WordPress">
                                        <button type="button" class="button-link-delete ai-fr-repeater-remove">Rimuovi</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="button button-secondary ai-fr-repeater-add" data-target="offerSources">Aggiungi sorgente</button>
                            <small>Accetta ID di termini, <code>taxonomy:slug</code>, permalink di categorie/tassonomie e permalink di pagine o CPT.</small>
                        </div>
                        <div class="ai-fr-schema-subsection-head">
                            <strong>Voci manuali</strong>
                            <span>Usale per completare o sostituire i dati ricavati dalle sorgenti WordPress.</span>
                        </div>
                        <div class="ai-fr-schema-services" id="ai-fr-schema-services">
                            <?php foreach ( $schema_services as $index => $service ) : ?>
                                <div class="ai-fr-schema-service" data-service-index="<?php echo esc_attr( $index ); ?>">
                                    <div class="ai-fr-schema-service-head">
                                        <strong>Servizio</strong>
                                        <button type="button" class="button button-link-delete ai-fr-schema-service-remove">Rimuovi</button>
                                    </div>
                                    <div class="ai-fr-schema-service-grid">
                                        <label class="ai-fr-field">
                                            <span>Nome</span>
                                            <input type="text" data-service-field="name" name="schema_services[<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $service['name'] ?? '' ); ?>" placeholder="UX e Graphic Design">
                                        </label>
                                        <label class="ai-fr-field">
                                            <span>URL pagina</span>
                                            <input type="url" data-service-field="url" name="schema_services[<?php echo esc_attr( $index ); ?>][url]" value="<?php echo esc_attr( $service['url'] ?? '' ); ?>" placeholder="https://example.com/servizio/">
                                        </label>
                                        <label class="ai-fr-field">
                                            <span>Tipo servizio</span>
                                            <input type="text" data-service-field="serviceType" name="schema_services[<?php echo esc_attr( $index ); ?>][serviceType]" value="<?php echo esc_attr( $service['serviceType'] ?? '' ); ?>" placeholder="Web design, UX/UI design">
                                        </label>
                                        <label class="ai-fr-field">
                                            <span>Area servita</span>
                                            <input type="text" data-service-field="areaServed" name="schema_services[<?php echo esc_attr( $index ); ?>][areaServed]" value="<?php echo esc_attr( $service['areaServed'] ?? '' ); ?>" placeholder="Italia">
                                        </label>
                                        <label class="ai-fr-field ai-fr-schema-service-description">
                                            <span>Descrizione</span>
                                            <textarea data-service-field="description" name="schema_services[<?php echo esc_attr( $index ); ?>][description]" rows="3" placeholder="Descrizione breve del servizio."><?php echo esc_textarea( $service['description'] ?? '' ); ?></textarea>
                                        </label>
                                        <label class="ai-fr-field">
                                            <span>Prezzo</span>
                                            <input type="text" data-service-field="price" name="schema_services[<?php echo esc_attr( $index ); ?>][price]" value="<?php echo esc_attr( $service['price'] ?? '' ); ?>" placeholder="0">
                                        </label>
                                        <label class="ai-fr-field">
                                            <span>Valuta</span>
                                            <input type="text" data-service-field="priceCurrency" name="schema_services[<?php echo esc_attr( $index ); ?>][priceCurrency]" value="<?php echo esc_attr( $service['priceCurrency'] ?? '' ); ?>" placeholder="EUR">
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="button button-secondary" id="ai-fr-schema-service-add">Aggiungi servizio</button>
                    </article>

                    <article class="ai-fr-schema-card ai-fr-schema-card-seven">
                        <div class="ai-fr-schema-card-head">
                            <h4>Realizzazione del sito</h4>
                            <p>Indica facoltativamente la persona o l'organizzazione che ha sviluppato il sito. Il dato viene pubblicato come <code>creator</code> del nodo <code>WebSite</code>.</p>
                        </div>
                        <div class="ai-fr-schema-fields">
                            <label class="ai-fr-field ai-fr-field-short">
                                <span>Tipo</span>
                                <select name="schema_creator_type">
                                    <option value="Organization" <?php selected( $options['schema_creator_type'], 'Organization' ); ?>>Organization</option>
                                    <option value="Person" <?php selected( $options['schema_creator_type'], 'Person' ); ?>>Person</option>
                                </select>
                            </label>
                            <label class="ai-fr-field">
                                <span>Nome sviluppatore / agenzia</span>
                                <input type="text" name="schema_creator_name" value="<?php echo esc_attr( $options['schema_creator_name'] ); ?>" placeholder="Nome agenzia o professionista">
                            </label>
                            <label class="ai-fr-field">
                                <span>URL</span>
                                <input type="url" name="schema_creator_url" value="<?php echo esc_attr( $options['schema_creator_url'] ); ?>" placeholder="https://www.esempio.it/">
                            </label>
                        </div>
                    </article>

                    <article class="ai-fr-schema-card ai-fr-schema-card-five">
                        <div class="ai-fr-schema-card-head">
                            <h4>Profilo e licenza</h4>
                            <p>Collega una pagina profilo e una licenza riutilizzabile sui contenuti.</p>
                        </div>
                        <div class="ai-fr-schema-fields">
                            <label class="ai-fr-field">
                                <span>Pagina ProfilePage</span>
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
                                <span>License URL</span>
                                <input type="url" name="schema_license" value="<?php echo esc_attr( $options['schema_license'] ); ?>" placeholder="https://creativecommons.org/licenses/by/4.0/">
                            </label>
                        </div>
                    </article>
                </div>
            </section>

            <section id="ai-fr-section-automation" class="ai-fr-section">
                <div class="ai-fr-section-heading"><span>05</span><div><h3>Automation</h3><p>Controlla generazione, frequenza, notifiche e cronologia operativa.</p></div></div>
                <div class="ai-fr-settings-cards">
                    <article class="ai-fr-settings-card">
                        <div class="ai-fr-card-head"><span>01</span><div><h4>Output statico</h4><p>Persistenza e spazio utilizzato dai file Markdown.</p></div></div>
                        <div class="ai-fr-option-list">
                            <label>
                                <input type="checkbox" id="ai-fr-static-md-files" name="static_md_files" value="1" <?php checked( $options['static_md_files'] ); ?>>
                                Salva e servi file MD statici
                            </label>
                            <p class="description ai-fr-statline">
                                File salvati: <?php echo intval( $version_stats['count'] ); ?> |
                                Spazio: <?php echo esc_html( size_format( intval( $version_stats['size'] ) ) ); ?>
                            </p>
                        </div>
                    </article>
                    <article class="ai-fr-settings-card">
                        <div class="ai-fr-card-head"><span>02</span><div><h4>Rigenerazione automatica</h4><p>Programmazione e dimensione dei batch.</p></div></div>
                        <div class="ai-fr-option-list">
                            <label>
                                <input type="checkbox" id="ai-fr-auto-regenerate" name="auto_regenerate" value="1" <?php checked( $options['auto_regenerate'] ); ?>>
                                Rigenera i file .md ad intervallo su tutto il sito (cron)
                            </label>
                            <div class="ai-fr-number-fields">
                                <label>Intervallo (ore)<input type="number" name="regenerate_interval" min="1" max="168" value="<?php echo esc_attr( $options['regenerate_interval'] ); ?>"></label>
                                <label>Contenuti per esecuzione<input type="number" name="regenerate_batch_size" min="10" max="1000" value="<?php echo esc_attr( intval( $options['regenerate_batch_size'] ?? 100 ) ); ?>"></label>
                            </div>
                            <p class="description">Per siti molto grandi, il cron processa solo questo numero di contenuti per run e continua dal successivo.</p>
                            <?php if ( $next_cron ) : ?>
                                <p class="description">Prossima esecuzione: <?php echo esc_html( date_i18n( 'Y-m-d H:i:s', $next_cron ) ); ?></p>
                            <?php endif; ?>
                        </div>
                    </article>
                    <article class="ai-fr-settings-card">
                        <div class="ai-fr-card-head"><span>03</span><div><h4>Trigger su eventi</h4><p>Decidi quando un contenuto deve aggiornare l’output.</p></div></div>
                        <div class="ai-fr-option-list">
                            <label>
                                <input type="checkbox" name="regenerate_on_save" value="1" <?php checked( $options['regenerate_on_save'] ); ?>>
                                Rigenera quando un contenuto viene salvato/aggiornato
                            </label>
                            <label>
                                <input type="checkbox" name="regenerate_on_change" value="1" <?php checked( $options['regenerate_on_change'] ); ?>>
                                Rigenera solo se il contenuto e cambiato (checksum)
                            </label>
                        </div>
                    </article>
                    <article class="ai-fr-settings-card">
                        <div class="ai-fr-card-head"><span>04</span><div><h4>Notifiche</h4><p>Segnala gli errori agli amministratori.</p></div></div>
                        <div class="ai-fr-option-list">
                            <label>
                                <input type="checkbox" name="notify_admin_notice" value="1" <?php checked( $options['notify_admin_notice'] ?? '' ); ?>>
                                Mostra notice admin quando una rigenerazione ha errori
                            </label>
                            <label>
                                <input type="checkbox" name="notify_email" value="1" <?php checked( $options['notify_email'] ?? '' ); ?>>
                                Invia email in caso di errori rigenerazione
                            </label>
                            <label class="ai-fr-stacked-label">Email destinatario
                                <input type="email" name="notify_email_to" value="<?php echo esc_attr( $options['notify_email_to'] ?? '' ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
                            </label>
                        </div>
                    </article>
                    <article class="ai-fr-settings-card ai-fr-settings-card-wide">
                        <div class="ai-fr-card-head"><span>05</span><div><h4>Azioni manuali</h4><p>Genera, forza o rimuovi i file statici.</p></div></div>
                        <div class="ai-fr-action-row">
                            <button type="button" id="ai-fr-regenerate" class="button button-primary">Rigenera tutti i file MD</button>
                            <button type="button" id="ai-fr-regenerate-force" class="button">Forza rigenerazione</button>
                            <button type="button" id="ai-fr-clear-versions" class="button ai-fr-button-danger">Elimina tutti i file</button>
                            <p id="ai-fr-action-status" role="status" aria-live="polite"></p>
                            <?php if ( ! empty( $last_regen['stats'] ) ) : ?>
                                <p class="description">
                                    Ultimo run: Processati <?php echo intval( $last_regen['stats']['processed'] ?? 0 ); ?>,
                                    Rigenerati <?php echo intval( $last_regen['stats']['regenerated'] ?? 0 ); ?>,
                                    Saltati <?php echo intval( $last_regen['stats']['skipped'] ?? 0 ); ?>,
                                    Errori <?php echo intval( $last_regen['stats']['errors'] ?? 0 ); ?>.
                                </p>
                            <?php endif; ?>
                        </div>
                    </article>
                </div>

                <div class="ai-fr-timeline">
                    <h3>Timeline aggiornamenti</h3>
                    <button type="button" id="ai-fr-refresh-timeline" class="button button-secondary">Aggiorna timeline</button>
                    <ul id="ai-fr-timeline-list" class="ai-fr-list"></ul>
                </div>
            </section>

            <div class="ai-fr-submit-wrap" id="ai-fr-submit-wrap" aria-live="polite">
                <span id="ai-fr-dirty-state">Tutte le modifiche sono salvate</span>
                <input type="submit" name="ai_fr_save" class="button button-primary" value="Salva impostazioni">
            </div>
        </form>
    </div>
    <?php
}

add_filter(
    'plugin_action_links_' . plugin_basename( AI_FR_PLUGIN_FILE ),
    function ( array $links ): array {
        $settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=ai-friendly' ) ) . '">Impostazioni</a>';
        $github_link   = '<a href="https://github.com/Sernicola-Labs-Srl/ai-friendly" target="_blank" rel="noopener noreferrer">GitHub</a>';
        array_unshift( $links, $settings_link, $github_link );
        return $links;
    }
);
