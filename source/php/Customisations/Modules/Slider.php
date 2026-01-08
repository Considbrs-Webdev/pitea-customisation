<?php

namespace PiteaCustomisation\Customisations\Modules;

class Slider
{
    private const SLIDER_MODULE_FIELD_GROUP = 'group_56a5e99108991';

    /**
     * Track if any slider on the page has stepper enabled
     */
    private static bool $hasStepperOnPage = false;

    /**
     * Field definitions - single source of truth
     */
    private const FIELDS = [
        'slider_show_stepper' => [
            'type' => 'true_false',
            'label' => 'Show Stepper',
            'instructions' => 'Show the stepper for the slider.',
            'css_property' => null,
            'unit' => '',
        ],
    ];

    public function __construct()
    {
        add_action('acf/init', [$this, 'registerFields'], 20);
        add_filter('Modularity/Display/mod-slider/viewData', [$this, 'checkStepperEnabled']);
        add_filter('ComponentLibrary/Component/Slider/Class', [$this, 'addStepperClass'], 10, 2);
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
                'parent' => self::SLIDER_MODULE_FIELD_GROUP,
            ];

            // Type-specific options
            if ($definition['type'] === 'true_false') {
                $field['message'] = __('Enable', 'pitea-customisation');
                $field['ui'] = 1;
                $field['ui_on_text'] = '';
                $field['ui_off_text'] = '';
                $field['default_value'] = 0;
                $field['menu_order'] = 0;
            }

            // Add conditional logic for all fields except the toggle itself
            if ($name !== 'slider_module_enable_styling') {
                $field['conditional_logic'] = [
                    [
                        [
                            'field' => 'field_slider_module_enable_styling',
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
     * Check if slider module has stepper enabled
     * @param array $data Module view data (contains ACF field values directly)
     * @return array
     */
    public function checkStepperEnabled(array $data): array
    {
        // Check if slider_show_stepper field exists and is enabled
        // The ACF field value is already in the data array
        if (isset($data['slider_show_stepper']) && !empty($data['slider_show_stepper'])) {
            self::$hasStepperOnPage = true;
        }
        return $data;
    }

    /**
     * Add stepper helper class to slider component
     *
     * @param array|string $class Current class list
     * @param array $context Component context
     * @return array
     */
    public function addStepperClass($class, $context): array
    {
        // Ensure $class is an array
        if (!is_array($class)) {
            $class = explode(' ', (string) $class);
            $class = array_filter($class);
        }

        if (self::$hasStepperOnPage) {
            $class[] = 'c-slider--stepper-visible';
        }

        return $class;
    }
}
