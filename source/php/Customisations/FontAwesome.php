<?php

namespace PiteaCustomisation\Customisations;

use PiteaCustomisation\Helpers\CacheBust;
use PiteaCustomisation\Helpers\Utils;

use ComponentLibrary\Component\BaseController as ComponentController;

class FontAwesome
{
    /**
     * Initialize the field replacer
     */
    public function __construct()
    {
        add_action('acf/include_field_types', [$this, 'registerAcfFieldType']);
        add_filter('acf/prepare_field/type=icon', [$this, 'convertToFontAwesomeField']);
        add_filter('ComponentLibrary/Component/Icon/Attribute', [$this, 'modifyIconAttributes'], 10, 1);
        add_filter('ComponentLibrary/Component/Icon/Class', [$this, 'filterIconClasses'], 10, 1);
        add_filter('ComponentLibrary/Component/Icon/Data', [$this, 'modifyIconData'], 10, 1);

        // TinyMCE FontAwesome icon picker
        add_filter('mce_external_plugins', [$this, 'registerTinyMcePlugin'], 50);
        add_filter('mce_buttons', [$this, 'addTinyMceButton'], 50);
        add_action('admin_enqueue_scripts', [$this, 'enqueueTinyMceStyles'], 50);
        add_action('admin_enqueue_scripts', [$this, 'enqueueQuicktagsScript'], 50);
        add_filter('mce_css', [$this, 'addFontAwesomeToTinyMce']);

        // Gutenberg FontAwesome icon picker
        add_action('enqueue_block_editor_assets', [$this, 'enqueueGutenbergAssets']);
    }

    /**
     * Register the custom ACF field type
     *
     * @return void
     */
    public function registerAcfFieldType(): void
    {
        require_once dirname(__DIR__) . '/AcfFields/FontAwesomeIconField.php';
        acf_register_field_type('PiteaCustomisation\AcfFields\FontAwesomeIconField');
    }

    /**
     * Modify icon attributes for FontAwesome
     *
     * @param string|array $attributes The icon attributes
     * @return string|array Modified attributes
     */
    public function modifyIconAttributes(string|array $attributes): string|array
    {
        $wasString = is_string($attributes);

        if ($wasString) {
            $attributes = Utils::parseAttributes($attributes);
        }

        if (!Utils::containsInAttributes($attributes, 'fa-')) {
            return $wasString ? ComponentController::buildAttributes($attributes) : $attributes;
        }

        $attributes['aria-hidden'] = 'true';

        unset($attributes['data-material-symbol']);
        unset($attributes['role']);
        unset($attributes['aria-label']);
        unset($attributes['data-nosnippet']);

        return $wasString ? ComponentController::buildAttributes($attributes) : $attributes;
    }

    /**
     * Modify icon component data for FontAwesome
     *
     * @param array $data The icon component data
     * @return array Modified data
     */
    public function modifyIconData(array $data): array
    {
        if (!isset($data['icon']) || !is_string($data['icon']) || strpos($data['icon'], 'fa-') !== 0) {
            return $data;
        }

        $data['componentElement'] = 'i';

        $icon = explode(' ', $data['icon']);
        $data['classList'] = array_merge($data['classList'], $icon);

        $data['icon'] = str_replace(' ', '-', $data['icon']);

        // Remove data-material-symbol attribute for FontAwesome icons
        if (isset($data['attribute']['data-material-symbol'])) {
            unset($data['attribute']['data-material-symbol']);
        }

        return $data;
    }

    /**
     * Filter icon classes to remove Material Symbols when using FontAwesome
     *
     * @param array $classes The icon classes
     * @return array Filtered classes
     */
    public function filterIconClasses(array $classes): array
    {
        $hasFaClass = false;
        foreach ($classes as $class) {
            if (strpos($class, 'fa-') === 0) {
                $hasFaClass = true;
                break;
            }
        }

        if (!$hasFaClass) {
            return $classes;
        }

        $classes = array_values(array_filter(
            $classes,
            function ($class) {
                // Remove material-symbols classes
                if (strpos($class, 'material-symbols') === 0) {
                    return false;
                }
                // Remove c-icon--material classes
                if (strpos($class, 'c-icon--material') === 0) {
                    return false;
                }
                return true;
            }
        ));

        // Replace c-icon--size-* with fa-*
        $classes = array_map(function ($class) {
            if (preg_match('/^c-icon--size-(.+)$/', $class, $matches)) {
                return 'fa-icon-size-' . $matches[1];
            }
            return $class;
        }, $classes);

        return $classes;
    }

    /**
     * Convert icon field to FontAwesome field type
     *
     * @param array $field The ACF field array
     * @return array Modified field array
     */
    public function convertToFontAwesomeField(array $field): array
    {
        $jsonPath = dirname(__DIR__, 3) . '/data/fontawesome-icons.json';

        // If no icons file found, don't modify the field
        if (!file_exists($jsonPath)) {
            return $field;
        }

        $field['type']       = 'fontawesome_icon';
        $field['allow_null'] = 1;

        return $field;
    }

    /**
     * Register the TinyMCE FontAwesome plugin
     *
     * @param array $plugins Array of TinyMCE plugins
     * @return array Modified plugins array
     */
    public function registerTinyMcePlugin($plugins): array
    {
        $plugins['fontawesome_icons'] = CacheBust::getFile('source/js/editor-plugins/tinymce-fontawesome-plugin.js');
        return $plugins;
    }

    /**
     * Add the FontAwesome button to TinyMCE toolbar
     *
     * @param array $buttons Array of TinyMCE buttons
     * @return array Modified buttons array
     */
    public function addTinyMceButton(array $buttons): array
    {
        $buttons[] = 'fontawesome_icons';
        return $buttons;
    }

    /**
     * Enqueue TinyMCE FontAwesome styles in admin
     *
     * @return void
     */
    public function enqueueTinyMceStyles(): void
    {
        // TinyMCE plugin styles
        wp_enqueue_style(
            'tinymce-fontawesome-plugin',
            CacheBust::getFile('source/css/tinymce-fontawesome-plugin.css'),
            [],
            PITEA_CUSTOMISATION_VERSION
        );
    }

    /**
     * Add FontAwesome styles to TinyMCE editor iframe
     *
     * @param string $mce_css Comma-separated list of stylesheets
     * @return string Modified stylesheet list
     */
    public function addFontAwesomeToTinyMce(string $mce_css): string
    {
        $fontAwesomeUrl = CacheBust::getFile('source/sass/font-awesome.scss');

        if (!$fontAwesomeUrl) {
            return $mce_css;
        }

        if (!empty($mce_css)) {
            $mce_css .= ',';
        }

        $mce_css .= $fontAwesomeUrl;

        return $mce_css;
    }

    /**
     * Enqueue Quicktags FontAwesome script in admin
     *
     * @return void
     */
    public function enqueueQuicktagsScript(): void
    {
        wp_enqueue_script(
            'quicktags-fontawesome-plugin',
            CacheBust::getFile('source/js/editor-plugins/quicktags-fontawesome-plugin.js'),
            ['quicktags'],
            PITEA_CUSTOMISATION_VERSION,
            true
        );
    }

    /**
     * Enqueue Gutenberg block editor assets
     *
     * @return void
     */
    public function enqueueGutenbergAssets(): void
    {
        $assetFile = dirname(__DIR__, 3) . '/dist/gutenberg/index.asset.php';

        if (!file_exists($assetFile)) {
            return;
        }

        $asset = include $assetFile;

        wp_enqueue_script(
            'pitea-gutenberg-fontawesome',
            plugin_dir_url(dirname(__DIR__, 2)) . 'dist/gutenberg/index.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        wp_enqueue_style(
            'pitea-gutenberg-fontawesome-editor',
            plugin_dir_url(dirname(__DIR__, 2)) . 'dist/gutenberg/style-index.css',
            ['wp-components'],
            $asset['version']
        );
    }
}
