/**
 * LanderFlow Pro - Admin JavaScript
 * Handles AJAX plugin installation with live progress tracking
 */
jQuery(document).ready(function($) {
    'use strict';
    
    const LanderFlowPro = {
        isProcessing: false,
        
        init: function() {
            this.cacheElements();
            this.bindEvents();
            this.checkInitialStatus();
        },
        
        cacheElements: function() {
            this.startButton = $('#start-installation');
            this.checkStatusButton = $('#check-status');
            this.pluginsContainer = $('#plugins-status');
            this.progressFill = $('.progress-fill');
            this.progressText = $('.progress-text');
            this.pluginItems = $('.plugin-status-item');
        },
        
        bindEvents: function() {
            this.startButton.on('click', () => this.handleInstallation());
            this.checkStatusButton.on('click', () => this.updateAllStatuses());
        },
        
        checkInitialStatus: function() {
            // Check if auto-trigger is set
            const autoTrigger = $('.landerflow-pro-wrap').hasClass('auto-install');
            
            if (autoTrigger) {
                setTimeout(() => {
                    this.handleInstallation();
                }, 1500);
            } else {
                this.updateAllStatuses();
            }
        },
        
        updateAllStatuses: function() {
            this.showLoadingState();
            
            $.ajax({
                url: landerflowPro.ajax_url,
                type: 'POST',
                data: {
                    action: 'landerflow_get_plugin_status',
                    nonce: landerflowPro.nonce
                },
                success: (response) => {
                    if (response.success) {
                        Object.keys(response.data).forEach((slug) => {
                            this.updatePluginUI(slug, response.data[slug]);
                        });
                    }
                },
                complete: () => {
                    this.hideLoadingState();
                }
            });
        },
        
        handleInstallation: function() {
            if (this.isProcessing) {
                return;
            }
            
            this.isProcessing = true;
            this.startButton.prop('disabled', true);
            this.checkStatusButton.prop('disabled', true);
            this.resetProgress();
            
            const plugins = Object.keys(landerflowPro.plugins);
            this.processPluginsSequentially(plugins, 0);
        },
        
        processPluginsSequentially: function(plugins, index) {
            if (index >= plugins.length) {
                this.completeInstallation();
                return;
            }
            
            const pluginSlug = plugins[index];
            const pluginName = landerflowPro.plugins[pluginSlug].name;
            const progress = Math.round((index / plugins.length) * 100);
            
            this.updateProgress(progress, `Processing: ${pluginName}...`);
            this.updatePluginStatus(pluginSlug, 'installing', landerflowPro.installing_text);
            
            // Install plugin first
            this.installPlugin(pluginSlug)
                .then(() => {
                    this.updatePluginStatus(pluginSlug, 'activating', landerflowPro.activating_text);
                    return this.activatePlugin(pluginSlug);
                })
                .then(() => {
                    this.updatePluginUI(pluginSlug, {
                        installed: true,
                        active: true,
                        name: pluginName
                    });
                    
                    const newProgress = Math.round(((index + 1) / plugins.length) * 100);
                    this.updateProgress(newProgress, `Completed: ${pluginName}`);
                    
                    // Process next plugin after short delay
                    setTimeout(() => {
                        this.processPluginsSequentially(plugins, index + 1);
                    }, 800);
                })
                .catch((error) => {
                    console.error(`Failed to process ${pluginName}:`, error);
                    
                    this.updatePluginUI(pluginSlug, {
                        installed: false,
                        active: false,
                        name: pluginName,
                        error: error.message || landerflowPro.installation_failed_text
                    });
                    
                    // Continue with next plugin even if current fails
                    setTimeout(() => {
                        this.processPluginsSequentially(plugins, index + 1);
                    }, 800);
                });
        },
        
        installPlugin: function(pluginSlug) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: landerflowPro.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'landerflow_install_plugin',
                        plugin_slug: pluginSlug,
                        nonce: landerflowPro.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            resolve(response.data);
                        } else {
                            reject(new Error(response.data || 'Installation failed'));
                        }
                    },
                    error: function(xhr, status, error) {
                        reject(new Error('Network error: ' + error));
                    }
                });
            });
        },
        
        activatePlugin: function(pluginSlug) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: landerflowPro.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'landerflow_activate_plugin',
                        plugin_slug: pluginSlug,
                        nonce: landerflowPro.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            resolve(response.data);
                        } else {
                            reject(new Error(response.data || 'Activation failed'));
                        }
                    },
                    error: function(xhr, status, error) {
                        reject(new Error('Network error: ' + error));
                    }
                });
            });
        },
        
        updatePluginStatus: function(pluginSlug, status, text) {
            const statusText = $(`#status-${pluginSlug}`);
            const statusIcon = $(`#icon-${pluginSlug}`);
            const pluginItem = $(`.plugin-status-item[data-plugin="${pluginSlug}"]`);
            
            statusText.text(text);
            
            if (status === 'installing' || status === 'activating') {
                statusIcon.html('<span class="dashicons dashicons-update"></span>');
                pluginItem.removeClass('completed error');
            }
        },
        
        updatePluginUI: function(pluginSlug, status) {
            const statusText = $(`#status-${pluginSlug}`);
            const statusIcon = $(`#icon-${pluginSlug}`);
            const pluginItem = $(`.plugin-status-item[data-plugin="${pluginSlug}"]`);
            
            pluginItem.removeClass('completed error');
            
            if (status.installed && status.active) {
                statusText.text(landerflowPro.ready_text);
                statusIcon.html('<span class="dashicons dashicons-yes"></span>');
                pluginItem.addClass('completed');
                
                // Add success animation
                pluginItem.css('animation', 'none');
                pluginItem[0].offsetHeight; // Trigger reflow
                pluginItem.css('animation', 'pulse 0.5s ease');
                
            } else if (status.error) {
                statusText.text(status.error);
                statusIcon.html('<span class="dashicons dashicons-no"></span>');
                pluginItem.addClass('error');
                
            } else if (status.installed && !status.active) {
                statusText.text(landerflowPro.activation_failed_text);
                statusIcon.html('<span class="dashicons dashicons-warning"></span>');
                
            } else {
                statusText.text(landerflowPro.error_text);
                statusIcon.html('<span class="dashicons dashicons-no"></span>');
                pluginItem.addClass('error');
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
                $(`#status-${slug}`).text(landerflowPro.checking_text);
                $(`#icon-${slug}`).html('');
            });
        },
        
        completeInstallation: function() {
            this.updateProgress(100, '100% - ' + landerflowPro.completed_text);
            this.startButton.prop('disabled', false);
            this.checkStatusButton.prop('disabled', false);
            this.isProcessing = false;
            
            // Show success notification
            this.showNotification(
                '🎉 ' + 'All plugins have been installed and activated successfully! Your landing page setup is ready.',
                'success'
            );
        },
        
        showLoadingState: function() {
            this.checkStatusButton.prop('disabled', true);
            this.checkStatusButton.find('.dashicons').addClass('dashicons-update');
            this.checkStatusButton.find('.dashicons').css('animation', 'spin 1s linear infinite');
        },
        
        hideLoadingState: function() {
            this.checkStatusButton.prop('disabled', false);
            this.checkStatusButton.find('.dashicons').removeClass('dashicons-update');
            this.checkStatusButton.find('.dashicons').css('animation', '');
        },
        
        showNotification: function(message, type) {
            // Remove existing notifications
            $('.landerflow-notification').remove();
            
            const notification = $(`
                <div class="notice notice-${type} is-dismissible landerflow-notification">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);
            
            $('.landerflow-pro-wrap h1').after(notification);
            
            // Auto dismiss after 8 seconds
            setTimeout(() => {
                notification.fadeOut(400, () => {
                    notification.remove();
                });
            }, 8000);
            
            // Manual dismiss
            notification.find('.notice-dismiss').on('click', function() {
                notification.fadeOut(400, () => {
                    notification.remove();
                });
            });
        }
    };
    
    // Initialize LanderFlow Pro
    LanderFlowPro.init();
});