<?php

namespace PiteaCustomisation\Customisations;

/**
 * Flushes object cache when Nested Pages reorders the tree via raw SQL.
 *
 * @upstream-shim id=nested-pages-cache-invalidation
 * @upstream-repo wp-nested-pages (third-party)
 * @upstream-broken Sort updates post_parent without save_post or clean_post_cache; nestedpages_post_order_updated fires per row.
 * @upstream-fix-needed Use WP APIs or fire cache invalidation after reorder.
 * @remove-when Nested Pages release invalidates caches on sort (verify on upgrade).
 * @verify-removal Reorder in Nested Pages; parent/permalink correct without this class.
 */
class NestedPagesCache
{
    /**
     * @var array<int, int>
     */
    private array $preSort = [];

    private bool $flushed = false;

    public function __construct()
    {
        add_action('wp_ajax_npsort', [$this, 'capturePreSortState'], 1);
        add_action('nestedpages_post_order_updated', [$this, 'onPostOrderUpdated'], 10, 2);
    }

    public function capturePreSortState(): void
    {
        $list = $_POST['list'] ?? null;
        if (!is_array($list)) {
            return;
        }

        foreach ($this->extractPostIds($list) as $id) {
            $post = get_post($id);
            if ($post) {
                $this->preSort[$id] = (int) $post->post_parent;
            }
        }
    }

    public function onPostOrderUpdated(int|string $postId, int $newParent): void
    {
        if ($this->flushed) {
            return;
        }

        $id            = (int) $postId;
        $oldParent     = $this->preSort[$id] ?? null;
        $parentChanged = $oldParent === null || $oldParent !== $newParent;

        if (!$parentChanged) {
            return;
        }

        wp_cache_flush();
        $this->flushed = true;
    }

    /**
     * @param  array<int, array{id?: string|int, children?: array}> $list
     * @return list<int>
     */
    private function extractPostIds(array $list): array
    {
        $ids = [];
        foreach ($list as $item) {
            if (!empty($item['id'])) {
                $ids[] = (int) $item['id'];
            }
            if (!empty($item['children']) && is_array($item['children'])) {
                $ids = array_merge($ids, $this->extractPostIds($item['children']));
            }
        }
        return $ids;
    }
}
