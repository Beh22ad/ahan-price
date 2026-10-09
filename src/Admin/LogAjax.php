<?php

namespace AhanPrice\Admin;

class LogAjax
{
    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('wp_ajax_ahan_price_delete_log',          [$this, 'delete_log']);
        add_action('wp_ajax_ahan_price_download_log',        [$this, 'download_log']);
        add_action('wp_ajax_ahan_price_retry_license_check', [$this, 'retry_license_check']);
        add_action('wp_ajax_ahan_price_check_license',       [$this, 'check_license']);
    }

    // ----- STATIC DISPATCHERS (kept for backward compatibility) -----

    public static function handle_delete_log()
    {
        self::get_instance()->delete_log();
    }
    public static function handle_download_log()
    {
        self::get_instance()->download_log();
    }
    public static function handle_retry_license()
    {
        self::get_instance()->retry_license_check();
    }
    public static function handle_check_license()
    {
        self::get_instance()->check_license();
    }

    // ----- HANDLERS -----

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

        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="ahan-price-log-' . date('Y-m-d-H-i-s') . '.log"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));

        readfile($file_path);
        exit;
    }

    public function retry_license_check()
    {
        check_ajax_referer('ahan_price_retry_license', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        LicenseChecker::get_instance()->clear_cache();

        wp_send_json_success('Cache cleared');
    }

    public function check_license()
    {
        check_ajax_referer('ahan_price_check_license', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $license_checker = LicenseChecker::get_instance();
        $license_checker->clear_cache();

        $html = $license_checker->get_license_status_html();

        // If we got nothing useful back, log the raw server response and
        // return a fallback notice explaining that.
        if (empty($html)) {
            $raw = $license_checker->last_raw_response;

            $log = Log::get_instance();
            if (is_array($raw)) {
                $log->write(
                    'License check returned invalid/empty response. HTTP ' . ($raw['code'] ?? '?')
                        . ' | Body: ' . $this->truncate((string) ($raw['body'] ?? '')),
                    'ERROR'
                );
            } else {
                $log->write('License check returned invalid/empty response. No raw response captured.', 'ERROR');
            }

            $html = '<div class="notice notice-error"><p>پاسخ نامعتبر از سرور دریافت شد.</p></div>';
        }

        wp_send_json_success($html);
    }

    private function truncate($str, $max = 2000)
    {
        $str = (string) $str;
        if (strlen($str) <= $max) {
            return $str;
        }
        return substr($str, 0, $max) . '...[truncated]';
    }
}
