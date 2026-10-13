<?php

declare(strict_types=1);

namespace PiteaCustomisation\Customisations;

use ScheduledPageReviews\Groups\Group;
use ScheduledPageReviews\Groups\GroupProvider;
use WP_Term;

/**
 * Recipient groups backed by the Municipio user_group taxonomy.
 *
 * Group IDs are term IDs. On a multisite network the taxonomy is stored on
 * the main site, so lookups switch there before reading terms.
 */
final class PageReviewsGroups implements GroupProvider
{
    private const TAXONOMY = 'user_group';

    public function key(): string
    {
        return self::TAXONOMY;
    }

    public function label(): string
    {
        return __('User groups', 'pitea-customisation');
    }

    /**
     * @return list<Group>
     */
    public function groups(): array
    {
        $groups = [];
        foreach ($this->terms() as $term) {
            $groups[] = new Group((string) $term->term_id, $term->name, (int) $term->count);
        }

        return $groups;
    }

    /**
     * @return list<int>
     */
    public function members(string $id): array
    {
        $termId = (int) $id;
        if ($termId <= 0 || !function_exists('get_objects_in_term')) {
            return [];
        }

        $objectIds = $this->onUserGroupBlog(function () use ($termId): array {
            if (!taxonomy_exists(self::TAXONOMY)) {
                return [];
            }
            
            $objects = get_objects_in_term($termId, self::TAXONOMY);
            
            return is_array($objects) ? $objects : [];
        });

        $userIds = [];
        foreach ($objectIds as $objectId) {
            $userId = (int) $objectId;
            if ($userId > 0) {
                $userIds[$userId] = $userId;
            }
        }

        return array_values($userIds);
    }

    /**
     * @return list<string>
     */
    public function groupsForUser(int $userId): array
    {
        if ($userId <= 0 || !function_exists('wp_get_object_terms')) {
            return [];
        }

        $termIds = $this->onUserGroupBlog(function () use ($userId): array {
            if (!taxonomy_exists(self::TAXONOMY)) {
                return [];
            }

            $terms = wp_get_object_terms($userId, self::TAXONOMY, ['fields' => 'ids']);

            return is_array($terms) ? $terms : [];
        });

        $ids = [];
        foreach ($termIds as $termId) {
            $id = (int) $termId;
            if ($id > 0) {
                $ids[] = (string) $id;
            }
        }

        return $ids;
    }

    /**
     * @return list<WP_Term>
     */
    private function terms(): array
    {
        if (!function_exists('get_terms')) {
            return [];
        }

        $terms = $this->onUserGroupBlog(function (): array {
            if (!taxonomy_exists(self::TAXONOMY)) {
                return [];
            }

            $found = get_terms([
                'taxonomy'   => self::TAXONOMY,
                'hide_empty' => false,
                'orderby'    => 'name',
                'order'      => 'ASC',
            ]);

            return is_array($found) ? $found : [];
        });

        $out = [];
        foreach ($terms as $term) {
            if ($term instanceof WP_Term && $term->term_id > 0) {
                $out[] = $term;
            }
        }

        return $out;
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function onUserGroupBlog(callable $callback): mixed
    {
        if (!function_exists('is_multisite') || !is_multisite()) {
            return $callback();
        }

        switch_to_blog(get_main_site_id());

        try {
            return $callback();
        } finally {
            restore_current_blog();
        }
    }
}
