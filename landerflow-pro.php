<?php
/**
 * Plugin Name: LanderFlow Pro 1.0.1
 * Plugin URI: https://github.com/DevWithEasy/landerflow-pro
 * Description: Professional plugin installer - Install free & premium plugins from external source
 * Version: 1.0.1
 * Author: Robiul Awal
 * Author URI: https://github.com/DevWithEasy
 * License: GPL v2 or later
 * Text Domain: landerflow-pro
 */
if (!defined('ABSPATH')) exit;

define('LANDERFLOW_PRO_VERSION', '1.0.1');
define('LANDERFLOW_PRO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LANDERFLOW_PRO_PLUGIN_URL', plugin_dir_url(__FILE__));
require_once LANDERFLOW_PRO_PLUGIN_DIR . 'includes/class-plugin-installer.php';

if (!class_exists('LanderFlow_Pro')) {
    class LanderFlow_Pro {
        private static $instance;
        private $core_slugs = ['woocommerce', 'elementor', 'cartflows'];
        private $free_plugins_json_url = 'https://raw.githubusercontent.com/DevWithEasy/landerflow-pro/v' . LANDERFLOW_PRO_VERSION . '/free-plugins.json';
        private $premium_plugins_json_url = 'https://raw.githubusercontent.com/DevWithEasy/landerflow-pro/v' . LANDERFLOW_PRO_VERSION . '/premium-plugins.json';

        public static function get_instance() { return self::$instance ?: self::$instance = new self(); }
        private function __construct() {
            add_action('admin_menu', [$this, 'add_menu']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
            add_action('wp_ajax_lf_install', [$this, 'ajax_install']);
            add_action('wp_ajax_lf_activate', [$this, 'ajax_activate']);
            add_action('wp_ajax_lf_premium_install', [$this, 'ajax_premium_install']);
            add_action('wp_ajax_lf_get_all_status', [$this, 'ajax_get_all_status']);
            add_action('wp_ajax_lf_get_free_plugins', [$this, 'ajax_get_free_plugins']);
            add_action('wp_ajax_lf_get_premium_plugins', [$this, 'ajax_get_premium_plugins']);
            add_action('activated_plugin', [$this, 'auto_trigger']);
        }
        public function add_menu() { add_menu_page('LanderFlow Pro', 'LanderFlow Pro', 'manage_options', 'landerflow-pro', [$this, 'render_page'], 'dashicons-admin-tools', 100); }
        public function enqueue_assets($hook) {
            if ('toplevel_page_landerflow-pro' !== $hook) return;
            wp_enqueue_style('lf-google-fonts', 'https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Syne:wght@700;800&display=swap', [], null);
            wp_enqueue_style('lf-admin', LANDERFLOW_PRO_PLUGIN_URL . 'assets/css/admin.css', [], LANDERFLOW_PRO_VERSION);
            wp_enqueue_script('lf-admin', LANDERFLOW_PRO_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], LANDERFLOW_PRO_VERSION, true);
            wp_localize_script('lf-admin', 'lfData', ['ajax' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('lf_nonce'), 'core' => $this->core_slugs]);
        }
        public function auto_trigger($plugin) { if (plugin_basename(__FILE__) === $plugin) update_option('lf_auto_trigger', true); }

        public function ajax_get_free_plugins() {
            check_ajax_referer('lf_nonce', 'nonce');
            $cache_key = 'lf_free_plugins_cache'; $cached = get_transient($cache_key);
            if ($cached !== false) { wp_send_json_success(['plugins' => $cached, 'source' => 'cache']); }
            $response = wp_remote_get($this->free_plugins_json_url, ['timeout' => 30, 'sslverify' => false]);
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) { wp_send_json_success(['plugins' => $this->get_fallback_free_plugins(), 'source' => 'fallback']); }
            $plugins = json_decode(wp_remote_retrieve_body($response), true);
            if (!is_array($plugins)) { wp_send_json_success(['plugins' => $this->get_fallback_free_plugins(), 'source' => 'fallback']); }
            set_transient($cache_key, $plugins, HOUR_IN_SECONDS); wp_send_json_success(['plugins' => $plugins, 'source' => 'live']);
        }

        public function ajax_get_premium_plugins() {
            check_ajax_referer('lf_nonce', 'nonce');
            $cache_key = 'lf_premium_plugins_cache'; $cached = get_transient($cache_key);
            if ($cached !== false) { wp_send_json_success(['plugins' => $cached, 'source' => 'cache']); }
            $response = wp_remote_get($this->premium_plugins_json_url, ['timeout' => 30, 'sslverify' => false]);
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) { wp_send_json_success(['plugins' => $this->get_fallback_premium_plugins(), 'source' => 'fallback']); }
            $plugins = json_decode(wp_remote_retrieve_body($response), true);
            if (!is_array($plugins)) { wp_send_json_success(['plugins' => $this->get_fallback_premium_plugins(), 'source' => 'fallback']); }
            set_transient($cache_key, $plugins, HOUR_IN_SECONDS); wp_send_json_success(['plugins' => $plugins, 'source' => 'live']);
        }

        public function ajax_install() {
            check_ajax_referer('lf_nonce', 'nonce');
            if (!current_user_can('install_plugins')) wp_send_json_error('Permission denied');
            $slug = sanitize_text_field($_POST['slug']); $file = sanitize_text_field($_POST['file'] ?? '');
            if (!$file) wp_send_json_error('No plugin file'); if ($this->is_installed($file)) wp_send_json_success();
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php'; require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php'; require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
            require_once ABSPATH . 'wp-admin/includes/file.php'; WP_Filesystem();
            $api = plugins_api('plugin_information', ['slug' => $slug, 'fields' => ['sections' => false]]);
            if (is_wp_error($api)) wp_send_json_error($api->get_error_message());
            $skin = new WP_Ajax_Upgrader_Skin(); $upgrader = new Plugin_Upgrader($skin);
            $result = $upgrader->install($api->download_link);
            if (is_wp_error($result)) wp_send_json_error($result->get_error_message()); wp_send_json_success();
        }

        public function ajax_activate() {
            check_ajax_referer('lf_nonce', 'nonce');
            if (!current_user_can('activate_plugins')) wp_send_json_error('Permission denied');
            $file = sanitize_text_field($_POST['file'] ?? ''); if (!$file) wp_send_json_error('No plugin file');
            if (is_plugin_active($file)) wp_send_json_success();
            if (!function_exists('activate_plugin')) require_once ABSPATH . 'wp-admin/includes/plugin.php';
            $result = activate_plugin($file); if (is_wp_error($result)) wp_send_json_error($result->get_error_message()); wp_send_json_success();
        }

        public function ajax_get_all_status() {
            check_ajax_referer('lf_nonce', 'nonce');
            if (!function_exists('get_plugins')) require_once ABSPATH . 'wp-admin/includes/plugin.php';
            $free_plugins = get_transient('lf_free_plugins_cache') ?: $this->get_fallback_free_plugins();
            $premium_plugins = get_transient('lf_premium_plugins_cache') ?: $this->get_fallback_premium_plugins();
            $status = ['free' => [], 'premium' => []];
            foreach ($free_plugins as $p) { $file = $p['file'] ?? ''; $status['free'][$p['id'] ?? $p['slug']] = ['name' => $p['name'], 'installed' => $this->is_installed($file), 'active' => is_plugin_active($file)]; }
            foreach ($premium_plugins as $p) { $file = $p['plugin_file'] ?? ''; $status['premium'][$p['id']] = ['name' => $p['name'], 'installed' => $this->is_installed($file), 'active' => is_plugin_active($file)]; }
            wp_send_json_success($status);
        }

        public function ajax_premium_install() {
            check_ajax_referer('lf_nonce', 'nonce');
            if (!current_user_can('install_plugins')) wp_send_json_error('Permission denied');
            $plugin_id = sanitize_text_field($_POST['plugin_id']); $zip_url = esc_url_raw($_POST['zip_url'] ?? '');
            $plugin_file = sanitize_text_field($_POST['plugin_file'] ?? ''); $plugin_name = sanitize_text_field($_POST['plugin_name'] ?? 'Premium Plugin');
            if (empty($zip_url)) wp_send_json_error('No download URL'); if ($this->is_installed($plugin_file)) wp_send_json_success(['message' => 'Already installed']);
            $zip_file = $this->download_from_cdn($zip_url, $plugin_id); if (is_wp_error($zip_file)) wp_send_json_error($zip_file->get_error_message());
            $result = LanderFlow_Plugin_Installer::install_from_zip($zip_file, $plugin_name); @unlink($zip_file);
            if (is_wp_error($result)) wp_send_json_error($result->get_error_message()); wp_send_json_success(['message' => $plugin_name . ' installed!']);
        }

        private function download_from_cdn($zip_url, $id) {
            $temp_dir = LANDERFLOW_PRO_PLUGIN_DIR . 'temp/'; if (!file_exists($temp_dir)) wp_mkdir_p($temp_dir);
            $temp_file = $temp_dir . sanitize_file_name($id . '-' . time() . '.zip');
            $response = wp_remote_get($zip_url, ['timeout' => 600, 'sslverify' => false, 'stream' => true, 'filename' => $temp_file]);
            if (is_wp_error($response)) { @unlink($temp_file); return $response; }
            if (wp_remote_retrieve_response_code($response) !== 200) { @unlink($temp_file); return new WP_Error('http_error', 'Download failed'); }
            if (!file_exists($temp_file) || filesize($temp_file) < 500) { @unlink($temp_file); return new WP_Error('empty_file', 'Corrupted'); }
            return $temp_file;
        }

        private function get_fallback_free_plugins() { return [
            ['id' => 'woocommerce', 'slug' => 'woocommerce', 'name' => 'WooCommerce', 'file' => 'woocommerce/woocommerce.php', 'icon' => '🛒', 'desc' => 'Powerful eCommerce platform', 'required' => true],
            ['id' => 'elementor', 'slug' => 'elementor', 'name' => 'Elementor', 'file' => 'elementor/elementor.php', 'icon' => '⚡', 'desc' => 'Visual drag & drop page builder', 'required' => true],
            ['id' => 'cartflows', 'slug' => 'cartflows', 'name' => 'CartFlows', 'file' => 'cartflows/cartflows.php', 'icon' => '🚀', 'desc' => 'Sales funnel & checkout builder', 'required' => true],
            ['id' => 'litespeed-cache', 'slug' => 'litespeed-cache', 'name' => 'LiteSpeed Cache', 'file' => 'litespeed-cache/litespeed-cache.php', 'icon' => '⚡', 'desc' => 'High-performance page caching', 'required' => false],
            ['id' => 'svg-support', 'slug' => 'svg-support', 'name' => 'SVG Support', 'file' => 'svg-support/svg-support.php', 'icon' => '🎨', 'desc' => 'Upload SVG files', 'required' => false],
            ['id' => 'code-snippets', 'slug' => 'code-snippets', 'name' => 'Code Snippets', 'file' => 'code-snippets/code-snippets.php', 'icon' => '💻', 'desc' => 'Add custom code snippets', 'required' => false],
        ]; }

        private function get_fallback_premium_plugins() { return [
            ['id' => 'elementor-pro', 'name' => 'Elementor Pro', 'desc' => 'Advanced page builder', 'icon' => '🎨', 'zip_url' => 'https://github.com/DevWithEasy/landerflow-pro/releases/download/preium_plugin/elementor-pro.zip', 'plugin_file' => 'elementor-pro/elementor-pro.php', 'category' => 'Page Builder', 'version' => '3.18.0', 'required' => true],
            ['id' => 'cartflows-pro', 'name' => 'CartFlows Pro', 'desc' => 'Sales funnel builder', 'icon' => '🛒', 'zip_url' => 'https://github.com/DevWithEasy/landerflow-pro/releases/download/preium_plugin/cartflows-pro.zip', 'plugin_file' => 'cartflows-pro/cartflows-pro.php', 'category' => 'Funnel', 'version' => '2.0.0', 'required' => true],
            ['id' => 'pro-elements', 'name' => 'Pro Elements', 'desc' => 'Free Elementor Pro alternative', 'icon' => '⚡', 'zip_url' => 'https://github.com/DevWithEasy/landerflow-pro/releases/download/preium_plugin/pro-elements.zip', 'plugin_file' => 'pro-elements/pro-elements.php', 'category' => 'Page Builder', 'version' => 'latest', 'required' => false],
            ['id' => 'pixelyoursite-pro', 'name' => 'PixelYourSite Pro', 'desc' => 'Facebook Pixel tracking', 'icon' => '📊', 'zip_url' => 'https://github.com/DevWithEasy/landerflow-pro/releases/download/preium_plugin/pixelyoursite-pro.zip', 'plugin_file' => 'pixelyoursite-pro/pixelyoursite-pro.php', 'category' => 'Marketing', 'version' => 'latest', 'required' => false],
        ]; }

        private function is_installed($file) { if (!function_exists('get_plugins')) require_once ABSPATH . 'wp-admin/includes/plugin.php'; return isset(get_plugins()[$file]); }

        public function render_page() {
            $auto = get_option('lf_auto_trigger', false); if ($auto) delete_option('lf_auto_trigger'); $core_count = count($this->core_slugs);
            ?>
            <div class="lfp-wrap <?php echo $auto ? 'auto-install' : ''; ?>">
                <!-- Inline Header -->
                <div class="lfp-header">
                    <div class="lfp-brand-inline">
                        <div class="lfp-brand-icon">🚀</div>
                        <div>
                            <div class="lfp-brand-name">LanderFlow Pro</div>
                            <span class="lfp-version">v<?php echo LANDERFLOW_PRO_VERSION; ?></span>
                        </div>
                    </div>
                    <div class="lfp-stats">
                        <div class="lfp-stat"><span class="lfp-stat-val stat-completed">0</span><span class="lfp-stat-lbl">Active</span></div>
                        <div class="lfp-stat"><span class="lfp-stat-val"><?php echo $core_count; ?></span><span class="lfp-stat-lbl">Core</span></div>
                    </div>
                </div>

                <!-- Warning -->
                <div class="lfp-warning hidden" id="lfp-warning"><span class="lfp-warning-icon">⚠️</span><span id="lfp-warning-text">Some required plugins are missing.</span></div>

                <!-- Progress -->
                <div class="lfp-progress-card">
                    <div class="lfp-progress-header"><span class="lfp-progress-label" id="lfp-progress-label">Core Setup Progress</span><span class="lfp-progress-pct" id="lfp-progress-pct">0%</span></div>
                    <div class="lfp-progress-track"><div class="lfp-progress-fill" id="lfp-progress-fill"></div></div>
                </div>

                <!-- Two Columns: Left=Core+Optional, Right=Premium -->
                <div class="lfp-columns">
                    <!-- Left Column: Core + Optional -->
                    <div>
                        <h3 class="lfp-section-title">🔥 Core Plugins <span class="lfp-section-badge">Required</span></h3>
                        <div class="lfp-plugins-grid" id="lfp-core-plugins"><div class="lfp-loading">⏳ Loading...</div></div>
                        
                        <h3 class="lfp-section-title" style="margin-top:24px;">📦 Optional Plugins <span class="lfp-section-badge">Optional</span></h3>
                        <div class="lfp-plugins-grid" id="lfp-optional-plugins"><div class="lfp-loading">⏳ Loading...</div></div>
                        
                        <!-- Bulk Actions for Free Plugins -->
                        <div class="lfp-actions">
                            <button type="button" id="lf-start" class="lfp-btn lfp-btn-primary"><span>⬇</span> Install Selected</button>
                            <button type="button" id="lf-refresh" class="lfp-btn lfp-btn-ghost"><span>↻</span> Refresh</button>
                            <button type="button" id="lf-toggle" class="lfp-btn lfp-btn-ghost"><span>☐</span> Toggle All</button>
                        </div>
                    </div>

                    <!-- Right Column: Premium -->
                    <div>
                        <h3 class="lfp-section-title">💎 Premium Plugins <span class="lfp-section-badge">CDN</span></h3>
                        <p class="lfp-page-sub" id="lfp-premium-source" style="margin-bottom:8px;">Loading...</p>
                        <div class="lfp-plugins-grid" id="lfp-premium-plugins"><div class="lfp-loading">⏳ Loading...</div></div>
                        
                        <!-- Premium Actions -->
                        <div class="lfp-actions" style="margin-top:12px;">
                            <button type="button" id="lf-premium-install-all" class="lfp-btn lfp-btn-primary" disabled><span>⬇</span> Install Selected (0)</button>
                            <button type="button" id="lf-premium-refresh" class="lfp-btn lfp-btn-ghost"><span>↻</span> Refresh</button>
                        </div>
                    </div>
                </div>

                <!-- Notifications -->
                <div id="lfp-notification-area"></div>
            </div>
            <?php
        }
    }
    LanderFlow_Pro::get_instance();
}