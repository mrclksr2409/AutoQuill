/**
 * AutoQuill Admin JavaScript
 */

(function($) {
    'use strict';

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
            // Topic auswählen
            $(document).on('click', '.auto-quill-select-topic', this.selectTopic.bind(this));

            // Post veröffentlichen
            $(document).on('click', '#publish-post-btn', this.publishPost.bind(this));
        },

        selectTopic: function(e) {
            e.preventDefault();
            const $btn = $(e.target);
            const topicIndex = $btn.data('topic-index');

            // Hier würde die Topic-Auswahl stattfinden
            // und dann das Blog-Post generieren
            this.generateBlogPost(topicIndex);
        },

        generateBlogPost: function(topicIndex) {
            const $preview = $('#post-preview');
            $preview.html('<p class="auto-quill-loading">Blog-Post wird generiert...</p>');

            // Simulieren Sie hier den API-Aufruf
            $.ajax({
                url: this.apiUrl + 'generate-post',
                type: 'POST',
                dataType: 'json',
                data: JSON.stringify({
                    topic_id: topicIndex,
                    title: $('.topic-card').eq(topicIndex).find('h3').text(),
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
                    } else {
                        this.showAlert('Fehler beim Generieren des Posts', 'error');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Error:', error);
                    this.showAlert('Fehler beim Generieren des Posts', 'error');
                },
            });
        },

        publishPost: function(e) {
            e.preventDefault();
            const $btn = $(e.target);
            const postContent = $btn.data('post-content');

            if (!postContent || !this.currentTopic) {
                this.showAlert('Keine Post-Inhalte verfügbar', 'error');
                return;
            }

            $btn.prop('disabled', true).text('Wird veröffentlicht...');

            $.ajax({
                url: this.apiUrl + 'publish-post',
                type: 'POST',
                dataType: 'json',
                data: JSON.stringify({
                    post_title: this.currentTopic.title,
                    post_content: postContent,
                    topic_id: this.currentTopicId,
                }),
                headers: {
                    'X-WP-Nonce': this.restNonce,
                    'Content-Type': 'application/json',
                },
                success: (response) => {
                    if (response.success) {
                        this.showAlert('Post erfolgreich veröffentlicht!', 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        this.showAlert('Fehler beim Veröffentlichen', 'error');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Error:', error);
                    this.showAlert('Fehler beim Veröffentlichen', 'error');
                },
                complete: () => {
                    $btn.prop('disabled', false).text('Post veröffentlichen');
                },
            });
        },

        showAlert: function(message, type = 'info') {
            const alertClass = `auto-quill-alert auto-quill-alert-${type}`;
            const $alert = $(`<div class="${alertClass}">${message}</div>`);

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

    // Expose to global scope
    window.autoQuillFetchNow = function() {
        $.post(ajaxurl, { action: 'auto_quill_fetch_now', nonce: autoQuill.nonce }, function(response) {
            AutoQuill.showAlert('RSS-Feeds werden aktualisiert...', 'info');
            setTimeout(() => {
                location.reload();
            }, 3000);
        });
    };
})(jQuery);
