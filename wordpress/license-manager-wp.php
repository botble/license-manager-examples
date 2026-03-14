<?php

/**
 * Plugin Name: License Manager Client
 * Plugin URI:  https://github.com/your-org/license-manager-wordpress-example
 * Description: WordPress integration example for License Manager - activate, verify, and manage product licenses.
 * Version:     1.0.0
 * Author:      Your Name
 * Author URI:  https://your-site.com
 * License:     MIT
 * Text Domain: license-manager-wp
 * Requires PHP: 7.4
 */

if (! defined('ABSPATH')) {
    exit;
}

define('LM_WP_VERSION', '1.0.0');
define('LM_WP_PLUGIN_FILE', __FILE__);
define('LM_WP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LM_WP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LM_WP_PLUGIN_BASENAME', plugin_basename(__FILE__));

require_once LM_WP_PLUGIN_DIR . 'includes/class-lm-api-client.php';
require_once LM_WP_PLUGIN_DIR . 'includes/class-lm-admin.php';
require_once LM_WP_PLUGIN_DIR . 'includes/class-lm-updater.php';

/**
 * Main plugin class.
 */
final class License_Manager_WP
{
    private static ?self $instance = null;

    private LM_API_Client $client;

    private LM_Admin $admin;

    private LM_Updater $updater;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        $this->init_client();
        $this->admin = new LM_Admin($this->client);
        $this->updater = new LM_Updater($this->client);

        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        add_action('lm_daily_license_check', [$this, 'verify_license_cron']);
    }

    private function init_client(): void
    {
        $api_url = get_option('lm_api_url', '');
        $api_key = get_option('lm_api_key', '');
        $product_id = get_option('lm_product_id', '');

        $this->client = new LM_API_Client($api_url, $api_key, $product_id);
    }

    public function activate(): void
    {
        if (! wp_next_scheduled('lm_daily_license_check')) {
            wp_schedule_event(time(), 'daily', 'lm_daily_license_check');
        }
    }

    public function deactivate(): void
    {
        wp_clear_scheduled_hook('lm_daily_license_check');
    }

    public function verify_license_cron(): void
    {
        $license_data = get_option('lm_license_data', '');

        if (empty($license_data)) {
            return;
        }

        $result = $this->client->verify_license($license_data);

        update_option('lm_license_status', $result['is_active'] ? 'active' : 'invalid');
        update_option('lm_license_last_check', current_time('mysql'));
    }

    public function get_client(): LM_API_Client
    {
        return $this->client;
    }
}

/**
 * Returns the main plugin instance.
 */
function license_manager_wp(): License_Manager_WP
{
    return License_Manager_WP::instance();
}

license_manager_wp();
