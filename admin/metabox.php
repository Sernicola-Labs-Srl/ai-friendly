<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
//  5 â€” Metabox
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

add_action( 'add_meta_boxes', function () {

    $types = [ 'post', 'page' ];
    if ( class_exists( 'WooCommerce' ) ) {
        $types[] = 'product';
    }
    
    // Aggiungi CPT abilitati
    $options = wp_parse_args( get_option( 'ai_fr_options', [] ), ai_fr_get_default_options() );
    $types = array_merge( $types, (array) ( $options['include_cpt'] ?? [] ) );

    foreach ( array_unique( $types ) as $type ) {
        add_meta_box(
            'ai_fr_meta',
            'AI Friendly',
            'ai_fr_render_metabox',
            $type,
            'side',
            'low'
        );
    }

    $schema_types = get_post_types( [ 'public' => true ], 'names' );
    unset( $schema_types['attachment'] );
    foreach ( $schema_types as $type ) {
        add_meta_box(
            'ai_fr_schema_meta',
            'AI Friendly Schema',
            'ai_fr_render_schema_metabox',
            $type,
            'normal',
            'default'
        );
    }
} );

function ai_fr_render_metabox( WP_Post $post ): void {
    wp_nonce_field( 'ai_fr_save_meta', 'ai_fr_nonce' );
    $excluded = get_post_meta( $post->ID, '_ai_fr_exclude', true );
    $last_generated = get_post_meta( $post->ID, '_ai_fr_md_generated', true );
    ?>
    <label style="display:flex; align-items:center; gap:8px; margin-top:4px;">
        <input type="checkbox"
               name="_ai_fr_exclude"
               value="1"
               <?php checked( $excluded, '1' ); ?> />
        Escludi da llms.txt e versione .md
    </label>
    
    <?php if ( $last_generated ) : ?>
    <p style="margin-top:10px; color:#666; font-size:12px;">
        <strong>Ultima generazione MD:</strong><br>
        <?php echo esc_html( $last_generated ); ?>
    </p>
    <?php endif; ?>
    <?php
}

function ai_fr_render_schema_metabox( WP_Post $post ): void {
    wp_nonce_field( 'ai_fr_save_schema_meta', 'ai_fr_schema_nonce' );
    $schema = get_post_meta( $post->ID, '_ai_fr_schema', true );
    $schema = is_array( $schema ) ? $schema : [];
    $type = (string) ( $schema['type'] ?? '' );
    ?>
    <p>Genera un nodo per questo contenuto riusando automaticamente titolo, permalink, excerpt/descrizione, immagine in evidenza e date WordPress.</p>
    <table class="form-table" style="margin-top:0">
        <tr><th><label for="ai-fr-schema-type">Tipo Schema</label></th><td>
            <select id="ai-fr-schema-type" name="_ai_fr_schema[type]">
                <option value="">Nessuno</option>
                <?php foreach ( [ 'Course', 'Event', 'Service', 'FAQPage' ] as $allowed ) : ?>
                    <option value="<?php echo esc_attr( $allowed ); ?>" <?php selected( $type, $allowed ); ?>><?php echo esc_html( $allowed ); ?></option>
                <?php endforeach; ?>
            </select>
        </td></tr>
        <tr><th><label>Nome alternativo</label></th><td><input class="widefat" name="_ai_fr_schema[name]" value="<?php echo esc_attr( $schema['name'] ?? '' ); ?>" placeholder="Lascia vuoto per usare il titolo"></td></tr>
        <tr><th><label>Descrizione alternativa</label></th><td><textarea class="widefat" rows="3" name="_ai_fr_schema[description]" placeholder="Lascia vuoto per usare excerpt o meta description"><?php echo esc_textarea( $schema['description'] ?? '' ); ?></textarea></td></tr>
        <tr><th>Course</th><td style="display:grid;grid-template-columns:1fr 1fr;gap:8px"><input name="_ai_fr_schema[courseCode]" value="<?php echo esc_attr( $schema['courseCode'] ?? '' ); ?>" placeholder="Codice corso"><input name="_ai_fr_schema[educationalLevel]" value="<?php echo esc_attr( $schema['educationalLevel'] ?? '' ); ?>" placeholder="Livello"></td></tr>
        <tr><th>Event</th><td style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px"><input type="datetime-local" name="_ai_fr_schema[startDate]" value="<?php echo esc_attr( $schema['startDate'] ?? '' ); ?>"><input type="datetime-local" name="_ai_fr_schema[endDate]" value="<?php echo esc_attr( $schema['endDate'] ?? '' ); ?>"><input name="_ai_fr_schema[locationName]" value="<?php echo esc_attr( $schema['locationName'] ?? '' ); ?>" placeholder="Nome luogo"><input name="_ai_fr_schema[locationAddress]" value="<?php echo esc_attr( $schema['locationAddress'] ?? '' ); ?>" placeholder="Indirizzo"></td></tr>
        <tr><th>Service</th><td style="display:grid;grid-template-columns:1fr 1fr;gap:8px"><input name="_ai_fr_schema[serviceType]" value="<?php echo esc_attr( $schema['serviceType'] ?? '' ); ?>" placeholder="Tipo servizio"><input name="_ai_fr_schema[areaServed]" value="<?php echo esc_attr( $schema['areaServed'] ?? '' ); ?>" placeholder="Area servita"></td></tr>
        <tr><th><label>FAQPage</label></th><td><textarea class="widefat" rows="6" name="_ai_fr_schema[faq]" placeholder="Una FAQ per riga: Domanda | Risposta"><?php echo esc_textarea( $schema['faq'] ?? '' ); ?></textarea><p class="description">Una coppia domanda/risposta per riga, separata da <code>|</code>.</p></td></tr>
    </table>
    <?php
}

add_action( 'save_post', function ( int $post_id ): void {

    if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;
    $nonce = isset( $_POST['ai_fr_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ai_fr_nonce'] ) ) : '';
    $schema_nonce = isset( $_POST['ai_fr_schema_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ai_fr_schema_nonce'] ) ) : '';
    $can_save_content = wp_verify_nonce( $nonce, 'ai_fr_save_meta' );
    $can_save_schema = wp_verify_nonce( $schema_nonce, 'ai_fr_save_schema_meta' );
    if ( ! $can_save_content && ! $can_save_schema ) return;

    if ( $can_save_content ) {
        $exclude_raw = isset( $_POST['_ai_fr_exclude'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['_ai_fr_exclude'] ) ) : '';
        $exclude     = $exclude_raw !== '';
        $exclude
            ? update_post_meta( $post_id, '_ai_fr_exclude', '1' )
            : delete_post_meta( $post_id, '_ai_fr_exclude' );
    }

    if ( ! $can_save_schema ) return;

    $raw_schema = isset( $_POST['_ai_fr_schema'] ) && is_array( $_POST['_ai_fr_schema'] )
        ? wp_unslash( $_POST['_ai_fr_schema'] )
        : [];
    $allowed_types = [ 'Course', 'Event', 'Service', 'FAQPage' ];
    $schema_type = sanitize_text_field( (string) ( $raw_schema['type'] ?? '' ) );
    if ( ! in_array( $schema_type, $allowed_types, true ) ) {
        delete_post_meta( $post_id, '_ai_fr_schema' );
        return;
    }
    $schema = [ 'type' => $schema_type ];
    foreach ( [ 'name', 'courseCode', 'educationalLevel', 'startDate', 'endDate', 'locationName', 'locationAddress', 'serviceType', 'areaServed' ] as $key ) {
        $schema[ $key ] = sanitize_text_field( (string) ( $raw_schema[ $key ] ?? '' ) );
    }
    $schema['description'] = sanitize_textarea_field( (string) ( $raw_schema['description'] ?? '' ) );
    $schema['faq'] = sanitize_textarea_field( (string) ( $raw_schema['faq'] ?? '' ) );
    update_post_meta( $post_id, '_ai_fr_schema', $schema );
} );
