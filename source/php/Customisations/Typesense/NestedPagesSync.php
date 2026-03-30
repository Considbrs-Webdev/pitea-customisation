<?php

namespace PiteaCustomisation\Customisations\Typesense;

/**
 * Class NestedPagesSync
 *
 * Keeps the Typesense index in sync with changes made through the
 * Nested Pages plugin that bypass WordPress' standard post lifecycle hooks.
 *
 * --- Why this is needed ---
 * Nested Pages' sort operation (drag-and-drop reordering) writes
 * menu_order and post_parent directly via raw SQL, which means
 * wp_after_insert_post never fires. This matters for Typesense because
 * the indexed document can contain fields derived from the post hierarchy
 * (e.g. top_most_parent, path, url/permalink).
 *
 * Quick edit (npquickEdit) is already covered: it calls wp_update_post()
 * internally, which does fire wp_after_insert_post, so the existing
 * IndexingHooks handler picks it up automatically.
 *
 * --- How it works ---
 * Nested Pages sends the entire visible page tree on every sort, so
 * nestedpages_post_order_updated fires for every post in the tree — not
 * just the one that moved. To avoid re-indexing hundreds of unchanged
 * posts, we:
 *
 * 1. Hook into wp_ajax_npsort at priority 1 (before Nested Pages at 10)
 *    and snapshot each post's current post_parent.
 *
 * 2. In onPostOrderUpdated (fired per post, after the SQL write), compare
 *    the new parent against the snapshot. Only posts whose post_parent
 *    actually changed are re-indexed (order alone doesn't affect the index).
 *
 * 3. When a post moves to a new parent, cascade a re-index to all its
 *    published descendants — their post_parent doesn't change in the DB,
 *    but Typesense fields like `path` and `top_most_parent` are now stale.
 */
class NestedPagesSync
{
    /**
     * Pre-sort snapshot of post_parent for every post in the submitted
     * payload, captured before Nested Pages writes its SQL.
     *
     * @var array<int, int>
     */
    private array $preSort = [];

    public function __construct()
    {
        // Snapshot current state BEFORE Nested Pages overwrites it (priority 1 < 10).
        add_action('wp_ajax_npsort', [$this, 'capturePreSortState'], 1);

        // After each post's SQL update, check if the parent changed and
        // re-index only those posts. Accept 2 args: post_id, parent.
        add_action('nestedpages_post_order_updated', [$this, 'onPostOrderUpdated'], 10, 2);
    }

    /**
     * Snapshot post_parent for every post in the submitted sort payload
     * before Nested Pages writes its SQL updates.
     *
     * Runs on wp_ajax_npsort at priority 1 (unauthenticated requests cannot
     * reach wp_ajax_ hooks, so this is safe as a read-only operation).
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
     * Re-index a post only if its parent actually changed.
     *
     * Also cascades a re-index to all published descendants whose `path` /
     * `top_most_parent` fields are now stale (their own post_parent didn't
     * change, so they won't fire here on their own).
     *
     * @param int|string $postId    The post ID that was sorted.
     * @param int        $newParent The new post_parent value.
     */
    public function onPostOrderUpdated(int|string $postId, int $newParent): void
    {
        $id            = (int) $postId;
        $oldParent     = $this->preSort[$id] ?? null;
        $parentChanged = $oldParent === null || $oldParent !== $newParent;

        if (!$parentChanged) {
            return;
        }

        do_action('typesense_search/index_post', $id);

        // Children's hierarchy-derived fields (path, top_most_parent) are
        // now stale. Re-index all descendants.
        $this->reindexDescendants($id);
    }

    /**
     * Recursively re-index all published descendants of a post.
     *
     * @param int $postId The post whose descendants should be re-indexed.
     */
    private function reindexDescendants(int $postId): void
    {
        $children = get_posts([
            'post_parent'    => $postId,
            'post_type'      => 'any',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        foreach ($children as $childId) {
            do_action('typesense_search/index_post', (int) $childId);
            $this->reindexDescendants((int) $childId);
        }
    }

    /**
     * Recursively extract all post IDs from a Nested Pages list array.
     *
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

