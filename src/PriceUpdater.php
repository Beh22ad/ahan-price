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


        // Verify nonce
       // check_ajax_referer('ahan_price',  '_ajax_nonce');



        // Get product IDs
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
        $this->process_product($product_id);

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
                    'nonce'  => wp_create_nonce('ahan_price_update_nonce'),
                ],
            ]);
        } else {
            // Delete all transients when all products are processed
            $this->delete_all_transients();
        }

        wp_send_json_success('Product updated');
    }

    private function process_product($product_id) {
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
            $response = wp_remote_get($api_url);

            if (is_wp_error($response)) {
                $this->log("API request failed for product {$product_id}: " . $response->get_error_message());
                return;
            }

            $data = wp_remote_retrieve_body($response);
            set_transient($transient_name, $data, HOUR_IN_SECONDS);
        }

        $data = json_decode($data, true);
        if ($data['status'] !== 'ok') {
            $this->log("API response status is not OK for product {$product_id}");
            return;
        }

        $currency = get_woocommerce_currency();

        // Find the matching product in the API response
        foreach ($data['data'] as $item) {
            if ($item['id'] === $product_code) {
                // Access the price and last price date using Persian keys
                $api_price = $item['قیمت']; // قیمت

                		// اعمال تغییرات بر اساس واحد پول
				if ($currency === 'IRR') {
					// رند به نزدیک‌ترین 100 برای ریال
					$api_price = round($api_price / 100) * 100;
				} elseif ($currency === 'IRT') {
					// تقسیم بر 10 و رند به نزدیک‌ترین 100 برای تومان
					$api_price = $api_price / 10;
					$api_price = round($api_price / 10) * 10;
				}

                $last_price_date = str_replace('-', '/', $item['تاریخ اخرین قیمت']); // تاریخ اخرین قیمت

                // Calculate the adjusted price
                $price_adjustment = get_post_meta($product_id, '_ahan_price_adjustment', true);
                if (!empty($price_adjustment)) {
                    $adjusted_price = eval('return ' . str_replace('{price}', $api_price, $price_adjustment) . ';');
                } else {
                    $adjusted_price = $api_price;
                }

                // Update product price
                update_post_meta($product_id, '_price', $adjusted_price);
                update_post_meta($product_id, '_regular_price', $adjusted_price);

                // Update last price date
                update_post_meta($product_id, '_ahan_last_price_date', $last_price_date);

                // Update next update date using Persian calendar and Iran time
                $jalaliDate = DateConverter::gregorianToJalali(date('Y'), date('m'), date('d'), '/');
                date_default_timezone_set('Asia/Tehran');
                $iranTime = date('H:i');
                $next_update_date = $jalaliDate." ($iranTime)"; // Persian date format
                update_post_meta($product_id, '_ahan_next_update', $next_update_date);

                $this->log("Product {$product_id} updated: Price = {$adjusted_price}, Last Price Date = {$last_price_date}, Next Update Date = {$next_update_date}");
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

    // Log the deletion
    $this->log('All transients deleted');
}

    private function log($message) {
        if (get_option('ahan_price_debug')) {
            error_log("[Ahan Price Plugin] {$message}");
        }
    }
}
