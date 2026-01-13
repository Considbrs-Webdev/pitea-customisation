<?php

namespace PiteaCustomisation\Customisations;

class ColorPicker
{
    /**
     * Initialize the field replacer
     */
    public function __construct()
    {

        add_action('acf/include_field_types', [$this, 'registerAcfFieldType']);
        add_filter('acf/prepare_field/type=color_picker', [$this, 'convertToColorPickerField']);
    }

    /**
     * Register the custom ACF color picker field type
     *
     * @return void
     */
    public function registerAcfFieldType(): void
    {
        require_once dirname(__DIR__) . '/AcfFields/ColorPickerField.php';
        acf_register_field_type('PiteaCustomisation\AcfFields\ColorPickerField');
    }


    /**
     * Convert color picker field to custom design system color picker field type
     *
     * @param array $field The ACF field array
     * @return array Modified field array
     */
    public function convertToColorPickerField(array $field): array
    {
        $field['type']       = 'pitea_color_picker';
        $field['allow_null'] = 1;

        return $field;
    }
}
