<?php

namespace PiteaCustomisation\Customisations\Modules;

/**
 * Adds per-card eyebrow color fields to the Manual Input module (Modularity + Gutenberg)
 * and applies them via CSS variables on each item wrapper (stable #id from core).
 */
class ManualInput
{
    private const MANUAL_INPUTS_REPEATER_KEY = 'field_64ff22b2d91b7';

    private const DISPLAY_AS_FIELD_KEY = 'field_6752f959acfda';

    /** Municipio “Eyebrow” text subfield (manual_inputs repeater). */
    private const EYEBROW_TEXT_FIELD_KEY = 'field_6945264b7d66e';

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
        add_filter('Modularity/Display/mod-manualinput/viewData', [$this, 'injectDisableLayoutShift'], 5, 1);
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
                $declarations[] = '--c-card-eyebrow-background-color: ' . esc_attr($bg);
            }
            if ($fg !== '') {
                $declarations[] = '--c-card-eyebrow-color: ' . esc_attr($fg);
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
}
