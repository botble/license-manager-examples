<?php

/**
 * License Manager Auto-Updater
 *
 * Hooks into WordPress's plugin update system to check for and install
 * updates from the License Manager server.
 */

if (! defined('ABSPATH')) {
    exit;
}

class LM_Updater
{
    private LM_API_Client $client;

    private string $plugin_slug;

    public function __construct(LM_API_Client $client)
    {
        $this->client = $client;
        $this->plugin_slug = LM_WP_PLUGIN_BASENAME;

        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 10, 3);
        add_filter('upgrader_pre_download', [$this, 'download_with_license'], 10, 3);
        add_filter('upgrader_source_selection', [$this, 'maybe_rename_source'], 10, 4);
    }

    /**
     * Check the License Manager server for available updates.
     *
     * @param object $transient WordPress update transient
     * @return object Modified transient
     */
    public function check_for_update(object $transient): object
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        if (get_option('lm_license_status') !== 'active' || ! $this->client->is_configured()) {
            return $transient;
        }

        $result = $this->client->check_update(LM_WP_VERSION);

        if (empty($result['update_available']) || empty($result['version'])) {
            return $transient;
        }

        if (version_compare($result['version'], LM_WP_VERSION, '<=')) {
            return $transient;
        }

        $update_id = $result['update_id'] ?? '';
        $download_url = '';

        if ($update_id) {
            $download_url = $this->client->get_update_download_url($update_id, 'main');
        }

        $transient->response[$this->plugin_slug] = (object) [
            'slug' => dirname($this->plugin_slug),
            'plugin' => $this->plugin_slug,
            'new_version' => $result['version'],
            'url' => get_option('lm_api_url', ''),
            'package' => $download_url,
            'tested' => get_bloginfo('version'),
            'requires_php' => '7.4',
        ];

        return $transient;
    }

    /**
     * Provide plugin information for the update details popup.
     *
     * @param false|object|array $result
     * @param string             $action
     * @param object             $args
     * @return false|object
     */
    public function plugin_info($result, string $action, object $args)
    {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (! isset($args->slug) || $args->slug !== dirname($this->plugin_slug)) {
            return $result;
        }

        if (! $this->client->is_configured()) {
            return $result;
        }

        $latest = $this->client->get_latest_version();
        $version_data = $latest['data'] ?? null;

        if (! $version_data) {
            return $result;
        }

        return (object) [
            'name' => 'License Manager Client',
            'slug' => dirname($this->plugin_slug),
            'version' => $version_data['version'] ?? '',
            'author' => '<a href="https://your-site.com">Your Name</a>',
            'requires' => '5.0',
            'tested' => get_bloginfo('version'),
            'requires_php' => '7.4',
            'last_updated' => $version_data['released_at'] ?? '',
            'sections' => [
                'description' => $version_data['summary'] ?? '',
                'changelog' => $version_data['changelog'] ?? '',
            ],
        ];
    }

    /**
     * Intercept the download to use POST with license_data authentication.
     *
     * WordPress default uses GET for package downloads, but the License Manager
     * update endpoint requires POST with license_data in the body.
     *
     * @param bool|WP_Error $reply    Whether to bail without returning the package (default: false)
     * @param string        $package  The package URL
     * @param object        $upgrader The WP_Upgrader instance
     * @return bool|string|WP_Error The local path to the downloaded package, or false to let WP handle it
     */
    public function download_with_license($reply, string $package, object $upgrader)
    {
        if (! str_contains($package, '/api/external/update/') || ! str_contains($package, '/download/')) {
            return $reply;
        }

        $license_data = get_option('lm_license_data', '');

        if (empty($license_data)) {
            return $reply;
        }

        // Extract version_id and type from URL
        if (! preg_match('#/api/external/update/([^/]+)/download/([^/]+)#', $package, $matches)) {
            return $reply;
        }

        $version_id = $matches[1];
        $type = $matches[2];

        $tmp_file = wp_tempnam($package);

        $result = $this->client->download_update($version_id, $type, $license_data, $tmp_file);

        if (! $result['success']) {
            @unlink($tmp_file);

            return new WP_Error('download_failed', $result['message']);
        }

        return $tmp_file;
    }

    /**
     * Rename the extracted plugin folder to match the expected directory name.
     *
     * @param string       $source        Extracted source path
     * @param string       $remote_source Remote source path
     * @param object       $upgrader      WP_Upgrader instance
     * @param array|object $hook_extra    Extra hook data
     * @return string Source path
     */
    public function maybe_rename_source(string $source, string $remote_source, object $upgrader, $hook_extra): string
    {
        $plugin = $hook_extra['plugin'] ?? '';

        if ($plugin !== $this->plugin_slug) {
            return $source;
        }

        $expected_dir = trailingslashit($remote_source) . dirname($this->plugin_slug) . '/';

        if ($source !== $expected_dir) {
            global $wp_filesystem;

            if ($wp_filesystem->move($source, $expected_dir)) {
                return $expected_dir;
            }
        }

        return $source;
    }
}
