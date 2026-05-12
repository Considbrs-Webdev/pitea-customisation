<?php

namespace PiteaCustomisation\AcfFields;

class PageInternalDesignationFields
{
    public function __construct()
    {
        add_action('acf/init', [$this, 'addFieldGroup'], 20);
    }

    /**
     * Optional internal label shown in Nested Pages instead of (or prefixed to) the public title.
     */
    public function addFieldGroup(): void
    {
        acf_add_local_field_group([
            'key' => 'group_pitea_page_internal_designation',
            'title' => __('Page internal designation', 'pitea-customisation'),
            'fields' => [
                [
                    'key' => 'field_pitea_internal_page_designation',
                    'label' => __('Internal designation', 'pitea-customisation'),
                    'name' => 'pitea_internal_page_designation',
                    'type' => 'text',
                    'instructions' => __(
                        'Optional. Used in the Nested Pages admin view as Internal (Page title); leave empty to use the normal page title.',
                        'pitea-customisation'
                    ),
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => [
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ],
                    'maxlength' => '',
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
