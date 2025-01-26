<?php
/**
 * Plugin Name: قیمت آهن
 * Plugin URI:
 * Description: مدیریت اتوماتیک قیمت آهن
 * Version: 1.0.0
 * Author: mrnargil.ir
 * Author URI: https://mrnargil.ir
 * Text Domain: ahan-price
 */

use AhanPrice\Admin\Settings;
use AhanPrice\Admin\ProductMeta;
use AhanPrice\PriceUpdater;
require_once __DIR__ . '/vendor/autoload.php';

// Load SVG icon
function ahan_price_get_icon() {
    return plugin_dir_url(__FILE__) . 'icons/icon.svg';
}

add_action('plugins_loaded', function() {
    // Initialize plugin components
    Settings::get_instance();
    ProductMeta::get_instance();
    PriceUpdater::get_instance();
});

// Register deactivation hook
register_deactivation_hook(__FILE__, 'ahan_price_deactivate');

/**
 * Function to run when the plugin is deactivated.
 */
function ahan_price_deactivate() {
    // Check if ActionScheduler is available
    if (class_exists('ActionScheduler')) {
        // Unschedule the recurring action
        as_unschedule_action('ahan_price_daily_update');
    }
}