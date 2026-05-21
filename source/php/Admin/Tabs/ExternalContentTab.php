<?php

declare(strict_types=1);

namespace PiteaCustomisation\Admin\Tabs;

use PiteaCustomisation\Admin\SettingsTabInterface;
use PiteaCustomisation\ExternalContent\Noticeboard\NovaPublicationEndpoint;
use PiteaCustomisation\ExternalContent\Search\EServices\EServicesImporter;
use PiteaCustomisation\ExternalContent\ServiceInfo\TrafficDisruptionsImporter;

/**
 * Class ExternalContentTab
 *
 * Settings tab for configuring external content importers.
 *
 * Groups are rendered as modern cards. Each group has an internal page slug
 * used to register and render settings sections via the WordPress Settings API.
 *
 * To add a new group, define a GROUP_* constant, register sections/fields
 * in register(), add a renderGroup() call in render(), and handle saving
 * in save().
 */
class ExternalContentTab implements SettingsTabInterface
{
    private const OPTION_GROUP = 'pitea_customisation_external_content';

    /**
     * Internal page slugs used to scope settings sections to a group.
     * These are NOT WordPress menu slugs; they are only used with
     * add_settings_section / add_settings_field / do_settings_sections.
     */
    private const GROUP_SERVICE_INFO = 'pitea_customisation_group_service_info';
    private const GROUP_SEARCH        = 'pitea_customisation_group_search';
    private const GROUP_NOVA          = 'pitea_customisation_group_nova';

    // -------------------------------------------------------------------------
    // SettingsTabInterface
    // -------------------------------------------------------------------------

    public function getId(): string
    {
        return 'external-content';
    }

    public function getTitle(): string
    {
        return __('External Content', 'pitea-customisation');
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
        $this->registerNovaGroup();
        $this->registerServiceInfoGroup();

        if (class_exists(\TypesenseSearch\Indexing\Strategies\AbstractExternalIndexingStrategy::class)) {
            $this->registerSearchGroup();
        }
    }

    private function registerNovaGroup(): void
    {
        add_settings_section(
            'pitea_customisation_nova',
            __('Building permit notices', 'pitea-customisation'),
            function (): void {
                echo '<p class="pitea-settings__section-desc">' . esc_html__(
                    'Publication endpoint for receiving building permit notices and decisions from Sokigo Nova.',
                    'pitea-customisation'
                ) . '</p>';

                echo '<p class="pitea-settings__section-desc">';
                printf(
                    /* translators: 1: username constant name, 2: password constant name. */
                    esc_html__('Define %1$s and %2$s to activate incoming Nova publications.', 'pitea-customisation'),
                    '<code>' . esc_html(NovaPublicationEndpoint::USERNAME_CONSTANT) . '</code>',
                    '<code>' . esc_html(NovaPublicationEndpoint::PASSWORD_CONSTANT) . '</code>'
                );
                echo '</p>';
            },
            self::GROUP_NOVA
        );

        add_settings_field(
            'pitea_customisation_nova_endpoint_status',
            __('Publication endpoint', 'pitea-customisation'),
            [$this, 'renderNovaEndpointInfoField'],
            self::GROUP_NOVA,
            'pitea_customisation_nova'
        );
    }

    private function registerServiceInfoGroup(): void
    {
        register_setting(
            self::OPTION_GROUP,
            TrafficDisruptionsImporter::OPTION_SOURCE_URL,
            [
                'type'              => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default'           => '',
                'show_in_rest'      => false,
            ]
        );

        add_settings_section(
            'pitea_customisation_traffic_disruptions',
            __('Traffic Disruptions', 'pitea-customisation'),
            function (): void {
                echo '<p class="pitea-settings__section-desc">' . esc_html__(
                    'Automatically import traffic disruption data from an ArcGIS FeatureServer and publish it as service information posts. Leave the URL empty to disable importing.',
                    'pitea-customisation'
                ) . '</p>';
            },
            self::GROUP_SERVICE_INFO
        );

        add_settings_field(
            TrafficDisruptionsImporter::OPTION_SOURCE_URL,
            __('Source URL', 'pitea-customisation'),
            [$this, 'renderTrafficDisruptionsSourceUrlField'],
            self::GROUP_SERVICE_INFO,
            'pitea_customisation_traffic_disruptions'
        );
    }

    // -------------------------------------------------------------------------
    // Registration: Search group
    // -------------------------------------------------------------------------

    private function registerSearchGroup(): void
    {
        register_setting(
            self::OPTION_GROUP,
            EServicesImporter::OPTION_SOURCE_URL,
            [
                'type'              => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default'           => '',
                'show_in_rest'      => false,
            ]
        );

        add_settings_section(
            'pitea_customisation_eservices',
            __('E-Services', 'pitea-customisation'),
            function (): void {
                echo '<p class="pitea-settings__section-desc">' . esc_html__(
                    'Automatically index Piteå\'s public e-services from the eNämnd API into the Typesense search engine. Leave the URL empty to disable indexing.',
                    'pitea-customisation'
                ) . '</p>';
            },
            self::GROUP_SEARCH
        );

        add_settings_field(
            EServicesImporter::OPTION_SOURCE_URL,
            __('Source URL', 'pitea-customisation'),
            [$this, 'renderEServicesSourceUrlField'],
            self::GROUP_SEARCH,
            'pitea_customisation_eservices'
        );
    }

    // -------------------------------------------------------------------------
    // save()
    // -------------------------------------------------------------------------

    public function save(array $data): true|\WP_Error
    {
        $trafficUrl = isset($data[TrafficDisruptionsImporter::OPTION_SOURCE_URL])
            ? esc_url_raw(wp_unslash((string) $data[TrafficDisruptionsImporter::OPTION_SOURCE_URL]))
            : '';

        $eservicesUrl = isset($data[EServicesImporter::OPTION_SOURCE_URL])
            ? esc_url_raw(wp_unslash((string) $data[EServicesImporter::OPTION_SOURCE_URL]))
            : '';

        update_option(TrafficDisruptionsImporter::OPTION_SOURCE_URL, $trafficUrl);
        update_option(EServicesImporter::OPTION_SOURCE_URL, $eservicesUrl);

        return true;
    }

    // -------------------------------------------------------------------------
    // Field renderers
    // -------------------------------------------------------------------------

    public function renderEServicesSourceUrlField(): void
    {
        $value   = (string) get_option(EServicesImporter::OPTION_SOURCE_URL, '');
        $fieldId = EServicesImporter::OPTION_SOURCE_URL;
        ?>
        <div class="pitea-settings__field">
            <input
                type="url"
                id="<?php echo esc_attr($fieldId); ?>"
                name="<?php echo esc_attr($fieldId); ?>"
                value="<?php echo esc_attr($value); ?>"
                class="pitea-settings__input"
            />
            <p class="pitea-settings__field-desc">
                <?php esc_html_e(
                    'The eNämnd public-services API URL. Leave empty to disable e-service indexing.',
                    'pitea-customisation'
                ); ?>
            </p>
        </div>
        <?php
    }

    public function renderTrafficDisruptionsSourceUrlField(): void
    {
        $value   = (string) get_option(TrafficDisruptionsImporter::OPTION_SOURCE_URL, '');
        $fieldId = TrafficDisruptionsImporter::OPTION_SOURCE_URL;
        ?>
        <div class="pitea-settings__field">
            <input
                type="url"
                id="<?php echo esc_attr($fieldId); ?>"
                name="<?php echo esc_attr($fieldId); ?>"
                value="<?php echo esc_attr($value); ?>"
                class="pitea-settings__input"
            />
            <p class="pitea-settings__field-desc">
                <?php esc_html_e(
                    'The GeoJSON endpoint URL for the traffic disruption layer. Leave empty to disable importing.',
                    'pitea-customisation'
                ); ?>
            </p>
        </div>
        <?php
    }

    public function renderNovaEndpointInfoField(): void
    {
        $isConfigured = NovaPublicationEndpoint::isConfigured();
        $endpointUrl  = NovaPublicationEndpoint::getEndpointUrl();
        ?>
        <div class="pitea-settings__field">
            <p>
                <strong><?php esc_html_e('Status', 'pitea-customisation'); ?>:</strong>
                <?php echo esc_html($isConfigured ? __('Activated', 'pitea-customisation') : __('Not activated', 'pitea-customisation')); ?>
            </p>
            <p>
                <strong><?php esc_html_e('Endpoint URL', 'pitea-customisation'); ?>:</strong>
                <code><?php echo esc_html($endpointUrl); ?></code>
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
            __('Digital noticeboard', 'pitea-customisation'),
            self::GROUP_NOVA
        );

        $this->renderGroup(
            __('Service Information', 'pitea-customisation'),
            self::GROUP_SERVICE_INFO
        );

        $this->renderGroup(
            __('Search', 'pitea-customisation'),
            self::GROUP_SEARCH
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
