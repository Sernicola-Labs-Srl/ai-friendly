<?php
/**
 * Plugin Name:        AI Friendly
 * Description:        Espone contenuti WordPress per AI/LLM con llms.txt, Markdown e Semantic Schema JSON-LD.
 * Version:            2.0.0
 * Author:             Sernicola Labs
 * Author URI:         https://sernicola-labs.com
 * License:            GPL v3 or later
 * License URI:        https://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least:  6.0
 * Requires PHP:       8.1
 * Text Domain:        ai-friendly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
    add_action( 'admin_notices', function () {
        echo '<div class="notice notice-error"><p>'
           . esc_html__( 'AI Friendly requires PHP 8.1 or later.', 'ai-friendly' )
           . ' (' . esc_html__( 'Current version:', 'ai-friendly' ) . ' ' . esc_html( PHP_VERSION ) . ')</p></div>';
    } );
    return;
}

if ( ! defined( 'AI_FR_PLUGIN_FILE' ) ) {
    define( 'AI_FR_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'AI_FR_PLUGIN_DIR' ) ) {
    define( 'AI_FR_PLUGIN_DIR', __DIR__ );
}
if ( ! defined( 'AI_FR_VERSION' ) ) {
    define( 'AI_FR_VERSION', '2.0.0' );
}

require_once AI_FR_PLUGIN_DIR . '/includes/boot.php';
