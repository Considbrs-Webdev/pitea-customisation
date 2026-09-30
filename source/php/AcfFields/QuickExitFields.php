<?php

declare(strict_types=1);

namespace PiteaCustomisation\AcfFields;

/**
 * ACF fields for the quick-exit Modularity module.
 */
class QuickExitFields
{
    public function __construct()
    {
        add_action('acf/init', [$this, 'addFieldGroup'], 20);
    }

    /**
     * Register the field group on the module post type and its block.
     */
    public function addFieldGroup(): void
    {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group([
            'key' => 'group_pitea_quick_exit',
            'title' => __('Quick exit module settings', 'pitea-customisation'),
            'fields' => [
                [
                    'key' => 'field_quick_exit_display',
                    'label' => __('Display', 'pitea-customisation'),
                    'name' => 'quick_exit_display',
                    'type' => 'select',
                    'instructions' => __('Sticky stays in the corner while the page scrolls. Inline sits where the module is placed.', 'pitea-customisation'),
                    'required' => 0,
                    'choices' => [
                        'sticky' => __('Sticky (follows the page)', 'pitea-customisation'),
                        'inline' => __('Inline (where placed)', 'pitea-customisation'),
                        'both' => __('Both', 'pitea-customisation'),
                    ],
                    'default_value' => 'sticky',
                    'allow_null' => 0,
                    'multiple' => 0,
                    'ui' => 1,
                    'return_format' => 'value',
                ],
                [
                    'key' => 'field_quick_exit_label',
                    'label' => __('Button text', 'pitea-customisation'),
                    'name' => 'quick_exit_label',
                    'type' => 'text',
                    'instructions' => __('Leave empty to use the default from Settings → Piteå kommun → Quick exit.', 'pitea-customisation'),
                    'required' => 0,
                ],
                [
                    'key' => 'field_quick_exit_url',
                    'label' => __('Destination', 'pitea-customisation'),
                    'name' => 'quick_exit_url',
                    'type' => 'url',
                    'instructions' => __('Leave empty to use the default destination.', 'pitea-customisation'),
                    'required' => 0,
                ],
                [
                    'key' => 'field_quick_exit_escape',
                    'label' => __('Escape shortcut', 'pitea-customisation'),
                    'name' => 'quick_exit_escape',
                    'type' => 'true_false',
                    'instructions' => __('Also leave when Esc is pressed three times quickly.', 'pitea-customisation'),
                    'required' => 0,
                    'default_value' => 1,
                    'ui' => 1,
                ],
                [
                    'key' => 'field_quick_exit_info_url',
                    'label' => __('Info link', 'pitea-customisation'),
                    'name' => 'quick_exit_info_url',
                    'type' => 'url',
                    'instructions' => __('Optional link shown under the inline button, for example a page that explains the button.', 'pitea-customisation'),
                    'required' => 0,
                ],
                [
                    'key' => 'field_quick_exit_info_text',
                    'label' => __('Info link text', 'pitea-customisation'),
                    'name' => 'quick_exit_info_text',
                    'type' => 'text',
                    'instructions' => __('Leave empty to use “Läs om Lämna sidan snabbt”.', 'pitea-customisation'),
                    'required' => 0,
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'mod-quick-exit',
                    ],
                ],
                [
                    [
                        'param' => 'block',
                        'operator' => '==',
                        'value' => 'acf/quick-exit',
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
