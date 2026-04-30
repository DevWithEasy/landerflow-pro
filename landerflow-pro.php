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
        private $core_slugs = ['woocommerce', 'elementor', 'cartflows'];
        private $all_plugins = [
            'woocommerce' => ['name' => 'WooCommerce', 'file' => 'woocommerce/woocommerce.php', 'icon' => '🛒', 'desc' => 'Powerful eCommerce platform'],
            'elementor' => ['name' => 'Elementor', 'file' => 'elementor/elementor.php', 'icon' => '⚡', 'desc' => 'Visual drag & drop page builder'],
            'cartflows' => ['name' => 'CartFlows', 'file' => 'cartflows/cartflows.php', 'icon' => '🚀', 'desc' => 'Sales funnel & checkout builder'],
            'litespeed-cache' => ['name' => 'LiteSpeed Cache', 'file' => 'litespeed-cache/litespeed-cache.php', 'icon' => '⚡', 'desc' => 'High-performance page caching'],
            'svg-support' => ['name' => 'SVG Support', 'file' => 'svg-support/svg-support.php', 'icon' => '🎨', 'desc' => 'Upload SVG files to your media library'],
            'code-snippets' => ['name' => 'Code Snippets', 'file' => 'code-snippets/code-snippets.php', 'icon' => '💻', 'desc' => 'Add custom code snippets easily'],
            'woo-checkout-field-editor-pro' => ['name' => 'Checkout Field Editor Pro', 'file' => 'woo-checkout-field-editor-pro/checkout-form-designer.php', 'icon' => '📝', 'desc' => 'Customize WooCommerce checkout fields'],
            'woocommerce-direct-checkout' => ['name' => 'Direct Checkout', 'file' => 'woocommerce-direct-checkout/woocommerce-direct-checkout.php', 'icon' => '🛍️', 'desc' => 'Skip cart and go directly to checkout'],
            'bkash' => ['name' => 'bKash, Rocket, Nagad', 'file' => 'bkash/index.php', 'icon' => '💰', 'desc' => 'Bangladeshi payment gateway integration']
        ];

        /**
         * 💎 PREMIUM PLUGINS - ALL FROM CDN/GitHub
         */
        private $premium_plugins = [
            [
                'id' => 'elementor-pro',
                'name' => 'Elementor Pro',
                'desc' => 'Advanced page builder with theme builder, popup & dynamic content',
                'icon' => '🎨',
                'zip_url' => 'https://github.com/DevWithEasy/landerflow-pro/releases/download/preium_plugin/elementor-pro.zip',
                'plugin_file' => 'elementor-pro/elementor-pro.php',
                'category' => 'Page Builder',
                'version' => '3.18.0',
                'author' => 'Elementor',
                'required' => true,
                'source' => 'cdn',
            ],
            [
                'id' => 'cartflows-pro',
                'name' => 'CartFlows Pro',
                'desc' => 'Sales funnel builder with upsell, downsell & checkout optimization',
                'icon' => '🛒',
                'zip_url' => 'https://github.com/DevWithEasy/landerflow-pro/releases/download/preium_plugin/cartflows-pro.zip',
                'plugin_file' => 'cartflows-pro/cartflows-pro.php',
                'category' => 'Funnel',
                'version' => '2.0.0',
                'author' => 'CartFlows',
                'required' => true,
                'source' => 'cdn',
            ],
            [
                'id' => 'pro-elements',
                'name' => 'Pro Elements',
                'desc' => 'Free alternative to Elementor Pro features',
                'icon' => '⚡',
                'zip_url' => 'https://github.com/DevWithEasy/landerflow-pro/releases/download/preium_plugin/pro-elements.zip',
                'plugin_file' => 'pro-elements/pro-elements.php',
                'category' => 'Page Builder',
                'version' => 'latest',
                'author' => 'ProElements',
                'required' => false,
                'source' => 'cdn',
            ],
            [
                'id' => 'pixelyoursite-pro',
                'name' => 'PixelYourSite Pro',
                'desc' => 'Facebook Pixel & conversion tracking advanced plugin',
                'icon' => '📊',
                'zip_url' => 'https://github.com/DevWithEasy/landerflow-pro/releases/download/preium_plugin/pixelyoursite-pro.zip',
                'plugin_file' => 'pixelyoursite-pro/pixelyoursite-pro.php',
                'category' => 'Marketing',
                'version' => 'latest',
                'author' => 'PixelYourSite',
                'required' => false,
                'source' => 'cdn',
            ],
            [
                'id' => 'sohag-ecommerce',
                'name' => 'Sohag Online Ecommerce',
                'desc' => 'Custom ecommerce solution with local features',
                'icon' => '🛍️',
                'zip_url' => 'https://github.com/DevWithEasy/landerflow-pro/releases/download/preium_plugin/SohagOnlineEcommerce.zip',
                'plugin_file' => 'Sohag Online eCommerce/orderflow-real-order-tracking.php',
                'category' => 'Ecommerce',
                'version' => '1.0.0',
                'author' => 'Custom',
                'required' => false,
                'source' => 'cdn',
            ],
            [
                'id' => 'all-in-one-business',
                'name' => 'All in One Business Solution',
                'desc' => 'Complete business toolkit plugin for all solutions',
                'icon' => '🏢',
                'zip_url' => 'https://github.com/DevWithEasy/landerflow-pro/releases/download/preium_plugin/All.in.One.Business.Solution.zip',
                'plugin_file' => 'All in One Business Solution/orderflow-real-order-tracking.php',
                'category' => 'Business',
                'version' => '1.0.0',
                'author' => 'Custom',
                'required' => false,
                'source' => 'cdn',
            ],
        ];

        /**
         * 🚀 TEMPLATES EXTERNAL JSON URL
         * এই JSON ফাইলটি তুমি GitHub এ আপডেট করবে
         * নতুন টেমপ্লেট যোগ করলে এখানে একবার প্লাগিনে কোনো পরিবর্তন করতে হবে না
         */
        private $templates_json_url = 'https://raw.githubusercontent.com/DevWithEasy/landerflow-pro/v' . LANDERFLOW_PRO_VERSION . '/templates.json';

        public static function get_instance()
        {
            return self::$instance ?: self::$instance = new self();
        }

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
            add_action('activated_plugin', [$this, 'auto_trigger']);
        }

        public function add_menu()
        {
            add_menu_page('LanderFlow Pro', 'LanderFlow Pro', 'manage_options', 'landerflow-pro', [$this, 'render_page'], 'dashicons-admin-tools', 100);
        }

        public function enqueue_assets($hook)
        {
            if ('toplevel_page_landerflow-pro' !== $hook) return;
            wp_enqueue_style('lf-google-fonts', 'https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Syne:wght@700;800&display=swap', [], null);
            wp_enqueue_style('lf-admin', LANDERFLOW_PRO_PLUGIN_URL . 'assets/css/admin.css', [], LANDERFLOW_PRO_VERSION);
            wp_enqueue_script('lf-admin', LANDERFLOW_PRO_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], LANDERFLOW_PRO_VERSION, true);
            wp_localize_script('lf-admin', 'lfData', [
                'ajax' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('lf_nonce'),
                'plugins' => $this->all_plugins,
                'core' => $this->core_slugs,
                'premium_plugins' => $this->premium_plugins,
                'templates_json_url' => $this->templates_json_url,
                'cartflows_url' => admin_url('admin.php?page=cartflows'),
                'cartflows_import_url' => admin_url('admin.php?page=cartflows&tab=import'),
                'texts' => [
                    'active' => __('Active', 'landerflow-pro'),
                    'inactive' => __('Inactive', 'landerflow-pro'),
                    'not_installed' => __('Not Installed', 'landerflow-pro'),
                    'ready_install' => __('Ready to Install', 'landerflow-pro'),
                    'installing' => __('Installing...', 'landerflow-pro'),
                    'activating' => __('Activating...', 'landerflow-pro'),
                    'downloading' => __('Downloading...', 'landerflow-pro'),
                    'install_selected' => __('Install Selected', 'landerflow-pro'),
                    'install_all_premium' => __('Install All Premium', 'landerflow-pro'),
                    'refresh' => __('Refresh Status', 'landerflow-pro'),
                    'toggle' => __('Toggle All', 'landerflow-pro'),
                    'core_setup_complete' => __('Core Setup Complete', 'landerflow-pro'),
                    'confirm_bulk' => __('Process selected plugins?', 'landerflow-pro'),
                    'confirm_premium_bulk' => __('Install all premium plugins from CDN? This may take a few minutes.', 'landerflow-pro'),
                    'retry' => __('Retry', 'landerflow-pro'),
                    'active_btn' => __('✓ Active', 'landerflow-pro'),
                    'activate_btn' => __('Activate', 'landerflow-pro'),
                    'install_btn' => __('Install', 'landerflow-pro'),
                    'download_json' => __('Download JSON', 'landerflow-pro'),
                    'preview' => __('Preview', 'landerflow-pro'),
                    'loading_templates' => __('Loading templates...', 'landerflow-pro'),
                ]
            ]);
        }

        public function auto_trigger($plugin)
        {
            if (plugin_basename(__FILE__) === $plugin) update_option('lf_auto_trigger', true);
        }

        // ============ FREE PLUGIN AJAX ============
        public function ajax_install()
        {
            check_ajax_referer('lf_nonce', 'nonce');
            if (!current_user_can('install_plugins')) wp_send_json_error('Permission denied');
            $slug = sanitize_text_field($_POST['slug']);
            $file = $this->all_plugins[$slug]['file'] ?? null;
            if (!$file) wp_send_json_error('Invalid plugin');
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

        public function ajax_activate()
        {
            check_ajax_referer('lf_nonce', 'nonce');
            if (!current_user_can('activate_plugins')) wp_send_json_error('Permission denied');
            $slug = sanitize_text_field($_POST['slug']);
            $file = $this->all_plugins[$slug]['file'] ?? null;
            if (!$file) {
                foreach ($this->premium_plugins as $pp) {
                    if ($pp['id'] === $slug) {
                        $file = $pp['plugin_file'];
                        break;
                    }
                }
            }
            if (!$file) wp_send_json_error('Invalid plugin');
            if (is_plugin_active($file)) wp_send_json_success();
            if (!function_exists('activate_plugin')) require_once ABSPATH . 'wp-admin/includes/plugin.php';
            $result = activate_plugin($file);
            if (is_wp_error($result)) wp_send_json_error($result->get_error_message());
            wp_send_json_success();
        }

        public function ajax_status()
        {
            check_ajax_referer('lf_nonce', 'nonce');
            if (!function_exists('get_plugins')) require_once ABSPATH . 'wp-admin/includes/plugin.php';
            $status = [];
            foreach ($this->all_plugins as $slug => $p) {
                $file = $p['file'];
                $status[$slug] = ['name' => $p['name'], 'installed' => $this->is_installed($file), 'active' => is_plugin_active($file)];
            }
            wp_send_json_success($status);
        }

        public function ajax_get_all_status()
        {
            check_ajax_referer('lf_nonce', 'nonce');
            if (!function_exists('get_plugins')) require_once ABSPATH . 'wp-admin/includes/plugin.php';
            $status = ['free' => [], 'premium' => []];
            foreach ($this->all_plugins as $slug => $p) {
                $file = $p['file'];
                $status['free'][$slug] = ['name' => $p['name'], 'installed' => $this->is_installed($file), 'active' => is_plugin_active($file)];
            }
            foreach ($this->premium_plugins as $pp) {
                $file = $pp['plugin_file'];
                $status['premium'][$pp['id']] = [
                    'name' => $pp['name'],
                    'installed' => $this->is_installed($file),
                    'active' => is_plugin_active($file),
                    'zip_exists' => !empty($pp['zip_url']),
                    'zip_size' => 'CDN'
                ];
            }
            wp_send_json_success($status);
        }

        // ============ PREMIUM PLUGIN - CDN INSTALL ============
        public function ajax_premium_install()
        {
            check_ajax_referer('lf_nonce', 'nonce');
            if (!current_user_can('install_plugins')) wp_send_json_error('Permission denied');
            $plugin_id = sanitize_text_field($_POST['plugin_id']);
            $plugin_data = null;
            foreach ($this->premium_plugins as $pp) {
                if ($pp['id'] === $plugin_id) {
                    $plugin_data = $pp;
                    break;
                }
            }
            if (!$plugin_data) wp_send_json_error('Plugin not found');
            if ($this->is_installed($plugin_data['plugin_file'])) wp_send_json_success(['message' => 'Already installed']);

            $zip_file = $this->download_from_cdn($plugin_data);
            if (is_wp_error($zip_file)) wp_send_json_error($zip_file->get_error_message());

            $result = LanderFlow_Plugin_Installer::install_from_zip($zip_file, $plugin_data['name']);
            @unlink($zip_file);
            if (is_wp_error($result)) wp_send_json_error($result->get_error_message());
            wp_send_json_success(['message' => $plugin_data['name'] . ' installed from CDN!']);
        }

        public function ajax_premium_bulk_install()
        {
            check_ajax_referer('lf_nonce', 'nonce');
            if (!current_user_can('install_plugins')) wp_send_json_error('Permission denied');
            $results = [];
            foreach ($this->premium_plugins as $pp) {
                if ($this->is_installed($pp['plugin_file'])) {
                    $results[$pp['id']] = ['message' => 'Already installed'];
                    continue;
                }
                $zip_file = $this->download_from_cdn($pp);
                if (is_wp_error($zip_file)) {
                    $results[$pp['id']] = ['error' => $zip_file->get_error_message()];
                    continue;
                }
                $result = LanderFlow_Plugin_Installer::install_from_zip($zip_file, $pp['name']);
                @unlink($zip_file);
                $results[$pp['id']] = is_wp_error($result) ? ['error' => $result->get_error_message()] : ['success' => true];
            }
            wp_send_json_success($results);
        }

        private function download_from_cdn($plugin_data)
        {
            $zip_url = $plugin_data['zip_url'];
            if (empty($zip_url)) return new WP_Error('no_url', 'No download URL');

            $temp_dir = LANDERFLOW_PRO_PLUGIN_DIR . 'temp/';
            if (!file_exists($temp_dir)) wp_mkdir_p($temp_dir);

            $temp_file = $temp_dir . sanitize_file_name($plugin_data['id'] . '-' . time() . '.zip');

            $response = wp_remote_get($zip_url, [
                'timeout' => 600,
                'sslverify' => false,
                'stream' => true,
                'filename' => $temp_file,
                'headers' => ['User-Agent' => 'Mozilla/5.0 LanderFlow-Pro/' . LANDERFLOW_PRO_VERSION]
            ]);

            if (is_wp_error($response)) {
                @unlink($temp_file);
                return new WP_Error('download_failed', $response->get_error_message());
            }
            if (wp_remote_retrieve_response_code($response) !== 200) {
                @unlink($temp_file);
                return new WP_Error('http_error', 'Download failed');
            }
            if (!file_exists($temp_file) || filesize($temp_file) < 500) {
                @unlink($temp_file);
                return new WP_Error('empty_file', 'Corrupted download');
            }

            return $temp_file;
        }

        // ============ DOWNLOAD FUNNEL JSON ============
        public function ajax_download_json()
        {
            check_ajax_referer('lf_nonce', 'nonce');
            if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
            $json_url = esc_url_raw($_POST['json_url']);
            $response = wp_remote_get($json_url, ['timeout' => 60, 'sslverify' => false]);
            if (is_wp_error($response)) wp_send_json_error('Failed: ' . $response->get_error_message());
            $json_body = wp_remote_retrieve_body($response);
            $json_body = preg_replace('/^\xEF\xBB\xBF/', '', trim($json_body));
            $filename = sanitize_file_name(basename(parse_url($json_url, PHP_URL_PATH)) ?: 'funnel-template.json');
            wp_send_json_success(['filename' => $filename, 'content' => base64_encode($json_body), 'size' => size_format(strlen($json_body))]);
        }

        // ============ 🔥 GET TEMPLATES FROM EXTERNAL JSON ============
        public function ajax_get_templates()
        {
            check_ajax_referer('lf_nonce', 'nonce');

            $json_url = $this->templates_json_url;

            // Cache key
            $cache_key = 'lf_templates_cache';
            $cached = get_transient($cache_key);

            // Return cached version if exists (cache for 1 hour)
            if ($cached !== false) {
                wp_send_json_success(['templates' => $cached, 'source' => 'cache']);
            }

            // Fetch from external URL
            $response = wp_remote_get($json_url, [
                'timeout' => 30,
                'sslverify' => false,
                'headers' => ['Accept' => 'application/json', 'User-Agent' => 'LanderFlow-Pro/' . LANDERFLOW_PRO_VERSION]
            ]);

            if (is_wp_error($response)) {
                // Fallback to static templates
                $fallback = $this->get_fallback_templates();
                wp_send_json_success(['templates' => $fallback, 'source' => 'fallback', 'error' => $response->get_error_message()]);
            }

            $http_code = wp_remote_retrieve_response_code($response);
            if ($http_code !== 200) {
                $fallback = $this->get_fallback_templates();
                wp_send_json_success(['templates' => $fallback, 'source' => 'fallback', 'error' => 'HTTP ' . $http_code]);
            }

            $json_body = wp_remote_retrieve_body($response);
            $json_body = preg_replace('/^\xEF\xBB\xBF/', '', trim($json_body));

            $templates = json_decode($json_body, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($templates)) {
                $fallback = $this->get_fallback_templates();
                wp_send_json_success(['templates' => $fallback, 'source' => 'fallback', 'error' => 'Invalid JSON: ' . json_last_error_msg()]);
            }

            // Cache for 1 hour
            set_transient($cache_key, $templates, HOUR_IN_SECONDS);

            wp_send_json_success(['templates' => $templates, 'source' => 'live']);
        }

        /**
         * Fallback templates - if external JSON fails
         */
        private function get_fallback_templates()
        {
            return [
                [
                    'id' => 'rupantor-nursery',
                    'title' => 'Rupantor Nursery Funnel',
                    'desc' => 'Complete nursery sales funnel with landing, checkout & thank you page',
                    'type' => 'funnel',
                    'category' => 'Ecommerce',
                    'steps' => 3,
                    'featured' => true,
                    'preview_img' => 'https://github.com/DevWithEasy/landerflow-pro/releases/download/template_json_image/rupantor-nursery.png',
                    'json_url' => 'https://github.com/DevWithEasy/landerflow-pro/releases/download/template_json_image/rupantor-nursery.json',
                    'tags' => ['Nursery', 'Plants', 'Checkout', 'Sales'],
                ],
            ];
        }

        private function is_installed($file)
        {
            if (!function_exists('get_plugins')) require_once ABSPATH . 'wp-admin/includes/plugin.php';
            return isset(get_plugins()[$file]);
        }

        // ============ RENDER PAGE ============
        public function render_page()
        {
            $auto = get_option('lf_auto_trigger', false);
            if ($auto) delete_option('lf_auto_trigger');
            $cf_active = is_plugin_active('cartflows/cartflows.php');
            $prem_count = count($this->premium_plugins);
?>
            <div class="lfp-wrap <?php echo $auto ? 'auto-install' : ''; ?>">
                <aside class="lfp-sidebar">
                    <div class="lfp-brand">
                        <div class="lfp-brand-icon">🚀</div>
                        <div>
                            <div class="lfp-brand-name">LanderFlow</div>
                            <div class="lfp-brand-tag">Pro</div>
                        </div>
                    </div>
                    <nav class="lfp-nav">
                        <a href="#setup" class="lfp-nav-item active" data-section="setup"><span class="lfp-nav-icon">⚙️</span><span>Setup Wizard</span></a>
                        <a href="#premium" class="lfp-nav-item" data-section="premium"><span class="lfp-nav-icon">💎</span><span>Premium Plugins</span><span class="lfp-nav-count"><?php echo $prem_count; ?></span></a>
                        <?php if ($cf_active): ?><a href="#templates" class="lfp-nav-item" data-section="templates"><span class="lfp-nav-icon">📚</span><span>Templates</span></a><?php endif; ?>
                    </nav>
                    <div class="lfp-sidebar-footer">
                        <div class="lfp-version-badge">v<?php echo LANDERFLOW_PRO_VERSION; ?></div>
                        <p>by Robiul Awal</p>
                    </div>
                </aside>
                <main class="lfp-main">
                    <!-- SETUP WIZARD -->
                    <section class="lfp-section" id="section-setup">
                        <div class="lfp-topbar">
                            <div class="lfp-topbar-left">
                                <h1 class="lfp-page-title">Setup Wizard</h1>
                                <p class="lfp-page-sub">Install free plugins from WordPress.org</p>
                            </div>
                            <div class="lfp-topbar-right">
                                <div class="lfp-stat"><span class="lfp-stat-val stat-completed">0</span><span class="lfp-stat-lbl">Active</span></div>
                                <div class="lfp-stat-sep">/</div>
                                <div class="lfp-stat"><span class="lfp-stat-val stat-total"><?php echo count($this->core_slugs); ?></span><span class="lfp-stat-lbl">Core</span></div>
                            </div>
                        </div>
                        <div class="lfp-warning hidden" id="lfp-warning"><span class="lfp-warning-icon">⚠️</span><span id="lfp-warning-text">Some required plugins are missing or inactive.</span></div>
                        <div class="lfp-progress-card">
                            <div class="lfp-progress-header"><span class="lfp-progress-label" id="lfp-progress-label">Overall Progress</span><span class="lfp-progress-pct" id="lfp-progress-pct">0%</span></div>
                            <div class="lfp-progress-track">
                                <div class="lfp-progress-fill" id="lfp-progress-fill"></div>
                            </div>
                        </div>
                        <h3 class="lfp-section-title">🔥 Required Plugins <span class="lfp-section-badge">Core</span></h3>
                        <div class="lfp-plugins-grid" id="lfp-core-plugins"><?php foreach ($this->core_slugs as $s) $this->render_plugin_card($s, true); ?></div>
                        <h3 class="lfp-section-title">📦 Optional Plugins <span class="lfp-section-badge">Optional</span></h3>
                        <div class="lfp-plugins-grid" id="lfp-optional-plugins"><?php foreach (array_diff(array_keys($this->all_plugins), $this->core_slugs) as $s) $this->render_plugin_card($s, false); ?></div>
                        <div class="lfp-actions"><button type="button" id="lf-start" class="lfp-btn lfp-btn-primary"><span>⬇</span> Install Selected</button><button type="button" id="lf-refresh" class="lfp-btn lfp-btn-ghost"><span>↻</span> Refresh Status</button><button type="button" id="lf-toggle" class="lfp-btn lfp-btn-ghost"><span>☐</span> Toggle All</button></div>
                    </section>

                    <!-- PREMIUM PLUGINS -->
                    <section class="lfp-section hidden" id="section-premium">
                        <div class="lfp-topbar">
                            <div class="lfp-topbar-left">
                                <h1 class="lfp-page-title">💎 Premium Plugins</h1>
                                <p class="lfp-page-sub">Auto-download & install from CDN. Source: <code>GitHub Releases</code></p>
                            </div>
                            <div class="lfp-topbar-right"><button type="button" id="lf-premium-install-all" class="lfp-btn lfp-btn-primary"><span>⬇</span> Install All (<?php echo $prem_count; ?>)</button><button type="button" id="lf-premium-refresh" class="lfp-btn lfp-btn-ghost"><span>↻</span> Refresh</button></div>
                        </div>
                        <div class="lfp-plugins-grid" id="lfp-premium-plugins">
                            <?php foreach ($this->premium_plugins as $pp):
                                $installed = $this->is_installed($pp['plugin_file']);
                                $active = is_plugin_active($pp['plugin_file']);
                            ?>
                                <div class="lfp-plugin-card <?php echo $active ? 'lfp-active' : ($installed ? 'lfp-pending' : 'lfp-premium-ready'); ?>" data-plugin="<?php echo esc_attr($pp['id']); ?>" data-type="premium" data-core="<?php echo $pp['required'] ? '1' : '0'; ?>">
                                    <div class="lfp-plugin-icon"><?php echo esc_html($pp['icon']); ?></div>
                                    <div class="lfp-plugin-info">
                                        <div class="lfp-plugin-name"><label class="lfp-plugin-check-label"><input type="checkbox" class="lfp-plugin-checkbox" <?php echo $active ? 'disabled' : ($pp['required'] ? 'checked' : ''); ?>><?php echo esc_html($pp['name']); ?><?php if ($pp['required']) echo '<span class="lfp-required-badge">Required</span>'; ?></label></div>
                                        <div class="lfp-plugin-desc"><?php echo esc_html($pp['desc']); ?></div>
                                        <div class="lfp-plugin-tags"><span class="lfp-tag"><?php echo esc_html($pp['category']); ?></span><span class="lfp-tag">v<?php echo esc_html($pp['version'] ?? '1.0'); ?></span><span class="lfp-tag">☁️ CDN</span><span class="lfp-tag"><?php echo esc_html($pp['author'] ?? ''); ?></span></div>
                                    </div>
                                    <div class="lfp-plugin-status-col">
                                        <div class="lfp-status-pill <?php echo $active ? 'active' : ($installed ? 'pending' : 'premium'); ?>"><span class="lfp-pill-dot"></span><span><?php echo $active ? 'Active' : ($installed ? 'Inactive' : 'CDN'); ?></span></div>
                                        <button class="lfp-btn-action" data-slug="<?php echo esc_attr($pp['id']); ?>" data-action="<?php echo $active ? 'active' : ($installed ? 'activate' : 'install'); ?>" data-type="premium" <?php echo $active ? 'disabled' : ''; ?>><?php echo $active ? '✓ Active' : ($installed ? 'Activate' : 'Install'); ?></button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <!-- TEMPLATES (DYNAMIC FROM EXTERNAL JSON) -->
                    <?php if ($cf_active): ?>
                        <section class="lfp-section hidden" id="section-templates">
                            <div class="lfp-template-library">
                                <div class="lfp-tl-header">
                                    <div>
                                        <h2 class="lfp-tl-title">📚 Ready-Made Funnel Templates</h2>
                                        <p class="lfp-tl-subtitle">Templates load from external source. Always up-to-date! <span id="lfp-template-source" style="color:var(--accent);font-size:11px;"></span></p>
                                    </div>
                                    <div class="lfp-tl-header-right"><a href="<?php echo admin_url('admin.php?page=cartflows&tab=import'); ?>" class="lfp-btn lfp-btn-ghost" target="_blank"><span>📥</span> CartFlows Import</a></div>
                                </div>
                                <div class="lfp-tl-search-bar">
                                    <div class="lfp-tl-search-input-wrap"><input type="text" class="lfp-tl-search-input" id="lfp-tl-search" placeholder="Search templates..."></div>
                                    <div class="lfp-tl-filters" id="lfp-tl-filters"><button class="lfp-tl-filter-btn active" data-filter="all">🗂️ All</button></div>
                                </div>
                                <div class="lfp-tl-grid" id="lfp-tl-grid">
                                    <div class="lfp-tl-loading" id="lfp-tl-loading" style="grid-column:1/-1;text-align:center;padding:60px;color:var(--muted);">
                                        <div class="lfp-spin" style="font-size:32px;margin-bottom:16px;">⏳</div>
                                        <p>Loading templates from server...</p>
                                    </div>
                                </div>
                                <div class="lfp-tl-empty hidden">
                                    <div class="lfp-tl-empty-icon">📭</div>
                                    <h3>No templates found</h3><button class="lfp-btn lfp-btn-ghost lfp-tl-clear-search">Clear Filters</button>
                                </div>
                            </div>
                        </section>
                    <?php endif; ?>
                    <div id="lfp-notification-area"></div>
                </main>
            </div>
        <?php
        }

        private function render_plugin_card($slug, $is_core = true)
        {
            $p = $this->all_plugins[$slug];
            $in = $this->is_installed($p['file']);
            $ac = is_plugin_active($p['file']);
        ?>
            <div class="lfp-plugin-card <?php echo $ac ? 'lfp-active' : 'lfp-pending'; ?>" data-plugin="<?php echo esc_attr($slug); ?>" data-type="free">
                <div class="lfp-plugin-icon"><?php echo esc_html($p['icon'] ?? '📦'); ?></div>
                <div class="lfp-plugin-info">
                    <div class="lfp-plugin-name"><label class="lfp-plugin-check-label"><input type="checkbox" class="lfp-plugin-checkbox" <?php echo $ac ? 'disabled' : ($is_core ? 'checked' : ''); ?>><?php echo esc_html($p['name']); ?></label></div>
                    <div class="lfp-plugin-desc"><?php echo esc_html($p['desc'] ?? ''); ?></div>
                    <div class="lfp-plugin-tags"><span class="lfp-tag">WP.org</span><span class="lfp-tag"><?php echo $is_core ? 'Required' : 'Optional'; ?></span></div>
                </div>
                <div class="lfp-plugin-status-col">
                    <div class="lfp-status-pill <?php echo $ac ? 'active' : 'pending'; ?>"><span class="lfp-pill-dot"></span><span><?php echo $ac ? 'Active' : ($in ? 'Inactive' : 'Not Installed'); ?></span></div>
                    <button class="lfp-btn-action" data-slug="<?php echo esc_attr($slug); ?>" data-action="<?php echo $ac ? 'active' : ($in ? 'activate' : 'install'); ?>" data-type="free" <?php echo $ac ? 'disabled' : ''; ?>><?php echo $ac ? '✓ Active' : ($in ? 'Activate' : 'Install'); ?></button>
                </div>
            </div>
<?php
        }
    }
    LanderFlow_Pro::get_instance();
}
