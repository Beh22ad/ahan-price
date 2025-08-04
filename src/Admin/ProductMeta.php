<?php
namespace AhanPrice\Admin;

class ProductMeta {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter('woocommerce_product_data_tabs', [$this, 'add_product_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'add_product_tab_content']);
        add_action('woocommerce_process_product_meta', [$this, 'save_product_meta']);
        add_action('admin_head', [$this, 'custom_admin_css']);
        add_shortcode('ahan-date', [$this, 'ahan_date_shortcode']);
        add_shortcode('ahan-change', [$this, 'ahan_change_shortcode']);
    }

    public function add_product_tab($tabs) {
        $tabs['ahan_price'] = [
            'label'    => 'قیمت آهن',
            'target'   => 'ahan_price_data',
            'class'    => array( 'show_if_simple' ),
            'priority' => 100,
        ];
        return $tabs;
    }

    public function add_product_tab_content() {
        global $post;
        ?>
        <div id="ahan_price_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <?php
                // Checkbox for auto-update
                woocommerce_wp_checkbox([
                    'id'          => '_ahan_auto_update',
                    'label'       => 'بروز رسانی:',
                    'description' => 'قیمت این محصول به طور اتوماتیک آپدیت شود',
                    'desc_tip'    => false,
                ]);

                // Product code input
                woocommerce_wp_text_input([
                    'id'    => '_ahan_product_code',
                    'label' => 'کد محصول:',
                    'type'  => 'text',
                    'class' => 'ltr-input',
                    'description' => '<a href="https://mrnargil.ir/product/ahan-price-membership/">لیست محصولات موجود</a>',
                ]);

                // Price adjustment input (formula)
                woocommerce_wp_text_input([
                    'id'          => '_ahan_price_adjustment',
                    'label'       => 'اصلاح قیمت:',
                    'type'        => 'text',
                    'class'       => 'ltr-input',
                    'placeholder' => '{price}',
                    'description' => 'در اینجا میتوانید قیمت دریافت شده را کم یا زیاد کنید مثلا:<br>
                                     <code>{price}*1.1</code> قیمت دریافتی را ده درصد زیاد میکند.<br>',
                    'desc_tip'    => false,
                ]);

                // Last price date (disabled)
                woocommerce_wp_text_input([
                    'id'    => '_ahan_last_price_date',
                    'label' => 'قیمت برای:',
                    'type'  => 'text',
                    'description' => 'شورتکد تاریخ:<code>[ahan-date]</code>',
                    'disabled' => true,
                    'class' => 'ltr-input',
                ]);

                // New 24h change field (disabled)
                woocommerce_wp_text_input([
                    'id'    => '_ahan_24h_change',
                    'label' => 'نوسانات:',
                    'type'  => 'text',
                    'description' => 'شورتکد نوسانات:<code>[ahan-change]</code>',
                    'disabled' => true,
                    'class' => 'ltr-input',
                ]);


                // Next update date (disabled)
                woocommerce_wp_text_input([
                    'id'    => '_ahan_next_update',
                    'label' => 'تاریخ اجرای ربات:',
                    'type'  => 'text',
                    'disabled' => true,
                    'class' => 'ltr-input',
                ]);
                ?>
            </div>
        </div>
        <?php
    }

    public function save_product_meta($post_id) {
        // Save auto-update checkbox
        $auto_update = isset($_POST['_ahan_auto_update']) ? 'yes' : 'no';
        update_post_meta($post_id, '_ahan_auto_update', $auto_update);

        // Save product code
        if (isset($_POST['_ahan_product_code'])) {
            update_post_meta($post_id, '_ahan_product_code', sanitize_text_field($_POST['_ahan_product_code']));
        }

        // Save price adjustment formula
        if (isset($_POST['_ahan_price_adjustment'])) {
            update_post_meta($post_id, '_ahan_price_adjustment', sanitize_text_field($_POST['_ahan_price_adjustment']));
        }
    }


    /**
     * Shortcode to display the last price date.
     *
     * @param array $atts Shortcode attributes.
     * @return string The last price date.
     */
    public function ahan_date_shortcode() {
    // Enqueue CSS
    wp_enqueue_style(
        'ahan-price-frontend-style',
        plugin_dir_url(__DIR__) . '../assets/css/ahan-price-frontend.css',
        [],
        '1.0.0'
    );

    // Get product ID
    $product_id = 0;

    if (is_product()) {
        global $post;
        $product_id = $post->ID;
    } elseif (function_exists('wc_get_product')) {
        global $product;
        if ($product && is_object($product)) {
            $product_id = $product->get_id();
        }
    }

    if (!$product_id) {
        return '<p class="ahan-update-date">نامشخص</p>';
    }

    $last_price_date = get_post_meta($product_id, '_ahan_last_price_date', true);

    if (!empty($last_price_date)) {
        return '<p class="ahan-update-date">' . esc_html($last_price_date) . '</p>';
    }

    return '<p class="ahan-update-date">نامشخص</p>';
}



    /**
     * Shortcode to display the 24h change.
     *
     * @param array $atts Shortcode attributes.
     * @return string The 24h change.
     */
    public function ahan_change_shortcode() {
    // Enqueue CSS
    wp_enqueue_style(
        'ahan-price-frontend-style',
        plugin_dir_url(__DIR__) . '../assets/css/ahan-price-frontend.css',
        [],
        '1.0.0'
    );

    // Get product ID
    $product_id = 0;

    if (is_product()) {
        global $post;
        $product_id = $post->ID;
    } elseif (function_exists('wc_get_product')) {
        global $product;
        if ($product && is_object($product)) {
            $product_id = $product->get_id();
        }
    }

    if (!$product_id) {
        return ''; // No product found
    }

    $change_value = get_post_meta($product_id, '_ahan_24h_change', true);

    if (empty($change_value) || $change_value == '0') {
        return '<p class="ahan-change ahan-no-changes">۰.۰%</p>';
    }

    $formatted_value = floatval($change_value);
    $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $display_value = str_replace($en, $fa, $change_value);

    if ($formatted_value > 0) {
        return '<p class="ahan-change ahan-increase">+' . esc_html($display_value) . '%</p>';
    } elseif ($formatted_value < 0) {
        return '<p class="ahan-change ahan-decrease">' . esc_html($display_value) . '%</p>';
    }

    return '<p class="ahan-change ahan-no-changes">۰.۰%</p>';
}




    /**
     * Get product ID by product code.
     *
     * @param string $code The product code.
     * @return int|false The product ID or false if not found.
     */
    private function get_product_id_by_code($code) {
        $args = [
            'post_type'  => 'product',
            'meta_key'   => '_ahan_product_code',
            'meta_value' => $code,
            'fields'     => 'ids',
            'posts_per_page' => 1,
        ];

        $products = get_posts($args);

        if (!empty($products)) {
            return $products[0];
        }

        return false;
    }


    public function custom_admin_css() {
        ?>
        <style>
            /* Make input fields LTR */
            .ltr-input input {
                direction: ltr;
                text-align: left;
            }

            /* Add some spacing for the checkbox label */
            .options_group .checkbox {
                margin-bottom: 10px;
            }

            /* Style for the formula input and description */
            #_ahan_price_adjustment {
                font-family: monospace;
            }

            .options_group .description {
                margin-top: 10px;
                display: block;
                font-size: 13px;
                color: #666;
            }

            .options_group .description code {
                background: #f7f7f7;
                padding: 2px 4px;
                border-radius: 3px;
                font-family: monospace;
            }
            .ltr-input {
                direction: ltr;
                width: 80% !important;
            }
            li.ahan_price_options.ahan_price_tab a::before {
                content: "\f137" !important;
            }
        </style>
        <?php
    }
}