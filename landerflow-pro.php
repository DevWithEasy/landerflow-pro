<?php
/**
 * Plugin Name: LanderFlow Pro 1.0.2
 * Plugin URI: https://github.com/DevWithEasy/landerflow-pro
 * Description: Professional plugin installer - Install verified free plugins from WordPress.org
 * Version: 1.0.2
 * Author: Robiul Awal
 * Author URI: https://github.com/DevWithEasy
 * License: GPL v2 or later
 * Text Domain: landerflow-pro
 */
if (!defined('ABSPATH')) exit;

define('LANDERFLOW_PRO_VERSION', '1.0.2');
define('LANDERFLOW_PRO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LANDERFLOW_PRO_PLUGIN_URL', plugin_dir_url(__FILE__));
if (!class_exists('LanderFlow_Pro')) {
    class LanderFlow_Pro {
        private static $instance;
        private $core_slugs = ['woocommerce', 'elementor', 'cartflows'];
        private $free_plugins_json_url = 'https://raw.githubusercontent.com/DevWithEasy/landerflow-pro/v1.0.1/free-plugins.json';

        public static function get_instance() { return self::$instance ?: self::$instance = new self(); }
        
        private function __construct() {
            add_action('admin_menu', [$this, 'add_menu']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
            add_action('wp_ajax_lf_install', [$this, 'ajax_install']);
            add_action('wp_ajax_lf_activate', [$this, 'ajax_activate']);
            add_action('wp_ajax_lf_get_all_status', [$this, 'ajax_get_all_status']);
            add_action('wp_ajax_lf_get_free_plugins', [$this, 'ajax_get_free_plugins']);
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

        private function get_plugins_list() {
            if (!function_exists('get_plugins')) require_once ABSPATH . 'wp-admin/includes/plugin.php';
            return get_plugins();
        }

        private function get_allowed_plugins() {
            static $allowed = null;
            if (null !== $allowed) return $allowed;
            $allowed = $this->get_fallback_free_plugins();
            $json_path = LANDERFLOW_PRO_PLUGIN_DIR . 'free-plugins.json';
            if (file_exists($json_path)) {
                $decoded = json_decode((string) file_get_contents($json_path), true);
                if (is_array($decoded)) {
                    $allowed = array_merge($allowed, $decoded);
                }
            }
            $filtered = [];
            foreach ($allowed as $p) {
                if (!is_array($p)) continue;
                $slug = isset($p['slug']) && is_string($p['slug']) ? $p['slug'] : '';
                $file = isset($p['file']) && is_string($p['file']) ? $p['file'] : '';
                if ('' === $slug || '' === $file) continue;
                $key = $slug . '|' . $file;
                if (isset($filtered[$key])) continue;
                $filtered[$key] = [
                    'id'       => isset($p['id']) && is_string($p['id']) && '' !== $p['id'] ? $p['id'] : $slug,
                    'slug'     => $slug,
                    'file'     => $file,
                    'name'     => isset($p['name']) && is_string($p['name']) ? $p['name'] : $slug,
                    'desc'     => isset($p['desc']) && is_string($p['desc']) ? $p['desc'] : '',
                    'icon'     => isset($p['icon']) && is_string($p['icon']) ? $p['icon'] : '📦',
                    'required' => !empty($p['required']),
                ];
            }
            $allowed = array_values($filtered);
            return $allowed;
        }

        private function get_allowed_slugs() {
            return array_column($this->get_allowed_plugins(), 'slug');
        }

        private function get_allowed_files() {
            return array_column($this->get_allowed_plugins(), 'file');
        }

        private function sanitize_catalog($catalog) {
            if (!is_array($catalog)) return [];
            $by_file = [];
            foreach ($catalog as $p) {
                if (!is_array($p)) continue;
                $file = isset($p['file']) && is_string($p['file']) ? $p['file'] : '';
                if ('' !== $file) $by_file[$file] = $p;
            }
            $result = [];
            foreach ($this->get_allowed_plugins() as $entry) {
                $meta = isset($by_file[$entry['file']]) ? $by_file[$entry['file']] : [];
                $result[] = [
                    'id'       => $entry['id'],
                    'slug'     => $entry['slug'],
                    'file'     => $entry['file'],
                    'name'     => isset($meta['name']) && is_string($meta['name']) && '' !== $meta['name'] ? $meta['name'] : $entry['name'],
                    'desc'     => isset($meta['desc']) && is_string($meta['desc']) ? $meta['desc'] : $entry['desc'],
                    'icon'     => isset($meta['icon']) && is_string($meta['icon']) ? $meta['icon'] : $entry['icon'],
                    'required' => $entry['required'] || !empty($meta['required']),
                ];
            }
            return $result;
        }

        private function add_status_to_plugins(&$plugins, $file_key = 'file') {
            if (!is_array($plugins)) return;
            $plugins_list = $this->get_plugins_list();
            foreach ($plugins as &$p) {
                if (!is_array($p)) continue;
                $file = isset($p[$file_key]) && is_string($p[$file_key]) ? $p[$file_key] : '';
                $p['active'] = $file ? is_plugin_active($file) : false;
                $p['installed'] = $file ? isset($plugins_list[$file]) : false;
            }
            unset($p);
        }

        public function ajax_get_free_plugins() {
            check_ajax_referer('lf_nonce', 'nonce');
            if (!current_user_can('install_plugins')) wp_send_json_error('Permission denied');
            $cache_key = 'lf_free_plugins_cache';
            $plugins = get_transient($cache_key);
            if (is_array($plugins) && $plugins) {
                $plugins = $this->sanitize_catalog($plugins);
                $source = 'cache';
            } else {
                $plugins = null;
                $response = wp_remote_get($this->free_plugins_json_url, ['timeout' => 30, 'sslverify' => true]);
                if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                    $decoded = json_decode(wp_remote_retrieve_body($response), true);
                    if (is_array($decoded)) $plugins = $decoded;
                }
                $plugins = $this->sanitize_catalog($plugins);
                $source = 'live';
                if (!$plugins) {
                    $plugins = $this->sanitize_catalog([]);
                    $source = 'fallback';
                }
                set_transient($cache_key, $plugins, HOUR_IN_SECONDS);
            }
            $this->add_status_to_plugins($plugins, 'file');
            wp_send_json_success(['plugins' => $plugins, 'source' => $source]);
        }

        public function ajax_install() {
            check_ajax_referer('lf_nonce', 'nonce');
            if (!current_user_can('install_plugins')) wp_send_json_error('Permission denied');
            $slug = isset($_POST['slug']) ? sanitize_text_field(wp_unslash($_POST['slug'])) : '';
            $file = isset($_POST['file']) ? sanitize_text_field(wp_unslash($_POST['file'])) : '';
            if (!$file) wp_send_json_error('No plugin file'); 
            if (!in_array($slug, $this->get_allowed_slugs(), true)) wp_send_json_error('Plugin is not in the allowed list');
            if (!in_array($file, $this->get_allowed_files(), true)) wp_send_json_error('Plugin file is not in the allowed list');
            if ($this->is_installed($file)) wp_send_json_success();
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php'; 
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php'; 
            require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
            require_once ABSPATH . 'wp-admin/includes/file.php'; 
            WP_Filesystem();
            $api = plugins_api('plugin_information', ['slug' => $slug, 'fields' => ['sections' => false]]);
            if (is_wp_error($api)) {
                error_log('LanderFlow: plugins_api failed for "' . $slug . '": ' . $api->get_error_message());
                wp_send_json_error('Plugin information could not be retrieved from WordPress.org.');
            }
            $skin = new WP_Ajax_Upgrader_Skin(); 
            $upgrader = new Plugin_Upgrader($skin);
            $result = $upgrader->install($api->download_link);
            if (is_wp_error($result)) {
                error_log('LanderFlow: install failed for "' . $slug . '": ' . $result->get_error_message());
                wp_send_json_error('Plugin installation failed. Please try again.');
            } 
            wp_send_json_success();
        }

        public function ajax_activate() {
            check_ajax_referer('lf_nonce', 'nonce');
            if (!current_user_can('activate_plugins')) wp_send_json_error('Permission denied');
            $file = isset($_POST['file']) ? sanitize_text_field(wp_unslash($_POST['file'])) : '';
            if (!$file) wp_send_json_error('No plugin file');
            if (!in_array($file, $this->get_allowed_files(), true)) wp_send_json_error('Plugin file is not in the allowed list');
            if (is_plugin_active($file)) wp_send_json_success();
            if (!function_exists('activate_plugin')) require_once ABSPATH . 'wp-admin/includes/plugin.php';
            $result = activate_plugin($file); 
            if (is_wp_error($result)) wp_send_json_error($result->get_error_message()); 
            wp_send_json_success();
        }

        public function ajax_get_all_status() {
            check_ajax_referer('lf_nonce', 'nonce');
            if (!current_user_can('install_plugins')) wp_send_json_error('Permission denied');
            $free_plugins = get_transient('lf_free_plugins_cache');
            if (!is_array($free_plugins)) $free_plugins = $this->get_allowed_plugins();
            $plugins_list = $this->get_plugins_list();
            $status = ['free' => []];
            foreach ($free_plugins as $p) {
                if (!is_array($p)) continue;
                $file = isset($p['file']) && is_string($p['file']) ? $p['file'] : '';
                $key = isset($p['id']) && is_string($p['id']) && '' !== $p['id'] ? $p['id'] : (isset($p['slug']) ? (string) $p['slug'] : '');
                if (!$key) continue;
                $status['free'][$key] = [
                    'name' => isset($p['name']) && is_string($p['name']) ? $p['name'] : $key,
                    'installed' => $file ? isset($plugins_list[$file]) : false,
                    'active' => $file ? is_plugin_active($file) : false,
                    'required' => !empty($p['required'])
                ];
            }
            wp_send_json_success($status);
        }

        private function get_fallback_free_plugins() { return [
            ['id' => 'woocommerce', 'slug' => 'woocommerce', 'name' => 'WooCommerce', 'file' => 'woocommerce/woocommerce.php', 'icon' => '🛒', 'desc' => 'Powerful eCommerce platform for WordPress', 'required' => true],
            ['id' => 'elementor', 'slug' => 'elementor', 'name' => 'Elementor', 'file' => 'elementor/elementor.php', 'icon' => '⚡', 'desc' => 'Leading visual drag & drop page builder', 'required' => true],
            ['id' => 'cartflows', 'slug' => 'cartflows', 'name' => 'CartFlows', 'file' => 'cartflows/cartflows.php', 'icon' => '🚀', 'desc' => 'Advanced sales funnel & checkout builder', 'required' => true],
            ['id' => 'litespeed-cache', 'slug' => 'litespeed-cache', 'name' => 'LiteSpeed Cache', 'file' => 'litespeed-cache/litespeed-cache.php', 'icon' => '⚡', 'desc' => 'High-performance page caching & optimization', 'required' => false],
            ['id' => 'svg-support', 'slug' => 'svg-support', 'name' => 'SVG Support', 'file' => 'svg-support/svg-support.php', 'icon' => '🎨', 'desc' => 'Securely upload and use SVG files', 'required' => false],
            ['id' => 'code-snippets', 'slug' => 'code-snippets', 'name' => 'Code Snippets', 'file' => 'code-snippets/code-snippets.php', 'icon' => '💻', 'desc' => 'Add custom code snippets easily', 'required' => false],
        ]; }

        private function is_installed($file) { 
            if (!is_string($file) || '' === $file) return false; 
            return isset($this->get_plugins_list()[$file]); 
        }

        public function render_page() {
            $auto = get_option('lf_auto_trigger', false); 
            if ($auto) delete_option('lf_auto_trigger'); 
            $core_count = count($this->core_slugs);
            $total_count = count($this->get_allowed_plugins());
            ?>
            <div class="lfp-wrap <?php echo esc_attr($auto ? 'auto-install' : ''); ?>" id="lfp-app">
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
                            <div class="lfp-badge">v<?php echo esc_html(LANDERFLOW_PRO_VERSION); ?></div>
                        </div>
                    </div>
                    <div class="lfp-dash-stats">
                        <div class="lfp-stat-card">
                            <span class="lfp-stat-card-icon">📦</span>
                            <div class="lfp-stat-card-body">
                                <span class="lfp-stat-card-value" id="lfp-total-plugins"><?php echo esc_html($total_count); ?></span>
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
                                <span class="lfp-stat-card-value"><?php echo esc_html($core_count); ?></span>
                                <span class="lfp-stat-card-label">Required</span>
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
                </div>

                <!-- Toast Container -->
                <div id="lfp-toasts"></div>
            </div>
            <?php
        }
    }
    LanderFlow_Pro::get_instance();
}