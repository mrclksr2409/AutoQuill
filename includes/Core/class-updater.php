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

        self::$checker->setBranch(Constants::UPDATE_MAIN_BRANCH);

        add_filter(
            'puc_vcs_update_detection_strategies-' . Constants::UPDATE_SLUG,
            [self::class, 'filter_strategies']
        );
    }

    public static function filter_strategies(array $strategies): array {
        if (!self::is_beta_enabled()) {
            return $strategies;
        }

        // Beta mode: ignore releases/tags, always follow the branch HEAD.
        unset($strategies['latest_release'], $strategies['latest_tag']);
        return $strategies;
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
