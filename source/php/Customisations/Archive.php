<?php

declare(strict_types=1);

namespace PiteaCustomisation\Customisations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Keeps empty mounted CPT archives from falling back to page queries.
 *
 * @upstream-shim id=municipio-empty-mounted-archive
 * @upstream-repo municipio (archive mount / query)
 * @upstream-broken Empty CPT archives rewrite to page queries; archive Modularity areas do not load.
 * @upstream-fix-needed Preserve mounted archive post type and seed context for module loading when post count is zero.
 * @remove-when Empty mounted CPT archive URLs render archive template and modules without these hooks.
 * @verify-removal Hit an empty mounted CPT archive; modules from mount page still render.
 */
class Archive
{
    /**
     * Register hooks.
     */
    public function __construct()
    {
        add_action('pre_get_posts', [$this, 'restoreEmptyMountedArchivePostType'], 31);
        add_action('pre_get_posts', [$this, 'resolveMountedArchiveChildPage'], 32);
        add_action('wp', [$this, 'ensureArchiveHasPostForModules'], 9);
        add_filter('Municipio/Helper/CurrentPostId', [$this, 'resolveArchivePageId'], 10, 1);
    }

    /**
     * Restore the original CPT post type after Municipio rewrites empty archives to pages.
     *
     * @param \WP_Query $query The main query.
     */
    public function restoreEmptyMountedArchivePostType(\WP_Query $query): void
    {
        if (is_admin() || !$query->is_main_query() || !$query->is_post_type_archive()) {
            return;
        }

        if ($query->get('post_type') !== 'page') {
            return;
        }

        $originalPostType = $this->getOriginalArchivePostType($query);
        if ($originalPostType === null) {
            return;
        }

        $query->set('post_type', $originalPostType);
        $query->set('child_of', null);
    }

    /**
     * Serve a child of the mount page when a CPT single under that path does not exist.
     *
     * Municipio rewrites the miss to post_type=page plus child_of set to the mount
     * page. WP_Query ignores child_of, so every page with that slug matches and the
     * newest one is shown.
     *
     * @upstream-shim id=municipio-mounted-archive-child-page
     * @upstream-repo municipio (library/Theme/Archive.php enablePageForPostTypeChildren)
     * @upstream-broken A missed {mounted_archive}/{slug} query becomes an unconstrained page slug lookup.
     * @upstream-fix-needed Apply child_of (descendants of page_for_{postType}) or leave the CPT query as a 404.
     * @remove-when enablePageForPostTypeChildren limits the page query to descendants of the mount page.
     * @verify-removal Pretty URL of a child under a mounted archive returns that child; a same-slug page elsewhere is not served. A real CPT single still resolves.
     *
     * @param \WP_Query $query The main query.
     */
    public function resolveMountedArchiveChildPage(\WP_Query $query): void
    {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        if ($query->get('post_type') !== 'page' || $query->is_post_type_archive()) {
            return;
        }

        $originalPostType = $this->getOriginalArchivePostType($query);
        if ($originalPostType === null) {
            return;
        }

        $slug = $query->get('name');
        if (!is_string($slug) || $slug === '') {
            return;
        }

        $mountPageId = (int) get_option('page_for_' . $originalPostType);
        if ($mountPageId <= 0) {
            return;
        }

        $pageId = $this->findDescendantPageId($mountPageId, $slug);
        if ($pageId === null) {
            $query->set('post_type', $originalPostType);
            $query->set('child_of', null);
            return;
        }

        $query->set('post_type', 'page');
        $query->set('page_id', $pageId);
        $query->set('p', $pageId);
        $query->set('name', '');
        $query->set('pagename', '');
        $query->set('child_of', null);
        $query->set($originalPostType, '');
        $query->is_single = false;
        $query->is_page = true;
        $query->is_singular = true;
        $query->is_attachment = false;
        $query->is_archive = false;
        $query->is_post_type_archive = false;
        $query->is_404 = false;
    }

    /**
     * Find the closest viewable descendant of a mount page with this slug.
     *
     * @param int    $mountPageId Page the post type archive is mounted on.
     * @param string $slug        Requested page slug.
     * @return int|null
     */
    private function findDescendantPageId(int $mountPageId, string $slug): ?int
    {
        $ids = get_posts([
            'name' => $slug,
            'post_type' => 'page',
            'post_status' => ['publish', 'private'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'suppress_filters' => true,
            'no_found_rows' => true,
            'orderby' => 'ID',
            'order' => 'ASC',
        ]);

        $bestId = null;
        $bestDepth = PHP_INT_MAX;

        foreach ($ids as $id) {
            $pageId = (int) $id;
            if (!$this->isViewablePage($pageId)) {
                continue;
            }

            $ancestors = array_map('intval', get_post_ancestors($pageId));
            $depth = array_search($mountPageId, $ancestors, true);
            if ($depth === false || $depth >= $bestDepth) {
                continue;
            }

            $bestDepth = $depth;
            $bestId = $pageId;
        }

        return $bestId;
    }

    /**
     * Whether the current visitor may see this page.
     *
     * @param int $pageId Page ID.
     * @return bool
     */
    private function isViewablePage(int $pageId): bool
    {
        $status = get_post_status($pageId);
        if ($status === 'publish') {
            return true;
        }

        return $status === 'private' && current_user_can('read_post', $pageId);
    }

    /**
     * Give Modularity a post object on empty archives so archive modules still initialize.
     *
     * Modularity Display::init() returns early when global $post is empty. An empty CPT
     * archive has no posts, so that guard skips modules stored as archive-{postType}.
     * The seeded object keeps the CPT as post_type so get_post_type() does not resolve to page.
     */
    public function ensureArchiveHasPostForModules(): void
    {
        if (is_admin() || !is_post_type_archive()) {
            return;
        }

        global $post;
        if ($post instanceof \WP_Post) {
            return;
        }

        $archivePage = $this->getMountedArchivePage();
        if (!$archivePage instanceof \WP_Post) {
            return;
        }

        $postType = $this->getArchivePostType();
        if ($postType === null) {
            return;
        }

        $seed = clone $archivePage;
        $seed->post_type = $postType;
        $post = $seed;
    }

    /**
     * Resolve the current page ID for mounted CPT archives without front-page fallback.
     *
     * @param int $pageId The page ID resolved by Municipio.
     * @return int
     */
    public function resolveArchivePageId(int $pageId): int
    {
        if (!is_post_type_archive()) {
            return $pageId;
        }

        $postType = $this->getArchivePostType();
        if ($postType === null) {
            return $pageId;
        }

        $archivePageId = (int) get_option('page_for_' . $postType);
        if ($archivePageId > 0 && get_post_status($archivePageId) !== false) {
            return $archivePageId;
        }

        $frontPageId = (int) get_option('page_on_front');
        if ($frontPageId > 0 && $pageId === $frontPageId) {
            return 0;
        }

        return $pageId;
    }

    /**
     * Get the original CPT slug from the request before Municipio rewrites the query.
     *
     * @param \WP_Query $query The main query.
     * @return string|null
     */
    private function getOriginalArchivePostType(\WP_Query $query): ?string
    {
        $postType = $query->query['post_type'] ?? null;

        if (is_array($postType)) {
            $postType = end($postType);
        }

        if (!is_string($postType) || $postType === '' || $postType === 'page') {
            return null;
        }

        return $postType;
    }

    /**
     * Get the page a CPT archive is mounted on, if any.
     *
     * @return \WP_Post|null
     */
    private function getMountedArchivePage(): ?\WP_Post
    {
        $postType = $this->getArchivePostType();
        if ($postType === null) {
            return null;
        }

        $archivePageId = (int) get_option('page_for_' . $postType);
        if ($archivePageId <= 0) {
            return null;
        }

        $archivePage = get_post($archivePageId);
        if (!$archivePage instanceof \WP_Post) {
            return null;
        }

        return $archivePage;
    }

    /**
     * Resolve the current archive post type slug.
     *
     * @return string|null
     */
    private function getArchivePostType(): ?string
    {
        $postType = get_query_var('post_type');

        if (is_array($postType)) {
            $postType = end($postType);
        }

        if (is_string($postType) && $postType !== '') {
            return $postType;
        }

        $queriedObject = get_queried_object();
        if ($queriedObject instanceof \WP_Post_Type && !empty($queriedObject->name)) {
            return $queriedObject->name;
        }

        return null;
    }
}
