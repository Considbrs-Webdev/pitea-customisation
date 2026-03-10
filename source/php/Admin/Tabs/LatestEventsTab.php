<?php

declare(strict_types=1);

namespace PiteaCustomisation\Admin\Tabs;

use PiteaCustomisation\Admin\SettingsTabInterface;

/**
 * Class LatestEventsTab
 *
 * Settings tab for configuring the Modularity Latest Events module API credentials.
 */
class LatestEventsTab implements SettingsTabInterface
{
    private const OPTION_GROUP = 'pitea_customisation_latest_events';

    public const OPTION_API_URL   = 'modularity_latest_events_api_url';
    public const OPTION_API_TOKEN = 'modularity_latest_events_api_token';

    /**
     * Internal page slug used to scope settings sections to a group.
     */
    private const GROUP_LATEST_EVENTS = 'pitea_customisation_group_latest_events';

    // -------------------------------------------------------------------------
    // SettingsTabInterface
    // -------------------------------------------------------------------------

    public function getId(): string
    {
        return 'latest-events';
    }

    public function getTitle(): string
    {
        return __('Latest Events', 'pitea-customisation');
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
        $this->registerLatestEventsGroup();
    }

    private function registerLatestEventsGroup(): void
    {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_API_URL,
            [
                'type'              => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default'           => '',
                'show_in_rest'      => false,
            ]
        );

        register_setting(
            self::OPTION_GROUP,
            self::OPTION_API_TOKEN,
            [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
                'show_in_rest'      => false,
            ]
        );

        add_settings_section(
            'pitea_customisation_latest_events',
            __('API Configuration', 'pitea-customisation'),
            function (): void {
                echo '<p class="pitea-settings__section-desc">' . esc_html__(
                    'Configure the API credentials for the Latest Events module. These settings allow the module to fetch event data from the Visit Piteå API.',
                    'pitea-customisation'
                ) . '</p>';
            },
            self::GROUP_LATEST_EVENTS
        );

        add_settings_field(
            self::OPTION_API_URL,
            __('API URL', 'pitea-customisation'),
            [$this, 'renderApiUrlField'],
            self::GROUP_LATEST_EVENTS,
            'pitea_customisation_latest_events'
        );

        add_settings_field(
            self::OPTION_API_TOKEN,
            __('API Token', 'pitea-customisation'),
            [$this, 'renderApiTokenField'],
            self::GROUP_LATEST_EVENTS,
            'pitea_customisation_latest_events'
        );
    }

    // -------------------------------------------------------------------------
    // save()
    // -------------------------------------------------------------------------

    public function save(array $data): true|\WP_Error
    {
        $apiUrl = isset($data[self::OPTION_API_URL])
            ? esc_url_raw(wp_unslash((string) $data[self::OPTION_API_URL]))
            : '';

        $apiToken = isset($data[self::OPTION_API_TOKEN])
            ? sanitize_text_field(wp_unslash((string) $data[self::OPTION_API_TOKEN]))
            : '';

        update_option(self::OPTION_API_URL, $apiUrl);
        update_option(self::OPTION_API_TOKEN, $apiToken);

        return true;
    }

    // -------------------------------------------------------------------------
    // Field renderers
    // -------------------------------------------------------------------------

    public function renderApiUrlField(): void
    {
        $value   = (string) get_option(self::OPTION_API_URL, '');
        $fieldId = self::OPTION_API_URL;
        ?>
        <div class="pitea-settings__field">
            <input
                type="url"
                id="<?php echo esc_attr($fieldId); ?>"
                name="<?php echo esc_attr($fieldId); ?>"
                value="<?php echo esc_attr($value); ?>"
                class="pitea-settings__input"
                placeholder="https://api.example.com/wp-json/visitpitea/v1"
            />
            <p class="pitea-settings__field-desc">
                <?php esc_html_e(
                    'The Visit Piteå API base URL. Include the full path up to the API version.',
                    'pitea-customisation'
                ); ?>
            </p>
        </div>
        <?php
    }

    public function renderApiTokenField(): void
    {
        $value   = (string) get_option(self::OPTION_API_TOKEN, '');
        $fieldId = self::OPTION_API_TOKEN;
        ?>
        <div class="pitea-settings__field">
            <input
                type="password"
                id="<?php echo esc_attr($fieldId); ?>"
                name="<?php echo esc_attr($fieldId); ?>"
                value="<?php echo esc_attr($value); ?>"
                class="pitea-settings__input"
                placeholder="Bearer token"
            />
            <p class="pitea-settings__field-desc">
                <?php esc_html_e(
                    'The API Bearer token for authenticating with the Visit Piteå API.',
                    'pitea-customisation'
                ); ?>
            </p>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Rendering
    // -------------------------------------------------------------------------

    public function render(): void
    {
        $this->renderGroup(
            __('Latest Events Settings', 'pitea-customisation'),
            self::GROUP_LATEST_EVENTS
        );
    }

    /**
     * Render a settings group as a modern card.
     */
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
}
