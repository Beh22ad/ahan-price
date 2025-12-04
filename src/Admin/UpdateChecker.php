<?php

namespace AhanPrice\Admin;

class UpdateChecker
{
    protected static $plugin_file;

    public static function run($plugin_file, $license_key)
    {
        self::$plugin_file = $plugin_file;

        $plugin_data = get_file_data($plugin_file, [
            "Version" => "Version",
            "TextDomain" => "Text Domain",
        ]);

        $namespace = $plugin_data["TextDomain"];
        $version = $plugin_data["Version"];

        // transient key based on namespace
        $transient_key = $namespace . "_update_response";

        // Try to get cached response
        $data = get_transient($transient_key);

        if ($data === false) {
            // No cached response, call API
            $api_url = add_query_arg(
                [
                    "namespace" => $namespace,
                    "version" => $version,
                    "license_key" => $license_key,
                    "site" => site_url(),
                ],
                "https://mrnargil-updater.spaindoh.workers.dev/",
            );

            $response = wp_remote_get($api_url, ["timeout" => 10]);

            if (
                !is_wp_error($response) &&
                wp_remote_retrieve_response_code($response) === 200
            ) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);

                // Store in transient for 3 HOUR
                set_transient($transient_key, $data, 3 * HOUR_IN_SECONDS);
            }
        }

        // If we have data (either cached or fresh), display notice
        if (!empty($data)) {
            if (!empty($data["update_available"])) {
                return '<div class="notice notice-error is-dismissible"><p>' .
                    esc_html($data["message"]) .
                    ' <a href="' .
                    esc_url($data["download_url"]) .
                    '" target="_blank">دانلود نسخه ' .
                    esc_html($data["latest_version"]) .
                    "</a>" .
                    "</p></div>";
            } else {
                return;
                /*
                echo '<div class="notice notice-success is-dismissible"><p>' .
                    esc_html($data["message"]) .
                    "</p></div>";
                    */
            }
        }
    }

    public static function registerCacheCleaner($plugin_file)
    {
        self::$plugin_file = $plugin_file;
        add_action('upgrader_process_complete', [__CLASS__, 'clearCache'], 10, 2);
    }

    public static function clearCache($upgrader, $options)
    {
        if (!self::$plugin_file) {
            return;
        }

        $plugin_data = get_file_data(self::$plugin_file, [
            'TextDomain' => 'Text Domain',
        ]);

        if (!empty($plugin_data['TextDomain'])) {
            $namespace = $plugin_data['TextDomain'];
            delete_transient($namespace . '_update_response');
        }
    }
}
