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

        // Specify Font Awesome archive link icon for service information
        add_filter('Modularity/ServiceInformation/Module/ArchiveLink/Icon', [$this, 'setServiceInfoArchiveLinkIcon']);

        // Contact banner CTA icon customization
        add_filter('Modularity/Module/ContactBanner/CtaIcon', [$this, 'setContactBannerCtaIcon']);

        // Noticeboard archive icon customization
        add_filter('Modularity/Module/Noticeboard/ArchiveIcon', [$this, 'setNoticeboardArchiveIcon']);

        // Remove font-face declarations from Kirki inline styles on the frontend
        add_filter('kirki_inline_styles', [$this, 'maybeRemoveFontFaces']);

        // Remove page template from Gutenberg so that we don't get the setting twice
        // Otherwise they will have to change both values
        add_action('add_meta_boxes', [$this, 'removePageTemplateMetaBox'], 100);

        // Change the default username validation to allow dots and uppercase letters
        add_filter('wpmu_validate_user_signup', [$this, 'validateUserSignupUsername']);
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
     * Remove the page template meta box in Gutenberg (prevents duplicate setting)
     *
     * @return void
     */
    public function removePageTemplateMetaBox(): void
    {
        remove_meta_box('pageparentdiv', 'page', 'side');
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
