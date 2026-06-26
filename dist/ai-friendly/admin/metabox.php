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

add_action( 'save_post', function ( int $post_id ): void {

    if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;
    $nonce = isset( $_POST['ai_fr_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ai_fr_nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'ai_fr_save_meta' ) ) return;

    $exclude_raw = isset( $_POST['_ai_fr_exclude'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['_ai_fr_exclude'] ) ) : '';
    $exclude     = $exclude_raw !== '';
    $exclude
        ? update_post_meta( $post_id, '_ai_fr_exclude', '1' )
        : delete_post_meta( $post_id, '_ai_fr_exclude' );
} );
