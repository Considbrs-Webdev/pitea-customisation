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
        add_filter('ComponentLibrary/Component/Icon/Data', [$this, 'modifyIconData'], 10, 1);
        add_filter('ComponentLibrary/Component/Icon/Class', [$this, 'filterIconClasses'], 10, 1);
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
        if (!isset($data['icon']) || !is_string($data['icon']) || strpos($data['icon'], 'fa-') !== 0) {
            return $data;
        }

        $data['componentElement'] = 'i';
        
        $icon = explode(' ', $data['icon']);
        $data['classList'] += $icon;
        
        $data['icon'] = str_replace(' ', '-', $data['icon']);

        // Remove data-material-symbol attribute for FontAwesome icons
        if (isset($data['attribute']['data-material-symbol'])) {
            unset($data['attribute']['data-material-symbol']);
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
                // Remove material-symbols classes
                if (strpos($class, 'material-symbols') === 0) {
                    return false;
                }
                // Remove c-icon--material classes
                if (strpos($class, 'c-icon--material') === 0) {
                    return false;
                }
                return true;
            }
        ));

        // Replace c-icon--size-* with fa-*
        $classes = array_map(function ($class) {
            if (preg_match('/^c-icon--size-(.+)$/', $class, $matches)) {
                return 'fa-icon-size-' . $matches[1];
            }
            return $class;
        }, $classes);

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
