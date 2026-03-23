<?php

namespace PiteaCustomisation\Customisations;

use PiteaCustomisation\Customisations\ColorPicker\UserGroupPaletteAccess;

class ColorPicker
{
    /**
     * Initialize the field replacer
     */
    public function __construct()
    {

        add_action('acf/include_field_types', [$this, 'registerAcfFieldType']);
        add_filter('acf/prepare_field/type=color_picker', [$this, 'convertToColorPickerField']);
        add_filter('acf/prepare_field/type=pitea_color_picker', [$this, 'applyUserGroupColorRules']);
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

    /**
     * Apply user-group palette settings to allow_custom (union with ACF field setting).
     *
     * @param array<string, mixed> $field
     * @return array<string, mixed>
     */
    public function applyUserGroupColorRules(array $field): array
    {
        $fieldAllows = !empty($field['allow_custom'] ?? 1);
        $effective   = UserGroupPaletteAccess::getEffectiveAllowCustom(
            get_current_user_id(),
            $fieldAllows
        );

        $field['allow_custom'] = $effective ? 1 : 0;

        return $field;
    }
}
