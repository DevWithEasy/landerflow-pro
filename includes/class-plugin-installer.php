<?php
if (!defined('ABSPATH')) exit;

class LanderFlow_Plugin_Installer
{
    public static function install_from_zip($zip_path, $plugin_name = '')
    {
        if (!file_exists($zip_path)) return new WP_Error('zip_missing', 'ZIP file not found');
        if (!is_readable($zip_path)) return new WP_Error('zip_not_readable', 'ZIP file not readable.');
        if (!function_exists('WP_Filesystem')) require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
        if (!class_exists('Plugin_Upgrader')) require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        if (!class_exists('WP_Ajax_Upgrader_Skin')) require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
        $skin = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader($skin);
        $result = $upgrader->install($zip_path);
        if (is_wp_error($result)) return $result;
        if (!$result) return new WP_Error('install_failed', 'Installation failed.');
        return ['success' => true, 'message' => $plugin_name ? "$plugin_name installed!" : 'Plugin installed!'];
    }
}