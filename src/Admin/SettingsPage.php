<?php

namespace AhanPrice\Admin;

class SettingsPage
{
    public static function render()
    {
        $license_key = get_option('ahan_price_key', '');
        echo UpdateChecker::run(AHAN_PRICE_MAIN_FILE, $license_key);

        $log        = Log::get_instance();
        $log_exists = $log->exists();

        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
        if (!in_array($active_tab, ['general', 'update', 'troubleshoot'], true)) {
            $active_tab = 'general';
        }

        $base_url = admin_url('admin.php?page=ahan-price-settings');
?>
        <div class="wrap ahan-price-settings">
            <h1>تنظیمات افزونه قیمت آهن</h1>

            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_url(add_query_arg('tab', 'general', $base_url)); ?>"
                    class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">عمومی</a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'update', $base_url)); ?>"
                    class="nav-tab <?php echo $active_tab === 'update' ? 'nav-tab-active' : ''; ?>">آپدیت قیمت‌ها</a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'troubleshoot', $base_url)); ?>"
                    class="nav-tab <?php echo $active_tab === 'troubleshoot' ? 'nav-tab-active' : ''; ?>">عیب یابی</a>
            </h2>

            <?php
            if ($active_tab === 'general') {
                self::render_general_tab();
            } elseif ($active_tab === 'update') {
                self::render_update_tab();
            } else {
                self::render_troubleshoot_tab($log, $log_exists);
            }
            ?>
        </div>
    <?php
    }

    private static function render_general_tab()
    {
    ?>
        <form method="post" action="options.php">
            <?php settings_fields('ahan_price_settings_general'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">نقطه اتصال</th>
                    <td>
                        <input type="text" name="ahan_price_key" value="<?php echo esc_attr(get_option('ahan_price_key')); ?>"
                            class="regular-text">
                        <p class="description">
                            با استفاده از <a href="https://mrnargil.ir/product/ahan-price-membership/">کلید دسترسی</a>
                            به تمام قیمت‌های ارائه شده دسترسی خواهید داشت
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
            </table>

            <?php submit_button(); ?>
        </form>

        <hr>

        <h2>بررسی وضعیت اتصال</h2>
        <p class="description">
            برای بررسی صحت کلید دسترسی و در دسترس بودن سرور، روی دکمه زیر کلیک کنید.
        </p>
        <p>
            <button type="button" id="ahan-check-license-btn" class="button button-secondary">
                بررسی وضعیت اتصال
            </button>
        </p>
        <div id="ahan-license-result"></div>
    <?php
    }

    private static function render_update_tab()
    {
    ?>
        <h2>آپدیت قیمت‌ها</h2>
        <p class="description ahan-update-description">
            افزونه به طور روزانه و اتوماتیک قیمت‌ها را آپدیت می‌کند، اگر می‌خواهید اکنون قیمت‌ها را بروز رسانی کنید از این قسمت
            استفاده کنید.
        </p>

        <button id="ahan-price-manual-update" class="button button-primary">شروع آپدیت قیمت‌ها</button>
        <p id="ahan-price-update-message" class="ahan-hidden">
            ربات دریافت قیمت با موفقیت اجرا شد، چند لحظه صبر کنید سپس قیمت محصولات سایت را چک کنید.
        </p>
    <?php
    }

    private static function render_troubleshoot_tab($log, $log_exists)
    {
    ?>
        <h2>عیب یابی</h2>

        <form method="post" action="options.php">
            <?php settings_fields('ahan_price_settings_troubleshoot'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">فعال‌سازی حالت دیباگ</th>
                    <td>
                        <label>
                            <input type="hidden" name="ahan_price_debug" value="0">
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

        <h2>مدیریت فایل لاگ</h2>
        <div class="ahan-log-management">
            <?php if ($log_exists): ?>
                <p>
                    <a href="<?php echo esc_url($log->get_download_url()); ?>" class="button button-secondary"
                        id="ahan-download-log">
                        <span class="dashicons dashicons-download"></span>
                        دانلود فایل لاگ
                    </a>
                    <button id="ahan-delete-log" class="button button-secondary ahan-delete-log-btn">
                        <span class="dashicons dashicons-trash"></span>
                        حذف فایل لاگ
                    </button>
                </p>
                <p class="description ahan-log-size">
                    حجم فایل: <?php echo esc_html($log->get_formatted_size()); ?>
                </p>
            <?php else: ?>
                <p class="description">
                    حالت دیباگ خاموش است و فایل لاگی برای نمایش وجود ندارد.
                </p>
            <?php endif; ?>

            <div id="ahan-log-message" class="ahan-hidden"></div>
        </div>
<?php
    }
}
