<?php
/**
 * Plugin Name:        Sernicola Labs AI Friendly – llms.txt, Markdown & Schema
 * Plugin URI:         https://www.sernicola-labs.com/plugin-geo-aeo-posizionamento-intelligenza-artificiale/
 * Description:        Toolkit GEO/AEO per llms.txt, contenuti Markdown e Semantic Schema JSON-LD su WordPress.
 * Version:            2.1.1
 * Author:             Sernicola Labs
 * Author URI:         https://www.sernicola-labs.com/
 * License:            GPL v3 or later
 * License URI:        https://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least:  6.0
 * Requires PHP:       8.1
 * Text Domain:        sernicola-labs-ai-friendly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
    add_action( 'admin_notices', function () {
        echo '<div class="notice notice-error"><p>'
           . esc_html__( 'AI Friendly requires PHP 8.1 or later.', 'sernicola-labs-ai-friendly' )
           . ' (' . esc_html__( 'Current version:', 'sernicola-labs-ai-friendly' ) . ' ' . esc_html( PHP_VERSION ) . ')</p></div>';
    } );
    return;
}

if ( ! defined( 'SAIFR_PLUGIN_FILE' ) ) {
    define( 'SAIFR_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'SAIFR_PLUGIN_DIR' ) ) {
    define( 'SAIFR_PLUGIN_DIR', __DIR__ );
}
if ( ! defined( 'SAIFR_VERSION' ) ) {
    define( 'SAIFR_VERSION', '2.1.1' );
}

require_once SAIFR_PLUGIN_DIR . '/includes/boot.php';
