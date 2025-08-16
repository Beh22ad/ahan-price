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
        // Actions for variable products
        add_action('woocommerce_product_after_variable_attributes', [$this, 'add_variation_fields'], 10, 3);
        add_action('woocommerce_save_product_variation', [$this, 'save_variation_meta'], 10, 2);
    }

    public function add_product_tab($tabs) {
        // Show the tab for both simple and variable products
        $tabs['ahan_price'] = [
            'label'    => 'قیمت آهن',
            'target'   => 'ahan_price_data',
            'class'    => array('show_if_simple', 'show_if_variable'),
            'priority' => 100,
        ];
        return $tabs;
    }

    public function add_product_tab_content() {
        global $post;
        ?>
        <div id="ahan_price_data" class="panel woocommerce_options_panel">
            <div class="options_group show_if_simple">
                <?php
                // Checkbox for auto-update (Simple Products)
                woocommerce_wp_checkbox([
                    'id'          => '_ahan_auto_update',
                    'label'       => 'بروز رسانی:',
                    'description' => 'قیمت این محصول به طور اتوماتیک آپدیت شود',
                    'desc_tip'    => false,
                ]);

                // Product code input (Simple Products)
                woocommerce_wp_text_input([
                    'id'    => '_ahan_product_code',
                    'label' => 'کد محصول:',
                    'type'  => 'text',
                    'class' => 'ltr-input',
                    'description' => '<a href="https://mrnargil.ir/product/ahan-price-membership/">لیست محصولات موجود</a>',
                ]);

                // Price adjustment input (Simple Products)
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

                // Last price date (Simple Products)
                woocommerce_wp_text_input([
                    'id'    => '_ahan_last_price_date',
                    'label' => 'قیمت برای:',
                    'type'  => 'text',
                    'description' => 'شورتکد تاریخ:<code>[ahan-date]</code>',
                    'disabled' => true,
                    'class' => 'ltr-input',
                ]);

                // New 24h change field (Simple Products)
                woocommerce_wp_text_input([
                    'id'    => '_ahan_24h_change',
                    'label' => 'نوسانات:',
                    'type'  => 'text',
                    'description' => 'شورتکد نوسانات:<code>[ahan-change]</code>',
                    'disabled' => true,
                    'class' => 'ltr-input',
                ]);


                // Next update date (Simple Products)
                woocommerce_wp_text_input([
                    'id'    => '_ahan_next_update',
                    'label' => 'تاریخ اجرای ربات:',
                    'type'  => 'text',
                    'disabled' => true,
                    'class' => 'ltr-input',
                ]);
                ?>
            </div>
            <div class="options_group show_if_variable">
                <?php
                // Checkbox for auto-update (Variable Products)
                woocommerce_wp_checkbox([
                    'id'          => '_ahan_auto_update',
                    'label'       => 'بروز رسانی:',
                    'description' => 'قیمت این محصول به طور اتوماتیک آپدیت شود',
                    'desc_tip'    => false,
                ]);
                // Last price date (Variable Products)
                woocommerce_wp_text_input([
                    'id'    => '_ahan_last_price_date',
                    'label' => 'قیمت برای:',
                    'type'  => 'text',
                    'description' => 'شورتکد تاریخ:<code>[ahan-date]</code>',
                    'disabled' => true,
                    'class' => 'ltr-input',
                ]);

                // Next update date (Variable Products)
                woocommerce_wp_text_input([
                    'id'    => '_ahan_next_update',
                    'label' => 'تاریخ اجرای ربات:',
                    'type'  => 'text',
                    'disabled' => true,
                    'class' => 'ltr-input',
                ]);
                ?>
                <p class="ahan-info-message">
                    مابقی تنظیمات در تب متغیرها قرار دارد.
                </p>
            </div>
        </div>
        <?php
    }

    public function add_variation_fields($loop, $variation_data, $variation) {
        // Add fields to each variation
        ?>
        <h3 class="ahan-variation-heading">تنظیمات قیمت آهن</h3>
        <div class="variation-ahan-fields">
            <?php
            // Product code input for variations
            woocommerce_wp_text_input([
                'id'    => '_ahan_product_code[' . $loop . ']',
                'label' => 'کد محصول:',
                'value' => get_post_meta($variation->ID, '_ahan_product_code', true),
                'type'  => 'text',
                'class' => 'ltr-input',
                'desc_tip' => false,
                'description' => '<a href="https://mrnargil.ir/product/ahan-price-membership/">لیست محصولات موجود</a>',
            ]);

            // Price adjustment input for variations
            woocommerce_wp_text_input([
                'id'          => '_ahan_price_adjustment[' . $loop . ']',
                'label'       => 'اصلاح قیمت:',
                'value'       => get_post_meta($variation->ID, '_ahan_price_adjustment', true),
                'type'        => 'text',
                'class'       => 'ltr-input',
                'placeholder' => '{price}',
                'desc_tip'    => false,
            ]);
            
            // 24h change for variations
            woocommerce_wp_text_input([
                'id'          => '_ahan_24h_change[' . $loop . ']',
                'label'       => 'نوسانات:',
                'value'       => get_post_meta($variation->ID, '_ahan_24h_change', true),
                'type'        => 'text',
                'disabled'    => true,
                'class'       => 'ltr-input',
                'description' => 'شورتکد نوسانات:<code>[ahan-change]</code>',
            ]);
            ?>
        </div>
        <?php
    }

    public function save_product_meta($post_id) {
        // Save auto-update checkbox for simple and variable products
        $auto_update = isset($_POST['_ahan_auto_update']) ? 'yes' : 'no';
        update_post_meta($post_id, '_ahan_auto_update', $auto_update);

        // Save product code for simple products
        if (isset($_POST['_ahan_product_code'])) {
            update_post_meta($post_id, '_ahan_product_code', sanitize_text_field($_POST['_ahan_product_code']));
        }

        // Save price adjustment formula for simple products
        if (isset($_POST['_ahan_price_adjustment'])) {
            update_post_meta($post_id, '_ahan_price_adjustment', sanitize_text_field($_POST['_ahan_price_adjustment']));
        }
    }

    public function save_variation_meta($variation_id, $i) {
        // Save product code for variations
        if (isset($_POST['_ahan_product_code'][$i])) {
            update_post_meta($variation_id, '_ahan_product_code', sanitize_text_field($_POST['_ahan_product_code'][$i]));
        }

        // Save price adjustment formula for variations
        if (isset($_POST['_ahan_price_adjustment'][$i])) {
            update_post_meta($variation_id, '_ahan_price_adjustment', sanitize_text_field($_POST['_ahan_price_adjustment'][$i]));
        }
    }

    /**
     * Shortcode to display the last price date.
     *
     * @return string The last price date.
     */
    public function ahan_date_shortcode() {
        wp_enqueue_style(
            'ahan-price-frontend-style',
            plugin_dir_url(__DIR__) . '../assets/css/ahan-price-frontend.css',
            [],
            '1.0.0'
        );

        $product_id = get_the_ID();
        $last_price_date = get_post_meta($product_id, '_ahan_last_price_date', true);

        if (!empty($last_price_date)) {
            return '<p class="ahan-update-date">' . esc_html($last_price_date) . '</p>';
        }

        return '<p class="ahan-update-date">نامشخص</p>';
    }

    /**
     * Shortcode to display the 24h change.
     *
     * @return string The 24h change.
     */
    public function ahan_change_shortcode() {
        wp_enqueue_style(
            'ahan-price-frontend-style',
            plugin_dir_url(__DIR__) . '../assets/css/ahan-price-frontend.css',
            [],
            '1.0.0'
        );

        $product_id = get_the_ID();
        $change_value = get_post_meta($product_id, '_ahan_24h_change', true);

        if (empty($change_value)) {
            return '<p class="ahan-change ahan-no-changes">۰.۰%</p>';
        }

        $formatted_value = floatval($change_value);
        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $display_value = str_replace($en, $fa, $change_value);

        $class = 'ahan-no-changes';
        if ($formatted_value > 0) {
            $class = 'ahan-increase';
            $display_value = '+' . $display_value;
        } elseif ($formatted_value < 0) {
            $class = 'ahan-decrease';
        }

        return '<p class="ahan-change ' . esc_attr($class) . '">' . esc_html($display_value) . '%</p>';
    }

    public function custom_admin_css() {
        ?>
        <style>
            .ltr-input input {
                direction: ltr;
            }
            input.ltr-input {
                direction: ltr!important;
            }

            .options_group .checkbox {
                margin-bottom: 10px;
            }

            .options_group .description {
                margin-top: 10px;
                font-size: 13px;
                color: #666;
            }

            .options_group .description code {
                background: #f7f7f7;
                padding: 2px 4px;
                border-radius: 3px;
            }

            li.ahan_price_options.ahan_price_tab a::before {
                content: "\f137" !important;
            }
            /* New styles for variable product fields */
            .ahan-info-message {
                color: #279e27;
                background-color: #d1ffc2;
                padding: 10px;
                border-radius: 5px;
                border: 1px solid #279e27;
            }

        </style>
        <?php
    }
}
