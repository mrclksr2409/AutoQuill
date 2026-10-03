<?php
namespace AutoQuill\Core;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

class Updater {
    private static ?object $checker = null;

    public static function boot(): void {
        $loader = AUTO_QUILL_PLUGIN_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';
        if (!is_file($loader)) {
            return;
        }
        require_once $loader;

        if (!class_exists(PucFactory::class)) {
            return;
        }

        self::$checker = PucFactory::buildUpdateChecker(
            Constants::UPDATE_REPO_URL,
            AUTO_QUILL_PLUGIN_DIR . 'auto-quill.php',
            Constants::UPDATE_SLUG
        );

        // Stable sites follow main, beta sites follow the beta branch.
        self::$checker->setBranch(
            self::is_beta_enabled() ? Constants::UPDATE_BETA_BRANCH : Constants::UPDATE_MAIN_BRANCH
        );

        add_filter(
            self::$checker->getUniqueName('vcs_update_detection_strategies'),
            [self::class, 'filter_strategies']
        );

        add_action(
            'update_option_' . Constants::OPTION_KEY,
            [self::class, 'on_settings_updated'],
            10,
            2
        );
    }

    public static function filter_strategies(array $strategies): array {
        // Ignore releases/tags, always follow the branch HEAD.
        unset($strategies['latest_release'], $strategies['latest_tag']);
        return $strategies;
    }

    /**
     * Drop the cached update info when beta mode is toggled, so the next
     * check queries the newly selected branch instead of the old result.
     */
    public static function on_settings_updated($old_value, $value): void {
        $was_beta = is_array($old_value) && !empty($old_value['beta_mode']);
        $is_beta  = is_array($value) && !empty($value['beta_mode']);
        if ($was_beta !== $is_beta && self::$checker && method_exists(self::$checker, 'resetUpdateState')) {
            self::$checker->resetUpdateState();
        }
    }

    public static function is_beta_enabled(): bool {
        $settings = get_option(Constants::OPTION_KEY, Constants::defaults());
        if (!is_array($settings)) {
            return false;
        }
        return !empty($settings['beta_mode']);
    }

    public static function check_now(): void {
        if (self::$checker && method_exists(self::$checker, 'checkForUpdates')) {
            self::$checker->checkForUpdates();
        }
    }
}
