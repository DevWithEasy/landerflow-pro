<?php
/**
 * Plugin Name: LanderFlow Pro
 * Plugin URI: https://wpexpertbd.shop
 * Description: Automated plugin installation and activation utility for WooCommerce, Elementor, and CartFlows
 * Version: 1.0.0
 * Author: Robiul Awal
 * Author URI: https://github.com/DevWithEasy
 * License: GPL v2 or later
 * Text Domain: landerflow-pro
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WP_DEV_UTILITY_VERSION', '1.0.0');
define('WP_DEV_UTILITY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_DEV_UTILITY_PLUGIN_URL', plugin_dir_url(__FILE__));

// Initialize the plugin
if (!class_exists('WP_Dev_Utility')) {
    
    class WP_Dev_Utility {
        
        private static $instance = null;
        private $required_plugins = array();
        
        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        
        private function __construct() {
            $this->required_plugins = array(
                'woocommerce' => array(
                    'name' => 'WooCommerce',
                    'slug' => 'woocommerce',
                    'file' => 'woocommerce/woocommerce.php'
                ),
                'elementor' => array(
                    'name' => 'Elementor',
                    'slug' => 'elementor',
                    'file' => 'elementor/elementor.php'
                ),
                'cartflows' => array(
                    'name' => 'CartFlows',
                    'slug' => 'cartflows',
                    'file' => 'cartflows/cartflows.php'
                )
            );
            
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
            add_action('wp_ajax_wp_dev_install_plugin', array($this, 'ajax_install_plugin'));
            add_action('wp_ajax_wp_dev_activate_plugin', array($this, 'ajax_activate_plugin'));
            add_action('wp_ajax_wp_dev_get_plugin_status', array($this, 'ajax_get_plugin_status'));
            add_action('activated_plugin', array($this, 'check_auto_trigger'));
        }
        
        public function add_admin_menu() {
            add_menu_page(
                'WP Developer Utility',
                'Dev Utility',
                'manage_options',
                'wp-dev-utility',
                array($this, 'render_admin_page'),
                'dashicons-admin-tools',
                100
            );
        }
        
        public function enqueue_admin_scripts($hook) {
            if ('toplevel_page_wp-dev-utility' !== $hook) {
                return;
            }
            
            wp_enqueue_style(
                'wp-dev-utility-admin',
                WP_DEV_UTILITY_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                WP_DEV_UTILITY_VERSION
            );
            
            wp_enqueue_script(
                'wp-dev-utility-admin',
                WP_DEV_UTILITY_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                WP_DEV_UTILITY_VERSION,
                true
            );
            
            wp_localize_script('wp-dev-utility-admin', 'wpDevUtility', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wp_dev_utility_nonce'),
                'plugins' => $this->required_plugins,
                'installing_text' => __('Installing...', 'wp-dev-utility'),
                'activating_text' => __('Activating...', 'wp-dev-utility'),
                'ready_text' => __('Ready', 'wp-dev-utility'),
                'error_text' => __('Error', 'wp-dev-utility')
            ));
        }
        
        public function check_auto_trigger($plugin) {
            if (plugin_basename(__FILE__) === $plugin) {
                // Store auto-trigger flag
                update_option('wp_dev_utility_auto_trigger', true);
            }
        }
        
        public function ajax_install_plugin() {
            check_ajax_referer('wp_dev_utility_nonce', 'nonce');
            
            if (!current_user_can('install_plugins')) {
                wp_send_json_error('Insufficient permissions');
            }
            
            $plugin_slug = sanitize_text_field($_POST['plugin_slug']);
            
            if (!array_key_exists($plugin_slug, $this->required_plugins)) {
                wp_send_json_error('Invalid plugin');
            }
            
            // Check if plugin is already installed
            $plugin_file = $this->required_plugins[$plugin_slug]['file'];
            
            if ($this->is_plugin_installed($plugin_file)) {
                wp_send_json_success(array(
                    'status' => 'installed',
                    'message' => 'Plugin already installed'
                ));
            }
            
            // Include required files for installation
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
            require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
            
            // Get plugin info from WordPress.org
            $api = plugins_api('plugin_information', array(
                'slug' => $plugin_slug,
                'fields' => array(
                    'short_description' => false,
                    'sections' => false,
                    'requires' => false,
                    'rating' => false,
                    'ratings' => false,
                    'downloaded' => false,
                    'last_updated' => false,
                    'added' => false,
                    'tags' => false,
                    'compatibility' => false,
                    'homepage' => false,
                    'donate_link' => false,
                ),
            ));
            
            if (is_wp_error($api)) {
                wp_send_json_error($api->get_error_message());
            }
            
            $skin = new WP_Ajax_Upgrader_Skin();
            $upgrader = new Plugin_Upgrader($skin);
            
            $result = $upgrader->install($api->download_link);
            
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }
            
            if ($skin->get_errors()->has_errors()) {
                wp_send_json_error($skin->get_errors()->get_error_message());
            }
            
            if (is_null($result)) {
                wp_send_json_error('Unable to connect to the filesystem. Please confirm your credentials.');
            }
            
            wp_send_json_success(array(
                'status' => 'installed',
                'message' => 'Plugin installed successfully'
            ));
        }
        
        public function ajax_activate_plugin() {
            check_ajax_referer('wp_dev_utility_nonce', 'nonce');
            
            if (!current_user_can('activate_plugins')) {
                wp_send_json_error('Insufficient permissions');
            }
            
            $plugin_slug = sanitize_text_field($_POST['plugin_slug']);
            
            if (!array_key_exists($plugin_slug, $this->required_plugins)) {
                wp_send_json_error('Invalid plugin');
            }
            
            $plugin_file = $this->required_plugins[$plugin_slug]['file'];
            
            // Check if plugin is already active
            if (is_plugin_active($plugin_file)) {
                wp_send_json_success(array(
                    'status' => 'active',
                    'message' => 'Plugin already active'
                ));
            }
            
            // Activate the plugin
            $result = activate_plugin($plugin_file);
            
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }
            
            wp_send_json_success(array(
                'status' => 'active',
                'message' => 'Plugin activated successfully'
            ));
        }
        
        public function ajax_get_plugin_status() {
            check_ajax_referer('wp_dev_utility_nonce', 'nonce');
            
            $status = array();
            
            foreach ($this->required_plugins as $slug => $plugin) {
                $plugin_file = $plugin['file'];
                
                $status[$slug] = array(
                    'name' => $plugin['name'],
                    'installed' => $this->is_plugin_installed($plugin_file),
                    'active' => is_plugin_active($plugin_file)
                );
            }
            
            wp_send_json_success($status);
        }
        
        private function is_plugin_installed($plugin_file) {
            $plugins = get_plugins();
            return isset($plugins[$plugin_file]);
        }
        
        public function render_admin_page() {
            $auto_trigger = get_option('wp_dev_utility_auto_trigger', false);
            
            if ($auto_trigger) {
                delete_option('wp_dev_utility_auto_trigger');
                $auto_trigger_class = 'auto-install';
            } else {
                $auto_trigger_class = '';
            }
            ?>
            <div class="wrap wp-dev-utility-wrap <?php echo $auto_trigger_class; ?>">
                <h1><?php _e('WP Developer Utility', 'wp-dev-utility'); ?></h1>
                
                <div class="wp-dev-utility-dashboard">
                    <div class="dashboard-section">
                        <h2><?php _e('Plugin Installation Status', 'wp-dev-utility'); ?></h2>
                        
                        <div id="wp-dev-utility-progress">
                            <div class="progress-container">
                                <div class="progress-bar" id="overall-progress">
                                    <div class="progress-fill"></div>
                                    <span class="progress-text">0%</span>
                                </div>
                            </div>
                            
                            <div class="plugins-status-container" id="plugins-status">
                                <?php foreach ($this->required_plugins as $slug => $plugin): ?>
                                    <div class="plugin-status-item" data-plugin="<?php echo esc_attr($slug); ?>" 
                                         data-plugin-name="<?php echo esc_attr($plugin['name']); ?>">
                                        <div class="plugin-info">
                                            <span class="plugin-name"><?php echo esc_html($plugin['name']); ?></span>
                                        </div>
                                        <div class="plugin-status">
                                            <span class="status-text" id="status-<?php echo esc_attr($slug); ?>">
                                                <?php _e('Checking...', 'wp-dev-utility'); ?>
                                            </span>
                                            <span class="status-icon" id="icon-<?php echo esc_attr($slug); ?>"></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="action-buttons">
                            <button type="button" id="start-installation" class="button button-primary">
                                <?php _e('Install & Activate All Plugins', 'wp-dev-utility'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <div class="dashboard-section">
                        <h2><?php _e('Template Importer', 'wp-dev-utility'); ?></h2>
                        <div class="coming-soon">
                            <div class="coming-soon-content">
                                <span class="dashicons dashicons-clock"></span>
                                <h3><?php _e('Coming Soon', 'wp-dev-utility'); ?></h3>
                                <p><?php _e('The Template Importer feature is currently under development. Stay tuned for updates!', 'wp-dev-utility'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }
    }
    
    // Initialize the plugin
    WP_Dev_Utility::get_instance();
}