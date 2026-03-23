<?php

namespace PiteaCustomisation\Customisations\Modules;

class QuickLinks
{
    private const QUICK_LINKS_FIELD_GROUP = 'group_69445f2589f85';

    private const FIELDS = [
        'quick_links_enable_hover_styling' => [
            'type' => 'true_false',
            'label' => 'Enable hover background',
            'instructions' => 'Choose a custom hover overlay color for the quick link cards.',
        ],
        'quick_links_hover_background' => [
            'type' => 'color_picker',
            'label' => 'Hover background',
            'instructions' => 'Sets the hover overlay color (CSS variable --quick-links-hover-color).',
        ],
    ];

    public function __construct()
    {
        add_action('acf/init', [$this, 'registerFields'], 20);
        add_filter('Modularity/Display/mod-quick-links/viewData', [$this, 'appendHoverRootStyle'], 10, 1);
    }

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
                'parent' => self::QUICK_LINKS_FIELD_GROUP,
            ];

            if ($definition['type'] === 'color_picker') {
                $field['enable_opacity'] = 1;
                $field['return_format'] = 'string';
            } elseif ($definition['type'] === 'true_false') {
                $field['message'] = __('Enable', 'pitea-customisation');
                $field['ui'] = 1;
                $field['ui_on_text'] = '';
                $field['ui_off_text'] = '';
                $field['default_value'] = 0;
                $field['menu_order'] = 0;
            }

            if ($name !== 'quick_links_enable_hover_styling') {
                $field['conditional_logic'] = [
                    [
                        [
                            'field' => 'field_quick_links_enable_hover_styling',
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
     * Sets inline CSS variables on the module root so each block / module instance is scoped
     * without relying on .modularity-mod-{postType}-{id} (blocks do not get a unique id).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function appendHoverRootStyle(array $data): array
    {
        if (empty($data['quick_links_enable_hover_styling'])) {
            return $data;
        }

        $color = $data['quick_links_hover_background'] ?? '';
        if (!is_string($color) || $color === '') {
            return $data;
        }

        $data['quickLinksRootStyle'] = sprintf(
            '--quick-links-hover-color: %s;',
            esc_attr($color)
        );

        return $data;
    }
}
