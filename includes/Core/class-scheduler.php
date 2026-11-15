<?php
namespace AutoQuill\Core;

use AutoQuill\AI\Selector;
use AutoQuill\RSS\Fetcher;

/**
 * RSS fetch and topic selection at configurable local times.
 *
 * Same pattern as the digest in Notifier: self-chaining single events instead
 * of WordPress' 'daily' recurrence, which is a fixed 86400s interval and would
 * drift by an hour at every DST switch - and could not be moved to a chosen
 * time of day anyway.
 */
class Scheduler {
    /** Cron hook => settings key holding its HH:MM local time. */
    const JOBS = [
        Constants::CRON_FETCH  => 'fetch_time',
        Constants::CRON_SELECT => 'select_time',
    ];

    /** Jobs that only run on the active weekdays (run_days). */
    const DAY_GATED = [Constants::CRON_SELECT];

    public static function boot(): void {
        add_action(Constants::CRON_FETCH,  [self::class, 'run_fetch']);
        add_action(Constants::CRON_SELECT, [self::class, 'run_select']);

        // wp_next_scheduled() cannot notice a CHANGED time, so a settings write
        // that moves a time re-plans that job.
        add_action('update_option_' . Constants::OPTION_KEY, [self::class, 'on_settings_updated'], 10, 2);
        add_action('add_option_' . Constants::OPTION_KEY, [self::class, 'reschedule_all']);
    }

    private static function settings(): array {
        $stored = get_option(Constants::OPTION_KEY, []);
        return array_merge(Constants::defaults(), is_array($stored) ? $stored : []);
    }

    /**
     * Weekdays (ISO 1-7) on which the token-spending selection and the digest
     * run. Anything unusable - missing, empty, garbage - means every day: a
     * broken setting must never silently stop the plugin.
     *
     * @return int[]
     */
    public static function active_days(?array $settings = null): array {
        $settings = $settings ?? self::settings();
        $raw      = is_array($settings['run_days'] ?? null) ? $settings['run_days'] : [];
        $days     = array_values(array_intersect(Constants::WEEKDAYS, array_map('intval', $raw)));

        return $days ?: Constants::WEEKDAYS;
    }

    /**
     * Next occurrence of a local HH:MM time, as a UTC timestamp.
     *
     * @param int|null   $from Reference point, for testing.
     * @param int[]|null $days Allowed ISO weekdays; null = every day.
     */
    public static function next_occurrence(string $time, string $fallback, ?int $from = null, ?array $days = null): int {
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time, $m)) {
            preg_match('/^(\d{2}):(\d{2})$/', $fallback, $m);
        }

        $now  = (new \DateTimeImmutable('@' . ($from ?? time())))->setTimezone(wp_timezone());
        $next = $now->setTime((int) $m[1], (int) $m[2], 0);

        if ($next <= $now) {
            $next = $next->modify('+1 day');
        }

        // Walking whole local days keeps the wall-clock time across DST. Seven
        // steps always reach an allowed day when $days is non-empty.
        if ($days) {
            for ($i = 0; $i < 7 && !in_array((int) $next->format('N'), $days, true); $i++) {
                $next = $next->modify('+1 day');
            }
        }

        return $next->getTimestamp();
    }

    public static function next_run_timestamp(string $hook, ?int $from = null): int {
        $key = self::JOBS[$hook];
        return self::next_occurrence(
            (string) (self::settings()[$key] ?? ''),
            Constants::defaults()[$key],
            $from,
            in_array($hook, self::DAY_GATED, true) ? self::active_days() : null
        );
    }

    /**
     * How many hours of fetched articles the selection should look at: back to
     * the previous active day, so Monday after a Friday run also sees the
     * weekend. With every day active this stays the classic 24h. Capped by
     * the RSS lookback, since older articles are pruned anyway.
     *
     * @param int|null $from Reference point, for testing.
     */
    public static function selection_window_hours(?int $from = null): int {
        $settings = self::settings();
        $days     = self::active_days($settings);
        $today    = (int) (new \DateTimeImmutable('@' . ($from ?? time())))->setTimezone(wp_timezone())->format('N');

        $gap = 1;
        while ($gap < 7 && !in_array((($today - $gap - 1 + 7) % 7) + 1, $days, true)) {
            $gap++;
        }

        $hours    = $gap * 24;
        $lookback = (int) ($settings['rss_lookback_days'] ?? Constants::defaults()['rss_lookback_days']);
        if ($lookback > 0) {
            $hours = min($hours, $lookback * 24);
        }

        return max(24, $hours);
    }

    private static function reschedule(string $hook): void {
        wp_clear_scheduled_hook($hook);
        wp_schedule_single_event(self::next_run_timestamp($hook), $hook);
    }

    public static function reschedule_all(): void {
        foreach (array_keys(self::JOBS) as $hook) {
            self::reschedule($hook);
        }
    }

    /**
     * Only a changed time re-plans: clearing unconditionally would drop an
     * overdue run whenever an unrelated setting is saved.
     */
    public static function on_settings_updated($old, $new): void {
        $old      = is_array($old) ? $old : [];
        $new      = is_array($new) ? $new : [];
        $defaults = Constants::defaults();

        $days_changed = self::active_days(array_merge($defaults, $old)) !== self::active_days(array_merge($defaults, $new));

        foreach (self::JOBS as $hook => $key) {
            $time_changed = ($old[$key] ?? $defaults[$key]) !== ($new[$key] ?? $defaults[$key]);
            if ($time_changed || ($days_changed && in_array($hook, self::DAY_GATED, true))) {
                self::reschedule($hook);
            }
        }
    }

    /**
     * Self-heal from Plugin::boot() and activation. Also migrates installs from
     * before 1.5.0, whose jobs are recurring 'daily' events at whatever time
     * the plugin happened to be activated.
     */
    public static function ensure_scheduled(): void {
        foreach (array_keys(self::JOBS) as $hook) {
            $event = wp_get_scheduled_event($hook);
            if (!$event || $event->schedule !== false) {
                self::reschedule($hook);
            }
        }
    }

    public static function run_fetch(): void {
        // Re-chain FIRST: a fatal further down must not break the schedule.
        wp_schedule_single_event(self::next_run_timestamp(Constants::CRON_FETCH, time() + 60), Constants::CRON_FETCH);
        Fetcher::fetch_feeds();
    }

    public static function run_select(): void {
        wp_schedule_single_event(self::next_run_timestamp(Constants::CRON_SELECT, time() + 60), Constants::CRON_SELECT);
        Selector::select_top_topics();
    }
}
