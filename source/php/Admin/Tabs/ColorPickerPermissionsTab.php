<?php

declare(strict_types=1);

namespace PiteaCustomisation\Admin\Tabs;

use PiteaCustomisation\Admin\SettingsTabInterface;
use PiteaCustomisation\Customisations\ColorPicker\UserGroupPaletteAccess;
use PiteaCustomisation\Helpers\DesignSystemColors;

/**
 * Maps user groups to allowed design-system palette groups and custom hex usage.
 */
class ColorPickerPermissionsTab implements SettingsTabInterface
{
    private const OPTION_GROUP = 'pitea_customisation_color_picker_permissions_tab';

    private const GROUP_MAIN = 'pitea_customisation_group_color_picker_permissions';

    // -------------------------------------------------------------------------
    // SettingsTabInterface
    // -------------------------------------------------------------------------

    public function getId(): string
    {
        return 'color-picker-permissions';
    }

    public function getTitle(): string
    {
        return __('Color picker', 'pitea-customisation');
    }

    public function getOptionGroup(): string
    {
        return self::OPTION_GROUP;
    }

    // -------------------------------------------------------------------------
    // Registration
    // -------------------------------------------------------------------------

    public function register(): void
    {
        register_setting(
            self::OPTION_GROUP,
            UserGroupPaletteAccess::OPTION_KEY,
            [
                'type'         => 'string',
                'default'      => '[]',
                'show_in_rest' => false,
            ]
        );

        add_settings_section(
            'pitea_customisation_color_picker_permissions',
            __('User group color access', 'pitea-customisation'),
            function (): void {
                echo '<p class="pitea-settings__section-desc">' . esc_html__(
                    'Limit which palette groups each user group may use in the design system color picker, and whether they may enter a custom hex color. Users without a matching rule here keep full access. Users in several groups get the union of allowed palette groups; custom color is allowed if any matching rule allows it.',
                    'pitea-customisation'
                ) . '</p>';
            },
            self::GROUP_MAIN
        );

        add_settings_field(
            UserGroupPaletteAccess::OPTION_KEY,
            __('Rules', 'pitea-customisation'),
            [$this, 'renderRulesField'],
            self::GROUP_MAIN,
            'pitea_customisation_color_picker_permissions'
        );
    }

    // -------------------------------------------------------------------------
    // save
    // -------------------------------------------------------------------------

    public function save(array $data): true|\WP_Error
    {
        $key     = UserGroupPaletteAccess::OPTION_KEY;
        $rawRows = isset($data[$key]) && is_array($data[$key]) ? $data[$key] : [];

        $validGroupNames = array_flip(array_keys(DesignSystemColors::getGroupedColors()));

        $byUserGroup = [];
        foreach ($rawRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $groupId = isset($row['user_group_id']) ? absint($row['user_group_id']) : 0;
            if ($groupId === 0) {
                continue;
            }

            $allowed = [];
            if (isset($row['allowed_palette_groups']) && is_array($row['allowed_palette_groups'])) {
                foreach ($row['allowed_palette_groups'] as $g) {
                    if (!is_string($g) || $g === '') {
                        continue;
                    }
                    if (isset($validGroupNames[$g])) {
                        $allowed[] = $g;
                    }
                }
            }

            $byUserGroup[$groupId] = [
                'user_group_id'          => $groupId,
                'allowed_palette_groups' => $allowed,
                'allow_custom'           => !empty($row['allow_custom']),
            ];
        }

        update_option($key, wp_json_encode(array_values($byUserGroup)));

        return true;
    }

    // -------------------------------------------------------------------------
    // Rendering
    // -------------------------------------------------------------------------

    public function render(): void
    {
        $this->renderGroup(__('Color picker permissions', 'pitea-customisation'), self::GROUP_MAIN);
    }

    private function renderGroup(string $title, string $groupPageSlug): void
    {
        ?>
        <div class="pitea-settings__card">
            <div class="pitea-settings__card-header">
                <h2 class="pitea-settings__card-title"><?php echo esc_html($title); ?></h2>
            </div>
            <div class="pitea-settings__card-body">
                <?php do_settings_sections($groupPageSlug); ?>
            </div>
        </div>
        <?php
    }

    public function renderRulesField(): void
    {
        $paletteGroups = DesignSystemColors::getGroupedColors();
        $groupNames    = array_keys($paletteGroups);

        $raw  = (string) get_option(UserGroupPaletteAccess::OPTION_KEY, '[]');
        $rows = json_decode($raw, true);
        if (!is_array($rows)) {
            $rows = [];
        }

        if ($groupNames === []) {
            echo '<p class="notice notice-warning inline"><strong>' . esc_html__(
                'No design system palette groups were found. Check that variables.scss is available or Municipio color helpers are loaded.',
                'pitea-customisation'
            ) . '</strong></p>';
        }
        ?>
        <div class="pitea-settings__repeater" data-repeater="color-picker-permissions">
            <div class="pitea-settings__repeater-rows" id="color-picker-permissions-rows">
                <?php foreach ($rows as $i => $row) : ?>
                    <?php
                    $selected = isset($row['allowed_palette_groups']) && is_array($row['allowed_palette_groups'])
                        ? $row['allowed_palette_groups']
                        : [];
                    $this->renderRuleRow(
                        $i,
                        (int) ($row['user_group_id'] ?? 0),
                        $selected,
                        !empty($row['allow_custom']),
                        $groupNames
                    );
                    ?>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button pitea-settings__repeater-add">
                <?php esc_html_e('+ Add rule', 'pitea-customisation'); ?>
            </button>
            <template id="color-picker-permissions-row-template">
                <?php $this->renderRuleRow('{{INDEX}}', 0, [], false, $groupNames); ?>
            </template>
        </div>
        <?php
    }

    /**
     * @param string|int $index
     * @param list<string> $selectedGroups
     * @param list<string> $allGroupNames
     */
    private function renderRuleRow(
        string|int $index,
        int $selectedGroupId,
        array $selectedGroups,
        bool $allowCustom,
        array $allGroupNames
    ): void {
        $namePrefix = UserGroupPaletteAccess::OPTION_KEY . '[' . $index . ']';
        $terms      = get_terms(['taxonomy' => 'user_group', 'hide_empty' => false]);
        if (is_wp_error($terms)) {
            $terms = [];
        }

        $selectedSet = array_flip($selectedGroups);
        ?>
        <div class="pitea-settings__repeater-row pitea-settings__repeater-row--color-picker-rule">
            <div class="pitea-settings__repeater-fields pitea-settings__repeater-fields--stack">
                <label class="pitea-settings__field-label">
                    <span><?php esc_html_e('User group', 'pitea-customisation'); ?></span>
                    <select name="<?php echo esc_attr($namePrefix); ?>[user_group_id]" class="pitea-settings__input">
                        <option value="0"><?php esc_html_e('— Select user group —', 'pitea-customisation'); ?></option>
                        <?php foreach ($terms as $term) : ?>
                            <option
                                value="<?php echo esc_attr((string) $term->term_id); ?>"
                                <?php selected($selectedGroupId, $term->term_id); ?>
                            >
                                <?php echo esc_html($term->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <?php if ($allGroupNames !== []) : ?>
                    <fieldset class="pitea-settings__palette-fieldset">
                        <legend class="pitea-settings__field-legend"><?php esc_html_e('Allowed palette groups', 'pitea-customisation'); ?></legend>
                        <div class="pitea-settings__palette-checkboxes">
                            <?php foreach ($allGroupNames as $gName) : ?>
                                <label class="pitea-settings__checkbox-label">
                                    <input
                                        type="checkbox"
                                        name="<?php echo esc_attr($namePrefix); ?>[allowed_palette_groups][]"
                                        value="<?php echo esc_attr($gName); ?>"
                                        <?php checked(isset($selectedSet[$gName])); ?>
                                    />
                                    <?php echo esc_html($gName); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                <?php endif; ?>

                <label class="pitea-settings__checkbox-label">
                    <input
                        type="checkbox"
                        name="<?php echo esc_attr($namePrefix); ?>[allow_custom]"
                        value="1"
                        <?php checked($allowCustom); ?>
                    />
                    <?php esc_html_e('Allow custom hex color', 'pitea-customisation'); ?>
                </label>
            </div>
            <button type="button" class="button pitea-settings__repeater-remove">
                <?php esc_html_e('Remove', 'pitea-customisation'); ?>
            </button>
        </div>
        <?php
    }
}
