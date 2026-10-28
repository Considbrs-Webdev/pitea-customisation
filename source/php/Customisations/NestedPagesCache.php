<?php

namespace PiteaCustomisation\Customisations;

/**
 * Keeps public URLs in sync when Nested Pages reorders the tree via raw SQL.
 *
 * Nested Pages updates post_parent without save_post, so WordPress never bumps the
 * posts cache salt and Yoast never rewrites its permalink index or redirects.
 * A later request can 301 the new URL back to the old path, which 404s, and
 * LiteSpeed then caches that 404.
 *
 * @upstream-shim id=nested-pages-cache-invalidation
 * @upstream-repo wp-nested-pages (third-party)
 * @upstream-broken Sort updates post_parent without save_post or clean_post_cache; nestedpages_post_order_updated fires per row. Yoast indexables and premium redirects stay on the previous permalink.
 * @upstream-fix-needed Use WP APIs or fire cache, indexable, and redirect updates after reorder.
 * @remove-when Nested Pages release invalidates caches on sort and Yoast sees the permalink change (verify on upgrade).
 * @verify-removal Reorder in Nested Pages; the new URL returns 200 and the previous URL redirects there, without this class.
 */
class NestedPagesCache
{
    private const REDIRECT_OPTION = 'wpseo-premium-redirects-export-plain';

    /**
     * @var array<int, int>
     */
    private array $preSort = [];

    /**
     * Permalinks read before the object cache is cleared, keyed by post ID.
     * Includes the moved page and its descendants.
     *
     * @var array<int, string>
     */
    private array $oldPermalinks = [];

    private bool $flushPending = false;

    private bool $repairPending = false;

    public function __construct()
    {
        add_action('wp_ajax_npsort', [$this, 'capturePreSortState'], 1);
        add_action('nestedpages_post_order_updated', [$this, 'rememberOldPermalink'], 1, 4);
        add_action('nestedpages_post_order_updated', [$this, 'onPostOrderUpdated'], 10, 2);
    }

    /**
     * Flush once the request is done. Flushing inside the per-row hook empties the cache while
     * later rows are still unwritten, so anything read in between (Typesense sync, ancestors,
     * nav menus) re-caches the old tree and keeps it until the TTL expires.
     */
    public function flushAfterSort(): void
    {
        wp_cache_flush();
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

    /**
     * Store the current permalink while the object cache still has the old parent.
     *
     * Nested Pages deletes the post cache after this action, and Typesense calls
     * clean_post_cache from the same action at priority 10.
     *
     * @param int|string $menuOrder Unused. Present so the filtered flag arrives in the fourth argument.
     */
    public function rememberOldPermalink(int|string $postId, int $newParent, int|string $menuOrder = 0, bool $filtered = false): void
    {
        if ($filtered) {
            return;
        }

        $id   = (int) $postId;
        $post = get_post($id);
        if (!$post || (int) $post->post_parent === $newParent) {
            return;
        }

        $this->rememberPermalink($id);
        foreach ($this->descendantIds($id) as $descendantId) {
            $this->rememberPermalink($descendantId);
        }

        if ($this->repairPending) {
            return;
        }

        $this->repairPending = true;
        add_action('shutdown', [$this, 'repairPermalinks'], 20);
    }

    public function onPostOrderUpdated(int|string $postId, int $newParent): void
    {
        if ($this->flushPending) {
            return;
        }

        $id            = (int) $postId;
        $oldParent     = $this->preSort[$id] ?? null;
        $parentChanged = $oldParent === null || $oldParent !== $newParent;

        if (!$parentChanged) {
            return;
        }

        $this->flushPending = true;
        add_action('shutdown', [$this, 'flushAfterSort']);
    }

    /**
     * Point Yoast at the permalink WordPress now serves, and drop the redirect that sends that URL back to the old path.
     */
    public function repairPermalinks(): void
    {
        if (!defined('LITESPEED_PURGE_SILENT')) {
            define('LITESPEED_PURGE_SILENT', true);
        }

        $redirects = get_option(self::REDIRECT_OPTION, []);
        $redirects = is_array($redirects) ? $redirects : [];
        $changed   = false;

        foreach ($this->oldPermalinks as $id => $oldUrl) {
            clean_post_cache($id);
            $newUrl = get_permalink($id);
            if (!is_string($oldUrl) || $oldUrl === '' || !is_string($newUrl) || $newUrl === '' || $oldUrl === $newUrl) {
                continue;
            }

            $changed = $this->retargetRedirect($redirects, $oldUrl, $newUrl) || $changed;
            $this->updateIndexable($id, $newUrl);

            do_action('litespeed_purge_url', $oldUrl);
            do_action('litespeed_purge_url', $newUrl);
            do_action('litespeed_purge_post', $id);
        }

        if ($changed) {
            update_option(self::REDIRECT_OPTION, $redirects);
        }
    }

    /**
     * @param array<string, array{url?: string, type?: int}|string> $redirects
     */
    private function retargetRedirect(array &$redirects, string $oldUrl, string $newUrl): bool
    {
        $oldPath = $this->pathFromUrl($oldUrl);
        $newPath = $this->pathFromUrl($newUrl);
        if ($oldPath === '' || $newPath === '' || $oldPath === $newPath) {
            return false;
        }

        unset($redirects[$newPath]);
        $redirects[$oldPath] = [
            'url'  => $newPath,
            'type' => 301,
        ];

        return true;
    }

    private function updateIndexable(int $postId, string $newUrl): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'yoast_indexable';
        if (!$this->indexableTableExists($table)) {
            return;
        }

        $post = get_post($postId);
        $wpdb->update(
            $table,
            [
                'permalink'      => $newUrl,
                'permalink_hash' => strlen($newUrl) . ':' . md5($newUrl),
                'post_parent'    => $post ? (int) $post->post_parent : 0,
            ],
            [
                'object_id'   => $postId,
                'object_type' => 'post',
            ]
        );
    }

    private function indexableTableExists(string $table): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }

        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;

        return $exists;
    }

    private function rememberPermalink(int $postId): void
    {
        if (isset($this->oldPermalinks[$postId])) {
            return;
        }

        $post = get_post($postId);
        if (!$post) {
            return;
        }

        $permalink = get_permalink($post);
        if (is_string($permalink) && $permalink !== '') {
            $this->oldPermalinks[$postId] = $permalink;
        }
    }

    /**
     * @return list<int>
     */
    private function descendantIds(int $postId): array
    {
        global $wpdb;

        $ids  = [];
        $seen = [$postId => true];
        $queue = [$postId];

        while ($queue) {
            $current = array_shift($queue);
            $children = $wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_status = 'publish'",
                $current
            ));
            foreach ($children as $child) {
                $childId = (int) $child;
                if (isset($seen[$childId])) {
                    continue;
                }
                $seen[$childId] = true;
                $ids[] = $childId;
                $queue[] = $childId;
            }
        }

        return $ids;
    }

    private function pathFromUrl(string $url): string
    {
        $path = wp_parse_url($url, PHP_URL_PATH);

        return trim(is_string($path) ? $path : '', '/');
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
