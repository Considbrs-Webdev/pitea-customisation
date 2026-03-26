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
