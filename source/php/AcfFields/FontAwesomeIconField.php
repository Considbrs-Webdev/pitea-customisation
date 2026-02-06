<?php

namespace PiteaCustomisation\AcfFields;

use PiteaCustomisation\Helpers\CacheBust;

class FontAwesomeIconField extends \acf_field
{
    /**
     * Field type name
     */
    public $name = 'fontawesome_icon';

    /**
     * Field label
     */
    public $label = 'FontAwesome Icon';

    /**
     * Field category
     */
    public $category = 'choice';

    /**
     * Default field settings
     */
    public $defaults = [
        'allow_null' => 0,
        'placeholder' => '',
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        add_action('wp_ajax_acf/fields/fontawesome_icon/query', [$this, 'ajaxQuery']);
    }

    /**
     * Render field settings
     *
     * @param array $field The field settings
     * @return void
     */
    public function render_field_settings($field): void
    {
        acf_render_field_setting($field, [
            'label'        => __('Allow Null?', 'acf'),
            'instructions' => '',
            'name'         => 'allow_null',
            'type'         => 'true_false',
            'ui'           => 1,
        ]);

        acf_render_field_setting($field, [
            'label'        => __('Placeholder', 'acf'),
            'instructions' => __('Appears within the input', 'acf'),
            'name'         => 'placeholder',
            'type'         => 'text',
        ]);
    }

    /**
     * Enqueue field assets
     *
     * @return void
     */
    public function input_admin_enqueue_scripts(): void
    {
        $version = '1.0.0';

        // Enqueue font awesome icons stylesheet
        $file = CacheBust::getFile('source/sass/font-awesome.scss');
        if ($file) {
            wp_enqueue_style(
                'font-awesome-icons-style',
                $file,
                [],
                $version,
            );
        }

        $file = CacheBust::getFile('source/js/acf/acf-fontawesome-icon-field.js');
        if ($file) {
            wp_enqueue_script(
                'acf-fontawesome-icon-field',
                $file,
                ['acf-input', 'jquery'],
                $version,
                true
            );
        }

        $file = CacheBust::getFile('source/sass/acf/acf-fontawesome-icon-field.scss');
        if ($file) {
            wp_enqueue_style(
                'acf-fontawesome-icon-field',
                $file,
                ['acf-input'],
                $version
            );
        }
    }

    /**
     * Render the field
     *
     * @param array $field The field settings
     * @return void
     */
    public function render_field($field): void
    {
        $attrs = [
            'id'               => $field['id'],
            'class'            => 'acf-fontawesome-icon-field',
            'data-allow_null'  => $field['allow_null'],
            'data-placeholder' => $field['placeholder'],
        ];
        ?>
        <div <?php echo acf_esc_attrs($attrs); ?>>
            <input 
                type="hidden" 
                name="<?php echo esc_attr($field['name']); ?>" 
                value="<?php echo esc_attr($field['value']); ?>"
                class="acf-fontawesome-icon-value"
            />
            
            <div class="acf-fontawesome-icon-preview">
                <?php if ($field['value']): ?>
                    <i class="<?php echo esc_attr($field['value']); ?>"></i>
                <?php else: ?>
                    <span class="acf-fontawesome-icon-no-selection"><?php _e('No icon selected', 'pitea-customisation'); ?></span>
                <?php endif; ?>
            </div>
            
            <div class="acf-fontawesome-icon-search-wrap">
                <input 
                    type="text" 
                    class="acf-fontawesome-icon-search" 
                    placeholder="<?php echo esc_attr($field['placeholder'] ?: __('Search icons...', 'pitea-customisation')); ?>"
                    autocomplete="off"
                />
                <div class="acf-fontawesome-icon-dropdown"></div>
            </div>
            
            <?php if ($field['allow_null'] && $field['value']): ?>
                <button type="button" class="acf-fontawesome-icon-clear button"><?php _e('Clear', 'pitea-customisation'); ?></button>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * AJAX handler for querying icons
     *
     * @return void
     */
    public function ajaxQuery(): void
    {
        $search = isset($_POST['s']) ? sanitize_text_field($_POST['s']) : '';
        $paged = isset($_POST['paged']) ? intval($_POST['paged']) : 1;
        $perPage = 50;

        $icons = $this->getIcons();

        // Filter by search term
        if (!empty($search)) {
            $icons = array_filter($icons, function ($icon) use ($search) {
                // Search in label
                if (stripos($icon['label'], $search) !== false) {
                    return true;
                }

                // Search in id (icon key name)
                if (stripos($icon['id'], $search) !== false) {
                    return true;
                }

                // Search in classname
                if (stripos($icon['classname'], $search) !== false) {
                    return true;
                }

                // Search in search terms
                foreach ($icon['searchTerms'] as $term) {
                    if (stripos($term, $search) !== false) {
                        return true;
                    }
                }

                return false;
            });
        }

        // Paginate
        $total = count($icons);
        $icons = array_slice($icons, ($paged - 1) * $perPage, $perPage);

        // Format results
        $results = [];
        foreach ($icons as $icon) {
            $results[] = [
                'id'    => $icon['classname'],
                'label' => $icon['label'],
            ];
        }

        wp_send_json([
            'results' => $results,
            'more'    => ($paged * $perPage) < $total,
        ]);
    }

    /**
     * Get icons from JSON file
     *
     * @return array
     */
    protected function getIcons(): array
    {
        static $icons = null;

        if ($icons === null) {
            $icons = [];
            $jsonPath = dirname(__DIR__, 3) . '/data/fontawesome-icons.json';

            if (!file_exists($jsonPath)) {
                return $icons;
            }

            $raw = json_decode(file_get_contents($jsonPath), true) ?: [];

            foreach ($raw as $name => $meta) {
                if (!is_array($meta)) {
                    continue;
                }

                // Get label
                $label = !empty($meta['label']) ? $meta['label'] : ucwords(str_replace('-', ' ', $name));

                // Get classname (use first one if array)
                $classname = '';
                if (!empty($meta['classname'])) {
                    $classname = is_array($meta['classname']) ? $meta['classname'][0] : $meta['classname'];
                }

                if (empty($classname)) {
                    continue; // Skip icons without a classname
                }

                // Get search terms
                $searchTerms = [];
                if (!empty($meta['search']) && is_array($meta['search'])) {
                    $searchTerms = $meta['search'];
                }

                $icons[] = [
                    'id'          => $name,
                    'label'       => $label,
                    'classname'   => $classname,
                    'searchTerms' => $searchTerms,
                ];
            }
        }

        return $icons;
    }
}
