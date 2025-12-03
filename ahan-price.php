<?php

/**
 * Plugin Name: قیمت آهن
 * Plugin URI:
 * Description: مدیریت اتوماتیک قیمت آهن
 * Version: 2.2.1
 * Author: mrnargil.ir
 * Author URI: https://mrnargil.ir
 * Text Domain: ahan-price
 */

use AhanPrice\Admin\Settings;
use AhanPrice\Admin\ProductMeta;
use AhanPrice\PriceUpdater;

require_once __DIR__ . '/vendor/autoload.php';

// Define the main plugin file path
if (!defined("AHAN_PRICE_MAIN_FILE")) {
    define("AHAN_PRICE_MAIN_FILE", __FILE__);
}

// delete update catch after upgrade
add_action('upgrader_process_complete', function ($upgrader, $options) {
    // Only run for plugin updates
    if ($options['type'] === 'plugin' && !empty($options['plugins'])) {
        foreach ($options['plugins'] as $plugin) {
            // Check if our plugin was updated
            if ($plugin === plugin_basename(AHAN_PRICE_MAIN_FILE)) {
                // Get namespace from header
                $plugin_data = get_file_data(AHAN_PRICE_MAIN_FILE, [
                    'TextDomain' => 'Text Domain',
                ]);
                $namespace = $plugin_data['TextDomain'];

                // Delete transient
                delete_transient($namespace . '_update_response');
            }
        }
    }
}, 10, 2);

// Load SVG icon
function ahan_price_get_icon()
{
    return plugin_dir_url(__FILE__) . 'icons/icon.svg';
}

add_action('plugins_loaded', function () {
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
function ahan_price_deactivate()
{
    // Check if ActionScheduler is available
    if (class_exists('ActionScheduler')) {
        // Unschedule the recurring action
        as_unschedule_action('ahan_price_daily_update');
    }
}
