<?php

declare(strict_types=1);

namespace PiteaCustomisation\Customisations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Keeps empty mounted CPT archives from falling back to page queries.
 */
class Archive
{
    /**
     * Register hooks.
     */
    public function __construct()
    {
        add_action('pre_get_posts', [$this, 'restoreEmptyMountedArchivePostType'], 31);
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
