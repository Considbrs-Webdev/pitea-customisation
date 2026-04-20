<?php

namespace PiteaCustomisation\Customisations\Modules;

class Text
{
    private const TEXT_MODULE_FIELD_GROUP = 'group_5891b49127038';

    private const TEXT_MODULE_CARD_CONTEXT = 'module.text.box';

    /**
     * Inline styles for the next Text module box @card, set in viewData and consumed in ComponentLibrary/Component/Data.
     */
    private static ?string $pendingCardStyle = null;

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
        add_filter('Modularity/Display/mod-text/viewData', [$this, 'captureTextModuleStyles']);
        // Older ComponentLibrary versions (mu-plugins/component-library, used by the legacy
        // `municipio` theme) fire this filter with 1 argument, while the newer version bundled
        // in `new_municipio` fires it with 2. Register for 1 arg so we stay compatible with both;
        // the method itself still accepts an optional component instance when provided.
        add_filter('ComponentLibrary/Component/Data', [$this, 'applyPendingCardStyles'], 10, 1);

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
     * Prepare inline styles for the boxed Text module card (runs before Blade render).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function captureTextModuleStyles(array $data): array
    {
        self::$pendingCardStyle = null;

        if (empty($data['text_module_enable_styling'])) {
            return $data;
        }

        // Article / no-frame variant: no @card with module.text.box — styling not applied there.
        if (!empty($data['hide_box_frame'])) {
            return $data;
        }

        $bg = isset($data['text_module_background']) ? trim((string) $data['text_module_background']) : '';
        $borderColor = isset($data['text_module_border_color']) ? trim((string) $data['text_module_border_color']) : '';
        $borderThickness = $data['text_module_border_thickness'] ?? null;
        $borderRadius = $data['text_module_border_radius'] ?? null;

        $hasBorder = $borderColor !== ''
            || ($borderThickness !== null && $borderThickness !== '' && (string) $borderThickness !== '0');

        $declarations = [];

        if ($hasBorder) {
            $declarations[] = 'border-style: solid';
        }

        if ($bg !== '') {
            $declarations[] = 'background-color: ' . esc_attr($bg);
            $declarations[] = 'color: ' . esc_attr($this->getContrastTextColor($bg));
        }

        if ($borderColor !== '') {
            $declarations[] = 'border-color: ' . esc_attr($borderColor);
        }

        if ($borderThickness !== null && $borderThickness !== '') {
            $declarations[] = 'border-width: ' . esc_attr((string) (int) $borderThickness) . 'px';
        }

        if ($borderRadius !== null && $borderRadius !== '') {
            $declarations[] = 'border-radius: ' . esc_attr((string) (int) $borderRadius) . 'px';
        }

        if ($declarations === []) {
            return $data;
        }

        self::$pendingCardStyle = implode('; ', $declarations);

        return $data;
    }

    /**
     * Merge pending Text module styles onto the Card used by box.blade.php (context module.text.box).
     *
     * The ComponentLibrary filter is fired with 1 arg in the legacy (mu-plugins) component-library
     * and with 2 args in the newer (theme-vendored) one. `$_component` is therefore optional so the
     * same callback works against either version.
     *
     * @param array<string, mixed> $data
     * @param object|null $_component Component instance (BaseController) when available; not used.
     * @return array<string, mixed>
     */
    public function applyPendingCardStyles(array $data, ?object $_component = null): array
    {
        if (self::$pendingCardStyle === null || self::$pendingCardStyle === '') {
            return $data;
        }

        $context = $data['context'] ?? [];
        $contextList = is_array($context) ? $context : [$context];
        if (!in_array(self::TEXT_MODULE_CARD_CONTEXT, $contextList, true)) {
            return $data;
        }

        if (!isset($data['attributeList']) || !is_array($data['attributeList'])) {
            $data['attributeList'] = [];
        }

        $existing = $data['attributeList']['style'] ?? '';
        $merged = self::$pendingCardStyle;
        if ($existing !== '' && $existing !== null) {
            $data['attributeList']['style'] = $existing . '; ' . $merged;
        } else {
            $data['attributeList']['style'] = $merged;
        }

        self::$pendingCardStyle = null;

        return $data;
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
}
