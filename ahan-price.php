<?php

/**
 * Plugin Name: قیمت آهن 
 * Plugin URI:
 * Description: مدیریت اتوماتیک قیمت آهن
 * Version: 2.3.1
 * Author: mrnargil.ir
 * Author URI: https://mrnargil.ir
 * Text Domain: ahan-price
 */

use AhanPrice\Admin\Settings;
use AhanPrice\Admin\ProductMeta;
use AhanPrice\PriceUpdater;
use AhanPrice\Admin\UpdateChecker;

require_once __DIR__ . '/vendor/autoload.php';

// Define the main plugin file path
if (!defined("AHAN_PRICE_MAIN_FILE")) {
    define("AHAN_PRICE_MAIN_FILE", __FILE__);
}

// delete update catch after upgrade
UpdateChecker::registerCacheCleaner(AHAN_PRICE_MAIN_FILE);

// Load SVG icon
function ahan_price_get_icon()
{
    return plugin_dir_url(__FILE__) . 'icons/icon.svg';
}

// Initialize plugin on plugins_loaded
add_action('plugins_loaded', function () {
    // Initialize plugin components
    Settings::get_instance();
    ProductMeta::get_instance();
    PriceUpdater::get_instance();
});

// Register AJAX actions early
add_action('init', function () {
    // Register all AJAX actions for both logged-in and non-logged-in users
    add_action('wp_ajax_ahan_price_manual_update', ['AhanPrice\Admin\Settings', 'handle_manual_update']);
    add_action('wp_ajax_ahan_price_update_batch', ['AhanPrice\Admin\Settings', 'handle_update_batch']);
    add_action('wp_ajax_ahan_price_get_progress', ['AhanPrice\Admin\Settings', 'handle_get_progress']);
    add_action('wp_ajax_ahan_price_cancel_update', ['AhanPrice\Admin\Settings', 'handle_cancel_update']);
    add_action('wp_ajax_ahan_price_delete_log', ['AhanPrice\Admin\Settings', 'handle_delete_log']);
    add_action('wp_ajax_ahan_price_retry_license_check', ['AhanPrice\Admin\Settings', 'handle_retry_license']);
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

    // Clean up transients
    delete_transient('ahan_products_ids_batch');
    delete_transient('ahan_update_progress');
}

// Enqueue admin scripts
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook === 'toplevel_page_ahan-price-settings') {
        wp_enqueue_script('ahan-price-admin', plugin_dir_url(__FILE__) . 'assets/js/admin.js', ['jquery'], '1.0.0', true);

        wp_localize_script('ahan-price-admin', 'ahan_price_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ahan_price_manual_update_nonce'),
            'batch_nonce' => wp_create_nonce('ahan_price_batch_update'),
            'delete_log_nonce' => wp_create_nonce('ahan_price_delete_log'),
            'retry_nonce' => wp_create_nonce('ahan_price_retry_license'),
            'download_log_url' => add_query_arg([
                'action' => 'ahan_price_download_log',
                'nonce' => wp_create_nonce('ahan_price_download_log'),
            ], admin_url('admin-ajax.php')),
            'confirm_delete_message' => 'آیا از حذف فایل لاگ اطمینان دارید؟',
            'delete_success_message' => 'فایل لاگ با موفقیت حذف شد',
            'delete_error_message' => 'خطا در حذف فایل لاگ',
        ]);
    }
});
