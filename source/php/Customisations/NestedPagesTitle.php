<?php

namespace PiteaCustomisation\Customisations;

/**
 * Optional internal page label in Nested Pages list: Internal designation (public title).
 */
class NestedPagesTitle
{
    private const META_KEY = 'pitea_internal_page_designation';

    public function __construct()
    {
        new \PiteaCustomisation\AcfFields\PageInternalDesignationFields();

        add_filter('nestedpages_post_title', [$this, 'filterNestedPagesPostTitle'], 10, 2);
        add_filter('the_title', [$this, 'filterTheTitleOnNestedPagesScreen'], 10, 2);
    }

    /**
     * @param string       $title Already filtered title for the row.
     * @param object|mixed $post  Nested Pages post object (WP_Post plus id, post_type, type, etc.).
     */
    public function filterNestedPagesPostTitle(string $title, mixed $post): string
    {
        if (!is_object($post)) {
            return $title;
        }

        $postId = $this->resolvePageIdForNestedPost($post);
        if ($postId === null) {
            return $title;
        }

        $internal = $this->getInternalDesignation($postId);
        if ($internal === '') {
            return $title;
        }

        return $this->formatDesignationTitle($internal, $title);
    }

    /**
     * Link rows (np-redirect) only run core the_title — mirror designation when linking to a page.
     *
     * @param string $title
     */
    public function filterTheTitleOnNestedPagesScreen(string $title, int|string $postId = ''): string
    {
        if (!is_scalar($postId) || (string) $postId === '') {
            return $title;
        }

        $id = absint((string) $postId);
        if ($id < 1) {
            return $title;
        }

        if (!$this->isNestedPagesAdminScreen()) {
            return $title;
        }

        // Page rows also run nestedpages_post_title after the_title — only np-redirect link rows miss that filter.
        if (get_post_type($id) !== 'np-redirect') {
            return $title;
        }

        $metaPostId = $this->resolveMetaSourcePostIdFromAnyRow($id);
        if ($metaPostId === null) {
            return $title;
        }

        $internal = $this->getInternalDesignation($metaPostId);
        if ($internal === '') {
            return $title;
        }

        return $this->formatDesignationTitle($internal, $title);
    }

    private function isNestedPagesAdminScreen(): bool
    {
        if (!is_admin()) {
            return false;
        }

        if (!function_exists('get_current_screen')) {
            return false;
        }

        $screen = get_current_screen();
        if (!$screen || !isset($screen->id)) {
            return false;
        }

        return strpos((string) $screen->id, 'nestedpages') !== false;
    }

    /**
     * Page rows only.
     *
     * @param object $post
     */
    private function resolvePageIdForNestedPost(object $post): ?int
    {
        $type = isset($post->post_type)
            ? (string) $post->post_type
            : (isset($post->type) ? (string) $post->type : '');
        if ($type !== 'page') {
            return null;
        }

        $id = isset($post->ID) ? absint((string) $post->ID)
            : (isset($post->id) ? absint((string) $post->id) : 0);
        if ($id < 1) {
            return null;
        }

        return $id;
    }

    /**
     * For np-redirect rows, internal label lives on the linked page.
     */
    private function resolveMetaSourcePostIdFromAnyRow(int $rowPostId): ?int
    {
        $target = get_post_meta($rowPostId, '_np_nav_menu_item_object_id', true);
        $targetId = absint((string) $target);
        if ($targetId > 0 && get_post_type($targetId) === 'page') {
            return $targetId;
        }

        return null;
    }

    private function getInternalDesignation(int $pageId): string
    {
        if (function_exists('get_field')) {
            $value = get_field(self::META_KEY, $pageId);
            if (is_string($value) && $value !== '') {
                return sanitize_text_field($value);
            }
        }

        $raw = get_post_meta($pageId, self::META_KEY, true);
        if (!is_string($raw) || $raw === '') {
            return '';
        }

        return sanitize_text_field($raw);
    }

    private function formatDesignationTitle(string $internal, string $publicTitle): string
    {
        return sprintf(
            '%s (%s)',
            esc_html($internal),
            esc_html($publicTitle)
        );
    }
}
