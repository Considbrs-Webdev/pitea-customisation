<?php

declare(strict_types=1);

namespace PiteaCustomisation\Customisations\Permissions;

use PiteaCustomisation\Admin\Tabs\PagePermissionsTab;

/**
 * Class PageTreeOwnership
 *
 * Enforces page tree ownership rules: users who do not match the configured
 * user-group and/or user-role rules for a page tree cannot see or access those
 * pages in the admin.
 *
 * Administrators and super-administrators are always exempt.
 */
class PageTreeOwnership
{
    public function __construct()
    {
        // Filter the standard admin Pages list view (WP_List_Table).
        add_action('pre_get_posts', [$this, 'filterAdminPagesList']);

        // Filter the Nested Pages plugin's own WP_Query (not is_main_query).
        add_filter('nestedpages_page_listing', [$this, 'filterNestedPagesQuery'], 10, 2);
        add_filter('nestedpages_post_tree_parent', [$this, 'filterNestedPagesTreeQuery']);
        add_filter('nestedpages_post_tree_children', [$this, 'filterNestedPagesTreeQuery']);
        add_filter('the_posts', [$this, 'reparentNestedPagesListingPosts'], 10, 2);

        // Block direct read/edit/delete access to owned pages the user can't see.
        add_filter('map_meta_cap', [$this, 'restrictOwnedPageAccess'], 10, 4);
    }

    // -------------------------------------------------------------------------
    // Hooks
    // -------------------------------------------------------------------------

    /**
     * Limit the admin Pages list query to pages the current user is permitted to see.
     *
     * When ownership rules are configured, non-privileged users may only see pages
     * that belong to one of their matching groups/roles. Pages outside those trees
     * are hidden entirely (post__in approach rather than post__not_in).
     */
    public function filterAdminPagesList(\WP_Query $query): void
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        if ($query->get('post_type') !== 'page') {
            return;
        }

        $userId = get_current_user_id();
        if ($this->isPrivilegedUser($userId)) {
            return;
        }

        if (empty($this->getOwnershipRules())) {
            return;
        }

        $accessibleIds = $this->getAccessiblePageIdsForUser($userId);
        // Use post__in so only explicitly accessible pages are shown.
        // If the user owns nothing, pass a non-existent ID to produce an empty list.
        $query->set('post__in', empty($accessibleIds) ? [0] : $accessibleIds);
    }

    /**
     * Limit Nested Pages' own WP_Query to pages the current user is permitted to see.
     *
     * Nested Pages bypasses is_main_query() by running a separate WP_Query and
     * exposes the query args via the nestedpages_page_listing filter.
     *
     * @param  array<string, mixed> $queryArgs
     * @param  mixed                $postType
     * @return array<string, mixed>
     */
    public function filterNestedPagesQuery(array $queryArgs, mixed $postType = null): array
    {
        if (!$this->isPagePostType($postType) && !$this->isPagePostType($queryArgs['post_type'] ?? null)) {
            return $queryArgs;
        }

        return $this->limitQueryArgsToAccessiblePages($queryArgs, true);
    }

    /**
     * Limit Nested Pages' tree parent/children queries to pages the current user is permitted to see.
     *
     * @param  array<string, mixed> $queryArgs
     * @return array<string, mixed>
     */
    public function filterNestedPagesTreeQuery(array $queryArgs): array
    {
        if (!$this->isPagePostType($queryArgs['post_type'] ?? null)) {
            return $queryArgs;
        }

        return $this->limitQueryArgsToAccessiblePages($queryArgs);
    }

    /**
     * Deny read/edit/delete capabilities on pages the current user cannot access.
     *
     * @param string[]          $caps
     * @param string            $cap
     * @param int               $userId
     * @param array<int, mixed> $args
     * @return string[]
     */
    public function restrictOwnedPageAccess(array $caps, string $cap, int $userId, array $args): array
    {
        $watchedCaps = ['read_post', 'read_page', 'edit_post', 'edit_page', 'delete_post', 'delete_page'];
        if (!in_array($cap, $watchedCaps, true)) {
            return $caps;
        }

        $postId = isset($args[0]) ? (int) $args[0] : 0;
        if ($postId === 0) {
            return $caps;
        }

        if (!$this->isRestrictedForUser($postId, $userId)) {
            return $caps;
        }

        return ['do_not_allow'];
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    private function isRestrictedForUser(int $postId, int $userId): bool
    {
        if ($this->isPrivilegedUser($userId)) {
            return false;
        }

        if (empty($this->getOwnershipRules())) {
            return false;
        }

        // Only apply restrictions to pages that are explicitly covered by some ownership rule.
        // New pages, auto-drafts, and pages outside all ownership rules are not restricted
        // here — normal WordPress capability checks handle those.
        if (!in_array($postId, $this->getAllOwnedPageIds(), true)) {
            return false;
        }

        // Page is covered by an ownership rule; restrict if it is not accessible to the user.
        return !in_array($postId, $this->getAccessiblePageIdsForUser($userId), true);
    }

    private function isPrivilegedUser(int $userId): bool
    {
        if (is_multisite() && is_super_admin($userId)) {
            return true;
        }

        $user = get_userdata($userId);
        return $user && in_array('administrator', (array) $user->roles, true);
    }

    /**
     * @param  array<string, mixed> $queryArgs
     * @return array<string, mixed>
     */
    public function reparentNestedPagesListingPosts(array $posts, \WP_Query $query): array
    {
        if (!$query->get('pitea_root_nested_pages_listing')) {
            return $posts;
        }

        if (empty($posts)) {
            return $posts;
        }

        $postIds = [];
        foreach ($posts as $post) {
            $postIds[(int) $post->ID] = true;
        }

        foreach ($posts as $post) {
            $parentId = (int) $post->post_parent;
            if ($parentId !== 0 && !isset($postIds[$parentId])) {
                $post->post_parent = 0;
            }
        }

        return $posts;
    }

    private function limitQueryArgsToAccessiblePages(array $queryArgs, bool $rootNestedPagesListing = false): array
    {
        $userId = get_current_user_id();
        if ($this->isPrivilegedUser($userId)) {
            return $queryArgs;
        }

        if (empty($this->getOwnershipRules())) {
            return $queryArgs;
        }

        $accessibleIds = $this->getAccessiblePageIdsForUser($userId);
        $queryArgs['post__in'] = empty($accessibleIds) ? [0] : $accessibleIds;
        if ($rootNestedPagesListing) {
            $queryArgs['pitea_root_nested_pages_listing'] = true;
        }

        // Ensure no conflicting exclusion list remains.
        unset($queryArgs['post__not_in']);

        return $queryArgs;
    }

    private function isPagePostType(mixed $postType): bool
    {
        if (is_object($postType) && isset($postType->name)) {
            $postType = $postType->name;
        }

        if (is_string($postType)) {
            return in_array($postType, ['page', 'np-redirect'], true);
        }

        if (is_array($postType)) {
            foreach ($postType as $singlePostType) {
                if ($this->isPagePostType($singlePostType)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Return the page IDs the given user may see, based on page tree ownership rules.
     *
     * Only pages (and their descendants, when inherit is enabled) that belong to one of
     * the user's matching groups and/or roles are returned. Pages outside all ownership
     * rules are NOT included — access is strictly additive (positive-permission model).
     *
     * @return int[]
     */
    private function getAccessiblePageIdsForUser(int $userId): array
    {
        static $cache = [];
        if (array_key_exists($userId, $cache)) {
            return $cache[$userId];
        }

        $rules = $this->getOwnershipRules();
        if (empty($rules)) {
            $cache[$userId] = [];
            return [];
        }

        $userGroupIds = $this->getUserGroupTermIds($userId);
        $userRoles    = $this->getUserRoleNames($userId);

        $accessibleIds = [];
        foreach ($rules as $rule) {
            if (!$this->ruleMatchesUser($rule, $userGroupIds, $userRoles)) {
                continue;
            }

            $pageId = (int) ($rule['page_id'] ?? 0);
            if ($pageId === 0) {
                continue;
            }

            $accessibleIds[] = $pageId;
            if (!empty($rule['inherit'])) {
                $accessibleIds = array_merge($accessibleIds, $this->getDescendantIds($pageId));
            }
        }

        $cache[$userId] = array_values(array_unique($accessibleIds));
        return $cache[$userId];
    }

    /**
     * @param  array<string, mixed> $rule
     * @param  int[]                $userGroupIds
     * @param  string[]             $userRoles
     * @return bool
     */
    private function ruleMatchesUser(array $rule, array $userGroupIds, array $userRoles): bool
    {
        $groupId = (int) ($rule['user_group_id'] ?? 0);
        $role    = sanitize_key((string) ($rule['user_role'] ?? ''));

        if ($groupId === 0 && $role === '') {
            return false;
        }

        if ($groupId !== 0 && in_array($groupId, $userGroupIds, true)) {
            return true;
        }

        return $role !== '' && in_array($role, $userRoles, true);
    }

    /**
     * Return all page IDs that are covered by any ownership rule.
     *
     * Used to distinguish "owned by someone" from "not in any rule" so that
     * new pages and unassigned pages are not accidentally blocked.
     *
     * @return int[]
     */
    private function getAllOwnedPageIds(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $ids = [];
        foreach ($this->getOwnershipRules() as $rule) {
            $pageId = (int) ($rule['page_id'] ?? 0);
            if ($pageId === 0) {
                continue;
            }
            $ids[] = $pageId;
            if (!empty($rule['inherit'])) {
                $ids = array_merge($ids, $this->getDescendantIds($pageId));
            }
        }

        $cache = array_values(array_unique($ids));
        return $cache;
    }

    /**
     * Return the current user's role names.
     *
     * @return string[]
     */
    private function getUserRoleNames(int $userId): array
    {
        $user = get_userdata($userId);
        if (!$user) {
            return [];
        }

        return array_map('strval', (array) $user->roles);
    }

    /**
     * Return the user group term IDs (integers) for a given user.
     *
     * On multisite the user_group taxonomy is only registered on the main site,
     * so we must switch context before querying term relationships — this mirrors
     * how Municipio's own User::getUserGroup() retrieves the data.
     *
     * @return int[]
     */
    private function getUserGroupTermIds(int $userId): array
    {
        if (is_multisite()) {
            switch_to_blog(get_main_site_id());
            $terms = wp_get_object_terms($userId, 'user_group', ['fields' => 'ids']);
            restore_current_blog();
        } else {
            $terms = wp_get_object_terms($userId, 'user_group', ['fields' => 'ids']);
        }

        if (is_wp_error($terms)) {
            return [];
        }
        return array_map('intval', (array) $terms);
    }

    /**
     * Load and cache ownership rules from the option.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getOwnershipRules(): array
    {
        static $rules = null;
        if ($rules !== null) {
            return $rules;
        }

        $raw   = (string) get_option(PagePermissionsTab::OPTION_PAGE_TREE_OWNERSHIP, '[]');
        $rules = json_decode($raw, true);
        if (!is_array($rules)) {
            $rules = [];
        }

        return $rules;
    }

    /**
     * Recursively collect all descendant page IDs for a given parent.
     * Uses suppress_filters so private/hidden pages are included.
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
