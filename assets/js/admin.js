jQuery(function($) {
    'use strict';
    if (typeof lfData === 'undefined') { console.error('lfData missing'); return; }

    var LFP = {
        isProcessing: false,
        coreTotal: lfData.core.length,
        freePlugins: [],
        premiumPlugins: [],

        init: function() {
            this.loadFreePlugins();
            this.loadPremiumPlugins();
            this.bindEvents();
            this.initCoreInstallModal();
        },

        initCoreInstallModal: function() {
            var self = this;
            if ($('.lfp-wrap').hasClass('auto-install')) {
                setTimeout(function() { self.showCoreInstallModal(); }, 500);
            }
        },

        showCoreInstallModal: function() {
            var self = this;
            var corePlugins = this.freePlugins.filter(function(p) { return p.required; });
            var uninstalled = corePlugins.filter(function(p) { return !self.isPluginActive(p.file); });
            
            if (uninstalled.length === 0) { this.toast('✅ All core plugins already active!', 'success'); return; }
            
            var modal = $(
                '<div class="lfp-preview-modal active" id="lfp-core-modal">' +
                '<div class="lfp-preview-overlay"></div>' +
                '<div class="lfp-preview-content">' +
                '<button class="lfp-preview-close">&times;</button>' +
                '<div class="lfp-preview-header"><h2 class="lfp-preview-title">🚀 Install Core Plugins</h2><p class="lfp-preview-meta">' + uninstalled.length + ' required plugin(s) pending:</p></div>' +
                '<div class="lfp-preview-body" id="lfp-core-modal-list">' +
                uninstalled.map(function(p) {
                    return '<div class="lfp-core-modal-item" data-plugin="' + (p.id || p.slug) + '">' +
                        '<span style="font-size:18px;">' + (p.icon || '📦') + '</span>' +
                        '<span style="flex:1;font-weight:600;font-size:13px;">' + self.esc(p.name) + '</span>' +
                        '<span class="lfp-core-modal-status">⏳ Waiting</span>' +
                    '</div>';
                }).join('') +
                '</div>' +
                '<div class="lfp-preview-footer">' +
                '<button class="lfp-btn lfp-btn-primary" id="lfp-core-install-btn" style="width:100%;">⬇ Install & Activate All</button>' +
                '<p style="font-size:11px;color:var(--muted);margin-top:6px;">No page reload needed.</p>' +
                '</div></div></div>'
            );
            $('body').append(modal);
            modal.find('.lfp-preview-close, .lfp-preview-overlay').on('click', function() { modal.remove(); self.toast('Install core plugins anytime from the list.', 'info'); });
            modal.find('#lfp-core-install-btn').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true).html('<span class="lfp-spin">⏳</span> Installing...');
                self.installCorePluginsSequentially(uninstalled, 0, modal);
            });
        },

        installCorePluginsSequentially: function(plugins, index, modal) {
            var self = this;
            if (index >= plugins.length) {
                modal.find('#lfp-core-modal-list').append('<div style="text-align:center;padding:8px;color:var(--green);font-weight:700;font-size:13px;">✅ All core plugins ready!</div>');
                setTimeout(function() { modal.remove(); self.refreshAllStatus(); }, 1000);
                return;
            }
            var p = plugins[index], item = modal.find('[data-plugin="' + (p.id || p.slug) + '"]'), statusEl = item.find('.lfp-core-modal-status'), id = p.id || p.slug, file = p.file || '';
            statusEl.text('⬇ Installing...').css('color', 'var(--accent)'); item.css('border-color', 'var(--accent)');
            this.doInstall(id, file).then(function() {
                statusEl.text('⚡ Activating...'); return self.doActivate(file);
            }).then(function() {
                statusEl.text('✅ Active').css('color', 'var(--green)'); item.css({'border-color': 'var(--green)', 'background': 'rgba(63,185,80,.06)'});
                self.updateCardUI(id, true);
                setTimeout(function() { self.installCorePluginsSequentially(plugins, index + 1, modal); }, 350);
            }).catch(function(e) {
                statusEl.text('❌ Failed').css('color', 'var(--red)'); item.css('border-color', 'var(--red)');
                self.toast('❌ ' + p.name + ': ' + e.message, 'error');
                setTimeout(function() { self.installCorePluginsSequentially(plugins, index + 1, modal); }, 350);
            });
        },

        bindEvents: function() {
            var self = this;
            $('#lf-start').on('click', function() { self.runBulk(); });
            $('#lf-refresh').on('click', function() { self.refreshAllStatus(); });
            $('#lf-toggle').on('click', function() {
                var c = $('.lfp-plugin-checkbox:not(:disabled)');
                c.prop('checked', c.filter(':checked').length !== c.length);
                self.updatePremInstallCount(); // Update count when toggling
            });
            // Update count when any checkbox changes
            $(document).on('change', '.lfp-plugin-checkbox[data-type="premium"]', function() { self.updatePremInstallCount(); });
            $('#lf-premium-install-all').on('click', function() { self.installCheckedPremium(); });
            $('#lf-premium-refresh').on('click', function() { self.loadPremiumPlugins(); });
            $(document).on('click', '.lfp-btn-action', function(e) {
                e.preventDefault(); if ($(this).is(':disabled') || self.isProcessing) return; self.handleSingleAction($(this));
            });
            $(document).on('click', '.lfp-preview-close, .lfp-preview-overlay', function() { $(this).closest('.lfp-preview-modal').remove(); });
            $(document).on('keydown', function(e) { if (e.key === 'Escape') $('.lfp-preview-modal').remove(); });
        },

        loadFreePlugins: function() {
            var self = this;
            $.ajax({ url: lfData.ajax, type: 'POST', data: { action: 'lf_get_free_plugins', nonce: lfData.nonce },
                success: function(r) { if (r.success) { self.freePlugins = r.data.plugins; $('#lfp-free-source').text((r.data.source === 'live' ? '🌐' : r.data.source === 'cache' ? '💾' : '📦') + ' ' + self.freePlugins.length + ' plugins'); self.renderFreeCards(); }},
                error: function() { $('#lfp-core-plugins, #lfp-optional-plugins').html('<p style="color:var(--red);font-size:12px;">❌ Failed to load</p>'); }
            });
        },

        loadPremiumPlugins: function() {
            var self = this;
            $.ajax({ url: lfData.ajax, type: 'POST', data: { action: 'lf_get_premium_plugins', nonce: lfData.nonce },
                success: function(r) { if (r.success) { self.premiumPlugins = r.data.plugins; $('#lfp-premium-source').text((r.data.source === 'live' ? '🌐' : r.data.source === 'cache' ? '💾' : '📦') + ' ' + self.premiumPlugins.length + ' plugins'); self.renderPremiumCards(); self.updatePremInstallCount(); }},
                error: function() { $('#lfp-premium-plugins').html('<p style="color:var(--red);font-size:12px;">❌ Failed to load</p>'); }
            });
        },

        renderFreeCards: function() {
            var self = this, coreGrid = $('#lfp-core-plugins'), optGrid = $('#lfp-optional-plugins');
            coreGrid.empty(); optGrid.empty();
            var hasCore = false, hasOpt = false;
            this.freePlugins.forEach(function(p) {
                var ac = self.isPluginActive(p.file), installed = self.isPluginInstalled(p.file), card = self.createFreeCard(p, ac, installed);
                if (p.required) { coreGrid.append(card); hasCore = true; } else { optGrid.append(card); hasOpt = true; }
            });
            if (!hasCore) coreGrid.html('<div class="lfp-empty-state"><div class="lfp-empty-icon">📭</div><p>No core plugins</p></div>');
            if (!hasOpt) optGrid.html('<div class="lfp-empty-state"><div class="lfp-empty-icon">📭</div><p>No optional plugins</p></div>');
            this.updateProgressStats();
        },

        createFreeCard: function(p, ac, installed) {
            var stateClass = ac ? 'lfp-active' : (installed ? 'lfp-pending' : ''), pillClass = ac ? 'active' : (installed ? 'pending' : ''), pillText = ac ? 'Active' : (installed ? 'Inactive' : 'Not Installed'), action = ac ? 'active' : (installed ? 'activate' : 'install'), btnText = ac ? '✓ Active' : (installed ? 'Activate' : 'Install'), checked = ac ? 'disabled' : (p.required ? 'checked' : ''), id = p.id || p.slug, slug = p.slug || p.id, file = p.file || '';
            return '<div class="lfp-plugin-card ' + stateClass + '" data-plugin="' + id + '" data-type="free" data-core="' + (p.required ? '1' : '0') + '" data-file="' + file + '" data-slug="' + slug + '">' +
                '<div class="lfp-plugin-icon">' + (p.icon || '📦') + '</div><div class="lfp-plugin-info">' +
                '<div class="lfp-plugin-name"><label class="lfp-plugin-check-label"><input type="checkbox" class="lfp-plugin-checkbox" ' + checked + ' data-file="' + file + '" data-type="free"> ' + this.esc(p.name) + (p.required ? ' <span class="lfp-required-badge">Core</span>' : '') + '</label></div>' +
                '<div class="lfp-plugin-desc">' + this.esc(p.desc || '') + '</div><div class="lfp-plugin-tags"><span class="lfp-tag">WP.org</span><span class="lfp-tag">' + (p.required ? 'Required' : 'Optional') + '</span></div></div>' +
                '<div class="lfp-plugin-status-col"><div class="lfp-status-pill ' + pillClass + '" id="pill-' + id + '"><span class="lfp-pill-dot"></span><span id="status-' + id + '">' + pillText + '</span></div><button class="lfp-btn-action" id="btn-' + id + '" data-slug="' + id + '" data-action="' + action + '" data-type="free" data-file="' + file + '" data-slug-wp="' + slug + '" ' + (ac ? 'disabled' : '') + '>' + btnText + '</button></div></div>';
        },

        renderPremiumCards: function() {
            var self = this, grid = $('#lfp-premium-plugins'); grid.empty();
            if (!this.premiumPlugins.length) { grid.html('<div class="lfp-empty-state"><div class="lfp-empty-icon">💎</div><p>No premium plugins</p></div>'); return; }
            this.premiumPlugins.forEach(function(p) {
                var ac = self.isPluginActive(p.plugin_file), installed = self.isPluginInstalled(p.plugin_file), stateClass = ac ? 'lfp-active' : (installed ? 'lfp-pending' : 'lfp-premium-ready'), pillClass = ac ? 'active' : (installed ? 'pending' : 'premium'), pillText = ac ? 'Active' : (installed ? 'Inactive' : 'CDN'), action = ac ? 'active' : (installed ? 'activate' : 'install'), btnText = ac ? '✓ Active' : (installed ? 'Activate' : 'Install'), checked = ac ? 'disabled' : '';
                grid.append('<div class="lfp-plugin-card ' + stateClass + '" data-plugin="' + p.id + '" data-type="premium" data-file="' + (p.plugin_file || '') + '" data-zip="' + (p.zip_url || '') + '" data-name="' + self.esc(p.name) + '">' +
                    '<div class="lfp-plugin-icon">' + (p.icon || '💎') + '</div><div class="lfp-plugin-info">' +
                    '<div class="lfp-plugin-name"><label class="lfp-plugin-check-label"><input type="checkbox" class="lfp-plugin-checkbox" ' + checked + ' data-file="' + (p.plugin_file || '') + '" data-type="premium"> ' + self.esc(p.name) + (p.required ? ' <span class="lfp-required-badge">Required</span>' : '') + '</label></div>' +
                    '<div class="lfp-plugin-desc">' + self.esc(p.desc || '') + '</div><div class="lfp-plugin-tags"><span class="lfp-tag">' + (p.category || 'Premium') + '</span><span class="lfp-tag">v' + (p.version || '1.0') + '</span><span class="lfp-tag">☁️</span></div></div>' +
                    '<div class="lfp-plugin-status-col"><div class="lfp-status-pill ' + pillClass + '" id="pill-' + p.id + '"><span class="lfp-pill-dot"></span><span id="status-' + p.id + '">' + pillText + '</span></div><button class="lfp-btn-action" id="btn-' + p.id + '" data-slug="' + p.id + '" data-action="' + action + '" data-type="premium" data-file="' + (p.plugin_file || '') + '" data-zip="' + (p.zip_url || '') + '" data-name="' + self.esc(p.name) + '" ' + (ac ? 'disabled' : '') + '>' + btnText + '</button></div></div>');
            });
        },

        // ✅ Update: Count only CHECKED & not-yet-installed premium plugins
        updatePremInstallCount: function() {
            var self = this;
            var count = this.premiumPlugins.filter(function(p) {
                var file = p.plugin_file || '';
                var isInstalled = self.isPluginInstalled(file);
                var isChecked = $('[data-file="' + file + '"][data-type="premium"]').find('.lfp-plugin-checkbox').is(':checked');
                return !isInstalled && isChecked;
            }).length;
            $('#lf-premium-install-all').html('<span>⬇</span> Install Selected (' + count + ')').prop('disabled', count === 0 || self.isProcessing);
        },

        isPluginInstalled: function(file) { if (!file) return false; var c = $('[data-file="' + file + '"]').closest('.lfp-plugin-card'); return c.hasClass('lfp-active') || c.hasClass('lfp-pending'); },
        isPluginActive: function(file) { if (!file) return false; return $('[data-file="' + file + '"]').closest('.lfp-plugin-card').hasClass('lfp-active'); },

        handleSingleAction: function(b) {
            if (b.is(':disabled') || this.isProcessing) return;
            var self = this, slug = b.data('slug'), action = b.data('action'), type = b.data('type') || 'free', file = b.data('file') || '', zipUrl = b.data('zip') || '', name = b.data('name') || 'Plugin', card = b.closest('.lfp-plugin-card'), checkbox = card.find('.lfp-plugin-checkbox');
            b.prop('disabled', true); var pillEl = $('#pill-' + slug), statusEl = $('#status-' + slug), originalText = b.text();
            if (action === 'install') {
                b.html('<span class="lfp-spin">⏳</span>' + (type === 'premium' ? ' Download' : ' Install')); pillEl.removeClass().addClass('lfp-status-pill installing'); statusEl.text('Installing...'); card.addClass('lfp-installing');
                var installPromise = type === 'premium' ? this.doPremiumInstall(slug, zipUrl, file, name) : this.doInstall(slug, file);
                installPromise.then(function() { b.html('<span class="lfp-spin">⚡</span> Activate'); pillEl.removeClass().addClass('lfp-status-pill activating'); statusEl.text('Activating...'); return self.doActivate(file); })
                .then(function() { self.updateCardUI(slug, true); checkbox.prop('checked', false).prop('disabled', true); self.toast('✅ ' + name + ' active!', 'success'); self.updateProgressStats(); if (type === 'premium') self.updatePremInstallCount(); })
                .catch(function(e) { b.prop('disabled', false).text('Retry').data('action', 'install'); pillEl.removeClass().addClass('lfp-status-pill error'); statusEl.text('Failed'); card.removeClass('lfp-installing'); self.toast('❌ ' + e.message, 'error'); });
            } else if (action === 'activate') {
                b.html('<span class="lfp-spin">⚡</span> Activate'); pillEl.removeClass().addClass('lfp-status-pill activating'); statusEl.text('Activating...'); card.addClass('lfp-activating');
                this.doActivate(file).then(function() { self.updateCardUI(slug, true); checkbox.prop('checked', false).prop('disabled', true); self.toast('✅ ' + name + ' active!', 'success'); self.updateProgressStats(); if (type === 'premium') self.updatePremInstallCount(); })
                .catch(function(e) { b.prop('disabled', false).text('Retry').data('action', 'activate'); pillEl.removeClass().addClass('lfp-status-pill error'); statusEl.text('Failed'); card.removeClass('lfp-activating'); self.toast('❌ ' + e.message, 'error'); });
            }
        },

        updateCardUI: function(slug, active) {
            var card = $('[data-plugin="' + slug + '"]'), pillEl = $('#pill-' + slug), statusEl = $('#status-' + slug), btn = $('#btn-' + slug), checkbox = card.find('.lfp-plugin-checkbox');
            card.removeClass('lfp-pending lfp-premium-ready lfp-installing lfp-activating lfp-error').addClass('lfp-active');
            pillEl.removeClass().addClass('lfp-status-pill active'); statusEl.text('Active');
            btn.data('action', 'active').prop('disabled', true).text('✓ Active'); checkbox.prop('checked', false).prop('disabled', true);
        },

        runBulk: function() {
            var self = this, sel = [];
            $('.lfp-plugin-checkbox:checked[data-type="free"]').each(function() {
                var c = $(this).closest('.lfp-plugin-card'); if (!c.hasClass('lfp-active')) sel.push({ id: c.data('plugin'), type: 'free', file: c.data('file'), zip: c.data('zip'), name: c.find('.lfp-plugin-name').text().replace(/\s*<.*?>/g, '').trim() });
            });
            if (!sel.length) return this.toast('All selected free plugins are already active.', 'info');
            if (!confirm('Install ' + sel.length + ' selected plugin(s)?')) return;
            this.isProcessing = true; $('.lfp-btn, .lfp-btn-action, input[type="checkbox"]').prop('disabled', true);
            this.toast('🚀 Processing ' + sel.length + ' plugin(s)...', 'info'); this.processBulk(sel, 0);
        },

        processBulk: function(list, i) {
            var self = this; if (i >= list.length) { this.finishBulk(); return; }
            var p = list[i], card = $('[data-plugin="' + p.id + '"]'), b = card.find('.lfp-btn-action'), checkbox = card.find('.lfp-plugin-checkbox'), needsInstall = b.data('action') === 'install', pillEl = $('#pill-' + p.id), statusEl = $('#status-' + p.id);
            b.prop('disabled', true);
            if (needsInstall) { b.html('<span class="lfp-spin">⏳</span>' + (p.type === 'premium' ? ' Download' : ' Install')); pillEl.removeClass().addClass('lfp-status-pill installing'); statusEl.text('Installing...'); card.addClass('lfp-installing'); }
            else { b.html('<span class="lfp-spin">⚡</span> Activate'); pillEl.removeClass().addClass('lfp-status-pill activating'); statusEl.text('Activating...'); card.addClass('lfp-activating'); }
            var installPromise = needsInstall ? (p.type === 'premium' ? this.doPremiumInstall(p.id, p.zip, p.file, p.name) : this.doInstall(p.id, p.file)) : Promise.resolve();
            installPromise.then(function() { if (needsInstall) { b.html('<span class="lfp-spin">⚡</span> Activate'); pillEl.removeClass().addClass('lfp-status-pill activating'); statusEl.text('Activating...'); } return self.doActivate(p.file); })
            .then(function() { self.updateCardUI(p.id, true); checkbox.prop('checked', false).prop('disabled', true); self.toast('✅ ' + p.name + ' active!', 'success'); self.updateProgressStats(); if (p.type === 'premium') self.updatePremInstallCount(); setTimeout(function() { self.processBulk(list, i + 1); }, 250); })
            .catch(function(e) { b.prop('disabled', false).text('Retry').data('action', 'install'); pillEl.removeClass().addClass('lfp-status-pill error'); statusEl.text('Failed'); card.removeClass('lfp-installing lfp-activating'); self.toast('❌ ' + p.name + ': ' + e.message, 'error'); setTimeout(function() { self.processBulk(list, i + 1); }, 250); });
        },

        // ✅ Install only CHECKED premium plugins
        installCheckedPremium: function() {
            var self = this;
            var toInstall = this.premiumPlugins.filter(function(p) {
                var file = p.plugin_file || '';
                var isInstalled = self.isPluginInstalled(file);
                var isChecked = $('[data-file="' + file + '"][data-type="premium"]').find('.lfp-plugin-checkbox').is(':checked');
                return !isInstalled && isChecked;
            });
            if (!toInstall.length) return this.toast('No premium plugins selected or already installed.', 'info');
            if (!confirm('Install ' + toInstall.length + ' selected premium plugin(s) from CDN?')) return;
            this.isProcessing = true; $('.lfp-btn, .lfp-btn-action').prop('disabled', true);
            this.toast('☁️ Downloading ' + toInstall.length + ' plugin(s)...', 'info');
            this.processBulk(toInstall.map(function(p) { return { id: p.id, type: 'premium', file: p.plugin_file, zip: p.zip_url, name: p.name }; }), 0);
        },

        doPremiumInstall: function(id, zipUrl, file, name) { return new Promise(function(resolve, reject) {
            $.ajax({ url: lfData.ajax, type: 'POST', timeout: 600000, data: { action: 'lf_premium_install', plugin_id: id, zip_url: zipUrl, plugin_file: file, plugin_name: name, nonce: lfData.nonce },
                success: function(d) { if (d.success) resolve(); else reject(new Error(d.data || 'Install failed')); }, error: function(xhr) { reject(new Error(xhr.responseText || 'Network error')); } });
        }); },

        doInstall: function(slug, file) { return new Promise(function(resolve, reject) {
            $.ajax({ url: lfData.ajax, type: 'POST', timeout: 60000, data: { action: 'lf_install', slug: slug, file: file, nonce: lfData.nonce },
                success: function(d) { if (d.success) resolve(); else reject(new Error(d.data || 'Install failed')); }, error: function(xhr) { reject(new Error(xhr.responseText || 'Network error')); } });
        }); },

        doActivate: function(file) { return new Promise(function(resolve, reject) {
            $.ajax({ url: lfData.ajax, type: 'POST', timeout: 30000, data: { action: 'lf_activate', file: file, nonce: lfData.nonce },
                success: function(d) { if (d.success) resolve(); else reject(new Error(d.data || 'Activate failed')); }, error: function(xhr) { reject(new Error(xhr.responseText || 'Network error')); } });
        }); },

        refreshAllStatus: function() {
            var self = this; this.toast('🔄 Refreshing...', 'info');
            $.ajax({ url: lfData.ajax, type: 'POST', data: { action: 'lf_get_all_status', nonce: lfData.nonce },
                success: function(r) { if (!r.success) return; var d = 0;
                    $.each(r.data.free, function(s, v) { if (v.active) d++; self.updateCardFromStatus(s, v, 'free'); });
                    $.each(r.data.premium, function(s, v) { if (v.active) d++; self.updateCardFromStatus(s, v, 'premium'); });
                    $('.stat-completed').text(d); self.updateProgressStats(); self.toast('✅ Status refreshed', 'success');
                }, error: function() { self.toast('❌ Refresh failed', 'error'); }
            });
        },

        updateCardFromStatus: function(slug, v, type) {
            var selector = type === 'premium' ? '[data-plugin="' + slug + '"][data-type="premium"]' : '[data-plugin="' + slug + '"][data-type="free"]', card = $(selector); if (!card.length) return;
            var pillEl = $('#pill-' + slug), statusEl = $('#status-' + slug), btn = $('#btn-' + slug), checkbox = card.find('.lfp-plugin-checkbox');
            card.removeClass('lfp-pending lfp-premium-ready lfp-installing lfp-activating lfp-error');
            if (v.active) { card.addClass('lfp-active'); pillEl.removeClass().addClass('lfp-status-pill active'); statusEl.text('Active'); btn.data('action', 'active').prop('disabled', true).text('✓ Active'); checkbox.prop('checked', false).prop('disabled', true); }
            else if (v.installed) { card.addClass('lfp-pending'); pillEl.removeClass().addClass('lfp-status-pill pending'); statusEl.text('Inactive'); btn.data('action', 'activate').prop('disabled', false).text('Activate'); checkbox.prop('disabled', false); }
            else { pillEl.removeClass().addClass('lfp-status-pill ' + (type === 'premium' ? 'premium' : '')); statusEl.text(type === 'premium' ? 'CDN' : 'Not Installed'); btn.data('action', 'install').prop('disabled', false).text('Install'); checkbox.prop('disabled', false); }
        },

        updateProgressStats: function() {
            var self = this, d = 0; this.freePlugins.forEach(function(p) { if (p.required && self.isPluginActive(p.file)) d++; });
            var pct = this.coreTotal > 0 ? Math.round((d / this.coreTotal) * 100) : 100;
            $('.stat-completed').text(d); $('#lfp-progress-fill').css('width', pct + '%'); $('#lfp-progress-pct').text(pct + '%');
            $('#lfp-progress-label').text(pct >= 100 ? 'Core Setup Complete 🎉' : d + '/' + this.coreTotal + ' Core Ready');
            if (d >= this.coreTotal) { $('#lfp-warning').addClass('hidden'); } else { $('#lfp-warning').removeClass('hidden'); $('#lfp-warning-text').text(d + '/' + this.coreTotal + ' core plugins ready.'); }
        },

        finishBulk: function() { this.isProcessing = false; $('.lfp-btn, .lfp-btn-action, input[type="checkbox"]').prop('disabled', false); this.updateProgressStats(); this.updatePremInstallCount(); this.toast('✅ All selected plugins processed!', 'success'); },

        toast: function(m, t) {
            var icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' }, el = $('<div class="lfp-toast ' + t + '"><span>' + (icons[t] || '') + '</span><span>' + m + '</span><button class="lfp-toast-close">&times;</button></div>');
            $('#lfp-notification-area').prepend(el); el.find('.lfp-toast-close').on('click', function() { el.fadeOut(200, function() { $(this).remove(); }); });
            setTimeout(function() { el.fadeOut(300, function() { el.remove(); }); }, t === 'success' ? 4000 : 3500);
        },

        esc: function(t) { var d = document.createElement('div'); d.textContent = t || ''; return d.innerHTML; }
    };
    LFP.init();
});