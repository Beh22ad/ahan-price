<?php
namespace AhanPrice;
use AhanPrice\Admin\DateConverter;

class PriceUpdater {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_ahan_price_update_product', [$this, 'update_product']);
        add_action('wp_ajax_nopriv_ahan_price_update_product', [$this, 'update_product']);
    }

    public function update_product() {
        // Get product IDs from transient
        $product_ids = get_transient('ahan_products_ids');
        if (false === $product_ids) {
            // Get all products with auto-update enabled
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
            set_transient('ahan_products_ids', $product_ids, HOUR_IN_SECONDS);
        }

        if (empty($product_ids)) {
            // Delete all transients when no products are left
            $this->delete_all_transients();
            $this->log('All products updated and transients deleted');
            wp_send_json_success('All products updated and transients deleted');
        }

        // Process the first product
        $product_id = array_shift($product_ids);
        $product_obj = wc_get_product($product_id);

        if ($product_obj) {
            if ($product_obj->is_type('variable')) {
                $this->process_variable_product($product_obj);
            } else {
                $this->process_single_product($product_obj);
            }
        }

        // Update the transient with remaining product IDs
        set_transient('ahan_products_ids', $product_ids, HOUR_IN_SECONDS);

        // If there are more products, trigger the next AJAX request
        if (!empty($product_ids)) {
            wp_remote_post(admin_url('admin-ajax.php'), [
                'blocking' => false,
                'sslverify' => false,
                'headers' => array('X-Requested-With' => 'XMLHttpRequest'),
                'body' => [
                    'action' => 'ahan_price_update_product',
                ],
            ]);
        } else {
            // Delete all transients when all products are processed
            $this->delete_all_transients();
        }

        wp_send_json_success('Product updated');
    }

    private function process_variable_product($product_obj) {
        $variation_ids = $product_obj->get_children();

        foreach ($variation_ids as $variation_id) {
            $variation_obj = wc_get_product($variation_id);
            if ($variation_obj) {
                // Get the parent product ID from the variation
                $parent_id = $variation_obj->get_parent_id();
                // Process the variation as a single product, and pass the parent ID to update the date on it
                $this->process_single_product($variation_obj, $parent_id);
            }
        }
    }

    private function process_single_product($product_or_variation_obj, $parent_id = null) {
        $product_id = $product_or_variation_obj->get_id();
        $product_code = get_post_meta($product_id, '_ahan_product_code', true);

        if (empty($product_code)) {
            $this->log("Product {$product_id} skipped: No product code found");
            return;
        }

        // Extract the base code (e.g., "loole" from "loole_8")
        $base_code = preg_replace('/_\d+$/', '', $product_code);

        // Prefix the transient name with 'ahan_'
        $transient_name = 'ahan_' . $base_code;

        // Get cached data or fetch from API
        $data = get_transient($transient_name);
        if (false === $data) {
            $api_key = get_option('ahan_price_key');
            $api_url = "https://ahan-price-api.spaindoh.workers.dev/?auth={$api_key}&id={$base_code}";
            $this->log("api url = ".$api_url );
            $response = wp_remote_get($api_url);

            if (is_wp_error($response)) {
                $this->log("API request failed for product {$product_id}: " . $response->get_error_message());
                return;
            }

            $data = wp_remote_retrieve_body($response);
            set_transient($transient_name, $data, HOUR_IN_SECONDS);
        }

        $data = json_decode($data, true);
        if (!isset($data['status']) || $data['status'] !== 'ok') {
            $this->log("API response status is not OK for product {$product_id}");
            return;
        }

        // Find the matching product in the API response
        foreach ($data['data'] as $item) {
            if ($item['id'] === $product_code) {
                // Access the price, last price date and 24h change
                $api_price = $item['قیمت'];
                $last_price_date = str_replace('-', '/', $item['تاریخ اخرین قیمت']);
                $change_24h = $item['24h']; // Get the 24h change value
                $currency = get_woocommerce_currency();

                // Apply currency rules
                if ($currency === 'IRR') {
                    $api_price = round($api_price / 100) * 100;
                } elseif ($currency === 'IRT') {
                    $api_price = $api_price / 10;
                    $api_price = round($api_price / 10) * 10;
                }

                // Calculate the adjusted price
                $price_adjustment = get_post_meta($product_id, '_ahan_price_adjustment', true);
                if (!empty($price_adjustment)) {
                    try {
                        $adjusted_price = eval('return ' . str_replace('{price}', $api_price, $price_adjustment) . ';');
                    } catch (\Throwable $e) {
                        $this->log('Price adjustment eval error: ' . $e->getMessage());
                        $adjusted_price = $api_price;
                    }
                } else {
                    $adjusted_price = $api_price;
                }

                // Update product price
                update_post_meta($product_id, '_price', $adjusted_price);
                update_post_meta($product_id, '_regular_price', $adjusted_price);
                update_post_meta($product_id, '_ahan_24h_change', $change_24h);
                
                // Update next update date using Persian calendar and Iran time
                $jalaliDate = DateConverter::gregorianToJalali(date('Y'), date('m'), date('d'), '/');
                date_default_timezone_set('Asia/Tehran');
                $iranTime = date('H:i');
                $next_update_date = $jalaliDate." ($iranTime)";
                
                // Determine which post ID to update for the last price date
                $date_update_id = $parent_id ? $parent_id : $product_id;
                update_post_meta($date_update_id, '_ahan_last_price_date', $last_price_date);
                update_post_meta($date_update_id, '_ahan_next_update', $next_update_date);

                $this->log("Product {$product_id} updated: Price = {$adjusted_price}, Last Price Date = {$last_price_date}, 24h Change = {$change_24h}, Next Update Date = {$next_update_date}");
                break;
            }
        }
    }

    private function delete_all_transients() {
        global $wpdb;

        // Delete the main transient
        delete_transient('ahan_products_ids');

        // Delete all transients prefixed with 'ahan_'
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_ahan_%'
            )
        );

        $this->log('All transients deleted');
    }

    private function log($message) {
        if (get_option('ahan_price_debug')) {
            error_log("[Ahan Price Plugin] {$message}");
        }
    }
}
