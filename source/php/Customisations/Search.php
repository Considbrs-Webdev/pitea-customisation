<?php

namespace PiteaCustomisation\Customisations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Search
 *
 * Handles customizing the search form action, adding a rewrite rule for
 * a localized search path, and redirecting the default search query
 * URLs to the friendly `/sok/` path.
 */
class Search
{
    /**
     * Register hooks.
     */
    public function __construct()
    {
        add_filter('get_search_form', [$this, 'filter_search_form'], 10, 1);
        add_action('init', [$this, 'add_rewrite_rules']);
        add_action('template_redirect', [$this, 'maybe_redirect_search_url']);
    }

    /**
     * Replace the search form `action` attribute with the localized path.
     *
     * @param string $form The HTML of the search form.
     * @return string Filtered HTML search form.
     */
    public function filter_search_form($form)
    {
        $action = esc_url(home_url('/sok/'));

        // Replace the first occurrence of action="..." or action='...'.
        $form = preg_replace(
            '/action=("|\').*?\1/i',
            'action="' . $action . '"',
            $form,
            1
        );

        return $form;
    }

    /**
     * Add rewrite rules so that `/sok/` maps to the main search query.
     *
     * Remember to flush rewrite rules when deploying if needed.
     */
    public function add_rewrite_rules()
    {
        add_rewrite_rule('^sok/?$', 'index.php?s=', 'top');
    }

    /**
     * Redirect default `/?s=term` search URLs to `/sok/?s=term`.
     *
     * This keeps the search URL consistent and avoids duplicate content.
     */
    public function maybe_redirect_search_url()
    {
        // Only act on front-end search requests.
        if (!is_search() || is_admin() || (function_exists('wp_doing_ajax') && wp_doing_ajax())) {
            return;
        }

        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $request_path = trim(parse_url($request_uri, PHP_URL_PATH) ?? '', '/');

        // If already on the friendly path, do nothing to avoid loops.
        if ($request_path === 'sok') {
            return;
        }

        $term = (string) get_query_var('s');
        $target = home_url('/sok/') . '?s=' . rawurlencode($term);

        // Preserve a small set of useful query vars.
        $keep = ['post_type', 'paged'];
        foreach ($keep as $k) {
            if (isset($_GET[$k])) {
                $target .= '&' . rawurlencode($k) . '=' . rawurlencode((string) $_GET[$k]);
            }
        }

        wp_redirect($target, 301);
        exit;
    }
}
