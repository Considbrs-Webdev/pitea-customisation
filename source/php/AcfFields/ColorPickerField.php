<?php

namespace PiteaCustomisation\AcfFields;

use PiteaCustomisation\Helpers\ScssColorParser;

class ColorPickerField extends \acf_field
{
    /**
     * Field type name
     */
    public $name = 'pitea_color_picker';

    /**
     * Field label
     */
    public $label = 'Design System Color';

    /**
     * Field category
     */
    public $category = 'jquery';

    /**
     * Default field settings
     */
    public $defaults = [
        'allow_null' => 0,
        'allow_custom' => 1,
        'default_value' => '',
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
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
            'label'        => __('Allow Custom Colors?', 'pitea-customisation'),
            'instructions' => __('Allow users to enter custom hex colors', 'pitea-customisation'),
            'name'         => 'allow_custom',
            'type'         => 'true_false',
            'ui'           => 1,
        ]);

        acf_render_field_setting($field, [
            'label'        => __('Default Value', 'acf'),
            'instructions' => '',
            'name'         => 'default_value',
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

        wp_enqueue_script(
            'acf-pitea-color-picker-field',
            plugin_dir_url(dirname(__DIR__, 2)) . 'assets/js/acf-pitea-color-picker-field.js',
            ['acf-input', 'jquery'],
            $version,
            true
        );

        // Localize script with design system colors (grouped)
        wp_localize_script('acf-pitea-color-picker-field', 'piteaColorPicker', [
            'colorGroups' => $this->getDesignSystemColors(),
            'flatColors' => $this->getFlatColorList(),
        ]);

        wp_enqueue_style(
            'acf-pitea-color-picker-field',
            plugin_dir_url(dirname(__DIR__, 2)) . 'assets/css/acf-pitea-color-picker-field.css',
            ['acf-input'],
            $version
        );
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
            'class'            => 'acf-pitea-color-picker-field',
            'data-allow_null'  => $field['allow_null'],
            'data-allow_custom' => $field['allow_custom'] ?? 1,
        ];

        $colorGroups = $this->getDesignSystemColors();
        $flatColors = $this->getFlatColorList();
        $currentValue = $field['value'] ?? '';
        $currentColorName = $this->getColorNameByValue($currentValue, $flatColors);
?>
        <div <?php echo acf_esc_attrs($attrs); ?>>
            <input
                type="hidden"
                name="<?php echo esc_attr($field['name']); ?>"
                value="<?php echo esc_attr($currentValue); ?>"
                class="acf-pitea-color-picker-value" />

            <!-- Trigger Button -->
            <button
                type="button"
                class="acf-pitea-color-picker-trigger button button-secondary"
                title="<?php echo $currentValue ? esc_attr(($currentColorName ?: $currentValue) . ' (' . $currentValue . ')') : esc_attr__('Select Color', 'pitea-customisation'); ?>">
                <div class="acf-pitea-color-picker-selected">
                    <?php if ($currentValue): ?>
                        <div class="acf-pitea-color-picker-preview">
                            <span
                                class="acf-pitea-color-picker-swatch"
                                style="background-color: <?php echo esc_attr($currentValue); ?>;"></span>
                            <span class="acf-pitea-color-picker-name">
                                <?php echo esc_html($currentColorName ?: $currentValue); ?>
                            </span>
                        </div>
                    <?php else: ?>
                        <span class="acf-pitea-color-picker-no-selection">
                            <?php _e('Select Color', 'pitea-customisation'); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </button>

            <?php if ($field['allow_null']): ?>
                <button type="button" class="acf-pitea-color-picker-clear-trigger button button-link" <?php echo !$currentValue ? ' style="display: none;"' : ''; ?>>
                    <?php _e('Clear', 'pitea-customisation'); ?>
                </button>
            <?php endif; ?>

            <!-- Modal Overlay -->
            <div class="acf-pitea-color-picker-modal" style="display: none;">
                <div class="acf-pitea-color-picker-modal-overlay"></div>
                <div class="acf-pitea-color-picker-modal-content">
                    <div class="acf-pitea-color-picker-modal-header">
                        <h3><?php _e('Select Color', 'pitea-customisation'); ?></h3>
                        <button type="button" class="acf-pitea-color-picker-modal-close" aria-label="<?php _e('Close', 'pitea-customisation'); ?>">×</button>
                    </div>

                    <div class="acf-pitea-color-picker-modal-body">
                        <!-- Preview Section -->
                        <div class="acf-pitea-color-picker-modal-preview">
                            <?php if ($currentValue): ?>
                                <div class="acf-pitea-color-picker-modal-preview-content">
                                    <span
                                        class="acf-pitea-color-picker-modal-preview-swatch"
                                        style="background-color: <?php echo esc_attr($currentValue); ?>;"></span>
                                    <div class="acf-pitea-color-picker-modal-preview-info">
                                        <span class="acf-pitea-color-picker-modal-preview-name">
                                            <?php echo esc_html($currentColorName ?: $currentValue); ?>
                                        </span>
                                        <span class="acf-pitea-color-picker-modal-preview-hex">
                                            <?php echo esc_html($currentValue); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="acf-pitea-color-picker-modal-preview-empty">
                                    <?php _e('No color selected', 'pitea-customisation'); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="acf-pitea-color-picker-palette">
                            <?php foreach ($colorGroups as $groupName => $colors): ?>
                                <div class="acf-pitea-color-picker-group is-collapsed" data-group="<?php echo esc_attr($groupName); ?>">
                                    <button
                                        type="button"
                                        class="acf-pitea-color-picker-group-header"
                                        aria-expanded="false">
                                        <span class="acf-pitea-color-picker-group-name"><?php echo esc_html($groupName); ?></span>
                                        <span class="acf-pitea-color-picker-group-toggle"></span>
                                    </button>
                                    <div class="acf-pitea-color-picker-group-content">
                                        <?php foreach ($colors as $colorData): ?>
                                            <button
                                                type="button"
                                                class="acf-pitea-color-picker-option <?php echo ($currentValue === $colorData['hex']) ? 'is-selected' : ''; ?>"
                                                data-color="<?php echo esc_attr($colorData['hex']); ?>"
                                                data-name="<?php echo esc_attr($colorData['name']); ?>"
                                                data-var="<?php echo esc_attr($colorData['var'] ?? ''); ?>"
                                                title="<?php echo esc_attr($colorData['name'] . ' (' . $colorData['hex'] . ')'); ?>">
                                                <span
                                                    class="acf-pitea-color-picker-swatch"
                                                    style="background-color: <?php echo esc_attr($colorData['hex']); ?>;"></span>
                                                <span class="acf-pitea-color-picker-label"><?php echo esc_html($colorData['name']); ?></span>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($field['allow_custom'] ?? 1): ?>
                            <div class="acf-pitea-color-picker-custom">
                                <button type="button" class="acf-pitea-color-picker-custom-toggle button">
                                    <?php _e('Use Custom Color', 'pitea-customisation'); ?>
                                </button>
                                <div class="acf-pitea-color-picker-custom-input" style="display: none;">
                                    <input
                                        type="text"
                                        class="acf-pitea-color-picker-hex-input"
                                        placeholder="#000000"
                                        value="<?php echo esc_attr($this->isCustomColor($currentValue, $flatColors) ? $currentValue : ''); ?>"
                                        pattern="^#[0-9A-Fa-f]{6}$" />
                                    <button type="button" class="acf-pitea-color-picker-custom-apply button button-primary">
                                        <?php _e('Apply', 'pitea-customisation'); ?>
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="acf-pitea-color-picker-modal-footer">
                        <?php if ($field['allow_null'] && $currentValue): ?>
                            <button type="button" class="acf-pitea-color-picker-clear button">
                                <?php _e('Clear', 'pitea-customisation'); ?>
                            </button>
                        <?php endif; ?>
                        <button type="button" class="acf-pitea-color-picker-modal-close button button-primary">
                            <?php _e('Done', 'pitea-customisation'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
<?php
    }

    /**
     * Get design system colors from SCSS variables file, grouped by comment sections
     *
     * @return array Array of group name => array of color data
     */
    protected function getDesignSystemColors(): array
    {
        static $colorGroups = null;

        if ($colorGroups === null) {
            $colorGroups = [];

            // Prefer data/variables.scss (copied during build); fallback to source for dev
            $pluginDir = dirname(__DIR__, 3);
            $scssPath  = ScssColorParser::resolveScssPath($pluginDir);

            if ($scssPath !== null) {
                $colorGroups = ScssColorParser::parseFile($scssPath);
            }

            // Fallback to Municipio if SCSS file not found or empty
            if (empty($colorGroups)) {
                $colorGroups = $this->getFallbackColors();
            }
        }

        return apply_filters('PiteaCustomisation/ColorPicker/DesignSystemColorGroups', $colorGroups);
    }

    /**
     * Get flat list of all colors (name => hex) for backward compatibility
     *
     * @return array
     */
    protected function getFlatColorList(): array
    {
        static $flatColors = null;

        if ($flatColors === null) {
            $flatColors = [];
            $colorGroups = $this->getDesignSystemColors();

            foreach ($colorGroups as $group => $colors) {
                foreach ($colors as $colorData) {
                    $flatColors[$colorData['name']] = $colorData['hex'];
                }
            }
        }

        return $flatColors;
    }

    /**
     * Fallback to Municipio colors if SCSS file parsing fails
     *
     * @return array
     */
    protected function getFallbackColors(): array
    {
        $colorGroups = [];

        // Get colors from Municipio design system
        if (class_exists('\Municipio\Helper\Color')) {
            $palettes = \Municipio\Helper\Color::getPalettes([
                'color_palette_primary',
                'color_palette_secondary',
                'color_palette_complement',
                'color_palette_additional',
            ]);

            foreach ($palettes as $paletteName => $palette) {
                if (!is_array($palette)) {
                    continue;
                }

                $groupName = str_replace('color_palette_', '', $paletteName);
                $groupName = ucfirst($groupName);

                if (!isset($colorGroups[$groupName])) {
                    $colorGroups[$groupName] = [];
                }

                foreach ($palette as $colorKey => $hex) {
                    if (empty($hex)) {
                        continue;
                    }

                    $colorName = ucfirst(str_replace('_', ' ', $colorKey));

                    $colorGroups[$groupName][] = [
                        'name' => $colorName,
                        'hex' => $hex,
                        'var' => null,
                    ];
                }
            }
        }

        // Fallback to KirkiSwatches if available
        if (empty($colorGroups) && class_exists('\Municipio\Helper\KirkiSwatches')) {
            $swatches = \Municipio\Helper\KirkiSwatches::getColors();
            $colorGroups['Default'] = [];
            foreach ($swatches as $index => $hex) {
                $colorGroups['Default'][] = [
                    'name' => 'Color ' . ($index + 1),
                    'hex' => $hex,
                    'var' => null,
                ];
            }
        }

        return $colorGroups;
    }

    /**
     * Get color name by hex value
     *
     * @param string $value
     * @param array $colors
     * @return string|null
     */
    protected function getColorNameByValue(string $value, array $colors): ?string
    {
        foreach ($colors as $name => $hex) {
            if (strtolower($hex) === strtolower($value)) {
                return $name;
            }
        }
        return null;
    }

    /**
     * Check if value is a custom color (not in design system)
     *
     * @param string $value
     * @param array $colors
     * @return bool
     */
    protected function isCustomColor(string $value, array $colors): bool
    {
        return !empty($value) && $this->getColorNameByValue($value, $colors) === null;
    }

    /**
     * Format the value for display
     *
     * @param mixed $value
     * @param int $post_id
     * @param array $field
     * @return mixed
     */
    public function format_value($value, $post_id, $field)
    {
        if (empty($value)) {
            return $value;
        }

        // Return hex value (can be extended to return CSS variable if needed)
        return $value;
    }
}
