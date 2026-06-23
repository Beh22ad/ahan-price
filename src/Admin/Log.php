<?php
// File: src/Admin/Log.php

namespace AhanPrice\Admin;

class Log
{
    private static $instance = null;
    private $log_dir;
    private $log_file;
    private $htaccess_file;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->log_dir = WP_CONTENT_DIR . '/ahan-price';
        $this->log_file = $this->log_dir . '/data.log';
        $this->htaccess_file = $this->log_dir . '/.htaccess';

        $this->ensure_directory_exists();
        $this->protect_directory();
        $this->ensure_log_file_exists();
    }

    /**
     * Ensure the log directory exists
     */
    private function ensure_directory_exists()
    {
        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
        }
    }

    /**
     * Ensure the log file exists
     */
    private function ensure_log_file_exists()
    {
        if (!file_exists($this->log_file)) {
            @touch($this->log_file);
            @chmod($this->log_file, 0644);
        }
    }

    /**
     * Protect log directory from direct access
     */
    private function protect_directory()
    {
        // Create .htaccess for Apache
        if (!file_exists($this->htaccess_file)) {
            $htaccess_content = "# Prevent direct access to log files\n";
            $htaccess_content .= "<FilesMatch \"\.log$\">\n";
            $htaccess_content .= "    Order Deny,Allow\n";
            $htaccess_content .= "    Deny from all\n";
            $htaccess_content .= "</FilesMatch>\n";

            @file_put_contents($this->htaccess_file, $htaccess_content);
        }

        // Create index.php to prevent directory listing
        $index_file = $this->log_dir . '/index.php';
        if (!file_exists($index_file)) {
            @file_put_contents($index_file, '<?php // Silence is golden');
        }
    }

    /**
     * Write a message to the log file
     *
     * @param string $message The message to log
     * @param string $level The log level (INFO, ERROR, WARNING, DEBUG)
     * @return bool True on success, false on failure
     */
    public function write($message, $level = 'INFO')
    {
        // Only log if debug mode is enabled
        if (!get_option('ahan_price_debug', false)) {
            return false;
        }

        // Ensure file exists before writing
        $this->ensure_log_file_exists();

        // Set timezone to Tehran
        date_default_timezone_set('Asia/Tehran');

        // Get current date and time
        $gregorian_time = date('H:i:s');

        // Convert to Jalali date
        $jalali_date = DateConverter::gregorianToJalali(
            date('Y'),
            date('m'),
            date('d'),
            '/'
        );

        // Combine Persian date with time
        $persian_datetime = $jalali_date . ' - ' . $gregorian_time;

        // Format the log entry
        $log_entry = sprintf(
            "[%s] [%s] %s\n",
            $persian_datetime,
            strtoupper($level),
            $message
        );

        // Write to file
        $result = @file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);

        return $result !== false;
    }

    /**
     * Get log file contents
     *
     * @return string|false The log contents or false on failure
     */
    public function get_contents()
    {
        if (!file_exists($this->log_file)) {
            return false;
        }

        return @file_get_contents($this->log_file);
    }

    /**
     * Check if log file exists and has content
     *
     * @return bool
     */
    public function exists()
    {
        return file_exists($this->log_file) && is_readable($this->log_file) && filesize($this->log_file) > 0;
    }

    /**
     * Get log file size in bytes
     *
     * @return int|false
     */
    public function get_size()
    {
        if (!file_exists($this->log_file)) {
            return false;
        }

        return filesize($this->log_file);
    }

    /**
     * Get formatted file size
     *
     * @return string
     */
    public function get_formatted_size()
    {
        $size = $this->get_size();

        if ($size === false || $size === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, 2) . ' ' . $units[$i];
    }

    /**
     * Delete the log file
     *
     * @return bool True on success, false on failure
     */
    public function delete()
    {
        if (!file_exists($this->log_file)) {
            return false;
        }

        return @unlink($this->log_file);
    }

    /**
     * Get the log file path for download
     *
     * @return string
     */
    public function get_file_path()
    {
        return $this->log_file;
    }

    /**
     * Get the log file URL for download (not directly accessible)
     *
     * @return string
     */
    public function get_download_url()
    {
        return add_query_arg(
            [
                'action' => 'ahan_price_download_log',
                'nonce'  => wp_create_nonce('ahan_price_download_log'),
            ],
            admin_url('admin-ajax.php')
        );
    }
}