/**
 * AutoQuill - interview screen (AutoQuill -> Interview).
 *
 * List view: starts a new interview and deletes old ones.
 * Detail view: the chat with the AI editor on the left; the shared post
 * editor (assets/post-editor.js) on the right shows the post written from it.
 *
 * Everything the AI says is inserted with .text(), never as HTML.
 */

(function($) {
    'use strict';

    const shared = window.AutoQuill || {};
    const config = window.autoQuillInterview || {};
    const Editor = shared.PostEditor;

    const t = function(key, fallback) {
        return Editor ? Editor.t(key, fallback) : ((config.i18n && config.i18n[key]) || fallback || '');
    };

    const request = function(method, path, data, timeout) {
        return $.ajax({
            url: shared.apiUrl + path,
            type: method,
            dataType: 'json',
            data: data ? JSON.stringify(data) : undefined,
            timeout: timeout || 90000,
            headers: {
                'X-WP-Nonce': shared.restNonce,
                'Content-Type': 'application/json',
            },
        });
    };

    const errorMessage = function(xhr, fallbackKey, textStatus) {
        return shared.errorMessage ? shared.errorMessage(xhr, fallbackKey, textStatus) : t(fallbackKey);
    };

    /* ------------------------------------------------------------------ */
    /* List view                                                          */
    /* ------------------------------------------------------------------ */

    const InterviewList = {
        init: function() {
            if (!$('#auto-quill-interview-form').length) {
                return;
            }
            $('#auto-quill-interview-form').on('submit', this.onStart.bind(this));
            $(document).on('click', '.auto-quill-interview-delete', this.onDelete.bind(this));
        },

        onStart: function(e) {
            e.preventDefault();
            const topic = ($('#auto-quill-interview-topic').val() || '').trim();
            const notes = ($('#auto-quill-interview-notes').val() || '').trim();
            if (!topic) {
                $('#auto-quill-interview-topic').trigger('focus');
                return;
            }

            const $btn = $('#auto-quill-interview-start-btn').prop('disabled', true);
            const $spinner = $('#auto-quill-interview-start-spinner').addClass('is-active');
            const $status = $('#auto-quill-interview-start-status').removeClass('is-error').text(t('starting'));

            const goTo = (id) => {
                window.location.href = config.listUrl + '&interview_id=' + encodeURIComponent(id);
            };

            request('POST', 'interviews', { topic: topic, notes: notes }).done((response) => {
                if (response && response.interview && response.interview.id) {
                    goTo(response.interview.id);
                    return;
                }
                $status.addClass('is-error').text((response && response.error) || t('createError'));
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
            }).fail((xhr, textStatus) => {
                // The row may exist even though the first question failed;
                // the detail view offers to ask again.
                const json = xhr && xhr.responseJSON;
                if (json && json.interview && json.interview.id) {
                    goTo(json.interview.id);
                    return;
                }
                $status.addClass('is-error').text(errorMessage(xhr, 'createError', textStatus));
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
            });
        },

        onDelete: function(e) {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            const id = parseInt($btn.data('id'), 10);
            if (!id || !window.confirm(t('confirmDelete'))) {
                return;
            }
            $btn.prop('disabled', true);
            request('DELETE', 'interviews/' + id).done(() => {
                $btn.closest('tr').fadeOut(200, function() { $(this).remove(); });
            }).fail((xhr, textStatus) => {
                $btn.prop('disabled', false);
                if (shared.showAlert) {
                    shared.showAlert(errorMessage(xhr, 'deleteError', textStatus), 'error');
                }
            });
        },
    };

    /* ------------------------------------------------------------------ */
    /* Detail view                                                        */
    /* ------------------------------------------------------------------ */

    const Chat = {
        id: 0,
        interview: null,
        busy: false,
        busyTimer: null,
        wrote: false,

        init: function() {
            this.id = parseInt(config.interviewId, 10) || 0;
            if (!this.id || !$('#auto-quill-chat').length || !Editor) {
                return;
            }

            Editor.init({ i18n: config.i18n || {}, backUrl: config.listUrl || '' });

            $('#auto-quill-chat-form').on('submit', this.onSubmit.bind(this));
            $('#auto-quill-chat-input').on('keydown', this.onInputKeydown.bind(this))
                                       .on('input', this.updateCount.bind(this));
            $('#auto-quill-chat-skip').on('click', this.onSkip.bind(this));
            $('#auto-quill-chat-other').on('click', this.onOtherQuestion.bind(this));
            $('#auto-quill-interview-write').on('click', this.onWrite.bind(this));

            this.load();
        },

        load: function() {
            request('GET', 'interviews/' + this.id).done((response) => {
                if (response && response.interview) {
                    this.render(response.interview);
                    if (response.interview.awaiting === 'question') {
                        this.showChatError(t('questionError'));
                    }
                }
            }).fail((xhr, textStatus) => {
                $('#auto-quill-chat').empty();
                this.showChatError(errorMessage(xhr, 'loadError', textStatus), () => this.load());
            });
        },

        render: function(interview) {
            this.interview = interview;
            const $chat = $('#auto-quill-chat').empty();

            (interview.messages || []).forEach((message) => {
                $chat.append(this.bubble(message));
            });

            this.renderProgress();
            this.updateControls();
            this.scrollToEnd();
        },

        bubble: function(message) {
            const isUser = message.role === 'user';
            const $bubble = $('<div>')
                .addClass('auto-quill-chat-message')
                .addClass(isUser ? 'is-user' : 'is-ai');

            $bubble.append(
                $('<span>').addClass('auto-quill-chat-author').text(isUser ? t('you') : t('editor'))
            );

            const $text = $('<div>').addClass('auto-quill-chat-text');
            if (isUser && message.skipped) {
                $bubble.addClass('is-skipped');
                $text.text(t('skipped'));
            } else {
                $text.text(message.text || '');
            }
            return $bubble.append($text);
        },

        renderProgress: function() {
            const iv = this.interview || {};
            const answers = iv.answers || 0;
            $('#auto-quill-interview-progress').text(
                (t('progress') || '%1$d / %2$d')
                    .replace('%1$d', answers)
                    .replace('%2$d', iv.target || 0)
            );

            const $enough = $('#auto-quill-chat-enough');
            if (iv.enough) {
                $enough.find('p').text(t('enoughHint'));
                $enough.prop('hidden', false);
            } else {
                $enough.prop('hidden', true);
            }
        },

        canWrite: function() {
            const iv = this.interview || {};
            return (iv.answers || 0) >= (iv.min_answers || 3);
        },

        updateControls: function() {
            const iv = this.interview || {};
            const canAnswer = !this.busy && iv.awaiting === 'answer';

            $('#auto-quill-chat-input').prop('disabled', !canAnswer);
            $('#auto-quill-chat-send, #auto-quill-chat-skip, #auto-quill-chat-other').prop('disabled', !canAnswer);

            const writeAllowed = !this.busy && !Editor.busy && this.canWrite();
            $('#auto-quill-interview-write').prop('disabled', !writeAllowed);

            const missing = Math.max(0, (iv.min_answers || 3) - (iv.answers || 0));
            $('#auto-quill-interview-write-hint').text(
                missing > 0 ? (t('needMore') || '').replace('%d', missing) : ''
            );

            this.updateCount();
            if (canAnswer) {
                $('#auto-quill-chat-input').trigger('focus');
            }
        },

        updateCount: function() {
            const max = (this.interview && this.interview.max_chars) || 4000;
            const len = ($('#auto-quill-chat-input').val() || '').length;
            $('#auto-quill-chat-count').text(
                len > 0
                    ? (t('charCount') || '%1$d / %2$d').replace('%1$d', len).replace('%2$d', max)
                    : ''
            );
        },

        scrollToEnd: function() {
            const el = document.getElementById('auto-quill-chat');
            if (el) {
                el.scrollTop = el.scrollHeight;
            }
        },

        setBusy: function(busy, label) {
            this.busy = busy;
            const $typing = $('#auto-quill-chat-typing');

            if (this.busyTimer) {
                window.clearInterval(this.busyTimer);
                this.busyTimer = null;
            }

            if (busy) {
                const started = Date.now();
                $('#auto-quill-chat-typing-label').text(label || t('thinking'));
                const tick = () => {
                    const seconds = Math.round((Date.now() - started) / 1000);
                    $('#auto-quill-chat-typing-elapsed').text((t('elapsedSeconds') || '%d s').replace('%d', seconds));
                };
                tick();
                this.busyTimer = window.setInterval(tick, 1000);
                $typing.prop('hidden', false);
                $('#auto-quill-chat-error').prop('hidden', true).empty();
            } else {
                $typing.prop('hidden', true);
            }
            this.updateControls();
        },

        showChatError: function(message, onRetry) {
            const $box = $('#auto-quill-chat-error').empty().append($('<p>').text(message));
            const retry = onRetry || (() => this.askQuestion(false));
            $box.append(
                $('<button>')
                    .attr('type', 'button')
                    .addClass('button')
                    .text(onRetry ? t('retry') : t('askAgain'))
                    .on('click', (e) => {
                        e.preventDefault();
                        retry();
                    })
            );
            $box.prop('hidden', false);
        },

        onInputKeydown: function(e) {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                $('#auto-quill-chat-form').trigger('submit');
            }
        },

        onSubmit: function(e) {
            e.preventDefault();
            if (this.busy) {
                return;
            }
            const answer = ($('#auto-quill-chat-input').val() || '').trim();
            if (!answer) {
                if (shared.showAlert) {
                    shared.showAlert(t('enterAnswer'), 'error');
                }
                return;
            }
            this.sendAnswer({ answer: answer });
        },

        onSkip: function(e) {
            e.preventDefault();
            if (!this.busy) {
                this.sendAnswer({ skip: true });
            }
        },

        onOtherQuestion: function(e) {
            e.preventDefault();
            if (!this.busy) {
                this.askQuestion(true);
            }
        },

        sendAnswer: function(data) {
            const $input = $('#auto-quill-chat-input');
            const typed = $input.val();

            // Show the answer right away; the server response re-renders the
            // whole conversation anyway.
            $('#auto-quill-chat').append(this.bubble({
                role: 'user',
                text: data.answer || '',
                skipped: !!data.skip,
            }));
            this.scrollToEnd();
            $input.val('');
            this.setBusy(true, t('thinking'));

            request('POST', 'interviews/' + this.id + '/answer', data).done((response) => {
                this.setBusy(false);
                if (response && response.interview) {
                    this.render(response.interview);
                }
            }).fail((xhr, textStatus) => {
                this.setBusy(false);
                const json = xhr && xhr.responseJSON;
                if (json && json.interview) {
                    // Answer stored, only the next question failed.
                    this.render(json.interview);
                    this.showChatError(errorMessage(xhr, 'questionError', textStatus));
                    return;
                }
                // Nothing stored: put the text back so nothing is lost.
                if (this.interview) {
                    this.render(this.interview);
                }
                $input.val(typed);
                this.updateCount();
                this.showChatError(errorMessage(xhr, 'answerError', textStatus), () => this.sendAnswer(data));
            });
        },

        askQuestion: function(replace) {
            this.setBusy(true, t('thinking'));
            request('POST', 'interviews/' + this.id + '/question', { replace: !!replace }).done((response) => {
                this.setBusy(false);
                if (response && response.interview) {
                    this.render(response.interview);
                }
            }).fail((xhr, textStatus) => {
                this.setBusy(false);
                const json = xhr && xhr.responseJSON;
                if (json && json.interview) {
                    this.render(json.interview);
                }
                this.showChatError(errorMessage(xhr, 'questionError', textStatus));
            });
        },

        onWrite: function(e) {
            e.preventDefault();
            if (this.busy || Editor.busy || !this.canWrite()) {
                return;
            }
            if (Editor.hasUnsavedPost && !window.confirm(t('confirmRewrite'))) {
                return;
            }
            this.write();
        },

        write: function() {
            Editor.setPlaceholder(t('writing'));
            Editor.showBusy(t('writing'));
            this.updateControls();

            request('POST', 'interviews/' + this.id + '/write', {}, 120000).done((response) => {
                if (!response || !response.success) {
                    Editor.showError((response && response.error) || t('writeError'), () => this.write());
                    Editor.clearPreview();
                    return;
                }
                Editor.fill(response, { interview_id: this.id });
                this.wrote = true;
                $('#auto-quill-interview-write').text(t('rewrite'));
            }).fail((xhr, textStatus) => {
                Editor.showError(errorMessage(xhr, 'writeError', textStatus), () => this.write());
                Editor.clearPreview();
            }).always(() => {
                Editor.hideBusy();
                this.updateControls();
            });
        },
    };

    $(document).ready(() => {
        InterviewList.init();
        Chat.init();
    });
})(jQuery);
