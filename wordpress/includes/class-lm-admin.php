<?php
/**
 * License Manager Admin Page
 *
 * Handles the WordPress admin settings page for license management.
 */

if (! defined('ABSPATH')) {
    exit;
}

class LM_Admin
{
    private LM_API_Client $client;

    public function __construct(LM_API_Client $client)
    {
        $this->client = $client;

        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'handle_actions']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_notices', [$this, 'show_license_notices']);
    }

    public function add_menu_page(): void
    {
        add_options_page(
            __('License Manager', 'license-manager-wp'),
            __('License Manager', 'license-manager-wp'),
            'manage_options',
            'license-manager',
            [$this, 'render_page']
        );
    }

    public function register_settings(): void
    {
        register_setting('lm_settings', 'lm_api_url', [
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
        ]);
        register_setting('lm_settings', 'lm_api_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('lm_settings', 'lm_product_id', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
    }

    public function enqueue_assets(string $hook): void
    {
        if ($hook !== 'settings_page_license-manager') {
            return;
        }

        wp_enqueue_style(
            'lm-admin',
            LM_WP_PLUGIN_URL . 'assets/css/admin.css',
            [],
            LM_WP_VERSION
        );

        wp_enqueue_script(
            'lm-admin',
            LM_WP_PLUGIN_URL . 'assets/js/admin.js',
            [],
            LM_WP_VERSION,
            true
        );
    }

    public function handle_actions(): void
    {
        if (! isset($_POST['lm_action']) || ! current_user_can('manage_options')) {
            return;
        }

        if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['lm_nonce'] ?? '')), 'lm_action_nonce')) {
            return;
        }

        $action = sanitize_text_field(wp_unslash($_POST['lm_action']));

        switch ($action) {
            case 'activate':
                $this->handle_activate();

                break;
            case 'deactivate':
                $this->handle_deactivate();

                break;
            case 'verify':
                $this->handle_verify();

                break;
            case 'check_connection':
                $this->handle_check_connection();

                break;
        }
    }

    private function handle_activate(): void
    {
        $license_code = sanitize_text_field(wp_unslash($_POST['lm_license_code'] ?? ''));
        $client_name = sanitize_text_field(wp_unslash($_POST['lm_client_name'] ?? ''));

        if (empty($license_code) || empty($client_name)) {
            $this->add_notice('error', __('License code and client name are required.', 'license-manager-wp'));

            return;
        }

        if (! $this->client->is_configured()) {
            $this->add_notice('error', __('Please configure API settings first.', 'license-manager-wp'));

            return;
        }

        $result = $this->client->activate_license($license_code, $client_name);

        if (! empty($result['is_active'])) {
            $license_data = $result['lic_response'] ?? $result['data']['license_data'] ?? '';

            if (! empty($license_data)) {
                update_option('lm_license_data', $license_data);
            }

            update_option('lm_license_code', $license_code);
            update_option('lm_client_name', $client_name);
            update_option('lm_license_status', 'active');
            update_option('lm_license_last_check', current_time('mysql'));

            $this->add_notice('success', $result['message'] ?? __('License activated successfully!', 'license-manager-wp'));
        } else {
            update_option('lm_license_status', 'inactive');
            $this->add_notice('error', $result['message'] ?? __('Activation failed.', 'license-manager-wp'));
        }
    }

    private function handle_deactivate(): void
    {
        $license_data = get_option('lm_license_data', '');

        if (empty($license_data)) {
            $this->add_notice('error', __('No active license to deactivate.', 'license-manager-wp'));

            return;
        }

        $result = $this->client->deactivate_license($license_data);

        if (! empty($result['is_active'])) {
            delete_option('lm_license_data');
            update_option('lm_license_status', 'inactive');
            $this->add_notice('success', $result['message'] ?? __('License deactivated.', 'license-manager-wp'));
        } else {
            $this->add_notice('error', $result['message'] ?? __('Deactivation failed.', 'license-manager-wp'));
        }
    }

    private function handle_verify(): void
    {
        $license_data = get_option('lm_license_data', '');

        if (empty($license_data)) {
            $this->add_notice('error', __('No license data found. Activate a license first.', 'license-manager-wp'));

            return;
        }

        $result = $this->client->verify_license($license_data);

        update_option('lm_license_last_check', current_time('mysql'));

        if (! empty($result['is_active'])) {
            update_option('lm_license_status', 'active');
            $this->add_notice('success', $result['message'] ?? __('License is valid!', 'license-manager-wp'));
        } else {
            update_option('lm_license_status', 'invalid');
            $this->add_notice('error', $result['message'] ?? __('License is invalid.', 'license-manager-wp'));
        }
    }

    private function handle_check_connection(): void
    {
        if (! $this->client->is_configured()) {
            $this->add_notice('error', __('Please configure API settings first.', 'license-manager-wp'));

            return;
        }

        $result = $this->client->check_connection();

        if (! empty($result['is_active'])) {
            $this->add_notice('success', $result['message'] ?? __('Connection successful!', 'license-manager-wp'));
        } else {
            $this->add_notice('error', $result['message'] ?? __('Connection failed.', 'license-manager-wp'));
        }
    }

    public function show_license_notices(): void
    {
        $notices = get_transient('lm_admin_notices');

        if (empty($notices) || ! is_array($notices)) {
            return;
        }

        foreach ($notices as $notice) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($notice['type']),
                esc_html($notice['message'])
            );
        }

        delete_transient('lm_admin_notices');
    }

    private function add_notice(string $type, string $message): void
    {
        $notices = get_transient('lm_admin_notices') ?: [];
        $notices[] = ['type' => $type, 'message' => $message];
        set_transient('lm_admin_notices', $notices, 60);
    }

    public function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $license_status = get_option('lm_license_status', 'inactive');
        $last_check = get_option('lm_license_last_check', '');
        $is_active = $license_status === 'active';
        ?>
        <div class="wrap lm-admin-wrap">
            <h1><?php echo esc_html__('License Manager', 'license-manager-wp'); ?></h1>

            <!-- License Status Card -->
            <div class="lm-card lm-status-card <?php echo $is_active ? 'lm-status-active' : 'lm-status-inactive'; ?>">
                <div class="lm-status-indicator">
                    <span class="lm-status-dot"></span>
                    <strong>
                        <?php
                        if ($is_active) {
                            echo esc_html__('License Active', 'license-manager-wp');
                        } elseif ($license_status === 'invalid') {
                            echo esc_html__('License Invalid', 'license-manager-wp');
                        } else {
                            echo esc_html__('No Active License', 'license-manager-wp');
                        }
        ?>
                    </strong>
                </div>
                <?php if ($last_check) : ?>
                    <p class="lm-last-check">
                        <?php
        printf(
            esc_html__('Last verified: %s', 'license-manager-wp'),
            esc_html($last_check)
        );
                    ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- API Settings -->
            <div class="lm-card">
                <h2><?php echo esc_html__('API Settings', 'license-manager-wp'); ?></h2>
                <form method="post" action="options.php">
                    <?php settings_fields('lm_settings'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="lm_api_url"><?php echo esc_html__('API URL', 'license-manager-wp'); ?></label>
                            </th>
                            <td>
                                <input type="url" id="lm_api_url" name="lm_api_url"
                                       value="<?php echo esc_attr(get_option('lm_api_url', '')); ?>"
                                       class="regular-text" placeholder="https://license.example.com" />
                                <p class="description"><?php echo esc_html__('Your License Manager server URL.', 'license-manager-wp'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="lm_api_key"><?php echo esc_html__('API Key', 'license-manager-wp'); ?></label>
                            </th>
                            <td>
                                <input type="password" id="lm_api_key" name="lm_api_key"
                                       value="<?php echo esc_attr(get_option('lm_api_key', '')); ?>"
                                       class="regular-text" autocomplete="off" />
                                <button type="button" class="button lm-toggle-password" data-target="lm_api_key">
                                    <?php echo esc_html__('Show', 'license-manager-wp'); ?>
                                </button>
                                <p class="description"><?php echo esc_html__('External API key from License Manager settings.', 'license-manager-wp'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="lm_product_id"><?php echo esc_html__('Product ID', 'license-manager-wp'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="lm_product_id" name="lm_product_id"
                                       value="<?php echo esc_attr(get_option('lm_product_id', '')); ?>"
                                       class="regular-text" placeholder="ABC12345" />
                                <p class="description"><?php echo esc_html__('Product Reference ID from License Manager.', 'license-manager-wp'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <?php submit_button(__('Save Settings', 'license-manager-wp'), 'primary', 'submit', false); ?>
                        &nbsp;
                        <button type="submit" name="lm_action" value="check_connection" class="button"
                                form="lm-connection-form">
                            <?php echo esc_html__('Test Connection', 'license-manager-wp'); ?>
                        </button>
                    </p>
                </form>

                <form method="post" id="lm-connection-form" style="display:none;">
                    <?php wp_nonce_field('lm_action_nonce', 'lm_nonce'); ?>
                    <input type="hidden" name="lm_action" value="check_connection" />
                </form>
            </div>

            <!-- License Activation -->
            <div class="lm-card">
                <h2><?php echo esc_html__('License', 'license-manager-wp'); ?></h2>

                <?php if ($is_active) : ?>
                    <div class="lm-license-info">
                        <table class="lm-info-table">
                            <tr>
                                <td><strong><?php echo esc_html__('License Code', 'license-manager-wp'); ?></strong></td>
                                <td><code><?php echo esc_html(get_option('lm_license_code', '')); ?></code></td>
                            </tr>
                            <tr>
                                <td><strong><?php echo esc_html__('Client Name', 'license-manager-wp'); ?></strong></td>
                                <td><?php echo esc_html(get_option('lm_client_name', '')); ?></td>
                            </tr>
                        </table>
                    </div>

                    <form method="post" class="lm-action-form">
                        <?php wp_nonce_field('lm_action_nonce', 'lm_nonce'); ?>
                        <button type="submit" name="lm_action" value="verify" class="button">
                            <?php echo esc_html__('Verify License', 'license-manager-wp'); ?>
                        </button>
                        &nbsp;
                        <button type="submit" name="lm_action" value="deactivate" class="button lm-btn-danger"
                                onclick="return confirm('<?php echo esc_js(__('Are you sure you want to deactivate this license?', 'license-manager-wp')); ?>');">
                            <?php echo esc_html__('Deactivate License', 'license-manager-wp'); ?>
                        </button>
                    </form>

                <?php else : ?>
                    <form method="post" class="lm-action-form">
                        <?php wp_nonce_field('lm_action_nonce', 'lm_nonce'); ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="lm_license_code"><?php echo esc_html__('License Code', 'license-manager-wp'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="lm_license_code" name="lm_license_code"
                                           value="<?php echo esc_attr(get_option('lm_license_code', '')); ?>"
                                           class="regular-text" placeholder="XXXX-XXXX-XXXX-XXXX" required />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="lm_client_name"><?php echo esc_html__('Client Name', 'license-manager-wp'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="lm_client_name" name="lm_client_name"
                                           value="<?php echo esc_attr(get_option('lm_client_name', '')); ?>"
                                           class="regular-text" placeholder="John Doe" required />
                                    <p class="description"><?php echo esc_html__('The buyer/customer name associated with the license.', 'license-manager-wp'); ?></p>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <button type="submit" name="lm_action" value="activate" class="button button-primary">
                                <?php echo esc_html__('Activate License', 'license-manager-wp'); ?>
                            </button>
                        </p>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
