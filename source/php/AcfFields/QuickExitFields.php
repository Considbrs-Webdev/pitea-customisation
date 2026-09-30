<?php

declare(strict_types=1);

namespace PiteaCustomisation\AcfFields;

/**
 * ACF fields for the quick-exit Modularity module. Content is configured site-wide in Settings.
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
                    'instructions' => __('Sticky stays at the edge of the screen under the header while the page scrolls. Inline sits where the module is placed.', 'pitea-customisation'),
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
                    'key' => 'field_quick_exit_show_read_more',
                    'label' => __('Show read-more link', 'pitea-customisation'),
                    'name' => 'quick_exit_show_read_more',
                    'type' => 'true_false',
                    'instructions' => __('Shows the link to the page that explains the button. The page is set under Settings → Piteå kommun → Quick exit.', 'pitea-customisation'),
                    'required' => 0,
                    'default_value' => 1,
                    'ui' => 1,
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
