<?php

namespace PiteaCustomisation\Customisations;

class Config
{
    /**
     * Initialize configuration and setup
     */
    public function __construct()
    {
        // Load plugin textdomain for translations
        add_action('init', [$this, 'loadTextdomain']);

        // Rename the default template to "Page (default template)"
        add_action('default_page_template_title', [$this, 'renameDefaultTemplate'], 11, 0);

        // Specify Font Awesome archive link icon for service information
        add_filter('Modularity/ServiceInformation/Module/ArchiveLink/Icon', [$this, 'setServiceInfoArchiveLinkIcon']);

        // Contact banner CTA icon customization
        add_filter('Modularity/Module/ContactBanner/CtaIcon', [$this, 'setContactBannerCtaIcon']);

        // Noticeboard archive icon customization
        add_filter('Modularity/Module/Noticeboard/ArchiveIcon', [$this, 'setNoticeboardArchiveIcon']);

        // Remove font-face declarations from Kirki inline styles on the frontend
        add_filter('kirki_inline_styles', [$this, 'maybeRemoveFontFaces']);

        // Change the default username validation to allow dots and uppercase letters
        add_filter('wpmu_validate_user_signup', [$this, 'validateUserSignupUsername']);

        // Ensure that the original username (with dots and uppercase) is preserved during sanitization
        add_filter('sanitize_user', [$this, 'preserveUsernameCase'], 10, 3);

        // Better Post UI renders its own page template selector in pageparentdiv.
        // Remove Gutenberg's duplicate classic-theme template control from the block editor.
        add_filter('block_editor_settings_all', [$this, 'maybeRemoveBlockEditorTemplateSelector'], 10, 2);
    }

    public function maybeRemoveBlockEditorTemplateSelector(array $settings, $blockEditorContext): array
    {
        if (!$this->isBetterPostUiActive()) {
            return $settings;
        }

        if (current_theme_supports('block-templates')) {
            return $settings;
        }

        $post = $blockEditorContext->post ?? null;
        if (!$post instanceof \WP_Post) {
            return $settings;
        }

        if (empty(get_page_templates($post, $post->post_type))) {
            return $settings;
        }

        if ((int) get_option('page_for_posts') === (int) $post->ID) {
            return $settings;
        }

        $settings['availableTemplates'] = [];

        return $settings;
    }

    /**
     * Check if Better Post UI is active on this site or network.
     *
     * @return bool
     */
    private function isBetterPostUiActive(): bool
    {
        $pluginFiles = [
            'better-post-ui/better-post-ui.php',
            'better-post-UI/better-post-ui.php',
        ];

        $activePlugins = (array) get_option('active_plugins', []);
        $networkActivePlugins = is_multisite()
            ? array_keys((array) get_site_option('active_sitewide_plugins', []))
            : [];

        foreach ($pluginFiles as $pluginFile) {
            if (
                in_array($pluginFile, $activePlugins, true) ||
                in_array($pluginFile, $networkActivePlugins, true)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Load plugin textdomain for translations
     *
     * @return void
     */
    public function loadTextdomain(): void
    {
        load_plugin_textdomain(
            'pitea-customisation',
            false,
            dirname(dirname(dirname(__DIR__))) . '/languages'
        );
    }

    public function renameDefaultTemplate(): string
    {
        return __('Content page', 'pitea-customisation');
    }

    public function setServiceInfoArchiveLinkIcon(): string
    {
        return 'fa-solid fa-arrow-right';
    }

    /**
     * Return the icon class for Contact Banner CTA
     *
     * @return string
     */
    public function setContactBannerCtaIcon(): string
    {
        return 'fa-solid fa-arrow-right';
    }

    /**
     * Return the icon class for Noticeboard archive
     *
     * @return string
     */
    public function setNoticeboardArchiveIcon(): string
    {
        return 'fa-solid fa-arrow-right';
    }

    public function maybeRemoveFontFaces($styles)
    {
        if (is_admin()) {
            return $styles;
        }

        // Remove font-face declarations from the styles
        $styles = preg_replace('/@font-face\s*{[^}]*}/', '', $styles);

        return $styles;
    }

    /**
     * Preserve the original username casing and dots during sanitization.
     *
     * @param string $username
     * @param string $raw_username
     * @param bool   $strict
     * @return string
     */
    public function preserveUsernameCase(string $username, string $raw_username, bool $strict): string
    {
        // Allow uppercase + lowercase + numbers + dots
        if (preg_match('/^[A-Za-z0-9\.]+$/', $raw_username)) {
            return $raw_username;
        }

        return $username;
    }

    /**
     * Validate user signup username to allow dots and uppercase letters.
     * Removes the default "lowercase only" error and enforces a custom pattern.
     *
     * @param array $result
     * @return array
     */
    public function validateUserSignupUsername(array $result): array
    {
        $username = $result['user_name'];
        $errors   = $result['errors'];

        $is_valid = preg_match('/^[A-Za-z0-9\.]+$/', $username);

        // Always remove default "lowercase only" errors
        if (isset($errors->errors['user_name'])) {
            foreach ($errors->errors['user_name'] as $key => $message) {
                if (
                    str_contains($message, 'gemener') ||
                    str_contains($message, 'lowercase')
                ) {
                    unset($errors->errors['user_name'][$key]);
                }
            }

            if (empty($errors->errors['user_name'])) {
                unset($errors->errors['user_name']);
            }
        }

        // If NOT valid → add your custom error
        if (!$is_valid) {
            $errors->add(
                'user_name',
                __('Username may only contain letters (A–Z), numbers (0–9) and dots (.)', 'pitea-customisation')
            );
        }

        $result['errors'] = $errors;

        return $result;
    }

    /**
     * Short-circuit the server-side HTTP request Kirki makes to download Google
     * Fonts CSS on the frontend. Returning a mock 200 response means the
     * Downloader gets an empty body and outputs nothing, while the fonts array
     * that Municipio relies on for CSS variable generation is left untouched.
     *
     * @param false|array|\WP_Error $preempt
     * @param array                 $args
     * @param string                $url
     * @return false|array
     */
    public function blockKirkiFontDownloads(mixed $preempt, array $args, string $url): mixed
    {
        if (is_admin()) {
            return $preempt;
        }

        if (str_contains($url, 'fonts.googleapis.com')) {
            return [
                'headers'       => [],
                'body'          => '',
                'response'      => ['code' => 200, 'message' => 'OK'],
                'cookies'       => [],
                'http_response' => null,
            ];
        }

        return $preempt;
    }
}
