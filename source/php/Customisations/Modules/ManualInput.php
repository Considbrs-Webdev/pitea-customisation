<?php

namespace PiteaCustomisation\Customisations\Modules;

/**
 * Adds per-card eyebrow color fields to the Manual Input module (Modularity + Gutenberg)
 * and applies them via CSS variables on each item wrapper (stable #id from core).
 * Adds a module-level accordion header background color (same for all rows when display is accordion).
 */
class ManualInput
{
    private const MANUAL_INPUTS_REPEATER_KEY = 'field_64ff22b2d91b7';

    private const DISPLAY_AS_FIELD_KEY = 'field_6752f959acfda';

    private const ACCORDION_HEADER_BG_FIELD_KEY = 'field_pitea_mi_accordion_header_bg';

    /** Municipio “Eyebrow” text subfield (manual_inputs repeater). */
    private const EYEBROW_TEXT_FIELD_KEY = 'field_6945264b7d66e';

    private const ACCORDION_CARD_CONTEXT = 'module.manual-input.accordion';

    /**
     * CSS declarations for the current Manual Input accordion, applied to the
     * wrapping Card and Accordion components (item init() overwrites style).
     */
    private static ?string $pendingAccordionStyle = null;

    private const FIELDS = [
        'manual_input_eyebrow_background' => [
            'key' => 'field_pitea_mi_eyebrow_bg',
            'type' => 'color_picker',
            'label' => 'Eyebrow background',
            'instructions' => 'Optional. Overrides the default eyebrow background for this card.',
        ],
        'manual_input_eyebrow_text_color' => [
            'key' => 'field_pitea_mi_eyebrow_text',
            'type' => 'color_picker',
            'label' => 'Eyebrow text',
            'instructions' => 'Optional. Overrides the default eyebrow text color for this card.',
        ],
    ];

    public function __construct()
    {
        add_action('acf/init', [$this, 'registerFields'], 20);
        add_filter('acf/load_field/key=' . self::MANUAL_INPUTS_REPEATER_KEY, [$this, 'orderEyebrowColorFieldsAfterEyebrow'], 99, 1);
        add_filter('Modularity/Display/mod-manualinput/viewData', [$this, 'applyEyebrowStylesToItems'], 10, 1);
        add_filter('Modularity/Display/mod-manualinput/viewData', [$this, 'applyAccordionHeaderBgToItems'], 10, 1);
        add_filter('Modularity/Display/mod-manualinput/viewData', [$this, 'injectDisableLayoutShift'], 5, 1);
        add_filter('ComponentLibrary/Component/Data', [$this, 'applyPendingAccordionCardStyles'], 10, 1);
        add_filter('ComponentLibrary/Component/Accordion/Attribute', [$this, 'mergePendingAccordionAttributes']);
    }

    public function registerFields(): void
    {
        if (!function_exists('acf_add_local_field')) {
            return;
        }

        foreach (self::FIELDS as $name => $definition) {
            $field = [
                'key' => $definition['key'],
                'label' => __($definition['label'], 'pitea-customisation'),
                'name' => $name,
                'type' => $definition['type'],
                'instructions' => __($definition['instructions'], 'pitea-customisation'),
                'required' => 0,
                'conditional_logic' => [
                    [
                        [
                            'field' => self::DISPLAY_AS_FIELD_KEY,
                            'operator' => '==',
                            'value' => 'card',
                        ],
                        [
                            'field' => self::EYEBROW_TEXT_FIELD_KEY,
                            'operator' => '!=empty',
                        ],
                    ],
                ],
                'wrapper' => ['width' => '50', 'class' => '', 'id' => ''],
                'default_value' => '',
                'parent' => self::MANUAL_INPUTS_REPEATER_KEY,
            ];

            if ($definition['type'] === 'color_picker') {
                $field['enable_opacity'] = 1;
                $field['return_format'] = 'string';
            }

            acf_add_local_field($field);
        }

        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group([
            'key' => 'group_pitea_mi_accordion',
            'title' => '',
            'fields' => [
                [
                    'key' => self::ACCORDION_HEADER_BG_FIELD_KEY,
                    'label' => __('Accordion header background', 'pitea-customisation'),
                    'name' => 'manual_input_accordion_header_bg',
                    'type' => 'color_picker',
                    'instructions' => __(
                        'Optional. Same color for every accordion row when display is Accordion. Text and icon color are adjusted automatically for contrast.',
                        'pitea-customisation'
                    ),
                    'required' => 0,
                    'conditional_logic' => [
                        [
                            [
                                'field' => self::DISPLAY_AS_FIELD_KEY,
                                'operator' => '==',
                                'value' => 'accordion',
                            ],
                        ],
                    ],
                    'wrapper' => ['width' => '', 'class' => '', 'id' => ''],
                    'enable_opacity' => 1,
                    'return_format' => 'string',
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'mod-manualinput',
                    ],
                ],
            ],
            'menu_order' => 1000,
        ]);
    }

    /**
     * Inject `disableLayoutShift` into the ManualInput card view data.
     *
     * Core removed this in its data() method; we restore it here so our
     * card.blade.php override can pass `containerAware` to the Card component.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function injectDisableLayoutShift(array $data): array
    {
        if (empty($data['manualInputs']) || !is_array($data['manualInputs'])) {
            return $data;
        }

        // disable_resize_layout_shift is a module-level field, not a per-item field.
        // $data['ID'] is the module CPT post ID when available, or a uniqid string
        // for Gutenberg blocks rendered without a resolved post ID. Fall back to
        // calling get_field() without a post ID so ACF uses its own block context.
        $postId = $data['ID'] ?? null;
        $disableLayoutShift = is_numeric($postId)
            ? (bool) get_field('disable_resize_layout_shift', (int) $postId)
            : (bool) get_field('disable_resize_layout_shift');

        if (!$disableLayoutShift) {
            return $data;
        }

        foreach ($data['manualInputs'] as &$input) {
            if (is_object($input)) {
                $input = (array) $input;
            }
            if (!is_array($input)) {
                continue;
            }
            if (!isset($input['attributeList']) || !is_array($input['attributeList'])) {
                $input['attributeList'] = [];
            }
            $input['attributeList']['data-disable-layout-shift'] = 'true';
        }
        unset($input);

        return $data;
    }

    /**
     * acf_add_local_field() appends repeater subfields at the end; move our color fields directly after Eyebrow.
     *
     * @param array<string, mixed>|false $field
     * @return array<string, mixed>|false
     */
    public function orderEyebrowColorFieldsAfterEyebrow(array|false $field): array|false
    {
        if ($field === false || !is_array($field)) {
            return $field;
        }

        if (empty($field['sub_fields']) || !is_array($field['sub_fields'])) {
            return $field;
        }

        $ourKeys = [
            self::FIELDS['manual_input_eyebrow_background']['key'],
            self::FIELDS['manual_input_eyebrow_text_color']['key'],
        ];

        $oursByKey = [];
        $rest = [];
        foreach ($field['sub_fields'] as $sub) {
            $key = $sub['key'] ?? '';
            if (in_array($key, $ourKeys, true)) {
                $oursByKey[$key] = $sub;
            } else {
                $rest[] = $sub;
            }
        }

        if (count($oursByKey) !== count($ourKeys)) {
            return $field;
        }

        $ordered = [];
        foreach ($rest as $sub) {
            $ordered[] = $sub;
            if (($sub['key'] ?? '') === self::EYEBROW_TEXT_FIELD_KEY) {
                foreach ($ourKeys as $k) {
                    $ordered[] = $oursByKey[$k];
                }
            }
        }

        $field['sub_fields'] = $ordered;
        return $field;
    }

    /**
     * Merge CSS variables onto each manual input wrapper so styling works for blocks (no module id class)
     * and classic modules (same markup: @element + #item-{moduleId}-{index}).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function applyEyebrowStylesToItems(array $data): array
    {
        if (empty($data['manualInputs']) || !is_array($data['manualInputs'])) {
            return $data;
        }

        foreach ($data['manualInputs'] as &$input) {
            if (is_object($input)) {
                $input = (array) $input;
            }
            if (!is_array($input)) {
                continue;
            }

            $eyebrow = isset($input['eyebrow']) ? trim((string) $input['eyebrow']) : '';
            if ($eyebrow === '') {
                continue;
            }

            $bg = isset($input['manualInputEyebrowBackground']) ? trim((string) $input['manualInputEyebrowBackground']) : '';
            $fg = isset($input['manualInputEyebrowTextColor']) ? trim((string) $input['manualInputEyebrowTextColor']) : '';

            if ($bg === '' && $fg === '') {
                continue;
            }

            $declarations = [];
            if ($bg !== '') {
                $declarations[] = '--c-card--eyebrow-background-color: ' . esc_attr($bg);
            }
            if ($fg !== '') {
                $declarations[] = '--c-card--eyebrow-color: ' . esc_attr($fg);
            }

            $merged = implode('; ', $declarations);
            if ($merged === '') {
                continue;
            }

            if (!isset($input['attributeList']) || !is_array($input['attributeList'])) {
                $input['attributeList'] = [];
            }

            $existingStyle = $input['attributeList']['style'] ?? '';
            if ($existingStyle !== '' && $existingStyle !== null) {
                $input['attributeList']['style'] = $existingStyle . '; ' . $merged;
            } else {
                $input['attributeList']['style'] = $merged;
            }
        }
        unset($input);

        return $data;
    }

    /**
     * Applies module-level accordion header background as CSS variables.
     *
     * Gutenberg blocks store the field on the block, not post meta, so this
     * reads `$data` / `$data['blockData']` first. Item `style` is overwritten
     * by Accordion__item::init(); the vars are therefore also stashed for the
     * wrapping Card and Accordion components.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function applyAccordionHeaderBgToItems(array $data): array
    {
        self::$pendingAccordionStyle = null;

        if (empty($data['manualInputs']) || !is_array($data['manualInputs'])) {
            return $data;
        }

        $color = $this->resolveAccordionHeaderBg($data);
        if ($color === '') {
            return $data;
        }

        $fg = $this->getContrastTextColor($color);
        $declaration = implode('; ', [
            '--mi-accordion-button-bg: ' . esc_attr($color),
            '--mi-accordion-button-fg: ' . esc_attr($fg),
            '--c-accordion--color--surface-alt: ' . esc_attr($color),
            '--c-accordion--color--surface-contrast: ' . esc_attr($fg),
        ]);

        self::$pendingAccordionStyle = $declaration;

        foreach ($data['manualInputs'] as &$input) {
            if (is_object($input)) {
                $input = (array) $input;
            }
            if (!is_array($input)) {
                continue;
            }

            if (!isset($input['attributeList']) || !is_array($input['attributeList'])) {
                $input['attributeList'] = [];
            }

            $existingStyle = $input['attributeList']['style'] ?? '';
            if ($existingStyle !== '' && $existingStyle !== null) {
                $input['attributeList']['style'] = $existingStyle . '; ' . $declaration;
            } else {
                $input['attributeList']['style'] = $declaration;
            }
        }
        unset($input);

        return $data;
    }

    /**
     * Merge pending accordion header colours onto the wrapping @card.
     *
     * @param array<string, mixed> $data
     * @param object|null $_component
     * @return array<string, mixed>
     */
    public function applyPendingAccordionCardStyles(array $data, ?object $_component = null): array
    {
        if (self::$pendingAccordionStyle === null || self::$pendingAccordionStyle === '') {
            return $data;
        }

        $context = $data['context'] ?? [];
        $contextList = is_array($context) ? $context : [$context];
        if (!in_array(self::ACCORDION_CARD_CONTEXT, $contextList, true)) {
            return $data;
        }

        if (!isset($data['attributeList']) || !is_array($data['attributeList'])) {
            $data['attributeList'] = [];
        }

        $data['attributeList']['style'] = $this->appendInlineStyle(
            $data['attributeList']['style'] ?? '',
            self::$pendingAccordionStyle
        );

        return $data;
    }

    /**
     * Merge pending accordion header colours onto the Accordion wrapper.
     *
     * Accordion::init() replaces `style` with heading-count; this filter runs
     * afterwards in getAttribute().
     *
     * @param mixed $attribute Attribute list (array) or compiled attribute string.
     * @return mixed
     */
    public function mergePendingAccordionAttributes(mixed $attribute): mixed
    {
        if (!is_array($attribute)) {
            return $attribute;
        }

        if (self::$pendingAccordionStyle === null || self::$pendingAccordionStyle === '') {
            return $attribute;
        }

        $attribute['style'] = $this->appendInlineStyle(
            $attribute['style'] ?? '',
            self::$pendingAccordionStyle
        );
        self::$pendingAccordionStyle = null;

        return $attribute;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveAccordionHeaderBg(array $data): string
    {
        $blockData = [];
        if (isset($data['blockData']['data']) && is_array($data['blockData']['data'])) {
            $blockData = $data['blockData']['data'];
        }

        $candidates = [
            $data['manualInputAccordionHeaderBg'] ?? null,
            $data['manual_input_accordion_header_bg'] ?? null,
            $blockData['manual_input_accordion_header_bg'] ?? null,
            $blockData[self::ACCORDION_HEADER_BG_FIELD_KEY] ?? null,
        ];

        $postId = $data['ID'] ?? null;
        if (is_numeric($postId) && function_exists('get_field')) {
            $candidates[] = get_field('manual_input_accordion_header_bg', (int) $postId);
            $candidates[] = get_field(self::ACCORDION_HEADER_BG_FIELD_KEY, (int) $postId);
        }

        if (function_exists('get_field')) {
            $candidates[] = get_field('manual_input_accordion_header_bg');
        }

        foreach ($candidates as $value) {
            $color = $this->normalizeCssColor($value);
            if ($color !== '') {
                return $color;
            }
        }

        return '';
    }

    /**
     * @param mixed $value
     */
    private function normalizeCssColor(mixed $value): string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return '';
        }

        $value = trim((string) $value);
        if ($value === '' || strcasecmp($value, 'null') === 0) {
            return '';
        }

        if (preg_match('/#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})\b/', $value, $matches)) {
            return $matches[0];
        }

        return $value;
    }

    private function appendInlineStyle(mixed $existing, string $declaration): string
    {
        $existing = is_string($existing) ? trim($existing) : '';
        if ($existing === '') {
            return $declaration;
        }

        return rtrim($existing, ';') . '; ' . $declaration;
    }

    /**
     * Pick black or white text for a given background (WCAG relative luminance).
     *
     * @param string $backgroundColor Hex, rgb(), or rgba()
     * @return string
     */
    private function getContrastTextColor(string $backgroundColor): string
    {
        $rgb = $this->parseColorToRgb($backgroundColor);
        if ($rgb === null) {
            return 'black';
        }

        $channel = static function (int $value): float {
            $c = $value / 255;
            return $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
        };

        $luminance = 0.2126 * $channel($rgb['r']) + 0.7152 * $channel($rgb['g']) + 0.0722 * $channel($rgb['b']);

        return $luminance > 0.5 ? 'black' : 'white';
    }

    /**
     * @param string $color
     * @return array{r: int, g: int, b: int}|null
     */
    private function parseColorToRgb(string $color): ?array
    {
        $color = trim($color);

        if (preg_match('/^#([a-f0-9]{3}|[a-f0-9]{6})$/i', $color, $matches)) {
            $hex = $matches[1];
            if (strlen($hex) === 3) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            }

            return [
                'r' => hexdec(substr($hex, 0, 2)),
                'g' => hexdec(substr($hex, 2, 2)),
                'b' => hexdec(substr($hex, 4, 2)),
            ];
        }

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
