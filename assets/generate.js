/**
 * AutoQuill - post generation screen (admin.php?page=auto-quill&aq_view=generate).
 *
 * Loaded only on that screen, after assets/admin.js and assets/post-editor.js,
 * so window.AutoQuill and window.AutoQuill.PostEditor exist by the time this
 * runs. The right-hand editor (preview, meta fields, image picker, saving)
 * lives in post-editor.js; this file only starts the generation.
 */

(function($) {
    'use strict';

    const shared = window.AutoQuill || {};
    const config = window.autoQuillGenerate || {};
    const Editor = shared.PostEditor;

    const Generate = {
        apiUrl: shared.apiUrl,
        restNonce: shared.restNonce,

        init: function() {
            if (!Editor || !$('#post-preview').length) {
                return;
            }

            Editor.init({ i18n: config.i18n || {}, backUrl: config.backUrl || '' });
            $(document).on('click', '#auto-quill-generate-btn', this.onGenerateClick.bind(this));

            if (config.autostart) {
                // Drop the nonce from the address bar right away: a reload or a
                // back navigation would otherwise fire a second paid API call,
                // and a bookmark would capture a self-firing URL.
                this.stripNonceFromUrl();
                this.generateBlogPost(config.params || {});
            }
        },

        stripNonceFromUrl: function() {
            if (!window.history || !window.history.replaceState) {
                return;
            }
            const url = new URL(window.location.href);
            url.searchParams.delete('_wpnonce');
            window.history.replaceState(null, '', url.pathname + url.search);
        },

        onGenerateClick: function(e) {
            e.preventDefault();
            if (Editor.busy) {
                return;
            }
            this.generateBlogPost(config.params || {});
        },

        generateBlogPost: function(params) {
            if (Editor.busy) {
                return;
            }

            const retry = () => this.generateBlogPost(config.params || {});

            Editor.setPlaceholder(Editor.t('generating'));
            Editor.showBusy(Editor.t('generating'));

            $.ajax({
                url: this.apiUrl + 'generate-post',
                type: 'POST',
                dataType: 'json',
                data: JSON.stringify(params),
                // Without a client-side cap a dead backend leaves the ring
                // turning and the counter climbing forever.
                timeout: 120000,
                headers: {
                    'X-WP-Nonce': this.restNonce,
                    'Content-Type': 'application/json',
                },
                success: (response) => {
                    if (!response || !response.success) {
                        Editor.showError((response && response.error) || Editor.t('generateError'), retry);
                        Editor.clearPreview();
                        return;
                    }

                    Editor.fill(response, {
                        topic_id: response.topic_id,
                        article_id: parseInt(response.article_id, 10) || 0,
                    });
                    $('#auto-quill-generate-btn').text(Editor.t('regenerate'));
                },
                error: (xhr, textStatus) => {
                    Editor.showError(Editor.errorMessage(xhr, 'generateError', textStatus), retry);
                    Editor.clearPreview();
                },
                complete: () => {
                    Editor.hideBusy();
                },
            });
        },
    };

    /**
     * Fourth tab widget in this plugin, and the third distinct class pair.
     * SettingsTabs hides .auto-quill-tab-panel globally and DashboardTabs owns
     * .auto-quill-dash-panel, so reusing either would make the screens hide
     * each other's content.
     */
    const SourceTabs = {
        init: function() {
            const $tabs = $('.auto-quill-source-tabs .nav-tab');
            if (!$tabs.length) {
                return;
            }

            $tabs.on('click', function(e) {
                e.preventDefault();
                const tab = $(this).data('tab');
                $tabs.removeClass('nav-tab-active')
                     .filter('[data-tab="' + tab + '"]').addClass('nav-tab-active');
                $('.auto-quill-source-panel').hide()
                     .filter('[data-tab="' + tab + '"]').show();
            });
        },
    };

    $(document).ready(() => {
        Generate.init();
        SourceTabs.init();
    });
})(jQuery);
