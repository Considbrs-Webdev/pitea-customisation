<?php

declare(strict_types=1);

namespace PiteaCustomisation\Admin\Tabs;

use PiteaCustomisation\Admin\SettingsTabInterface;

/**
 * Settings tab for controlling where the customer feedback form is hidden.
 */
class CustomerFeedbackTab implements SettingsTabInterface
{
    private const OPTION_GROUP = 'pitea_customisation_customer_feedback';

    public const OPTION_EXCLUDED_ARCHIVE_POST_TYPES = 'pitea_customisation_customer_feedback_excluded_archive_post_types';
    public const OPTION_EXCLUDED_CONTEXTS = 'pitea_customisation_customer_feedback_excluded_contexts';

    private const GROUP_MAIN = 'pitea_customisation_group_customer_feedback';

    // -------------------------------------------------------------------------
    // SettingsTabInterface
    // -------------------------------------------------------------------------

    public function getId(): string
    {
        return 'customer-feedback';
    }

    public function getTitle(): string
    {
        return __('Customer feedback', 'pitea-customisation');
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
            self::OPTION_EXCLUDED_ARCHIVE_POST_TYPES,
            [
                'type'         => 'array',
                'default'      => [],
                'show_in_rest' => false,
            ]
        );

        register_setting(
            self::OPTION_GROUP,
            self::OPTION_EXCLUDED_CONTEXTS,
            [
                'type'         => 'array',
                'default'      => [],
                'show_in_rest' => false,
            ]
        );

        add_settings_section(
            'pitea_customisation_customer_feedback',
            __('Visibility', 'pitea-customisation'),
            function (): void {
                echo '<p class="pitea-settings__section-desc">' . esc_html__(
                    'Control where the customer feedback form should be hidden. Front page and individually excluded singular posts/pages are already handled separately.',
                    'pitea-customisation'
                ) . '</p>';
            },
            self::GROUP_MAIN
        );

        add_settings_field(
            self::OPTION_EXCLUDED_ARCHIVE_POST_TYPES,
            __('Exclude on post type archives', 'pitea-customisation'),
            [$this, 'renderArchivePostTypesField'],
            self::GROUP_MAIN,
            'pitea_customisation_customer_feedback'
        );

        add_settings_field(
            self::OPTION_EXCLUDED_CONTEXTS,
            __('Exclude on other page types', 'pitea-customisation'),
            [$this, 'renderContextsField'],
            self::GROUP_MAIN,
            'pitea_customisation_customer_feedback'
        );
    }

    // -------------------------------------------------------------------------
    // save
    // -------------------------------------------------------------------------

    public function save(array $data): true|\WP_Error
    {
        $archivePostTypes = isset($data[self::OPTION_EXCLUDED_ARCHIVE_POST_TYPES]) && is_array($data[self::OPTION_EXCLUDED_ARCHIVE_POST_TYPES])
            ? self::sanitizeSelectedValues(
                $data[self::OPTION_EXCLUDED_ARCHIVE_POST_TYPES],
                array_keys(self::getArchivePostTypeOptions())
            )
            : [];

        $contexts = isset($data[self::OPTION_EXCLUDED_CONTEXTS]) && is_array($data[self::OPTION_EXCLUDED_CONTEXTS])
            ? self::sanitizeSelectedValues($data[self::OPTION_EXCLUDED_CONTEXTS], array_keys(self::getContextOptions()))
            : [];

        update_option(self::OPTION_EXCLUDED_ARCHIVE_POST_TYPES, $archivePostTypes);
        update_option(self::OPTION_EXCLUDED_CONTEXTS, $contexts);

        return true;
    }

    // -------------------------------------------------------------------------
    // Rendering
    // -------------------------------------------------------------------------

    public function render(): void
    {
        $this->renderGroup(__('Customer feedback settings', 'pitea-customisation'), self::GROUP_MAIN);
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

    public function renderArchivePostTypesField(): void
    {
        $options     = self::getArchivePostTypeOptions();
        $selectedSet = array_flip(self::getExcludedArchivePostTypes());
        ?>
        <div class="pitea-settings__field">
            <?php if ($options === []) : ?>
                <p class="description">
                    <?php esc_html_e('No public post types with archives were found.', 'pitea-customisation'); ?>
                </p>
            <?php else : ?>
                <div class="pitea-settings__palette-checkboxes">
                    <?php foreach ($options as $slug => $label) : ?>
                        <label class="pitea-settings__checkbox-label">
                            <input
                                type="checkbox"
                                name="<?php echo esc_attr(self::OPTION_EXCLUDED_ARCHIVE_POST_TYPES); ?>[]"
                                value="<?php echo esc_attr($slug); ?>"
                                <?php checked(isset($selectedSet[$slug])); ?>
                            />
                            <?php echo esc_html($label); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <p class="pitea-settings__field-desc">
                <?php esc_html_e('When checked, the feedback form is hidden on archive pages for the selected post types.', 'pitea-customisation'); ?>
            </p>
        </div>
        <?php
    }

    public function renderContextsField(): void
    {
        $options     = self::getContextOptions();
        $selectedSet = array_flip(self::getExcludedContexts());
        ?>
        <div class="pitea-settings__field">
            <div class="pitea-settings__palette-checkboxes">
                <?php foreach ($options as $key => $label) : ?>
                    <label class="pitea-settings__checkbox-label">
                        <input
                            type="checkbox"
                            name="<?php echo esc_attr(self::OPTION_EXCLUDED_CONTEXTS); ?>[]"
                            value="<?php echo esc_attr($key); ?>"
                            <?php checked(isset($selectedSet[$key])); ?>
                        />
                        <?php echo esc_html($label); ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="pitea-settings__field-desc">
                <?php esc_html_e('Use these toggles to hide feedback on common non-singular or utility page types.', 'pitea-customisation'); ?>
            </p>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Runtime getters
    // -------------------------------------------------------------------------

    /**
     * @return list<string>
     */
    public static function getExcludedArchivePostTypes(): array
    {
        $raw = get_option(self::OPTION_EXCLUDED_ARCHIVE_POST_TYPES, []);

        if (!is_array($raw)) {
            return [];
        }

        return self::sanitizeSelectedValues($raw, array_keys(self::getArchivePostTypeOptions()));
    }

    /**
     * @return list<string>
     */
    public static function getExcludedContexts(): array
    {
        $raw = get_option(self::OPTION_EXCLUDED_CONTEXTS, []);

        if (!is_array($raw)) {
            return [];
        }

        return self::sanitizeSelectedValues($raw, array_keys(self::getContextOptions()));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @param array<int|string, mixed> $values
     * @param list<string>             $allowedKeys
     *
     * @return list<string>
     */
    private static function sanitizeSelectedValues(array $values, array $allowedKeys): array
    {
        $allowedSet = array_flip($allowedKeys);
        $resultSet  = [];

        foreach ($values as $value) {
            $key = sanitize_key((string) $value);
            if ($key !== '' && isset($allowedSet[$key])) {
                $resultSet[$key] = true;
            }
        }

        return array_keys($resultSet);
    }

    /**
     * @return array<string, string>
     */
    private static function getArchivePostTypeOptions(): array
    {
        $objects = get_post_types(
            [
                'public' => true,
            ],
            'objects'
        );

        $options = [];

        foreach ($objects as $slug => $object) {
            if (empty($object->has_archive)) {
                continue;
            }

            $label = $object->labels->name ?? $slug;
            $options[$slug] = (string) $label;
        }

        natcasesort($options);

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private static function getContextOptions(): array
    {
        return [
            'home'      => __('Posts page (blog index)', 'pitea-customisation'),
            'search'    => __('Search results', 'pitea-customisation'),
            'taxonomy'  => __('Taxonomy archives', 'pitea-customisation'),
            'category'  => __('Category archives', 'pitea-customisation'),
            'tag'       => __('Tag archives', 'pitea-customisation'),
            'date'      => __('Date archives', 'pitea-customisation'),
            'author'    => __('Author archives', 'pitea-customisation'),
            '404'       => __('404 pages', 'pitea-customisation'),
        ];
    }
}
