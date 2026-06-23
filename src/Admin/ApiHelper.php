<?php

namespace AhanPrice\Admin;

class ApiHelper
{
    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get the API URL for a specific product code
     *
     * @param string $base_code The product base code
     * @return string The API URL
     */
    public function get_api_url($base_code)
    {
        $api_key = get_option('ahan_price_key', '');
        $network_mode = get_option('ahan_price_network_mode', 'normal');

        if ($network_mode === 'internal') {
            $url = "https://o.roojino.ir/ahan/api.php";
        } else {
            $url = "https://ahan-price-api.spaindoh.workers.dev/";
        }

        $params = [];
        if (!empty($api_key)) {
            $params['auth'] = $api_key;
        }
        $params['id'] = $base_code;

        return add_query_arg($params, $url);
    }

    /**
     * Get API URL for license check (without product code)
     *
     * @return string The API URL for license check
     */
    public function get_license_check_url()
    {
        $api_key = get_option('ahan_price_key', '');
        $network_mode = get_option('ahan_price_network_mode', 'normal');

        if ($network_mode === 'internal') {
            $url = "https://o.roojino.ir/ahan/api.php";
        } else {
            $url = "https://ahan-price-api.spaindoh.workers.dev/";
        }

        $params = [];
        if (!empty($api_key)) {
            $params['auth'] = $api_key;
        }

        return add_query_arg($params, $url);
    }
}
