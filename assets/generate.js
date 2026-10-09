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

    // The source tabs (Quelltext / Roh-HTML) are plain client-side tabs and
    // handled by WP-Backend UI via data-wpb-tabs; see GeneratePage.

    $(document).ready(() => {
        Generate.init();
    });
})(jQuery);
