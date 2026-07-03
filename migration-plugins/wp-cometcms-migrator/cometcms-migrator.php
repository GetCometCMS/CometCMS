<?php
/**
 * Plugin Name: CometCMS Migrator
 * Description: Migrates WordPress posts, pages, and media into CometCMS through the public API.
 * Version: 0.1.0
 * Author: CometCMS
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Text Domain: cometcms-migrator
 */

if (!defined('ABSPATH')) {
    exit;
}

define('COMETCMS_MIGRATOR_VERSION', '0.1.0');
define('COMETCMS_MIGRATOR_FILE', __FILE__);
define('COMETCMS_MIGRATOR_PATH', plugin_dir_path(__FILE__));
define('COMETCMS_MIGRATOR_URL', plugin_dir_url(__FILE__));

require_once COMETCMS_MIGRATOR_PATH . 'includes/class-cometcms-api-client.php';
require_once COMETCMS_MIGRATOR_PATH . 'includes/class-cometcms-migrator.php';
require_once COMETCMS_MIGRATOR_PATH . 'includes/class-cometcms-admin.php';

add_action('plugins_loaded', static function (): void {
    CometCMS_Migrator_Admin::init();
});
