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
 *   - "Post template access": maps user groups to which page templates they may create.
 */
class PagePermissionsTab implements SettingsTabInterface
{
    private const OPTION_GROUP = 'pitea_customisation_page_permissions_tab';

    public const OPTION_PAGE_PERMISSIONS    = 'pitea_customisation_page_permissions';
    public const OPTION_PAGE_TREE_OWNERSHIP = 'pitea_customisation_user_group_ownership';
    public const OPTION_POST_TEMPLATE_ACCESS = 'pitea_customisation_post_template_access';

    private const GROUP_PROTECTED           = 'pitea_customisation_group_protected_pages';
    private const GROUP_PAGE_TREE_OWNERSHIP = 'pitea_customisation_group_user_group_ownership';
    private const GROUP_POST_TEMPLATE_ACCESS = 'pitea_customisation_group_post_template_access';

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
        $this->registerPostTemplateAccessGroup();
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
            '',
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
            '',
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

    private function registerPostTemplateAccessGroup(): void
    {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_POST_TEMPLATE_ACCESS,
            [
                'type'         => 'string',
                'default'      => '[]',
                'show_in_rest' => false,
            ]
        );

        add_settings_section(
            'pitea_customisation_post_template_access',
            '',
            function (): void {
                echo '<p class="pitea-settings__section-desc">' . esc_html__(
                    'Control which user groups can use each post template in the Page menu. Administrators always have access to all templates.',
                    'pitea-customisation'
                ) . '</p>';
            },
            self::GROUP_POST_TEMPLATE_ACCESS
        );

        add_settings_field(
            self::OPTION_POST_TEMPLATE_ACCESS,
            __('Template rules', 'pitea-customisation'),
            [$this, 'renderPostTemplateAccessField'],
            self::GROUP_POST_TEMPLATE_ACCESS,
            'pitea_customisation_post_template_access'
        );
    }

    // -------------------------------------------------------------------------
    // save
    // -------------------------------------------------------------------------

    public function save(array $data): true|\WP_Error
    {
        $this->saveProtectedPages($data);
        $this->savePageTreeOwnership($data);
        $this->savePostTemplateAccess($data);

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

    private function savePostTemplateAccess(array $data): void
    {
        $rawRows = isset($data[self::OPTION_POST_TEMPLATE_ACCESS]) && is_array($data[self::OPTION_POST_TEMPLATE_ACCESS])
            ? $data[self::OPTION_POST_TEMPLATE_ACCESS]
            : [];

        $validTemplateSlugs = array_flip(array_keys($this->getPostTemplateOptions()));
        $byUserGroup        = [];

        foreach ($rawRows as $row) {
            $groupId = isset($row['user_group_id']) ? absint($row['user_group_id']) : 0;
            if ($groupId === 0) {
                continue;
            }

            if (!isset($byUserGroup[$groupId])) {
                $byUserGroup[$groupId] = [];
            }

            $allowedTemplates = isset($row['allowed_templates']) && is_array($row['allowed_templates'])
                ? $row['allowed_templates']
                : [];

            foreach ($allowedTemplates as $slug) {
                $slug = sanitize_key((string) $slug);
                if (isset($validTemplateSlugs[$slug])) {
                    $byUserGroup[$groupId][$slug] = true;
                }
            }
        }

        $orderedTemplateSlugs = array_keys($this->getPostTemplateOptions());
        $rules                = [];
        foreach ($byUserGroup as $groupId => $allowedSet) {
            $allowed = [];
            foreach ($orderedTemplateSlugs as $templateSlug) {
                if (isset($allowedSet[$templateSlug])) {
                    $allowed[] = $templateSlug;
                }
            }

            $rules[] = [
                'user_group_id'    => (int) $groupId,
                'allowed_templates' => $allowed,
            ];
        }

        update_option(self::OPTION_POST_TEMPLATE_ACCESS, wp_json_encode($rules));
    }

    // -------------------------------------------------------------------------
    // Rendering
    // -------------------------------------------------------------------------

    public function render(): void
    {
        $this->renderGroup(__('Protected pages and templates', 'pitea-customisation'), self::GROUP_PROTECTED);
        $this->renderGroup(__('Page tree ownership rules', 'pitea-customisation'), self::GROUP_PAGE_TREE_OWNERSHIP);
        $this->renderGroup(__('Post template access', 'pitea-customisation'), self::GROUP_POST_TEMPLATE_ACCESS);
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

    // -------------------------------------------------------------------------
    // Field renderers — post template access
    // -------------------------------------------------------------------------

    public function renderPostTemplateAccessField(): void
    {
        $raw  = (string) get_option(self::OPTION_POST_TEMPLATE_ACCESS, '[]');
        $rows = json_decode($raw, true);
        if (!is_array($rows)) {
            $rows = [];
        }

        $templateOptions = $this->getPostTemplateOptions();
        ?>
        <div class="pitea-settings__repeater" data-repeater="post-template-access">
            <div class="pitea-settings__repeater-rows" id="post-template-access-rows">
                <?php foreach ($rows as $i => $row) : ?>
                    <?php
                    $allowedTemplates = isset($row['allowed_templates']) && is_array($row['allowed_templates'])
                        ? $row['allowed_templates']
                        : [];
                    $this->renderPostTemplateAccessRow(
                        $i,
                        (int) ($row['user_group_id'] ?? 0),
                        $allowedTemplates,
                        $templateOptions
                    );
                    ?>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button pitea-settings__repeater-add">
                <?php esc_html_e('+ Add rule', 'pitea-customisation'); ?>
            </button>
            <template id="post-template-access-row-template">
                <?php $this->renderPostTemplateAccessRow('{{INDEX}}', 0, [], $templateOptions); ?>
            </template>
        </div>
        <?php
    }

    /**
     * @param string|int                   $index
     * @param list<string>                 $allowedTemplates
     * @param array<string, string>        $templateOptions
     */
    private function renderPostTemplateAccessRow(
        string|int $index,
        int $selectedGroupId,
        array $allowedTemplates,
        array $templateOptions
    ): void {
        $namePrefix = self::OPTION_POST_TEMPLATE_ACCESS . '[' . $index . ']';
        $terms      = get_terms(['taxonomy' => 'user_group', 'hide_empty' => false]);
        if (is_wp_error($terms)) {
            $terms = [];
        }

        $selectedSet = array_flip(array_map('strval', $allowedTemplates));
        ?>
        <div class="pitea-settings__repeater-row">
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

                <fieldset class="pitea-settings__palette-fieldset">
                    <legend class="pitea-settings__field-legend">
                        <?php esc_html_e('Allowed templates', 'pitea-customisation'); ?>
                    </legend>
                    <div class="pitea-settings__palette-checkboxes">
                        <?php foreach ($templateOptions as $slug => $label) : ?>
                            <label class="pitea-settings__checkbox-label">
                                <input
                                    type="checkbox"
                                    name="<?php echo esc_attr($namePrefix); ?>[allowed_templates][]"
                                    value="<?php echo esc_attr($slug); ?>"
                                    <?php checked(isset($selectedSet[$slug])); ?>
                                />
                                <?php echo esc_html($label); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            </div>
            <button type="button" class="button pitea-settings__repeater-remove">
                <?php esc_html_e('Remove', 'pitea-customisation'); ?>
            </button>
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

    /**
     * @return array<string, string>
     */
    private function getPostTemplateOptions(): array
    {
        return [
            'pitea-create-navigation-page'              => __('New navigation page', 'pitea-customisation'),
            'pitea-create-theme-page'                   => __('New theme page', 'pitea-customisation'),
            'pitea-create-navigation-second-level-page' => __('New navigation page (second level)', 'pitea-customisation'),
            'pitea-create-content-page'                 => __('New content page', 'pitea-customisation'),
        ];
    }
}
