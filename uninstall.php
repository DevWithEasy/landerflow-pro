<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

delete_option('lf_auto_trigger');
delete_transient('lf_free_plugins_cache');
