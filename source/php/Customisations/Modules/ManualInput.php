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
        add_filter('Modularity/Display/mod-manualinput/viewData', [$this, 'applyEyebrowStylesToItems'], 10, 1);
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
