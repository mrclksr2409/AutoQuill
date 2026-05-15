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
        currentTopic: null,
        currentTopicId: null,
        selectedImage: null,
        imageQuery: '',
        imagePage: 1,
        imageTotalHits: 0,
        imagePerPage: 20,

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $(document).on('click', '.auto-quill-select-topic', this.selectTopic.bind(this));
            $(document).on('click', '#publish-post-btn', this.publishPost.bind(this));
            $(document).on('click', '#auto-quill-recrawl-btn', this.recrawl.bind(this));
            $(document).on('click', '#auto-quill-reselect-btn', this.reselect.bind(this));
            $(document).on('click', '#auto-quill-pick-image-btn', this.openImagePicker.bind(this));
            $(document).on('click', '#auto-quill-clear-image-btn', this.clearImage.bind(this));
            $(document).on('click', '[data-modal-close]', this.closeImagePicker.bind(this));
            $(document).on('submit', '#auto-quill-image-search-form', this.onImageSearchSubmit.bind(this));
            $(document).on('click', '#auto-quill-image-prev', this.prevImagePage.bind(this));
            $(document).on('click', '#auto-quill-image-next', this.nextImagePage.bind(this));
            $(document).on('click', '.auto-quill-image-card', this.onImageCardClick.bind(this));
            $(document).on('keydown', this.onKeydown.bind(this));
        },

        onKeydown: function(e) {
            if (e.key === 'Escape' && !$('#auto-quill-image-modal').prop('hidden')) {
                this.closeImagePicker(e);
            }
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

        selectTopic: function(e) {
            e.preventDefault();
            const $card = $(e.target).closest('.topic-card');
            const topicId = parseInt($card.data('topic-id'), 10);
            const title = $card.find('h3').text();

            this.generateBlogPost(topicId, title);
        },

        generateBlogPost: function(topicId, title) {
            const $preview = $('#post-preview');
            $preview.empty().append(
                $('<p>').addClass('auto-quill-loading').text(t('generating'))
            );

            $.ajax({
                url: this.apiUrl + 'generate-post',
                type: 'POST',
                dataType: 'json',
                data: JSON.stringify({
                    topic_id: topicId,
                    title: title,
                }),
                headers: {
                    'X-WP-Nonce': this.restNonce,
                    'Content-Type': 'application/json',
                },
                success: (response) => {
                    if (response.success) {
                        $preview.html(response.post_content);
                        $('#publish-post-btn').show().data('post-content', response.post_content);
                        this.currentTopic = response.topic;
                        this.currentTopicId = response.topic_id;
                        this.renderMetaFields(
                            response.post_title || (response.topic && response.topic.title) || '',
                            response.post_excerpt || '',
                            response.available_categories || [],
                            response.category_ids || []
                        );
                        this.resetImageSelection();
                    } else {
                        const msg = (response && response.error) || t('generateError');
                        this.showAlert(msg, 'error');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Error:', error);
                    const msg = (xhr && xhr.responseJSON && xhr.responseJSON.error)
                        || t('generateError');
                    this.showAlert(msg, 'error');
                },
            });
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
                    if (response.success) {
                        this.showAlert(t('publishSuccess'), 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        this.showAlert(t('publishError'), 'error');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Error:', error);
                    this.showAlert(t('publishError'), 'error');
                },
                complete: () => {
                    const label = (window.autoQuill && window.autoQuill.publishButtonLabel) || t('publishSuccess');
                    $btn.prop('disabled', false).text(label);
                },
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

    // Initialize when document is ready
    $(document).ready(() => {
        AutoQuill.init();
        SettingsTabs.init();
    });
})(jQuery);
