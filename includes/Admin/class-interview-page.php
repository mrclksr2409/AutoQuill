<?php
namespace AutoQuill\Admin;

use AutoQuill\Core\Constants as C;
use AutoQuill\Database\InterviewsRepository;
use AutoQuill\Database\Schema;

/**
 * AutoQuill -> Interview.
 *
 * Without interview_id: start form plus the list of earlier interviews.
 * With interview_id: the chat on the left, the shared post editor on the
 * right. Both views are driven by assets/interview.js over the REST API; the
 * PHP side only renders the frame.
 */
class InterviewPage {
    const LIST_LIMIT = 50;

    public static function requested_id(): int {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view switch.
        return isset($_GET['interview_id']) ? absint($_GET['interview_id']) : 0;
    }

    public static function list_url(): string {
        return admin_url('admin.php?page=' . C::INTERVIEW_PAGE_SLUG);
    }

    public static function detail_url(int $id): string {
        return add_query_arg('interview_id', $id, self::list_url());
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Zugriff verweigert', 'auto-quill'));
        }

        Schema::ensure_tables();

        $id = self::requested_id();
        if ($id > 0) {
            $row = (new InterviewsRepository())->find($id);
            if (!$row) {
                self::render_missing();
                return;
            }
            self::render_detail($row);
            return;
        }

        self::render_list();
    }

    /**
     * @return array<string, string>
     */
    public static function status_labels(): array {
        return [
            'open'      => __('Läuft', 'auto-quill'),
            'drafted'   => __('Beitrag entworfen', 'auto-quill'),
            'published' => __('Beitrag gespeichert', 'auto-quill'),
        ];
    }

    /** WP-Backend UI badge variant for an interview status. */
    private static function status_variant(string $status): string {
        switch ($status) {
            case 'published':
                return 'success';
            case 'drafted':
                return 'info';
            default:
                return 'neutral';
        }
    }

    /**
     * @param bool $back_link Detail views link back to the list.
     */
    private static function render_header(bool $back_link = false): void {
        $actions = [];
        if ($back_link) {
            $actions[] = [
                'label' => __('Zurück zu den Interviews', 'auto-quill'),
                'url'   => self::list_url(),
            ];
        }

        \WPB_Admin_UI::header([
            'title'    => __('Interview', 'auto-quill'),
            'subtitle' => __('Blog-Beiträge im Gespräch mit dem KI-Redakteur', 'auto-quill'),
            'icon'     => 'dashicons-rss',
            'version'  => AUTO_QUILL_VERSION,
            'actions'  => $actions,
        ]);
    }

    private static function render_list(): void {
        $repo   = new InterviewsRepository();
        $rows   = $repo->list_recent(self::LIST_LIMIT);
        $total  = $repo->count();
        $labels = self::status_labels();
        ?>
        <div class="wrap auto-quill-wrap">
            <?php self::render_header(); ?>

            <?php Notices::flush(); ?>

            <div class="wpb-card auto-quill-interview-start">
                <div class="wpb-card__header">
                    <div>
                        <h2 class="wpb-card__title"><?php esc_html_e('Neues Interview', 'auto-quill'); ?></h2>
                        <p class="wpb-card__description">
                            <?php esc_html_e('Die KI übernimmt die Rolle eines Redakteurs: Sie stellt dir nacheinander Fragen zu deinem Thema, du antwortest im Chat. Aus deinen Antworten entsteht anschließend ein Blog-Beitrag. Die Perspektive des Beitrags legst du unter Einstellungen → Interview fest.', 'auto-quill'); ?>
                        </p>
                    </div>
                </div>
                <div class="wpb-card__body">
                    <form id="auto-quill-interview-form">
                        <p>
                            <label for="auto-quill-interview-topic"><strong><?php esc_html_e('Thema', 'auto-quill'); ?></strong></label><br>
                            <input type="text" id="auto-quill-interview-topic" class="large-text" maxlength="200" required
                                   placeholder="<?php esc_attr_e('z. B. Meine Erfahrungen mit Homeoffice im Handwerk', 'auto-quill'); ?>">
                        </p>
                        <p>
                            <label for="auto-quill-interview-notes"><strong><?php esc_html_e('Hinweise für den Redakteur', 'auto-quill'); ?></strong>
                                <span class="description"><?php esc_html_e('(optional: Zielgruppe, Stichpunkte, was unbedingt vorkommen soll)', 'auto-quill'); ?></span>
                            </label><br>
                            <textarea id="auto-quill-interview-notes" class="large-text" rows="3" maxlength="2000"></textarea>
                        </p>
                        <p>
                            <button type="submit" class="button button-primary" id="auto-quill-interview-start-btn">
                                <?php esc_html_e('Interview starten', 'auto-quill'); ?>
                            </button>
                            <span class="spinner" id="auto-quill-interview-start-spinner"></span>
                            <span id="auto-quill-interview-start-status" class="auto-quill-interview-start-status" role="status" aria-live="polite"></span>
                        </p>
                    </form>
                </div>
            </div>

            <div class="wpb-card auto-quill-interview-list">
                <div class="wpb-card__header">
                    <h2 class="wpb-card__title"><?php esc_html_e('Bisherige Interviews', 'auto-quill'); ?></h2>
                </div>
                <div class="wpb-card__body">
                    <?php if (empty($rows)): ?>
                        <p class="wpb-empty"><?php esc_html_e('Noch keine Interviews geführt.', 'auto-quill'); ?></p>
                    <?php else: ?>
                        <table class="widefat striped auto-quill-feed-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Thema', 'auto-quill'); ?></th>
                                    <th><?php esc_html_e('Antworten', 'auto-quill'); ?></th>
                                    <th><?php esc_html_e('Status', 'auto-quill'); ?></th>
                                    <th><?php esc_html_e('Zuletzt bearbeitet', 'auto-quill'); ?></th>
                                    <th><?php esc_html_e('Aktionen', 'auto-quill'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $row):
                                    $id       = (int) $row->id;
                                    $answers  = InterviewsRepository::answer_count(InterviewsRepository::messages($row));
                                    $status   = (string) $row->status;
                                    $post_id  = (int) $row->post_id;
                                    $edit_url = $post_id > 0 && get_post($post_id) ? get_edit_post_link($post_id) : '';
                                    ?>
                                    <tr data-interview-id="<?php echo $id; ?>">
                                        <td>
                                            <a href="<?php echo esc_url(self::detail_url($id)); ?>"><strong><?php echo esc_html((string) $row->topic); ?></strong></a>
                                        </td>
                                        <td><?php echo (int) $answers; ?></td>
                                        <td>
                                            <?php
                                            // badge() escapes its output.
                                            echo \WPB_Admin_UI::badge(
                                                $labels[$status] ?? $status,
                                                self::status_variant($status)
                                            );
                                            ?>
                                            <?php if ($edit_url): ?>
                                                <br><a href="<?php echo esc_url($edit_url); ?>"><?php esc_html_e('Post bearbeiten', 'auto-quill'); ?></a>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), (string) $row->updated_at)); ?></td>
                                        <td>
                                            <a class="button button-small" href="<?php echo esc_url(self::detail_url($id)); ?>">
                                                <?php echo $status === 'open' ? esc_html__('Fortsetzen', 'auto-quill') : esc_html__('Öffnen', 'auto-quill'); ?>
                                            </a>
                                            <button type="button" class="button-link button-link-delete auto-quill-interview-delete" data-id="<?php echo $id; ?>">
                                                <?php esc_html_e('Löschen', 'auto-quill'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if ($total > count($rows)): ?>
                            <p class="description">
                                <?php
                                printf(
                                    /* translators: 1: shown, 2: total */
                                    esc_html__('Es werden die %1$d zuletzt bearbeiteten von %2$d Interviews angezeigt.', 'auto-quill'),
                                    count($rows),
                                    (int) $total
                                );
                                ?>
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    private static function render_detail($row): void {
        $settings = get_option(C::OPTION_KEY, C::defaults());
        if (!is_array($settings)) {
            $settings = C::defaults();
        }
        $notes = trim((string) $row->notes);
        ?>
        <div class="wrap auto-quill-wrap">
            <?php self::render_header(true); ?>

            <?php Notices::flush(); ?>

            <div class="wpb-grid wpb-grid--2">
                <div class="wpb-card auto-quill-interview-column">
                    <div class="wpb-card__header">
                        <div>
                            <h2 class="wpb-card__title"><?php echo esc_html((string) $row->topic); ?></h2>
                            <?php if ($notes !== ''): ?>
                                <p class="wpb-card__description auto-quill-interview-notes"><?php echo esc_html($notes); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="wpb-card__body">
                        <p class="auto-quill-interview-progress" id="auto-quill-interview-progress" aria-live="polite"></p>

                        <div class="auto-quill-chat" id="auto-quill-chat" role="log" aria-live="polite" aria-relevant="additions">
                            <p class="auto-quill-chat-loading wpb-muted"><?php esc_html_e('Interview wird geladen…', 'auto-quill'); ?></p>
                        </div>

                        <div class="auto-quill-chat-typing" id="auto-quill-chat-typing" hidden>
                            <span class="auto-quill-typing-dots" aria-hidden="true"><span></span><span></span><span></span></span>
                            <span id="auto-quill-chat-typing-label"></span>
                            <span id="auto-quill-chat-typing-elapsed" class="auto-quill-busy-elapsed"></span>
                        </div>

                        <div class="auto-quill-chat-error wpb-alert wpb-alert--error" id="auto-quill-chat-error" role="alert" hidden></div>

                        <div class="wpb-alert wpb-alert--success auto-quill-chat-enough" id="auto-quill-chat-enough" role="status" hidden>
                            <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                            <div class="wpb-alert__body"><p></p></div>
                        </div>

                        <form class="auto-quill-chat-form" id="auto-quill-chat-form">
                            <label for="auto-quill-chat-input" class="screen-reader-text"><?php esc_html_e('Deine Antwort', 'auto-quill'); ?></label>
                            <textarea id="auto-quill-chat-input" rows="4" class="large-text"
                                      maxlength="<?php echo (int) C::INTERVIEW_ANSWER_MAX_CHARS; ?>"
                                      placeholder="<?php esc_attr_e('Deine Antwort… (Strg+Enter zum Senden)', 'auto-quill'); ?>" disabled></textarea>
                            <div class="auto-quill-chat-actions">
                                <button type="submit" class="button button-primary" id="auto-quill-chat-send" disabled>
                                    <?php esc_html_e('Antworten', 'auto-quill'); ?>
                                </button>
                                <button type="button" class="button" id="auto-quill-chat-skip" disabled>
                                    <?php esc_html_e('Frage überspringen', 'auto-quill'); ?>
                                </button>
                                <button type="button" class="button-link" id="auto-quill-chat-other" disabled>
                                    <?php esc_html_e('Andere Frage', 'auto-quill'); ?>
                                </button>
                                <span class="auto-quill-chat-count wpb-muted wpb-small" id="auto-quill-chat-count"></span>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="wpb-card auto-quill-result-column" id="auto-quill-result-column">
                    <div class="wpb-card__header">
                        <h2 class="wpb-card__title"><?php esc_html_e('Beitrag aus dem Interview', 'auto-quill'); ?></h2>
                    </div>
                    <div class="wpb-card__body">
                        <p class="auto-quill-generate-actions">
                            <button type="button" class="button button-primary" id="auto-quill-interview-write" data-aq-busy-disable disabled>
                                <?php esc_html_e('Beitrag schreiben', 'auto-quill'); ?>
                            </button>
                            <span class="description" id="auto-quill-interview-write-hint"></span>
                        </p>

                        <?php
                        GeneratePage::render_post_editor(
                            Dashboard::publish_button_label($settings),
                            __('Sobald genug Antworten da sind, auf „Beitrag schreiben" klicken.', 'auto-quill')
                        );
                        ?>
                    </div>
                </div>
            </div>

            <?php GeneratePage::render_image_modal(); ?>
        </div>
        <?php
    }

    private static function render_missing(): void {
        ?>
        <div class="wrap auto-quill-wrap">
            <?php self::render_header(true); ?>
            <div class="notice notice-error"><p><?php esc_html_e('Interview nicht gefunden', 'auto-quill'); ?></p></div>
        </div>
        <?php
    }
}
