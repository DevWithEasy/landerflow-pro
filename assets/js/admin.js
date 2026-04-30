jQuery(function($) {
    'use strict';
    if (typeof lfData === 'undefined') {
        console.error('lfData missing');
        return;
    }

    var LFP = {
        isProcessing: false,
        coreTotal: lfData.core.length,
        freePlugins: [],
        premiumPlugins: [],
        templates: [],

        init: function() {
            this.loadFreePlugins();
            this.loadPremiumPlugins();
            this.loadTemplates();
            this.bindEvents();
            this.initNavigation();
            
            // Auto-trigger: show modal for core plugin installation
            if ($('.lfp-wrap').hasClass('auto-install')) {
                setTimeout(function() { LFP.showCoreInstallModal(); }, 800);
            }
        },

        showCoreInstallModal: function() {
            var self = this;
            var corePlugins = this.freePlugins.filter(function(p) { return p.required; });
            var uninstalled = corePlugins.filter(function(p) {
                return !self.isPluginActive(p.file);
            });
            
            if (uninstalled.length === 0) {
                this.toast('✅ All core plugins already active!', 'success');
                return;
            }
            
            // Create modal
            var modal = $(
                '<div class="lfp-preview-modal active" id="lfp-core-modal">' +
                '<div class="lfp-preview-overlay"></div>' +
                '<div class="lfp-preview-content" style="max-width:550px;">' +
                '<button class="lfp-preview-close">&times;</button>' +
                '<div class="lfp-preview-header">' +
                '<h2 class="lfp-preview-title">🚀 Install Core Plugins</h2>' +
                '<p class="lfp-preview-meta">The following ' + uninstalled.length + ' required plugin(s) need to be installed:</p>' +
                '</div>' +
                '<div style="padding:20px 24px;">' +
                '<div id="lfp-core-modal-list" style="display:flex;flex-direction:column;gap:10px;margin-bottom:16px;">' +
                uninstalled.map(function(p) {
                    return '<div class="lfp-core-modal-item" data-plugin="' + (p.id || p.slug) + '" data-file="' + (p.file || '') + '" style="padding:12px;border:1px solid var(--border);border-radius:8px;display:flex;align-items:center;gap:10px;">' +
                        '<span style="font-size:20px;">' + (p.icon || '📦') + '</span>' +
                        '<span style="flex:1;font-weight:600;">' + self.esc(p.name) + '</span>' +
                        '<span class="lfp-core-modal-status" style="font-size:12px;color:var(--muted);">⏳ Waiting...</span>' +
                    '</div>';
                }).join('') +
                '</div>' +
                '<div style="text-align:center;">' +
                '<button class="lfp-btn lfp-btn-primary" id="lfp-core-install-btn" style="width:100%;">' +
                '<span>⬇</span> Install & Activate All Core Plugins' +
                '</button>' +
                '<p style="font-size:12px;color:var(--muted);margin-top:8px;">The page will reload after all plugins are activated.</p>' +
                '</div>' +
                '</div>' +
                '</div></div>'
            );
            
            $('body').append(modal);
            
            // Close button
            modal.find('.lfp-preview-close').on('click', function() {
                modal.remove();
                LFP.toast('You can install core plugins anytime from Setup Wizard.', 'info');
            });
            
            // Install button
            modal.find('#lfp-core-install-btn').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true).text('⏳ Installing...');
                self.installCorePluginsSequentially(uninstalled, 0, modal);
            });
        },

        installCorePluginsSequentially: function(plugins, index, modal) {
            var self = this;
            if (index >= plugins.length) {
                // All done - reload
                modal.find('#lfp-core-modal-list').append(
                    '<div style="text-align:center;padding:10px;color:var(--green);font-weight:700;">✅ All core plugins installed & activated! Reloading...</div>'
                );
                setTimeout(function() { location.reload(); }, 1500);
                return;
            }
            
            var p = plugins[index];
            var item = modal.find('[data-plugin="' + (p.id || p.slug) + '"]');
            var statusEl = item.find('.lfp-core-modal-status');
            var id = p.id || p.slug;
            var file = p.file || '';
            
            // Step 1: Install
            statusEl.text('⬇ Installing...').css('color', 'var(--accent)');
            
            this.doInstall(id, file)
                .then(function() {
                    // Step 2: Activate
                    statusEl.text('⚡ Activating...').css('color', 'var(--accent)');
                    return self.doActivate(file);
                })
                .then(function() {
                    // Step 3: Done
                    statusEl.text('✅ Active').css('color', 'var(--green)');
                    item.css({
                        'border-color': 'var(--green)',
                        'background': 'rgba(63,185,80,.08)'
                    });
                    
                    // Small delay then next
                    setTimeout(function() {
                        self.installCorePluginsSequentially(plugins, index + 1, modal);
                    }, 600);
                })
                .catch(function(e) {
                    statusEl.text('❌ Failed: ' + e.message).css('color', 'var(--red)');
                    item.css('border-color', 'var(--red)');
                    // Continue anyway
                    setTimeout(function() {
                        self.installCorePluginsSequentially(plugins, index + 1, modal);
                    }, 600);
                });
        },

        initNavigation: function() {
            var self = this;
            $('.lfp-nav-item[data-section]').on('click', function(e) {
                e.preventDefault();
                var s = $(this).data('section');
                $('.lfp-nav-item').removeClass('active');
                $(this).addClass('active');
                $('.lfp-section').addClass('hidden');
                $('#section-' + s).removeClass('hidden');
                if (s === 'premium') self.renderPremiumCards();
            });
        },

        bindEvents: function() {
            var self = this;
            $('#lf-start').on('click', function() { self.runBulk(); });
            $('#lf-refresh').on('click', function() { self.loadFreePlugins(); });
            $('#lf-toggle').on('click', function() {
                var c = $('.lfp-plugin-checkbox:not(:disabled)');
                c.prop('checked', c.filter(':checked').length !== c.length);
            });
            $('#lf-premium-install-all').on('click', function() { self.installAllPremium(); });
            $('#lf-premium-refresh').on('click', function() { self.loadPremiumPlugins(); });
            $(document).on('click', '.lfp-btn-action', function(e) {
                e.preventDefault();
                self.handleAction($(this));
            });
        },

        // ===== LOAD DATA =====
        loadFreePlugins: function() {
            var self = this;
            $.ajax({
                url: lfData.ajax,
                type: 'POST',
                data: { action: 'lf_get_free_plugins', nonce: lfData.nonce },
                success: function(r) {
                    if (r.success) {
                        self.freePlugins = r.data.plugins;
                        $('#lfp-free-source').text('Source: ' + r.data.source + ' | ' + self.freePlugins.length + ' plugins');
                        self.renderFreeCards();
                        self.refreshStatus();
                    }
                },
                error: function() {
                    $('#lfp-core-plugins, #lfp-optional-plugins').html('<p style="color:var(--red)">❌ Failed to load</p>');
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
                        $('#lfp-premium-source').text('Source: ' + r.data.source + ' | ' + self.premiumPlugins.length + ' plugins');
                        self.renderPremiumCards();
                    }
                },
                error: function() {
                    $('#lfp-premium-plugins').html('<p style="color:var(--red)">❌ Failed to load</p>');
                }
            });
        },

        renderFreeCards: function() {
            var self = this;
            var coreGrid = $('#lfp-core-plugins');
            var optGrid = $('#lfp-optional-plugins');
            coreGrid.empty();
            optGrid.empty();

            this.freePlugins.forEach(function(p) {
                var ac = self.isPluginActive(p.file);
                var installed = self.isPluginInstalled(p.file);
                var card = self.createFreeCard(p, ac, installed);
                if (p.required) {
                    coreGrid.append(card);
                } else {
                    optGrid.append(card);
                }
            });

            this.updateProgressStats();
        },

        createFreeCard: function(p, ac, installed) {
            var stateClass = ac ? 'lfp-active' : 'lfp-pending';
            var pillClass = ac ? 'active' : 'pending';
            var pillText = ac ? 'Active' : (installed ? 'Inactive' : 'Not Installed');
            var action = ac ? 'active' : (installed ? 'activate' : 'install');
            var btnText = ac ? '✓ Active' : (installed ? 'Activate' : 'Install');
            var checked = ac ? 'disabled' : (p.required ? 'checked' : '');
            var id = p.id || p.slug;
            var slug = p.slug || p.id;
            var file = p.file || '';

            return '<div class="lfp-plugin-card ' + stateClass + '" data-plugin="' + id + '" data-type="free" data-core="' + (p.required ? '1' : '0') + '" data-file="' + file + '" data-slug="' + slug + '">' +
                '<div class="lfp-plugin-icon">' + (p.icon || '📦') + '</div>' +
                '<div class="lfp-plugin-info">' +
                '<div class="lfp-plugin-name"><label class="lfp-plugin-check-label"><input type="checkbox" class="lfp-plugin-checkbox" ' + checked + '>' + this.esc(p.name) + (p.required ? ' <span class="lfp-required-badge">Core</span>' : '') + '</label></div>' +
                '<div class="lfp-plugin-desc">' + this.esc(p.desc || '') + '</div>' +
                '<div class="lfp-plugin-tags"><span class="lfp-tag">WP.org</span><span class="lfp-tag">' + (p.required ? 'Required' : 'Optional') + '</span></div>' +
                '</div>' +
                '<div class="lfp-plugin-status-col">' +
                '<div class="lfp-status-pill ' + pillClass + '" id="pill-' + id + '"><span class="lfp-pill-dot"></span><span id="status-' + id + '">' + pillText + '</span></div>' +
                '<button class="lfp-btn-action" id="btn-' + id + '" data-slug="' + id + '" data-action="' + action + '" data-type="free" data-file="' + file + '" data-slug-wp="' + slug + '" ' + (ac ? 'disabled' : '') + '>' + btnText + '</button>' +
                '</div></div>';
        },

        renderPremiumCards: function() {
            var self = this;
            var grid = $('#lfp-premium-plugins');
            grid.empty();

            if (!this.premiumPlugins.length) {
                grid.html('<p style="color:var(--muted);text-align:center;padding:40px;">No premium plugins available</p>');
                return;
            }

            this.premiumPlugins.forEach(function(p) {
                var ac = self.isPluginActive(p.plugin_file);
                var installed = self.isPluginInstalled(p.plugin_file);
                var stateClass = ac ? 'lfp-active' : (installed ? 'lfp-pending' : 'lfp-premium-ready');
                var pillClass = ac ? 'active' : (installed ? 'pending' : 'premium');
                var pillText = ac ? 'Active' : (installed ? 'Inactive' : 'CDN');
                var action = ac ? 'active' : (installed ? 'activate' : 'install');
                var btnText = ac ? '✓ Active' : (installed ? 'Activate' : 'Install');
                var checked = ac ? 'disabled' : (p.required ? 'checked' : '');

                grid.append(
                    '<div class="lfp-plugin-card ' + stateClass + '" data-plugin="' + p.id + '" data-type="premium" data-core="' + (p.required ? '1' : '0') + '" data-file="' + (p.plugin_file || '') + '" data-zip="' + (p.zip_url || '') + '" data-name="' + self.esc(p.name) + '">' +
                    '<div class="lfp-plugin-icon">' + (p.icon || '💎') + '</div>' +
                    '<div class="lfp-plugin-info">' +
                    '<div class="lfp-plugin-name"><label class="lfp-plugin-check-label"><input type="checkbox" class="lfp-plugin-checkbox" ' + checked + '>' + self.esc(p.name) + (p.required ? ' <span class="lfp-required-badge">Required</span>' : '') + '</label></div>' +
                    '<div class="lfp-plugin-desc">' + self.esc(p.desc || '') + '</div>' +
                    '<div class="lfp-plugin-tags"><span class="lfp-tag">' + (p.category || 'Premium') + '</span><span class="lfp-tag">v' + (p.version || '1.0') + '</span><span class="lfp-tag">☁️ CDN</span></div>' +
                    '</div>' +
                    '<div class="lfp-plugin-status-col">' +
                    '<div class="lfp-status-pill ' + pillClass + '" id="pill-' + p.id + '"><span class="lfp-pill-dot"></span><span id="status-' + p.id + '">' + pillText + '</span></div>' +
                    '<button class="lfp-btn-action" id="btn-' + p.id + '" data-slug="' + p.id + '" data-action="' + action + '" data-type="premium" data-file="' + (p.plugin_file || '') + '" data-zip="' + (p.zip_url || '') + '" data-name="' + self.esc(p.name) + '" ' + (ac ? 'disabled' : '') + '>' + btnText + '</button>' +
                    '</div></div>'
                );
            });

            $('.lfp-nav-count').text(this.premiumPlugins.length);
            this.updatePremButton();
        },

        updatePremButton: function() {
            var self = this;
            var b = $('#lf-premium-install-all');
            var count = this.premiumPlugins.filter(function(p) {
                return !self.isPluginInstalled(p.plugin_file);
            }).length;
            b.text('⬇ Install All (' + count + ')').prop('disabled', count === 0);
        },

        // ===== PLUGIN CHECKS =====
        isPluginInstalled: function(file) {
            if (!file) return false;
            var card = $('[data-file="' + file + '"]').closest('.lfp-plugin-card');
            return card.hasClass('lfp-active') || card.hasClass('lfp-pending');
        },

        isPluginActive: function(file) {
            if (!file) return false;
            return $('[data-file="' + file + '"]').closest('.lfp-plugin-card').hasClass('lfp-active');
        },

        // ===== SINGLE PLUGIN ACTION (NO RELOAD) =====
        handleAction: function(b) {
            if (b.is(':disabled') || this.isProcessing) return;
            var self = this;
            var slug = b.data('slug');
            var action = b.data('action');
            var type = b.data('type') || 'free';
            var file = b.data('file') || '';
            var zipUrl = b.data('zip') || '';
            var name = b.data('name') || 'Plugin';

            b.prop('disabled', true);
            var pillEl = $('#pill-' + slug);
            var statusEl = $('#status-' + slug);

            if (action === 'install') {
                // Update UI: Installing
                b.text(type === 'premium' ? '☁️ Downloading...' : '⬇ Installing...');
                pillEl.removeClass().addClass('lfp-status-pill installing');
                statusEl.text('Installing...');
                
                var installPromise = type === 'premium' ? 
                    this.doPremiumInstall(slug, zipUrl, file, name) : 
                    this.doInstall(slug, file);

                installPromise
                    .then(function() {
                        // Update UI: Activating
                        b.text('⚡ Activating...');
                        pillEl.removeClass().addClass('lfp-status-pill activating');
                        statusEl.text('Activating...');
                        return self.doActivate(file);
                    })
                    .then(function() {
                        // Success - Update UI without reload
                        self.updateSingleCardUI(slug, true);
                        self.toast('✅ ' + name + ' is now active!', 'success');
                        self.updateProgressStats();
                    })
                    .catch(function(e) {
                        // Error
                        b.prop('disabled', false).text('Retry').data('action', 'install');
                        pillEl.removeClass().addClass('lfp-status-pill error');
                        statusEl.text('Failed');
                        self.toast('❌ ' + e.message, 'error');
                    });
                    
            } else if (action === 'activate') {
                // Update UI: Activating
                b.text('⚡ Activating...');
                pillEl.removeClass().addClass('lfp-status-pill activating');
                statusEl.text('Activating...');
                
                this.doActivate(file)
                    .then(function() {
                        // Success
                        self.updateSingleCardUI(slug, true);
                        self.toast('✅ ' + name + ' is now active!', 'success');
                        self.updateProgressStats();
                    })
                    .catch(function(e) {
                        b.prop('disabled', false).text('Retry').data('action', 'activate');
                        pillEl.removeClass().addClass('lfp-status-pill error');
                        statusEl.text('Failed');
                        self.toast('❌ ' + e.message, 'error');
                    });
            }
        },

        // Update single card UI without reload
        updateSingleCardUI: function(slug, active) {
            var card = $('[data-plugin="' + slug + '"]');
            var pillEl = $('#pill-' + slug);
            var statusEl = $('#status-' + slug);
            var btn = $('#btn-' + slug);
            var checkbox = card.find('.lfp-plugin-checkbox');
            
            card.removeClass('lfp-pending lfp-premium-ready lfp-installing lfp-activating lfp-error');
            card.addClass('lfp-active');
            
            pillEl.removeClass().addClass('lfp-status-pill active');
            statusEl.text('Active');
            
            btn.data('action', 'active').prop('disabled', true).text('✓ Active');
            checkbox.prop('checked', false).prop('disabled', true);
        },

        // ===== BULK OPERATIONS =====
        runBulk: function() {
            var self = this;
            var sel = [];
            $('.lfp-plugin-checkbox:checked').each(function() {
                var c = $(this).closest('.lfp-plugin-card');
                if (!c.hasClass('lfp-active')) {
                    sel.push({
                        id: c.data('plugin'),
                        type: c.data('type'),
                        file: c.data('file'),
                        zip: c.data('zip'),
                        name: c.find('.lfp-plugin-name').text().trim()
                    });
                }
            });
            if (!sel.length) return this.toast('All selected plugins are already active.', 'info');
            if (!confirm(lfData.texts.confirm_bulk)) return;
            this.isProcessing = true;
            $('.lfp-btn, .lfp-btn-action, input[type="checkbox"]').prop('disabled', true);
            this.processBulk(sel, 0);
        },

        processBulk: function(list, i) {
            var self = this;
            if (i >= list.length) {
                this.finishBulk();
                return;
            }

            var p = list[i];
            var b = $('[data-plugin="' + p.id + '"]').find('.lfp-btn-action');
            var needsInstall = b.data('action') === 'install';
            var pillEl = $('#pill-' + p.id);
            var statusEl = $('#status-' + p.id);

            b.text(needsInstall ? (p.type === 'premium' ? '☁️ Downloading...' : '⬇ Installing...') : '⚡ Activating...');
            pillEl.removeClass().addClass(needsInstall ? 'lfp-status-pill installing' : 'lfp-status-pill activating');
            statusEl.text(needsInstall ? 'Installing...' : 'Activating...');
            b.prop('disabled', true);

            var installPromise = needsInstall ?
                (p.type === 'premium' ? this.doPremiumInstall(p.id, p.zip, p.file, p.name) : this.doInstall(p.id, p.file)) :
                Promise.resolve();

            installPromise
                .then(function() {
                    if (needsInstall) {
                        b.text('⚡ Activating...');
                        pillEl.removeClass().addClass('lfp-status-pill activating');
                        statusEl.text('Activating...');
                    }
                    return self.doActivate(p.file);
                })
                .then(function() {
                    self.updateSingleCardUI(p.id, true);
                    self.toast('✅ ' + p.name + ' active!', 'success');
                    self.updateProgressStats();
                    setTimeout(function() { self.processBulk(list, i + 1); }, 500);
                })
                .catch(function(e) {
                    b.text('Retry').prop('disabled', false);
                    pillEl.removeClass().addClass('lfp-status-pill error');
                    statusEl.text('Failed');
                    self.toast('❌ ' + p.name + ': ' + e.message, 'error');
                    setTimeout(function() { self.processBulk(list, i + 1); }, 500);
                });
        },

        // ===== AJAX CALLS =====
        doPremiumInstall: function(id, zipUrl, file, name) {
            return new Promise(function(resolve, reject) {
                $.ajax({
                    url: lfData.ajax,
                    type: 'POST',
                    timeout: 600000,
                    data: {
                        action: 'lf_premium_install',
                        plugin_id: id,
                        zip_url: zipUrl,
                        plugin_file: file,
                        plugin_name: name,
                        nonce: lfData.nonce
                    },
                    success: function(d) {
                        if (d.success) resolve();
                        else reject(new Error(d.data || 'Install failed'));
                    },
                    error: function() {
                        reject(new Error('Network error'));
                    }
                });
            });
        },

        doInstall: function(slug, file) {
            return new Promise(function(resolve, reject) {
                $.ajax({
                    url: lfData.ajax,
                    type: 'POST',
                    timeout: 30000,
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
                    error: function() {
                        reject(new Error('Network error'));
                    }
                });
            });
        },

        doActivate: function(file) {
            return new Promise(function(resolve, reject) {
                $.ajax({
                    url: lfData.ajax,
                    type: 'POST',
                    timeout: 15000,
                    data: {
                        action: 'lf_activate',
                        file: file,
                        nonce: lfData.nonce
                    },
                    success: function(d) {
                        if (d.success) resolve();
                        else reject(new Error(d.data || 'Activate failed'));
                    },
                    error: function() {
                        reject(new Error('Network error'));
                    }
                });
            });
        },

        installAllPremium: function() {
            var self = this;
            var toInstall = this.premiumPlugins.filter(function(p) {
                return !self.isPluginInstalled(p.plugin_file);
            });
            if (!toInstall.length) return this.toast('All premium plugins are already installed.', 'info');
            if (!confirm('Install ' + toInstall.length + ' premium plugins from CDN? This may take a few minutes.')) return;

            this.isProcessing = true;
            $('.lfp-btn, .lfp-btn-action').prop('disabled', true);
            this.toast('☁️ Downloading from CDN...', 'info');

            $.ajax({
                url: lfData.ajax,
                type: 'POST',
                timeout: 600000,
                data: { action: 'lf_premium_bulk_install', nonce: lfData.nonce },
                success: function(r) {
                    self.isProcessing = false;
                    $('.lfp-btn, .lfp-btn-action').prop('disabled', false);
                    self.toast(r.success ? '✅ All premium plugins installed!' : '⚠️ Some plugins failed.', r.success ? 'success' : 'warning');
                    self.loadPremiumPlugins();
                },
                error: function() {
                    self.isProcessing = false;
                    $('.lfp-btn, .lfp-btn-action').prop('disabled', false);
                    self.toast('❌ Bulk install failed.', 'error');
                }
            });
        },

        refreshStatus: function() {
            var self = this;
            $.ajax({
                url: lfData.ajax,
                type: 'POST',
                data: { action: 'lf_status', nonce: lfData.nonce },
                success: function(r) {
                    if (!r.success) return;
                    var d = 0;
                    $.each(r.data, function(s, v) {
                        if (v.active) d++;
                        var c = $('[data-plugin="' + s + '"]');
                        if (c.length) {
                            if (v.active) {
                                c.removeClass('lfp-pending lfp-premium-ready').addClass('lfp-active');
                                $('#pill-' + s).removeClass().addClass('lfp-status-pill active');
                                $('#status-' + s).text('Active');
                                $('#btn-' + s).data('action', 'active').prop('disabled', true).text('✓ Active');
                                c.find('.lfp-plugin-checkbox').prop('checked', false).prop('disabled', true);
                            } else if (v.installed) {
                                c.removeClass('lfp-active lfp-premium-ready').addClass('lfp-pending');
                                $('#pill-' + s).removeClass().addClass('lfp-status-pill pending');
                                $('#status-' + s).text('Inactive');
                                $('#btn-' + s).data('action', 'activate').prop('disabled', false).text('Activate');
                            }
                        }
                    });
                    $('.stat-completed').text(d);
                    self.updateProgressStats();
                }
            });
        },

        updateProgressStats: function() {
            var self = this;
            var d = 0;
            this.freePlugins.forEach(function(p) {
                if (p.required && self.isPluginActive(p.file)) d++;
            });
            var pct = Math.round((d / this.coreTotal) * 100);
            $('.stat-completed').text(d);
            $('#lfp-progress-fill').css('width', pct + '%');
            $('#lfp-progress-pct').text(pct + '%');
            $('#lfp-progress-label').text(pct >= 100 ? 'Core Setup Complete 🎉' : d + '/' + this.coreTotal + ' Core Ready');
            
            if (d >= this.coreTotal) {
                $('#lfp-warning').addClass('hidden');
            } else {
                $('#lfp-warning').removeClass('hidden');
            }
        },

        // ===== TEMPLATES =====
        loadTemplates: function() {
            var self = this;
            $.ajax({
                url: lfData.ajax,
                type: 'POST',
                data: { action: 'lf_get_templates', nonce: lfData.nonce },
                success: function(r) {
                    if (r.success && r.data.templates) {
                        self.templates = r.data.templates;
                        $('#lfp-template-source').text('Source: ' + r.data.source + ' | ' + r.data.templates.length + ' templates');
                        self.renderTemplateCards();
                    }
                }
            });
        },

        renderTemplateCards: function() {
            var self = this;
            var g = $('#lfp-tl-grid');
            g.empty();

            if (!this.templates.length) {
                g.html('<div class="lfp-tl-empty"><div class="lfp-tl-empty-icon">📭</div><h3>No templates</h3></div>');
                return;
            }

            var cats = [];
            var seen = {};
            this.templates.forEach(function(t) {
                if (!seen[t.category]) {
                    seen[t.category] = true;
                    cats.push(t.category);
                }
            });

            var filterHtml = '<button class="lfp-tl-filter-btn active" data-filter="all">🗂️ All</button>';
            cats.forEach(function(c) {
                filterHtml += '<button class="lfp-tl-filter-btn" data-filter="' + c.toLowerCase().replace(/\s+/g, '-') + '">' + self.esc(c) + '</button>';
            });
            $('#lfp-tl-filters').html(filterHtml);

            this.templates.forEach(function(f) {
                var st = f.steps + ' ' + (f.steps > 1 ? 'Steps' : 'Step');
                var tagsHtml = '';
                (f.tags || []).forEach(function(t) {
                    tagsHtml += '<span class="lfp-tl-tag">' + self.esc(t) + '</span>';
                });

                var card = $(
                    '<div class="lfp-tl-card ' + (f.featured ? 'lfp-tl-featured' : '') + '" data-category="' + (f.category || '').toLowerCase().replace(/\s+/g, '-') + '" data-search="' + (f.title + ' ' + ((f.tags || []).join(' '))).toLowerCase() + '">' +
                    '<div class="lfp-tl-card-img">' +
                    '<img src="' + f.preview_img + '" alt="' + self.esc(f.title) + '" loading="lazy">' +
                    '<div class="lfp-tl-card-overlay">' +
                    '<button class="lfp-tl-preview-btn" data-title="' + self.esc(f.title) + '" data-img="' + f.preview_img + '" data-steps="' + st + '" data-desc="' + self.esc(f.desc) + '">🔍 Preview</button>' +
                    '<button class="lfp-tl-download-btn-overlay" data-json="' + f.json_url + '" data-title="' + self.esc(f.title) + '">📥 Download JSON</button>' +
                    '</div>' +
                    '<span class="lfp-tl-type-badge">' + (f.type === 'funnel' ? '🔄 Funnel' : '📄 Template') + '</span>' +
                    (f.featured ? '<span class="lfp-tl-featured-badge">⭐ Featured</span>' : '') +
                    '</div>' +
                    '<div class="lfp-tl-card-info">' +
                    '<h3>' + self.esc(f.title) + '</h3>' +
                    '<p>' + self.esc(f.desc) + '</p>' +
                    '<div class="lfp-tl-card-meta"><span>📋 ' + st + '</span><span>🏷️ ' + self.esc(f.category) + '</span></div>' +
                    '<div class="lfp-tl-tags">' + tagsHtml + '</div>' +
                    '</div>' +
                    '<div class="lfp-tl-card-footer">' +
                    '<button class="lfp-btn lfp-btn-primary lfp-tl-footer-btn lfp-tl-download-btn-footer" data-json="' + f.json_url + '">📥 Download JSON</button>' +
                    '<button class="lfp-btn lfp-btn-ghost lfp-tl-footer-btn lfp-tl-preview-btn" data-title="' + self.esc(f.title) + '" data-img="' + f.preview_img + '" data-steps="' + st + '" data-desc="' + self.esc(f.desc) + '">🔍 Preview</button>' +
                    '</div>' +
                    '</div>'
                );
                g.append(card);
            });

            this.initTemplateEvents();
        },

        initTemplateEvents: function() {
            var self = this;
            var tm;

            $('#lfp-tl-search').on('input', function() {
                clearTimeout(tm);
                tm = setTimeout(function() {
                    self.filterTemplates($('#lfp-tl-search').val().toLowerCase().trim());
                }, 300);
            });

            $(document).on('click', '.lfp-tl-filter-btn', function() {
                $('.lfp-tl-filter-btn').removeClass('active');
                $(this).addClass('active');
                self.filterByCategory($(this).data('filter'));
            });

            $(document).on('click', '.lfp-tl-preview-btn', function(e) {
                e.preventDefault();
                var d = $(this);
                self.openPreviewModal({
                    title: d.data('title'),
                    img: d.data('img'),
                    steps: d.data('steps'),
                    desc: d.data('desc')
                });
            });

            $(document).on('click', '.lfp-tl-download-btn-footer, .lfp-tl-download-btn-overlay', function(e) {
                e.preventDefault();
                self.downloadJSON($(this));
            });

            $(document).on('click', '.lfp-tl-clear-search', function() {
                $('#lfp-tl-search').val('').trigger('input');
                $('.lfp-tl-filter-btn[data-filter="all"]').trigger('click');
            });
        },

        filterTemplates: function(q) {
            var f = $('.lfp-tl-filter-btn.active').data('filter') || 'all';
            var v = 0;

            $('.lfp-tl-card').each(function() {
                var c = $(this);
                var match = (!q || (c.data('search') || '').indexOf(q) !== -1) && (f === 'all' || c.data('category') === f);
                if (match) {
                    c.removeClass('hidden').fadeIn(200);
                    v++;
                } else {
                    c.addClass('hidden').fadeOut(200);
                }
            });

            if (v > 0) {
                $('.lfp-tl-empty').addClass('hidden');
            } else {
                $('.lfp-tl-empty').removeClass('hidden');
            }
        },

        filterByCategory: function(f) {
            this.filterTemplates($('#lfp-tl-search').val().toLowerCase().trim());
        },

        openPreviewModal: function(d) {
            $('#lfp-preview-modal').remove();
            var m = $(
                '<div class="lfp-preview-modal active" id="lfp-preview-modal">' +
                '<div class="lfp-preview-overlay"></div>' +
                '<div class="lfp-preview-content">' +
                '<button class="lfp-preview-close">&times;</button>' +
                '<div class="lfp-preview-header"><h2>' + this.esc(d.title) + '</h2><p class="lfp-preview-meta"><span>📋 ' + this.esc(d.steps) + '</span><span>' + this.esc(d.desc) + '</span></p></div>' +
                '<div class="lfp-preview-image"><img src="' + d.img + '" class="lfp-preview-img" style="width:100%;"></div>' +
                '</div></div>'
            );
            $('body').append(m);

            var close = function() {
                m.removeClass('active');
                setTimeout(function() { m.remove(); }, 300);
                $(document).off('keydown.previewModal');
            };

            $('.lfp-preview-overlay, .lfp-preview-close').on('click', close);
            $(document).on('keydown.previewModal', function(e) {
                if (e.key === 'Escape') close();
            });
        },

        downloadJSON: function(b) {
            if (this.isProcessing) return;
            var self = this;
            var u = b.data('json');
            var ot = b.text();

            this.isProcessing = true;
            b.prop('disabled', true).text('⏳ Downloading...');

            $.ajax({
                url: lfData.ajax,
                type: 'POST',
                timeout: 60000,
                data: { action: 'lf_download_json', json_url: u, nonce: lfData.nonce },
                success: function(r) {
                    self.isProcessing = false;
                    b.prop('disabled', false).text(ot);
                    if (r.success && r.data.content) {
                        var bs = atob(r.data.content);
                        var ab = new ArrayBuffer(bs.length);
                        var ia = new Uint8Array(ab);
                        for (var i = 0; i < bs.length; i++) { ia[i] = bs.charCodeAt(i); }
                        var blob = new Blob([ab], { type: 'application/json' });
                        var url = window.URL.createObjectURL(blob);
                        var a = document.createElement('a');
                        a.href = url;
                        a.download = r.data.filename || 'funnel-template.json';
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        window.URL.revokeObjectURL(url);
                        self.toast('✅ JSON downloaded! Import via CartFlows.', 'success');
                    } else {
                        window.open(u, '_blank');
                        self.toast('📥 JSON opened in new tab.', 'info');
                    }
                },
                error: function() {
                    self.isProcessing = false;
                    b.prop('disabled', false).text(ot);
                    window.open(u, '_blank');
                    self.toast('📥 JSON opened in new tab.', 'info');
                }
            });
        },

        finishBulk: function() {
            this.isProcessing = false;
            $('.lfp-btn, .lfp-btn-action, input[type="checkbox"]').prop('disabled', false);
            this.updateProgressStats();
            this.toast('✅ All selected plugins processed!', 'success');
        },

        finishReload: function() {
            this.isProcessing = false;
            this.toast('🎉 Core plugins ready! Reloading...', 'success');
            setTimeout(function() { location.reload(); }, 1500);
        },

        toast: function(m, t) {
            var icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
            var el = $(
                '<div class="lfp-toast ' + t + '">' +
                '<span>' + (icons[t] || '') + '</span>' +
                '<span>' + m + '</span>' +
                '<button class="lfp-toast-close">&times;</button>' +
                '</div>'
            );
            $('#lfp-notification-area').prepend(el);
            el.find('.lfp-toast-close').on('click', function() {
                el.fadeOut(300, function() { el.remove(); });
            });
            setTimeout(function() {
                el.fadeOut(400, function() { el.remove(); });
            }, t === 'success' ? 6000 : 4000);
        },

        esc: function(t) {
            var d = document.createElement('div');
            d.textContent = t;
            return d.innerHTML;
        }
    };

    // Initialize
    LFP.init();
});