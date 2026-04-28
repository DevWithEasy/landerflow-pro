<?php
/**
 * Plugin Name: LanderFlow Pro
 * Plugin URI: https://github.com/DevWithEasy/landerflow-pro
 * Description: Professional landing page setup utility - Automatically installs and activates WooCommerce, Elementor, and CartFlows for seamless landing page creation
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
define('LANDERFLOW_PRO_VERSION', '1.0.0');
define('LANDERFLOW_PRO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LANDERFLOW_PRO_PLUGIN_URL', plugin_dir_url(__FILE__));

// Initialize the plugin
if (!class_exists('LanderFlow_Pro')) {
    
    class LanderFlow_Pro {
        
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
            add_action('wp_ajax_landerflow_install_plugin', array($this, 'ajax_install_plugin'));
            add_action('wp_ajax_landerflow_activate_plugin', array($this, 'ajax_activate_plugin'));
            add_action('wp_ajax_landerflow_get_plugin_status', array($this, 'ajax_get_plugin_status'));
            add_action('activated_plugin', array($this, 'check_auto_trigger'));
        }
        
        public function add_admin_menu() {
            add_menu_page(
                'LanderFlow Pro',
                'LanderFlow Pro',
                'manage_options',
                'landerflow-pro',
                array($this, 'render_admin_page'),
                'dashicons-admin-tools',
                100
            );
        }
        
        public function enqueue_admin_scripts($hook) {
            if ('toplevel_page_landerflow-pro' !== $hook) {
                return;
            }
            
            wp_enqueue_style(
                'landerflow-pro-admin',
                LANDERFLOW_PRO_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                LANDERFLOW_PRO_VERSION
            );
            
            wp_enqueue_script(
                'landerflow-pro-admin',
                LANDERFLOW_PRO_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                LANDERFLOW_PRO_VERSION,
                true
            );
            
            wp_localize_script('landerflow-pro-admin', 'landerflowPro', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('landerflow_pro_nonce'),
                'plugins' => $this->required_plugins,
                'installing_text' => __('Installing...', 'landerflow-pro'),
                'activating_text' => __('Activating...', 'landerflow-pro'),
                'ready_text' => __('Ready', 'landerflow-pro'),
                'error_text' => __('Error', 'landerflow-pro'),
                'checking_text' => __('Checking...', 'landerflow-pro'),
                'completed_text' => __('Completed!', 'landerflow-pro'),
                'activation_failed_text' => __('Activation Failed', 'landerflow-pro'),
                'installation_failed_text' => __('Installation Failed', 'landerflow-pro')
            ));
        }
        
        public function check_auto_trigger($plugin) {
            if (plugin_basename(__FILE__) === $plugin) {
                // Store auto-trigger flag
                update_option('landerflow_pro_auto_trigger', true);
            }
        }
        
        public function ajax_install_plugin() {
            check_ajax_referer('landerflow_pro_nonce', 'nonce');
            
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
            check_ajax_referer('landerflow_pro_nonce', 'nonce');
            
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
            check_ajax_referer('landerflow_pro_nonce', 'nonce');
            
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
            $auto_trigger = get_option('landerflow_pro_auto_trigger', false);
            
            if ($auto_trigger) {
                delete_option('landerflow_pro_auto_trigger');
                $auto_trigger_class = 'auto-install';
            } else {
                $auto_trigger_class = '';
            }
            ?>
            <div class="wrap landerflow-pro-wrap <?php echo $auto_trigger_class; ?>">
                <div class="landerflow-header">
                    <h1>
                        <span class="dashicons dashicons-superhero"></span>
                        <?php _e('LanderFlow Pro', 'landerflow-pro'); ?>
                    </h1>
                    <p class="landerflow-subtitle"><?php _e('Professional Landing Page Setup Utility', 'landerflow-pro'); ?></p>
                </div>
                
                <div class="landerflow-pro-dashboard">
                    <div class="dashboard-section">
                        <div class="section-header">
                            <h2><?php _e('🚀 Plugin Installation Status', 'landerflow-pro'); ?></h2>
                            <p><?php _e('Installing and activating required plugins for optimal landing page performance', 'landerflow-pro'); ?></p>
                        </div>
                        
                        <div id="landerflow-progress">
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
                                            <span class="plugin-icon">
                                                <?php echo $this->get_plugin_icon($slug); ?>
                                            </span>
                                            <div class="plugin-details">
                                                <span class="plugin-name"><?php echo esc_html($plugin['name']); ?></span>
                                                <span class="plugin-description">
                                                    <?php echo $this->get_plugin_description($slug); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="plugin-status">
                                            <span class="status-text" id="status-<?php echo esc_attr($slug); ?>">
                                                <?php _e('Checking...', 'landerflow-pro'); ?>
                                            </span>
                                            <span class="status-icon" id="icon-<?php echo esc_attr($slug); ?>"></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="action-buttons">
                            <button type="button" id="start-installation" class="button button-primary button-hero">
                                <span class="dashicons dashicons-download"></span>
                                <?php _e('Install & Activate All Plugins', 'landerflow-pro'); ?>
                            </button>
                            <button type="button" id="check-status" class="button button-secondary">
                                <span class="dashicons dashicons-update"></span>
                                <?php _e('Refresh Status', 'landerflow-pro'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <div class="dashboard-section template-importer-section">
                        <div class="section-header">
                            <h2><?php _e('🎨 Template Importer', 'landerflow-pro'); ?></h2>
                            <p><?php _e('Import professional landing page templates with one click', 'landerflow-pro'); ?></p>
                        </div>
                        <div class="coming-soon">
                            <div class="coming-soon-content">
                                <span class="dashicons dashicons-layout"></span>
                                <h3><?php _e('Coming Soon', 'landerflow-pro'); ?></h3>
                                <p><?php _e('We\'re crafting beautiful landing page templates for you. This feature will be available in the next update!', 'landerflow-pro'); ?></p>
                                <div class="feature-list">
                                    <span class="feature-item">
                                        <span class="dashicons dashicons-yes"></span>
                                        Pre-built Landing Pages
                                    </span>
                                    <span class="feature-item">
                                        <span class="dashicons dashicons-yes"></span>
                                        One-Click Import
                                    </span>
                                    <span class="feature-item">
                                        <span class="dashicons dashicons-yes"></span>
                                        Mobile Responsive
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }
        
        private function get_plugin_icon($slug) {
            $icons = array(
                'woocommerce' => '🛒',
                'elementor' => '⚡',
                'cartflows' => '🛍️'
            );
            return isset($icons[$slug]) ? $icons[$slug] : '📦';
        }
        
        private function get_plugin_description($slug) {
            $descriptions = array(
                'woocommerce' => 'E-commerce platform',
                'elementor' => 'Page builder',
                'cartflows' => 'Sales funnel builder'
            );
            return isset($descriptions[$slug]) ? $descriptions[$slug] : '';
        }
    }
    
    // Initialize the plugin
    LanderFlow_Pro::get_instance();
}