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
        add_action('update_option_ahan_price_key', [$this, 'on_license_key_changed'], 10, 2);
    }

    public function add_admin_menu()
    {
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
                'batch_nonce' => wp_create_nonce('ahan_price_batch_update'),
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

            /* Progress bar styles */
            #ahan-progress-container {
                margin-top: 20px !important;
                padding: 15px !important;
                background: #f0f8ff !important;
                border: 2px solid #2271b1 !important;
                border-radius: 8px !important;
            }

            #ahan-progress-bar {
                transition: width 0.5s ease !important;
                background: linear-gradient(90deg, #4CAF50, #45a049) !important;
                border-radius: 14px !important;
            }

            #ahan-progress-text {
                font-weight: bold;
                color: #2271b1;
            }

            #ahan-cancel-update:hover {
                background: #f8d7da !important;
                border-color: #d63638 !important;
            }
        </style>
<?php
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
            'blocking' => false,
            'sslverify' => false,
            'headers' => array('X-Requested-With' => 'XMLHttpRequest'),
            'body' => [
                'action' => 'ahan_price_update_product',
                'nonce'  => wp_create_nonce('ahan_price_update_nonce'),
            ],
        ]);
    }

    // STATIC AJAX HANDLERS
    public static function handle_manual_update()
    {
        $instance = self::get_instance();
        $instance->manual_update();
    }

    public static function handle_update_batch()
    {
        $instance = self::get_instance();
        $instance->update_batch();
    }

    public static function handle_get_progress()
    {
        $instance = self::get_instance();
        $instance->get_progress();
    }

    public static function handle_cancel_update()
    {
        $instance = self::get_instance();
        $instance->cancel_update();
    }

    public static function handle_delete_log()
    {
        $instance = self::get_instance();
        $instance->delete_log();
    }

    public static function handle_retry_license()
    {
        $instance = self::get_instance();
        $instance->retry_license_check();
    }

    public function manual_update()
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ahan_price_manual_update_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $this->cleanup_update_session();

        wp_send_json_success('Update started successfully');
    }

    public function update_batch()
    {
        check_ajax_referer('ahan_price_batch_update', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $batch_size = apply_filters('ahan_price_batch_size', 5);
        $product_ids = get_transient('ahan_products_ids_batch');

        if (false === $product_ids) {
            $product_ids = get_posts([
                'post_type'      => 'product',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => [
                    [
                        'key'   => '_ahan_auto_update',
                        'value' => 'yes',
                    ],
                ],
            ]);

            if (empty($product_ids)) {
                wp_send_json_success([
                    'completed' => true,
                    'total' => 0,
                    'processed' => 0,
                    'success_count' => 0,
                    'error_count' => 0,
                    'percentage' => 0,
                    'message' => 'No products to update'
                ]);
            }

            set_transient('ahan_products_ids_batch', $product_ids, HOUR_IN_SECONDS);
            set_transient('ahan_update_progress', [
                'total' => count($product_ids),
                'processed' => 0,
                'success_count' => 0,
                'error_count' => 0,
                'status' => 'running',
                'started_at' => time(),
            ], HOUR_IN_SECONDS);
        }

        $progress = get_transient('ahan_update_progress');

        if (!$progress) {
            wp_send_json_error('Progress data not found');
        }

        $remaining = array_slice($product_ids, $progress['processed'], $batch_size);

        if (empty($remaining)) {
            $this->cleanup_update_session();
            wp_send_json_success([
                'completed' => true,
                'total' => $progress['total'],
                'processed' => $progress['processed'],
                'success_count' => $progress['success_count'],
                'error_count' => $progress['error_count'],
                'percentage' => 100,
                'message' => 'All products updated successfully!'
            ]);
        }

        $batch_success = 0;
        $batch_error = 0;

        foreach ($remaining as $product_id) {
            try {
                $price_updater = \AhanPrice\PriceUpdater::get_instance();
                $result = $price_updater->process_single_product_by_id($product_id);

                if ($result) {
                    $batch_success++;
                } else {
                    $batch_error++;
                }
            } catch (\Exception $e) {
                $batch_error++;
                $log = Log::get_instance();
                $log->write("Error processing product {$product_id}: " . $e->getMessage(), 'ERROR');
            }

            $progress['processed']++;
        }

        $progress['success_count'] = ($progress['success_count'] ?? 0) + $batch_success;
        $progress['error_count'] = ($progress['error_count'] ?? 0) + $batch_error;

        set_transient('ahan_update_progress', $progress, HOUR_IN_SECONDS);

        $completed = $progress['processed'] >= $progress['total'];

        if ($completed) {
            $this->cleanup_update_session();
        }

        wp_send_json_success([
            'completed' => $completed,
            'total' => $progress['total'],
            'processed' => $progress['processed'],
            'success_count' => $progress['success_count'],
            'error_count' => $progress['error_count'],
            'percentage' => $progress['total'] > 0 ? round(($progress['processed'] / $progress['total']) * 100, 2) : 0,
            'message' => $completed ? 'All products updated successfully!' : 'Batch processed successfully'
        ]);
    }

    public function get_progress()
    {
        check_ajax_referer('ahan_price_batch_update', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $progress = get_transient('ahan_update_progress');

        if (false === $progress) {
            wp_send_json_success([
                'status' => 'idle',
                'total' => 0,
                'processed' => 0,
                'success_count' => 0,
                'error_count' => 0,
                'percentage' => 0,
            ]);
        }

        $percentage = $progress['total'] > 0
            ? round(($progress['processed'] / $progress['total']) * 100, 2)
            : 0;

        wp_send_json_success([
            'status' => $progress['status'] ?? 'running',
            'total' => $progress['total'],
            'processed' => $progress['processed'],
            'success_count' => $progress['success_count'] ?? 0,
            'error_count' => $progress['error_count'] ?? 0,
            'percentage' => $percentage,
            'started_at' => $progress['started_at'] ?? null,
        ]);
    }

    public function cancel_update()
    {
        check_ajax_referer('ahan_price_batch_update', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $this->cleanup_update_session();

        wp_send_json_success('Update cancelled successfully');
    }

    private function cleanup_update_session()
    {
        delete_transient('ahan_products_ids_batch');
        delete_transient('ahan_update_progress');
    }

    public function download_log()
    {
        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'ahan_price_download_log')) {
            wp_die('درخواست نامعتبر - Invalid nonce');
        }

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

        nocache_headers();
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="ahan-price-log-' . date('Y-m-d-H-i-s') . '.log"');
        header('Content-Length: ' . filesize($file_path));
        header('Content-Transfer-Encoding: binary');
        header('Pragma: no-cache');
        header('Expires: 0');

        while (ob_get_level()) {
            ob_end_clean();
        }

        readfile($file_path);
        exit;
    }

    public function delete_log()
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ahan_price_delete_log')) {
            wp_send_json_error('درخواست نامعتبر');
        }

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
