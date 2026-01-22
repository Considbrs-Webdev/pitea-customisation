<?php

namespace PiteaCustomisation\AcfFields;

class ShareButtonFields
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
            'key' => 'group_pitea_share_button_placement',
            'title' => __('Share Button Settings', 'pitea-customisation'),
            'fields' => [
                [
                    'key' => 'field_share_button_placement',
                    'label' => __('Share Button Placement', 'pitea-customisation'),
                    'name' => 'share_button_placement',
                    'type' => 'select',
                    'instructions' => __('Choose where the share button should appear on this page.', 'pitea-customisation'),
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => [
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ],
                    'choices' => [
                        'none' => __('None', 'pitea-customisation'),
                        'bottom' => __('Bottom of content', 'pitea-customisation'),
                    ],
                    'default_value' => 'none',
                    'allow_null' => 0,
                    'multiple' => 0,
                    'ui' => 1,
                    'ajax' => 0,
                    'return_format' => 'value',
                    'placeholder' => '',
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
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'post',
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
