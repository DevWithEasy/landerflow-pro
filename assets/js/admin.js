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
            
            // সিঙ্গেল বাটন ইভেন্ট হ্যান্ডলার
            $(document).on('click', '.lf-single-action', (e) => {
                e.preventDefault();
                this.handleSingle($(e.target).closest('.lf-single-action'));
            });

            // অটো-রান লজিক (প্রথমবার ইন্সটল)
            if ($('.lf-wrap').hasClass('auto-run')) {
                setTimeout(() => this.runCoreOnly(), 1000);
            } else {
                this.refreshStatus();
            }
        },

        // 🎯 অটো-ট্রিগার: শুধু কোর ৩টি ইন্সটল করে রিলোড
        runCoreOnly() {
            if (this.busy) return;
            this.busy = true;
            $('.lf-btn, .lf-single-action, input[type="checkbox"]').prop('disabled', true);
            this.progressText('Installing core plugins...');
            this.processBulk([...lfData.core], 0, true); // true = reload after done
        },

        // 🔘 সিঙ্গেল প্লাগিন ইন্সটল/অ্যাক্টিভেট লজিক (ফিক্সড)
        handleSingle($btn) {
            if ($btn.is(':disabled') || this.busy) return;
            
            const slug = $btn.data('slug');
            const pluginName = lfData.plugins[slug]?.name || slug;
            
            // বাটন স্টেট ম্যানেজমেন্ট
            $btn.prop('disabled', true).data('original-text', $btn.text());
            
            const isMissing = $btn.hasClass('btn-install');
            const isInactive = $btn.hasClass('btn-activate');

            // ধাপ ১: ইন্সটলেশন (যদি না থাকে)
            if (isMissing) {
                $btn.text('Installing...'); // টেক্সট আপডেট
                
                this.ajax('lf_install', {slug})
                    .then(() => {
                        // ধাপ ২: ইন্সটল শেষ, এখন অ্যাক্টিভেট
                        $btn.text('Activating...'); 
                        return this.ajax('lf_activate', {slug});
                    })
                    .then(() => {
                        // ধাপ ৩: সফলভাবে শেষ
                        this.finishSingleAction(slug, $btn, '✓ Active', true);
                    })
                    .catch(err => {
                        this.handleErrorSingle(slug, $btn, err.message || 'Install Failed');
                    });
            } 
            // ধাপ ১: শুধু অ্যাক্টিভেশন (যদি ইন্সটল করা থাকে)
            else if (isInactive) {
                $btn.text('Activating...');
                
                this.ajax('lf_activate', {slug})
                    .then(() => {
                        this.finishSingleAction(slug, $btn, '✓ Active', true);
                    })
                    .catch(err => {
                        this.handleErrorSingle(slug, $btn, err.message || 'Activate Failed');
                    });
            }
        },

        // ✅ সিঙ্গেল অ্যাকশন সফল হলে কী হবে
        finishSingleAction(slug, $btn, finalText, shouldReload) {
            // ১. UI আপডেট
            this.updateSingleUI(slug, 'active');
            this.updateProgress();
            
            // ২. বাটন ফিক্স
            $btn.removeClass('btn-install btn-activate').addClass('btn-done')
                .text(finalText).prop('disabled', true);
            
            // ৩. নোটিশ
            this.notice('✓ ' + lfData.plugins[slug].name + ' is ready!', 'success');
            
            // ৪. রিলোড লজিক (যদি প্রয়োজন হয়)
            if (shouldReload) {
                setTimeout(() => {
                    location.reload(); 
                }, 1500); // ১.৫ সেকেন্ড পর রিলোড
            }
        },

        // ❌ এরর হ্যান্ডলিং
        handleErrorSingle(slug, $btn, errMsg) {
            console.error(errMsg);
            const originalText = $btn.data('original-text') || 'Retry';
            
            // UI আপডেট (এরর স্টেট)
            this.updateSingleUI(slug, 'error', errMsg);
            
            // বাটন রিসেট
            $btn.prop('disabled', false).removeClass('loading').text('Retry');
            
            this.notice('Error: ' + errMsg, 'error');
        },

        // 📦 বাল্ক ইন্সটল লজিক
        runBulk() {
            const selected = [];
            $('input[name="lf_plugins[]"]:checked').each(function() {
                const $item = $(this).closest('.lf-item');
                if (!$item.find('.lf-status').hasClass('st-active')) {
                    selected.push($(this).val());
                }
            });

            if (selected.length === 0) return this.notice('All selected plugins are already active.', 'info');
            if (this.busy || !confirm(`Process ${selected.length} plugin(s)?`)) return;

            this.busy = true;
            $('.lf-btn, .lf-single-action, input[type="checkbox"]').prop('disabled', true);
            this.progressText(`Starting...`);
            this.processBulk(selected, 0, false); // false = no reload for manual bulk
        },

        processBulk(list, i, shouldReload) {
            if (i >= list.length) {
                return shouldReload ? this.finishWithReload() : this.finish();
            }
            const slug = list[i];
            const $item = $(`.lf-item[data-slug="${slug}"]`);
            const $btn = $item.find('.lf-single-action');
            const needsInstall = $btn.hasClass('btn-install');

            // ধাপ ১: ইন্সটল
            const doInstall = () => {
                $btn.text('Installing...');
                this.ajax('lf_install', {slug})
                    .then(() => doActivate())
                    .catch(err => this.handleBulkError(slug, $btn, err.message));
            };

            // ধাপ ২: অ্যাক্টিভেট
            const doActivate = () => {
                $btn.text('Activating...');
                this.ajax('lf_activate', {slug})
                    .then(() => {
                        this.updateSingleUI(slug, 'active');
                        this.updateProgress();
                        setTimeout(() => this.processBulk(list, i + 1, shouldReload), 400);
                    })
                    .catch(err => this.handleBulkError(slug, $btn, err.message));
            };

            if (needsInstall) {
                doInstall();
            } else {
                doActivate();
            }
        },

        handleBulkError(slug, $btn, errMsg) {
            console.error(errMsg);
            this.updateSingleUI(slug, 'error', errMsg);
            $btn.text('Retry').prop('disabled', false);
            this.notice(`${lfData.plugins[slug]?.name}: ${errMsg}`, 'error');
            // Continue to next item even if one fails
            // Note: In a real sequential process, you might want to stop here.
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
                const $status = $(`.lf-item[data-slug="${slug}"] .lf-status`);
                if ($status.hasClass('st-active')) coreDone++;
            });
            const pct = Math.round((coreDone / this.coreTotal) * 100);
            $('#lf-fill').css('width', pct + '%').css('background', pct >= 100 ? '#2e7d32' : '#0073aa');
            this.progressText(pct >= 100 ? 'Core Setup Complete! 🎉' : `${coreDone}/${this.coreTotal} Core Plugins Ready`);
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
                $status.addClass('st-missing').text(state === 'error' ? '✗ Error' : '⬜ Not Installed');
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
            this.notice('✓ Selection processed.', 'success');
        },

        finishWithReload() {
            this.busy = false;
            this.updateProgress();
            this.notice('🎉 Core plugins ready! Reloading to update menu...', 'success');
            setTimeout(() => location.reload(), 1500);
        },

        ajax(action, data) {
            return new Promise((res, rej) => {
                $.post(lfData.ajax, {action, nonce: lfData.nonce, ...data}, 
                    r => r.success ? res(r.data) : rej(new Error(r.data)), 'json')
                .fail((xhr, status) => rej(new Error(status === 'timeout' ? 'Timeout' : 'Network error')));
            });
        },

        notice(msg, type='info') {
            const $n = $('#lf-notice').text(msg).attr('class', `show ${type}`).fadeIn(200);
            if(type !== 'error') setTimeout(() => $n.fadeOut(300), 4000);
        }
    };
    lf.init();
});