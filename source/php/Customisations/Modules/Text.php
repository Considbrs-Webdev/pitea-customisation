<?php

namespace PiteaCustomisation\Customisations\Modules;

class Text
{
    private const TEXT_MODULE_FIELD_GROUP = 'group_5891b49127038';

    /**
     * Field definitions - single source of truth
     */
    private const FIELDS = [
        'text_module_enable_styling' => [
            'type' => 'true_false',
            'label' => 'Enable Styling',
            'instructions' => 'Enable custom styling options for this text module.',
            'css_property' => null,
            'unit' => '',
        ],
        'text_module_background' => [
            'type' => 'color_picker',
            'label' => 'Background',
            'instructions' => 'Set a background color for this text module.',
            'css_property' => 'background-color',
            'unit' => '',
        ],
        'text_module_border_color' => [
            'type' => 'color_picker',
            'label' => 'Border Color',
            'instructions' => 'Set a border color for this text module.',
            'css_property' => 'border-color',
            'unit' => '',
        ],
        'text_module_border_thickness' => [
            'type' => 'number',
            'label' => 'Border Thickness',
            'instructions' => 'Set the border thickness in pixels.',
            'css_property' => 'border-width',
            'unit' => 'px',
        ],
        'text_module_border_radius' => [
            'type' => 'number',
            'label' => 'Border Radius',
            'instructions' => 'Set the border radius in pixels.',
            'css_property' => 'border-radius',
            'unit' => 'px',
        ],

    ];

    public function __construct()
    {
        add_action('acf/init', [$this, 'registerFields'], 20);
        add_action('wp_head', [$this, 'injectModuleSettingsStyles'], 999);

        // Hide fields from Gutenberg editor (only show in module editor)
        foreach (array_keys(self::FIELDS) as $fieldName) {
            add_filter("acf/prepare_field/name={$fieldName}", [$this, 'hideFieldInGutenberg']);
        }
    }


    /**
     * Register fields
     */
    public function registerFields(): void
    {
        if (!function_exists('acf_add_local_field')) {
            return;
        }

        foreach (self::FIELDS as $name => $definition) {
            $fieldKey = 'field_' . $name;


            $field = [
                'key' => $fieldKey,
                'label' => __($definition['label'], 'pitea-customisation'),
                'name' => $name,
                'type' => $definition['type'],
                'instructions' => __($definition['instructions'], 'pitea-customisation'),
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => ['width' => '', 'class' => '', 'id' => ''],
                'default_value' => '',
                'parent' => self::TEXT_MODULE_FIELD_GROUP,
            ];

            // Type-specific options
            if ($definition['type'] === 'color_picker') {
                $field['enable_opacity'] = 1;
                $field['return_format'] = 'string';
            } elseif ($definition['type'] === 'number') {
                $field['placeholder'] = '0';
                $field['append'] = 'px';
                $field['min'] = 0;
                $field['step'] = 1;
            } elseif ($definition['type'] === 'true_false') {
                $field['message'] = __('Enable', 'pitea-customisation');
                $field['ui'] = 1;
                $field['ui_on_text'] = '';
                $field['ui_off_text'] = '';
                $field['default_value'] = 0;
                $field['menu_order'] = 0;
            }

            // Add conditional logic for all fields except the toggle itself
            if ($name !== 'text_module_enable_styling') {
                $field['conditional_logic'] = [
                    [
                        [
                            'field' => 'field_text_module_enable_styling',
                            'operator' => '==',
                            'value' => '1',
                        ],
                    ],
                ];
            }

            acf_add_local_field($field);
        }
    }

    /**
     * Hide field when editing in Gutenberg (block editor)
     * Only show in the classic module editor (post_type = mod-text)
     *
     * @param array|false $field The field settings or false to hide
     * @return array|false
     */
    public function hideFieldInGutenberg(array|false $field): array|false
    {
        if ($field === false) {
            return false;
        }

        // Get current screen
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        // If we're editing a mod-text post type, show the field
        if ($screen && $screen->post_type === 'mod-text') {
            return $field;
        }

        // Check if we're in an AJAX request for the module editor
        if (wp_doing_ajax()) {
            $postId = $_POST['post_id'] ?? $_GET['post_id'] ?? 0;
            if ($postId) {
                $postType = get_post_type($postId);
                if ($postType === 'mod-text') {
                    return $field;
                }
            }
        }

        // Hide field in all other contexts (Gutenberg blocks, other post types)
        return false;
    }

    /**
     * Collect all module settings
     */
    private function collectAllModuleSettings(): array
    {
        $settings = [];

        if (!class_exists('\Modularity\Editor')) {
            return $settings;
        }

        $postId = get_the_ID();
        if (!$postId) {
            return $settings;
        }

        $allModules = [];
        $modules = \Modularity\Editor::getPostModules($postId);

        // Singular or archive modules
        if (is_singular() || is_archive()) {
            $templateSlug = is_singular()
                ? \Modularity\Helper\Wp::getSingleSlug()
                : \Modularity\Helper\Wp::getArchiveSlug();
            $templateModules = \Modularity\Editor::getPostModules($templateSlug);
            $modules = array_merge($modules, $templateModules);
        }

        if (is_array($modules)) {
            foreach ($modules as $item) {
                if (isset($item['modules']) && is_array($item['modules'])) {
                    $allModules = array_merge($allModules, $item['modules']);
                }
            }
        }

        foreach ($allModules as $module) {
            if (!is_object($module) || ($module->post_type ?? '') !== 'mod-text' || !isset($module->ID)) {
                continue;
            }

            $moduleSettings = [];
            foreach (self::FIELDS as $name => $definition) {
                $value = get_field($name, $module->ID);
                if (!empty($value)) {
                    $moduleSettings[$name] = [
                        'value' => $value,
                        'property' => $definition['css_property'],
                        'unit' => $definition['unit'],
                    ];
                }
            }

            // Only add if module has at least one setting
            if (!empty($moduleSettings)) {
                $settings[$module->ID] = $moduleSettings;
            }
        }

        return $settings;
    }

    /**
     * Calculate optimal text color (white or black) based on background color contrast
     * Uses WCAG relative luminance formula
     *
     * @param string $backgroundColor Background color in hex, rgb, or rgba format
     * @return string 'white' or 'black'
     */
    private function getContrastTextColor(string $backgroundColor): string
    {
        // Parse color to RGB values
        $rgb = $this->parseColorToRgb($backgroundColor);

        if (!$rgb) {
            return 'black'; // Default fallback
        }

        // Calculate relative luminance using WCAG formula
        // L = 0.2126 * R + 0.7152 * G + 0.0722 * B
        // Where R, G, B are normalized to 0-1 range
        $r = $rgb['r'] / 255;
        $g = $rgb['g'] / 255;
        $b = $rgb['b'] / 255;

        // Apply gamma correction
        $r = $r <= 0.03928 ? $r / 12.92 : pow(($r + 0.055) / 1.055, 2.4);
        $g = $g <= 0.03928 ? $g / 12.92 : pow(($g + 0.055) / 1.055, 2.4);
        $b = $b <= 0.03928 ? $b / 12.92 : pow(($b + 0.055) / 1.055, 2.4);

        $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

        // Return white for dark backgrounds, black for light backgrounds
        // Threshold of 0.5 (middle gray)
        return $luminance > 0.5 ? 'black' : 'white';
    }

    /**
     * Parse color string to RGB array
     * Supports hex (#rgb, #rrggbb), rgb(), and rgba() formats
     *
     * @param string $color Color string
     * @return array|null Array with 'r', 'g', 'b' keys or null if invalid
     */
    private function parseColorToRgb(string $color): ?array
    {
        $color = trim($color);

        // Handle hex colors (#rgb or #rrggbb)
        if (preg_match('/^#([a-f0-9]{3}|[a-f0-9]{6})$/i', $color, $matches)) {
            $hex = $matches[1];

            // Expand short hex (#rgb -> #rrggbb)
            if (strlen($hex) === 3) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            }

            return [
                'r' => hexdec(substr($hex, 0, 2)),
                'g' => hexdec(substr($hex, 2, 2)),
                'b' => hexdec(substr($hex, 4, 2)),
            ];
        }

        // Handle rgb() and rgba() formats
        if (preg_match('/rgba?\((\d+),\s*(\d+),\s*(\d+)/i', $color, $matches)) {
            return [
                'r' => (int) $matches[1],
                'g' => (int) $matches[2],
                'b' => (int) $matches[3],
            ];
        }

        return null;
    }

    private function generateCssRule(string $selector, string $property, string $value, string $unit = ''): string
    {
        if (empty($value)) {
            return '';
        }

        return sprintf(
            '%s { %s: %s; }',
            $selector,
            $property,
            esc_attr($value) . $unit,
        );
    }

    public function injectModuleSettingsStyles(): void
    {
        $settings = $this->collectAllModuleSettings();

        if (empty($settings)) {
            return;
        }

        $css = '<style id="text-module-settings" type="text/css">';

        foreach ($settings as $moduleId => $moduleSettings) {
            $selector = ".modularity-mod-text-{$moduleId}";

            // Add border-style if border properties are set
            $hasBorder = isset($moduleSettings['text_module_border_color'])
                || isset($moduleSettings['text_module_border_thickness']);
            if ($hasBorder) {
                $css .= "{$selector} { border-style: solid; }";
            }

            // Add text color if background is set
            if (isset($moduleSettings['text_module_background'])) {
                $backgroundColor = $moduleSettings['text_module_background']['value'];
                $textColor = $this->getContrastTextColor($backgroundColor);
                $css .= $this->generateCssRule($selector, 'color', $textColor);
            }

            foreach ($moduleSettings as $setting) {
                if ($setting['property'] === null) {
                    continue;
                }
                $css .= $this->generateCssRule($selector, $setting['property'], $setting['value'], $setting['unit']);
            }
        }

        $css .= '</style>';

        echo $css;
    }
}
