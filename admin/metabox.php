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
        <?php esc_html_e( 'Exclude from llms.txt and the Markdown version', 'sernicola-labs-ai-friendly' ); ?>
    </label>
    
    <?php if ( $last_generated ) : ?>
    <p style="margin-top:10px; color:#666; font-size:12px;">
        <strong><?php esc_html_e( 'Last Markdown generation:', 'sernicola-labs-ai-friendly' ); ?></strong><br>
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
    $rule = saifr_schema_get_type_rule( $post->post_type );
    $data = saifr_schema_get_content_schema_data( $post );
    $field_labels = [
        'startDate'        => __( 'Start', 'sernicola-labs-ai-friendly' ),
        'endDate'          => __( 'End', 'sernicola-labs-ai-friendly' ),
        'locationName'     => __( 'Venue', 'sernicola-labs-ai-friendly' ),
        'locationAddress'  => __( 'Address', 'sernicola-labs-ai-friendly' ),
        'price'            => __( 'Price', 'sernicola-labs-ai-friendly' ),
        'priceCurrency'    => __( 'Currency', 'sernicola-labs-ai-friendly' ),
        'courseCode'       => __( 'Course code', 'sernicola-labs-ai-friendly' ),
        'educationalLevel' => __( 'Level', 'sernicola-labs-ai-friendly' ),
        'serviceType'      => __( 'Service type', 'sernicola-labs-ai-friendly' ),
        'areaServed'       => __( 'Area served', 'sernicola-labs-ai-friendly' ),
    ];
    $auto_values = [];
    foreach ( (array) ( $data['sources'] ?? [] ) as $field => $source ) {
        if ( $source === 'auto' && isset( $field_labels[ $field ] ) ) {
            $auto_values[] = $field_labels[ $field ] . ': ' . $data['values'][ $field ];
        }
    }
    $attendance_labels = [
        'offline' => __( 'In person', 'sernicola-labs-ai-friendly' ),
        'online'  => __( 'Online', 'sernicola-labs-ai-friendly' ),
        'mixed'   => __( 'Blended', 'sernicola-labs-ai-friendly' ),
    ];
    ?>
    <p><?php esc_html_e( 'Generate a node for this content, automatically reusing the WordPress title, permalink, excerpt, description, featured image, and dates.', 'sernicola-labs-ai-friendly' ); ?></p>
    <?php if ( ! empty( $rule ) ) : ?>
        <p class="description">
            <?php
            /* translators: %s: Schema.org type configured for the post type. */
            echo esc_html( sprintf( __( 'This content type automatically generates a %s node based on the Semantic Schema section mapping. Fields filled in here take precedence.', 'sernicola-labs-ai-friendly' ), $rule['type'] ) );
            ?>
        </p>
    <?php endif; ?>
    <?php if ( ! empty( $auto_values ) ) : ?>
        <p class="description"><strong><?php esc_html_e( 'Automatically detected values:', 'sernicola-labs-ai-friendly' ); ?></strong> <?php echo esc_html( implode( ' · ', $auto_values ) ); ?></p>
    <?php endif; ?>
    <?php if ( ( $data['type'] ?? '' ) === 'Event' && empty( $data['values']['startDate'] ) ) : ?>
        <p class="description" style="color:#b32d2e"><?php esc_html_e( 'No start date available: the Event node is not published until you set one here or in the mapping.', 'sernicola-labs-ai-friendly' ); ?></p>
    <?php endif; ?>
    <table class="form-table" style="margin-top:0">
        <tr><th><label for="saifr-schema-type"><?php esc_html_e( 'Schema type', 'sernicola-labs-ai-friendly' ); ?></label></th><td>
            <select id="saifr-schema-type" name="_saifr_schema[type]">
                <?php if ( ! empty( $rule ) ) : ?>
                    <?php /* translators: %s: Schema.org type configured for the post type. */ ?>
                    <option value=""><?php echo esc_html( sprintf( __( 'Automatic (%s)', 'sernicola-labs-ai-friendly' ), $rule['type'] ) ); ?></option>
                <?php else : ?>
                    <option value=""><?php esc_html_e( 'None', 'sernicola-labs-ai-friendly' ); ?></option>
                <?php endif; ?>
                <?php foreach ( saifr_schema_content_types() as $allowed ) : ?>
                    <option value="<?php echo esc_attr( $allowed ); ?>" <?php selected( $type, $allowed ); ?>><?php echo esc_html( $allowed ); ?></option>
                <?php endforeach; ?>
                <?php if ( ! empty( $rule ) ) : ?>
                    <option value="none" <?php selected( $type, 'none' ); ?>><?php esc_html_e( 'Disable for this content', 'sernicola-labs-ai-friendly' ); ?></option>
                <?php endif; ?>
            </select>
        </td></tr>
        <tr><th><label><?php esc_html_e( 'Alternative name', 'sernicola-labs-ai-friendly' ); ?></label></th><td><input class="widefat" name="_saifr_schema[name]" value="<?php echo esc_attr( $schema['name'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Leave empty to use the title', 'sernicola-labs-ai-friendly' ); ?>"></td></tr>
        <tr><th><label><?php esc_html_e( 'Alternative description', 'sernicola-labs-ai-friendly' ); ?></label></th><td><textarea class="widefat" rows="3" name="_saifr_schema[description]" placeholder="<?php esc_attr_e( 'Leave empty to use the excerpt or meta description', 'sernicola-labs-ai-friendly' ); ?>"><?php echo esc_textarea( $schema['description'] ?? '' ); ?></textarea></td></tr>
        <tr><th>Course</th><td style="display:grid;grid-template-columns:1fr 1fr;gap:8px"><input name="_saifr_schema[courseCode]" value="<?php echo esc_attr( $schema['courseCode'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Course code', 'sernicola-labs-ai-friendly' ); ?>"><input name="_saifr_schema[educationalLevel]" value="<?php echo esc_attr( $schema['educationalLevel'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Level', 'sernicola-labs-ai-friendly' ); ?>"></td></tr>
        <tr><th><?php esc_html_e( 'Event / Course', 'sernicola-labs-ai-friendly' ); ?></th><td style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px">
            <input type="datetime-local" name="_saifr_schema[startDate]" value="<?php echo esc_attr( $schema['startDate'] ?? '' ); ?>" aria-label="<?php esc_attr_e( 'Start', 'sernicola-labs-ai-friendly' ); ?>">
            <input type="datetime-local" name="_saifr_schema[endDate]" value="<?php echo esc_attr( $schema['endDate'] ?? '' ); ?>" aria-label="<?php esc_attr_e( 'End', 'sernicola-labs-ai-friendly' ); ?>">
            <input name="_saifr_schema[locationName]" value="<?php echo esc_attr( $schema['locationName'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Venue name', 'sernicola-labs-ai-friendly' ); ?>">
            <input name="_saifr_schema[locationAddress]" value="<?php echo esc_attr( $schema['locationAddress'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Address', 'sernicola-labs-ai-friendly' ); ?>">
            <select name="_saifr_schema[attendanceMode]" aria-label="<?php esc_attr_e( 'Attendance mode', 'sernicola-labs-ai-friendly' ); ?>">
                <option value=""><?php esc_html_e( 'Mode: default', 'sernicola-labs-ai-friendly' ); ?></option>
                <?php foreach ( $attendance_labels as $mode => $label ) : ?>
                    <option value="<?php echo esc_attr( $mode ); ?>" <?php selected( $schema['attendanceMode'] ?? '', $mode ); ?>><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
            </select>
        </td></tr>
        <tr><th><?php esc_html_e( 'Price', 'sernicola-labs-ai-friendly' ); ?></th><td style="display:grid;grid-template-columns:1fr 1fr;gap:8px"><input name="_saifr_schema[price]" value="<?php echo esc_attr( $schema['price'] ?? '' ); ?>" placeholder="<?php esc_attr_e( '0 = free', 'sernicola-labs-ai-friendly' ); ?>"><input name="_saifr_schema[priceCurrency]" value="<?php echo esc_attr( $schema['priceCurrency'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'EUR', 'sernicola-labs-ai-friendly' ); ?>"></td></tr>
        <tr><th>Service</th><td style="display:grid;grid-template-columns:1fr 1fr;gap:8px"><input name="_saifr_schema[serviceType]" value="<?php echo esc_attr( $schema['serviceType'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Service type', 'sernicola-labs-ai-friendly' ); ?>"><input name="_saifr_schema[areaServed]" value="<?php echo esc_attr( $schema['areaServed'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Area served', 'sernicola-labs-ai-friendly' ); ?>"></td></tr>
        <tr><th><label>FAQPage</label></th><td><textarea class="widefat" rows="6" name="_saifr_schema[faq]" placeholder="<?php esc_attr_e( 'One FAQ per line: Question | Answer', 'sernicola-labs-ai-friendly' ); ?>"><?php echo esc_textarea( $schema['faq'] ?? '' ); ?></textarea><p class="description"><?php esc_html_e( 'One question/answer pair per line, separated by |.', 'sernicola-labs-ai-friendly' ); ?></p></td></tr>
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
    $schema_type = sanitize_text_field( (string) ( $raw_schema['type'] ?? '' ) );
    if ( ! in_array( $schema_type, array_merge( saifr_schema_content_types(), [ 'none' ] ), true ) ) {
        $schema_type = '';
    }
    $schema = [ 'type' => $schema_type ];
    foreach ( [ 'name', 'courseCode', 'educationalLevel', 'startDate', 'endDate', 'locationName', 'locationAddress', 'price', 'priceCurrency', 'serviceType', 'areaServed' ] as $key ) {
        $schema[ $key ] = sanitize_text_field( (string) ( $raw_schema[ $key ] ?? '' ) );
    }
    $attendance_mode = sanitize_key( (string) ( $raw_schema['attendanceMode'] ?? '' ) );
    $schema['attendanceMode'] = in_array( $attendance_mode, saifr_schema_attendance_modes(), true ) ? $attendance_mode : '';
    $schema['description'] = sanitize_textarea_field( (string) ( $raw_schema['description'] ?? '' ) );
    $schema['faq'] = sanitize_textarea_field( (string) ( $raw_schema['faq'] ?? '' ) );

    // Senza tipo né valori manuali il contenuto segue solo la mappatura del post type.
    if ( count( array_filter( $schema, static fn( string $value ): bool => $value !== '' ) ) === 0 ) {
        delete_post_meta( $post_id, '_saifr_schema' );
        return;
    }
    update_post_meta( $post_id, '_saifr_schema', $schema );
} );
