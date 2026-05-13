/**
 * AutoQuill Admin JavaScript
 */

(function($) {
    'use strict';

    const AutoQuill = {
        apiUrl: autoQuill.apiUrl,
        nonce: autoQuill.nonce,
        currentTopic: null,

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Topic auswählen
            $(document).on('click', '.auto-quill-select-topic', this.selectTopic.bind(this));

            // Post veröffentlichen
            $(document).on('click', '#publish-post-btn', this.publishPost.bind(this));

            // RSS-Quelle hinzufügen
            $(document).on('submit', '#add-source-form', this.addSource.bind(this));

            // RSS-Quelle löschen
            $(document).on('click', '.delete-source', this.deleteSource.bind(this));

            // Einstellungen speichern
            $(document).on('submit', '.auto-quill-settings-form', this.saveSettings.bind(this));
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
                    'X-WP-Nonce': this.nonce,
                    'Content-Type': 'application/json',
                },
                success: (response) => {
                    if (response.success) {
                        $preview.html(response.post_content);
                        $('#publish-post-btn').show().data('post-content', response.post_content);
                        this.currentTopic = response.topic;
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
                }),
                headers: {
                    'X-WP-Nonce': this.nonce,
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

        addSource: function(e) {
            e.preventDefault();
            const $form = $(e.target);
            const title = $form.find('input[name="source_title"]').val();
            const url = $form.find('input[name="source_url"]').val();

            if (!title || !url) {
                this.showAlert('Bitte füllen Sie alle Felder aus', 'error');
                return;
            }

            // Hier würde der API-Aufruf stattfinden
            this.showAlert('RSS-Quelle hinzugefügt', 'success');
            $form.reset();
        },

        deleteSource: function(e) {
            e.preventDefault();
            const sourceId = $(e.target).data('source-id');

            if (!confirm('Wirklich löschen?')) {
                return;
            }

            // Hier würde der API-Aufruf stattfinden
            this.showAlert('RSS-Quelle gelöscht', 'success');
            setTimeout(() => {
                location.reload();
            }, 1000);
        },

        saveSettings: function(e) {
            e.preventDefault();
            this.showAlert('Einstellungen gespeichert', 'success');
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
