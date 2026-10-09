<?php

namespace AhanPrice\Admin;

class Settings
{
    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('init', [$this, 'schedule_price_updater']);
        add_action('ahan_price_daily_update', [$this, 'start_price_update']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('update_option_ahan_price_key', [$this, 'on_license_key_changed'], 10, 2);

        UpdateAjax::get_instance();
        LogAjax::get_instance();
    }

    public function add_admin_menu()
    {
        add_menu_page(
            'تنظیمات افزونه قیمت آهن',
            'قیمت آهن',
            'manage_options',
            'ahan-price-settings',
            [SettingsPage::class, 'render'],
            ahan_price_get_icon()
        );
    }

    public function register_settings()
    {
        // --- عمومی tab group ---
        register_setting('ahan_price_settings_general', 'ahan_price_key');
        register_setting('ahan_price_settings_general', 'ahan_price_network_mode', [
            'type'    => 'string',
            'default' => 'normal',
        ]);

        // --- عیب یابی tab group ---
        register_setting('ahan_price_settings_troubleshoot', 'ahan_price_debug', [
            'type'    => 'boolean',
            'default' => false,
        ]);
    }

    public function enqueue_scripts($hook)
    {
        if ($hook !== 'toplevel_page_ahan-price-settings') {
            return;
        }

        wp_enqueue_style(
            'ahan-price-admin',
            plugin_dir_url(__DIR__) . '../assets/css/admin.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'ahan-price-admin',
            plugin_dir_url(__DIR__) . '../assets/js/admin.js',
            ['jquery'],
            '1.0.0',
            true
        );

        wp_localize_script('ahan-price-admin', 'ahan_price_admin', [
            'ajax_url'               => admin_url('admin-ajax.php'),
            'nonce'                  => wp_create_nonce('ahan_price_manual_update_nonce'),
            'delete_log_nonce'       => wp_create_nonce('ahan_price_delete_log'),
            'batch_nonce'            => wp_create_nonce('ahan_price_batch_update'),
            'check_license_nonce'    => wp_create_nonce('ahan_price_check_license'),
            'download_log_url'       => add_query_arg([
                'action' => 'ahan_price_download_log',
                'nonce'  => wp_create_nonce('ahan_price_download_log'),
            ], admin_url('admin-ajax.php')),
            'confirm_delete_message' => 'آیا از حذف فایل لاگ اطمینان دارید؟',
            'delete_success_message' => 'فایل لاگ با موفقیت حذف شد',
            'delete_error_message'   => 'خطا در حذف فایل لاگ',
            'retry_nonce'            => wp_create_nonce('ahan_price_retry_license'),
        ]);
    }

    public function schedule_price_updater()
    {
        if (class_exists('ActionScheduler')) {
            if (! as_next_scheduled_action('ahan_price_daily_update')) {
                as_schedule_recurring_action(time(), 24 * HOUR_IN_SECONDS, 'ahan_price_daily_update');
            }
        }
    }

    public function start_price_update()
    {
        wp_remote_post(admin_url('admin-ajax.php'), [
            'blocking'  => false,
            'sslverify' => false,
            'headers'   => ['X-Requested-With' => 'XMLHttpRequest'],
            'body'      => [
                'action' => 'ahan_price_update_product',
                'nonce'  => wp_create_nonce('ahan_price_update_nonce'),
            ],
        ]);
    }

    public function on_license_key_changed($old_value, $new_value)
    {
        if ($old_value === $new_value) {
            return;
        }

        $license_checker = LicenseChecker::get_instance();
        $license_checker->clear_cache();

        $log = Log::get_instance();
        $log->write('License key changed, cache cleared', 'INFO');
    }
}
