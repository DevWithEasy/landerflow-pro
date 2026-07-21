jQuery(function($) {
    'use strict';
    if (typeof lfData === 'undefined') { console.error('lfData missing'); return; }

    var LFP = {
        freePlugins: [],
        premiumPlugins: [],
        isProcessing: false,

        init: function() {
            this.loadFreePlugins();
            this.loadPremiumPlugins();
            this.bindEvents();
            this.checkAutoInstall();
        },

        checkAutoInstall: function() {
            var self = this;
            if ($('#lfp-app').hasClass('auto-install')) {
                setTimeout(function() { self.checkCorePlugins(); }, 600);
            }
        },

        checkCorePlugins: function() {
            var self = this;
            $.ajax({
                url: lfData.ajax,
                type: 'POST',
                data: { action: 'lf_get_all_status', nonce: lfData.nonce },
                success: function(r) {
                    if (!r.success) return;
                    var missingCore = [];
                    $.each(r.data.free, function(slug, info) {
                        if (info.required && !info.active) {
                            missingCore.push({ slug: slug, name: info.name });
                        }
                    });
                    if (missingCore.length > 0) {
                        self.showCoreAlert(missingCore);
                    } else {
                        $('#lfp-core-alert').addClass('hidden');
                    }
                }
            });
        },

        showCoreAlert: function(missingCore) {
            var alert = $('#lfp-core-alert');
            alert.removeClass('hidden');
            $('#lfp-alert-title').text('Core Plugins Missing');
            $('#lfp-alert-text').text(missingCore.length + ' required plugin(s) not active: ' + missingCore.map(function(p) { return p.name; }).join(', '));
            $('#lfp-core-fix-btn').off('click').on('click', function() {
                LFP.showCoreInstallModal(missingCore);
            });
        },

        showCoreInstallModal: function(missingCore) {
            var modal = $(
                '<div class="lfp-modal-overlay">' +
                '<div class="lfp-modal">' +
                '<div class="lfp-modal-header">' +
                '<h2>🚀 Install Core Plugins</h2>' +
                '<button class="lfp-modal-close">&times;</button>' +
                '</div>' +
                '<div class="lfp-modal-body" id="core-modal-list">' +
                missingCore.map(function(p) {
                    return '<div class="lfp-modal-plugin-item" data-slug="' + p.slug + '">' +
                        '<div class="icon">📦</div>' +
                        '<div class="name">' + p.name + '</div>' +
                        '<div class="status">⏳ Waiting</div>' +
                    '</div>';
                }).join('') +
                '</div>' +
                '<div class="lfp-modal-footer">' +
                '<button class="lfp-btn primary" id="core-install-btn">⬇ Install & Activate All</button>' +
                '</div>' +
                '</div>' +
                '</div>'
            );
            $('body').append(modal);
            modal.find('.lfp-modal-close, .lfp-modal-overlay').on('click', function(e) {
                if (e.target === this || $(this).hasClass('lfp-modal-close')) {
                    modal.remove();
                }
            });
            modal.find('#core-install-btn').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true).text('Installing...');
                LFP.installCoreSequentially(missingCore, 0, modal);
            });
        },

        installCoreSequentially: function(plugins, index, modal) {
            var self = this;
            if (index >= plugins.length) {
                setTimeout(function() {
                    modal.remove();
                    self.refreshAllData();
                    self.toast('✅ All core plugins installed!', 'success');
                }, 500);
                return;
            }
            var p = plugins[index];
            var item = modal.find('[data-slug="' + p.slug + '"]');
            item.find('.status').text('⬇ Installing...').css('color', '#F0C040');
            
            // Find the plugin data
            var pluginData = this.freePlugins.find(function(fp) { return (fp.id || fp.slug) === p.slug; });
            if (!pluginData) {
                this.toast('❌ Plugin data not found: ' + p.name, 'error');
                setTimeout(function() { self.installCoreSequentially(plugins, index + 1, modal); }, 300);
                return;
            }
            
            this.doInstall(pluginData.id || pluginData.slug, pluginData.file)
                .then(function() {
                    item.find('.status').text('⚡ Activating...').css('color', '#60A5FA');
                    return self.doActivate(pluginData.file);
                })
                .then(function() {
                    item.find('.status').text('✅ Active').css('color', '#10B981');
                    item.css({ 'border-color': 'rgba(16, 185, 129, 0.3)', 'background': 'rgba(16, 185, 129, 0.1)' });
                    setTimeout(function() { self.installCoreSequentially(plugins, index + 1, modal); }, 300);
                })
                .catch(function(e) {
                    item.find('.status').text('❌ Failed').css('color', '#EF4444');
                    self.toast('❌ ' + p.name + ': ' + e.message, 'error');
                    setTimeout(function() { self.installCoreSequentially(plugins, index + 1, modal); }, 300);
                });
        },

        bindEvents: function() {
            var self = this;

            // Free plugins bulk install
            $('#install-selected-free').on('click', function() {
                self.installSelectedFree();
            });

            // Premium plugins bulk install
            $('#install-selected-premium').on('click', function() {
                self.installSelectedPremium();
            });

            // Refresh buttons
            $('#refresh-free').on('click', function() { self.loadFreePlugins(); });
            $('#refresh-premium').on('click', function() { self.loadPremiumPlugins(); });

            // Single plugin action
            $(document).on('click', '.lfp-action-btn', function(e) {
                e.preventDefault();
                var btn = $(this);
                if (btn.is(':disabled') || self.isProcessing) return;
                self.handleSingleAction(btn);
            });

            // Checkbox changes update counts
            $(document).on('change', '.lfp-plugin-checkbox', function() {
                self.updateCounts();
            });

            // Keyboard escape for modals
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') $('.lfp-modal-overlay').remove();
            });
        },

        loadFreePlugins: function() {
            var self = this;
            $.ajax({
                url: lfData.ajax,
                type: 'POST',
                data: { action: 'lf_get_free_plugins', nonce: lfData.nonce },
                success: function(r) {
                    if (r.success) {
                        self.freePlugins = r.data.plugins;
                        self.renderFreePlugins();
                    }
                },
                error: function() {
                    $('#core-plugins, #optional-plugins').html('<p style="color:#EF4444;text-align:center;">❌ Failed to load</p>');
                }
            });
        },

        loadPremiumPlugins: function() {
            var self = this;
            $.ajax({
                url: lfData.ajax,
                type: 'POST',
                data: { action: 'lf_get_premium_plugins', nonce: lfData.nonce },
                success: function(r) {
                    if (r.success) {
                        self.premiumPlugins = r.data.plugins;
                        $('#premium-source').text((r.data.source === 'live' ? '🌐 Live' : r.data.source === 'cache' ? '💾 Cached' : '📦 Fallback') + ' • ' + self.premiumPlugins.length + ' plugins');
                        self.renderPremiumPlugins();
                    }
                },
                error: function() {
                    $('#premium-plugins').html('<p style="color:#EF4444;text-align:center;">❌ Failed to load</p>');
                }
            });
        },

        renderFreePlugins: function() {
            var self = this;
            var coreContainer = $('#core-plugins').empty();
            var optContainer = $('#optional-plugins').empty();
            
            this.freePlugins.forEach(function(p) {
                var isActive = p.active;
                var isInstalled = p.installed;
                var card = self.createPluginCard(p, isActive, isInstalled, 'free');
                
                if (p.required) {
                    coreContainer.append(card);
                } else {
                    optContainer.append(card);
                }
            });

            if (!coreContainer.children().length) {
                coreContainer.html('<div class="lfp-skeleton">No core plugins found</div>');
            }
            if (!optContainer.children().length) {
                optContainer.html('<div class="lfp-skeleton">No optional plugins found</div>');
            }
            
            this.updateCounts();
            this.updateProgress();
        },

        renderPremiumPlugins: function() {
            var self = this;
            var container = $('#premium-plugins').empty();
            
            if (!this.premiumPlugins.length) {
                container.html('<div class="lfp-skeleton">No premium plugins found</div>');
                return;
            }
            
            this.premiumPlugins.forEach(function(p) {
                var isActive = p.active;
                var isInstalled = p.installed;
                var card = self.createPluginCard(p, isActive, isInstalled, 'premium');
                container.append(card);
            });
            
            this.updateCounts();
        },

        createPluginCard: function(p, isActive, isInstalled, type) {
            var id = type === 'premium' ? p.id : (p.id || p.slug);
            var file = type === 'premium' ? (p.plugin_file || '') : (p.file || '');
            var name = p.name || 'Plugin';
            var desc = p.desc || '';
            var icon = p.icon || '📦';
            var required = p.required || false;
            var category = p.category || (type === 'premium' ? 'Premium' : 'Free');
            var version = p.version || '';
            
            var cardClass = isActive ? 'active' : (isInstalled ? 'installed' : '');
            var statusClass = isActive ? 'active' : (isInstalled ? 'inactive' : (type === 'premium' ? 'premium' : 'not-installed'));
            var statusText = isActive ? 'Active' : (isInstalled ? 'Inactive' : (type === 'premium' ? 'CDN Available' : 'Not Installed'));
            var btnAction = isActive ? 'active' : (isInstalled ? 'activate' : 'install');
            var btnText = isActive ? '✓ Active' : (isInstalled ? 'Activate' : 'Install');
            var btnDisabled = isActive ? 'disabled' : '';
            var checkboxDisabled = isActive ? 'disabled' : '';
            var checkboxChecked = required && !isActive ? 'checked' : '';

            return '<div class="lfp-plugin-card ' + cardClass + '" data-plugin="' + id + '" data-type="' + type + '" data-file="' + file + '">' +
                '<div class="lfp-plugin-icon">' + icon + '</div>' +
                '<div class="lfp-plugin-info">' +
                    '<div class="lfp-plugin-name">' +
                        '<label>' +
                            '<input type="checkbox" class="lfp-plugin-checkbox" data-type="' + type + '" data-file="' + file + '" ' + checkboxDisabled + ' ' + checkboxChecked + '>' +
                            name +
                        '</label>' +
                        (required ? '<span class="lfp-required-badge">Required</span>' : '') +
                    '</div>' +
                    '<div class="lfp-plugin-desc">' + desc + '</div>' +
                    '<div class="lfp-plugin-meta">' +
                        '<span class="lfp-tag">' + category + '</span>' +
                        (version ? '<span class="lfp-tag">v' + version + '</span>' : '') +
                        (type === 'premium' ? '<span class="lfp-tag">☁️ CDN</span>' : '<span class="lfp-tag">WP.org</span>') +
                    '</div>' +
                '</div>' +
                '<div class="lfp-plugin-status">' +
                    '<span class="lfp-status-badge ' + statusClass + '">' +
                        '<span class="lfp-status-dot"></span>' +
                        statusText +
                    '</span>' +
                    '<button class="lfp-action-btn" data-action="' + btnAction + '" data-type="' + type + '" data-file="' + file + '" data-plugin="' + id + '" ' + btnDisabled + '>' + btnText + '</button>' +
                '</div>' +
            '</div>';
        },

        isPluginInstalled: function(file) {
            if (!file) return false;
            var card = $('[data-file="' + file + '"]').closest('.lfp-plugin-card');
            return card.hasClass('active') || card.hasClass('installed');
        },

        isPluginActive: function(file) {
            if (!file) return false;
            return $('[data-file="' + file + '"]').closest('.lfp-plugin-card').hasClass('active');
        },

        updateCounts: function() {
            var self = this;
            
            // Free plugins count
            var freeChecked = 0;
            $('.lfp-plugin-checkbox[data-type="free"]:checked').each(function() {
                var card = $(this).closest('.lfp-plugin-card');
                if (!card.hasClass('active')) freeChecked++;
            });
            $('#free-count').text(freeChecked);
            $('#install-selected-free').prop('disabled', freeChecked === 0 || this.isProcessing);
            
            // Premium plugins count
            var premiumChecked = 0;
            $('.lfp-plugin-checkbox[data-type="premium"]:checked').each(function() {
                var card = $(this).closest('.lfp-plugin-card');
                if (!card.hasClass('active')) premiumChecked++;
            });
            $('#premium-count').text(premiumChecked);
            $('#install-selected-premium').prop('disabled', premiumChecked === 0 || this.isProcessing);
        },

        installSelectedFree: function() {
            var self = this;
            var toInstall = [];
            
            $('.lfp-plugin-checkbox[data-type="free"]:checked').each(function() {
                var card = $(this).closest('.lfp-plugin-card');
                if (!card.hasClass('active')) {
                    toInstall.push({
                        id: card.data('plugin'),
                        file: card.data('file'),
                        name: card.find('.lfp-plugin-name label').text().trim(),
                        type: 'free'
                    });
                }
            });
            
            if (!toInstall.length) {
                this.toast('All selected plugins are already active.', 'info');
                return;
            }
            
            if (!confirm('Install and activate ' + toInstall.length + ' selected free plugin(s)?')) return;
            
            this.isProcessing = true;
            $('.lfp-btn').prop('disabled', true);
            this.toast('🚀 Processing ' + toInstall.length + ' plugin(s)...', 'info');
            this.processBatch(toInstall, 0);
        },

        installSelectedPremium: function() {
            var self = this;
            var toInstall = [];
            
            $('.lfp-plugin-checkbox[data-type="premium"]:checked').each(function() {
                var card = $(this).closest('.lfp-plugin-card');
                if (!card.hasClass('active')) {
                    var pluginData = self.premiumPlugins.find(function(p) { return p.id === card.data('plugin'); });
                    toInstall.push({
                        id: card.data('plugin'),
                        file: card.data('file'),
                        name: pluginData ? pluginData.name : card.find('.lfp-plugin-name label').text().trim(),
                        type: 'premium',
                        zipUrl: pluginData ? pluginData.zip_url : ''
                    });
                }
            });
            
            if (!toInstall.length) {
                this.toast('All selected premium plugins are already active.', 'info');
                return;
            }
            
            if (!confirm('Download and install ' + toInstall.length + ' premium plugin(s) from CDN?')) return;
            
            this.isProcessing = true;
            $('.lfp-btn').prop('disabled', true);
            this.toast('☁️ Downloading ' + toInstall.length + ' plugin(s)...', 'info');
            this.processBatch(toInstall, 0);
        },

        handleSingleAction: function(btn) {
            var self = this;
            var id = btn.data('plugin');
            var action = btn.data('action');
            var type = btn.data('type');
            var file = btn.data('file');
            var card = btn.closest('.lfp-plugin-card');
            var statusBadge = card.find('.lfp-status-badge');
            var name = card.find('.lfp-plugin-name label').text().trim();
            
            if (action === 'install') {
                btn.prop('disabled', true).text('Installing...').addClass('installing');
                statusBadge.removeClass().addClass('lfp-status-badge inactive').html('<span class="lfp-status-dot"></span>Installing...');
                card.addClass('installing');
                
                var installPromise = type === 'premium' 
                    ? this.doPremiumInstall(id, file, name)
                    : this.doInstall(id, file);
                
                installPromise.then(function() {
                    btn.text('Activating...').removeClass('installing').addClass('activating');
                    statusBadge.removeClass().addClass('lfp-status-badge inactive').html('<span class="lfp-status-dot"></span>Activating...');
                    card.removeClass('installing').addClass('activating');
                    return self.doActivate(file);
                })
                .then(function() {
                    card.addClass('active').removeClass('activating installed');
                    statusBadge.removeClass().addClass('lfp-status-badge active').html('<span class="lfp-status-dot"></span>Active');
                    btn.removeClass('activating').text('✓ Active').data('action', 'active').prop('disabled', true);
                    card.find('.lfp-plugin-checkbox').prop('checked', false).prop('disabled', true);
                    self.toast('✅ ' + name + ' active!', 'success');
                    self.updateCounts();
                    self.updateProgress();
                })
                .catch(function(e) {
                    card.addClass('error').removeClass('installing activating');
                    statusBadge.removeClass().addClass('lfp-status-badge error').html('<span class="lfp-status-dot"></span>Failed');
                    btn.prop('disabled', false).text('Retry').data('action', 'install').removeClass('installing activating');
                    self.toast('❌ ' + name + ': ' + e.message, 'error');
                });
            } else if (action === 'activate') {
                btn.prop('disabled', true).text('Activating...').addClass('activating');
                statusBadge.removeClass().addClass('lfp-status-badge inactive').html('<span class="lfp-status-dot"></span>Activating...');
                card.addClass('activating');
                
                this.doActivate(file)
                    .then(function() {
                        card.addClass('active').removeClass('activating installed');
                        statusBadge.removeClass().addClass('lfp-status-badge active').html('<span class="lfp-status-dot"></span>Active');
                        btn.removeClass('activating').text('✓ Active').data('action', 'active').prop('disabled', true);
                        card.find('.lfp-plugin-checkbox').prop('checked', false).prop('disabled', true);
                        self.toast('✅ ' + name + ' active!', 'success');
                        self.updateCounts();
                        self.updateProgress();
                    })
                    .catch(function(e) {
                        card.addClass('error').removeClass('activating');
                        statusBadge.removeClass().addClass('lfp-status-badge error').html('<span class="lfp-status-dot"></span>Failed');
                        btn.prop('disabled', false).text('Retry').data('action', 'activate').removeClass('activating');
                        self.toast('❌ ' + name + ': ' + e.message, 'error');
                    });
            }
        },

        processBatch: function(list, index) {
            var self = this;
            if (index >= list.length) {
                this.finishBatch();
                return;
            }
            
            var p = list[index];
            var card = $('[data-plugin="' + p.id + '"][data-type="' + p.type + '"]');
            var btn = card.find('.lfp-action-btn');
            var statusBadge = card.find('.lfp-status-badge');
            var needsInstall = btn.data('action') === 'install';
            
            btn.prop('disabled', true);
            
            if (needsInstall) {
                btn.text('Installing...').addClass('installing');
                statusBadge.removeClass().addClass('lfp-status-badge inactive').html('<span class="lfp-status-dot"></span>Installing...');
                card.addClass('installing');
                
                var installPromise = p.type === 'premium'
                    ? this.doPremiumInstall(p.id, p.file, p.name)
                    : this.doInstall(p.id, p.file);
            } else {
                btn.text('Activating...').addClass('activating');
                statusBadge.removeClass().addClass('lfp-status-badge inactive').html('<span class="lfp-status-dot"></span>Activating...');
                card.addClass('activating');
                var installPromise = Promise.resolve();
            }
            
            installPromise.then(function() {
                if (needsInstall) {
                    btn.text('Activating...').removeClass('installing').addClass('activating');
                    card.removeClass('installing').addClass('activating');
                }
                return self.doActivate(p.file);
            })
            .then(function() {
                card.addClass('active').removeClass('activating installed');
                statusBadge.removeClass().addClass('lfp-status-badge active').html('<span class="lfp-status-dot"></span>Active');
                btn.removeClass('activating').text('✓ Active').data('action', 'active').prop('disabled', true);
                card.find('.lfp-plugin-checkbox').prop('checked', false).prop('disabled', true);
                self.toast('✅ ' + p.name + ' active!', 'success');
                self.updateCounts();
                self.updateProgress();
                setTimeout(function() { self.processBatch(list, index + 1); }, 250);
            })
            .catch(function(e) {
                card.addClass('error').removeClass('installing activating');
                statusBadge.removeClass().addClass('lfp-status-badge error').html('<span class="lfp-status-dot"></span>Failed');
                btn.prop('disabled', false).text('Retry').data('action', 'install').removeClass('installing activating');
                self.toast('❌ ' + p.name + ': ' + e.message, 'error');
                setTimeout(function() { self.processBatch(list, index + 1); }, 250);
            });
        },

        doPremiumInstall: function(id, file, name) {
            var pluginData = this.premiumPlugins.find(function(p) { return p.id === id; });
            return new Promise(function(resolve, reject) {
                $.ajax({
                    url: lfData.ajax,
                    type: 'POST',
                    timeout: 600000,
                    data: {
                        action: 'lf_premium_install',
                        plugin_id: id,
                        zip_url: pluginData ? pluginData.zip_url : '',
                        plugin_file: file,
                        plugin_name: name,
                        nonce: lfData.nonce
                    },
                    success: function(d) {
                        if (d.success) resolve();
                        else reject(new Error(d.data || 'Install failed'));
                    },
                    error: function(xhr) {
                        reject(new Error(xhr.responseText || 'Network error'));
                    }
                });
            });
        },

        doInstall: function(slug, file) {
            return new Promise(function(resolve, reject) {
                $.ajax({
                    url: lfData.ajax,
                    type: 'POST',
                    timeout: 60000,
                    data: {
                        action: 'lf_install',
                        slug: slug,
                        file: file,
                        nonce: lfData.nonce
                    },
                    success: function(d) {
                        if (d.success) resolve();
                        else reject(new Error(d.data || 'Install failed'));
                    },
                    error: function(xhr) {
                        reject(new Error(xhr.responseText || 'Network error'));
                    }
                });
            });
        },

        doActivate: function(file) {
            return new Promise(function(resolve, reject) {
                $.ajax({
                    url: lfData.ajax,
                    type: 'POST',
                    timeout: 30000,
                    data: {
                        action: 'lf_activate',
                        file: file,
                        nonce: lfData.nonce
                    },
                    success: function(d) {
                        if (d.success) resolve();
                        else reject(new Error(d.data || 'Activate failed'));
                    },
                    error: function(xhr) {
                        reject(new Error(xhr.responseText || 'Network error'));
                    }
                });
            });
        },

        refreshAllData: function() {
            this.loadFreePlugins();
            this.loadPremiumPlugins();
            this.checkCorePlugins();
        },

        updateProgress: function() {
            var self = this;
            var coreTotal = lfData.coreCount || 3;
            var activeCore = 0;
            
            this.freePlugins.forEach(function(p) {
                if (p.required && self.isPluginActive(p.file)) {
                    activeCore++;
                }
            });

            
            var percent = coreTotal > 0 ? Math.round((activeCore / coreTotal) * 100) : 100;
            $('#active-count').text(activeCore);
            $('#progress-percent').text(percent + '%');
            $('#progress-fill').css('width', percent + '%');
        },

        finishBatch: function() {
            this.isProcessing = false;
            $('.lfp-btn').prop('disabled', false);
            this.updateCounts();
            this.updateProgress();
            this.toast('✅ All selected plugins processed!', 'success');
        },

        toast: function(message, type) {
            var icons = { success: '✅', error: '❌', info: 'ℹ️', warning: '⚠️' };
            var toast = $(
                '<div class="lfp-toast ' + type + '">' +
                '<span>' + (icons[type] || '') + '</span>' +
                '<span>' + message + '</span>' +
                '<button class="lfp-toast-close">&times;</button>' +
                '</div>'
            );
            $('#lfp-toasts').prepend(toast);
            toast.find('.lfp-toast-close').on('click', function() {
                toast.fadeOut(200, function() { $(this).remove(); });
            });
            setTimeout(function() {
                toast.fadeOut(300, function() { toast.remove(); });
            }, 4000);
        }
    };

    LFP.init();
});