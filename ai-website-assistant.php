<?php
/**
 * Plugin Name: AI Website Assistant
 * Description: Analyze and improve pages with AI suggestions (SEO, headings, accessibility, CTAs).
 * Version: 0.1.0
 * Author: You
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) exit;

define('AIWA_PLUGIN_FILE', __FILE__);
define('AIWA_PLUGIN_DIR', __DIR__);
define('AIWA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AIWA_VERSION', '0.1.0');

require_once AIWA_PLUGIN_DIR . '/includes/Autoloader.php';
AIWA\Autoloader::register();

add_action('plugins_loaded', function () {
  (new AIWA\Plugin())->register();
});