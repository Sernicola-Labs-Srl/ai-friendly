<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action(
    'admin_menu',
    function () {
        add_options_page(
            'Sernicola Labs | AI Friendly - AI Content Hub',
            'Sernicola Labs | AI Friendly',
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

        wp_enqueue_style(
            'ai-fr-admin',
            plugins_url( 'admin/assets/ai-fr-admin.css', AI_FR_PLUGIN_FILE ),
            [],
            '1.6.0'
        );

        wp_enqueue_script(
            'ai-fr-admin',
            plugins_url( 'admin/assets/ai-fr-admin.js', AI_FR_PLUGIN_FILE ),
            [ 'jquery' ],
            '1.6.0',
            true
        );

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

add_action(
    'wp_ajax_ai_fr_regenerate_all',
    function (): void {
        ai_fr_admin_require_permissions();
        $force = ! empty( $_POST['force'] );
        $stats = ai_fr_regenerate_all( $force, 'manual_ajax' );
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
                'page'      => intval( $_POST['page'] ?? 1 ),
                'per_page'  => intval( $_POST['per_page'] ?? 10 ),
                'search'    => sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) ),
                'status'    => sanitize_key( $_POST['status'] ?? 'any' ),
                'post_type' => sanitize_key( $_POST['post_type'] ?? 'all' ),
            ]
        );
        wp_send_json_success( $result );
    }
);

add_action(
    'wp_ajax_ai_fr_toggle_content_exclusion',
    function (): void {
        ai_fr_admin_require_permissions();
        $post_id = intval( $_POST['post_id'] ?? 0 );
        $exclude = ! empty( $_POST['exclude'] );
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
                'exclude'   => $exclude ? 1 : 0,
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
        $limit = min( 100, max( 5, intval( $_POST['limit'] ?? 20 ) ) );
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
        $content = isset( $_POST['content'] ) ? (string) wp_unslash( $_POST['content'] ) : ai_fr_build_llms_txt();

        wp_send_json_success(
            [
                'html'       => ai_fr_render_markdown_preview_html( $content ),
                'tokens'     => ai_fr_estimate_tokens( $content ),
                'chars'      => strlen( $content ),
                'simulation' => ai_fr_run_ai_simulation( $content ),
            ]
        );
    }
);

add_action(
    'wp_ajax_ai_fr_create_llms_snapshot',
    function (): void {
        ai_fr_admin_require_permissions();
        $reason  = sanitize_text_field( wp_unslash( $_POST['reason'] ?? 'manual' ) );
        $content = isset( $_POST['content'] ) ? (string) wp_unslash( $_POST['content'] ) : ai_fr_build_llms_txt();
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
        $id     = sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) );
        $result = ai_fr_restore_llms_snapshot( $id );

        if ( empty( $result['restored'] ) ) {
            wp_send_json_error( $result['message'] ?? 'Ripristino fallito.' );
        }

        ai_fr_add_event( 'llms_snapshot_restore', [ 'id' => $id ] );
        wp_send_json_success( $result );
    }
);

add_action(
    'wp_ajax_ai_fr_run_ai_simulation',
    function (): void {
        ai_fr_admin_require_permissions();
        $content = (string) wp_unslash( $_POST['content'] ?? '' );
        wp_send_json_success( ai_fr_run_ai_simulation( $content ) );
    }
);

function ai_fr_render_options_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $defaults = ai_fr_get_default_options();
    $options  = wp_parse_args( get_option( 'ai_fr_options', [] ), $defaults );

    if ( isset( $_POST['ai_fr_save'] ) && check_admin_referer( 'ai_fr_options_nonce' ) ) {
        $options['llms_content']         = sanitize_textarea_field( wp_unslash( $_POST['llms_content'] ?? '' ) );
        $options['llms_include_auto']    = ! empty( $_POST['llms_include_auto'] ) ? '1' : '';
        $options['include_pages']        = ! empty( $_POST['include_pages'] ) ? '1' : '';
        $options['include_posts']        = ! empty( $_POST['include_posts'] ) ? '1' : '';
        $options['include_products']     = ! empty( $_POST['include_products'] ) ? '1' : '';
        $options['include_cpt']          = array_map( 'sanitize_key', (array) ( $_POST['include_cpt'] ?? [] ) );
        $options['exclude_categories']   = array_map( 'intval', (array) ( $_POST['exclude_categories'] ?? [] ) );
        $options['exclude_tags']         = array_map( 'intval', (array) ( $_POST['exclude_tags'] ?? [] ) );
        $options['exclude_templates']    = array_map( 'sanitize_text_field', (array) ( $_POST['exclude_templates'] ?? [] ) );
        $options['exclude_url_patterns'] = sanitize_textarea_field( wp_unslash( $_POST['exclude_url_patterns'] ?? '' ) );
        $options['exclude_noindex']      = ! empty( $_POST['exclude_noindex'] ) ? '1' : '';
        $options['exclude_password']     = ! empty( $_POST['exclude_password'] ) ? '1' : '';
        $options['static_md_files']      = ! empty( $_POST['static_md_files'] ) ? '1' : '';
        $options['auto_regenerate']      = ! empty( $_POST['auto_regenerate'] ) ? '1' : '';
        $options['regenerate_interval']  = max( 1, intval( $_POST['regenerate_interval'] ?? 24 ) );
        $options['regenerate_on_save']   = ! empty( $_POST['regenerate_on_save'] ) ? '1' : '';
        $options['regenerate_on_change'] = ! empty( $_POST['regenerate_on_change'] ) ? '1' : '';
        $options['onboarding_done']      = ! empty( $_POST['onboarding_done'] ) ? '1' : '';
        $options['ui_version']           = 'hub-v1';

        update_option( 'ai_fr_options', $options );
        update_option( 'ai_fr_onboarding_done', $options['onboarding_done'], false );
        update_option( 'ai_fr_ui_version', 'hub-v1', false );

        ai_fr_schedule_cron();
        ai_fr_add_event( 'settings_saved', [ 'source' => 'admin_page' ] );

        echo '<div class="notice notice-success"><p>Impostazioni salvate.</p></div>';
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

    ?>
    <div class="wrap ai-fr-wrap">
        <h1>AI Friendly - AI Content Hub <small class="ai-fr-version">v1.6.0</small></h1>

        <?php if ( ! $onboarding_done ) : ?>
            <div class="ai-fr-onboarding">
                <strong>Setup rapido</strong>
                <p>Imposta il tipo di sito e genera una prima base per llms.txt.</p>
                <div class="ai-fr-onboarding-actions">
                    <button type="button" class="button" data-ai-fr-preset="blog">Preset Blog</button>
                    <button type="button" class="button" data-ai-fr-preset="azienda">Preset Azienda</button>
                    <button type="button" class="button" data-ai-fr-preset="ecommerce">Preset E-commerce</button>
                    <button type="button" class="button button-secondary" id="ai-fr-onboarding-dismiss">Completa e nascondi</button>
                </div>
            </div>
        <?php endif; ?>

        <form method="post" id="ai-fr-main-form">
            <?php wp_nonce_field( 'ai_fr_options_nonce' ); ?>
            <input type="hidden" name="onboarding_done" id="onboarding_done" value="<?php echo ! empty( $options['onboarding_done'] ) ? '1' : ''; ?>">

            <nav class="ai-fr-nav">
                <button type="button" class="ai-fr-nav-item is-active" data-section="overview">Overview</button>
                <button type="button" class="ai-fr-nav-item" data-section="content">Content</button>
                <button type="button" class="ai-fr-nav-item" data-section="rules">Rules</button>
                <button type="button" class="ai-fr-nav-item" data-section="automation">Automation</button>
            </nav>

            <section id="ai-fr-section-overview" class="ai-fr-section is-active">
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
                        <button type="button" id="ai-fr-regenerate-overview" class="button button-primary">Rigenera llms/MD</button>
                    </article>

                    <article class="ai-fr-card">
                        <h3>Avvisi rapidi</h3>
                        <ul id="ai-fr-overview-warnings" class="ai-fr-list">
                            <?php foreach ( $overview['diagnostics']['warnings'] as $warning ) : ?>
                                <li><?php echo esc_html( $warning['message'] ?? '' ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" id="ai-fr-refresh-diagnostics" class="button">Aggiorna diagnostica</button>
                    </article>
                </div>

                <div class="ai-fr-quick-actions">
                    <button type="button" class="button button-primary" data-section-jump="content">Apri Editor</button>
                    <button type="button" class="button" id="ai-fr-refresh-overview">Aggiorna Overview</button>
                    <button type="button" class="button" id="ai-fr-run-now">Esegui ora</button>
                </div>
            </section>

            <section id="ai-fr-section-content" class="ai-fr-section">
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
                    </div>
                    <ul id="ai-fr-snapshot-list" class="ai-fr-list"></ul>
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
                </div>
            </section>

            <section id="ai-fr-section-rules" class="ai-fr-section">
                <h3>Filtri & esclusioni</h3>
                <table class="form-table">
                    <tr>
                        <th>Contenuti standard</th>
                        <td>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="checkbox" name="include_pages" value="1" <?php checked( $options['include_pages'] ); ?>>
                                Pagine
                            </label>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="checkbox" name="include_posts" value="1" <?php checked( $options['include_posts'] ); ?>>
                                Articoli (Post)
                            </label>
                            <?php if ( class_exists( 'WooCommerce' ) ) : ?>
                                <label style="display:block; margin-bottom:8px;">
                                    <input type="checkbox" name="include_products" value="1" <?php checked( $options['include_products'] ); ?>>
                                    Prodotti WooCommerce
                                </label>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ( ! empty( $all_cpt ) ) : ?>
                    <tr>
                        <th>Custom Post Types</th>
                        <td>
                            <?php foreach ( $all_cpt as $cpt ) : ?>
                                <label style="display:block; margin-bottom:8px;">
                                    <input type="checkbox" name="include_cpt[]" value="<?php echo esc_attr( $cpt->name ); ?>"
                                        <?php checked( in_array( $cpt->name, (array) $options['include_cpt'], true ) ); ?>>
                                    <?php echo esc_html( $cpt->labels->name ); ?> <code>(<?php echo esc_html( $cpt->name ); ?>)</code>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Opzioni generali</th>
                        <td>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="checkbox" name="exclude_noindex" value="1" <?php checked( $options['exclude_noindex'] ); ?>>
                                Escludi pagine con meta <code>noindex</code>
                            </label>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="checkbox" name="exclude_password" value="1" <?php checked( $options['exclude_password'] ); ?>>
                                Escludi contenuti protetti da password
                            </label>
                        </td>
                    </tr>
                    <?php if ( ! empty( $all_categories ) ) : ?>
                    <tr>
                        <th>Escludi categorie</th>
                        <td>
                            <select name="exclude_categories[]" multiple size="6" style="min-width:300px;">
                                <?php foreach ( $all_categories as $cat ) : ?>
                                    <option value="<?php echo esc_attr( $cat->term_id ); ?>"
                                        <?php selected( in_array( $cat->term_id, (array) $options['exclude_categories'], false ) ); ?>>
                                        <?php echo esc_html( $cat->name ); ?> (<?php echo intval( $cat->count ); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php if ( ! empty( $all_tags ) ) : ?>
                    <tr>
                        <th>Escludi tag</th>
                        <td>
                            <select name="exclude_tags[]" multiple size="6" style="min-width:300px;">
                                <?php foreach ( $all_tags as $tag ) : ?>
                                    <option value="<?php echo esc_attr( $tag->term_id ); ?>"
                                        <?php selected( in_array( $tag->term_id, (array) $options['exclude_tags'], false ) ); ?>>
                                        <?php echo esc_html( $tag->name ); ?> (<?php echo intval( $tag->count ); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php if ( ! empty( $all_templates ) ) : ?>
                    <tr>
                        <th>Escludi template</th>
                        <td>
                            <select name="exclude_templates[]" multiple size="6" style="min-width:300px;">
                                <?php foreach ( $all_templates as $file => $name ) : ?>
                                    <option value="<?php echo esc_attr( $file ); ?>"
                                        <?php selected( in_array( $file, (array) $options['exclude_templates'], true ) ); ?>>
                                        <?php echo esc_html( $name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Pattern URL</th>
                        <td>
                            <textarea name="exclude_url_patterns" id="exclude_url_patterns" rows="5" cols="50" class="code"><?php echo esc_textarea( $options['exclude_url_patterns'] ); ?></textarea>
                            <p class="description">Un pattern per riga, con wildcard <code>*</code>.</p>
                        </td>
                    </tr>
                </table>
            </section>

            <section id="ai-fr-section-automation" class="ai-fr-section">
                <h3>Automation</h3>
                <table class="form-table">
                    <tr>
                        <th>File MD statici</th>
                        <td>
                            <label>
                                <input type="checkbox" name="static_md_files" value="1" <?php checked( $options['static_md_files'] ); ?>>
                                Salva e servi file MD statici
                            </label>
                            <p class="description">
                                File salvati: <?php echo intval( $version_stats['count'] ); ?> |
                                Spazio: <?php echo esc_html( size_format( intval( $version_stats['size'] ) ) ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th>Rigenerazione automatica</th>
                        <td>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="checkbox" name="auto_regenerate" value="1" <?php checked( $options['auto_regenerate'] ); ?>>
                                Attiva rigenerazione automatica via cron
                            </label>
                            <label style="display:block;">
                                Intervallo:
                                <input type="number" name="regenerate_interval" min="1" max="168" value="<?php echo esc_attr( $options['regenerate_interval'] ); ?>" style="width:60px;">
                                ore
                            </label>
                            <?php if ( $next_cron ) : ?>
                                <p class="description">Prossima esecuzione: <?php echo esc_html( date_i18n( 'Y-m-d H:i:s', $next_cron ) ); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Trigger su eventi</th>
                        <td>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="checkbox" name="regenerate_on_save" value="1" <?php checked( $options['regenerate_on_save'] ); ?>>
                                Rigenera quando un contenuto viene salvato/aggiornato
                            </label>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="checkbox" name="regenerate_on_change" value="1" <?php checked( $options['regenerate_on_change'] ); ?>>
                                Rigenera solo se il contenuto e cambiato (checksum)
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>Azioni</th>
                        <td>
                            <button type="button" id="ai-fr-regenerate" class="button button-primary">Rigenera tutti i file MD</button>
                            <button type="button" id="ai-fr-regenerate-force" class="button">Forza rigenerazione</button>
                            <button type="button" id="ai-fr-clear-versions" class="button">Elimina tutti i file</button>
                            <p id="ai-fr-action-status"></p>
                            <?php if ( ! empty( $last_regen['stats'] ) ) : ?>
                                <p class="description">
                                    Ultimo run: Processati <?php echo intval( $last_regen['stats']['processed'] ?? 0 ); ?>,
                                    Rigenerati <?php echo intval( $last_regen['stats']['regenerated'] ?? 0 ); ?>,
                                    Saltati <?php echo intval( $last_regen['stats']['skipped'] ?? 0 ); ?>,
                                    Errori <?php echo intval( $last_regen['stats']['errors'] ?? 0 ); ?>.
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <div class="ai-fr-timeline">
                    <h3>Timeline aggiornamenti</h3>
                    <button type="button" id="ai-fr-refresh-timeline" class="button button-secondary">Aggiorna timeline</button>
                    <ul id="ai-fr-timeline-list" class="ai-fr-list"></ul>
                </div>
            </section>

            <p class="submit">
                <input type="submit" name="ai_fr_save" class="button button-primary" value="Salva impostazioni">
            </p>
        </form>
    </div>
    <?php
}

add_filter(
    'plugin_action_links_' . plugin_basename( AI_FR_PLUGIN_FILE ),
    function ( array $links ): array {
        $settings_link = '<a href="' . admin_url( 'options-general.php?page=ai-friendly' ) . '">Impostazioni</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }
);
