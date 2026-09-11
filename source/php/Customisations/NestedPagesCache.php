<?php

namespace PiteaCustomisation\Customisations;

/**
 * Flush the WordPress object cache after Nested Pages changes a page parent.
 *
 * Nested Pages writes menu_order and post_parent with raw SQL. That path
 * never fires save_post, so Redis-backed object cache keeps stale post and
 * navigation data. Breadcrumbs keep the old ancestor trail until the
 * object cache is flushed.
 *
 * Nested Pages posts the whole visible tree on every sort, so
 * nestedpages_post_order_updated runs for every row. Capture post_parent
 * before the SQL write and flush once, only when a parent actually changed.
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

    /**
     * Snapshot post_parent for every post in the sort payload.
     */
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

    /**
     * Flush the object cache when this post's parent changed.
     *
     * @param int|string $postId    The post Nested Pages just wrote.
     * @param int        $newParent The new post_parent value.
     */
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
