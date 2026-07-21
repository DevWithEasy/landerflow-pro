<?php
/**
 * Plugin Name: LanderFlow Pro 1.0.2
 * Plugin URI: https://github.com/DevWithEasy/landerflow-pro
 * Description: Professional plugin installer - Install free & premium plugins from external source
 * Version: 1.0.2
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
        private $free_plugins_json_url = 'https://raw.githubusercontent.com/DevWithEasy/landerflow-pro/v1.0.1/free-plugins.json';
        private $premium_plugins_json_url = 'https://raw.githubusercontent.com/DevWithEasy/landerflow-pro/v1.0.1/premium-plugins.json';

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

        public function add_menu() { 
            add_menu_page('LanderFlow Pro', 'LanderFlow Pro', 'manage_options', 'landerflow-pro', [$this, 'render_page'], 'dashicons-admin-tools', 100); 
        }

        public function enqueue_assets($hook) {
            if ('toplevel_page_landerflow-pro' !== $hook) return;
            wp_enqueue_style('lf-inter-font', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap', [], null);
            wp_enqueue_style('lf-admin', LANDERFLOW_PRO_PLUGIN_URL . 'assets/css/admin.css', [], LANDERFLOW_PRO_VERSION . '.' . time());
            wp_enqueue_script('lf-admin', LANDERFLOW_PRO_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], LANDERFLOW_PRO_VERSION . '.' . time(), true);
            wp_localize_script('lf-admin', 'lfData', [
                'ajax' => admin_url('admin-ajax.php'), 
                'nonce' => wp_create_nonce('lf_nonce'), 
                'core' => $this->core_slugs,
                'coreCount' => count($this->core_slugs)
            ]);
        }

        public function auto_trigger($plugin) { 
            if (plugin_basename(__FILE__) === $plugin) update_option('lf_auto_trigger', true); 
        }

        private function add_status_to_plugins(&$plugins, $file_key = 'file') {
            if (!function_exists('get_plugins')) require_once ABSPATH . 'wp-admin/includes/plugin.php';
            foreach ($plugins as &$p) {
                $file = $p[$file_key] ?? '';
                $p['active'] = $file ? is_plugin_active($file) : false;
                $p['installed'] = $file ? $this->is_installed($file) : false;
            }
        }

        public function ajax_get_free_plugins() {
            check_ajax_referer('lf_nonce', 'nonce');
            $cache_key = 'lf_free_plugins_cache'; 
            $cached = get_transient($cache_key);
            if ($cached !== false) { 
                $this->add_status_to_plugins($cached, 'file');
                wp_send_json_success(['plugins' => $cached, 'source' => 'cache']); 
            }
            $response = wp_remote_get($this->free_plugins_json_url, ['timeout' => 30, 'sslverify' => false]);
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) { 
                $plugins = $this->get_fallback_free_plugins();
                $this->add_status_to_plugins($plugins, 'file');
                wp_send_json_success(['plugins' => $plugins, 'source' => 'fallback']); 
            }
            $plugins = json_decode(wp_remote_retrieve_body($response), true);
            if (!is_array($plugins)) { 
                $plugins = $this->get_fallback_free_plugins();
                $this->add_status_to_plugins($plugins, 'file');
                wp_send_json_success(['plugins' => $plugins, 'source' => 'fallback']); 
            }
            set_transient($cache_key, $plugins, HOUR_IN_SECONDS);
            $this->add_status_to_plugins($plugins, 'file');
            wp_send_json_success(['plugins' => $plugins, 'source' => 'live']);
        }

        public function ajax_get_premium_plugins() {
            check_ajax_referer('lf_nonce', 'nonce');
            $cache_key = 'lf_premium_plugins_cache'; 
            $cached = get_transient($cache_key);
            if ($cached !== false) { 
                $this->add_status_to_plugins($cached, 'plugin_file');
                wp_send_json_success(['plugins' => $cached, 'source' => 'cache']); 
            }
            $response = wp_remote_get($this->premium_plugins_json_url, ['timeout' => 30, 'sslverify' => false]);
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) { 
                $plugins = $this->get_fallback_premium_plugins();
                $this->add_status_to_plugins($plugins, 'plugin_file');
                wp_send_json_success(['plugins' => $plugins, 'source' => 'fallback']); 
            }
            $plugins = json_decode(wp_remote_retrieve_body($response), true);
            if (!is_array($plugins)) { 
                $plugins = $this->get_fallback_premium_plugins();
                $this->add_status_to_plugins($plugins, 'plugin_file');
                wp_send_json_success(['plugins' => $plugins, 'source' => 'fallback']); 
            }
            set_transient($cache_key, $plugins, HOUR_IN_SECONDS);
            $this->add_status_to_plugins($plugins, 'plugin_file');
            wp_send_json_success(['plugins' => $plugins, 'source' => 'live']);
        }

        public function ajax_install() {
            check_ajax_referer('lf_nonce', 'nonce');
            if (!current_user_can('install_plugins')) wp_send_json_error('Permission denied');
            $slug = sanitize_text_field($_POST['slug']); 
            $file = sanitize_text_field($_POST['file'] ?? '');
            if (!$file) wp_send_json_error('No plugin file'); 
            if ($this->is_installed($file)) wp_send_json_success();
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php'; 
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php'; 
            require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
            require_once ABSPATH . 'wp-admin/includes/file.php'; 
            WP_Filesystem();
            $api = plugins_api('plugin_information', ['slug' => $slug, 'fields' => ['sections' => false]]);
            if (is_wp_error($api)) wp_send_json_error($api->get_error_message());
            $skin = new WP_Ajax_Upgrader_Skin(); 
            $upgrader = new Plugin_Upgrader($skin);
            $result = $upgrader->install($api->download_link);
            if (is_wp_error($result)) wp_send_json_error($result->get_error_message()); 
            wp_send_json_success();
        }

        public function ajax_activate() {
            check_ajax_referer('lf_nonce', 'nonce');
            if (!current_user_can('activate_plugins')) wp_send_json_error('Permission denied');
            $file = sanitize_text_field($_POST['file'] ?? ''); 
            if (!$file) wp_send_json_error('No plugin file');
            if (is_plugin_active($file)) wp_send_json_success();
            if (!function_exists('activate_plugin')) require_once ABSPATH . 'wp-admin/includes/plugin.php';
            $result = activate_plugin($file); 
            if (is_wp_error($result)) wp_send_json_error($result->get_error_message()); 
            wp_send_json_success();
        }

        public function ajax_get_all_status() {
            check_ajax_referer('lf_nonce', 'nonce');
            if (!function_exists('get_plugins')) require_once ABSPATH . 'wp-admin/includes/plugin.php';
            $free_plugins = get_transient('lf_free_plugins_cache') ?: $this->get_fallback_free_plugins();
            $premium_plugins = get_transient('lf_premium_plugins_cache') ?: $this->get_fallback_premium_plugins();
            $status = ['free' => [], 'premium' => []];
            foreach ($free_plugins as $p) { 
                $file = $p['file'] ?? ''; 
                $status['free'][$p['id'] ?? $p['slug']] = [
                    'name' => $p['name'], 
                    'installed' => $this->is_installed($file), 
                    'active' => is_plugin_active($file),
                    'required' => $p['required'] ?? false
                ]; 
            }
            foreach ($premium_plugins as $p) { 
                $file = $p['plugin_file'] ?? ''; 
                $status['premium'][$p['id']] = [
                    'name' => $p['name'], 
                    'installed' => $this->is_installed($file), 
                    'active' => is_plugin_active($file),
                    'required' => $p['required'] ?? false
                ]; 
            }
            wp_send_json_success($status);
        }

        public function ajax_premium_install() {
            check_ajax_referer('lf_nonce', 'nonce');
            if (!current_user_can('install_plugins')) wp_send_json_error('Permission denied');
            $plugin_id = sanitize_text_field($_POST['plugin_id']); 
            $zip_url = esc_url_raw($_POST['zip_url'] ?? '');
            $plugin_file = sanitize_text_field($_POST['plugin_file'] ?? ''); 
            $plugin_name = sanitize_text_field($_POST['plugin_name'] ?? 'Premium Plugin');
            if (empty($zip_url)) wp_send_json_error('No download URL'); 
            if ($this->is_installed($plugin_file)) wp_send_json_success(['message' => 'Already installed']);
            $zip_file = $this->download_from_cdn($zip_url, $plugin_id); 
            if (is_wp_error($zip_file)) wp_send_json_error($zip_file->get_error_message());
            $result = LanderFlow_Plugin_Installer::install_from_zip($zip_file, $plugin_name); 
            @unlink($zip_file);
            if (is_wp_error($result)) wp_send_json_error($result->get_error_message()); 
            wp_send_json_success(['message' => $plugin_name . ' installed!']);
        }

        private function download_from_cdn($zip_url, $id) {
            $temp_dir = LANDERFLOW_PRO_PLUGIN_DIR . 'temp/'; 
            if (!file_exists($temp_dir)) wp_mkdir_p($temp_dir);
            $temp_file = $temp_dir . sanitize_file_name($id . '-' . time() . '.zip');
            $response = wp_remote_get($zip_url, [
                'timeout' => 600, 
                'sslverify' => false, 
                'stream' => true, 
                'filename' => $temp_file
            ]);
            if (is_wp_error($response)) { @unlink($temp_file); return $response; }
            if (wp_remote_retrieve_response_code($response) !== 200) { 
                @unlink($temp_file); 
                return new WP_Error('http_error', 'Download failed'); 
            }
            if (!file_exists($temp_file) || filesize($temp_file) < 500) { 
                @unlink($temp_file); 
                return new WP_Error('empty_file', 'Corrupted'); 
            }
            return $temp_file;
        }

        private function get_fallback_free_plugins() { return [
            ['id' => 'woocommerce', 'slug' => 'woocommerce', 'name' => 'WooCommerce', 'file' => 'woocommerce/woocommerce.php', 'icon' => '🛒', 'desc' => 'Powerful eCommerce platform for WordPress', 'required' => true],
            ['id' => 'elementor', 'slug' => 'elementor', 'name' => 'Elementor', 'file' => 'elementor/elementor.php', 'icon' => '⚡', 'desc' => 'Leading visual drag & drop page builder', 'required' => true],
            ['id' => 'cartflows', 'slug' => 'cartflows', 'name' => 'CartFlows', 'file' => 'cartflows/cartflows.php', 'icon' => '🚀', 'desc' => 'Advanced sales funnel & checkout builder', 'required' => true],
            ['id' => 'litespeed-cache', 'slug' => 'litespeed-cache', 'name' => 'LiteSpeed Cache', 'file' => 'litespeed-cache/litespeed-cache.php', 'icon' => '⚡', 'desc' => 'High-performance page caching & optimization', 'required' => false],
            ['id' => 'svg-support', 'slug' => 'svg-support', 'name' => 'SVG Support', 'file' => 'svg-support/svg-support.php', 'icon' => '🎨', 'desc' => 'Securely upload and use SVG files', 'required' => false],
            ['id' => 'code-snippets', 'slug' => 'code-snippets', 'name' => 'Code Snippets', 'file' => 'code-snippets/code-snippets.php', 'icon' => '💻', 'desc' => 'Add custom code snippets easily', 'required' => false],
        ]; }

        private function get_fallback_premium_plugins() { return [
            ['id' => 'elementor-pro', 'name' => 'Elementor Pro', 'desc' => 'Advanced page builder with premium widgets', 'icon' => '🎨', 'zip_url' => 'https://github.com/DevWithEasy/landerflow-pro/releases/download/preium_plugin/elementor-pro.zip', 'plugin_file' => 'elementor-pro/elementor-pro.php', 'category' => 'Page Builder', 'version' => '3.18.0', 'required' => true],
            ['id' => 'cartflows-pro', 'name' => 'CartFlows Pro', 'desc' => 'Premium sales funnel builder', 'icon' => '🛒', 'zip_url' => 'https://github.com/DevWithEasy/landerflow-pro/releases/download/preium_plugin/cartflows-pro.zip', 'plugin_file' => 'cartflows-pro/cartflows-pro.php', 'category' => 'Funnel', 'version' => '2.0.0', 'required' => true],
            ['id' => 'pro-elements', 'name' => 'Pro Elements', 'desc' => 'Free Elementor Pro alternative', 'icon' => '⚡', 'zip_url' => 'https://github.com/DevWithEasy/landerflow-pro/releases/download/preium_plugin/pro-elements.zip', 'plugin_file' => 'pro-elements/pro-elements.php', 'category' => 'Page Builder', 'version' => 'latest', 'required' => false],
            ['id' => 'pixelyoursite-pro', 'name' => 'PixelYourSite Pro', 'desc' => 'Advanced Facebook Pixel & tracking', 'icon' => '📊', 'zip_url' => 'https://github.com/DevWithEasy/landerflow-pro/releases/download/preium_plugin/pixelyoursite-pro.zip', 'plugin_file' => 'pixelyoursite-pro/pixelyoursite-pro.php', 'category' => 'Marketing', 'version' => 'latest', 'required' => false],
        ]; }

        private function is_installed($file) { 
            if (!function_exists('get_plugins')) require_once ABSPATH . 'wp-admin/includes/plugin.php'; 
            return isset(get_plugins()[$file]); 
        }

        public function render_page() {
            $auto = get_option('lf_auto_trigger', false); 
            if ($auto) delete_option('lf_auto_trigger'); 
            $core_count = count($this->core_slugs);
            ?>
            <div class="lfp-wrap <?php echo $auto ? 'auto-install' : ''; ?>" id="lfp-app">
                <!-- Top Bar -->
                <div class="lfp-top-bar">
                    <div class="lfp-header">
                        <div class="lfp-brand">
                            <div class="lfp-logo">
                                <svg width="28" height="28" viewBox="0 0 32 32" fill="none">
                                    <rect width="32" height="32" rx="8" fill="url(#lfp-grad)"/>
                                    <path d="M8 16L14 22L24 12" stroke="#0D1117" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                                    <defs><linearGradient id="lfp-grad" x1="0" y1="0" x2="32" y2="32"><stop stop-color="#FACC15"/><stop offset="1" stop-color="#E6A817"/></linearGradient></defs>
                                </svg>
                            </div>
                            <div>
                                <h1 class="lfp-title">LanderFlow Pro</h1>
                                <p class="lfp-subtitle">Plugin Management Suite</p>
                            </div>
                        </div>
                        <div class="lfp-meta">
                            <div class="lfp-badge">v<?php echo LANDERFLOW_PRO_VERSION; ?></div>
                        </div>
                    </div>
                    <div class="lfp-dash-stats">
                        <div class="lfp-stat-card">
                            <span class="lfp-stat-card-icon">📦</span>
                            <div class="lfp-stat-card-body">
                                <span class="lfp-stat-card-value" id="lfp-total-plugins"><?php echo $core_count + 4; ?></span>
                                <span class="lfp-stat-card-label">Plugins</span>
                            </div>
                        </div>
                        <div class="lfp-stat-card">
                            <span class="lfp-stat-card-icon">✅</span>
                            <div class="lfp-stat-card-body">
                                <span class="lfp-stat-card-value"><span id="active-count">0</span></span>
                                <span class="lfp-stat-card-label">Active</span>
                            </div>
                        </div>
                        <div class="lfp-stat-card">
                            <span class="lfp-stat-card-icon">⚠️</span>
                            <div class="lfp-stat-card-body">
                                <span class="lfp-stat-card-value"><?php echo $core_count; ?></span>
                                <span class="lfp-stat-card-label">Required</span>
                            </div>
                        </div>
                        <div class="lfp-stat-card">
                            <span class="lfp-stat-card-icon">💎</span>
                            <div class="lfp-stat-card-body">
                                <span class="lfp-stat-card-value">4</span>
                                <span class="lfp-stat-card-label">Premium</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Core Check Alert -->
                <div id="lfp-core-alert" class="lfp-alert hidden">
                    <div class="lfp-alert-icon">⚠️</div>
                    <div class="lfp-alert-content">
                        <strong id="lfp-alert-title">Core Plugins Missing</strong>
                        <p id="lfp-alert-text">Some required plugins are not active.</p>
                    </div>
                    <button id="lfp-core-fix-btn" class="lfp-alert-btn">Fix Now</button>
                </div>

                <!-- Progress Bar -->
                <div class="lfp-progress-container">
                    <div class="lfp-progress-header">
                        <span>Setup Progress</span>
                        <span class="lfp-progress-percent" id="progress-percent">0%</span>
                    </div>
                    <div class="lfp-progress-bar">
                        <div class="lfp-progress-fill" id="progress-fill"></div>
                    </div>
                </div>

                <!-- Sections -->
                <div class="lfp-grid">
                    <div class="lfp-col">
                        <div class="lfp-section">
                            <div class="lfp-section-header">
                                <h2>🔥 Core Plugins</h2>
                                <span class="lfp-section-badge required">Required</span>
                            </div>
                            <div id="core-plugins" class="lfp-plugin-list">
                                <div class="lfp-skeleton">Loading core plugins...</div>
                            </div>
                        </div>

                        <div class="lfp-section">
                            <div class="lfp-section-header">
                                <h2>📦 Optional Plugins</h2>
                                <span class="lfp-section-badge">Optional</span>
                            </div>
                            <div id="optional-plugins" class="lfp-plugin-list">
                                <div class="lfp-skeleton">Loading optional plugins...</div>
                            </div>
                        </div>

                        <div class="lfp-actions-bar">
                            <button id="install-selected-free" class="lfp-btn primary" disabled>
                                <span>⬇</span> Install Selected (<span id="free-count">0</span>)
                            </button>
                            <button id="refresh-free" class="lfp-btn ghost">
                                <span>↻</span>
                            </button>
                        </div>
                    </div>

                    <div class="lfp-col">
                        <div class="lfp-section premium">
                            <div class="lfp-section-header">
                                <h2>💎 Premium Plugins</h2>
                                <span class="lfp-section-badge premium">CDN</span>
                            </div>
                            <p id="premium-source" class="lfp-source-info">Loading premium plugins...</p>
                            <div id="premium-plugins" class="lfp-plugin-list">
                                <div class="lfp-skeleton">Loading premium plugins...</div>
                            </div>
                        </div>

                        <div class="lfp-actions-bar">
                            <button id="install-selected-premium" class="lfp-btn primary" disabled>
                                <span>⬇</span> Install Selected (<span id="premium-count">0</span>)
                            </button>
                            <button id="refresh-premium" class="lfp-btn ghost">
                                <span>↻</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Toast Container -->
                <div id="lfp-toasts"></div>
            </div>
            <?php
        }
    }
    LanderFlow_Pro::get_instance();
}