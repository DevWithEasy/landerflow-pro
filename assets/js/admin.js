jQuery(function($) {
    const lf = {
        busy: false,
        coreTotal: lfData.core.length,

        init() {
            $('#lf-start').on('click', () => this.runBulk());
            $('#lf-refresh').on('click', () => this.refreshStatus());
            $('#lf-toggle').on('click', () => {
                const $checks = $('input[name="lf_plugins[]"]:not(:disabled)');
                const allChecked = $checks.filter(':checked').length === $checks.length;
                $checks.prop('checked', !allChecked);
            });
            $('.lf-single-action').on('click', (e) => {
                e.preventDefault();
                this.handleSingle($(e.target).closest('.lf-single-action'));
            });
            $('.lf-wrap.auto-run').length && setTimeout(() => this.runBulk(), 1000);
            this.refreshStatus();
        },

        handleSingle($btn) {
            if ($btn.is(':disabled') || this.busy) return;
            const slug = $btn.data('slug');
            $btn.prop('disabled', true).addClass('loading').text('Processing...');
            
            const needsInstall = $btn.hasClass('btn-install');
            const process = (action) => {
                this.ajax(action, {slug})
                    .then(() => {
                        if (needsInstall) process('lf_activate');
                        else this.updateUIAfterAction(slug);
                    })
                    .catch(err => this.updateSingleUI(slug, 'error', err.message || 'Failed'));
            };
            if (needsInstall) process('lf_install');
            else process('lf_activate');
        },

        runBulk() {
            const selected = [];
            $('input[name="lf_plugins[]"]:checked').each(function() {
                const $item = $(this).closest('.lf-item');
                if (!$item.find('.lf-status').hasClass('st-active')) selected.push($(this).val());
            });

            if (selected.length === 0) return this.notice('All selected plugins are already active.', 'info');
            if (this.busy || !confirm(`Process ${selected.length} plugin(s)?`)) return;

            this.busy = true;
            $('.lf-btn, .lf-single-action, input[type="checkbox"]').prop('disabled', true);
            this.progressText(`Starting...`);
            this.processBulk(selected, 0);
        },

        processBulk(list, i) {
            if (i >= list.length) return this.finish();
            const slug = list[i];
            const $item = $(`.lf-item[data-slug="${slug}"]`);
            const needsInstall = $item.find('.lf-status').hasClass('st-missing');

            const doAction = (action) => {
                this.ajax(action, {slug})
                    .then(() => {
                        if (needsInstall) doAction('lf_activate');
                        else this.nextBulk(i, list);
                    })
                    .catch(err => {
                        this.updateSingleUI(slug, 'error', err.message || 'Failed');
                        this.nextBulk(i, list);
                    });
            };
            if (needsInstall) doAction('lf_install');
            else doAction('lf_activate');
        },

        nextBulk(i, list) {
            const slug = list[i];
            this.updateSingleUI(slug, 'active');
            this.updateProgress(); // Always update core progress after any action
            setTimeout(() => this.processBulk(list, i + 1), 400);
        },

        refreshStatus() {
            this.ajax('lf_status', {}).then(data => {
                $.each(data, (slug, s) => {
                    const state = s.installed && s.active ? 'active' : s.installed ? 'inactive' : 'missing';
                    this.updateSingleUI(slug, state);
                });
                this.updateProgress();
            });
        },

        updateProgress() {
            let coreDone = 0;
            lfData.core.forEach(slug => {
                if ($(`.lf-item[data-slug="${slug}"] .lf-status`).hasClass('st-active')) coreDone++;
            });
            const pct = Math.round((coreDone / this.coreTotal) * 100);
            $('#lf-fill').css('width', pct + '%').css('background', pct >= 100 ? '#2e7d32' : '#0073aa');
            this.progressText(pct >= 100 ? 'Core Setup Complete! 🎉' : `${coreDone}/${this.coreTotal} Core Plugins Ready`);
        },

        updateUIAfterAction(slug) {
            this.updateSingleUI(slug, 'active');
            this.updateProgress();
            this.notice('✓ Plugin processed!', 'success');
        },

        updateSingleUI(slug, state, errMsg = '') {
            const $item = $(`.lf-item[data-slug="${slug}"]`);
            if (!$item.length) return;
            const $btn = $item.find('.lf-single-action');
            const $status = $item.find('.lf-status');
            const $check = $item.find('input[type="checkbox"]');

            $status.removeClass('st-active st-inactive st-missing');
            $btn.removeClass('btn-install btn-activate btn-done loading');

            if (state === 'active') {
                $status.addClass('st-active').text('✓ Active');
                $btn.addClass('btn-done').text('✓ Active').prop('disabled', true);
                $check.prop('checked', false).prop('disabled', true);
            } else if (state === 'inactive') {
                $status.addClass('st-inactive').text('⚡ Inactive');
                $btn.addClass('btn-activate').text('Activate').prop('disabled', false);
                $check.prop('checked', true).prop('disabled', false);
            } else {
                $status.addClass('st-missing').text(state === 'error' ? '✗ Error' : '⬜ Missing');
                $btn.addClass('btn-install').text(errMsg || 'Install').prop('disabled', false);
                $check.prop('checked', true).prop('disabled', false);
                if (state === 'error' && errMsg) this.notice(errMsg, 'error');
            }
        },

        progressText(txt) { $('#lf-progress-text').text(txt); },

        finish() {
            this.busy = false;
            $('.lf-btn, .lf-single-action, input[type="checkbox"]').prop('disabled', false);
            this.updateProgress();
            if ($('#lf-fill').css('width') === '100%') this.notice('🎉 All core plugins ready!', 'success');
            else this.notice('✓ Selection processed.', 'success');
        },

        ajax(action, data) {
            return new Promise((res, rej) => {
                $.post(lfData.ajax, {action, nonce: lfData.nonce, ...data}, 
                    r => r.success ? res(r.data) : rej(new Error(r.data)), 'json')
                .fail(() => rej(new Error('Network error')));
            });
        },

        notice(msg, type='info') {
            const $n = $('#lf-notice').text(msg).attr('class', `show ${type}`).fadeIn(200);
            if(type !== 'error') setTimeout(() => $n.fadeOut(300), 4000);
        }
    };
    lf.init();
});