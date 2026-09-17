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
    $options = wp_parse_args( get_option( 'saifr_options', [] ), saifr_get_default_options() );
    $types = array_merge( $types, (array) ( $options['include_cpt'] ?? [] ) );

    foreach ( array_unique( $types ) as $type ) {
        add_meta_box(
            'saifr_meta',
            'AI Friendly',
            'saifr_render_metabox',
            $type,
            'side',
            'low'
        );
    }

    $schema_types = get_post_types( [ 'public' => true ], 'names' );
    unset( $schema_types['attachment'] );
    foreach ( $schema_types as $type ) {
        add_meta_box(
            'saifr_schema_meta',
            __( 'AI Friendly Schema', 'sernicola-labs-ai-friendly' ),
            'saifr_render_schema_metabox',
            $type,
            'normal',
            'default'
        );
    }
} );

function saifr_render_metabox( WP_Post $post ): void {
    wp_nonce_field( 'saifr_save_meta', 'saifr_nonce' );
    $excluded = get_post_meta( $post->ID, '_saifr_exclude', true );
    $last_generated = get_post_meta( $post->ID, '_saifr_md_generated', true );
    ?>
    <label style="display:flex; align-items:center; gap:8px; margin-top:4px;">
        <input type="checkbox"
               name="_saifr_exclude"
               value="1"
               <?php checked( $excluded, '1' ); ?> />
        <?php esc_html_e( 'Escludi da llms.txt e dalla versione Markdown', 'sernicola-labs-ai-friendly' ); ?>
    </label>
    
    <?php if ( $last_generated ) : ?>
    <p style="margin-top:10px; color:#666; font-size:12px;">
        <strong><?php esc_html_e( 'Ultima generazione Markdown:', 'sernicola-labs-ai-friendly' ); ?></strong><br>
        <?php echo esc_html( $last_generated ); ?>
    </p>
    <?php endif; ?>
    <?php
}

function saifr_render_schema_metabox( WP_Post $post ): void {
    wp_nonce_field( 'saifr_save_schema_meta', 'saifr_schema_nonce' );
    $schema = get_post_meta( $post->ID, '_saifr_schema', true );
    $schema = is_array( $schema ) ? $schema : [];
    $type = (string) ( $schema['type'] ?? '' );
    ?>
    <p><?php esc_html_e( 'Genera un nodo per questo contenuto riusando automaticamente titolo, permalink, riassunto, descrizione, immagine in evidenza e date WordPress.', 'sernicola-labs-ai-friendly' ); ?></p>
    <table class="form-table" style="margin-top:0">
        <tr><th><label for="saifr-schema-type"><?php esc_html_e( 'Tipo Schema', 'sernicola-labs-ai-friendly' ); ?></label></th><td>
            <select id="saifr-schema-type" name="_saifr_schema[type]">
                <option value=""><?php esc_html_e( 'Nessuno', 'sernicola-labs-ai-friendly' ); ?></option>
                <?php foreach ( [ 'Course', 'Event', 'Service', 'FAQPage' ] as $allowed ) : ?>
                    <option value="<?php echo esc_attr( $allowed ); ?>" <?php selected( $type, $allowed ); ?>><?php echo esc_html( $allowed ); ?></option>
                <?php endforeach; ?>
            </select>
        </td></tr>
        <tr><th><label><?php esc_html_e( 'Nome alternativo', 'sernicola-labs-ai-friendly' ); ?></label></th><td><input class="widefat" name="_saifr_schema[name]" value="<?php echo esc_attr( $schema['name'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Lascia vuoto per usare il titolo', 'sernicola-labs-ai-friendly' ); ?>"></td></tr>
        <tr><th><label><?php esc_html_e( 'Descrizione alternativa', 'sernicola-labs-ai-friendly' ); ?></label></th><td><textarea class="widefat" rows="3" name="_saifr_schema[description]" placeholder="<?php esc_attr_e( 'Lascia vuoto per usare il riassunto o la meta description', 'sernicola-labs-ai-friendly' ); ?>"><?php echo esc_textarea( $schema['description'] ?? '' ); ?></textarea></td></tr>
        <tr><th>Course</th><td style="display:grid;grid-template-columns:1fr 1fr;gap:8px"><input name="_saifr_schema[courseCode]" value="<?php echo esc_attr( $schema['courseCode'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Codice corso', 'sernicola-labs-ai-friendly' ); ?>"><input name="_saifr_schema[educationalLevel]" value="<?php echo esc_attr( $schema['educationalLevel'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Livello', 'sernicola-labs-ai-friendly' ); ?>"></td></tr>
        <tr><th>Event</th><td style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px"><input type="datetime-local" name="_saifr_schema[startDate]" value="<?php echo esc_attr( $schema['startDate'] ?? '' ); ?>"><input type="datetime-local" name="_saifr_schema[endDate]" value="<?php echo esc_attr( $schema['endDate'] ?? '' ); ?>"><input name="_saifr_schema[locationName]" value="<?php echo esc_attr( $schema['locationName'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Nome luogo', 'sernicola-labs-ai-friendly' ); ?>"><input name="_saifr_schema[locationAddress]" value="<?php echo esc_attr( $schema['locationAddress'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Indirizzo', 'sernicola-labs-ai-friendly' ); ?>"></td></tr>
        <tr><th>Service</th><td style="display:grid;grid-template-columns:1fr 1fr;gap:8px"><input name="_saifr_schema[serviceType]" value="<?php echo esc_attr( $schema['serviceType'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Tipo servizio', 'sernicola-labs-ai-friendly' ); ?>"><input name="_saifr_schema[areaServed]" value="<?php echo esc_attr( $schema['areaServed'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Area servita', 'sernicola-labs-ai-friendly' ); ?>"></td></tr>
        <tr><th><label>FAQPage</label></th><td><textarea class="widefat" rows="6" name="_saifr_schema[faq]" placeholder="<?php esc_attr_e( 'Una FAQ per riga: Domanda | Risposta', 'sernicola-labs-ai-friendly' ); ?>"><?php echo esc_textarea( $schema['faq'] ?? '' ); ?></textarea><p class="description"><?php esc_html_e( 'Una coppia domanda/risposta per riga, separata da |.', 'sernicola-labs-ai-friendly' ); ?></p></td></tr>
    </table>
    <?php
}

add_action( 'save_post', function ( int $post_id ): void {

    if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;
    $nonce = isset( $_POST['saifr_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['saifr_nonce'] ) ) : '';
    $schema_nonce = isset( $_POST['saifr_schema_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['saifr_schema_nonce'] ) ) : '';
    $can_save_content = wp_verify_nonce( $nonce, 'saifr_save_meta' );
    $can_save_schema = wp_verify_nonce( $schema_nonce, 'saifr_save_schema_meta' );
    if ( ! $can_save_content && ! $can_save_schema ) return;

    if ( $can_save_content ) {
        $exclude_raw = isset( $_POST['_saifr_exclude'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['_saifr_exclude'] ) ) : '';
        $exclude     = $exclude_raw !== '';
        $exclude
            ? update_post_meta( $post_id, '_saifr_exclude', '1' )
            : delete_post_meta( $post_id, '_saifr_exclude' );
    }

    if ( ! $can_save_schema ) return;

    $raw_schema = isset( $_POST['_saifr_schema'] ) && is_array( $_POST['_saifr_schema'] )
        ? map_deep( wp_unslash( $_POST['_saifr_schema'] ), 'sanitize_textarea_field' )
        : [];
    $allowed_types = [ 'Course', 'Event', 'Service', 'FAQPage' ];
    $schema_type = sanitize_text_field( (string) ( $raw_schema['type'] ?? '' ) );
    if ( ! in_array( $schema_type, $allowed_types, true ) ) {
        delete_post_meta( $post_id, '_saifr_schema' );
        return;
    }
    $schema = [ 'type' => $schema_type ];
    foreach ( [ 'name', 'courseCode', 'educationalLevel', 'startDate', 'endDate', 'locationName', 'locationAddress', 'serviceType', 'areaServed' ] as $key ) {
        $schema[ $key ] = sanitize_text_field( (string) ( $raw_schema[ $key ] ?? '' ) );
    }
    $schema['description'] = sanitize_textarea_field( (string) ( $raw_schema['description'] ?? '' ) );
    $schema['faq'] = sanitize_textarea_field( (string) ( $raw_schema['faq'] ?? '' ) );
    update_post_meta( $post_id, '_saifr_schema', $schema );
} );
