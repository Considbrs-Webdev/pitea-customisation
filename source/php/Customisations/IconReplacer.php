<?php

namespace PiteaCustomisation\Customisations;

class IconReplacer
{
    /**
     * Initialize the field replacer
     */
    public function __construct()
    {
        add_action('wp_head', function () {
            ?>
            <script src="https://kit.fontawesome.com/be6ad42a19.js" crossorigin="anonymous"></script>
            <?php
        });

        add_filter('acf/prepare_field/type=icon', [$this, 'convertToSelectField']);

        add_filter('ComponentLibrary/Component/Icon/Data', function ($data) {
            $data['componentElement'] = 'i';

            $data['classList'][] = $data['icon'];

            return $data;
        }, 500, 1);

        add_filter('ComponentLibrary/Component/Icon/Class', function ($classes) {
            $hasFaClass = false;
            foreach ($classes as $class) {
                if (strpos($class, 'fa-') === 0) {
                    $hasFaClass = true;
                    break;
                }
            }

            if (!$hasFaClass) {
                return $classes;
            }

            $classes = array_values(array_filter(
                $classes,
                function ($class) {
                    return strpos($class, 'material-symbols') !== 0;
                }
            ));

            return $classes;
        }, 600, 1);
    }

    /**
     * Convert icon field to select field
     *
     * @param array $field The ACF field array
     * @return array Modified field array
     */
    public function convertToSelectField(array $field): array
    {
        $icons = $this->getIconChoices();

        $field['type']       = 'select';
        $field['choices']    = $this->buildChoices($icons);
        $field['allow_null'] = 1;
        $field['ui']         = 1;
        $field['ajax']       = 0;

        return $field;
    }

    /**
     * Build choices array from icons
     *
     * @param array $icons List of icon names
     * @return array Associative array of choices
     */
    protected function buildChoices(array $icons): array
    {
        $choices = ['' => __('— Select Icon —', 'pitea-customisation')];

        foreach ($icons as $key => $value) {
            $choices[$key] = $value;
        }

        return $choices;
    }

    /**
     * Get available icon choices
     *
     * @return array List of icon names
     */
    protected function getIconChoices(): array
    {
        $icons = [
            'wifi' => 'WiFi',
            'fa-solid fa-brush' => 'Brush',
            'fa-solid fa-code' => 'Code',
        ];

        return apply_filters('pitea_customisation/icons', $icons);
    }
}
