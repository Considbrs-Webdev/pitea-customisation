<?php

declare(strict_types=1);

namespace PiteaCustomisation\Admin\Tabs;

use PiteaCustomisation\Admin\SettingsTabInterface;

/**
 * Class ReadSpeakerTab
 *
 * Settings tab for configuring ReadSpeaker integration.
 */
class ReadSpeakerTab implements SettingsTabInterface
{
    private const OPTION_GROUP = 'pitea_customisation_readspeaker';

    public const OPTION_CUSTOMER_ID = 'pitea_customisation_readspeaker_customer_id';
    public const OPTION_READ_ID = 'pitea_customisation_readspeaker_read_id';

    /**
     * When enabled, nav-helper accessibility buttons are hidden; use the Modularity module “Accessibility buttons” in the sidebar instead.
     */
    public const OPTION_USE_MODULE_PLACEMENT = 'pitea_customisation_accessibility_use_module_placement';

    /**
     * Internal page slug used to scope settings sections to a group.
     */
    private const GROUP_READSPEAKER = 'pitea_customisation_group_readspeaker';

    // -------------------------------------------------------------------------
    // SettingsTabInterface
    // -------------------------------------------------------------------------

    public function getId(): string
    {
        return 'readspeaker';
    }

    public function getTitle(): string
    {
        return __('ReadSpeaker', 'pitea-customisation');
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
        $this->registerReadSpeakerGroup();
    }

    private function registerReadSpeakerGroup(): void
    {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_CUSTOMER_ID,
            [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
                'show_in_rest'      => false,
            ]
        );

        register_setting(
            self::OPTION_GROUP,
            self::OPTION_READ_ID,
            [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
                'show_in_rest'      => false,
            ]
        );

        register_setting(
            self::OPTION_GROUP,
            self::OPTION_USE_MODULE_PLACEMENT,
            [
                'type'              => 'boolean',
                'sanitize_callback' => static fn ($value): bool => !empty($value),
                'default'           => false,
                'show_in_rest'      => false,
            ]
        );

        add_settings_section(
            'pitea_customisation_readspeaker',
            __('ReadSpeaker Configuration', 'pitea-customisation'),
            function (): void {
                echo '<p class="pitea-settings__section-desc">' . esc_html__(
                    'Configure ReadSpeaker integration settings.',
                    'pitea-customisation'
                ) . '</p>';
            },
            self::GROUP_READSPEAKER
        );

        add_settings_field(
            self::OPTION_CUSTOMER_ID,
            __('Customer ID', 'pitea-customisation'),
            [$this, 'renderCustomerIdField'],
            self::GROUP_READSPEAKER,
            'pitea_customisation_readspeaker'
        );

        add_settings_field(
            self::OPTION_READ_ID,
            __('Read ID', 'pitea-customisation'),
            [$this, 'renderReadIdField'],
            self::GROUP_READSPEAKER,
            'pitea_customisation_readspeaker'
        );

        add_settings_field(
            self::OPTION_USE_MODULE_PLACEMENT,
            __('Module placement', 'pitea-customisation'),
            [$this, 'renderUseModulePlacementField'],
            self::GROUP_READSPEAKER,
            'pitea_customisation_readspeaker'
        );
    }

    // -------------------------------------------------------------------------
    // Rendering
    // -------------------------------------------------------------------------

    public function render(): void
    {
        $this->renderGroup(__('ReadSpeaker Settings', 'pitea-customisation'), self::GROUP_READSPEAKER);
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

    public function renderCustomerIdField(): void
    {
        $value = get_option(self::OPTION_CUSTOMER_ID, '');
        ?>
        <div class="pitea-settings__field">
            <input 
                type="text" 
                name="<?php echo esc_attr(self::OPTION_CUSTOMER_ID); ?>" 
                value="<?php echo esc_attr($value); ?>" 
                class="pitea-settings__input"
            />
            <p class="pitea-settings__field-desc">
                <?php esc_html_e('Enter your ReadSpeaker customer ID', 'pitea-customisation'); ?>
            </p>
        </div>
        <?php
    }

    public function renderReadIdField(): void
    {
        $value = get_option(self::OPTION_READ_ID);
        ?>
        <div class="pitea-settings__field">
            <input 
                type="text" 
                name="<?php echo esc_attr(self::OPTION_READ_ID); ?>" 
                value="<?php echo esc_attr($value); ?>" 
                class="pitea-settings__input"
            />
            <p class="pitea-settings__field-desc">
                <?php esc_html_e('Enter the ID of the element to read', 'pitea-customisation'); ?>
            </p>
        </div>
        <?php
    }

    public function renderUseModulePlacementField(): void
    {
        $enabled = (bool) get_option(self::OPTION_USE_MODULE_PLACEMENT, false);
        ?>
        <div class="pitea-settings__field">
            <label>
                <input
                    type="checkbox"
                    name="<?php echo esc_attr(self::OPTION_USE_MODULE_PLACEMENT); ?>"
                    value="1"
                    <?php checked($enabled); ?>
                />
                <?php esc_html_e('Hide nav bar buttons; use Modularity module only', 'pitea-customisation'); ?>
            </label>
            <p class="pitea-settings__field-desc">
                <?php esc_html_e('When enabled, Listen and Print are removed from the top nav-helper area. Add the “Accessibility buttons” module (mod-acc-buttons) in the sidebar on each template where you want them. Enable that module under Modularity → Modules.', 'pitea-customisation'); ?>
            </p>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // save
    // -------------------------------------------------------------------------

    public function save(array $data): true|\WP_Error
    {
        $customerId = isset($data[self::OPTION_CUSTOMER_ID])
            ? sanitize_text_field(wp_unslash((string) $data[self::OPTION_CUSTOMER_ID]))
            : '';

        $readId = isset($data[self::OPTION_READ_ID])
            ? sanitize_text_field(wp_unslash((string) $data[self::OPTION_READ_ID]))
            : '';

        update_option(self::OPTION_CUSTOMER_ID, $customerId);
        update_option(self::OPTION_READ_ID, $readId);
        update_option(self::OPTION_USE_MODULE_PLACEMENT, !empty($data[self::OPTION_USE_MODULE_PLACEMENT]));

        return true;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Get the configured ReadSpeaker customer ID
     */
    public static function getCustomerId(): string
    {
        return get_option(self::OPTION_CUSTOMER_ID, '');
    }

    /**
     * Get the configured ReadSpeaker read ID
     */
    public static function getReadId(): string
    {
        return get_option(self::OPTION_READ_ID, 'article');
    }

    /**
     * Whether nav-helper accessibility items should be stripped in favour of the AccButtons module.
     */
    public static function useModulePlacementForAccessibility(): bool
    {
        return (bool) get_option(self::OPTION_USE_MODULE_PLACEMENT, false);
    }
}