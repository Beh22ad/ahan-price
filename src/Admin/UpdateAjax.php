<?php

namespace AhanPrice\Admin;

class UpdateAjax
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
        add_action('wp_ajax_ahan_price_manual_update', [$this, 'manual_update']);
        add_action('wp_ajax_ahan_price_update_batch',  [$this, 'update_batch']);
        add_action('wp_ajax_ahan_price_get_progress',  [$this, 'get_progress']);
        add_action('wp_ajax_ahan_price_cancel_update', [$this, 'cancel_update']);
    }

    // ----- STATIC DISPATCHERS (kept for backward compatibility) -----

    public static function handle_manual_update()
    {
        self::get_instance()->manual_update();
    }
    public static function handle_update_batch()
    {
        self::get_instance()->update_batch();
    }
    public static function handle_get_progress()
    {
        self::get_instance()->get_progress();
    }
    public static function handle_cancel_update()
    {
        self::get_instance()->cancel_update();
    }

    // ----- HANDLERS -----

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

        $batch_size  = apply_filters('ahan_price_batch_size', 5);
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
                    'completed'     => true,
                    'total'         => 0,
                    'processed'     => 0,
                    'success_count' => 0,
                    'error_count'   => 0,
                    'percentage'    => 0,
                    'message'       => 'No products to update',
                ]);
            }

            set_transient('ahan_products_ids_batch', $product_ids, HOUR_IN_SECONDS);
            set_transient('ahan_update_progress', [
                'total'         => count($product_ids),
                'processed'     => 0,
                'success_count' => 0,
                'error_count'   => 0,
                'status'        => 'running',
                'started_at'    => time(),
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
                'completed'     => true,
                'total'         => $progress['total'],
                'processed'     => $progress['processed'],
                'success_count' => $progress['success_count'],
                'error_count'   => $progress['error_count'],
                'percentage'    => 100,
                'message'       => 'All products updated successfully!',
            ]);
        }

        $batch_success = 0;
        $batch_error   = 0;

        foreach ($remaining as $product_id) {
            try {
                $price_updater = \AhanPrice\PriceUpdater::get_instance();
                $result        = $price_updater->process_single_product_by_id($product_id);

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
        $progress['error_count']   = ($progress['error_count'] ?? 0) + $batch_error;

        set_transient('ahan_update_progress', $progress, HOUR_IN_SECONDS);

        $completed = $progress['processed'] >= $progress['total'];

        if ($completed) {
            $this->cleanup_update_session();
        }

        wp_send_json_success([
            'completed'     => $completed,
            'total'         => $progress['total'],
            'processed'     => $progress['processed'],
            'success_count' => $progress['success_count'],
            'error_count'   => $progress['error_count'],
            'percentage'    => $progress['total'] > 0 ? round(($progress['processed'] / $progress['total']) * 100, 2) : 0,
            'message'       => $completed ? 'All products updated successfully!' : 'Batch processed successfully',
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
                'status'        => 'idle',
                'total'         => 0,
                'processed'     => 0,
                'success_count' => 0,
                'error_count'   => 0,
                'percentage'    => 0,
            ]);
        }

        $percentage = $progress['total'] > 0
            ? round(($progress['processed'] / $progress['total']) * 100, 2)
            : 0;

        wp_send_json_success([
            'status'        => $progress['status'] ?? 'running',
            'total'         => $progress['total'],
            'processed'     => $progress['processed'],
            'success_count' => $progress['success_count'] ?? 0,
            'error_count'   => $progress['error_count'] ?? 0,
            'percentage'    => $percentage,
            'started_at'    => $progress['started_at'] ?? null,
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
}
