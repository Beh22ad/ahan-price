<?php

namespace AhanPrice\Admin;

class LicenseChecker
{
    private static $instance = null;
    private $api_helper;

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
     * Check license status from API
     *
     * @return array|false License data or false on failure
     */
    public function check_license()
    {
        // Check cache first
        $cached = get_transient('ahan_license_check');
        if ($cached !== false) {
            return $cached;
        }

        $api_url = $this->api_helper->get_license_check_url();

        $response = wp_remote_get($api_url, [
            'timeout' => 30,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            $log = Log::get_instance();
            $log->write('License check failed: ' . $response->get_error_message(), 'ERROR');

            // Cache the failure for 5 minutes
            set_transient('ahan_license_check', ['error' => true], 5 * MINUTE_IN_SECONDS);
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $log = Log::get_instance();
            $log->write('License check returned invalid JSON', 'ERROR');

            // Cache the failure for 5 minutes
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
     * Get license status HTML
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
        $status = isset($license_data['status']) ? $license_data['status'] : '';

        switch ($account) {
            case 'free':
                return $this->get_notice_html('info', 'وضعیت لایسنس: رایگان', false);

            case 'paid':
                if ($status === 'ok') {
                    return $this->get_notice_html('success', 'وضعیت لایسنس: فعال', false);
                } else {
                    return $this->get_notice_html('success', 'وضعیت لایسنس: فعال', false);
                }

            case 'expired':
                return $this->get_notice_html('warning', 'وضعیت لایسنس: منقضی شده', false);

            case 'unauthorized':
                return $this->get_notice_html('error', 'وضعیت لایسنس: نامعتبر', false);

            default:
                return '';
        }
    }

    /**
     * Get connection error HTML
     *
     * @return string HTML for connection error notice
     */
    private function get_connection_error_html()
    {
        return $this->get_notice_html('error', 'ارتباط با سرور برقرار نشد!', true);
    }

    /**
     * Generate WordPress admin notice HTML
     *
     * @param string $type Notice type (info, success, warning, error)
     * @param string $message Notice message
     * @param bool $show_retry Whether to show retry button
     * @return string HTML notice
     */
    private function get_notice_html($type, $message, $show_retry = false)
    {
        $classes = [
            'info' => 'notice notice-info',
            'success' => 'notice notice-success',
            'warning' => 'notice notice-warning',
            'error' => 'notice notice-error',
        ];

        $class = isset($classes[$type]) ? $classes[$type] : 'notice notice-info';

        $html = '<div class="' . $class . ' is-dismissible" style="display: flex; align-items: center; justify-content: space-between;">';
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
