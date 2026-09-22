/**
 * AutoQuill - post generation screen (admin.php?page=auto-quill-generate).
 *
 * Loaded only on that screen, with assets/admin.js as a declared dependency so
 * window.AutoQuill is guaranteed to exist by the time this runs.
 */

(function($) {
    'use strict';

    const shared = window.AutoQuill || {};
    const config = window.autoQuillGenerate || {};

    // Screen-specific strings live in autoQuillGenerate; shared ones stay in
    // autoQuill so the settings and logs pages do not ship them.
    const t = function(key, fallback) {
        const local = (config.i18n && config.i18n[key]);
        if (local) {
            return local;
        }
        return shared.t ? shared.t(key, fallback) : (fallback || '');
    };

    const Generate = {
        apiUrl: shared.apiUrl,
        restNonce: shared.restNonce,

        currentTopic: null,
        currentTopicId: null,
        currentTopicIndex: -1,
        currentArticleId: 0,
        selectedImage: null,
        imageQuery: '',
        imagePage: 1,
        imageTotalHits: 0,
        imagePerPage: 20,

        busy: false,
        busyTimer: null,
        busyStarted: 0,
        hasUnsavedPost: false,

        showAlert: function(message, type) {
            if (shared.showAlert) {
                shared.showAlert(message, type);
            }
        },

        errorMessage: function(xhr, fallbackKey, textStatus) {
            return shared.errorMessage
                ? shared.errorMessage(xhr, fallbackKey, textStatus)
                : t(fallbackKey);
        },

        init: function() {
            if (!$('#post-preview').length) {
                return;
            }

            this.bindEvents();
            this.guardUnload();

            if (config.autostart) {
                // Drop the nonce from the address bar right away: a reload or a
                // back navigation would otherwise fire a second paid API call,
                // and a bookmark would capture a self-firing URL.
                this.stripNonceFromUrl();
                this.generateBlogPost(config.params || {});
            }
        },

        bindEvents: function() {
            $(document).on('click', '#auto-quill-generate-btn', this.onGenerateClick.bind(this));
            $(document).on('click', '#publish-post-btn', this.publishPost.bind(this));
            $(document).on('click', '#auto-quill-pick-image-btn', this.openImagePicker.bind(this));
            $(document).on('click', '#auto-quill-clear-image-btn', this.clearImage.bind(this));
            $(document).on('click', '[data-modal-close]', this.closeImagePicker.bind(this));
            $(document).on('submit', '#auto-quill-image-search-form', this.onImageSearchSubmit.bind(this));
            $(document).on('click', '#auto-quill-image-prev', this.prevImagePage.bind(this));
            $(document).on('click', '#auto-quill-image-next', this.nextImagePage.bind(this));
            $(document).on('click', '.auto-quill-image-card', this.onImageCardClick.bind(this));
            $(document).on('keydown', this.onKeydown.bind(this));
        },

        stripNonceFromUrl: function() {
            if (!window.history || !window.history.replaceState) {
                return;
            }
            const url = new URL(window.location.href);
            url.searchParams.delete('_wpnonce');
            window.history.replaceState(null, '', url.pathname + url.search);
        },

        guardUnload: function() {
            $(window).on('beforeunload', (e) => {
                if (!this.hasUnsavedPost) {
                    return undefined;
                }
                // A generated post lives only in the browser until it is saved,
                // so leaving throws away a paid call and up to 90s of waiting.
                e.preventDefault();
                e.originalEvent.returnValue = '';
                return '';
            });
        },

        onGenerateClick: function(e) {
            e.preventDefault();
            if (this.busy) {
                return;
            }
            this.generateBlogPost(config.params || {});
        },

        showBusy: function(label) {
            this.busy = true;
            this.busyStarted = Date.now();

            $('#auto-quill-result-column').attr('aria-busy', 'true');
            $('#auto-quill-generate-error').prop('hidden', true).empty();
            $('#auto-quill-generate-btn').prop('disabled', true);
            $('#auto-quill-busy-label').text(label);
            $('#auto-quill-busy-elapsed').text('');
            $('#auto-quill-busy').prop('hidden', false);

            const tick = () => {
                const seconds = Math.round((Date.now() - this.busyStarted) / 1000);
                $('#auto-quill-busy-elapsed').text(
                    (t('elapsedSeconds') || '%d s').replace('%d', seconds)
                );
            };
            tick();
            this.busyTimer = window.setInterval(tick, 1000);
        },

        hideBusy: function() {
            this.busy = false;
            if (this.busyTimer) {
                window.clearInterval(this.busyTimer);
                this.busyTimer = null;
            }
            $('#auto-quill-busy').prop('hidden', true);
            $('#auto-quill-result-column').removeAttr('aria-busy');
            $('#auto-quill-generate-btn').prop('disabled', false);
        },

        /**
         * A failure after 60 seconds of waiting deserves a box that stays, not
         * an alert that fades out after three.
         */
        showGenerateError: function(message) {
            const $box = $('#auto-quill-generate-error');
            $box.empty()
                .append($('<p>').text(message))
                .append(
                    $('<button>')
                        .attr('type', 'button')
                        .addClass('button')
                        .attr('id', 'auto-quill-retry-btn')
                        .text(t('retry'))
                        .on('click', (e) => {
                            e.preventDefault();
                            this.generateBlogPost(config.params || {});
                        })
                )
                .prop('hidden', false);
        },

        generateBlogPost: function(params) {
            if (this.busy) {
                return;
            }

            const $preview = $('#post-preview');
            $preview.empty().append($('<p>').text(t('generating')));
            this.showBusy(t('generating'));

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
                        this.showGenerateError((response && response.error) || t('generateError'));
                        $preview.empty();
                        return;
                    }

                    $preview.html(response.post_content);
                    $('#publish-post-btn').show().prop('disabled', false)
                        .data('post-content', response.post_content);

                    this.currentTopic = response.topic;
                    this.currentTopicId = response.topic_id;
                    this.currentTopicIndex = typeof response.topic_index === 'number'
                        ? response.topic_index
                        : -1;
                    this.currentArticleId = parseInt(response.article_id, 10) || 0;
                    this.hasUnsavedPost = true;

                    $('#auto-quill-generate-result').prop('hidden', true).empty();
                    $('#auto-quill-generate-btn').text(t('regenerate'));

                    this.renderMetaFields(
                        response.post_title || (response.topic && response.topic.title) || '',
                        response.post_excerpt || '',
                        response.available_categories || [],
                        response.category_ids || []
                    );
                    this.resetImageSelection();
                },
                error: (xhr, textStatus) => {
                    this.showGenerateError(this.errorMessage(xhr, 'generateError', textStatus));
                    $preview.empty();
                },
                complete: () => {
                    this.hideBusy();
                },
            });
        },

        publishPost: function(e) {
            e.preventDefault();
            const $btn = $(e.target);
            const postContent = $btn.data('post-content');

            if (!postContent || !this.currentTopic) {
                this.showAlert(t('noContent'), 'error');
                return;
            }

            const postExcerpt = $('#auto-quill-excerpt').val() || '';
            const postTitle   = ($('#auto-quill-title').val() || '').trim()
                || this.currentTopic.title;
            const categoryIds = ($('#auto-quill-categories').val() || [])
                .map((v) => parseInt(v, 10))
                .filter((v) => !isNaN(v));

            $btn.prop('disabled', true).text(t('saving'));

            const payload = {
                post_title: postTitle,
                post_content: postContent,
                post_excerpt: postExcerpt,
                category_ids: categoryIds,
                topic_id: this.currentTopicId,
                article_id: this.currentArticleId,
            };
            if (this.selectedImage && this.selectedImage.url) {
                payload.image_url = this.selectedImage.url;
                payload.image_alt = this.selectedImage.alt || '';
            }

            $.ajax({
                url: this.apiUrl + 'publish-post',
                type: 'POST',
                dataType: 'json',
                data: JSON.stringify(payload),
                headers: {
                    'X-WP-Nonce': this.restNonce,
                    'Content-Type': 'application/json',
                },
                success: (response) => {
                    if (!response || !response.success) {
                        this.showAlert(t('publishError'), 'error');
                        $btn.prop('disabled', false).text(t('publishRetryLabel'));
                        return;
                    }

                    // No location.reload() here: the page would re-run the whole
                    // generation and the draft is already saved.
                    this.hasUnsavedPost = false;
                    $btn.prop('disabled', true);
                    this.renderSuccess(response);
                },
                error: (xhr, textStatus) => {
                    this.showAlert(this.errorMessage(xhr, 'publishError', textStatus), 'error');
                    $btn.prop('disabled', false).text(t('publishRetryLabel'));
                },
            });
        },

        renderSuccess: function(response) {
            const $box = $('#auto-quill-generate-result');
            $box.empty().append($('<p>').text(response.message || t('publishSuccess')));

            const $links = $('<p>').addClass('auto-quill-result-links');
            if (response.edit_url) {
                $links.append(
                    $('<a>').addClass('button button-primary')
                        .attr('href', response.edit_url)
                        .text(t('editPost'))
                );
            }
            if (config.backUrl) {
                $links.append(
                    $('<a>').addClass('button')
                        .attr('href', config.backUrl)
                        .text(t('backToList'))
                );
            }

            $box.append($links).prop('hidden', false);
            if ($box[0] && $box[0].scrollIntoView) {
                $box[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        },

        onKeydown: function(e) {
            if (e.key === 'Escape' && !$('#auto-quill-image-modal').prop('hidden')) {
                this.closeImagePicker(e);
            }
        },

        renderMetaFields: function(title, excerpt, availableCategories, selectedIds) {
            const $title   = $('#auto-quill-title');
            const $excerpt = $('#auto-quill-excerpt');
            const $select  = $('#auto-quill-categories');
            const selected = new Set((selectedIds || []).map((id) => parseInt(id, 10)));

            $title.val(title);
            $excerpt.val(excerpt);
            $select.empty();
            (availableCategories || []).forEach((cat) => {
                const id = parseInt(cat.id, 10);
                const $opt = $('<option></option>').val(id).text(cat.name);
                if (selected.has(id)) {
                    $opt.prop('selected', true);
                }
                $select.append($opt);
            });
        },

        resetImageSelection: function() {
            this.selectedImage = null;
            const $preview = $('#auto-quill-image-preview');
            $preview
                .addClass('is-empty')
                .empty()
                .append($('<span>').addClass('placeholder').text(t('noImageSelected')));
            $('#auto-quill-clear-image-btn').prop('hidden', true);
        },

        clearImage: function(e) {
            if (e && e.preventDefault) { e.preventDefault(); }
            this.resetImageSelection();
        },

        openImagePicker: function(e) {
            if (e && e.preventDefault) { e.preventDefault(); }
            const $modal = $('#auto-quill-image-modal');
            $modal.prop('hidden', false).attr('aria-hidden', 'false');

            $('#auto-quill-image-grid').empty();
            $('#auto-quill-image-pagination').prop('hidden', true);
            this.imagePage = 1;
            this.imageTotalHits = 0;

            const $input = $('#auto-quill-image-query');
            $input.val('').trigger('focus');

            const title   = ($('#auto-quill-title').val() || '').trim();
            const excerpt = ($('#auto-quill-excerpt').val() || '').trim();

            this.setImageStatus(t('suggestingKeywords'), false);

            $.ajax({
                url: this.apiUrl + 'suggest-image-keywords',
                type: 'POST',
                dataType: 'json',
                data: JSON.stringify({ title: title, excerpt: excerpt }),
                headers: {
                    'X-WP-Nonce': this.restNonce,
                    'Content-Type': 'application/json',
                },
            }).done((response) => {
                const kws = (response && response.keywords) || [];
                if (kws.length) {
                    const initial = kws.join(' ');
                    $input.val(initial);
                    this.searchImages(initial, 1);
                } else {
                    this.setImageStatus('', false);
                }
            }).fail(() => {
                this.setImageStatus('', false);
            });
        },

        closeImagePicker: function(e) {
            if (e && e.preventDefault) { e.preventDefault(); }
            $('#auto-quill-image-modal').prop('hidden', true).attr('aria-hidden', 'true');
        },

        onImageSearchSubmit: function(e) {
            e.preventDefault();
            const query = ($('#auto-quill-image-query').val() || '').trim();
            if (!query) {
                this.setImageStatus(t('enterSearchQuery'), true);
                return;
            }
            this.searchImages(query, 1);
        },

        prevImagePage: function(e) {
            e.preventDefault();
            if (this.imagePage > 1) {
                this.searchImages(this.imageQuery, this.imagePage - 1);
            }
        },

        nextImagePage: function(e) {
            e.preventDefault();
            const totalPages = Math.max(1, Math.ceil(this.imageTotalHits / this.imagePerPage));
            if (this.imagePage < totalPages) {
                this.searchImages(this.imageQuery, this.imagePage + 1);
            }
        },

        searchImages: function(query, page) {
            this.imageQuery = query;
            this.imagePage = page;

            const $grid = $('#auto-quill-image-grid');
            $grid.empty();
            $('#auto-quill-image-pagination').prop('hidden', true);
            this.setImageStatus(t('searchingImages'), false);

            $.ajax({
                url: this.apiUrl + 'search-images',
                type: 'GET',
                dataType: 'json',
                data: { query: query, page: page, per_page: this.imagePerPage },
                headers: { 'X-WP-Nonce': this.restNonce },
            }).done((response) => {
                const images = (response && response.images) || [];
                this.imageTotalHits = (response && response.total_hits) || 0;

                if (!images.length) {
                    this.setImageStatus(t('noImagesFound'), false);
                    return;
                }

                this.setImageStatus('', false);

                images.forEach((img) => {
                    const $card = $('<button>')
                        .attr('type', 'button')
                        .addClass('auto-quill-image-card')
                        .attr('data-url', img.large_url)
                        .attr('data-preview', img.preview_url)
                        .attr('data-alt', this.buildImageAlt(img))
                        .attr('title', img.tags || '');
                    const $img = $('<img>')
                        .attr('src', img.preview_url)
                        .attr('alt', img.tags || '')
                        .attr('loading', 'lazy');
                    $card.append($img);
                    $grid.append($card);
                });

                const totalPages = Math.max(1, Math.ceil(this.imageTotalHits / this.imagePerPage));
                $('#auto-quill-image-page-info').text(
                    (t('imagePageInfo') || 'Seite %1$d von %2$d')
                        .replace('%1$d', this.imagePage)
                        .replace('%2$d', totalPages)
                );
                $('#auto-quill-image-prev').prop('disabled', this.imagePage <= 1);
                $('#auto-quill-image-next').prop('disabled', this.imagePage >= totalPages);
                $('#auto-quill-image-pagination').prop('hidden', false);
            }).fail((xhr) => {
                const msg = (xhr && xhr.responseJSON && xhr.responseJSON.error) || t('imageSearchError');
                this.setImageStatus(msg, true);
            });
        },

        buildImageAlt: function(img) {
            const tags = (img.tags || '').trim();
            const user = (img.user || '').trim();
            if (tags && user) {
                return tags + ' — Foto: ' + user + ' (Pixabay)';
            }
            if (tags) { return tags + ' (Pixabay)'; }
            if (user) { return 'Foto: ' + user + ' (Pixabay)'; }
            return 'Pixabay';
        },

        onImageCardClick: function(e) {
            e.preventDefault();
            const $card = $(e.currentTarget);
            const url   = $card.attr('data-url');
            if (!url) { return; }

            this.selectedImage = {
                url: url,
                alt: $card.attr('data-alt') || '',
                preview: $card.attr('data-preview') || url,
            };

            const $preview = $('#auto-quill-image-preview');
            $preview
                .removeClass('is-empty')
                .empty()
                .append($('<img>').attr('src', this.selectedImage.preview).attr('alt', this.selectedImage.alt));
            $('#auto-quill-clear-image-btn').prop('hidden', false);

            this.closeImagePicker();
        },

        setImageStatus: function(message, isError) {
            const $status = $('#auto-quill-image-status');
            if (!message) {
                $status.prop('hidden', true).removeClass('is-error').text('');
                return;
            }
            $status.prop('hidden', false).toggleClass('is-error', !!isError).text(message);
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
