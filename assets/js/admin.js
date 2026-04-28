jQuery(document).ready(function($) {
    'use strict';
    
    const WPDevUtility = {
        init: function() {
            this.cacheElements();
            this.bindEvents();
            this.checkInitialStatus();
        },
        
        cacheElements: function() {
            this.startButton = $('#start-installation');
            this.pluginsContainer = $('#plugins-status');
            this.progressFill = $('.progress-fill');
            this.progressText = $('.progress-text');
            this.pluginItems = $('.plugin-status-item');
        },
        
        bindEvents: function() {
            this.startButton.on('click', this.handleInstallation.bind(this));
        },
        
        checkInitialStatus: function() {
            // Check if auto-trigger is set
            const autoTrigger = $('.wp-dev-utility-wrap').hasClass('auto-install');
            
            if (autoTrigger) {
                setTimeout(() => {
                    this.handleInstallation();
                }, 1000);
            } else {
                this.updateAllStatuses();
            }
        },
        
        updateAllStatuses: function() {
            $.ajax({
                url: wpDevUtility.ajax_url,
                type: 'POST',
                data: {
                    action: 'wp_dev_get_plugin_status',
                    nonce: wpDevUtility.nonce
                },
                success: (response) => {
                    if (response.success) {
                        Object.keys(response.data).forEach((slug) => {
                            this.updatePluginUI(slug, response.data[slug]);
                        });
                    }
                }
            });
        },
        
        handleInstallation: function() {
            if (this.startButton.prop('disabled')) {
                return;
            }
            
            this.startButton.prop('disabled', true);
            this.resetProgress();
            
            const plugins = Object.keys(wpDevUtility.plugins);
            this.processPlugins(plugins, 0);
        },
        
        processPlugins: function(plugins, index) {
            if (index >= plugins.length) {
                this.completeInstallation();
                return;
            }
            
            const pluginSlug = plugins[index];
            const pluginName = wpDevUtility.plugins[pluginSlug].name;
            const progress = Math.round((index / plugins.length) * 100);
            
            this.updateProgress(progress, `Processing: ${pluginName}`);
            this.updatePluginStatus(pluginSlug, 'installing');
            
            // First install the plugin
            this.installPlugin(pluginSlug, (success) => {
                if (success) {
                    this.updatePluginStatus(pluginSlug, 'activating');
                    
                    // Then activate the plugin
                    this.activatePlugin(pluginSlug, (activated) => {
                        if (activated) {
                            this.updatePluginUI(pluginSlug, {
                                installed: true,
                                active: true,
                                name: pluginName
                            });
                        } else {
                            this.updatePluginUI(pluginSlug, {
                                installed: true,
                                active: false,
                                name: pluginName,
                                error: 'Activation failed'
                            });
                        }
                        
                        // Process next plugin
                        const newProgress = Math.round(((index + 1) / plugins.length) * 100);
                        this.updateProgress(newProgress, `Completed: ${pluginName}`);
                        
                        setTimeout(() => {
                            this.processPlugins(plugins, index + 1);
                        }, 500);
                    });
                } else {
                    this.updatePluginUI(pluginSlug, {
                        installed: false,
                        active: false,
                        name: pluginName,
                        error: 'Installation failed'
                    });
                    
                    // Continue with next plugin even if current one failed
                    setTimeout(() => {
                        this.processPlugins(plugins, index + 1);
                    }, 500);
                }
            });
        },
        
        installPlugin: function(pluginSlug, callback) {
            $.ajax({
                url: wpDevUtility.ajax_url,
                type: 'POST',
                data: {
                    action: 'wp_dev_install_plugin',
                    plugin_slug: pluginSlug,
                    nonce: wpDevUtility.nonce
                },
                success: function(response) {
                    callback(response.success);
                },
                error: function() {
                    callback(false);
                }
            });
        },
        
        activatePlugin: function(pluginSlug, callback) {
            $.ajax({
                url: wpDevUtility.ajax_url,
                type: 'POST',
                data: {
                    action: 'wp_dev_activate_plugin',
                    plugin_slug: pluginSlug,
                    nonce: wpDevUtility.nonce
                },
                success: function(response) {
                    callback(response.success);
                },
                error: function() {
                    callback(false);
                }
            });
        },
        
        updatePluginStatus: function(pluginSlug, status) {
            const statusText = $(`#status-${pluginSlug}`);
            const statusIcon = $(`#icon-${pluginSlug}`);
            const pluginItem = $(`.plugin-status-item[data-plugin="${pluginSlug}"]`);
            
            if (status === 'installing') {
                statusText.text(wpDevUtility.installing_text);
                statusIcon.html('<span class="dashicons dashicons-update"></span>');
            } else if (status === 'activating') {
                statusText.text(wpDevUtility.activating_text);
                statusIcon.html('<span class="dashicons dashicons-update"></span>');
            }
        },
        
        updatePluginUI: function(pluginSlug, status) {
            const statusText = $(`#status-${pluginSlug}`);
            const statusIcon = $(`#icon-${pluginSlug}`);
            const pluginItem = $(`.plugin-status-item[data-plugin="${pluginSlug}"]`);
            
            if (status.installed && status.active) {
                statusText.text(wpDevUtility.ready_text);
                statusIcon.html('<span class="dashicons dashicons-yes"></span>');
                pluginItem.removeClass('error').addClass('completed');
            } else if (status.error) {
                statusText.text(status.error);
                statusIcon.html('<span class="dashicons dashicons-no"></span>');
                pluginItem.removeClass('completed').addClass('error');
            } else if (status.installed && !status.active) {
                statusText.text('Installed but not active');
                statusIcon.html('<span class="dashicons dashicons-warning"></span>');
                pluginItem.removeClass('completed error');
            } else {
                statusText.text(wpDevUtility.error_text);
                statusIcon.html('<span class="dashicons dashicons-no"></span>');
                pluginItem.removeClass('completed').addClass('error');
            }
        },
        
        updateProgress: function(percentage, text) {
            this.progressFill.css('width', percentage + '%');
            this.progressText.text(text || percentage + '%');
        },
        
        resetProgress: function() {
            this.updateProgress(0, '0%');
            this.pluginItems.removeClass('completed error');
            
            this.pluginItems.each(function() {
                const slug = $(this).data('plugin');
                $(`#status-${slug}`).text('Checking...');
                $(`#icon-${slug}`).html('');
            });
        },
        
        completeInstallation: function() {
            this.updateProgress(100, '100% - Complete!');
            this.startButton.prop('disabled', false);
            
            // Show success message
            this.showNotification('All plugins have been installed and activated successfully!', 'success');
        },
        
        showNotification: function(message, type) {
            const notification = $(`
                <div class="notice notice-${type} is-dismissible">
                    <p>${message}</p>
                </div>
            `);
            
            $('.wp-dev-utility-wrap h1').after(notification);
            
            setTimeout(() => {
                notification.fadeOut(() => {
                    notification.remove();
                });
            }, 5000);
        }
    };
    
    // Initialize the utility
    WPDevUtility.init();
});