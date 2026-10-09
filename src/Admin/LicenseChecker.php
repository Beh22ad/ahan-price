<?php

namespace AhanPrice\Admin;

class LicenseChecker
{
    private static $instance = null;
    private $api_helper;

    /**
     * Raw response of the last check_license() call (body + code).
     * Populated for both success and failure so callers can log it.
     *
     * @var array{code:int|string, body:string}|null
     */
    public $last_raw_response = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->api_helper = ApiHelper::get_instance();
    }

    /**
     * Check license status from API.
     *
     * @return array|false License data or false on failure
     */
    public function check_license()
    {
        // Reset raw response for this run
        $this->last_raw_response = null;

        // Check cache first
        $cached = get_transient('ahan_license_check');
        if ($cached !== false) {
            return $cached;
        }

        $api_url = $this->api_helper->get_license_check_url();

        $response = wp_remote_get($api_url, [
            'timeout'   => 30,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            $this->last_raw_response = [
                'code' => 'wp_error',
                'body' => $response->get_error_message(),
            ];

            $log = Log::get_instance();
            $log->write('License check failed (WP_Error): ' . $response->get_error_message(), 'ERROR');

            set_transient('ahan_license_check', ['error' => true], 5 * MINUTE_IN_SECONDS);
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        $this->last_raw_response = [
            'code' => $code,
            'body' => $body,
        ];

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $log = Log::get_instance();
            $log->write(
                'License check returned invalid JSON. HTTP ' . $code . ' | Body: ' . $this->truncate($body),
                'ERROR'
            );

            set_transient('ahan_license_check', ['error' => true], 5 * MINUTE_IN_SECONDS);
            return false;
        }

        if (!is_array($data) || !isset($data['account'])) {
            $log = Log::get_instance();
            $log->write(
                'License check response missing expected fields. HTTP ' . $code . ' | Body: ' . $this->truncate($body),
                'ERROR'
            );

            set_transient('ahan_license_check', ['error' => true], 5 * MINUTE_IN_SECONDS);
            return false;
        }

        // Cache successful response for 5 minutes
        set_transient('ahan_license_check', $data, 5 * MINUTE_IN_SECONDS);

        return $data;
    }

    /**
     * Clear license check cache
     */
    public function clear_cache()
    {
        delete_transient('ahan_license_check');
    }

    /**
     * Get license status HTML.
     *
     * @return string HTML for license status notice
     */
    public function get_license_status_html()
    {
        $license_data = $this->check_license();

        if ($license_data === false) {
            return $this->get_connection_error_html();
        }

        if (isset($license_data['error'])) {
            return $this->get_connection_error_html();
        }

        $account = isset($license_data['account']) ? $license_data['account'] : '';
        $status  = isset($license_data['status']) ? $license_data['status'] : '';

        switch ($account) {
            case 'free':
                return $this->get_notice_html('info', 'وضعیت لایسنس: رایگان', false);

            case 'paid':
                if ($status === 'ok') {
                    return $this->get_notice_html('success', 'وضعیت لایسنس: فعال', false);
                }
                return $this->get_notice_html('success', 'وضعیت لایسنس: فعال', false);

            case 'expired':
                return $this->get_notice_html('warning', 'وضعیت لایسنس: منقضی شده', false);

            case 'unauthorized':
                return $this->get_notice_html('error', 'وضعیت لایسنس: نامعتبر', false);

            default:
                return '';
        }
    }

    private function get_connection_error_html()
    {
        return $this->get_notice_html('error', 'ارتباط با سرور برقرار نشد!', true);
    }

    /**
     * Truncate long bodies for the log.
     */
    private function truncate($str, $max = 2000)
    {
        $str = (string) $str;
        if (strlen($str) <= $max) {
            return $str;
        }
        return substr($str, 0, $max) . '...[truncated]';
    }

    private function get_notice_html($type, $message, $show_retry = false)
    {
        $classes = [
            'info'    => 'notice notice-info',
            'success' => 'notice notice-success',
            'warning' => 'notice notice-warning',
            'error'   => 'notice notice-error',
        ];

        $class = isset($classes[$type]) ? $classes[$type] : 'notice notice-info';

        $html  = '<div class="' . $class . ' is-dismissible" style="display: flex; align-items: center; justify-content: space-between;">';
        $html .= '<p style="margin: 0.5em 0;">' . esc_html($message) . '</p>';

        if ($show_retry) {
            $html .= '<button class="button button-small ahan-retry-license-check" style="margin: 0.5em 0;">';
            $html .= 'تلاش مجدد';
            $html .= '</button>';
        }

        $html .= '</div>';

        return $html;
    }
}
