<?php
namespace AutoQuill\Core;

/**
 * Who may use AutoQuill's working screens (dashboard, generate, interview,
 * RSS sources).
 *
 * CAP is never stored on a role: it is granted at check time through
 * user_has_cap, so the role selection in the settings takes effect at once
 * and nothing is left behind on uninstall. Administrators always have it.
 *
 * Settings, logs and backups stay on manage_options - they expose API keys,
 * payloads and this very role selection.
 */
class Access {
    const CAP = 'auto_quill_use';

    /**
     * A role must be able to publish on its own: AutoQuill creates posts with
     * the configured status (possibly "publish") and sideloads images.
     */
    const ROLE_REQUIRED_CAP = 'publish_posts';

    public static function boot(): void {
        add_filter('user_has_cap', [self::class, 'grant'], 10, 4);
    }

    /**
     * @param array    $allcaps All capabilities of the user.
     * @param array    $caps    Primitive capabilities being checked.
     * @param array    $args    [0] is the requested capability.
     * @param \WP_User $user
     */
    public static function grant($allcaps, $caps, $args, $user) {
        if (!is_array($allcaps) || ($args[0] ?? '') !== self::CAP || !$user instanceof \WP_User) {
            return $allcaps;
        }

        // Read from $allcaps rather than user_can(): that would re-enter
        // this filter.
        if (!empty($allcaps['manage_options'])) {
            $allcaps[self::CAP] = true;
        } elseif (!empty($allcaps[self::ROLE_REQUIRED_CAP])
            && array_intersect((array) $user->roles, self::allowed_roles())
        ) {
            $allcaps[self::CAP] = true;
        }

        return $allcaps;
    }

    /** @return string[] Role slugs picked in the settings. */
    public static function allowed_roles(): array {
        $settings = get_option(Constants::OPTION_KEY, Constants::defaults());
        $roles    = is_array($settings) && is_array($settings['access_roles'] ?? null)
            ? $settings['access_roles']
            : [];
        return array_values(array_filter(array_map('strval', $roles)));
    }

    /**
     * Users who currently have CAP, e.g. as notification recipients.
     *
     * get_users()'s capability query reads role definitions and cannot see a
     * capability granted through user_has_cap, so the candidate roles are
     * resolved here and every user is checked once more.
     *
     * @return object[] Rows with ID, display_name, user_email.
     */
    public static function users(int $limit = 200): array {
        $roles = self::allowed_roles();
        foreach (wp_roles()->roles as $slug => $role) {
            if (!empty($role['capabilities']['manage_options'])) {
                $roles[] = (string) $slug;
            }
        }
        if (empty($roles)) {
            return [];
        }

        $users = get_users([
            'role__in' => array_values(array_unique($roles)),
            'fields'   => ['ID', 'display_name', 'user_email'],
            'number'   => $limit,
            'orderby'  => 'display_name',
        ]);

        return array_values(array_filter(
            $users,
            static fn($user) => user_can((int) $user->ID, self::CAP)
        ));
    }

    /**
     * Roles that can be granted access, administrator excluded (it always
     * has access).
     *
     * @return array<string, string> slug => translated name
     */
    public static function selectable_roles(): array {
        $out = [];
        foreach (wp_roles()->roles as $slug => $role) {
            if ($slug === 'administrator' || empty($role['capabilities'][self::ROLE_REQUIRED_CAP])) {
                continue;
            }
            $out[(string) $slug] = translate_user_role((string) $role['name']);
        }
        return $out;
    }
}
