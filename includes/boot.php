<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'AI_FR_PLUGIN_DIR' ) ) {
    define( 'AI_FR_PLUGIN_DIR', dirname( __DIR__ ) );
}
if ( ! defined( 'AI_FR_PLUGIN_FILE' ) ) {
    define( 'AI_FR_PLUGIN_FILE', AI_FR_PLUGIN_DIR . '/ai-friendly.php' );
}

require_once AI_FR_PLUGIN_DIR . '/includes/constants.php';
require_once AI_FR_PLUGIN_DIR . '/includes/options.php';
require_once AI_FR_PLUGIN_DIR . '/includes/activation.php';
require_once AI_FR_PLUGIN_DIR . '/includes/content-filter.php';
require_once AI_FR_PLUGIN_DIR . '/includes/versioning.php';
require_once AI_FR_PLUGIN_DIR . '/includes/converter.php';
require_once AI_FR_PLUGIN_DIR . '/includes/metadata.php';
require_once AI_FR_PLUGIN_DIR . '/includes/utils.php';
require_once AI_FR_PLUGIN_DIR . '/includes/markdown.php';
require_once AI_FR_PLUGIN_DIR . '/includes/llms.php';
require_once AI_FR_PLUGIN_DIR . '/includes/scheduler.php';
require_once AI_FR_PLUGIN_DIR . '/includes/intercept.php';
require_once AI_FR_PLUGIN_DIR . '/includes/head.php';
require_once AI_FR_PLUGIN_DIR . '/admin/metabox.php';
require_once AI_FR_PLUGIN_DIR . '/admin/settings-page.php';
