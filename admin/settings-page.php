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
            'sernicola-labs-ai-friendly',
            'saifr_render_options_page'
        );
    }
);

add_action(
    'admin_enqueue_scripts',
    function ( string $hook ): void {
        if ( $hook !== 'settings_page_sernicola-labs-ai-friendly' ) {
            return;
        }

        $admin_css_path = SAIFR_PLUGIN_DIR . '/admin/assets/saifr-admin.css';
        $admin_js_path  = SAIFR_PLUGIN_DIR . '/admin/assets/saifr-admin.js';
        $admin_css_version = SAIFR_VERSION . '-' . (string) ( file_exists( $admin_css_path ) ? filemtime( $admin_css_path ) : '0' );
        $admin_js_version  = SAIFR_VERSION . '-' . (string) ( file_exists( $admin_js_path ) ? filemtime( $admin_js_path ) : '0' );

        wp_enqueue_style(
            'saifr-admin',
            plugins_url( 'admin/assets/saifr-admin.css', SAIFR_PLUGIN_FILE ),
            [],
            $admin_css_version
        );

        wp_enqueue_script(
            'saifr-admin',
            plugins_url( 'admin/assets/saifr-admin.js', SAIFR_PLUGIN_FILE ),
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
                'saifr-admin',
                'SaifrCodeEditor',
                [ 'settings' => $editor ]
            );
        }

        wp_localize_script(
            'saifr-admin',
            'SaifrAdmin',
            [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'saifr_admin_nonce' ),
                'relatedTypes' => array_keys( saifr_schema_related_entity_types() ),
                'i18n'    => [
                    'loading'             => __( 'Loading...', 'sernicola-labs-ai-friendly' ),
                    'error'               => __( 'An error occurred.', 'sernicola-labs-ai-friendly' ),
                    'noHeadings'          => __( 'No headings found.', 'sernicola-labs-ai-friendly' ),
                    'noWarnings'          => __( 'No notices.', 'sernicola-labs-ai-friendly' ),
                    'included'            => __( 'Included', 'sernicola-labs-ai-friendly' ),
                    'excluded'            => __( 'Excluded', 'sernicola-labs-ai-friendly' ),
                    'include'             => __( 'Include', 'sernicola-labs-ai-friendly' ),
                    'excludeContent'      => __( 'Exclude', 'sernicola-labs-ai-friendly' ),
                    'untitled'            => __( '(Untitled)', 'sernicola-labs-ai-friendly' ),
                    'noContent'           => __( 'No content.', 'sernicola-labs-ai-friendly' ),
                    'page'                => __( 'Page', 'sernicola-labs-ai-friendly' ),
                    'noEvents'            => __( 'No events.', 'sernicola-labs-ai-friendly' ),
                    'restore'             => __( 'Restore', 'sernicola-labs-ai-friendly' ),
                    'noSnapshots'         => __( 'No snapshots.', 'sernicola-labs-ai-friendly' ),
                    'selectTwoSnapshots'  => __( 'Select exactly 2 snapshots.', 'sernicola-labs-ai-friendly' ),
                    'noDifferences'       => __( 'No differences.', 'sernicola-labs-ai-friendly' ),
                    'remove'              => __( 'Remove', 'sernicola-labs-ai-friendly' ),
                    'noImage'             => __( 'No image', 'sernicola-labs-ai-friendly' ),
                    'noLogo'              => __( 'No logo', 'sernicola-labs-ai-friendly' ),
                    'saving'              => __( 'Saving…', 'sernicola-labs-ai-friendly' ),
                    'saveAndGenerate'      => __( 'Save and generate', 'sernicola-labs-ai-friendly' ),
                    'setupComplete'        => __( 'Setup complete. The output has been verified.', 'sernicola-labs-ai-friendly' ),
                    'requestFailed'        => __( 'Unable to complete the request. Try again without losing your choices.', 'sernicola-labs-ai-friendly' ),
                    'regenerationComplete' => __( 'Regeneration complete.', 'sernicola-labs-ai-friendly' ),
                    'forcedComplete'       => __( 'Forced regeneration complete.', 'sernicola-labs-ai-friendly' ),
                    'confirmDeleteFiles'   => __( 'Delete all saved Markdown files?', 'sernicola-labs-ai-friendly' ),
                    'service'              => __( 'Service', 'sernicola-labs-ai-friendly' ),
                    'serviceIdentity'      => __( 'Service identity', 'sernicola-labs-ai-friendly' ),
                    'offer'                => __( 'Offer', 'sernicola-labs-ai-friendly' ),
                    'manualOnly'           => __( 'Manual only', 'sernicola-labs-ai-friendly' ),
                    'name'                 => __( 'Name', 'sernicola-labs-ai-friendly' ),
                    'pageUrl'              => __( 'Page URL', 'sernicola-labs-ai-friendly' ),
                    'serviceType'          => __( 'Service type', 'sernicola-labs-ai-friendly' ),
                    'areaServed'           => __( 'Area served', 'sernicola-labs-ai-friendly' ),
                    'description'          => __( 'Description', 'sernicola-labs-ai-friendly' ),
                    'price'                => __( 'Price', 'sernicola-labs-ai-friendly' ),
                    'currency'             => __( 'Currency', 'sernicola-labs-ai-friendly' ),
                    'noManualServices'     => __( 'No manual services configured.', 'sernicola-labs-ai-friendly' ),
                    'additionalType'       => __( 'Additional type', 'sernicola-labs-ai-friendly' ),
                    'schemaType'           => __( 'Schema.org type', 'sernicola-labs-ai-friendly' ),
                    'contact'              => __( 'Contact', 'sernicola-labs-ai-friendly' ),
                    'department'           => __( 'Department / function', 'sernicola-labs-ai-friendly' ),
                    'phone'                => __( 'Phone', 'sernicola-labs-ai-friendly' ),
                    'languages'            => __( 'Languages', 'sernicola-labs-ai-friendly' ),
                    'availability'         => __( 'Availability', 'sernicola-labs-ai-friendly' ),
                    'timeSlot'             => __( 'Time slot', 'sernicola-labs-ai-friendly' ),
                    'days'                 => __( 'Days', 'sernicola-labs-ai-friendly' ),
                    'opens'                => __( 'Opens', 'sernicola-labs-ai-friendly' ),
                    'closes'               => __( 'Closes', 'sernicola-labs-ai-friendly' ),
                    'validFrom'            => __( 'Valid from', 'sernicola-labs-ai-friendly' ),
                    'validThrough'         => __( 'Valid until', 'sernicola-labs-ai-friendly' ),
                    'certification'        => __( 'Certification', 'sernicola-labs-ai-friendly' ),
                    'identifier'           => __( 'Identifier', 'sernicola-labs-ai-friendly' ),
                    'source'               => __( 'WordPress source', 'sernicola-labs-ai-friendly' ),
                    'noConfiguredItem'     => __( 'No items configured.', 'sernicola-labs-ai-friendly' ),
                    'none'                 => __( 'None', 'sernicola-labs-ai-friendly' ),
                    'staticOutput'         => __( 'Static Markdown files', 'sernicola-labs-ai-friendly' ),
                    'dynamicOutput'        => __( 'Dynamic output', 'sernicola-labs-ai-friendly' ),
                    'scheduledDisabled'    => __( 'Scheduled regeneration disabled', 'sernicola-labs-ai-friendly' ),
                    'disabled'             => __( 'Disabled', 'sernicola-labs-ai-friendly' ),
                    'enabled'              => __( 'Enabled', 'sernicola-labs-ai-friendly' ),
                    'acfFields'            => __( 'ACF fields', 'sernicola-labs-ai-friendly' ),
                    'includedContent'      => __( 'Included content', 'sernicola-labs-ai-friendly' ),
                    'output'               => __( 'Output', 'sernicola-labs-ai-friendly' ),
                    'automation'           => __( 'Automation', 'sernicola-labs-ai-friendly' ),
                    'semanticSchema'       => __( 'Semantic Schema', 'sernicola-labs-ai-friendly' ),
                    'selectIdentityImage'  => __( 'Select identity image', 'sernicola-labs-ai-friendly' ),
                    'useImage'             => __( 'Use this image', 'sernicola-labs-ai-friendly' ),
                    'selectLogo'           => __( 'Select company logo', 'sernicola-labs-ai-friendly' ),
                    'useLogo'              => __( 'Use this logo', 'sernicola-labs-ai-friendly' ),
                    'issuesSingular'        => __( 'issue', 'sernicola-labs-ai-friendly' ),
                    'issuesPlural'          => __( 'issues', 'sernicola-labs-ai-friendly' ),
                    'sitemap'               => __( 'Sitemap', 'sernicola-labs-ai-friendly' ),
                    'robots'                => __( 'Robots', 'sernicola-labs-ai-friendly' ),
                    'notAvailable'          => __( 'n/a', 'sernicola-labs-ai-friendly' ),
                    'token'                 => __( 'tokens', 'sernicola-labs-ai-friendly' ),
                    'lines'                 => __( 'Lines', 'sernicola-labs-ai-friendly' ),
                    'tokenDelta'            => __( 'Token delta', 'sernicola-labs-ai-friendly' ),
                    'score'                 => __( 'Score', 'sernicola-labs-ai-friendly' ),
                    'duplicates'            => __( 'Duplicates', 'sernicola-labs-ai-friendly' ),
                    'certificateNumber'     => __( 'Certificate number', 'sernicola-labs-ai-friendly' ),
                    'certificationIssuer'   => __( 'Certifying body', 'sernicola-labs-ai-friendly' ),
                    'identifierNumber'      => __( 'Identification number', 'sernicola-labs-ai-friendly' ),
                    'sourceReference'       => __( 'Term ID, taxonomy:slug, or WordPress permalink', 'sernicola-labs-ai-friendly' ),
                    'value'                 => __( 'Value', 'sernicola-labs-ai-friendly' ),
                    'noAdditionalTypes'     => __( 'No additional types configured.', 'sernicola-labs-ai-friendly' ),
                    'noContacts'            => __( 'No contacts configured.', 'sernicola-labs-ai-friendly' ),
                    'noTimeSlots'           => __( 'No time slots configured.', 'sernicola-labs-ai-friendly' ),
                    'noCertifications'      => __( 'No certifications configured.', 'sernicola-labs-ai-friendly' ),
                    'noIdentifiers'         => __( 'No additional identifiers configured.', 'sernicola-labs-ai-friendly' ),
                    'noOfferSources'        => __( 'No WordPress sources configured.', 'sernicola-labs-ai-friendly' ),
                    'shippingRule'          => __( 'Shipping rule', 'sernicola-labs-ai-friendly' ),
                    'shippingCost'          => __( 'Cost', 'sernicola-labs-ai-friendly' ),
                    'handlingTime'          => __( 'Handling', 'sernicola-labs-ai-friendly' ),
                    'transitTime'           => __( 'Delivery', 'sernicola-labs-ai-friendly' ),
                    'noShippingRules'       => __( 'No shipping rules configured.', 'sernicola-labs-ai-friendly' ),
                    'countries'             => __( 'Countries (ISO codes)', 'sernicola-labs-ai-friendly' ),
                    'shippingRate'          => __( 'Shipping cost', 'sernicola-labs-ai-friendly' ),
                    'freeThreshold'         => __( 'Free from (price)', 'sernicola-labs-ai-friendly' ),
                    'handlingMin'           => __( 'Handling min (days)', 'sernicola-labs-ai-friendly' ),
                    'handlingMax'           => __( 'Handling max (days)', 'sernicola-labs-ai-friendly' ),
                    'transitMin'            => __( 'Delivery min (days)', 'sernicola-labs-ai-friendly' ),
                    'transitMax'            => __( 'Delivery max (days)', 'sernicola-labs-ai-friendly' ),
                    'relatedEntity'         => __( 'Related entity', 'sernicola-labs-ai-friendly' ),
                    'noRelatedEntities'     => __( 'No related entities configured.', 'sernicola-labs-ai-friendly' ),
                    'entityType'            => __( 'Type', 'sernicola-labs-ai-friendly' ),
                    'issnOrIdentifier'      => __( 'ISSN / ISBN / identifier', 'sernicola-labs-ai-friendly' ),
                    'sameAsProfiles'        => __( 'sameAs (one per line)', 'sernicola-labs-ai-friendly' ),
                    'vatIncluded'           => __( 'VAT', 'sernicola-labs-ai-friendly' ),
                    'vatUnspecified'        => __( 'Not specified', 'sernicola-labs-ai-friendly' ),
                    'vatYes'                => __( 'VAT included', 'sernicola-labs-ai-friendly' ),
                    'vatNo'                 => __( 'VAT excluded', 'sernicola-labs-ai-friendly' ),
                    'billingPeriod'         => __( 'Frequency', 'sernicola-labs-ai-friendly' ),
                    'billingOnce'           => __( 'One-time / not specified', 'sernicola-labs-ai-friendly' ),
                    'billingMonth'          => __( 'Monthly', 'sernicola-labs-ai-friendly' ),
                    'billingYear'           => __( 'Yearly', 'sernicola-labs-ai-friendly' ),
                    'every'                 => __( 'Every', 'sernicola-labs-ai-friendly' ),
                    'hoursBatch'            => __( 'hours, batches of', 'sernicola-labs-ai-friendly' ),
                    'nameNotProvided'       => __( 'name not specified', 'sernicola-labs-ai-friendly' ),
                    'savingAndVerifying'    => __( 'Saving settings and verifying the output.', 'sernicola-labs-ai-friendly' ),
                    'generationFailed'      => __( 'Generation failed. The saved settings remain active.', 'sernicola-labs-ai-friendly' ),
                    'batchComplete'         => __( 'Batch complete.', 'sernicola-labs-ai-friendly' ),
                    'processed'             => __( 'Processed', 'sernicola-labs-ai-friendly' ),
                    'regenerated'           => __( 'regenerated', 'sernicola-labs-ai-friendly' ),
                    'regenProgress'         => __( 'Regeneration: %1$s/%2$s items processed, %3$s errors.', 'sernicola-labs-ai-friendly' ),
                    'deletedFiles'          => __( 'Files deleted', 'sernicola-labs-ai-friendly' ),
                    'unsavedChanges'        => __( 'Unsaved changes', 'sernicola-labs-ai-friendly' ),
                    'allChangesSaved'       => __( 'All changes saved', 'sernicola-labs-ai-friendly' ),
                    'serviceDescription'    => __( 'Short description of the service.', 'sernicola-labs-ai-friendly' ),
                ],
            ]
        );
    }
);

function saifr_admin_require_permissions(): void {
    check_ajax_referer( 'saifr_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'Insufficient permissions.', 'sernicola-labs-ai-friendly' ) );
    }
}

function saifr_post_bool( string $key ): bool {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is validated by the AJAX or settings-page caller.
    $value = isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) ) : '';
    return in_array( strtolower( $value ), [ '1', 'true', 'on', 'yes' ], true );
}

function saifr_post_int( string $key, int $default = 0 ): int {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is validated by the AJAX or settings-page caller.
    return isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? absint( wp_unslash( (string) $_POST[ $key ] ) ) : $default;
}

function saifr_post_key( string $key, string $default = '' ): string {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is validated by the AJAX caller.
    return isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? sanitize_key( wp_unslash( (string) $_POST[ $key ] ) ) : $default;
}

function saifr_post_text( string $key, string $default = '' ): string {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is validated by the AJAX or settings-page caller.
    return isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) ) : $default;
}

function saifr_post_textarea( string $key, string $default = '' ): string {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is validated by the AJAX or settings-page caller.
    return isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST[ $key ] ) ) : $default;
}

function saifr_post_email( string $key, string $default = '' ): string {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is validated by the settings-page caller.
    return isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? sanitize_email( wp_unslash( (string) $_POST[ $key ] ) ) : $default;
}

function saifr_post_url( string $key, string $default = '' ): string {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is validated by the settings-page caller.
    return isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? esc_url_raw( wp_unslash( (string) $_POST[ $key ] ) ) : $default;
}

function saifr_post_array( string $key ): array {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is validated by the AJAX or settings-page caller.
    return isset( $_POST[ $key ] ) && is_array( $_POST[ $key ] ) ? map_deep( wp_unslash( $_POST[ $key ] ), 'sanitize_textarea_field' ) : [];
}

function saifr_admin_sanitize_schema_services( array $rows ): array {
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
            'vatIncluded'   => in_array( $row['vatIncluded'] ?? '', [ 'yes', 'no' ], true ) ? (string) $row['vatIncluded'] : '',
            'billingPeriod' => in_array( $row['billingPeriod'] ?? '', [ 'month', 'year' ], true ) ? (string) $row['billingPeriod'] : '',
        ];

        if ( $service['name'] === '' && $service['url'] === '' ) {
            continue;
        }

        $services[] = $service;
    }

    return $services;
}

function saifr_admin_sanitize_schema_rows( array $rows, array $fields ): array {
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
    'wp_ajax_saifr_regenerate_all',
    function (): void {
        saifr_admin_require_permissions();
        $force = saifr_post_bool( 'force' );
        $mode  = saifr_post_key( 'mode', 'full' );
        if ( $mode === 'batch' ) {
            $options = wp_parse_args( get_option( 'saifr_options', [] ), saifr_get_default_options() );
            $batch_size = min( 1000, max( 1, intval( $options['regenerate_batch_size'] ?? 100 ) ) );
            $stats = saifr_regenerate_batch( $batch_size, $force, 'manual_ajax_batch' );
        } else {
            $stats = saifr_regenerate_all( $force, 'manual_ajax' );
        }
        wp_send_json_success( $stats );
    }
);

add_action( 'wp_ajax_saifr_regeneration_step', function (): void {
    saifr_admin_require_permissions();
    wp_send_json_success( saifr_run_regeneration_step() );
} );

add_action( 'wp_ajax_saifr_regeneration_status', function (): void {
    saifr_admin_require_permissions();
    wp_send_json_success( get_option( 'saifr_regeneration_job', [] ) );
} );

add_action(
    'wp_ajax_saifr_clear_versions',
    function (): void {
        saifr_admin_require_permissions();
        $count = SaifrVersioning::clearAll();
        saifr_add_event( 'clear_versions', [ 'deleted' => $count ] );
        wp_send_json_success( [ 'deleted' => $count ] );
    }
);

add_action(
    'wp_ajax_saifr_get_overview_stats',
    function (): void {
        saifr_admin_require_permissions();
        wp_send_json_success( saifr_get_overview_stats() );
    }
);

add_action(
    'wp_ajax_saifr_list_content_items',
    function (): void {
        saifr_admin_require_permissions();
        $result = saifr_list_content_items(
            [
                'page'      => saifr_post_int( 'page', 1 ),
                'per_page'  => saifr_post_int( 'per_page', 10 ),
                'search'    => saifr_post_text( 'search', '' ),
                'status'    => saifr_post_key( 'status', 'any' ),
                'post_type' => saifr_post_key( 'post_type', 'all' ),
            ]
        );
        wp_send_json_success( $result );
    }
);

add_action(
    'wp_ajax_saifr_toggle_content_exclusion',
    function (): void {
        saifr_admin_require_permissions();
        $post_id = saifr_post_int( 'post_id', 0 );
        $exclude = saifr_post_bool( 'exclude' );
        $post    = get_post( $post_id );
        if ( ! $post ) {
            wp_send_json_error( __( 'Content not found.', 'sernicola-labs-ai-friendly' ) );
        }

        if ( $exclude ) {
            update_post_meta( $post_id, '_saifr_exclude', '1' );
        } else {
            delete_post_meta( $post_id, '_saifr_exclude' );
        }

        saifr_add_event(
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
    'wp_ajax_saifr_get_event_timeline',
    function (): void {
        saifr_admin_require_permissions();
        $limit = min( 100, max( 5, saifr_post_int( 'limit', 20 ) ) );
        wp_send_json_success(
            [
                'items' => array_slice( saifr_get_event_log(), 0, $limit ),
            ]
        );
    }
);

add_action(
    'wp_ajax_saifr_run_diagnostics',
    function (): void {
        saifr_admin_require_permissions();
        wp_send_json_success( saifr_run_diagnostics() );
    }
);

add_action(
    'wp_ajax_saifr_get_llms_preview',
    function (): void {
        saifr_admin_require_permissions();
        $content = saifr_post_textarea( 'content', saifr_build_llms_txt() );

        wp_send_json_success(
            [
                'html'       => saifr_render_markdown_preview_html( $content ),
                'tokens'     => saifr_estimate_tokens( $content ),
                'chars'      => strlen( $content ),
                'simulation' => saifr_run_ai_simulation( $content ),
                'validation' => saifr_validate_llms_links( $content ),
            ]
        );
    }
);

add_action(
    'wp_ajax_saifr_create_llms_snapshot',
    function (): void {
        saifr_admin_require_permissions();
        $reason  = saifr_post_text( 'reason', 'manual' );
        $content = saifr_post_textarea( 'content', saifr_build_llms_txt() );
        $result  = saifr_create_llms_snapshot( $content, $reason );

        if ( empty( $result['saved'] ) ) {
            wp_send_json_error( __( 'Unable to create snapshot.', 'sernicola-labs-ai-friendly' ) );
        }

        saifr_add_event(
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
    'wp_ajax_saifr_list_llms_snapshots',
    function (): void {
        saifr_admin_require_permissions();
        wp_send_json_success( [ 'items' => saifr_get_llms_history_index() ] );
    }
);

add_action(
    'wp_ajax_saifr_restore_llms_snapshot',
    function (): void {
        saifr_admin_require_permissions();
        $id     = saifr_post_text( 'id', '' );
        $result = saifr_restore_llms_snapshot( $id );

        if ( empty( $result['restored'] ) ) {
            wp_send_json_error( $result['message'] ?? __( 'Restore failed.', 'sernicola-labs-ai-friendly' ) );
        }

        saifr_add_event( 'llms_snapshot_restore', [ 'id' => $id ] );
        wp_send_json_success( $result );
    }
);

add_action(
    'wp_ajax_saifr_compare_llms_snapshots',
    function (): void {
        saifr_admin_require_permissions();
        $left_id  = saifr_post_text( 'left_id', '' );
        $right_id = saifr_post_text( 'right_id', '' );

        if ( $left_id === '' || $right_id === '' ) {
            wp_send_json_error( __( 'Select two snapshots to compare.', 'sernicola-labs-ai-friendly' ) );
        }

        $left_content  = saifr_get_llms_snapshot_content( $left_id );
        $right_content = saifr_get_llms_snapshot_content( $right_id );
        if ( ! is_string( $left_content ) || ! is_string( $right_content ) ) {
            wp_send_json_error( __( 'One or both snapshots are not available.', 'sernicola-labs-ai-friendly' ) );
        }

        wp_send_json_success( saifr_diff_llms_content( $left_content, $right_content ) );
    }
);

add_action(
    'wp_ajax_saifr_set_onboarding_status',
    function (): void {
        saifr_admin_require_permissions();
        $done = saifr_post_bool( 'done' ) ? '1' : '';

        $options                    = wp_parse_args( get_option( 'saifr_options', [] ), saifr_get_default_options() );
        $options['onboarding_done'] = $done;
        update_option( 'saifr_options', $options );
        update_option( 'saifr_onboarding_done', $done, false );

        saifr_add_event(
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
    'wp_ajax_saifr_complete_setup',
    function (): void {
        saifr_admin_require_permissions();

        $options = wp_parse_args( get_option( 'saifr_options', [] ), saifr_get_default_options() );
        $types   = array_values( array_unique( array_filter( array_map( 'sanitize_key', saifr_post_array( 'content_types' ) ) ) ) );
        $public  = get_post_types( [ 'public' => true ], 'names' );
        unset( $public['attachment'] );
        $types = array_values( array_intersect( $types, array_keys( $public ) ) );

        $options['include_pages']     = in_array( 'page', $types, true ) ? '1' : '';
        $options['include_posts']     = in_array( 'post', $types, true ) ? '1' : '';
        $options['include_products']  = class_exists( 'WooCommerce' ) && in_array( 'product', $types, true ) ? '1' : '';
        $options['include_cpt']       = array_values( array_diff( $types, [ 'page', 'post', 'product' ] ) );
        $options['include_acf_fields'] = saifr_post_bool( 'include_acf_fields' ) ? '1' : '';
        $options['exclude_noindex']   = saifr_post_bool( 'exclude_noindex' ) ? '1' : '';
        $options['exclude_password']  = saifr_post_bool( 'exclude_password' ) ? '1' : '';
        $options['llms_include_auto'] = saifr_post_bool( 'llms_include_auto' ) ? '1' : '';
        $options['llms_full_enabled'] = saifr_post_bool( 'llms_full_enabled' ) ? '1' : '';
        $options['static_md_files']   = saifr_post_bool( 'static_md_files' ) ? '1' : '';
        $options['auto_regenerate']   = saifr_post_bool( 'auto_regenerate' ) ? '1' : '';
        if ( ! empty( $options['auto_regenerate'] ) ) {
            $options['static_md_files'] = '1';
        }
        $options['regenerate_interval']   = min( 168, max( 1, saifr_post_int( 'regenerate_interval', 24 ) ) );
        $options['regenerate_batch_size'] = min( 1000, max( 1, saifr_post_int( 'regenerate_batch_size', 100 ) ) );
        $options['regenerate_on_save']    = saifr_post_bool( 'regenerate_on_save' ) ? '1' : '';
        $options['regenerate_on_change']  = saifr_post_bool( 'regenerate_on_change' ) ? '1' : '';
        $options['schema_enabled']        = saifr_post_bool( 'schema_enabled' ) ? '1' : '';
        $options['schema_mode']           = 'auto';
        $entity_type = saifr_post_text( 'schema_entity_type', 'Organization' );
        $options['schema_entity_type'] = in_array( $entity_type, [ 'Person', 'Organization' ], true ) ? $entity_type : 'Organization';
        $options['schema_name']        = saifr_post_text( 'schema_name', get_bloginfo( 'name' ) );
        $options['schema_same_as']     = saifr_post_textarea( 'schema_same_as', '' );
        if ( saifr_is_breakdance_active() ) {
            $options['schema_breakdance_faq_enabled'] = saifr_post_bool( 'schema_breakdance_faq_enabled' ) ? '1' : '';
        }
        $options['onboarding_done'] = '1';
        $options['ui_version']      = 'hub-v2';

        update_option( 'saifr_options', $options );
        update_option( 'saifr_onboarding_done', '1', false );
        update_option( 'saifr_ui_version', 'hub-v2', false );
        delete_option( 'saifr_regeneration_cursor' );
        SaifrVersioning::pruneObsoleteVersions();
        saifr_schedule_cron();
        saifr_add_event( 'settings_saved', [ 'source' => 'setup_wizard' ] );

        try {
            if ( ! empty( $options['static_md_files'] ) ) {
                $stats = saifr_regenerate_all( false, 'setup_wizard' );
                if ( ( $stats['status'] ?? '' ) === 'failed' ) {
                    throw new RuntimeException( 'La generazione ha restituito uno o piu errori.' );
                }
                $output = [
                    'mode'  => 'static',
                    'stats' => $stats,
                ];
            } else {
                $content = saifr_build_llms_txt();
                $output  = [
                    'mode'       => 'dynamic',
                    'validation' => saifr_validate_llms_links( $content ),
                    'tokens'     => saifr_estimate_tokens( $content ),
                    'chars'      => strlen( $content ),
                ];
            }
        } catch ( Throwable $error ) {
            saifr_add_event( 'setup_generation_error', [ 'message' => $error->getMessage() ] );
            wp_send_json_error(
                [
                    'saved'      => true,
                    'message'    => __( 'Settings were saved, but the first generation failed.', 'sernicola-labs-ai-friendly' ),
                    'diagnostics'=> saifr_run_diagnostics(),
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
                'diagnostics' => saifr_run_diagnostics(),
                'done'        => '1',
            ]
        );
    }
);

add_action(
    'wp_ajax_saifr_run_ai_simulation',
    function (): void {
        saifr_admin_require_permissions();
        $content = saifr_post_textarea( 'content', '' );
        wp_send_json_success( saifr_run_ai_simulation( $content ) );
    }
);

function saifr_render_options_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $defaults = saifr_get_default_options();
    $options  = wp_parse_args( get_option( 'saifr_options', [] ), $defaults );
    $settings_saved = false;

    // Il pulsante invia la propria etichetta come valore: basta che il campo sia presente.
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is validated by check_admin_referer() in the same condition.
    if ( isset( $_POST['saifr_save'] ) && check_admin_referer( 'saifr_options_nonce' ) ) {
        $options['llms_content']         = saifr_post_textarea( 'llms_content', '' );
        $options['llms_include_auto']    = saifr_post_bool( 'llms_include_auto' ) ? '1' : '';
        $options['include_pages']        = saifr_post_bool( 'include_pages' ) ? '1' : '';
        $options['include_posts']        = saifr_post_bool( 'include_posts' ) ? '1' : '';
        $options['include_products']     = saifr_post_bool( 'include_products' ) ? '1' : '';
        $options['include_cpt']          = array_map( 'sanitize_key', saifr_post_array( 'include_cpt' ) );
        $options['include_acf_fields']   = saifr_post_bool( 'include_acf_fields' ) ? '1' : '';
        $options['exclude_categories']   = array_map( 'intval', saifr_post_array( 'exclude_categories' ) );
        $options['exclude_tags']         = array_map( 'intval', saifr_post_array( 'exclude_tags' ) );
        $options['exclude_templates']    = array_map( 'sanitize_text_field', saifr_post_array( 'exclude_templates' ) );
        $options['exclude_url_patterns'] = saifr_post_textarea( 'exclude_url_patterns', '' );
        $options['exclude_noindex']      = saifr_post_bool( 'exclude_noindex' ) ? '1' : '';
        $options['exclude_password']     = saifr_post_bool( 'exclude_password' ) ? '1' : '';
        $options['static_md_files']      = saifr_post_bool( 'static_md_files' ) ? '1' : '';
        $options['auto_regenerate']      = saifr_post_bool( 'auto_regenerate' ) ? '1' : '';
        if ( ! empty( $options['auto_regenerate'] ) ) {
            // La rigenerazione a intervallo richiede i file statici attivi.
            $options['static_md_files'] = '1';
        }
        $options['regenerate_interval']  = max( 1, saifr_post_int( 'regenerate_interval', 24 ) );
        $options['regenerate_batch_size'] = min( 1000, max( 1, saifr_post_int( 'regenerate_batch_size', 100 ) ) );
        $options['llms_full_enabled'] = saifr_post_bool( 'llms_full_enabled' ) ? '1' : '';
        $options['regenerate_on_save']   = saifr_post_bool( 'regenerate_on_save' ) ? '1' : '';
        $options['regenerate_on_change'] = saifr_post_bool( 'regenerate_on_change' ) ? '1' : '';
        $options['onboarding_done']      = saifr_post_bool( 'onboarding_done' ) ? '1' : '';
        $options['ui_version']           = 'hub-v2';
        $options['notify_admin_notice']  = saifr_post_bool( 'notify_admin_notice' ) ? '1' : '';
        $options['notify_email']         = saifr_post_bool( 'notify_email' ) ? '1' : '';
        $options['notify_email_to']      = saifr_post_email( 'notify_email_to', '' );
        $options['schema_enabled']       = saifr_post_bool( 'schema_enabled' ) ? '1' : '';
        if ( saifr_is_breakdance_active() ) {
            $options['schema_breakdance_faq_enabled'] = saifr_post_bool( 'schema_breakdance_faq_enabled' ) ? '1' : '';
        }
        $schema_mode = saifr_post_key( 'schema_mode', 'auto' );
        $options['schema_mode'] = in_array( $schema_mode, [ 'auto', 'standalone', 'extend_yoast', 'extend_rank_math' ], true ) ? $schema_mode : 'auto';
        $schema_creator_type = saifr_post_text( 'schema_creator_type', 'Organization' );
        $options['schema_creator_type'] = in_array( $schema_creator_type, [ 'Person', 'Organization' ], true ) ? $schema_creator_type : 'Organization';
        $options['schema_creator_name'] = saifr_post_text( 'schema_creator_name', '' );
        $options['schema_creator_url'] = saifr_post_url( 'schema_creator_url', '' );
        $schema_entity_type = saifr_post_text( 'schema_entity_type', 'Person' );
        $options['schema_entity_type'] = in_array( $schema_entity_type, [ 'Person', 'Organization' ], true ) ? $schema_entity_type : 'Person';
        $options['schema_name'] = saifr_post_text( 'schema_name', '' );
        $options['schema_alternate_name'] = saifr_post_text( 'schema_alternate_name', '' );
        $options['schema_description'] = saifr_post_textarea( 'schema_description', '' );
        $options['schema_disambiguating_description'] = saifr_post_text( 'schema_disambiguating_description', '' );
        $options['schema_job_title'] = saifr_post_text( 'schema_job_title', '' );
        $options['schema_additional_type'] = saifr_post_text( 'schema_additional_type', '' );
        $options['schema_types'] = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', saifr_post_array( 'schema_types' ) ) ) ) );
        $options['schema_slogan'] = saifr_post_text( 'schema_slogan', '' );
        $options['schema_founding_date'] = saifr_post_text( 'schema_founding_date', '' );
        $options['schema_legal_name'] = saifr_post_text( 'schema_legal_name', '' );
        $options['schema_vat_id'] = saifr_post_text( 'schema_vat_id', '' );
        $options['schema_tax_id'] = saifr_post_text( 'schema_tax_id', '' );
        $options['schema_lei_code'] = saifr_post_text( 'schema_lei_code', '' );
        $options['schema_ticker_symbol'] = saifr_post_text( 'schema_ticker_symbol', '' );
        $options['schema_logo_id'] = max( 0, saifr_post_int( 'schema_logo_id', 0 ) );
        $options['schema_street_address'] = saifr_post_text( 'schema_street_address', '' );
        $options['schema_postal_code'] = saifr_post_text( 'schema_postal_code', '' );
        $options['schema_address_locality'] = saifr_post_text( 'schema_address_locality', '' );
        $options['schema_address_region'] = saifr_post_text( 'schema_address_region', '' );
        $options['schema_address_country'] = saifr_post_text( 'schema_address_country', '' );
        $options['schema_contact_type'] = saifr_post_text( 'schema_contact_type', '' );
        $options['schema_contact_email'] = saifr_post_email( 'schema_contact_email', '' );
        $options['schema_contact_languages'] = saifr_post_textarea( 'schema_contact_languages', '' );
        $options['schema_contacts'] = saifr_admin_sanitize_schema_rows(
            saifr_post_array( 'schema_contacts' ),
            [ 'contactType' => 'text', 'telephone' => 'text', 'email' => 'email', 'availableLanguage' => 'textarea', 'hoursAvailable' => 'text' ]
        );
        $options['schema_opening_hours'] = saifr_admin_sanitize_schema_rows(
            saifr_post_array( 'schema_opening_hours' ),
            [ 'dayOfWeek' => 'text', 'opens' => 'text', 'closes' => 'text', 'validFrom' => 'text', 'validThrough' => 'text' ]
        );
        $place_type = saifr_post_text( 'schema_place_type', 'Place' );
        $options['schema_place_type'] = preg_match( '/^[A-Z][A-Za-z0-9]*$/', $place_type ) ? $place_type : 'Place';
        $options['schema_place_name'] = saifr_post_text( 'schema_place_name', '' );
        $options['schema_latitude'] = saifr_post_text( 'schema_latitude', '' );
        $options['schema_longitude'] = saifr_post_text( 'schema_longitude', '' );
        $options['schema_public_transportation_access'] = saifr_post_textarea( 'schema_public_transportation_access', '' );
        $options['schema_certifications'] = saifr_admin_sanitize_schema_rows(
            saifr_post_array( 'schema_certifications' ),
            [ 'name' => 'text', 'identifier' => 'text', 'issuedBy' => 'text', 'url' => 'url' ]
        );
        $options['schema_identifiers'] = saifr_admin_sanitize_schema_rows(
            saifr_post_array( 'schema_identifiers' ),
            [ 'propertyID' => 'text', 'value' => 'text' ]
        );
        $options['schema_founders'] = saifr_post_textarea( 'schema_founders', '' );
        $options['schema_area_served'] = saifr_post_textarea( 'schema_area_served', '' );
        $options['schema_services'] = saifr_admin_sanitize_schema_services( saifr_post_array( 'schema_services' ) );
        $options['schema_related_entities'] = array_values(
            array_filter(
                saifr_admin_sanitize_schema_rows(
                    saifr_post_array( 'schema_related_entities' ),
                    [ 'type' => 'text', 'name' => 'text', 'url' => 'url', 'description' => 'textarea', 'identifier' => 'text', 'sameAs' => 'textarea' ]
                ),
                static fn( array $row ): bool => isset( saifr_schema_related_entity_types()[ $row['type'] ] ) && $row['name'] !== ''
            )
        );
        $options['schema_type_rules'] = saifr_schema_sanitize_type_rules( saifr_post_array( 'schema_type_rules' ) );
        if ( class_exists( 'WooCommerce' ) ) {
            $options['woo_schema_enabled'] = saifr_post_bool( 'woo_schema_enabled' ) ? '1' : '';
            $options['woo_brand_name'] = saifr_post_text( 'woo_brand_name', '' );
            $options['woo_brand_source'] = substr( saifr_post_text( 'woo_brand_source', '' ), 0, 191 );
            $options['woo_shipping_rules'] = saifr_admin_sanitize_schema_rows(
                saifr_post_array( 'woo_shipping_rules' ),
                [ 'countries' => 'text', 'rate' => 'text', 'freeThreshold' => 'text', 'handlingMin' => 'text', 'handlingMax' => 'text', 'transitMin' => 'text', 'transitMax' => 'text' ]
            );
            $return_category = saifr_post_key( 'woo_return_category', '' );
            $options['woo_return_category'] = isset( saifr_woo_return_categories()[ $return_category ] ) ? $return_category : '';
            $options['woo_return_countries'] = saifr_post_text( 'woo_return_countries', '' );
            $return_days = saifr_post_text( 'woo_return_days', '' );
            $options['woo_return_days'] = ctype_digit( $return_days ) ? $return_days : '';
            $return_method = saifr_post_key( 'woo_return_method', 'mail' );
            $options['woo_return_method'] = isset( saifr_woo_return_methods()[ $return_method ] ) ? $return_method : 'mail';
            $return_fees = saifr_post_key( 'woo_return_fees', 'customer' );
            $options['woo_return_fees'] = isset( saifr_woo_return_fees()[ $return_fees ] ) ? $return_fees : 'customer';
            $options['woo_return_fee_amount'] = saifr_post_text( 'woo_return_fee_amount', '' );
            $options['woo_valid_from'] = saifr_post_bool( 'woo_valid_from' ) ? '1' : '';
        }
        $options['schema_offer_sources'] = array_values( array_filter( array_map( 'sanitize_text_field', saifr_post_array( 'schema_offer_sources' ) ) ) );
        $options['schema_offer_catalog'] = '';
        $options['schema_image_id'] = max( 0, saifr_post_int( 'schema_image_id', 0 ) );
        $options['schema_same_as'] = saifr_post_textarea( 'schema_same_as', '' );
        $options['schema_knows_about'] = saifr_post_textarea( 'schema_knows_about', '' );
        $options['schema_knows_language'] = saifr_post_textarea( 'schema_knows_language', '' );
        $options['schema_license'] = saifr_post_url( 'schema_license', '' );
        $options['schema_profile_page_id'] = max( 0, saifr_post_int( 'schema_profile_page_id', 0 ) );

        update_option( 'saifr_options', $options );
        delete_option( 'saifr_regeneration_cursor' );
        update_option( 'saifr_onboarding_done', $options['onboarding_done'], false );
        update_option( 'saifr_ui_version', 'hub-v2', false );

        SaifrVersioning::pruneObsoleteVersions();
        saifr_schedule_cron();
        saifr_add_event( 'settings_saved', [ 'source' => 'admin_page' ] );
        $settings_saved = true;
    }

    $all_categories  = get_categories( [ 'hide_empty' => false ] );
    $all_tags        = get_tags( [ 'hide_empty' => false ] );
    $all_templates   = wp_get_theme()->get_page_templates();
    $all_cpt         = get_post_types( [ 'public' => true, '_builtin' => false ], 'objects' );
    $overview        = saifr_get_overview_stats();
    $version_stats   = SaifrVersioning::getStats();
    $last_regen      = get_option( 'saifr_last_regeneration', [] );
    $next_cron       = wp_next_scheduled( 'saifr_cron_regenerate' );
    $onboarding_done = ! empty( get_option( 'saifr_onboarding_done', $options['onboarding_done'] ?? '' ) );
    $services_md_url = saifr_permalink_to_md( home_url( '/servizi/' ) );
    $schema_provider = function_exists( 'saifr_schema_detect_provider' ) ? saifr_schema_detect_provider() : 'none';
    $schema_mode     = function_exists( 'saifr_schema_output_mode' ) ? saifr_schema_output_mode() : 'standalone';
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
        ? saifr_schema_normalize_service_inputs( $options['schema_services'] )
        : [];
    if ( empty( $schema_services ) && function_exists( 'saifr_schema_parse_legacy_offer_catalog' ) ) {
        $legacy_services = saifr_schema_parse_legacy_offer_catalog( (string) ( $options['schema_offer_catalog'] ?? '' ) );
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
    $schema_related_entities = ! empty( $options['schema_related_entities'] ) && is_array( $options['schema_related_entities'] ) ? $options['schema_related_entities'] : [];
    $schema_type_rules = ! empty( $options['schema_type_rules'] ) && is_array( $options['schema_type_rules'] ) ? $options['schema_type_rules'] : [];
    $woo_shipping_rules = ! empty( $options['woo_shipping_rules'] ) && is_array( $options['woo_shipping_rules'] ) ? $options['woo_shipping_rules'] : [];
    $schema_rule_post_types = get_post_types( [ 'public' => true ], 'objects' );
    unset( $schema_rule_post_types['attachment'] );
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
    <div class="wrap saifr-wrap">
        <div class="saifr-header">
            <div>
                <p class="saifr-eyebrow"><?php esc_html_e( 'AI Friendly', 'sernicola-labs-ai-friendly' ); ?></p>
                <h1><?php esc_html_e( 'AI Content Hub', 'sernicola-labs-ai-friendly' ); ?> <small class="saifr-version">v<?php echo esc_html( SAIFR_VERSION ); ?></small></h1>
            </div>
            <button type="button" class="button saifr-header-wizard<?php echo $onboarding_done ? '' : ' is-hidden'; ?>" id="saifr-reopen-wizard"><?php esc_html_e( 'Reopen guided setup', 'sernicola-labs-ai-friendly' ); ?></button>
            <?php if ( $settings_saved ) : ?>
                <div class="saifr-save-notice" role="status" aria-live="polite">
                    <span class="saifr-save-notice-icon" aria-hidden="true"></span>
                    <span><?php esc_html_e( 'Settings saved', 'sernicola-labs-ai-friendly' ); ?></span>
                    <button type="button" class="saifr-save-notice-dismiss" aria-label="<?php esc_attr_e( 'Dismiss notice', 'sernicola-labs-ai-friendly' ); ?>" onclick="this.closest('.saifr-save-notice').hidden = true;"><?php esc_html_e( '×', 'sernicola-labs-ai-friendly' ); ?></button>
                </div>
            <?php endif; ?>
        </div>

        <div class="saifr-onboarding<?php echo $onboarding_done ? ' is-hidden' : ''; ?>" id="saifr-onboarding" data-initial-step="1">
            <div class="saifr-wizard-topline">
                <div>
                    <p class="saifr-eyebrow"><?php esc_html_e( 'Guided setup', 'sernicola-labs-ai-friendly' ); ?></p>
                    <h2><?php esc_html_e( 'Let\'s set up the Hub using your site\'s real data.', 'sernicola-labs-ai-friendly' ); ?></h2>
                </div>
                <button type="button" class="button-link" id="saifr-onboarding-dismiss"><?php esc_html_e( 'Set up later', 'sernicola-labs-ai-friendly' ); ?></button>
            </div>
            <ol class="saifr-stepper" aria-label="<?php esc_attr_e( 'Setup progress', 'sernicola-labs-ai-friendly' ); ?>">
                <?php
                $wizard_steps = [
                    __( 'Analysis', 'sernicola-labs-ai-friendly' ),
                    __( 'Contents', 'sernicola-labs-ai-friendly' ),
                    __( 'Markdown', 'sernicola-labs-ai-friendly' ),
                    __( 'Schema', 'sernicola-labs-ai-friendly' ),
                    __( 'Summary', 'sernicola-labs-ai-friendly' ),
                ];
                foreach ( $wizard_steps as $index => $label ) :
                ?>
                    <li data-wizard-marker="<?php echo esc_attr( $index + 1 ); ?>"><span><?php echo esc_html( $index + 1 ); ?></span><?php echo esc_html( $label ); ?></li>
                <?php endforeach; ?>
            </ol>

            <div class="saifr-wizard-panel" data-wizard-step="1">
                <div class="saifr-section-heading"><span>01</span><div><h3><?php esc_html_e( 'Site analysis', 'sernicola-labs-ai-friendly' ); ?></h3><p><?php esc_html_e( 'This data was detected from WordPress and the active plugins.', 'sernicola-labs-ai-friendly' ); ?></p></div></div>
                <div class="saifr-analysis-grid">
                    <div><small><?php esc_html_e( 'Site', 'sernicola-labs-ai-friendly' ); ?></small><strong><?php echo esc_html( get_bloginfo( 'name' ) ); ?></strong><code><?php echo esc_html( home_url( '/' ) ); ?></code></div>
                    <div><small><?php esc_html_e( 'SEO provider', 'sernicola-labs-ai-friendly' ); ?></small><strong><?php echo esc_html( $schema_provider === 'none' ? __( 'None detected', 'sernicola-labs-ai-friendly' ) : $schema_provider ); ?></strong></div>
                    <div><small><?php esc_html_e( 'WooCommerce', 'sernicola-labs-ai-friendly' ); ?></small><strong><?php echo class_exists( 'WooCommerce' ) ? esc_html__( 'Active', 'sernicola-labs-ai-friendly' ) : esc_html__( 'Not detected', 'sernicola-labs-ai-friendly' ); ?></strong></div>
                    <div><small><?php esc_html_e( 'Breakdance', 'sernicola-labs-ai-friendly' ); ?></small><strong><?php echo saifr_is_breakdance_active() ? esc_html__( 'Active', 'sernicola-labs-ai-friendly' ) : esc_html__( 'Not detected', 'sernicola-labs-ai-friendly' ); ?></strong></div>
                </div>
                <div class="saifr-detected-types">
                    <?php foreach ( $wizard_post_types as $post_type ) : $counts = wp_count_posts( $post_type->name ); ?>
                        <span><strong><?php echo esc_html( $post_type->labels->name ); ?></strong> <?php
                        $published_count = intval( $counts->publish ?? 0 );
                        printf(
                            /* translators: %s: formatted number of published items. */
                            esc_html( _n( '%s published', '%s published', $published_count, 'sernicola-labs-ai-friendly' ) ),
                            esc_html( number_format_i18n( $published_count ) )
                        );
                        ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="saifr-wizard-panel" data-wizard-step="2" hidden>
                <div class="saifr-section-heading"><span>02</span><div><h3><?php esc_html_e( 'Content to expose', 'sernicola-labs-ai-friendly' ); ?></h3><p><?php esc_html_e( 'Choose only the types meant for public consultation.', 'sernicola-labs-ai-friendly' ); ?></p></div></div>
                <div class="saifr-choice-grid">
                    <?php foreach ( $wizard_post_types as $post_type ) :
                        $is_first_setup = ! $onboarding_done;
                        $selected = $is_first_setup ? in_array( $post_type->name, [ 'page', 'post' ], true ) : in_array( $post_type->name, $wizard_selected_types, true );
                        ?>
                        <label class="saifr-choice"><input type="checkbox" data-wizard-field="content_types" value="<?php echo esc_attr( $post_type->name ); ?>" <?php checked( $selected ); ?>><span><strong><?php echo esc_html( $post_type->labels->name ); ?></strong><small><?php echo esc_html( $post_type->name ); ?></small></span></label>
                    <?php endforeach; ?>
                </div>
                <div class="saifr-wizard-acf">
                    <label class="saifr-setting">
                        <input type="checkbox" data-wizard-field="include_acf_fields" <?php checked( $options['include_acf_fields'] ); ?>>
                        <span>
                            <strong><?php esc_html_e( 'Include ACF fields in the Markdown output', 'sernicola-labs-ai-friendly' ); ?></strong>
                            <small><?php esc_html_e( 'Disabled by default: extracted values become public in the .md endpoints.', 'sernicola-labs-ai-friendly' ); ?></small>
                        </span>
                    </label>
                    <p class="description"><strong><?php esc_html_e( 'Warning:', 'sernicola-labs-ai-friendly' ); ?></strong> <?php esc_html_e( 'enable this option only if all ACF text fields of the included content are meant to be published.', 'sernicola-labs-ai-friendly' ); ?></p>
                </div>
                <div class="saifr-inline-options">
                    <label><input type="checkbox" data-wizard-field="exclude_noindex" <?php checked( $options['exclude_noindex'] ); ?>> <?php esc_html_e( 'Exclude noindex content', 'sernicola-labs-ai-friendly' ); ?></label>
                    <label><input type="checkbox" data-wizard-field="exclude_password" <?php checked( $options['exclude_password'] ); ?>> <?php esc_html_e( 'Exclude password-protected content', 'sernicola-labs-ai-friendly' ); ?></label>
                </div>
            </div>

            <div class="saifr-wizard-panel" data-wizard-step="3" hidden>
                <div class="saifr-section-heading"><span>03</span><div><h3><?php esc_html_e( 'Markdown and automation', 'sernicola-labs-ai-friendly' ); ?></h3><p><?php esc_html_e( 'The suggested settings keep the output updated every 24 hours.', 'sernicola-labs-ai-friendly' ); ?></p></div></div>
                <div class="saifr-settings-grid">
                    <label class="saifr-setting"><input type="checkbox" data-wizard-field="llms_include_auto" <?php checked( $onboarding_done ? $options['llms_include_auto'] : '1' ); ?>><span><strong><?php esc_html_e( 'Automatic list', 'sernicola-labs-ai-friendly' ); ?></strong><small><?php esc_html_e( 'Adds the selected content to llms.txt.', 'sernicola-labs-ai-friendly' ); ?></small></span></label>
                    <label class="saifr-setting"><input type="checkbox" data-wizard-field="static_md_files" <?php checked( $onboarding_done ? $options['static_md_files'] : '1' ); ?>><span><strong><?php esc_html_e( 'Static Markdown files', 'sernicola-labs-ai-friendly' ); ?></strong><small><?php esc_html_e( 'Generates and serves persistent .md copies.', 'sernicola-labs-ai-friendly' ); ?></small></span></label>
                    <label class="saifr-setting"><input type="checkbox" data-wizard-field="auto_regenerate" <?php checked( $onboarding_done ? $options['auto_regenerate'] : '1' ); ?>><span><strong><?php esc_html_e( 'Automatic regeneration', 'sernicola-labs-ai-friendly' ); ?></strong><small><?php esc_html_e( 'Runs the process through WordPress cron.', 'sernicola-labs-ai-friendly' ); ?></small></span></label>
                    <label class="saifr-setting"><input type="checkbox" data-wizard-field="regenerate_on_save" <?php checked( $options['regenerate_on_save'] ); ?>><span><strong><?php esc_html_e( 'Trigger on edit', 'sernicola-labs-ai-friendly' ); ?></strong><small><?php esc_html_e( 'Starts regeneration when you save content.', 'sernicola-labs-ai-friendly' ); ?></small></span></label>
                </div>
                <div class="saifr-number-fields">
                    <label><?php esc_html_e( 'Interval (hours)', 'sernicola-labs-ai-friendly' ); ?><input type="number" min="1" max="168" data-wizard-field="regenerate_interval" value="<?php echo esc_attr( $onboarding_done ? $options['regenerate_interval'] : 24 ); ?>"></label>
                    <label><?php esc_html_e( 'Content per run', 'sernicola-labs-ai-friendly' ); ?><input type="number" min="1" max="1000" data-wizard-field="regenerate_batch_size" value="<?php echo esc_attr( $onboarding_done ? $options['regenerate_batch_size'] : 100 ); ?>"></label>
                    <label class="saifr-compact-check"><input type="checkbox" data-wizard-field="regenerate_on_change" <?php checked( $options['regenerate_on_change'] ); ?>> <?php esc_html_e( 'Only if the checksum changes', 'sernicola-labs-ai-friendly' ); ?></label>
                </div>
            </div>

            <div class="saifr-wizard-panel" data-wizard-step="4" hidden>
                <div class="saifr-section-heading"><span>04</span><div><h3><?php esc_html_e( 'Semantic Schema', 'sernicola-labs-ai-friendly' ); ?></h3><p><?php esc_html_e( 'You can enable it now and complete the details in the Schema section.', 'sernicola-labs-ai-friendly' ); ?></p></div></div>
                <label class="saifr-setting saifr-setting-primary"><input type="checkbox" data-wizard-field="schema_enabled" <?php checked( $options['schema_enabled'] ); ?>><span><strong><?php esc_html_e( 'Enable Semantic Schema', 'sernicola-labs-ai-friendly' ); ?></strong><small><?php esc_html_e( 'Automatic mode, compatible with the detected SEO provider.', 'sernicola-labs-ai-friendly' ); ?></small></span></label>
                <div class="saifr-number-fields saifr-schema-setup-fields">
                    <label><?php esc_html_e( 'Main entity', 'sernicola-labs-ai-friendly' ); ?><select data-wizard-field="schema_entity_type"><option value="Organization" <?php selected( $options['schema_entity_type'], 'Organization' ); ?>><?php esc_html_e( 'Organization', 'sernicola-labs-ai-friendly' ); ?></option><option value="Person" <?php selected( $options['schema_entity_type'], 'Person' ); ?>><?php esc_html_e( 'Person', 'sernicola-labs-ai-friendly' ); ?></option></select></label>
                    <label><?php esc_html_e( 'Name', 'sernicola-labs-ai-friendly' ); ?><input type="text" data-wizard-field="schema_name" value="<?php echo esc_attr( $options['schema_name'] ?: get_bloginfo( 'name' ) ); ?>"></label>
                    <label class="saifr-field-wide"><?php esc_html_e( 'sameAs, one URL per line', 'sernicola-labs-ai-friendly' ); ?><textarea rows="4" data-wizard-field="schema_same_as"><?php echo esc_textarea( $options['schema_same_as'] ); ?></textarea></label>
                    <?php if ( saifr_is_breakdance_active() ) : ?><label class="saifr-compact-check"><input type="checkbox" data-wizard-field="schema_breakdance_faq_enabled" <?php checked( $options['schema_breakdance_faq_enabled'] ); ?>> <?php esc_html_e( 'Automatically detect Breakdance FAQs', 'sernicola-labs-ai-friendly' ); ?></label><?php endif; ?>
                </div>
            </div>

            <div class="saifr-wizard-panel" data-wizard-step="5" hidden>
                <div class="saifr-section-heading"><span>05</span><div><h3><?php esc_html_e( 'Summary', 'sernicola-labs-ai-friendly' ); ?></h3><p><?php esc_html_e( 'Review your choices before saving and running the first generation.', 'sernicola-labs-ai-friendly' ); ?></p></div></div>
                <div id="saifr-wizard-summary" class="saifr-review-grid"></div>
                <div id="saifr-wizard-result" class="saifr-wizard-result" role="status" aria-live="polite"></div>
            </div>

            <div class="saifr-wizard-actions">
                <button type="button" class="button" id="saifr-wizard-prev" hidden><?php esc_html_e( 'Back', 'sernicola-labs-ai-friendly' ); ?></button>
                <span class="saifr-wizard-spacer"></span>
                <button type="button" class="button button-primary" id="saifr-wizard-next"><?php esc_html_e( 'Continue', 'sernicola-labs-ai-friendly' ); ?></button>
                <button type="button" class="button button-primary" id="saifr-wizard-complete" hidden><?php esc_html_e( 'Save and generate', 'sernicola-labs-ai-friendly' ); ?></button>
                <button type="button" class="button" id="saifr-wizard-retry" hidden><?php esc_html_e( 'Retry generation', 'sernicola-labs-ai-friendly' ); ?></button>
            </div>
        </div>

        <form method="post" id="saifr-main-form" class="<?php echo $onboarding_done ? '' : 'is-hidden'; ?>">
            <?php wp_nonce_field( 'saifr_options_nonce' ); ?>
            <input type="hidden" name="onboarding_done" id="onboarding_done" value="<?php echo $onboarding_done ? '1' : ''; ?>">
            <input type="hidden" id="saifr-wizard-step" value="1">

            <nav class="saifr-nav" role="tablist" aria-label="<?php esc_attr_e( 'AI Content Hub sections', 'sernicola-labs-ai-friendly' ); ?>">
                <button type="button" class="saifr-nav-item is-active" data-section="overview"><?php esc_html_e( 'Overview', 'sernicola-labs-ai-friendly' ); ?></button>
                <button type="button" class="saifr-nav-item" data-section="content"><?php esc_html_e( 'Content', 'sernicola-labs-ai-friendly' ); ?></button>
                <button type="button" class="saifr-nav-item" data-section="rules"><?php esc_html_e( 'Rules', 'sernicola-labs-ai-friendly' ); ?></button>
                <button type="button" class="saifr-nav-item" data-section="schema"><?php esc_html_e( 'Schema', 'sernicola-labs-ai-friendly' ); ?></button>
                <button type="button" class="saifr-nav-item" data-section="automation"><?php esc_html_e( 'Automation', 'sernicola-labs-ai-friendly' ); ?></button>
            </nav>

            <section id="saifr-section-overview" class="saifr-section is-active">
                <div class="saifr-section-heading"><span>01</span><div><h3><?php esc_html_e( 'Overview', 'sernicola-labs-ai-friendly' ); ?></h3><p><?php esc_html_e( 'Output status, diagnostics, and main actions.', 'sernicola-labs-ai-friendly' ); ?></p></div></div>
                <?php saifr_render_legacy_migration_status(); ?>
                <div class="saifr-card-grid">
                    <article class="saifr-card">
                        <h3><?php esc_html_e( 'llms.txt status', 'sernicola-labs-ai-friendly' ); ?></h3>
                        <p><code><?php echo esc_html( $overview['llms']['url'] ); ?></code></p>
                        <p><?php esc_html_e( 'Characters:', 'sernicola-labs-ai-friendly' ); ?> <strong id="saifr-llms-chars"><?php echo intval( $overview['llms']['chars'] ); ?></strong></p>
                        <p><?php esc_html_e( 'Lines:', 'sernicola-labs-ai-friendly' ); ?> <strong id="saifr-llms-lines"><?php echo intval( $overview['llms']['lines'] ); ?></strong></p>
                        <p><?php esc_html_e( 'Last regeneration:', 'sernicola-labs-ai-friendly' ); ?> <strong id="saifr-last-regen"><?php echo esc_html( $overview['llms']['last_regen_time'] ?: __( 'n/a', 'sernicola-labs-ai-friendly' ) ); ?></strong></p>
                        <a href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>" target="_blank" class="button button-secondary"><?php esc_html_e( 'llms.txt preview', 'sernicola-labs-ai-friendly' ); ?></a>
                    </article>

                    <article class="saifr-card">
                        <h3><?php esc_html_e( 'Markdown Pack', 'sernicola-labs-ai-friendly' ); ?></h3>
                        <p><?php esc_html_e( 'Static mode:', 'sernicola-labs-ai-friendly' ); ?>
                            <span class="saifr-badge <?php echo ! empty( $overview['markdown']['static_enabled'] ) ? 'is-ok' : 'is-muted'; ?>">
                                <?php echo ! empty( $overview['markdown']['static_enabled'] ) ? esc_html__( 'active', 'sernicola-labs-ai-friendly' ) : esc_html__( 'inactive', 'sernicola-labs-ai-friendly' ); ?>
                            </span>
                        </p>
                        <p><?php esc_html_e( 'Files:', 'sernicola-labs-ai-friendly' ); ?> <strong><?php echo intval( $overview['markdown']['count'] ); ?></strong></p>
                        <p><?php esc_html_e( 'Size:', 'sernicola-labs-ai-friendly' ); ?> <strong><?php echo esc_html( size_format( intval( $overview['markdown']['size'] ) ) ); ?></strong></p>
                        <button type="button" id="saifr-regenerate-overview" class="button button-secondary"><?php esc_html_e( 'Regenerate llms/MD', 'sernicola-labs-ai-friendly' ); ?></button>
                    </article>

                    <article class="saifr-card">
                        <h3><?php esc_html_e( 'Quick notices', 'sernicola-labs-ai-friendly' ); ?></h3>
                        <ul id="saifr-overview-warnings" class="saifr-list">
                            <?php foreach ( $overview['diagnostics']['warnings'] as $warning ) : ?>
                                <li><?php echo esc_html( $warning['message'] ?? '' ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="description" id="saifr-sr-info"></p>
                        <button type="button" id="saifr-refresh-diagnostics" class="button"><?php esc_html_e( 'Refresh diagnostics', 'sernicola-labs-ai-friendly' ); ?></button>
                    </article>

                    <article class="saifr-card">
                        <h3><?php esc_html_e( 'Semantic Schema', 'sernicola-labs-ai-friendly' ); ?></h3>
                        <p><?php esc_html_e( 'Status:', 'sernicola-labs-ai-friendly' ); ?>
                            <span class="saifr-badge <?php echo ! empty( $options['schema_enabled'] ) ? 'is-ok' : 'is-muted'; ?>">
                                <?php echo ! empty( $options['schema_enabled'] ) ? esc_html__( 'active', 'sernicola-labs-ai-friendly' ) : esc_html__( 'inactive', 'sernicola-labs-ai-friendly' ); ?>
                            </span>
                        </p>
                        <p><?php esc_html_e( 'SEO provider:', 'sernicola-labs-ai-friendly' ); ?> <strong><?php echo esc_html( $schema_provider ); ?></strong></p>
                        <p><?php esc_html_e( 'Output:', 'sernicola-labs-ai-friendly' ); ?> <strong><?php echo esc_html( $schema_mode ); ?></strong></p>
                        <button type="button" class="button button-secondary" data-section-jump="schema"><?php esc_html_e( 'Configure Schema', 'sernicola-labs-ai-friendly' ); ?></button>
                    </article>
                </div>

                <div class="saifr-quick-actions">
                    <button type="button" class="button button-primary" data-section-jump="content"><?php esc_html_e( 'Edit llms.txt', 'sernicola-labs-ai-friendly' ); ?></button>
                    <button type="button" class="button" id="saifr-refresh-overview"><?php esc_html_e( 'Refresh Overview', 'sernicola-labs-ai-friendly' ); ?></button>
                    <button type="button" class="button" id="saifr-run-now"><?php esc_html_e( 'Regenerate now', 'sernicola-labs-ai-friendly' ); ?></button>
                </div>
            </section>

            <section id="saifr-section-content" class="saifr-section">
                <div class="saifr-section-heading"><span>02</span><div><h3><?php esc_html_e( 'Content', 'sernicola-labs-ai-friendly' ); ?></h3><p><?php esc_html_e( 'Edit llms.txt, check the preview, and manage the exposed content.', 'sernicola-labs-ai-friendly' ); ?></p></div></div>
                <div class="saifr-editor-layout">
                    <aside class="saifr-panel saifr-panel-left">
                        <h3><?php esc_html_e( 'Document structure', 'sernicola-labs-ai-friendly' ); ?></h3>
                        <ul id="saifr-toc" class="saifr-list"></ul>
                    </aside>

                    <div class="saifr-panel saifr-panel-center">
                        <h3><?php esc_html_e( 'llms.txt editor', 'sernicola-labs-ai-friendly' ); ?></h3>
                        <textarea
                            name="llms_content"
                            id="llms_content"
                            rows="16"
                            class="large-text code"
                            placeholder="<?php esc_attr_e( '# Site name
> Site summary', 'sernicola-labs-ai-friendly' ); ?>"
                        ><?php echo esc_textarea( $options['llms_content'] ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Custom Markdown content. If empty, the plugin generates it automatically.', 'sernicola-labs-ai-friendly' ); ?></p>
                        <label>
                            <input type="checkbox" name="llms_include_auto" value="1" <?php checked( $options['llms_include_auto'] ); ?>>
                            <?php esc_html_e( 'Add the automatic list after the custom content', 'sernicola-labs-ai-friendly' ); ?>
                        </label>
                        <div class="saifr-preview-split">
                            <div class="saifr-preview-head"><?php esc_html_e( 'Live preview', 'sernicola-labs-ai-friendly' ); ?></div>
                            <div id="saifr-preview-pane"></div>
                        </div>
                    </div>

                    <aside class="saifr-panel saifr-panel-right">
                        <h3><?php esc_html_e( 'Helper', 'sernicola-labs-ai-friendly' ); ?></h3>
                        <p><?php esc_html_e( 'Estimated tokens:', 'sernicola-labs-ai-friendly' ); ?> <strong id="saifr-token-count">0</strong></p>
                        <p><?php esc_html_e( 'Link validation:', 'sernicola-labs-ai-friendly' ); ?> <strong id="saifr-link-validation"><?php esc_html_e( '0 issue', 'sernicola-labs-ai-friendly' ); ?></strong></p>
                        <ul class="saifr-list">
                            <li><button type="button" class="button-link saifr-insert-snippet" data-snippet="<?php echo esc_attr( __( '# About us', 'sernicola-labs-ai-friendly' ) ); ?>"><?php esc_html_e( '+ Heading', 'sernicola-labs-ai-friendly' ); ?></button></li>
                            <li><button type="button" class="button-link saifr-insert-snippet" data-snippet="<?php echo esc_attr( '- [' . __( 'Services', 'sernicola-labs-ai-friendly' ) . '](' . $services_md_url . ')' ); ?>"><?php esc_html_e( '+ Section link', 'sernicola-labs-ai-friendly' ); ?></button></li>
                            <li><button type="button" class="button-link saifr-insert-snippet" data-snippet="> <?php echo esc_attr( __( 'AI summary in 1-2 sentences.', 'sernicola-labs-ai-friendly' ) ); ?>"><?php esc_html_e( '+ Summary', 'sernicola-labs-ai-friendly' ); ?></button></li>
                        </ul>
                        <p><?php esc_html_e( 'Useful variables:', 'sernicola-labs-ai-friendly' ); ?></p>
                        <ul class="saifr-list saifr-small">
                            <li><code><?php echo esc_html( get_bloginfo( 'name' ) ); ?></code></li>
                            <li><code><?php echo esc_html( home_url() ); ?></code></li>
                            <li><code><?php echo esc_html( get_locale() ); ?></code></li>
                        </ul>
                        <button type="button" id="saifr-run-simulation" class="button"><?php esc_html_e( 'AI Simulation', 'sernicola-labs-ai-friendly' ); ?></button>
                        <div id="saifr-simulation-result" class="saifr-simulation"></div>
                    </aside>
                </div>

                <div class="saifr-history">
                    <h3><?php esc_html_e( 'llms versioning', 'sernicola-labs-ai-friendly' ); ?></h3>
                    <div class="saifr-history-actions">
                        <button type="button" id="saifr-create-snapshot" class="button"><?php esc_html_e( 'Create snapshot', 'sernicola-labs-ai-friendly' ); ?></button>
                        <button type="button" id="saifr-load-snapshots" class="button button-secondary"><?php esc_html_e( 'Refresh list', 'sernicola-labs-ai-friendly' ); ?></button>
                        <button type="button" id="saifr-compare-snapshots" class="button"><?php esc_html_e( 'Compare selected', 'sernicola-labs-ai-friendly' ); ?></button>
                    </div>
                    <ul id="saifr-snapshot-list" class="saifr-list"></ul>
                    <div class="saifr-diff-wrap">
                        <div class="saifr-diff-summary" id="saifr-diff-summary"></div>
                        <div class="saifr-diff-columns">
                            <div>
                                <h4><?php esc_html_e( 'Side-by-side diff', 'sernicola-labs-ai-friendly' ); ?></h4>
                                <table class="widefat striped saifr-diff-table">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e( 'Left', 'sernicola-labs-ai-friendly' ); ?></th>
                                            <th><?php esc_html_e( 'Right', 'sernicola-labs-ai-friendly' ); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="saifr-diff-rows"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="saifr-content-manager">
                    <h3><?php esc_html_e( 'Site pages', 'sernicola-labs-ai-friendly' ); ?></h3>
                    <div class="saifr-filters">
                        <input type="text" id="saifr-content-search" placeholder="<?php esc_attr_e( 'Search title', 'sernicola-labs-ai-friendly' ); ?>">
                        <select id="saifr-content-type">
                            <option value="all"><?php esc_html_e( 'All types', 'sernicola-labs-ai-friendly' ); ?></option>
                            <option value="page"><?php esc_html_e( 'Pages', 'sernicola-labs-ai-friendly' ); ?></option>
                            <option value="post"><?php esc_html_e( 'Posts', 'sernicola-labs-ai-friendly' ); ?></option>
                            <option value="product"><?php esc_html_e( 'Products', 'sernicola-labs-ai-friendly' ); ?></option>
                        </select>
                        <select id="saifr-content-status">
                            <option value="any"><?php esc_html_e( 'All statuses', 'sernicola-labs-ai-friendly' ); ?></option>
                            <option value="publish"><?php esc_html_e( 'Published', 'sernicola-labs-ai-friendly' ); ?></option>
                            <option value="draft"><?php esc_html_e( 'Draft', 'sernicola-labs-ai-friendly' ); ?></option>
                            <option value="private"><?php esc_html_e( 'Private', 'sernicola-labs-ai-friendly' ); ?></option>
                        </select>
                        <button type="button" id="saifr-content-apply" class="button"><?php esc_html_e( 'Filter', 'sernicola-labs-ai-friendly' ); ?></button>
                    </div>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Included/Excluded', 'sernicola-labs-ai-friendly' ); ?></th>
                                <th><?php esc_html_e( 'Title', 'sernicola-labs-ai-friendly' ); ?></th>
                                <th><?php esc_html_e( 'Type', 'sernicola-labs-ai-friendly' ); ?></th>
                                <th><?php esc_html_e( 'Language', 'sernicola-labs-ai-friendly' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'sernicola-labs-ai-friendly' ); ?></th>
                                <th><?php esc_html_e( 'Token', 'sernicola-labs-ai-friendly' ); ?></th>
                                <th><?php esc_html_e( 'Action', 'sernicola-labs-ai-friendly' ); ?></th>
                            </tr>
                        </thead>
                        <tbody id="saifr-content-tbody"></tbody>
                    </table>
                    <div class="saifr-pagination">
                        <button type="button" class="button" id="saifr-prev-page"><?php esc_html_e( 'Previous', 'sernicola-labs-ai-friendly' ); ?></button>
                        <span id="saifr-page-info"><?php esc_html_e( 'Page 1', 'sernicola-labs-ai-friendly' ); ?></span>
                        <button type="button" class="button" id="saifr-next-page"><?php esc_html_e( 'Next', 'sernicola-labs-ai-friendly' ); ?></button>
                    </div>
                </div>
            </section>

            <section id="saifr-section-rules" class="saifr-section">
                <div class="saifr-section-heading"><span>03</span><div><h3><?php esc_html_e( 'Filters & exclusions', 'sernicola-labs-ai-friendly' ); ?></h3><p><?php esc_html_e( 'Define what goes into the public output and what must stay out of it.', 'sernicola-labs-ai-friendly' ); ?></p></div></div>
                <div class="saifr-settings-cards">
                    <article class="saifr-settings-card">
                        <div class="saifr-card-head"><span>01</span><div><h4><?php esc_html_e( 'Content types', 'sernicola-labs-ai-friendly' ); ?></h4><p><?php esc_html_e( 'Select the public collections to expose.', 'sernicola-labs-ai-friendly' ); ?></p></div></div>
                        <div class="saifr-option-list">
                            <label>
                                <input type="checkbox" name="include_pages" value="1" <?php checked( $options['include_pages'] ); ?>>
                                <?php esc_html_e( 'Pages', 'sernicola-labs-ai-friendly' ); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="include_posts" value="1" <?php checked( $options['include_posts'] ); ?>>
                                <?php esc_html_e( 'Posts', 'sernicola-labs-ai-friendly' ); ?>
                            </label>
                            <?php if ( class_exists( 'WooCommerce' ) ) : ?>
                                <label>
                                    <input type="checkbox" name="include_products" value="1" <?php checked( $options['include_products'] ); ?>>
                                    <?php esc_html_e( 'WooCommerce products', 'sernicola-labs-ai-friendly' ); ?>
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
                    <article class="saifr-settings-card">
                        <div class="saifr-card-head"><span>02</span><div><h4><?php esc_html_e( 'Protections', 'sernicola-labs-ai-friendly' ); ?></h4><p><?php esc_html_e( 'Respect SEO directives and restricted access.', 'sernicola-labs-ai-friendly' ); ?></p></div></div>
                        <div class="saifr-option-list">
                            <label>
                                <input type="checkbox" name="exclude_noindex" value="1" <?php checked( $options['exclude_noindex'] ); ?>>
                                <?php esc_html_e( 'Exclude pages with meta', 'sernicola-labs-ai-friendly' ); ?> <code><?php esc_html_e( 'noindex', 'sernicola-labs-ai-friendly' ); ?></code>
                            </label>
                            <label>
                                <input type="checkbox" name="exclude_password" value="1" <?php checked( $options['exclude_password'] ); ?>>
                                <?php esc_html_e( 'Exclude password-protected content', 'sernicola-labs-ai-friendly' ); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="include_acf_fields" value="1" <?php checked( $options['include_acf_fields'] ); ?>>
                                <?php esc_html_e( 'Include ACF text field values in the public output', 'sernicola-labs-ai-friendly' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'Disabled by default: enable it only if all ACF fields of the included content are meant to be published.', 'sernicola-labs-ai-friendly' ); ?></p>
                        </div>
                    </article>
                    <article class="saifr-settings-card saifr-settings-card-wide">
                        <div class="saifr-card-head"><span>03</span><div><h4><?php esc_html_e( 'Granular exclusions', 'sernicola-labs-ai-friendly' ); ?></h4><p><?php esc_html_e( 'Categories, tags, templates, and URL patterns.', 'sernicola-labs-ai-friendly' ); ?></p></div></div>
                        <div class="saifr-field-grid">
                            <?php if ( ! empty( $all_categories ) ) : ?><label class="saifr-field"><span><?php esc_html_e( 'Categories', 'sernicola-labs-ai-friendly' ); ?></span><select name="exclude_categories[]" multiple size="6">
                                <?php foreach ( $all_categories as $cat ) : ?>
                                    <option value="<?php echo esc_attr( $cat->term_id ); ?>"
                                        <?php selected( in_array( $cat->term_id, (array) $options['exclude_categories'], false ) ); ?>>
                                        <?php echo esc_html( $cat->name ); ?> (<?php echo intval( $cat->count ); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select></label><?php endif; ?>
                            <?php if ( ! empty( $all_tags ) ) : ?><label class="saifr-field"><span><?php esc_html_e( 'Tags', 'sernicola-labs-ai-friendly' ); ?></span><select name="exclude_tags[]" multiple size="6">
                                <?php foreach ( $all_tags as $tag ) : ?>
                                    <option value="<?php echo esc_attr( $tag->term_id ); ?>"
                                        <?php selected( in_array( $tag->term_id, (array) $options['exclude_tags'], false ) ); ?>>
                                        <?php echo esc_html( $tag->name ); ?> (<?php echo intval( $tag->count ); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select></label><?php endif; ?>
                            <?php if ( ! empty( $all_templates ) ) : ?><label class="saifr-field"><span><?php esc_html_e( 'Templates', 'sernicola-labs-ai-friendly' ); ?></span><select name="exclude_templates[]" multiple size="6">
                                <?php foreach ( $all_templates as $file => $name ) : ?>
                                    <option value="<?php echo esc_attr( $file ); ?>"
                                        <?php selected( in_array( $file, (array) $options['exclude_templates'], true ) ); ?>>
                                        <?php echo esc_html( $name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select></label><?php endif; ?>
                            <label class="saifr-field saifr-field-wide"><span><?php esc_html_e( 'URL patterns', 'sernicola-labs-ai-friendly' ); ?></span><textarea name="exclude_url_patterns" id="exclude_url_patterns" rows="5" class="code"><?php echo esc_textarea( $options['exclude_url_patterns'] ); ?></textarea><small><?php esc_html_e( 'One pattern per line, with wildcards', 'sernicola-labs-ai-friendly' ); ?> <code>*</code>.</small></label>
                        </div>
                    </article>
                </div>
            </section>

            <section id="saifr-section-schema" class="saifr-section">
                <div class="saifr-schema-head saifr-section-heading">
                    <span>04</span>
                    <div>
                        <h3><?php esc_html_e( 'Semantic Schema', 'sernicola-labs-ai-friendly' ); ?></h3>
                        <p class="description"><?php esc_html_e( 'Adds identity, profiles, and AI-friendly context to the JSON-LD, without duplicating the work of your SEO plugin.', 'sernicola-labs-ai-friendly' ); ?></p>
                    </div>
                    <div class="saifr-schema-status" aria-label="<?php esc_attr_e( 'Semantic Schema status', 'sernicola-labs-ai-friendly' ); ?>">
                        <span class="saifr-badge <?php echo ! empty( $options['schema_enabled'] ) ? 'is-ok' : 'is-muted'; ?>">
                            <?php echo ! empty( $options['schema_enabled'] ) ? esc_html__( 'Active', 'sernicola-labs-ai-friendly' ) : esc_html__( 'Inactive', 'sernicola-labs-ai-friendly' ); ?>
                        </span>
                        <span><?php esc_html_e( 'Provider:', 'sernicola-labs-ai-friendly' ); ?> <code><?php echo esc_html( $schema_provider ); ?></code></span>
                        <span><?php esc_html_e( 'Output:', 'sernicola-labs-ai-friendly' ); ?> <code><?php echo esc_html( $schema_mode ); ?></code></span>
                        <a class="button button-secondary" href="<?php echo esc_url( $schema_validator_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Schema Validator', 'sernicola-labs-ai-friendly' ); ?></a>
                    </div>
                </div>

                <div class="saifr-schema-grid">
                    <article class="saifr-schema-card saifr-schema-card-wide">
                        <div class="saifr-schema-card-head">
                            <h4><?php esc_html_e( 'Output', 'sernicola-labs-ai-friendly' ); ?></h4>
                            <p><?php esc_html_e( 'Decide whether AI Friendly should extend Yoast/Rank Math or output a standalone graph.', 'sernicola-labs-ai-friendly' ); ?></p>
                        </div>
                        <div class="saifr-schema-fields saifr-schema-fields-inline">
                            <label class="saifr-field saifr-field-check">
                                <input type="checkbox" name="schema_enabled" value="1" <?php checked( $options['schema_enabled'] ); ?>>
                                <span><?php esc_html_e( 'Enable AI Friendly semantic JSON-LD', 'sernicola-labs-ai-friendly' ); ?></span>
                            </label>
                            <?php if ( saifr_is_breakdance_active() ) : ?>
                                <label class="saifr-field saifr-field-check">
                                    <input type="checkbox" name="schema_breakdance_faq_enabled" value="1" <?php checked( $options['schema_breakdance_faq_enabled'] ); ?>>
                                    <span><?php esc_html_e( 'Automatically detect Breakdance FAQs', 'sernicola-labs-ai-friendly' ); ?></span>
                                </label>
                            <?php endif; ?>
                            <label class="saifr-field">
                                <span><?php esc_html_e( 'Mode', 'sernicola-labs-ai-friendly' ); ?></span>
                                <select name="schema_mode">
                                    <option value="auto" <?php selected( $options['schema_mode'], 'auto' ); ?>><?php esc_html_e( 'Auto', 'sernicola-labs-ai-friendly' ); ?></option>
                                    <option value="standalone" <?php selected( $options['schema_mode'], 'standalone' ); ?>><?php esc_html_e( 'Standalone', 'sernicola-labs-ai-friendly' ); ?></option>
                                    <option value="extend_yoast" <?php selected( $options['schema_mode'], 'extend_yoast' ); ?>><?php esc_html_e( 'Extend Yoast', 'sernicola-labs-ai-friendly' ); ?></option>
                                    <option value="extend_rank_math" <?php selected( $options['schema_mode'], 'extend_rank_math' ); ?>><?php esc_html_e( 'Extend Rank Math', 'sernicola-labs-ai-friendly' ); ?></option>
                                </select>
                            </label>
                        </div>
                    </article>

                    <article class="saifr-schema-card saifr-schema-card-wide saifr-schema-identity">
                        <div class="saifr-schema-card-head">
                            <h4><?php esc_html_e( 'Main identity', 'sernicola-labs-ai-friendly' ); ?></h4>
                            <p><?php esc_html_e( 'The `Person` or `Organization` node that represents the site or brand.', 'sernicola-labs-ai-friendly' ); ?></p>
                        </div>
                        <div class="saifr-schema-fields">
                            <label class="saifr-field saifr-field-short">
                                <span><?php esc_html_e( 'Type', 'sernicola-labs-ai-friendly' ); ?></span>
                                <select name="schema_entity_type" id="saifr-schema-entity-type">
                                    <option value="Person" <?php selected( $options['schema_entity_type'], 'Person' ); ?>><?php esc_html_e( 'Person', 'sernicola-labs-ai-friendly' ); ?></option>
                                    <option value="Organization" <?php selected( $options['schema_entity_type'], 'Organization' ); ?>><?php esc_html_e( 'Organization', 'sernicola-labs-ai-friendly' ); ?></option>
                                </select>
                            </label>
                            <label class="saifr-field">
                                <span><?php esc_html_e( 'Name', 'sernicola-labs-ai-friendly' ); ?></span>
                                <input type="text" name="schema_name" value="<?php echo esc_attr( $options['schema_name'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
                            </label>
                            <label class="saifr-field">
                                <span><?php esc_html_e( 'Alternative name', 'sernicola-labs-ai-friendly' ); ?></span>
                                <input type="text" name="schema_alternate_name" value="<?php echo esc_attr( $options['schema_alternate_name'] ); ?>">
                            </label>
                            <label class="saifr-field" data-entity-scope="person">
                                <span><?php esc_html_e( 'Role / job title', 'sernicola-labs-ai-friendly' ); ?></span>
                                <input type="text" name="schema_job_title" value="<?php echo esc_attr( $options['schema_job_title'] ); ?>">
                            </label>
                            <div class="saifr-field" data-entity-scope="organization">
                                <span><?php esc_html_e( 'Additional types', 'sernicola-labs-ai-friendly' ); ?></span>
                                <div class="saifr-schema-repeaters" data-repeater="types">
                                    <?php foreach ( $schema_types as $index => $schema_type ) : ?>
                                        <div class="saifr-schema-repeater-row">
                                            <input type="text" data-field="value" name="schema_types[<?php echo esc_attr( $index ); ?>]" value="<?php echo esc_attr( $schema_type ); ?>" placeholder="<?php esc_attr_e( 'EducationalOrganization', 'sernicola-labs-ai-friendly' ); ?>">
                                            <button type="button" class="button-link-delete saifr-repeater-remove"><?php esc_html_e( 'Remove', 'sernicola-labs-ai-friendly' ); ?></button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="button button-secondary saifr-repeater-add" data-target="types"><?php esc_html_e( 'Add type', 'sernicola-labs-ai-friendly' ); ?></button>
                                <input type="hidden" name="schema_additional_type" value="">
                                <small><?php esc_html_e( 'Generates a real array', 'sernicola-labs-ai-friendly' ); ?> <code><?php esc_html_e( '@type', 'sernicola-labs-ai-friendly' ); ?></code><?php esc_html_e( ', for example Organization + EducationalOrganization + NGO.', 'sernicola-labs-ai-friendly' ); ?></small>
                            </div>
                        </div>
                        <aside class="saifr-identity-assets" aria-label="<?php esc_attr_e( 'Identity images', 'sernicola-labs-ai-friendly' ); ?>">
                            <div class="saifr-media-block" data-entity-scope="organization">
                                <div class="saifr-media-block-head"><strong><?php esc_html_e( 'Company logo', 'sernicola-labs-ai-friendly' ); ?></strong><span><?php esc_html_e( 'Used in the Organization node.', 'sernicola-labs-ai-friendly' ); ?></span></div>
                                <input type="hidden" name="schema_logo_id" id="saifr-schema-logo-id" value="<?php echo esc_attr( intval( $options['schema_logo_id'] ) ); ?>">
                                <div class="saifr-schema-media">
                                    <div class="saifr-schema-image-preview" id="saifr-schema-logo-preview">
                                        <?php if ( $schema_logo_url !== '' ) : ?><img src="<?php echo esc_url( $schema_logo_url ); ?>" alt="" /><?php else : ?><span><?php esc_html_e( 'No logo', 'sernicola-labs-ai-friendly' ); ?></span><?php endif; ?>
                                    </div>
                                    <div class="saifr-schema-media-actions">
                                        <button type="button" class="button" id="saifr-schema-logo-select"><?php esc_html_e( 'Select', 'sernicola-labs-ai-friendly' ); ?></button>
                                        <button type="button" class="button saifr-button-danger" id="saifr-schema-logo-clear"><?php esc_html_e( 'Remove', 'sernicola-labs-ai-friendly' ); ?></button>
                                    </div>
                                </div>
                            </div>
                            <div class="saifr-media-block">
                                <div class="saifr-media-block-head"><strong><?php esc_html_e( 'Main image', 'sernicola-labs-ai-friendly' ); ?></strong><span><?php esc_html_e( 'Logo, portrait, or identity image.', 'sernicola-labs-ai-friendly' ); ?></span></div>
                                <input type="hidden" name="schema_image_id" id="saifr-schema-image-id" value="<?php echo esc_attr( intval( $options['schema_image_id'] ) ); ?>">
                                <div class="saifr-schema-media">
                                    <div class="saifr-schema-image-preview" id="saifr-schema-entity-image-preview">
                                        <?php if ( $schema_image_url !== '' ) : ?><img src="<?php echo esc_url( $schema_image_url ); ?>" alt="" /><?php else : ?><span><?php esc_html_e( 'No image', 'sernicola-labs-ai-friendly' ); ?></span><?php endif; ?>
                                    </div>
                                    <div class="saifr-schema-media-actions">
                                        <button type="button" class="button" id="saifr-schema-image-select"><?php esc_html_e( 'Select', 'sernicola-labs-ai-friendly' ); ?></button>
                                        <button type="button" class="button saifr-button-danger" id="saifr-schema-image-clear"><?php esc_html_e( 'Remove', 'sernicola-labs-ai-friendly' ); ?></button>
                                    </div>
                                </div>
                            </div>
                        </aside>
                    </article>

                    <article class="saifr-schema-card saifr-schema-card-wide" data-entity-scope="organization">
                        <div class="saifr-schema-card-head">
                            <h4><?php esc_html_e( 'Company data', 'sernicola-labs-ai-friendly' ); ?></h4>
                            <p><?php esc_html_e( 'Optional fields for', 'sernicola-labs-ai-friendly' ); ?> <code><?php esc_html_e( 'Organization', 'sernicola-labs-ai-friendly' ); ?></code><?php esc_html_e( '. Enter only official, publicly verifiable data.', 'sernicola-labs-ai-friendly' ); ?></p>
                        </div>
                        <div class="saifr-schema-fields">
                            <label class="saifr-field"><span><?php esc_html_e( 'Legal name', 'sernicola-labs-ai-friendly' ); ?></span><input type="text" name="schema_legal_name" value="<?php echo esc_attr( $options['schema_legal_name'] ); ?>" placeholder="<?php esc_attr_e( 'Acme Inc.', 'sernicola-labs-ai-friendly' ); ?>"></label>
                            <label class="saifr-field"><span><?php esc_html_e( 'VAT number', 'sernicola-labs-ai-friendly' ); ?></span><input type="text" name="schema_vat_id" value="<?php echo esc_attr( $options['schema_vat_id'] ); ?>" placeholder="<?php esc_attr_e( 'IT01234567890', 'sernicola-labs-ai-friendly' ); ?>"></label>
                            <label class="saifr-field"><span><?php esc_html_e( 'Tax code / taxID', 'sernicola-labs-ai-friendly' ); ?></span><input type="text" name="schema_tax_id" value="<?php echo esc_attr( $options['schema_tax_id'] ); ?>"></label>
                            <label class="saifr-field"><span><?php esc_html_e( 'LEI code', 'sernicola-labs-ai-friendly' ); ?></span><input type="text" name="schema_lei_code" value="<?php echo esc_attr( $options['schema_lei_code'] ); ?>" placeholder="<?php esc_attr_e( 'LEI code (20 characters)', 'sernicola-labs-ai-friendly' ); ?>"></label>
                            <label class="saifr-field"><span><?php esc_html_e( 'Stock symbol', 'sernicola-labs-ai-friendly' ); ?></span><input type="text" name="schema_ticker_symbol" value="<?php echo esc_attr( $options['schema_ticker_symbol'] ); ?>" placeholder="<?php esc_attr_e( 'ACME', 'sernicola-labs-ai-friendly' ); ?>"></label>
                        </div>
                    </article>

                    <article class="saifr-schema-card saifr-schema-card-wide" data-entity-scope="organization">
                        <div class="saifr-schema-card-head">
                            <h4><?php esc_html_e( 'Physical location', 'sernicola-labs-ai-friendly' ); ?></h4>
                            <p><?php esc_html_e( 'Address, place type, coordinates, and directions to get there.', 'sernicola-labs-ai-friendly' ); ?></p>
                        </div>
                        <div class="saifr-schema-fields">
                            <label class="saifr-field"><span><?php esc_html_e( 'Address', 'sernicola-labs-ai-friendly' ); ?></span><input type="text" name="schema_street_address" value="<?php echo esc_attr( $options['schema_street_address'] ); ?>" placeholder="<?php esc_attr_e( '10 Example Street', 'sernicola-labs-ai-friendly' ); ?>"></label>
                            <label class="saifr-field"><span><?php esc_html_e( 'Postal code', 'sernicola-labs-ai-friendly' ); ?></span><input type="text" name="schema_postal_code" value="<?php echo esc_attr( $options['schema_postal_code'] ); ?>" placeholder="00000"></label>
                            <label class="saifr-field"><span><?php esc_html_e( 'City', 'sernicola-labs-ai-friendly' ); ?></span><input type="text" name="schema_address_locality" value="<?php echo esc_attr( $options['schema_address_locality'] ); ?>" placeholder="<?php esc_attr_e( 'Exampleville', 'sernicola-labs-ai-friendly' ); ?>"></label>
                            <label class="saifr-field"><span><?php esc_html_e( 'Province / region', 'sernicola-labs-ai-friendly' ); ?></span><input type="text" name="schema_address_region" value="<?php echo esc_attr( $options['schema_address_region'] ); ?>" placeholder="<?php esc_attr_e( 'MI', 'sernicola-labs-ai-friendly' ); ?>"></label>
                            <label class="saifr-field"><span><?php esc_html_e( 'Country (ISO code)', 'sernicola-labs-ai-friendly' ); ?></span><input type="text" name="schema_address_country" value="<?php echo esc_attr( $options['schema_address_country'] ); ?>" placeholder="<?php esc_attr_e( 'IT', 'sernicola-labs-ai-friendly' ); ?>"></label>
                            <label class="saifr-field"><span><?php esc_html_e( 'Location name', 'sernicola-labs-ai-friendly' ); ?></span><input type="text" name="schema_place_name" value="<?php echo esc_attr( $options['schema_place_name'] ); ?>" placeholder="<?php esc_attr_e( 'Headquarters', 'sernicola-labs-ai-friendly' ); ?>"></label>
                            <label class="saifr-field"><span><?php esc_html_e( 'Schema.org location type', 'sernicola-labs-ai-friendly' ); ?></span><input type="text" name="schema_place_type" value="<?php echo esc_attr( $options['schema_place_type'] ); ?>" placeholder="<?php esc_attr_e( 'PerformingArtsTheater', 'sernicola-labs-ai-friendly' ); ?>"></label>
                            <label class="saifr-field"><span><?php esc_html_e( 'Latitude', 'sernicola-labs-ai-friendly' ); ?></span><input type="text" name="schema_latitude" value="<?php echo esc_attr( $options['schema_latitude'] ); ?>" placeholder="45.4642"></label>
                            <label class="saifr-field"><span><?php esc_html_e( 'Longitude', 'sernicola-labs-ai-friendly' ); ?></span><input type="text" name="schema_longitude" value="<?php echo esc_attr( $options['schema_longitude'] ); ?>" placeholder="9.1900"></label>
                            <label class="saifr-field"><span><?php esc_html_e( 'Public transport access', 'sernicola-labs-ai-friendly' ); ?></span><textarea name="schema_public_transportation_access" rows="3" placeholder="<?php esc_attr_e( 'Metro line M2, stop ...', 'sernicola-labs-ai-friendly' ); ?>"><?php echo esc_textarea( $options['schema_public_transportation_access'] ); ?></textarea></label>
                            <input type="hidden" name="schema_contact_type" value="">
                            <input type="hidden" name="schema_contact_email" value="">
                            <input type="hidden" name="schema_contact_languages" value="">
                        </div>
                    </article>

                    <article class="saifr-schema-card saifr-schema-card-wide" data-entity-scope="organization">
                        <div class="saifr-schema-card-head"><h4><?php esc_html_e( 'Departments and contacts', 'sernicola-labs-ai-friendly' ); ?></h4><p><?php esc_html_e( 'Repeatable ContactPoints with specific phone, email, languages, and hours.', 'sernicola-labs-ai-friendly' ); ?></p></div>
                        <div class="saifr-schema-repeaters" data-repeater="contacts">
                            <?php foreach ( $schema_contacts as $index => $contact ) : ?>
                                <div class="saifr-schema-repeater-row saifr-schema-repeater-grid">
                                    <input data-field="contactType" name="schema_contacts[<?php echo esc_attr( $index ); ?>][contactType]" value="<?php echo esc_attr( $contact['contactType'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'course office', 'sernicola-labs-ai-friendly' ); ?>">
                                    <input data-field="telephone" name="schema_contacts[<?php echo esc_attr( $index ); ?>][telephone]" value="<?php echo esc_attr( $contact['telephone'] ?? '' ); ?>" placeholder="+39 02 ...">
                                    <input type="email" data-field="email" name="schema_contacts[<?php echo esc_attr( $index ); ?>][email]" value="<?php echo esc_attr( $contact['email'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'email@example.com', 'sernicola-labs-ai-friendly' ); ?>">
                                    <input data-field="availableLanguage" name="schema_contacts[<?php echo esc_attr( $index ); ?>][availableLanguage]" value="<?php echo esc_attr( $contact['availableLanguage'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'it, en', 'sernicola-labs-ai-friendly' ); ?>">
                                    <input data-field="hoursAvailable" name="schema_contacts[<?php echo esc_attr( $index ); ?>][hoursAvailable]" value="<?php echo esc_attr( $contact['hoursAvailable'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Mo-Fr 09:00-18:00', 'sernicola-labs-ai-friendly' ); ?>">
                                    <button type="button" class="button-link-delete saifr-repeater-remove"><?php esc_html_e( 'Remove', 'sernicola-labs-ai-friendly' ); ?></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="button button-secondary saifr-repeater-add" data-target="contacts"><?php esc_html_e( 'Add contact', 'sernicola-labs-ai-friendly' ); ?></button>
                    </article>

                    <article class="saifr-schema-card saifr-schema-card-wide" data-entity-scope="organization">
                        <div class="saifr-schema-card-head"><h4><?php esc_html_e( 'Opening hours', 'sernicola-labs-ai-friendly' ); ?></h4><p><?php esc_html_e( 'Generates', 'sernicola-labs-ai-friendly' ); ?> <code><?php esc_html_e( 'openingHoursSpecification', 'sernicola-labs-ai-friendly' ); ?></code> <?php esc_html_e( 'on the physical location node.', 'sernicola-labs-ai-friendly' ); ?></p></div>
                        <div class="saifr-schema-repeaters" data-repeater="hours">
                            <?php foreach ( $schema_opening_hours as $index => $hours ) : ?>
                                <div class="saifr-schema-repeater-row saifr-schema-repeater-grid">
                                    <input data-field="dayOfWeek" name="schema_opening_hours[<?php echo esc_attr( $index ); ?>][dayOfWeek]" value="<?php echo esc_attr( $hours['dayOfWeek'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Monday, Tuesday', 'sernicola-labs-ai-friendly' ); ?>">
                                    <input type="time" data-field="opens" name="schema_opening_hours[<?php echo esc_attr( $index ); ?>][opens]" value="<?php echo esc_attr( $hours['opens'] ?? '' ); ?>">
                                    <input type="time" data-field="closes" name="schema_opening_hours[<?php echo esc_attr( $index ); ?>][closes]" value="<?php echo esc_attr( $hours['closes'] ?? '' ); ?>">
                                    <input type="date" data-field="validFrom" name="schema_opening_hours[<?php echo esc_attr( $index ); ?>][validFrom]" value="<?php echo esc_attr( $hours['validFrom'] ?? '' ); ?>">
                                    <input type="date" data-field="validThrough" name="schema_opening_hours[<?php echo esc_attr( $index ); ?>][validThrough]" value="<?php echo esc_attr( $hours['validThrough'] ?? '' ); ?>">
                                    <button type="button" class="button-link-delete saifr-repeater-remove"><?php esc_html_e( 'Remove', 'sernicola-labs-ai-friendly' ); ?></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="button button-secondary saifr-repeater-add" data-target="hours"><?php esc_html_e( 'Add time slot', 'sernicola-labs-ai-friendly' ); ?></button>
                    </article>

                    <article class="saifr-schema-card saifr-schema-card-wide" data-entity-scope="organization">
                        <div class="saifr-schema-card-head">
                            <h4><?php esc_html_e( 'Founders', 'sernicola-labs-ai-friendly' ); ?></h4>
                            <p><?php esc_html_e( 'One per line: Name | current role | profile URL. Role and URL are optional.', 'sernicola-labs-ai-friendly' ); ?></p>
                        </div>
                        <label class="saifr-field">
                            <span><?php esc_html_e( 'Founding members', 'sernicola-labs-ai-friendly' ); ?></span>
                            <textarea name="schema_founders" rows="4" placeholder="<?php esc_attr_e( 'John Smith
Jane Doe | CEO', 'sernicola-labs-ai-friendly' ); ?>"><?php echo esc_textarea( $options['schema_founders'] ); ?></textarea>
                        </label>
                    </article>

                    <article class="saifr-schema-card saifr-schema-card-wide">
                        <div class="saifr-schema-card-head">
                            <h4><?php esc_html_e( 'Descriptions', 'sernicola-labs-ai-friendly' ); ?></h4>
                            <p><?php esc_html_e( 'Use them to clarify who you are and set yourself apart from similar entities.', 'sernicola-labs-ai-friendly' ); ?></p>
                        </div>
                        <div class="saifr-schema-fields">
                            <label class="saifr-field">
                                <span><?php esc_html_e( 'Description', 'sernicola-labs-ai-friendly' ); ?></span>
                                <textarea name="schema_description" rows="4"><?php echo esc_textarea( $options['schema_description'] ); ?></textarea>
                            </label>
                            <label class="saifr-field">
                                <span><?php esc_html_e( 'Disambiguating description', 'sernicola-labs-ai-friendly' ); ?></span>
                                <input type="text" name="schema_disambiguating_description" value="<?php echo esc_attr( $options['schema_disambiguating_description'] ); ?>">
                            </label>
                            <label class="saifr-field" data-entity-scope="organization">
                                <span><?php esc_html_e( 'Slogan', 'sernicola-labs-ai-friendly' ); ?></span>
                                <input type="text" name="schema_slogan" value="<?php echo esc_attr( $options['schema_slogan'] ); ?>" placeholder="<?php esc_attr_e( 'E-problem solving: web development outside the box.', 'sernicola-labs-ai-friendly' ); ?>">
                            </label>
                            <label class="saifr-field" data-entity-scope="organization">
                                <span><?php esc_html_e( 'Founding date', 'sernicola-labs-ai-friendly' ); ?></span>
                                <input type="text" name="schema_founding_date" value="<?php echo esc_attr( $options['schema_founding_date'] ); ?>" placeholder="2015">
                            </label>
                        </div>
                    </article>

                    <article class="saifr-schema-card saifr-schema-card-wide">
                        <div class="saifr-schema-card-head">
                            <h4><?php esc_html_e( 'Profiles and expertise', 'sernicola-labs-ai-friendly' ); ?></h4>
                            <p><?php esc_html_e( 'One entry per line. These are the most useful fields for disambiguation.', 'sernicola-labs-ai-friendly' ); ?></p>
                        </div>
                        <div class="saifr-schema-fields">
                            <label class="saifr-field">
                                <span><?php esc_html_e( 'sameAs / external profiles', 'sernicola-labs-ai-friendly' ); ?></span>
                                <textarea name="schema_same_as" rows="4" placeholder="<?php esc_attr_e( 'https://www.linkedin.com/in/...
https://github.com/...', 'sernicola-labs-ai-friendly' ); ?>"><?php echo esc_textarea( $options['schema_same_as'] ); ?></textarea>
                            </label>
                            <label class="saifr-field">
                                <span><?php esc_html_e( 'knowsAbout', 'sernicola-labs-ai-friendly' ); ?></span>
                                <textarea name="schema_knows_about" rows="4" placeholder="<?php esc_attr_e( 'Technical SEO
AI content strategy', 'sernicola-labs-ai-friendly' ); ?>"><?php echo esc_textarea( $options['schema_knows_about'] ); ?></textarea>
                            </label>
                            <label class="saifr-field">
                                <span><?php esc_html_e( 'knowsLanguage', 'sernicola-labs-ai-friendly' ); ?></span>
                                <textarea name="schema_knows_language" rows="3" placeholder="<?php esc_attr_e( 'it-IT
en-US', 'sernicola-labs-ai-friendly' ); ?>"><?php echo esc_textarea( $options['schema_knows_language'] ); ?></textarea>
                            </label>
                            <label class="saifr-field" data-entity-scope="organization">
                                <span><?php esc_html_e( 'areaServed', 'sernicola-labs-ai-friendly' ); ?></span>
                                <textarea name="schema_area_served" rows="3" placeholder="<?php esc_attr_e( 'City: Exampleville
Country: Italy', 'sernicola-labs-ai-friendly' ); ?>"><?php echo esc_textarea( $options['schema_area_served'] ); ?></textarea>
                            </label>
                        </div>
                    </article>

                    <article class="saifr-schema-card saifr-schema-card-wide" data-entity-scope="organization">
                        <div class="saifr-schema-card-head"><h4><?php esc_html_e( 'Certifications', 'sernicola-labs-ai-friendly' ); ?></h4><p><?php esc_html_e( 'Accreditations, ISO standards, and professional register memberships as nodes', 'sernicola-labs-ai-friendly' ); ?> <code><?php esc_html_e( 'Certification', 'sernicola-labs-ai-friendly' ); ?></code>.</p></div>
                        <div class="saifr-schema-repeaters" data-repeater="certifications">
                            <?php foreach ( $schema_certifications as $index => $certification ) : ?>
                                <div class="saifr-schema-repeater-row saifr-schema-repeater-grid">
                                    <input data-field="name" name="schema_certifications[<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $certification['name'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'ISO 9001', 'sernicola-labs-ai-friendly' ); ?>">
                                    <input data-field="identifier" name="schema_certifications[<?php echo esc_attr( $index ); ?>][identifier]" value="<?php echo esc_attr( $certification['identifier'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Certificate no.', 'sernicola-labs-ai-friendly' ); ?>">
                                    <input data-field="issuedBy" name="schema_certifications[<?php echo esc_attr( $index ); ?>][issuedBy]" value="<?php echo esc_attr( $certification['issuedBy'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Certifying body', 'sernicola-labs-ai-friendly' ); ?>">
                                    <input type="url" data-field="url" name="schema_certifications[<?php echo esc_attr( $index ); ?>][url]" value="<?php echo esc_attr( $certification['url'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'https://...', 'sernicola-labs-ai-friendly' ); ?>">
                                    <button type="button" class="button-link-delete saifr-repeater-remove"><?php esc_html_e( 'Remove', 'sernicola-labs-ai-friendly' ); ?></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="button button-secondary saifr-repeater-add" data-target="certifications"><?php esc_html_e( 'Add certification', 'sernicola-labs-ai-friendly' ); ?></button>
                    </article>

                    <article class="saifr-schema-card saifr-schema-card-wide" data-entity-scope="organization">
                        <div class="saifr-schema-card-head"><h4><?php esc_html_e( 'Additional identifiers', 'sernicola-labs-ai-friendly' ); ?></h4><p><?php esc_html_e( 'Key-value pairs for RUNTS, REA, ATECO, and industry registers.', 'sernicola-labs-ai-friendly' ); ?></p></div>
                        <div class="saifr-schema-repeaters" data-repeater="identifiers">
                            <?php foreach ( $schema_identifiers as $index => $identifier ) : ?>
                                <div class="saifr-schema-repeater-row">
                                    <input data-field="propertyID" name="schema_identifiers[<?php echo esc_attr( $index ); ?>][propertyID]" value="<?php echo esc_attr( $identifier['propertyID'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'RUNTS', 'sernicola-labs-ai-friendly' ); ?>">
                                    <input data-field="value" name="schema_identifiers[<?php echo esc_attr( $index ); ?>][value]" value="<?php echo esc_attr( $identifier['value'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Identification number', 'sernicola-labs-ai-friendly' ); ?>">
                                    <button type="button" class="button-link-delete saifr-repeater-remove"><?php esc_html_e( 'Remove', 'sernicola-labs-ai-friendly' ); ?></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="button button-secondary saifr-repeater-add" data-target="identifiers"><?php esc_html_e( 'Add identifier', 'sernicola-labs-ai-friendly' ); ?></button>
                    </article>

                    <article class="saifr-schema-card saifr-schema-card-wide" data-entity-scope="organization">
                        <div class="saifr-schema-card-head"><h4><?php esc_html_e( 'Related entities', 'sernicola-labs-ai-friendly' ); ?></h4><p><?php esc_html_e( 'Publications, series, podcasts, event cycles, brands, and group companies. Each entry becomes a node linked to the organization as', 'sernicola-labs-ai-friendly' ); ?> <code>publisher</code>, <code>organizer</code>, <code>brand</code> <?php esc_html_e( 'or', 'sernicola-labs-ai-friendly' ); ?> <code>subOrganization</code>.</p></div>
                        <div class="saifr-schema-repeaters" data-repeater="related">
                            <?php foreach ( $schema_related_entities as $index => $entity ) : ?>
                                <div class="saifr-schema-repeater-row saifr-schema-repeater-grid">
                                    <select data-field="type" name="schema_related_entities[<?php echo esc_attr( $index ); ?>][type]">
                                        <?php foreach ( array_keys( saifr_schema_related_entity_types() ) as $related_type ) : ?>
                                            <option value="<?php echo esc_attr( $related_type ); ?>" <?php selected( $entity['type'] ?? '', $related_type ); ?>><?php echo esc_html( $related_type ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input data-field="name" name="schema_related_entities[<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $entity['name'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Publication or brand name', 'sernicola-labs-ai-friendly' ); ?>">
                                    <input type="url" data-field="url" name="schema_related_entities[<?php echo esc_attr( $index ); ?>][url]" value="<?php echo esc_attr( $entity['url'] ?? '' ); ?>" placeholder="https://...">
                                    <input data-field="identifier" name="schema_related_entities[<?php echo esc_attr( $index ); ?>][identifier]" value="<?php echo esc_attr( $entity['identifier'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'ISSN 1234-5678', 'sernicola-labs-ai-friendly' ); ?>">
                                    <textarea data-field="description" rows="2" name="schema_related_entities[<?php echo esc_attr( $index ); ?>][description]" placeholder="<?php esc_attr_e( 'Short description', 'sernicola-labs-ai-friendly' ); ?>"><?php echo esc_textarea( $entity['description'] ?? '' ); ?></textarea>
                                    <textarea data-field="sameAs" rows="2" name="schema_related_entities[<?php echo esc_attr( $index ); ?>][sameAs]" placeholder="https://www.linkedin.com/..."><?php echo esc_textarea( $entity['sameAs'] ?? '' ); ?></textarea>
                                    <button type="button" class="button-link-delete saifr-repeater-remove"><?php esc_html_e( 'Remove', 'sernicola-labs-ai-friendly' ); ?></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="button button-secondary saifr-repeater-add" data-target="related"><?php esc_html_e( 'Add entity', 'sernicola-labs-ai-friendly' ); ?></button>
                    </article>

                    <article class="saifr-schema-card saifr-schema-card-wide" data-entity-scope="organization">
                        <div class="saifr-schema-card-head">
                            <h4><?php esc_html_e( 'Service catalog', 'sernicola-labs-ai-friendly' ); ?></h4>
                            <p><?php esc_html_e( 'Adds an', 'sernicola-labs-ai-friendly' ); ?> <code><?php esc_html_e( 'OfferCatalog', 'sernicola-labs-ai-friendly' ); ?></code><?php esc_html_e( '. WordPress sources automatically fill in name, URL, and description; manual rows remain available for integrations.', 'sernicola-labs-ai-friendly' ); ?></p>
                        </div>
                        <div class="saifr-field saifr-schema-source-field">
                            <span><?php esc_html_e( 'WordPress sources', 'sernicola-labs-ai-friendly' ); ?></span>
                            <div class="saifr-schema-repeaters" data-repeater="offerSources">
                                <?php foreach ( $schema_offer_sources as $index => $source ) : ?>
                                    <div class="saifr-schema-repeater-row">
                                        <input data-field="value" name="schema_offer_sources[<?php echo esc_attr( $index ); ?>]" value="<?php echo esc_attr( $source ); ?>" placeholder="<?php esc_attr_e( 'Term ID, taxonomy:slug, or WordPress permalink', 'sernicola-labs-ai-friendly' ); ?>">
                                        <button type="button" class="button-link-delete saifr-repeater-remove"><?php esc_html_e( 'Remove', 'sernicola-labs-ai-friendly' ); ?></button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="button button-secondary saifr-repeater-add" data-target="offerSources"><?php esc_html_e( 'Add source', 'sernicola-labs-ai-friendly' ); ?></button>
                            <small><?php esc_html_e( 'Accepts term IDs,', 'sernicola-labs-ai-friendly' ); ?> <code><?php esc_html_e( 'taxonomy:slug', 'sernicola-labs-ai-friendly' ); ?></code><?php esc_html_e( ', category/taxonomy permalinks, and page or CPT permalinks.', 'sernicola-labs-ai-friendly' ); ?></small>
                        </div>
                        <div class="saifr-schema-subsection-head saifr-schema-subsection-offer">
                            <strong><?php esc_html_e( 'Manual entries', 'sernicola-labs-ai-friendly' ); ?></strong>
                            <span><?php esc_html_e( 'Use them to complete or replace the data derived from WordPress sources.', 'sernicola-labs-ai-friendly' ); ?></span>
                        </div>
                        <div class="saifr-schema-services" id="saifr-schema-services">
                            <?php foreach ( $schema_services as $index => $service ) : ?>
                                <div class="saifr-schema-service" data-service-index="<?php echo esc_attr( $index ); ?>">
                                    <div class="saifr-schema-service-head">
                                        <strong><?php esc_html_e( 'Service', 'sernicola-labs-ai-friendly' ); ?></strong>
                                        <button type="button" class="button button-link-delete saifr-schema-service-remove"><?php esc_html_e( 'Remove', 'sernicola-labs-ai-friendly' ); ?></button>
                                    </div>
                                    <div class="saifr-schema-service-grid">
                                        <div class="saifr-schema-field-group saifr-schema-service-identity">
                                            <div class="saifr-schema-field-group-head"><strong><?php esc_html_e( 'Service identity', 'sernicola-labs-ai-friendly' ); ?></strong></div>
                                        <label class="saifr-field">
                                            <span><?php esc_html_e( 'Name', 'sernicola-labs-ai-friendly' ); ?></span>
                                            <input type="text" data-service-field="name" name="schema_services[<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $service['name'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'UX and Graphic Design', 'sernicola-labs-ai-friendly' ); ?>">
                                        </label>
                                        <label class="saifr-field">
                                            <span><?php esc_html_e( 'Page URL', 'sernicola-labs-ai-friendly' ); ?></span>
                                            <input type="url" data-service-field="url" name="schema_services[<?php echo esc_attr( $index ); ?>][url]" value="<?php echo esc_attr( $service['url'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'https://example.com/service/', 'sernicola-labs-ai-friendly' ); ?>">
                                        </label>
                                        <label class="saifr-field">
                                            <span><?php esc_html_e( 'Service type', 'sernicola-labs-ai-friendly' ); ?></span>
                                            <input type="text" data-service-field="serviceType" name="schema_services[<?php echo esc_attr( $index ); ?>][serviceType]" value="<?php echo esc_attr( $service['serviceType'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Web design, UX/UI design', 'sernicola-labs-ai-friendly' ); ?>">
                                        </label>
                                        <label class="saifr-field">
                                            <span><?php esc_html_e( 'Area served', 'sernicola-labs-ai-friendly' ); ?></span>
                                            <input type="text" data-service-field="areaServed" name="schema_services[<?php echo esc_attr( $index ); ?>][areaServed]" value="<?php echo esc_attr( $service['areaServed'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Italy', 'sernicola-labs-ai-friendly' ); ?>">
                                        </label>
                                        <label class="saifr-field saifr-schema-service-description">
                                            <span><?php esc_html_e( 'Description', 'sernicola-labs-ai-friendly' ); ?></span>
                                            <textarea data-service-field="description" name="schema_services[<?php echo esc_attr( $index ); ?>][description]" rows="3" placeholder="<?php esc_attr_e( 'Short description of the service.', 'sernicola-labs-ai-friendly' ); ?>"><?php echo esc_textarea( $service['description'] ?? '' ); ?></textarea>
                                        </label>
                                        </div>
                                        <div class="saifr-schema-field-group saifr-schema-service-offer">
                                            <div class="saifr-schema-field-group-head"><strong><?php esc_html_e( 'Offer', 'sernicola-labs-ai-friendly' ); ?></strong></div>
                                        <label class="saifr-field">
                                            <span><?php esc_html_e( 'Price', 'sernicola-labs-ai-friendly' ); ?></span>
                                            <input type="text" data-service-field="price" name="schema_services[<?php echo esc_attr( $index ); ?>][price]" value="<?php echo esc_attr( $service['price'] ?? '' ); ?>" placeholder="0">
                                        </label>
                                        <label class="saifr-field">
                                            <span><?php esc_html_e( 'Currency', 'sernicola-labs-ai-friendly' ); ?></span>
                                            <input type="text" data-service-field="priceCurrency" name="schema_services[<?php echo esc_attr( $index ); ?>][priceCurrency]" value="<?php echo esc_attr( $service['priceCurrency'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'EUR', 'sernicola-labs-ai-friendly' ); ?>">
                                        </label>
                                        <label class="saifr-field">
                                            <span><?php esc_html_e( 'VAT', 'sernicola-labs-ai-friendly' ); ?></span>
                                            <select data-service-field="vatIncluded" name="schema_services[<?php echo esc_attr( $index ); ?>][vatIncluded]">
                                                <option value=""><?php esc_html_e( 'Not specified', 'sernicola-labs-ai-friendly' ); ?></option>
                                                <option value="yes" <?php selected( $service['vatIncluded'] ?? '', 'yes' ); ?>><?php esc_html_e( 'VAT included', 'sernicola-labs-ai-friendly' ); ?></option>
                                                <option value="no" <?php selected( $service['vatIncluded'] ?? '', 'no' ); ?>><?php esc_html_e( 'VAT excluded', 'sernicola-labs-ai-friendly' ); ?></option>
                                            </select>
                                        </label>
                                        <label class="saifr-field">
                                            <span><?php esc_html_e( 'Frequency', 'sernicola-labs-ai-friendly' ); ?></span>
                                            <select data-service-field="billingPeriod" name="schema_services[<?php echo esc_attr( $index ); ?>][billingPeriod]">
                                                <option value=""><?php esc_html_e( 'One-time / not specified', 'sernicola-labs-ai-friendly' ); ?></option>
                                                <option value="month" <?php selected( $service['billingPeriod'] ?? '', 'month' ); ?>><?php esc_html_e( 'Monthly', 'sernicola-labs-ai-friendly' ); ?></option>
                                                <option value="year" <?php selected( $service['billingPeriod'] ?? '', 'year' ); ?>><?php esc_html_e( 'Yearly', 'sernicola-labs-ai-friendly' ); ?></option>
                                            </select>
                                        </label>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="button button-secondary" id="saifr-schema-service-add"><?php esc_html_e( 'Add service', 'sernicola-labs-ai-friendly' ); ?></button>
                    </article>

                    <article class="saifr-schema-card saifr-schema-card-wide">
                        <div class="saifr-schema-card-head saifr-schema-mapping-head">
                            <h4><?php esc_html_e( 'Automatic schema by content type', 'sernicola-labs-ai-friendly' ); ?></h4>
                            <p><?php esc_html_e( 'Generates an Event, Course, or Service node for each content item of a post type, reading dates, venue, and price from its fields. Accepted sources:', 'sernicola-labs-ai-friendly' ); ?> <code>meta:chiave</code>, <code>acf:campo</code>, <code>tax:tassonomia</code>, <code>text:valore fisso</code>. <?php esc_html_e( 'Values entered in the individual content\'s metabox take precedence.', 'sernicola-labs-ai-friendly' ); ?></p>
                        </div>
                        <div class="saifr-type-rules">
                            <?php foreach ( $schema_rule_post_types as $rule_post_type => $rule_post_type_object ) : ?>
                                <?php
                                $type_rule   = isset( $schema_type_rules[ $rule_post_type ] ) && is_array( $schema_type_rules[ $rule_post_type ] ) ? $schema_type_rules[ $rule_post_type ] : [];
                                $rule_type   = (string) ( $type_rule['type'] ?? '' );
                                $rule_name   = 'schema_type_rules[' . $rule_post_type . ']';
                                $datalist_id = 'saifr-sources-' . $rule_post_type;
                                $rule_fields = [
                                    'startDate'        => [ __( 'Start date', 'sernicola-labs-ai-friendly' ), 'Event, Course', 'acf:data_inizio' ],
                                    'endDate'          => [ __( 'End date', 'sernicola-labs-ai-friendly' ), 'Event, Course', 'acf:data_fine' ],
                                    'locationName'     => [ __( 'Venue name', 'sernicola-labs-ai-friendly' ), 'Event, Course', 'meta:luogo' ],
                                    'locationAddress'  => [ __( 'Venue address', 'sernicola-labs-ai-friendly' ), 'Event, Course', 'meta:indirizzo' ],
                                    'price'            => [ __( 'Price', 'sernicola-labs-ai-friendly' ), 'Event, Course, Service', 'meta:prezzo' ],
                                    'priceCurrency'    => [ __( 'Currency', 'sernicola-labs-ai-friendly' ), 'Event, Course, Service', 'text:EUR' ],
                                    'courseCode'       => [ __( 'Course code', 'sernicola-labs-ai-friendly' ), 'Course', 'meta:codice' ],
                                    'educationalLevel' => [ __( 'Level', 'sernicola-labs-ai-friendly' ), 'Course', 'tax:livello' ],
                                    'serviceType'      => [ __( 'Service type', 'sernicola-labs-ai-friendly' ), 'Service', 'tax:category' ],
                                    'areaServed'       => [ __( 'Area served', 'sernicola-labs-ai-friendly' ), 'Service', 'text:Italia' ],
                                ];
                                ?>
                                <details class="saifr-type-rule" <?php echo $rule_type !== '' ? 'open' : ''; ?>>
                                    <summary>
                                        <strong><?php echo esc_html( $rule_post_type_object->labels->name ); ?></strong>
                                        <code><?php echo esc_html( $rule_post_type ); ?></code>
                                        <span class="saifr-badge <?php echo $rule_type !== '' ? 'is-ok' : 'is-muted'; ?>"><?php echo $rule_type !== '' ? esc_html( $rule_type ) : esc_html__( 'Manual only', 'sernicola-labs-ai-friendly' ); ?></span>
                                    </summary>
                                    <div class="saifr-schema-rule-body">
                                    <div class="saifr-schema-rule-controls">
                                        <label class="saifr-field">
                                            <span><?php esc_html_e( 'Schema type', 'sernicola-labs-ai-friendly' ); ?></span>
                                            <select class="saifr-rule-type" name="<?php echo esc_attr( $rule_name . '[type]' ); ?>">
                                                <option value=""><?php esc_html_e( 'None (metabox only)', 'sernicola-labs-ai-friendly' ); ?></option>
                                                <?php foreach ( saifr_schema_rule_types() as $allowed_rule_type ) : ?>
                                                    <option value="<?php echo esc_attr( $allowed_rule_type ); ?>" <?php selected( $rule_type, $allowed_rule_type ); ?>><?php echo esc_html( $allowed_rule_type ); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                        <label class="saifr-field saifr-rule-attendance" data-rule-applies="Event">
                                            <span><?php esc_html_e( 'Attendance mode', 'sernicola-labs-ai-friendly' ); ?></span>
                                            <select name="<?php echo esc_attr( $rule_name . '[attendanceMode]' ); ?>">
                                                <option value="offline" <?php selected( $type_rule['attendanceMode'] ?? 'offline', 'offline' ); ?>><?php esc_html_e( 'In person', 'sernicola-labs-ai-friendly' ); ?></option>
                                                <option value="online" <?php selected( $type_rule['attendanceMode'] ?? '', 'online' ); ?>><?php esc_html_e( 'Online', 'sernicola-labs-ai-friendly' ); ?></option>
                                                <option value="mixed" <?php selected( $type_rule['attendanceMode'] ?? '', 'mixed' ); ?>><?php esc_html_e( 'Blended', 'sernicola-labs-ai-friendly' ); ?></option>
                                            </select>
                                        </label>
                                    </div>
                                    <div class="saifr-rule-field-groups">
                                        <section class="saifr-schema-field-group" data-rule-applies="Event Course">
                                            <div class="saifr-schema-field-group-head"><strong><?php esc_html_e( 'Dates and venue', 'sernicola-labs-ai-friendly' ); ?></strong><span><?php esc_html_e( 'For events and courses', 'sernicola-labs-ai-friendly' ); ?></span></div>
                                            <div class="saifr-schema-fields">
                                        <?php foreach ( $rule_fields as $rule_field => $rule_field_meta ) : ?>
                                            <?php if ( in_array( $rule_field, [ 'startDate', 'endDate', 'locationName', 'locationAddress' ], true ) ) : ?>
                                            <label class="saifr-field" data-rule-applies="<?php echo esc_attr( $rule_field_meta[1] ); ?>">
                                                <span><?php echo esc_html( $rule_field_meta[0] ); ?> <small>(<?php echo esc_html( $rule_field_meta[1] ); ?>)</small></span>
                                                <input type="text" list="<?php echo esc_attr( $datalist_id ); ?>" name="<?php echo esc_attr( $rule_name . '[' . $rule_field . ']' ); ?>" value="<?php echo esc_attr( $type_rule[ $rule_field ] ?? '' ); ?>" placeholder="<?php echo esc_attr( $rule_field_meta[2] ); ?>">
                                            </label>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                            </div>
                                        </section>
                                        <section class="saifr-schema-field-group" data-rule-applies="Event Course Service">
                                            <div class="saifr-schema-field-group-head"><strong><?php esc_html_e( 'Offer', 'sernicola-labs-ai-friendly' ); ?></strong><span><?php esc_html_e( 'Price and currency', 'sernicola-labs-ai-friendly' ); ?></span></div>
                                            <div class="saifr-schema-fields">
                                        <?php foreach ( $rule_fields as $rule_field => $rule_field_meta ) : ?>
                                            <?php if ( in_array( $rule_field, [ 'price', 'priceCurrency' ], true ) ) : ?>
                                            <label class="saifr-field" data-rule-applies="<?php echo esc_attr( $rule_field_meta[1] ); ?>">
                                                <span><?php echo esc_html( $rule_field_meta[0] ); ?> <small>(<?php echo esc_html( $rule_field_meta[1] ); ?>)</small></span>
                                                <input type="text" list="<?php echo esc_attr( $datalist_id ); ?>" name="<?php echo esc_attr( $rule_name . '[' . $rule_field . ']' ); ?>" value="<?php echo esc_attr( $type_rule[ $rule_field ] ?? '' ); ?>" placeholder="<?php echo esc_attr( $rule_field_meta[2] ); ?>">
                                            </label>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                            </div>
                                        </section>
                                        <section class="saifr-schema-field-group" data-rule-applies="Course Service">
                                            <div class="saifr-schema-field-group-head"><strong><?php esc_html_e( 'Specific fields', 'sernicola-labs-ai-friendly' ); ?></strong><span><?php esc_html_e( 'Course or Service', 'sernicola-labs-ai-friendly' ); ?></span></div>
                                            <div class="saifr-schema-fields">
                                        <?php foreach ( $rule_fields as $rule_field => $rule_field_meta ) : ?>
                                            <?php if ( in_array( $rule_field, [ 'courseCode', 'educationalLevel', 'serviceType', 'areaServed' ], true ) ) : ?>
                                            <label class="saifr-field" data-rule-applies="<?php echo esc_attr( $rule_field_meta[1] ); ?>">
                                                <span><?php echo esc_html( $rule_field_meta[0] ); ?> <small>(<?php echo esc_html( $rule_field_meta[1] ); ?>)</small></span>
                                                <input type="text" list="<?php echo esc_attr( $datalist_id ); ?>" name="<?php echo esc_attr( $rule_name . '[' . $rule_field . ']' ); ?>" value="<?php echo esc_attr( $type_rule[ $rule_field ] ?? '' ); ?>" placeholder="<?php echo esc_attr( $rule_field_meta[2] ); ?>">
                                            </label>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                            </div>
                                        </section>
                                    </div>
                                        <datalist id="<?php echo esc_attr( $datalist_id ); ?>">
                                            <?php foreach ( saifr_schema_discover_sources( $rule_post_type ) as $suggested_source ) : ?>
                                                <option value="<?php echo esc_attr( $suggested_source ); ?>"></option>
                                            <?php endforeach; ?>
                                        </datalist>
                                    </div>
                                </details>
                            <?php endforeach; ?>
                        </div>
                        <small><?php esc_html_e( 'Dates are recognized in ISO, Ymd (ACF), Unix timestamp, and dd/mm/yyyy formats. An Event without a start date is not published.', 'sernicola-labs-ai-friendly' ); ?></small>
                    </article>

                    <?php if ( class_exists( 'WooCommerce' ) ) : ?>
                    <article class="saifr-schema-card saifr-schema-card-wide">
                        <div class="saifr-schema-card-head">
                            <h4><?php esc_html_e( 'WooCommerce products: merchant listings', 'sernicola-labs-ai-friendly' ); ?></h4>
                            <p><?php esc_html_e( 'Completes the Product schema with brand, shipping, return policy, and price validFrom, the fields flagged by Search Console for merchant listings. Works with WooCommerce structured data, Yoast WooCommerce SEO, and Rank Math, and does not overwrite existing values.', 'sernicola-labs-ai-friendly' ); ?></p>
                        </div>
                        <div class="saifr-schema-fields">
                            <label class="saifr-field saifr-field-check">
                                <input type="checkbox" name="woo_schema_enabled" value="1" <?php checked( $options['woo_schema_enabled'] ); ?>>
                                <span><?php esc_html_e( 'Enrich product schema', 'sernicola-labs-ai-friendly' ); ?></span>
                            </label>
                            <label class="saifr-field">
                                <span><?php esc_html_e( 'Default brand', 'sernicola-labs-ai-friendly' ); ?></span>
                                <input type="text" name="woo_brand_name" value="<?php echo esc_attr( $options['woo_brand_name'] ); ?>" placeholder="<?php esc_attr_e( 'Brand name', 'sernicola-labs-ai-friendly' ); ?>">
                            </label>
                            <label class="saifr-field">
                                <span><?php esc_html_e( 'Brand from product (source)', 'sernicola-labs-ai-friendly' ); ?></span>
                                <input type="text" name="woo_brand_source" list="saifr-sources-product" value="<?php echo esc_attr( $options['woo_brand_source'] ); ?>" placeholder="tax:product_brand">
                            </label>
                            <label class="saifr-field saifr-field-check">
                                <input type="checkbox" name="woo_valid_from" value="1" <?php checked( $options['woo_valid_from'] ); ?>>
                                <span><?php esc_html_e( 'Add validFrom to the price (promotion start or last product modification)', 'sernicola-labs-ai-friendly' ); ?></span>
                            </label>
                        </div>

                        <div class="saifr-schema-subsection-head">
                            <strong><?php esc_html_e( 'Shipping', 'sernicola-labs-ai-friendly' ); ?></strong>
                            <span><?php esc_html_e( 'One rule per area. Cost and free-shipping threshold are in the store currency; leave the threshold empty if shipping always costs the same.', 'sernicola-labs-ai-friendly' ); ?></span>
                        </div>
                        <div class="saifr-schema-repeaters" data-repeater="shipping">
                            <?php foreach ( $woo_shipping_rules as $index => $shipping_rule ) : ?>
                                <div class="saifr-schema-repeater-row saifr-schema-repeater-grid">
                                    <?php foreach ( [ 'countries' => 'IT', 'rate' => '5.90', 'freeThreshold' => '49', 'handlingMin' => '0', 'handlingMax' => '3', 'transitMin' => '1', 'transitMax' => '4' ] as $shipping_field => $shipping_placeholder ) : ?>
                                        <input data-field="<?php echo esc_attr( $shipping_field ); ?>" name="woo_shipping_rules[<?php echo esc_attr( $index ); ?>][<?php echo esc_attr( $shipping_field ); ?>]" value="<?php echo esc_attr( $shipping_rule[ $shipping_field ] ?? '' ); ?>" placeholder="<?php echo esc_attr( $shipping_placeholder ); ?>">
                                    <?php endforeach; ?>
                                    <button type="button" class="button-link-delete saifr-repeater-remove"><?php esc_html_e( 'Remove', 'sernicola-labs-ai-friendly' ); ?></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="button button-secondary saifr-repeater-add" data-target="shipping"><?php esc_html_e( 'Add shipping rule', 'sernicola-labs-ai-friendly' ); ?></button>

                        <div class="saifr-schema-subsection-head">
                            <strong><?php esc_html_e( 'Return policy', 'sernicola-labs-ai-friendly' ); ?></strong>
                            <span><?php esc_html_e( 'The country goods are returned to is the store country set in WooCommerce.', 'sernicola-labs-ai-friendly' ); ?></span>
                        </div>
                        <div class="saifr-schema-fields">
                            <label class="saifr-field">
                                <span><?php esc_html_e( 'Returns', 'sernicola-labs-ai-friendly' ); ?></span>
                                <select name="woo_return_category">
                                    <option value=""><?php esc_html_e( 'Do not specify', 'sernicola-labs-ai-friendly' ); ?></option>
                                    <option value="finite" <?php selected( $options['woo_return_category'], 'finite' ); ?>><?php esc_html_e( 'Within a number of days', 'sernicola-labs-ai-friendly' ); ?></option>
                                    <option value="unlimited" <?php selected( $options['woo_return_category'], 'unlimited' ); ?>><?php esc_html_e( 'No time limit', 'sernicola-labs-ai-friendly' ); ?></option>
                                    <option value="not_permitted" <?php selected( $options['woo_return_category'], 'not_permitted' ); ?>><?php esc_html_e( 'Returns not accepted', 'sernicola-labs-ai-friendly' ); ?></option>
                                </select>
                            </label>
                            <label class="saifr-field">
                                <span><?php esc_html_e( 'Return window (days)', 'sernicola-labs-ai-friendly' ); ?></span>
                                <input type="number" min="0" name="woo_return_days" value="<?php echo esc_attr( $options['woo_return_days'] ); ?>" placeholder="14">
                            </label>
                            <label class="saifr-field">
                                <span><?php esc_html_e( 'Countries where it applies', 'sernicola-labs-ai-friendly' ); ?></span>
                                <input type="text" name="woo_return_countries" value="<?php echo esc_attr( $options['woo_return_countries'] ); ?>" placeholder="<?php echo esc_attr( saifr_woo_store_country() ?: 'IT' ); ?>">
                            </label>
                            <label class="saifr-field">
                                <span><?php esc_html_e( 'Return method', 'sernicola-labs-ai-friendly' ); ?></span>
                                <select name="woo_return_method">
                                    <option value="mail" <?php selected( $options['woo_return_method'], 'mail' ); ?>><?php esc_html_e( 'By mail', 'sernicola-labs-ai-friendly' ); ?></option>
                                    <option value="store" <?php selected( $options['woo_return_method'], 'store' ); ?>><?php esc_html_e( 'In store', 'sernicola-labs-ai-friendly' ); ?></option>
                                    <option value="kiosk" <?php selected( $options['woo_return_method'], 'kiosk' ); ?>><?php esc_html_e( 'Drop-off point', 'sernicola-labs-ai-friendly' ); ?></option>
                                </select>
                            </label>
                            <label class="saifr-field">
                                <span><?php esc_html_e( 'Return fees', 'sernicola-labs-ai-friendly' ); ?></span>
                                <select name="woo_return_fees">
                                    <option value="customer" <?php selected( $options['woo_return_fees'], 'customer' ); ?>><?php esc_html_e( 'Paid by the customer', 'sernicola-labs-ai-friendly' ); ?></option>
                                    <option value="free" <?php selected( $options['woo_return_fees'], 'free' ); ?>><?php esc_html_e( 'Free return', 'sernicola-labs-ai-friendly' ); ?></option>
                                    <option value="shipping" <?php selected( $options['woo_return_fees'], 'shipping' ); ?>><?php esc_html_e( 'Fixed shipping cost', 'sernicola-labs-ai-friendly' ); ?></option>
                                </select>
                            </label>
                            <label class="saifr-field">
                                <span><?php esc_html_e( 'Fixed return cost', 'sernicola-labs-ai-friendly' ); ?></span>
                                <input type="text" name="woo_return_fee_amount" value="<?php echo esc_attr( $options['woo_return_fee_amount'] ); ?>" placeholder="5.90">
                            </label>
                        </div>
                        <?php if ( ! isset( $schema_rule_post_types['product'] ) ) : ?>
                            <datalist id="saifr-sources-product">
                                <?php foreach ( saifr_schema_discover_sources( 'product' ) as $suggested_source ) : ?>
                                    <option value="<?php echo esc_attr( $suggested_source ); ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        <?php endif; ?>
                    </article>
                    <?php endif; ?>

                    <article class="saifr-schema-card saifr-schema-card-seven">
                        <div class="saifr-schema-card-head">
                            <h4><?php esc_html_e( 'Site development', 'sernicola-labs-ai-friendly' ); ?></h4>
                            <p><?php esc_html_e( 'Optionally specify the person or organization that developed the site. The data is published as', 'sernicola-labs-ai-friendly' ); ?> <code><?php esc_html_e( 'creator', 'sernicola-labs-ai-friendly' ); ?></code> <?php esc_html_e( 'of the', 'sernicola-labs-ai-friendly' ); ?> <code><?php esc_html_e( 'WebSite', 'sernicola-labs-ai-friendly' ); ?></code>.</p>
                        </div>
                        <div class="saifr-schema-fields">
                            <label class="saifr-field saifr-field-short">
                                <span><?php esc_html_e( 'Type', 'sernicola-labs-ai-friendly' ); ?></span>
                                <select name="schema_creator_type">
                                    <option value="Organization" <?php selected( $options['schema_creator_type'], 'Organization' ); ?>><?php esc_html_e( 'Organization', 'sernicola-labs-ai-friendly' ); ?></option>
                                    <option value="Person" <?php selected( $options['schema_creator_type'], 'Person' ); ?>><?php esc_html_e( 'Person', 'sernicola-labs-ai-friendly' ); ?></option>
                                </select>
                            </label>
                            <label class="saifr-field">
                                <span><?php esc_html_e( 'Developer / agency name', 'sernicola-labs-ai-friendly' ); ?></span>
                                <input type="text" name="schema_creator_name" value="<?php echo esc_attr( $options['schema_creator_name'] ); ?>" placeholder="<?php esc_attr_e( 'Agency or professional name', 'sernicola-labs-ai-friendly' ); ?>">
                            </label>
                            <label class="saifr-field">
                                <span><?php esc_html_e( 'URL', 'sernicola-labs-ai-friendly' ); ?></span>
                                <input type="url" name="schema_creator_url" value="<?php echo esc_attr( $options['schema_creator_url'] ); ?>" placeholder="<?php esc_attr_e( 'https://www.example.com/', 'sernicola-labs-ai-friendly' ); ?>">
                            </label>
                        </div>
                    </article>

                    <article class="saifr-schema-card saifr-schema-card-five">
                        <div class="saifr-schema-card-head">
                            <h4><?php esc_html_e( 'Profile and license', 'sernicola-labs-ai-friendly' ); ?></h4>
                            <p><?php esc_html_e( 'Link a profile page and a reusable license to the content.', 'sernicola-labs-ai-friendly' ); ?></p>
                        </div>
                        <div class="saifr-schema-fields">
                            <label class="saifr-field">
                                <span><?php esc_html_e( 'ProfilePage page', 'sernicola-labs-ai-friendly' ); ?></span>
                                <?php
                                wp_dropdown_pages(
                                    [
                                        'name'              => 'schema_profile_page_id',
                                        'show_option_none'  => __( 'None', 'sernicola-labs-ai-friendly' ),
                                        'option_none_value' => 0,
                                        'selected'          => intval( $options['schema_profile_page_id'] ),
                                    ]
                                );
                                ?>
                            </label>
                            <label class="saifr-field">
                                <span><?php esc_html_e( 'License URL', 'sernicola-labs-ai-friendly' ); ?></span>
                                <input type="url" name="schema_license" value="<?php echo esc_attr( $options['schema_license'] ); ?>" placeholder="<?php esc_attr_e( 'https://creativecommons.org/licenses/by/4.0/', 'sernicola-labs-ai-friendly' ); ?>">
                            </label>
                        </div>
                    </article>
                </div>
            </section>

            <section id="saifr-section-automation" class="saifr-section">
                <div class="saifr-section-heading"><span>05</span><div><h3><?php esc_html_e( 'Automation', 'sernicola-labs-ai-friendly' ); ?></h3><p><?php esc_html_e( 'Control generation, frequency, notifications, and operational history.', 'sernicola-labs-ai-friendly' ); ?></p></div></div>
                <div class="saifr-settings-cards">
                    <article class="saifr-settings-card">
                        <div class="saifr-card-head"><span>01</span><div><h4><?php esc_html_e( 'Static output', 'sernicola-labs-ai-friendly' ); ?></h4><p><?php esc_html_e( 'Persistence and space used by the Markdown files.', 'sernicola-labs-ai-friendly' ); ?></p></div></div>
                        <div class="saifr-option-list">
                            <label>
                                <input type="checkbox" id="saifr-static-md-files" name="static_md_files" value="1" <?php checked( $options['static_md_files'] ); ?>>
                                <?php esc_html_e( 'Save and serve static MD files', 'sernicola-labs-ai-friendly' ); ?>
                            </label>
                            <label><input type="checkbox" name="llms_full_enabled" value="1" <?php checked( $options['llms_full_enabled'] ); ?>> <?php esc_html_e( 'Publish llms-full.txt after the full regeneration', 'sernicola-labs-ai-friendly' ); ?></label>
                            <?php if ( ! empty( $options['llms_full_enabled'] ) ) : ?><p><?php echo is_file( saifr_llms_full_target() ) ? esc_html__( 'Corpus ready', 'sernicola-labs-ai-friendly' ) . ' (' . esc_html( size_format( filesize( saifr_llms_full_target() ) ) ) . ')' : esc_html__( 'Corpus waiting for the first full generation', 'sernicola-labs-ai-friendly' ); ?> · <a href="<?php echo esc_url( home_url( '/llms-full.txt' ) ); ?>" target="_blank"><?php esc_html_e( 'Open llms-full.txt', 'sernicola-labs-ai-friendly' ); ?></a></p><?php endif; ?>
                            <p class="description saifr-statline">
                                <?php esc_html_e( 'Saved files:', 'sernicola-labs-ai-friendly' ); ?> <?php echo intval( $version_stats['count'] ); ?> |
                                <?php esc_html_e( 'Size:', 'sernicola-labs-ai-friendly' ); ?> <?php echo esc_html( size_format( intval( $version_stats['size'] ) ) ); ?>
                            </p>
                        </div>
                    </article>
                    <article class="saifr-settings-card">
                        <div class="saifr-card-head"><span>02</span><div><h4><?php esc_html_e( 'Automatic regeneration', 'sernicola-labs-ai-friendly' ); ?></h4><p><?php esc_html_e( 'Scheduling and batch size.', 'sernicola-labs-ai-friendly' ); ?></p></div></div>
                        <div class="saifr-option-list">
                            <label>
                                <input type="checkbox" id="saifr-auto-regenerate" name="auto_regenerate" value="1" <?php checked( $options['auto_regenerate'] ); ?>>
                                <?php esc_html_e( 'Regenerate .md files at an interval across the whole site (cron)', 'sernicola-labs-ai-friendly' ); ?>
                            </label>
                            <div class="saifr-number-fields">
                                <label><?php esc_html_e( 'Interval (hours)', 'sernicola-labs-ai-friendly' ); ?><input type="number" name="regenerate_interval" min="1" max="168" value="<?php echo esc_attr( $options['regenerate_interval'] ); ?>"></label>
                                <label><?php esc_html_e( 'Content per run', 'sernicola-labs-ai-friendly' ); ?><input type="number" name="regenerate_batch_size" min="1" max="1000" value="<?php echo esc_attr( intval( $options['regenerate_batch_size'] ?? 100 ) ); ?>"></label>
                            </div>
                            <p class="description"><?php esc_html_e( 'For very large sites, cron processes only this number of content items per run and continues from the next one.', 'sernicola-labs-ai-friendly' ); ?></p>
                            <?php if ( $next_cron ) : ?>
                                <p class="description"><?php esc_html_e( 'Next run:', 'sernicola-labs-ai-friendly' ); ?> <?php echo esc_html( date_i18n( 'Y-m-d H:i:s', $next_cron ) ); ?></p>
                            <?php endif; ?>
                        </div>
                    </article>
                    <article class="saifr-settings-card">
                        <div class="saifr-card-head"><span>03</span><div><h4><?php esc_html_e( 'Event triggers', 'sernicola-labs-ai-friendly' ); ?></h4><p><?php esc_html_e( 'Decide when a content item should update the output.', 'sernicola-labs-ai-friendly' ); ?></p></div></div>
                        <div class="saifr-option-list">
                            <label>
                                <input type="checkbox" name="regenerate_on_save" value="1" <?php checked( $options['regenerate_on_save'] ); ?>>
                                <?php esc_html_e( 'Regenerate when content is saved/updated', 'sernicola-labs-ai-friendly' ); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="regenerate_on_change" value="1" <?php checked( $options['regenerate_on_change'] ); ?>>
                                <?php esc_html_e( 'Regenerate only if the content has changed (checksum)', 'sernicola-labs-ai-friendly' ); ?>
                            </label>
                        </div>
                    </article>
                    <article class="saifr-settings-card">
                        <div class="saifr-card-head"><span>04</span><div><h4><?php esc_html_e( 'Notifications', 'sernicola-labs-ai-friendly' ); ?></h4><p><?php esc_html_e( 'Report errors to administrators.', 'sernicola-labs-ai-friendly' ); ?></p></div></div>
                        <div class="saifr-option-list">
                            <label>
                                <input type="checkbox" name="notify_admin_notice" value="1" <?php checked( $options['notify_admin_notice'] ?? '' ); ?>>
                                <?php esc_html_e( 'Show an admin notice when a regeneration has errors', 'sernicola-labs-ai-friendly' ); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="notify_email" value="1" <?php checked( $options['notify_email'] ?? '' ); ?>>
                                <?php esc_html_e( 'Send an email when regeneration errors occur', 'sernicola-labs-ai-friendly' ); ?>
                            </label>
                            <label class="saifr-stacked-label"><?php esc_html_e( 'Recipient email', 'sernicola-labs-ai-friendly' ); ?>
                                <input type="email" name="notify_email_to" value="<?php echo esc_attr( $options['notify_email_to'] ?? '' ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
                            </label>
                        </div>
                    </article>
                    <article class="saifr-settings-card saifr-settings-card-wide">
                        <div class="saifr-card-head"><span>05</span><div><h4><?php esc_html_e( 'Manual actions', 'sernicola-labs-ai-friendly' ); ?></h4><p><?php esc_html_e( 'Generate, force, or remove static files.', 'sernicola-labs-ai-friendly' ); ?></p></div></div>
                        <div class="saifr-action-row">
                            <button type="button" id="saifr-regenerate" class="button button-primary"><?php esc_html_e( 'Regenerate all MD files', 'sernicola-labs-ai-friendly' ); ?></button>
                            <button type="button" id="saifr-regenerate-force" class="button"><?php esc_html_e( 'Force regeneration', 'sernicola-labs-ai-friendly' ); ?></button>
                            <button type="button" id="saifr-clear-versions" class="button saifr-button-danger"><?php esc_html_e( 'Delete all files', 'sernicola-labs-ai-friendly' ); ?></button>
                            <p id="saifr-action-status" role="status" aria-live="polite"></p>
                            <?php if ( ! empty( $last_regen['stats'] ) ) : ?>
                                <p class="description">
                                    <?php esc_html_e( 'Last run:', 'sernicola-labs-ai-friendly' ); ?>
                                    <?php esc_html_e( 'Processed', 'sernicola-labs-ai-friendly' ); ?> <?php echo intval( $last_regen['stats']['processed'] ?? 0 ); ?>,
                                    <?php esc_html_e( 'Regenerated', 'sernicola-labs-ai-friendly' ); ?> <?php echo intval( $last_regen['stats']['regenerated'] ?? 0 ); ?>,
                                    <?php esc_html_e( 'Skipped', 'sernicola-labs-ai-friendly' ); ?> <?php echo intval( $last_regen['stats']['skipped'] ?? 0 ); ?>,
                                    <?php esc_html_e( 'Errors', 'sernicola-labs-ai-friendly' ); ?> <?php echo intval( $last_regen['stats']['errors'] ?? 0 ); ?>.
                                </p>
                            <?php endif; ?>
                        </div>
                    </article>
                </div>

                <div class="saifr-timeline">
                    <h3><?php esc_html_e( 'Update timeline', 'sernicola-labs-ai-friendly' ); ?></h3>
                    <button type="button" id="saifr-refresh-timeline" class="button button-secondary"><?php esc_html_e( 'Refresh timeline', 'sernicola-labs-ai-friendly' ); ?></button>
                    <ul id="saifr-timeline-list" class="saifr-list"></ul>
                </div>
            </section>

            <div class="saifr-submit-wrap" id="saifr-submit-wrap" aria-live="polite">
                <span id="saifr-dirty-state"><?php esc_html_e( 'All changes saved', 'sernicola-labs-ai-friendly' ); ?></span>
                <input type="submit" name="saifr_save" class="button button-primary" value="<?php esc_attr_e( 'Save settings', 'sernicola-labs-ai-friendly' ); ?>">
            </div>
        </form>
        <p class="saifr-credit">
            <?php esc_html_e( 'Developed by', 'sernicola-labs-ai-friendly' ); ?>
            <a href="<?php echo esc_url( 'https://www.sernicola-labs.com/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Sernicola Labs', 'sernicola-labs-ai-friendly' ); ?></a>
        </p>
    </div>
    <?php
}

add_filter(
    'plugin_action_links_' . plugin_basename( SAIFR_PLUGIN_FILE ),
    function ( array $links ): array {
        $settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=sernicola-labs-ai-friendly' ) ) . '">' . esc_html__( 'Settings', 'sernicola-labs-ai-friendly' ) . '</a>';
        $github_link   = '<a href="https://github.com/Sernicola-Labs-Srl/ai-friendly" target="_blank" rel="noopener noreferrer">GitHub</a>';
        array_unshift( $links, $settings_link, $github_link );
        return $links;
    }
);
