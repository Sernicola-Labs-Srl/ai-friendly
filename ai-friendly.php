<?php
/**
 * Plugin Name:        Sernicola Labs | AI Friendly - llms.txt & Markdown
 * Description:        Genera /llms.txt e versioni .md di post e pagine.
 * Version:            1.6.4
 * Changelog:          CHANGELOG.md
 * Author:             Sernicola Labs
 * Author URI:         https://sernicola-labs.com
 * License:            GPL v2 or later
 * Requires at least:  6.0
 * Requires PHP:       8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
    add_action( 'admin_notices', function () {
        echo '<div class="notice notice-error"><p>'
           . '<strong>AI Friendly</strong> richiede PHP >= 8.1 '
           . '(versione attuale: ' . PHP_VERSION . ').</p></div>';
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
    define( 'AI_FR_VERSION', '1.6.4' );
}

require_once AI_FR_PLUGIN_DIR . '/includes/boot.php';
