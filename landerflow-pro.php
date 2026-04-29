<?php

/**
 * Plugin Name: LanderFlow Pro 1.0.0
 * Plugin URI: https://github.com/DevWithEasy/landerflow-pro
 * Description: Professional landing page setup utility - Automatically installs and activates WooCommerce, Elementor, and CartFlows
 * Version: 1.0.0
 * Author: Robiul Awal
 * Author URI: https://github.com/DevWithEasy
 * License: GPL v2 or later
 * Text Domain: landerflow-pro
 */
if (!defined('ABSPATH')) exit;

define('LANDERFLOW_PRO_VERSION', '1.0.0');

if (!class_exists('LanderFlow_Pro')) {
    class LanderFlow_Pro
    {
        private static $instance;
        private $core_slugs = ['woocommerce', 'elementor', 'cartflows'];
        private $all_plugins = [
            'woocommerce'   => ['name' => 'WooCommerce', 'file' => 'woocommerce/woocommerce.php'],
            'elementor'     => ['name' => 'Elementor', 'file' => 'elementor/elementor.php'],
            'cartflows'     => ['name' => 'CartFlows', 'file' => 'cartflows/cartflows.php'],
            'litespeed-cache' => [
                'name' => 'LiteSpeed Cache',
                'file' => 'litespeed-cache/litespeed-cache.php'
            ],
            'svg-support' => [
                'name' => 'SVG Support',
                'file' => 'svg-support/svg-support.php'
            ],
            'code-snippets' => [
                'name' => 'Code Snippets',
                'file' => 'code-snippets/code-snippets.php'
            ],
            'woo-checkout-field-editor-pro' => [
                'name' => 'WooCommerce Checkout Field Editor Pro',
                'file' => 'woo-checkout-field-editor-pro/checkout-form-designer.php'
            ],
            'woocommerce-direct-checkout' => [
                'name' => 'WooCommerce Direct Checkout',
                'file' => 'woocommerce-direct-checkout/woocommerce-direct-checkout.php'
            ],
            'bkash' => [
                'name' => 'SoftTech-IT bKash, Rocket, Nagad',
                'file' => 'bkash/index.php'
            ]
        ];

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
            add_action('activated_plugin', [$this, 'auto_trigger']);
        }

        public function add_menu()
        {
            add_menu_page('LanderFlow Pro', 'LanderFlow Pro', 'manage_options', 'landerflow-pro', [$this, 'render_page'], 'dashicons-admin-tools', 100);
        }

        public function enqueue_assets($hook)
        {
            if ('toplevel_page_landerflow-pro' !== $hook) return;
            wp_enqueue_style('lf-admin', plugins_url('assets/css/admin.css', __FILE__), [], LANDERFLOW_PRO_VERSION);
            wp_enqueue_script('lf-admin', plugins_url('assets/js/admin.js', __FILE__), ['jquery'], LANDERFLOW_PRO_VERSION, true);

            wp_localize_script('lf-admin', 'lfData', [
                'ajax' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('lf_nonce'),
                'plugins' => $this->all_plugins,
                'core' => $this->core_slugs
            ]);
        }

        public function auto_trigger($plugin)
        {
            if (plugin_basename(__FILE__) === $plugin) update_option('lf_auto_trigger', true);
        }

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

            $api = plugins_api('plugin_information', ['slug' => $slug, 'fields' => ['sections' => false]]);
            if (is_wp_error($api)) wp_send_json_error($api->get_error_message());

            $skin = new WP_Ajax_Upgrader_Skin();
            $upgrader = new Plugin_Upgrader($skin);
            $result = $upgrader->install($api->download_link);
            is_wp_error($result) ? wp_send_json_error($result->get_error_message()) : wp_send_json_success();
        }

        public function ajax_activate()
        {
            check_ajax_referer('lf_nonce', 'nonce');
            if (!current_user_can('activate_plugins')) wp_send_json_error('Permission denied');
            $slug = sanitize_text_field($_POST['slug']);
            $file = $this->all_plugins[$slug]['file'] ?? null;
            if (!$file) wp_send_json_error('Invalid plugin');
            if (is_plugin_active($file)) wp_send_json_success();

            $result = activate_plugin($file);
            is_wp_error($result) ? wp_send_json_error($result->get_error_message()) : wp_send_json_success();
        }

        public function ajax_status()
        {
            check_ajax_referer('lf_nonce', 'nonce');
            $status = [];
            foreach ($this->all_plugins as $slug => $p) {
                $file = $p['file'];
                $status[$slug] = ['name' => $p['name'], 'installed' => $this->is_installed($file), 'active' => is_plugin_active($file)];
            }
            wp_send_json_success($status);
        }

        private function is_installed($file)
        {
            return isset(get_plugins()[$file]);
        }

        public function render_page()
        {
            $auto = get_option('lf_auto_trigger', false);
            if ($auto) delete_option('lf_auto_trigger');
?>
            <div class="lf-wrap <?= $auto ? 'auto-run' : '' ?>">
                <div class="lf-header">
                    <h1>🚀 LanderFlow Pro</h1>
                    <p>Core plugins must be installed. Optional tools are on-demand.</p>
                </div>

                <div class="lf-progress">
                    <div class="lf-progress-bar">
                        <div id="lf-fill"></div>
                    </div>
                    <span id="lf-progress-text">Checking core status...</span>
                </div>

                <h3 class="lf-section-title">🔥 Required Plugins (Progress Tracked)</h3>
                <div id="lf-required-list">
                    <?php $this->render_plugins($this->core_slugs); ?>
                </div>

                <h3 class="lf-section-title">📦 Optional Plugins (Install As Needed)</h3>
                <div id="lf-optional-list">
                    <?php $this->render_plugins(array_diff(array_keys($this->all_plugins), $this->core_slugs)); ?>
                </div>

                <div class="lf-actions">
                    <button id="lf-start" class="lf-btn primary">Install Selected</button>
                    <button id="lf-refresh" class="lf-btn">Refresh Status</button>
                    <button id="lf-toggle" class="lf-btn">Toggle All</button>
                </div>

                <div id="lf-notice"></div>
            </div>
<?php
        }

        private function render_plugins($slugs)
        {
            foreach ($slugs as $slug) {
                $p = $this->all_plugins[$slug];
                $installed = $this->is_installed($p['file']);
                $active = is_plugin_active($p['file']);
                $is_core = in_array($slug, $this->core_slugs);

                $status_cls = $active ? 'st-active' : ($installed ? 'st-inactive' : 'st-missing');
                $btn_cls = $active ? 'btn-done' : ($installed ? 'btn-activate' : 'btn-install');
                $btn_text = $active ? '✓ Active' : ($installed ? 'Activate' : 'Install');
                $checked = $active ? '' : ($is_core ? 'checked' : '');
                $check_disabled = $active ? 'disabled' : '';
                $btn_disabled = $active ? 'disabled' : '';

                echo '<div class="lf-item" data-slug="' . esc_attr($slug) . '">
                    <label class="lf-item-left">
                        <input type="checkbox" name="lf_plugins[]" value="' . esc_attr($slug) . '" ' . $checked . ' ' . $check_disabled . '>
                        <span class="lf-name">' . esc_html($p['name']) . '</span>
                    </label>
                    <div class="lf-item-right">
                        <span class="lf-status ' . $status_cls . '">' . esc_html($active ? '✓ Active' : ($installed ? '⚡ Inactive' : '⬜ Missing')) . '</span>
                        <button class="lf-single-action ' . $btn_cls . '" data-slug="' . esc_attr($slug) . '" ' . $btn_disabled . '>' . esc_html($btn_text) . '</button>
                    </div>
                </div>';
            }
        }
    }
    LanderFlow_Pro::get_instance();
}
