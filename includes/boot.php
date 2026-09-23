<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'SAIFR_PLUGIN_DIR' ) ) {
    define( 'SAIFR_PLUGIN_DIR', dirname( __DIR__ ) );
}
if ( ! defined( 'SAIFR_PLUGIN_FILE' ) ) {
    define( 'SAIFR_PLUGIN_FILE', SAIFR_PLUGIN_DIR . '/sernicola-labs-ai-friendly.php' );
}

require_once SAIFR_PLUGIN_DIR . '/includes/constants.php';
require_once SAIFR_PLUGIN_DIR . '/includes/options.php';
require_once SAIFR_PLUGIN_DIR . '/includes/migration.php';
require_once SAIFR_PLUGIN_DIR . '/includes/activation.php';
require_once SAIFR_PLUGIN_DIR . '/includes/content-filter.php';
require_once SAIFR_PLUGIN_DIR . '/includes/versioning.php';
require_once SAIFR_PLUGIN_DIR . '/includes/converter.php';
require_once SAIFR_PLUGIN_DIR . '/includes/metadata.php';
require_once SAIFR_PLUGIN_DIR . '/includes/utils.php';
require_once SAIFR_PLUGIN_DIR . '/includes/markdown.php';
require_once SAIFR_PLUGIN_DIR . '/includes/llms.php';
require_once SAIFR_PLUGIN_DIR . '/includes/scheduler.php';
require_once SAIFR_PLUGIN_DIR . '/includes/intercept.php';
require_once SAIFR_PLUGIN_DIR . '/includes/head.php';
require_once SAIFR_PLUGIN_DIR . '/includes/schema-mapping.php';
require_once SAIFR_PLUGIN_DIR . '/includes/schema.php';
require_once SAIFR_PLUGIN_DIR . '/includes/schema-faq.php';
require_once SAIFR_PLUGIN_DIR . '/includes/schema-woocommerce.php';
require_once SAIFR_PLUGIN_DIR . '/includes/admin-activity-log.php';
require_once SAIFR_PLUGIN_DIR . '/includes/admin-diagnostics.php';
require_once SAIFR_PLUGIN_DIR . '/includes/admin-dashboard.php';
require_once SAIFR_PLUGIN_DIR . '/includes/admin-content-table.php';
require_once SAIFR_PLUGIN_DIR . '/includes/llms-history.php';
require_once SAIFR_PLUGIN_DIR . '/includes/admin-notifications.php';
require_once SAIFR_PLUGIN_DIR . '/admin/metabox.php';
require_once SAIFR_PLUGIN_DIR . '/admin/settings-page.php';
