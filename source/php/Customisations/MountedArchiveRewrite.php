<?php

declare(strict_types=1);

namespace PiteaCustomisation\Customisations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Removes core's original archive rules after a post type is mounted on a page.
 *
 * @upstream-shim id=wp-page-for-posttype-orphaned-archive-rules
 * @upstream-repo helsingborg-stad/wp-page-for-posttype (source/php/Rewrite.php updateRewrite / Rewrite/RewriteBuilder.php)
 * @upstream-broken Mounting a post type on a page adds archive rules for the page path but leaves core's original `{rewrite.slug}/?$`, `/page/N` and `/feed` archive rules in place, so the old slug (e.g. /kaleido) keeps serving the archive and blocks pages and easy-quicklinks snabblänkar.
 * @upstream-fix-needed Remove the original archive rules from $wp_rewrite->extra_rules_top when page_for_<postType> is set.
 * @remove-when wp-page-for-posttype removes the original archive rules itself.
 * @verify-removal `wp rewrite list --match=/kaleido/` shows no sv_kaleido archive rule for `kaleido/?$` without this class.
 */
class MountedArchiveRewrite
{
    /**
     * Register hooks.
     */
    public function __construct()
    {
        add_action('registered_post_type', [$this, 'removeOrphanedArchiveRules'], 12, 2);
    }

    /**
     * Remove original archive, pagination and feed rules for a mounted post type.
     *
     * Rules under the mounted path are kept, including when that path starts
     * with the original slug.
     *
     * @param string        $postType Post type name.
     * @param \WP_Post_Type $args     Post type object after the mount plugin mutates it.
     */
    public function removeOrphanedArchiveRules(string $postType, \WP_Post_Type $args): void
    {
        if (!class_exists(\WpPageForPostType\Settings::class)) {
            return;
        }

        if (!isset(\WpPageForPostType\Settings::$originalSlugs[$postType])) {
            return;
        }

        $pageId = get_option('page_for_' . $postType);
        if (!is_numeric($pageId)) {
            return;
        }

        $status = get_post_status((int) $pageId);
        if (!in_array($status, ['publish', 'private'], true)) {
            return;
        }

        $original = trim((string) \WpPageForPostType\Settings::$originalSlugs[$postType], '/');
        $mounted = is_string($args->has_archive) ? trim($args->has_archive, '/') : '';
        if ($mounted === '' || $original === '' || $original === $mounted) {
            return;
        }

        global $wp_rewrite;

        if (!$wp_rewrite instanceof \WP_Rewrite || !is_array($wp_rewrite->extra_rules_top)) {
            return;
        }

        if (!empty($args->rewrite['with_front'])) {
            $prefix = substr((string) $wp_rewrite->front, 1);
        } else {
            $prefix = (string) $wp_rewrite->root;
        }

        $needle = $prefix . $original . '/';
        $mountedNeedle = $prefix . $mounted . '/';
        $queryPrefix = 'index.php?post_type=' . $postType;

        foreach ($wp_rewrite->extra_rules_top as $rule => $query) {
            if (!is_string($rule) || !is_string($query)) {
                continue;
            }

            if (str_starts_with($rule, $mountedNeedle)) {
                continue;
            }

            if (str_starts_with($rule, $needle) && str_starts_with($query, $queryPrefix)) {
                unset($wp_rewrite->extra_rules_top[$rule]);
            }
        }
    }
}
