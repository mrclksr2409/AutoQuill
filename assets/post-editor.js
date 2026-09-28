/**
 * AutoQuill - shared post editor (right-hand column of the generate and the
 * interview screen): busy overlay, preview, title/excerpt/categories,
 * Pixabay picker, saving and the success box.
 *
 * Markup: GeneratePage::render_post_editor() and render_image_modal().
 * Exposed as window.AutoQuill.PostEditor; the screen scripts (generate.js,
 * interview.js) call init() with their own strings and then fill() with a
 * generate-post shaped response.
 */

(function($) {
    'use strict';

    const shared = window.AutoQuill || {};

    const PostEditor = {
        apiUrl: shared.apiUrl,
        restNonce: shared.restNonce,

        i18n: {},
        backUrl: '',

        currentTopic: null,
        // Extra fields sent to publish-post (topic_id, article_id, interview_id).
        publishContext: {},
        selectedImage: null,
        imageQuery: '',
        imagePage: 1,
        imageTotalHits: 0,
        imagePerPage: 20,

        busy: false,
        busyTimer: null,
        busyStarted: 0,
        hasUnsavedPost: false,
        meta: { title: '', excerpt: '', categoryIds: [] },

        /**
         * @param {Object} options { i18n: {}, backUrl: '' }
         */
        init: function(options) {
            options = options || {};
            this.i18n = options.i18n || {};
            this.backUrl = options.backUrl || '';

            if (!$('#post-preview').length) {
                return;
            }

            this.bindEvents();
            this.guardUnload();
        },

        t: function(key, fallback) {
            if (this.i18n && this.i18n[key]) {
                return this.i18n[key];
            }
            return shared.t ? shared.t(key, fallback) : (fallback || '');
        },

        showAlert: function(message, type) {
            if (shared.showAlert) {
                shared.showAlert(message, type);
            }
        },

        errorMessage: function(xhr, fallbackKey, textStatus) {
            return shared.errorMessage
                ? shared.errorMessage(xhr, fallbackKey, textStatus)
                : this.t(fallbackKey);
        },

        bindEvents: function() {
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

        /**
         * Buttons marked data-aq-busy-disable are disabled while the overlay
         * is up, whichever screen they belong to.
         */
        showBusy: function(label) {
            this.busy = true;
            this.busyStarted = Date.now();

            $('#auto-quill-result-column').attr('aria-busy', 'true');
            $('#auto-quill-generate-error').prop('hidden', true).empty();
            $('[data-aq-busy-disable]').prop('disabled', true);
            $('#auto-quill-busy-label').text(label);
            $('#auto-quill-busy-elapsed').text('');
            $('#auto-quill-busy').prop('hidden', false);

            const tick = () => {
                const seconds = Math.round((Date.now() - this.busyStarted) / 1000);
                $('#auto-quill-busy-elapsed').text(
                    (this.t('elapsedSeconds') || '%d s').replace('%d', seconds)
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
            $('[data-aq-busy-disable]').prop('disabled', false);
        },

        /**
         * A failure after 60 seconds of waiting deserves a box that stays, not
         * an alert that fades out after three.
         */
        showError: function(message, onRetry) {
            const $box = $('#auto-quill-generate-error');
            $box.empty().append($('<p>').text(message));
            if (typeof onRetry === 'function') {
                $box.append(
                    $('<button>')
                        .attr('type', 'button')
                        .addClass('button')
                        .attr('id', 'auto-quill-retry-btn')
                        .text(this.t('retry'))
                        .on('click', (e) => {
                            e.preventDefault();
                            onRetry();
                        })
                );
            }
            $box.prop('hidden', false);
        },

        setPlaceholder: function(text) {
            $('#post-preview').empty().append($('<p>').text(text));
            this.renderMetaFields('', '', [], []);
        },

        clearPreview: function() {
            $('#post-preview').empty();
        },

        /**
         * Shows a generated post.
         *
         * @param {Object} response       generate-post shaped response.
         * @param {Object} publishContext Extra fields for publish-post.
         */
        fill: function(response, publishContext) {
            $('#post-preview').html(response.post_content);
            $('#publish-post-btn').show().prop('disabled', false)
                .text(this.t('publishRetryLabel'))
                .data('post-content', response.post_content);

            this.currentTopic = response.topic || { title: response.post_title || '' };
            this.publishContext = publishContext || {};
            this.hasUnsavedPost = true;

            $('#auto-quill-generate-result').prop('hidden', true).empty();

            this.renderMetaFields(
                response.post_title || (response.topic && response.topic.title) || '',
                response.post_excerpt || '',
                response.available_categories || [],
                response.category_ids || []
            );
            this.resetImageSelection();
        },

        publishPost: function(e) {
            e.preventDefault();
            const $btn = $(e.target);
            const postContent = $btn.data('post-content');

            if (!postContent || !this.currentTopic) {
                this.showAlert(this.t('noContent'), 'error');
                return;
            }

            const postExcerpt = this.meta.excerpt;
            const postTitle   = this.meta.title.trim() || this.currentTopic.title;
            const categoryIds = this.meta.categoryIds;

            $btn.prop('disabled', true).text(this.t('saving'));

            const payload = $.extend({}, this.publishContext, {
                post_title: postTitle,
                post_content: postContent,
                post_excerpt: postExcerpt,
                category_ids: categoryIds,
            });
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
                        this.showAlert(this.t('publishError'), 'error');
                        $btn.prop('disabled', false).text(this.t('publishRetryLabel'));
                        return;
                    }

                    // No location.reload() here: the page would re-run the whole
                    // generation and the draft is already saved.
                    this.hasUnsavedPost = false;
                    $btn.prop('disabled', true);
                    this.renderSuccess(response);
                    $(document).trigger('autoquill:published', [response]);
                },
                error: (xhr, textStatus) => {
                    this.showAlert(this.errorMessage(xhr, 'publishError', textStatus), 'error');
                    $btn.prop('disabled', false).text(this.t('publishRetryLabel'));
                },
            });
        },

        renderSuccess: function(response) {
            const $box = $('#auto-quill-generate-result');
            $box.empty().append($('<p>').text(response.message || this.t('publishSuccess')));

            const $links = $('<p>').addClass('auto-quill-result-links');
            if (response.edit_url) {
                $links.append(
                    $('<a>').addClass('button button-primary')
                        .attr('href', response.edit_url)
                        .text(this.t('editPost'))
                );
            }
            if (this.backUrl) {
                $links.append(
                    $('<a>').addClass('button')
                        .attr('href', this.backUrl)
                        .text(this.t('backToList'))
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

        /** Title, excerpt and categories as read-only text; edited later in WordPress. */
        renderMetaFields: function(title, excerpt, availableCategories, selectedIds) {
            const selected = new Set((selectedIds || []).map((id) => parseInt(id, 10)));
            const names = [];
            const ids = [];
            (availableCategories || []).forEach((cat) => {
                const id = parseInt(cat.id, 10);
                if (selected.has(id)) {
                    ids.push(id);
                    names.push(cat.name);
                }
            });

            this.meta = { title: title || '', excerpt: excerpt || '', categoryIds: ids };

            const show = ($el, text) => {
                $el.text(text || $el.data('placeholder') || '').toggleClass('is-empty', !text);
            };
            show($('#auto-quill-title'), this.meta.title);
            show($('#auto-quill-excerpt'), this.meta.excerpt);
            show($('#auto-quill-categories'), names.join(', '));
        },

        resetImageSelection: function() {
            this.selectedImage = null;
            const $preview = $('#auto-quill-image-preview');
            $preview
                .addClass('is-empty')
                .empty()
                .append($('<span>').addClass('placeholder').text(this.t('noImageSelected')));
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

            const title   = (this.meta.title || '').trim();
            const excerpt = (this.meta.excerpt || '').trim();

            this.setImageStatus(this.t('suggestingKeywords'), false);

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
                this.setImageStatus(this.t('enterSearchQuery'), true);
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
            this.setImageStatus(this.t('searchingImages'), false);

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
                    this.setImageStatus(this.t('noImagesFound'), false);
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
                    (this.t('imagePageInfo') || 'Seite %1$d von %2$d')
                        .replace('%1$d', this.imagePage)
                        .replace('%2$d', totalPages)
                );
                $('#auto-quill-image-prev').prop('disabled', this.imagePage <= 1);
                $('#auto-quill-image-next').prop('disabled', this.imagePage >= totalPages);
                $('#auto-quill-image-pagination').prop('hidden', false);
            }).fail((xhr) => {
                const msg = (xhr && xhr.responseJSON && xhr.responseJSON.error) || this.t('imageSearchError');
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

    window.AutoQuill = window.AutoQuill || {};
    window.AutoQuill.PostEditor = PostEditor;
})(jQuery);
