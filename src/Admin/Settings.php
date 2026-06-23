<?php

namespace AhanPrice\Admin;

use AhanPrice\Admin\LicenseChecker;
use AhanPrice\Admin\ApiHelper;

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
        add_action('wp_ajax_ahan_price_manual_update', [$this, 'manual_update']);
        add_action('wp_ajax_ahan_price_delete_log', [$this, 'delete_log']);
        add_action('wp_ajax_ahan_price_download_log', [$this, 'download_log']);
        add_action('wp_ajax_ahan_price_retry_license_check', [$this, 'retry_license_check']);
        add_action('update_option_ahan_price_key', [$this, 'on_license_key_changed'], 10, 2);
    }

    public function add_admin_menu()
    {
        // Load SVG icon URL
        $icon_url = ahan_price_get_icon();

        add_menu_page(
            'تنظیمات افزونه قیمت آهن',
            'قیمت آهن',
            'manage_options',
            'ahan-price-settings',
            [$this, 'settings_page'],
            $icon_url
        );
    }

    public function register_settings()
    {
        register_setting('ahan_price_settings', 'ahan_price_key');
        register_setting('ahan_price_settings', 'ahan_price_debug', [
            'type' => 'boolean',
            'default' => false,
        ]);
        register_setting('ahan_price_settings', 'ahan_price_network_mode', [
            'type' => 'string',
            'default' => 'normal',
        ]);
    }

    public function enqueue_scripts($hook)
    {
        if ($hook === 'toplevel_page_ahan-price-settings') {
            wp_enqueue_script('ahan-price-admin', plugin_dir_url(__DIR__) . '../assets/js/admin.js', ['jquery'], '1.0.0', true);
            wp_localize_script('ahan-price-admin', 'ahan_price_admin', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('ahan_price_manual_update_nonce'),
                'delete_log_nonce' => wp_create_nonce('ahan_price_delete_log'),
                'download_log_url' => add_query_arg([
                    'action' => 'ahan_price_download_log',
                    'nonce'  => wp_create_nonce('ahan_price_download_log'),
                ], admin_url('admin-ajax.php')),
                'confirm_delete_message' => 'آیا از حذف فایل لاگ اطمینان دارید؟',
                'delete_success_message' => 'فایل لاگ با موفقیت حذف شد',
                'delete_error_message' => 'خطا در حذف فایل لاگ',
                'retry_nonce' => wp_create_nonce('ahan_price_retry_license'),
            ]);
        }
    }

    public function settings_page()
    {
        // Show license status
        $license_checker = LicenseChecker::get_instance();
        echo $license_checker->get_license_status_html();

        // check for update
        $license_key = get_option("ahan_price_key", "");
        echo UpdateChecker::run(AHAN_PRICE_MAIN_FILE, $license_key);

        // Get log instance
        $log = Log::get_instance();
        $log_exists = $log->exists();
?>
        <div class="wrap">
            <h1>تنظیمات افزونه قیمت آهن</h1>

            <form method="post" action="options.php">
                <?php settings_fields('ahan_price_settings'); ?>
                <?php do_settings_sections('ahan_price_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">کلید دسترسی</th>
                        <td>
                            <input type="text" name="ahan_price_key"
                                value="<?php echo esc_attr(get_option('ahan_price_key')); ?>" class="regular-text">
                            <p class="description">
                                با استفاده از <a href="https://mrnargil.ir/product/ahan-price-membership/">کلید دسترسی</a> به
                                تمام قیمت‌های ارائه شده دسترسی خواهید داشت
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">وضعیت شبکه</th>
                        <td>
                            <select name="ahan_price_network_mode" style="direction:RTL;">
                                <option value="normal"
                                    <?php selected(get_option('ahan_price_network_mode', 'normal'), 'normal'); ?>>
                                    عادی - اتصال به api اصلی
                                </option>
                                <option value="internal"
                                    <?php selected(get_option('ahan_price_network_mode', 'normal'), 'internal'); ?>>
                                    اینترنت داخلی - اتصال به api اضطراری
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">فعالسازی حالت دیباگ</th>
                        <td>
                            <label>
                                <input type="checkbox" name="ahan_price_debug" value="1"
                                    <?php checked(get_option('ahan_price_debug'), 1); ?>>
                                فعال کردن حالت دیباگ
                            </label>
                            <p class="description">
                                در حالت دیباگ، اطلاعات مربوط به بروزرسانی قیمت‌ها در فایل لاگ ثبت می‌شود.
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <hr>

            <h2>اجرای دستی ربات</h2>
            <button id="ahan-price-manual-update" class="button button-primary">اجرای ربات</button>
            <p id="ahan-price-update-message" style="display: none;">
                ربات دریافت قیمت با موفقیت اجرا شد، چند لحظه صبر کنید سپس قیمت محصولات سایت را چک کنید.
            </p>

            <hr>

            <h2>مدیریت فایل لاگ</h2>
            <div class="ahan-log-management">
                <?php if ($log_exists): ?>
                    <p>
                        <a href="<?php echo esc_url($log->get_download_url()); ?>" class="button button-secondary"
                            id="ahan-download-log">
                            <span class="dashicons dashicons-download" style="vertical-align: middle;"></span>
                            دانلود فایل لاگ
                        </a>
                        <button id="ahan-delete-log" class="button button-secondary" style="color: #a00; margin-right: 10px;">
                            <span class="dashicons dashicons-trash" style="vertical-align: middle;"></span>
                            حذف فایل لاگ
                        </button>
                    </p>
                    <p class="description" style="margin-top: 10px;">
                        حجم فایل: <?php echo esc_html($log->get_formatted_size()); ?>
                    </p>
                <?php else: ?>
                    <p class="description">
                        حالت دیباگ خاموش است و فایل لاگی برای نمایش وجود ندارد.
                    </p>
                <?php endif; ?>

                <div id="ahan-log-message" style="display: none;"></div>
            </div>
        </div>

        <style>
            .ahan-log-management {
                background: #fff;
                padding: 15px;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                margin-top: 10px;
            }

            .ahan-log-management p {
                margin-top: 0;
            }

            #ahan-log-message {
                margin-top: 10px;
                padding: 10px;
                border-radius: 4px;
            }

            #ahan-log-message.notice-success {
                background: #d4edda;
                border: 1px solid #c3e6cb;
                color: #155724;
            }

            #ahan-log-message.notice-error {
                background: #f8d7da;
                border: 1px solid #f5c6cb;
                color: #721c24;
            }
        </style>
<?php
    }

    public function schedule_price_updater()
    {
        if (class_exists('ActionScheduler')) {
            // Schedule the action to run twice a day if it's not already scheduled
            if (! as_next_scheduled_action('ahan_price_daily_update')) {
                as_schedule_recurring_action(time(), 24 * HOUR_IN_SECONDS, 'ahan_price_daily_update');
            }
        }
    }

    public function start_price_update()
    {
        // Clear the transient to start fresh
        //delete_transient('ahan_products_ids');

        // Trigger the first AJAX request to start the update process
        wp_remote_post(admin_url('admin-ajax.php'), [
            'blocking' => false,
            'sslverify' => false,
            'headers' => array('X-Requested-With' => 'XMLHttpRequest'),
            'body' => [
                'action' => 'ahan_price_update_product',
                'nonce'  => wp_create_nonce('ahan_price_update_nonce'),
            ],
        ]);
    }

    public function manual_update()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ahan_price_manual_update_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        // Clear the transient to start fresh
        //delete_transient('ahan_products_ids');

        // Trigger the first AJAX request to start the update process
        $response = wp_remote_post(admin_url('admin-ajax.php'), [
            'blocking' => true,
            'sslverify' => false,
            'headers' => array('X-Requested-With' => 'XMLHttpRequest'),
            'body' => [
                'action' => 'ahan_price_update_product',
                '_ajax_nonce' => wp_create_nonce('ahan_price'),
            ],
        ]);

        // Debug the response:
        if (is_wp_error($response)) {
            wp_send_json_error('Request failed: ' . $response->get_error_message());
        } else {
            wp_send_json_success('Update started');
        }
    }

    public function download_log()
    {
        // Verify nonce
        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'ahan_price_download_log')) {
            wp_die('درخواست نامعتبر - Invalid nonce');
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز - Unauthorized');
        }

        $log = Log::get_instance();

        if (!$log->exists()) {
            wp_die('فایل لاگ یافت نشد - Log file not found');
        }

        $file_path = $log->get_file_path();

        if (!is_readable($file_path)) {
            wp_die('فایل لاگ قابل خواندن نیست - Log file is not readable');
        }

        // Set headers for download
        nocache_headers();
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="ahan-price-log-' . date('Y-m-d-H-i-s') . '.log"');
        header('Content-Length: ' . filesize($file_path));
        header('Content-Transfer-Encoding: binary');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Clear any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Output file
        readfile($file_path);
        exit;
    }

    public function delete_log()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ahan_price_delete_log')) {
            wp_send_json_error('درخواست نامعتبر');
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('دسترسی غیرمجاز');
        }

        $log = Log::get_instance();

        if ($log->delete()) {
            wp_send_json_success('فایل لاگ با موفقیت حذف شد');
        } else {
            wp_send_json_error('خطا در حذف فایل لاگ');
        }
    }

    public function retry_license_check()
    {
        check_ajax_referer('ahan_price_retry_license', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $license_checker = LicenseChecker::get_instance();
        $license_checker->clear_cache();

        wp_send_json_success('Cache cleared');
    }

    /**
     * Clear license cache when license key is changed
     */
    public function on_license_key_changed($old_value, $new_value)
    {
        if ($old_value !== $new_value) {
            $license_checker = LicenseChecker::get_instance();
            $license_checker->clear_cache();

            $log = Log::get_instance();
            $log->write('License key changed, cache cleared', 'INFO');
        }
    }
}
