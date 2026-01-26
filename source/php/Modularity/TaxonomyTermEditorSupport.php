<?php

namespace PiteaCustomisation\Modularity;

/**
 * Class TaxonomyTermEditorSupport
 * 
 * Extends Modularity's Editor to support taxonomy term IDs.
 * This hooks into the existing Modularity save mechanism to handle
 * IDs in the format "taxonomy-term-{term_id}".
 * 
 * NOTE: This is a TEMPORARY WORKAROUND until Modularity adds native taxonomy support.
 * See PR_PROPOSAL_TAXONOMY_SUPPORT.md for the proposed upstream fix.
 * 
 * @package PiteaCustomisation\Modularity
 */
class TaxonomyTermEditorSupport
{
    /**
     * Store the original term ID for reference
     * 
     * @var int|null
     */
    private static ?int $originalTermId = null;

    /**
     * Flag to prevent double execution of save handler
     * 
     * @var bool
     */
    private static bool $saveHandled = false;

    public function __construct()
    {
        // Filter modularity-options to add taxonomy term templates to enabled areas
        add_filter('option_modularity-options', [$this, 'filterModularityOptions']);

        // Hook into admin_init to catch form POST saves (Modularity uses form POST, not AJAX)
        add_action('admin_init', [$this, 'checkForFormSave'], 1);

        // Hook into the admin bar to set up the editing context for taxonomy terms
        add_action('admin_init', [$this, 'setupTaxonomyTermEditing'], 5);

        // Hook into admin_head to set JavaScript variable AFTER Modularity does
        add_action('admin_head', [$this, 'setModularityPostId'], 15);

        // Transform taxonomy term ID so Modularity recognizes it as an archive
        add_action('admin_init', [$this, 'prepareTaxonomyTermForModularity'], 1);

        // Hook into the editor title display
        add_action('Modularity/options_page_title_suffix', [$this, 'modifyEditorTitle'], 5);

        // Filter the is_editing data
        add_filter('Modularity/is_editing', [$this, 'filterIsEditing']);

        // Inject JavaScript to ensure ID is included in AJAX requests (defensive - currently unused)
        add_action('admin_footer', [$this, 'injectAjaxIdFix']);
    }

    /**
     * Filter modularity-options to add taxonomy term templates to enabled areas
     * 
     * @param mixed $options
     * @return mixed
     */
    public function filterModularityOptions($options): mixed
    {
        $termId = self::isEditingTaxonomyTerm();

        if ($termId === false) {
            return $options;
        }

        if (!is_array($options) || !isset($options['enabled-areas'])) {
            return $options;
        }

        $term = get_term($termId);

        if (!$term || is_wp_error($term)) {
            return $options;
        }

        // Get the post type this taxonomy belongs to
        $taxonomyObject = get_taxonomy($term->taxonomy);
        $postType = null;

        if ($taxonomyObject && !empty($taxonomyObject->object_type)) {
            $postType = $taxonomyObject->object_type[0];
        }

        // Try to find sidebars in this order:
        // 1. archive-{posttype} (specific post type archive)
        // 2. archive (generic archive template)
        // 3. page (fallback to page template)
        // 4. First available template with sidebars
        $archiveSidebars = [];

        $templatesToTry = [];
        if ($postType) {
            $templatesToTry[] = 'archive-' . $postType;
        }
        $templatesToTry[] = 'archive';
        $templatesToTry[] = 'page';

        foreach ($templatesToTry as $template) {
            if (!empty($options['enabled-areas'][$template])) {
                $archiveSidebars = $options['enabled-areas'][$template];
                break;
            }
        }

        // If still no sidebars found, use the first template that has sidebars
        if (empty($archiveSidebars)) {
            foreach ($options['enabled-areas'] as $template => $sidebars) {
                if (!empty($sidebars)) {
                    $archiveSidebars = $sidebars;
                    break;
                }
            }
        }

        // Add the taxonomy term template with the found sidebars
        $termTemplate = "taxonomy-term-{$termId}";
        $options['enabled-areas'][$termTemplate] = $archiveSidebars;

        return $options;
    }

    /**
     * Intercept the save_modules action for taxonomy terms
     * 
     * This runs before Modularity's save handler and handles taxonomy term saves.
     * If the ID is a taxonomy term, we save it and exit. Otherwise, we let
     * Modularity handle it normally.
     * 
     * @return void
     */
    public function interceptSaveModules(): void
    {
        // Prevent double execution
        if (self::$saveHandled) {
            return;
        }

        // Check for stored original ID first (if we transformed it)
        if (isset($_REQUEST['_original_taxonomy_term_id'])) {
            $_REQUEST['id'] = $_REQUEST['_original_taxonomy_term_id'];
            $_GET['id'] = $_REQUEST['_original_taxonomy_term_id'];
        }

        // Fallback: Try to get ID from referer if not in request
        if (empty($_REQUEST['id']) && !empty($_SERVER['HTTP_REFERER'])) {
            $referer = esc_url_raw($_SERVER['HTTP_REFERER']);
            if (preg_match('/[?&]id=([^&]+)/', $referer, $matches)) {
                $_REQUEST['id'] = $matches[1];
                $_GET['id'] = $matches[1];
                $_POST['id'] = $matches[1];
            }
        }

        $termId = self::isEditingTaxonomyTerm();

        if ($termId === false) {
            return; // Let Modularity handle it
        }

        // Verify user can edit
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Unauthorized', 403);
        }

        // Save modules
        $key = "taxonomy-term-{$termId}";
        $this->saveTermModulesAsOption($key);

        // Mark as handled to prevent double execution
        self::$saveHandled = true;

        // Handle response based on request type
        if (defined('DOING_AJAX') && DOING_AJAX) {
            // AJAX request - return success and stop execution
            echo 'success';
            wp_die();
        } else {
            // Form POST - redirect back to editor with success message
            $redirectUrl = admin_url('options.php?page=modularity-editor&id=taxonomy-term-' . $termId . '&modules_saved=1');
            wp_redirect($redirectUrl);
            exit;
        }
    }

    /**
     * Save term modules as WordPress options
     * 
     * @param string $key The option key suffix (e.g., 'taxonomy-term-123')
     * @return bool
     */
    private function saveTermModulesAsOption(string $key): bool
    {
        $optionName = 'modularity_' . $key . '_modules';

        if (isset($_POST['modularity_modules'])) {
            $data = $this->sanitizeModuleData($_POST['modularity_modules']);

            if (get_option($optionName) !== false) {
                update_option($optionName, $data);
            } else {
                add_option($optionName, $data, '', 'no');
            }
        } else {
            delete_option($optionName);
        }

        // Save/remove sidebar options
        $optionName = 'modularity_' . $key . '_sidebar-options';

        if (isset($_POST['modularity_sidebar_options'])) {
            if (get_option($optionName) !== false) {
                update_option($optionName, $_POST['modularity_sidebar_options']);
            } else {
                add_option($optionName, $_POST['modularity_sidebar_options'], '', 'no');
            }
        } else {
            delete_option($optionName);
        }

        return true;
    }

    /**
     * Sanitize module data (copied from Modularity\Editor)
     * 
     * Removes empty sidebars to ensure removed modules are properly deleted.
     * 
     * @param array $sidebars
     * @return array
     */
    private function sanitizeModuleData(array $sidebars): array
    {
        foreach ($sidebars as $sidebarKey => &$sidebar) {
            if (empty($sidebar) || !is_array($sidebar)) {
                // Remove empty sidebars - this is critical for module removal
                unset($sidebars[$sidebarKey]);
                continue;
            }

            foreach ($sidebar as &$module) {
                $module['hidden'] = isset($module['hidden']) && $module['hidden'] == 'hidden';
            }
        }

        return $sidebars;
    }

    /**
     * Check if we're editing a taxonomy term's modules
     * 
     * @return int|false Term ID if editing a taxonomy term, false otherwise
     */
    public static function isEditingTaxonomyTerm(): int|false
    {
        // Check both $_GET and $_REQUEST (AJAX might use $_REQUEST)
        $id = null;
        if (isset($_GET['id']) && is_string($_GET['id'])) {
            $id = sanitize_text_field($_GET['id']);
        } elseif (isset($_REQUEST['id']) && is_string($_REQUEST['id'])) {
            $id = sanitize_text_field($_REQUEST['id']);
        }

        // Check for stored original ID (if we transformed it)
        if (isset($_REQUEST['_original_taxonomy_term_id'])) {
            $id = sanitize_text_field($_REQUEST['_original_taxonomy_term_id']);
        }

        if (!$id) {
            return false;
        }

        // Handle transformed ID (archive-taxonomy-term-{id})
        if (strpos($id, 'archive-taxonomy-term-') === 0) {
            $id = str_replace('archive-taxonomy-term-', 'taxonomy-term-', $id);
        }

        if (strpos($id, 'taxonomy-term-') !== 0) {
            return false;
        }

        $termId = (int) str_replace('taxonomy-term-', '', $id);

        if ($termId <= 0) {
            return false;
        }

        // Verify the term exists
        $term = get_term($termId);

        if (!$term || is_wp_error($term)) {
            return false;
        }

        return $termId;
    }

    /**
     * Get the term being edited
     * 
     * @return \WP_Term|null
     */
    public static function getEditingTerm(): ?\WP_Term
    {
        $termId = self::isEditingTaxonomyTerm();

        if ($termId === false) {
            return null;
        }

        $term = get_term($termId);

        return ($term instanceof \WP_Term) ? $term : null;
    }

    /**
     * Setup the editing context for taxonomy terms
     * 
     * @return void
     */
    public function setupTaxonomyTermEditing(): void
    {
        $termId = self::isEditingTaxonomyTerm();

        if ($termId === false) {
            return;
        }

        $term = get_term($termId);

        if (!$term || is_wp_error($term)) {
            return;
        }

        // Set up the global archive variable that Modularity uses
        global $archive;
        $archive = "taxonomy-term-{$termId}";

        // Add admin bar node for viewing the term archive
        add_action('admin_bar_menu', function () use ($term) {
            global $wp_admin_bar;

            $termLink = get_term_link($term);

            if (!is_wp_error($termLink)) {
                $wp_admin_bar->add_node([
                    'id' => 'view_term_archive',
                    'title' => __('View Term Archive', 'pitea-customisation'),
                    'href' => $termLink,
                    'meta' => [
                        'target' => '_blank',
                    ],
                ]);
            }
        }, 1050);
    }

    /**
     * Set the modularity_post_id JavaScript variable for taxonomy terms
     * 
     * @return void
     */
    public function setModularityPostId(): void
    {
        // Only on the Modularity editor page
        if (!isset($_GET['page']) || $_GET['page'] !== 'modularity-editor') {
            return;
        }

        $termId = self::isEditingTaxonomyTerm();

        if ($termId === false) {
            return;
        }

        $id = "taxonomy-term-{$termId}";

        // Output JavaScript variable - wrap in quotes since it's a string
        echo '<script>var modularity_post_id = "' . esc_js($id) . '";</script>' . "\n";
    }

    /**
     * Filter the is_editing data for taxonomy terms
     * 
     * @param array $isEditing
     * @return array
     */
    public function filterIsEditing(array $isEditing): array
    {
        $term = self::getEditingTerm();

        if (!$term) {
            return $isEditing;
        }

        $taxonomyObject = get_taxonomy($term->taxonomy);
        $taxonomyLabel = $taxonomyObject ? $taxonomyObject->labels->singular_name : $term->taxonomy;

        return [
            'id' => null,
            'title' => sprintf('%s: %s', $taxonomyLabel, $term->name),
            'term_id' => $term->term_id,
            'taxonomy' => $term->taxonomy,
        ];
    }

    /**
     * Modify the editor title for taxonomy terms
     * 
     * @return void
     */
    public function modifyEditorTitle(): void
    {
        $term = self::getEditingTerm();

        if (!$term) {
            return;
        }

        $taxonomyObject = get_taxonomy($term->taxonomy);
        $taxonomyLabel = $taxonomyObject ? $taxonomyObject->labels->singular_name : $term->taxonomy;

        echo sprintf(': %s, Term ID', $term->name);
    }

    /**
     * Prepare taxonomy term ID for Modularity to recognize
     * 
     * Since Post::isArchive() only recognizes 'archive-' and 'single-' prefixes,
     * we transform our 'taxonomy-term-{id}' to 'archive-taxonomy-term-{id}' so
     * it passes the prefix check. We'll extract the real term ID in our save handler.
     * 
     * STABILITY NOTE: This directly manipulates superglobals ($_GET, $_REQUEST) and
     * a global variable ($archive). If Modularity changes how it reads IDs or if
     * WordPress changes superglobal handling, this will break. Monitor updates carefully.
     * 
     * @return void
     */
    public function prepareTaxonomyTermForModularity(): void
    {
        // Only on the Modularity editor page
        if (!isset($_GET['page']) || $_GET['page'] !== 'modularity-editor') {
            return;
        }

        // Check if we have a taxonomy term ID
        if (!isset($_GET['id']) || strpos($_GET['id'], 'taxonomy-term-') !== 0) {
            return;
        }

        $originalId = sanitize_text_field($_GET['id']);
        $transformedId = 'archive-' . $originalId; // Make it look like an archive

        // Transform the ID so Post::isArchive() recognizes it
        $_REQUEST['id'] = $transformedId;
        $_GET['id'] = $transformedId;

        // Store the original ID so we can restore it later
        $_REQUEST['_original_taxonomy_term_id'] = $originalId;

        // Set global $archive for Post::isArchive() to use
        global $archive;
        $archive = $transformedId;
    }

    /**
     * Check for form-based saves (non-AJAX)
     * 
     * This is the KEY method that makes module removal work.
     * Modularity uses form POST (not AJAX) when clicking Save, so we need to
     * detect form submissions and handle them directly.
     * 
     * @return void
     */
    public function checkForFormSave(): void
    {
        // Check if this is a save request
        // Modularity sends modularity_modules in POST when saving
        $isSaveRequest = isset($_POST['publish']) ||
            (isset($_REQUEST['action']) && $_REQUEST['action'] === 'save_modules') ||
            (isset($_POST['modularity_modules'])); // Core detection: if modularity_modules is in POST, it's a save

        if (!$isSaveRequest) {
            return;
        }

        // Try to get ID from referer if not in request
        if (empty($_REQUEST['id']) && !empty($_SERVER['HTTP_REFERER'])) {
            $referer = esc_url_raw($_SERVER['HTTP_REFERER']);
            if (preg_match('/[?&]id=([^&]+)/', $referer, $matches)) {
                $_REQUEST['id'] = $matches[1];
                $_GET['id'] = $matches[1];
                $_POST['id'] = $matches[1];
            }
        }

        // Check if this is a taxonomy term save
        $termId = self::isEditingTaxonomyTerm();
        if ($termId !== false) {
            // Call our save handler directly since this is a form POST
            $this->interceptSaveModules();
        }
    }

    /**
     * Inject JavaScript to ensure taxonomy term ID is included in AJAX requests
     * 
     * NOTE: This is defensive code - Modularity currently uses form POST, not AJAX.
     * If Modularity switches to AJAX in the future, this will ensure the ID is included.
     * 
     * @return void
     */
    public function injectAjaxIdFix(): void
    {
        // Only on the Modularity editor page
        if (!isset($_GET['page']) || $_GET['page'] !== 'modularity-editor') {
            return;
        }

        $termId = self::isEditingTaxonomyTerm();

        if ($termId === false) {
            return;
        }

        // Use the original taxonomy-term-{id} format (not the archive-transformed version)
        $originalId = "taxonomy-term-{$termId}";
?>
        <script>
            (function() {
                var taxonomyTermId = <?php echo json_encode($originalId); ?>;

                // Intercept jQuery AJAX requests (defensive - currently unused)
                if (typeof jQuery !== 'undefined') {
                    var originalAjax = jQuery.ajax;
                    jQuery.ajax = function(options) {
                        if (options.url && options.url.indexOf('action=save_modules') !== -1) {
                            if (!options.data) {
                                options.data = {};
                            }
                            if (typeof options.data === 'string') {
                                options.data += '&id=' + encodeURIComponent(taxonomyTermId);
                            } else if (typeof options.data === 'object') {
                                options.data.id = taxonomyTermId;
                            }
                        }
                        return originalAjax.apply(this, arguments);
                    };
                }

                // Intercept fetch requests (defensive - currently unused)
                if (typeof fetch !== 'undefined') {
                    var originalFetch = window.fetch;
                    window.fetch = function(url, options) {
                        if (url && url.indexOf('action=save_modules') !== -1) {
                            if (!options) {
                                options = {};
                            }
                            if (!options.body) {
                                options.body = new FormData();
                            }
                            if (options.body instanceof FormData) {
                                options.body.append('id', taxonomyTermId);
                            } else if (typeof options.body === 'string') {
                                options.body += '&id=' + encodeURIComponent(taxonomyTermId);
                            }
                        }
                        return originalFetch.apply(this, arguments);
                    };
                }
            })();
        </script>
<?php
    }
}
