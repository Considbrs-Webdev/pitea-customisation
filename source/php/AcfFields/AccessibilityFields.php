<?php

namespace PiteaCustomisation\AcfFields;

class AccessibilityFields
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
            'key' => 'group_pitea_accessibility_buttons',
            'title' => __('Accessibility Buttons Settings', 'pitea-customisation'),
            'fields' => [
                [
                    'key' => 'field_show_accessibility_buttons',
                    'label' => __('Show accessibility buttons', 'pitea-customisation'),
                    'name' => 'show_accessibility_buttons',
                    'type' => 'true_false',
                    'instructions' => __('Display accessibility buttons (ReadSpeaker and Print) on this page.', 'pitea-customisation'),
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
                        'value' => 'page',
                    ],
                ],
            ],
            'menu_order' => 0,
            'position' => 'side',
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
