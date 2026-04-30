<?php
/**
 * Plugin Name: LanderFlow Pro 1.0.1
 * Plugin URI: https://github.com/DevWithEasy/landerflow-pro
 * Description: Professional landing page setup utility - Install free & premium plugins, CartFlows templates from external source
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
    class LanderFlow_Pro
    {
        private static $instance;
        
        // শুধু core slugs - বাকি সব JSON থেকে আসবে
        private $core_slugs = ['woocommerce', 'elementor', 'cartflows'];

        /**
         * 📡 EXTERNAL JSON URLs
         */
        private $free_plugins_json_url = 'https://raw.githubusercontent.com/DevWithEasy/landerflow-pro/v' . LANDERFLOW_PRO_VERSION . '/free-plugins.json';
        private $premium_plugins_json_url = 'https://raw.githubusercontent.com/DevWithEasy/landerflow-pro/v' . LANDERFLOW_PRO_VERSION . '/premium-plugins.json';
        private $templates_json_url = 'https://raw.githubusercontent.com/DevWithEasy/landerflow-pro/v' . LANDERFLOW_PRO_VERSION . '/templates.json';

        public static function get_instance() { return self::$instance ?: self::$instance = new self(); }

        private function __construct()
        {
            add_action('admin_menu', [$this, 'add_menu']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
            add_action('wp_ajax_lf_install', [$this, 'ajax_install']);
            add_action('wp_ajax_lf_activate', [$this, 'ajax_activate']);
            add_action('wp_ajax_lf_status', [$this, 'ajax_status']);
            add_action('wp_ajax_lf_premium_install', [$this, 'ajax_premium_install']);
            add_action('wp_ajax_lf_get_all_status', [$this, 'ajax_get_all_status']);
            add_action('wp_ajax_lf_premium_bulk_install', [$this, 'ajax_premium_bulk_install']);
            add_action('wp_ajax_lf_download_json', [$this, 'ajax_download_json']);
            add_action('wp_ajax_lf_get_templates', [$this, 'ajax_get_templates']);
            add_action('wp_ajax_lf_get_free_plugins', [$this, 'ajax_get_free_plugins']);
            add_action('wp_ajax_lf_get_premium_plugins', [$this, 'ajax_get_premium_plugins']);
            add_action('activated_plugin', [$this, 'auto_trigger']);
        }

        public function add_menu() { add_menu_page('LanderFlow Pro', 'LanderFlow Pro', 'manage_options', 'landerflow-pro', [$this, 'render_page'], 'dashicons-admin-tools', 100); }

        public function enqueue_assets($hook)
        {
            if ('toplevel_page_landerflow-pro' !== $hook) return;
            wp_enqueue_style('lf-google-fonts', 'https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Syne:wght@700;800&display=swap', [], null);
            wp_enqueue_style('lf-admin', LANDERFLOW_PRO_PLUGIN_URL . 'assets/css/admin.css', [], LANDERFLOW_PRO_VERSION);
            wp_enqueue_script('lf-admin', LANDERFLOW_PRO_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], LANDERFLOW_PRO_VERSION, true);
            wp_localize_script('lf-admin', 'lfData', [
                'ajax' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('lf_nonce'),
                'core' => $this->core_slugs,
                'free_plugins_json_url' => $this->free_plugins_json_url,
                'premium_plugins_json_url' => $this->premium_plugins_json_url,
                'templates_json_url' => $this->templates_json_url,
                'cartflows_url' => admin_url('admin.php?page=cartflows'),
                'cartflows_import_url' => admin_url('admin.php?page=cartflows&tab=import'),
                'texts' => [
                    'active' => __('Active', 'landerflow-pro'), 'inactive' => __('Inactive', 'landerflow-pro'),
                    'not_installed' => __('Not Installed', 'landerflow-pro'), 'ready_install' => __('Ready to Install', 'landerflow-pro'),
                    'installing' => __('Installing...', 'landerflow-pro'), 'activating' => __('Activating...', 'landerflow-pro'),
                    'downloading' => __('Downloading...', 'landerflow-pro'), 'install_selected' => __('Install Selected', 'landerflow-pro'),
                    'install_all_premium' => __('Install All Premium', 'landerflow-pro'), 'refresh' => __('Refresh Status', 'landerflow-pro'),
                    'toggle' => __('Toggle All', 'landerflow-pro'), 'core_setup_complete' => __('Core Setup Complete', 'landerflow-pro'),
                    'confirm_bulk' => __('Process selected plugins?', 'landerflow-pro'),
                    'confirm_premium_bulk' => __('Install all premium plugins from CDN?', 'landerflow-pro'),
                    'retry' => __('Retry', 'landerflow-pro'), 'active_btn' => __('✓ Active', 'landerflow-pro'),
                    'activate_btn' => __('Activate', 'landerflow-pro'), 'install_btn' => __('Install', 'landerflow-pro'),
                    'download_json' => __('Download JSON', 'landerflow-pro'), 'preview' => __('Preview', 'landerflow-pro'),
                    'loading_templates' => __('Loading...', 'landerflow-pro'),
                ]
            ]);
        }

        public function auto_trigger($plugin) { if (plugin_basename(__FILE__) === $plugin) update_option('lf_auto_trigger', true); }

        // ============ AJAX: GET FREE PLUGINS FROM JSON ============
        public function ajax_get_free_plugins()
        {
            check_ajax_referer('lf_nonce', 'nonce');
            $cache_key = 'lf_free_plugins_cache';
            $cached = get_transient($cache_key);
            if ($cached !== false) { wp_send_json_success(['plugins' => $cached, 'source' => 'cache']); }

            $response = wp_remote_get($this->free_plugins_json_url, ['timeout' => 30, 'sslverify' => false]);
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                $fallback = $this->get_fallback_free_plugins();
                wp_send_json_success(['plugins' => $fallback, 'source' => 'fallback']);
            }

            $plugins = json_decode(wp_remote_retrieve_body($response), true);
            if (!is_array($plugins)) {
                $fallback = $this->get_fallback_free_plugins();
                wp_send_json_success(['plugins' => $fallback, 'source' => 'fallback']);
            }

            set_transient($cache_key, $plugins, HOUR_IN_SECONDS);
            wp_send_json_success(['plugins' => $plugins, 'source' => 'live']);
        }

        // ============ AJAX: GET PREMIUM PLUGINS FROM JSON ============
        public function ajax_get_premium_plugins()
        {
            check_ajax_referer('lf_nonce', 'nonce');
            $cache_key = 'lf_premium_plugins_cache';
            $cached = get_transient($cache_key);
            if ($cached !== false) { wp_send_json_success(['plugins' => $cached, 'source' => 'cache']); }

            $response = wp_remote_get($this->premium_plugins_json_url, ['timeout' => 30, 'sslverify' => false]);
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                $fallback = $this->get_fallback_premium_plugins();
                wp_send_json_success(['plugins' => $fallback, 'source' => 'fallback']);
            }

            $plugins = json_decode(wp_remote_retrieve_body($response), true);
            if (!is_array($plugins)) {
                $fallback = $this->get_fallback_premium_plugins();
                wp_send_json_success(['plugins' => $fallback, 'source' => 'fallback']);
            }

            set_transient($cache_key, $plugins, HOUR_IN_SECONDS);
            wp_send_json_success(['plugins' => $plugins, 'source' => 'live']);
        }

        // ============ AJAX: INSTALL FREE PLUGIN ============
        public function ajax_install()
        {
            check_ajax_referer('lf_nonce', 'nonce');
            if (!current_user_can('install_plugins')) wp_send_json_error('Permission denied');
            $slug = sanitize_text_field($_POST['slug']);
            $file = sanitize_text_field($_POST['file'] ?? '');
            if (!$file) wp_send_json_error('No plugin file specified');
            if ($this->is_installed($file)) wp_send_json_success();
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
            require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $api = plugins_api('plugin_information', ['slug' => $slug, 'fields' => ['sections' => false]]);
            if (is_wp_error($api)) wp_send_json_error($api->get_error_message());
            WP_Filesystem();
            $skin = new WP_Ajax_Upgrader_Skin();
            $upgrader = new Plugin_Upgrader($skin);
            $result = $upgrader->install($api->download_link);
            if (is_wp_error($result)) wp_send_json_error($result->get_error_message());
            wp_send_json_success();
        }

        // ============ AJAX: ACTIVATE PLUGIN ============
        public function ajax_activate()
        {
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

        // ============ AJAX: FREE PLUGIN STATUS ============
        public function ajax_status()
        {
            check_ajax_referer('lf_nonce', 'nonce');
            if (!function_exists('get_plugins')) require_once ABSPATH . 'wp-admin/includes/plugin.php';
            // Get from cache or fallback
            $plugins = get_transient('lf_free_plugins_cache') ?: $this->get_fallback_free_plugins();
            $status = [];
            foreach ($plugins as $p) {
                $file = $p['file'] ?? '';
                $status[$p['id'] ?? $p['slug']] = ['name' => $p['name'], 'installed' => $this->is_installed($file), 'active' => is_plugin_active($file)];
            }
            wp_send_json_success($status);
        }

        // ============ AJAX: ALL STATUS ============
        public function ajax_get_all_status()
        {
            check_ajax_referer('lf_nonce', 'nonce');
            if (!function_exists('get_plugins')) require_once ABSPATH . 'wp-admin/includes/plugin.php';
            $free_plugins = get_transient('lf_free_plugins_cache') ?: $this->get_fallback_free_plugins();
            $premium_plugins = get_transient('lf_premium_plugins_cache') ?: $this->get_fallback_premium_plugins();
            
            $status = ['free' => [], 'premium' => []];
            foreach ($free_plugins as $p) {
                $file = $p['file'] ?? '';
                $status['free'][$p['id'] ?? $p['slug']] = ['name' => $p['name'], 'installed' => $this->is_installed($file), 'active' => is_plugin_active($file)];
            }
            foreach ($premium_plugins as $p) {
                $file = $p['plugin_file'] ?? '';
                $status['premium'][$p['id']] = ['name' => $p['name'], 'installed' => $this->is_installed($file), 'active' => is_plugin_active($file), 'zip_exists' => !empty($p['zip_url']), 'zip_size' => 'CDN'];
            }
            wp_send_json_success($status);
        }

        // ============ PREMIUM PLUGIN - CDN INSTALL ============
        public function ajax_premium_install()
        {
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

        public function ajax_premium_bulk_install()
        {
            check_ajax_referer('lf_nonce', 'nonce');
            if (!current_user_can('install_plugins')) wp_send_json_error('Permission denied');
            $premium_plugins = get_transient('lf_premium_plugins_cache') ?: $this->get_fallback_premium_plugins();
            $results = [];
            foreach ($premium_plugins as $p) {
                $file = $p['plugin_file'] ?? '';
                if ($this->is_installed($file)) { $results[$p['id']] = ['message' => 'Already installed']; continue; }
                $zip_file = $this->download_from_cdn($p['zip_url'], $p['id']);
                if (is_wp_error($zip_file)) { $results[$p['id']] = ['error' => $zip_file->get_error_message()]; continue; }
                $result = LanderFlow_Plugin_Installer::install_from_zip($zip_file, $p['name']);
                @unlink($zip_file);
                $results[$p['id']] = is_wp_error($result) ? ['error' => $result->get_error_message()] : ['success' => true];
            }
            wp_send_json_success($results);
        }

        private function download_from_cdn($zip_url, $id)
        {
            $temp_dir = LANDERFLOW_PRO_PLUGIN_DIR . 'temp/';
            if (!file_exists($temp_dir)) wp_mkdir_p($temp_dir);
            $temp_file = $temp_dir . sanitize_file_name($id . '-' . time() . '.zip');
            $response = wp_remote_get($zip_url, ['timeout' => 600, 'sslverify' => false, 'stream' => true, 'filename' => $temp_file]);
            if (is_wp_error($response)) { @unlink($temp_file); return $response; }
            if (wp_remote_retrieve_response_code($response) !== 200) { @unlink($temp_file); return new WP_Error('http_error', 'Download failed'); }
            if (!file_exists($temp_file) || filesize($temp_file) < 500) { @unlink($temp_file); return new WP_Error('empty_file', 'Corrupted'); }
            return $temp_file;
        }

        // ============ DOWNLOAD FUNNEL JSON ============
        public function ajax_download_json()
        {
            check_ajax_referer('lf_nonce', 'nonce');
            if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
            $json_url = esc_url_raw($_POST['json_url']);
            $response = wp_remote_get($json_url, ['timeout' => 60, 'sslverify' => false]);
            if (is_wp_error($response)) wp_send_json_error('Failed');
            $json_body = trim(preg_replace('/^\xEF\xBB\xBF/', '', wp_remote_retrieve_body($response)));
            $filename = sanitize_file_name(basename(parse_url($json_url, PHP_URL_PATH)) ?: 'funnel-template.json');
            wp_send_json_success(['filename' => $filename, 'content' => base64_encode($json_body), 'size' => size_format(strlen($json_body))]);
        }

        // ============ GET TEMPLATES ============
        public function ajax_get_templates()
        {
            check_ajax_referer('lf_nonce', 'nonce');
            $cache_key = 'lf_templates_cache';
            $cached = get_transient($cache_key);
            if ($cached !== false) { wp_send_json_success(['templates' => $cached, 'source' => 'cache']); }

            $response = wp_remote_get($this->templates_json_url, ['timeout' => 30, 'sslverify' => false]);
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                wp_send_json_success(['templates' => $this->get_fallback_templates(), 'source' => 'fallback']);
            }
            $templates = json_decode(trim(preg_replace('/^\xEF\xBB\xBF/', '', wp_remote_retrieve_body($response))), true);
            if (!is_array($templates)) {
                wp_send_json_success(['templates' => $this->get_fallback_templates(), 'source' => 'fallback']);
            }
            set_transient($cache_key, $templates, HOUR_IN_SECONDS);
            wp_send_json_success(['templates' => $templates, 'source' => 'live']);
        }

        // ============ FALLBACK DATA ============
        private function get_fallback_free_plugins() {
            return [
                ['id' => 'woocommerce', 'slug' => 'woocommerce', 'name' => 'WooCommerce', 'file' => 'woocommerce/woocommerce.php', 'icon' => '🛒', 'desc' => 'Powerful eCommerce platform', 'required' => true],
                ['id' => 'elementor', 'slug' => 'elementor', 'name' => 'Elementor', 'file' => 'elementor/elementor.php', 'icon' => '⚡', 'desc' => 'Visual drag & drop page builder', 'required' => true],
                ['id' => 'cartflows', 'slug' => 'cartflows', 'name' => 'CartFlows', 'file' => 'cartflows/cartflows.php', 'icon' => '🚀', 'desc' => 'Sales funnel & checkout builder', 'required' => true],
                ['id' => 'litespeed-cache', 'slug' => 'litespeed-cache', 'name' => 'LiteSpeed Cache', 'file' => 'litespeed-cache/litespeed-cache.php', 'icon' => '⚡', 'desc' => 'High-performance page caching', 'required' => false],
                ['id' => 'svg-support', 'slug' => 'svg-support', 'name' => 'SVG Support', 'file' => 'svg-support/svg-support.php', 'icon' => '🎨', 'desc' => 'Upload SVG files', 'required' => false],
                ['id' => 'code-snippets', 'slug' => 'code-snippets', 'name' => 'Code Snippets', 'file' => 'code-snippets/code-snippets.php', 'icon' => '💻', 'desc' => 'Add custom code snippets', 'required' => false],
                ['id' => 'woo-checkout-field-editor-pro', 'slug' => 'woo-checkout-field-editor-pro', 'name' => 'Checkout Field Editor Pro', 'file' => 'woo-checkout-field-editor-pro/checkout-form-designer.php', 'icon' => '📝', 'desc' => 'Customize checkout fields', 'required' => false],
                ['id' => 'woocommerce-direct-checkout', 'slug' => 'woocommerce-direct-checkout', 'name' => 'Direct Checkout', 'file' => 'woocommerce-direct-checkout/woocommerce-direct-checkout.php', 'icon' => '🛍️', 'desc' => 'Skip cart', 'required' => false],
                ['id' => 'bkash', 'slug' => 'bkash', 'name' => 'bKash, Rocket, Nagad', 'file' => 'bkash/index.php', 'icon' => '💰', 'desc' => 'BD payment gateway', 'required' => false],
            ];
        }

        private function get_fallback_premium_plugins() {
            return [
                ['id' => 'elementor-pro', 'name' => 'Elementor Pro', 'desc' => 'Advanced page builder', 'icon' => '🎨', 'zip_url' => 'https://github.com/DevWithEasy/landerflow-pro/releases/download/preium_plugin/elementor-pro.zip', 'plugin_file' => 'elementor-pro/elementor-pro.php', 'category' => 'Page Builder', 'version' => '3.18.0', 'author' => 'Elementor', 'required' => true],
                ['id' => 'cartflows-pro', 'name' => 'CartFlows Pro', 'desc' => 'Sales funnel builder', 'icon' => '🛒', 'zip_url' => 'https://github.com/DevWithEasy/landerflow-pro/releases/download/preium_plugin/cartflows-pro.zip', 'plugin_file' => 'cartflows-pro/cartflows-pro.php', 'category' => 'Funnel', 'version' => '2.0.0', 'author' => 'CartFlows', 'required' => true],
                ['id' => 'pro-elements', 'name' => 'Pro Elements', 'desc' => 'Free Elementor Pro alternative', 'icon' => '⚡', 'zip_url' => 'https://github.com/DevWithEasy/landerflow-pro/releases/download/preium_plugin/pro-elements.zip', 'plugin_file' => 'pro-elements/pro-elements.php', 'category' => 'Page Builder', 'version' => 'latest', 'author' => 'ProElements', 'required' => false],
                ['id' => 'pixelyoursite-pro', 'name' => 'PixelYourSite Pro', 'desc' => 'Facebook Pixel tracking', 'icon' => '📊', 'zip_url' => 'https://github.com/DevWithEasy/landerflow-pro/releases/download/preium_plugin/pixelyoursite-pro.zip', 'plugin_file' => 'pixelyoursite-pro/pixelyoursite-pro.php', 'category' => 'Marketing', 'version' => 'latest', 'author' => 'PixelYourSite', 'required' => false],
                ['id' => 'sohag-ecommerce', 'name' => 'Sohag Online Ecommerce', 'desc' => 'Custom ecommerce solution', 'icon' => '🛍️', 'zip_url' => 'https://github.com/DevWithEasy/landerflow-pro/releases/download/preium_plugin/SohagOnlineEcommerce.zip', 'plugin_file' => 'Sohag Online eCommerce/orderflow-real-order-tracking.php', 'category' => 'Ecommerce', 'version' => '1.0.0', 'author' => 'Custom', 'required' => false],
                ['id' => 'all-in-one-business', 'name' => 'All in One Business Solution', 'desc' => 'Complete business toolkit', 'icon' => '🏢', 'zip_url' => 'https://github.com/DevWithEasy/landerflow-pro/releases/download/preium_plugin/All.in.One.Business.Solution.zip', 'plugin_file' => 'All in One Business Solution/orderflow-real-order-tracking.php', 'category' => 'Business', 'version' => '1.0.0', 'author' => 'Custom', 'required' => false],
            ];
        }

        private function get_fallback_templates() {
            return [
                ['id' => 'rupantor-nursery', 'title' => 'Rupantor Nursery Funnel', 'desc' => 'Complete nursery sales funnel', 'type' => 'funnel', 'category' => 'Ecommerce', 'steps' => 3, 'featured' => true, 'preview_img' => 'https://github.com/DevWithEasy/landerflow-pro/releases/download/template_json_image/rupantor-nursery.png', 'json_url' => 'https://github.com/DevWithEasy/landerflow-pro/releases/download/template_json_image/rupantor-nursery.json', 'tags' => ['Nursery', 'Plants', 'Checkout', 'Sales']],
            ];
        }

        private function is_installed($file) {
            if (!function_exists('get_plugins')) require_once ABSPATH . 'wp-admin/includes/plugin.php';
            return isset(get_plugins()[$file]);
        }

        // ============ RENDER PAGE ============
        public function render_page()
        {
            $auto = get_option('lf_auto_trigger', false);
            if ($auto) delete_option('lf_auto_trigger');
            $cf_active = is_plugin_active('cartflows/cartflows.php');
            ?>
            <div class="lfp-wrap <?php echo $auto ? 'auto-install' : ''; ?>">
                <aside class="lfp-sidebar">
                    <div class="lfp-brand"><div class="lfp-brand-icon">🚀</div><div><div class="lfp-brand-name">LanderFlow</div><div class="lfp-brand-tag">Pro</div></div></div>
                    <nav class="lfp-nav">
                        <a href="#setup" class="lfp-nav-item active" data-section="setup"><span class="lfp-nav-icon">⚙️</span><span>Setup Wizard</span></a>
                        <a href="#premium" class="lfp-nav-item" data-section="premium"><span class="lfp-nav-icon">💎</span><span>Premium Plugins</span></a>
                        <?php if ($cf_active): ?><a href="#templates" class="lfp-nav-item" data-section="templates"><span class="lfp-nav-icon">📚</span><span>Templates</span></a><?php endif; ?>
                    </nav>
                    <div class="lfp-sidebar-footer"><div class="lfp-version-badge">v<?php echo LANDERFLOW_PRO_VERSION; ?></div><p>by Robiul Awal</p></div>
                </aside>
                <main class="lfp-main">
                    <!-- SETUP WIZARD -->
                    <section class="lfp-section" id="section-setup">
                        <div class="lfp-topbar"><div class="lfp-topbar-left"><h1 class="lfp-page-title">Setup Wizard</h1><p class="lfp-page-sub" id="lfp-free-source">Loading plugins...</p></div><div class="lfp-topbar-right"><div class="lfp-stat"><span class="lfp-stat-val stat-completed">0</span><span class="lfp-stat-lbl">Active</span></div><div class="lfp-stat-sep">/</div><div class="lfp-stat"><span class="lfp-stat-val stat-total"><?php echo count($this->core_slugs); ?></span><span class="lfp-stat-lbl">Core</span></div></div></div>
                        <div class="lfp-warning hidden" id="lfp-warning"><span class="lfp-warning-icon">⚠️</span><span id="lfp-warning-text">Some required plugins are missing or inactive.</span></div>
                        <div class="lfp-progress-card"><div class="lfp-progress-header"><span class="lfp-progress-label" id="lfp-progress-label">Overall Progress</span><span class="lfp-progress-pct" id="lfp-progress-pct">0%</span></div><div class="lfp-progress-track"><div class="lfp-progress-fill" id="lfp-progress-fill"></div></div></div>
                        <h3 class="lfp-section-title">🔥 Required Plugins <span class="lfp-section-badge">Core</span></h3>
                        <div class="lfp-plugins-grid" id="lfp-core-plugins"><div class="lfp-tl-loading">⏳ Loading...</div></div>
                        <h3 class="lfp-section-title">📦 Optional Plugins <span class="lfp-section-badge">Optional</span></h3>
                        <div class="lfp-plugins-grid" id="lfp-optional-plugins"><div class="lfp-tl-loading">⏳ Loading...</div></div>
                        <div class="lfp-actions"><button type="button" id="lf-start" class="lfp-btn lfp-btn-primary"><span>⬇</span> Install Selected</button><button type="button" id="lf-refresh" class="lfp-btn lfp-btn-ghost"><span>↻</span> Refresh</button><button type="button" id="lf-toggle" class="lfp-btn lfp-btn-ghost"><span>☐</span> Toggle All</button></div>
                    </section>
                    <!-- PREMIUM PLUGINS -->
                    <section class="lfp-section hidden" id="section-premium">
                        <div class="lfp-topbar"><div class="lfp-topbar-left"><h1 class="lfp-page-title">💎 Premium Plugins</h1><p class="lfp-page-sub" id="lfp-premium-source">Loading...</p></div><div class="lfp-topbar-right"><button type="button" id="lf-premium-install-all" class="lfp-btn lfp-btn-primary"><span>⬇</span> Install All</button><button type="button" id="lf-premium-refresh" class="lfp-btn lfp-btn-ghost"><span>↻</span> Refresh</button></div></div>
                        <div class="lfp-plugins-grid" id="lfp-premium-plugins"><div class="lfp-tl-loading">⏳ Loading...</div></div>
                    </section>
                    <!-- TEMPLATES -->
                    <?php if ($cf_active): ?>
                    <section class="lfp-section hidden" id="section-templates">
                        <div class="lfp-template-library">
                            <div class="lfp-tl-header"><div><h2 class="lfp-tl-title">📚 Ready-Made Funnel Templates</h2><p class="lfp-tl-subtitle">Templates load from external source. <span id="lfp-template-source" style="color:var(--accent);font-size:11px;"></span></p></div><div class="lfp-tl-header-right"><a href="<?php echo admin_url('admin.php?page=cartflows&tab=import'); ?>" class="lfp-btn lfp-btn-ghost" target="_blank"><span>📥</span> CartFlows Import</a></div></div>
                            <div class="lfp-tl-search-bar"><div class="lfp-tl-search-input-wrap"><input type="text" class="lfp-tl-search-input" id="lfp-tl-search" placeholder="Search templates..."></div><div class="lfp-tl-filters" id="lfp-tl-filters"><button class="lfp-tl-filter-btn active" data-filter="all">🗂️ All</button></div></div>
                            <div class="lfp-tl-grid" id="lfp-tl-grid"><div class="lfp-tl-loading">⏳ Loading templates...</div></div>
                            <div class="lfp-tl-empty hidden"><div class="lfp-tl-empty-icon">📭</div><h3>No templates found</h3><button class="lfp-btn lfp-btn-ghost lfp-tl-clear-search">Clear Filters</button></div>
                        </div>
                    </section>
                    <?php endif; ?>
                    <div id="lfp-notification-area"></div>
                </main>
            </div>
            <?php
        }
    }
    LanderFlow_Pro::get_instance();
}