<?php

declare(strict_types=1);

namespace PiteaCustomisation\Admin\Tabs;

use PiteaCustomisation\Admin\SettingsTabInterface;

/**
 * Site-wide settings for the quick-exit module: destination, texts, read-more page and keyboard shortcut.
 */
class QuickExitTab implements SettingsTabInterface
{
    private const OPTION_GROUP = 'pitea_customisation_quick_exit';

    public const OPTION_URL = 'pitea_customisation_quick_exit_url';

    public const OPTION_LABEL = 'pitea_customisation_quick_exit_label';

    public const OPTION_HEADING = 'pitea_customisation_quick_exit_heading';

    public const OPTION_TEXT = 'pitea_customisation_quick_exit_text';

    public const OPTION_READ_MORE_URL = 'pitea_customisation_quick_exit_read_more_url';

    public const OPTION_READ_MORE_TEXT = 'pitea_customisation_quick_exit_read_more_text';

    public const OPTION_SHORTCUT = 'pitea_customisation_quick_exit_shortcut';

    public const FALLBACK_URL = 'https://www.aftonbladet.se';

    private const SECTION = 'pitea_customisation_quick_exit';

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
        $string = static fn (string $default = ''): array => [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => $default,
            'show_in_rest'      => false,
        ];

        register_setting(self::OPTION_GROUP, self::OPTION_URL, array_merge($string(), [
            'sanitize_callback' => static fn ($value): string => self::sanitizeUrl(is_string($value) ? $value : ''),
        ]));
        register_setting(self::OPTION_GROUP, self::OPTION_READ_MORE_URL, array_merge($string(), [
            'sanitize_callback' => static fn ($value): string => self::sanitizeUrl(is_string($value) ? $value : ''),
        ]));
        register_setting(self::OPTION_GROUP, self::OPTION_LABEL, $string());
        register_setting(self::OPTION_GROUP, self::OPTION_HEADING, $string());
        register_setting(self::OPTION_GROUP, self::OPTION_READ_MORE_TEXT, $string());
        register_setting(self::OPTION_GROUP, self::OPTION_TEXT, array_merge($string(), [
            'sanitize_callback' => 'sanitize_textarea_field',
        ]));
        register_setting(self::OPTION_GROUP, self::OPTION_SHORTCUT, [
            'type'              => 'boolean',
            'sanitize_callback' => static fn ($value): bool => !empty($value),
            'default'           => true,
            'show_in_rest'      => false,
        ]);

        add_settings_section(
            self::SECTION,
            __('Quick exit', 'pitea-customisation'),
            static function (): void {
                echo '<p class="pitea-settings__section-desc">' . esc_html__(
                    'Applies to every quick-exit module on the site. Modules only choose where the button is shown and whether the read-more link appears.',
                    'pitea-customisation'
                ) . '</p>';
            },
            self::GROUP_QUICK_EXIT
        );

        $fields = [
            self::OPTION_URL => [__('Destination', 'pitea-customisation'), 'renderUrlField'],
            self::OPTION_LABEL => [__('Button text', 'pitea-customisation'), 'renderLabelField'],
            self::OPTION_HEADING => [__('Accordion heading', 'pitea-customisation'), 'renderHeadingField'],
            self::OPTION_TEXT => [__('Explanation', 'pitea-customisation'), 'renderTextField'],
            self::OPTION_READ_MORE_URL => [__('Read-more page', 'pitea-customisation'), 'renderReadMoreUrlField'],
            self::OPTION_READ_MORE_TEXT => [__('Read-more link text', 'pitea-customisation'), 'renderReadMoreTextField'],
            self::OPTION_SHORTCUT => [__('Keyboard shortcut', 'pitea-customisation'), 'renderShortcutField'],
        ];

        foreach ($fields as $option => [$title, $callback]) {
            add_settings_field($option, $title, [$this, $callback], self::GROUP_QUICK_EXIT, self::SECTION);
        }
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
        $this->renderInput(
            self::OPTION_URL,
            'url',
            self::FALLBACK_URL,
            __('Where the button sends visitors. Choose a plain, neutral site that does not show personalised or recently visited content.', 'pitea-customisation')
        );
    }

    public function renderLabelField(): void
    {
        $this->renderInput(
            self::OPTION_LABEL,
            'text',
            self::defaultLabel(),
            __('Text on the button. Leave empty for the default.', 'pitea-customisation')
        );
    }

    public function renderHeadingField(): void
    {
        $this->renderInput(
            self::OPTION_HEADING,
            'text',
            self::defaultHeading(),
            __('Heading of the accordion that explains the button. Leave empty for the default.', 'pitea-customisation')
        );
    }

    public function renderTextField(): void
    {
        $value = (string) get_option(self::OPTION_TEXT, '');
        ?>
        <div class="pitea-settings__field">
            <textarea
                name="<?php echo esc_attr(self::OPTION_TEXT); ?>"
                rows="3"
                class="pitea-settings__input"
                placeholder="<?php echo esc_attr(self::defaultText()); ?>"
            ><?php echo esc_textarea($value); ?></textarea>
            <p class="pitea-settings__field-desc">
                <?php esc_html_e('Shown when the accordion is open. Use {site} for the address of the destination. Leave empty for the default.', 'pitea-customisation'); ?>
            </p>
        </div>
        <?php
    }

    public function renderReadMoreUrlField(): void
    {
        $this->renderInput(
            self::OPTION_READ_MORE_URL,
            'url',
            'https://',
            __('Page that explains the quick-exit button. Modules can show or hide the link; with no page here the link is never shown.', 'pitea-customisation')
        );
    }

    public function renderReadMoreTextField(): void
    {
        $this->renderInput(
            self::OPTION_READ_MORE_TEXT,
            'text',
            self::defaultReadMoreText(),
            __('Leave empty for the default.', 'pitea-customisation')
        );
    }

    public function renderShortcutField(): void
    {
        $enabled = self::isShortcutEnabled();
        ?>
        <div class="pitea-settings__field">
            <label>
                <input
                    type="checkbox"
                    name="<?php echo esc_attr(self::OPTION_SHORTCUT); ?>"
                    value="1"
                    <?php checked($enabled); ?>
                />
                <?php esc_html_e('Leave the page when Shift is pressed three times', 'pitea-customisation'); ?>
            </label>
            <p class="pitea-settings__field-desc">
                <?php esc_html_e('Screen readers announce the progress. The explanation mentions the shortcut when this is on.', 'pitea-customisation'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save(array $data): true|\WP_Error
    {
        foreach ([self::OPTION_URL, self::OPTION_READ_MORE_URL] as $option) {
            $raw = isset($data[$option]) ? trim((string) wp_unslash($data[$option])) : '';
            if ($raw !== '' && !self::isHttpUrl($raw)) {
                return new \WP_Error(
                    'invalid_url',
                    __('Enter a valid http(s) URL.', 'pitea-customisation')
                );
            }
        }

        $text = static fn (string $key): string => isset($data[$key])
            ? sanitize_text_field(wp_unslash((string) $data[$key]))
            : '';

        update_option(self::OPTION_URL, self::sanitizeUrl((string) wp_unslash($data[self::OPTION_URL] ?? '')));
        update_option(self::OPTION_READ_MORE_URL, self::sanitizeUrl((string) wp_unslash($data[self::OPTION_READ_MORE_URL] ?? '')));
        update_option(self::OPTION_LABEL, $text(self::OPTION_LABEL));
        update_option(self::OPTION_HEADING, $text(self::OPTION_HEADING));
        update_option(self::OPTION_READ_MORE_TEXT, $text(self::OPTION_READ_MORE_TEXT));
        update_option(
            self::OPTION_TEXT,
            isset($data[self::OPTION_TEXT]) ? sanitize_textarea_field(wp_unslash((string) $data[self::OPTION_TEXT])) : ''
        );
        update_option(self::OPTION_SHORTCUT, !empty($data[self::OPTION_SHORTCUT]));

        return true;
    }

    /**
     * Empty string, or an absolute http(s) URL. Anything else becomes an empty string.
     */
    public static function sanitizeUrl(string $value): string
    {
        $value = trim($value);
        if (!self::isHttpUrl($value)) {
            return '';
        }

        return (string) esc_url_raw($value, ['http', 'https']);
    }

    public static function getDefaultUrl(): string
    {
        $url = self::sanitizeUrl((string) get_option(self::OPTION_URL, ''));

        return $url !== '' ? $url : self::FALLBACK_URL;
    }

    public static function getLabel(): string
    {
        return self::optionOrDefault(self::OPTION_LABEL, self::defaultLabel());
    }

    public static function getHeading(): string
    {
        return self::optionOrDefault(self::OPTION_HEADING, self::defaultHeading());
    }

    /**
     * Explanation text with {site} replaced by the destination host.
     */
    public static function getText(string $destinationUrl): string
    {
        $text = (string) get_option(self::OPTION_TEXT, '');
        $text = trim($text) !== '' ? $text : self::defaultText();
        $host = (string) wp_parse_url($destinationUrl, PHP_URL_HOST);
        $host = preg_replace('/^www\./i', '', $host) ?? $host;

        return str_replace('{site}', $host, $text);
    }

    public static function getReadMoreUrl(): string
    {
        return self::sanitizeUrl((string) get_option(self::OPTION_READ_MORE_URL, ''));
    }

    public static function getReadMoreText(): string
    {
        return self::optionOrDefault(self::OPTION_READ_MORE_TEXT, self::defaultReadMoreText());
    }

    public static function isShortcutEnabled(): bool
    {
        return (bool) get_option(self::OPTION_SHORTCUT, true);
    }

    private static function defaultLabel(): string
    {
        return __('Leave the page quickly', 'pitea-customisation');
    }

    private static function defaultHeading(): string
    {
        return __('About the quick exit button', 'pitea-customisation');
    }

    private static function defaultText(): string
    {
        return __('Click the button if you need to leave the page quickly. You will end up on {site} instead.', 'pitea-customisation');
    }

    private static function defaultReadMoreText(): string
    {
        return __('Read more about the quick exit button', 'pitea-customisation');
    }

    private static function optionOrDefault(string $option, string $default): string
    {
        $value = sanitize_text_field((string) get_option($option, ''));

        return $value !== '' ? $value : $default;
    }

    private static function isHttpUrl(string $value): bool
    {
        if (!preg_match('#^https?://#i', $value)) {
            return false;
        }

        $parts = wp_parse_url($value);

        return is_array($parts) && !empty($parts['host']);
    }

    private function renderInput(string $option, string $type, string $placeholder, string $description): void
    {
        $value = (string) get_option($option, '');
        ?>
        <div class="pitea-settings__field">
            <input
                type="<?php echo esc_attr($type); ?>"
                name="<?php echo esc_attr($option); ?>"
                value="<?php echo esc_attr($value); ?>"
                class="pitea-settings__input"
                placeholder="<?php echo esc_attr($placeholder); ?>"
            />
            <p class="pitea-settings__field-desc"><?php echo esc_html($description); ?></p>
        </div>
        <?php
    }
}
