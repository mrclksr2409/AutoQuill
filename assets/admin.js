/**
 * AutoQuill Admin JavaScript
 */

(function($) {
    'use strict';

    const i18n = (autoQuill && autoQuill.i18n) || {};
    const t = function(key, fallback) {
        return (i18n && i18n[key]) || fallback || '';
    };

    const AutoQuill = {
        apiUrl: autoQuill.apiUrl,
        nonce: autoQuill.nonce,
        restNonce: autoQuill.restNonce || autoQuill.nonce,
        feedPage: 1,
        feedPerPage: 20,
        feedPages: 1,
        feedLoaded: false,

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $(document).on('click', '#auto-quill-recrawl-btn', this.recrawl.bind(this));
            $(document).on('click', '#auto-quill-reselect-btn', this.reselect.bind(this));
            $(document).on('click', '#auto-quill-feed-apply', this.applyFeedFilters.bind(this));
            $(document).on('click', '#auto-quill-feed-prev', this.prevFeedPage.bind(this));
            $(document).on('click', '#auto-quill-feed-next', this.nextFeedPage.bind(this));
            $(document).on('keydown', '#auto-quill-feed-search', this.onFeedSearchKeydown.bind(this));
        },

        recrawl: function(e) {
            e.preventDefault();
            const $btn = $(e.target);
            const sourceId = parseInt($('#auto-quill-source-select').val(), 10) || 0;
            const originalText = $btn.text();

            $btn.prop('disabled', true).text(t('recrawling'));
            this.showAlert(t('recrawlInfo'), 'info');

            $.post(ajaxurl, {
                action: 'auto_quill_recrawl_topics',
                nonce: this.nonce,
                source_id: sourceId,
            }).done((response) => {
                if (response && response.success) {
                    setTimeout(() => location.reload(), 1500);
                } else {
                    const msg = (response && response.data && response.data.message) || t('recrawlError');
                    this.showAlert(msg, 'error');
                    $btn.prop('disabled', false).text(originalText);
                }
            }).fail(() => {
                this.showAlert(t('recrawlError'), 'error');
                $btn.prop('disabled', false).text(originalText);
            });
        },

        reselect: function(e) {
            e.preventDefault();
            const $btn = $(e.target);
            const originalText = $btn.text();

            $btn.prop('disabled', true).text(t('reselecting'));
            this.showAlert(t('reselectInfo'), 'info');

            $.post(ajaxurl, {
                action: 'auto_quill_reselect_topics',
                nonce: this.nonce,
            }).done((response) => {
                if (response && response.success) {
                    setTimeout(() => location.reload(), 1500);
                } else {
                    const msg = (response && response.data && response.data.message) || t('reselectError');
                    this.showAlert(msg, 'error');
                    $btn.prop('disabled', false).text(originalText);
                }
            }).fail(() => {
                this.showAlert(t('reselectError'), 'error');
                $btn.prop('disabled', false).text(originalText);
            });
        },

        errorMessage: function(xhr, fallbackKey, textStatus) {
            if (textStatus === 'timeout') {
                return t('requestTimeout');
            }
            const json = xhr && xhr.responseJSON;
            if (json && json.code === 'rest_cookie_invalid_nonce') {
                return t('sessionExpired');
            }
            if (json && json.error) {
                return json.error;
            }
            if (xhr && xhr.status === 403) {
                return t('sessionExpired');
            }
            return t(fallbackKey);
        },

        applyFeedFilters: function(e) {
            if (e && e.preventDefault) { e.preventDefault(); }
            this.loadFeed(1);
        },

        onFeedSearchKeydown: function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.loadFeed(1);
            }
        },

        prevFeedPage: function(e) {
            e.preventDefault();
            if (this.feedPage > 1) {
                this.loadFeed(this.feedPage - 1);
            }
        },

        nextFeedPage: function(e) {
            e.preventDefault();
            if (this.feedPage < this.feedPages) {
                this.loadFeed(this.feedPage + 1);
            }
        },

        loadFeed: function(page) {
            const $body = $('#auto-quill-feed-body');
            if (!$body.length) {
                return;
            }

            this.setFeedStatus(t('loadingFeed'), false);
            $('#auto-quill-feed-pagination').prop('hidden', true);

            $.ajax({
                url: this.apiUrl + 'articles',
                type: 'GET',
                dataType: 'json',
                data: {
                    page: page,
                    per_page: this.feedPerPage,
                    source_id: parseInt($('#auto-quill-feed-source').val(), 10) || 0,
                    search: ($('#auto-quill-feed-search').val() || '').trim(),
                    linked: $('#auto-quill-feed-unlinked').is(':checked') ? 'unlinked' : 'all',
                },
                headers: { 'X-WP-Nonce': this.restNonce },
            }).done((response) => {
                // The markup is rendered and escaped server-side; see
                // Dashboard::render_feed_rows().
                $body.html((response && response.html) || '');

                this.feedLoaded = true;
                this.feedPage = (response && response.page) || 1;
                this.feedPages = (response && response.pages) || 1;

                this.setFeedStatus('', false);

                $('#auto-quill-feed-page-info').text(
                    (t('feedPageInfo') || 'Seite %1$d von %2$d')
                        .replace('%1$d', this.feedPage)
                        .replace('%2$d', this.feedPages)
                        .replace('%3$d', (response && response.total) || 0)
                );
                $('#auto-quill-feed-prev').prop('disabled', this.feedPage <= 1);
                $('#auto-quill-feed-next').prop('disabled', this.feedPage >= this.feedPages);
                $('#auto-quill-feed-pagination').prop('hidden', this.feedPages <= 1);
            }).fail((xhr) => {
                this.setFeedStatus(this.errorMessage(xhr, 'feedLoadError'), true);
            });
        },

        setFeedStatus: function(message, isError) {
            const $status = $('#auto-quill-feed-status');
            if (!message) {
                $status.prop('hidden', true).removeClass('is-error').text('');
                return;
            }
            $status.prop('hidden', false).toggleClass('is-error', !!isError).text(message);
        },

        showAlert: function(message, type = 'info') {
            const $alert = $('<div>')
                .addClass('auto-quill-alert')
                .addClass('auto-quill-alert-' + type)
                .text(message);

            $('.wrap').prepend($alert);

            setTimeout(() => {
                $alert.fadeOut(() => {
                    $alert.remove();
                });
            }, 3000);
        },
    };

    /**
     * Deliberately separate from SettingsTabs and scoped to its own classes:
     * SettingsTabs hides '.auto-quill-tab-panel' globally, and the settings
     * page relies on that (its StatusPanel sits outside the form). Reusing
     * those class names here would make the two pages hide each other.
     */
    const DashboardTabs = {
        init: function(onActivate) {
            const $tabs = $('.auto-quill-dashboard-tabs .nav-tab');
            if (!$tabs.length) {
                return;
            }

            const activate = (tab) => {
                $tabs.removeClass('nav-tab-active')
                     .filter('[data-tab="' + tab + '"]').addClass('nav-tab-active');
                $('.auto-quill-dash-panel').hide()
                     .filter('[data-tab="' + tab + '"]').show();
                if (typeof onActivate === 'function') {
                    onActivate(tab);
                }
            };

            $tabs.on('click', function(e) {
                e.preventDefault();
                const tab = $(this).data('tab');
                activate(tab);
                history.replaceState(null, '', '#dash-' + tab);
            });

            this.activate = activate;

            // Honour #dash-articles so the generate page can link straight back
            // to the feed tab the user came from.
            const initial = (window.location.hash || '').replace(/^#dash-/, '');
            if (initial && $tabs.filter('[data-tab="' + initial + '"]').length) {
                activate(initial);
            }
        },
    };

    const SettingsTabs = {
        init: function() {
            const $tabs = $('.auto-quill-settings-tabs .nav-tab');
            if (!$tabs.length) {
                return;
            }
            const activate = (tab) => {
                $tabs.removeClass('nav-tab-active')
                     .filter('[data-tab="' + tab + '"]').addClass('nav-tab-active');
                $('.auto-quill-tab-panel').hide()
                     .filter('[data-tab="' + tab + '"]').show();
            };
            $tabs.on('click', function(e) {
                e.preventDefault();
                const tab = $(this).data('tab');
                activate(tab);
                history.replaceState(null, '', '#tab-' + tab);
            });
            const initial = (window.location.hash || '').replace(/^#tab-/, '');
            if (initial && $tabs.filter('[data-tab="' + initial + '"]').length) {
                activate(initial);
            }
        },
    };

    /**
     * Model dropdowns on the KI tab. Options come from the provider via
     * POST /models; the saved value is always kept selectable, so a failed
     * load never changes what gets saved.
     */
    const ModelPicker = {
        loaded: {},

        init: function() {
            if (!$('.auto-quill-model-select').length) {
                return;
            }

            $('#ai_provider').on('change', () => {
                const provider = this.activeProvider();
                this.showRow(provider);
                if (!this.loaded[provider]) {
                    this.load(provider, false);
                }
            });

            $(document).on('click', '.auto-quill-models-refresh', (e) => {
                e.preventDefault();
                this.load($(e.currentTarget).data('provider'), true);
            });

            // A newly typed key may unlock a different set of models.
            $('#ai_api_key').on('change', () => {
                if (($('#ai_api_key').val() || '').trim() !== '') {
                    this.loaded = {};
                    this.load(this.activeProvider(), true);
                }
            });

            const provider = this.activeProvider();
            this.showRow(provider);
            this.load(provider, false);
        },

        activeProvider: function() {
            return $('#ai_provider').val() || 'openai';
        },

        showRow: function(provider) {
            $('.auto-quill-model-row').hide()
                .filter('[data-provider="' + provider + '"]').show();
        },

        load: function(provider, refresh) {
            const $row = $('.auto-quill-model-row[data-provider="' + provider + '"]');
            const $select = $row.find('.auto-quill-model-select');
            const $status = $row.find('.auto-quill-models-status');
            const $spinner = $row.find('.auto-quill-models-spinner');
            const $button = $row.find('.auto-quill-models-refresh');

            $spinner.addClass('is-active');
            $button.prop('disabled', true);
            $status.removeClass('is-error').text(t('modelsLoading'));

            $.ajax({
                url: AutoQuill.apiUrl + 'models',
                type: 'POST',
                dataType: 'json',
                timeout: 30000,
                data: {
                    provider: provider,
                    api_key: ($('#ai_api_key').val() || '').trim(),
                    refresh: refresh ? 1 : 0,
                },
                headers: { 'X-WP-Nonce': AutoQuill.restNonce },
            }).done((response) => {
                const models = (response && response.models) || [];
                this.fill($select, models);
                this.loaded[provider] = true;
                $status.text(
                    (t('modelsLoaded') || '%d Modelle verfügbar').replace('%d', models.length)
                );
            }).fail((xhr, textStatus) => {
                $status.addClass('is-error')
                    .text(AutoQuill.errorMessage(xhr, 'modelsError', textStatus));
            }).always(() => {
                $spinner.removeClass('is-active');
                $button.prop('disabled', false);
            });
        },

        fill: function($select, models) {
            const current = $select.val();
            const ids = models.map((m) => m.id);

            $select.empty();

            if (current && ids.indexOf(current) === -1) {
                $('<option>')
                    .val(current)
                    .text(current + ' ' + t('modelNotListed'))
                    .appendTo($select);
            }
            models.forEach((m) => {
                $('<option>').val(m.id).text(m.label || m.id).appendTo($select);
            });

            $select.val(current);
        },
    };

    // Initialize when document is ready
    // Explicit shared surface for assets/generate.js, which is enqueued with
    // this file as a dependency so the object exists by the time it runs.
    window.AutoQuill = {
        t: t,
        showAlert: AutoQuill.showAlert,
        errorMessage: AutoQuill.errorMessage,
        apiUrl: AutoQuill.apiUrl,
        restNonce: AutoQuill.restNonce,
    };

    $(document).ready(() => {
        AutoQuill.init();
        SettingsTabs.init();
        ModelPicker.init();
        DashboardTabs.init((tab) => {
            // Loaded lazily so opening the dashboard costs nothing extra, and
            // switching tabs never reloads the page - an unpublished generated
            // post lives only in the browser and would be lost.
            if (tab === 'articles' && !AutoQuill.feedLoaded) {
                AutoQuill.loadFeed(1);
            }
        });
    });
})(jQuery);
