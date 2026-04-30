jQuery(function($) {
    'use strict';
    if (typeof lfData === 'undefined') {
        console.error('lfData missing');
        return;
    }

    const LFP = {
        isProcessing: false,
        coreTotal: lfData.core.length,
        freePlugins: [],
        premiumPlugins: [],
        templates: [],

        init() {
            this.loadFreePlugins();
            this.loadPremiumPlugins();
            this.loadTemplates();
            this.bindEvents();
            this.initNavigation();
            if ($('.lfp-wrap').hasClass('auto-install')) {
                setTimeout(() => this.runCoreOnly(), 1200);
            }
        },

        initNavigation() {
            const self = this;
            $('.lfp-nav-item[data-section]').on('click', function(e) {
                e.preventDefault();
                const s = $(this).data('section');
                $('.lfp-nav-item').removeClass('active');
                $(this).addClass('active');
                $('.lfp-section').addClass('hidden');
                $('#section-' + s).removeClass('hidden');
                if (s === 'premium') self.renderPremiumCards();
            });
        },

        bindEvents() {
            const self = this;
            $('#lf-start').on('click', () => this.runBulk());
            $('#lf-refresh').on('click', () => this.loadFreePlugins());
            $('#lf-toggle').on('click', () => {
                const c = $('.lfp-plugin-checkbox:not(:disabled)');
                c.prop('checked', c.filter(':checked').length !== c.length);
            });
            $('#lf-premium-install-all').on('click', () => this.installAllPremium());
            $('#lf-premium-refresh').on('click', () => this.loadPremiumPlugins());
            $(document).on('click', '.lfp-btn-action', function(e) {
                e.preventDefault();
                self.handleAction($(this));
            });
        },

        runCoreOnly() {
            if (this.isProcessing) return;
            this.isProcessing = true;
            $('.lfp-btn, .lfp-btn-action, input[type="checkbox"]').prop('disabled', true);
            const core = this.freePlugins.filter(function(p) { return p.required; });
            const coreIds = core.map(function(p) { return p.id || p.slug; });
            this.processBulk(coreIds, 0, true);
        },

        // ===== LOAD DATA =====
        loadFreePlugins() {
            const self = this;
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

        loadPremiumPlugins() {
            const self = this;
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

        renderFreeCards() {
            const self = this;
            const coreGrid = $('#lfp-core-plugins');
            const optGrid = $('#lfp-optional-plugins');
            coreGrid.empty();
            optGrid.empty();

            this.freePlugins.forEach(function(p) {
                const ac = self.isPluginActive(p.file);
                const installed = ac || self.isPluginInstalled(p.file);
                const card = self.createFreeCard(p, ac, installed);
                if (p.required) {
                    coreGrid.append(card);
                } else {
                    optGrid.append(card);
                }
            });

            this.updateProgressStats();
        },

        createFreeCard(p, ac, installed) {
            const stateClass = ac ? 'lfp-active' : 'lfp-pending';
            const pillClass = ac ? 'active' : 'pending';
            const pillText = ac ? 'Active' : (installed ? 'Inactive' : 'Not Installed');
            const action = ac ? 'active' : (installed ? 'activate' : 'install');
            const btnText = ac ? '✓ Active' : (installed ? 'Activate' : 'Install');
            const checked = ac ? 'disabled' : (p.required ? 'checked' : '');
            const id = p.id || p.slug;
            const slug = p.slug || p.id;
            const file = p.file || '';

            return '<div class="lfp-plugin-card ' + stateClass + '" data-plugin="' + id + '" data-type="free" data-core="' + (p.required ? '1' : '0') + '" data-file="' + file + '" data-slug="' + slug + '">' +
                '<div class="lfp-plugin-icon">' + (p.icon || '📦') + '</div>' +
                '<div class="lfp-plugin-info">' +
                '<div class="lfp-plugin-name"><label class="lfp-plugin-check-label"><input type="checkbox" class="lfp-plugin-checkbox" ' + checked + '>' + this.esc(p.name) + (p.required ? ' <span class="lfp-required-badge">Core</span>' : '') + '</label></div>' +
                '<div class="lfp-plugin-desc">' + this.esc(p.desc || '') + '</div>' +
                '<div class="lfp-plugin-tags"><span class="lfp-tag">WP.org</span><span class="lfp-tag">' + (p.required ? 'Required' : 'Optional') + '</span></div>' +
                '</div>' +
                '<div class="lfp-plugin-status-col">' +
                '<div class="lfp-status-pill ' + pillClass + '"><span class="lfp-pill-dot"></span><span>' + pillText + '</span></div>' +
                '<button class="lfp-btn-action" data-slug="' + id + '" data-action="' + action + '" data-type="free" data-file="' + file + '" data-slug-wp="' + slug + '" ' + (ac ? 'disabled' : '') + '>' + btnText + '</button>' +
                '</div></div>';
        },

        renderPremiumCards() {
            const self = this;
            const grid = $('#lfp-premium-plugins');
            grid.empty();

            if (!this.premiumPlugins.length) {
                grid.html('<p style="color:var(--muted)">No premium plugins available</p>');
                return;
            }

            this.premiumPlugins.forEach(function(p) {
                const ac = self.isPluginActive(p.plugin_file);
                const installed = ac || self.isPluginInstalled(p.plugin_file);
                const stateClass = ac ? 'lfp-active' : (installed ? 'lfp-pending' : 'lfp-premium-ready');
                const pillClass = ac ? 'active' : (installed ? 'pending' : 'premium');
                const pillText = ac ? 'Active' : (installed ? 'Inactive' : 'CDN');
                const action = ac ? 'active' : (installed ? 'activate' : 'install');
                const btnText = ac ? '✓ Active' : (installed ? 'Activate' : 'Install');
                const checked = ac ? 'disabled' : (p.required ? 'checked' : '');

                grid.append(
                    '<div class="lfp-plugin-card ' + stateClass + '" data-plugin="' + p.id + '" data-type="premium" data-core="' + (p.required ? '1' : '0') + '" data-file="' + (p.plugin_file || '') + '" data-zip="' + (p.zip_url || '') + '" data-name="' + self.esc(p.name) + '">' +
                    '<div class="lfp-plugin-icon">' + (p.icon || '💎') + '</div>' +
                    '<div class="lfp-plugin-info">' +
                    '<div class="lfp-plugin-name"><label class="lfp-plugin-check-label"><input type="checkbox" class="lfp-plugin-checkbox" ' + checked + '>' + self.esc(p.name) + (p.required ? ' <span class="lfp-required-badge">Required</span>' : '') + '</label></div>' +
                    '<div class="lfp-plugin-desc">' + self.esc(p.desc || '') + '</div>' +
                    '<div class="lfp-plugin-tags"><span class="lfp-tag">' + (p.category || 'Premium') + '</span><span class="lfp-tag">v' + (p.version || '1.0') + '</span><span class="lfp-tag">☁️ CDN</span></div>' +
                    '</div>' +
                    '<div class="lfp-plugin-status-col">' +
                    '<div class="lfp-status-pill ' + pillClass + '"><span class="lfp-pill-dot"></span><span>' + pillText + '</span></div>' +
                    '<button class="lfp-btn-action" data-slug="' + p.id + '" data-action="' + action + '" data-type="premium" data-file="' + (p.plugin_file || '') + '" data-zip="' + (p.zip_url || '') + '" data-name="' + self.esc(p.name) + '" ' + (ac ? 'disabled' : '') + '>' + btnText + '</button>' +
                    '</div></div>'
                );
            });

            $('.lfp-nav-count').text(this.premiumPlugins.length);
            this.updatePremButton();
        },

        updatePremButton() {
            const self = this;
            const b = $('#lf-premium-install-all');
            const count = this.premiumPlugins.filter(function(p) {
                return !self.isPluginInstalled(p.plugin_file);
            }).length;
            b.text('⬇ Install All (' + count + ')').prop('disabled', count === 0);
        },

        // ===== PLUGIN CHECKS =====
        isPluginInstalled(file) {
            if (!file) return false;
            const card = $('[data-file="' + file + '"]').closest('.lfp-plugin-card');
            return card.hasClass('lfp-active') || card.hasClass('lfp-pending');
        },

        isPluginActive(file) {
            if (!file) return false;
            return $('[data-file="' + file + '"]').closest('.lfp-plugin-card').hasClass('lfp-active');
        },

        handleAction(b) {
            if (b.is(':disabled') || this.isProcessing) return;
            const self = this;
            const slug = b.data('slug');
            const action = b.data('action');
            const type = b.data('type') || 'free';
            const file = b.data('file') || '';
            const zipUrl = b.data('zip') || '';
            const name = b.data('name') || 'Plugin';

            b.prop('disabled', true);

            if (action === 'install') {
                b.text(type === 'premium' ? '☁️ Downloading' : lfData.texts.installing);
                const installPromise = type === 'premium' ? this.doPremiumInstall(slug, zipUrl, file, name) : this.doInstall(slug, file);

                installPromise
                    .then(function() {
                        b.text(lfData.texts.activating);
                        return self.doActivate(file);
                    })
                    .then(function() {
                        self.finishAction(b);
                    })
                    .catch(function(e) {
                        b.prop('disabled', false).text(lfData.texts.retry);
                        self.toast('❌ ' + e.message, 'error');
                    });
            } else if (action === 'activate') {
                b.text(lfData.texts.activating);
                this.doActivate(file)
                    .then(function() {
                        self.finishAction(b);
                    })
                    .catch(function(e) {
                        b.prop('disabled', false).text(lfData.texts.retry);
                        self.toast('❌ ' + e.message, 'error');
                    });
            }
        },

        finishAction(b) {
            b.data('action', 'active').prop('disabled', true).text(lfData.texts.active_btn);
            b.closest('.lfp-plugin-card').addClass('lfp-active')
                .find('.lfp-status-pill').removeClass().addClass('lfp-status-pill active')
                .find('span:last').text('Active');
            b.closest('.lfp-plugin-card').find('.lfp-plugin-checkbox').prop('checked', false).prop('disabled', true);
            this.toast('✓ Ready!', 'success');
            this.updateProgressStats();
            setTimeout(function() { location.reload(); }, 1500);
        },

        runBulk() {
            const self = this;
            const sel = [];
            $('.lfp-plugin-checkbox:checked').each(function() {
                const c = $(this).closest('.lfp-plugin-card');
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
            if (!sel.length) return this.toast('All active', 'info');
            if (!confirm(lfData.texts.confirm_bulk)) return;
            this.isProcessing = true;
            $('.lfp-btn, .lfp-btn-action, input[type="checkbox"]').prop('disabled', true);
            this.processBulk(sel, 0, false);
        },

        processBulk(list, i, reload) {
            const self = this;
            if (i >= list.length) return reload ? this.finishReload() : this.finishBulk();

            const p = list[i];
            const b = $('[data-plugin="' + p.id + '"]').find('.lfp-btn-action');
            const needsInstall = b.data('action') === 'install';

            b.text(needsInstall ? (p.type === 'premium' ? '☁️ Downloading' : lfData.texts.installing) : lfData.texts.activating);

            const installPromise = needsInstall ?
                (p.type === 'premium' ? this.doPremiumInstall(p.id, p.zip, p.file, p.name) : this.doInstall(p.id, p.file)) :
                Promise.resolve();

            installPromise
                .then(function() {
                    b.text(lfData.texts.activating);
                    return self.doActivate(p.file);
                })
                .then(function() {
                    b.data('action', 'active').prop('disabled', true).text(lfData.texts.active_btn);
                    b.closest('.lfp-plugin-card').addClass('lfp-active');
                    setTimeout(function() { self.processBulk(list, i + 1, reload); }, 400);
                })
                .catch(function(e) {
                    b.text(lfData.texts.retry).prop('disabled', false);
                    setTimeout(function() { self.processBulk(list, i + 1, reload); }, 400);
                });
        },

        doPremiumInstall(id, zipUrl, file, name) {
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
                        else reject(new Error(d.data || 'Failed'));
                    },
                    error: function() {
                        reject(new Error('Network error'));
                    }
                });
            });
        },

        doInstall(slug, file) {
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
                        else reject(new Error(d.data || 'Failed'));
                    },
                    error: function() {
                        reject(new Error('Network error'));
                    }
                });
            });
        },

        doActivate(file) {
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
                        else reject(new Error(d.data || 'Failed'));
                    },
                    error: function() {
                        reject(new Error('Network error'));
                    }
                });
            });
        },

        installAllPremium() {
            const self = this;
            const toInstall = this.premiumPlugins.filter(function(p) {
                return !self.isPluginInstalled(p.plugin_file);
            });
            if (!toInstall.length) return this.toast('All installed', 'info');
            if (!confirm('Install ' + toInstall.length + ' premium plugins?')) return;

            this.isProcessing = true;
            $('.lfp-btn, .lfp-btn-action').prop('disabled', true);
            this.toast('☁️ Downloading...', 'info');

            $.ajax({
                url: lfData.ajax,
                type: 'POST',
                timeout: 600000,
                data: { action: 'lf_premium_bulk_install', nonce: lfData.nonce },
                success: function(r) {
                    self.isProcessing = false;
                    $('.lfp-btn, .lfp-btn-action').prop('disabled', false);
                    self.toast(r.success ? '✓ Done!' : 'Some failed', r.success ? 'success' : 'warning');
                    self.loadPremiumPlugins();
                },
                error: function() {
                    self.isProcessing = false;
                    self.toast('Failed', 'error');
                }
            });
        },

        refreshStatus() {
            const self = this;
            $.ajax({
                url: lfData.ajax,
                type: 'POST',
                data: { action: 'lf_status', nonce: lfData.nonce },
                success: function(r) {
                    if (!r.success) return;
                    let d = 0;
                    $.each(r.data, function(s, v) {
                        if (v.active) d++;
                        const c = $('[data-plugin="' + s + '"]');
                        if (c.length) {
                            c.removeClass('lfp-active lfp-pending').addClass(v.active ? 'lfp-active' : 'lfp-pending');
                            const b = c.find('.lfp-btn-action');
                            b.data('action', v.active ? 'active' : (v.installed ? 'activate' : 'install'))
                                .text(v.active ? '✓ Active' : (v.installed ? 'Activate' : 'Install'))
                                .prop('disabled', v.active);
                            c.find('.lfp-plugin-checkbox')
                                .prop('checked', !v.active && c.data('core') === '1')
                                .prop('disabled', v.active);
                        }
                    });
                    $('.stat-completed').text(d);
                    self.updateProgressStats();
                }
            });
        },

        updateProgressStats() {
            const self = this;
            let d = 0;
            this.freePlugins.forEach(function(p) {
                if (p.required && self.isPluginActive(p.file)) d++;
            });
            const pct = Math.round((d / this.coreTotal) * 100);
            $('.stat-completed').text(d);
            $('#lfp-progress-fill').css('width', pct + '%');
            $('#lfp-progress-pct').text(pct + '%');
            $('#lfp-progress-label').text(pct >= 100 ? 'Core Complete 🎉' : d + '/' + this.coreTotal + ' Core Ready');
            $('#lfp-warning').toggleClass('hidden', d >= this.coreTotal);
        },

        // ===== TEMPLATES =====
        loadTemplates() {
            const self = this;
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

        renderTemplateCards() {
            const self = this;
            const g = $('#lfp-tl-grid');
            g.empty();

            if (!this.templates.length) {
                g.html('<div class="lfp-tl-empty"><div class="lfp-tl-empty-icon">📭</div><h3>No templates</h3></div>');
                return;
            }

            const cats = [...new Set(this.templates.map(function(t) { return t.category; }))];
            $('#lfp-tl-filters').html(
                '<button class="lfp-tl-filter-btn active" data-filter="all">🗂️ All</button>' +
                cats.map(function(c) {
                    return '<button class="lfp-tl-filter-btn" data-filter="' + c.toLowerCase().replace(/\s+/g, '-') + '">' + self.esc(c) + '</button>';
                }).join('')
            );

            this.templates.forEach(function(f) {
                const st = f.steps + ' ' + (f.steps > 1 ? 'Steps' : 'Step');
                const tagsHtml = (f.tags || []).map(function(t) {
                    return '<span class="lfp-tl-tag">' + self.esc(t) + '</span>';
                }).join('');

                const card = $(
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

        initTemplateEvents() {
            const self = this;
            let tm;

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
                const d = $(this);
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

        filterTemplates(q) {
            const f = $('.lfp-tl-filter-btn.active').data('filter') || 'all';
            let v = 0;

            $('.lfp-tl-card').each(function() {
                const c = $(this);
                const match = (!q || (c.data('search') || '').includes(q)) && (f === 'all' || c.data('category') === f);
                if (match) {
                    c.removeClass('hidden').fadeIn(200);
                    v++;
                } else {
                    c.addClass('hidden').fadeOut(200);
                }
            });

            $('.lfp-tl-empty').toggleClass('hidden', v > 0);
        },

        filterByCategory(f) {
            this.filterTemplates($('#lfp-tl-search').val().toLowerCase().trim());
        },

        openPreviewModal(d) {
            $('#lfp-preview-modal').remove();
            const m = $(
                '<div class="lfp-preview-modal" id="lfp-preview-modal">' +
                '<div class="lfp-preview-overlay"></div>' +
                '<div class="lfp-preview-content">' +
                '<button class="lfp-preview-close">&times;</button>' +
                '<div class="lfp-preview-header"><h2>' + this.esc(d.title) + '</h2><p><span>📋 ' + this.esc(d.steps) + '</span> <span>' + this.esc(d.desc) + '</span></p></div>' +
                '<div class="lfp-preview-image"><img src="' + d.img + '" class="lfp-preview-img"></div>' +
                '</div></div>'
            );
            $('body').append(m);
            setTimeout(function() { m.addClass('active'); }, 50);

            const close = function() {
                m.removeClass('active');
                setTimeout(function() { m.remove(); }, 300);
            };

            $('.lfp-preview-overlay, .lfp-preview-close').on('click', close);
        },

        downloadJSON(b) {
            if (this.isProcessing) return;
            const self = this;
            const u = b.data('json');
            const ot = b.text();

            this.isProcessing = true;
            b.prop('disabled', true).text('⏳');

            $.ajax({
                url: lfData.ajax,
                type: 'POST',
                timeout: 60000,
                data: { action: 'lf_download_json', json_url: u, nonce: lfData.nonce },
                success: function(r) {
                    self.isProcessing = false;
                    b.prop('disabled', false).text(ot);
                    if (r.success && r.data.content) {
                        const bs = atob(r.data.content);
                        const ab = new ArrayBuffer(bs.length);
                        const ia = new Uint8Array(ab);
                        for (let i = 0; i < bs.length; i++) { ia[i] = bs.charCodeAt(i); }
                        const blob = new Blob([ab], { type: 'application/json' });
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = r.data.filename || 'funnel.json';
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        window.URL.revokeObjectURL(url);
                        self.toast('✅ Downloaded!', 'success');
                    } else {
                        window.open(u, '_blank');
                    }
                },
                error: function() {
                    self.isProcessing = false;
                    b.prop('disabled', false).text(ot);
                    window.open(u, '_blank');
                }
            });
        },

        finishBulk() {
            this.isProcessing = false;
            $('.lfp-btn, .lfp-btn-action, input[type="checkbox"]').prop('disabled', false);
            this.toast('✓ Done!', 'success');
            setTimeout(function() { location.reload(); }, 1500);
        },

        finishReload() {
            this.isProcessing = false;
            this.toast('🎉 Done!', 'success');
            setTimeout(function() { location.reload(); }, 1500);
        },

        toast(m, t) {
            const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
            const el = $(
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

        esc(t) {
            const d = document.createElement('div');
            d.textContent = t;
            return d.innerHTML;
        }
    };

    // Initialize
    LFP.init();
});