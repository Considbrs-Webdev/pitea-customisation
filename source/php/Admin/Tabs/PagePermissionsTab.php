<?php

declare(strict_types=1);

namespace PiteaCustomisation\Admin\Tabs;

use PiteaCustomisation\Admin\SettingsTabInterface;

/**
 * Class PagePermissionsTab
 *
 * Settings tab for page-level permissions:
 *   - "Protected pages / Templates": pages non-admins cannot edit/move/delete.
 *   - "Page tree ownership": maps user groups and/or user roles to page trees they can manage.
 */
class PagePermissionsTab implements SettingsTabInterface
{
    private const OPTION_GROUP = 'pitea_customisation_page_permissions_tab';

    public const OPTION_PAGE_PERMISSIONS    = 'pitea_customisation_page_permissions';
    public const OPTION_PAGE_TREE_OWNERSHIP = 'pitea_customisation_user_group_ownership';

    private const GROUP_PROTECTED           = 'pitea_customisation_group_protected_pages';
    private const GROUP_PAGE_TREE_OWNERSHIP = 'pitea_customisation_group_user_group_ownership';

    // -------------------------------------------------------------------------
    // SettingsTabInterface
    // -------------------------------------------------------------------------

    public function getId(): string
    {
        return 'page-permissions';
    }

    public function getTitle(): string
    {
        return __('Page permissions', 'pitea-customisation');
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
        $this->registerProtectedPagesGroup();
        $this->registerPageTreeOwnershipGroup();
    }

    private function registerProtectedPagesGroup(): void
    {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_PAGE_PERMISSIONS,
            [
                'type'         => 'string',
                'default'      => '[]',
                'show_in_rest' => false,
            ]
        );

        add_settings_section(
            'pitea_customisation_protected_pages',
            __('Protected pages / Templates', 'pitea-customisation'),
            function (): void {
                echo '<p class="pitea-settings__section-desc">' . esc_html__(
                    'These pages cannot be edited, moved or deleted by other roles than administrators.',
                    'pitea-customisation'
                ) . '</p>';
            },
            self::GROUP_PROTECTED
        );

        add_settings_field(
            self::OPTION_PAGE_PERMISSIONS,
            __('Protected pages', 'pitea-customisation'),
            [$this, 'renderPagePermissionsField'],
            self::GROUP_PROTECTED,
            'pitea_customisation_protected_pages'
        );
    }

    private function registerPageTreeOwnershipGroup(): void
    {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_PAGE_TREE_OWNERSHIP,
            [
                'type'         => 'string',
                'default'      => '[]',
                'show_in_rest' => false,
            ]
        );

        add_settings_section(
            'pitea_customisation_user_group_ownership',
            __('Page tree ownership', 'pitea-customisation'),
            function (): void {
                echo '<p class="pitea-settings__section-desc">' . esc_html__(
                    'Define which user groups and/or user roles own and manage particular parts of the page tree. Users who do not match an owning rule cannot see those pages or their descendants in the admin.',
                    'pitea-customisation'
                ) . '</p>';
            },
            self::GROUP_PAGE_TREE_OWNERSHIP
        );

        add_settings_field(
            self::OPTION_PAGE_TREE_OWNERSHIP,
            __('Ownership rules', 'pitea-customisation'),
            [$this, 'renderPageTreeOwnershipField'],
            self::GROUP_PAGE_TREE_OWNERSHIP,
            'pitea_customisation_user_group_ownership'
        );
    }

    // -------------------------------------------------------------------------
    // save
    // -------------------------------------------------------------------------

    public function save(array $data): true|\WP_Error
    {
        $this->saveProtectedPages($data);
        $this->savePageTreeOwnership($data);

        return true;
    }

    private function saveProtectedPages(array $data): void
    {
        $rawRows = isset($data[self::OPTION_PAGE_PERMISSIONS]) && is_array($data[self::OPTION_PAGE_PERMISSIONS])
            ? $data[self::OPTION_PAGE_PERMISSIONS]
            : [];

        $permissions = [];
        foreach ($rawRows as $row) {
            $pageId = isset($row['page_id']) ? absint($row['page_id']) : 0;
            if ($pageId === 0) {
                continue;
            }
            $permissions[] = [
                'page_id' => $pageId,
                'inherit' => !empty($row['inherit']),
            ];
        }

        update_option(self::OPTION_PAGE_PERMISSIONS, wp_json_encode($permissions));
    }

    private function savePageTreeOwnership(array $data): void
    {
        $rawRows = isset($data[self::OPTION_PAGE_TREE_OWNERSHIP]) && is_array($data[self::OPTION_PAGE_TREE_OWNERSHIP])
            ? $data[self::OPTION_PAGE_TREE_OWNERSHIP]
            : [];

        $ownership = [];
        foreach ($rawRows as $row) {
            $groupId = isset($row['user_group_id']) ? absint($row['user_group_id']) : 0;
            $role    = isset($row['user_role']) ? sanitize_key((string) $row['user_role']) : '';
            $pageId  = isset($row['page_id']) ? absint($row['page_id']) : 0;
            if (($groupId === 0 && $role === '') || $pageId === 0) {
                continue;
            }
            $ownership[] = [
                'user_group_id' => $groupId,
                'user_role'     => $role,
                'page_id'       => $pageId,
                'inherit'       => !empty($row['inherit']),
            ];
        }

        update_option(self::OPTION_PAGE_TREE_OWNERSHIP, wp_json_encode($ownership));
    }

    // -------------------------------------------------------------------------
    // Rendering
    // -------------------------------------------------------------------------

    public function render(): void
    {
        $this->renderGroup(__('Protected pages / Templates', 'pitea-customisation'), self::GROUP_PROTECTED);
        $this->renderGroup(__('Page tree ownership', 'pitea-customisation'), self::GROUP_PAGE_TREE_OWNERSHIP);
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

    // -------------------------------------------------------------------------
    // Field renderers — protected pages
    // -------------------------------------------------------------------------

    public function renderPagePermissionsField(): void
    {
        $raw  = (string) get_option(self::OPTION_PAGE_PERMISSIONS, '[]');
        $rows = json_decode($raw, true);
        if (!is_array($rows)) {
            $rows = [];
        }
        ?>
        <div class="pitea-settings__repeater" data-repeater="page-permissions">
            <div class="pitea-settings__repeater-rows" id="page-permissions-rows">
                <?php foreach ($rows as $i => $row) : ?>
                    <?php $this->renderPagePermissionRow($i, (int) ($row['page_id'] ?? 0), !empty($row['inherit'])); ?>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button pitea-settings__repeater-add">
                <?php esc_html_e('+ Add page', 'pitea-customisation'); ?>
            </button>
            <template id="page-permissions-row-template">
                <?php $this->renderPagePermissionRow('{{INDEX}}', 0, false); ?>
            </template>
        </div>
        <?php
    }

    private function renderPagePermissionRow(string|int $index, int $selectedPageId, bool $inherit): void
    {
        $namePrefix = self::OPTION_PAGE_PERMISSIONS . '[' . $index . ']';
        ?>
        <div class="pitea-settings__repeater-row">
            <div class="pitea-settings__repeater-fields">
                <?php $this->renderPageDropdown($namePrefix . '[page_id]', $selectedPageId); ?>
                <label class="pitea-settings__checkbox-label">
                    <input
                        type="checkbox"
                        name="<?php echo esc_attr($namePrefix); ?>[inherit]"
                        value="1"
                        <?php checked($inherit); ?>
                    />
                    <?php esc_html_e('Apply to all subpages', 'pitea-customisation'); ?>
                </label>
            </div>
            <button type="button" class="button pitea-settings__repeater-remove">
                <?php esc_html_e('Remove', 'pitea-customisation'); ?>
            </button>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Field renderers — page tree ownership
    // -------------------------------------------------------------------------

    public function renderPageTreeOwnershipField(): void
    {
        $raw  = (string) get_option(self::OPTION_PAGE_TREE_OWNERSHIP, '[]');
        $rows = json_decode($raw, true);
        if (!is_array($rows)) {
            $rows = [];
        }
        ?>
        <div class="pitea-settings__repeater" data-repeater="user-group-ownership">
            <div class="pitea-settings__repeater-rows" id="user-group-ownership-rows">
                <?php foreach ($rows as $i => $row) : ?>
                    <?php $this->renderPageTreeOwnershipRow(
                        $i,
                        (int) ($row['user_group_id'] ?? 0),
                        (string) ($row['user_role'] ?? ''),
                        (int) ($row['page_id'] ?? 0),
                        !empty($row['inherit'])
                    ); ?>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button pitea-settings__repeater-add">
                <?php esc_html_e('+ Add rule', 'pitea-customisation'); ?>
            </button>
            <template id="user-group-ownership-row-template">
                <?php $this->renderPageTreeOwnershipRow('{{INDEX}}', 0, '', 0, true); ?>
            </template>
        </div>
        <?php
    }

    private function renderPageTreeOwnershipRow(
        string|int $index,
        int $selectedGroupId,
        string $selectedRole,
        int $selectedPageId,
        bool $inherit
    ): void
    {
        $namePrefix = self::OPTION_PAGE_TREE_OWNERSHIP . '[' . $index . ']';
        $terms      = get_terms(['taxonomy' => 'user_group', 'hide_empty' => false]);
        if (is_wp_error($terms)) {
            $terms = [];
        }
        ?>
        <div class="pitea-settings__repeater-row">
            <div class="pitea-settings__repeater-fields">
                <select name="<?php echo esc_attr($namePrefix); ?>[user_group_id]" class="pitea-settings__input">
                    <option value="0"><?php esc_html_e('— Optional user group —', 'pitea-customisation'); ?></option>
                    <?php foreach ($terms as $term) : ?>
                        <option
                            value="<?php echo esc_attr((string) $term->term_id); ?>"
                            <?php selected($selectedGroupId, $term->term_id); ?>
                        >
                            <?php echo esc_html($term->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="pitea-settings__inline-separator">
                    <?php esc_html_e('or', 'pitea-customisation'); ?>
                </span>
                <?php $this->renderRoleDropdown($namePrefix . '[user_role]', $selectedRole); ?>
                <?php $this->renderPageDropdown($namePrefix . '[page_id]', $selectedPageId); ?>
                <label class="pitea-settings__checkbox-label">
                    <input
                        type="checkbox"
                        name="<?php echo esc_attr($namePrefix); ?>[inherit]"
                        value="1"
                        <?php checked($inherit); ?>
                    />
                    <?php esc_html_e('Include all subpages', 'pitea-customisation'); ?>
                </label>
                <p class="description">
                    <?php esc_html_e('A rule matches when the user belongs to the selected user group or has the selected user role.', 'pitea-customisation'); ?>
                </p>
            </div>
            <button type="button" class="button pitea-settings__repeater-remove">
                <?php esc_html_e('Remove', 'pitea-customisation'); ?>
            </button>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Render a page <select> bypassing query filters so private/hidden pages appear.
     */
    private function renderPageDropdown(string $name, int $selectedId): void
    {
        $pages = get_posts([
            'post_type'        => 'page',
            'post_status'      => ['publish', 'private'],
            'orderby'          => 'menu_order title',
            'order'            => 'ASC',
            'numberposts'      => -1,
            'suppress_filters' => true,
        ]);

        echo '<select name="' . esc_attr($name) . '" class="pitea-settings__input">';
        echo '<option value="0">' . esc_html__('— Select a page —', 'pitea-customisation') . '</option>';
        echo walk_page_dropdown_tree($pages, 0, ['selected' => $selectedId]);
        echo '</select>';
    }

    private function renderRoleDropdown(string $name, string $selectedRole): void
    {
        $roles = wp_roles();

        echo '<select name="' . esc_attr($name) . '" class="pitea-settings__input">';
        echo '<option value="">' . esc_html__('— Optional user role —', 'pitea-customisation') . '</option>';

        if ($roles instanceof \WP_Roles) {
            foreach ($roles->roles as $roleKey => $roleData) {
                $label = isset($roleData['name']) ? translate_user_role((string) $roleData['name']) : $roleKey;
                echo '<option value="' . esc_attr($roleKey) . '" ' . selected($selectedRole, $roleKey, false) . '>';
                echo esc_html($label);
                echo '</option>';
            }
        }

        echo '</select>';
    }
}
