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

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $(document).on('click', '.auto-quill-select-topic', this.selectTopic.bind(this));
            $(document).on('click', '#publish-post-btn', this.publishPost.bind(this));
            $(document).on('click', '#auto-quill-recrawl-btn', this.recrawl.bind(this));
            $(document).on('click', '#auto-quill-reselect-btn', this.reselect.bind(this));
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
                            response.post_excerpt || '',
                            response.available_categories || [],
                            response.category_ids || []
                        );
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

        renderMetaFields: function(excerpt, availableCategories, selectedIds) {
            const $excerpt = $('#auto-quill-excerpt');
            const $select  = $('#auto-quill-categories');
            const selected = new Set((selectedIds || []).map((id) => parseInt(id, 10)));

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

            $('#auto-quill-meta-fields').show();
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
            const categoryIds = ($('#auto-quill-categories').val() || [])
                .map((v) => parseInt(v, 10))
                .filter((v) => !isNaN(v));

            $btn.prop('disabled', true).text(t('saving'));

            $.ajax({
                url: this.apiUrl + 'publish-post',
                type: 'POST',
                dataType: 'json',
                data: JSON.stringify({
                    post_title: this.currentTopic.title,
                    post_content: postContent,
                    post_excerpt: postExcerpt,
                    category_ids: categoryIds,
                    topic_id: this.currentTopicId,
                }),
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

    // Initialize when document is ready
    $(document).ready(() => {
        AutoQuill.init();
    });
})(jQuery);
