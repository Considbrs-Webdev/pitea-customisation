<?php

declare(strict_types=1);

namespace PiteaCustomisation\Admin\Tabs;

use PiteaCustomisation\Admin\SettingsTabInterface;

/**
 * Settings tab for the default quick-exit destination and label.
 */
class QuickExitTab implements SettingsTabInterface
{
    private const OPTION_GROUP = 'pitea_customisation_quick_exit';

    public const OPTION_URL = 'pitea_customisation_quick_exit_url';

    public const OPTION_LABEL = 'pitea_customisation_quick_exit_label';

    public const FALLBACK_URL = 'https://www.aftonbladet.se';

    /**
     * Internal page slug used to scope settings sections to a group.
     */
    private const GROUP_QUICK_EXIT = 'pitea_customisation_group_quick_exit';

    public function getId(): string
    {
        return 'quick-exit';
    }

    public function getTitle(): string
    {
        return __('Quick exit', 'pitea-customisation');
    }

    public function getOptionGroup(): string
    {
        return self::OPTION_GROUP;
    }

    public function register(): void
    {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_URL,
            [
                'type'              => 'string',
                'sanitize_callback' => static function ($value): string {
                    return self::sanitizeUrl(is_string($value) ? $value : '');
                },
                'default'           => '',
                'show_in_rest'      => false,
            ]
        );

        register_setting(
            self::OPTION_GROUP,
            self::OPTION_LABEL,
            [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
                'show_in_rest'      => false,
            ]
        );

        add_settings_section(
            'pitea_customisation_quick_exit',
            __('Quick exit', 'pitea-customisation'),
            function (): void {
                echo '<p class="pitea-settings__section-desc">' . esc_html__(
                    'Default destination and label for the quick-exit module. Individual modules can override both.',
                    'pitea-customisation'
                ) . '</p>';
            },
            self::GROUP_QUICK_EXIT
        );

        add_settings_field(
            self::OPTION_URL,
            __('Default destination', 'pitea-customisation'),
            [$this, 'renderUrlField'],
            self::GROUP_QUICK_EXIT,
            'pitea_customisation_quick_exit'
        );

        add_settings_field(
            self::OPTION_LABEL,
            __('Default button text', 'pitea-customisation'),
            [$this, 'renderLabelField'],
            self::GROUP_QUICK_EXIT,
            'pitea_customisation_quick_exit'
        );
    }

    public function render(): void
    {
        ?>
        <div class="pitea-settings__card">
            <div class="pitea-settings__card-header">
                <h2 class="pitea-settings__card-title"><?php echo esc_html__('Quick exit', 'pitea-customisation'); ?></h2>
            </div>
            <div class="pitea-settings__card-body">
                <?php do_settings_sections(self::GROUP_QUICK_EXIT); ?>
            </div>
        </div>
        <?php
    }

    public function renderUrlField(): void
    {
        $value = (string) get_option(self::OPTION_URL, '');
        ?>
        <div class="pitea-settings__field">
            <input
                type="url"
                name="<?php echo esc_attr(self::OPTION_URL); ?>"
                value="<?php echo esc_attr($value); ?>"
                class="pitea-settings__input"
                placeholder="<?php echo esc_attr(self::FALLBACK_URL); ?>"
            />
            <p class="pitea-settings__field-desc">
                <?php esc_html_e('Where the quick-exit button sends visitors. Choose a neutral, everyday site. Can be overridden per module.', 'pitea-customisation'); ?>
            </p>
        </div>
        <?php
    }

    public function renderLabelField(): void
    {
        $value = (string) get_option(self::OPTION_LABEL, '');
        ?>
        <div class="pitea-settings__field">
            <input
                type="text"
                name="<?php echo esc_attr(self::OPTION_LABEL); ?>"
                value="<?php echo esc_attr($value); ?>"
                class="pitea-settings__input"
                placeholder="<?php echo esc_attr__('Leave the page quickly', 'pitea-customisation'); ?>"
            />
            <p class="pitea-settings__field-desc">
                <?php esc_html_e('Button text. Leave empty to use “Lämna sidan snabbt”.', 'pitea-customisation'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save(array $data): true|\WP_Error
    {
        $urlRaw = isset($data[self::OPTION_URL])
            ? trim((string) wp_unslash($data[self::OPTION_URL]))
            : '';

        if ($urlRaw !== '' && !self::isHttpUrl($urlRaw)) {
            return new \WP_Error(
                'invalid_url',
                __('Enter a valid http(s) URL.', 'pitea-customisation')
            );
        }

        $url = self::sanitizeUrl($urlRaw);

        $label = isset($data[self::OPTION_LABEL])
            ? sanitize_text_field(wp_unslash((string) $data[self::OPTION_LABEL]))
            : '';

        update_option(self::OPTION_URL, $url);
        update_option(self::OPTION_LABEL, $label);

        return true;
    }

    /**
     * Empty string, or an http(s) URL. Anything else becomes an empty string.
     */
    public static function sanitizeUrl(string $value): string
    {
        $value = trim($value);
        if (!self::isHttpUrl($value)) {
            return '';
        }

        return (string) esc_url_raw($value, ['http', 'https']);
    }

    /**
     * Whether the value is an absolute http or https URL.
     */
    private static function isHttpUrl(string $value): bool
    {
        if (!preg_match('#^https?://#i', $value)) {
            return false;
        }

        $parts = wp_parse_url($value);

        return is_array($parts) && !empty($parts['host']);
    }

    /**
     * Destination used when a module does not set its own URL.
     */
    public static function getDefaultUrl(): string
    {
        $url = (string) esc_url_raw((string) get_option(self::OPTION_URL, ''), ['http', 'https']);

        return $url !== '' ? $url : self::FALLBACK_URL;
    }

    /**
     * Button label used when a module does not set its own text.
     */
    public static function getDefaultLabel(): string
    {
        $label = sanitize_text_field((string) get_option(self::OPTION_LABEL, ''));

        return $label !== '' ? $label : __('Leave the page quickly', 'pitea-customisation');
    }
}
