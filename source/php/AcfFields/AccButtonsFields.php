<?php

namespace PiteaCustomisation\AcfFields;

class AccButtonsFields
{
    public function __construct()
    {
        add_action('acf/init', [$this, 'addFieldGroup'], 20);
    }

    /**
     * Add ACF field group
     */
    public function addFieldGroup(): void
    {
        acf_add_local_field_group([
            'key' => 'group_pitea_acc_buttons',
            'title' => __('Accessibility Buttons Module Settings', 'pitea-customisation'),
            'fields' => [
                [
                    'key' => 'field_acc_buttons_automatic_mobile_insertion',
                    'label' => __('Show at top of page on mobile', 'pitea-customisation'),
                    'name' => 'automatic_mobile_insertion',
                    'type' => 'true_false',
                    'instructions' => __('On narrow screens, sidebars stack below the main content, which pushes this module to the bottom of the page. When enabled, a duplicate of the buttons is moved to the top of the main content area on mobile, while this module keeps its normal sidebar position on desktop.', 'pitea-customisation'),
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => [
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ],
                    'message' => '',
                    'default_value' => 1,
                    'ui' => 1,
                    'ui_on_text' => '',
                    'ui_off_text' => '',
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'mod-acc-buttons',
                    ],
                ],
                [
                    [
                        'param' => 'block',
                        'operator' => '==',
                        'value' => 'acf/acc-buttons',
                    ],
                ],
            ],
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => '',
            'active' => true,
            'description' => '',
            'show_in_rest' => 0,
        ]);
    }
}
