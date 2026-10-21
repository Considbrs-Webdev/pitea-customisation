<?php

namespace PiteaCustomisation\Customisations;

/**
 * Purges Municipio's persistent navigation cache when content that feeds menus and breadcrumbs changes.
 *
 * @upstream-shim id=municipio-nav-cache-invalidation
 * @upstream-repo municipio (theme)
 * @upstream-broken Nav cache (group key `municipioNavMenu`, 15 min TTL) is only purged on updated_post_meta for its
 *                  own meta keys. Added/deleted meta and title, slug, parent, status or trash changes are not.
 * @upstream-fix-needed Purge the nav cache on post_updated, transition_post_status and added/deleted_post_meta.
 * @remove-when Municipio purges the nav cache on those events (verify on upgrade).
 * @verify-removal Rename a page, hide it in the menu for the first time, trash it; menu and breadcrumbs update at once.
 */
class NavMenuCachePurge
{
    private const CACHE_GROUP_KEY = 'municipioNavMenu';

    /**
     * Post fields that affect what menus and breadcrumbs render.
     */
    private const WATCHED_FIELDS = ['post_title', 'post_name', 'post_parent', 'post_status', 'menu_order'];

    private bool $purgePending = false;

    public function __construct()
    {
        add_action('post_updated', [$this, 'onPostUpdated'], 10, 3);
        add_action('transition_post_status', [$this, 'onStatusTransition'], 10, 3);
        add_action('deleted_post', [$this, 'schedulePurge']);
        add_action('added_post_meta', [$this, 'onMetaChanged'], 10, 4);
        add_action('deleted_post_meta', [$this, 'onMetaChanged'], 10, 4);
    }

    public function onPostUpdated(int $postId, \WP_Post $after, \WP_Post $before): void
    {
        if (wp_is_post_revision($postId)) {
            return;
        }

        foreach (self::WATCHED_FIELDS as $field) {
            if ($before->$field !== $after->$field) {
                $this->schedulePurge();
                return;
            }
        }
    }

    public function onStatusTransition(string $newStatus, string $oldStatus, \WP_Post $post): void
    {
        if ($newStatus !== $oldStatus && !wp_is_post_revision($post->ID)) {
            $this->schedulePurge();
        }
    }

    /**
     * Municipio already handles updated_post_meta; added and deleted meta slip through.
     *
     * @param array<int, string>|int $metaIds
     */
    public function onMetaChanged(array|int $metaIds, int $postId, string $metaKey): void
    {
        $cachedKeys = wp_cache_get(self::CACHE_GROUP_KEY);

        if (is_array($cachedKeys) && in_array($metaKey, $cachedKeys, true)) {
            $this->schedulePurge();
        }
    }

    /**
     * Purge on shutdown so later writes in the same request cannot be re-cached with stale data.
     */
    public function schedulePurge(): void
    {
        if ($this->purgePending) {
            return;
        }

        $this->purgePending = true;
        add_action('shutdown', [$this, 'purge']);
    }

    public function purge(): void
    {
        $cachedKeys = wp_cache_get(self::CACHE_GROUP_KEY);

        if (is_array($cachedKeys)) {
            foreach ($cachedKeys as $key) {
                wp_cache_delete($key);
            }
        }

        wp_cache_delete(self::CACHE_GROUP_KEY);
    }
}
