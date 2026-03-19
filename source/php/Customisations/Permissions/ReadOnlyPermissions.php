<?php

declare(strict_types=1);

namespace PiteaCustomisation\Customisations\Permissions;

use PiteaCustomisation\Admin\Tabs\PagePermissionsTab;

/**
 * Class ReadOnlyPermissions
 *
 * Prevents non-administrators from editing, moving, or deleting pages that
 * have been marked as protected in the General settings tab.
 *
 * Respects the per-page "inherit" flag: when set, protection is extended
 * to all descendant pages recursively.
 */
class ReadOnlyPermissions
{
    public function __construct()
    {
        // Deletes are still hard-blocked via capability.
        add_filter('map_meta_cap', [$this, 'restrictProtectedPages'], 10, 4);

        // Block saves: REST API path (Block Editor) returns a WP_Error.
        add_filter('rest_pre_insert_page', [$this, 'blockProtectedPageRestSave'], 10, 2);

        // Block saves: classic editor path silently reverts to existing data
        // and queues an admin notice via transient.
        add_filter('wp_insert_post_data', [$this, 'blockProtectedPageSave'], 10, 2);
        add_action('admin_notices', [$this, 'showProtectedPageNotice']);
    }

    /**
     * Deny edit/delete capabilities on protected pages for non-administrators.
     *
     * @param string[]             $caps    Required primitive caps (may become ['do_not_allow']).
     * @param string               $cap     Meta capability being checked.
     * @param int                  $userId  User ID.
     * @param array<int, mixed>    $args    Contextual args; $args[0] is the post ID for post caps.
     * @return string[]
     */
    public function restrictProtectedPages(array $caps, string $cap, int $userId, array $args): array
    {        // Only block delete capabilities; edit is allowed so the page can be opened.
        $watchedCaps = ['delete_post', 'delete_page'];
        if (!in_array($cap, $watchedCaps, true)) {
            return $caps;
        }

        $postId = isset($args[0]) ? (int) $args[0] : 0;
        if ($postId === 0) {
            return $caps;
        }

        // Administrators are exempt from restrictions.
        $user = get_userdata($userId);
        if ($user && in_array('administrator', (array) $user->roles, true)) {
            return $caps;
        }

        if (!in_array($postId, $this->getProtectedPageIds(), true)) {
            return $caps;
        }

        return ['do_not_allow'];
    }

    /**
     * @param object|array $prepared Prepared post object/array.
     * @param \WP_REST_Request $request Current request.
     * @return object|\WP_Error
     */
    public function blockProtectedPageRestSave($prepared, \WP_REST_Request $request)
    {
        $postId = isset($prepared->ID) ? (int) $prepared->ID : 0;
        if ($postId === 0) {
            return $prepared;
        }

        if (!$this->isProtectedForCurrentUser($postId)) {
            return $prepared;
        }

        return new \WP_Error(
            'page_protected',
            __('This page is protected and cannot be edited.', 'pitea-customisation'),
            ['status' => 403]
        );
    }

    /**
     * Block classic editor saves on protected pages by reverting to existing
     * post data and queuing an admin notice.
     *
     * @param  array<string, mixed>  $data    Sanitised post data about to be saved.
     * @param  array<string, mixed>  $postarr Raw POST data including the post ID.
     * @return array<string, mixed>
     */
    public function blockProtectedPageSave(array $data, array $postarr): array
    {
        $postId = isset($postarr['ID']) ? (int) $postarr['ID'] : 0;
        if ($postId === 0) {
            return $data;
        }

        if (!$this->isProtectedForCurrentUser($postId)) {
            return $data;
        }

        // Revert every posts-table field to the currently saved values so the
        // UPDATE query becomes a no-op for content/structure.
        $existing = get_post($postId, ARRAY_A);
        if (!$existing) {
            return $data;
        }

        foreach (
            ['post_title', 'post_content', 'post_excerpt', 'post_parent',
             'post_name', 'post_status', 'menu_order', 'post_password']
            as $field
        ) {
            if (array_key_exists($field, $existing)) {
                $data[$field] = $existing[$field];
            }
        }

        // Queue a notice so the user knows their changes were not applied.
        set_transient('pitea_protected_page_notice_' . get_current_user_id(), true, 60);

        return $data;
    }

    /**
     * Display an admin notice when a classic-editor save was silently blocked.
     */
    public function showProtectedPageNotice(): void
    {
        $key = 'pitea_protected_page_notice_' . get_current_user_id();
        if (!get_transient($key)) {
            return;
        }
        delete_transient($key);
        echo '<div class="notice notice-error"><p>'
            . esc_html__('This page is protected. Your changes were not saved.', 'pitea-customisation')
            . '</p></div>';
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Returns true when the given post is protected and the current user is
     * not an administrator or super-administrator.
     */
    private function isProtectedForCurrentUser(int $postId): bool
    {
        $userId = get_current_user_id();

        if (is_multisite() && is_super_admin($userId)) {
            return false;
        }

        $user = get_userdata($userId);
        if ($user && in_array('administrator', (array) $user->roles, true)) {
            return false;
        }

        return in_array($postId, $this->getProtectedPageIds(), true);
    }

    /**
     * Return the full set of protected page IDs, expanding inherited hierarchies.
     *
     * Result is cached per request.
     *
     * @return int[]
     */
    private function getProtectedPageIds(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $raw  = (string) get_option(PagePermissionsTab::OPTION_PAGE_PERMISSIONS, '[]');
        $rows = json_decode($raw, true);
        if (!is_array($rows)) {
            $cache = [];
            return $cache;
        }

        $ids = [];
        foreach ($rows as $row) {
            $pageId = (int) ($row['page_id'] ?? 0);
            if ($pageId === 0) {
                continue;
            }

            $ids[] = $pageId;

            if (!empty($row['inherit'])) {
                $ids = array_merge($ids, $this->getDescendantIds($pageId));
            }
        }

        $cache = array_unique($ids);
        return $cache;
    }

    /**
     * Recursively collect all descendant page IDs for a given parent.
     *
     * Uses suppress_filters so pages hidden from public queries are still found.
     *
     * @return int[]
     */
    private function getDescendantIds(int $parentId): array
    {
        $children = get_posts([
            'post_type'        => 'page',
            'post_status'      => ['publish', 'private'],
            'post_parent'      => $parentId,
            'numberposts'      => -1,
            'fields'           => 'ids',
            'suppress_filters' => true,
        ]);

        $ids = [];
        foreach ($children as $childId) {
            $childId = (int) $childId;
            $ids[]   = $childId;
            $ids     = array_merge($ids, $this->getDescendantIds($childId));
        }

        return $ids;
    }
}
