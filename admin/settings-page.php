<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

//  7 — ADMIN
// ═══════════════════════════════════════════════════════════════════════════════

add_action( 'admin_menu', function () {
    add_options_page(
        'Sernicola Labs | AI Friendly — llms.txt & Markdown',
        'Sernicola Labs | AI Friendly — llms.txt & Markdown',
        'manage_options',
        'ai-friendly',
        'ai_fr_render_options_page'
    );
} );

// AJAX per rigenerazione manuale
add_action( 'wp_ajax_ai_fr_regenerate_all', function() {
    check_ajax_referer( 'ai_fr_admin_nonce', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permessi insufficienti' );
    }
    
    $force = ! empty( $_POST['force'] );
    $stats = ai_fr_regenerate_all( $force );
    
    wp_send_json_success( $stats );
} );

// AJAX per pulizia versioni
add_action( 'wp_ajax_ai_fr_clear_versions', function() {
    check_ajax_referer( 'ai_fr_admin_nonce', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permessi insufficienti' );
    }
    
    $count = AiFrVersioning::clearAll();
    
    wp_send_json_success( [ 'deleted' => $count ] );
} );

function ai_fr_render_options_page(): void {

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $defaults = ai_fr_get_default_options();
    $options = wp_parse_args( get_option( 'ai_fr_options', [] ), $defaults );

    // Salvataggio
    if ( isset( $_POST['ai_fr_save'] ) && check_admin_referer( 'ai_fr_options_nonce' ) ) {
        $options['llms_content']        = sanitize_textarea_field( wp_unslash( $_POST['llms_content'] ?? '' ) );
        $options['llms_include_auto']   = ! empty( $_POST['llms_include_auto'] ) ? '1' : '';
        
        // Tipi di contenuto
        $options['include_pages']       = ! empty( $_POST['include_pages'] ) ? '1' : '';
        $options['include_posts']       = ! empty( $_POST['include_posts'] ) ? '1' : '';
        $options['include_products']    = ! empty( $_POST['include_products'] ) ? '1' : '';
        $options['include_cpt']         = array_map( 'sanitize_key', (array) ( $_POST['include_cpt'] ?? [] ) );
        
        // Esclusioni
        $options['exclude_categories']  = array_map( 'intval', (array) ( $_POST['exclude_categories'] ?? [] ) );
        $options['exclude_tags']        = array_map( 'intval', (array) ( $_POST['exclude_tags'] ?? [] ) );
        $options['exclude_templates']   = array_map( 'sanitize_text_field', (array) ( $_POST['exclude_templates'] ?? [] ) );
        $options['exclude_url_patterns'] = sanitize_textarea_field( wp_unslash( $_POST['exclude_url_patterns'] ?? '' ) );
        $options['exclude_noindex']     = ! empty( $_POST['exclude_noindex'] ) ? '1' : '';
        $options['exclude_password']    = ! empty( $_POST['exclude_password'] ) ? '1' : '';
        
        // Versioning
        $options['static_md_files']     = ! empty( $_POST['static_md_files'] ) ? '1' : '';
        
        // Scheduler
        $options['auto_regenerate']     = ! empty( $_POST['auto_regenerate'] ) ? '1' : '';
        $options['regenerate_interval'] = max( 1, intval( $_POST['regenerate_interval'] ?? 24 ) );
        $options['regenerate_on_save']  = ! empty( $_POST['regenerate_on_save'] ) ? '1' : '';
        $options['regenerate_on_change'] = ! empty( $_POST['regenerate_on_change'] ) ? '1' : '';

        update_option( 'ai_fr_options', $options );
        
        // Aggiorna cron
        ai_fr_schedule_cron();

        echo '<div class="notice notice-success"><p>Impostazioni salvate.</p></div>';
    }

    // Dati per il form
    $all_categories = get_categories( [ 'hide_empty' => false ] );
    $all_tags = get_tags( [ 'hide_empty' => false ] );
    $all_templates = wp_get_theme()->get_page_templates();
    $all_cpt = get_post_types( [ 'public' => true, '_builtin' => false ], 'objects' );
    
    // Statistiche versioni
    $version_stats = AiFrVersioning::getStats();
    $last_regen = get_option( 'ai_fr_last_regeneration', [] );
    
    // Prossimo cron
    $next_cron = wp_next_scheduled( 'ai_fr_cron_regenerate' );

    $name = get_bloginfo( 'blogname' );
    $desc = get_bloginfo( 'description' ) ?: 'Descrizione del sito';

    ?>
    <div class="wrap">
        <h1>AI Friendly — Impostazioni <small style="font-weight:normal; color:#666;">v1.5.2</small></h1>

        <form method="post">
            <?php wp_nonce_field( 'ai_fr_options_nonce' ); ?>
            
            <!-- Tab Navigation -->
            <h2 class="nav-tab-wrapper">
                <a href="#tab-content" class="nav-tab nav-tab-active" data-tab="content">Contenuto llms.txt</a>
                <a href="#tab-filters" class="nav-tab" data-tab="filters">Filtri & Esclusioni</a>
                <a href="#tab-versioning" class="nav-tab" data-tab="versioning">Versioning MD</a>
                <a href="#tab-scheduler" class="nav-tab" data-tab="scheduler">Scheduler</a>
            </h2>

            <!-- TAB: Contenuto llms.txt -->
            <div id="tab-content" class="ai-fr-tab-content" style="display:block;">
                <table class="form-table">
                    <tr>
                        <th><label for="llms_content">Contenuto llms.txt</label></th>
                        <td>
                            <textarea name="llms_content" id="llms_content" rows="15" cols="100" class="large-text code"
                                placeholder="# <?php echo esc_attr( $name ); ?>&#10;> <?php echo esc_attr( $desc ); ?>"
                            ><?php echo esc_textarea( $options['llms_content'] ); ?></textarea>
                            <p class="description">Contenuto custom in Markdown. Lascia vuoto per generazione automatica.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Lista automatica</th>
                        <td>
                            <label>
                                <input type="checkbox" name="llms_include_auto" value="1" <?php checked( $options['llms_include_auto'] ); ?> />
                                Aggiungi lista automatica dopo il contenuto custom
                            </label>
                        </td>
                    </tr>
                </table>
                
                <h3>Anteprima</h3>
                <p>
                    <code><?php echo esc_html( home_url( '/llms.txt' ) ); ?></code>
                    <a href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>" target="_blank" class="button button-secondary">Visualizza →</a>
                </p>
            </div>

            <!-- TAB: Filtri & Esclusioni -->
            <div id="tab-filters" class="ai-fr-tab-content" style="display:none;">
                
                <h3>Tipi di contenuto da includere</h3>
                <table class="form-table">
                    <tr>
                        <th>Contenuti standard</th>
                        <td>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="checkbox" name="include_pages" value="1" <?php checked( $options['include_pages'] ); ?> />
                                Pagine
                            </label>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="checkbox" name="include_posts" value="1" <?php checked( $options['include_posts'] ); ?> />
                                Articoli (Post)
                            </label>
                            <?php if ( class_exists( 'WooCommerce' ) ) : ?>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="checkbox" name="include_products" value="1" <?php checked( $options['include_products'] ); ?> />
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
                                    <?php checked( in_array( $cpt->name, (array) $options['include_cpt'], true ) ); ?> />
                                <?php echo esc_html( $cpt->labels->name ); ?> <code>(<?php echo esc_html( $cpt->name ); ?>)</code>
                            </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
                
                <h3>Esclusioni</h3>
                <table class="form-table">
                    <tr>
                        <th>Opzioni generali</th>
                        <td>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="checkbox" name="exclude_noindex" value="1" <?php checked( $options['exclude_noindex'] ); ?> />
                                Escludi pagine con meta <code>noindex</code> (Yoast, Rank Math, etc.)
                            </label>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="checkbox" name="exclude_password" value="1" <?php checked( $options['exclude_password'] ); ?> />
                                Escludi contenuti protetti da password
                            </label>
                        </td>
                    </tr>
                    
                    <?php if ( ! empty( $all_categories ) ) : ?>
                    <tr>
                        <th><label>Escludi categorie</label></th>
                        <td>
                            <select name="exclude_categories[]" multiple size="6" style="min-width:300px;">
                                <?php foreach ( $all_categories as $cat ) : ?>
                                <option value="<?php echo esc_attr( $cat->term_id ); ?>"
                                    <?php selected( in_array( $cat->term_id, (array) $options['exclude_categories'], false ) ); ?>>
                                    <?php echo esc_html( $cat->name ); ?> (<?php echo $cat->count; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Tieni premuto Ctrl/Cmd per selezione multipla</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if ( ! empty( $all_tags ) ) : ?>
                    <tr>
                        <th><label>Escludi tag</label></th>
                        <td>
                            <select name="exclude_tags[]" multiple size="6" style="min-width:300px;">
                                <?php foreach ( $all_tags as $tag ) : ?>
                                <option value="<?php echo esc_attr( $tag->term_id ); ?>"
                                    <?php selected( in_array( $tag->term_id, (array) $options['exclude_tags'], false ) ); ?>>
                                    <?php echo esc_html( $tag->name ); ?> (<?php echo $tag->count; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if ( ! empty( $all_templates ) ) : ?>
                    <tr>
                        <th><label>Escludi template</label></th>
                        <td>
                            <select name="exclude_templates[]" multiple size="5" style="min-width:300px;">
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
                        <th><label for="exclude_url_patterns">Escludi pattern URL</label></th>
                        <td>
                            <textarea name="exclude_url_patterns" id="exclude_url_patterns" rows="5" cols="50" class="code"
                                placeholder="/landing/*&#10;/promo-*&#10;/test/"
                            ><?php echo esc_textarea( $options['exclude_url_patterns'] ); ?></textarea>
                            <p class="description">
                                Un pattern per riga. Supporta <code>*</code> come wildcard.<br>
                                Esempio: <code>/landing/*</code> esclude tutte le pagine sotto /landing/
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- TAB: Versioning MD -->
            <div id="tab-versioning" class="ai-fr-tab-content" style="display:none;">
                <table class="form-table">
                    <tr>
                        <th>File MD statici</th>
                        <td>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="checkbox" name="static_md_files" value="1" <?php checked( $options['static_md_files'] ); ?> />
                                Salva e servi file MD statici (più veloce)
                            </label>
                            <p class="description">
                                Se attivo, i file .md vengono salvati su disco e serviti direttamente.<br>
                                Se disattivo, vengono generati dinamicamente ad ogni richiesta.<br>
                                Directory: <code><?php echo esc_html( AI_FR_VERSIONS_DIR ); ?></code>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th>Statistiche</th>
                        <td>
                            <div style="background:#f5f5f5; padding:15px; border-radius:4px;">
                                <strong>File salvati:</strong> <?php echo $version_stats['count']; ?><br>
                                <strong>Spazio utilizzato:</strong> <?php echo size_format( $version_stats['size'] ); ?><br>
                                <?php if ( ! empty( $last_regen['time'] ) ) : ?>
                                <strong>Ultima rigenerazione:</strong> <?php echo esc_html( $last_regen['time'] ); ?>
                                    <?php if ( ! empty( $last_regen['stats'] ) ) : ?>
                                    <br><small>
                                        Processati: <?php echo $last_regen['stats']['processed']; ?> |
                                        Rigenerati: <?php echo $last_regen['stats']['regenerated']; ?> |
                                        Saltati: <?php echo $last_regen['stats']['skipped']; ?> |
                                        Errori: <?php echo $last_regen['stats']['errors']; ?>
                                    </small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th>Azioni</th>
                        <td>
                            <button type="button" id="ai-fr-regenerate" class="button button-primary">
                                🔄 Rigenera tutti i file MD
                            </button>
                            <button type="button" id="ai-fr-regenerate-force" class="button">
                                ⚡ Forza rigenerazione (ignora checksum)
                            </button>
                            <button type="button" id="ai-fr-clear-versions" class="button" style="color:#a00;">
                                🗑️ Elimina tutti i file
                            </button>
                            <p id="ai-fr-action-status" style="margin-top:10px;"></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- TAB: Scheduler -->
            <div id="tab-scheduler" class="ai-fr-tab-content" style="display:none;">
                <table class="form-table">
                    <tr>
                        <th>Rigenerazione automatica</th>
                        <td>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="checkbox" name="auto_regenerate" value="1" <?php checked( $options['auto_regenerate'] ); ?> />
                                Attiva rigenerazione automatica via cron
                            </label>
                            <label style="display:block; margin-top:10px;">
                                Intervallo: 
                                <input type="number" name="regenerate_interval" value="<?php echo esc_attr( $options['regenerate_interval'] ); ?>" 
                                    min="1" max="168" style="width:60px;" /> ore
                            </label>
                            <?php if ( $next_cron ) : ?>
                            <p class="description" style="margin-top:10px;">
                                <strong>Prossima esecuzione:</strong> <?php echo esc_html( date( 'Y-m-d H:i:s', $next_cron ) ); ?>
                            </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Trigger su eventi</th>
                        <td>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="checkbox" name="regenerate_on_save" value="1" <?php checked( $options['regenerate_on_save'] ); ?> />
                                Rigenera quando un contenuto viene salvato/aggiornato
                            </label>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="checkbox" name="regenerate_on_change" value="1" <?php checked( $options['regenerate_on_change'] ); ?> />
                                Rigenera solo se il contenuto è effettivamente cambiato (checksum)
                            </label>
                            <p class="description">
                                Il checksum confronta il nuovo MD con quello salvato per evitare scritture inutili.
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <p class="submit">
                <input type="submit" name="ai_fr_save" class="button button-primary" value="Salva impostazioni">
            </p>
        </form>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Tab switching
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            var tab = $(this).data('tab');
            
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            $('.ai-fr-tab-content').hide();
            $('#tab-' + tab).show();
        });
        
        // AJAX actions
        var nonce = '<?php echo wp_create_nonce( 'ai_fr_admin_nonce' ); ?>';
        
        $('#ai-fr-regenerate').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).text('⏳ Rigenerazione in corso...');
            
            $.post(ajaxurl, {
                action: 'ai_fr_regenerate_all',
                nonce: nonce,
                force: 0
            }, function(response) {
                $btn.prop('disabled', false).text('🔄 Rigenera tutti i file MD');
                if (response.success) {
                    var s = response.data;
                    $('#ai-fr-action-status').html(
                        '<span style="color:green;">✓ Completato! ' +
                        'Processati: ' + s.processed + ', Rigenerati: ' + s.regenerated + 
                        ', Saltati: ' + s.skipped + ', Errori: ' + s.errors + '</span>'
                    );
                } else {
                    $('#ai-fr-action-status').html('<span style="color:red;">✗ Errore: ' + response.data + '</span>');
                }
            });
        });
        
        $('#ai-fr-regenerate-force').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).text('⏳ Rigenerazione forzata...');
            
            $.post(ajaxurl, {
                action: 'ai_fr_regenerate_all',
                nonce: nonce,
                force: 1
            }, function(response) {
                $btn.prop('disabled', false).text('⚡ Forza rigenerazione');
                if (response.success) {
                    var s = response.data;
                    $('#ai-fr-action-status').html(
                        '<span style="color:green;">✓ Rigenerazione forzata completata! ' +
                        'Rigenerati: ' + s.regenerated + ', Errori: ' + s.errors + '</span>'
                    );
                }
            });
        });
        
        $('#ai-fr-clear-versions').on('click', function() {
            if (!confirm('Eliminare tutti i file MD salvati?')) return;
            
            var $btn = $(this);
            $btn.prop('disabled', true);
            
            $.post(ajaxurl, {
                action: 'ai_fr_clear_versions',
                nonce: nonce
            }, function(response) {
                $btn.prop('disabled', false);
                if (response.success) {
                    $('#ai-fr-action-status').html(
                        '<span style="color:green;">✓ Eliminati ' + response.data.deleted + ' file</span>'
                    );
                }
            });
        });
    });
    </script>
    <?php
}

add_filter( 'plugin_action_links_' . plugin_basename( AI_FR_PLUGIN_FILE ), function ( array $links ): array {
    $settings_link = '<a href="' . admin_url( 'options-general.php?page=ai-friendly' ) . '">Impostazioni</a>';
    array_unshift( $links, $settings_link );
    return $links;
} );
