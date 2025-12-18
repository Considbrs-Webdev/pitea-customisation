<?php

namespace PiteaCustomisation\Customisations;

class FontAwesome
{
    /**
     * Initialize the field replacer
     */
    public function __construct()
    {
        add_action('wp_head', [$this, 'enqueueFontAwesomeKit']);
        add_action('acf/include_field_types', [$this, 'registerAcfFieldType']);
        add_filter('acf/prepare_field/type=icon', [$this, 'convertToFontAwesomeField']);
        add_filter('ComponentLibrary/Component/Icon/Data', [$this, 'modifyIconData'], 500, 1);
        add_filter('ComponentLibrary/Component/Icon/Class', [$this, 'filterIconClasses'], 600, 1);
    }

    /**
     * Register the custom ACF field type
     *
     * @return void
     */
    public function registerAcfFieldType(): void
    {
        require_once dirname(__DIR__) . '/AcfFields/FontAwesomeIconField.php';
        acf_register_field_type('PiteaCustomisation\AcfFields\FontAwesomeIconField');
    }

    /**
     * Enqueue FontAwesome kit script
     *
     * @return void
     */
    public function enqueueFontAwesomeKit(): void
    {
        ?>
        <script src="https://kit.fontawesome.com/be6ad42a19.js" crossorigin="anonymous"></script>
        <?php
    }

    /**
     * Modify icon component data for FontAwesome
     *
     * @param array $data The icon component data
     * @return array Modified data
     */
    public function modifyIconData(array $data): array
    {
        $data['componentElement'] = 'i';
        
        $icon = explode(' ', $data['icon']);
        $data['classList'] += $icon;

        if (isset($data['icon']) && is_string($data['icon']) && strpos($data['icon'], 'fa-') === 0) {
            $data['icon'] = str_replace(' ', '-', $data['icon']);
        }

        return $data;
    }

    /**
     * Filter icon classes to remove Material Symbols when using FontAwesome
     *
     * @param array $classes The icon classes
     * @return array Filtered classes
     */
    public function filterIconClasses(array $classes): array
    {
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
    }

    /**
     * Convert icon field to FontAwesome field type
     *
     * @param array $field The ACF field array
     * @return array Modified field array
     */
    public function convertToFontAwesomeField(array $field): array
    {
        $jsonPath = dirname(__DIR__, 3) . '/data/fontawesome-icons.json';

        // If no icons file found, don't modify the field
        if (!file_exists($jsonPath)) {
            return $field;
        }

        $field['type']       = 'fontawesome_icon';
        $field['allow_null'] = 1;

        return $field;
    }
}
