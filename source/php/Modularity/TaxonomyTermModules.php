<?php

namespace PiteaCustomisation\Modularity;

use WP_Term;

/**
 * Class TaxonomyTermModules
 * 
 * Enables Modularity modules on taxonomy term archive pages.
 * This allows editors to configure different modules for each top-level term
 * (e.g., different calendars can have different modules).
 * 
 * NOTE: This is a TEMPORARY WORKAROUND until Modularity adds native taxonomy support.
 * See PR_PROPOSAL_TAXONOMY_SUPPORT.md for the proposed upstream fix.
 * 
 * This implementation uses direct property manipulation and manual hook registration
 * because Modularity's Display::init() explicitly skips taxonomy pages (is_tax() check).
 * Once Modularity adds native support, this code can be significantly simplified.
 * 
 * @package PiteaCustomisation\Modularity
 */
class TaxonomyTermModules
{
    /**
     * Taxonomies that support term modules
     * 
     * @var array
     */
    private array $enabledTaxonomies = [
        'sv_event_category',
        // Add more taxonomies here as needed
    ];

    /**
     * Cached modules for the current request
     * 
     * @var array|null
     */
    private ?array $currentTermModules = null;

    public function __construct()
    {
        // Admin: Add link to term edit pages
        add_action('admin_init', [$this, 'addTermEditHooks']);

        // Admin: Register the editor page for terms
        add_action('admin_menu', [$this, 'addTermModulesSubmenu'], 20);

        // Frontend: Load term modules on taxonomy archives - VERY EARLY to beat Modularity's Display
        add_action('wp', [$this, 'initTaxonomyTermModules'], 1);

        // Frontend: Inject modules into Modularity's Display instance
        // This is the KEY - we need to populate Modularity's modules array, not work around it
        // Modularity's Display will then handle all the output via its own hooks
        add_action('wp', [$this, 'injectModulesIntoModularityDisplay'], 2);

        // Frontend: Make sidebars with term modules appear active so they render
        // Use priority 5 to run BEFORE Modularity's Display filter (priority 10)
        add_filter('is_active_sidebar', [$this, 'makeSidebarActiveIfHasTermModules'], 5, 2);

        // Filter to allow extending enabled taxonomies
        $this->enabledTaxonomies = apply_filters(
            'PiteaCustomisation/TaxonomyTermModules/EnabledTaxonomies',
            $this->enabledTaxonomies
        );
    }

    /**
     * Add hooks for term edit pages
     * 
     * @return void
     */
    public function addTermEditHooks(): void
    {
        foreach ($this->enabledTaxonomies as $taxonomy) {
            // Add modules link on term edit form
            add_action("{$taxonomy}_edit_form", [$this, 'renderModulesLink'], 100, 2);
        }
    }

    /**
     * Add submenu items for term modules under each taxonomy
     * 
     * @return void
     */
    public function addTermModulesSubmenu(): void
    {
        foreach ($this->enabledTaxonomies as $taxonomy) {
            $taxonomyObject = get_taxonomy($taxonomy);

            if (!$taxonomyObject) {
                continue;
            }

            // Get the parent menu slug for this taxonomy
            $parentSlug = $this->getTaxonomyParentMenu($taxonomy);

            if (!$parentSlug) {
                continue;
            }

            // Get top-level terms for this taxonomy
            $topLevelTerms = get_terms([
                'taxonomy' => $taxonomy,
                'parent' => 0,
                'hide_empty' => false,
            ]);

            if (is_wp_error($topLevelTerms) || empty($topLevelTerms)) {
                continue;
            }

            // Add a submenu for each top-level term
            foreach ($topLevelTerms as $term) {
                $editorLink = "options.php?page=modularity-editor&id=taxonomy-term-{$term->term_id}";

                add_submenu_page(
                    $parentSlug,
                    sprintf(__('Modules: %s', 'pitea-customisation'), $term->name),
                    sprintf(__('Modules: %s', 'pitea-customisation'), $term->name),
                    'edit_posts',
                    $editorLink
                );
            }
        }
    }

    /**
     * Get the parent menu slug for a taxonomy
     * 
     * @param string $taxonomy
     * @return string|null
     */
    private function getTaxonomyParentMenu(string $taxonomy): ?string
    {
        $taxonomyObject = get_taxonomy($taxonomy);

        if (!$taxonomyObject) {
            return null;
        }

        // Get the post types this taxonomy is registered to
        $postTypes = $taxonomyObject->object_type;

        if (empty($postTypes)) {
            return null;
        }

        $postType = $postTypes[0];

        // Return the edit.php URL for the post type
        if ($postType === 'post') {
            return 'edit.php';
        }

        return "edit.php?post_type={$postType}";
    }

    /**
     * Render a link to the modules editor on term edit pages
     * 
     * @param WP_Term $term
     * @param string $taxonomy
     * @return void
     */
    public function renderModulesLink(WP_Term $term, string $taxonomy): void
    {
        // Only show for top-level terms
        if ($term->parent !== 0) {
            $parentTerm = $this->getTopLevelTerm($term);
            if ($parentTerm) {
                $editUrl = admin_url("options.php?page=modularity-editor&id=taxonomy-term-{$parentTerm->term_id}");
                echo '<div class="form-field term-modules-wrap" style="margin-top: 20px; padding: 15px; background: #f0f0f1; border-left: 4px solid #2271b1;">';
                echo '<h3 style="margin-top: 0;">' . __('Modularity Modules', 'pitea-customisation') . '</h3>';
                echo '<p>' . sprintf(
                    __('This term inherits modules from its parent: <strong>%s</strong>', 'pitea-customisation'),
                    esc_html($parentTerm->name)
                ) . '</p>';
                echo '<a href="' . esc_url($editUrl) . '" class="button button-secondary">';
                echo __('Edit Parent Modules', 'pitea-customisation');
                echo '</a>';
                echo '</div>';
                return;
            }
        }

        $editUrl = admin_url("options.php?page=modularity-editor&id=taxonomy-term-{$term->term_id}");

        echo '<div class="form-field term-modules-wrap" style="margin-top: 20px; padding: 15px; background: #f0f0f1; border-left: 4px solid #2271b1;">';
        echo '<h3 style="margin-top: 0;">' . __('Modularity Modules', 'pitea-customisation') . '</h3>';
        echo '<p>' . __('Configure modules that will be displayed on this term\'s archive page.', 'pitea-customisation') . '</p>';
        echo '<a href="' . esc_url($editUrl) . '" class="button button-primary">';
        echo __('Edit Modules', 'pitea-customisation');
        echo '</a>';
        echo '</div>';
    }

    /**
     * Inject modules into Modularity's Display instance
     * 
     * This is the KEY to making modules work on taxonomy archives.
     * Modularity's Display::init() explicitly skips taxonomy pages (is_tax() check),
     * so we need to manually populate its modules array and register its hooks.
     * 
     * STABILITY NOTE: This directly manipulates Modularity's Display instance properties.
     * If Modularity changes its internal structure (e.g., makes properties private or
     * changes data format), this will break. Monitor Modularity updates carefully.
     * 
     * @return void
     */
    public function injectModulesIntoModularityDisplay(): void
    {
        // Only proceed if we're on a taxonomy archive with modules
        if (empty($this->currentTermModules) || !is_tax($this->enabledTaxonomies)) {
            return;
        }

        // Check if Modularity's Display instance exists
        if (!class_exists('\Modularity\App') || !isset(\Modularity\App::$display)) {
            return;
        }

        $display = \Modularity\App::$display;

        // Verify Display instance has the expected structure (defensive check)
        if (!is_object($display) || !property_exists($display, 'modules')) {
            return;
        }

        // Inject our term modules into Modularity's Display instance
        // This makes Modularity think these modules were loaded normally
        $display->modules = $this->currentTermModules;

        // Get and set sidebar options for this term
        if (property_exists($display, 'options')) {
            $display->options = $this->getTermSidebarOptions();
        }

        // CRITICAL: Register Modularity's output hooks since its init() was skipped for taxonomy pages
        // Check if hooks are already registered to avoid duplicates
        // STABILITY NOTE: If Modularity changes hook names or method names, this will break
        if (
            method_exists($display, 'outputBefore') &&
            method_exists($display, 'outputAfter') &&
            method_exists($display, 'hideWidgets') &&
            !has_action('dynamic_sidebar_before', [$display, 'outputBefore'])
        ) {
            add_action('dynamic_sidebar_before', [$display, 'outputBefore']);
            add_action('dynamic_sidebar_after', [$display, 'outputAfter']);
            add_filter('sidebars_widgets', [$display, 'hideWidgets']);
        }
    }

    /**
     * Initialize term modules on taxonomy archive pages
     * 
     * @return void
     */
    public function initTaxonomyTermModules(): void
    {
        global $wp_query;
        $queriedObject = get_queried_object();

        // Check both is_tax() and $wp_query->is_tax for better detection
        $isTaxArchive = is_tax($this->enabledTaxonomies) || (isset($wp_query) && $wp_query->is_tax && $queriedObject instanceof WP_Term && in_array($queriedObject->taxonomy, $this->enabledTaxonomies));

        if (!$isTaxArchive) {
            return;
        }

        $term = get_queried_object();

        if (!$term instanceof WP_Term) {
            return;
        }

        // Get the top-level term (for hierarchical taxonomies)
        $effectiveTerm = $this->getTopLevelTerm($term);

        if (!$effectiveTerm) {
            $effectiveTerm = $term;
        }

        // Load modules for this term
        $this->currentTermModules = self::getTermModules($effectiveTerm->term_id, $effectiveTerm->taxonomy);
    }

    /**
     * Get the top-level parent term
     * 
     * @param WP_Term $term
     * @return WP_Term|null
     */
    private function getTopLevelTerm(WP_Term $term): ?WP_Term
    {
        if ($term->parent === 0) {
            return $term;
        }

        $ancestors = get_ancestors($term->term_id, $term->taxonomy, 'taxonomy');

        if (empty($ancestors)) {
            return $term;
        }

        // The last ancestor is the top-level term
        $topLevelTermId = end($ancestors);
        $topLevelTerm = get_term($topLevelTermId, $term->taxonomy);

        return ($topLevelTerm instanceof WP_Term) ? $topLevelTerm : null;
    }

    /**
     * Get modules for a specific term
     * 
     * @param int $termId
     * @param string $taxonomy
     * @return array
     */
    public static function getTermModules(int $termId, string $taxonomy): array
    {
        $optionKey = "modularity_taxonomy-term-{$termId}_modules";
        $moduleSidebars = get_option($optionKey, []);

        if (empty($moduleSidebars)) {
            return [];
        }

        // Get enabled modules from Modularity
        $enabled = [];
        if (class_exists('\Modularity\ModuleManager')) {
            $enabled = \Modularity\ModuleManager::$enabled ?? [];
        }

        if (empty($enabled)) {
            return [];
        }

        // Collect all module IDs
        $moduleIds = [];
        foreach ($moduleSidebars as $sidebar) {
            foreach ($sidebar as $module) {
                if (isset($module['postid'])) {
                    $moduleIds[] = $module['postid'];
                }
            }
        }

        if (empty($moduleIds)) {
            return [];
        }

        // Get allowed post statuses
        $postStatuses = ['publish'];
        if (is_user_logged_in()) {
            $postStatuses[] = 'private';
        }

        // Fetch module posts
        $modulesPosts = get_posts([
            'posts_per_page' => count($moduleIds),
            'post_type' => $enabled,
            'include' => $moduleIds,
            'post_status' => $postStatuses,
        ]);

        // Index modules by ID
        $modules = [];
        foreach ($modulesPosts as $module) {
            $modules[$module->ID] = $module;
        }

        // Build the return structure
        $retModules = [];
        foreach ($moduleSidebars as $sidebarId => $sidebar) {
            $retModules[$sidebarId] = [
                'modules' => [],
            ];

            foreach ($sidebar as $moduleData) {
                if (!isset($moduleData['postid']) || !isset($modules[$moduleData['postid']])) {
                    continue;
                }

                $moduleId = $moduleData['postid'];
                $module = clone $modules[$moduleId];

                // Add module metadata
                $module->hidden = isset($moduleData['hidden']) && $moduleData['hidden'] === true;
                $module->columnWidth = $moduleData['columnWidth'] ?? '';

                // Get module post type name
                if (class_exists('\Modularity\ModuleManager') && isset(\Modularity\ModuleManager::$available[$module->post_type])) {
                    $module->post_type_name = \Modularity\ModuleManager::$available[$module->post_type]['labels']['name'];
                }

                $module->meta = get_post_custom($module->ID);

                $retModules[$sidebarId]['modules'][] = $module;
            }
        }

        return $retModules;
    }

    /**
     * Get sidebar options for the current term
     * 
     * @return array
     */
    private function getTermSidebarOptions(): array
    {
        $term = get_queried_object();

        if (!$term instanceof WP_Term) {
            return [];
        }

        $effectiveTerm = $this->getTopLevelTerm($term);

        if (!$effectiveTerm) {
            $effectiveTerm = $term;
        }

        $optionKey = "modularity_taxonomy-term-{$effectiveTerm->term_id}_sidebar-options";

        return get_option($optionKey, []);
    }

    /**
     * Check if a taxonomy supports term modules
     * 
     * @param string $taxonomy
     * @return bool
     */
    public function taxonomySupportsTermModules(string $taxonomy): bool
    {
        return in_array($taxonomy, $this->enabledTaxonomies, true);
    }

    /**
     * Make sidebars with term modules appear active so they render
     * 
     * This filter runs at priority 5, BEFORE Modularity's Display::isActiveSidebar (priority 10)
     * to ensure we can mark sidebars as active before Modularity caches the result.
     * 
     * Note: Modules should already be loaded via initTaxonomyTermModules() at wp hook priority 1.
     * This filter runs later, so modules should already be available.
     * 
     * @param bool $isActive
     * @param string $sidebarId
     * @return bool
     */
    public function makeSidebarActiveIfHasTermModules(bool $isActive, string $sidebarId): bool
    {
        // Only on taxonomy archive pages
        if (!is_tax($this->enabledTaxonomies)) {
            return $isActive;
        }

        // Modules should already be loaded via initTaxonomyTermModules() at wp hook priority 1
        // If they're not loaded here, something went wrong - return original state
        if (empty($this->currentTermModules)) {
            return $isActive;
        }

        // Check if this sidebar has modules (exact match first, then prefix match)
        if (isset($this->currentTermModules[$sidebarId])) {
            return true;
        }

        // Check prefix match (e.g., "content-area" matches "content-area-bottom")
        foreach (array_keys($this->currentTermModules) as $savedSidebarId) {
            if (strpos($sidebarId, $savedSidebarId) === 0 || strpos($savedSidebarId, $sidebarId) === 0) {
                return true;
            }
        }

        return $isActive;
    }

    /**
     * Get enabled taxonomies
     * 
     * @return array
     */
    public function getEnabledTaxonomies(): array
    {
        return $this->enabledTaxonomies;
    }
}
